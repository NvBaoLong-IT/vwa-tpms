<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">Nhập yêu cầu cấp văn bằng</h2></x-slot>
    <div class="py-8 max-w-5xl mx-auto px-4 space-y-4">
        <p>Các cột: MaSV, LoaiVanBang, SoPhoi, NgayCap, MaKhoa, SoQuyetDinh. Số quyết định không bắt buộc cho chứng chỉ.</p>
        <x-input-error :messages="$errors->all()" />
        <form method="POST" action="{{ route('diplomas.import') }}" enctype="multipart/form-data" class="flex flex-wrap gap-3">
            @csrf<input type="file" name="file" accept=".xlsx,.xls,.csv" aria-label="Danh sách tốt nghiệp" class="block border rounded p-2" required>
            <x-primary-button>Gửi danh sách chờ duyệt</x-primary-button>
        </form>
        <h3 class="font-semibold">Các lần nhập gần đây</h3>
        <p>Danh sách chỉ tạo yêu cầu chờ duyệt. <a class="text-indigo-700 underline" href="{{ route('diplomas.import.form') }}">Tải lại kết quả</a></p>
        <div class="overflow-x-auto"><table class="w-full text-left bg-white">
            <thead><tr><th class="p-3">Tệp</th><th class="p-3">Trạng thái</th><th class="p-3">Đã nhập</th><th class="p-3">Lỗi</th></tr></thead>
            <tbody>@forelse($imports as $import)<tr class="border-t">
                <td class="p-3">{{ $import->original_name }}</td>
                <td class="p-3">{{ ['queued'=>'Chờ xử lý','processing'=>'Đang xử lý','completed'=>'Hoàn tất','failed'=>'Thất bại'][$import->status] ?? 'Chưa xác định' }}</td>
                <td class="p-3">{{ $import->rows_imported }}</td>
                <td class="p-3">@if($import->failure_message)<p>{{ $import->failure_message }}</p>@endif
                    @if($import->errors)<details><summary>{{ $import->error_count }} lỗi</summary><ul>@foreach($import->errors as $error)<li>{{ $error }}</li>@endforeach</ul></details>@else{{ $import->error_count }}@endif
                </td>
            </tr>@empty<tr><td colspan="4" class="p-3">Chưa có lần nhập.</td></tr>@endforelse</tbody>
        </table></div>
    </div>
</x-app-layout>
