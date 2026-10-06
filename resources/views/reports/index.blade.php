<x-app-layout>
    <x-slot name="header">
        <div class="academic-heading">
            <div><p class="academic-eyebrow">Thống kê và tổng hợp</p><h1>Báo cáo đào tạo</h1><p>Số liệu tổng hợp, chi tiết và lịch sử xuất báo cáo.</p></div>
            <a class="report-history-link" href="#report-history">Lịch sử xuất báo cáo <span aria-hidden="true">&darr;</span></a>
        </div>
    </x-slot>
    <div class="academic-page reports-page app-shell">
        @php
            $degreeLabels = ['dai_hoc'=>'Đại học','thac_si'=>'Thạc sĩ','tien_si'=>'Tiến sĩ'];
            $annualReport = in_array($type, ['academic_year_summary', 'annual_blanks'], true);
            $supportsProgramFilters = !in_array($type, ['academic_year_summary', 'annual_blanks', 'blanks'], true);
            $year = $filters['academic_year'] ?? null;
            $scopeNotes = [
                'academic' => 'Cảnh báo phát sinh theo năm, ngành và trình độ đang chọn.',
                'iso' => 'Đợt rà soát theo năm bắt đầu, ngành và trình độ đang chọn.',
                'programs' => 'Chương trình theo năm ban hành quyết định, ngành và trình độ đang chọn.',
                'plo' => 'Các liên kết giữa học phần và chuẩn đầu ra; không áp dụng bộ lọc năm.',
                'diploma' => 'Văn bằng, chứng chỉ theo năm cấp, ngành và trình độ đang chọn.',
                'blanks' => 'Phôi theo năm nhập kho; không áp dụng bộ lọc ngành và trình độ.',
                'academic_year_summary' => 'Tổng hợp toàn hệ thống từ 01/09/'.($year - 1).' đến 31/08/'.$year.'.',
                'annual_blanks' => 'Phôi nhập đến 31/12/'.$year.', thống kê theo trạng thái hiện tại trên toàn hệ thống.',
            ];
        @endphp
        <p class="report-type-label">Loại báo cáo</p>
        <nav class="report-tabs" aria-label="Loại báo cáo">
            @foreach($reportLabels as $reportType => $title)
                <a href="{{ route('reports.index', ['type'=>$reportType] + request()->except(['type','report_page','history_page'])) }}" @if($type === $reportType) aria-current="page" @endif><span>{{ $title }}</span><span class="report-type-indicator" aria-hidden="true">{{ $type === $reportType ? '✓' : '→' }}</span></a>
            @endforeach
        </nav>

        <form method="GET" action="{{ route('reports.index') }}" class="academic-filters report-filters" aria-label="Bộ lọc báo cáo">
            <input type="hidden" name="type" value="{{ $type }}">
            <div class="academic-filter-grid">
                <div class="academic-filter"><label for="report-year">{{ $type === 'academic_year_summary' ? 'Năm kết thúc năm học' : 'Năm báo cáo' }}</label><input id="report-year" type="number" name="academic_year" min="2000" max="2100" value="{{ $year }}" placeholder="Tất cả năm" @disabled($type === 'plo') @required($annualReport)></div>
                <div class="academic-filter"><label for="report-program">Ngành đào tạo</label><select id="report-program" name="program_id" @disabled(!$supportsProgramFilters)><option value="">Tất cả ngành</option>@foreach($programs as $program)<option value="{{ $program->id }}" @selected(($filters['program_id'] ?? null) == $program->id)>{{ $program->name_vi }}</option>@endforeach</select></div>
                <div class="academic-filter"><label for="report-degree">Trình độ</label><select id="report-degree" name="degree_level" @disabled(!$supportsProgramFilters)><option value="">Tất cả trình độ</option>@foreach($degreeLabels as $value => $label)<option value="{{ $value }}" @selected(($filters['degree_level'] ?? null) === $value)>{{ $label }}</option>@endforeach</select></div>
                <div class="report-filter-actions"><button type="submit" class="academic-button academic-button-primary">Xem báo cáo</button><a href="{{ route('reports.index', ['type'=>$type]) }}" class="academic-button academic-button-secondary">Đặt lại</a></div>
            </div>
        </form>

        <section aria-labelledby="report-preview-title">
            <div class="academic-section-heading report-title-row">
                <div><h2 id="report-preview-title">{{ $reportLabels[$type] }}</h2><p class="report-scope-note">{{ $scopeNotes[$type] }}</p></div>
                @canany(['reports.export','dashboard.view'])
                    <details class="academic-export">
                        <summary>Xuất báo cáo</summary>
                        <div class="academic-export-options">
                            @foreach(['csv'=>'Dữ liệu CSV','xlsx'=>'Bảng tính Excel','pdf'=>'Tệp PDF'] as $format => $label)<a href="{{ route('reports.export', [$type, $format] + $filters) }}">{{ $label }}</a>@endforeach
                        </div>
                    </details>
                @endcanany
            </div>
            <div class="report-scope" role="status">
                <span>{{ !$supportsProgramFilters ? 'Toàn hệ thống' : (($filters['program_id'] ?? null) ? ($programs->firstWhere('id', $filters['program_id'])?->name_vi ?? 'Ngành đã chọn') : 'Tất cả ngành') }}</span>
                @if($supportsProgramFilters)<span>{{ $degreeLabels[$filters['degree_level'] ?? ''] ?? 'Tất cả trình độ' }}</span>@endif
                @if($type !== 'plo')<span>{{ $type === 'academic_year_summary' ? 'Năm học '.($year - 1).'-'.$year : ($year ? 'Năm '.$year : 'Tất cả năm') }}</span>@endif
                <span>Dữ liệu hiện tại</span>
            </div>
            <dl class="academic-counts report-metrics">
                @foreach($metrics as $label => $value)<div class="academic-count"><dt>{{ $label }}</dt><dd>{{ number_format($value) }}</dd></div>@endforeach
            </dl>
            <div class="academic-section-heading report-detail-heading"><h3>Chi tiết báo cáo</h3><span>{{ $preview->total() }} dòng @if($preview->total()) &middot; {{ $preview->firstItem() }}&ndash;{{ $preview->lastItem() }} / {{ $preview->total() }}@endif</span></div>
            <div class="report-table-wrap">
                <table class="report-table">
                    <caption class="sr-only">{{ $reportLabels[$type] }} theo bộ lọc đang chọn</caption>
                    <thead><tr>@foreach($columns as $label)<th scope="col">{{ $label }}</th>@endforeach</tr></thead>
                    <tbody>
                        @forelse($preview as $row)
                            <tr>@foreach($columns as $key => $label)<td data-label="{{ $label }}" @class(['report-cell-number'=>is_numeric($row[$key] ?? null), 'report-cell-description'=>in_array($key, ['LyDo','MoTa','Ten','HoTen','SinhVien','HocPhan'], true)])>{{ $row[$key] ?? 'Chưa ghi nhận' }}</td>@endforeach</tr>
                        @empty
                            <tr><td colspan="{{ count($columns) }}" class="report-empty">Không có dữ liệu báo cáo trong phạm vi đã chọn.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="academic-pagination">{{ $preview->links() }}</div>
            @if($type === 'plo')<p class="report-data-note">Chuẩn đầu ra chưa liên kết học phần không xuất hiện trong bảng mức đáp ứng này.</p>@endif
            @if($annualReport)<p class="report-data-note">Số liệu trên trang được tính từ dữ liệu hiện tại. Bản PDF đã lưu trong lịch sử giữ nguyên số liệu tại thời điểm tạo.</p>@endif
        </section>

        <section id="report-history" class="report-history" aria-labelledby="report-history-title">
            <div class="academic-section-heading"><h2 id="report-history-title">Lịch sử xuất báo cáo</h2><span>{{ auth()->user()->can('dashboard.view') ? 'Toàn hệ thống' : 'Báo cáo của bạn' }}</span></div>
            <div class="report-table-wrap">
                <table class="report-table report-history-table">
                    <caption class="sr-only">Các báo cáo đã xuất và bản lưu</caption>
                    <thead><tr><th scope="col">Loại báo cáo</th><th scope="col">Định dạng</th><th scope="col">Phạm vi</th><th scope="col">Số dòng</th><th scope="col">Người xuất</th><th scope="col">Thời điểm</th><th scope="col">Thao tác</th></tr></thead>
                    <tbody>
                        @forelse($history as $item)
                            <tr>
                                <td class="p-3">{{ $reportLabels[$item->report_type] ?? $item->report_type }}</td>
                                <td class="p-3">{{ ['csv'=>'Dữ liệu CSV','xlsx'=>'Bảng tính Excel','pdf'=>'Tệp PDF'][$item->format] ?? strtoupper($item->format) }}</td>
                                <td class="p-3" data-label="Phạm vi">{{ collect($item->filters ?? [])->map(fn ($value, $key) => (['academic_year'=>'Năm','program_id'=>'Ngành','degree_level'=>'Trình độ'][$key] ?? $key).': '.($key === 'program_id' ? ($programs->firstWhere('id', $value)?->name_vi ?? $value) : ($key === 'degree_level' ? ($degreeLabels[$value] ?? $value) : $value)))->join(', ') ?: 'Không lọc' }}</td>
                                <td class="p-3" data-label="Số dòng">{{ number_format($item->row_count) }}</td>
                                <td class="p-3" data-label="Người xuất">{{ $item->user?->name ?: 'Hệ thống' }}</td>
                                <td class="p-3" data-label="Thời điểm">{{ $item->created_at->format('d/m/Y H:i') }}</td>
                                <td class="p-3" data-label="Thao tác">
                                    <a class="report-action-link" href="{{ route('reports.index', ['type'=>$item->report_type] + ($item->filters ?? []) + ['academic_year'=>'']) }}">Xem số liệu hiện tại</a>
                                    @canany(['reports.export','dashboard.view'])<a class="report-action-link" href="{{ route('reports.reload', $item) }}">{{ $item->file_path ? 'Tải bản lưu' : 'Xuất lại' }}</a>@endcanany
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="report-empty">Chưa có báo cáo đã xuất.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="academic-pagination">{{ $history->links() }}</div>
        </section>
    </div>
</x-app-layout>
