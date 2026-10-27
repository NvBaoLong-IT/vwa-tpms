<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiplomaAuditLog extends Model
{
    protected $fillable = ['diploma_ledger_id', 'user_id', 'action', 'old_values', 'new_values', 'ip_address'];

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(DiplomaLedger::class, 'diploma_ledger_id')->withTrashed();
    }
}
