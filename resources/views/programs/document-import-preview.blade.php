<x-app-layout>
    <x-slot name="header"><div class="academic-heading"><div><p class="academic-eyebrow">Bản nháp AI · {{ $documentImport->original_name }}</p><h1>Kiểm tra dữ liệu trước khi nhập</h1><p>{{ $program ? $program->name_vi.' · '.$program->code : 'Tạo chương trình đào tạo mới' }}</p></div><a class="academic-button academic-button-secondary" href="{{ $program ? route('programs.document-imports.create', $program) : route('programs.document-imports.new') }}">Lượt khác</a></div></x-slot>
    @php($payload = $documentImport->payload ?? [])
    <div class="academic-page app-shell space-y-5">
        <x-input-error :messages="$errors->all()" />
        <section class="program-detail-section"><h2>Đối chiếu tín chỉ bản nháp</h2><p>{{ $creditScopeLabel }}</p><p><strong>{{ $reconciliation['effective_credits'] }} / {{ $reconciliation['declared_credits'] }} tín chỉ</strong> · Thiếu {{ $reconciliation['missing_credits'] }} · Ngoài tổng: {{ $reconciliation['outside_total_credits'] }} tín chỉ.</p><p>Học phần tạm bỏ qua vẫn được giữ trong bản nháp để bổ sung. Lưu bản nháp chưa đồng nghĩa đủ điều kiện trình duyệt.</p></section>@foreach($creditIssues ?? [] as $issue)<p role="alert" class="alert {{ $issue['severity'] === 'error' ? 'alert-error' : 'alert-info' }}">{{ $issue['message'] }}</p>@endforeach
        @if($comparisonError)<p class="alert alert-error" role="alert">{{ $comparisonError }}</p>@endif
        @if(in_array($documentImport->status, ['review', 'returned', 'submitted', 'approved', 'imported'], true))
            <section class="program-detail-section"><h2>Đối chiếu thay đổi khi gộp bản nhập AI</h2><p>Học phần, chuẩn đầu ra và ô ma trận không có trong bản nhập vẫn được giữ lại. Quan hệ học trước hoặc song hành chỉ được bỏ khi tệp có chỉ định xóa rõ ràng. Bảng dưới hiển thị đúng kết quả thêm, sửa, bỏ của thao tác gộp.</p>@include('programs.partials.snapshot-changes')</section>
        @endif
        @if($documentImport->status === 'processing')
            <div role="status" class="alert alert-info">Tài liệu đang chờ hoặc đang được trích xuất. <a href="{{ request()->url() }}" class="underline">Tải lại để xem kết quả</a>. Chỉ có thể xác nhận sau khi bản nháp đã sẵn sàng.</div>
        @endif
        <p><a class="text-indigo-700" href="{{ $program ? route('programs.document-imports.download', [$program, $documentImport]) : route('programs.document-imports.new.download', $documentImport) }}">Tải lại file gốc</a></p>
        @if($documentImport->status === 'failed')<div class="alert alert-error" role="alert">{{ $documentImport->error_message }}</div>@endif
        <section class="border bg-white p-5"><h2 class="text-lg font-semibold mb-3">Thông tin chương trình</h2><dl class="grid md:grid-cols-3 gap-3 text-sm">@foreach(($payload['program'] ?? []) as $key => $value)<div><dt class="text-gray-500">{{ ['code'=>'Mã','name_vi'=>'Tên tiếng Việt','name_en'=>'Tên tiếng Anh','degree_level'=>'Trình độ','delivery_mode'=>'Hình thức','total_credits'=>'Tổng tín chỉ','elective_credits_required'=>'Tín chỉ tự chọn','decision_number'=>'Số quyết định','decision_date'=>'Ngày quyết định'][$key] ?? $key }}</dt><dd class="font-medium">{{ is_scalar($value) && $value !== null && $value !== '' ? $value : 'Chưa xác định' }}</dd></div>@endforeach</dl></section>
        @if($documentImport->status !== 'failed')
        <section class="border border-amber-300 bg-amber-50 p-5">
            <h2 class="font-semibold mb-2">Những điểm cần kiểm tra sau khi gộp dữ liệu ({{ count($reviewSummary['issues']) }} nhóm)</h2>
            <p class="text-sm mb-3">Đánh giá trên toàn bộ bản nháp. Nhận xét riêng từng trang được thu gọn bên dưới.</p>
            <ul class="space-y-3">
                @forelse($reviewSummary['issues'] as $issue)
                <li><strong>{{ ['high'=>'Cần xử lý','medium'=>'Cần đối chiếu','low'=>'Thông tin bổ sung'][$issue['severity']] }}</strong>: {{ $issue['message'] }}
                    @if($issue['items'])<details class="mt-1"><summary class="cursor-pointer text-indigo-700">Xem danh sách mã</summary><ul class="list-disc pl-5 mt-2">@foreach($issue['items'] as $item)<li>{{ $item }}</li>@endforeach</ul></details>@endif
                </li>
                @empty<li>Chưa phát hiện dữ liệu thiếu hoặc chênh lệch theo các kiểm tra hiện có. Vẫn cần đối chiếu file gốc.</li>@endforelse
            </ul>
            @if($reviewSummary['details'])
            <details class="mt-4 border-t border-amber-200 pt-3">
                <summary class="cursor-pointer font-medium">Nhận xét AI theo từng trang ({{ count($reviewSummary['details']) }} nhận xét sau khi gộp trùng)</summary>
                <p class="text-sm my-2">Ví dụ “trang này không có tên chương trình” chỉ mô tả giới hạn của trang, không có nghĩa toàn bộ tài liệu thiếu thông tin. Các nhận xét nội dung khác vẫn được giữ để đối chiếu.</p>
                <ul class="list-disc pl-5 space-y-2">@foreach($reviewSummary['details'] as $detail)<li>{{ $detail['message'] }} @if($detail['sources'])<small>({{ implode('; ', $detail['sources']) }})</small>@endif</li>@endforeach</ul>
            </details>
            @endif
        </section>
        @endif
        <section class="border bg-white p-5"><h2 class="text-lg font-semibold mb-3">Học phần ({{ count($payload['courses'] ?? []) }})</h2><div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="border-b text-left"><th class="p-2">Mã</th><th class="p-2">Tên</th><th class="p-2">TC</th><th class="p-2">Khối</th><th class="p-2">Loại</th><th class="p-2">Phạm vi / trạng thái</th><th class="p-2">HK</th><th class="p-2">Học trước</th><th class="p-2">Học song hành</th></tr></thead><tbody>@forelse($payload['courses'] ?? [] as $course)<tr class="border-b"><td class="p-2 font-mono">{{ $course['course_code'] ?? '' }}</td><td class="p-2">{{ $course['name_vi'] ?? '' }}</td><td class="p-2">{{ $course['credits'] ?? '?' }}</td><td class="p-2">{{ $course['knowledge_block'] ?? 'Chưa xác định' }}</td><td class="p-2">{{ ($course['is_mandatory'] ?? null) === true ? 'Bắt buộc' : (($course['is_mandatory'] ?? null) === false ? 'Tự chọn' : 'Chưa xác định') }}</td><td class="p-2">{{ ($course['counts_toward_total'] ?? true) ? 'Trong tổng' : 'Ngoài tổng' }}@if($course['is_deferred'] ?? false) · Tạm bỏ qua @endif</td><td class="p-2">{{ $course['suggested_semester'] ?? '?' }}</td><td class="p-2">{{ $course['prerequisite_course_code'] ?? '—' }}</td><td class="p-2">{{ $course['corequisite_course_code'] ?? '—' }}</td></tr>@empty<tr><td colspan="9" class="p-3 text-gray-500">Không trích xuất được học phần.</td></tr>@endforelse</tbody></table></div></section>
        <section class="border bg-white p-5"><h2 class="text-lg font-semibold mb-3">Chuẩn đầu ra và ma trận</h2><p class="text-sm">{{ count($payload['plos'] ?? []) }} PLO · {{ count($payload['matrix'] ?? []) }} liên kết ma trận</p><div class="mt-3 flex flex-wrap gap-2">@foreach($payload['plos'] ?? [] as $plo)<span class="rounded bg-slate-100 px-2 py-1 text-sm">{{ $plo['code'] ?? '' }}: {{ $plo['description_vi'] ?? '' }}</span>@endforeach</div></section>
        @include('programs.partials.ai-draft-editor')
        <section class="program-detail-overview space-y-3">
            <h2>Phê duyệt bản nhập AI</h2>
            <p><strong>{{ $documentImport->statusLabel() }}</strong> · Người gửi: {{ $documentImport->uploader?->name }}. Quy trình: kiểm tra → trình → lãnh đạo khác người trình duyệt → người trình nhập.</p>
            @if($documentImport->approval_expires_at)<p>Hạn xử lý: {{ $documentImport->approval_expires_at->format('d/m/Y H:i') }}.</p>@endif
            @if($documentImport->review_note)<p>Ý kiến của {{ $documentImport->reviewer?->name }}: {{ $documentImport->review_note }}</p>@endif
            @if($comparisonStale)<p role="alert">Dữ liệu hoặc nội dung bản nhập đã thay đổi từ khi trình duyệt. Chưa thể duyệt/ghi; cần trả về hoặc tải tài liệu và trình duyệt lại.</p>@endif
            @if(in_array($documentImport->status, ['review','returned'], true) && $comparisonHash && !collect($creditIssues ?? [])->contains('severity', 'error') && $documentImport->uploaded_by === auth()->id())
                <form method="POST" action="{{ route('programs.document-imports.submit', $documentImport) }}" class="space-y-3">@csrf
                    <input type="hidden" name="comparison_hash" value="{{ $comparisonHash }}">
                    <label class="block"><input type="checkbox" name="confirm" value="1" required> Tôi đã kiểm tra bảng đối chiếu và trình lãnh đạo duyệt bản nhập này.</label>
                    <button class="academic-button academic-button-primary">Trình duyệt bản nhập AI</button>
                </form>
            @endif
            @if($documentImport->status === 'submitted' && auth()->user()->hasRole('Lãnh đạo phòng') && $documentImport->submitted_by !== auth()->id() && $documentImport->uploaded_by !== auth()->id())
                <form method="POST" action="{{ route('programs.document-imports.review', $documentImport) }}" class="space-y-3">@csrf
                    <label class="block">Ý kiến xét duyệt bản nhập AI<textarea name="review_note" required maxlength="4000" class="block w-full rounded border-gray-300"></textarea></label>
                    @if(! $comparisonStale && $documentImport->approval_expires_at?->isFuture())<button name="decision" value="approve" class="academic-button academic-button-primary">Duyệt bản nhập AI</button>@endif
                    <button name="decision" value="return" class="academic-button academic-button-secondary">Trả về bản nhập AI</button>
                </form>
            @endif
            @if($documentImport->status === 'approved' && ! $comparisonStale && $documentImport->approval_expires_at?->isFuture() && $documentImport->submitted_by === auth()->id())
                <form method="POST" action="{{ $program ? route('programs.document-imports.import', [$program, $documentImport]) : route('programs.document-imports.new.import', $documentImport) }}" class="space-y-3">@csrf
                    <input type="hidden" name="comparison_hash" value="{{ $documentImport->approved_hash }}">
                    <label class="block"><input type="checkbox" name="confirm" value="1" required> Tôi xác nhận ghi đúng bản nhập AI đã được lãnh đạo duyệt.</label>
                    <button class="academic-button academic-button-primary">Nhập bản AI đã được duyệt</button>
                </form>
            @endif
            @if(in_array($documentImport->status, ['submitted','approved'], true) && ($documentImport->submitted_by === auth()->id() || $documentImport->uploaded_by === auth()->id()))<p>Người trình không được tự xét duyệt. Việc duyệt bản nhập chưa thay thế duyệt phiên bản CTĐT trước công bố.</p>@endif
            <a class="academic-button academic-button-secondary" href="{{ route('programs.document-imports.index') }}">Theo dõi bản nhập AI</a>
        </section>

    </div>
</x-app-layout>
