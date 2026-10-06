<?php

namespace App\Jobs;

use App\Imports\AcademicProgressImport;
use App\Models\AcademicImport;
use App\Models\User;
use App\Notifications\AcademicImportFailedNotification;
use App\Services\AcademicWarningService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;

class ImportAcademicProgressJob implements ShouldQueue
{
    use Batchable, InteractsWithQueue, Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(public string $path, public int $userId, public int $importId)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(AcademicWarningService $warningService): void
    {
        $batch = AcademicImport::findOrFail($this->importId);
        $batch->update(['status' => 'processing', 'started_at' => now()]);
        try {
            HeadingRowFormatter::default('none');
            $import = new AcademicProgressImport;
            Excel::import($import, $this->path);
            $warningService->scan();
            $batch->update(['status' => 'completed', 'rows_imported' => $import->imported, 'error_count' => count($import->errors), 'errors' => $import->errors, 'completed_at' => now()]);
            if ($import->errors !== []) {
                User::find($this->userId)?->notify(new AcademicImportFailedNotification($batch->fresh()));
            }
        } catch (\Throwable $exception) {
            $batch->update(['status' => 'failed', 'failure_message' => $exception->getMessage(), 'completed_at' => now()]);
            User::find($this->userId)?->notify(new AcademicImportFailedNotification($batch->fresh()));
            throw $exception;
        } finally {
            Storage::disk('local')->delete($this->path);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $import = AcademicImport::find($this->importId);
        if ($import) {
            $import->update(['status' => 'failed', 'failure_message' => $exception->getMessage(), 'completed_at' => now()]);
            User::find($this->userId)?->notify(new AcademicImportFailedNotification($import->fresh()));
        }
    }
}
