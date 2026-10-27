<?php

namespace App\Http\Controllers;

use App\Exceptions\CurriculumExtractionException;
use App\Jobs\ExtractCurriculumDocumentJob;
use App\Models\DocumentImport;
use App\Models\Program;
use App\Services\AiCurriculumApprovalService;
use App\Services\CurriculumAuditService;
use App\Services\CurriculumDraftImporter;
use App\Services\CurriculumReviewSummary;
use App\Services\CurriculumSnapshotDiffer;
use App\Services\CurriculumWriteLock;
use App\Services\ProgramCreditReconciler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class ProgramDocumentImportController extends Controller
{
    public function editDraft(Request $request, DocumentImport $documentImport, CurriculumDraftImporter $importer): RedirectResponse
    {
        $data = $request->validate([
            'payload_hash' => ['required', 'string', 'size:64'],
            'draft_program' => ['required', 'array:code,name_vi,name_en,degree_level,delivery_mode,total_credits,elective_credits_required,decision_number,decision_date'],
            'draft_program.code' => ['nullable', 'string', 'max:30'],
            'draft_program.name_vi' => ['nullable', 'string', 'max:255'],
            'draft_program.total_credits' => ['nullable', 'integer', 'min:1', 'max:999'],
            'draft_program.elective_credits_required' => ['nullable', 'integer', 'min:0', 'max:999'],
            'blocks_text' => ['nullable', 'string', 'max:10000'], 'groups_text' => ['nullable', 'string', 'max:20000'],
            'draft_courses' => ['present', 'array', 'max:1000'],
            'draft_courses.*' => ['array:course_code,name_vi,credits,knowledge_block,is_mandatory,suggested_semester,elective_group_code,prerequisite_course_code,corequisite_course_code,counts_toward_total,is_deferred'],
        ]);
        CurriculumWriteLock::transaction(function () use ($request, $documentImport, $importer, $data): void {
            $draft = DocumentImport::whereKey($documentImport->id)->lockForUpdate()->firstOrFail();
            abort_unless($draft->uploaded_by === $request->user()->id, 403);
            abort_unless(in_array($draft->status, ['review', 'returned'], true), 422, 'Chỉ sửa bản chưa trình hoặc được trả về.');
            if (! hash_equals(app(CurriculumSnapshotDiffer::class)->fingerprint($draft->payload ?? []), $data['payload_hash'])) {
                throw ValidationException::withMessages(['payload' => 'Bản nháp đã thay đổi. Hãy tải lại trước khi sửa.']);
            }
            $groups = [];
            foreach (preg_split('/\R/u', trim($data['groups_text'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $line) {
                $parts = array_map('trim', explode('|', $line));
                if (count($parts) !== 4) {
                    throw ValidationException::withMessages(['groups_text' => 'Mỗi dòng nhóm cần đúng 4 phần: MÃ | TÊN | KHỐI | TÍN CHỈ.']);
                }
                $groups[] = ['code' => $parts[0], 'name' => $parts[1], 'knowledge_block' => $parts[2], 'credits_required' => $parts[3]];
            }
            $payload = $draft->payload ?? [];
            $before = $payload;
            $payload['program'] = array_merge($payload['program'] ?? [], $data['draft_program']);
            $payload['knowledge_blocks'] = array_values(array_filter(array_map('trim', preg_split('/\R/u', $data['blocks_text'] ?? '')), fn ($block) => $block !== ''));
            $payload['elective_groups'] = $groups;
            $payload['courses'] = array_values($data['draft_courses']);
            $importer->validatePayload($payload, draft: true);
            foreach ($payload['courses'] as &$course) {
                $course['credits'] = isset($course['credits']) ? (int) $course['credits'] : null;
                $course['is_mandatory'] = isset($course['is_mandatory']) ? (bool) $course['is_mandatory'] : null;
                foreach (['counts_toward_total', 'is_deferred'] as $flag) {
                    if (array_key_exists($flag, $course)) {
                        $course[$flag] = (bool) $course[$flag];
                    }
                }
                $course['suggested_semester'] = filled($course['suggested_semester'] ?? null) ? (int) $course['suggested_semester'] : null;
            }
            unset($course);
            $draft->update(['payload' => $payload, 'comparison_before' => null, 'comparison_after' => null, 'comparison_hash' => null, 'approved_hash' => null]);
            app(CurriculumAuditService::class)->record('document_import', $draft->id, 'ai_draft_edited', $before, $payload, $draft->program_id);
        });

        return back()->with('success', 'Đã lưu bản nháp. Hãy kiểm tra bảng đối chiếu và tín chỉ trước khi trình lại.');
    }

    public function create(Program $program): View
    {
        return view('programs.document-import', [
            'program' => $program,
            'imports' => $program->documentImports()->latest()->limit(10)->get(),
        ]);
    }

    public function createNew(): View
    {
        return view('programs.document-import', ['program' => null, 'imports' => DocumentImport::whereNull('program_id')->latest()->limit(10)->get()]);
    }

    public function store(Request $request, Program $program): RedirectResponse
    {
        return $this->storeDocument($request, $program, 'programs.document-imports.create');
    }

    public function storeNew(Request $request): RedirectResponse
    {
        return $this->storeDocument($request, null, 'programs.document-imports.new');
    }

    public function show(Program $program, DocumentImport $documentImport, CurriculumReviewSummary $summary, CurriculumDraftImporter $importer): View
    {
        abort_unless($documentImport->program_id === $program->id, 404);

        return view('programs.document-import-preview', compact('program', 'documentImport') + ['reviewSummary' => $summary->build($documentImport->payload ?? [])] + $this->comparisonData($documentImport, $importer));
    }

    public function showNew(DocumentImport $documentImport, CurriculumReviewSummary $summary, CurriculumDraftImporter $importer): View
    {
        abort_unless($documentImport->program_id === null, 404);

        return view('programs.document-import-preview', ['program' => null, 'documentImport' => $documentImport, 'reviewSummary' => $summary->build($documentImport->payload ?? [])] + $this->comparisonData($documentImport, $importer));
    }

    public function import(Request $request, Program $program, DocumentImport $documentImport, AiCurriculumApprovalService $approval): RedirectResponse
    {
        abort_unless($documentImport->program_id === $program->id, 404);
        abort_unless($documentImport->status === 'approved', 422, 'Bản nhập AI phải được lãnh đạo phê duyệt trước khi ghi dữ liệu.');
        $this->applyReviewed($request, $documentImport, $approval);

        return redirect()->route('programs.show', $program)->with('success', 'Đã nhập bản nháp AI vào chương trình. Vui lòng kiểm tra nhật ký và số liệu đối chiếu.');
    }

    public function importNew(Request $request, DocumentImport $documentImport, AiCurriculumApprovalService $approval): RedirectResponse
    {
        abort_unless($documentImport->program_id === null, 404);
        abort_unless($documentImport->status === 'approved', 422, 'Bản nhập AI phải được lãnh đạo phê duyệt trước khi ghi dữ liệu.');
        $program = $this->applyReviewed($request, $documentImport, $approval);

        return redirect()->route('programs.show', $program)->with('success', 'Đã tạo chương trình mới từ bản nháp AI. Vui lòng kiểm tra nhật ký và số liệu đối chiếu.');
    }

    public function download(Program $program, DocumentImport $documentImport)
    {
        abort_unless($documentImport->program_id === $program->id, 404);
        abort_unless(Storage::disk('local')->exists($documentImport->path), 404);

        return Storage::disk('local')->download($documentImport->path, $documentImport->original_name);
    }

    public function downloadNew(DocumentImport $documentImport)
    {
        abort_unless($documentImport->program_id === null, 404);
        abort_unless(Storage::disk('local')->exists($documentImport->path), 404);

        return Storage::disk('local')->download($documentImport->path, $documentImport->original_name);
    }

    private function comparisonData(DocumentImport $documentImport, CurriculumDraftImporter $importer): array
    {
        $draftReconciliation = $importer->draftReconciliation($documentImport->payload ?? []);
        $base = ['reconciliation' => $draftReconciliation, 'creditIssues' => $draftReconciliation['issues'], 'creditScopeLabel' => 'Đối chiếu sơ bộ riêng bản nháp; chưa tính dữ liệu giữ lại của chương trình hiện có.'];
        if (! in_array($documentImport->status, ['review', 'returned', 'submitted', 'approved', 'imported'], true)) {
            return ['changes' => [], 'comparisonHash' => null, 'comparisonError' => null, 'comparisonStale' => false] + $base;
        }
        try {
            $comparison = $importer->comparison($documentImport);

            $reconciliation = app(ProgramCreditReconciler::class)->forSnapshot($comparison['after']);

            return ['changes' => app(CurriculumSnapshotDiffer::class)->compare($comparison['before'], $comparison['after']), 'comparisonHash' => $comparison['hash'], 'comparisonError' => null, 'creditIssues' => $reconciliation['issues'], 'reconciliation' => $reconciliation, 'creditScopeLabel' => 'Đối chiếu kết quả sau khi gộp bản nháp vào chương trình.', 'comparisonStale' => in_array($documentImport->status, ['submitted', 'approved'], true) && ! hash_equals($documentImport->comparison_hash ?? '', $importer->comparisonHash($documentImport))];
        } catch (ValidationException $exception) {
            return ['changes' => [], 'comparisonHash' => null, 'comparisonError' => $exception->errors()['payload'][0] ?? $exception->getMessage(), 'comparisonStale' => false] + $base;
        }
    }

    private function applyReviewed(Request $request, DocumentImport $documentImport, AiCurriculumApprovalService $approval): Program
    {
        $data = $request->validate(['confirm' => ['accepted'], 'comparison_hash' => ['required', 'string', 'size:64']]);

        return app(AiCurriculumApprovalService::class)->apply($documentImport, $data['comparison_hash'], $request->user()->id);
    }

    public function index(Request $request): View
    {
        $request->validate(['status' => ['nullable', 'in:review,submitted,approved,returned,imported,failed,processing']]);
        $query = DocumentImport::with(['program', 'uploader', 'reviewer'])->when(! $request->user()->hasRole('Lãnh đạo phòng'), fn ($query) => $query->where('uploaded_by', $request->user()->id));
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return view('programs.ai-imports', ['imports' => $query->orderByDesc('id')->paginate(20)->withQueryString()]);
    }

    public function submit(Request $request, DocumentImport $documentImport, AiCurriculumApprovalService $approval): RedirectResponse
    {
        $data = $request->validate(['confirm' => ['accepted'], 'comparison_hash' => ['required', 'string', 'size:64']]);
        $approval->submit($documentImport, $data['comparison_hash'], $request->user()->id);

        return back()->with('success', 'Đã trình lãnh đạo duyệt bản nhập AI. Chưa thay đổi dữ liệu CTĐT.');
    }

    public function review(Request $request, DocumentImport $documentImport, AiCurriculumApprovalService $approval): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', 'in:approve,return'], 'review_note' => ['required', 'string', 'max:4000']]);
        $approval->review($documentImport, $data['decision'] === 'approve', $data['review_note'], $request->user()->id);

        return back()->with('success', $data['decision'] === 'approve' ? 'Đã duyệt bản nhập AI; người trình có thể ghi đúng nội dung được duyệt.' : 'Đã trả về bản nhập để bổ sung.');
    }

    private function storeDocument(Request $request, ?Program $program, string $route): RedirectResponse
    {
        $file = $request->validate(['file' => ['required', 'file', 'mimes:pdf,docx', 'max:20480']])['file'];
        $path = $file->store('program-document-imports', 'local');
        $documentImport = DocumentImport::create([
            'program_id' => $program?->id,
            'uploaded_by' => $request->user()->id,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'status' => 'processing',
        ]);
        try {
            ExtractCurriculumDocumentJob::dispatch($documentImport->id);
            $documentImport->refresh();

            return redirect()->route($program ? 'programs.document-imports.show' : 'programs.document-imports.new.show', $program ? [$program, $documentImport] : $documentImport)->with('success', $documentImport->status === 'review'
                ? 'Đã trích xuất bản nháp. Hãy kiểm tra trước khi nhập.' : 'Đã nhận tài liệu. Tác vụ đang chờ hoặc đang xử lý; hãy tải lại trang để xem kết quả.');
        } catch (Throwable $exception) {
            Log::error('AI curriculum extraction failed.', ['document_import_id' => $documentImport->id, 'exception' => $exception]);
            $documentImport->update(['status' => 'failed', 'error_message' => $exception instanceof CurriculumExtractionException ? $exception->getMessage() : 'Không thể trích xuất tài liệu. Kiểm tra cấu hình API trung gian hoặc nội dung file.']);

            return redirect()->route($route, $program ?: [])->withErrors(['file' => $documentImport->error_message]);
        }
    }
}
