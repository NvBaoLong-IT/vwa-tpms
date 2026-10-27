<?php

namespace App\Imports;

use App\Models\Student;
use App\Services\DiplomaIssuanceService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class GraduationImport implements ToCollection, WithHeadingRow
{
    public int $imported = 0;

    public array $errors = [];

    public function __construct(private ?int $userId = null) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $line = $index + 2;
            try {
                foreach (['MaSV', 'LoaiVanBang', 'SoPhoi', 'NgayCap', 'MaKhoa'] as $column) {
                    if (! isset($row[$column]) || trim((string) $row[$column]) === '') {
                        throw new \InvalidArgumentException("Thiếu cột {$column}");
                    }
                }
                $type = trim((string) $row['LoaiVanBang']);
                if (! in_array($type, ['dai_hoc', 'thac_si', 'tien_si', 'chung_chi'], true)) {
                    throw new \InvalidArgumentException('Loại văn bằng không hợp lệ');
                }
                $student = Student::where('student_code', trim((string) $row['MaSV']))->first();
                if (! $student || $student->status !== 'graduated') {
                    throw new \InvalidArgumentException('Sinh viên chưa đủ điều kiện cấp bằng');
                }
                $decision = trim((string) ($row['SoQuyetDinh'] ?? ''));
                if ($type !== 'chung_chi' && $decision === '') {
                    throw new \InvalidArgumentException('Thiếu số quyết định');
                }
                $cohort = trim((string) $row['MaKhoa']);
                if (! preg_match('/^[A-Za-z0-9-]+$/', $cohort) || ($decision !== '' && ! preg_match('/^[A-Za-z0-9-]+$/', $decision))) {
                    throw new \InvalidArgumentException('Mã khóa hoặc số quyết định không hợp lệ');
                }
                app(DiplomaIssuanceService::class)->request(['student_id' => $student->id, 'diploma_type' => $type, 'serial_number' => trim((string) $row['SoPhoi']), 'cohort_code' => $cohort, 'decision_code' => $decision ?: null, 'issue_date' => $row['NgayCap']], $this->userId ?? auth()->id());
                $this->imported++;
            } catch (\Throwable $exception) {
                $this->errors[] = 'Dòng '.$line.': '.$exception->getMessage();
            }
        }
    }
}
