<?php

namespace App\Services;

use App\Models\CreditRecognition;
use App\Models\ProgramVersion;
use App\Models\Student;
use App\Models\StudentCourseResult;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CreditRecognitionService
{
    public function __construct(private CurriculumSnapshotDiffer $differ, private CurriculumAuditService $audit) {}

    public function submit(Student $student, ProgramVersion $version, array $data, int $userId): CreditRecognition
    {
        return CurriculumWriteLock::transaction(function () use ($student, $version, $data, $userId): CreditRecognition {
            $student = Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $version = $version->fresh();
            $allocation = $this->allocate($student, $version, $data);
            $recognition = CreditRecognition::create([
                'student_id' => $student->id, 'program_version_id' => $version->id,
                'request_data' => ['result_ids' => array_map('intval', $data['result_ids']), 'rule_id' => $data['rule_id'] ?? null],
                'allocation' => $allocation, 'baseline_hash' => $this->baseline($student, $version),
                'status' => 'submitted', 'decision_number' => $data['decision_number'], 'note' => $data['note'], 'submitted_by' => $userId,
            ]);
            $this->audit->record('credit_recognition', $recognition->id, 'recognition_submitted', null, $recognition->only(['student_id', 'program_version_id', 'allocation', 'decision_number', 'note']), $version->program_id);

            return $recognition;
        });
    }

    public function review(Student $student, CreditRecognition $recognition, bool $approve, string $note, int $userId): void
    {
        CurriculumWriteLock::transaction(function () use ($student, $recognition, $approve, $note, $userId): void {
            abort_unless(User::findOrFail($userId)->hasRole('Lãnh đạo phòng'), 403);
            $student = Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $recognition = CreditRecognition::whereKey($recognition->id)->lockForUpdate()->firstOrFail();
            abort_unless($recognition->student_id === $student->id, 404);
            abort_unless($recognition->status === 'submitted', 422, 'Hồ sơ không còn chờ phê duyệt.');
            abort_unless($recognition->submitted_by !== $userId, 403, 'Người trình không được tự phê duyệt hồ sơ công nhận tín chỉ.');
            if ($approve) {
                $version = $recognition->version;
                if (! hash_equals($recognition->baseline_hash, $this->baseline($student, $version))) {
                    throw ValidationException::withMessages(['recognition' => 'Kết quả học phần, hồ sơ sinh viên hoặc phiên bản đích đã thay đổi. Hãy trả về và lập đề nghị mới.']);
                }
                $allocation = $this->allocate($student, $version, $recognition->request_data);
                if ($this->differ->fingerprint($allocation) !== $this->differ->fingerprint($recognition->allocation)) {
                    throw ValidationException::withMessages(['recognition' => 'Nội dung phân bổ không còn đúng bản đã trình.']);
                }
            }
            $recognition->update(['status' => $approve ? 'approved' : 'returned', 'reviewed_by' => $userId, 'reviewed_at' => now(), 'review_note' => $note]);
            $this->audit->record('credit_recognition', $recognition->id, $approve ? 'recognition_approved' : 'recognition_returned', ['status' => 'submitted'], $recognition->only(['status', 'student_id', 'allocation', 'review_note']), $recognition->version->program_id);
        });
    }

    public function allocate(Student $student, ProgramVersion $version, array $data): array
    {
        if ($student->program_id !== $version->program_id || (int) $student->enrollment_year !== (int) $version->cohort_year || $student->degree_level !== $version->snapshot['program']['degree_level'] || $version->status !== 'published' || $version->effective_from === null || $version->effective_from->isFuture() || $version->effective_until?->lt(today())) {
            throw ValidationException::withMessages(['recognition' => 'Phiên bản đích phải đang có hiệu lực, đúng ngành, trình độ và khóa nhập học của sinh viên.']);
        }
        $ids = array_map('intval', $data['result_ids']);
        $results = StudentCourseResult::where('student_id', $student->id)->whereIn('id', $ids)->whereNull('voided_at')->whereIn('outcome', ['passed', 'exempt'])->get();
        if (count(array_unique($ids)) !== count($ids) || $results->count() !== count($ids) || $results->isEmpty()) {
            throw ValidationException::withMessages(['recognition' => 'Chỉ chọn kết quả đạt hoặc miễn hợp lệ của chính sinh viên; không được chọn trùng.']);
        }
        $sourceKeys = $results->map(fn ($result) => $result->program_version_id.':'.$result->course_code)->all();
        $validSources = ProgramVersion::whereIn('id', $results->pluck('program_version_id'))->where('program_id', $student->program_id)->whereIn('status', ['published', 'archived'])->pluck('id')->all();
        if ($results->contains(fn ($result) => ! in_array($result->program_version_id, $validSources, true))) {
            throw ValidationException::withMessages(['recognition' => 'Kết quả nguồn không thuộc chương trình hiện tại của sinh viên. Cần đối chiếu và xử lý hồ sơ trước khi công nhận.']);
        }
        if (count(array_unique($sourceKeys)) !== count($sourceKeys)) {
            throw ValidationException::withMessages(['recognition' => 'Học lại cùng học phần chỉ được dùng một lần, không cộng tín chỉ các lần học.']);
        }
        $targets = [];
        if (filled($data['rule_id'] ?? null)) {
            $rule = collect($version->snapshot['substitutions'] ?? [])->firstWhere('id', $data['rule_id']);
            if (! $rule || $results->contains(fn ($result) => $result->program_version_id !== $rule['source_version_id']) || $results->pluck('course_code')->sort()->values()->all() !== collect(CurriculumSubstitutionRules::sources($rule))->sort()->values()->all()) {
                throw ValidationException::withMessages(['recognition' => 'Cần đủ học phần nguồn đúng phiên bản theo quy tắc thay thế đã ban hành.']);
            }
            $targets = CurriculumSubstitutionRules::targets($rule);
        } else {
            foreach ($results as $result) {
                if ($result->program_version_id !== $version->id) {
                    throw ValidationException::withMessages(['recognition' => 'Kết quả thuộc phiên bản khác phải có quy tắc thay thế hoặc tương đương đã ban hành.']);
                }
                $targets[] = ['course_code' => $result->course_code, 'credits' => (int) $result->credits];
            }
        }
        $approved = CreditRecognition::where('student_id', $student->id)->where('program_version_id', $version->id)->where('status', 'approved')->get();
        $usedKeys = $approved->flatMap(fn ($item) => $item->allocation['source_keys'])->all();
        if (array_intersect($sourceKeys, $usedKeys) !== []) {
            throw ValidationException::withMessages(['recognition' => 'Học phần nguồn đã được công nhận trong phiên bản đích này; không được tính trùng, kể cả dùng lần học lại khác.']);
        }
        $catalog = collect($version->snapshot['courses'])->keyBy('course_code');
        $already = [];
        foreach ($approved as $item) {
            foreach ($item->allocation['targets'] as $target) {
                $already[$target['course_code']] = ($already[$target['course_code']] ?? 0) + $target['credits'];
            }
        }
        foreach ($targets as $target) {
            $course = $catalog->get($target['course_code']);
            $already[$target['course_code']] = ($already[$target['course_code']] ?? 0) + $target['credits'];
            if (! $course || $target['credits'] < 1 || $already[$target['course_code']] > $course['credits']) {
                throw ValidationException::withMessages(['recognition' => 'Tổng công nhận vượt tín chỉ học phần đích hoặc học phần đích không thuộc CTĐT.']);
            }
        }
        if (array_sum(array_column($targets, 'credits')) > $results->sum('credits')) {
            throw ValidationException::withMessages(['recognition' => 'Tín chỉ phân bổ vượt tổng tín chỉ kết quả nguồn.']);
        }

        return ['source_keys' => $sourceKeys, 'results' => $results->map(fn ($result) => $result->only(['id', 'program_version_id', 'course_code', 'semester', 'credits', 'outcome', 'evidence']))->all(), 'targets' => $targets, 'rule_id' => $data['rule_id'] ?? null];
    }

    public function baseline(Student $student, ProgramVersion $version): string
    {
        return $this->differ->fingerprint(['student' => $student->only(['id', 'program_id', 'degree_level', 'enrollment_year']), 'version' => $version->fresh()->only(['id', 'status', 'snapshot', 'effective_from', 'effective_until']), 'results' => StudentCourseResult::where('student_id', $student->id)->orderBy('id')->get()->map(fn ($result) => $result->only(['id', 'program_version_id', 'course_code', 'credits', 'outcome', 'voided_at']))->all()]);
    }

    public function summary(Student $student, ProgramVersion $version): array
    {
        $recognized = [];
        foreach (CreditRecognition::where('student_id', $student->id)->where('program_version_id', $version->id)->where('status', 'approved')->get() as $item) {
            foreach ($item->allocation['targets'] as $target) {
                $recognized[$target['course_code']] = ($recognized[$target['course_code']] ?? 0) + $target['credits'];
            }
        }
        $mandatory = $elective = $outside = 0;
        $groups = [];
        $courses = [];
        foreach ($version->snapshot['courses'] as $course) {
            $credits = min($recognized[$course['course_code']] ?? 0, (int) $course['credits']);
            $courses[] = $course + ['recognized' => $credits, 'missing' => (int) $course['credits'] - $credits];
            if (! ($course['counts_toward_total'] ?? true)) {
                $outside += $credits;
                continue;
            }
            if ($course['is_mandatory']) {
                $mandatory += $credits;
            } else {
                $elective += $credits;
                $code = $course['elective_group_code'] ?? '';
                $groups[$code] = ($groups[$code] ?? 0) + $credits;
            }
        }
        $groupRows = [];
        $countedElective = 0;
        foreach ($version->snapshot['elective_groups'] ?? [] as $group) {
            $credits = $groups[$group['code']] ?? 0;
            $groupRows[] = $group + ['recognized' => $credits, 'missing' => max(0, $group['credits_required'] - $credits)];
            $countedElective += min($credits, $group['credits_required']);
        }
        if ($groupRows === []) {
            $countedElective = min($elective, (int) $version->snapshot['program']['elective_credits_required']);
        }

        return ['courses' => $courses, 'groups' => $groupRows, 'mandatory' => $mandatory, 'elective' => $elective, 'outside_total' => $outside, 'counted' => $mandatory + $countedElective, 'required' => (int) $version->snapshot['program']['total_credits']];
    }
}
