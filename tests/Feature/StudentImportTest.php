<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceCommand;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class StudentImportTest extends TestCase
{
    use RefreshDatabase;

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('students.csv', $content);
    }

    public function test_csv_with_header_creates_and_updates_students(): void
    {
        Student::factory()->create(['name' => 'Old Name', 'roll' => '9', 'sid' => '1002']);

        $this->post(route('students.import'), [
            'file' => $this->csv("\xEF\xBB\xBFStudent Name,Roll No,SID\nRahim Uddin,1,1001\nKarim Hasan,2,1002\n\n"),
            'update_existing' => '1',
        ])->assertRedirect(route('students.index'))
            ->assertSessionHas('status', 'Import finished: 1 added, 1 updated.');

        $this->assertDatabaseHas('students', ['sid' => '1001', 'name' => 'Rahim Uddin', 'roll' => '1']);
        $this->assertDatabaseHas('students', ['sid' => '1002', 'name' => 'Karim Hasan', 'roll' => '2']);
    }

    public function test_existing_students_are_skipped_when_update_is_off(): void
    {
        Student::factory()->create(['name' => 'Old Name', 'sid' => '1002']);

        $this->post(route('students.import'), [
            'file' => $this->csv("name,roll,sid\nKarim Hasan,2,1002\n"),
        ])->assertSessionHas('status', 'Import finished: 0 added, 0 updated, 1 skipped (SID already exists).');

        $this->assertDatabaseHas('students', ['sid' => '1002', 'name' => 'Old Name']);
    }

    public function test_invalid_and_duplicate_rows_are_reported(): void
    {
        $this->post(route('students.import'), [
            'file' => $this->csv("sid;name;roll\n1001;Rahim;1\n1001;Copy;2\n10 03;Bad;3\n1004;;4\n"),
        ])->assertSessionHas('import_errors', [
            'Row 3: SID 1001 already appears on row 2.',
            'Row 4: SID may only contain letters and numbers.',
            'Row 5: The name field is required.',
        ]);

        $this->assertSame(['1001'], Student::query()->pluck('sid')->all());
    }

    public function test_file_without_header_uses_template_column_order(): void
    {
        $this->post(route('students.import'), [
            'file' => $this->csv("Rahim Uddin,1,1001\n"),
        ])->assertSessionHas('status', 'Import finished: 1 added, 0 updated.');

        $this->assertDatabaseHas('students', ['sid' => '1001', 'name' => 'Rahim Uddin']);
    }

    public function test_import_can_queue_name_sync(): void
    {
        Device::query()->create(['sn' => 'ABC123', 'last_seen_at' => now()]);

        $this->post(route('students.import'), [
            'file' => $this->csv("name,roll,sid\nRahim Uddin,1,1001\nKarim Hasan,2,1002\n"),
            'sync' => '1',
        ]);

        $this->assertSame(2, DeviceCommand::query()->where('status', 'pending')->count());
    }

    public function test_template_can_be_downloaded(): void
    {
        $this->get(route('students.import-template'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertSee('name,roll,sid', false);
    }
}
