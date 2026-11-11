<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Device;
use App\Models\DeviceCommand;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ZktecoRequestTest extends TestCase
{
    use RefreshDatabase;

    private function pushAttlog(string $body): TestResponse
    {
        return $this->call('POST', '/iclock/cdata?SN=ABC123&table=ATTLOG', [], [], [], [
            'CONTENT_TYPE' => 'text/plain',
            'HTTP_USER_AGENT' => 'iClock Proxy/1.09',
        ], $body);
    }

    public function test_punch_is_stored_and_shown_with_student_details(): void
    {
        Student::factory()->create(['name' => 'Rahim Uddin', 'roll' => '12', 'sid' => '1001']);

        $this->pushAttlog("1001\t2026-11-09 09:15:00\t0\t1\t0\t0\t0\n2002\t2026-11-09 09:16:00\t1\t15\t0\t0\t0")
            ->assertOk()
            ->assertSee('OK: 2', false);

        $this->assertSame(2, AttendanceLog::query()->count());

        $this->get('/')
            ->assertOk()
            ->assertSee('Punch log')
            ->assertSee('Rahim Uddin')
            ->assertSee('1001')
            ->assertSee('2026-11-09 09:15:00 AM')
            ->assertSee('Check In')
            ->assertSee('Fingerprint')
            ->assertSee('Not registered')
            ->assertSee('2002')
            ->assertDontSee('/iclock/cdata');
    }

    public function test_duplicate_punches_are_ignored(): void
    {
        $line = "1001\t2026-11-09 09:15:00\t0\t1\t0\t0\t0";

        $this->pushAttlog($line)->assertOk();
        $this->pushAttlog($line)->assertOk();

        $this->assertSame(1, AttendanceLog::query()->count());
    }

    public function test_device_handshake_returns_plain_text_options_and_registers_device(): void
    {
        $response = $this->get('/iclock/cdata?SN=ABC123&options=all');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $response->assertSee('GET OPTION FROM: ABC123', false);
        $response->assertSee('TimeZone=6', false);

        $this->assertDatabaseHas('devices', ['sn' => 'ABC123']);
    }

    public function test_browser_get_on_root_shows_empty_punch_log(): void
    {
        $this->get('/')->assertOk()->assertSee('No punches yet.');

        $this->assertSame(0, Device::query()->count());
    }

    public function test_name_sync_is_delivered_on_poll_and_confirmed_by_device(): void
    {
        Device::query()->create(['sn' => 'ABC123', 'last_seen_at' => now()]);
        $student = Student::factory()->create(['name' => 'Karim Hasan', 'sid' => '1001']);

        $this->post(route('students.sync', $student))
            ->assertRedirect(route('students.index'))
            ->assertSessionHas('status');

        $command = DeviceCommand::query()->sole();

        $this->get('/iclock/getrequest?SN=ABC123')
            ->assertOk()
            ->assertSee("C:{$command->id}:DATA UPDATE USERINFO PIN=1001\tName=Karim Hasan", false);

        $this->assertSame('sent', $command->fresh()->status);

        // Already delivered, so the next poll is idle.
        $this->get('/iclock/getrequest?SN=ABC123')->assertSee('OK', false);

        $this->call('POST', '/iclock/devicecmd?SN=ABC123', [], [], [], [
            'CONTENT_TYPE' => 'text/plain',
        ], "ID={$command->id}&Return=0&CMD=DATA")->assertOk();

        $this->assertSame('done', $command->fresh()->status);

        $this->get(route('students.index'))->assertSee('Synced');
    }

    public function test_sync_without_connected_device_reports_error(): void
    {
        $student = Student::factory()->create();

        $this->post(route('students.sync', $student))->assertSessionHas('error');

        $this->assertSame(0, DeviceCommand::query()->count());
    }
}
