<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CurriculumEditGuard
{
    public function programHash(Program $program): string
    {
        $program = Program::whereKey($program->id)->firstOrFail();

        return app(CurriculumSnapshotDiffer::class)->fingerprint(['attributes' => $program->getAttributes(), 'snapshot' => app(ProgramVersionService::class)->snapshot($program)]);
    }

    public function courseHash(Course $course): string
    {
        $course = Course::whereKey($course->id)->firstOrFail();

        return app(CurriculumSnapshotDiffer::class)->fingerprint(['attributes' => $course->getAttributes(), 'program_ids' => $course->programs()->orderBy('programs.id')->pluck('programs.id')->all()]);
    }

    public function assertRequest(Request $request): void
    {
        if (! $request->routeIs('programs.update', 'courses.update')) {
            return;
        }
        $data = $request->validate(['editing_hash' => ['required', 'string', 'size:64']]);
        $current = $request->routeIs('programs.update') ? $this->programHash($request->route('program')) : $this->courseHash($request->route('course'));
        if (! hash_equals($current, $data['editing_hash'])) {
            throw ValidationException::withMessages(['editing_hash' => 'Dữ liệu đã được người khác thay đổi từ khi mở biểu mẫu. Hãy tải lại trang và đối chiếu trước khi lưu; chưa ghi đè dữ liệu.']);
        }
    }
}
