<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\ProgramExcelImport;
use App\Models\User;
use App\Services\ProgramExcelImportService;
use Database\Seeders\OfficeRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramExcelApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function draft(User $owner): ProgramExcelImport
    {
        $payload = ['program' => ['code' => 'APPROVAL', 'name_vi' => 'CT chờ duyệt', 'degree_level' => 'dai_hoc', 'total_credits' => 3, 'elective_credits_required' => 0, 'review_cycle_years' => 3], 'required_blocks' => ['Đại cương'], 'elective_groups' => [], 'courses' => [['course_code' => 'APP01', 'name_vi' => 'Học phần', 'credits' => 3, 'knowledge_block' => 'Đại cương', 'is_mandatory' => true, 'suggested_semester' => 1, 'sort_order' => 1, 'elective_group_code' => null]], 'plos' => [['code' => 'PLO1', 'description_vi' => 'Chuẩn đầu ra']], 'matrix' => []];

        return ProgramExcelImport::create(['created_by' => $owner->id, 'filename' => 'approval.xlsx', 'program_code' => 'APPROVAL', 'payload' => $payload, 'baseline_hash' => app(ProgramExcelImportService::class)->baseline($payload), 'status' => 'review', 'expires_at' => now()->addHours(2)]);
    }

    public function test_separate_manager_approval_is_required_and_review_is_audited_before_apply(): void
    {
        $this->seed(OfficeRolesSeeder::class);
        $owner = $this->user('Chuyên viên');
        $manager = $this->user('Lãnh đạo phòng');
        $other = $this->user('Chuyên viên');
        $draft = $this->draft($owner);
        $this->actingAs($owner)->post(route('programs.import.apply', $draft), ['confirm' => 1])->assertStatus(422);
        $this->post(route('programs.import.confirm', $draft), ['confirm' => 1])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('submitted', $draft->fresh()->status);
        $this->assertDatabaseCount('programs', 0);
        $this->post(route('programs.import.review', $draft), ['decision' => 'approve', 'review_note' => 'Tự duyệt'])->assertForbidden();
        $this->actingAs($other)->get(route('programs.imports.index'))->assertOk()->assertDontSee('approval.xlsx');
        $this->actingAs($manager)->get(route('programs.imports.index', ['status' => 'submitted']))->assertOk()->assertSee('approval.xlsx');
        $this->get(route('programs.import.preview', $draft))->assertOk()->assertSee('Phê duyệt bản nhập');
        $this->post(route('programs.import.review', $draft), ['decision' => 'approve', 'review_note' => 'Đồng ý bản nhập'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('programs', 0);
        $this->assertDatabaseHas('program_excel_imports', ['id' => $draft->id, 'status' => 'approved', 'reviewed_by' => $manager->id]);
        $this->assertDatabaseHas('curriculum_audits', ['subject_type' => 'program_excel_import', 'subject_id' => $draft->id, 'action' => 'excel_approved', 'actor_id' => $manager->id]);
        $this->actingAs($owner)->post(route('programs.import.apply', $draft), ['confirm' => 1])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('programs', ['code' => 'APPROVAL']);
        $this->post(route('programs.import.apply', $draft), ['confirm' => 1])->assertStatus(422);
    }

    public function test_manager_cannot_approve_own_import_and_returned_import_can_be_resubmitted(): void
    {
        $this->seed(OfficeRolesSeeder::class);
        $manager = $this->user('Lãnh đạo phòng');
        $reviewer = $this->user('Lãnh đạo phòng');
        $draft = $this->draft($manager);
        $this->actingAs($manager)->post(route('programs.import.confirm', $draft), ['confirm' => 1])->assertRedirect();
        $this->post(route('programs.import.review', $draft), ['decision' => 'approve', 'review_note' => 'Tự duyệt'])->assertForbidden();
        $this->actingAs($reviewer)->post(route('programs.import.review', $draft), ['decision' => 'return', 'review_note' => 'Cần kiểm tra hồ sơ'])->assertRedirect();
        $this->assertSame('returned', $draft->fresh()->status);
        $this->actingAs($manager)->get(route('programs.import.preview', $draft))->assertOk()->assertSee('Cần kiểm tra hồ sơ');
        $this->post(route('programs.import.confirm', $draft), ['confirm' => 1])->assertRedirect();
        $this->assertSame('submitted', $draft->fresh()->status);
        $this->assertNull($draft->fresh()->reviewed_by);
        $this->assertDatabaseCount('programs', 0);
    }

    public function test_stale_or_tampered_approved_payload_cannot_be_applied(): void
    {
        $this->seed(OfficeRolesSeeder::class);
        $owner = $this->user('Chuyên viên');
        $manager = $this->user('Lãnh đạo phòng');
        $draft = $this->draft($owner);
        $this->actingAs($owner)->post(route('programs.import.confirm', $draft), ['confirm' => 1])->assertRedirect();
        $this->actingAs($manager)->post(route('programs.import.review', $draft), ['decision' => 'approve', 'review_note' => 'Đồng ý'])->assertRedirect();
        $payload = $draft->fresh()->payload;
        $payload['program']['name_vi'] = 'Tên bị đổi sau duyệt';
        $draft->update(['payload' => $payload]);
        $this->actingAs($owner)->post(route('programs.import.apply', $draft), ['confirm' => 1])->assertStatus(422);
        $this->assertDatabaseCount('programs', 0);
        $draft->update(['payload' => $this->draft($owner)->payload]);
        Program::create(['code' => 'APPROVAL', 'name_vi' => 'Chương trình người khác mới tạo', 'degree_level' => 'dai_hoc', 'total_credits' => 3]);
        $this->post(route('programs.import.apply', $draft), ['confirm' => 1])->assertSessionHasErrors('file');
        $this->assertSame('Chương trình người khác mới tạo', Program::where('code', 'APPROVAL')->firstOrFail()->name_vi);
    }
}
