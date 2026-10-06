<?php

namespace Tests\Feature;

use App\Models\ReportHistory;
use App\Models\WebScheduledRun;
use App\Services\AcademicWarningService;
use App\Services\WebScheduleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WebScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_console_tick_runs_daily_tasks_once(): void
    {
        $this->get('/schedule/tick')->assertNotFound();
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
}
