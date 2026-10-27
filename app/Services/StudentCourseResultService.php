<?php

namespace App\Services;

use App\Models\ProgramVersion;
use App\Models\Student;
use App\Models\StudentCourseResult;
use Illuminate\Validation\ValidationException;

class StudentCourseResultService
{
    public function __construct(private CurriculumAuditService $audit) {}

    public function validateResult(Student $student, array $data): array
    {
        $version = ProgramVersion::whereKey($data['program_version_id'])->whereIn('status', ['published', 'archived'])->first();
        $course = $version ? collect($version->snapshot['courses'])->firstWhere('course_code', $data['course_code']) : null;
        if (! $course || $version->program_id !== $student->program_id || $version->snapshot['program']['degree_level'] !== $student->degree_level) {
            throw ValidationException::withMessages(['result' => 'Học phần phải thuộc phiên bản đã ban hành đúng chương trình và cùng trình độ của sinh viên.']);
        }
        if (StudentCourseResult::where('student_id', $student->id)->where('program_version_id', $version->id)->where('course_code', $data['course_code'])->where('semester', $data['semester'])->exists()) {
            throw ValidationException::withMessages(['result' => 'Đã ghi nhận học phần trong kỳ này. Nếu có sai sót, hãy hủy kết quả với lý do và ghi kỳ/đợt đính chính riêng.']);
        }

        return ['credits' => (int) $course['credits'], 'program_id' => $version->program_id];
    }

    public function record(Student $student, array $data, int $userId): StudentCourseResult
    {
        return CurriculumWriteLock::transaction(function () use ($student, $data, $userId): StudentCourseResult {
            $student = Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $validated = $this->validateResult($student, $data);
            $result = StudentCourseResult::create(collect($data)->only(['program_version_id', 'course_code', 'semester', 'outcome', 'evidence'])->all() + ['student_id' => $student->id, 'credits' => $validated['credits'], 'recorded_by' => $userId]);
            $this->audit->record('student_course_result', $result->id, 'result_recorded', null, $result->only(['student_id', 'program_version_id', 'course_code', 'semester', 'credits', 'outcome', 'evidence']), $validated['program_id']);

            return $result;
        });
    }
}
