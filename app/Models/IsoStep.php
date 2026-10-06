<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IsoStep extends Model
{
    protected $fillable = ['iso_workflow_id', 'step_number', 'step_name', 'deadline', 'status', 'assigned_to_user_id', 'note', 'overdue_notified_at'];

    protected function casts(): array
    {
        return ['deadline' => 'date', 'overdue_notified_at' => 'datetime'];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(IsoWorkflow::class, 'iso_workflow_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(IsoHistory::class);
    }
}
