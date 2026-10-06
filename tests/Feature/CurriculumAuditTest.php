<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CurriculumAudit;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\OfficeRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurriculumAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OfficeRolesSeeder::class);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_program_course_and_plo_changes_record_actor_and_values(): void
    {
        $specialist = $this->user('Chuyên viên');
        $this->actingAs($specialist)->post(route('programs.store'), [
            'code' => 'AUD-01', 'name_vi' => 'Tên ban đầu', 'degree_level' => 'dai_hoc',
            'total_credits' => 3, 'review_cycle_years' => 3,
        ])->assertRedirect();
        $program = Program::where('code', 'AUD-01')->firstOrFail();
        $created = CurriculumAudit::where('subject_type', 'program')->where('action', 'created')->firstOrFail();
        $this->assertSame($specialist->id, $created->actor_id);
        $this->assertSame('Tên ban đầu', $created->new_values['name_vi']);

        $this->put(route('programs.update', $program), [
            'code' => 'AUD-01', 'name_vi' => 'Tên mới', 'degree_level' => 'dai_hoc',
            'total_credits' => 3, 'review_cycle_years' => 3,
        ])->assertRedirect();
        $changed = CurriculumAudit::where('subject_type', 'program')->where('action', 'updated')->firstOrFail();
        $this->assertSame('Tên ban đầu', $changed->old_values['name_vi']);
        $this->assertSame('Tên mới', $changed->new_values['name_vi']);

        $this->post(route('courses.store'), ['course_code' => 'AUD101', 'name_vi' => 'Học phần', 'credits' => 3, 'theory_hours' => 45, 'practice_hours' => 0])->assertRedirect();
        $course = Course::where('course_code', 'AUD101')->firstOrFail();
        $this->put(route('courses.update', $course), ['course_code' => 'AUD101', 'name_vi' => 'Học phần mới', 'credits' => 3, 'theory_hours' => 45, 'practice_hours' => 0])->assertRedirect();
        $courseAudit = CurriculumAudit::where('subject_type', 'course')->where('action', 'updated')->firstOrFail();
        $this->assertSame('Học phần', $courseAudit->old_values['name_vi']);
        $this->assertSame('Học phần mới', $courseAudit->new_values['name_vi']);
        $program->courses()->attach($course, ['knowledge_block' => 'Đại cương', 'is_mandatory' => true]);
        $this->put(route('courses.update', $course), ['course_code' => 'AUD101', 'name_vi' => 'Học phần sửa lần hai', 'credits' => 3, 'theory_hours' => 45, 'practice_hours' => 0])->assertRedirect();
        $this->assertDatabaseHas('curriculum_audits', ['subject_type' => 'course', 'subject_id' => $course->id, 'program_id' => $program->id, 'actor_id' => $specialist->id]);

        $this->post(route('programs.plos.store', $program), ['code' => 'PLO1', 'description_vi' => 'Mô tả cũ'])->assertRedirect();
        $plo = $program->plos()->firstOrFail();
        $this->put(route('programs.plos.update', [$program, $plo]), ['code' => 'PLO1', 'description_vi' => 'Mô tả mới'])->assertRedirect();
        $ploAudit = CurriculumAudit::where('subject_type', 'plo')->where('action', 'updated')->firstOrFail();
        $this->assertSame('Mô tả cũ', $ploAudit->old_values['description_vi']);
        $this->assertSame('Mô tả mới', $ploAudit->new_values['description_vi']);
    }

    public function test_pivot_matrix_and_version_events_are_recorded(): void
    {
        $manager = $this->user('Lãnh đạo phòng');
        $program = Program::create(['code' => 'AUD-02', 'name_vi' => 'Chương trình', 'degree_level' => 'dai_hoc', 'total_credits' => 3]);
        $course = Course::create(['course_code' => 'AUD201', 'name_vi' => 'Học phần', 'credits' => 3]);
        $plo = $program->plos()->create(['code' => 'PLO1', 'description_vi' => 'Chuẩn']);

        $this->actingAs($manager)->post(route('programs.courses.attach', $program), ['course_id' => $course->id, 'knowledge_block' => 'Đại cương', 'is_mandatory' => 1])->assertRedirect();
        $this->put(route('programs.matrix.update', $program), ['levels' => [$course->id => [$plo->id => 2]]])->assertRedirect();
        $this->post(route('programs.versions.store', $program), ['cohort_year' => 2026, 'decision_number' => '604/QĐ', 'decision_date' => '2026-06-16'])->assertRedirect();
        $version = $program->versions()->firstOrFail();
        $this->post(route('programs.versions.publish', [$program, $version]))->assertRedirect();

        $this->assertDatabaseHas('curriculum_audits', ['subject_type' => 'program_course', 'action' => 'attached', 'actor_id' => $manager->id]);
        $this->assertDatabaseHas('curriculum_audits', ['subject_type' => 'plo_matrix', 'action' => 'updated', 'actor_id' => $manager->id]);
        $this->assertDatabaseHas('curriculum_audits', ['subject_type' => 'program_version', 'action' => 'created', 'actor_id' => $manager->id]);
        $this->assertDatabaseHas('curriculum_audits', ['subject_type' => 'program_version', 'action' => 'published', 'actor_id' => $manager->id]);
        $this->get(route('programs.audit', ['program_id' => $program->id]))->assertOk()->assertSee($manager->name)->assertSee('Công bố');
    }

    public function test_audit_is_read_only_for_viewer_and_survives_program_deletion(): void
    {
        $manager = $this->user('Lãnh đạo phòng');
        $viewer = $this->user('Xem');
        $program = Program::create(['code' => 'AUD-03', 'name_vi' => 'Xóa thử', 'degree_level' => 'dai_hoc', 'total_credits' => 3]);

        $this->actingAs($viewer)->get(route('programs.audit'))->assertOk();
        $this->delete(route('programs.destroy', $program))->assertForbidden();
        $this->actingAs($manager)->delete(route('programs.destroy', $program))->assertRedirect();
        $deleted = CurriculumAudit::where('subject_type', 'program')->where('action', 'deleted')->firstOrFail();
        $this->assertSame($program->id, $deleted->subject_id);
        $this->assertNull($deleted->program_id);
        $this->assertSame('Xóa thử', $deleted->old_values['name_vi']);

    }
}
