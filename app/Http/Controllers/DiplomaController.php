<?php

namespace App\Http\Controllers;

use App\Imports\GraduationImport;
use App\Models\DiplomaAuditLog;
use App\Models\DiplomaBlank;
use App\Models\DiplomaBlankAudit;
use App\Models\DiplomaLedger;
use App\Models\DiplomaRequest;
use App\Models\Student;
use App\Services\DiplomaIssuanceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DiplomaController extends Controller
{
    public function index(): View
    {
        return view('diploma.index', ['ledgers' => DiplomaLedger::with('student')->latest()->paginate(20), 'blanks' => DiplomaBlank::latest()->paginate(20, ['*'], 'blanks'), 'students' => Student::where('status', 'graduated')->whereDoesntHave('diplomas', fn ($q) => $q->where('status', 'issued'))->orderBy('student_code')->get(), 'requests' => DiplomaRequest::with(['student', 'requester'])->latest()->paginate(20, ['*'], 'requests')]);
    }

    public function storeBlank(Request $request, DiplomaIssuanceService $service): RedirectResponse
    {
        $blank = DiplomaBlank::create($request->validate(['serial_number' => ['required', 'string', 'max:100', 'unique:diploma_blanks,serial_number'], 'type' => ['required', 'in:dai_hoc,thac_si,tien_si,chung_chi'], 'import_date' => ['required', 'date'], 'note' => ['nullable', 'string']]));
        $service->auditBlank($blank, $request->user()->id, 'imported', null, $blank->only(['serial_number', 'type', 'import_date', 'status']));

        return back()->with('success', 'Đã nhập phôi mới.');
    }

    public function updateBlank(Request $request, DiplomaBlank $blank, DiplomaIssuanceService $service): RedirectResponse
    {
        abort_if($request->input('status') === 'destroyed' && ! $request->user()->can('diploma.approve'), 403);
        $data = $request->validate(['status' => ['required', 'in:damaged,destroyed'], 'note' => ['required', 'string', 'max:2000'], 'decision_number' => ['required_if:status,destroyed', 'nullable', 'string', 'max:100'], 'minutes_file' => ['required_if:status,destroyed', 'nullable', 'file', 'mimes:pdf,doc,docx', 'max:20480']]);
        abort_unless($blank->status === 'in_stock', 422, 'Phôi đã rời kho không thể đổi trạng thái.');
        $old = $blank->only(['status', 'note']);
        $path = isset($data['minutes_file']) ? $data['minutes_file']->store('diploma-blank-minutes', 'local') : null;
        $blank->update(['status' => $data['status'], 'note' => $data['note']]);
        $service->auditBlank($blank, $request->user()->id, $data['status'], $old, ['status' => $data['status'], 'note' => $data['note'], 'decision_number' => $data['decision_number'] ?? null], $path);

        return back()->with('success', 'Đã cập nhật trạng thái phôi.');
    }

    public function store(Request $request, DiplomaIssuanceService $service): RedirectResponse
    {
        $data = $request->validate(['student_id' => ['required', 'exists:students,id'], 'diploma_type' => ['required', 'in:dai_hoc,thac_si,tien_si,chung_chi'], 'serial_number' => ['required', 'exists:diploma_blanks,serial_number'], 'cohort_code' => ['required', 'regex:/^[A-Za-z0-9-]+$/', 'max:30'], 'decision_code' => ['required_unless:diploma_type,chung_chi', 'nullable', 'regex:/^[A-Za-z0-9-]+$/', 'max:50'], 'issue_date' => ['required', 'date']]);
        $service->request($data, $request->user()->id);

        return back()->with('success', 'Đã gửi yêu cầu cấp văn bằng để lãnh đạo phê duyệt.');
    }

    public function approve(DiplomaRequest $diplomaRequest, DiplomaIssuanceService $service): RedirectResponse
    {
        $ledger = $service->approve($diplomaRequest, auth()->id());

        return back()->with('success', 'Đã phê duyệt và cấp số vào sổ: '.$ledger->reg_number);
    }

    public function reject(Request $request, DiplomaRequest $diplomaRequest): RedirectResponse
    {
        $data = $request->validate(['review_note' => ['required', 'string', 'max:2000']]);
        DB::transaction(function () use ($diplomaRequest, $request, $data): void {
            $locked = DiplomaRequest::whereKey($diplomaRequest->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'pending', 422);
            $locked->update(['status' => 'rejected', 'review_note' => $data['review_note'], 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        });

        return back()->with('success', 'Đã từ chối yêu cầu cấp.');
    }

    public function update(Request $request, DiplomaLedger $ledger): RedirectResponse
    {
        abort_unless($ledger->status === 'issued', 422);
        $old = $ledger->only(['holder_name', 'holder_date_of_birth', 'holder_ethnicity', 'amendment_decision']);
        $data = $request->validate(['holder_name' => ['required', 'string', 'max:255'], 'holder_date_of_birth' => ['nullable', 'date'], 'holder_ethnicity' => ['nullable', 'string', 'max:100'], 'amendment_decision' => ['required', 'string', 'max:100'], 'reason' => ['required', 'string', 'max:2000']]);
        $reason = $data['reason'];
        unset($data['reason']);
        DB::transaction(function () use ($ledger, $data, $old, $reason): void {
            $ledger->update($data);
            $this->writeAudit($ledger, 'amended', $old, $data + ['reason' => $reason]);
        });

        return back()->with('success', 'Đã cập nhật sổ gốc và lưu lịch sử thay đổi.');
    }

    public function cancel(Request $request, DiplomaLedger $ledger): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        abort_if($ledger->status === 'cancelled', 422, 'Sổ gốc đã bị hủy.');
        $old = ['status' => $ledger->status];
        DB::transaction(function () use ($ledger, $old, $request): void {
            $ledger->update(['status' => 'cancelled']);
            $this->writeAudit($ledger, 'cancelled', $old, ['status' => 'cancelled', 'reason' => $request->input('reason')]);
        });

        return back()->with('success', 'Đã hủy văn bằng và lưu lịch sử thay đổi.');
    }

    public function audit(): View
    {
        return view('diploma.audit', ['audits' => DiplomaAuditLog::with(['user', 'ledger.student'])->latest()->paginate(30), 'blankAudits' => DiplomaBlankAudit::with(['user', 'blank'])->latest()->paginate(30, ['*'], 'blanks')]);
    }

    public function downloadBlankMinutes(DiplomaBlankAudit $audit): BinaryFileResponse
    {
        abort_unless($audit->document_path && Storage::disk('local')->exists($audit->document_path), 404);

        return response()->download(Storage::disk('local')->path($audit->document_path));
    }

    public function importForm(): View
    {
        return view('diploma.import');
    }

    public function import(Request $request): RedirectResponse
    {
        $file = $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:20480']])['file'];
        $import = new GraduationImport;
        HeadingRowFormatter::default('none');
        Excel::import($import, $file);

        return back()->with('success', "Đã nhập {$import->imported} dòng tốt nghiệp.")->with('import_errors', $import->errors);
    }

    public function document(DiplomaLedger $ledger, string $type)
    {
        abort_unless(in_array($type, ['copy', 'extract'], true), 404);
        abort_unless($ledger->status === 'issued', 422);

        return Pdf::loadView('diploma.document', ['ledger' => $ledger->load('student'), 'type' => $type])->download($type.'-'.$ledger->reg_number.'.pdf');
    }

    public function lookup(Request $request)
    {
        $code = $request->string('code')->toString();
        $ledger = DiplomaLedger::with('student')->where(fn ($q) => $q->where('reg_number', $code)->orWhere('serial_number', $code))->where('status', 'issued')->first();
        $result = $ledger ? ['valid' => true, 'reg_number' => $ledger->reg_number, 'student' => $ledger->holder_name ?: $ledger->student->full_name, 'issue_date' => $ledger->issue_date->format('Y-m-d'), 'type' => $ledger->diploma_type] : ['valid' => false];

        return response()->json($result);
    }

    private function writeAudit(DiplomaLedger $ledger, string $action, ?array $old, ?array $new): void
    {
        DiplomaAuditLog::create(['diploma_ledger_id' => $ledger->id, 'user_id' => auth()->id(), 'action' => $action, 'old_values' => $old, 'new_values' => $new, 'ip_address' => request()->ip()]);
    }
}
