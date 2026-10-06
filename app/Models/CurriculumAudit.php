<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurriculumAudit extends Model
{
    protected $fillable = ['actor_id', 'actor_name', 'subject_type', 'subject_id', 'program_id', 'action', 'old_values', 'new_values', 'context'];

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array', 'context' => 'array'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
