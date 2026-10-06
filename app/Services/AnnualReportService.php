<?php

namespace App\Services;

use App\Models\DiplomaBlank;
use App\Models\ReportHistory;
use App\Models\Student;
use App\Models\WarningLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class AnnualReportService
{
    public function rows(string $type, int $year): array
    {
        return match ($type) {
            'academic_year_summary' => $this->academicRows($year),
            'annual_blanks' => $this->blankRows($year),
            default => throw new \InvalidArgumentException('Loại báo cáo năm không hợp lệ.'),
        };
    }

    public function generate(string $type, int $year): ReportHistory
    {
        $existing = ReportHistory::where('report_type', $type)->where('format', 'pdf')->whereNull('user_id')->get()
            ->first(fn ($history) => (int) ($history->filters['academic_year'] ?? 0) === $year);
        if ($existing?->file_path && Storage::disk('local')->exists($existing->file_path)) {
            return $existing;
        }
        $rows = $this->rows($type, $year);
        $title = $type === 'academic_year_summary' ? 'Tổng kết năm học '.($year - 1).'-'.$year : 'Báo cáo phôi văn bằng ngày 31/12/'.$year;
        $path = 'annual-reports/'.$type.'-'.$year.'.pdf';
        Storage::disk('local')->put($path, Pdf::loadView('reports.pdf', compact('title', 'rows'))->output());
        if ($existing) {
            $existing->update(['row_count' => count($rows), 'file_path' => $path]);

            return $existing;
        }

        return ReportHistory::create(['user_id' => null, 'report_type' => $type, 'format' => 'pdf', 'row_count' => count($rows), 'filters' => ['academic_year' => $year], 'file_path' => $path]);
    }

    private function academicRows(int $year): array
    {
        $start = ($year - 1).'-09-01';
        $end = $year.'-08-31';
        $rows = [];
        foreach (['dai_hoc' => 'Đại học', 'thac_si' => 'Thạc sĩ', 'tien_si' => 'Tiến sĩ'] as $degree => $label) {
            $cohort = Student::where('degree_level', $degree);
            $due = (clone $cohort)->whereDate('expected_graduation_date', '>=', $start)->whereDate('expected_graduation_date', '<=', $end);
            $eligible = (clone $due)->count();
            $onTime = (clone $due)->where('status', 'graduated')->whereNotNull('graduated_at')->whereColumn('graduated_at', '<=', 'expected_graduation_date')->count();
            $population = (clone $cohort)->where('enrollment_year', '<=', $year)->count();
            $withdrawn = (clone $cohort)->whereDate('withdrawn_at', '>=', $start)->whereDate('withdrawn_at', '<=', $end)->count();
            $warnings = WarningLog::whereHas('student', fn ($query) => $query->where('degree_level', $degree))->whereBetween('issued_at', [$start, $end.' 23:59:59'])->count();
            $rows[] = ['TrinhDo' => $label, 'DenHanTotNghiep' => $eligible, 'DungHan' => $onTime, 'TyLeDungHan' => $eligible ? round($onTime / $eligible * 100, 1).'%' : 'Chưa đủ dữ liệu', 'QuyMo' => $population, 'ThoiHoc' => $withdrawn, 'TyLeThoiHoc' => $population ? round($withdrawn / $population * 100, 1).'%' : 'Chưa đủ dữ liệu', 'CanhBao' => $warnings];
        }

        return $rows;
    }

    private function blankRows(int $year): array
    {
        $rows = [];
        foreach (['dai_hoc' => 'Đại học', 'thac_si' => 'Thạc sĩ', 'tien_si' => 'Tiến sĩ', 'chung_chi' => 'Chứng chỉ'] as $type => $label) {
            $blanks = DiplomaBlank::where('type', $type)->whereDate('import_date', '<=', $year.'-12-31');
            $rows[] = ['Loai' => $label, 'TongPhoi' => (clone $blanks)->count(), 'DaCap' => (clone $blanks)->where('status', 'used')->count(), 'Hong' => (clone $blanks)->where('status', 'damaged')->count(), 'DaHuy' => (clone $blanks)->where('status', 'destroyed')->count(), 'TonKho' => (clone $blanks)->where('status', 'in_stock')->count()];
        }

        return $rows;
    }
}
