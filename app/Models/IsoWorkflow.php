<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IsoWorkflow extends Model
{
    protected $fillable = ['program_id', 'type', 'title', 'start_date', 'expected_end_date', 'status', 'note', 'approved_by', 'approved_at', 'closed_by', 'closed_at', 'changed_credits'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'expected_end_date' => 'date', 'approved_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function isEditable(): bool
    {
        return $this->closed_at === null && in_array($this->status, ['draft', 'in_progress'], true);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class)->withTrashed();
    }

    public function steps(): HasMany
    {
        return $this->hasMany(IsoStep::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(IsoFile::class);
    }
}
