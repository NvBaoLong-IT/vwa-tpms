<x-app-layout>
    <x-slot name="header">
        <div class="academic-heading">
            <div><p class="academic-eyebrow">Quản lý đào tạo</p><h1>Chương trình đào tạo</h1><p>Danh mục chương trình, học phần và phiên bản theo khóa.</p></div>
            <div class="academic-heading-links">
                <a href="{{ route('programs.audit') }}">Nhật ký thay đổi <span aria-hidden="true">&rarr;</span></a>
                @can('programs.import')<a href="{{ route('programs.document-imports.index', ['status' => 'submitted']) }}">Bản nhập AI chờ duyệt &rarr;</a><a href="{{ route('programs.imports.index', ['status' => 'submitted']) }}">Bản nhập Excel chờ duyệt &rarr;</a>@endcan @can('courses.view')<a href="{{ route('courses.index') }}">Danh mục học phần <span aria-hidden="true">&rarr;</span></a>@endcan
            </div>
        </div>
    </x-slot>

    <div class="academic-page programs-page app-shell">
        @php
            $degreeLabels = ['dai_hoc'=>'Đại học', 'thac_si'=>'Thạc sĩ', 'tien_si'=>'Tiến sĩ'];
            $hasFilters = request()->filled('q') || request()->filled('degree_level') || request()->filled('is_active') || request()->filled('completeness');
            $exportFilters = request()->except('page');
        @endphp

        <section aria-labelledby="program-summary-title">
            <div class="academic-section-heading"><h2 id="program-summary-title">Tổng quan danh mục</h2><span>Toàn hệ thống</span></div>
            <dl class="academic-counts">
                <div class="academic-count"><dt>Tổng chương trình</dt><dd>{{ number_format($summary['total']) }}</dd></div>
                <div class="academic-count academic-count-resolved"><dt>Đang hoạt động</dt><dd>{{ number_format($summary['active']) }}</dd><a class="program-summary-link" href="{{ route('programs.index', ['is_active'=>1]) }}">Xem danh sách <span aria-hidden="true">&rarr;</span></a></div>
                <div class="academic-count academic-count-processing"><dt>Chưa có học phần</dt><dd>{{ number_format($summary['missing_courses']) }}</dd><a class="program-summary-link" href="{{ route('programs.index', ['completeness'=>'missing_courses']) }}">Rà soát học phần <span aria-hidden="true">&rarr;</span></a></div>
                <div class="academic-count academic-count-open"><dt>Chưa có phiên bản</dt><dd>{{ number_format($summary['missing_versions']) }}</dd><a class="program-summary-link" href="{{ route('programs.index', ['completeness'=>'missing_versions']) }}">Rà soát phiên bản <span aria-hidden="true">&rarr;</span></a></div>
            </dl>
        </section>

        <p><a class="program-summary-link" href="{{ route('programs.index', ['version_status' => 'submitted']) }}">Chương trình có phiên bản chờ phê duyệt: {{ $summary['pending_review'] }} →</a></p>
        <form method="GET" action="{{ route('programs.index') }}" class="academic-filters" aria-label="Bộ lọc chương trình đào tạo">
            <label class="academic-filter">Trạng thái phiên bản<select name="version_status"><option value="">Tất cả</option>@foreach(['draft'=>'Bản nháp','submitted'=>'Chờ phê duyệt','returned'=>'Trả về bổ sung','approved'=>'Đã phê duyệt','published'=>'Đã công bố','archived'=>'Đã thay thế'] as $value => $label)<option value="{{ $value }}" @selected(request('version_status') === $value)>{{ $label }}</option>@endforeach</select></label>
            <div class="academic-filter-grid">
                <div class="academic-filter"><label for="program-search">Tìm chương trình</label><input id="program-search" type="search" name="q" value="{{ request('q') }}" maxlength="120" placeholder="Mã, tên tiếng Việt hoặc tiếng Anh"></div>
                <div class="academic-filter"><label for="program-degree">Trình độ</label><select id="program-degree" name="degree_level"><option value="">Tất cả trình độ</option>@foreach($degreeLabels as $value => $label)<option value="{{ $value }}" @selected(request('degree_level') === $value)>{{ $label }}</option>@endforeach</select></div>
                <div class="academic-filter"><label for="program-status">Trạng thái</label><select id="program-status" name="is_active"><option value="">Tất cả trạng thái</option><option value="1" @selected(request('is_active') === '1')>Đang hoạt động</option><option value="0" @selected(request('is_active') === '0')>Ngừng hoạt động</option></select></div>
                <div class="academic-filter"><label for="program-completeness">Học phần và phiên bản</label><select id="program-completeness" name="completeness"><option value="">Tất cả chương trình</option><option value="missing_courses" @selected(request('completeness') === 'missing_courses')>Chưa có học phần</option><option value="missing_versions" @selected(request('completeness') === 'missing_versions')>Chưa có phiên bản theo khóa</option></select></div>
            </div>
            <div class="program-filter-bottom">
                <div class="academic-filter-actions"><button type="submit" class="academic-button academic-button-primary">Tìm và lọc</button><a href="{{ route('programs.index') }}" class="academic-button academic-button-secondary">Xóa lọc</a></div>
                <div class="program-display-options">
                    <div class="academic-filter"><label for="program-sort">Sắp xếp</label><select id="program-sort" name="sort"><option value="name" @selected(request('sort', 'name') === 'name')>Tên A &ndash; Z</option><option value="code" @selected(request('sort') === 'code')>Mã chương trình</option><option value="newest" @selected(request('sort') === 'newest')>Mới tạo gần nhất</option><option value="updated" @selected(request('sort') === 'updated')>Mới cập nhật gần nhất</option></select></div>
                    <div class="academic-filter"><label for="program-per-page">Số dòng mỗi trang</label><select id="program-per-page" name="per_page">@foreach([15,30,50] as $size)<option value="{{ $size }}" @selected((int) request('per_page', 15) === $size)>{{ $size }} dòng</option>@endforeach</select></div>
                </div>
            </div>
        </form>

        <div class="academic-toolbar">
            <div class="academic-tools">
                @can('programs.create')<a href="{{ route('programs.create') }}" class="academic-button academic-button-primary">Thêm chương trình</a><a href="{{ route('programs.document-imports.new') }}" class="academic-button academic-button-secondary">Tạo mới từ PDF/Word bằng AI</a>@endcan
                @can('programs.import')<a href="{{ route('programs.document-imports.index', ['status' => 'submitted']) }}">Bản nhập AI chờ duyệt &rarr;</a><a href="{{ route('programs.import.form') }}" class="academic-button academic-button-secondary">Nhập từ Excel</a><a href="{{ route('programs.import.template') }}" class="program-tool-link">Tải mẫu nhập Excel</a>@endcan
            </div>
            @can('reports.export')<a href="{{ route('programs.export.csv', $exportFilters) }}" class="academic-button academic-button-secondary">Xuất danh sách CSV</a>@endcan
        </div>

        <section aria-labelledby="program-list-title">
            <div class="academic-section-heading">
                <h2 id="program-list-title">Danh sách chương trình <span class="academic-list-count">{{ number_format($programs->total()) }}</span></h2>
                <span>{{ $hasFilters ? 'Theo bộ lọc đang chọn' : 'Tất cả chương trình' }}@if($programs->total()) &middot; {{ $programs->firstItem() }}&ndash;{{ $programs->lastItem() }} / {{ $programs->total() }}@endif</span>
            </div>
            <div class="academic-table-wrap">
                <table class="academic-table program-table">
                    <caption class="sr-only">Chương trình đào tạo, cấu trúc học phần và phiên bản theo khóa</caption>
                    <thead><tr><th scope="col">Chương trình</th><th scope="col">Cấu trúc đào tạo</th><th scope="col">Quyết định / trạng thái</th><th scope="col">Thao tác</th></tr></thead>
                    <tbody>
                        @forelse($programs as $program)
                            <tr>
                                <td data-label="Chương trình">
                                    <a class="program-name" href="{{ route('programs.show', $program) }}">{{ $program->name_vi }}</a>
                                    <span class="academic-code">{{ $program->code }}</span>
                                    @if($program->name_en)<small lang="en">{{ $program->name_en }}</small>@endif
                                    <div class="program-degree">{{ $degreeLabels[$program->degree_level] ?? $program->degree_level }}@if($program->delivery_mode) &middot; {{ $program->delivery_mode }}@endif</div>
                                </td>
                                <td data-label="Cấu trúc đào tạo">
                                    <strong>{{ number_format($program->total_credits) }} tín chỉ</strong>
                                    <a class="program-structure-link" href="{{ route('programs.show', $program) }}#program-courses">{{ $program->courses_count }} học phần <span aria-hidden="true">&rarr;</span></a>
                                    <a class="program-structure-link" href="{{ route('programs.show', $program) }}#program-versions">{{ $program->versions_count }} phiên bản <span aria-hidden="true">&rarr;</span></a>
                                    @if($program->pending_versions_count)<small class="program-attention">{{ $program->pending_versions_count }} phiên bản chờ phê duyệt</small>@endif
                                    @if(!$program->courses_count || !$program->versions_count)<small class="program-attention">{{ !$program->courses_count ? 'Chưa có học phần' : 'Chưa có phiên bản theo khóa' }}</small>@endif
                                </td>
                                <td data-label="Quyết định / trạng thái">
                                    <span class="academic-status {{ $program->is_active ? 'academic-status-resolved' : '' }}">{{ $program->is_active ? 'Đang hoạt động' : 'Ngừng hoạt động' }}</span>
                                    <p>{{ $program->decision_number ?: 'Chưa có số quyết định' }}</p>
                                    @if($program->decision_date)<small>Ban hành {{ $program->decision_date->format('d/m/Y') }}</small>@endif
                                    <small>Cập nhật {{ $program->updated_at?->format('d/m/Y') }}</small>
                                </td>
                                <td data-label="Thao tác">
                                    <div class="program-row-actions">
                                        <a href="{{ route('programs.show', $program) }}" class="program-view-link">Xem chi tiết <span aria-hidden="true">&rarr;</span></a>
                                        @can('programs.update')<a class="program-edit-link" href="{{ route('programs.edit', $program) }}">Chỉnh sửa</a>@endcan
                                        <details class="program-more">
                                            <summary>Tác vụ khác</summary>
                                            <div class="program-more-links">
                                                <a href="{{ route('programs.plos', $program) }}">Chuẩn đầu ra</a>
                                                <a href="{{ route('programs.matrix', $program) }}">Mức đáp ứng chuẩn đầu ra</a>
                                                <a href="{{ route('programs.audit', ['program_id'=>$program->id]) }}">Nhật ký chương trình</a>
                                            </div>
                                        </details>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="academic-empty"><p>Không có chương trình phù hợp bộ lọc.</p>@if($hasFilters)<a class="program-empty-link" href="{{ route('programs.index') }}">Xóa lọc và xem tất cả chương trình</a>@endif</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="academic-pagination">{{ $programs->links() }}</div>
        </section>
    </div>
</x-app-layout>
