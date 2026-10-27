<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramVersion extends Model
{
    protected $fillable = [
        'cohort_year', 'revision', 'decision_number', 'decision_date',
        'status', 'snapshot', 'created_by', 'published_at',
        'effective_from', 'effective_until', 'iso_workflow_id',
        'submitted_by', 'submitted_at', 'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected function casts(): array
    {
        return [
            'decision_date' => 'date',
            'published_at' => 'datetime',
            'snapshot' => 'array',
            'effective_from' => 'date',
            'effective_until' => 'date',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isoWorkflow(): BelongsTo
    {
        return $this->belongsTo(IsoWorkflow::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'draft' => 'Bản nháp',
            'submitted' => 'Chờ phê duyệt',
            'returned' => 'Trả về bổ sung',
            'approved' => 'Đã phê duyệt',
            'published' => $this->effective_from === null ? 'Đã công bố · Chưa xác định hiệu lực' : ($this->effective_from->isFuture() ? 'Đã công bố · Chưa đến hiệu lực' : ($this->effective_until?->lt(today()) ? 'Đã hết hiệu lực' : 'Đang áp dụng')),
            'archived' => 'Đã thay thế',
            default => $this->status,
        };
    }
}
