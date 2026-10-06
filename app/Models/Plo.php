<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plo extends Model
{
    protected $fillable = ['program_id', 'code', 'description_vi', 'description_en'];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function matrix(): HasMany
    {
        return $this->hasMany(CoursePloMatrix::class);
    }
}
