<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceLogEditTest extends TestCase
{
    use RefreshDatabase;

    private function pushPunch(string $line): void
    {
        $this->call('POST', '/iclock/cdata?SN=ABC123&table=ATTLOG', [], [], [], [
            'CONTENT_TYPE' => 'text/plain',
        ], $line)->assertOk();
    }

    public function test_punch_can_be_edited_and_device_resend_does_not_undo_it(): void
    {
        $line = "1001\t2026-11-09 09:15:00\t0\t1\t0\t0\t0";
        $this->pushPunch($line);
        $log = AttendanceLog::query()->sole();

        $this->get(route('punches.edit', $log))->assertOk()->assertSee('2026-11-09 09:15:00 AM');

        $this->put(route('punches.update', $log), ['punched_at' => '2026-11-09T08:55:00', 'status' => 1])
            ->assertRedirect(route('punches', ['date' => '2026-11-09']));

        $log->refresh();
        $this->assertSame('2026-11-09 08:55:00', $log->punched_at->format('Y-m-d H:i:s'));
        $this->assertSame(1, $log->status);
        $this->assertSame('2026-11-09 09:15:00', $log->original_punched_at->format('Y-m-d H:i:s'));
        $this->assertTrue($log->isEdited());

        // The device re-sends its unchanged record after reconnecting.
        $this->pushPunch($line);

        $this->assertSame(1, AttendanceLog::query()->count());
        $this->assertSame('2026-11-09 08:55:00', $log->fresh()->punched_at->format('Y-m-d H:i:s'));

        $this->get('/')->assertSee('Edited')->assertSee('08:55:00 AM');
    }

    public function test_edited_punch_can_be_restored(): void
    {
        $this->pushPunch("1001\t2026-11-09 09:15:00\t0\t1\t0\t0\t0");
        $log = AttendanceLog::query()->sole();

        $this->put(route('punches.update', $log), ['punched_at' => '2026-11-09T08:55:00', 'status' => 1]);
        $this->post(route('punches.restore', $log))->assertRedirect();

        $log->refresh();
        $this->assertSame('2026-11-09 09:15:00', $log->punched_at->format('Y-m-d H:i:s'));
        $this->assertSame(0, $log->status);
        $this->assertFalse($log->isEdited());
    }

    public function test_invalid_edit_is_rejected(): void
    {
        $this->pushPunch("1001\t2026-11-09 09:15:00\t0\t1\t0\t0\t0");
        $log = AttendanceLog::query()->sole();

        $this->put(route('punches.update', $log), ['punched_at' => 'not a date', 'status' => 9])
            ->assertSessionHasErrors(['punched_at', 'status']);
    }
}
