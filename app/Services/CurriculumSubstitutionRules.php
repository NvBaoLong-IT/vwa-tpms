<?php

namespace App\Services;

class CurriculumSubstitutionRules
{
    public static function sources(array $rule): array
    {
        return $rule['source_course_codes'] ?? [$rule['source_course_code']];
    }

    public static function targets(array $rule): array
    {
        return $rule['target_courses'] ?? [['course_code' => $rule['target_course_code'], 'credits' => (int) $rule['recognized_credits']]];
    }

    public static function label(array $rule): string
    {
        return implode(' + ', self::sources($rule)).' → '.implode(' + ', array_column(self::targets($rule), 'course_code'));
    }
}
