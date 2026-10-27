<?php

namespace Database\Seeders;

use App\Models\DocumentImport;
use App\Models\Program;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class CurriculumReviewFixBrowserSeeder extends Seeder
{
    public function run(): void
    {
        $database = str_replace('\\', '/', (string) config('database.connections.sqlite.database'));
        if (! app()->environment('testing') || config('database.default') !== 'sqlite' || ! str_starts_with($database, str_replace('\\', '/', storage_path('framework/testing/'))) || ! str_contains(basename($database), 'curriculum-browser-')) {
            throw new RuntimeException('Chỉ tạo bản nháp demo trong SQLite kiểm thử riêng.');
        }
        $user = User::where('email', 'officer@curriculum.test')->firstOrFail();
        $program = Program::create(['code' => 'DEMO-REVIEW-FIX', 'name_vi' => 'DEMO - Sửa bản nháp kiểm soát tín chỉ', 'degree_level' => 'dai_hoc', 'total_credits' => 9, 'elective_credits_required' => 3]);
        DocumentImport::create(['program_id' => $program->id, 'uploaded_by' => $user->id, 'original_name' => 'DEMO-KIEM-TRA-TIN-CHI.docx', 'path' => 'demo-only.docx', 'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'status' => 'review', 'payload' => [
            'program' => ['code' => $program->code, 'name_vi' => $program->name_vi, 'degree_level' => 'dai_hoc', 'total_credits' => 9, 'elective_credits_required' => 3],
            'knowledge_blocks' => ['Chuyên ngành'], 'elective_groups' => [['code' => 'TC_A', 'name' => 'Nhóm tự chọn A', 'knowledge_block' => 'Chuyên ngành', 'credits_required' => 3]],
            'courses' => [['course_code' => 'DEMO-TC-FIX', 'name_vi' => 'Học phần tự chọn demo', 'credits' => 3, 'knowledge_block' => 'Chuyên ngành', 'is_mandatory' => false, 'elective_group_code' => 'TC_A', 'suggested_semester' => 1]], 'plos' => [], 'matrix' => [], 'warnings' => [],
        ]]);
    }
}
