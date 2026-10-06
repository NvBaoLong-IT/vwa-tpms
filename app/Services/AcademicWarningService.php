<?php

namespace App\Services;

use App\Models\AcademicSetting;
use App\Models\Student;
use App\Models\StudentProgress;
use App\Models\User;
use App\Models\WarningLog;
use App\Notifications\AcademicWarningNotification;

class AcademicWarningService
{
    public function thresholds(?string $degreeLevel = null): array
    {
        $defaults = config('academic.warning_thresholds');
        $defaults['gpa_below'] = config('academic.gpa_by_degree')[$degreeLevel] ?? $defaults['gpa_below'];
        $settings = AcademicSetting::whereIn('key', array_keys($defaults))
            ->where(fn ($query) => $query->whereNull('degree_level')->orWhere('degree_level', $degreeLevel))
            ->get()->sortBy(fn ($setting) => $setting->degree_level === null ? 0 : 1);
        foreach ($settings as $setting) {
            if ($degreeLevel !== null && $setting->key === 'gpa_below' && $setting->degree_level === null) {
                continue;
            }
            $defaults[$setting->key] = (float) $setting->value;
        }

        return $defaults;
    }

    public function scan(): int
    {
        $count = 0;
        Student::where('status', 'active')->with(['progress' => fn ($query) => $query->orderByDesc('semester')])->chunkById(200, function ($students) use (&$count): void {
            foreach ($students as $student) {
                $progress = $student->progress->first();
                $threshold = $this->thresholds($student->degree_level);
                $rules = [];
                if ($progress) {
                    if ($progress->gpa_4 !== null && (float) $progress->gpa_4 < $threshold['gpa_below']) {
                        $rules['low_gpa'] = 'Điểm trung bình hệ 4 '.$progress->gpa_4.' thấp hơn ngưỡng '.$threshold['gpa_below'];
                    }
                    $previous = $student->progress->skip(1)->first();
                    if ($student->degree_level === 'dai_hoc' && $previous
                        && $this->consecutive($previous, $progress)
                        && $this->debt($progress) > $threshold['credit_debt_above']
                        && $this->debt($previous) > $threshold['credit_debt_above']) {
                        $rules['credit_debt'] = 'Nợ trên '.$threshold['credit_debt_above'].' tín chỉ trong hai học kỳ liên tiếp';
                    }
                    if ($progress->credits_required && $progress->credits_accumulated / $progress->credits_required < $threshold['progress_ratio_below']) {
                        $rules['delayed_progress'] = 'Tiến độ tích lũy dưới '.($threshold['progress_ratio_below'] * 100).'% kế hoạch';
                    }
                    if ($student->expected_graduation_date
                        && $student->expected_graduation_date->lte(today()->addDays((int) $threshold['graduation_lookahead_days']))
                        && $this->debt($progress) > $threshold['credit_debt_above']) {
                        $rules['graduation_risk'] = 'Còn '.$this->debt($progress).' tín chỉ trước hạn tốt nghiệp dự kiến '.$student->expected_graduation_date->format('d/m/Y');
                    }
                }
                if ($student->degree_level !== 'dai_hoc') {
                    foreach (['proposal_deadline' => ['proposal_submitted_at', 'proposal_due', 'đề cương'], 'department_defense_deadline' => ['department_defended_at', 'department_defense_due', 'bảo vệ cấp cơ sở'], 'academy_defense_deadline' => ['academy_defended_at', 'academy_defense_due', 'bảo vệ cấp Học viện']] as $field => [$completed, $type, $label]) {
                        if ($student->$field && ! $student->$completed && $student->$field->lte(today()->addDays((int) $threshold['milestone_lookahead_days']))) {
                            $rules[$type] = 'Sắp đến hoặc quá hạn '.$label.' ngày '.$student->$field->format('d/m/Y');
                        }
                    }
                    if ($student->maximum_study_end_date && $student->maximum_study_end_date->lte(today()->addDays((int) $threshold['milestone_lookahead_days']))) {
                        $rules['maximum_study_due'] = 'Sắp đến hoặc quá thời gian đào tạo tối đa ngày '.$student->maximum_study_end_date->format('d/m/Y');
                    }
                }
                foreach ($rules as $type => $reason) {
                    if (WarningLog::where('student_id', $student->id)->where('warning_type', $type)->where('reason', $reason)->exists()
                        || WarningLog::where('student_id', $student->id)->where('warning_type', $type)->whereIn('status', ['open', 'processing'])->exists()) {
                        continue;
                    }
                    $warning = WarningLog::create(['student_id' => $student->id, 'student_progress_id' => $progress?->id, 'warning_type' => $type, 'reason' => $reason, 'issued_at' => now()]);
                    $this->notifyRecipients($student, $warning);
                    $count++;
                }
            }
        });

        return $count;
    }

    private function debt(StudentProgress $progress): int
    {
        return max(0, (int) $progress->credits_required - (int) $progress->credits_accumulated);
    }

    private function consecutive(StudentProgress $previous, StudentProgress $current): bool
    {
        if (! preg_match('/^(\d{4})-([12])$/', $previous->semester, $older)
            || ! preg_match('/^(\d{4})-([12])$/', $current->semester, $newer)) {
            return false;
        }

        return (int) $newer[1] * 2 + (int) $newer[2] === (int) $older[1] * 2 + (int) $older[2] + 1;
    }

    private function notifyRecipients(Student $student, WarningLog $warning): void
    {
        $recipients = collect([$student->advisor])->filter()->merge(User::permission('academic.process')->get())->unique('id');
        foreach ($recipients as $recipient) {
            $recipient->notify(new AcademicWarningNotification($warning));
        }
    }

    public function resolve(WarningLog $warning, int $userId, string $status = 'resolved'): void
    {
        $warning->update(['status' => $status, 'resolved_by' => $userId, 'resolved_at' => now()]);
    }
}
