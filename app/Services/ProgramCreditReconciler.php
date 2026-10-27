<?php

namespace App\Services;

use App\Models\Program;
use App\Models\ProgramVersion;

class ProgramCreditReconciler
{
    public function forProgram(Program $program): array
    {
        $courses = $program->courses->map(fn ($course) => [
            'credits' => (int) $course->credits,
            'knowledge_block' => $course->pivot->knowledge_block,
            'is_mandatory' => (bool) $course->pivot->is_mandatory,
            'counts_toward_total' => (bool) $course->pivot->counts_toward_total,
            'elective_group_code' => $course->pivot->elective_group_code,
        ])->all();

        return $this->analyze((int) $program->total_credits, (int) $program->elective_credits_required, $courses, $program->required_knowledge_blocks ?? [], $program->elective_groups ?? []);
    }

    public function forVersion(ProgramVersion $version): array
    {
        return $this->forSnapshot($version->snapshot);
    }

    public function forSnapshot(array $snapshot): array
    {
        return $this->analyze((int) $snapshot['program']['total_credits'], (int) $snapshot['program']['elective_credits_required'], $snapshot['courses'], $snapshot['required_blocks'], $snapshot['elective_groups'] ?? []);
    }

    private function analyze(int $declaredCredits, int $electiveRequired, array $courses, array $requiredBlocks, array $groups = []): array
    {
        $mandatoryCredits = 0;
        $electiveOffered = 0;
        $outsideCredits = 0;
        $countedCourses = [];
        $presentBlocks = [];
        $issues = [];
        foreach ($courses as $course) {
            if (! ($course['counts_toward_total'] ?? true)) {
                $outsideCredits += (int) $course['credits'];
                if (! empty($course['elective_group_code'])) {
                    $issues[] = ['severity' => 'error', 'message' => 'Học phần ngoài tổng tín chỉ không được gắn nhóm tự chọn tính trong tổng: '.($course['course_code'] ?? $course['knowledge_block']).'.'];
                }
                continue;
            }
            $countedCourses[] = $course;
            if ($course['is_mandatory']) {
                $mandatoryCredits += (int) $course['credits'];
            } else {
                $electiveOffered += (int) $course['credits'];
            }
            $presentBlocks[] = mb_strtolower(trim($course['knowledge_block']));
        }

        $assignedCredits = $mandatoryCredits + $electiveOffered;
        $effectiveCredits = $mandatoryCredits + $electiveRequired;
        if ($effectiveCredits !== $declaredCredits) {
            $issues[] = ['severity' => 'error', 'message' => "Tổng tín chỉ không khớp: bắt buộc + tự chọn cần tích lũy = {$effectiveCredits}, trong khi chương trình khai báo {$declaredCredits}."];
        }
        if ($assignedCredits !== $declaredCredits) {
            $issues[] = ['severity' => 'notice', 'message' => "Tổng tín chỉ học phần gắn là {$assignedCredits}, khác {$declaredCredits} tín chỉ khai báo. Danh mục tự chọn có thể lớn hơn chỉ tiêu cần tích lũy."];
        }
        if ($electiveOffered < $electiveRequired) {
            $missingCredits = $electiveRequired - $electiveOffered;
            $issues[] = ['severity' => 'error', 'message' => "Học phần tự chọn đang mở thiếu {$missingCredits} tín chỉ so với chỉ tiêu."];
        }

        $missingBlocks = array_values(array_filter($requiredBlocks, fn ($block) => ! in_array(mb_strtolower(trim($block)), $presentBlocks, true)));
        if ($missingBlocks !== []) {
            $issues[] = ['severity' => 'error', 'message' => 'Thiếu khối kiến thức: '.implode(', ', $missingBlocks).'.'];
        } elseif ($requiredBlocks === []) {
            $issues[] = ['severity' => 'notice', 'message' => 'Chưa khai báo khối kiến thức cần có để đối chiếu.'];
        }

        $groupResults = [];
        $groupDefinitions = collect($groups)->keyBy('code');
        if ($groups !== []) {
            $groupRequired = (int) collect($groups)->sum('credits_required');
            if ($groupRequired !== $electiveRequired) {
                $issues[] = ['severity' => 'error', 'message' => "Tổng chỉ tiêu các nhóm tự chọn là {$groupRequired}, không khớp {$electiveRequired} tín chỉ tự chọn phải tích lũy."];
            }
        }
        foreach ($countedCourses as $course) {
            $code = $course['elective_group_code'] ?? null;
            $group = $code ? $groupDefinitions->get($code) : null;
            if ($course['is_mandatory'] && $code) {
                $issues[] = ['severity' => 'error', 'message' => 'Học phần bắt buộc không được thuộc nhóm tự chọn '.$code.'.'];
            } elseif (! $course['is_mandatory'] && ($groups !== [] || $code) && (! $group || mb_strtolower(trim($group['knowledge_block'])) !== mb_strtolower(trim($course['knowledge_block'])))) {
                $issues[] = ['severity' => 'error', 'message' => 'Học phần tự chọn chưa gắn nhóm hợp lệ hoặc không đúng khối kiến thức: '.($course['course_code'] ?? $course['knowledge_block']).'.'];
            }
        }
        foreach ($groups as $group) {
            $offered = (int) collect($countedCourses)->filter(fn ($course) => ! $course['is_mandatory'] && ($course['elective_group_code'] ?? null) === $group['code'] && mb_strtolower(trim($course['knowledge_block'])) === mb_strtolower(trim($group['knowledge_block'])))->sum('credits');
            $required = (int) $group['credits_required'];
            $groupResults[] = $group + ['credits_offered' => $offered, 'credits_missing' => max(0, $required - $offered)];
            if ($offered < $required) {
                $issues[] = ['severity' => 'error', 'message' => 'Nhóm tự chọn '.$group['name'].' thiếu '.($required - $offered).' tín chỉ học phần so với chỉ tiêu '.$required.'.'];
            }
        }

        return [
            'declared_credits' => $declaredCredits,
            'mandatory_credits' => $mandatoryCredits,
            'elective_required' => $electiveRequired,
            'elective_offered' => $electiveOffered,
            'assigned_credits' => $assignedCredits,
            'effective_credits' => $effectiveCredits,
            'outside_total_credits' => $outsideCredits,
            'missing_credits' => max(0, $declaredCredits - $effectiveCredits),
            'missing_blocks' => $missingBlocks,
            'issues' => $issues,
            'elective_groups' => $groupResults,
        ];
    }
}
