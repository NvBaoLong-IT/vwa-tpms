<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CoursePloMatrix;
use App\Models\DiplomaBlank;
use App\Models\Program;
use App\Models\ReportHistory;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        $role = Role::create(['name' => 'Report manager', 'guard_name' => 'web']);
        $role->syncPermissions([Permission::findOrCreate('dashboard.view', 'web'), Permission::findOrCreate('reports.export', 'web')]);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_filters_are_applied_to_program_plo_and_academic_reports(): void
    {
        $user = $this->manager();
        $program = Program::create(['code' => 'RPT1', 'name_vi' => 'Ngành báo cáo', 'degree_level' => 'dai_hoc', 'total_credits' => 120, 'decision_date' => '2026-01-10']);
        $course = Course::create(['course_code' => 'RPT101', 'name_vi' => 'Học phần báo cáo', 'credits' => 3]);
        $program->courses()->attach($course, ['knowledge_block' => 'Cơ sở', 'is_mandatory' => true]);
        $plo = $program->plos()->create(['code' => 'PLO1', 'description_vi' => 'Mô tả']);
        CoursePloMatrix::create(['course_id' => $course->id, 'plo_id' => $plo->id, 'contribution_level' => 3]);
        $this->actingAs($user)->get(route('reports.export', ['programs', 'csv', 'program_id' => $program->id, 'degree_level' => 'dai_hoc', 'academic_year' => 2026]))->assertOk()->assertSee('RPT1');
        $this->actingAs($user)->get(route('reports.export', ['plo', 'csv', 'program_id' => $program->id]))->assertOk()->assertSee('PLO1');
        $history = ReportHistory::latest()->firstOrFail();
        $this->actingAs($user)->get(route('reports.reload', $history))->assertOk();
    }

    public function test_excel_pdf_and_blank_report_formats_are_downloadable(): void
    {
        $user = $this->manager();
        DiplomaBlank::create(['serial_number' => 'RPT-B1', 'type' => 'dai_hoc', 'import_date' => '2026-09-01']);
        $xlsx = $this->actingAs($user)->get(route('reports.export', ['blanks', 'xlsx']))->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringStartsWith('PK', substr($xlsx->streamedContent(), 0, 2));
        $this->actingAs($user)->get(route('reports.export', ['blanks', 'pdf']))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_user_without_report_permission_is_forbidden(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('reports.index'))->assertForbidden();
    }

    public function test_year_end_blank_report_is_saved_once_and_downloads_its_snapshot(): void
    {
        Storage::fake('local');
        $manager = $this->manager();
        DiplomaBlank::create(['serial_number' => 'ANNUAL-1', 'type' => 'dai_hoc', 'import_date' => '2026-09-01', 'status' => 'in_stock']);

        $this->artisan('reports:generate-annual', ['type' => 'annual_blanks', 'year' => 2026])->assertSuccessful();
        $history = ReportHistory::firstOrFail();
        Storage::disk('local')->assertExists($history->file_path);
        $saved = Storage::disk('local')->get($history->file_path);
        DiplomaBlank::create(['serial_number' => 'ANNUAL-2', 'type' => 'dai_hoc', 'import_date' => '2026-10-01', 'status' => 'used']);
        $this->artisan('reports:generate-annual', ['type' => 'annual_blanks', 'year' => 2026])->assertSuccessful();

        $this->assertDatabaseCount('report_histories', 1);
        $this->assertSame($saved, Storage::disk('local')->get($history->file_path));
        $this->actingAs($manager)->get(route('reports.reload', $history))->assertDownload('annual_blanks-2026.pdf');
    }

    public function test_academic_year_summary_reports_on_time_graduation_and_withdrawal(): void
    {
        $manager = $this->manager();
        Student::create(['student_code' => 'YEAR-ON', 'full_name' => 'Đúng hạn', 'degree_level' => 'dai_hoc', 'status' => 'graduated', 'enrollment_year' => 2022, 'expected_graduation_date' => '2026-08-31', 'graduated_at' => '2026-08-30']);
        Student::create(['student_code' => 'YEAR-OUT', 'full_name' => 'Thôi học', 'degree_level' => 'dai_hoc', 'status' => 'withdrawn', 'enrollment_year' => 2022, 'withdrawn_at' => '2026-03-01']);

        $this->actingAs($manager)->get(route('reports.export', ['academic_year_summary', 'csv', 'academic_year' => 2026]))->assertOk()->assertSee('100%')->assertSee('50%');
    }
}
