<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiplomaRequest extends Model
{
    protected $fillable = ['student_id', 'requested_by', 'reviewed_by', 'diploma_ledger_id', 'diploma_type', 'serial_number', 'cohort_code', 'decision_code', 'issue_date', 'status', 'review_note', 'reviewed_at'];

    protected function casts(): array
    {
        return ['issue_date' => 'date', 'reviewed_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
