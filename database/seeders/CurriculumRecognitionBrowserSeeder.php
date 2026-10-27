<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\DocumentImport;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Services\ProgramVersionService;
use Illuminate\Database\Seeder;
use RuntimeException;

class CurriculumRecognitionBrowserSeeder extends Seeder
{
    public function run(): void
    {
        $database = str_replace('\\', '/', (string) config('database.connections.sqlite.database'));
        if (! app()->environment('testing') || config('database.default') !== 'sqlite' || ! str_starts_with($database, str_replace('\\', '/', storage_path('framework/testing/'))) || ! str_contains(basename($database), 'curriculum-browser-')) {
            throw new RuntimeException('Chỉ chạy dữ liệu mẫu trong SQLite kiểm thử riêng.');
        }
        $this->call(CurriculumBrowserDemoSeeder::class);
        $user = User::where('email', 'officer@curriculum.test')->firstOrFail();
        $program = Program::create(['code' => 'DEMO-CREDIT', 'name_vi' => 'DEMO - CTĐT công nhận tín chỉ', 'degree_level' => 'dai_hoc', 'total_credits' => 6, 'elective_credits_required' => 0, 'review_cycle_years' => 3, 'required_knowledge_blocks' => ['Đại cương']]);
        foreach (['OLD-A', 'OLD-B'] as $code) {
            $course = Course::create(['course_code' => $code, 'name_vi' => 'DEMO - '.$code, 'credits' => 3]);
            $program->courses()->attach($course, ['knowledge_block' => 'Đại cương', 'is_mandatory' => true]);
        }
        $source = app(ProgramVersionService::class)->capture($program, 2025, 'DEMO-OLD/QD', '2025-01-01', $user->id);
        $source->update(['status' => 'published', 'effective_from' => '2025-01-01', 'published_at' => now()]);
        $program->courses()->detach();
        foreach (['NEW-A', 'NEW-B'] as $code) {
            $course = Course::create(['course_code' => $code, 'name_vi' => 'DEMO - '.$code, 'credits' => 3]);
            $program->courses()->attach($course, ['knowledge_block' => 'Đại cương', 'is_mandatory' => true]);
        }
        Student::create(['student_code' => 'DEMO-SV01', 'full_name' => 'DEMO - Sinh viên công nhận tín chỉ', 'program_id' => $program->id, 'enrollment_year' => 2026, 'degree_level' => 'dai_hoc', 'status' => 'active']);
        DocumentImport::create(['program_id' => $program->id, 'uploaded_by' => $user->id, 'status' => 'review', 'original_name' => 'DEMO-AI.docx', 'path' => 'demo-not-a-real-file.docx', 'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'payload' => ['program' => ['name_vi' => 'DEMO - CTĐT công nhận tín chỉ sau AI'], 'plos' => [], 'matrix' => [], 'warnings' => [], 'courses' => [['course_code' => 'NEW-A', 'name_vi' => 'DEMO - NEW-A sửa từ bản nháp AI', 'credits' => 3, 'knowledge_block' => 'Đại cương', 'is_mandatory' => true, 'suggested_semester' => 1]]]]);
    }
}
