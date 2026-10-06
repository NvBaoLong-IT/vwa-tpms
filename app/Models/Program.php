<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Program extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name_vi', 'name_en', 'degree_level', 'delivery_mode',
        'total_credits', 'decision_number', 'decision_date',
        'review_cycle_years', 'is_active', 'elective_credits_required', 'required_knowledge_blocks',
    ];

    protected function casts(): array
    {
        return [
            'decision_date' => 'date',
            'is_active' => 'boolean',
            'required_knowledge_blocks' => 'array',
        ];
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'program_course')
            ->withPivot(['knowledge_block', 'is_mandatory', 'suggested_semester', 'sort_order'])
            ->withTimestamps();
    }

    public function plos(): HasMany
    {
        return $this->hasMany(Plo::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ProgramVersion::class);
    }
}
