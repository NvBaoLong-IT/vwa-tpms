<?php

namespace App\Console\Commands;

use App\Services\AnnualReportService;
use Illuminate\Console\Command;

class GenerateAnnualReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:generate-annual {type : academic_year_summary or annual_blanks} {year? : Ending or calendar year}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Save a year-end training or diploma blank report';

    /**
     * Execute the console command.
     */
    public function handle(AnnualReportService $reports): int
    {
        $type = (string) $this->argument('type');
        $year = (int) ($this->argument('year') ?: now()->year);
        if (! in_array($type, ['academic_year_summary', 'annual_blanks'], true) || $year < 2000 || $year > 2100) {
            $this->error('Invalid report type or year.');

            return self::FAILURE;
        }
        $history = $reports->generate($type, $year);
        $this->info('Saved annual report #'.$history->id.': '.$history->file_path);

        return self::SUCCESS;
    }
}
