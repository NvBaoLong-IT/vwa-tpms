<?php

namespace App\Http\Controllers;

use App\Models\CoursePloMatrix;
use App\Models\Program;
use App\Services\CurriculumAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PloMatrixController extends Controller
{
    public function index(Program $program): View
    {
        $program->load(['courses' => fn ($query) => $query->orderByPivot('sort_order')->orderBy('course_code'), 'plos']);
        $levels = CoursePloMatrix::whereIn('course_id', $program->courses->modelKeys())
            ->whereIn('plo_id', $program->plos->modelKeys())
            ->get()->keyBy(fn ($item) => $item->course_id.'-'.$item->plo_id);

        return view('programs.matrix', compact('program', 'levels'));
    }

    public function update(Request $request, Program $program, CurriculumAuditService $audit): RedirectResponse
    {
        $data = $request->validate(['levels' => ['nullable', 'array'], 'levels.*' => ['array'], 'levels.*.*' => ['nullable', 'integer', 'between:1,3']]);
        DB::transaction(function () use ($data, $program, $audit): void {
            $program = Program::whereKey($program->id)->lockForUpdate()->firstOrFail();
            $courseIds = $program->courses()->pluck('courses.id')->map(fn ($id) => (int) $id)->all();
            $ploIds = $program->plos()->pluck('id')->map(fn ($id) => (int) $id)->all();
            $before = [];
            $after = [];
            foreach ($data['levels'] ?? [] as $courseId => $values) {
                abort_unless(in_array((int) $courseId, $courseIds, true), 422);
                foreach ($values as $ploId => $level) {
                    abort_unless(in_array((int) $ploId, $ploIds, true), 422);
                    $current = CoursePloMatrix::where('course_id', $courseId)->where('plo_id', $ploId)->first();
                    $oldLevel = $current?->contribution_level;
                    $newLevel = $level === null || $level === '' ? null : (int) $level;
                    if ($oldLevel === $newLevel) {
                        continue;
                    }
                    $key = $courseId.':'.$ploId;
                    $before[$key] = $oldLevel;
                    $after[$key] = $newLevel;
                    if ($newLevel === null) {
                        $current?->delete();
                    } else {
                        CoursePloMatrix::updateOrCreate(['course_id' => $courseId, 'plo_id' => $ploId], ['contribution_level' => $newLevel]);
                    }
                }
            }
            if ($after !== []) {
                $audit->record('plo_matrix', $program->id, 'updated', $before, $after, $program->id);
            }
        });

        return back()->with('success', 'Đã lưu mức đáp ứng chuẩn đầu ra.');
    }
}
