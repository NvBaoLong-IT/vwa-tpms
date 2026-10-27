<?php

namespace Tests\Feature;

use App\Models\CreditRecognition;
use App\Models\Program;
use App\Models\ProgramExcelImport;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\OfficeRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurriculumDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_manager_worklists_respect_curriculum_student_filters_and_exclude_expired_imports(): void
    {
        $this->seed(OfficeRolesSeeder::class);
        $manager = $this->user('Lãnh đạo phòng');
        $officer = $this->user('Chuyên viên');
        foreach (['A' => 2026, 'B' => 2025] as $code => $year) {
            $program = Program::create(['code' => 'WORK-'.$code, 'name_vi' => 'CT '.$code, 'degree_level' => 'dai_hoc', 'total_credits' => 3]);
            $version = $program->versions()->create(['cohort_year' => $year, 'revision' => 1, 'decision_number' => 'QD-'.$code, 'decision_date' => '2026-01-01', 'status' => 'submitted', 'created_by' => $officer->id, 'snapshot' => ['program' => ['name_vi' => 'CT '.$code]]]);
            $student = Student::create(['student_code' => 'SV-'.$code, 'full_name' => 'Sinh viên '.$code, 'program_id' => $program->id, 'degree_level' => 'dai_hoc', 'enrollment_year' => $year]);
            CreditRecognition::create(['student_id' => $student->id, 'program_version_id' => $version->id, 'request_data' => [], 'allocation' => [], 'baseline_hash' => str_repeat('a', 64), 'status' => 'submitted', 'decision_number' => 'CN-'.$code, 'note' => 'Chờ duyệt', 'submitted_by' => $officer->id]);
        }
        ProgramExcelImport::create(['created_by' => $officer->id, 'program_code' => 'WORK-A', 'filename' => 'Con-han.xlsx', 'payload' => [], 'baseline_hash' => str_repeat('b', 64), 'status' => 'submitted', 'expires_at' => now()->addHour()]);
        ProgramExcelImport::create(['created_by' => $officer->id, 'program_code' => 'WORK-B', 'filename' => 'Het-han.xlsx', 'payload' => [], 'baseline_hash' => str_repeat('c', 64), 'status' => 'submitted', 'expires_at' => now()->subHour()]);
        $this->actingAs($manager)->get(route('dashboard', ['program_id' => Program::where('code', 'WORK-A')->firstOrFail()->id, 'year' => 2026]))
            ->assertOk()->assertSee('Kiểm soát CTĐT và công nhận tín chỉ')->assertSee('QD-A')->assertDontSee('QD-B')->assertSee('CN-A')->assertDontSee('CN-B')->assertSee('Con-han.xlsx')->assertDontSee('Het-han.xlsx')
            ->assertViewHas('curriculumWork', fn ($groups) => array_column($groups, 'count') === [1, 1, 1, 0]);
    }

    public function test_officer_sees_only_own_work_and_viewer_sees_no_recognition_data(): void
    {
        $this->seed(OfficeRolesSeeder::class);
        $owner = $this->user('Chuyên viên');
        $other = $this->user('Chuyên viên');
        foreach ([$owner, $other] as $user) {
            ProgramExcelImport::create(['created_by' => $user->id, 'program_code' => 'OWN-'.$user->id, 'filename' => 'Nguoi-'.$user->id.'.xlsx', 'payload' => [], 'baseline_hash' => str_repeat('c', 64), 'status' => 'submitted', 'expires_at' => now()->addHour()]);
        }
        $this->actingAs($owner)->get(route('dashboard'))->assertOk()->assertSee('Nguoi-'.$owner->id.'.xlsx')->assertDontSee('Nguoi-'.$other->id.'.xlsx');
        $viewer = $this->user('Xem');
        $this->actingAs($viewer)->get(route('dashboard'))->assertOk()->assertDontSee('Kiểm soát CTĐT và công nhận tín chỉ')->assertDontSee('Nguoi-'.$owner->id.'.xlsx')->assertViewHas('curriculumWork', []);
    }
}
