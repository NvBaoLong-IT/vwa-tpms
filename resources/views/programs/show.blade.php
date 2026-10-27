<x-app-layout>
    <x-slot name="header">
        <div class="academic-heading">
            <div><p class="academic-eyebrow">Chi tiết chương trình · Bản đang soạn</p><h1>{{ $program->name_vi }}</h1><p>{{ $program->code }} @if($program->name_en) · <span lang="en">{{ $program->name_en }}</span>@endif</p></div>
            <div class="academic-tools"><a class="academic-button academic-button-secondary" href="{{ route('programs.index') }}">Danh sách chương trình</a>@can('programs.import')<a class="academic-button academic-button-secondary" href="{{ route('programs.document-imports.create', $program) }}">Nhập từ PDF/Word bằng AI</a>@endcan @can('programs.update')<a class="academic-button academic-button-primary" href="{{ route('programs.edit', $program) }}">Chỉnh sửa thông tin</a>@endcan</div>
        </div>
    </x-slot>

    <div class="academic-page program-detail-page app-shell">
        <x-input-error :messages="$errors->all()" />

        <section class="program-detail-overview" aria-labelledby="program-info-title">
            <div class="academic-section-heading"><h2 id="program-info-title">Thông tin chương trình</h2><span class="academic-status {{ $program->is_active ? 'academic-status-resolved' : '' }}">{{ $program->is_active ? 'Đang hoạt động' : 'Ngừng hoạt động' }}</span></div>
            <dl class="student-facts">
                <div><dt>Trình độ</dt><dd>{{ ['dai_hoc'=>'Đại học','thac_si'=>'Thạc sĩ','tien_si'=>'Tiến sĩ'][$program->degree_level] ?? $program->degree_level }}</dd></div>
                <div><dt>Hình thức đào tạo</dt><dd>{{ $program->delivery_mode ?: 'Chưa ghi nhận' }}</dd></div>
                <div><dt>Số quyết định</dt><dd>{{ $program->decision_number ?: 'Chưa ghi nhận' }}</dd></div>
                <div><dt>Ngày ban hành</dt><dd>{{ $program->decision_date?->format('d/m/Y') ?? 'Chưa ghi nhận' }}</dd></div>
                <div><dt>Chu kỳ rà soát</dt><dd>{{ $program->review_cycle_years }} năm</dd></div>
                <div><dt>Học phần</dt><dd>{{ $program->courses->count() }} học phần</dd></div>
                <div><dt>Phiên bản theo khóa</dt><dd>{{ $program->versions->count() }} phiên bản</dd></div>
                <div><dt>Cập nhật gần nhất</dt><dd>{{ $program->updated_at?->format('d/m/Y') }}</dd></div>
            </dl>
        </section>
        <nav class="program-detail-navigation" aria-label="Nội dung chương trình">
            <a href="#program-credits">Đối chiếu tín chỉ</a><a href="#program-courses">Học phần</a><a href="#program-versions">Phiên bản theo khóa</a><a href="#program-substitutions">Học phần thay thế</a><a href="#program-elective-groups">Nhóm tự chọn</a>
            <a href="{{ route('programs.plos', $program) }}">Chuẩn đầu ra &rarr;</a><a href="{{ route('programs.matrix', $program) }}">Mức đáp ứng &rarr;</a><a href="{{ route('programs.audit', ['program_id'=>$program->id]) }}">Nhật ký &rarr;</a>
        </nav>
        <section id="program-credits" class="program-detail-section" aria-labelledby="credit-heading">
            <div class="academic-section-heading"><h2 id="credit-heading">Đối chiếu tín chỉ</h2><span>Tính từ học phần trong bản đang soạn</span></div>
            <div class="program-credit-grid">
                <div><strong class="program-credit-value">{{ $reconciliation['declared_credits'] }}</strong>Tín chỉ khai báo</div>
                <div><strong class="program-credit-value">{{ $reconciliation['mandatory_credits'] }}</strong>Bắt buộc</div>
                <div><strong class="program-credit-value">{{ $reconciliation['elective_required'] }}</strong>Tự chọn cần tích lũy</div>
                <div><strong class="program-credit-value">{{ $reconciliation['elective_offered'] }}</strong>Tự chọn đang mở</div>
                <div><strong class="program-credit-value">{{ $reconciliation['assigned_credits'] }}</strong>Học phần đã gắn</div>
            </div>
            <div class="mt-4 space-y-2">
                @foreach($reconciliation['issues'] as $issue)
                    <p role="alert" class="border-l-4 p-3 text-sm {{ $issue['severity'] === 'error' ? 'border-red-600 bg-red-50 text-red-800' : 'border-amber-600 bg-amber-50 text-amber-900' }}">{{ $issue['message'] }}</p>
                @endforeach
            </div>
        </section>

        <section id="program-versions" aria-labelledby="versions-heading" class="program-detail-section">
            <p class="version-snapshot-note">Quy trình: soạn CTĐT → lưu phiên bản → trình duyệt → lãnh đạo phê duyệt → công bố theo khóa và ngày hiệu lực. Mở từng phiên bản để xem ý kiến duyệt và so sánh thay đổi.</p>
            <div class="academic-section-heading"><h2 id="versions-heading">Phiên bản theo khóa</h2><span>Bản lưu tại thời điểm tạo phiên bản</span></div>
            <div class="program-detail-table-wrap">
                <table class="program-detail-table">
                    <thead class="bg-gray-50"><tr><th class="p-3">Khóa</th><th class="p-3">Lần</th><th class="p-3">Quyết định</th><th class="p-3">Ngày</th><th class="p-3">Trạng thái</th><th class="p-3"></th></tr></thead>
                    <tbody>
                        @forelse($program->versions as $version)
                            <tr class="border-t"><td class="p-3" data-label="Khóa">K{{ $version->cohort_year }}</td><td class="p-3" data-label="Lần">{{ $version->revision }}</td><td class="p-3" data-label="Quyết định">{{ $version->decision_number }}</td><td class="p-3" data-label="Ngày">{{ $version->decision_date->format('d/m/Y') }}</td><td class="p-3" data-label="Trạng thái">{{ $version->statusLabel() }}</td><td class="p-3" data-label="Thao tác"><a class="program-detail-link" href="{{ route('programs.versions.show', [$program, $version]) }}">Xem</a></td></tr>
                        @empty
                            <tr><td colspan="6" class="p-5 text-gray-500">Chưa có phiên bản theo khóa.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @can('programs.update')
                <details class="program-management" @if($errors->hasAny(['cohort_year','decision_number','decision_date'])) open @endif><summary>Tạo phiên bản theo khóa</summary><p>Ghi lại chương trình, học phần, chuẩn đầu ra và ma trận hiện tại thành một bản lưu.</p>
                <form method="POST" action="{{ route('programs.versions.store', $program) }}" class="flex flex-wrap items-end gap-3 mt-4">
                    @csrf
                    <label class="text-sm">Năm khóa<input type="number" name="cohort_year" min="1900" max="2100" value="{{ old('cohort_year') }}" class="block w-28 rounded border-gray-300" required></label>
                    <label class="text-sm">Số quyết định<input name="decision_number" value="{{ old('decision_number', $program->decision_number) }}" class="block w-52 rounded border-gray-300" required></label>
                    <label class="text-sm">Ngày quyết định<input type="date" name="decision_date" value="{{ old('decision_date', $program->decision_date?->format('Y-m-d')) }}" class="block rounded border-gray-300" required></label>
                    <button class="btn btn-primary">Lưu phiên bản</button>
                </form></details>
            @endcan
        </section>

        @include('programs.partials.elective-groups', ['editable' => true])
        @include('programs.partials.substitutions', ['rules' => $program->course_substitutions ?? [], 'editable' => true])
        <section id="program-courses" aria-labelledby="courses-heading" class="program-detail-section">
            <div class="academic-section-heading"><h2 id="courses-heading">Học phần theo khối kiến thức</h2><span class="text-sm text-gray-500">{{ $program->code }}</span></div>
            <div class="program-detail-table-wrap">
                <table class="program-detail-table">
                    <thead class="bg-gray-50"><tr><th class="p-3">Mã</th><th class="p-3">Học phần</th><th class="p-3">TC</th><th class="p-3">Khối kiến thức</th><th class="p-3">Loại</th><th class="p-3">Học kỳ</th></tr></thead>
                    <tbody>
                        @forelse($program->courses as $course)
                            <tr class="border-t"><td class="p-3 font-mono" data-label="Mã">{{ $course->course_code }}</td><td class="p-3" data-label="Học phần">@can('courses.view')<a class="program-detail-link" href="{{ route('courses.show', $course) }}">{{ $course->name_vi }}</a>@else{{ $course->name_vi }}@endcan</td><td class="p-3" data-label="TC">{{ $course->credits }}</td><td class="p-3" data-label="Khối kiến thức">{{ $course->pivot->knowledge_block }}</td><td class="p-3" data-label="Loại">{{ $course->pivot->is_mandatory ? 'Bắt buộc' : 'Tự chọn' }}@if(!$course->pivot->counts_toward_total) · Ngoài tổng tín chỉ @endif@if($course->pivot->elective_group_code) · {{ $course->pivot->elective_group_code }}@endif</td><td class="p-3" data-label="Học kỳ">{{ $course->pivot->suggested_semester ?: '—' }}</td></tr>
                        @empty
                            <tr><td colspan="6" class="p-5 text-gray-500">Chưa có học phần.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @can('courses.assign')
            <section class="program-detail-section"><div class="academic-section-heading"><h2>Quản lý học phần</h2><span>Dành cho cán bộ được phân quyền</span></div>
                <details class="program-management" @if($errors->hasAny(['course_id','knowledge_block','suggested_semester'])) open @endif><summary>Thêm học phần vào chương trình</summary>
                <form method="POST" action="{{ route('programs.courses.attach', $program) }}" class="flex flex-wrap gap-3 items-end">
                    @csrf
                    <label class="program-form-field">Học phần<select name="course_id" aria-label="Học phần" class="rounded border-gray-300" required>@foreach($availableCourses as $item)<option value="{{ $item->id }}">{{ $item->course_code }} - {{ $item->name_vi }}</option>@endforeach</select></label>
                    <label class="program-form-field">Khối kiến thức<input name="knowledge_block" placeholder="Khối kiến thức" aria-label="Khối kiến thức" class="rounded border-gray-300" required></label>
                    <label class="program-form-field">Học kỳ gợi ý<input type="number" name="suggested_semester" min="1" max="20" placeholder="Học kỳ" aria-label="Học kỳ" class="w-24 rounded border-gray-300"></label>
                    <label class="program-form-field">Nhóm tự chọn<select name="elective_group_code" class="rounded border-gray-300"><option value="">Chưa phân nhóm</option>@foreach($program->elective_groups ?? [] as $group)<option value="{{ $group['code'] }}">{{ $group['code'] }} · {{ $group['name'] }}</option>@endforeach</select></label><label class="flex items-center gap-2"><input type="checkbox" name="is_mandatory" value="1" checked>Bắt buộc</label><label>Phạm vi tín chỉ<select name="counts_toward_total" class="rounded border-gray-300"><option value="1">Tính vào tổng tín chỉ</option><option value="0">Ngoài tổng tín chỉ</option></select></label>
                    <button class="btn btn-primary">Thêm</button>
                </form></details>
                <details class="program-management"><summary>Cập nhật học phần trong chương trình</summary>
                <div class="grid md:grid-cols-2 gap-3">
                    @foreach($program->courses as $course)
                        <form method="POST" action="{{ route('programs.courses.update', [$program, $course]) }}" class="border p-3 space-y-2">
                            @csrf @method('PUT')
                            <strong class="text-sm">{{ $course->course_code }} · {{ $course->name_vi }}</strong>
                            <input name="knowledge_block" value="{{ $course->pivot->knowledge_block }}" aria-label="Khối kiến thức của {{ $course->course_code }}" class="block w-full rounded border-gray-300" required>
                            <label class="block">Nhóm tự chọn của {{ $course->course_code }}<select name="elective_group_code" class="block w-full rounded border-gray-300"><option value="">Chưa phân nhóm</option>@foreach($program->elective_groups ?? [] as $group)<option value="{{ $group['code'] }}" @selected($course->pivot->elective_group_code === $group['code'])>{{ $group['code'] }} · {{ $group['name'] }}</option>@endforeach</select></label><div class="flex flex-wrap gap-3 items-center"><input type="number" name="suggested_semester" value="{{ $course->pivot->suggested_semester }}" min="1" max="20" placeholder="Học kỳ" aria-label="Học kỳ của {{ $course->course_code }}" class="w-24 rounded border-gray-300"><label class="text-sm"><input type="checkbox" name="is_mandatory" value="1" @checked($course->pivot->is_mandatory)> Bắt buộc</label><label>Phạm vi tín chỉ<select name="counts_toward_total" class="rounded border-gray-300"><option value="1" @selected($course->pivot->counts_toward_total)>Tính vào tổng tín chỉ</option><option value="0" @selected(!$course->pivot->counts_toward_total)>Ngoài tổng tín chỉ</option></select></label><button class="text-indigo-700 font-medium">Lưu</button></div>
                        </form>
                        <details class="program-management"><summary>Bỏ {{ $course->course_code }} khỏi chương trình</summary><p>Chỉ bỏ liên kết với chương trình này; học phần vẫn được giữ trong danh mục.</p><form method="POST" action="{{ route('programs.courses.detach', [$program, $course]) }}">@csrf @method('DELETE')<button class="text-red-700">Bỏ khỏi chương trình</button></form></details>
                    @endforeach
                </div></details>
            </section>
        @endcan
    </div>
</x-app-layout>
