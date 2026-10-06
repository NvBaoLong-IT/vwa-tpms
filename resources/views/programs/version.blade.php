<x-app-layout>
    <x-slot name="header">
        <div class="academic-heading">
            <div><p class="academic-eyebrow">Phiên bản chương trình · K{{ $version->cohort_year }} · Lần {{ $version->revision }}</p><h1>{{ $version->snapshot['program']['name_vi'] }}</h1></div>
            <div class="academic-tools"><a href="{{ route('programs.versions.word', [$program, $version]) }}" class="academic-button academic-button-secondary">Xuất Word</a><a href="{{ route('programs.show', $program) }}" class="academic-button academic-button-secondary">Bản đang soạn</a></div>
        </div>
    </x-slot>
    <div class="academic-page program-detail-page app-shell">
        @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
        <section class="program-detail-overview" aria-labelledby="version-info-title">
            <div class="academic-section-heading"><h2 id="version-info-title">Thông tin phiên bản</h2><span class="academic-status {{ $version->status === 'published' ? 'academic-status-resolved' : '' }}">{{ ['draft'=>'Bản nháp','published'=>'Đang áp dụng','archived'=>'Đã thay thế'][$version->status] ?? $version->status }}</span></div>
            <dl class="student-facts">
                <div><dt>Khóa áp dụng</dt><dd>K{{ $version->cohort_year }}</dd></div><div><dt>Lần sửa</dt><dd>{{ $version->revision }}</dd></div>
                <div><dt>Số quyết định</dt><dd>{{ $version->decision_number }}</dd></div><div><dt>Ngày quyết định</dt><dd>{{ $version->decision_date->format('d/m/Y') }}</dd></div>
                <div><dt>Ngày tạo bản lưu</dt><dd>{{ $version->created_at?->format('d/m/Y H:i') }}</dd></div><div><dt>Ngày công bố</dt><dd>{{ $version->published_at?->format('d/m/Y H:i') ?? 'Chưa công bố' }}</dd></div>
            </dl>
            <p class="version-snapshot-note">Bản lưu gồm thông tin chương trình, học phần, khối kiến thức, chuẩn đầu ra và mức đáp ứng tại thời điểm tạo phiên bản. Thay đổi bản đang soạn không làm thay đổi nội dung đã lưu ở đây.</p>
            @if($version->status === 'draft' && auth()->user()->hasRole('Lãnh đạo phòng'))
                <details class="program-management"><summary>Công bố phiên bản</summary><p>Khi công bố, phiên bản đang áp dụng trước đó của cùng khóa sẽ được chuyển thành “Đã thay thế”. Kiểm tra đối chiếu tín chỉ và nội dung trước khi xác nhận.</p><form method="POST" action="{{ route('programs.versions.publish', [$program, $version]) }}">@csrf<button type="submit" class="academic-button academic-button-primary">Công bố phiên bản</button></form></details>
            @endif
        </section>
        <nav class="program-detail-navigation" aria-label="Nội dung phiên bản"><a href="#version-credits">Đối chiếu tín chỉ</a><a href="#version-courses">Học phần</a><a href="#version-plos">Chuẩn đầu ra</a><a href="#version-matrix">Mức đáp ứng</a></nav>
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
                            <tr class="border-t"><td class="p-3 font-mono" data-label="Mã">{{ $course['course_code'] }}</td><td class="p-3" data-label="Tên học phần">{{ $course['name_vi'] }}</td><td class="p-3" data-label="TC">{{ $course['credits'] }}</td><td class="p-3" data-label="Khối kiến thức">{{ $course['knowledge_block'] }}</td><td class="p-3" data-label="Loại">{{ $course['is_mandatory'] ? 'Bắt buộc' : 'Tự chọn' }}</td><td class="p-3" data-label="Học kỳ">{{ $course['suggested_semester'] ?: '—' }}</td></tr>
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
