<?php

namespace App\Http\Controllers;

use App\Models\ProgramExcelImport;
use App\Services\CurriculumSnapshotDiffer;
use App\Services\ProgramCreditReconciler;
use App\Services\ProgramExcelImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProgramImportController extends Controller
{
    public function create(): View
    {
        return view('programs.import');
    }

    public function index(Request $request): View
    {
        $filters = $request->validate(['status' => ['nullable', 'in:review,submitted,approved,returned,imported']]);
        $query = ProgramExcelImport::with(['creator', 'reviewer']);
        if (! $request->user()->hasRole('Lãnh đạo phòng')) {
            $query->where('created_by', $request->user()->id);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return view('programs.imports', ['imports' => $query->orderByDesc('id')->paginate(20)->withQueryString()]);
    }

    public function template(): StreamedResponse
    {
        $book = new Spreadsheet;
        foreach (ProgramExcelImportService::HEADERS + ProgramExcelImportService::OPTIONAL_HEADERS as $name => $headers) {
            if ($name === 'HocPhan') {
                $headers[] = 'MaNhomTuChon';
                $headers[] = 'TinhVaoTong';
            }
            $sheet = $name === 'CTDT' ? $book->getActiveSheet() : $book->createSheet();
            $sheet->setTitle($name);
            $sheet->fromArray($headers);
        }

        return response()->streamDownload(function () use ($book): void {
            (new Xlsx($book))->save('php://output');
            $book->disconnectWorksheets();
        }, 'mau-import-ctdt.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function store(Request $request, ProgramExcelImportService $imports): RedirectResponse
    {
        $file = $request->validate(['file' => ['required', 'file', 'mimes:xlsx', 'max:10240']])['file'];
        $import = $imports->preview($file, $request->user()->id);

        return redirect()->route('programs.import.preview', $import);
    }

    public function preview(Request $request, ProgramExcelImport $excelImport, ProgramExcelImportService $imports, CurriculumSnapshotDiffer $differ, ProgramCreditReconciler $reconciler): View
    {
        abort_unless($excelImport->created_by === $request->user()->id || $request->user()->hasRole('Lãnh đạo phòng'), 403);
        $excelImport->load(['creator', 'reviewer']);
        $snapshot = $excelImport->payload;
        $current = $excelImport->baseline_snapshot;

        return view('programs.import-preview', [
            'excelImport' => $excelImport, 'snapshot' => $snapshot,
            'changes' => $differ->compare($current, $snapshot),
            'stale' => $excelImport->status !== 'imported' && ! hash_equals($excelImport->baseline_hash, $imports->baseline($snapshot)),
            'reconciliation' => $reconciler->forSnapshot($snapshot),
        ]);
    }

    public function confirm(Request $request, ProgramExcelImport $excelImport, ProgramExcelImportService $imports): RedirectResponse
    {
        abort_unless($excelImport->created_by === $request->user()->id, 403);
        $request->validate(['confirm' => ['accepted']]);
        $imports->submit($excelImport, $request->user()->id);

        return back()->with('success', 'Đã trình lãnh đạo duyệt bản nhập Excel. Chưa ghi dữ liệu CTĐT.');
    }

    public function review(Request $request, ProgramExcelImport $excelImport, ProgramExcelImportService $imports): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', 'in:approve,return'], 'review_note' => ['required', 'string', 'max:4000']]);
        $imports->review($excelImport, $request->user()->id, $data['decision'] === 'approve', $data['review_note']);

        return back()->with('success', $data['decision'] === 'approve' ? 'Đã duyệt đúng nội dung bản nhập. Người trình có thể ghi dữ liệu vào bản đang soạn.' : 'Đã trả về bản nhập để bổ sung.');
    }

    public function apply(Request $request, ProgramExcelImport $excelImport, ProgramExcelImportService $imports): RedirectResponse
    {
        abort_unless($excelImport->created_by === $request->user()->id, 403);
        $request->validate(['confirm' => ['accepted']]);
        $program = $imports->confirm($excelImport, $request->user()->id);

        return redirect()->route('programs.show', $program)->with('success', 'Đã nhập CTĐT sau khi xác nhận thay đổi. Bản đang soạn cần được lưu phiên bản và trình duyệt trước khi công bố.');
    }
}
