<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginLog extends Model
{
    protected $fillable = ['user_id', 'identifier', 'successful', 'ip_address', 'logged_at'];

    protected function casts(): array
    {
        return ['successful' => 'boolean', 'logged_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
