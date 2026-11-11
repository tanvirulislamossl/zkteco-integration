<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Models\Device;
use App\Models\DeviceCommand;
use App\Models\ZktecoRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ZktecoRequestController extends Controller
{
    /**
     * Browser visits show the student punch log. A ZKTeco push to "/" is handled as device traffic.
     */
    public function root(Request $request): View|Response
    {
        if ($this->isDeviceRequest($request)) {
            return $this->capture($request);
        }

        $date = $request->date('date');

        $logs = AttendanceLog::query()
            ->with('student')
            ->when($date, fn ($query) => $query->whereDate('punched_at', $date))
            ->orderByDesc('punched_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return view('zkteco.punches', [
            'logs' => $logs,
            'date' => $date?->format('Y-m-d'),
            'devices' => Device::query()->orderBy('sn')->get(),
        ]);
    }

    /**
     * Handle an ADMS / iClock request and reply so the device keeps pushing.
     */
    public function capture(Request $request): Response
    {
        $sn = $this->serialNumber($request);
        $path = strtolower($request->path());

        if ($sn !== '') {
            Device::query()->updateOrCreate(['sn' => $sn], [
                'ip' => $request->ip(),
                'last_seen_at' => now(),
            ]);
        }

        if (! $this->isIdlePoll($request)) {
            $this->storeRaw($request);
        }

        if ($request->isMethod('POST') && str_ends_with($path, 'cdata')
            && strtoupper((string) $request->query('table')) === 'ATTLOG') {
            $count = $this->storePunches($sn, $request->getContent());

            return $this->plain('OK: '.$count."\n");
        }

        if (str_ends_with($path, 'devicecmd')) {
            $this->storeCommandResults($request->getContent());

            return $this->plain("OK\n");
        }

        if ($request->isMethod('GET') && str_ends_with($path, 'getrequest') && $sn !== '') {
            return $this->plain($this->pendingCommands($sn));
        }

        return $this->deviceResponse($request);
    }

    private function serialNumber(Request $request): string
    {
        return trim((string) ($request->query('SN') ?? $request->query('sn') ?? ''));
    }

    private function isDeviceRequest(Request $request): bool
    {
        if ($request->isMethod('HEAD')) {
            return false;
        }

        if (! $request->isMethod('GET')) {
            return true;
        }

        if ($request->query->has('SN') || $request->query->has('sn') || $request->query->has('options')) {
            return true;
        }

        $agent = strtolower((string) $request->userAgent());

        return str_contains($agent, 'iclock')
            || str_contains($agent, 'zkteco');
    }

    /**
     * The device polls getrequest every few seconds; keeping those would flush out useful raw records.
     */
    private function isIdlePoll(Request $request): bool
    {
        return $request->isMethod('GET') && str_ends_with(strtolower($request->path()), 'getrequest');
    }

    /**
     * Parse ATTLOG lines: PIN \t YYYY-MM-DD HH:MM:SS \t Status \t Verify \t ...
     */
    private function storePunches(string $sn, string $body): int
    {
        $now = now();
        $rows = [];

        foreach (preg_split('/\r\n|\r|\n/', $body) as $line) {
            $fields = explode("\t", trim($line));

            if (count($fields) < 2) {
                continue;
            }

            $pin = trim($fields[0]);
            $time = trim($fields[1]);

            if ($pin === '' || ! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $time)) {
                continue;
            }

            $status = isset($fields[2]) && is_numeric($fields[2]) ? (int) $fields[2] : null;

            $rows[] = [
                'device_sn' => $sn !== '' ? $sn : 'unknown',
                'pin' => $pin,
                'punched_at' => $time,
                'original_punched_at' => $time,
                'status' => $status,
                'original_status' => $status,
                'verify_type' => isset($fields[3]) && is_numeric($fields[3]) ? (int) $fields[3] : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            // Devices re-send old punches after reconnecting; the unique index on the original
            // device time drops duplicates, so punches edited here are not overwritten.
            AttendanceLog::query()->insertOrIgnore($rows);
        }

        return count($rows);
    }

    /**
     * Hand queued commands (e.g. user name updates) to the device on its next poll.
     */
    private function pendingCommands(string $sn): string
    {
        $commands = DeviceCommand::query()
            ->where('device_sn', $sn)
            ->where('status', 'pending')
            ->orderBy('id')
            ->limit(20)
            ->get();

        if ($commands->isEmpty()) {
            return "OK\n";
        }

        DeviceCommand::query()
            ->whereKey($commands->modelKeys())
            ->update(['status' => 'sent', 'sent_at' => now()]);

        return $commands
            ->map(fn (DeviceCommand $command) => 'C:'.$command->id.':'.$command->command)
            ->implode("\n")."\n";
    }

    /**
     * Device replies look like "ID=12&Return=0&CMD=DATA", one per line. Return=0 means success.
     */
    private function storeCommandResults(string $body): void
    {
        foreach (preg_split('/\r\n|\r|\n/', $body) as $line) {
            parse_str(trim($line), $result);

            if (! isset($result['ID']) || ! ctype_digit((string) $result['ID'])) {
                continue;
            }

            $return = (string) ($result['Return'] ?? '');

            DeviceCommand::query()->whereKey((int) $result['ID'])->update([
                'status' => $return === '0' ? 'done' : 'failed',
                'return_code' => $return,
                'completed_at' => now(),
            ]);
        }
    }

    private function storeRaw(Request $request): void
    {
        $raw = $request->getContent();
        $originalLength = strlen($raw);
        $max = 100_000;
        $slice = $originalLength > $max ? substr($raw, 0, $max) : $raw;
        $truncated = $originalLength > $max;

        if ($slice !== '' && ! mb_check_encoding($slice, 'UTF-8')) {
            $body = '[binary '.$originalLength.' bytes] '.base64_encode(substr($slice, 0, 2000));
        } else {
            $body = $slice.($truncated ? "\n\n[truncated, {$originalLength} bytes total]" : '');
        }

        $headers = [];

        foreach ($request->headers->all() as $name => $values) {
            $headers[$name] = implode(', ', $values);
        }

        ZktecoRequest::query()->create([
            'method' => $request->method(),
            'path' => '/'.ltrim($request->path(), '/'),
            'query' => $request->query(),
            'headers' => $headers,
            'body' => $body,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $cutoffId = ZktecoRequest::query()
            ->orderByDesc('id')
            ->offset(200)
            ->limit(1)
            ->value('id');

        if ($cutoffId !== null) {
            ZktecoRequest::query()->where('id', '<=', $cutoffId)->delete();
        }
    }

    private function deviceResponse(Request $request): Response
    {
        $sn = $this->serialNumber($request);
        $options = strtolower((string) $request->query('options', ''));
        $path = strtolower($request->path());

        $isHandshake = $request->isMethod('GET') && $sn !== '' && (
            $options === 'all'
            || str_contains($path, 'registry')
            || (str_ends_with($path, 'cdata') && $options !== '')
        );

        if ($isHandshake) {
            return $this->plain(implode("\r\n", [
                'GET OPTION FROM: '.$sn,
                'Stamp=0',
                'OpStamp=0',
                'PhotoStamp=999999999',
                'ErrorDelay=60',
                'Delay=10',
                'TransTimes=00:00;14:05',
                'TransInterval=1',
                'TransFlag=111111111111',
                'Realtime=1',
                'Encrypt=0',
                'TimeZone=6',
                'ServerVer=2.4.1',
                '',
            ]));
        }

        return $this->plain("OK\n");
    }

    private function plain(string $content): Response
    {
        return response($content, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
