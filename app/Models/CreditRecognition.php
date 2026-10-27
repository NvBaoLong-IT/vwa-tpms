<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditRecognition extends Model
{
    protected $fillable = ['student_id', 'program_version_id', 'request_data', 'allocation', 'baseline_hash', 'status', 'decision_number', 'note', 'submitted_by', 'reviewed_by', 'reviewed_at', 'review_note'];

    protected function casts(): array
    {
        return ['request_data' => 'array', 'allocation' => 'array', 'reviewed_at' => 'datetime'];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ProgramVersion::class, 'program_version_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
