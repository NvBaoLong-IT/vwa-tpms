<?php

namespace App\Services;

use App\Models\CoursePloMatrix;
use App\Models\Program;
use App\Models\ProgramVersion;
use Illuminate\Support\Facades\DB;

class ProgramVersionService
{
    public function __construct(private CurriculumAuditService $audit) {}

    public function capture(Program $program, int $cohortYear, string $decisionNumber, string $decisionDate, int $userId): ProgramVersion
    {
        return DB::transaction(function () use ($program, $cohortYear, $decisionNumber, $decisionDate, $userId): ProgramVersion {
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
                'knowledge_block' => $course->pivot->knowledge_block,
                'is_mandatory' => (bool) $course->pivot->is_mandatory,
                'suggested_semester' => $course->pivot->suggested_semester,
                'sort_order' => (int) $course->pivot->sort_order,
            ])->all(),
            'plos' => $program->plos->map(fn ($plo) => $plo->only(['code', 'description_vi', 'description_en']))->all(),
            'matrix' => $matrix,
        ];
    }

    public function publish(ProgramVersion $version): ProgramVersion
    {
        return DB::transaction(function () use ($version): ProgramVersion {
            Program::whereKey($version->program_id)->lockForUpdate()->firstOrFail();
            $version = ProgramVersion::whereKey($version->id)->lockForUpdate()->firstOrFail();
            abort_unless($version->status === 'draft', 422);
            $previousVersions = ProgramVersion::where('program_id', $version->program_id)
                ->where('cohort_year', $version->cohort_year)
                ->where('status', 'published')
                ->get();
            foreach ($previousVersions as $previous) {
                $previous->update(['status' => 'archived']);
                $this->audit->record('program_version', $previous->id, 'archived', ['status' => 'published'], ['status' => 'archived'], $version->program_id);
            }
            $version->update(['status' => 'published', 'published_at' => now()]);
            $this->audit->record('program_version', $version->id, 'published', ['status' => 'draft'], ['status' => 'published'], $version->program_id);

            return $version;
        });
    }
}
