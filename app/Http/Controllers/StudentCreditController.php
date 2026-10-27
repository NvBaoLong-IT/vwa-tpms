<?php

namespace App\Http\Controllers;

use App\Models\CreditRecognition;
use App\Models\ProgramVersion;
use App\Models\Student;
use App\Models\StudentCourseResult;
use App\Services\CreditRecognitionService;
use App\Services\CurriculumAuditService;
use App\Services\StudentCourseResultService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StudentCreditController extends Controller
{
    public function queue(): View
    {
        return view('academic.recognition-queue', ['recognitions' => CreditRecognition::with(['student', 'version', 'submitter'])->where('status', 'submitted')->orderBy('id')->paginate(20)]);
    }

    public function index(Request $request, Student $student, CreditRecognitionService $service): View
    {
        $request->validate(['version_id' => ['nullable', 'integer']]);
        $versions = ProgramVersion::where('program_id', $student->program_id)->whereIn('status', ['published', 'archived'])->orderByDesc('cohort_year')->orderByDesc('revision')->get();
        $version = $request->filled('version_id') ? $versions->firstWhere('id', $request->integer('version_id')) : $versions->firstWhere('cohort_year', $student->enrollment_year);
        abort_if($request->filled('version_id') && $version === null, 404);

        return view('academic.credits', [
            'student' => $student, 'versions' => $versions, 'version' => $version,
            'sourceVersions' => ProgramVersion::where('program_id', $student->program_id)->whereIn('status', ['published', 'archived'])->orderByDesc('id')->get(),
            'results' => StudentCourseResult::with('version')->where('student_id', $student->id)->orderByDesc('id')->get(),
            'recognitions' => CreditRecognition::with(['version', 'submitter', 'reviewer'])->where('student_id', $student->id)->orderByDesc('id')->get(),
            'summary' => $version ? $service->summary($student, $version) : null,
        ]);
    }

    public function result(Request $request, Student $student, StudentCourseResultService $results): RedirectResponse
    {
        $data = $request->validate([
            'program_version_id' => ['required', 'integer', 'exists:program_versions,id'],
            'course_code' => ['required', 'string', 'max:30'], 'semester' => ['required', 'string', 'max:30'],
            'outcome' => ['required', 'in:passed,failed,exempt'], 'evidence' => ['required', 'string', 'max:255'],
        ]);
        $results->record($student, $data, $request->user()->id);

        return back()->with('success', 'Đã ghi kết quả học phần có căn cứ. Chưa cộng vào tín chỉ công nhận cho đến khi hồ sơ được duyệt.');
    }

    public function voidResult(Request $request, Student $student, StudentCourseResult $result, CurriculumAuditService $audit): RedirectResponse
    {
        abort_unless($result->student_id === $student->id, 404);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        DB::transaction(function () use ($student, $result, $data, $audit): void {
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $result = StudentCourseResult::whereKey($result->id)->lockForUpdate()->firstOrFail();
            abort_if($result->voided_at !== null, 422);
            foreach (CreditRecognition::where('student_id', $student->id)->where('status', 'approved')->get() as $recognition) {
                if (in_array($result->id, array_column($recognition->allocation['results'], 'id'), true)) {
                    throw ValidationException::withMessages(['result' => 'Kết quả đang làm căn cứ công nhận tín chỉ. Cần thu hồi hồ sơ công nhận liên quan trước khi hủy kết quả.']);
                }
            }
            $result->update(['voided_at' => now(), 'void_note' => $data['reason']]);
            $audit->record('student_course_result', $result->id, 'result_voided', ['voided_at' => null], $result->only(['student_id', 'voided_at', 'void_note']), $result->version->program_id);
        });

        return back()->with('success', 'Đã hủy kết quả và giữ lại lịch sử.');
    }

    public function submit(Request $request, Student $student, CreditRecognitionService $service): RedirectResponse
    {
        $data = $request->validate([
            'program_version_id' => ['required', 'integer', 'exists:program_versions,id'],
            'result_ids' => ['required', 'array', 'min:1', 'max:100'], 'result_ids.*' => ['required', 'integer', 'distinct'],
            'rule_id' => ['nullable', 'string', 'max:100'], 'decision_number' => ['required', 'string', 'max:100'], 'note' => ['required', 'string', 'max:2000'],
        ]);
        $service->submit($student, ProgramVersion::findOrFail($data['program_version_id']), $data, $request->user()->id);

        return back()->with('success', 'Đã trình hồ sơ công nhận tín chỉ. Chưa tính vào tín chỉ được công nhận.');
    }

    public function review(Request $request, Student $student, CreditRecognition $recognition, CreditRecognitionService $service): RedirectResponse
    {
        abort_unless($recognition->student_id === $student->id, 404);
        $data = $request->validate(['decision' => ['required', 'in:approve,return'], 'review_note' => ['required', 'string', 'max:2000']]);
        $service->review($student, $recognition, $data['decision'] === 'approve', $data['review_note'], $request->user()->id);

        return back()->with('success', $data['decision'] === 'approve' ? 'Đã phê duyệt công nhận tín chỉ.' : 'Đã trả về hồ sơ. Hãy lập hồ sơ mới sau khi bổ sung.');
    }

    public function revoke(Request $request, Student $student, CreditRecognition $recognition, CurriculumAuditService $audit): RedirectResponse
    {
        abort_unless($recognition->student_id === $student->id, 404);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        DB::transaction(function () use ($student, $recognition, $request, $data, $audit): void {
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $recognition = CreditRecognition::whereKey($recognition->id)->lockForUpdate()->firstOrFail();
            abort_unless($recognition->status === 'approved', 422);
            $recognition->update(['status' => 'revoked']);
            $audit->record('credit_recognition', $recognition->id, 'recognition_revoked', ['status' => 'approved'], ['status' => 'revoked', 'reason' => $data['reason'], 'revoked_by' => $request->user()->id, 'student_id' => $student->id], $recognition->version->program_id);
        });

        return back()->with('success', 'Đã thu hồi công nhận tín chỉ, giữ nguyên căn cứ và lịch sử phê duyệt.');
    }
}
