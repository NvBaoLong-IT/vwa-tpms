<?php

namespace App\Console\Commands;

use App\Services\IsoDeadlineService;
use Illuminate\Console\Command;

class CheckIsoDeadlines extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'iso:check-deadlines';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Report overdue ISO workflow steps';

    /**
     * Execute the console command.
     */
    public function handle(IsoDeadlineService $service): int
    {
        $overdue = $service->check();
        $this->info("{$overdue} ISO steps passed three working days overdue and were notified.");

        return self::SUCCESS;
    }
}
