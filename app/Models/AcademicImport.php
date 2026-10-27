<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicImport extends Model
{
    protected $fillable = ['user_id', 'original_name', 'kind', 'status', 'rows_imported', 'error_count', 'errors', 'failure_message', 'started_at', 'completed_at'];

    protected function casts(): array
    {
        return ['errors' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function errorRows(): array
    {
        return array_values($this->errors ?? []);
    }
}
