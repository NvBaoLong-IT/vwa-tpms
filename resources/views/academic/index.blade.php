<x-app-layout>
    <x-slot name="header">
        <div class="academic-heading">
            <div><p class="academic-eyebrow">Quản lý học vụ</p><h1>Cảnh báo học vụ</h1><p>Theo dõi kết quả học tập và tiến độ xử lý cảnh báo.</p></div>
            <div class="academic-heading-links">
                <a href="{{ route('academic.students') }}">Danh sách sinh viên <span aria-hidden="true">&rarr;</span></a>
                <a href="{{ route('academic.notifications') }}">Thông báo <span class="academic-notification-count">{{ auth()->user()->unreadNotifications()->count() }}</span></a>
            </div>
        </div>
    </x-slot>

    <div class="academic-page app-shell">
        @php
            $statusLabels = ['open'=>'Chưa xử lý','processing'=>'Đang xử lý','resolved'=>'Đã xử lý','dismissed'=>'Đã bỏ qua'];
            $hasAdvancedFilters = request()->filled('enrollment_year') || request()->filled('student_status') || request()->filled('class_code') || request()->filled('semester') || request()->filled('priority') || request()->filled('faculty_name');
        @endphp
        @if(session('success'))<div class="academic-success" role="status">{{ session('success') }}</div>@endif

        <section class="academic-overview" aria-labelledby="academic-overview-title">
            <div class="academic-section-heading"><h2 id="academic-overview-title">Tình hình xử lý</h2><span>Toàn hệ thống</span></div>
            <dl class="academic-counts">
                @foreach($statusLabels as $value => $label)
                    <div class="academic-count academic-count-{{ $value }}"><dt>{{ $label }}</dt><dd>{{ number_format($counts[$value] ?? 0) }}</dd></div>
                @endforeach
            </dl>
        </section>

        <form method="GET" action="{{ route('academic.index') }}" class="academic-filters" aria-label="Bộ lọc cảnh báo học vụ">
            <div class="academic-filter-grid">
            <label class="academic-filter">Ngành<select name="program_id" ><option value="">Tất cả ngành</option>@foreach($programs as $program)<option value="{{ $program->id }}" @selected(request('program_id') == $program->id)>{{ $program->name_vi }}</option>@endforeach</select></label>
            <label class="academic-filter">Trình độ<select name="degree_level" ><option value="">Tất cả trình độ</option>@foreach(['dai_hoc'=>'Đại học','thac_si'=>'Thạc sĩ','tien_si'=>'Tiến sĩ'] as $value => $label)<option value="{{ $value }}" @selected(request('degree_level') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class="academic-filter">Loại cảnh báo<select name="warning_type" ><option value="">Tất cả loại</option>@foreach($warningLabels as $value => $label)<option value="{{ $value }}" @selected(request('warning_type') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class="academic-filter">Trạng thái cảnh báo<select name="status"><option value="">Tất cả trạng thái</option>@foreach($statusLabels as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select></label>
            </div>
            <details class="academic-filter-details" @if($hasAdvancedFilters) open @endif>
                <summary>Bộ lọc bổ sung</summary>
                <div class="academic-filter-grid">
            <label class="academic-filter">Năm nhập học<input type="number" min="2000" max="2100" name="enrollment_year" value="{{ request('enrollment_year') }}" placeholder="Tất cả năm" ></label>
            <label class="academic-filter">Trạng thái sinh viên<select name="student_status" ><option value="">Tất cả</option>@foreach(['active'=>'Đang học','graduated'=>'Đã tốt nghiệp','withdrawn'=>'Thôi học'] as $value => $label)<option value="{{ $value }}" @selected(request('student_status') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class="academic-filter">Lớp<select name="class_code" ><option value="">Tất cả lớp</option>@foreach($classes as $class)<option value="{{ $class }}" @selected(request('class_code') === $class)>{{ $class }}</option>@endforeach</select></label>
            <label class="academic-filter">Học kỳ<select name="semester" ><option value="">Tất cả học kỳ</option>@foreach($semesters as $semester)<option value="{{ $semester }}" @selected(request('semester') === $semester)>{{ $semester }}</option>@endforeach</select></label>
            <label class="academic-filter">Mức ưu tiên<select name="priority" ><option value="">Tất cả</option><option value="critical" @selected(request('priority') === 'critical')>Cần ưu tiên</option></select></label>
            <label class="academic-filter">Khoa<select name="faculty_name" ><option value="">Tất cả khoa</option>@foreach($faculties as $faculty)<option value="{{ $faculty }}" @selected(request('faculty_name') === $faculty)>{{ $faculty }}</option>@endforeach</select></label>
                </div>
            </details>
            <div class="academic-filter-actions">
                <button type="submit" class="academic-button academic-button-primary">Lọc cảnh báo</button>
                <a href="{{ route('academic.index') }}" class="academic-button academic-button-secondary">Xóa lọc</a>
            </div>
        </form>

        <div class="academic-toolbar">
            <div class="academic-tools">
                @can('academic.import')<a href="{{ route('academic.import.form') }}" class="academic-button academic-button-primary">Nhập Excel/CSV</a>@endcan
                @can('academic.process')<form method="POST" action="{{ route('academic.scan') }}">@csrf<button type="submit" class="academic-button academic-button-secondary">Quét cảnh báo</button></form>@endcan
                @if(auth()->user()->hasRole('Lãnh đạo phòng'))<a href="{{ route('academic.settings') }}" class="academic-button academic-button-secondary">Ngưỡng GPA</a>@endif
            </div>
            <details class="academic-export">
                <summary>Xuất danh sách</summary>
                <div class="academic-export-options">
                    <a href="{{ route('academic.export.excel', request()->query()) }}">Excel</a>
                    <a href="{{ route('academic.export.word', request()->query()) }}">Word</a>
                    <a href="{{ route('academic.export.pdf', request()->query()) }}">PDF</a>
                </div>
            </details>
        </div>

        <section aria-labelledby="academic-list-title">
            <div class="academic-section-heading"><h2 id="academic-list-title">Danh sách cảnh báo <span class="academic-list-count">{{ number_format($warnings->total()) }}</span></h2><span>{{ request()->hasAny(['program_id','degree_level','warning_type','status','enrollment_year','student_status','class_code','semester','priority','faculty_name','advisor_user_id']) ? 'Theo bộ lọc đang chọn' : 'Tất cả cảnh báo' }}</span></div>
            <div class="academic-table-wrap">
                <table class="academic-table">
                    <caption class="sr-only">Danh sách cảnh báo học vụ và trạng thái xử lý</caption>
                    <thead><tr><th scope="col">Sinh viên</th><th scope="col">Cảnh báo</th><th scope="col">Nơi tiếp nhận</th><th scope="col">Trạng thái / xử lý</th></tr></thead>
                    <tbody>
                        @forelse($warnings as $warning)
                            <tr>
                                <td data-label="Sinh viên">
                                    <a class="academic-student-link" href="{{ route('academic.student', $warning->student) }}">{{ $warning->student->full_name }}</a>
                                    <span class="academic-code">{{ $warning->student->student_code }}</span>
                                    <small>{{ $warning->student->program?->name_vi ?: 'Chưa phân ngành' }} &middot; {{ $warning->student->class_code ?: 'Chưa có lớp' }}</small>
                                </td>
                                <td data-label="Cảnh báo">
                                    <strong>{{ $warningLabels[$warning->warning_type] ?? $warning->warning_type }}</strong>
                                    <p>{{ str_replace('GPA', 'Điểm trung bình hệ 4', $warning->reason) }}</p>
                                    <small>{{ $warning->progress?->semester ? 'Học kỳ '.$warning->progress->semester.' · ' : '' }}{{ $warning->issued_at?->format('d/m/Y H:i') }}</small>
                                </td>
                                <td data-label="Nơi tiếp nhận"><strong>{{ $warning->student->advisor?->name ?: 'Chưa gán cố vấn' }}</strong><small>{{ $warning->student->faculty_name ?: 'Chưa có khoa' }}</small></td>
                                <td data-label="Trạng thái / xử lý">
                                    <span class="academic-status academic-status-{{ $warning->status }}">{{ $statusLabels[$warning->status] ?? $warning->status }}</span>
                                    @can('academic.process')
                                        <form method="POST" action="{{ route('academic.warnings.update', $warning) }}" class="academic-row-form">
                                            @csrf @method('PATCH')
                                            <label for="warning-status-{{ $warning->id }}" class="sr-only">Trạng thái cảnh báo {{ $warning->student->student_code }} - {{ $warningLabels[$warning->warning_type] ?? $warning->warning_type }}</label>
                                            <select id="warning-status-{{ $warning->id }}" name="status" required>
                                                @if($warning->status === 'open')<option value="" selected disabled>Chọn xử lý</option>@endif
                                                @foreach(['processing'=>'Đang xử lý','resolved'=>'Đã xử lý','dismissed'=>'Đã bỏ qua'] as $value => $label)<option value="{{ $value }}" @selected($warning->status === $value)>{{ $label }}</option>@endforeach
                                            </select>
                                            <button type="submit" class="academic-row-save" aria-label="Lưu trạng thái cảnh báo {{ $warning->student->student_code }} - {{ $warningLabels[$warning->warning_type] ?? $warning->warning_type }}">Lưu</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="academic-empty">Không có cảnh báo phù hợp bộ lọc.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="academic-pagination">{{ $warnings->links() }}</div>
        </section>

        <section class="academic-imports" aria-labelledby="academic-imports-title">
            <div class="academic-section-heading"><h2 id="academic-imports-title">Các lần nhập dữ liệu gần đây</h2><span>10 lần gần nhất trên toàn hệ thống</span></div>
            @forelse($imports as $import)
                <div class="academic-import-row">
                    <div><a href="{{ route('academic.import.report', $import) }}">{{ $import->original_name }}</a><small>{{ $import->created_at?->format('d/m/Y H:i') }} &middot; {{ number_format($import->rows_imported) }} dòng &middot; {{ number_format($import->error_count) }} lỗi</small></div>
                    <span class="academic-status academic-import-{{ $import->status }}">{{ ['queued'=>'Đang chờ','processing'=>'Đang xử lý','completed'=>'Hoàn thành','failed'=>'Thất bại'][$import->status] ?? $import->status }}</span>
                </div>
            @empty
                <p class="academic-empty">Chưa có lần nhập dữ liệu.</p>
            @endforelse
        </section>
    </div>
</x-app-layout>
