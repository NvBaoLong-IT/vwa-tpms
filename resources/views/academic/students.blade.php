<x-app-layout>
    <x-slot name="header"><div class="academic-heading"><div><p class="academic-eyebrow">Hồ sơ người học</p><h1>Danh sách sinh viên và cố vấn</h1><p>Tra cứu hồ sơ, chương trình học và phân công cố vấn.</p></div>@can('academic.view')<div class="academic-heading-links"><a href="{{ route('academic.index') }}">Cảnh báo học vụ &rarr;</a></div>@endcan</div></x-slot>
    <div class="academic-page student-directory-page app-shell">
        <form method="GET" action="{{ route('academic.students') }}" class="academic-filters" aria-label="Bộ lọc sinh viên"><div class="academic-filter-grid">
            <label class="academic-filter">Tìm sinh viên<input name="q" value="{{ request('q') }}" placeholder="Mã SV hoặc họ tên" ></label>
            <label class="academic-filter">Ngành<select name="program_id" ><option value="">Tất cả ngành</option>@foreach($programs as $program)<option value="{{ $program->id }}" @selected(request('program_id') == $program->id)>{{ $program->name_vi }}</option>@endforeach</select></label>
            <label class="academic-filter">Trình độ<select name="degree_level" ><option value="">Tất cả trình độ</option>@foreach(['dai_hoc'=>'Đại học','thac_si'=>'Thạc sĩ','tien_si'=>'Tiến sĩ'] as $value => $label)<option value="{{ $value }}" @selected(request('degree_level') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class="academic-filter">Năm nhập học<input type="number" min="2000" max="2100" name="enrollment_year" value="{{ request('enrollment_year') }}" placeholder="Tất cả năm" ></label>
            </div><details class="academic-filter-details" @if(request()->filled('status') || request()->filled('class_code') || request()->filled('faculty_name') || request()->filled('advisor_user_id')) open @endif><summary>Bộ lọc bổ sung</summary><div class="academic-filter-grid">
            <label class="academic-filter">Trạng thái<select name="status" ><option value="">Tất cả trạng thái</option>@foreach(['active'=>'Đang học','graduated'=>'Đã tốt nghiệp','withdrawn'=>'Thôi học'] as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class="academic-filter">Lớp<input name="class_code" value="{{ request('class_code') }}" placeholder="Mã lớp" ></label>
            <label class="academic-filter">Khoa<select name="faculty_name" ><option value="">Tất cả khoa</option>@foreach($faculties as $faculty)<option value="{{ $faculty }}" @selected(request('faculty_name') === $faculty)>{{ $faculty }}</option>@endforeach</select></label>
            <label class="academic-filter">Cố vấn<select name="advisor_user_id" ><option value="">Tất cả cố vấn</option><option value="unassigned" @selected(request('advisor_user_id') === 'unassigned')>Chưa phân công cố vấn</option>@foreach($advisors as $advisor)<option value="{{ $advisor->id }}" @selected(request('advisor_user_id') == $advisor->id)>{{ $advisor->name }}</option>@endforeach</select></label>
            </div></details><div class="academic-filter-actions"><button class="academic-button academic-button-primary" type="submit">Lọc danh sách</button><a class="academic-button academic-button-secondary" href="{{ route('academic.students') }}">Xóa lọc</a></div>
        </form>
        <section aria-labelledby="student-directory-title">
            <div class="academic-section-heading"><h2 id="student-directory-title">Danh sách sinh viên <span class="academic-list-count">{{ number_format($students->total()) }}</span></h2><span>{{ $students->total() ? $students->firstItem().'–'.$students->lastItem().' / '.$students->total() : 'Không có kết quả' }}</span></div>
            <div class="academic-table-wrap"><table class="academic-table program-table"><caption class="sr-only">Sinh viên, chương trình học, trạng thái và cố vấn</caption>
                <thead><tr><th scope="col">Sinh viên</th><th scope="col">Thông tin đào tạo</th><th scope="col">Trạng thái học tập</th><th scope="col">Khoa và cố vấn</th></tr></thead>
                <tbody>@forelse($students as $student)<tr>
                    <td data-label="Sinh viên"><a class="program-name" href="{{ route('academic.student', $student) }}">{{ $student->full_name }}</a><span class="academic-code">{{ $student->student_code }}</span><a class="program-view-link" href="{{ route('academic.student', $student) }}">Xem hồ sơ &rarr;</a></td>
                    <td data-label="Thông tin đào tạo"><strong>{{ $student->program?->name_vi ?: 'Chưa phân ngành' }}</strong><small>{{ ['dai_hoc'=>'Đại học','thac_si'=>'Thạc sĩ','tien_si'=>'Tiến sĩ'][$student->degree_level] ?? 'Chưa ghi nhận' }}</small><small>Lớp: {{ $student->class_code ?: 'Chưa ghi nhận' }}</small><small>Năm nhập học: {{ $student->enrollment_year ?: 'Chưa ghi nhận' }}</small></td>
                    <td data-label="Trạng thái học tập"><span class="academic-status student-status-{{ $student->status }}">{{ ['active'=>'Đang học','graduated'=>'Đã tốt nghiệp','withdrawn'=>'Thôi học'][$student->status] ?? $student->status }}</span>@can('academic.view')<small>{{ $student->warnings_count }} cảnh báo đã ghi nhận</small>@endcan</td>
                    <td data-label="Khoa và cố vấn"><strong>{{ $student->advisor?->name ?: 'Chưa phân công cố vấn' }}</strong><small>{{ $student->faculty_name ?: 'Chưa phân công khoa' }}</small></td>
                </tr>@empty<tr><td colspan="4" class="academic-empty">Không có sinh viên phù hợp bộ lọc.</td></tr>@endforelse</tbody>
            </table></div>
        <div class="academic-pagination">{{ $students->links() }}</div></section>
    </div>
</x-app-layout>
