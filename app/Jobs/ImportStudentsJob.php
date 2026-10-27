<?php

namespace App\Jobs;

use App\Imports\StudentImport;
use App\Models\AcademicImport;
use App\Models\User;
use App\Notifications\AcademicImportFailedNotification;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;

class ImportStudentsJob implements ShouldQueue
{
    use Batchable, InteractsWithQueue, Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(public string $path, public int $userId, public int $importId) {}

    public function handle(): void
    {
        $batch = AcademicImport::findOrFail($this->importId);
        $batch->update(['status' => 'processing', 'started_at' => now()]);

        try {
            HeadingRowFormatter::default('none');
            $import = new StudentImport;
            Excel::import($import, $this->path);
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
        $batch = AcademicImport::find($this->importId);
        if ($batch) {
            $batch->update(['status' => 'failed', 'failure_message' => $exception->getMessage(), 'completed_at' => now()]);
        }
    }
}
