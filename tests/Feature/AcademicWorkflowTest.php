<?php

namespace Tests\Feature;

use App\Jobs\ImportAcademicProgressJob;
use App\Models\AcademicImport;
use App\Models\AcademicSetting;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentProgress;
use App\Models\User;
use App\Notifications\AcademicImportFailedNotification;
use App\Notifications\AcademicWarningNotification;
use App\Services\AcademicWarningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AcademicWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $role = Role::create(['name' => 'Học vụ workflow', 'guard_name' => 'web']);
        $role->syncPermissions(collect(['academic.view', 'academic.import', 'academic.process', 'reports.export'])->map(fn ($p) => Permission::findOrCreate($p, 'web'))->all());
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_credit_debt_requires_two_consecutive_semesters_and_undergraduate_gpa_uses_1_6(): void
    {
        $this->user();
        $student = Student::create(['student_code' => 'DEBT-01', 'full_name' => 'Kiểm tra nợ tín chỉ', 'degree_level' => 'dai_hoc', 'status' => 'active']);
        StudentProgress::create(['student_id' => $student->id, 'semester' => '2025-2', 'credits_accumulated' => 20, 'credits_required' => 40, 'gpa_4' => 1.7]);
        app(AcademicWarningService::class)->scan();
        $this->assertDatabaseMissing('warning_logs', ['student_id' => $student->id, 'warning_type' => 'credit_debt']);
        $this->assertDatabaseMissing('warning_logs', ['student_id' => $student->id, 'warning_type' => 'low_gpa']);

        StudentProgress::create(['student_id' => $student->id, 'semester' => '2026-1', 'credits_accumulated' => 40, 'credits_required' => 60, 'gpa_4' => 1.7]);
        app(AcademicWarningService::class)->scan();

        $this->assertDatabaseHas('warning_logs', ['student_id' => $student->id, 'warning_type' => 'credit_debt']);
    }

    public function test_postgraduate_milestones_notify_until_completed(): void
    {
        $user = $this->user();
        $student = Student::create(['student_code' => 'MASTER-01', 'full_name' => 'Học viên', 'degree_level' => 'thac_si', 'status' => 'active']);
        $this->travelTo('2026-09-29');

        $this->actingAs($user)->put(route('academic.milestones.update', $student), ['proposal_deadline' => '2026-10-10', 'department_defense_deadline' => '2026-10-20', 'maximum_study_end_date' => '2026-10-01'])->assertRedirect();

        $this->assertDatabaseHas('warning_logs', ['student_id' => $student->id, 'warning_type' => 'proposal_due']);
        $this->assertDatabaseHas('warning_logs', ['student_id' => $student->id, 'warning_type' => 'department_defense_due']);
        $this->assertDatabaseHas('warning_logs', ['student_id' => $student->id, 'warning_type' => 'maximum_study_due']);
        $this->actingAs($user)->get(route('academic.student', $student))->assertSee('Hạn nộp đề cương');
        $this->get(route('academic.index'))->assertOk()->assertSee('Hạn đề cương');
        $this->get(route('academic.export.word'))->assertOk()->assertHeader('content-type', 'application/msword; charset=UTF-8')->assertSee('MASTER-01');
        $this->actingAs($user)->put(route('academic.milestones.update', $student), ['proposal_submitted_at' => '2026-09-29'])->assertRedirect();
        $this->assertDatabaseHas('warning_logs', ['student_id' => $student->id, 'warning_type' => 'proposal_due', 'status' => 'resolved']);
    }

    public function test_filters_assignment_history_and_degree_thresholds_are_applied(): void
    {
        Notification::fake();
        $user = $this->user();
        $advisor = User::factory()->create(['name' => 'Cố vấn A']);
        $program = Program::create(['code' => 'AW-DH', 'name_vi' => 'Đại học', 'degree_level' => 'dai_hoc', 'total_credits' => 120]);
        AcademicSetting::create(['key' => 'gpa_below', 'degree_level' => 'dai_hoc', 'value' => '1.8', 'label' => 'GPA ĐH']);
        $student = Student::create(['student_code' => 'AW-01', 'full_name' => 'Nguyễn A', 'program_id' => $program->id, 'class_code' => 'K1', 'degree_level' => 'dai_hoc', 'faculty_name' => 'Khoa A', 'advisor_user_id' => $advisor->id]);
        $progress = StudentProgress::create(['student_id' => $student->id, 'semester' => '2026-1', 'gpa_4' => 1.7, 'credits_accumulated' => 20, 'credits_required' => 60]);
        $this->actingAs($user)->post(route('academic.scan'))->assertRedirect();
        $this->assertDatabaseHas('warning_logs', ['student_id' => $student->id, 'warning_type' => 'low_gpa']);
        Notification::assertSentTo($advisor, AcademicWarningNotification::class);
        $this->actingAs($user)->get(route('academic.index', ['program_id' => $program->id, 'class_code' => 'K1', 'semester' => '2026-1', 'warning_type' => 'low_gpa', 'status' => 'open']))->assertOk()->assertSee('AW-01')->assertSee('Khoa A');
        $this->actingAs($user)->put(route('academic.progress.update', $student), ['semester' => '2026-1', 'credits_accumulated' => 25, 'credits_required' => 60, 'gpa_4' => 2.2])->assertRedirect();
        $this->assertDatabaseHas('student_progress_histories', ['student_progress_id' => $progress->id, 'action' => 'updated']);
        $this->actingAs($user)->get(route('academic.student', $student))->assertOk()->assertSee('Lịch sử tiến độ');
    }

    public function test_import_report_contains_errors_and_failure_notifies_user(): void
    {
        Storage::fake('local');
        Notification::fake();
        $user = $this->user();
        $import = AcademicImport::create(['user_id' => $user->id, 'original_name' => 'loi.csv', 'status' => 'completed', 'rows_imported' => 2, 'error_count' => 1, 'errors' => ['Dòng 4: Điểm trung bình hệ 4 phải từ 0 đến 4']]);
        $this->actingAs($user)->get(route('academic.import.report', $import))->assertOk()->assertSee('Dòng 4');
        $this->actingAs($user)->get(route('academic.import.errors', $import))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8')->assertSee('Điểm trung bình hệ 4 phải từ 0 đến 4');
        $batch = AcademicImport::create(['user_id' => $user->id, 'original_name' => 'fail.csv', 'status' => 'queued']);
        $job = new ImportAcademicProgressJob('missing/file.csv', $user->id, $batch->id);
        try {
            $job->handle(app(AcademicWarningService::class));
        } catch (\Throwable) {
        }
        $this->assertDatabaseHas('academic_imports', ['id' => $batch->id, 'status' => 'failed']);
        Notification::assertSentTo($user, AcademicImportFailedNotification::class);
    }

    public function test_large_csv_is_accepted_and_queued(): void
    {
        Queue::fake();
        $user = $this->user();
        $rows = "MaSV,HoTen,MaNganh,HocKy,TinChiTichLuy,GPA,TinChiYeuCau\n";
        for ($i = 1; $i <= 1200; $i++) {
            $rows .= "LARGE{$i},Sinh Vien {$i},AW-LARGE,2026-1,30,2.5,60\n";
        }
        $file = UploadedFile::fake()->createWithContent('large.csv', $rows);
        $this->actingAs($user)->post(route('academic.import'), ['file' => $file])->assertRedirect(route('academic.index'));
        Queue::assertPushed(ImportAcademicProgressJob::class);
        $this->assertDatabaseHas('academic_imports', ['original_name' => 'large.csv', 'status' => 'queued']);
    }

    public function test_large_and_invalid_files_are_processed_with_row_report(): void
    {
        Storage::fake('local');
        Notification::fake();
        $user = $this->user();
        Program::create(['code' => 'AW-LARGE', 'name_vi' => 'Ngành lớn', 'degree_level' => 'dai_hoc', 'total_credits' => 120]);
        $rows = "MaSV,HoTen,MaNganh,HocKy,TinChiTichLuy,GPA,TinChiYeuCau\n";
        for ($i = 1; $i <= 1200; $i++) {
            $rows .= "RUN{$i},Sinh Vien {$i},AW-LARGE,2026-1,60,3.0,60\n";
        }
        Storage::disk('local')->put('large-run.csv', $rows);
        $batch = AcademicImport::create(['user_id' => $user->id, 'original_name' => 'large-run.csv', 'status' => 'queued']);
        (new ImportAcademicProgressJob('large-run.csv', $user->id, $batch->id))->handle(app(AcademicWarningService::class));
        $this->assertDatabaseHas('academic_imports', ['id' => $batch->id, 'status' => 'completed', 'rows_imported' => 1200]);
        $this->assertDatabaseCount('students', 1200);

        Storage::disk('local')->put('bad-run.csv', "MaSV,HoTen,MaNganh,HocKy,TinChiTichLuy,GPA,TinChiYeuCau\nBAD1,Sai,AW-LARGE,2026-1,20,9.0,60\n");
        $bad = AcademicImport::create(['user_id' => $user->id, 'original_name' => 'bad-run.csv', 'status' => 'queued']);
        (new ImportAcademicProgressJob('bad-run.csv', $user->id, $bad->id))->handle(app(AcademicWarningService::class));
        $this->assertDatabaseHas('academic_imports', ['id' => $bad->id, 'status' => 'completed', 'error_count' => 1]);
        $this->assertDatabaseMissing('students', ['student_code' => 'BAD1']);
    }

    public function test_progress_import_preserves_graduated_status_and_kpi_date(): void
    {
        Storage::fake('local');
        $user = $this->user();
        $program = Program::create(['code' => 'AW-GRAD', 'name_vi' => 'Ngành tốt nghiệp', 'degree_level' => 'dai_hoc', 'total_credits' => 120]);
        $student = Student::create(['student_code' => 'KEEP-GRAD', 'full_name' => 'Người đã tốt nghiệp', 'program_id' => $program->id, 'status' => 'graduated', 'graduated_at' => '2026-08-30']);
        Storage::disk('local')->put('graduated.csv', "MaSV,HoTen,MaNganh,HocKy,TinChiTichLuy,GPA,TinChiYeuCau\nKEEP-GRAD,Người đã tốt nghiệp,AW-GRAD,2026-1,120,3.0,120\n");
        $batch = AcademicImport::create(['user_id' => $user->id, 'original_name' => 'graduated.csv', 'status' => 'queued']);

        (new ImportAcademicProgressJob('graduated.csv', $user->id, $batch->id))->handle(app(AcademicWarningService::class));

        $this->assertDatabaseHas('students', ['id' => $student->id, 'status' => 'graduated']);
        $this->assertSame('2026-08-30', $student->fresh()->graduated_at->format('Y-m-d'));
    }
}
