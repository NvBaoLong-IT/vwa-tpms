<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseResultImport extends Model
{
    protected $fillable = ['student_id', 'created_by', 'filename', 'payload', 'errors', 'baseline_hash', 'status', 'expires_at', 'imported_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'errors' => 'array', 'expires_at' => 'datetime', 'imported_at' => 'datetime'];
    }
}
