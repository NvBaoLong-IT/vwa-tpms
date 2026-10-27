<?php

namespace App\Http\Controllers;

use App\Models\CourseResultImport;
use App\Models\ProgramVersion;
use App\Models\Student;
use App\Services\CourseResultImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class CourseResultImportController extends Controller
{
    public function create(Request $request, Student $student): View
    {
        return view('academic.course-result-import', ['student' => $student, 'versions' => ProgramVersion::with('program')->where('program_id', $student->program_id)->whereIn('status', ['published', 'archived'])->whereHas('program', fn ($query) => $query->where('degree_level', $student->degree_level))->orderByDesc('id')->get(), 'imports' => CourseResultImport::where('student_id', $student->id)->where('created_by', $request->user()->id)->orderByDesc('id')->limit(10)->get()]);
    }

    public function template(Student $student): Response
    {
        return response("\xEF\xBB\xBF".implode(',', CourseResultImportService::HEADERS)."\nCTDT01,2026,1,HP01,2026-1,Dat,Bang diem xac nhan so 01\n", 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="mau-ket-qua-hoc-phan.csv"', 'Cache-Control' => 'private, no-store']);
    }

    public function store(Request $request, Student $student, CourseResultImportService $imports): RedirectResponse
    {
        $data = $request->validate(['file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:5120']]);
        $import = $imports->preview($student, $data['file'], $request->user()->id);

        return redirect()->route('academic.course-results.import.preview', [$student, $import]);
    }

    public function preview(Request $request, Student $student, CourseResultImport $resultImport, CourseResultImportService $imports): View
    {
        abort_unless($resultImport->student_id === $student->id, 404);
        abort_unless($resultImport->created_by === $request->user()->id, 403);

        return view('academic.course-result-preview', ['student' => $student, 'import' => $resultImport, 'stale' => $resultImport->status !== 'imported' && ! hash_equals($resultImport->baseline_hash, $imports->baseline($student, $resultImport->payload))]);
    }

    public function confirm(Request $request, Student $student, CourseResultImport $resultImport, CourseResultImportService $imports): RedirectResponse
    {
        abort_unless($resultImport->student_id === $student->id, 404);
        abort_unless($resultImport->created_by === $request->user()->id, 403);
        $request->validate(['confirm' => ['accepted']]);
        $count = $imports->confirm($student, $resultImport, $request->user()->id);

        return redirect()->route('academic.credits', $student)->with('success', 'Đã nhập '.$count.' kết quả học phần. Cần lập và duyệt hồ sơ công nhận trước khi tính tín chỉ đáp ứng CTĐT.');
    }
}
