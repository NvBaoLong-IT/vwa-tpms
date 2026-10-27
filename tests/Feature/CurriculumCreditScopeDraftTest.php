<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\DocumentImport;
use App\Models\Program;
use App\Models\User;
use App\Services\CurriculumDraftImporter;
use App\Services\CurriculumSnapshotDiffer;
use App\Services\ProgramCreditReconciler;
use App\Services\ProgramVersionService;
use Database\Seeders\OfficeRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurriculumCreditScopeDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_import_keeps_outside_courses_and_freezes_scope_in_version(): void
    {
        [$owner, $manager] = $this->users();
        $draft = $this->draft($owner);
        $this->actingAs($owner)->get(route('programs.document-imports.new.show', $draft))->assertOk()->assertSee('Ngoài tổng')->assertSee('3 / 3 tín chỉ');
        $hash = $draft->fresh()->comparison_hash;
        $this->post(route('programs.document-imports.submit', $draft), ['confirm' => 1, 'comparison_hash' => $hash])->assertSessionHasNoErrors();
        $this->actingAs($manager)->post(route('programs.document-imports.review', $draft), ['decision' => 'approve', 'review_note' => 'Đã đối chiếu'])->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('programs.document-imports.new.import', $draft), ['confirm' => 1, 'comparison_hash' => $hash])->assertSessionHasNoErrors();
        $program = Program::firstOrFail();
        $outside = $program->courses()->where('course_code', 'PE')->firstOrFail();
        $this->assertFalse((bool) $outside->pivot->counts_toward_total);
        $this->assertDatabaseCount('courses', 2);
        $this->assertSame(3, app(ProgramCreditReconciler::class)->forProgram($program)['effective_credits']);
        $this->assertSame(2, app(ProgramCreditReconciler::class)->forProgram($program)['outside_total_credits']);
        $version = app(ProgramVersionService::class)->capture($program, 2026, 'QD01', '2026-01-01', $owner->id);
        $program->courses()->updateExistingPivot($outside->id, ['counts_toward_total' => true]);
        $this->assertFalse(collect($version->fresh()->snapshot['courses'])->firstWhere('course_code', 'PE')['counts_toward_total']);
        $this->assertSame(3, app(ProgramCreditReconciler::class)->forVersion($version)['effective_credits']);
    }

    public function test_incomplete_draft_can_be_saved_previewed_but_not_submitted(): void
    {
        [$owner] = $this->users();
        $draft = $this->draft($owner);
        $payload = $draft->payload;
        $payload['courses'][0]['credits'] = null;
        $payload['courses'][0]['is_mandatory'] = null;
        $payload['courses'][0]['is_deferred'] = true;
        $this->actingAs($owner)->put(route('programs.document-imports.draft', $draft), $this->editData($draft, $payload))->assertSessionHasNoErrors();
        $saved = $draft->fresh();
        $this->assertNull($saved->payload['courses'][0]['credits']);
        $this->assertNull($saved->payload['courses'][0]['is_mandatory']);
        $this->assertTrue($saved->payload['courses'][0]['is_deferred']);
        $this->get(route('programs.document-imports.new.show', $draft))->assertOk()->assertSee('Tạm bỏ qua')->assertSee('0 / 3 tín chỉ')->assertDontSee('Trình duyệt bản nhập AI');
        $this->post(route('programs.document-imports.submit', $draft), ['confirm' => 1, 'comparison_hash' => app(CurriculumDraftImporter::class)->comparisonHash($saved)])->assertSessionHasErrors('payload');
        $this->assertSame('review', $draft->fresh()->status);
        $this->assertDatabaseCount('programs', 0);
        $this->assertDatabaseCount('courses', 0);
    }

    public function test_deferred_course_blocks_submission_even_if_remaining_credits_match(): void
    {
        [$owner] = $this->users();
        $draft = $this->draft($owner);
        $payload = $draft->payload;
        $payload['courses'][1]['is_deferred'] = true;
        $draft->update(['payload' => $payload]);
        $this->actingAs($owner)->post(route('programs.document-imports.submit', $draft), ['confirm' => 1, 'comparison_hash' => app(CurriculumDraftImporter::class)->comparisonHash($draft)])->assertSessionHasErrors('payload');
        $this->assertSame('review', $draft->fresh()->status);
        $this->assertDatabaseCount('courses', 0);
    }

    public function test_missing_credits_can_be_previewed_but_not_submitted_and_other_users_cannot_edit(): void
    {
        [$owner, $other] = $this->users();
        $draft = $this->draft($owner);
        $payload = $draft->payload;
        $payload['program']['total_credits'] = 9;
        $draft->update(['payload' => $payload]);
        $this->actingAs($owner)->get(route('programs.document-imports.new.show', $draft))->assertOk()->assertSee('3 / 9 tín chỉ')->assertSee('Thiếu 6')->assertDontSee('Trình duyệt bản nhập AI');
        $this->post(route('programs.document-imports.submit', $draft), ['confirm' => 1, 'comparison_hash' => $draft->fresh()->comparison_hash])->assertSessionHasErrors('payload');
        $this->actingAs($other)->put(route('programs.document-imports.draft', $draft), $this->editData($draft->fresh(), $payload))->assertForbidden();
        $this->assertSame('review', $draft->fresh()->status);
        $this->assertDatabaseCount('courses', 0);
    }

    public function test_manual_scope_is_per_program_and_old_snapshots_default_to_counted(): void
    {
        [$owner] = $this->users();
        $program = Program::create(['code' => 'SCOPE', 'name_vi' => 'Phạm vi', 'degree_level' => 'dai_hoc', 'total_credits' => 3]);
        $other = Program::create(['code' => 'OTHER', 'name_vi' => 'Khác', 'degree_level' => 'dai_hoc', 'total_credits' => 3]);
        $course = Course::create(['course_code' => 'SHARED', 'name_vi' => 'Dùng chung', 'credits' => 3]);
        $other->courses()->attach($course, ['knowledge_block' => 'Đại cương', 'is_mandatory' => true]);
        $this->actingAs($owner)->post(route('programs.courses.attach', $program), ['course_id' => $course->id, 'knowledge_block' => 'Đại cương', 'is_mandatory' => 1, 'counts_toward_total' => 0])->assertSessionHasNoErrors();
        $this->assertFalse((bool) $program->courses()->firstOrFail()->pivot->counts_toward_total);
        $this->assertTrue((bool) $other->courses()->firstOrFail()->pivot->counts_toward_total);
        $legacy = ['program' => ['total_credits' => 3, 'elective_credits_required' => 0], 'required_blocks' => [], 'courses' => [['credits' => 3, 'is_mandatory' => true, 'knowledge_block' => 'Đại cương']]];
        $this->assertSame(3, app(ProgramCreditReconciler::class)->forSnapshot($legacy)['effective_credits']);
    }

    public function test_outside_course_cannot_supply_elective_group_credits(): void
    {
        $snapshot = ['program' => ['total_credits' => 3, 'elective_credits_required' => 3], 'required_blocks' => ['Chuyên ngành'], 'elective_groups' => [['code' => 'TC', 'name' => 'Tự chọn', 'knowledge_block' => 'Chuyên ngành', 'credits_required' => 3]], 'courses' => [['course_code' => 'OUT', 'credits' => 3, 'knowledge_block' => 'Chuyên ngành', 'is_mandatory' => false, 'elective_group_code' => 'TC', 'counts_toward_total' => false]]];
        $analysis = app(ProgramCreditReconciler::class)->forSnapshot($snapshot);
        $this->assertSame(0, $analysis['elective_groups'][0]['credits_offered']);
        $this->assertNotEmpty(array_filter($analysis['issues'], fn ($issue) => $issue['severity'] === 'error'));
    }

    private function users(): array
    {
        $this->seed(OfficeRolesSeeder::class);
        $owner = User::factory()->create();
        $owner->assignRole('Chuyên viên');
        $manager = User::factory()->create();
        $manager->assignRole('Lãnh đạo phòng');
        return [$owner, $manager];
    }

    private function draft(User $owner): DocumentImport
    {
        return DocumentImport::create(['uploaded_by' => $owner->id, 'original_name' => 'scope.docx', 'path' => 'scope.docx', 'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'status' => 'review', 'payload' => [
            'program' => ['code' => 'SCOPE-AI', 'name_vi' => 'Chương trình thử', 'degree_level' => 'dai_hoc', 'total_credits' => 3, 'elective_credits_required' => 0],
            'knowledge_blocks' => ['Đại cương'], 'elective_groups' => [], 'plos' => [], 'matrix' => [],
            'courses' => [
                ['course_code' => 'MAIN', 'name_vi' => 'Trong tổng', 'credits' => 3, 'knowledge_block' => 'Đại cương', 'is_mandatory' => true],
                ['course_code' => 'PE', 'name_vi' => 'Thể chất', 'credits' => 2, 'knowledge_block' => 'Thể chất', 'is_mandatory' => true, 'counts_toward_total' => false],
            ],
        ]]);
    }

    private function editData(DocumentImport $draft, array $payload): array
    {
        return ['payload_hash' => app(CurriculumSnapshotDiffer::class)->fingerprint($draft->payload), 'draft_program' => $payload['program'], 'blocks_text' => 'Đại cương', 'groups_text' => '', 'draft_courses' => $payload['courses']];
    }
}
