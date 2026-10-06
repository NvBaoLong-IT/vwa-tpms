<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarningLog extends Model
{
    protected $fillable = ['student_id', 'student_progress_id', 'warning_type', 'reason', 'status', 'issued_at', 'resolved_at', 'resolved_by'];

    protected function casts(): array
    {
        return ['issued_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function progress(): BelongsTo
    {
        return $this->belongsTo(StudentProgress::class, 'student_progress_id');
    }
}
