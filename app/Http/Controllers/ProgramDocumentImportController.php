<?php

namespace App\Http\Controllers;

use App\Exceptions\CurriculumExtractionException;
use App\Models\DocumentImport;
use App\Models\Program;
use App\Services\CurriculumDraftImporter;
use App\Services\CurriculumReviewSummary;
use App\Services\ModelApiCurriculumExtractor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class ProgramDocumentImportController extends Controller
{
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

    public function store(Request $request, Program $program, ModelApiCurriculumExtractor $extractor): RedirectResponse
    {
        $file = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,docx', 'max:20480'],
        ])['file'];
        $path = $file->store('program-document-imports');
        $documentImport = DocumentImport::create([
            'program_id' => $program->id,
            'uploaded_by' => $request->user()->id,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'status' => 'processing',
        ]);

        try {
            $payload = $extractor->extract($file);
            $documentImport->update(['status' => 'review', 'payload' => $payload]);

            return redirect()->route('programs.document-imports.show', [$program, $documentImport])->with('success', 'Đã trích xuất bản nháp. Hãy kiểm tra trước khi nhập.');
        } catch (Throwable $exception) {
            Log::error('AI curriculum extraction failed.', ['document_import_id' => $documentImport->id, 'exception' => $exception]);
            $documentImport->update(['status' => 'failed', 'error_message' => $exception instanceof CurriculumExtractionException ? $exception->getMessage() : 'Không thể trích xuất tài liệu. Kiểm tra cấu hình API trung gian hoặc nội dung file.']);

            return redirect()->route('programs.document-imports.create', $program)->withErrors(['file' => $documentImport->error_message]);
        }
    }

    public function storeNew(Request $request, ModelApiCurriculumExtractor $extractor): RedirectResponse
    {
        return $this->storeDocument($request, null, $extractor, 'programs.document-imports.new');
    }

    public function show(Program $program, DocumentImport $documentImport, CurriculumReviewSummary $summary): View
    {
        abort_unless($documentImport->program_id === $program->id, 404);

        return view('programs.document-import-preview', compact('program', 'documentImport') + ['reviewSummary' => $summary->build($documentImport->payload ?? [])]);
    }

    public function showNew(DocumentImport $documentImport, CurriculumReviewSummary $summary): View
    {
        abort_unless($documentImport->program_id === null, 404);

        return view('programs.document-import-preview', ['program' => null, 'documentImport' => $documentImport, 'reviewSummary' => $summary->build($documentImport->payload ?? [])]);
    }

    public function import(Request $request, Program $program, DocumentImport $documentImport, CurriculumDraftImporter $importer): RedirectResponse
    {
        abort_unless($documentImport->program_id === $program->id, 404);
        abort_unless($documentImport->status === 'review', 422, 'Bản nháp này không còn chờ duyệt.');
        $request->validate(['confirm' => ['accepted']]);
        $importer->import($documentImport, $request->user()->id);
        $documentImport->update(['status' => 'imported', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'imported_by' => $request->user()->id, 'imported_at' => now()]);

        return redirect()->route('programs.show', $program)->with('success', 'Đã nhập bản nháp AI vào chương trình. Vui lòng kiểm tra nhật ký và số liệu đối chiếu.');
    }

    public function importNew(Request $request, DocumentImport $documentImport, CurriculumDraftImporter $importer): RedirectResponse
    {
        abort_unless($documentImport->program_id === null, 404);
        abort_unless($documentImport->status === 'review', 422, 'Bản nháp này không còn chờ duyệt.');
        $request->validate(['confirm' => ['accepted']]);
        $program = $importer->importNew($documentImport, $request->user()->id);
        $documentImport->update(['program_id' => $program->id, 'status' => 'imported', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'imported_by' => $request->user()->id, 'imported_at' => now()]);

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

    private function storeDocument(Request $request, ?Program $program, ModelApiCurriculumExtractor $extractor, string $route): RedirectResponse
    {
        $file = $request->validate(['file' => ['required', 'file', 'mimes:pdf,docx', 'max:20480']])['file'];
        $path = $file->store('program-document-imports');
        $documentImport = DocumentImport::create([
            'program_id' => $program?->id,
            'uploaded_by' => $request->user()->id,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'status' => 'processing',
        ]);
        try {
            $documentImport->update(['status' => 'review', 'payload' => $extractor->extract($file)]);

            return redirect()->route($program ? 'programs.document-imports.show' : 'programs.document-imports.new.show', $program ? [$program, $documentImport] : $documentImport)->with('success', 'Đã trích xuất bản nháp. Hãy kiểm tra trước khi nhập.');
        } catch (Throwable $exception) {
            Log::error('AI curriculum extraction failed.', ['document_import_id' => $documentImport->id, 'exception' => $exception]);
            $documentImport->update(['status' => 'failed', 'error_message' => $exception instanceof CurriculumExtractionException ? $exception->getMessage() : 'Không thể trích xuất tài liệu. Kiểm tra cấu hình API trung gian hoặc nội dung file.']);

            return redirect()->route($route, $program ?: [])->withErrors(['file' => $documentImport->error_message]);
        }
    }
}
