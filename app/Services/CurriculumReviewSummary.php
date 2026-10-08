<?php

namespace App\Services;

class CurriculumReviewSummary
{
    public function build(array $payload): array
    {
        $issues = [];
        $program = $payload['program'] ?? [];
        $missing = [];
        foreach (['code' => 'mã chương trình', 'name_vi' => 'tên chương trình', 'degree_level' => 'trình độ', 'total_credits' => 'tổng tín chỉ'] as $field => $label) {
            if (! isset($program[$field]) || $program[$field] === '') {
                $missing[] = $label;
            }
        }
        if ($missing !== []) {
            $issues[] = ['severity' => 'high', 'message' => 'Thông tin chương trình còn thiếu: '.implode(', ', $missing).'.', 'items' => []];
        }
        $courses = $payload['courses'] ?? [];
        foreach (['credits' => ['high', 'Học phần chưa xác định số tín chỉ'], 'is_mandatory' => ['medium', 'Học phần chưa xác định bắt buộc/tự chọn'], 'suggested_semester' => ['low', 'Học phần chưa xác định học kỳ']] as $field => [$severity, $label]) {
            $codes = [];
            foreach ($courses as $index => $course) {
                if (! isset($course[$field]) || $course[$field] === '') {
                    $codes[] = $course['course_code'] ?? 'Dòng '.($index + 1);
                }
            }
            if ($codes !== []) {
                $codes = array_values(array_unique($codes));
                $issues[] = ['severity' => $severity, 'message' => $label.': '.count($codes).' mã.', 'items' => $codes];
            }
        }
        $conflicts = [];
        $details = [];
        $seen = [];
        $labels = ['name_vi' => 'tên học phần', 'knowledge_block' => 'khối kiến thức', 'credits' => 'số tín chỉ', 'is_mandatory' => 'bắt buộc/tự chọn', 'suggested_semester' => 'học kỳ', 'description_vi' => 'nội dung chuẩn đầu ra', 'contribution_level' => 'mức đóng góp'];
        foreach ($payload['warnings'] ?? [] as $warning) {
            $message = trim((string) ($warning['message'] ?? ''));
            if ($message === '') {
                continue;
            }
            if (preg_match('/Dữ liệu (courses|plos|matrix) mã (.+?) khác nhau ở trường (\w+)\./u', $message, $match)) {
                $key = $match[1].':'.$match[2];
                $conflicts[$key]['code'] = $match[2];
                $conflicts[$key]['fields'][] = $labels[$match[3]] ?? $match[3];

                continue;
            }
            $fingerprint = mb_strtolower(preg_replace('/\s+/u', ' ', $message));
            if (isset($seen[$fingerprint])) {
                $position = $seen[$fingerprint];
                if (! empty($warning['source']) && ! in_array($warning['source'], $details[$position]['sources'], true)) {
                    $details[$position]['sources'][] = $warning['source'];
                }

                continue;
            }
            $seen[$fingerprint] = count($details);
            $details[] = ['severity' => $warning['severity'] ?? 'medium', 'message' => $message, 'sources' => empty($warning['source']) ? [] : [$warning['source']]];
        }
        if ($conflicts !== []) {
            $items = [];
            foreach ($conflicts as $conflict) {
                $items[] = $conflict['code'].': '.implode(', ', array_unique($conflict['fields']));
            }
            $issues[] = ['severity' => 'high', 'message' => 'Thông tin khác nhau giữa các trang: '.count($items).' mã cần đối chiếu.', 'items' => $items];
        }
        if (empty($payload['plos'])) {
            $issues[] = ['severity' => 'medium', 'message' => 'Chưa trích xuất được chuẩn đầu ra (PLO) trong toàn bộ tài liệu.', 'items' => []];
        }
        if (empty($payload['matrix'])) {
            $issues[] = ['severity' => 'medium', 'message' => 'Chưa trích xuất được ma trận học phần–chuẩn đầu ra trong toàn bộ tài liệu.', 'items' => []];
        }
        $priority = ['high' => 0, 'medium' => 1, 'low' => 2];
        usort($issues, fn ($a, $b) => $priority[$a['severity']] <=> $priority[$b['severity']]);

        return ['issues' => $issues, 'details' => $details, 'original_count' => count($payload['warnings'] ?? [])];
    }
}
