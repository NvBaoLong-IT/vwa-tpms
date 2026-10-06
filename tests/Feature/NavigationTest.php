<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\StudentDirectoryPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_academic_user_can_find_students_without_curriculum_links(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->givePermissionTo([
            Permission::findOrCreate('academic.view', 'web'),
            Permission::findOrCreate('dashboard.view', 'web'),
        ]);
        Permission::findOrCreate('academic.process', 'web');

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('academic.students').'"', false)
            ->assertDontSee('href="'.route('courses.index').'"', false);
        $this->get(route('academic.students'))->assertOk();
    }

    public function test_viewer_can_open_student_directory_without_edit_access(): void
    {
        Permission::findOrCreate('academic.process', 'web');
        $role = Role::findOrCreate('Xem', 'web');
        $role->givePermissionTo(Permission::findOrCreate('dashboard.view', 'web'));
        $this->seed(StudentDirectoryPermissionsSeeder::class);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole($role);
        $student = Student::create(['student_code' => 'SV-NAV-01', 'full_name' => 'Sinh viên thử nghiệm', 'status' => 'active']);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('academic.students').'"', false)
            ->assertDontSee('href="'.route('diplomas.index').'"', false);
        $this->get(route('academic.students'))->assertOk()->assertSee('SV-NAV-01');
        $this->get(route('academic.student', $student))->assertOk()->assertSee('Sinh viên thử nghiệm');
        $this->get(route('academic.index'))->assertForbidden();
        $this->put(route('academic.progress.update', $student), [])->assertForbidden();
    }

    public function test_course_viewer_can_navigate_but_cannot_see_edit_actions(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->givePermissionTo([
            Permission::findOrCreate('programs.view', 'web'),
            Permission::findOrCreate('courses.view', 'web'),
        ]);
        $program = Program::create(['code' => 'NAV-01', 'name_vi' => 'Chương trình', 'degree_level' => 'dai_hoc', 'total_credits' => 3]);
        $course = Course::create(['course_code' => 'NAV101', 'name_vi' => 'Học phần', 'credits' => 3]);
        $program->courses()->attach($course, ['knowledge_block' => 'Đại cương', 'is_mandatory' => true]);

        $this->actingAs($user)->get(route('programs.index'))
            ->assertOk()
            ->assertSee('href="'.route('courses.index').'"', false);
        $this->get(route('courses.index'))
            ->assertOk()
            ->assertSee('href="'.route('courses.show', $course).'"', false)
            ->assertDontSee('href="'.route('courses.create').'"', false)
            ->assertDontSee('href="'.route('courses.edit', $course).'"', false);
        $this->get(route('courses.create'))->assertForbidden();
    }

    public function test_program_viewer_does_not_receive_inaccessible_course_link(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->givePermissionTo(Permission::findOrCreate('programs.view', 'web'));
        $program = Program::create(['code' => 'NAV-02', 'name_vi' => 'Chương trình', 'degree_level' => 'dai_hoc', 'total_credits' => 3]);
        $course = Course::create(['course_code' => 'NAV102', 'name_vi' => 'Học phần', 'credits' => 3]);
        $program->courses()->attach($course, ['knowledge_block' => 'Đại cương', 'is_mandatory' => true]);

        $this->actingAs($user)->get(route('programs.show', $program))
            ->assertOk()
            ->assertSee('Học phần')
            ->assertDontSee('href="'.route('courses.show', $course).'"', false);
    }
}
