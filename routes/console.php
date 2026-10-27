<?php

use App\Services\WebScheduleService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => app(WebScheduleService::class)->tickFromConsole())
    ->everyFifteenMinutes()
    ->name('vwa-operational-schedule')->withoutOverlapping(120)
    ->timezone(config('web_schedule.timezone'));

Schedule::command('backup:run')->dailyAt('01:00')->withoutOverlapping(180)
    ->timezone(config('web_schedule.timezone'))->appendOutputTo(storage_path('logs/backup.log'));
Schedule::command('backup:monitor')->dailyAt('03:00')->withoutOverlapping()
    ->timezone(config('web_schedule.timezone'))->appendOutputTo(storage_path('logs/backup.log'));
Schedule::command('backup:clean')->dailyAt('03:30')->withoutOverlapping()
    ->timezone(config('web_schedule.timezone'))->appendOutputTo(storage_path('logs/backup.log'));
