<?php

namespace App\Jobs;

use App\Exceptions\CurriculumExtractionException;
use App\Models\DocumentImport;
use App\Models\User;
use App\Services\ModelApiCurriculumExtractor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ExtractCurriculumDocumentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public bool $failOnTimeout = true;

    public function __construct(public int $documentImportId) {}

    /**
     * Execute the job.
     */
    public function handle(ModelApiCurriculumExtractor $extractor): void
    {
        Cache::lock('curriculum-extract:'.$this->documentImportId, $this->timeout + 60)->get(function () use ($extractor): void {
            $draft = DocumentImport::findOrFail($this->documentImportId);
            if ($draft->status !== 'processing') {
                return;
            }
            try {
                $user = User::find($draft->uploaded_by);
                if (! $user?->is_active || ! $user->can('programs.import')) {
                    throw new CurriculumExtractionException('Tài khoản gửi tài liệu không còn quyền nhập chương trình.');
                }
                $file = new UploadedFile(Storage::disk('local')->path($draft->path), $draft->original_name, $draft->mime_type, null, true);
                $draft->update(['status' => 'review', 'payload' => $extractor->extract($file, $draft->mime_type), 'error_message' => null]);
            } catch (Throwable $exception) {
                $draft->update(['status' => 'failed', 'error_message' => $exception instanceof CurriculumExtractionException
                    ? $exception->getMessage() : 'Không thể trích xuất tài liệu. Kiểm tra cấu hình AI hoặc nội dung tệp.']);
                throw $exception;
            }
        });
    }

    public function failed(?Throwable $exception): void
    {
        DocumentImport::whereKey($this->documentImportId)->where('status', 'processing')
            ->update(['status' => 'failed', 'error_message' => 'Tác vụ trích xuất bị lỗi hoặc quá thời gian. Vui lòng gửi lại tài liệu.']);
    }
}
