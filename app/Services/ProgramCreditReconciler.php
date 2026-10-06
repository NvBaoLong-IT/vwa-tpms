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
        ])->all();

        return $this->analyze((int) $program->total_credits, (int) $program->elective_credits_required, $courses, $program->required_knowledge_blocks ?? []);
    }

    public function forVersion(ProgramVersion $version): array
    {
        $snapshot = $version->snapshot;

        return $this->analyze((int) $snapshot['program']['total_credits'], (int) $snapshot['program']['elective_credits_required'], $snapshot['courses'], $snapshot['required_blocks']);
    }

    private function analyze(int $declaredCredits, int $electiveRequired, array $courses, array $requiredBlocks): array
    {
        $mandatoryCredits = 0;
        $electiveOffered = 0;
        $presentBlocks = [];
        foreach ($courses as $course) {
            if ($course['is_mandatory']) {
                $mandatoryCredits += (int) $course['credits'];
            } else {
                $electiveOffered += (int) $course['credits'];
            }
            $presentBlocks[] = mb_strtolower(trim($course['knowledge_block']));
        }

        $assignedCredits = $mandatoryCredits + $electiveOffered;
        $effectiveCredits = $mandatoryCredits + $electiveRequired;
        $issues = [];
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

        return [
            'declared_credits' => $declaredCredits,
            'mandatory_credits' => $mandatoryCredits,
            'elective_required' => $electiveRequired,
            'elective_offered' => $electiveOffered,
            'assigned_credits' => $assignedCredits,
            'effective_credits' => $effectiveCredits,
            'missing_blocks' => $missingBlocks,
            'issues' => $issues,
        ];
    }
}
