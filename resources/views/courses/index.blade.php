<x-app-layout>
    <x-slot name="header">
        <div class="academic-heading">
            <div><p class="academic-eyebrow">Quản lý đào tạo</p><h1>Danh mục học phần</h1><p>Thông tin học phần, chương trình sử dụng và học liệu.</p></div>
            <div class="academic-heading-links"><a href="{{ route('programs.index') }}">Chương trình đào tạo <span aria-hidden="true">&rarr;</span></a><a href="{{ route('programs.audit', ['type'=>'course']) }}">Nhật ký học phần <span aria-hidden="true">&rarr;</span></a></div>
        </div>
    </x-slot>
    <div class="academic-page programs-page courses-page app-shell">
        @php
            $hasFilters = request()->filled('q') || request()->filled('program_id') || request()->filled('is_active') || request()->filled('credits') || request()->filled('coverage') || request()->filled('prerequisite');
            $hasAdvancedFilters = request()->filled('coverage') || request()->filled('prerequisite') || (request()->filled('sort') && request('sort') !== 'code') || (request()->filled('per_page') && (int) request('per_page') !== 20);
        @endphp
        @if(session('success'))<div class="academic-success" role="status">{{ session('success') }}</div>@endif
        <section aria-labelledby="course-summary-title">
            <div class="academic-section-heading"><h2 id="course-summary-title">Tổng quan học phần</h2><span>Toàn hệ thống</span></div>
            <dl class="academic-counts">
                <div class="academic-count"><dt>Tổng học phần</dt><dd>{{ number_format($summary['total']) }}</dd></div>
                <div class="academic-count academic-count-resolved"><dt>Đang hoạt động</dt><dd>{{ number_format($summary['active']) }}</dd><a class="program-summary-link" href="{{ route('courses.index', ['is_active'=>1]) }}">Xem danh sách <span aria-hidden="true">&rarr;</span></a></div>
                <div class="academic-count academic-count-processing"><dt>Chưa gắn chương trình</dt><dd>{{ number_format($summary['unassigned']) }}</dd><a class="program-summary-link" href="{{ route('courses.index', ['coverage'=>'unassigned']) }}">Rà soát chương trình <span aria-hidden="true">&rarr;</span></a></div>
                <div class="academic-count academic-count-open"><dt>Chưa có học liệu</dt><dd>{{ number_format($summary['missing_materials']) }}</dd><a class="program-summary-link" href="{{ route('courses.index', ['coverage'=>'missing_materials']) }}">Rà soát học liệu <span aria-hidden="true">&rarr;</span></a></div>
            </dl>
        </section>
        <form method="GET" action="{{ route('courses.index') }}" class="academic-filters" aria-label="Bộ lọc học phần">
            <div class="academic-filter-grid">
                <div class="academic-filter"><label for="course-search">Tìm học phần</label><input id="course-search" type="search" name="q" value="{{ request('q') }}" maxlength="120" placeholder="Mã, tên tiếng Việt hoặc tiếng Anh"></div>
                <div class="academic-filter"><label for="course-program">Chương trình sử dụng</label><select id="course-program" name="program_id"><option value="">Tất cả chương trình</option>@foreach($programs as $program)<option value="{{ $program->id }}" @selected(request('program_id') == $program->id)>{{ $program->name_vi }}</option>@endforeach</select></div>
                <div class="academic-filter"><label for="course-status">Trạng thái</label><select id="course-status" name="is_active"><option value="">Tất cả trạng thái</option><option value="1" @selected(request('is_active') === '1')>Đang hoạt động</option><option value="0" @selected(request('is_active') === '0')>Ngừng hoạt động</option></select></div>
                <div class="academic-filter"><label for="course-credits">Số tín chỉ</label><input id="course-credits" type="number" name="credits" min="1" max="99" value="{{ request('credits') }}" placeholder="Tất cả tín chỉ"></div>
            </div>
            <details class="academic-filter-details" @if($hasAdvancedFilters) open @endif>
                <summary>Bộ lọc bổ sung</summary>
                <div class="academic-filter-grid">
                    <div class="academic-filter"><label for="course-coverage">Chương trình và học liệu</label><select id="course-coverage" name="coverage"><option value="">Tất cả học phần</option><option value="unassigned" @selected(request('coverage') === 'unassigned')>Chưa gắn chương trình</option><option value="missing_materials" @selected(request('coverage') === 'missing_materials')>Chưa có học liệu</option></select></div>
                    <div class="academic-filter"><label for="course-prerequisite">Học phần tiên quyết</label><select id="course-prerequisite" name="prerequisite"><option value="">Tất cả</option><option value="has" @selected(request('prerequisite') === 'has')>Có học phần tiên quyết</option><option value="none" @selected(request('prerequisite') === 'none')>Không có học phần tiên quyết</option></select></div>
                    <div class="academic-filter"><label for="course-sort">Sắp xếp</label><select id="course-sort" name="sort"><option value="code" @selected(request('sort', 'code') === 'code')>Mã học phần</option><option value="name" @selected(request('sort') === 'name')>Tên A &ndash; Z</option><option value="credits" @selected(request('sort') === 'credits')>Tín chỉ từ cao xuống thấp</option><option value="updated" @selected(request('sort') === 'updated')>Mới cập nhật gần nhất</option></select></div>
                    <div class="academic-filter"><label for="course-per-page">Số dòng mỗi trang</label><select id="course-per-page" name="per_page">@foreach([20,30,50] as $size)<option value="{{ $size }}" @selected((int) request('per_page', 20) === $size)>{{ $size }} dòng</option>@endforeach</select></div>
                </div>
            </details>
            <div class="academic-filter-actions"><button type="submit" class="academic-button academic-button-primary">Tìm và lọc</button><a href="{{ route('courses.index') }}" class="academic-button academic-button-secondary">Xóa lọc</a></div>
        </form>
        <section aria-labelledby="course-list-title">
            <div class="academic-section-heading">
                <div><h2 id="course-list-title">Danh sách học phần <span class="academic-list-count">{{ number_format($courses->total()) }}</span></h2><p class="course-list-scope">{{ $hasFilters ? 'Theo bộ lọc đang chọn' : 'Tất cả học phần' }}@if($courses->total()) &middot; {{ $courses->firstItem() }}&ndash;{{ $courses->lastItem() }} / {{ $courses->total() }}@endif</p></div>
                @can('courses.create')<a href="{{ route('courses.create') }}" class="academic-button academic-button-primary">Thêm học phần</a>@endcan
            </div>
            <div class="academic-table-wrap">
                <table class="academic-table program-table">
                    <caption class="sr-only">Danh mục học phần, tín chỉ, học liệu và chương trình sử dụng</caption>
                    <thead><tr><th scope="col">Học phần</th><th scope="col">Tín chỉ / thời lượng</th><th scope="col">Chương trình / tiên quyết</th><th scope="col">Học liệu / thao tác</th></tr></thead>
                    <tbody>
                        @forelse($courses as $course)
                            <tr>
                                <td data-label="Học phần"><a class="program-name" href="{{ route('courses.show', $course) }}">{{ $course->name_vi }}</a><span class="academic-code">{{ $course->course_code }}</span>@if($course->name_en)<small lang="en">{{ $course->name_en }}</small>@endif<div class="course-row-status"><span class="academic-status {{ $course->is_active ? 'academic-status-resolved' : '' }}">{{ $course->is_active ? 'Đang hoạt động' : 'Ngừng hoạt động' }}</span></div></td>
                                <td data-label="Tín chỉ / thời lượng"><strong>{{ $course->credits }} tín chỉ</strong><small>{{ $course->theory_hours }} giờ lý thuyết</small><small>{{ $course->practice_hours }} giờ thực hành</small></td>
                                <td data-label="Chương trình / tiên quyết">
                                    @forelse($course->programs->take(2) as $program)<a class="course-program-link" href="{{ route('programs.show', $program) }}#program-courses">{{ $program->name_vi }}</a>@empty<small class="program-attention">Chưa gắn chương trình</small>@endforelse
                                    @if($course->programs->count() > 2)<details class="program-more"><summary>{{ $course->programs->count() - 2 }} chương trình khác</summary><div class="program-more-links">@foreach($course->programs->skip(2) as $program)<a href="{{ route('programs.show', $program) }}#program-courses">{{ $program->name_vi }}</a>@endforeach</div></details>@endif
                                    <small>Tiên quyết: @if($course->prerequisite)<a class="course-prerequisite-link" href="{{ route('courses.show', $course->prerequisite) }}">{{ $course->prerequisite->course_code }}</a>@else Không có @endif</small>
                                </td>
                                <td data-label="Học liệu / thao tác"><div class="program-row-actions"><a class="program-view-link" href="{{ route('courses.show', $course) }}#course-materials">{{ $course->materials_count }} học liệu <span aria-hidden="true">&rarr;</span></a>@can('courses.update')<a href="{{ route('courses.edit', $course) }}" class="program-edit-link">Chỉnh sửa học phần</a>@endcan</div>@if(!$course->materials_count)<small class="program-attention">Chưa có học liệu</small>@endif<small>Cập nhật {{ $course->updated_at?->format('d/m/Y') }}</small></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="academic-empty"><p>{{ $hasFilters ? 'Không có học phần phù hợp bộ lọc.' : 'Chưa có học phần.' }}</p>@if($hasFilters)<a href="{{ route('courses.index') }}" class="program-empty-link">Xóa lọc và xem tất cả học phần</a>@endif</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="academic-pagination">{{ $courses->links() }}</div>
        </section>
    </div>
</x-app-layout>
