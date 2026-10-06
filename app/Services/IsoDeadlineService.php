<?php

namespace App\Services;

use App\Models\IsoStep;
use App\Models\User;
use App\Notifications\IsoDeadlineNotification;
use Carbon\Carbon;

class IsoDeadlineService
{
    public function check(): int
    {
        $overdue = 0;
        $today = Carbon::today(config('web_schedule.timezone'));
        $calendar = new BusinessDayCalculator;
        IsoStep::with('assignee')->whereHas('workflow', fn ($query) => $query->whereIn('status', ['draft', 'in_progress']))
            ->where('status', '!=', 'completed')->whereNull('overdue_notified_at')->whereDate('deadline', '<', $today)
            ->chunkById(200, function ($steps) use ($calendar, $today, &$overdue): void {
                foreach ($steps as $step) {
                    if ($calendar->daysOverdue($step->deadline, $today) <= 3) {
                        continue;
                    }
                    $recipients = collect([$step->assignee])->filter()->merge(User::permission('iso.approve')->get())->unique('id');
                    if ($recipients->isEmpty()) {
                        continue;
                    }
                    foreach ($recipients as $recipient) {
                        $recipient->notify(new IsoDeadlineNotification($step, 'overdue'));
                    }
                    $step->update(['overdue_notified_at' => now()]);
                    $overdue++;
                }
            });

        return $overdue;
    }
}
