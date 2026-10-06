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
        if (Program::where('code', $programData['code'])->exists()) {
            throw ValidationException::withMessages(['payload' => 'Mã chương trình đã tồn tại. Hãy dùng luồng cập nhật chương trình hiện có.']);
        }

        return DB::transaction(function () use ($payload, $programData, $documentImport, $userId): Program {
            $programData['review_cycle_years'] = 3;
            $programData['is_active'] = true;
            $programData['elective_credits_required'] ??= 0;
            $program = Program::create($programData);

            return $this->apply($payload, $program, $documentImport, $userId, true);
        });
    }

    private function apply(array $payload, Program $program, DocumentImport $documentImport, int $userId, bool $newProgram = false): Program
    {
        return DB::transaction(function () use ($payload, $program, $documentImport, $userId, $newProgram): Program {
            $before = app(ProgramVersionService::class)->snapshot($program);
            $data = collect($payload['program'])->only([
                'name_vi', 'name_en', 'degree_level', 'delivery_mode', 'total_credits',
                'elective_credits_required', 'decision_number', 'decision_date',
            ])->filter(fn ($value) => $value !== null && $value !== '')->all();
            $program->update($data);

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
                $course = Course::updateOrCreate(
                    ['course_code' => $row['course_code']],
                    ['name_vi' => $row['name_vi'], 'credits' => $row['credits']],
                );
                $courses[$course->course_code] = $course;
                $program->courses()->syncWithoutDetaching([
                    $course->id => [
                        'knowledge_block' => $row['knowledge_block'] ?: 'Chưa phân loại',
                        'is_mandatory' => (bool) $row['is_mandatory'],
                        'suggested_semester' => $row['suggested_semester'],
                        'sort_order' => $index + 1,
                    ],
                ]);
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
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validatePayload(array $payload): void
    {
        $validator = Validator::make($payload, [
            'program' => ['required', 'array'],
            'program.name_vi' => ['nullable', 'string', 'max:255'],
            'program.degree_level' => ['nullable', 'in:dai_hoc,thac_si,tien_si'],
            'program.total_credits' => ['nullable', 'integer', 'min:1', 'max:999'],
            'program.elective_credits_required' => ['nullable', 'integer', 'min:0', 'max:999'],
            'plos' => ['array'], 'plos.*.code' => ['required', 'string', 'max:30'], 'plos.*.description_vi' => ['required', 'string'],
            'courses' => ['array'], 'courses.*.course_code' => ['required', 'string', 'max:30'], 'courses.*.name_vi' => ['required', 'string', 'max:255'],
            'courses.*.credits' => ['required', 'integer', 'min:1', 'max:99'], 'courses.*.knowledge_block' => ['nullable', 'string', 'max:100'],
            'courses.*.is_mandatory' => ['nullable', 'boolean'], 'courses.*.suggested_semester' => ['nullable', 'integer', 'min:1', 'max:20'],
            'matrix' => ['array'], 'matrix.*.course_code' => ['required', 'string'], 'matrix.*.plo_code' => ['required', 'string'], 'matrix.*.contribution_level' => ['required', 'integer', 'between:1,3'],
        ]);
        if ($validator->fails()) {
            throw ValidationException::withMessages(['payload' => $validator->errors()->first()]);
        }
    }
}
