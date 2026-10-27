<?php

namespace App\Services;

use App\Models\Course;
use Illuminate\Validation\ValidationException;

class CoursePrerequisiteValidator
{
    public function validatePrerequisiteChain(?int $prerequisiteId, ?int $courseId = null): void
    {
        $courses = Course::orderBy('id')->lockForUpdate()->get(['id', 'prerequisite_course_id'])->keyBy('id');
        $visited = [];
        while ($prerequisiteId !== null) {
            if ($prerequisiteId === $courseId || isset($visited[$prerequisiteId])) {
                throw ValidationException::withMessages([
                    'prerequisite_course_id' => 'Học phần tiên quyết tạo thành vòng lặp. Hãy chọn học phần khác hoặc bỏ điều kiện tiên quyết.',
                ]);
            }
            if (! $courses->has($prerequisiteId)) {
                throw ValidationException::withMessages(['prerequisite_course_id' => 'Học phần tiên quyết không tồn tại hoặc đã bị xóa.']);
            }
            $visited[$prerequisiteId] = true;
            $next = $courses->get($prerequisiteId)->prerequisite_course_id;
            $prerequisiteId = $next === null ? null : (int) $next;
        }
    }
}
