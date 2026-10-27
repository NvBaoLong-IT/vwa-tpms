<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CurriculumAudit;
use App\Models\Plo;
use App\Models\Program;
use Illuminate\Database\Eloquent\Model;

class CurriculumAuditService
{
    private int $modelAuditSuppression = 0;

    public function withoutModelAudit(callable $callback): mixed
    {
        $this->modelAuditSuppression++;
        try {
            return $callback();
        } finally {
            $this->modelAuditSuppression--;
        }
    }

    public function recordModel(Model $model, string $action, ?array $before, ?array $after): void
    {
        if ($this->modelAuditSuppression > 0) {
            return;
        }
        $type = match (true) {
            $model instanceof Program => 'program',
            $model instanceof Course => 'course',
            $model instanceof Plo => 'plo',
        };
        if ($model instanceof Course) {
            $programIds = $action === 'deleted' && $model->relationLoaded('auditPrograms')
                ? $model->getRelation('auditPrograms')->pluck('id')
                : $model->programs()->pluck('programs.id');
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
