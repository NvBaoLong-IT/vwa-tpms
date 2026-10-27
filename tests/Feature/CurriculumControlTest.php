<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\IsoWorkflow;
use App\Models\Program;
use App\Models\ProgramVersion;
use App\Models\User;
use App\Services\ProgramVersionService;
use Database\Seeders\OfficeRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurriculumControlTest extends TestCase
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

    private function version(bool $valid = true, int $cohort = 2026): ProgramVersion
    {
        $program = Program::create(['code' => fake()->unique()->bothify('CT-####'), 'name_vi' => 'Kiểm soát CTĐT', 'degree_level' => 'dai_hoc', 'total_credits' => 3, 'elective_credits_required' => 0, 'required_knowledge_blocks' => ['Đại cương']]);
        if ($valid) {
            $course = Course::create(['course_code' => fake()->unique()->bothify('HP-####'), 'name_vi' => 'Học phần', 'credits' => 3]);
            $program->courses()->attach($course, ['knowledge_block' => 'Đại cương', 'is_mandatory' => true]);
        }

        return app(ProgramVersionService::class)->capture($program, $cohort, '01/QĐ', '2026-01-01', auth()->id());
    }

    public function test_review_return_resubmit_publish_preserves_the_submitted_snapshot(): void
    {
        $officer = $this->user('Chuyên viên');
        $manager = $this->user('Lãnh đạo phòng');
        $this->actingAs($officer);
        $version = $this->version();
        $args = [$version->program_id, $version];
        $snapshot = $version->snapshot;
        $this->post(route('programs.versions.publish', $args))->assertForbidden();
        $this->post(route('programs.versions.submit', $args), ['effective_from' => '2026-01-01'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('submitted', $version->fresh()->status);
        $this->post(route('programs.versions.review', $args), ['decision' => 'approve', 'review_note' => 'Đồng ý'])->assertForbidden();
        $this->actingAs($manager)->post(route('programs.versions.publish', $args))->assertStatus(422);
        $this->post(route('programs.versions.review', $args), ['decision' => 'return', 'review_note' => 'Bổ sung hồ sơ'])->assertRedirect();
        $this->assertSame('returned', $version->fresh()->status);
        $this->get(route('programs.versions.show', $args))->assertOk()->assertSee('Bổ sung hồ sơ')->assertSee('Trình duyệt phiên bản');
        $this->actingAs($officer)->post(route('programs.versions.submit', $args), ['effective_from' => '2026-01-01'])->assertSessionHasNoErrors()->assertRedirect();
        $version->program->courses->first()->update(['credits' => 9]);
        $this->actingAs($manager)->post(route('programs.versions.review', $args), ['decision' => 'approve', 'review_note' => 'Đồng ý nội dung bản lưu'])->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('programs.versions.publish', $args))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('published', $version->fresh()->status);
        $this->assertSame($snapshot, $version->fresh()->snapshot);
        $this->post(route('programs.versions.review', $args), ['decision' => 'return', 'review_note' => 'Thử lại'])->assertStatus(422);
        $this->assertDatabaseHas('curriculum_audits', ['subject_id' => $version->id, 'subject_type' => 'program_version', 'action' => 'approved', 'actor_id' => $manager->id]);
    }

    public function test_invalid_credits_dates_and_cross_program_requests_cannot_be_submitted(): void
    {
        $this->actingAs($this->user('Chuyên viên'));
        $invalid = $this->version(false);
        $args = [$invalid->program_id, $invalid];
        $this->post(route('programs.versions.submit', $args), ['effective_from' => '2026-01-01'])->assertSessionHasErrors('version');
        $this->assertSame('draft', $invalid->fresh()->status);
        $valid = $this->version();
        $this->post(route('programs.versions.submit', [$valid->program_id, $valid]), ['effective_from' => '2026-02-01', 'effective_until' => '2026-01-01'])->assertSessionHasErrors('effective_until');
        $this->post(route('programs.versions.submit', [$invalid->program_id, $valid]), ['effective_from' => '2026-01-01'])->assertNotFound();
        $this->actingAs($this->user('Xem'))->post(route('programs.versions.submit', [$valid->program_id, $valid]), ['effective_from' => '2026-01-01'])->assertForbidden();
    }

    public function test_future_publication_keeps_current_version_effective_until_replacement_date_and_other_cohorts_untouched(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 26));
        $this->actingAs($this->user('Lãnh đạo phòng'));
        $first = $this->version();
        $service = app(ProgramVersionService::class);
        $service->submit($first, ['effective_from' => '2026-01-01'], auth()->id());
        $independentReviewer = $this->user('Lãnh đạo phòng');
        $service->review($first, true, 'Duyệt', $independentReviewer->id);
        $service->publish($first);
        $other = $service->capture($first->program, 2025, '02/QĐ', '2026-01-01', auth()->id());
        $service->submit($other, ['effective_from' => '2026-01-01'], auth()->id());
        $service->review($other, true, 'Duyệt', $independentReviewer->id);
        $service->publish($other);
        $second = $service->capture($first->program, 2026, '03/QĐ', '2026-10-01', auth()->id());
        $service->submit($second, ['effective_from' => '2027-01-01'], auth()->id());
        $service->review($second, true, 'Duyệt', $independentReviewer->id);
        $service->publish($second);
        $this->assertSame('published', $first->fresh()->status);
        $this->assertSame('2026-12-31', $first->fresh()->effective_until->toDateString());
        $this->assertSame('Đang áp dụng', $first->fresh()->statusLabel());
        $this->assertStringContainsString('Chưa đến hiệu lực', $second->fresh()->statusLabel());
        $this->assertNull($other->fresh()->effective_until);
        $this->travelTo(now()->setDate(2027, 1, 1));
        $this->assertSame('Đã hết hiệu lực', $first->fresh()->statusLabel());
        $this->assertSame('Đang áp dụng', $second->fresh()->statusLabel());
    }

    public function test_compare_shows_course_changes_and_rejects_version_from_another_program(): void
    {
        $this->actingAs($this->user('Chuyên viên'));
        $first = $this->version();
        $first->program->courses->first()->update(['name_vi' => 'Học phần sửa']);
        $second = app(ProgramVersionService::class)->capture($first->program, 2026, '02/QĐ', '2026-02-01', auth()->id());
        $this->get(route('programs.versions.show', [$first->program_id, $second, 'compare' => $first->id]))->assertOk()->assertSee('Học phần sửa')->assertSee('Sửa');
        $other = $this->version();
        $this->get(route('programs.versions.show', [$first->program_id, $second, 'compare' => $other->id]))->assertNotFound();
    }

    public function test_linked_iso_review_must_be_completed_and_belongs_to_the_program(): void
    {
        $this->actingAs($this->user('Lãnh đạo phòng'));
        $version = $this->version();
        $args = [$version->program_id, $version];
        $iso = IsoWorkflow::create(['program_id' => $version->program_id, 'type' => 'minor', 'title' => 'Rà soát CTĐT', 'start_date' => '2026-01-01', 'expected_end_date' => '2026-02-01', 'status' => 'in_progress']);
        $this->post(route('programs.versions.submit', $args), ['effective_from' => '2026-01-01', 'iso_workflow_id' => $iso->id])->assertSessionHasNoErrors()->assertRedirect();
        $this->get(route('programs.index', ['version_status' => 'submitted']))->assertOk()->assertSee('Kiểm soát CTĐT')->assertSee('1 phiên bản chờ phê duyệt');
        $this->actingAs($this->user('Lãnh đạo phòng'))->post(route('programs.versions.review', $args), ['decision' => 'approve', 'review_note' => 'Duyệt'])->assertSessionHasErrors('version');
        $iso->update(['status' => 'completed', 'approved_by' => auth()->id(), 'approved_at' => now()]);
        $this->post(route('programs.versions.review', $args), ['decision' => 'approve', 'review_note' => 'Duyệt'])->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('programs.versions.publish', $args))->assertSessionHasNoErrors()->assertRedirect();

        $other = $this->version();
        $this->post(route('programs.versions.submit', [$other->program_id, $other]), ['effective_from' => '2026-01-01', 'iso_workflow_id' => $iso->id])->assertStatus(422);
        config(['curriculum.require_iso_review' => true]);
        $this->post(route('programs.versions.submit', [$other->program_id, $other]), ['effective_from' => '2026-01-01'])->assertSessionHasErrors('version');
    }
}
