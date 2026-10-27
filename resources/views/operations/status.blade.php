<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold">Tình trạng vận hành</h1></x-slot>
    <div class="max-w-7xl mx-auto p-6 space-y-5">
        <p>Thời điểm kiểm tra: {{ now()->format('d/m/Y H:i') }}. <a class="text-indigo-700 underline" href="{{ route('operations.status') }}">Tải lại</a></p>
        <section class="rounded border bg-white p-4 space-y-2">
            <h2 class="font-semibold">Lịch tự động</h2>
            <p>{{ $schedulerHealthy ? 'Đang nhận tín hiệu lịch tự động.' : 'Chưa nhận hoặc đã quá 35 phút chưa nhận tín hiệu lịch tự động. Cần kiểm tra máy chủ.' }}</p>
            <p>Lần hoàn tất gần nhất: {{ $heartbeat?->finished_at?->format('d/m/Y H:i') ?? 'Chưa ghi nhận' }}</p>
            <p>Thao tác cứu hộ trên web không xác nhận lịch tự động của máy chủ đã hoạt động.</p>
        </section>
        <section class="rounded border bg-white p-4 space-y-2">
            <h2 class="font-semibold">Sao lưu</h2>
            <p>{{ !$backupReadable ? 'Không đọc được nơi lưu bản sao. Cần kiểm tra quyền truy cập.' : ($backupHealthy ? 'Có bản sao được ghi trong 26 giờ gần nhất.' : 'Chưa có bản sao hoặc bản sao đã quá 26 giờ. Cần kiểm tra sao lưu.') }}</p>
            <p>Bản sao gần nhất: {{ $lastBackup?->format('d/m/Y H:i') ?? 'Chưa ghi nhận' }}</p>
            <p>Thông tin này chỉ kiểm tra thời điểm tệp tại nơi lưu đã cấu hình. Cần kiểm tra riêng nội dung khôi phục và bản sao ngoài máy.</p>
        </section>
        <section class="rounded border bg-white p-4 space-y-2">
            <h2 class="font-semibold">Xử lý danh sách và tài liệu</h2>
            <p>{{ $queueIsSync ? 'Đang xử lý ngay khi gửi yêu cầu.' : 'Đang dùng hàng đợi xử lý nền; cần tiến trình xử lý trên máy chủ.' }}</p>
            <p>Tài liệu AI đang chờ/đang xử lý: {{ $pendingDocuments }}. Lượt nhập đang chờ/đang xử lý: {{ $pendingImports }}. Lượt nhập thất bại: {{ $failedImports }}.</p>
            <p>Các số liệu là tổng toàn hệ thống. Nếu trạng thái chờ kéo dài, người vận hành cần kiểm tra tiến trình xử lý.</p>
        </section>
        <section class="space-y-3">
            <h2 class="font-semibold">20 lượt chạy nghiệp vụ gần nhất</h2>
            <div class="overflow-x-auto"><table class="w-full bg-white text-left">
                <thead><tr><th class="p-3">Tác vụ</th><th class="p-3">Kỳ</th><th class="p-3">Trạng thái</th><th class="p-3">Bắt đầu</th><th class="p-3">Kết thúc</th></tr></thead>
                <tbody>@forelse($runs as $run)<tr class="border-t">
                    <td class="p-3">{{ \App\Services\WebScheduleService::TASKS[$run->task] ?? 'Tác vụ khác' }}</td><td class="p-3">{{ $run->period }}</td>
                    <td class="p-3">{{ ['waiting'=>'Chờ xử lý','running'=>'Đang chạy','completed'=>'Hoàn tất','failed'=>'Thất bại'][$run->status] ?? 'Chưa xác định' }}</td>
                    <td class="p-3">{{ $run->started_at?->format('d/m/Y H:i') }}</td><td class="p-3">{{ $run->finished_at?->format('d/m/Y H:i') }}</td>
                </tr>@empty<tr><td colspan="5" class="p-3">Chưa có lượt chạy nghiệp vụ.</td></tr>@endforelse</tbody>
            </table></div>
        </section>
    </div>
</x-app-layout>
