<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CurriculumAudit;
use App\Models\Plo;
use App\Models\Program;
use Illuminate\Database\Eloquent\Model;

class CurriculumAuditService
{
    public function recordModel(Model $model, string $action, ?array $before, ?array $after): void
    {
        $type = match (true) {
            $model instanceof Program => 'program',
            $model instanceof Course => 'course',
            $model instanceof Plo => 'plo',
        };
        if ($model instanceof Course && $action !== 'deleted') {
            $programIds = $model->programs()->pluck('programs.id');
            if ($programIds->isNotEmpty()) {
                foreach ($programIds as $programId) {
                    $this->record($type, $model->id, $action, $before, $after, (int) $programId);
                }

                return;
            }
        }
        $programId = match (true) {
            $model instanceof Program => $action === 'deleted' ? null : $model->id,
            $model instanceof Plo => $model->program_id,
            default => null,
        };

        $this->record($type, $model->id, $action, $before, $after, $programId);
    }

    public function record(string $subjectType, int $subjectId, string $action, ?array $before, ?array $after, ?int $programId = null, ?array $context = null): void
    {
        $actor = auth()->user();
        CurriculumAudit::create([
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'program_id' => $programId,
            'action' => $action,
            'old_values' => $before,
            'new_values' => $after,
            'context' => $context,
        ]);
    }
}
