<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseResultImport;
use App\Models\DocumentImport;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Services\CurriculumDraftImporter;
use App\Services\CurriculumSnapshotDiffer;
use App\Services\ProgramVersionService;
use Database\Seeders\OfficeRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CurriculumIntegrityReviewTest extends TestCase
{
    use RefreshDatabase;

    private function users(): array
    {
        $this->seed(OfficeRolesSeeder::class);
        $officer = User::factory()->create();
        $officer->assignRole('Chuyên viên');
        $manager = User::factory()->create();
        $manager->assignRole('Lãnh đạo phòng');
        $this->actingAs($officer);

        return [$officer, $manager];
    }

    private function program(string $code, int $userId): array
    {
        $program = Program::create(['code' => $code, 'name_vi' => 'CT '.$code, 'degree_level' => 'dai_hoc', 'total_credits' => 3]);
        $course = Course::create(['course_code' => $code.'HP', 'name_vi' => 'HP '.$code, 'credits' => 3]);
        $program->courses()->attach($course, ['knowledge_block' => 'Đại cương', 'is_mandatory' => true]);
        $version = app(ProgramVersionService::class)->capture($program, 2026, '01/QĐ', '2026-01-01', $userId);
        $version->update(['status' => 'published', 'effective_from' => '2026-01-01']);

        return [$program, $course, $version];
    }

    public function test_replacement_rejects_other_program_and_unpublished_source_without_writing(): void
    {
        [$user] = $this->users();
        [$a, $sourceCourse, $source] = $this->program('A', $user->id);
        [$b, $target] = $this->program('B', $user->id);
        $input = ['source_version_id' => $source->id, 'source_codes' => $sourceCourse->course_code, 'target_lines' => $target->course_code.'=3', 'decision_number' => '01/CN', 'note' => 'Sai nguồn'];
        $this->post(route('programs.substitutions.store', $b), $input)->assertSessionHasErrors('substitution');
        $this->assertEmpty($b->fresh()->course_substitutions);
        foreach (['draft', 'returned'] as $status) {
            $source->update(['status' => $status]);
            $this->post(route('programs.substitutions.store', $a), array_merge($input, ['target_lines' => $sourceCourse->course_code.'=3']))->assertSessionHasErrors('substitution');
            $this->assertEmpty($a->fresh()->course_substitutions);
        }
        $this->assertDatabaseMissing('curriculum_audits', ['action' => 'substitution_updated']);
    }

    public function test_manual_and_csv_results_cannot_use_another_program_of_the_same_degree(): void
    {
        [$user] = $this->users();
        [$a] = $this->program('A', $user->id);
        [$b, $course, $version] = $this->program('B', $user->id);
        $student = Student::create(['student_code' => 'SV-A', 'full_name' => 'Sinh viên A', 'program_id' => $a->id, 'degree_level' => 'dai_hoc', 'enrollment_year' => 2026]);
        $this->post(route('academic.course-results.store', $student), ['program_version_id' => $version->id, 'course_code' => $course->course_code, 'semester' => '2026-1', 'outcome' => 'passed', 'evidence' => 'Bảng điểm'])->assertSessionHasErrors('result');
        $csv = "MaCTDT,Khoa,LanSua,MaHocPhan,HocKy,KetQua,CanCu\nB,2026,1,BHP,2026-1,Dat,Bang diem\n";
        $this->post(route('academic.course-results.import.store', $student), ['file' => UploadedFile::fake()->createWithContent('wrong.csv', $csv)])->assertRedirect();
        $import = CourseResultImport::firstOrFail();
        $this->assertCount(1, $import->errors);
        $this->post(route('academic.course-results.import.confirm', [$student, $import]), ['confirm' => 1])->assertStatus(422);
        $this->assertDatabaseCount('student_course_results', 0);
        $this->get(route('academic.credits', $student))->assertOk()->assertDontSee('B · K2026');
    }

    private function draft(User $user): DocumentImport
    {
        $program = Program::create(['code' => 'AI-GROUP', 'name_vi' => 'AI nhóm', 'degree_level' => 'dai_hoc', 'total_credits' => 3, 'elective_credits_required' => 3]);

        return DocumentImport::create(['program_id' => $program->id, 'uploaded_by' => $user->id, 'original_name' => 'groups.docx', 'path' => 'groups.docx', 'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'status' => 'review', 'payload' => [
            'program' => ['total_credits' => 3, 'elective_credits_required' => 3],
            'knowledge_blocks' => ['Chuyên ngành'],
            'elective_groups' => [['code' => 'A', 'name' => 'Nhóm A', 'knowledge_block' => 'Chuyên ngành', 'credits_required' => 3]],
            'courses' => [['course_code' => 'AI-TC', 'name_vi' => 'Tự chọn', 'credits' => 3, 'knowledge_block' => 'Chuyên ngành', 'is_mandatory' => false, 'elective_group_code' => 'A', 'suggested_semester' => 1]], 'plos' => [], 'matrix' => [],
        ]]);
    }

    public function test_ai_preserves_required_blocks_elective_groups_and_assignment_through_approval(): void
    {
        [$user,$manager] = $this->users();
        $draft = $this->draft($user);
        $hash = app(CurriculumDraftImporter::class)->comparison($draft)['hash'];
        $this->post(route('programs.document-imports.submit', $draft), ['confirm' => 1, 'comparison_hash' => $hash])->assertSessionHasNoErrors()->assertRedirect();
        $this->actingAs($manager)->post(route('programs.document-imports.review', $draft), ['decision' => 'approve', 'review_note' => 'Đủ chỉ tiêu'])->assertSessionHasNoErrors()->assertRedirect();
        $this->actingAs($user)->post(route('programs.document-imports.import', [$draft->program_id, $draft]), ['confirm' => 1, 'comparison_hash' => $hash])->assertSessionHasNoErrors()->assertRedirect();
        $program = $draft->program->fresh();
        $this->assertSame(['Chuyên ngành'], $program->required_knowledge_blocks);
        $this->assertSame(3, $program->elective_groups[0]['credits_required']);
        $this->assertSame('A', $program->courses->first()->pivot->elective_group_code);
    }

    public function test_ai_credit_errors_block_submission_and_owner_can_correct_draft_but_not_submitted_payload(): void
    {
        [$user,$manager] = $this->users();
        $draft = $this->draft($user);
        $payload = $draft->payload;
        $payload['program']['total_credits'] = 9;
        $draft->update(['payload' => $payload]);
        $hash = app(CurriculumDraftImporter::class)->comparison($draft)['hash'];
        $this->post(route('programs.document-imports.submit', $draft), ['confirm' => 1, 'comparison_hash' => $hash])->assertSessionHasErrors('payload');
        $this->assertSame('review', $draft->fresh()->status);
        $this->assertDatabaseCount('courses', 0);
        $edit = ['payload_hash' => app(CurriculumSnapshotDiffer::class)->fingerprint($payload), 'draft_program' => ['name_vi' => 'AI đã sửa', 'total_credits' => 3, 'elective_credits_required' => 3], 'blocks_text' => 'Chuyên ngành', 'groups_text' => 'A | Nhóm A | Chuyên ngành | 3', 'draft_courses' => $payload['courses']];
        $this->actingAs($manager)->put(route('programs.document-imports.draft', $draft), $edit)->assertForbidden();
        $this->actingAs($user)->put(route('programs.document-imports.draft', $draft), $edit)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('courses', 0);
        $this->assertSame(3, (int) $draft->fresh()->payload['program']['total_credits']);
        $this->get(route('programs.document-imports.show', [$draft->program_id, $draft]))->assertOk();
        $this->post(route('programs.document-imports.submit', $draft), ['confirm' => 1, 'comparison_hash' => $draft->fresh()->comparison_hash])->assertSessionHasNoErrors()->assertRedirect();
        $edit['payload_hash'] = app(CurriculumSnapshotDiffer::class)->fingerprint($draft->fresh()->payload);
        $this->put(route('programs.document-imports.draft', $draft), $edit)->assertStatus(422);
        $this->assertDatabaseHas('curriculum_audits', ['subject_type' => 'document_import', 'action' => 'ai_draft_edited']);
    }
}
