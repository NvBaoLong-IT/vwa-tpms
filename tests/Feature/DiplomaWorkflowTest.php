<?php

namespace Tests\Feature;

use App\Models\DiplomaBlank;
use App\Models\DiplomaBlankAudit;
use App\Models\DiplomaLedger;
use App\Models\DiplomaRequest;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DiplomaWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $role = Role::create(['name' => 'VBCC workflow', 'guard_name' => 'web']);
        $role->syncPermissions(collect(['diploma.view', 'diploma.issue', 'diploma.update'])->map(fn ($p) => Permission::findOrCreate($p, 'web'))->all());
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function approver(): User
    {
        $role = Role::create(['name' => 'Lãnh đạo VBCC', 'guard_name' => 'web']);
        $role->syncPermissions(collect(['diploma.view', 'diploma.update', 'diploma.approve'])->map(fn ($p) => Permission::findOrCreate($p, 'web'))->all());
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_issue_requires_graduated_student_uses_blank_and_writes_audit(): void
    {
        $user = $this->user();
        $eligible = Student::create(['student_code' => 'GRAD1', 'full_name' => 'Sinh viên tốt nghiệp', 'degree_level' => 'dai_hoc', 'status' => 'graduated']);
        $notEligible = Student::create(['student_code' => 'ACTIVE1', 'full_name' => 'Sinh viên đang học', 'degree_level' => 'dai_hoc', 'status' => 'active']);
        $blank = DiplomaBlank::create(['serial_number' => 'DH-001', 'type' => 'dai_hoc', 'import_date' => '2026-01-01']);
        $input = ['diploma_type' => 'dai_hoc', 'serial_number' => $blank->serial_number, 'cohort_code' => 'K12', 'decision_code' => '501', 'issue_date' => '2026-09-28'];
        $this->actingAs($user)->post(route('diplomas.store'), $input + ['student_id' => $notEligible->id])->assertSessionHasErrors('student_id');
        $this->actingAs($user)->post(route('diplomas.store'), $input + ['student_id' => $eligible->id])->assertRedirect();
        $this->assertDatabaseCount('diploma_ledgers', 0);
        $this->actingAs($user)->post(route('diplomas.requests.approve', DiplomaRequest::firstOrFail()))->assertForbidden();
        $approver = $this->approver();
        $this->actingAs($approver)->post(route('diplomas.requests.approve', DiplomaRequest::firstOrFail()))->assertRedirect();
        $ledger = DiplomaLedger::firstOrFail();
        $this->assertSame('DH/K12/2026/QĐ501-001', $ledger->reg_number);
        $this->assertDatabaseHas('diploma_blanks', ['serial_number' => 'DH-001', 'status' => 'used']);
        $this->assertDatabaseHas('diploma_blank_audits', ['diploma_blank_id' => $blank->id, 'action' => 'used']);
        $this->assertDatabaseHas('diploma_audit_logs', ['diploma_ledger_id' => $ledger->id, 'action' => 'issued']);
        $this->actingAs($approver)->post(route('diplomas.cancel', $ledger), ['reason' => 'Sai thông tin'])->assertRedirect();
        $this->assertDatabaseHas('diploma_audit_logs', ['diploma_ledger_id' => $ledger->id, 'action' => 'cancelled']);
        $this->assertDatabaseHas('diploma_ledgers', ['id' => $ledger->id, 'status' => 'cancelled']);
        $this->assertDatabaseCount('diploma_ledgers', 1);
    }

    public function test_ledger_has_no_delete_route_and_internal_lookup_is_closed(): void
    {
        $user = $this->user();
        $student = Student::create(['student_code' => 'GRAD2', 'full_name' => 'Người tra cứu', 'degree_level' => 'dai_hoc', 'status' => 'graduated']);
        $blank = DiplomaBlank::create(['serial_number' => 'DH-002', 'type' => 'dai_hoc', 'import_date' => '2026-01-01']);
        $this->actingAs($user)->post(route('diplomas.store'), ['student_id' => $student->id, 'diploma_type' => 'dai_hoc', 'serial_number' => $blank->serial_number, 'cohort_code' => 'K12', 'decision_code' => '501', 'issue_date' => '2026-09-28']);
        $this->actingAs($this->approver())->post(route('diplomas.requests.approve', DiplomaRequest::firstOrFail()))->assertRedirect();
        $ledger = DiplomaLedger::firstOrFail();
        $this->actingAs($user)->delete('/diplomas/'.$ledger->id)->assertStatus(405);
        $this->get('/tra-cuu-van-bang')->assertOk()->assertSee('Tra cứu văn bằng');
        $this->get('/diplomas/lookup?code='.$ledger->reg_number)->assertStatus(405);
        $this->actingAs($user)->get(route('diplomas.audit'))->assertOk()->assertSee('Đã cấp');
        $this->assertDatabaseHas('diploma_audit_logs', ['diploma_ledger_id' => $ledger->id, 'action' => 'issued']);
    }

    public function test_graduation_csv_import_reports_ineligible_rows(): void
    {
        $user = $this->user();
        Student::create(['student_code' => 'GRAD3', 'full_name' => 'Import tốt nghiệp', 'degree_level' => 'dai_hoc', 'status' => 'graduated']);
        DiplomaBlank::create(['serial_number' => 'DH-003', 'type' => 'dai_hoc', 'import_date' => '2026-01-01']);
        $csv = "MaSV,LoaiVanBang,SoPhoi,NgayCap,MaKhoa,SoQuyetDinh\nGRAD3,dai_hoc,DH-003,2026-09-28,K12,501\nBAD3,dai_hoc,DH-999,2026-09-28,K12,501\n";
        $file = UploadedFile::fake()->createWithContent('graduates.csv', $csv);
        $this->actingAs($user)->post(route('diplomas.import'), ['file' => $file])->assertRedirect()->assertSessionHas('import_errors');
        $this->assertDatabaseCount('diploma_requests', 1);
        $this->assertDatabaseCount('diploma_ledgers', 0);
        $this->actingAs($this->approver())->post(route('diplomas.requests.approve', DiplomaRequest::firstOrFail()))->assertRedirect();
        $this->assertDatabaseHas('diploma_blanks', ['serial_number' => 'DH-003', 'status' => 'used']);
    }

    public function test_requests_use_sequential_numbers_and_rejection_does_not_consume_a_blank(): void
    {
        $officer = $this->user();
        $manager = $this->approver();
        $first = Student::create(['student_code' => 'GRAD4', 'full_name' => 'Người thứ nhất', 'degree_level' => 'dai_hoc', 'status' => 'graduated']);
        $second = Student::create(['student_code' => 'GRAD5', 'full_name' => 'Người thứ hai', 'degree_level' => 'dai_hoc', 'status' => 'graduated']);
        foreach (['DH-004', 'DH-005'] as $serial) {
            DiplomaBlank::create(['serial_number' => $serial, 'type' => 'dai_hoc', 'import_date' => '2026-01-01']);
        }
        $input = ['diploma_type' => 'dai_hoc', 'cohort_code' => 'K12', 'decision_code' => '501', 'issue_date' => '2026-09-28'];
        $this->actingAs($officer)->post(route('diplomas.store'), $input + ['student_id' => $first->id, 'serial_number' => 'DH-004'])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($manager)->post(route('diplomas.requests.reject', DiplomaRequest::firstOrFail()), ['review_note' => 'Sai hồ sơ'])->assertRedirect();
        $this->assertDatabaseHas('diploma_blanks', ['serial_number' => 'DH-004', 'status' => 'in_stock']);
        $this->actingAs($officer)->post(route('diplomas.store'), $input + ['student_id' => $first->id, 'serial_number' => 'DH-004'])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($manager)->post(route('diplomas.requests.approve', DiplomaRequest::where('status', 'pending')->firstOrFail()))->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($officer)->post(route('diplomas.store'), $input + ['student_id' => $second->id, 'serial_number' => 'DH-005'])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($manager)->post(route('diplomas.requests.approve', DiplomaRequest::where('status', 'pending')->firstOrFail()))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(['DH/K12/2026/QĐ501-001', 'DH/K12/2026/QĐ501-002'], DiplomaLedger::orderBy('id')->pluck('reg_number')->all());
        $this->assertDatabaseCount('diploma_ledgers', 2);
    }

    public function test_amendment_requires_decision_and_keeps_saved_holder_name(): void
    {
        $officer = $this->user();
        $manager = $this->approver();
        $student = Student::create(['student_code' => 'GRAD6', 'full_name' => 'Tên ban đầu', 'degree_level' => 'dai_hoc', 'status' => 'graduated']);
        DiplomaBlank::create(['serial_number' => 'DH-006', 'type' => 'dai_hoc', 'import_date' => '2026-01-01']);
        $this->actingAs($officer)->post(route('diplomas.store'), ['student_id' => $student->id, 'diploma_type' => 'dai_hoc', 'serial_number' => 'DH-006', 'cohort_code' => 'K12', 'decision_code' => '501', 'issue_date' => '2026-09-28'])->assertRedirect();
        $this->actingAs($manager)->post(route('diplomas.requests.approve', DiplomaRequest::firstOrFail()))->assertRedirect();
        $ledger = DiplomaLedger::firstOrFail();

        $this->actingAs($officer)->put(route('diplomas.update', $ledger), ['holder_name' => 'Tên đính chính', 'amendment_decision' => '99/QĐ', 'reason' => 'Sửa hộ tịch'])->assertRedirect();

        $this->assertDatabaseHas('diploma_audit_logs', ['diploma_ledger_id' => $ledger->id, 'action' => 'amended']);
        $this->assertDatabaseHas('diploma_ledgers', ['id' => $ledger->id, 'holder_name' => 'Tên đính chính']);
    }

    public function test_blank_destruction_requires_manager_decision_and_private_minutes(): void
    {
        Storage::fake('local');
        $officer = $this->user();
        $manager = $this->approver();
        $blank = DiplomaBlank::create(['serial_number' => 'DESTROY-01', 'type' => 'dai_hoc', 'import_date' => '2026-01-01']);
        $input = ['status' => 'destroyed', 'note' => 'Phôi in sai', 'decision_number' => '55/QĐ', 'minutes_file' => UploadedFile::fake()->create('bien-ban.pdf', 20, 'application/pdf')];

        $this->actingAs($officer)->patch(route('diplomas.blanks.update', $blank), $input)->assertForbidden();
        $this->assertDatabaseHas('diploma_blanks', ['id' => $blank->id, 'status' => 'in_stock']);
        $this->actingAs($manager)->patch(route('diplomas.blanks.update', $blank), $input)->assertRedirect()->assertSessionHasNoErrors();

        $audit = DiplomaBlankAudit::where('diploma_blank_id', $blank->id)->firstOrFail();
        $this->assertSame('destroyed', $audit->action);
        Storage::disk('local')->assertExists($audit->document_path);
        $this->actingAs($manager)->get(route('diplomas.blanks.minutes', $audit))->assertDownload();
    }
}
