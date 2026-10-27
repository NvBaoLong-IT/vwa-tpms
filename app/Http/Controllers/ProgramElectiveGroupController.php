<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Services\CurriculumAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProgramElectiveGroupController extends Controller
{
    public function store(Request $request, Program $program, CurriculumAuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9_-]+$/'],
            'name' => ['required', 'string', 'max:150'],
            'knowledge_block' => ['required', 'string', 'max:100'],
            'credits_required' => ['required', 'integer', 'min:1', 'max:999'],
        ]);
        $data['credits_required'] = (int) $data['credits_required'];
        DB::transaction(function () use ($program, $data, $audit): void {
            $program = Program::whereKey($program->id)->lockForUpdate()->firstOrFail();
            $before = $program->elective_groups ?? [];
            $groups = collect($before)->keyBy('code');
            $groups->put($data['code'], $data);
            $program->update(['elective_groups' => $groups->values()->all()]);
            $audit->record('program', $program->id, 'elective_groups_updated', ['elective_groups' => $before], ['elective_groups' => $program->elective_groups], $program->id);
        });

        return back()->with('success', 'Đã lưu nhóm tự chọn. Hãy đối chiếu chỉ tiêu và phân nhóm học phần trước khi trình duyệt CTĐT.');
    }

    public function destroy(Program $program, string $group, CurriculumAuditService $audit): RedirectResponse
    {
        DB::transaction(function () use ($program, $group, $audit): void {
            $program = Program::whereKey($program->id)->lockForUpdate()->firstOrFail();
            $before = $program->elective_groups ?? [];
            abort_unless(collect($before)->contains('code', $group), 404);
            if ($program->courses()->wherePivot('elective_group_code', $group)->exists()) {
                throw ValidationException::withMessages(['elective_group' => 'Nhóm đang có học phần. Hãy chuyển học phần sang nhóm khác trước khi bỏ nhóm.']);
            }
            $groups = collect($before)->reject(fn ($item) => $item['code'] === $group)->values()->all();
            $program->update(['elective_groups' => $groups]);
            $audit->record('program', $program->id, 'elective_groups_updated', ['elective_groups' => $before], ['elective_groups' => $groups], $program->id);
        });

        return back()->with('success', 'Đã bỏ nhóm khỏi bản đang soạn. Các phiên bản đã lưu vẫn được giữ nguyên.');
    }
}
