<?php

namespace App\Imports;

use App\Models\Program;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class StudentImport implements ToCollection, WithChunkReading, WithHeadingRow
{
    public int $imported = 0;

    /** @var array<int, string> */
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
                $code = trim((string) ($row['MaSV'] ?? ''));
                $name = trim((string) ($row['HoTen'] ?? ''));
                if ($code === '' || $name === '') {
                    throw new \InvalidArgumentException('Bắt buộc có MaSV và HoTen.');
                }

                $program = null;
                $programCode = trim((string) ($row['MaNganh'] ?? ''));
                if ($programCode !== '') {
                    $program = Program::where('code', $programCode)->first();
                    if ($program === null) {
                        throw new \InvalidArgumentException('Không tìm thấy mã ngành '.$programCode.'.');
                    }
                }

                $degreeInput = trim((string) ($row['TrinhDo'] ?? ''));
                $degree = [
                    'Đại học' => 'dai_hoc', 'Dai hoc' => 'dai_hoc',
                    'Thạc sĩ' => 'thac_si', 'Thac si' => 'thac_si',
                    'Tiến sĩ' => 'tien_si', 'Tien si' => 'tien_si',
                ][$degreeInput] ?? ($degreeInput !== '' ? $degreeInput : ($program?->degree_level ?? 'dai_hoc'));
                if (! in_array($degree, ['dai_hoc', 'thac_si', 'tien_si'], true)) {
                    throw new \InvalidArgumentException('TrinhDo phải là dai_hoc, thac_si hoặc tien_si.');
                }

                $statusInput = trim((string) ($row['TrangThai'] ?? ''));
                $status = ['Đang học' => 'active', 'Da tot nghiep' => 'graduated', 'Đã tốt nghiệp' => 'graduated', 'Thôi học' => 'withdrawn'][$statusInput] ?? ($statusInput !== '' ? $statusInput : 'active');
                if (! in_array($status, ['active', 'graduated', 'withdrawn'], true)) {
                    throw new \InvalidArgumentException('TrangThai không hợp lệ.');
                }

                $student = Student::firstOrNew(['student_code' => $code]);
                $student->full_name = $name;
                if ($programCode !== '') {
                    $student->program_id = $program?->id;
                }
                if ($degreeInput !== '' || ! $student->exists) {
                    $student->degree_level = $degree;
                }
                if ($statusInput !== '' || ! $student->exists) {
                    $student->status = $status;
                }
                foreach (['date_of_birth' => $this->date($row['NgaySinh'] ?? null), 'class_code' => $this->nullableString($row['Lop'] ?? null), 'faculty_name' => $this->nullableString($row['Khoa'] ?? null), 'enrollment_year' => $this->nullableInteger($row['NamNhapHoc'] ?? null), 'email' => $this->nullableString($row['Email'] ?? null)] as $field => $value) {
                    if ($value !== null || ! $student->exists) {
                        $student->{$field} = $value;
                    }
                }
                $student->save();
                $this->imported++;
            } catch (\Throwable $exception) {
                $this->errors[] = 'Dòng '.$line.': '.$exception->getMessage();
            }
        }
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableInteger(mixed $value): ?int
    {
        return $this->nullableString($value) === null ? null : (int) $value;
    }

    private function date(mixed $value): ?string
    {
        if ($this->nullableString($value) === null) {
            return null;
        }
        if (is_numeric($value)) {
            return Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString();
        }

        return Carbon::parse(str_replace('/', '-', (string) $value))->toDateString();
    }
}
