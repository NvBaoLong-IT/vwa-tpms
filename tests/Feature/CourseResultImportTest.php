<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseResultImport;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentCourseResult;
use App\Models\User;
use App\Services\ProgramVersionService;
use Database\Seeders\OfficeRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class CourseResultImportTest extends TestCase
{
    use RefreshDatabase;

    private function arrange(): array
    {
        $this->seed(OfficeRolesSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Chuyên viên');
        $this->actingAs($user);
        $program = Program::create(['code' => 'IMP', 'name_vi' => 'CT nhập kết quả', 'degree_level' => 'dai_hoc', 'total_credits' => 6]);
        foreach (['HP1', 'HP2'] as $code) {
            $course = Course::create(['course_code' => $code, 'name_vi' => $code, 'credits' => 3]);
            $program->courses()->attach($course, ['knowledge_block' => 'Đại cương', 'is_mandatory' => true]);
        }
        $version = app(ProgramVersionService::class)->capture($program, 2026, '01/QĐ', '2026-01-01', $user->id);
        $version->update(['status' => 'published', 'effective_from' => '2026-01-01']);
        $student = Student::create(['student_code' => 'SV-IMP', 'full_name' => 'SV nhập', 'degree_level' => 'dai_hoc', 'program_id' => $program->id, 'enrollment_year' => 2026]);

        return [$user, $student, $version];
    }

    private function file(array $rows, bool $excel = false): UploadedFile
    {
        $headers = ['MaCTDT', 'Khoa', 'LanSua', 'MaHocPhan', 'HocKy', 'KetQua', 'CanCu'];
        if (! $excel) {
            $stream = fopen('php://temp', 'r+');
            fwrite($stream, "\xEF\xBB\xBF");
            foreach ([$headers, ...$rows] as $row) {
                fputcsv($stream, $row, ',', '"', '');
            }
            rewind($stream);
            $content = stream_get_contents($stream);
            fclose($stream);

            return UploadedFile::fake()->createWithContent('results.csv', $content);
        }
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([$headers, ...$rows], null, 'A1', true);
        $path = tempnam(sys_get_temp_dir(), 'results');
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return new UploadedFile($path, 'results.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_csv_is_previewed_before_atomic_confirmation_and_rejects_replay(): void
    {
        [$user, $student] = $this->arrange();
        $this->get(route('academic.course-results.import', $student))->assertOk()->assertSee('Tải mẫu CSV');
        $this->get(route('academic.course-results.import.template', $student))->assertOk()->assertSee('MaCTDT,Khoa,LanSua');
        $this->post(route('academic.course-results.import.store', $student), ['file' => $this->file([['IMP', 2026, 1, 'HP1', '2026-1', 'Dat', 'Bảng điểm 01'], ['IMP', 2026, 1, 'HP2', '2026-1', 'Mien', 'Quyết định 02']])])->assertSessionHasNoErrors()->assertRedirect();
        $import = CourseResultImport::firstOrFail();
        $this->assertDatabaseCount('student_course_results', 0);
        $this->get(route('academic.course-results.import.preview', [$student, $import]))->assertOk()->assertSee('2 dòng hợp lệ')->assertSee('Bảng điểm 01');
        $this->post(route('academic.course-results.import.confirm', [$student, $import]), ['confirm' => 1])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('student_course_results', 2);
        $this->assertDatabaseHas('student_course_results', ['student_id' => $student->id, 'course_code' => 'HP2', 'credits' => 3, 'outcome' => 'exempt']);
        $this->assertDatabaseCount('credit_recognitions', 0);
        $this->assertDatabaseHas('curriculum_audits', ['subject_type' => 'course_result_import', 'action' => 'results_imported']);
        $this->post(route('academic.course-results.import.confirm', [$student, $import]), ['confirm' => 1])->assertStatus(422);
        $this->get(route('academic.course-results.import', $student))->assertOk()->assertSee('Đã nhập')->assertSee('results.csv');
        $this->post(route('academic.course-results.import.store', $student), ['file' => $this->file([['IMP', 2026, 1, 'HP1', '2026-1', 'Dat', 'Không được ghi đè']])])->assertRedirect();
        $duplicate = CourseResultImport::latest('id')->firstOrFail();
        $this->assertCount(1, $duplicate->errors);
        $this->post(route('academic.course-results.import.confirm', [$student, $duplicate]), ['confirm' => 1])->assertStatus(422);
        $this->assertDatabaseCount('student_course_results', 2);
    }

    public function test_excel_collects_invalid_rows_and_never_partially_writes(): void
    {
        [, $student] = $this->arrange();
        $rows = [['IMP', 2026, 1, 'HP1', '2026-1', 'Dat', 'Bảng điểm'], ['IMP', 2026, 1, 'HP1', '2026-1', 'Dat', 'Trùng dòng'], ['IMP', 2026, 1, 'WRONG', '2026-1', 'Dat', 'Sai mã']];
        $this->post(route('academic.course-results.import.store', $student), ['file' => $this->file($rows, true)])->assertSessionHasNoErrors()->assertRedirect();
        $import = CourseResultImport::firstOrFail();
        $this->assertCount(1, $import->payload);
        $this->assertCount(2, $import->errors);
        $this->get(route('academic.course-results.import.preview', [$student, $import]))->assertOk()->assertSee('Dòng 3')->assertSee('Dòng 4');
        $this->post(route('academic.course-results.import.confirm', [$student, $import]), ['confirm' => 1])->assertStatus(422);
        $this->assertDatabaseCount('student_course_results', 0);
    }

    public function test_changed_results_ownership_and_expiry_prevent_import(): void
    {
        [$user, $student, $version] = $this->arrange();
        $this->post(route('academic.course-results.import.store', $student), ['file' => $this->file([['IMP', 2026, 1, 'HP1', '2026-1', 'Dat', 'Bảng điểm']])])->assertRedirect();
        $import = CourseResultImport::firstOrFail();
        $other = User::factory()->create();
        $other->assignRole('Chuyên viên');
        $this->actingAs($other)->get(route('academic.course-results.import.preview', [$student, $import]))->assertForbidden();
        $this->actingAs($user);
        StudentCourseResult::create(['student_id' => $student->id, 'program_version_id' => $version->id, 'course_code' => 'HP2', 'semester' => '2026-1', 'credits' => 3, 'outcome' => 'passed', 'evidence' => 'Mới ghi']);
        $this->post(route('academic.course-results.import.confirm', [$student, $import]), ['confirm' => 1])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('student_course_results', 1);
        $this->travel(3)->hours();
        $this->post(route('academic.course-results.import.confirm', [$student, $import]), ['confirm' => 1])->assertStatus(422);
        $viewer = User::factory()->create();
        $viewer->assignRole('Xem');
        $this->actingAs($viewer)->get(route('academic.course-results.import', $student))->assertForbidden();
    }
}
