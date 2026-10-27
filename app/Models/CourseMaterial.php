<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseMaterial extends Model
{
    protected $fillable = ['course_id', 'title', 'author', 'publisher', 'isbn', 'material_type', 'library_status', 'confirmed_by', 'confirmed_at', 'library_requested_by', 'library_requested_at'];

    protected function casts(): array
    {
        return ['confirmed_at' => 'datetime', 'library_requested_at' => 'datetime'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }

    public function confirmations(): HasMany
    {
        return $this->hasMany(CourseMaterialConfirmation::class)->latest();
    }
}
