<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CurriculumManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['Lãnh đạo phòng', 'Chuyên viên CTĐT'] as $name) {
            $role = Role::create(['name' => $name, 'guard_name' => 'web']);
            $role->syncPermissions(collect(['programs.view', 'programs.create', 'programs.update', 'programs.delete', 'courses.view', 'courses.create', 'courses.update', 'courses.delete', 'courses.assign'])->map(fn ($p) => Permission::findOrCreate($p, 'web'))->all());
        }
        Role::create(['name' => 'Chuyên viên học vụ', 'guard_name' => 'web']);
    }

    public function test_curriculum_officer_can_create_program_and_course(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Chuyên viên CTĐT');
        $this->actingAs($user)->post(route('programs.store'), [
            'code' => 'TEST-PROGRAM', 'name_vi' => 'Chương trình thử nghiệm', 'degree_level' => 'dai_hoc',
            'total_credits' => 120, 'review_cycle_years' => 3, 'is_active' => 1,
        ])->assertRedirect(route('programs.index'));
        $this->assertDatabaseHas('programs', ['code' => 'TEST-PROGRAM']);

        $this->actingAs($user)->post(route('courses.store'), [
            'course_code' => 'TEST101', 'name_vi' => 'Học phần thử nghiệm', 'credits' => 3,
            'theory_hours' => 30, 'practice_hours' => 0, 'is_active' => 1,
        ])->assertRedirect(route('courses.index'));
        $this->assertDatabaseHas('courses', ['course_code' => 'TEST101']);
    }

    public function test_create_forms_show_field_errors_and_preserve_unchecked_active_state(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Chuyên viên CTĐT');

        $this->actingAs($user)->from(route('programs.create'))->post(route('programs.store'), [
            'code' => 'INVALID', 'name_vi' => '', 'degree_level' => 'dai_hoc',
            'total_credits' => 120, 'elective_credits_required' => 0, 'review_cycle_years' => 3, 'is_active' => 0,
        ])->assertSessionHasErrors('name_vi')->assertRedirect(route('programs.create'));
        $response = $this->get(route('programs.create'));
        $response->assertSee('id="programs-name_vi-error"', false)->assertSee('value="INVALID"', false);
        $document = new \DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $this->assertSame(0, $xpath->query('//input[@name="is_active"][@type="checkbox"][@checked]')->length);

        $this->from(route('courses.create'))->post(route('courses.store'), [
            'course_code' => 'INVALID101', 'name_vi' => '', 'credits' => 3,
            'theory_hours' => 30, 'practice_hours' => 0, 'is_active' => 0,
        ])->assertSessionHasErrors('name_vi')->assertRedirect(route('courses.create'));
        $this->get(route('courses.create'))
            ->assertSee('id="courses-name_vi-error"', false)
            ->assertSee('value="INVALID101"', false)
            ->assertSee('Tín chỉ và thời lượng');
    }

    public function test_academic_officer_cannot_manage_curriculum(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Chuyên viên học vụ');
        $this->actingAs($user)->get(route('programs.index'))->assertForbidden();
        $this->actingAs($user)->get(route('courses.index'))->assertForbidden();
    }

    public function test_program_can_attach_and_detach_course(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Lãnh đạo phòng');
        $program = Program::create(['code' => 'P-001', 'name_vi' => 'P', 'degree_level' => 'dai_hoc', 'total_credits' => 120]);
        $course = Course::create(['course_code' => 'C-001', 'name_vi' => 'C', 'credits' => 3, 'theory_hours' => 30, 'practice_hours' => 0]);
        $this->actingAs($user)->post(route('programs.courses.attach', $program), ['course_id' => $course->id, 'knowledge_block' => 'Đại cương', 'is_mandatory' => 1])->assertRedirect();
        $this->assertDatabaseHas('program_course', ['program_id' => $program->id, 'course_id' => $course->id]);
        $this->actingAs($user)->delete(route('programs.courses.detach', [$program, $course]))->assertRedirect();
        $this->assertDatabaseMissing('program_course', ['program_id' => $program->id, 'course_id' => $course->id]);
    }
}
