<?php

namespace Tests\Feature;

use App\Models\ReportHistory;
use App\Models\User;
use App\Models\WebScheduledRun;
use App\Services\AcademicWarningService;
use App\Services\WebScheduleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WebScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_console_tick_runs_daily_tasks_once(): void
    {
        $this->get('/schedule/tick')->assertStatus(405);
        $this->travelTo(Carbon::parse('2026-10-09 09:00:00', 'Asia/Bangkok'));

        $this->assertSame(['academic' => 'completed', 'iso' => 'completed'], app(WebScheduleService::class)->tick());
        app(WebScheduleService::class)->tick();

        $this->assertSame(1, WebScheduledRun::where('task', 'academic')->count());
        $this->assertSame(1, WebScheduledRun::where('task', 'iso')->count());
    }

    public function test_first_web_visit_after_year_end_saves_one_blank_report(): void
    {
        Storage::fake('local');
        $this->travelTo(Carbon::parse('2027-01-01 09:00:00', 'Asia/Bangkok'));

        $this->assertSame('completed', app(WebScheduleService::class)->tick()['blank_report']);
        app(WebScheduleService::class)->tick();

        $history = ReportHistory::where('report_type', 'annual_blanks')->firstOrFail();
        $this->assertSame(2026, (int) $history->filters['academic_year']);
        Storage::disk('local')->assertExists($history->file_path);
        $this->assertSame(1, WebScheduledRun::where('task', 'blank_report')->count());
    }

    public function test_local_daily_due_time_and_academic_year_end_are_respected(): void
    {
        Storage::fake('local');
        $this->travelTo(Carbon::parse('2027-08-31 00:30:00', 'Asia/Bangkok'));
        $this->assertSame('completed', app(WebScheduleService::class)->tick()['blank_report']);
        $this->assertDatabaseMissing('web_scheduled_runs', ['task' => 'academic', 'period' => '2027-08-31']);
        $this->travelTo(Carbon::parse('2027-08-31 01:16:00', 'Asia/Bangkok'));
        $this->assertSame('completed', app(WebScheduleService::class)->tick()['academic']);
        $this->travelTo(Carbon::parse('2027-08-31 23:05:00', 'Asia/Bangkok'));

        $this->assertSame('completed', app(WebScheduleService::class)->tick()['academic_report']);
        $this->assertDatabaseHas('report_histories', ['report_type' => 'academic_year_summary', 'format' => 'pdf']);
    }

    public function test_queue_and_schedule_web_endpoints_are_closed(): void
    {
        $this->get('/queue')->assertNotFound();
        $this->post('/queue/1/retry')->assertNotFound();
        $this->get('/schedule')->assertNotFound();
        $this->post('/schedule/run', ['task' => 'academic'])->assertNotFound();
    }

    public function test_failed_tick_waits_before_retrying(): void
    {
        $this->travelTo(Carbon::parse('2026-10-09 09:00:00', 'Asia/Bangkok'));
        $service = \Mockery::mock(AcademicWarningService::class);
        $service->shouldReceive('scan')->once()->andThrow(new \RuntimeException('temporary failure'));
        app()->instance(AcademicWarningService::class, $service);

        $this->assertSame('failed', app(WebScheduleService::class)->tick()['academic']);
        $this->assertSame('failed', app(WebScheduleService::class)->tick()['academic']);
        $run = WebScheduledRun::where('task', 'academic')->firstOrFail();
        $this->assertNotNull($run->retry_after);
        $this->travel(16)->minutes();
        $replacement = \Mockery::mock(AcademicWarningService::class);
        $replacement->shouldReceive('scan')->once()->andReturn(0);
        app()->instance(AcademicWarningService::class, $replacement);

        $this->assertSame('completed', app(WebScheduleService::class)->tick()['academic']);
    }

    public function test_authenticated_web_tick_runs_tasks_once_for_multiple_users(): void
    {
        $this->travelTo(Carbon::parse('2026-10-09 09:00:00', 'Asia/Bangkok'));
        $user = User::factory()->create();
        $other = User::factory()->create();
        $permission = Permission::findOrCreate('dashboard.view', 'web');
        $user->givePermissionTo($permission);
        $other->givePermissionTo($permission);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('name="web-schedule-url"', false);
        $this->post(route('schedule.tick'))->assertOk()->assertJson(['status' => 'checked']);
        $this->actingAs($other)->post(route('schedule.tick'))->assertOk()->assertJson(['status' => 'skipped']);
        $this->travel(61)->seconds();
        $this->post(route('schedule.tick'))->assertOk()->assertJson(['status' => 'checked']);
        $this->assertSame(1, WebScheduledRun::where('task', 'academic')->count());
        $this->assertSame(1, WebScheduledRun::where('task', 'iso')->count());
    }

    public function test_web_tick_requires_an_active_authorized_session(): void
    {
        $this->postJson(route('schedule.tick'))->assertUnauthorized();
        $user = User::factory()->create();
        $this->actingAs($user)->postJson(route('schedule.tick'))->assertForbidden();
        $user->givePermissionTo(Permission::findOrCreate('dashboard.view', 'web'));
        $user->update(['is_active' => false]);
        $this->postJson(route('schedule.tick'))->assertUnauthorized();
        $this->assertGuest();
        $this->assertDatabaseCount('web_scheduled_runs', 1);
    }

    public function test_web_tick_skips_work_while_another_request_holds_the_lock(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('dashboard.view', 'web'));
        $lock = Cache::lock('web-schedule:tick', 7200);
        $this->assertTrue($lock->get());
        try {
            $this->actingAs($user)->postJson(route('schedule.tick'))->assertOk()->assertJson(['status' => 'skipped']);
            $this->assertDatabaseCount('web_scheduled_runs', 1);
        } finally {
            $lock->release();
        }
    }
}
