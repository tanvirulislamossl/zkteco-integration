<?php

namespace Tests\Feature;

use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_be_added_and_listed(): void
    {
        $this->post(route('students.store'), ['name' => 'Rahim Uddin', 'roll' => '12', 'sid' => '1001'])
            ->assertRedirect(route('students.index'));

        $this->get(route('students.index'))
            ->assertOk()
            ->assertSee('Rahim Uddin')
            ->assertSee('1001');
    }

    public function test_sid_must_be_unique_and_alphanumeric(): void
    {
        Student::factory()->create(['sid' => '1001']);

        $this->post(route('students.store'), ['name' => 'A', 'sid' => '1001'])->assertSessionHasErrors('sid');
        $this->post(route('students.store'), ['name' => 'B', 'sid' => '10 01'])->assertSessionHasErrors('sid');
    }

    public function test_student_can_be_updated_and_deleted(): void
    {
        $student = Student::factory()->create(['sid' => '1001']);

        $this->put(route('students.update', $student), ['name' => 'New Name', 'roll' => '5', 'sid' => '1001'])
            ->assertSessionHasNoErrors();

        $this->assertSame('New Name', $student->fresh()->name);

        $this->delete(route('students.destroy', $student));

        $this->assertModelMissing($student);
    }
}
