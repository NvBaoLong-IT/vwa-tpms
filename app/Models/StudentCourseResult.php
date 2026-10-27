<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentCourseResult extends Model
{
    protected $fillable = ['student_id', 'program_version_id', 'course_code', 'semester', 'credits', 'outcome', 'evidence', 'recorded_by', 'voided_at', 'void_note'];

    protected function casts(): array
    {
        return ['voided_at' => 'datetime'];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ProgramVersion::class, 'program_version_id');
    }
}
