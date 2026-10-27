<x-app-layout>
    <x-slot name="header"><div class="academic-heading"><h1>Bản nhập Excel CTĐT</h1><a class="academic-button academic-button-primary" href="{{ route('programs.import.form') }}">Tạo bản nhập</a></div></x-slot>
    <div class="academic-page program-detail-page app-shell">
        <p>{{ auth()->user()->hasRole('Lãnh đạo phòng') ? 'Phạm vi: tất cả bản nhập. Người duyệt phải khác người trình.' : 'Phạm vi: các bản nhập do bạn tạo.' }}</p>
        <form method="GET" class="flex items-end gap-3"><label>Trạng thái<select name="status" class="block rounded border-gray-300"><option value="">Tất cả</option>@foreach(['review'=>'Đang xem trước','submitted'=>'Chờ lãnh đạo duyệt','approved'=>'Đã duyệt, chờ nhập','returned'=>'Trả về bổ sung','imported'=>'Đã nhập'] as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select></label><button class="academic-button academic-button-secondary">Lọc bản nhập</button></form>
        <div class="program-detail-table-wrap"><table class="program-detail-table"><thead><tr><th>CTĐT</th><th>Tệp</th><th>Người trình</th><th>Trạng thái</th><th>Người duyệt</th><th></th></tr></thead><tbody>
            @forelse($imports as $import)<tr><td data-label="CTĐT">{{ $import->program_code }}</td><td data-label="Tệp">{{ $import->filename }}</td><td data-label="Người trình">{{ $import->creator?->name }}</td><td data-label="Trạng thái">{{ $import->statusLabel() }}{{ $import->status !== 'imported' && $import->expires_at->isPast() ? ' · Đã hết hạn' : '' }}</td><td data-label="Người duyệt">{{ $import->reviewer?->name ?? 'Chưa duyệt' }}</td><td><a class="program-detail-link" href="{{ route('programs.import.preview', $import) }}">Xem bản nhập {{ $import->program_code }}</a></td></tr>@empty<tr><td colspan="6">Chưa có bản nhập trong phạm vi này.</td></tr>@endforelse
        </tbody></table></div>
        {{ $imports->links() }}
    </div>
</x-app-layout>
