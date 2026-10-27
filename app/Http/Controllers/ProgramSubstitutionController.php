<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\ProgramVersion;
use App\Services\CurriculumAuditService;
use App\Services\CurriculumSubstitutionRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProgramSubstitutionController extends Controller
{
    public function store(Request $request, Program $program, CurriculumAuditService $audit): RedirectResponse
    {
        if ($request->filled('source_codes')) {
            $request->merge(['source_course_codes' => preg_split('/[\s,;]+/u', trim($request->string('source_codes')->toString()), -1, PREG_SPLIT_NO_EMPTY)]);
        }
        if ($request->filled('target_lines')) {
            $targets = [];
            foreach (preg_split('/\R/u', trim($request->string('target_lines')->toString())) as $line) {
                $parts = array_map('trim', explode('=', $line));
                $targets[] = ['course_code' => $parts[0] ?? '', 'credits' => $parts[1] ?? null];
            }
            $request->merge(['target_courses' => $targets]);
        }
        $data = $request->validate([
            'source_version_id' => ['required', 'integer', 'exists:program_versions,id'],
            'source_course_code' => ['required_without:source_course_codes', 'nullable', 'string', 'max:30'],
            'source_course_codes' => ['sometimes', 'array', 'min:1', 'max:30'],
            'source_course_codes.*' => ['required', 'string', 'max:30', 'distinct:ignore_case'],
            'target_course_code' => ['required_without:target_courses', 'nullable', 'string', 'max:30'],
            'recognized_credits' => ['required_without:target_courses', 'nullable', 'integer', 'min:1', 'max:99'],
            'target_courses' => ['sometimes', 'array', 'min:1', 'max:30'],
            'target_courses.*.course_code' => ['required', 'string', 'max:30', 'distinct:ignore_case'],
            'target_courses.*.credits' => ['required', 'integer', 'min:1', 'max:99'],
            'decision_number' => ['required', 'string', 'max:100'],
            'note' => ['required', 'string', 'max:2000'],
            'kind' => ['sometimes', 'in:replacement,equivalent'],
        ]);
        DB::transaction(function () use ($program, $data, $audit): void {
            $program = Program::whereKey($program->id)->lockForUpdate()->firstOrFail();
            $source = ProgramVersion::whereKey($data['source_version_id'])->where('program_id', $program->id)->whereIn('status', ['published', 'archived'])->first();
            if ($source === null) {
                throw ValidationException::withMessages(['substitution' => 'Phiên bản nguồn phải đã ban hành và thuộc chính chương trình đang thao tác.']);
            }
            $sources = $data['source_course_codes'] ?? [$data['source_course_code']];
            $targets = array_map(fn ($target) => ['course_code' => $target['course_code'], 'credits' => (int) $target['credits']], $data['target_courses'] ?? [['course_code' => $data['target_course_code'], 'credits' => $data['recognized_credits']]]);
            $sourceCatalog = collect($source?->snapshot['courses'] ?? [])->keyBy('course_code');
            $targetCatalog = $program->courses()->get()->keyBy('course_code');
            $sourceCredits = 0;
            foreach ($sources as $code) {
                if (! $sourceCatalog->has($code)) {
                    throw ValidationException::withMessages(['substitution' => 'Học phần nguồn phải thuộc phiên bản đã công bố.']);
                }
                $sourceCredits += (int) $sourceCatalog[$code]['credits'];
            }
            foreach ($targets as $target) {
                if (! $targetCatalog->has($target['course_code']) || $target['credits'] > (int) $targetCatalog[$target['course_code']]->credits) {
                    throw ValidationException::withMessages(['substitution' => 'Học phần đích phải thuộc CTĐT; phân bổ không được vượt tín chỉ của học phần đích.']);
                }
            }
            $recognized = array_sum(array_column($targets, 'credits'));
            if ($recognized > $sourceCredits) {
                throw ValidationException::withMessages(['substitution' => 'Tổng tín chỉ phân bổ không được vượt tổng tín chỉ các học phần nguồn.']);
            }
            $before = $program->course_substitutions ?? [];
            $sourceKey = collect($sources)->sort()->values()->all();
            $targetKey = collect(array_column($targets, 'course_code'))->sort()->values()->all();
            foreach ($before as $rule) {
                if ($rule['source_version_id'] === $source->id && collect(CurriculumSubstitutionRules::sources($rule))->sort()->values()->all() === $sourceKey && collect(array_column(CurriculumSubstitutionRules::targets($rule), 'course_code'))->sort()->values()->all() === $targetKey) {
                    throw ValidationException::withMessages(['substitution' => 'Quy tắc này đã tồn tại. Hãy bỏ quy tắc cũ trước khi thay đổi.']);
                }
            }
            $rule = ['id' => (string) Str::uuid(), 'kind' => $data['kind'] ?? 'replacement', 'source_version_id' => $source->id, 'source_course_codes' => $sources, 'target_courses' => $targets, 'source_course_code' => implode(' + ', $sources), 'target_course_code' => implode(' + ', array_column($targets, 'course_code')), 'recognized_credits' => $recognized, 'decision_number' => $data['decision_number'], 'note' => $data['note'], 'source_cohort_year' => $source->cohort_year, 'source_program_code' => $source->snapshot['program']['code'], 'source_revision' => $source->revision];
            $rules = [...$before, $rule];
            $program->update(['course_substitutions' => $rules]);
            $audit->record('program', $program->id, 'substitution_updated', ['substitutions' => $before], ['substitutions' => $rules], $program->id);
        });

        return back()->with('success', 'Đã lưu quy tắc thay thế vào bản đang soạn. Cần lưu phiên bản và trình duyệt trước khi áp dụng.');
    }

    public function destroy(Program $program, string $rule, CurriculumAuditService $audit): RedirectResponse
    {
        DB::transaction(function () use ($program, $rule, $audit): void {
            $program = Program::whereKey($program->id)->lockForUpdate()->firstOrFail();
            $before = $program->course_substitutions ?? [];
            abort_unless(collect($before)->contains('id', $rule), 404);
            $rules = collect($before)->reject(fn ($item) => $item['id'] === $rule)->values()->all();
            $program->update(['course_substitutions' => $rules]);
            $audit->record('program', $program->id, 'substitution_updated', ['substitutions' => $before], ['substitutions' => $rules], $program->id);
        });

        return back()->with('success', 'Đã bỏ quy tắc khỏi bản đang soạn. Các phiên bản đã lưu vẫn giữ nguyên.');
    }
}
