<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WebScheduledRun;
use App\Services\WebScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OperationsStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_office_manager_can_view_operational_status(): void
    {
        $this->get(route('operations.status'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('operations.status'))->assertForbidden();
    }

    public function test_console_heartbeat_and_backup_age_are_visible_and_web_activity_cannot_mask_staleness(): void
    {
        $this->travelTo(Carbon::parse('2026-10-20 00:05:00', 'Asia/Bangkok'));
        Storage::fake('backups');
        $manager = User::factory()->create();
        $manager->assignRole(Role::findOrCreate('Lãnh đạo phòng', 'web'));
        $this->actingAs($manager)->get(route('operations.status'))->assertOk()->assertSee('Chưa nhận hoặc đã quá 35 phút');
        $service = app(WebScheduleService::class);
        $service->tickFromConsole();
        $heartbeat = WebScheduledRun::where('run_key', 'scheduler-heartbeat')->firstOrFail();
        $this->assertSame('console', $heartbeat->origin);
        $disk = Storage::disk('backups');
        $path = config('backup.backup.name').'/2026-10-20.zip';
        $disk->put($path, 'synthetic file age probe');
        touch($disk->path($path), now()->timestamp);
        $this->get(route('operations.status'))->assertOk()->assertSee('Đang nhận tín hiệu lịch tự động.')->assertSee('Có bản sao được ghi trong 26 giờ gần nhất.');

        $this->travel(40)->minutes();
        $service->tick();
        $this->get(route('operations.status'))->assertOk()->assertSee('Chưa nhận hoặc đã quá 35 phút');
        $this->assertSame($heartbeat->finished_at->timestamp, $heartbeat->fresh()->finished_at->timestamp);
        touch($disk->path($path), now()->subHours(27)->timestamp);
        clearstatcache();
        $this->get(route('operations.status'))->assertOk()->assertSee('Chưa có bản sao hoặc bản sao đã quá 26 giờ.');

    }
}
