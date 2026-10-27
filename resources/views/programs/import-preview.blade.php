<x-app-layout>
    <x-slot name="header"><div class="academic-heading"><h1>Xem trước và duyệt bản nhập CTĐT</h1><a class="academic-button academic-button-secondary" href="{{ route('programs.imports.index') }}">Danh sách bản nhập</a></div></x-slot>
    <div class="academic-page program-detail-page app-shell">
        <x-input-error :messages="$errors->all()" />
        <section class="program-detail-overview space-y-3">
            <h2>{{ $snapshot['program']['code'] }} · {{ $snapshot['program']['name_vi'] }}</h2>
            <p><strong>{{ $excelImport->statusLabel() }}</strong> · Người trình: {{ $excelImport->creator?->name }} · Tệp: {{ $excelImport->filename }}</p>
            <p>Hạn xử lý: {{ $excelImport->expires_at->format('d/m/Y H:i') }}. Quy trình: xem trước → trình duyệt → lãnh đạo khác người trình phê duyệt → người trình nhập dữ liệu.</p>
            <p>Bản nhập thay thế danh sách học phần, chuẩn đầu ra, nhóm tự chọn và ma trận trong bản đang soạn; các phiên bản đã lưu được giữ nguyên. Việc duyệt bản nhập chưa thay thế bước duyệt phiên bản CTĐT trước công bố.</p>
            @if($excelImport->review_note)<p><strong>Ý kiến của {{ $excelImport->reviewer?->name }}:</strong> {{ $excelImport->review_note }}</p>@endif
            @foreach($reconciliation['issues'] as $issue)<p role="alert">{{ $issue['message'] }}</p>@endforeach
            @if($stale)<p role="alert">Dữ liệu đã thay đổi sau khi xem trước. Hãy tải lại tệp để đối chiếu và trình duyệt lại.</p>@endif
            @if($excelImport->status === 'imported')
                <p>Đã nhập thành công lúc {{ $excelImport->imported_at?->format('d/m/Y H:i') }}. Bảng bên dưới là nội dung trước–sau của bản nhập đã được duyệt.</p>
            @elseif($excelImport->expires_at->isFuture())
                @if(in_array($excelImport->status, ['review', 'returned'], true) && $excelImport->created_by === auth()->id() && ! $stale)
                    <form method="POST" action="{{ route('programs.import.confirm', $excelImport) }}" class="space-y-3">@csrf
                        <label class="block"><input type="checkbox" name="confirm" value="1" required> Tôi đã kiểm tra thay đổi và trình lãnh đạo phê duyệt bản nhập này.</label>
                        <button class="academic-button academic-button-primary">Trình duyệt bản nhập</button>
                    </form>
                @endif
                @if($excelImport->status === 'submitted' && auth()->user()->hasRole('Lãnh đạo phòng') && $excelImport->created_by !== auth()->id())
                    <form method="POST" action="{{ route('programs.import.review', $excelImport) }}" class="space-y-3">@csrf
                        <label class="block">Ý kiến duyệt bản nhập<textarea name="review_note" class="block w-full rounded border-gray-300" required maxlength="4000">{{ old('review_note') }}</textarea></label>
                        @if(! $stale)<button name="decision" value="approve" class="academic-button academic-button-primary">Phê duyệt bản nhập</button>@endif
                        <button name="decision" value="return" class="academic-button academic-button-secondary">Trả về bản nhập</button>
                    </form>
                @endif
                @if($excelImport->status === 'approved' && $excelImport->created_by === auth()->id() && ! $stale)
                    <form method="POST" action="{{ route('programs.import.apply', $excelImport) }}" class="space-y-3">@csrf
                        <label class="block"><input type="checkbox" name="confirm" value="1" required> Tôi xác nhận ghi đúng nội dung đã được lãnh đạo duyệt vào bản đang soạn.</label>
                        <button class="academic-button academic-button-primary">Nhập dữ liệu đã được duyệt</button>
                    </form>
                @endif
            @else<p role="alert">Bản nhập đã hết hạn. Hãy tải lại tệp và trình duyệt lại.</p>@endif
            <a class="academic-button academic-button-secondary" href="{{ route('programs.import.form') }}">Tải tệp khác / xem trước lại</a>
        </section>
        @include('programs.partials.elective-groups', ['editable' => false])
        <section class="program-detail-section"><h2>Các thay đổi dự kiến ({{ count($changes) }})</h2>@include('programs.partials.snapshot-changes')</section>
    </div>
</x-app-layout>
