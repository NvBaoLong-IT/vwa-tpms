<?php

namespace App\Services;

use App\Models\WebScheduledRun;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WebScheduleService
{
    public const TASKS = [
        'academic' => 'Quét cảnh báo học vụ',
        'iso' => 'Kiểm tra hạn rà soát',
        'academic_report' => 'Báo cáo năm học',
        'blank_report' => 'Báo cáo phôi 31/12',
    ];

    public function tick(): array
    {
        $now = Carbon::now(config('web_schedule.timezone'));
        $results = [];
        foreach ($this->dueTasks($now) as [$task, $period]) {
            $run = $this->runOnce($task, $period, 'auto:'.$task.':'.$period, 'automatic');
            $results[$task] = $run->status;
        }

        return $results;
    }

    public function tickFromConsole(): array
    {
        $heartbeat = WebScheduledRun::updateOrCreate(['run_key' => 'scheduler-heartbeat'], [
            'task' => 'heartbeat', 'period' => now()->toDateString(), 'origin' => 'console',
            'status' => 'running', 'started_at' => now(), 'finished_at' => null, 'error' => null,
        ]);
        try {
            $results = $this->tick();
            $heartbeat->update(['status' => 'completed', 'finished_at' => now()]);

            return $results;
        } catch (\Throwable $exception) {
            $heartbeat->update(['status' => 'failed', 'finished_at' => now(), 'error' => Str::limit($exception->getMessage(), 2000)]);
            throw $exception;
        }
    }

    public function runNow(string $task, ?int $year, int $userId): WebScheduledRun
    {
        if (! array_key_exists($task, self::TASKS)) {
            throw new \InvalidArgumentException('Tác vụ không hợp lệ.');
        }
        $now = Carbon::now(config('web_schedule.timezone'));
        $period = in_array($task, ['academic_report', 'blank_report'], true) ? (string) ($year ?: $now->year) : $now->toDateString();
        if (in_array($task, ['academic_report', 'blank_report'], true) && ((int) $period < 2000 || (int) $period > $this->latestDueYear($task))) {
            throw new \InvalidArgumentException('Năm báo cáo chưa đến hạn hoặc không hợp lệ.');
        }

        return $this->runOnce($task, $period, 'manual:'.$task.':'.Str::uuid(), 'manual', $userId);
    }

    public function latestDueYear(string $task): int
    {
        $now = Carbon::now(config('web_schedule.timezone'));
        [$month, $day] = match ($task) {
            'academic_report' => [8, 31],
            'blank_report' => [12, 31],
            default => throw new \InvalidArgumentException('Không phải báo cáo định kỳ.'),
        };
        $due = Carbon::create($now->year, $month, $day, 23, 0, 0, $now->timezone);

        return $now->gte($due) ? $now->year : $now->year - 1;
    }

    private function dueTasks(Carbon $now): array
    {
        $tasks = [];
        if ($now->format('H:i') >= '01:00') {
            $tasks[] = ['academic', $now->toDateString()];
        }
        if ($now->format('H:i') >= '01:15') {
            $tasks[] = ['iso', $now->toDateString()];
        }
        $activation = WebScheduledRun::where('run_key', 'activation')->firstOrFail()->started_at->timezone($now->timezone);
        foreach (['academic_report' => [8, 31], 'blank_report' => [12, 31]] as $task => [$month, $day]) {
            $due = Carbon::create($now->year, $month, $day, 23, 0, 0, $now->timezone);
            if ($now->lt($due)) {
                $due->subYear();
            }
            if ($due->gte($activation)) {
                $tasks[] = [$task, (string) $due->year];
            }
        }

        return $tasks;
    }

    private function runOnce(string $task, string $period, string $key, string $origin, ?int $userId = null): WebScheduledRun
    {
        $claimed = DB::transaction(function () use ($task, $period, $key, $origin, $userId): bool {
            DB::table('web_scheduled_runs')->insertOrIgnore([
                'run_key' => $key, 'task' => $task, 'period' => $period, 'origin' => $origin,
                'status' => 'waiting', 'triggered_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $run = WebScheduledRun::where('run_key', $key)->lockForUpdate()->firstOrFail();
            if ($run->status === 'completed'
                || ($run->status === 'running' && $run->started_at?->gt(now()->subHours(2)))
                || ($run->status === 'failed' && $run->retry_after?->isFuture())) {
                return false;
            }
            $run->update(['status' => 'running', 'started_at' => now(), 'finished_at' => null, 'retry_after' => null, 'error' => null]);

            return true;
        });
        $run = WebScheduledRun::where('run_key', $key)->firstOrFail();
        if (! $claimed) {
            return $run;
        }
        try {
            match ($task) {
                'academic' => app(AcademicWarningService::class)->scan(),
                'iso' => app(IsoDeadlineService::class)->check(),
                'academic_report' => app(AnnualReportService::class)->generate('academic_year_summary', (int) $period),
                'blank_report' => app(AnnualReportService::class)->generate('annual_blanks', (int) $period),
            };
            $run->update(['status' => 'completed', 'finished_at' => now()]);
        } catch (\Throwable $exception) {
            $run->update(['status' => 'failed', 'finished_at' => now(), 'retry_after' => now()->addMinutes(15), 'error' => Str::limit($exception->getMessage(), 2000)]);
            report($exception);
        }

        return $run;
    }
}
