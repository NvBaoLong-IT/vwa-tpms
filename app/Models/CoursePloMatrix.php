<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoursePloMatrix extends Model
{
    protected $table = 'course_plo_matrix';

    protected $fillable = ['course_id', 'plo_id', 'contribution_level'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
