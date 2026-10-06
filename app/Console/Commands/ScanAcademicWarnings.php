<?php

namespace App\Console\Commands;

use App\Services\AcademicWarningService;
use Illuminate\Console\Command;

class ScanAcademicWarnings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'academic:scan-warnings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan student progress and create academic warnings';

    /**
     * Execute the console command.
     */
    public function handle(AcademicWarningService $service): int
    {
        $count = $service->scan();
        $this->info("Created {$count} new warnings.");

        return self::SUCCESS;
    }
}
