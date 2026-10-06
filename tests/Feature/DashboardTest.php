<?php

namespace Tests\Feature;

use App\Models\AcademicImport;
use App\Models\IsoWorkflow;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Models\WarningLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function viewer(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        foreach (['dashboard.view', 'students.view', 'academic.view', 'programs.view', 'iso.view'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user;
    }

    public function test_dashboard_shows_six_operational_kpis(): void
    {
        $this->travelTo('2026-09-30');
        $user = $this->viewer();
        $program = Program::create(['code' => 'DASH-01', 'name_vi' => 'Ngành KPI', 'degree_level' => 'dai_hoc', 'total_credits' => 120]);
        $onTime = Student::create(['student_code' => 'ON-TIME', 'full_name' => 'Đúng hạn', 'program_id' => $program->id, 'status' => 'graduated', 'expected_graduation_date' => '2026-08-31', 'graduated_at' => '2026-08-30']);
        Student::create(['student_code' => 'LATE', 'full_name' => 'Trễ hạn', 'program_id' => $program->id, 'status' => 'graduated', 'expected_graduation_date' => '2026-08-31', 'graduated_at' => '2026-09-02']);
        Student::create(['student_code' => 'ACTIVE', 'full_name' => 'Đang học', 'program_id' => $program->id, 'status' => 'active']);
        WarningLog::create(['student_id' => $onTime->id, 'warning_type' => 'low_gpa', 'reason' => 'GPA thấp', 'status' => 'open', 'issued_at' => now()]);
        $workflow = IsoWorkflow::create(['program_id' => $program->id, 'type' => 'minor', 'title' => 'Rà soát quá hạn', 'start_date' => '2026-01-01', 'expected_end_date' => '2026-08-31', 'status' => 'in_progress']);
        $workflow->steps()->create(['step_number' => 1, 'step_name' => 'Rà soát', 'deadline' => '2026-09-01', 'status' => 'in_progress']);
        AcademicImport::create(['user_id' => $user->id, 'original_name' => 'bad.csv', 'status' => 'completed', 'error_count' => 2]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['students'] === 3
                && $stats['warnings_open'] === 1
                && $stats['iso_overdue'] === 1
                && $stats['programs'] === 1
                && $stats['import_errors'] === 1
                && $stats['graduation_rate'] === 50.0)
            ->assertSee('50.0%')
            ->assertSee('Lần nhập có lỗi')
            ->assertDontSee('Thôi học');
    }

    public function test_student_filters_preserve_drilldowns_and_global_kpis(): void
    {
        $user = $this->viewer();
        $selected = Program::create(['code' => 'DASH-A', 'name_vi' => 'Ngành A', 'degree_level' => 'dai_hoc', 'total_credits' => 120]);
        $other = Program::create(['code' => 'DASH-B', 'name_vi' => 'Ngành B', 'degree_level' => 'dai_hoc', 'total_credits' => 120]);
        $student = Student::create(['student_code' => 'A-2026', 'full_name' => 'Sinh viên A', 'program_id' => $selected->id, 'degree_level' => 'dai_hoc', 'enrollment_year' => 2026]);
        Student::create(['student_code' => 'B-2025', 'full_name' => 'Sinh viên B', 'program_id' => $other->id, 'degree_level' => 'dai_hoc', 'enrollment_year' => 2025]);
        WarningLog::create(['student_id' => $student->id, 'warning_type' => 'low_gpa', 'reason' => 'GPA thấp', 'status' => 'open', 'issued_at' => now()]);
        AcademicImport::create(['user_id' => $user->id, 'original_name' => 'failed.csv', 'status' => 'failed']);
        $filters = ['program_id' => $selected->id, 'degree_level' => 'dai_hoc', 'year' => 2026];

        $this->actingAs($user)->get(route('dashboard', $filters))
            ->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['students'] === 1 && $stats['warnings_open'] === 1 && $stats['programs'] === 1 && $stats['import_errors'] === 1)
            ->assertSee(route('academic.students', ['program_id' => $selected->id, 'degree_level' => 'dai_hoc', 'enrollment_year' => 2026]))
            ->assertSee(route('academic.index', ['program_id' => $selected->id, 'degree_level' => 'dai_hoc', 'enrollment_year' => 2026, 'status' => 'open']));
    }

    public function test_graduation_rate_waits_for_a_due_cohort(): void
    {
        $user = $this->viewer();
        Student::create(['student_code' => 'FUTURE', 'full_name' => 'Chưa đến hạn', 'status' => 'active', 'expected_graduation_date' => today()->addYear()]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['graduation_rate'] === null)
            ->assertSee('Chưa đủ dữ liệu');
    }

    public function test_directory_viewer_sees_student_shortcut_without_restricted_program_link(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        foreach (['dashboard.view', 'students.view'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        $this->actingAs($user)->get(route('dashboard'))
            ->assertSee('Sinh viên &amp; cố vấn', false)
            ->assertSee('href="'.route('academic.students').'"', false)
            ->assertDontSee('href="'.route('programs.index').'"', false)
            ->assertDontSee('href="'.route('academic.index').'"', false);
    }
}
