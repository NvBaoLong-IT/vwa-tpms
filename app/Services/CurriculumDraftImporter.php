<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CoursePloMatrix;
use App\Models\DocumentImport;
use App\Models\Program;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CurriculumDraftImporter
{
    public function comparison(DocumentImport $documentImport): array
    {
        if (in_array($documentImport->status, ['submitted', 'approved', 'imported'], true)) {
            return ['before' => $documentImport->comparison_before, 'after' => $documentImport->comparison_after ?? [], 'hash' => $documentImport->status === 'imported' ? null : $documentImport->comparison_hash];
        }
        $payload = $documentImport->payload ?? [];
        $this->validatePayload($payload);
        $level = DB::transactionLevel();
        DB::beginTransaction();
        try {
            CurriculumWriteLock::acquire();
            $copy = $documentImport->fresh();
            if ($copy->program_id !== null) {
                Program::whereKey($copy->program_id)->lockForUpdate()->firstOrFail();
            }
            Course::whereIn('course_code', array_column($copy->payload['courses'] ?? [], 'course_code'))->orderBy('id')->lockForUpdate()->get();
            $before = $copy->program ? app(ProgramVersionService::class)->snapshot($copy->program->fresh()) : null;
            $hash = $this->comparisonHash($copy);
            $program = $copy->program_id === null ? $this->importNew($copy, $copy->uploaded_by) : $this->import($copy, $copy->uploaded_by);
            $after = app(ProgramVersionService::class)->snapshot($program);
        } finally {
            DB::rollBack($level);
        }
        $documentImport->update(['comparison_before' => $before, 'comparison_after' => $after, 'comparison_hash' => $hash]);

        return ['before' => $before, 'after' => $after, 'hash' => $hash];
    }

    public function comparisonHash(DocumentImport $documentImport): string
    {
        $program = $documentImport->program?->fresh();
        $catalog = Course::withTrashed()->with('programs:id')->whereIn('course_code', array_column($documentImport->payload['courses'] ?? [], 'course_code'))->orderBy('id')->get()->map(fn ($course) => $course->only(['id', 'course_code', 'name_vi', 'credits', 'prerequisite_course_id', 'corequisite_course_id', 'deleted_at']) + ['program_ids' => $course->programs->pluck('id')->sort()->values()->all()])->all();

        return app(CurriculumSnapshotDiffer::class)->fingerprint(['payload' => $documentImport->payload, 'program' => $program ? app(ProgramVersionService::class)->snapshot($program) : null, 'metadata' => $program?->only(['decision_number', 'decision_date', 'is_active']), 'catalog' => $catalog]);
    }

    public function import(DocumentImport $documentImport, int $userId): Program
    {
        $payload = $documentImport->payload ?? [];
        $this->validatePayload($payload);
        $program = $documentImport->program;
        abort_unless($program !== null, 422, 'Bản nháp này chưa gắn với chương trình.');

        return $this->apply($payload, $program, $documentImport, $userId);
    }

    public function importNew(DocumentImport $documentImport, int $userId): Program
    {
        $payload = $documentImport->payload ?? [];
        $this->validatePayload($payload);
        $programData = collect($payload['program'])->only([
            'code', 'name_vi', 'name_en', 'degree_level', 'delivery_mode', 'total_credits',
            'elective_credits_required', 'decision_number', 'decision_date',
        ])->all();
        if (blank($programData['code'] ?? null) || blank($programData['name_vi'] ?? null) || blank($programData['degree_level'] ?? null) || blank($programData['total_credits'] ?? null)) {
            throw ValidationException::withMessages(['payload' => 'Bản nháp thiếu mã, tên, trình độ hoặc tổng tín chỉ để tạo chương trình mới.']);
        }
        if (Program::withTrashed()->where('code', $programData['code'])->exists()) {
            throw ValidationException::withMessages(['payload' => 'Mã chương trình đã tồn tại. Hãy dùng luồng cập nhật chương trình hiện có.']);
        }

        return app(CurriculumAuditService::class)->withoutModelAudit(fn () => CurriculumWriteLock::transaction(function () use ($payload, $programData, $documentImport, $userId): Program {
            $programData['review_cycle_years'] = 3;
            $programData['is_active'] = true;
            $programData['elective_credits_required'] ??= 0;
            $program = Program::create($programData);

            return $this->apply($payload, $program, $documentImport, $userId, true);
        }));
    }

    private function apply(array $payload, Program $program, DocumentImport $documentImport, int $userId, bool $newProgram = false): Program
    {
        return app(CurriculumAuditService::class)->withoutModelAudit(fn () => CurriculumWriteLock::transaction(function () use ($payload, $program, $documentImport, $userId, $newProgram): Program {
            $program = Program::whereKey($program->id)->lockForUpdate()->firstOrFail();
            $before = app(ProgramVersionService::class)->snapshot($program);
            $data = collect($payload['program'])->only([
                'name_vi', 'name_en', 'degree_level', 'delivery_mode', 'total_credits',
                'elective_credits_required', 'decision_number', 'decision_date',
            ])->filter(fn ($value) => $value !== null && $value !== '')->all();
            $program->update($data);
            if (array_key_exists('knowledge_blocks', $payload)) {
                $program->update(['required_knowledge_blocks' => array_values(array_unique(array_map('trim', $payload['knowledge_blocks'])))]);
            }
            if (array_key_exists('elective_groups', $payload)) {
                $program->update(['elective_groups' => array_map(fn ($group) => array_merge($group, ['credits_required' => (int) $group['credits_required']]), $payload['elective_groups'])]);
            }

            $ploCodes = [];
            foreach ($payload['plos'] as $row) {
                $plo = $program->plos()->updateOrCreate(
                    ['code' => $row['code']],
                    ['description_vi' => $row['description_vi']],
                );
                $ploCodes[$plo->code] = $plo;
            }

            $courses = [];
            foreach ($payload['courses'] as $index => $row) {
                $course = Course::withTrashed()->where('course_code', $row['course_code'])->lockForUpdate()->first();
                if ($course?->trashed()) {
                    throw ValidationException::withMessages(['payload' => 'Học phần '.$row['course_code'].' đã bị xóa tạm. Hãy khôi phục trước khi nhập.']);
                }
                if ($course === null) {
                    $course = Course::create([
                        'course_code' => $row['course_code'], 'name_vi' => $row['name_vi'], 'credits' => $row['credits'],
                    ]);
                } else {
                    $isShared = $course->programs()->where('programs.id', '!=', $program->id)->exists();
                    $isChanged = $course->name_vi !== $row['name_vi'] || (int) $course->credits !== (int) $row['credits'];
                    if ($isShared && $isChanged) {
                        throw ValidationException::withMessages([
                            'payload' => 'Học phần '.$course->course_code.' đang dùng ở chương trình khác và có tên hoặc tín chỉ khác bản nháp. Chưa nhập dữ liệu; hãy đối chiếu học phần dùng chung trước khi xác nhận.',
                        ]);
                    }
                    if ($isChanged) {
                        $course->update(['name_vi' => $row['name_vi'], 'credits' => $row['credits']]);
                    }
                }
                $courses[$course->course_code] = $course;
                $program->courses()->syncWithoutDetaching([
                    $course->id => [
                        'knowledge_block' => ($row['knowledge_block'] ?? null) ?: 'Chưa phân loại',
                        'is_mandatory' => (bool) ($row['is_mandatory'] ?? false),
                        'counts_toward_total' => array_key_exists('counts_toward_total', $row) ? (bool) $row['counts_toward_total'] : ($program->courses()->whereKey($course->id)->first()?->pivot->counts_toward_total ?? true),
                        'suggested_semester' => $row['suggested_semester'] ?? null,
                        'sort_order' => $index + 1,
                        'elective_group_code' => array_key_exists('elective_group_code', $row) ? $row['elective_group_code'] : $program->courses()->whereKey($course->id)->first()?->pivot->elective_group_code,
                    ],
                ]);
            }

            foreach ($payload['courses'] as $row) {
                $course = $courses[$row['course_code']];
                $relations = [];
                foreach (['prerequisite', 'corequisite'] as $relation) {
                    $key = $relation.'_course_code';
                    if (! array_key_exists($key, $row)) {
                        continue;
                    }
                    $code = $row[$key];
                    $related = filled($code) ? ($courses[$code] ?? $program->courses()->where('course_code', $code)->first()) : null;
                    if (filled($code) && ($related === null || $related->id === $course->id)) {
                        throw ValidationException::withMessages(['payload' => 'Học phần '.$course->course_code.' có mã học trước/học song hành không hợp lệ: '.$code]);
                    }
                    $relations[$relation.'_course_id'] = $related?->id;
                }
                if ($relations !== []) {
                    $changed = collect($relations)->contains(fn ($value, $key) => $value !== $course->$key);
                    if ($changed && $course->programs()->where('programs.id', '!=', $program->id)->exists()) {
                        throw ValidationException::withMessages(['payload' => 'Không thể đổi quan hệ học trước/học song hành của học phần dùng chung '.$course->course_code.'.']);
                    }
                    $course->update($relations);
                }
            }
            try {
                foreach ($courses as $course) {
                    app(CoursePrerequisiteValidator::class)->validatePrerequisiteChain($course->prerequisite_course_id, $course->id);
                }
            } catch (ValidationException $exception) {
                throw ValidationException::withMessages(['payload' => $exception->validator->errors()->first()]);
            }

            foreach ($payload['matrix'] as $row) {
                $course = $courses[$row['course_code']] ?? null;
                $plo = $ploCodes[$row['plo_code']] ?? null;
                if ($course && $plo) {
                    CoursePloMatrix::updateOrCreate(
                        ['course_id' => $course->id, 'plo_id' => $plo->id],
                        ['contribution_level' => $row['contribution_level']],
                    );
                }
            }

            app(CurriculumAuditService::class)->record(
                'program', $program->id, $newProgram ? 'ai_document_created' : 'ai_document_imported', $before,
                app(ProgramVersionService::class)->snapshot($program->fresh()), $program->id,
                ['document_import_id' => $documentImport->id, 'imported_by' => $userId],
            );

            return $program->fresh();
        }));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function validatePayload(array $payload, bool $draft = false): void
    {
        $rules = [
            'program' => ['required', 'array'],
            'program.name_vi' => ['nullable', 'string', 'max:255'],
            'program.code' => ['nullable', 'string', 'max:30'],
            'program.name_en' => ['nullable', 'string', 'max:255'],
            'program.delivery_mode' => ['nullable', 'string', 'max:100'],
            'program.decision_number' => ['nullable', 'string', 'max:100'],
            'program.decision_date' => ['nullable', 'date_format:Y-m-d'],
            'program.degree_level' => ['nullable', 'in:dai_hoc,thac_si,tien_si'],
            'program.total_credits' => ['nullable', 'integer', 'min:1', 'max:999'],
            'program.elective_credits_required' => ['nullable', 'integer', 'min:0', 'max:999'],
            'knowledge_blocks' => ['sometimes', 'array', 'max:100'],
            'knowledge_blocks.*' => ['required', 'string', 'max:100', 'distinct:ignore_case'],
            'elective_groups' => ['sometimes', 'array', 'max:100'],
            'elective_groups.*.code' => ['required', 'string', 'max:30', 'distinct:ignore_case', 'regex:/^[A-Z0-9_-]+$/'],
            'elective_groups.*.name' => ['required', 'string', 'max:150'],
            'elective_groups.*.knowledge_block' => ['required', 'string', 'max:100'],
            'elective_groups.*.credits_required' => ['required', 'integer', 'min:1', 'max:999'],
            'courses.*.elective_group_code' => ['sometimes', 'nullable', 'string', 'max:30'],
            'courses.*' => ['array'], 'plos.*' => ['array'], 'matrix.*' => ['array'], 'elective_groups.*' => ['array'],
            'plos' => ['array'], 'plos.*.code' => ['required', 'string', 'max:30', 'distinct'], 'plos.*.description_vi' => ['required', 'string'],
            'courses' => ['array'], 'courses.*.course_code' => ['required', 'string', 'max:30', 'distinct'], 'courses.*.name_vi' => ['required', 'string', 'max:255'],
            'courses.*.prerequisite_course_code' => ['nullable', 'string', 'max:30'],
            'courses.*.corequisite_course_code' => ['nullable', 'string', 'max:30'],
            'courses.*.credits' => ['required', 'integer', 'min:1', 'max:99'], 'courses.*.knowledge_block' => ['nullable', 'string', 'max:100'],
            'courses.*.is_mandatory' => ['nullable', 'boolean'], 'courses.*.suggested_semester' => ['nullable', 'integer', 'min:1', 'max:20'],
            'courses.*.counts_toward_total' => ['sometimes', 'boolean'],
            'courses.*.is_deferred' => ['sometimes', 'boolean'],
            'matrix' => ['array'], 'matrix.*.course_code' => ['required', 'string'], 'matrix.*.plo_code' => ['required', 'string'], 'matrix.*.contribution_level' => ['required', 'integer', 'between:1,3'],
        ];
        if ($draft) {
            foreach (['program', 'courses', 'plos', 'matrix'] as $field) {
                $rules[$field] = ['present', 'array'];
            }
            foreach (['courses.*.course_code', 'courses.*.name_vi', 'courses.*.credits', 'plos.*.code', 'plos.*.description_vi', 'matrix.*.course_code', 'matrix.*.plo_code', 'matrix.*.contribution_level', 'elective_groups.*.credits_required'] as $field) {
                $rules[$field] = array_values(array_filter($rules[$field], fn ($rule) => ! in_array($rule, ['required', 'distinct'], true)));
                $rules[$field][] = 'nullable';
            }
        }
        $validator = Validator::make($payload, $rules, [
            'plos.*.code.distinct' => 'Mã chuẩn đầu ra trong bản nháp bị trùng. Hãy đối chiếu lại PLO.',
        ]);
        if ($validator->fails()) {
            throw ValidationException::withMessages(['payload' => $validator->errors()->first()]);
        }
        if ($draft) {
            return;
        }
        foreach ($payload['courses'] ?? [] as $course) {
            if ($course['is_deferred'] ?? false) {
                throw ValidationException::withMessages(['payload' => 'Bản nháp còn học phần tạm bỏ qua. Hãy bổ sung và đối chiếu trước khi trình duyệt.']);
            }
        }

        $courseCodes = array_fill_keys(array_column($payload['courses'] ?? [], 'course_code'), true);
        $ploCodes = array_fill_keys(array_column($payload['plos'] ?? [], 'code'), true);
        $matrixPairs = [];
        foreach (array_values($payload['matrix'] ?? []) as $index => $row) {
            $line = $index + 1;
            if (! isset($courseCodes[$row['course_code']])) {
                throw ValidationException::withMessages(['payload' => 'Ma trận dòng '.$line.' tham chiếu học phần '.$row['course_code'].' chưa có trong bản nháp. Hãy đối chiếu mã với danh mục học phần.']);
            }
            if (! isset($ploCodes[$row['plo_code']])) {
                throw ValidationException::withMessages(['payload' => 'Ma trận dòng '.$line.' tham chiếu chuẩn đầu ra '.$row['plo_code'].' chưa có trong bản nháp.']);
            }
            $pair = json_encode([$row['course_code'], $row['plo_code']]);
            if (isset($matrixPairs[$pair])) {
                throw ValidationException::withMessages(['payload' => 'Ma trận lặp cặp '.$row['course_code'].' – '.$row['plo_code'].'. Hãy đối chiếu và giữ một mức đóng góp đã xác nhận.']);
            }
            $matrixPairs[$pair] = true;
        }
    }

    public function draftReconciliation(array $payload): array
    {
        $courses = [];
        foreach ($payload['courses'] ?? [] as $course) {
            if (! is_array($course) || ($course['is_deferred'] ?? false)) {
                continue;
            }
            $courses[] = [
                'course_code' => $course['course_code'] ?? '', 'credits' => (int) ($course['credits'] ?? 0),
                'knowledge_block' => $course['knowledge_block'] ?? '', 'is_mandatory' => (bool) ($course['is_mandatory'] ?? false),
                'counts_toward_total' => (bool) ($course['counts_toward_total'] ?? true),
                'elective_group_code' => $course['elective_group_code'] ?? null,
            ];
        }

        return app(ProgramCreditReconciler::class)->forSnapshot([
            'program' => ['total_credits' => $payload['program']['total_credits'] ?? 0, 'elective_credits_required' => $payload['program']['elective_credits_required'] ?? 0],
            'courses' => $courses, 'required_blocks' => $payload['knowledge_blocks'] ?? [],
            'elective_groups' => array_map(fn ($group) => array_merge($group, ['credits_required' => (int) ($group['credits_required'] ?? 0)]), $payload['elective_groups'] ?? []),
        ]);
    }
}
