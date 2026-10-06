<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Program;
use App\Services\CurriculumAuditService;
use App\Services\ProgramCreditReconciler;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProgramController extends Controller
{
    public function index(Request $request): View
    {
        $query = $this->filteredPrograms($request);

        return view('programs.index', [
            'programs' => $query->paginate((int) ($request->input('per_page') ?: 15))->withQueryString(),
            'summary' => [
                'total' => Program::count(),
                'active' => Program::where('is_active', true)->count(),
                'missing_courses' => Program::doesntHave('courses')->count(),
                'missing_versions' => Program::doesntHave('versions')->count(),
            ],
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $query = $this->filteredPrograms($request);

        return response()->streamDownload(function () use ($query): void {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Mã chương trình', 'Tên chương trình', 'Tên tiếng Anh', 'Trình độ', 'Hình thức đào tạo', 'Tín chỉ', 'Học phần', 'Phiên bản', 'Trạng thái', 'Số quyết định', 'Ngày quyết định'], ',', '"', '');
            foreach ($query->cursor() as $program) {
                $row = [
                    $program->code, $program->name_vi, $program->name_en,
                    ['dai_hoc' => 'Đại học', 'thac_si' => 'Thạc sĩ', 'tien_si' => 'Tiến sĩ'][$program->degree_level] ?? $program->degree_level,
                    $program->delivery_mode, $program->total_credits, $program->courses_count, $program->versions_count,
                    $program->is_active ? 'Đang hoạt động' : 'Ngừng hoạt động',
                    $program->decision_number, $program->decision_date?->format('d/m/Y'),
                ];
                $row = array_map(function ($value): string {
                    $text = (string) $value;

                    return preg_match('/^[=+@\-]/', ltrim($text)) ? "'".$text : $text;
                }, $row);
                fputcsv($stream, $row, ',', '"', '');
            }
            fclose($stream);
        }, 'chuong-trinh-dao-tao-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filteredPrograms(Request $request): Builder
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'degree_level' => ['nullable', 'in:dai_hoc,thac_si,tien_si'],
            'is_active' => ['nullable', 'in:0,1'],
            'completeness' => ['nullable', 'in:missing_courses,missing_versions'],
            'sort' => ['nullable', 'in:name,code,newest,updated'],
            'per_page' => ['nullable', 'in:15,30,50'],
        ]);
        $query = Program::withCount(['courses', 'versions']);
        $term = trim($filters['q'] ?? '');
        if ($term !== '') {
            $query->where(fn ($query) => $query->where('code', 'like', '%'.$term.'%')
                ->orWhere('name_vi', 'like', '%'.$term.'%')
                ->orWhere('name_en', 'like', '%'.$term.'%'));
        }
        if (isset($filters['degree_level'])) {
            $query->where('degree_level', $filters['degree_level']);
        }
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active'] === '1');
        }
        if (($filters['completeness'] ?? null) === 'missing_courses') {
            $query->doesntHave('courses');
        }
        if (($filters['completeness'] ?? null) === 'missing_versions') {
            $query->doesntHave('versions');
        }
        [$column, $direction] = [
            'name' => ['name_vi', 'asc'],
            'code' => ['code', 'asc'],
            'newest' => ['created_at', 'desc'],
            'updated' => ['updated_at', 'desc'],
        ][$filters['sort'] ?? 'name'];

        return $query->orderBy($column, $direction)->orderBy('id');
    }

    public function create(): View
    {
        return view('programs.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:30', 'unique:programs,code'], 'name_vi' => ['required', 'string', 'max:255'], 'name_en' => ['nullable', 'string', 'max:255'], 'degree_level' => ['required', 'in:dai_hoc,thac_si,tien_si'], 'delivery_mode' => ['nullable', 'string', 'max:100'], 'total_credits' => ['required', 'integer', 'min:1', 'max:999'], 'elective_credits_required' => ['sometimes', 'integer', 'min:0', 'max:999'], 'decision_number' => ['nullable', 'string', 'max:100'], 'decision_date' => ['nullable', 'date'], 'review_cycle_years' => ['required', 'integer', 'min:1', 'max:10'], 'is_active' => ['sometimes', 'boolean'], 'required_blocks' => ['nullable', 'string', 'max:4000']]);
        $data['elective_credits_required'] ??= 0;
        $data['is_active'] = $request->boolean('is_active');
        $data['required_knowledge_blocks'] = $this->parseRequiredBlocks($data['required_blocks'] ?? '');
        unset($data['required_blocks']);
        DB::transaction(fn () => Program::create($data));

        return redirect()->route('programs.index')->with('success', 'Đã tạo chương trình đào tạo.');
    }

    public function show(Program $program, ProgramCreditReconciler $reconciler): View
    {
        $program->load(['courses' => fn ($query) => $query->orderByPivot('sort_order')->orderBy('course_code'), 'versions' => fn ($query) => $query->orderByDesc('cohort_year')->orderByDesc('revision')]);
        $reconciliation = $reconciler->forProgram($program);
        $availableCourses = Course::orderBy('course_code')->get();

        return view('programs.show', compact('program', 'reconciliation', 'availableCourses'));
    }

    public function edit(Program $program): View
    {
        return view('programs.edit', compact('program'));
    }

    public function update(Request $request, Program $program): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:30', 'unique:programs,code,'.$program->id], 'name_vi' => ['required', 'string', 'max:255'], 'name_en' => ['nullable', 'string', 'max:255'], 'degree_level' => ['required', 'in:dai_hoc,thac_si,tien_si'], 'delivery_mode' => ['nullable', 'string', 'max:100'], 'total_credits' => ['required', 'integer', 'min:1', 'max:999'], 'elective_credits_required' => ['sometimes', 'integer', 'min:0', 'max:999'], 'decision_number' => ['nullable', 'string', 'max:100'], 'decision_date' => ['nullable', 'date'], 'review_cycle_years' => ['required', 'integer', 'min:1', 'max:10'], 'is_active' => ['sometimes', 'boolean'], 'required_blocks' => ['nullable', 'string', 'max:4000']]);
        $data['is_active'] = $request->boolean('is_active');
        if (array_key_exists('required_blocks', $data)) {
            $data['required_knowledge_blocks'] = $this->parseRequiredBlocks($data['required_blocks'] ?? '');
            unset($data['required_blocks']);
        }
        DB::transaction(fn () => $program->update($data));

        return redirect()->route('programs.show', $program)->with('success', 'Đã cập nhật chương trình.');
    }

    public function destroy(Program $program): RedirectResponse
    {
        if ($program->versions()->exists()) {
            throw ValidationException::withMessages(['program' => 'Chương trình đã có phiên bản theo khóa, không thể xóa.']);
        }
        DB::transaction(fn () => $program->delete());

        return redirect()->route('programs.index')->with('success', 'Đã xóa chương trình.');
    }

    public function attachCourse(Request $request, Program $program, CurriculumAuditService $audit): RedirectResponse
    {
        $data = $request->validate(['course_id' => ['required', 'exists:courses,id'], 'knowledge_block' => ['required', 'string', 'max:100'], 'is_mandatory' => ['sometimes', 'boolean'], 'suggested_semester' => ['nullable', 'integer', 'min:1', 'max:20']]);
        DB::transaction(function () use ($program, $data, $request, $audit): void {
            $course = $program->courses()->whereKey($data['course_id'])->first();
            $before = $course?->pivot->only(['knowledge_block', 'is_mandatory', 'suggested_semester']);
            $after = ['knowledge_block' => $data['knowledge_block'], 'is_mandatory' => $request->boolean('is_mandatory'), 'suggested_semester' => $data['suggested_semester'] ?? null];
            $program->courses()->syncWithoutDetaching([$data['course_id'] => $after]);
            $audit->record('program_course', $program->id, $course ? 'updated' : 'attached', $before, $after, $program->id, ['course_id' => (int) $data['course_id']]);
        });

        return back()->with('success', 'Đã thêm học phần.');
    }

    public function detachCourse(Program $program, Course $course, CurriculumAuditService $audit): RedirectResponse
    {
        DB::transaction(function () use ($program, $course, $audit): void {
            $attached = $program->courses()->whereKey($course->id)->first();
            if ($attached) {
                $program->courses()->detach($course);
                $audit->record('program_course', $program->id, 'detached', $attached->pivot->only(['knowledge_block', 'is_mandatory', 'suggested_semester']), null, $program->id, ['course_id' => $course->id]);
            }
        });

        return back()->with('success', 'Đã bỏ học phần.');
    }

    public function updateCourse(Request $request, Program $program, Course $course, CurriculumAuditService $audit): RedirectResponse
    {
        abort_unless($program->courses()->whereKey($course->id)->exists(), 404);
        $data = $request->validate(['knowledge_block' => ['required', 'string', 'max:100'], 'is_mandatory' => ['sometimes', 'boolean'], 'suggested_semester' => ['nullable', 'integer', 'min:1', 'max:20']]);
        DB::transaction(function () use ($program, $course, $data, $request, $audit): void {
            $before = $program->courses()->whereKey($course->id)->firstOrFail()->pivot->only(['knowledge_block', 'is_mandatory', 'suggested_semester']);
            $after = ['knowledge_block' => $data['knowledge_block'], 'is_mandatory' => $request->boolean('is_mandatory'), 'suggested_semester' => $data['suggested_semester'] ?? null];
            $program->courses()->updateExistingPivot($course->id, $after);
            $audit->record('program_course', $program->id, 'updated', $before, $after, $program->id, ['course_id' => $course->id]);
        });

        return back()->with('success', 'Đã cập nhật học phần trong chương trình.');
    }

    private function parseRequiredBlocks(string $value): array
    {
        $blocks = collect(preg_split('/\R/u', $value))
            ->map(fn ($block) => trim($block))
            ->filter()
            ->unique(fn ($block) => mb_strtolower($block))
            ->values();
        if ($blocks->contains(fn ($block) => mb_strlen($block) > 100)) {
            throw ValidationException::withMessages(['required_blocks' => 'Tên khối kiến thức không được quá 100 ký tự.']);
        }

        return $blocks->all();
    }
}
