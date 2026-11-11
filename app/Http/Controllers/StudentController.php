<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\DeviceCommand;
use App\Models\Student;
use App\Services\StudentImporter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    public function index(): View
    {
        return view('students.index', [
            'students' => Student::query()->with('latestDeviceCommand')->orderBy('roll')->orderBy('name')->get(),
            'devices' => Device::query()->orderBy('sn')->get(),
            'editing' => null,
        ]);
    }

    public function edit(Student $student): View
    {
        return view('students.index', [
            'students' => Student::query()->with('latestDeviceCommand')->orderBy('roll')->orderBy('name')->get(),
            'devices' => Device::query()->orderBy('sn')->get(),
            'editing' => $student,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $student = Student::query()->create($this->validated($request));

        return $this->afterSave($request, $student, 'Student added.');
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        $student->update($this->validated($request, $student));

        return $this->afterSave($request, $student, 'Student updated.');
    }

    public function destroy(Student $student): RedirectResponse
    {
        $student->delete();

        return redirect()->route('students.index')->with('status', 'Student removed. Their user stays on the device until you delete it there.');
    }

    public function import(Request $request, StudentImporter $importer): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ], [
            'file.mimes' => 'Upload a CSV file. In Excel use File → Save As → "CSV UTF-8".',
        ]);

        $result = $importer->import($request->file('file')->getRealPath(), $request->boolean('update_existing'));

        $summary = count($result['created']).' added, '.count($result['updated']).' updated';

        if ($result['skipped'] > 0) {
            $summary .= ', '.$result['skipped'].' skipped (SID already exists)';
        }

        $summary = 'Import finished: '.$summary.'.';

        $imported = collect([...$result['created'], ...$result['updated']]);

        if ($request->boolean('sync') && $imported->isNotEmpty()) {
            [, $syncMessage] = $this->syncMessage($this->queueSync($imported), $imported->count());
            $summary .= ' '.$syncMessage;
        }

        return redirect()->route('students.index')
            ->with('status', $summary)
            ->with('import_errors', $result['errors']);
    }

    public function importTemplate(StudentImporter $importer): Response
    {
        return response($importer->template(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="students-template.csv"',
        ]);
    }

    public function sync(Student $student): RedirectResponse
    {
        $queued = $this->queueSync(collect([$student]));

        return redirect()->route('students.index')->with(...$this->syncMessage($queued, 1));
    }

    public function syncAll(): RedirectResponse
    {
        $students = Student::query()->get();
        $queued = $this->queueSync($students);

        return redirect()->route('students.index')->with(...$this->syncMessage($queued, $students->count()));
    }

    /**
     * @return array{name: string, roll: ?string, sid: string}
     */
    private function validated(Request $request, ?Student $student = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'roll' => ['nullable', 'string', 'max:50'],
            'sid' => [
                'required',
                'regex:/^[A-Za-z0-9]+$/',
                'max:20',
                Rule::unique('students', 'sid')->ignore($student),
            ],
        ], [
            'sid.regex' => 'SID may only contain letters and numbers (it must match the User ID on the device).',
        ], [
            'sid' => 'SID',
        ]);
    }

    private function afterSave(Request $request, Student $student, string $message): RedirectResponse
    {
        if ($request->boolean('sync')) {
            $queued = $this->queueSync(collect([$student]));
            [$key, $syncMessage] = $this->syncMessage($queued, 1);

            return redirect()->route('students.index')->with($key, $message.' '.$syncMessage);
        }

        return redirect()->route('students.index')->with('status', $message);
    }

    /**
     * Queue a user-info update for every device that has connected. Devices pick it up on their next poll.
     *
     * @param  Collection<int, Student>  $students
     */
    private function queueSync(Collection $students): int
    {
        $deviceSns = Device::query()->pluck('sn');

        if ($deviceSns->isEmpty() || $students->isEmpty()) {
            return 0;
        }

        $queued = 0;

        foreach ($students as $student) {
            foreach ($deviceSns as $sn) {
                DeviceCommand::query()
                    ->where('student_id', $student->id)
                    ->where('device_sn', $sn)
                    ->where('status', 'pending')
                    ->delete();

                DeviceCommand::query()->create([
                    'device_sn' => $sn,
                    'student_id' => $student->id,
                    'command' => "DATA UPDATE USERINFO PIN={$student->sid}\tName={$student->deviceName()}",
                    'status' => 'pending',
                ]);

                $queued++;
            }
        }

        return $queued;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function syncMessage(int $queued, int $studentCount): array
    {
        if ($studentCount === 0) {
            return ['error', 'There are no students to sync.'];
        }

        if ($queued === 0) {
            return ['error', 'No device has connected yet, so nothing could be sent. Turn on the device and wait until it shows as online.'];
        }

        return ['status', 'Name sync queued ('.$queued.' '.str('command')->plural($queued).'). The device applies it within a few seconds.'];
    }
}
