<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class GenericReportExport implements FromCollection, WithHeadings
{
    public function __construct(private array $rows) {}

    public function collection(): Collection
    {
        return collect($this->rows)->map(fn ($row) => array_values($row));
    }

    public function headings(): array
    {
        return array_keys($this->rows[0] ?? ['message' => 'Không có dữ liệu']);
    }
}
