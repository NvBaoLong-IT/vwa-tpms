<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Program;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CourseController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'is_active' => ['nullable', 'in:0,1'],
            'credits' => ['nullable', 'integer', 'min:1', 'max:99'],
            'prerequisite' => ['nullable', 'in:has,none'],
            'coverage' => ['nullable', 'in:missing_materials,unassigned'],
            'sort' => ['nullable', 'in:code,name,credits,updated'],
            'per_page' => ['nullable', 'in:20,30,50'],
        ]);
        $query = Course::with(['prerequisite', 'programs:id,code,name_vi'])->withCount('materials');
        $term = trim($filters['q'] ?? '');
        if ($term !== '') {
            $query->where(fn ($query) => $query->where('course_code', 'like', '%'.$term.'%')
                ->orWhere('name_vi', 'like', '%'.$term.'%')
                ->orWhere('name_en', 'like', '%'.$term.'%'));
        }
        if (isset($filters['program_id'])) {
            $query->whereHas('programs', fn ($query) => $query->whereKey($filters['program_id']));
        }
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active'] === '1');
        }
        if (isset($filters['credits'])) {
            $query->where('credits', $filters['credits']);
        }
        if (($filters['prerequisite'] ?? null) === 'has') {
            $query->whereNotNull('prerequisite_course_id');
        }
        if (($filters['prerequisite'] ?? null) === 'none') {
            $query->whereNull('prerequisite_course_id');
        }
        if (($filters['coverage'] ?? null) === 'missing_materials') {
            $query->doesntHave('materials');
        }
        if (($filters['coverage'] ?? null) === 'unassigned') {
            $query->doesntHave('programs');
        }
        [$column, $direction] = [
            'code' => ['course_code', 'asc'],
            'name' => ['name_vi', 'asc'],
            'credits' => ['credits', 'desc'],
            'updated' => ['updated_at', 'desc'],
        ][$filters['sort'] ?? 'code'];

        return view('courses.index', [
            'courses' => $query->orderBy($column, $direction)->orderBy('id')->paginate((int) ($filters['per_page'] ?? 20))->withQueryString(),
            'programs' => Program::orderBy('name_vi')->get(['id', 'name_vi']),
            'summary' => [
                'total' => Course::count(),
                'active' => Course::where('is_active', true)->count(),
                'unassigned' => Course::doesntHave('programs')->count(),
                'missing_materials' => Course::doesntHave('materials')->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('courses.create', ['courses' => Course::orderBy('course_code')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['course_code' => ['required', 'string', 'max:30', 'unique:courses,course_code'], 'name_vi' => ['required', 'string', 'max:255'], 'name_en' => ['nullable', 'string', 'max:255'], 'credits' => ['required', 'integer', 'min:1', 'max:99'], 'theory_hours' => ['required', 'integer', 'min:0'], 'practice_hours' => ['required', 'integer', 'min:0'], 'prerequisite_course_id' => ['nullable', 'exists:courses,id'], 'is_active' => ['sometimes', 'boolean']]);
        $data['is_active'] = $request->boolean('is_active');
        DB::transaction(fn () => Course::create($data));

        return redirect()->route('courses.index')->with('success', 'Đã tạo học phần.');
    }

    public function show(Course $course): View
    {
        $course->load(['prerequisite', 'materials.confirmations.user']);

        return view('courses.show', compact('course'));
    }

    public function edit(Course $course): View
    {
        return view('courses.edit', ['course' => $course, 'courses' => Course::where('id', '!=', $course->id)->orderBy('course_code')->get()]);
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        $data = $request->validate(['course_code' => ['required', 'string', 'max:30', 'unique:courses,course_code,'.$course->id], 'name_vi' => ['required', 'string', 'max:255'], 'name_en' => ['nullable', 'string', 'max:255'], 'credits' => ['required', 'integer', 'min:1', 'max:99'], 'theory_hours' => ['required', 'integer', 'min:0'], 'practice_hours' => ['required', 'integer', 'min:0'], 'prerequisite_course_id' => ['nullable', 'exists:courses,id', 'not_in:'.$course->id], 'is_active' => ['sometimes', 'boolean']]);
        $data['is_active'] = $request->boolean('is_active');
        DB::transaction(fn () => $course->update($data));

        return redirect()->route('courses.show', $course)->with('success', 'Đã cập nhật học phần.');
    }

    public function destroy(Course $course): RedirectResponse
    {
        if ($course->materials()->whereHas('confirmations')->exists()) {
            throw ValidationException::withMessages(['course' => 'Học phần đã có lịch sử xác nhận học liệu, không thể xóa.']);
        }
        DB::transaction(fn () => $course->delete());

        return redirect()->route('courses.index')->with('success', 'Đã xóa học phần.');
    }
}
