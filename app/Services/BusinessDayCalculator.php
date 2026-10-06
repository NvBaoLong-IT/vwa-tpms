<?php

namespace App\Services;

use Carbon\Carbon;

class BusinessDayCalculator
{
    public function daysOverdue(Carbon $deadline, Carbon $today): int
    {
        $days = 0;
        $holidays = config('iso.holidays', []);
        $end = Carbon::parse($today->toDateString(), $today->timezone);
        for ($date = Carbon::parse($deadline->toDateString(), $today->timezone)->addDay(); $date->lte($end); $date->addDay()) {
            if (! $date->isWeekend() && ! in_array($date->toDateString(), $holidays, true)) {
                $days++;
            }
        }

        return $days;
    }

    public function addMonths(Carbon $date, int $months): Carbon
    {
        $deadline = $date->copy()->addMonthsNoOverflow($months);
        while ($deadline->isWeekend() || in_array($deadline->toDateString(), config('iso.holidays', []), true)) {
            $deadline->addDay();
        }

        return $deadline;
    }
}
