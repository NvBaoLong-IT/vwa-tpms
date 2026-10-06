<?php

namespace Tests\Feature;

use App\Models\AcademicSetting;
use App\Models\Student;
use App\Models\User;
use App\Services\AcademicWarningService;
use Database\Seeders\OfficeRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseTwoAcademicTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OfficeRolesSeeder::class);
    }

    public function test_undergraduate_milestones_are_hidden_and_rejected_on_submit(): void
    {
        $specialist = User::factory()->create();
        $specialist->assignRole('Chuyên viên');
        $student = Student::create(['student_code' => 'DH-01', 'full_name' => 'Sinh viên', 'degree_level' => 'dai_hoc', 'status' => 'active']);

        $this->actingAs($specialist)->get(route('academic.student', $student))
            ->assertOk()
            ->assertSee('Tốt nghiệp dự kiến')
            ->assertDontSee('Hạn nộp đề cương')
            ->assertDontSee('Hạn đào tạo tối đa');
        $this->put(route('academic.milestones.update', $student), ['proposal_deadline' => '2026-10-10'])->assertSessionHasErrors('proposal_deadline');
        $this->assertNull($student->fresh()->proposal_deadline);
        $this->put(route('academic.milestones.update', $student), ['expected_graduation_date' => '2028-08-31'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('2028-08-31', $student->fresh()->expected_graduation_date->toDateString());
    }

    public function test_postgraduate_milestones_remain_available(): void
    {
        $specialist = User::factory()->create();
        $specialist->assignRole('Chuyên viên');
        $student = Student::create(['student_code' => 'THS-01', 'full_name' => 'Học viên', 'degree_level' => 'thac_si', 'status' => 'active']);

        $this->actingAs($specialist)->get(route('academic.student', $student))->assertOk()->assertSee('Hạn nộp đề cương');
        $this->put(route('academic.milestones.update', $student), ['proposal_deadline' => '2027-10-10'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('2027-10-10', $student->fresh()->proposal_deadline->toDateString());
    }

    public function test_only_manager_can_edit_visible_gpa_thresholds(): void
    {
        $specialist = User::factory()->create();
        $specialist->assignRole('Chuyên viên');
        $manager = User::factory()->create();
        $manager->assignRole('Lãnh đạo phòng');
        $undergraduate = AcademicSetting::create(['key' => 'gpa_below', 'degree_level' => 'dai_hoc', 'value' => '1.6', 'label' => 'GPA ĐH']);
        AcademicSetting::create(['key' => 'gpa_below', 'degree_level' => 'thac_si', 'value' => '2.0', 'label' => 'GPA ThS']);
        AcademicSetting::create(['key' => 'gpa_below', 'degree_level' => 'tien_si', 'value' => '2.0', 'label' => 'GPA TS']);
        $advanced = AcademicSetting::create(['key' => 'credit_debt_above', 'degree_level' => 'dai_hoc', 'value' => '15', 'label' => 'Nợ tín chỉ']);

        $this->actingAs($specialist)->get(route('academic.settings'))->assertForbidden();
        $this->put(route('academic.settings.update'), ['settings' => [$undergraduate->id => '1.8']])->assertForbidden();
        $this->actingAs($manager)->get(route('academic.settings'))->assertOk()->assertSee('Ngưỡng GPA cảnh báo')->assertDontSee('Nợ tín chỉ');
        $this->put(route('academic.settings.update'), ['settings' => [$advanced->id => '3']])->assertSessionHasErrors('settings');
        $this->assertSame('15', $advanced->fresh()->value);
        $this->put(route('academic.settings.update'), ['settings' => [$undergraduate->id => '1.8']])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1.8, app(AcademicWarningService::class)->thresholds('dai_hoc')['gpa_below']);
        $this->assertSame(2.0, app(AcademicWarningService::class)->thresholds('thac_si')['gpa_below']);
    }

    public function test_postgraduate_default_gpa_is_two_without_database_override(): void
    {
        AcademicSetting::create(['key' => 'gpa_below', 'degree_level' => null, 'value' => '1.6', 'label' => 'GPA chung']);
        $service = app(AcademicWarningService::class);

        $this->assertSame(1.6, $service->thresholds('dai_hoc')['gpa_below']);
        $this->assertSame(2.0, $service->thresholds('thac_si')['gpa_below']);
        $this->assertSame(2.0, $service->thresholds('tien_si')['gpa_below']);
    }
}
