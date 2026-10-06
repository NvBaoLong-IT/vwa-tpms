<?php

namespace Database\Seeders;

use App\Models\AcademicImport;
use App\Models\AcademicSetting;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\CoursePloMatrix;
use App\Models\CurriculumAudit;
use App\Models\DiplomaBlank;
use App\Models\DiplomaRequest;
use App\Models\IsoFile;
use App\Models\IsoHistory;
use App\Models\IsoWorkflow;
use App\Models\LoginLog;
use App\Models\Program;
use App\Models\ReportHistory;
use App\Models\Student;
use App\Models\StudentProgress;
use App\Models\StudentProgressHistory;
use App\Models\User;
use App\Models\WarningLog;
use App\Models\WebScheduledRun;
use App\Services\AnnualReportService;
use App\Services\DiplomaIssuanceService;
use App\Services\ProgramVersionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

class FullDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('Seeder dữ liệu mẫu chỉ chạy ở môi trường local hoặc testing.');
        }
        if (Role::whereIn('name', ['Lãnh đạo phòng', 'Chuyên viên', 'Xem'])->count() !== 3) {
            $this->call(OfficeRolesSeeder::class);
        }
        DB::transaction(function (): void {
            [$manager, $officer] = $this->accounts();
            $programs = $this->curriculum($manager);
            $this->academic($programs, $officer);
            $this->iso($programs, $manager, $officer);
            $this->diplomas($programs[0], $manager, $officer);
            $this->reports($manager);
        });
    }

    private function accounts(): array
    {
        $users = [];
        foreach ([
            ['long.demo@vwa.local', 'Nguyễn Phi Long', 'Lãnh đạo phòng'],
            ['daotao.demo@vwa.local', 'Chuyên viên Đào tạo Demo', 'Chuyên viên'],
            ['baolonglocal@vwa.local', 'baolonglocal', 'Lãnh đạo phòng'],
            ['xem.demo@vwa.local', 'Tài khoản chỉ xem Demo', 'Xem'],
        ] as [$email, $name, $role]) {
            $user = User::firstOrCreate(['email' => $email], [
                'name' => $name, 'password' => Hash::make('password'),
                'email_verified_at' => now(), 'is_active' => true, 'must_change_password' => false,
            ]);
            if ($user->wasRecentlyCreated) {
                $user->assignRole($role);
            }
            $users[] = $user;
        }

        return [$users[0], $users[1]];
    }

    private function curriculum(User $manager): array
    {
        $programs = [];
        foreach ([
            ['KTS-2026', 'Kinh tế số', 'Digital Economics', 'dai_hoc', 122],
            ['DEMO-CTXH', 'Công tác xã hội (mẫu)', 'Social Work', 'dai_hoc', 120],
            ['DEMO-THS', 'Quản trị kinh doanh (mẫu)', 'Business Administration', 'thac_si', 60],
            ['DEMO-TS', 'Nghiên cứu giới (mẫu)', 'Gender Studies', 'tien_si', 90],
        ] as $index => [$code, $name, $english, $degree, $credits]) {
            $program = Program::firstOrCreate(['code' => $code], [
                'name_vi' => $name, 'name_en' => $english, 'degree_level' => $degree,
                'delivery_mode' => 'Chính quy', 'total_credits' => $credits,
                'decision_number' => 'DEMO-'.($index + 1).'/QĐ', 'decision_date' => today()->startOfYear(),
                'review_cycle_years' => 3, 'is_active' => true,
            ]);
            $programs[] = $program;
            $previous = null;
            foreach ([
                ['101', 'Kiến thức nền tảng', 'Đại cương', 3],
                ['201', 'Phương pháp nghiên cứu', 'Cơ sở ngành', 3],
                ['301', 'Ứng dụng chuyên ngành', 'Chuyên ngành', 4],
                ['401', 'Thực tập và tốt nghiệp', 'Tốt nghiệp', 4],
            ] as $position => [$suffix, $label, $block, $courseCredits]) {
                $courseCode = $index === 0 ? 'KTS'.$suffix : 'DEMO'.($index + 1).$suffix;
                $course = Course::firstOrCreate(['course_code' => $courseCode], [
                    'name_vi' => $label.' - '.$name, 'credits' => $courseCredits,
                    'theory_hours' => 30, 'practice_hours' => 15,
                    'prerequisite_course_id' => $previous?->id, 'is_active' => true,
                ]);
                if (! $program->courses()->whereKey($course->id)->exists()) {
                    $program->courses()->attach($course, ['knowledge_block' => $block, 'is_mandatory' => true, 'suggested_semester' => $position + 1, 'sort_order' => $position + 1]);
                }
                $previous = $course;
                $material = CourseMaterial::firstOrCreate(['course_id' => $course->id, 'title' => 'Giáo trình mẫu: '.$course->course_code], [
                    'material_type' => 'textbook', 'author' => 'Nhóm biên soạn mẫu',
                    'publisher' => 'Nhà xuất bản mẫu', 'library_status' => 'pending',
                ]);
                if ($position === 0) {
                    $material->confirmations()->firstOrCreate(['user_id' => $manager->id, 'note' => 'Phản hồi lịch sử mẫu: giáo trình đã có trong kho.'], ['status' => 'confirmed']);
                    if ($material->wasRecentlyCreated) {
                        $material->update(['library_status' => 'confirmed', 'confirmed_by' => $manager->id, 'confirmed_at' => now()]);
                    }
                }
                foreach ([1 => 'Vận dụng kiến thức chuyên môn.', 2 => 'Phân tích, nghiên cứu và giải quyết vấn đề.', 3 => 'Giao tiếp và làm việc nhóm.'] as $number => $description) {
                    $plo = $program->plos()->firstOrCreate(['code' => 'PLO'.$number], ['description_vi' => $description]);
                    CoursePloMatrix::firstOrCreate(['course_id' => $course->id, 'plo_id' => $plo->id], ['contribution_level' => min(3, $number)]);
                }
            }
            if (! $program->versions()->where('decision_number', 'DEMO-PHIEN-BAN')->exists()) {
                app(ProgramVersionService::class)->capture($program, now()->year, 'DEMO-PHIEN-BAN', today()->toDateString(), $manager->id);
            }
            CurriculumAudit::firstOrCreate(['subject_type' => 'program', 'subject_id' => $program->id, 'action' => 'demo_seed'], [
                'program_id' => $program->id, 'actor_id' => $manager->id, 'actor_name' => $manager->name,
                'new_values' => ['code' => $program->code], 'context' => ['source' => 'FullDemoSeeder', 'message' => 'Dữ liệu trình diễn'],
            ]);
        }
        Course::firstOrCreate(['course_code' => 'DEMO-CHUA-GAN'], ['name_vi' => 'Học phần mẫu chưa gắn chương trình', 'credits' => 2, 'theory_hours' => 30, 'practice_hours' => 0, 'is_active' => false]);

        return $programs;
    }

    private function academic(array $programs, User $officer): void
    {
        foreach (config('academic.warning_thresholds') as $key => $value) {
            foreach ([null, 'dai_hoc', 'thac_si', 'tien_si'] as $degree) {
                AcademicSetting::firstOrCreate(['key' => $key, 'degree_level' => $degree], [
                    'value' => (string) ($key === 'gpa_below' && $degree ? config('academic.gpa_by_degree')[$degree] : $value),
                    'label' => [
                        'gpa_below' => 'Ngưỡng điểm trung bình hệ 4',
                        'credit_debt_above' => 'Ngưỡng nợ tín chỉ',
                        'progress_ratio_below' => 'Ngưỡng tỷ lệ tiến độ',
                        'graduation_lookahead_days' => 'Số ngày dự báo tốt nghiệp',
                        'milestone_lookahead_days' => 'Số ngày nhắc mốc học vụ',
                    ][$key],
                ]);
            }
        }
        foreach ($programs as $group => $program) {
            for ($number = 1; $number <= 4; $number++) {
                $student = Student::firstOrCreate(['student_code' => 'DEMO-SV-'.($group + 1).'-'.$number], [
                    'full_name' => ['Nguyễn Minh Anh', 'Trần Thu Hương', 'Lê Hoàng Nam', 'Phạm Ngọc Hà'][$number - 1].' (mẫu)',
                    'program_id' => $program->id, 'class_code' => 'DEMO-K'.(now()->year - 2),
                    'faculty_name' => 'Khoa đào tạo mẫu', 'advisor_user_id' => $officer->id,
                    'degree_level' => $program->degree_level, 'enrollment_year' => now()->year - 2,
                    'date_of_birth' => '2002-05-15', 'email' => 'sv'.$group.$number.'@demo.vwa.local',
                    'status' => $number === 4 ? 'withdrawn' : 'active',
                    'withdrawn_at' => $number === 4 ? today()->subMonth() : null,
                    'expected_graduation_date' => today()->addMonths(3),
                    'proposal_deadline' => $program->degree_level !== 'dai_hoc' ? today()->addDays(7) : null,
                    'maximum_study_end_date' => $program->degree_level !== 'dai_hoc' ? today()->addDays(25) : null,
                ]);
                foreach ([1, 2] as $semester) {
                    $progress = StudentProgress::firstOrCreate(['student_id' => $student->id, 'semester' => now()->year.'-'.$semester], [
                        'gpa_4' => $number === 1 ? 1.4 : 3.2,
                        'credits_accumulated' => $number === 1 ? 20 + $semester * 5 : 50 + $semester * 5,
                        'credits_required' => 70, 'recorded_at' => today()->subDays(30 - $semester),
                    ]);
                    StudentProgressHistory::firstOrCreate(['student_progress_id' => $progress->id, 'action' => 'created'], [
                        'student_id' => $student->id, 'changed_by' => $officer->id,
                        'new_values' => $progress->only(['semester', 'gpa_4', 'credits_accumulated', 'credits_required']),
                    ]);
                }
                $progress = $student->progress()->orderByDesc('semester')->first();
                $status = ['open', 'processing', 'resolved', 'dismissed'][$number - 1];
                $warning = WarningLog::firstOrCreate(['student_id' => $student->id, 'warning_type' => 'low_gpa', 'reason' => 'Cảnh báo mẫu phục vụ trình diễn quy trình học vụ.'], [
                    'student_progress_id' => $progress->id, 'status' => $status, 'issued_at' => now(),
                    'resolved_by' => in_array($status, ['resolved', 'dismissed'], true) ? $officer->id : null,
                    'resolved_at' => in_array($status, ['resolved', 'dismissed'], true) ? now() : null,
                ]);
                $officer->notifications()->firstOrCreate(['id' => sprintf('00000000-0000-4000-8000-%012d', $warning->id)], [
                    'type' => 'App\\Notifications\\AcademicWarningNotification',
                    'data' => ['message' => 'Thông báo mẫu: cảnh báo học vụ', 'reason' => $warning->reason, 'student_code' => $student->student_code, 'warning_id' => $warning->id],
                    'read_at' => $number > 2 ? now() : null,
                ]);
            }
        }
        foreach (['completed', 'failed', 'queued'] as $status) {
            AcademicImport::firstOrCreate(['original_name' => 'DEMO-tien-do-'.$status.'.csv', 'user_id' => $officer->id], [
                'status' => $status, 'rows_imported' => $status === 'completed' ? 16 : 0,
                'error_count' => $status === 'failed' ? 1 : 0,
                'errors' => $status === 'failed' ? ['Dòng 3: mã sinh viên mẫu không hợp lệ.'] : [],
                'failure_message' => $status === 'failed' ? 'Lỗi mẫu để kiểm tra lịch sử nhập.' : null,
                'completed_at' => $status === 'queued' ? null : now(),
            ]);
        }
    }

    private function iso(array $programs, User $manager, User $officer): void
    {
        foreach (['in_progress', 'completed', 'draft', 'cancelled'] as $index => $status) {
            $workflow = IsoWorkflow::firstOrCreate(['title' => 'DEMO - Rà soát '.$programs[$index]->code], [
                'program_id' => $programs[$index]->id, 'type' => $index === 0 ? 'major' : 'minor',
                'changed_credits' => $index === 0 ? 30 : 6,
                'start_date' => today()->subMonths(2), 'expected_end_date' => today()->addMonths(4),
                'status' => $status, 'note' => 'Đợt rà soát mẫu, không phải hồ sơ nghiệp vụ.',
                'approved_by' => $status === 'completed' ? $manager->id : null,
                'approved_at' => $status === 'completed' ? now() : null,
                'closed_by' => $status === 'completed' ? $manager->id : null,
                'closed_at' => $status === 'completed' ? now() : null,
            ]);
            foreach (['Khảo sát nhu cầu', 'Biên soạn chương trình', 'Thẩm định và phê duyệt', 'Chuẩn bị học liệu'] as $position => $name) {
                $step = $workflow->steps()->firstOrCreate(['step_number' => $position + 1], [
                    'step_name' => $name, 'status' => $status === 'completed' || $position === 0 ? 'completed' : ['completed', 'in_progress', 'waiting_approval', 'waiting_library'][$position],
                    'deadline' => $index === 0 && $position === 1 ? today()->subDays(5) : today()->addDays(($position + 1) * 15),
                    'assigned_to_user_id' => $officer->id, 'note' => 'Bước rà soát mẫu.',
                ]);
                IsoHistory::firstOrCreate(['iso_step_id' => $step->id, 'action' => 'assigned'], [
                    'user_id' => $manager->id, 'new_values' => ['assigned_to_user_id' => $officer->id],
                ]);
            }
            $path = 'demo-seeder/iso-'.$workflow->id.'.pdf';
            $this->samplePdf($path, 'Hồ sơ rà soát mẫu', [['Đợt' => $workflow->title, 'Ghi chú' => 'Tài liệu mẫu phục vụ trình diễn']]);
            IsoFile::firstOrCreate(['iso_workflow_id' => $workflow->id, 'original_name' => 'DEMO-bien-ban-ra-soat.pdf'], ['uploaded_by' => $officer->id, 'path' => $path, 'file_type' => 'application/pdf']);
        }
    }

    private function diplomas(Program $program, User $manager, User $officer): void
    {
        $service = app(DiplomaIssuanceService::class);
        foreach (['issued', 'pending', 'rejected', 'cancelled', 'available'] as $number => $status) {
            $student = Student::firstOrCreate(['student_code' => 'DEMO-TN-'.($number + 1)], [
                'full_name' => 'Người học tốt nghiệp mẫu '.($number + 1), 'program_id' => $program->id,
                'degree_level' => 'dai_hoc', 'status' => 'graduated', 'enrollment_year' => now()->year - 4,
                'date_of_birth' => '2000-06-20', 'graduated_at' => today()->startOfYear()->addMonths(5),
                'expected_graduation_date' => today()->startOfYear()->addMonths(5)->addDays(5), 'class_code' => 'DEMO-TN',
            ]);
            $blank = DiplomaBlank::firstOrCreate(['serial_number' => 'DEMO-PHOI-'.($number + 1)], [
                'type' => 'dai_hoc', 'import_date' => today()->subMonth(), 'status' => 'in_stock', 'note' => 'Phôi mẫu',
            ]);
            if ($blank->wasRecentlyCreated) {
                $service->auditBlank($blank, $officer->id, 'imported', null, ['status' => 'in_stock', 'source' => 'FullDemoSeeder']);
            }
            if ($status === 'available' || DiplomaRequest::where('serial_number', $blank->serial_number)->exists()) {
                continue;
            }
            $request = $service->request([
                'student_id' => $student->id, 'diploma_type' => 'dai_hoc', 'serial_number' => $blank->serial_number,
                'cohort_code' => 'DEMO', 'decision_code' => 'DEMO501', 'issue_date' => today()->subDays(10),
            ], $officer->id);
            if (in_array($status, ['issued', 'cancelled'], true)) {
                $ledger = $service->approve($request, $manager->id);
                if ($status === 'cancelled') {
                    $ledger->update(['status' => 'cancelled']);
                    $ledger->auditLogs()->create(['user_id' => $manager->id, 'action' => 'cancelled', 'old_values' => ['status' => 'issued'], 'new_values' => ['status' => 'cancelled', 'reason' => 'Hủy mẫu để trình diễn lịch sử']]);
                }
            } elseif ($status === 'rejected') {
                $request->update(['status' => 'rejected', 'reviewed_by' => $manager->id, 'reviewed_at' => now(), 'review_note' => 'Yêu cầu mẫu thiếu thông tin quyết định.']);
            }
        }
        foreach (['damaged', 'destroyed'] as $status) {
            $blank = DiplomaBlank::firstOrCreate(['serial_number' => 'DEMO-PHOI-'.$status], ['type' => 'thac_si', 'import_date' => today()->subMonth(), 'status' => $status, 'note' => 'Phôi mẫu '.$status]);
            if ($blank->wasRecentlyCreated) {
                $path = $status === 'destroyed' ? 'demo-seeder/bien-ban-huy-phoi.pdf' : null;
                if ($path) {
                    $this->samplePdf($path, 'Biên bản hủy phôi mẫu', [['Số phôi' => $blank->serial_number, 'Quyết định' => 'DEMO-HUY-01']]);
                }
                $service->auditBlank($blank, $manager->id, $status, ['status' => 'in_stock'], ['status' => $status, 'decision_number' => 'DEMO-HUY-01'], $path);
            }
        }
    }

    private function reports(User $manager): void
    {
        foreach (['academic', 'iso', 'programs', 'plo', 'diploma', 'blanks'] as $type) {
            $path = 'demo-seeder/report-'.$type.'.pdf';
            $this->samplePdf($path, 'Báo cáo mẫu '.$type, [['Ghi chú' => 'Bản lưu mẫu. Xem số liệu hiện tại trên trang Báo cáo.']]);
            ReportHistory::firstOrCreate(['user_id' => $manager->id, 'report_type' => $type, 'file_path' => $path], ['format' => 'pdf', 'row_count' => 1, 'filters' => ['academic_year' => now()->year]]);
        }
        foreach (['academic_year_summary', 'annual_blanks'] as $type) {
            app(AnnualReportService::class)->generate($type, now()->year - 1);
        }
        LoginLog::firstOrCreate(['identifier' => 'DEMO-login-success'], ['user_id' => $manager->id, 'successful' => true, 'ip_address' => '127.0.0.1', 'logged_at' => now()->subDay()]);
        LoginLog::firstOrCreate(['identifier' => 'DEMO-login-failed'], ['successful' => false, 'ip_address' => '127.0.0.1', 'logged_at' => now()->subDay()]);
        WebScheduledRun::firstOrCreate(['run_key' => 'demo:academic:completed'], [
            'task' => 'academic', 'period' => today()->subDay()->toDateString(), 'origin' => 'manual',
            'status' => 'completed', 'triggered_by' => $manager->id,
            'started_at' => now()->subDay()->subMinute(), 'finished_at' => now()->subDay(),
        ]);
    }

    private function samplePdf(string $path, string $title, array $rows): void
    {
        if (! Storage::disk('local')->exists($path)) {
            Storage::disk('local')->put($path, Pdf::loadView('reports.pdf', compact('title', 'rows'))->output());
        }
    }
}
