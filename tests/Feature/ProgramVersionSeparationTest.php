<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Program;
use App\Models\User;
use App\Services\CurriculumEditGuard;
use App\Services\ProgramVersionService;
use Database\Seeders\OfficeRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramVersionSeparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_leader_cannot_review_own_version_and_other_leader_can_publish(): void
    {
        $this->seed(OfficeRolesSeeder::class);
        $owner = User::factory()->create();
        $owner->assignRole('Lãnh đạo phòng');
        $reviewer = User::factory()->create();
        $reviewer->assignRole('Lãnh đạo phòng');
        $program = Program::create(['code' => 'INDEPENDENT', 'name_vi' => 'CT độc lập', 'degree_level' => 'dai_hoc', 'total_credits' => 3]);
        $course = Course::create(['course_code' => 'HP-I', 'name_vi' => 'Học phần', 'credits' => 3]);
        $program->courses()->attach($course, ['knowledge_block' => 'Đại cương', 'is_mandatory' => true]);
        $this->actingAs($owner);
        $version = app(ProgramVersionService::class)->capture($program, 2026, '01/QĐ', '2026-01-01', $owner->id);
        $this->post(route('programs.versions.submit', [$program, $version]), ['effective_from' => '2026-01-01'])->assertRedirect();
        $this->post(route('programs.versions.review', [$program, $version]), ['decision' => 'approve', 'review_note' => 'Tự duyệt'])->assertForbidden();
        $this->post(route('programs.versions.review', [$program, $version]), ['decision' => 'return', 'review_note' => 'Tự trả về'])->assertForbidden();
        $this->get(route('programs.versions.show', [$program, $version]))->assertOk()->assertDontSee('name="decision"', false)->assertSee('Cần một lãnh đạo khác');
        $this->actingAs($reviewer)->post(route('programs.versions.review', [$program, $version]), ['decision' => 'approve', 'review_note' => 'Đã đối chiếu'])->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('programs.versions.publish', [$program, $version]))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('published', $version->fresh()->status);
        $this->assertSame($reviewer->id, $version->fresh()->reviewed_by);
    }

    public function test_old_program_and_shared_course_forms_cannot_overwrite_newer_changes(): void
    {
        $this->seed(OfficeRolesSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Chuyên viên');
        $program = Program::create(['code' => 'STALE', 'name_vi' => 'Tên trước', 'degree_level' => 'dai_hoc', 'total_credits' => 3, 'review_cycle_years' => 3]);
        $course = Course::create(['course_code' => 'SHARED', 'name_vi' => 'Tên học phần trước', 'credits' => 3]);
        $guard = app(CurriculumEditGuard::class);
        $programHash = $guard->programHash($program);
        $courseHash = $guard->courseHash($course);
        $program->update(['name_vi' => 'Tên mới đã nhập']);
        $course->update(['credits' => 4]);
        $this->actingAs($user)->put(route('programs.update', $program), ['editing_hash' => $programHash, 'code' => 'STALE', 'name_vi' => 'Tên ghi đè', 'degree_level' => 'dai_hoc', 'total_credits' => 3, 'review_cycle_years' => 3])->assertSessionHasErrors('editing_hash');
        $this->put(route('courses.update', $course), ['editing_hash' => $courseHash, 'course_code' => 'SHARED', 'name_vi' => 'Tên ghi đè', 'credits' => 3, 'theory_hours' => 45, 'practice_hours' => 0])->assertSessionHasErrors('editing_hash');
        $this->assertSame('Tên mới đã nhập', $program->fresh()->name_vi);
        $this->assertSame(4, (int) $course->fresh()->credits);
    }
}
