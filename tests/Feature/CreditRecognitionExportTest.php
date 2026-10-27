<?php

namespace Tests\Feature;

use App\Models\CreditRecognition;
use App\Models\CurriculumAudit;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\OfficeRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CreditRecognitionExportTest extends TestCase
{
    use RefreshDatabase;

    private function dossier(): array
    {
        $this->seed(OfficeRolesSeeder::class);
        $user = User::factory()->create(['name' => 'Nguyễn Văn Kiểm Thử']);
        $user->assignRole('Lãnh đạo phòng');
        $officer = User::factory()->create(['name' => 'Chuyên viên lập hồ sơ']);
        $officer->assignRole('Chuyên viên');
        $program = Program::create(['code' => 'PDF-CT', 'name_vi' => 'Chương trình đối chiếu tín chỉ', 'degree_level' => 'dai_hoc', 'total_credits' => 6]);
        $version = $program->versions()->create(['cohort_year' => 2026, 'revision' => 1, 'decision_number' => '01/QĐ-HV', 'decision_date' => '2026-01-01', 'status' => 'published', 'snapshot' => ['program' => ['code' => 'PDF-CT', 'name_vi' => 'Chương trình đối chiếu tín chỉ'], 'courses' => [['course_code' => 'DICH1', 'name_vi' => 'Phương pháp nghiên cứu khoa học'], ['course_code' => 'DICH2', 'name_vi' => 'Ứng dụng nghiệp vụ']], 'substitutions' => []]]);
        $student = Student::create(['student_code' => 'SV-PDF', 'full_name' => 'Trần Thị Sinh Viên Mẫu', 'program_id' => $program->id, 'enrollment_year' => 2026, 'degree_level' => 'dai_hoc']);
        $record = CreditRecognition::create(['student_id' => $student->id, 'program_version_id' => $version->id, 'request_data' => [], 'allocation' => ['results' => [['id' => 1, 'program_version_id' => $version->id, 'course_code' => 'NGUON1', 'semester' => '2025-2', 'credits' => 3, 'outcome' => 'passed', 'evidence' => 'Bảng điểm xác nhận số 01, ngày 01/09/2026'], ['id' => 2, 'program_version_id' => $version->id, 'course_code' => 'NGUON2', 'semester' => '2025-2', 'credits' => 3, 'outcome' => 'exempt', 'evidence' => 'Quyết định miễn học số 02']], 'targets' => [['course_code' => 'DICH1', 'credits' => 3], ['course_code' => 'DICH2', 'credits' => 3]], 'source_keys' => ['1:NGUON1', '1:NGUON2']], 'baseline_hash' => str_repeat('a', 64), 'status' => 'approved', 'decision_number' => '15/QĐ-CN', 'note' => 'Đối chiếu kết quả học tập và căn cứ công nhận theo chương trình đã ban hành.', 'submitted_by' => $officer->id, 'reviewed_by' => $user->id, 'reviewed_at' => now(), 'review_note' => 'Đã kiểm tra các học phần nguồn và giới hạn phân bổ tín chỉ. Đồng ý công nhận.']);
        CurriculumAudit::create(['actor_id' => $user->id, 'actor_name' => $user->name, 'subject_type' => 'credit_recognition', 'subject_id' => $record->id, 'program_id' => $program->id, 'action' => 'recognition_approved', 'old_values' => ['status' => 'submitted'], 'new_values' => ['status' => 'approved', 'review_note' => $record->review_note]]);
        $this->actingAs($user);

        return [$user, $student, $record];
    }

    public function test_pdf_exports_each_status_and_preserves_privacy_headers(): void
    {
        [$user, $student, $record] = $this->dossier();
        File::ensureDirectoryExists(storage_path('framework/testing/evidence'));
        foreach (['submitted', 'approved', 'revoked'] as $status) {
            $record->update(['status' => $status, 'reviewed_by' => $status === 'submitted' ? null : $user->id, 'reviewed_at' => $status === 'submitted' ? null : now(), 'review_note' => $status === 'submitted' ? null : 'Đã kiểm tra, đủ căn cứ công nhận.']);
            CurriculumAudit::where('subject_type', 'credit_recognition')->where('subject_id', $record->id)->delete();
            CurriculumAudit::create(['actor_id' => $user->id, 'actor_name' => $user->name, 'subject_type' => 'credit_recognition', 'subject_id' => $record->id, 'program_id' => $record->version->program_id, 'action' => $status === 'submitted' ? 'recognition_submitted' : ($status === 'approved' ? 'recognition_approved' : 'recognition_revoked'), 'new_values' => ['status' => $status, 'reason' => $status === 'revoked' ? 'Căn cứ bảng điểm cần đính chính, thu hồi để kiểm tra lại.' : 'Hồ sơ đang được xử lý theo quy trình.']]);
            $response = $this->get(route('academic.recognitions.export', [$student, $record, 'format' => 'pdf']));
            $response->assertOk()->assertDownload('ho-so-cong-nhan-'.$record->id.'-'.$status.'.pdf')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            $this->assertStringStartsWith('%PDF-', $response->getContent());
            file_put_contents(storage_path('framework/testing/evidence/recognition-'.$status.'.pdf'), $response->getContent());
        }
        $this->assertSame('revoked', $record->fresh()->status);
        $this->assertDatabaseCount('credit_recognitions', 1);
    }

    public function test_csv_escapes_formulas_and_export_requires_permission_and_matching_student(): void
    {
        [$user, $student, $record] = $this->dossier();
        $record->update(['note' => '=HYPERLINK("https://example.test")']);
        $response = $this->get(route('academic.recognitions.export', [$student, $record, 'format' => 'csv']))->assertOk()->assertSee('Đã công nhận')->assertSee('DICH1')->assertSee('NGUON1');
        $this->assertStringContainsString("'=HYPERLINK", $response->getContent());
        $other = Student::create(['student_code' => 'OTHER', 'full_name' => 'Khác']);
        $this->get(route('academic.recognitions.export', [$other, $record, 'format' => 'csv']))->assertNotFound();
        $restricted = User::factory()->create();
        foreach (['students.manage', 'programs.view'] as $permission) {
            $restricted->givePermissionTo($permission);
        }
        $this->actingAs($restricted)->get(route('academic.recognitions.export', [$student, $record, 'format' => 'csv']))->assertForbidden();
        $viewer = User::factory()->create();
        $viewer->assignRole('Xem');
        $this->actingAs($viewer)->get(route('academic.recognitions.export', [$student, $record, 'format' => 'pdf']))->assertForbidden();
    }
}
