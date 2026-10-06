<x-app-layout>
    <x-slot name="header">
        <div class="academic-heading">
            <div><p class="academic-eyebrow">Chi tiết học phần</p><h1>{{ $course->name_vi }}</h1><p>{{ $course->course_code }} @if($course->name_en) · <span lang="en">{{ $course->name_en }}</span>@endif</p></div>
            <div class="academic-tools"><a href="{{ route('courses.index') }}" class="academic-button academic-button-secondary">Danh mục học phần</a>@can('courses.update')<a href="{{ route('courses.edit', $course) }}" class="academic-button academic-button-primary">Chỉnh sửa thông tin</a>@endcan</div>
        </div>
    </x-slot>
    <div class="academic-page course-detail-page app-shell">
        @if(session('success'))<p class="academic-success" role="status">{{ session('success') }}</p>@endif
        <x-input-error :messages="$errors->all()" />
        <section aria-labelledby="course-information-title" class="course-detail-overview">
            <div class="academic-section-heading"><h2 id="course-information-title">Thông tin học phần</h2><span class="academic-status {{ $course->is_active ? 'academic-status-resolved' : '' }}">{{ $course->is_active ? 'Đang hoạt động' : 'Ngừng hoạt động' }}</span></div>
            <dl class="academic-counts">
                <div class="academic-count"><dt>Tín chỉ</dt><dd>{{ $course->credits }}</dd></div>
                <div class="academic-count"><dt>Giờ lý thuyết</dt><dd>{{ $course->theory_hours }}</dd></div>
                <div class="academic-count"><dt>Giờ thực hành</dt><dd>{{ $course->practice_hours }}</dd></div>
                <div class="academic-count"><dt>Học liệu và giáo trình</dt><dd>{{ $course->materials->count() }}</dd></div>
            </dl>
            <dl class="student-facts course-detail-facts">
                <div><dt>Học phần tiên quyết</dt><dd>@if($course->prerequisite)<a class="program-detail-link" href="{{ route('courses.show', $course->prerequisite) }}">{{ $course->prerequisite->course_code }} · {{ $course->prerequisite->name_vi }}</a>@else Không có @endif</dd></div>
                <div><dt>Cập nhật gần nhất</dt><dd>{{ $course->updated_at?->format('d/m/Y') }}</dd></div>
            </dl>
        </section>
        <nav class="program-detail-navigation" aria-label="Nội dung học phần"><a href="#course-materials">Xem học liệu</a>@can('courses.update')<a href="#course-material-management">Thêm học liệu</a>@endcan<a href="{{ route('programs.audit', ['type'=>'course']) }}">Nhật ký học phần &rarr;</a></nav>
  <section id="course-materials" class="program-detail-section">
            <div class="academic-section-heading"><h2>Học liệu và giáo trình</h2><span>{{ $course->materials->count() }} tài liệu</span></div>
            <div class="space-y-3">
                @forelse($course->materials as $material)
                    <article class="course-material-card">
                        <div class="flex flex-wrap justify-between gap-3">
                            <div>
                                <h3 class="course-material-title">{{ $material->title }}</h3>
                                <p class="course-material-meta">{{ ['textbook' => 'Giáo trình', 'reference' => 'Tài liệu tham khảo', 'other' => 'Học liệu khác'][$material->material_type] ?? $material->material_type }} · {{ $material->author ?: 'Chưa có tác giả' }} · {{ $material->publisher ?: 'Chưa có NXB' }} @if($material->isbn)· ISBN {{ $material->isbn }}@endif</p>
                            </div>
                            <span class="academic-status course-library-{{ $material->library_status }}">{{ ['pending'=>'Chưa có xác nhận','confirmed'=>'Đã có tại Thư viện','unavailable'=>'Chưa có tại Thư viện'][$material->library_status] ?? $material->library_status }}</span>
                            @can('courses.update')
                                @if($material->confirmations->isEmpty())
                                    <details class="course-material-delete"><summary>Xóa học liệu</summary><p>Xóa tài liệu này khỏi danh sách học liệu của học phần.</p>
                                    <form method="POST" action="{{ route('courses.materials.destroy', [$course, $material]) }}">
                                        @csrf @method('DELETE')
                                        <button class="academic-button btn-danger">Xác nhận xóa học liệu</button>
                                    </form></details>
                                @endif
                            @endcan
                        </div>
                        @can('courses.update')
                            <details class="program-management">
                                <summary class="cursor-pointer text-indigo-700">Sửa học liệu</summary>
                                <form method="POST" action="{{ route('courses.materials.update', [$course, $material]) }}" class="course-material-form">
                                    @csrf @method('PUT')
                    <label class="program-form-field">Tên học liệu<input name="title" value="{{ $material->title }}" aria-label="Tên học liệu" class="rounded border-gray-300" required></label>
                    <label class="program-form-field">Loại học liệu<select name="material_type" aria-label="Loại học liệu" class="rounded border-gray-300"><option value="textbook" @selected($material->material_type === 'textbook')>Giáo trình</option><option value="reference" @selected($material->material_type === 'reference')>Tham khảo</option><option value="other" @selected($material->material_type === 'other')>Khác</option></select></label>
                    <label class="program-form-field">Tác giả<input name="author" value="{{ $material->author }}" placeholder="Tác giả" aria-label="Tác giả" class="rounded border-gray-300"></label>
                    <label class="program-form-field">Nhà xuất bản<input name="publisher" value="{{ $material->publisher }}" placeholder="Nhà xuất bản" aria-label="Nhà xuất bản" class="rounded border-gray-300"></label>
                    <label class="program-form-field">ISBN<input name="isbn" value="{{ $material->isbn }}" placeholder="ISBN" aria-label="ISBN" class="rounded border-gray-300"></label>
                                    <button class="academic-button academic-button-primary">Lưu</button>
                                </form>
                            </details>
                        @endcan
                        @if($material->confirmations->isNotEmpty())
                            <details class="program-management">
                                <summary class="cursor-pointer text-gray-700">Phản hồi Thư viện đã lưu ({{ $material->confirmations->count() }})</summary>
                                <ul class="mt-2 space-y-1">
                                    @foreach($material->confirmations as $entry)
                                        <li>{{ $entry->created_at->format('d/m/Y H:i') }} · {{ $entry->user?->name ?: 'Người dùng đã xóa' }} · {{ $entry->status === 'confirmed' ? 'Đã có' : 'Chưa có' }}@if($entry->note) · {{ $entry->note }}@endif</li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif
                    </article>
                @empty
                    <p class="text-gray-500">Chưa có học liệu.</p>
                @endforelse
            </div>
        </section>

        @can('courses.update')
            <section id="course-material-management" class="program-detail-section">
                <div class="academic-section-heading"><h2>Quản lý học liệu</h2><span>Dành cho cán bộ được phân quyền</span></div><details class="program-management" @if($errors->any()) open @endif><summary>Thêm học liệu và giáo trình</summary>
                <form method="POST" action="{{ route('courses.materials.store', $course) }}" class="course-material-form">
                    @csrf
                    <label class="program-form-field">Tên học liệu<input name="title" placeholder="Tên học liệu" aria-label="Tên học liệu" class="rounded border-gray-300" required></label>
                    <label class="program-form-field">Loại học liệu<select name="material_type" aria-label="Loại học liệu" class="rounded border-gray-300"><option value="textbook">Giáo trình</option><option value="reference">Tài liệu tham khảo</option><option value="other">Học liệu khác</option></select></label>
                    <label class="program-form-field">Tác giả<input name="author" placeholder="Tác giả" aria-label="Tác giả" class="rounded border-gray-300"></label>
                    <label class="program-form-field">Nhà xuất bản<input name="publisher" placeholder="Nhà xuất bản" aria-label="Nhà xuất bản" class="rounded border-gray-300"></label>
                    <label class="program-form-field">ISBN<input name="isbn" placeholder="ISBN" aria-label="ISBN" class="rounded border-gray-300"></label>
                    <button class="btn btn-primary">Thêm</button>
                </form></details>
            </section>
        @endcan
    </div>
</x-app-layout>
