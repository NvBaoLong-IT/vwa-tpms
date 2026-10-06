<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\StudentProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class StudentDetailTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPermissions(array $permissions): User
    {
        foreach (['students.view', 'academic.view', 'academic.process'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }

    public function test_processor_views_student_information_without_embedded_edit_forms(): void
    {
        $user = $this->userWithPermissions(['academic.view', 'academic.process']);
        $student = Student::create(['student_code' => 'DETAIL-01', 'full_name' => 'Sinh viên hồ sơ', 'degree_level' => 'dai_hoc', 'email' => 'student@example.test', 'enrollment_year' => 2026]);
        StudentProgress::create(['student_id' => $student->id, 'semester' => '2026-1', 'gpa_4' => 2.75, 'credits_accumulated' => 35, 'credits_required' => 60]);

        $this->actingAs($user)->get(route('academic.student', $student))
            ->assertSee('Sinh viên hồ sơ')
            ->assertSee('student@example.test')
            ->assertSee('2.75')
            ->assertSee('href="'.route('academic.student.edit', $student).'"', false)
            ->assertDontSee('action="'.route('academic.assignment.update', $student).'"', false)
            ->assertDontSee('action="'.route('academic.progress.update', $student).'"', false)
            ->assertDontSee('action="'.route('academic.milestones.update', $student).'"', false);
    }

    public function test_directory_viewer_cannot_edit_or_view_restricted_academic_data(): void
    {
        $user = $this->userWithPermissions(['students.view']);
        $student = Student::create(['student_code' => 'VIEW-01', 'full_name' => 'Sinh viên chỉ xem']);

        $this->actingAs($user)->get(route('academic.student', $student))
            ->assertSee('Sinh viên chỉ xem')
            ->assertDontSee('Chỉnh sửa hồ sơ')
            ->assertDontSee('Lịch sử cảnh báo');
        $this->get(route('academic.student.edit', $student))->assertForbidden();
        $this->put(route('academic.assignment.update', $student), ['faculty_name' => 'Không được sửa'])->assertForbidden();
        $this->assertNull($student->fresh()->faculty_name);
    }

    public function test_academic_viewer_without_processing_permission_cannot_open_editor(): void
    {
        $user = $this->userWithPermissions(['academic.view']);
        $student = Student::create(['student_code' => 'VIEW-02', 'full_name' => 'Sinh viên học vụ']);

        $this->actingAs($user)->get(route('academic.student', $student))
            ->assertSee('Lịch sử cảnh báo')
            ->assertDontSee('Chỉnh sửa hồ sơ');
        $this->get(route('academic.student.edit', $student))->assertForbidden();
    }

    public function test_processor_can_save_assignment_in_separate_editor_and_read_result(): void
    {
        $user = $this->userWithPermissions(['academic.view', 'academic.process']);
        $student = Student::create(['student_code' => 'EDIT-01', 'full_name' => 'Sinh viên chỉnh sửa', 'degree_level' => 'thac_si']);
        $editUrl = route('academic.student.edit', $student);

        $this->actingAs($user)->get($editUrl)
            ->assertSee('action="'.route('academic.assignment.update', $student).'"', false)
            ->assertSee('action="'.route('academic.progress.update', $student).'"', false)
            ->assertSee('action="'.route('academic.milestones.update', $student).'"', false)
            ->assertSee('Hạn nộp đề cương')
            ->assertSee('href="'.route('academic.student', $student).'"', false);
        $this->from($editUrl)->put(route('academic.assignment.update', $student), ['faculty_name' => 'Khoa đào tạo', 'advisor_user_id' => $user->id])
            ->assertRedirect($editUrl)
            ->assertSessionHasNoErrors();
        $this->assertSame('Khoa đào tạo', $student->fresh()->faculty_name);
        $this->assertSame($user->id, $student->fresh()->advisor_user_id);
        $this->get(route('academic.student', $student))->assertSee('Khoa đào tạo');
    }

    public function test_student_names_are_escaped_on_both_pages(): void
    {
        $user = $this->userWithPermissions(['academic.view', 'academic.process']);
        $student = Student::create(['student_code' => 'ESC-01', 'full_name' => '<script>alert(1)</script>']);

        $this->actingAs($user)->get(route('academic.student', $student))
            ->assertSee($student->full_name)
            ->assertDontSee($student->full_name, false);
        $this->get(route('academic.student.edit', $student))
            ->assertSee($student->full_name)
            ->assertDontSee($student->full_name, false);
    }
}
