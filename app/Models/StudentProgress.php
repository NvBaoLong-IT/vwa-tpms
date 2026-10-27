<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentProgress extends Model
{
    protected $fillable = ['student_id', 'semester', 'credits_accumulated', 'gpa_4', 'credits_required', 'recorded_at'];

    protected function casts(): array
    {
        return ['gpa_4' => 'decimal:2', 'recorded_at' => 'date'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function histories(): HasMany
    {
        return $this->hasMany(StudentProgressHistory::class)->latest();
    }
}
