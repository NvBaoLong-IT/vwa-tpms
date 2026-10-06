<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebScheduledRun extends Model
{
    protected $fillable = ['run_key', 'task', 'period', 'origin', 'status', 'triggered_by', 'started_at', 'finished_at', 'retry_after', 'error'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime', 'retry_after' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
