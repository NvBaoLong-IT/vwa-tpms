<?php

namespace App\Services;

use App\Models\CoursePloMatrix;
use App\Models\IsoWorkflow;
use App\Models\Program;
use App\Models\ProgramVersion;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ProgramVersionService
{
    public function __construct(private CurriculumAuditService $audit, private ProgramCreditReconciler $reconciler) {}

    public function capture(Program $program, int $cohortYear, string $decisionNumber, string $decisionDate, int $userId): ProgramVersion
    {
        return CurriculumWriteLock::transaction(function () use ($program, $cohortYear, $decisionNumber, $decisionDate, $userId): ProgramVersion {
            $program = Program::whereKey($program->id)->lockForUpdate()->firstOrFail();
            $snapshot = $this->snapshot($program);
            $revision = (int) $program->versions()->where('cohort_year', $cohortYear)->max('revision') + 1;

            $version = $program->versions()->create([
                'cohort_year' => $cohortYear,
                'revision' => $revision,
                'decision_number' => $decisionNumber,
                'decision_date' => $decisionDate,
                'status' => 'draft',
                'snapshot' => $snapshot,
                'created_by' => $userId,
            ]);
            $this->audit->record('program_version', $version->id, 'created', null, ['cohort_year' => $cohortYear, 'revision' => $revision, 'decision_number' => $decisionNumber, 'status' => 'draft'], $program->id);

            return $version;
        });
    }

    public function snapshot(Program $program): array
    {
        $program->load(['courses' => fn ($query) => $query->orderByPivot('sort_order')->orderBy('course_code'), 'plos']);
        $courseCodes = $program->courses->pluck('course_code', 'id');
        $ploCodes = $program->plos->pluck('code', 'id');
        $matrix = CoursePloMatrix::whereIn('course_id', $courseCodes->keys())
            ->whereIn('plo_id', $ploCodes->keys())
            ->get()->map(fn ($entry) => [
                'course_code' => $courseCodes[$entry->course_id],
                'plo_code' => $ploCodes[$entry->plo_id],
                'contribution_level' => (int) $entry->contribution_level,
            ])->all();

        return [
            'program' => $program->only(['code', 'name_vi', 'name_en', 'degree_level', 'delivery_mode', 'total_credits', 'elective_credits_required', 'review_cycle_years']),
            'required_blocks' => $program->required_knowledge_blocks ?? [],
            'courses' => $program->courses->map(fn ($course) => [
                'course_code' => $course->course_code,
                'name_vi' => $course->name_vi,
                'name_en' => $course->name_en,
                'credits' => (int) $course->credits,
                'prerequisite_course_id' => $course->prerequisite_course_id,
                'corequisite_course_id' => $course->corequisite_course_id,
                'knowledge_block' => $course->pivot->knowledge_block,
                'is_mandatory' => (bool) $course->pivot->is_mandatory,
                'counts_toward_total' => (bool) $course->pivot->counts_toward_total,
                'suggested_semester' => $course->pivot->suggested_semester,
                'sort_order' => (int) $course->pivot->sort_order,
                'elective_group_code' => $course->pivot->elective_group_code,
            ])->all(),
            'plos' => $program->plos->map(fn ($plo) => $plo->only(['code', 'description_vi', 'description_en']))->all(),
            'matrix' => $matrix,
            'substitutions' => $program->course_substitutions ?? [],
            'elective_groups' => $program->elective_groups ?? [],
        ];
    }

    public function publish(ProgramVersion $version): ProgramVersion
    {
        return CurriculumWriteLock::transaction(function () use ($version): ProgramVersion {
            Program::whereKey($version->program_id)->lockForUpdate()->firstOrFail();
            $version = ProgramVersion::whereKey($version->id)->lockForUpdate()->firstOrFail();
            abort_unless($version->status === 'approved', 422, 'Phiên bản phải được phê duyệt trước khi công bố.');
            abort_unless($version->submitted_by !== null && $version->reviewed_by !== null && $version->reviewed_by !== $version->submitted_by, 422, 'Phiên bản cần được người khác người trình phê duyệt trước khi công bố.');
            $this->assertReady($version);
            $this->assertIsoApproved($version);
            $previousVersions = ProgramVersion::where('program_id', $version->program_id)
                ->where('cohort_year', $version->cohort_year)
                ->where('status', 'published')
                ->get();
            foreach ($previousVersions as $previous) {
                $previousStart = $previous->effective_from ?? $previous->decision_date;
                if ($version->effective_from->lte($previousStart)) {
                    throw ValidationException::withMessages(['version' => 'Ngày hiệu lực phải sau ngày bắt đầu của phiên bản đã công bố cùng khóa.']);
                }
                $end = $version->effective_from->copy()->subDay();
                $before = $previous->only(['status', 'effective_until']);
                $attributes = [];
                if ($previous->effective_until === null || $previous->effective_until->gt($end)) {
                    $attributes['effective_until'] = $end;
                }
                if (! $version->effective_from->isFuture()) {
                    $attributes['status'] = 'archived';
                }
                $previous->update($attributes);
                $this->audit->record('program_version', $previous->id, 'superseded', $before, $previous->only(['status', 'effective_until']), $version->program_id);
            }
            $version->update(['status' => 'published', 'published_at' => now()]);
            $this->audit->record('program_version', $version->id, 'published', ['status' => 'approved'], ['status' => 'published'], $version->program_id);

            return $version;
        });
    }

    public function submit(ProgramVersion $version, array $data, int $userId): ProgramVersion
    {
        return CurriculumWriteLock::transaction(function () use ($version, $data, $userId): ProgramVersion {
            Program::whereKey($version->program_id)->lockForUpdate()->firstOrFail();
            $version = ProgramVersion::whereKey($version->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($version->status, ['draft', 'returned'], true), 422, 'Chỉ trình duyệt bản nháp hoặc bản được trả về.');
            $before = $version->only(['status', 'effective_from', 'effective_until', 'iso_workflow_id']);
            $version->fill($data);
            $this->assertReady($version);
            if ($version->iso_workflow_id !== null) {
                $iso = IsoWorkflow::findOrFail($version->iso_workflow_id);
                abort_unless($iso->program_id === $version->program_id && $iso->status !== 'cancelled', 422, 'Đợt rà soát phải thuộc chương trình và chưa bị hủy.');
            }
            $version->fill(['status' => 'submitted', 'submitted_by' => $userId, 'submitted_at' => now(), 'reviewed_by' => null, 'reviewed_at' => null, 'review_note' => null])->save();
            $this->audit->record('program_version', $version->id, 'submitted', $before, $version->only(['status', 'effective_from', 'effective_until', 'iso_workflow_id', 'submitted_by']), $version->program_id);

            return $version;
        });
    }

    public function review(ProgramVersion $version, bool $approve, string $note, int $userId): ProgramVersion
    {
        return CurriculumWriteLock::transaction(function () use ($version, $approve, $note, $userId): ProgramVersion {
            Program::whereKey($version->program_id)->lockForUpdate()->firstOrFail();
            $version = ProgramVersion::whereKey($version->id)->lockForUpdate()->firstOrFail();
            abort_unless($version->status === 'submitted', 422, 'Phiên bản không còn chờ phê duyệt.');
            abort_unless(User::findOrFail($userId)->hasRole('Lãnh đạo phòng'), 403);
            abort_unless($version->submitted_by !== null && $version->submitted_by !== $userId, 403, 'Người trình không được tự phê duyệt hoặc trả về phiên bản của mình.');
            if ($approve) {
                $this->assertReady($version);
                $this->assertIsoApproved($version);
            }
            $version->update(['status' => $approve ? 'approved' : 'returned', 'reviewed_by' => $userId, 'reviewed_at' => now(), 'review_note' => $note]);
            $this->audit->record('program_version', $version->id, $approve ? 'approved' : 'returned', ['status' => 'submitted'], $version->only(['status', 'reviewed_by', 'review_note']), $version->program_id);

            return $version;
        });
    }

    private function assertReady(ProgramVersion $version): void
    {
        $errors = collect($this->reconciler->forVersion($version)['issues'])->where('severity', 'error')->pluck('message')->all();
        if ($version->effective_from === null || ($version->effective_until !== null && $version->effective_until->lt($version->effective_from))) {
            $errors[] = 'Cần ngày bắt đầu hiệu lực hợp lệ; ngày kết thúc không được trước ngày bắt đầu.';
        }
        if (! config('curriculum.allow_retroactive_effective_date') && $version->effective_from !== null && $version->effective_from->lt($version->decision_date)) {
            $errors[] = 'Ngày hiệu lực không được trước ngày quyết định.';
        }
        if (config('curriculum.require_iso_review') && $version->iso_workflow_id === null) {
            $errors[] = 'Cần gắn đợt rà soát ISO theo cấu hình kiểm soát CTĐT.';
        }
        $targetCourses = collect($version->snapshot['courses'])->keyBy('course_code');
        foreach ($version->snapshot['substitutions'] ?? [] as $rule) {
            $source = ProgramVersion::whereKey($rule['source_version_id'])->where('program_id', $version->program_id)->whereIn('status', ['published', 'archived'])->first();
            if ($source === null) {
                $errors[] = 'Quy tắc thay thế có phiên bản nguồn không thuộc CTĐT hoặc chưa ban hành.';
            }
            foreach (CurriculumSubstitutionRules::targets($rule) as $allocation) {
                $target = $targetCourses->get($allocation['course_code']);
                if (! $target || $allocation['credits'] > $target['credits']) {
                    $errors[] = 'Quy tắc thay thế '.CurriculumSubstitutionRules::label($rule).' không còn phù hợp với học phần đích. Hãy sửa quy tắc và tạo phiên bản mới.';
                }
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages(['version' => $errors]);
        }
    }

    private function assertIsoApproved(ProgramVersion $version): void
    {
        if ($version->iso_workflow_id !== null) {
            $iso = IsoWorkflow::whereKey($version->iso_workflow_id)->lockForUpdate()->firstOrFail();
            if ($iso->program_id !== $version->program_id || $iso->approved_at === null || $iso->status !== 'completed') {
                throw ValidationException::withMessages(['version' => 'Đợt rà soát liên quan phải được phê duyệt và hoàn tất trước khi duyệt/công bố CTĐT.']);
            }
        }
    }
}
