<x-app-layout>
    <x-slot name="header">
        <div class="academic-heading">
            <div><p class="academic-eyebrow">Phiên bản chương trình · K{{ $version->cohort_year }} · Lần {{ $version->revision }}</p><h1>{{ $version->snapshot['program']['name_vi'] }}</h1></div>
            <div class="academic-tools"><a href="{{ route('programs.versions.word', [$program, $version]) }}" class="academic-button academic-button-secondary">Xuất Word</a><a href="{{ route('programs.show', $program) }}" class="academic-button academic-button-secondary">Bản đang soạn</a></div>
        </div>
    </x-slot>
    <div class="academic-page program-detail-page app-shell">
        <x-input-error :messages="$errors->all()" />
        <section class="program-detail-overview" aria-labelledby="version-info-title">
            <div class="academic-section-heading"><h2 id="version-info-title">Thông tin phiên bản</h2><span class="academic-status {{ $version->status === 'published' ? 'academic-status-resolved' : '' }}">{{ $version->statusLabel() }}</span></div>
            <dl class="student-facts">
                <div><dt>Khóa áp dụng</dt><dd>K{{ $version->cohort_year }}</dd></div><div><dt>Lần sửa</dt><dd>{{ $version->revision }}</dd></div>
                <div><dt>Số quyết định</dt><dd>{{ $version->decision_number }}</dd></div><div><dt>Ngày quyết định</dt><dd>{{ $version->decision_date->format('d/m/Y') }}</dd></div>
                <div><dt>Ngày tạo bản lưu</dt><dd>{{ $version->created_at?->format('d/m/Y H:i') }}</dd></div><div><dt>Ngày công bố</dt><dd>{{ $version->published_at?->format('d/m/Y H:i') ?? 'Chưa công bố' }}</dd></div>
                <div><dt>Bắt đầu hiệu lực</dt><dd>{{ $version->effective_from?->format('d/m/Y') ?? 'Chưa xác định' }}</dd></div>
                <div><dt>Kết thúc hiệu lực</dt><dd>{{ $version->effective_until?->format('d/m/Y') ?? 'Chưa xác định' }}</dd></div>
                <div><dt>Người xét duyệt</dt><dd>{{ $version->reviewer?->name ?? 'Chưa xét duyệt' }}</dd></div>
                <div><dt>Thời điểm xét duyệt</dt><dd>{{ $version->reviewed_at?->format('d/m/Y H:i') ?? 'Chưa xét duyệt' }}</dd></div>
            </dl>
            @if($version->isoWorkflow)<p>Đợt rà soát: @can('iso.view')<a class="program-detail-link" href="{{ route('iso.show', $version->isoWorkflow) }}">{{ $version->isoWorkflow->title }}</a>@else{{ $version->isoWorkflow->title }}@endcan</p>@endif
            @if($version->review_note)<p class="version-snapshot-note"><strong>Ý kiến xét duyệt:</strong> {{ $version->review_note }}</p>@endif
            <p class="version-snapshot-note">Bản lưu gồm thông tin chương trình, học phần, khối kiến thức, chuẩn đầu ra và mức đáp ứng tại thời điểm tạo phiên bản. Thay đổi bản đang soạn không làm thay đổi nội dung đã lưu ở đây.</p>
            @can('programs.update')
                @if(in_array($version->status, ['draft', 'returned'], true))
                    <details class="program-management" @if($errors->any()) open @endif><summary>Trình duyệt phiên bản</summary>
                        <p>Nếu cần sửa nội dung, hãy sửa bản đang soạn và tạo phiên bản mới. Trình lại bản này giữ nguyên nội dung đã lưu. Các lỗi tín chỉ và thiếu khối kiến thức phải được xử lý trước khi trình.</p>
                        <form method="POST" action="{{ route('programs.versions.submit', [$program, $version]) }}" class="flex flex-wrap items-end gap-3 mt-4">@csrf
                            <label>Hiệu lực từ ngày<input class="block rounded border-gray-300" type="date" name="effective_from" value="{{ old('effective_from', $version->effective_from?->format('Y-m-d')) }}" required></label>
                            <label>Đến hết ngày (nếu có)<input class="block rounded border-gray-300" type="date" name="effective_until" value="{{ old('effective_until', $version->effective_until?->format('Y-m-d')) }}"></label>
                            <label>Đợt rà soát liên quan<select class="block rounded border-gray-300" name="iso_workflow_id"><option value="">Không gắn đợt rà soát</option>@foreach($workflows as $workflow)<option value="{{ $workflow->id }}" @selected(old('iso_workflow_id', $version->iso_workflow_id) == $workflow->id)>{{ $workflow->title }}</option>@endforeach</select></label>
                            <button class="academic-button academic-button-primary">Trình lãnh đạo phê duyệt</button>
                        </form>
                        <p class="version-snapshot-note">Nếu gắn đợt rà soát, hồ sơ đó phải được phê duyệt và hoàn tất trước khi phiên bản được duyệt/công bố.</p>
                    </details>
                @endif
            @endcan
            @if($version->status === 'submitted' && auth()->user()->hasRole('Lãnh đạo phòng') && $version->submitted_by !== auth()->id())
                <form method="POST" action="{{ route('programs.versions.review', [$program, $version]) }}" class="program-management space-y-3">@csrf
                    <label class="block">Ý kiến xét duyệt<textarea class="block w-full rounded border-gray-300" name="review_note" required maxlength="4000">{{ old('review_note') }}</textarea></label>
                    <button name="decision" value="approve" class="academic-button academic-button-primary">Phê duyệt</button>
                    <button name="decision" value="return" class="academic-button academic-button-secondary">Trả về bổ sung</button>
                </form>
            @endif
            @if($version->status === 'approved' && auth()->user()->hasRole('Lãnh đạo phòng'))
                <details class="program-management"><summary>Công bố phiên bản</summary><p>Phiên bản trước của cùng khóa sẽ kết thúc hiệu lực chậm nhất vào ngày liền trước ngày hiệu lực mới. Phiên bản có ngày hiệu lực trong tương lai chưa được xem là đang áp dụng.</p><form method="POST" action="{{ route('programs.versions.publish', [$program, $version]) }}">@csrf<button type="submit" class="academic-button academic-button-primary">Công bố phiên bản</button></form></details>
            @endif
        </section>
        @if($version->status === 'submitted' && $version->submitted_by === auth()->id())<p class="version-snapshot-note">Bạn là người trình phiên bản này. Cần một lãnh đạo khác xét duyệt trước khi công bố.</p>@endif <section class="program-detail-section" aria-labelledby="version-compare-title">
            <h2 id="version-compare-title">So sánh phiên bản</h2>
            <form method="GET" action="{{ route('programs.versions.show', [$program, $version]) }}" class="flex flex-wrap items-end gap-3">
                <label>Phiên bản đối chiếu<select name="compare" class="block rounded border-gray-300" required><option value="">Chọn phiên bản</option>@foreach($versions as $other)<option value="{{ $other->id }}" @selected($comparison?->id === $other->id)>K{{ $other->cohort_year }} · Lần {{ $other->revision }} · {{ $other->decision_number }}</option>@endforeach</select></label>
                <button class="academic-button academic-button-secondary">Xem thay đổi</button>
            </form>
            @if($comparison)<p>Trước: K{{ $comparison->cohort_year }} lần {{ $comparison->revision }}. Sau: phiên bản đang xem.</p>@include('programs.partials.snapshot-changes', ['changes' => $changes])@endif
        </section>
        <nav class="program-detail-navigation" aria-label="Nội dung phiên bản"><a href="#version-credits">Đối chiếu tín chỉ</a><a href="#version-courses">Học phần</a><a href="#version-plos">Chuẩn đầu ra</a><a href="#version-matrix">Mức đáp ứng</a><a href="#program-substitutions">Học phần thay thế</a></nav>
        @include('programs.partials.elective-groups', ['editable' => false])
        @include('programs.partials.substitutions', ['rules' => $version->snapshot['substitutions'] ?? [], 'editable' => false])
        <section id="version-credits" class="program-detail-section" aria-labelledby="version-credits-heading">
            <div class="academic-section-heading"><h2 id="version-credits-heading">Đối chiếu tín chỉ</h2></div>
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

        <section id="version-courses" aria-labelledby="version-courses-heading" class="program-detail-section">
            <div class="academic-section-heading"><h2 id="version-courses-heading">Học phần</h2></div>
            <div class="program-detail-table-wrap">
                <table class="program-detail-table">
                    <thead class="bg-gray-50"><tr><th class="p-3">Mã</th><th class="p-3">Tên học phần</th><th class="p-3">TC</th><th class="p-3">Khối kiến thức</th><th class="p-3">Loại</th><th class="p-3">Học kỳ</th></tr></thead>
                    <tbody>
                        @forelse($version->snapshot['courses'] as $course)
                            <tr class="border-t"><td class="p-3 font-mono" data-label="Mã">{{ $course['course_code'] }}</td><td class="p-3" data-label="Tên học phần">{{ $course['name_vi'] }}</td><td class="p-3" data-label="TC">{{ $course['credits'] }}</td><td class="p-3" data-label="Khối kiến thức">{{ $course['knowledge_block'] }}</td><td class="p-3" data-label="Loại">{{ $course['is_mandatory'] ? 'Bắt buộc' : 'Tự chọn' }}@if(!($course['counts_toward_total'] ?? true)) · Ngoài tổng tín chỉ @endif@if($course['elective_group_code'] ?? null) · {{ $course['elective_group_code'] }}@endif</td><td class="p-3" data-label="Học kỳ">{{ $course['suggested_semester'] ?: '—' }}</td></tr>
                        @empty
                            <tr><td colspan="6" class="p-5 text-gray-500">Chưa có học phần.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section id="version-plos" aria-labelledby="version-plos-heading" class="program-detail-section">
            <div class="academic-section-heading"><h2 id="version-plos-heading">Chuẩn đầu ra</h2></div>
            <div class="program-detail-table-wrap">
                <table class="program-detail-table"><thead class="bg-gray-50"><tr><th class="p-3">Mã</th><th class="p-3">Mô tả</th></tr></thead><tbody>
                    @forelse($version->snapshot['plos'] as $plo)
                        <tr class="border-t"><td class="p-3 font-mono" data-label="Mã">{{ $plo['code'] }}</td><td class="p-3" data-label="Mô tả">{{ $plo['description_vi'] }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="p-5 text-gray-500">Chưa có chuẩn đầu ra.</td></tr>
                    @endforelse
                </tbody></table>
            </div>
        </section>

        <section id="version-matrix" aria-labelledby="version-matrix-heading" class="program-detail-section">
            <div class="academic-section-heading"><h2 id="version-matrix-heading">Mức đáp ứng chuẩn đầu ra</h2></div>
            <p class="version-snapshot-note">Mức 1: giới thiệu · Mức 2: củng cố · Mức 3: vận dụng.</p><div class="program-detail-table-wrap">
                <table class="program-detail-table"><thead class="bg-gray-50"><tr><th class="p-3">Học phần</th><th class="p-3">Chuẩn đầu ra</th><th class="p-3">Mức</th></tr></thead><tbody>
                    @forelse($version->snapshot['matrix'] as $entry)
                        <tr class="border-t"><td class="p-3 font-mono" data-label="Học phần">{{ $entry['course_code'] }}</td><td class="p-3 font-mono" data-label="Chuẩn đầu ra">{{ $entry['plo_code'] }}</td><td class="p-3" data-label="Mức">{{ $entry['contribution_level'] }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="p-5 text-gray-500">Chưa có mức đáp ứng.</td></tr>
                    @endforelse
                </tbody></table>
            </div>
        </section>
    </div>
</x-app-layout>
