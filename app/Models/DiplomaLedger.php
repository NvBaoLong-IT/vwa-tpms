<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DiplomaLedger extends Model
{
    use SoftDeletes;

    protected $fillable = ['student_id', 'diploma_type', 'reg_number', 'serial_number', 'issue_date', 'decision_number', 'status', 'cohort_code', 'decision_code', 'holder_name', 'holder_date_of_birth', 'holder_ethnicity', 'amendment_decision'];

    protected function casts(): array
    {
        return ['issue_date' => 'date', 'holder_date_of_birth' => 'date'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(DiplomaAuditLog::class)->latest();
    }
}
