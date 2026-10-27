<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CreditRecognition;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentCourseResult;
use App\Models\User;
use App\Services\CreditRecognitionService;
use App\Services\ProgramVersionService;
use Database\Seeders\OfficeRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditRecognitionTest extends TestCase
{
    use RefreshDatabase;

    private function setupCurriculum(): array
    {
        $this->seed(OfficeRolesSeeder::class);
        $officer = User::factory()->create();
        $officer->assignRole('Chuyên viên');
        $manager = User::factory()->create();
        $manager->assignRole('Lãnh đạo phòng');
        $this->actingAs($officer);
        $program = Program::create(['code' => 'RECOG', 'name_vi' => 'CT công nhận', 'degree_level' => 'dai_hoc', 'total_credits' => 6]);
        foreach (['OLD1', 'OLD2'] as $code) {
            $course = Course::create(['course_code' => $code, 'name_vi' => $code, 'credits' => 3]);
            $program->courses()->attach($course, ['knowledge_block' => 'Đại cương', 'is_mandatory' => true]);
        }
        $source = app(ProgramVersionService::class)->capture($program, 2025, '01/QĐ', '2025-01-01', $officer->id);
        $source->update(['status' => 'published', 'effective_from' => '2025-01-01']);
        $program->courses()->detach();
        foreach (['NEW1', 'NEW2'] as $code) {
            $course = Course::create(['course_code' => $code, 'name_vi' => $code, 'credits' => 3]);
            $program->courses()->attach($course, ['knowledge_block' => 'Đại cương', 'is_mandatory' => true]);
        }
        $this->post(route('programs.substitutions.store', $program), ['source_version_id' => $source->id, 'source_codes' => 'OLD1,OLD2', 'target_lines' => "NEW1=3\nNEW2=3", 'decision_number' => '02/QĐ', 'note' => 'Thay thế cả nhóm'])->assertSessionHasNoErrors()->assertRedirect();
        $target = app(ProgramVersionService::class)->capture($program, 2026, '02/QĐ', '2026-01-01', $officer->id);
        $target->update(['status' => 'published', 'effective_from' => '2026-01-01']);
        $student = Student::create(['student_code' => 'SV-RECOG', 'full_name' => 'Sinh viên thử', 'program_id' => $program->id, 'enrollment_year' => 2026, 'degree_level' => 'dai_hoc', 'status' => 'active']);
        foreach (['OLD1', 'OLD2'] as $code) {
            $this->post(route('academic.course-results.store', $student), ['program_version_id' => $source->id, 'course_code' => $code, 'semester' => '2025-1', 'outcome' => 'passed', 'evidence' => 'Bảng điểm xác nhận số 01'])->assertSessionHasNoErrors()->assertRedirect();
        }

        return [$officer, $manager, $student, $source, $target];
    }

    public function test_group_recognition_requires_approval_and_blocks_retake_double_counting(): void
    {
        [$officer, $manager, $student, $source, $target] = $this->setupCurriculum();
        $results = StudentCourseResult::where('student_id', $student->id)->pluck('id')->all();
        $data = ['program_version_id' => $target->id, 'result_ids' => $results, 'rule_id' => $target->snapshot['substitutions'][0]['id'], 'decision_number' => 'CN01', 'note' => 'Đối chiếu đủ nhóm nguồn'];
        $this->post(route('academic.recognitions.store', $student), array_merge($data, ['result_ids' => [$results[0]]]))->assertSessionHasErrors('recognition');
        $this->post(route('academic.recognitions.store', $student), $data)->assertSessionHasNoErrors()->assertRedirect();
        $record = CreditRecognition::firstOrFail();
        $this->assertSame(0, app(CreditRecognitionService::class)->summary($student, $target)['counted']);
        $this->post(route('academic.recognitions.review', [$student, $record]), ['decision' => 'approve', 'review_note' => 'Tự duyệt'])->assertForbidden();
        $this->actingAs($manager)->post(route('academic.recognitions.review', [$student, $record]), ['decision' => 'approve', 'review_note' => 'Đã đối chiếu căn cứ'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(6, app(CreditRecognitionService::class)->summary($student, $target)['counted']);
        $this->assertDatabaseCount('student_progress', 0);
        $this->get(route('academic.credits', [$student, 'version_id' => $target->id]))->assertOk()->assertSee('Đã công nhận')->assertSee('6 / 6');
        $this->actingAs($officer)->post(route('academic.recognitions.store', $student), $data)->assertSessionHasErrors('recognition');
        $this->post(route('academic.course-results.store', $student), ['program_version_id' => $source->id, 'course_code' => 'OLD1', 'semester' => '2025-2', 'outcome' => 'passed', 'evidence' => 'Học lại'])->assertRedirect();
        $retake = StudentCourseResult::latest('id')->firstOrFail();
        $this->post(route('academic.recognitions.store', $student), array_merge($data, ['result_ids' => [$retake->id, $results[1]]]))->assertSessionHasErrors('recognition');
        $this->post(route('academic.course-results.void', [$student, $results[0]]), ['reason' => 'Sửa kết quả'])->assertSessionHasErrors('result');
        $this->actingAs($manager)->post(route('academic.recognitions.revoke', [$student, $record]), ['reason' => 'Cần đính chính hồ sơ'])->assertRedirect();
        $this->assertSame(0, app(CreditRecognitionService::class)->summary($student, $target)['counted']);
        $this->post(route('academic.course-results.void', [$student, $results[0]]), ['reason' => 'Đính chính'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('curriculum_audits', ['subject_type' => 'credit_recognition', 'action' => 'recognition_revoked']);
        $this->get(route('academic.recognitions.queue'))->assertOk()->assertSee('Không có hồ sơ chờ phê duyệt');
    }

    public function test_stale_results_wrong_cohort_and_self_approval_are_rejected(): void
    {
        [$officer, $manager, $student, $source, $target] = $this->setupCurriculum();
        $data = ['program_version_id' => $target->id, 'result_ids' => StudentCourseResult::pluck('id')->all(), 'rule_id' => $target->snapshot['substitutions'][0]['id'], 'decision_number' => 'CN02', 'note' => 'Trình công nhận'];
        $this->post(route('academic.recognitions.store', $student), $data)->assertRedirect();
        $record = CreditRecognition::firstOrFail();
        StudentCourseResult::firstOrFail()->update(['outcome' => 'failed']);
        $this->actingAs($manager)->post(route('academic.recognitions.review', [$student, $record]), ['decision' => 'approve', 'review_note' => 'Duyệt'])->assertSessionHasErrors('recognition');
        $this->assertSame('submitted', $record->fresh()->status);
        $this->post(route('academic.recognitions.review', [$student, $record]), ['decision' => 'return', 'review_note' => 'Bổ sung bảng điểm'])->assertRedirect();
        $student->update(['enrollment_year' => 2025]);
        $this->actingAs($officer)->post(route('academic.recognitions.store', $student), $data)->assertSessionHasErrors('recognition');
        $viewer = User::factory()->create();
        $viewer->assignRole('Xem');
        $this->actingAs($viewer)->get(route('academic.credits', $student))->assertForbidden();
        $this->get(route('programs.audit'))->assertOk()->assertDontSee('CN02');
        $this->get(route('academic.recognitions.queue'))->assertForbidden();
    }

    public function test_direct_results_exemptions_and_self_review_are_controlled(): void
    {
        [$officer, $manager, $student, $source, $target] = $this->setupCurriculum();
        $this->post(route('academic.course-results.store', $student), ['program_version_id' => $target->id, 'course_code' => 'NEW1', 'semester' => '2026-1', 'outcome' => 'exempt', 'evidence' => 'Quyết định miễn học'])->assertSessionHasNoErrors()->assertRedirect();
        $result = StudentCourseResult::latest('id')->firstOrFail();
        $this->actingAs($manager)->post(route('academic.recognitions.store', $student), ['program_version_id' => $target->id, 'result_ids' => [$result->id], 'decision_number' => 'CN-MIEN', 'note' => 'Công nhận miễn theo quyết định'])->assertSessionHasNoErrors()->assertRedirect();
        $record = CreditRecognition::firstOrFail();
        $this->post(route('academic.recognitions.review', [$student, $record]), ['decision' => 'approve', 'review_note' => 'Tự duyệt'])->assertForbidden();
        $secondManager = User::factory()->create();
        $secondManager->assignRole('Lãnh đạo phòng');
        $this->actingAs($secondManager)->post(route('academic.recognitions.review', [$student, $record]), ['decision' => 'approve', 'review_note' => 'Đủ căn cứ'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(3, app(CreditRecognitionService::class)->summary($student, $target)['counted']);
        $otherStudent = Student::create(['student_code' => 'OTHER', 'full_name' => 'Sinh viên khác', 'program_id' => $student->program_id, 'enrollment_year' => 2026, 'degree_level' => 'dai_hoc']);
        $this->post(route('academic.recognitions.review', [$otherStudent, $record]), ['decision' => 'approve', 'review_note' => 'Sai sinh viên'])->assertNotFound();
        $this->actingAs($officer)->post(route('academic.recognitions.store', $otherStudent), ['program_version_id' => $target->id, 'result_ids' => [$result->id], 'decision_number' => 'CN-SAI', 'note' => 'Sai kết quả'])->assertSessionHasErrors('recognition');
    }
}
