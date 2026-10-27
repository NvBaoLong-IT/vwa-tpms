<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold">Khôi phục dữ liệu đã xóa</h1></x-slot>
    <div class="mx-auto max-w-7xl p-6 space-y-4">
        <p>Dữ liệu được xóa tạm và vẫn giữ liên kết, lịch sử. Khôi phục không thay đổi trạng thái hủy văn bằng hoặc tiêu hủy phôi.</p>
        <form method="GET" action="{{ route('records.deleted') }}" class="flex gap-3">
            <label for="type">Loại dữ liệu</label>
            <select id="type" name="type" class="rounded border-gray-300">
                @foreach($types as $key => $definition)<option value="{{ $key }}" @selected($type === $key)>{{ $definition[1] }}</option>@endforeach
            </select>
            <x-primary-button>Lọc</x-primary-button>
        </form>
        <h2 class="font-semibold">{{ $label }}</h2>
        <div class="overflow-x-auto bg-white"><table class="w-full text-left">
            <thead><tr><th class="p-3">Bản ghi</th><th class="p-3">Ngày xóa</th><th class="p-3">Thao tác</th></tr></thead>
            <tbody>@forelse($records as $record)<tr class="border-t">
                <td class="p-3">{{ $record->$nameField }}</td><td class="p-3">{{ $record->deleted_at->format('d/m/Y H:i') }}</td>
                <td class="p-3"><form method="POST" action="{{ route('records.restore', [$type, $record->id]) }}">@csrf<x-primary-button>Khôi phục</x-primary-button></form></td>
            </tr>@empty<tr><td colspan="3" class="p-3">Không có dữ liệu đã xóa.</td></tr>@endforelse</tbody>
        </table></div>
        {{ $records->links() }}
    </div>
</x-app-layout>
