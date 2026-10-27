<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentImport extends Model
{
    protected $fillable = [
        'program_id', 'uploaded_by', 'reviewed_by', 'imported_by',
        'original_name', 'path', 'mime_type', 'status', 'payload',
        'error_message', 'reviewed_at', 'imported_at',
        'comparison_before', 'comparison_after', 'comparison_hash',
        'submitted_by', 'submitted_at', 'approval_expires_at', 'review_note', 'approved_hash',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'comparison_before' => 'array',
            'comparison_after' => 'array',
            'submitted_at' => 'datetime',
            'approval_expires_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'imported_at' => 'datetime',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function statusLabel(): string
    {
        return ['processing' => 'Đang trích xuất', 'review' => 'Chờ kiểm tra', 'submitted' => 'Chờ lãnh đạo duyệt', 'approved' => 'Đã duyệt, chờ nhập', 'returned' => 'Trả về bổ sung', 'imported' => 'Đã nhập', 'failed' => 'Trích xuất lỗi'][$this->status] ?? $this->status;
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
