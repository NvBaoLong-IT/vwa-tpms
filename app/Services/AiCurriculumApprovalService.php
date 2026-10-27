<?php

namespace App\Services;

use App\Models\DocumentImport;
use App\Models\Program;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AiCurriculumApprovalService
{
    public function __construct(private CurriculumDraftImporter $importer, private CurriculumAuditService $audit) {}

    public function submit(DocumentImport $draft, string $comparisonHash, int $userId): void
    {
        CurriculumWriteLock::transaction(function () use ($draft, $comparisonHash, $userId): void {
            $draft = DocumentImport::whereKey($draft->id)->lockForUpdate()->firstOrFail();
            abort_unless($draft->uploaded_by === $userId, 403);
            abort_unless(in_array($draft->status, ['review', 'returned'], true), 422, 'Bản nháp không còn khả dụng để trình duyệt.');
            if (! hash_equals($this->importer->comparisonHash($draft), $comparisonHash)) {
                throw ValidationException::withMessages(['payload' => 'Dữ liệu đã thay đổi sau khi xem trước. Hãy mở lại bảng đối chiếu và trình lại.']);
            }
            $before = $draft->status;
            $comparison = $this->importer->comparison($draft);
            $this->assertClassification($draft);
            $this->assertCredits($comparison['after']);
            $draft->refresh();
            $draft->update(['status' => 'submitted', 'submitted_by' => $userId, 'submitted_at' => now(), 'approval_expires_at' => now()->addDays(7), 'reviewed_by' => null, 'reviewed_at' => null, 'review_note' => null, 'approved_hash' => null, 'comparison_hash' => $comparison['hash']]);
            $this->record($draft, 'ai_submitted', $before);
        });
    }

    public function review(DocumentImport $draft, bool $approve, string $note, int $userId): void
    {
        CurriculumWriteLock::transaction(function () use ($draft, $approve, $note, $userId): void {
            abort_unless(User::findOrFail($userId)->hasRole('Lãnh đạo phòng'), 403);
            $draft = DocumentImport::whereKey($draft->id)->lockForUpdate()->firstOrFail();
            abort_unless($draft->status === 'submitted', 422, 'Bản nhập không còn chờ duyệt.');
            abort_unless($draft->submitted_by !== null && $draft->submitted_by !== $userId && $draft->uploaded_by !== $userId, 403, 'Người gửi hoặc trình không được tự duyệt bản nhập AI.');
            if ($approve) {
                $this->assertUnchanged($draft);
                $this->assertCredits($draft->comparison_after ?? []);
            }
            $draft->update(['status' => $approve ? 'approved' : 'returned', 'reviewed_by' => $userId, 'reviewed_at' => now(), 'review_note' => $note, 'approved_hash' => $approve ? $draft->comparison_hash : null]);
            $this->record($draft, $approve ? 'ai_approved' : 'ai_returned', 'submitted');
        });
    }

    public function apply(DocumentImport $draft, string $comparisonHash, int $userId): Program
    {
        return CurriculumWriteLock::transaction(function () use ($draft, $comparisonHash, $userId): Program {
            $draft = DocumentImport::whereKey($draft->id)->lockForUpdate()->firstOrFail();
            abort_unless($draft->status === 'approved', 422, 'Bản nhập AI phải được lãnh đạo khác người trình phê duyệt trước khi ghi dữ liệu.');
            abort_unless($draft->submitted_by === $userId && $draft->uploaded_by === $userId, 403);
            abort_unless($draft->reviewed_by !== null && $draft->reviewed_by !== $draft->submitted_by, 422, 'Chưa có phê duyệt độc lập.');
            $this->assertUnchanged($draft);
            $this->assertCredits($draft->comparison_after ?? []);
            if (! hash_equals($draft->approved_hash ?? '', $comparisonHash) || ! hash_equals($draft->approved_hash ?? '', $draft->comparison_hash ?? '')) {
                throw ValidationException::withMessages(['payload' => 'Nội dung xác nhận không đúng bản đã được duyệt. Hãy tải và trình duyệt tài liệu lại.']);
            }
            $new = $draft->program_id === null;
            $program = $new ? $this->importer->importNew($draft, $userId) : $this->importer->import($draft, $userId);
            $this->assertCredits(app(ProgramVersionService::class)->snapshot($program->fresh()));
            $draft->update(['program_id' => $program->id, 'status' => 'imported', 'comparison_after' => app(ProgramVersionService::class)->snapshot($program->fresh()), 'imported_by' => $userId, 'imported_at' => now()]);
            $this->record($draft, 'ai_applied', 'approved');

            return $program;
        });
    }

    private function assertUnchanged(DocumentImport $draft): void
    {
        $this->assertClassification($draft);
        if ($draft->approval_expires_at === null || ! $draft->approval_expires_at->isFuture()) {
            throw ValidationException::withMessages(['payload' => 'Bản nhập đã hết hạn duyệt. Hãy tải tài liệu và trình duyệt lại.']);
        }
        if (! hash_equals($draft->comparison_hash ?? '', $this->importer->comparisonHash($draft))) {
            throw ValidationException::withMessages(['payload' => 'Dữ liệu CTĐT, học phần dùng chung hoặc nội dung AI đã thay đổi sau khi trình. Hãy đối chiếu và trình duyệt lại.']);
        }
    }

    private function assertClassification(DocumentImport $draft): void
    {
        $this->importer->validatePayload($draft->payload ?? []);
        foreach ($draft->payload['courses'] ?? [] as $course) {
            if (! array_key_exists('is_mandatory', $course) || $course['is_mandatory'] === null || blank($course['knowledge_block'] ?? null)) {
                throw ValidationException::withMessages(['payload' => 'Cần xác định loại bắt buộc/tự chọn và khối kiến thức của tất cả học phần trước khi trình hoặc nhập.']);
            }
        }
    }

    private function assertCredits(array $snapshot): void
    {
        if (! isset($snapshot['program'], $snapshot['courses'], $snapshot['required_blocks'])) {
            throw ValidationException::withMessages(['payload' => 'Cần mở lại bản xem trước và trình duyệt để kiểm tra tín chỉ đầy đủ.']);
        }
        $errors = collect(app(ProgramCreditReconciler::class)->forSnapshot($snapshot)['issues'])->where('severity', 'error')->pluck('message')->all();
        if ($errors !== []) {
            throw ValidationException::withMessages(['payload' => $errors]);
        }
    }

    private function record(DocumentImport $draft, string $action, string $before): void
    {
        $this->audit->record('document_import', $draft->id, $action, ['status' => $before], $draft->only(['status', 'submitted_by', 'reviewed_by', 'review_note', 'imported_by']), $draft->program_id, ['filename' => $draft->original_name]);
    }
}
