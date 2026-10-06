<x-app-layout>
    <x-slot name="header">
        <div class="academic-heading">
            <div><p class="academic-eyebrow">Quản lý chất lượng đào tạo</p><h1>Rà soát chương trình đào tạo</h1><p>Tiến độ thực hiện, thời hạn và hồ sơ của từng đợt rà soát.</p></div>
            <div class="academic-heading-links"><a href="{{ route('iso.kanban') }}">Xem bảng tiến độ <span aria-hidden="true">&rarr;</span></a></div>
        </div>
    </x-slot>
    <div class="academic-page programs-page iso-page app-shell">
        @php
            $statusLabels = ['draft'=>'Bản nháp','in_progress'=>'Đang thực hiện','completed'=>'Hoàn thành','cancelled'=>'Đã hủy'];
            $typeLabels = ['new'=>'Xây dựng mới','major'=>'Chỉnh sửa lớn','minor'=>'Chỉnh sửa nhỏ'];
            $stepLabels = ['not_started'=>'Chưa bắt đầu','in_progress'=>'Đang thực hiện','waiting_library'=>'Đợi Thư viện','waiting_approval'=>'Đợi phê duyệt','completed'=>'Hoàn thành'];
            $hasFilters = request()->filled('q') || request()->filled('program_id') || request()->filled('status') || request()->filled('type') || request()->boolean('overdue');
        @endphp
        @if(session('success'))<div class="academic-success" role="status">{{ session('success') }}</div>@endif
        <section aria-labelledby="iso-summary-title">
            <div class="academic-section-heading"><h2 id="iso-summary-title">Tổng quan rà soát</h2><span>Toàn hệ thống</span></div>
            <dl class="academic-counts">
                <div class="academic-count"><dt>Tổng đợt rà soát</dt><dd>{{ number_format($summary['total']) }}</dd></div>
                <div class="academic-count academic-count-processing"><dt>Đang thực hiện</dt><dd>{{ number_format($summary['in_progress']) }}</dd><a class="program-summary-link" href="{{ route('iso.index', ['status'=>'in_progress']) }}">Xem danh sách <span aria-hidden="true">&rarr;</span></a></div>
                <div class="academic-count academic-count-resolved"><dt>Hoàn thành</dt><dd>{{ number_format($summary['completed']) }}</dd><a class="program-summary-link" href="{{ route('iso.index', ['status'=>'completed']) }}">Xem đợt hoàn thành <span aria-hidden="true">&rarr;</span></a></div>
                <div class="academic-count academic-count-open"><dt>Đang thực hiện có bước quá hạn</dt><dd>{{ number_format($summary['overdue']) }}</dd><a class="program-summary-link" href="{{ route('iso.index', ['status'=>'in_progress','overdue'=>1]) }}">Xem đợt quá hạn <span aria-hidden="true">&rarr;</span></a></div>
            </dl>
        </section>

        <form method="GET" action="{{ route('iso.index') }}" class="academic-filters" aria-label="Bộ lọc đợt rà soát">
            <div class="academic-filter-grid">
                <div class="academic-filter"><label for="iso-search">Tìm đợt rà soát</label><input id="iso-search" type="search" name="q" maxlength="120" value="{{ request('q') }}" placeholder="Tên đợt, mã hoặc tên chương trình"></div>
                <div class="academic-filter"><label for="iso-program">Chương trình đào tạo</label><select id="iso-program" name="program_id"><option value="">Tất cả chương trình</option>@foreach($programs as $program)<option value="{{ $program->id }}" @selected(request('program_id') == $program->id)>{{ $program->name_vi }}</option>@endforeach</select></div>
                <div class="academic-filter"><label for="iso-status">Trạng thái</label><select id="iso-status" name="status"><option value="">Tất cả trạng thái</option>@foreach($statusLabels as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select></div>
                <div class="academic-filter"><label for="iso-type">Loại rà soát</label><select id="iso-type" name="type"><option value="">Tất cả loại</option>@foreach($typeLabels as $value => $label)<option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>@endforeach</select></div>
            </div>
            <div class="program-filter-bottom">
                <div class="iso-filter-actions">
                    <label class="iso-overdue-toggle"><input type="checkbox" name="overdue" value="1" @checked(request()->boolean('overdue'))> Có bước chưa hoàn thành quá hạn</label>
                    <div class="academic-filter-actions"><button type="submit" class="academic-button academic-button-primary">Tìm và lọc</button><a href="{{ route('iso.index') }}" class="academic-button academic-button-secondary">Xóa lọc</a></div>
                </div>
                <div class="program-display-options">
                    <div class="academic-filter"><label for="iso-sort">Sắp xếp</label><select id="iso-sort" name="sort"><option value="newest" @selected(request('sort', 'newest') === 'newest')>Mới tạo gần nhất</option><option value="deadline" @selected(request('sort') === 'deadline')>Hạn hoàn thành gần nhất</option><option value="updated" @selected(request('sort') === 'updated')>Mới cập nhật gần nhất</option></select></div>
                    <div class="academic-filter"><label for="iso-per-page">Số dòng mỗi trang</label><select id="iso-per-page" name="per_page">@foreach([15,30,50] as $size)<option value="{{ $size }}" @selected((int) request('per_page', 15) === $size)>{{ $size }} dòng</option>@endforeach</select></div>
                </div>
            </div>
        </form>

        <section aria-labelledby="iso-list-title">
            <div class="academic-section-heading">
                <div><h2 id="iso-list-title">Danh sách đợt rà soát <span class="academic-list-count">{{ number_format($workflows->total()) }}</span></h2><p class="course-list-scope">{{ $hasFilters ? 'Theo bộ lọc đang chọn' : 'Tất cả đợt rà soát' }}@if($workflows->total()) &middot; {{ $workflows->firstItem() }}&ndash;{{ $workflows->lastItem() }} / {{ $workflows->total() }}@endif</p></div>
                @can('iso.create')<a class="academic-button academic-button-primary" href="{{ route('iso.create') }}">Tạo đợt rà soát</a>@endcan
            </div>
            <div class="academic-table-wrap">
                <table class="academic-table program-table">
                    <caption class="sr-only">Tiến độ, thời hạn và bước tiếp theo của các đợt rà soát chương trình</caption>
                    <thead><tr><th scope="col">Đợt rà soát</th><th scope="col">Tiến độ</th><th scope="col">Bước tiếp theo</th><th scope="col">Thời hạn / hồ sơ</th></tr></thead>
                    <tbody>
                        @forelse($workflows as $item)
                            @php
                                $progressPercent = $item->steps_count ? round($item->completed_steps / $item->steps_count * 100) : 0;
                                $nextStep = $item->steps->sortBy('step_number')->first(fn ($step) => $step->status !== 'completed');
                            @endphp
                            <tr>
                                <td data-label="Đợt rà soát">
                                    <a class="program-name" href="{{ route('iso.show', $item) }}">{{ $item->title }}</a>
                                    <span class="iso-program-name">{{ $item->program?->name_vi ?: 'Chưa có chương trình' }}</span>
                                    <small>{{ $item->program?->code }} &middot; {{ $typeLabels[$item->type] ?? $item->type }}</small>
                                    <div class="course-row-status"><span class="academic-status iso-status-{{ $item->status }}">{{ $statusLabels[$item->status] ?? $item->status }}</span></div>
                                </td>
                                <td data-label="Tiến độ">
                                    @if($item->steps_count)
                                        <div class="iso-progress-label"><strong>{{ $item->completed_steps }}/{{ $item->steps_count }} bước</strong><span>{{ $progressPercent }}%</span></div>
                                        <progress class="iso-progress" value="{{ $item->completed_steps }}" max="{{ $item->steps_count }}" aria-label="Tiến độ đợt {{ $item->title }}">{{ $progressPercent }}%</progress>
                                        <small>Số bước đã hoàn thành</small>
                                    @else
                                        <small>Chưa có bước thực hiện</small>
                                    @endif
                                    @if($item->overdue_steps)<span class="iso-overdue">{{ $item->overdue_steps }} bước quá hạn</span>@endif
                                </td>
                                <td data-label="Bước tiếp theo">
                                    @if(in_array($item->status, ['completed','cancelled'], true))
                                        <small>{{ $item->status === 'completed' ? 'Đợt rà soát đã hoàn thành' : 'Đợt rà soát đã hủy' }}</small>
                                    @elseif($nextStep)
                                        <strong>{{ $nextStep->step_name }}</strong>
                                        <small>{{ $stepLabels[$nextStep->status] ?? $nextStep->status }}</small>
                                        <small>Phụ trách: {{ $nextStep->assignee?->name ?: 'Chưa phân công' }}</small>
                                        <small>Hạn bước: {{ $nextStep->deadline?->format('d/m/Y') ?? 'Chưa ghi nhận' }}</small>
                                    @else
                                        <small>{{ $item->steps_count ? 'Các bước đã hoàn thành' : 'Chưa có bước thực hiện' }}</small>
                                    @endif
                                </td>
                                <td data-label="Thời hạn / hồ sơ">
                                    <strong>{{ $item->expected_end_date?->format('d/m/Y') ?? 'Chưa có hạn hoàn thành' }}</strong>
                                    <small>Bắt đầu {{ $item->start_date?->format('d/m/Y') ?? 'chưa ghi nhận' }}</small>
                                    <small>{{ $item->files_count }} tệp hồ sơ</small>
                                    <a class="program-view-link" href="{{ route('iso.show', $item) }}">Xem chi tiết <span aria-hidden="true">&rarr;</span></a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="academic-empty"><p>Không có đợt rà soát phù hợp bộ lọc.</p>@if($hasFilters)<a class="program-empty-link" href="{{ route('iso.index') }}">Xóa lọc và xem tất cả đợt rà soát</a>@endif</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="academic-pagination">{{ $workflows->links() }}</div>
        </section>
    </div>
</x-app-layout>
