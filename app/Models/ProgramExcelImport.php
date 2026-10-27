<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramExcelImport extends Model
{
    protected $fillable = ['created_by', 'program_code', 'filename', 'payload', 'baseline_hash', 'baseline_snapshot', 'status', 'expires_at', 'imported_at', 'submitted_at', 'reviewed_by', 'reviewed_at', 'review_note', 'reviewed_payload_hash'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'baseline_snapshot' => 'array', 'expires_at' => 'datetime', 'imported_at' => 'datetime', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function statusLabel(): string
    {
        return ['review' => 'Đang xem trước', 'submitted' => 'Chờ lãnh đạo duyệt', 'approved' => 'Đã duyệt, chờ nhập', 'returned' => 'Trả về bổ sung', 'imported' => 'Đã nhập'][$this->status] ?? $this->status;
    }
}
