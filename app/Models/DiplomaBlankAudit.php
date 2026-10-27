<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiplomaBlankAudit extends Model
{
    protected $fillable = ['diploma_blank_id', 'user_id', 'action', 'old_values', 'new_values', 'ip_address', 'document_path'];

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array'];
    }

    public function blank(): BelongsTo
    {
        return $this->belongsTo(DiplomaBlank::class, 'diploma_blank_id')->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
