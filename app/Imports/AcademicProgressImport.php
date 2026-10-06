<?php

namespace App\Imports;

use App\Models\Program;
use App\Models\Student;
use App\Models\StudentProgress;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class AcademicProgressImport implements ToCollection, WithChunkReading, WithHeadingRow
{
    public int $imported = 0;

    public array $errors = [];

    private int $line = 2;

    public function chunkSize(): int
    {
        return 500;
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $line = $this->line++;
            try {
                $required = ['MaSV', 'HoTen', 'MaNganh', 'HocKy', 'TinChiTichLuy', 'GPA', 'TinChiYeuCau'];
                foreach ($required as $column) {
                    if (! isset($row[$column]) || trim((string) $row[$column]) === '') {
                        throw new \InvalidArgumentException("Thiếu cột {$column}");
                    }
                }$gpa = (float) $row['GPA'];
                $credits = (int) $row['TinChiTichLuy'];
                $requiredCredits = (int) $row['TinChiYeuCau'];
                if ($gpa < 0 || $gpa > 4) {
                    throw new \InvalidArgumentException('Điểm trung bình hệ 4 phải từ 0 đến 4');
                }if ($credits < 0 || $requiredCredits < 0) {
                    throw new \InvalidArgumentException('Tín chỉ không được âm');
                }$program = Program::where('code', trim((string) $row['MaNganh']))->first();
                if (! $program) {
                    throw new \InvalidArgumentException('Không tìm thấy mã ngành '.trim((string) $row['MaNganh']));
                }$student = Student::updateOrCreate(['student_code' => trim((string) $row['MaSV'])], ['full_name' => trim((string) $row['HoTen']), 'program_id' => $program->id, 'degree_level' => $program->degree_level]);
                StudentProgress::updateOrCreate(['student_id' => $student->id, 'semester' => trim((string) $row['HocKy'])], ['credits_accumulated' => $credits, 'gpa_4' => $gpa, 'credits_required' => $requiredCredits, 'recorded_at' => now()]);
                $this->imported++;
            } catch (\Throwable $e) {
                $this->errors[] = 'Dòng '.$line.': '.$e->getMessage();
            }
        }
    }
}
