<?php

namespace App\Services;

use App\Models\DiplomaAuditLog;
use App\Models\DiplomaBlank;
use App\Models\DiplomaBlankAudit;
use App\Models\DiplomaLedger;
use App\Models\DiplomaRequest;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DiplomaIssuanceService
{
    public function request(array $data, int $userId): DiplomaRequest
    {
        $student = Student::findOrFail($data['student_id']);
        $blank = DiplomaBlank::where('serial_number', $data['serial_number'])->first();
        if ($student->status !== 'graduated' || ($student->degree_level !== $data['diploma_type'] && $data['diploma_type'] !== 'chung_chi')) {
            throw ValidationException::withMessages(['student_id' => 'Người học chưa đủ điều kiện hoặc trình độ văn bằng không khớp.']);
        }
        if (! $blank || $blank->type !== $data['diploma_type'] || $blank->status !== 'in_stock') {
            throw ValidationException::withMessages(['serial_number' => 'Phôi không có sẵn hoặc không đúng loại.']);
        }
        if (DiplomaLedger::where('student_id', $student->id)->where('status', 'issued')->exists()
            || DiplomaRequest::where('student_id', $student->id)->where('status', 'pending')->exists()
            || DiplomaRequest::where('serial_number', $blank->serial_number)->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages(['student_id' => 'Người học hoặc phôi đã có yêu cầu cấp đang hiệu lực.']);
        }

        return DiplomaRequest::create($data + ['requested_by' => $userId]);
    }

    public function approve(DiplomaRequest $request, int $userId): DiplomaLedger
    {
        return DB::transaction(function () use ($request, $userId): DiplomaLedger {
            $request = DiplomaRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            if ($request->status !== 'pending') {
                throw ValidationException::withMessages(['request' => 'Yêu cầu đã được xử lý.']);
            }
            $blank = DiplomaBlank::where('serial_number', $request->serial_number)->lockForUpdate()->firstOrFail();
            $student = Student::whereKey($request->student_id)->lockForUpdate()->firstOrFail();
            if ($blank->status !== 'in_stock' || $student->status !== 'graduated'
                || DiplomaLedger::where('student_id', $student->id)->where('status', 'issued')->exists()) {
                throw ValidationException::withMessages(['request' => 'Điều kiện cấp hoặc trạng thái phôi đã thay đổi.']);
            }
            $year = $request->issue_date->format('Y');
            $prefix = ['dai_hoc' => 'DH', 'thac_si' => 'CH', 'tien_si' => 'TS', 'chung_chi' => 'CC'][$request->diploma_type];
            $series = $request->diploma_type === 'chung_chi'
                ? $prefix.'/'.$year.'/'.$request->cohort_code
                : $prefix.'/'.$request->cohort_code.'/'.$year.'/QĐ'.$request->decision_code;
            DB::table('diploma_registry_sequences')->insertOrIgnore(['series' => $series, 'last_number' => 0]);
            $sequence = DB::table('diploma_registry_sequences')->where('series', $series)->lockForUpdate()->first();
            $number = $sequence->last_number + 1;
            DB::table('diploma_registry_sequences')->where('id', $sequence->id)->update(['last_number' => $number]);
            $ledger = DiplomaLedger::create([
                'student_id' => $student->id, 'diploma_type' => $request->diploma_type,
                'serial_number' => $blank->serial_number, 'reg_number' => $series.'-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
                'issue_date' => $request->issue_date, 'decision_number' => $request->decision_code,
                'cohort_code' => $request->cohort_code, 'decision_code' => $request->decision_code,
                'holder_name' => $student->full_name, 'holder_date_of_birth' => $student->date_of_birth,
            ]);
            $blank->update(['status' => 'used']);
            $this->auditBlank($blank, $userId, 'used', ['status' => 'in_stock'], ['status' => 'used', 'reg_number' => $ledger->reg_number]);
            DiplomaAuditLog::create(['diploma_ledger_id' => $ledger->id, 'user_id' => $userId, 'action' => 'issued', 'new_values' => $ledger->only(['student_id', 'diploma_type', 'serial_number', 'reg_number', 'issue_date', 'holder_name']), 'ip_address' => request()->ip()]);
            $request->update(['status' => 'approved', 'reviewed_by' => $userId, 'reviewed_at' => now(), 'diploma_ledger_id' => $ledger->id]);

            return $ledger;
        });
    }

    public function auditBlank(DiplomaBlank $blank, int $userId, string $action, ?array $old, ?array $new, ?string $documentPath = null): void
    {
        DiplomaBlankAudit::create(['diploma_blank_id' => $blank->id, 'user_id' => $userId, 'action' => $action, 'old_values' => $old, 'new_values' => $new, 'ip_address' => request()->ip(), 'document_path' => $documentPath]);
    }
}
