<?php

namespace App\Http\Controllers;

use App\Exports\GenericReportExport;
use App\Models\DiplomaBlank;
use App\Models\DiplomaLedger;
use App\Models\IsoWorkflow;
use App\Models\Plo;
use App\Models\Program;
use App\Models\ReportHistory;
use App\Models\WarningLog;
use App\Services\AnnualReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function __construct(private AnnualReportService $annualReports) {}

    public function index(Request $request): View
    {
        $reportLabels = [
            'academic' => 'Cảnh báo học vụ', 'iso' => 'Tiến độ rà soát',
            'programs' => 'Chương trình đào tạo', 'plo' => 'Chuẩn đầu ra',
            'diploma' => 'Văn bằng, chứng chỉ', 'blanks' => 'Kho phôi',
            'academic_year_summary' => 'Tổng kết năm học', 'annual_blanks' => 'Tình hình phôi cuối năm',
        ];
        $request->validate([
            'type' => ['nullable', 'in:'.implode(',', array_keys($reportLabels))],
            'report_page' => ['nullable', 'integer', 'min:1'],
        ]);
        $type = $request->input('type') ?: 'academic';
        $filters = $this->filters($request);
        if (! $request->has('academic_year')) {
            $filters['academic_year'] = now()->year;
        }
        $filters = $this->reportFilters($type, $filters);
        $rows = $this->rows($type, $filters);
        $columns = $this->columns($type);
        $page = LengthAwarePaginator::resolveCurrentPage('report_page');
        $preview = new LengthAwarePaginator(
            array_map(fn ($row) => $this->displayRow($type, $row), array_slice($rows, ($page - 1) * 25, 25)),
            count($rows), 25, $page,
            ['path' => route('reports.index'), 'pageName' => 'report_page', 'query' => ['type' => $type] + $filters],
        );
        $history = ReportHistory::with('user')->latest()
            ->when(! $request->user()->can('dashboard.view'), fn ($query) => $query->where('user_id', $request->user()->id))
            ->paginate(10, ['*'], 'history_page')->withQueryString();

        return view('reports.index', [
            'history' => $history,
            'programs' => Program::orderBy('name_vi')->get(),
            'reportLabels' => $reportLabels, 'type' => $type, 'filters' => $filters,
            'columns' => $columns, 'preview' => $preview,
            'metrics' => $this->metrics($type, $rows),
        ]);
    }

    public function export(Request $request, string $type, string $format)
    {
        abort_unless(in_array($type, ['academic', 'iso', 'programs', 'plo', 'diploma', 'blanks', 'academic_year_summary', 'annual_blanks'], true), 404);
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 404);
        $filters = $this->reportFilters($type, $this->filters($request));
        $rows = $this->rows($type, $filters);
        $history = ReportHistory::create(['user_id' => auth()->id(), 'report_type' => $type, 'format' => $format, 'row_count' => count($rows), 'filters' => $filters]);

        return $this->download($type, $format, $rows, $history->id);
    }

    public function reload(ReportHistory $history)
    {
        abort_unless($history->user_id === auth()->id() || auth()->user()->can('dashboard.view'), 403);
        if ($history->file_path) {
            abort_unless(Storage::disk('local')->exists($history->file_path), 404);

            return Storage::disk('local')->download($history->file_path);
        }

        return $this->download($history->report_type, $history->format, $this->rows($history->report_type, $history->filters ?? []), $history->id);
    }

    private function download(string $type, string $format, array $rows, int $historyId)
    {
        $name = $type.'-report-'.$historyId.'-'.now()->format('YmdHis');
        if ($format === 'csv') {
            $stream = fopen('php://temp', 'w+');
            fputcsv($stream, array_keys($rows[0] ?? ['message' => 'Không có dữ liệu']));
            foreach ($rows as $row) {
                fputcsv($stream, $row);
            }
            rewind($stream);

            return response(stream_get_contents($stream), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="'.$name.'.csv"']);
        }
        if ($format === 'pdf') {
            return Pdf::loadView('reports.pdf', ['title' => $this->title($type), 'rows' => $rows])->download($name.'.pdf');
        }

        return Excel::download(new GenericReportExport($rows), $name.'.xlsx');
    }

    private function filters(Request $request): array
    {
        $request->validate(['academic_year' => ['nullable', 'integer', 'between:2000,2100'], 'program_id' => ['nullable', 'integer', 'exists:programs,id'], 'degree_level' => ['nullable', 'in:dai_hoc,thac_si,tien_si']]);

        return array_filter(['academic_year' => $request->input('academic_year'), 'program_id' => $request->input('program_id'), 'degree_level' => $request->input('degree_level')], fn ($value) => $value !== null && $value !== '');
    }

    private function rows(string $type, array $filters): array
    {
        return match ($type) {
            'academic_year_summary', 'annual_blanks' => $this->annualReports->rows($type, (int) ($filters['academic_year'] ?? now()->year)),
            'academic' => WarningLog::with(['student.program', 'progress'])->whereHas('student', function ($q) use ($filters) {
                $q->when($filters['program_id'] ?? null, fn ($x, $id) => $x->where('program_id', $id))->when($filters['degree_level'] ?? null, fn ($x, $level) => $x->where('degree_level', $level));
            })->when($filters['academic_year'] ?? null, fn ($q, $year) => $q->whereYear('issued_at', $year))->latest('issued_at')->get()->map(fn ($w) => ['MaSV' => $w->student->student_code, 'HoTen' => $w->student->full_name, 'Nganh' => $w->student->program?->name_vi, 'HocKy' => $w->progress?->semester, 'Loai' => $w->warning_type, 'LyDo' => $w->reason, 'TrangThai' => $w->status])->all(),
            'iso' => IsoWorkflow::with('program')->when($filters['program_id'] ?? null, fn ($q, $id) => $q->where('program_id', $id))->when($filters['degree_level'] ?? null, fn ($q, $level) => $q->whereHas('program', fn ($program) => $program->where('degree_level', $level)))->when($filters['academic_year'] ?? null, fn ($q, $year) => $q->whereYear('start_date', $year))->get()->map(fn ($w) => ['Dot' => $w->title, 'CTDT' => $w->program->name_vi, 'TrinhDo' => $w->program->degree_level, 'TrangThai' => $w->status, 'Han' => $w->expected_end_date->format('Y-m-d')])->all(),
            'programs' => Program::withCount('courses')->with('plos')->when($filters['program_id'] ?? null, fn ($q, $id) => $q->whereKey($id))->when($filters['degree_level'] ?? null, fn ($q, $level) => $q->where('degree_level', $level))->when($filters['academic_year'] ?? null, fn ($q, $year) => $q->whereYear('decision_date', $year))->get()->map(fn ($p) => ['Ma' => $p->code, 'Ten' => $p->name_vi, 'TrinhDo' => $p->degree_level, 'NamHoc' => $p->decision_date?->format('Y'), 'TinChi' => $p->total_credits, 'HocPhan' => $p->courses_count, 'PLO' => $p->plos->count()])->all(),
            'plo' => Plo::with(['program', 'matrix.course'])->when($filters['program_id'] ?? null, fn ($q, $id) => $q->where('program_id', $id))->when($filters['degree_level'] ?? null, fn ($q, $level) => $q->whereHas('program', fn ($x) => $x->where('degree_level', $level)))->get()->flatMap(fn ($plo) => $plo->matrix->map(fn ($matrix) => ['CTDT' => $plo->program->code, 'PLO' => $plo->code, 'MoTa' => $plo->description_vi, 'MaHocPhan' => $matrix->course?->course_code, 'HocPhan' => $matrix->course?->name_vi, 'MucDongGop' => $matrix->contribution_level]))->values()->all(),
            'diploma' => DiplomaLedger::with(['student.program'])->when($filters['program_id'] ?? null, fn ($q, $id) => $q->whereHas('student', fn ($x) => $x->where('program_id', $id)))->when($filters['degree_level'] ?? null, fn ($q, $level) => $q->whereHas('student', fn ($x) => $x->where('degree_level', $level)))->when($filters['academic_year'] ?? null, fn ($q, $year) => $q->whereYear('issue_date', $year))->latest('issue_date')->get()->map(fn ($d) => ['SoVaoSo' => $d->reg_number, 'SinhVien' => $d->student->full_name, 'Nganh' => $d->student->program?->name_vi, 'Loai' => $d->diploma_type, 'SoPhoi' => $d->serial_number, 'NgayCap' => $d->issue_date->format('Y-m-d'), 'TrangThai' => $d->status])->all(),
            'blanks' => DiplomaBlank::when($filters['academic_year'] ?? null, fn ($q, $year) => $q->whereYear('import_date', $year))->get()->map(fn ($b) => ['SoPhoi' => $b->serial_number, 'Loai' => $b->type, 'NgayNhap' => $b->import_date->format('Y-m-d'), 'TrangThai' => $b->status, 'GhiChu' => $b->note])->all(),
        };
    }

    private function reportFilters(string $type, array $filters): array
    {
        $keys = match ($type) {
            'academic_year_summary', 'annual_blanks', 'blanks' => ['academic_year'],
            'plo' => ['program_id', 'degree_level'],
            default => ['academic_year', 'program_id', 'degree_level'],
        };
        $filters = array_intersect_key($filters, array_flip($keys));
        if (in_array($type, ['academic_year_summary', 'annual_blanks'], true)) {
            $filters['academic_year'] ??= now()->year;
        }

        return $filters;
    }

    private function columns(string $type): array
    {
        return match ($type) {
            'academic' => ['MaSV' => 'Mã sinh viên', 'HoTen' => 'Họ tên', 'Nganh' => 'Ngành đào tạo', 'HocKy' => 'Học kỳ', 'Loai' => 'Loại cảnh báo', 'LyDo' => 'Lý do', 'TrangThai' => 'Trạng thái'],
            'iso' => ['Dot' => 'Đợt rà soát', 'CTDT' => 'Chương trình', 'TrinhDo' => 'Trình độ', 'TrangThai' => 'Trạng thái', 'Han' => 'Hạn hoàn thành'],
            'programs' => ['Ma' => 'Mã chương trình', 'Ten' => 'Tên chương trình', 'TrinhDo' => 'Trình độ', 'NamHoc' => 'Năm quyết định', 'TinChi' => 'Tín chỉ', 'HocPhan' => 'Học phần', 'PLO' => 'Chuẩn đầu ra'],
            'plo' => ['CTDT' => 'Mã chương trình', 'PLO' => 'Mã chuẩn đầu ra', 'MoTa' => 'Nội dung chuẩn đầu ra', 'MaHocPhan' => 'Mã học phần', 'HocPhan' => 'Tên học phần', 'MucDongGop' => 'Mức đóng góp'],
            'diploma' => ['SoVaoSo' => 'Số vào sổ', 'SinhVien' => 'Sinh viên', 'Nganh' => 'Ngành đào tạo', 'Loai' => 'Loại văn bằng', 'SoPhoi' => 'Số phôi', 'NgayCap' => 'Ngày cấp', 'TrangThai' => 'Trạng thái'],
            'blanks' => ['SoPhoi' => 'Số phôi', 'Loai' => 'Loại phôi', 'NgayNhap' => 'Ngày nhập', 'TrangThai' => 'Trạng thái', 'GhiChu' => 'Ghi chú'],
            'academic_year_summary' => ['TrinhDo' => 'Trình độ', 'DenHanTotNghiep' => 'Đến hạn tốt nghiệp', 'DungHan' => 'Tốt nghiệp đúng hạn', 'TyLeDungHan' => 'Tỷ lệ đúng hạn', 'QuyMo' => 'Quy mô sinh viên', 'ThoiHoc' => 'Thôi học', 'TyLeThoiHoc' => 'Tỷ lệ thôi học', 'CanhBao' => 'Cảnh báo'],
            'annual_blanks' => ['Loai' => 'Loại phôi', 'TongPhoi' => 'Tổng phôi', 'DaCap' => 'Đã cấp', 'Hong' => 'Hỏng', 'DaHuy' => 'Đã hủy', 'TonKho' => 'Tồn kho'],
        };
    }

    private function displayRow(string $type, array $row): array
    {
        $degrees = ['dai_hoc' => 'Đại học', 'thac_si' => 'Thạc sĩ', 'tien_si' => 'Tiến sĩ', 'chung_chi' => 'Chứng chỉ'];
        $statuses = match ($type) {
            'academic' => ['open' => 'Chưa xử lý', 'processing' => 'Đang xử lý', 'resolved' => 'Đã xử lý', 'dismissed' => 'Đã bỏ qua'],
            'iso' => ['draft' => 'Bản nháp', 'in_progress' => 'Đang thực hiện', 'completed' => 'Hoàn thành', 'cancelled' => 'Đã hủy'],
            'blanks' => ['in_stock' => 'Trong kho', 'used' => 'Đã sử dụng', 'damaged' => 'Hỏng', 'destroyed' => 'Đã hủy'],
            default => ['issued' => 'Đã cấp', 'cancelled' => 'Đã hủy', 'revoked' => 'Đã thu hồi'],
        };
        foreach ($row as $key => $value) {
            if ($key === 'TrangThai') {
                $value = $statuses[$value] ?? $value;
            } elseif ($key === 'TrinhDo' || ($key === 'Loai' && $type !== 'academic')) {
                $value = $degrees[$value] ?? $value;
            } elseif ($key === 'Loai' && $type === 'academic') {
                $value = config('academic.warning_labels')[$value] ?? $value;
            } elseif ($key === 'LyDo') {
                $value = str_replace('GPA', 'Điểm trung bình hệ 4', $value);
            } elseif (in_array($key, ['Han', 'NgayCap', 'NgayNhap'], true) && $value) {
                $value = Carbon::parse($value)->format('d/m/Y');
            }
            $row[$key] = $value === null || $value === '' ? 'Chưa ghi nhận' : $value;
        }

        return $row;
    }

    private function metrics(string $type, array $rows): array
    {
        $data = collect($rows);

        return match ($type) {
            'academic' => ['Tổng cảnh báo' => $data->count(), 'Chưa xử lý' => $data->where('TrangThai', 'open')->count(), 'Đang xử lý' => $data->where('TrangThai', 'processing')->count(), 'Đã xử lý' => $data->where('TrangThai', 'resolved')->count()],
            'iso' => ['Đợt rà soát' => $data->count(), 'Đang thực hiện' => $data->where('TrangThai', 'in_progress')->count(), 'Hoàn thành' => $data->where('TrangThai', 'completed')->count(), 'Đã hủy' => $data->where('TrangThai', 'cancelled')->count()],
            'programs' => ['Chương trình' => $data->count(), 'Tín chỉ khai báo' => $data->sum('TinChi'), 'Lượt học phần' => $data->sum('HocPhan'), 'Chuẩn đầu ra' => $data->sum('PLO')],
            'plo' => ['Mức đáp ứng đã ghi nhận' => $data->count(), 'Chương trình' => $data->pluck('CTDT')->unique()->count(), 'Chuẩn đầu ra có liên kết' => $data->map(fn ($row) => $row['CTDT'].'/'.$row['PLO'])->unique()->count(), 'Học phần có liên kết' => $data->pluck('MaHocPhan')->filter()->unique()->count()],
            'diploma' => ['Văn bằng, chứng chỉ' => $data->count(), 'Đã cấp' => $data->where('TrangThai', 'issued')->count(), 'Đã hủy' => $data->where('TrangThai', 'cancelled')->count(), 'Đã thu hồi' => $data->where('TrangThai', 'revoked')->count()],
            'blanks' => ['Tổng phôi' => $data->count(), 'Trong kho' => $data->where('TrangThai', 'in_stock')->count(), 'Đã sử dụng' => $data->where('TrangThai', 'used')->count(), 'Hỏng hoặc đã hủy' => $data->whereIn('TrangThai', ['damaged', 'destroyed'])->count()],
            'academic_year_summary' => ['Quy mô sinh viên' => $data->sum('QuyMo'), 'Đến hạn tốt nghiệp' => $data->sum('DenHanTotNghiep'), 'Tốt nghiệp đúng hạn' => $data->sum('DungHan'), 'Cảnh báo trong năm học' => $data->sum('CanhBao')],
            'annual_blanks' => ['Tổng phôi' => $data->sum('TongPhoi'), 'Đã cấp' => $data->sum('DaCap'), 'Tồn kho' => $data->sum('TonKho'), 'Hỏng hoặc đã hủy' => $data->sum('Hong') + $data->sum('DaHuy')],
        };
    }

    private function title(string $type): string
    {
        return ['academic' => 'Báo cáo chi tiết học vụ', 'iso' => 'Báo cáo tiến độ rà soát', 'programs' => 'Báo cáo chương trình đào tạo', 'plo' => 'Báo cáo chuẩn đầu ra', 'diploma' => 'Báo cáo văn bằng chứng chỉ', 'blanks' => 'Báo cáo kho phôi', 'academic_year_summary' => 'Tổng kết năm học', 'annual_blanks' => 'Tình hình cấp phát phôi năm'][$type];
    }
}
