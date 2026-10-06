<?php

namespace App\Observers;

use App\Services\CurriculumAuditService;
use Illuminate\Database\Eloquent\Model;

class CurriculumAuditObserver
{
    public function __construct(private CurriculumAuditService $audit) {}

    public function created(Model $model): void
    {
        $this->audit->recordModel($model, 'created', null, $model->attributesToArray());
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        $before = [];
        $after = [];
        foreach (array_keys($changes) as $key) {
            if ($key === 'updated_at') {
                continue;
            }
            $before[$key] = $model->getOriginal($key);
            $after[$key] = $model->getAttribute($key);
        }
        if ($after !== []) {
            $this->audit->recordModel($model, 'updated', $before, $after);
        }
    }

    public function deleted(Model $model): void
    {
        $this->audit->recordModel($model, 'deleted', $model->attributesToArray(), null);
    }
}
