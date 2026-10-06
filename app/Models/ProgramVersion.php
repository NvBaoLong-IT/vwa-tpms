<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramVersion extends Model
{
    protected $fillable = [
        'cohort_year', 'revision', 'decision_number', 'decision_date',
        'status', 'snapshot', 'created_by', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'decision_date' => 'date',
            'published_at' => 'datetime',
            'snapshot' => 'array',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
