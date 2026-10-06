<?php

namespace App\Http\Controllers;

use App\Exports\WarningsExport;
use App\Jobs\ImportAcademicProgressJob;
use App\Models\AcademicImport;
use App\Models\AcademicSetting;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentProgress;
use App\Models\StudentProgressHistory;
use App\Models\User;
use App\Models\WarningLog;
use App\Services\AcademicWarningService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class AcademicWarningController extends Controller
{
    public function index(Request $request): View
    {
        $query = $this->filteredWarnings($request)->with(['student.program', 'student.advisor', 'progress']);

        return view('academic.index', ['warnings' => $query->paginate(20)->withQueryString(), 'counts' => WarningLog::selectRaw('status,count(*) total')->groupBy('status')->pluck('total', 'status'), 'imports' => AcademicImport::latest()->limit(10)->get(), 'programs' => Program::where('is_active', true)->orderBy('name_vi')->get(), 'classes' => Student::whereNotNull('class_code')->distinct()->orderBy('class_code')->pluck('class_code'), 'faculties' => Student::whereNotNull('faculty_name')->distinct()->orderBy('faculty_name')->pluck('faculty_name'), 'semesters' => StudentProgress::query()->distinct()->orderByDesc('semester')->pluck('semester'), 'warningLabels' => config('academic.warning_labels')]);
    }

    public function students(Request $request): View
    {
        $query = Student::with(['program', 'advisor'])->withCount('warnings')->orderBy('student_code');
        foreach (['program_id', 'class_code', 'degree_level', 'faculty_name', 'advisor_user_id', 'enrollment_year', 'status'] as $filter) {
            if ($request->filled($filter)) {
                if ($filter === 'advisor_user_id' && $request->input($filter) === 'unassigned') {
                    $query->whereNull($filter);
                } else {
                    $query->where($filter, $request->input($filter));
                }
            }
        }
        if ($request->filled('q')) {
            $query->where(fn ($q) => $q->where('student_code', 'like', '%'.$request->q.'%')->orWhere('full_name', 'like', '%'.$request->q.'%'));
        }

        return view('academic.students', ['students' => $query->paginate(20)->withQueryString(), 'programs' => Program::orderBy('name_vi')->get(), 'advisors' => User::permission('academic.process')->orderBy('name')->get(), 'faculties' => Student::whereNotNull('faculty_name')->distinct()->orderBy('faculty_name')->pluck('faculty_name')]);
    }

    public function student(Student $student): View
    {
        $student->load(['program', 'advisor', 'progress.histories.user', 'warnings' => fn ($q) => $q->latest('issued_at')]);

        return view('academic.student', ['student' => $student]);
    }

    public function editStudent(Student $student): View
    {
        $student->load(['program', 'advisor']);

        return view('academic.student-edit', ['student' => $student, 'advisors' => User::permission('academic.process')->orderBy('name')->get()]);
    }

    public function updateProgress(Request $request, Student $student): RedirectResponse
    {
        $data = $request->validate(['semester' => ['required', 'string', 'max:30'], 'credits_accumulated' => ['required', 'integer', 'min:0'], 'credits_required' => ['required', 'integer', 'min:0'], 'gpa_4' => ['required', 'numeric', 'min:0', 'max:4']]);
        $progress = StudentProgress::firstOrNew(['student_id' => $student->id, 'semester' => $data['semester']]);
        $old = $progress->exists ? $progress->only(['semester', 'credits_accumulated', 'credits_required', 'gpa_4', 'recorded_at']) : null;
        $progress->fill($data + ['recorded_at' => now()]);
        $progress->save();
        StudentProgressHistory::create(['student_progress_id' => $progress->id, 'student_id' => $student->id, 'changed_by' => auth()->id(), 'action' => $old ? 'updated' : 'created', 'old_values' => $old, 'new_values' => $progress->only(['semester', 'credits_accumulated', 'credits_required', 'gpa_4', 'recorded_at'])]);
        app(AcademicWarningService::class)->scan();

        return back()->with('success', 'Đã cập nhật tiến độ và quét lại cảnh báo.');
    }

    public function updateAssignment(Request $request, Student $student): RedirectResponse
    {
        $student->update($request->validate(['advisor_user_id' => ['nullable', 'exists:users,id'], 'faculty_name' => ['nullable', 'string', 'max:150']]));

        return back()->with('success', 'Đã cập nhật cố vấn và khoa nhận cảnh báo.');
    }

    public function updateMilestones(Request $request, Student $student, AcademicWarningService $service): RedirectResponse
    {
        $rules = [
            'expected_graduation_date' => ['nullable', 'date'],
            'graduated_at' => ['nullable', 'date'],
            'withdrawn_at' => ['nullable', 'date'],
            'status' => ['sometimes', 'in:active,graduated,withdrawn'],
        ];
        foreach (['proposal_deadline', 'proposal_submitted_at', 'department_defense_deadline', 'department_defended_at', 'academy_defense_deadline', 'academy_defended_at', 'maximum_study_end_date'] as $field) {
            $rules[$field] = $student->degree_level === 'dai_hoc' ? ['prohibited'] : ['nullable', 'date'];
        }
        $data = $request->validate($rules);
        if (($data['status'] ?? null) === 'graduated' && empty($data['graduated_at'])) {
            return back()->withErrors(['graduated_at' => 'Cần nhập ngày tốt nghiệp thực tế.']);
        }
        if (($data['status'] ?? null) === 'withdrawn' && empty($data['withdrawn_at'])) {
            return back()->withErrors(['withdrawn_at' => 'Cần nhập ngày thôi học.']);
        }
        $student->update($data);
        $completed = [];
        foreach (['proposal_submitted_at' => 'proposal_due', 'department_defended_at' => 'department_defense_due', 'academy_defended_at' => 'academy_defense_due'] as $field => $type) {
            if ($student->$field) {
                $completed[] = $type;
            }
        }
        if ($student->status === 'graduated') {
            $completed = array_merge($completed, ['graduation_risk', 'maximum_study_due']);
        }
        foreach ($student->warnings()->whereIn('warning_type', $completed)->whereIn('status', ['open', 'processing'])->get() as $warning) {
            $service->resolve($warning, $request->user()->id);
        }
        $service->scan();

        return back()->with('success', 'Đã cập nhật mốc học vụ và quét lại cảnh báo.');
    }

    public function importForm(): View
    {
        return view('academic.import', ['programs' => Program::where('is_active', true)->orderBy('name_vi')->get()]);
    }

    public function importReport(AcademicImport $import): View
    {
        return view('academic.import-report', compact('import'));
    }

    public function downloadImportErrors(AcademicImport $import): Response
    {
        $lines = ['Dong,NoiDung'];
        foreach ($import->errorRows() as $index => $error) {
            $lines[] = ($index + 1).','.str_replace(["\r", "\n", ','], [' ', ' ', ';'], $error);
        }

        return response(implode("\n", $lines), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="import-errors-'.$import->id.'.csv"']);
    }

    public function settings(): View
    {
        return view('academic.settings', ['settings' => AcademicSetting::where('key', 'gpa_below')->whereIn('degree_level', ['dai_hoc', 'thac_si', 'tien_si'])->get()]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate(['settings' => ['required', 'array'], 'settings.*' => ['required', 'numeric', 'min:0', 'max:4']]);
        $settings = AcademicSetting::where('key', 'gpa_below')->whereIn('degree_level', ['dai_hoc', 'thac_si', 'tien_si'])->whereIn('id', array_keys($data['settings']))->get()->keyBy('id');
        if ($settings->count() !== count($data['settings'])) {
            throw ValidationException::withMessages(['settings' => 'Chỉ được sửa ngưỡng GPA theo trình độ.']);
        }
        foreach ($data['settings'] as $id => $value) {
            $settings[$id]->update(['value' => (string) $value]);
        }

        return back()->with('success', 'Đã cập nhật ngưỡng cảnh báo.');
    }

    public function import(Request $request): RedirectResponse
    {
        $data = $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:51200']]);
        $path = $data['file']->store('academic-imports');
        $batch = AcademicImport::create(['user_id' => auth()->id(), 'original_name' => $data['file']->getClientOriginalName(), 'status' => 'queued']);
        ImportAcademicProgressJob::dispatch($path, auth()->id(), $batch->id);

        return redirect()->route('academic.index')->with('success', config('queue.default') === 'sync' ? 'Đã xử lý tệp và quét cảnh báo.' : 'Tệp đang chờ xử lý.');
    }

    public function scan(AcademicWarningService $service): RedirectResponse
    {
        return back()->with('success', 'Đã quét và tạo '.$service->scan().' cảnh báo mới.');
    }

    public function update(Request $request, WarningLog $warning): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:processing,resolved,dismissed']]);
        $warning->update(['status' => $data['status'], 'resolved_by' => auth()->id(), 'resolved_at' => in_array($data['status'], ['resolved', 'dismissed'], true) ? now() : null]);

        return back()->with('success', 'Đã cập nhật trạng thái cảnh báo.');
    }

    public function exportExcel(Request $request)
    {
        return Excel::download(new WarningsExport($this->filteredWarnings($request)->with(['student.program', 'progress'])->get()), 'academic-warnings.xlsx');
    }

    public function exportPdf(Request $request)
    {
        return Pdf::loadView('academic.pdf', ['warnings' => $this->filteredWarnings($request)->with(['student.program'])->get()])->download('academic-warnings.pdf');
    }

    public function exportWord(Request $request): Response
    {
        return response()->view('academic.pdf', ['warnings' => $this->filteredWarnings($request)->with(['student.program'])->get()], 200, ['Content-Type' => 'application/msword; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="academic-warnings.doc"']);
    }

    private function filteredWarnings(Request $request)
    {
        $query = WarningLog::query()->latest('issued_at');
        foreach (['status', 'warning_type'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }
        foreach (['program_id', 'class_code', 'degree_level', 'faculty_name', 'advisor_user_id', 'enrollment_year'] as $filter) {
            if ($request->filled($filter)) {
                $query->whereHas('student', fn ($q) => $q->where($filter, $request->input($filter)));
            }
        }
        if ($request->filled('student_status')) {
            $query->whereHas('student', fn ($student) => $student->where('status', $request->input('student_status')));
        }
        if ($request->filled('semester')) {
            $query->whereHas('progress', fn ($q) => $q->where('semester', $request->input('semester')));
        }
        if ($request->input('priority') === 'critical') {
            $query->whereIn('warning_type', ['low_gpa', 'credit_debt', 'graduation_risk', 'maximum_study_due']);
        }

        return $query;
    }
}
