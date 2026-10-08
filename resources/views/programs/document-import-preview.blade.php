<x-app-layout>
    <x-slot name="header"><div class="academic-heading"><div><p class="academic-eyebrow">Bản nháp AI · {{ $documentImport->original_name }}</p><h1>Kiểm tra dữ liệu trước khi nhập</h1><p>{{ $program ? $program->name_vi.' · '.$program->code : 'Tạo chương trình đào tạo mới' }}</p></div><a class="academic-button academic-button-secondary" href="{{ $program ? route('programs.document-imports.create', $program) : route('programs.document-imports.new') }}">Lượt khác</a></div></x-slot>
    @php($payload = $documentImport->payload ?? [])
    <div class="academic-page app-shell space-y-5">
        @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
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
        <section class="border bg-white p-5"><h2 class="text-lg font-semibold mb-3">Học phần ({{ count($payload['courses'] ?? []) }})</h2><div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="border-b text-left"><th class="p-2">Mã</th><th class="p-2">Tên</th><th class="p-2">TC</th><th class="p-2">Khối</th><th class="p-2">Loại</th><th class="p-2">HK</th></tr></thead><tbody>@forelse($payload['courses'] ?? [] as $course)<tr class="border-b"><td class="p-2 font-mono">{{ $course['course_code'] ?? '' }}</td><td class="p-2">{{ $course['name_vi'] ?? '' }}</td><td class="p-2">{{ $course['credits'] ?? '?' }}</td><td class="p-2">{{ $course['knowledge_block'] ?? 'Chưa xác định' }}</td><td class="p-2">{{ $course['is_mandatory'] === true ? 'Bắt buộc' : ($course['is_mandatory'] === false ? 'Tự chọn' : 'Chưa xác định') }}</td><td class="p-2">{{ $course['suggested_semester'] ?? '?' }}</td></tr>@empty<tr><td colspan="6" class="p-3 text-gray-500">Không trích xuất được học phần.</td></tr>@endforelse</tbody></table></div></section>
        <section class="border bg-white p-5"><h2 class="text-lg font-semibold mb-3">Chuẩn đầu ra và ma trận</h2><p class="text-sm">{{ count($payload['plos'] ?? []) }} PLO · {{ count($payload['matrix'] ?? []) }} liên kết ma trận</p><div class="mt-3 flex flex-wrap gap-2">@foreach($payload['plos'] ?? [] as $plo)<span class="rounded bg-slate-100 px-2 py-1 text-sm">{{ $plo['code'] ?? '' }}: {{ $plo['description_vi'] ?? '' }}</span>@endforeach</div></section>
        @if($documentImport->status === 'review')<form method="POST" action="{{ $program ? route('programs.document-imports.import', [$program, $documentImport]) : route('programs.document-imports.new.import', $documentImport) }}" class="border border-red-200 bg-red-50 p-5 space-y-3">@csrf<label class="flex gap-2 items-start"><input type="checkbox" name="confirm" value="1" required class="mt-1"><span>Tôi đã kiểm tra bản nháp và chịu trách nhiệm xác nhận dữ liệu này được {{ $program ? 'nhập vào chương trình' : 'tạo thành chương trình mới' }}.</span></label><button class="academic-button academic-button-primary">{{ $program ? 'Xác nhận và nhập vào chương trình' : 'Xác nhận và tạo chương trình mới' }}</button></form>@endif
    </div>
</x-app-layout>
