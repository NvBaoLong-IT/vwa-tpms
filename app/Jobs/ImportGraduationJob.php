<?php

namespace App\Jobs;

use App\Imports\GraduationImport;
use App\Models\AcademicImport;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
use Throwable;

class ImportGraduationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public bool $failOnTimeout = true;

    public function __construct(public string $path, public int $userId, public int $importId) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            DB::transaction(function (): void {
                $batch = AcademicImport::whereKey($this->importId)->where('kind', 'graduation')->lockForUpdate()->firstOrFail();
                if ($batch->status === 'completed') {
                    return;
                }
                $user = User::find($this->userId);
                if (! $user?->is_active || ! $user->can('diploma.issue')) {
                    throw new \RuntimeException('Tài khoản gửi danh sách không còn quyền nhập văn bằng.');
                }
                $batch->update(['status' => 'processing', 'started_at' => now()]);
                HeadingRowFormatter::default('none');
                $import = new GraduationImport($this->userId);
                Excel::import($import, $this->path, 'local');
                $batch->update(['status' => 'completed', 'rows_imported' => $import->imported,
                    'error_count' => count($import->errors), 'errors' => $import->errors, 'completed_at' => now()]);
            });
        } catch (Throwable $exception) {
            $this->failed($exception);
            throw $exception;
        } finally {
            Storage::disk('local')->delete($this->path);
        }
    }

    public function failed(?Throwable $exception): void
    {
        AcademicImport::whereKey($this->importId)->where('kind', 'graduation')->where('status', '!=', 'completed')
            ->update(['status' => 'failed', 'failure_message' => 'Không thể nhập danh sách hoặc tác vụ quá thời gian. Vui lòng kiểm tra tệp và gửi lại.', 'completed_at' => now()]);
    }
}
