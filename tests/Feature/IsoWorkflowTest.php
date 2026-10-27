<?php

namespace Tests\Feature;

use App\Models\IsoWorkflow;
use App\Models\Program;
use App\Models\User;
use App\Notifications\IsoDeadlineNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class IsoWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['Lãnh đạo phòng', 'Chuyên viên CTĐT'] as $name) {
            $role = Role::create(['name' => $name, 'guard_name' => 'web']);
            $permissions = ['programs.view', 'iso.view', 'iso.create', 'iso.update', 'iso.assign', 'iso.upload'];
            if ($name === 'Lãnh đạo phòng') {
                $permissions = array_merge($permissions, ['iso.approve', 'iso.close']);
            }
            $role->syncPermissions(collect($permissions)->map(fn ($p) => Permission::findOrCreate($p, 'web'))->all());
        }
    }

    public function test_curriculum_role_can_create_six_month_workflow(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Chuyên viên CTĐT');
        $program = Program::create(['code' => 'ISO-001', 'name_vi' => 'CTĐT ISO', 'degree_level' => 'dai_hoc', 'total_credits' => 120]);
        $this->actingAs($user)->post(route('iso.store'), ['program_id' => $program->id, 'type' => 'major', 'changed_credits' => 30, 'title' => 'Rà soát ISO thử nghiệm', 'start_date' => '2026-08-31'])->assertRedirect();
        $workflow = IsoWorkflow::first();
        $this->assertNotNull($workflow);
        $this->assertCount(4, $workflow->steps);
        $this->assertSame('2027-03-01', $workflow->expected_end_date->format('Y-m-d'));
    }

    public function test_academic_role_cannot_access_iso(): void
    {
        $role = Role::create(['name' => 'Chuyên viên học vụ', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user)->get(route('iso.index'))->assertForbidden();
    }

    public function test_detail_preserves_current_step_status_in_update_form(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Chuyên viên CTĐT');
        $program = Program::create(['code' => 'ISO-DETAIL', 'name_vi' => 'Chương trình', 'degree_level' => 'dai_hoc', 'total_credits' => 120]);
        $workflow = IsoWorkflow::create(['program_id' => $program->id, 'type' => 'minor', 'title' => 'Chi tiết rà soát', 'start_date' => '2026-01-01', 'expected_end_date' => '2026-12-31', 'status' => 'in_progress']);
        $step = $workflow->steps()->create(['step_number' => 1, 'step_name' => 'Thẩm định', 'deadline' => '2026-12-01', 'status' => 'waiting_approval']);

        $response = $this->actingAs($user)->get(route('iso.show', $workflow));
        $response->assertSee('Tổng quan đợt rà soát')->assertSee('Đợi phê duyệt');
        $document = new \DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $this->assertSame('waiting_approval', $xpath->evaluate('string(//select[@id="iso-step-status-'.$step->id.'"]/option[@selected]/@value)'));
    }

    public function test_upload_approval_close_and_step_history_are_audited(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $user->assignRole('Chuyên viên CTĐT');
        $approver = User::factory()->create();
        $approver->assignRole('Lãnh đạo phòng');
        $program = Program::create(['code' => 'ISO-002', 'name_vi' => 'CTĐT ISO 2', 'degree_level' => 'dai_hoc', 'total_credits' => 120]);
        $this->actingAs($user)->post(route('iso.store'), ['program_id' => $program->id, 'type' => 'minor', 'changed_credits' => 12, 'title' => 'Rà soát', 'start_date' => '2026-09-01'])->assertRedirect();
        $workflow = IsoWorkflow::firstOrFail();
        $step = $workflow->steps()->first();
        $this->actingAs($user)->patch(route('iso.steps.update', [$workflow, $step->id]), ['status' => 'waiting_approval', 'note' => 'Đã gửi hồ sơ'])->assertRedirect();
        $this->actingAs($user)->patch(route('iso.steps.assign', [$workflow, $step->id]), ['assigned_to_user_id' => $user->id])->assertRedirect();
        $this->assertDatabaseCount('iso_histories', 2);
        $this->actingAs($user)->post(route('iso.files.upload', $workflow), ['file' => UploadedFile::fake()->create('bien-ban.pdf', 20, 'application/pdf')])->assertRedirect();
        $file = $workflow->files()->firstOrFail();
        Storage::disk('local')->assertExists($file->path);
        $this->actingAs($user)->get(route('iso.files.download', [$workflow, $file]))->assertDownload('bien-ban.pdf');
        $this->actingAs($approver)->post(route('iso.approve', $workflow))->assertRedirect();
        $this->assertDatabaseHas('iso_workflows', ['id' => $workflow->id, 'approved_by' => $approver->id]);
        $this->actingAs($approver)->post(route('iso.close', $workflow))->assertRedirect();
        $this->assertDatabaseHas('iso_workflows', ['id' => $workflow->id, 'status' => 'completed', 'closed_by' => $approver->id]);
        $this->get(route('iso.files.download', [$workflow, $file]))->assertDownload('bien-ban.pdf');
    }

    public function test_major_revision_requires_more_than_twenty_percent_of_credits(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Chuyên viên CTĐT');
        $program = Program::create(['code' => 'ISO-THRESHOLD', 'name_vi' => 'Kiểm tra phân loại', 'degree_level' => 'dai_hoc', 'total_credits' => 120]);

        $this->actingAs($user)->post(route('iso.store'), ['program_id' => $program->id, 'type' => 'major', 'changed_credits' => 24, 'title' => 'Không đủ lớn', 'start_date' => '2026-09-01'])->assertSessionHasErrors('changed_credits');
        $this->assertDatabaseCount('iso_workflows', 0);
    }

    public function test_completed_cancelled_and_closed_workflows_reject_all_mutations(): void
    {
        Storage::fake('local');
        $manager = User::factory()->create();
        $manager->assignRole('Lãnh đạo phòng');
        $program = Program::create(['code' => 'ISO-LOCK', 'name_vi' => 'Chương trình', 'degree_level' => 'dai_hoc', 'total_credits' => 120]);
        foreach ([['completed', now()], ['completed', null], ['cancelled', null], ['in_progress', now()]] as [$status, $closedAt]) {
            $workflow = IsoWorkflow::create(['program_id' => $program->id, 'type' => 'minor', 'title' => 'Hồ sơ đã khóa', 'start_date' => '2026-01-01', 'expected_end_date' => '2026-12-31', 'status' => $status, 'closed_at' => $closedAt, 'approved_at' => now(), 'approved_by' => $manager->id]);
            $step = $workflow->steps()->create(['step_number' => 1, 'step_name' => 'Thẩm định', 'deadline' => '2026-12-01', 'status' => 'completed', 'note' => 'Đã chốt']);
            $before = $workflow->fresh()->getAttributes();
            $stepBefore = $step->fresh()->getAttributes();

            config(['app.debug' => true]);
            $this->actingAs($manager)->patch(route('iso.steps.update', [$workflow, $step->id]), ['status' => 'not_started', 'note' => 'Sửa sau khi đóng'])->assertStatus(409)
                ->assertSee('Thay đổi của bạn chưa được lưu')
                ->assertSee('href="'.route('iso.show', $workflow).'"', false)
                ->assertDontSee('Exception trace');
            $this->patch(route('iso.steps.assign', [$workflow, $step->id]), ['assigned_to_user_id' => $manager->id])->assertStatus(409);
            $this->post(route('iso.files.upload', $workflow), ['file' => UploadedFile::fake()->create('blocked.pdf', 10, 'application/pdf')])->assertStatus(409);
            $this->post(route('iso.approve', $workflow))->assertStatus(409);
            $this->post(route('iso.close', $workflow))->assertStatus(409);
            $this->assertSame($before, $workflow->fresh()->getAttributes());
            $this->assertSame($stepBefore, $step->fresh()->getAttributes());
            $this->get(route('iso.show', $workflow))->assertOk()
                ->assertDontSee('action="'.route('iso.steps.update', [$workflow, $step->id]).'"', false)
                ->assertDontSee('action="'.route('iso.steps.assign', [$workflow, $step->id]).'"', false)
                ->assertDontSee('action="'.route('iso.files.upload', $workflow).'"', false)
                ->assertDontSee('action="'.route('iso.approve', $workflow).'"', false)
                ->assertDontSee('action="'.route('iso.close', $workflow).'"', false);
        }
        $this->assertDatabaseCount('iso_histories', 0);
        $this->assertDatabaseCount('iso_files', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('iso-files'));
    }

    public function test_deadline_notice_is_sent_once_after_four_working_days(): void
    {
        Notification::fake();
        $assignee = User::factory()->create();
        $assignee->assignRole('Chuyên viên CTĐT');
        $manager = User::factory()->create();
        $manager->assignRole('Lãnh đạo phòng');
        $program = Program::create(['code' => 'ISO-LATE', 'name_vi' => 'Kiểm tra hạn', 'degree_level' => 'dai_hoc', 'total_credits' => 120]);
        $workflow = IsoWorkflow::create(['program_id' => $program->id, 'type' => 'new', 'title' => 'Quá hạn', 'start_date' => '2026-09-01', 'expected_end_date' => '2027-03-01', 'status' => 'in_progress']);
        $step = $workflow->steps()->create(['step_number' => 1, 'step_name' => 'Khảo sát', 'deadline' => '2026-10-05', 'status' => 'in_progress', 'assigned_to_user_id' => $assignee->id]);
        $this->travelTo('2026-10-08');

        $this->artisan('iso:check-deadlines')->assertSuccessful();
        Notification::assertNothingSent();
        $this->travelTo('2026-10-09');
        $this->artisan('iso:check-deadlines')->assertSuccessful();
        $this->artisan('iso:check-deadlines')->assertSuccessful();

        Notification::assertSentTo($assignee, IsoDeadlineNotification::class, 1);
        Notification::assertSentTo($manager, IsoDeadlineNotification::class, 1);
        $this->assertNotNull($step->fresh()->overdue_notified_at);
    }
}
