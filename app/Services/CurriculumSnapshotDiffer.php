<?php

namespace App\Services;

class CurriculumSnapshotDiffer
{
    public function compare(?array $before, array $after): array
    {
        $changes = [];
        foreach (['version' => 'Thông tin phiên bản', 'program' => 'Thông tin chương trình', 'required_blocks' => 'Khối kiến thức', 'elective_groups' => 'Nhóm tự chọn', 'courses' => 'Học phần', 'plos' => 'Chuẩn đầu ra', 'matrix' => 'Mức đáp ứng', 'substitutions' => 'Học phần thay thế'] as $section => $label) {
            $old = $this->entries($section, $before[$section] ?? []);
            $new = $this->entries($section, $after[$section] ?? []);
            foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $key) {
                $left = $old[$key] ?? null;
                $right = $new[$key] ?? null;
                if ((is_array($left) ? $this->canonical($left) : $left) !== (is_array($right) ? $this->canonical($right) : $right)) {
                    $changes[] = ['section' => $label, 'key' => $key, 'action' => $left === null ? 'Thêm' : ($right === null ? 'Bỏ' : 'Sửa'), 'before' => $left, 'after' => $right];
                }
            }
        }

        return $changes;
    }

    public function fingerprint(array $snapshot): string
    {
        return hash('sha256', json_encode($this->canonical($snapshot), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function canonical(array $value): array
    {
        if (array_is_list($value)) {
            $value = array_map(fn ($item) => is_array($item) ? $this->canonical($item) : $item, $value);
            usort($value, fn ($left, $right) => strcmp(json_encode($left), json_encode($right)));
        } else {
            ksort($value);
            foreach ($value as $key => $item) {
                if (is_array($item)) {
                    $value[$key] = $this->canonical($item);
                }
            }
        }

        return $value;
    }

    private function entries(string $section, array $values): array
    {
        if (in_array($section, ['program', 'version'], true)) {
            return $values;
        }
        if ($section === 'required_blocks') {
            return array_fill_keys($values, true);
        }
        $entries = [];
        foreach ($values as $value) {
            $key = match ($section) {
                'courses' => $value['course_code'],
                'plos' => $value['code'],
                'elective_groups' => $value['code'],
                'substitutions' => '#'.$value['source_version_id'].' '.$value['source_course_code'].' → '.$value['target_course_code'],
                default => $value['course_code'].' / '.$value['plo_code'],
            };
            $entries[$key] = $value;
        }

        return $entries;
    }
}
