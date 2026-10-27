<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use SoftDeletes;

    protected $fillable = ['student_code', 'full_name', 'date_of_birth', 'class_code', 'program_id', 'advisor_user_id', 'faculty_name', 'enrollment_year', 'degree_level', 'email', 'status', 'expected_graduation_date', 'graduated_at', 'withdrawn_at', 'proposal_deadline', 'proposal_submitted_at', 'department_defense_deadline', 'department_defended_at', 'academy_defense_deadline', 'academy_defended_at', 'maximum_study_end_date'];

    protected function casts(): array
    {
        return ['date_of_birth' => 'date', 'expected_graduation_date' => 'date', 'graduated_at' => 'date', 'withdrawn_at' => 'date', 'proposal_deadline' => 'date', 'proposal_submitted_at' => 'date', 'department_defense_deadline' => 'date', 'department_defended_at' => 'date', 'academy_defense_deadline' => 'date', 'academy_defended_at' => 'date', 'maximum_study_end_date' => 'date'];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class)->withTrashed();
    }

    public function advisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advisor_user_id');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(StudentProgress::class);
    }

    public function warnings(): HasMany
    {
        return $this->hasMany(WarningLog::class);
    }

    public function diplomas(): HasMany
    {
        return $this->hasMany(DiplomaLedger::class);
    }
}
