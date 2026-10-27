<?php

namespace Tests\Feature;

use App\Jobs\ImportAcademicProgressJob;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentProgress;
use App\Models\User;
use App\Models\WarningLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AcademicWarningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::create(['name' => 'Chuyên viên học vụ', 'guard_name' => 'web']);
        $role->syncPermissions(collect(['academic.view', 'academic.import', 'academic.process', 'reports.export'])->map(fn ($p) => Permission::findOrCreate($p, 'web'))->all());
    }

    public function test_warning_scan_creates_gpa_and_progress_warnings(): void
    {
        $u = User::factory()->create();
        $u->assignRole('Chuyên viên học vụ');
        $p = Program::create(['code' => 'AW-001', 'name_vi' => 'Ngành kiểm thử', 'degree_level' => 'dai_hoc', 'total_credits' => 120]);
        $s = Student::create(['student_code' => 'AW001', 'full_name' => 'Sinh viên cảnh báo', 'program_id' => $p->id, 'degree_level' => 'dai_hoc']);
        StudentProgress::create(['student_id' => $s->id, 'semester' => '2026-1', 'gpa_4' => 1.5, 'credits_accumulated' => 20, 'credits_required' => 60]);
        $this->actingAs($u)->post(route('academic.scan'))->assertRedirect();
        $this->assertDatabaseHas('warning_logs', ['student_id' => $s->id, 'warning_type' => 'low_gpa']);
        $this->assertDatabaseHas('warning_logs', ['student_id' => $s->id, 'warning_type' => 'delayed_progress']);
    }

    public function test_academic_role_queues_csv_import(): void
    {
        Queue::fake();
        $u = User::factory()->create();
        $u->assignRole('Chuyên viên học vụ');
        $csv = "MaSV,HoTen,MaNganh,HocKy,TinChiTichLuy,GPA,TinChiYeuCau\nCSV001,Nguyen Van A,AW-CSV,2026-1,30,2.5,60\n";
        $file = UploadedFile::fake()->createWithContent('progress.csv', $csv);
        $this->actingAs($u)->post(route('academic.import'), ['file' => $file])->assertRedirect(route('academic.index'));
        Queue::assertPushed(ImportAcademicProgressJob::class);
    }

    public function test_non_academic_role_is_forbidden(): void
    {
        $role = Role::create(['name' => 'Chuyên viên CTĐT', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::findOrCreate('programs.view', 'web'));
        $u = User::factory()->create();
        $u->assignRole($role);
        $this->actingAs($u)->get(route('academic.index'))->assertForbidden();
    }

    public function test_excel_download_contains_only_warnings_matching_the_filter(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Chuyên viên học vụ');
        $student = Student::create(['student_code' => 'EXPORT-001', 'full_name' => 'Sinh viên xuất Excel']);
        WarningLog::create(['student_id' => $student->id, 'warning_type' => 'low_gpa', 'reason' => 'Điểm thấp', 'status' => 'open', 'issued_at' => now()]);
        WarningLog::create(['student_id' => $student->id, 'warning_type' => 'credit_debt', 'reason' => 'Nợ tín chỉ', 'status' => 'resolved', 'issued_at' => now()]);

        $response = $this->actingAs($user)->get(route('academic.export.excel', ['status' => 'open']));

        $response->assertOk()->assertDownload('academic-warnings.xlsx');
        $workbook = IOFactory::load($response->baseResponse->getFile()->getPathname());
        try {
            $sheet = $workbook->getActiveSheet();
            $this->assertSame(2, $sheet->getHighestDataRow());
            $this->assertSame('EXPORT-001', $sheet->getCell('A2')->getValue());
            $this->assertSame('low_gpa', $sheet->getCell('D2')->getValue());
            $this->assertSame('open', $sheet->getCell('F2')->getValue());
        } finally {
            $workbook->disconnectWorksheets();
        }
    }

    public function test_warning_controls_preserve_resolved_status_and_require_choice_for_open_warning(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Chuyên viên học vụ');
        $student = Student::create(['student_code' => 'UI-STATUS', 'full_name' => 'Sinh viên trạng thái']);
        $resolved = WarningLog::create(['student_id' => $student->id, 'warning_type' => 'low_gpa', 'reason' => 'Điểm thấp', 'status' => 'resolved', 'issued_at' => now()]);
        $open = WarningLog::create(['student_id' => $student->id, 'warning_type' => 'credit_debt', 'reason' => 'Nợ tín chỉ', 'status' => 'open', 'issued_at' => now()]);

        $response = $this->actingAs($user)->get(route('academic.index'));
        $document = new \DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);

        $this->assertSame('resolved', $xpath->evaluate('string(//select[@id="warning-status-'.$resolved->id.'"]/option[@selected]/@value)'));
        $this->assertSame('', $xpath->evaluate('string(//select[@id="warning-status-'.$open->id.'"]/option[@selected]/@value)'));
        $this->assertSame(1, $xpath->query('//select[@id="warning-status-'.$open->id.'"][@required]/option[@selected][@disabled]')->length);
    }

    public function test_read_only_warning_list_keeps_export_filters_without_processing_controls(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('academic.view');
        $student = Student::create(['student_code' => 'UI-VIEW', 'full_name' => 'Sinh viên chỉ xem']);
        $warning = WarningLog::create(['student_id' => $student->id, 'warning_type' => 'low_gpa', 'reason' => 'Điểm thấp', 'status' => 'open', 'issued_at' => now()]);
        $filters = ['status' => 'open', 'warning_type' => 'low_gpa'];

        $this->actingAs($user)->get(route('academic.index', $filters))
            ->assertSee('Sinh viên chỉ xem')
            ->assertDontSee('Xuất danh sách')
            ->assertDontSee('href="'.e(route('academic.export.excel', $filters)).'"', false)
            ->assertDontSee('action="'.route('academic.warnings.update', $warning).'"', false)
            ->assertDontSee('action="'.route('academic.scan').'"', false);
    }
}
