<?php

namespace Tests\Feature;

use App\Models\DocumentImport;
use App\Models\Program;
use App\Models\User;
use App\Services\CurriculumDraftImporter;
use Database\Seeders\OfficeRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AiCurriculumApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function draft(User $owner, bool $new = false): DocumentImport
    {
        $program = $new ? null : Program::create(['code' => 'AI-APP', 'name_vi' => 'Tên cũ', 'degree_level' => 'dai_hoc', 'total_credits' => 3]);

        return DocumentImport::create(['program_id' => $program?->id, 'uploaded_by' => $owner->id, 'original_name' => 'approval.docx', 'path' => 'approval.docx', 'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'status' => 'review', 'payload' => ['program' => ['code' => 'AI-APP', 'name_vi' => 'Tên từ AI', 'degree_level' => 'dai_hoc', 'total_credits' => 3], 'courses' => [['course_code' => 'AIHP', 'name_vi' => 'Học phần AI', 'credits' => 3, 'knowledge_block' => 'Đại cương', 'is_mandatory' => true, 'suggested_semester' => 1]], 'plos' => [], 'matrix' => [], 'warnings' => []]]);
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_ai_requires_independent_review_before_applying_existing_or_new_program(bool $new): void
    {
        $this->seed(OfficeRolesSeeder::class);
        $owner = $this->user('Chuyên viên');
        $manager = $this->user('Lãnh đạo phòng');
        $draft = $this->draft($owner, $new);
        $show = $new ? route('programs.document-imports.new.show', $draft) : route('programs.document-imports.show', [$draft->program_id, $draft]);
        $apply = $new ? route('programs.document-imports.new.import', $draft) : route('programs.document-imports.import', [$draft->program_id, $draft]);
        $this->actingAs($owner)->get($show)->assertOk()->assertSee('Trình duyệt bản nhập AI');
        $hash = $draft->fresh()->comparison_hash;
        $this->post($apply, ['confirm' => 1, 'comparison_hash' => $hash])->assertStatus(422);
        $this->post(route('programs.document-imports.submit', $draft), ['confirm' => 1, 'comparison_hash' => $hash])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('courses', 0);
        $this->post(route('programs.document-imports.review', $draft), ['decision' => 'approve', 'review_note' => 'Tự duyệt'])->assertForbidden();
        $this->actingAs($manager)->get(route('programs.document-imports.index', ['status' => 'submitted']))->assertOk()->assertSee('approval.docx');
        $this->post(route('programs.document-imports.review', $draft), ['decision' => 'approve', 'review_note' => 'Đã đối chiếu file gốc'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('courses', 0);
        $this->post($apply, ['confirm' => 1, 'comparison_hash' => $hash])->assertForbidden();
        $this->actingAs($owner)->post($apply, ['confirm' => 1, 'comparison_hash' => $hash])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('imported', $draft->fresh()->status);
        $this->assertSame($manager->id, $draft->fresh()->reviewed_by);
        $this->assertSame($owner->id, $draft->fresh()->imported_by);
        $this->assertDatabaseHas('programs', ['code' => 'AI-APP', 'name_vi' => 'Tên từ AI']);
        $this->assertDatabaseCount('courses', 1);
        $this->post($apply, ['confirm' => 1, 'comparison_hash' => $hash])->assertStatus($new ? 404 : 422);
        $this->assertDatabaseHas('curriculum_audits', ['subject_type' => 'document_import', 'subject_id' => $draft->id, 'action' => 'ai_approved', 'actor_id' => $manager->id]);
    }

    public function test_manager_cannot_review_own_ai_import_and_can_return_stale_import(): void
    {
        $this->seed(OfficeRolesSeeder::class);
        $owner = $this->user('Lãnh đạo phòng');
        $reviewer = $this->user('Lãnh đạo phòng');
        $draft = $this->draft($owner);
        $hash = app(CurriculumDraftImporter::class)->comparison($draft)['hash'];
        $this->actingAs($owner)->post(route('programs.document-imports.submit', $draft), ['confirm' => 1, 'comparison_hash' => $hash])->assertRedirect();
        $this->post(route('programs.document-imports.review', $draft), ['decision' => 'approve', 'review_note' => 'Tự duyệt'])->assertForbidden();
        $draft->program->update(['name_vi' => 'Dữ liệu mới của người khác']);
        $this->actingAs($reviewer)->post(route('programs.document-imports.review', $draft), ['decision' => 'approve', 'review_note' => 'Duyệt'])->assertSessionHasErrors('payload');
        $this->post(route('programs.document-imports.review', $draft), ['decision' => 'return', 'review_note' => 'Cần tải và đối chiếu lại'])->assertRedirect();
        $this->assertSame('returned', $draft->fresh()->status);
        $this->assertSame('Dữ liệu mới của người khác', $draft->program->fresh()->name_vi);
    }

    public function test_modified_payload_or_expired_approval_cannot_be_applied(): void
    {
        $this->seed(OfficeRolesSeeder::class);
        $owner = $this->user('Chuyên viên');
        $manager = $this->user('Lãnh đạo phòng');
        $draft = $this->draft($owner);
        $hash = app(CurriculumDraftImporter::class)->comparison($draft)['hash'];
        $this->actingAs($owner)->post(route('programs.document-imports.submit', $draft), ['confirm' => 1, 'comparison_hash' => $hash])->assertRedirect();
        $this->actingAs($manager)->post(route('programs.document-imports.review', $draft), ['decision' => 'approve', 'review_note' => 'Duyệt'])->assertRedirect();
        $original = $draft->fresh()->payload;
        $payload = $original;
        $payload['program']['name_vi'] = 'Sửa sau duyệt';
        $draft->update(['payload' => $payload]);
        $this->actingAs($owner)->post(route('programs.document-imports.import', [$draft->program_id, $draft]), ['confirm' => 1, 'comparison_hash' => $hash])->assertSessionHasErrors('payload');
        $draft->update(['payload' => $original]);
        $this->travel(8)->days();
        $this->post(route('programs.document-imports.import', [$draft->program_id, $draft]), ['confirm' => 1, 'comparison_hash' => $hash])->assertSessionHasErrors('payload');
        $this->assertDatabaseCount('courses', 0);
    }
}
