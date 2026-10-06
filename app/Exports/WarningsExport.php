<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class WarningsExport implements FromCollection, WithHeadings
{
    public function __construct(private Collection $warnings) {}

    public function collection(): Collection
    {
        return $this->warnings->map(fn ($w) => [$w->student->student_code, $w->student->full_name, $w->student->program?->name_vi, $w->warning_type, $w->reason, $w->status, $w->issued_at?->format('Y-m-d H:i:s')]);
    }

    public function headings(): array
    {
        return ['MaSV', 'HoTen', 'Nganh', 'LoaiCanhBao', 'LyDo', 'TrangThai', 'ThoiDiem'];
    }
}
