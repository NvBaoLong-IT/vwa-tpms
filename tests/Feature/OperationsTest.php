<?php

namespace Tests\Feature;

use App\Jobs\ImportAcademicProgressJob;
use App\Models\AcademicImport;
use App\Models\Program;
use App\Models\User;
use App\Notifications\AcademicImportFailedNotification;
use App\Services\AcademicWarningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_queue_processes_import_and_failed_callback_records_failure(): void
    {
        config(['queue.default' => 'sync']);
        Storage::fake('local');
        Notification::fake();
        $permission = Permission::findOrCreate('academic.process', 'web');
        $role = Role::create(['name' => 'Operations academic', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
        $user = User::factory()->create();
        $user->assignRole($role);
        $program = Program::create(['code' => 'OPS', 'name_vi' => 'Ngành vận hành', 'degree_level' => 'dai_hoc', 'total_credits' => 120]);
        Storage::disk('local')->put('ops.csv', "MaSV,HoTen,MaNganh,HocKy,TinChiTichLuy,GPA,TinChiYeuCau\nOPS1,Sinh Vien,OPS,2026-1,30,2.5,60\n");
        $batch = AcademicImport::create(['user_id' => $user->id, 'original_name' => 'ops.csv', 'status' => 'queued']);
        (new ImportAcademicProgressJob('ops.csv', $user->id, $batch->id))->handle(app(AcademicWarningService::class));
        $this->assertDatabaseHas('academic_imports', ['id' => $batch->id, 'status' => 'completed', 'rows_imported' => 1]);
        $failed = AcademicImport::create(['user_id' => $user->id, 'original_name' => 'failed.csv', 'status' => 'processing']);
        $exception = new \RuntimeException('worker failure');
        (new ImportAcademicProgressJob('missing.csv', $user->id, $failed->id))->failed($exception);
        $this->assertDatabaseHas('academic_imports', ['id' => $failed->id, 'status' => 'failed', 'failure_message' => 'worker failure']);
        Notification::assertSentTo($user, AcademicImportFailedNotification::class);
    }

    public function test_maintenance_commands_remain_available(): void
    {
        $this->artisan('academic:scan-warnings')->assertSuccessful();
        $this->artisan('iso:check-deadlines')->assertSuccessful();
    }
}
