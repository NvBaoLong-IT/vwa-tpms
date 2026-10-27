<?php

namespace Database\Seeders;

use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Services\CreditRecognitionService;
use App\Services\ProgramExcelImportService;
use App\Services\ProgramVersionService;
use App\Services\StudentCourseResultService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class CurriculumOperationsBrowserSeeder extends Seeder
{
    public function run(): void
    {
        $database = str_replace('\\', '/', (string) config('database.connections.sqlite.database'));
        if (! app()->environment('testing') || config('database.default') !== 'sqlite' || ! str_starts_with($database, str_replace('\\', '/', storage_path('framework/testing/'))) || ! str_contains(basename($database), 'curriculum-browser-')) {
            throw new RuntimeException('Chỉ chạy dữ liệu demo trong SQLite kiểm thử riêng.');
        }
        $this->call(CurriculumRecognitionBrowserSeeder::class);
        $officer = User::where('email', 'officer@curriculum.test')->firstOrFail();
        $program = Program::where('code', 'DEMO-CREDIT')->firstOrFail();
        $source = $program->versions()->firstOrFail();
        $program->update(['course_substitutions' => [[
            'id' => 'demo-operation-rule', 'kind' => 'replacement', 'source_version_id' => $source->id, 'source_course_codes' => ['OLD-A', 'OLD-B'], 'target_courses' => [['course_code' => 'NEW-A', 'credits' => 3], ['course_code' => 'NEW-B', 'credits' => 3]], 'source_course_code' => 'OLD-A + OLD-B', 'target_course_code' => 'NEW-A + NEW-B', 'recognized_credits' => 6, 'decision_number' => 'DEMO-GROUP/QD', 'note' => 'Quy tắc nhóm dùng cho kiểm thử', 'source_cohort_year' => 2025, 'source_program_code' => 'DEMO-CREDIT', 'source_revision' => 1,
        ]]]);
        $versions = app(ProgramVersionService::class);
        $target = $versions->capture($program, 2026, 'DEMO-NEW/QD', '2026-01-01', $officer->id);
        $target->update(['status' => 'published', 'effective_from' => '2026-01-01', 'published_at' => now()]);
        $pending = $versions->capture($program, 2027, 'DEMO-CHO-DUYET/QD', '2026-01-01', $officer->id);
        $versions->submit($pending, ['effective_from' => '2027-01-01'], $officer->id);
        $student = Student::where('student_code', 'DEMO-SV01')->firstOrFail();
        $results = [];
        foreach (['OLD-A', 'OLD-B'] as $code) {
            $results[] = app(StudentCourseResultService::class)->record($student, ['program_version_id' => $source->id, 'course_code' => $code, 'semester' => '2025-1', 'outcome' => 'passed', 'evidence' => 'Bảng điểm DEMO 01'], $officer->id)->id;
        }
        app(CreditRecognitionService::class)->submit($student, $target, ['result_ids' => $results, 'rule_id' => 'demo-operation-rule', 'decision_number' => 'DEMO-CN-CHO-DUYET', 'note' => 'Hồ sơ demo chờ lãnh đạo xét duyệt'], $officer->id);
        $import = app(ProgramExcelImportService::class)->preview(new UploadedFile(storage_path('framework/testing/curriculum-browser-import.xlsx'), 'DEMO-Excel-cho-duyet.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true), $officer->id);
        app(ProgramExcelImportService::class)->submit($import, $officer->id);
        Student::create(['student_code' => 'DEMO-SV02', 'full_name' => 'DEMO - Sinh viên nhập kết quả hàng loạt', 'program_id' => $program->id, 'enrollment_year' => 2026, 'degree_level' => 'dai_hoc']);
        file_put_contents(storage_path('framework/testing/course-result-browser.csv'), "\xEF\xBB\xBFMaCTDT,Khoa,LanSua,MaHocPhan,HocKy,KetQua,CanCu\nDEMO-CREDIT,2025,1,OLD-A,2025-1,Dat,Bang diem DEMO 01\nDEMO-CREDIT,2025,1,OLD-B,2025-1,Dat,Bang diem DEMO 01\n");
    }
}
