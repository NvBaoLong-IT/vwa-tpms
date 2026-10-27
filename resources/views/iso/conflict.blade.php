<x-app-layout>
    <x-slot name="header"><h1>Không thể thay đổi đợt rà soát</h1></x-slot>
    <div class="academic-page app-shell">
        <div class="alert alert-error" role="alert">
            <strong>Đợt rà soát đã hoàn tất hoặc đã hủy.</strong>
            <p>Thay đổi của bạn chưa được lưu. Bạn vẫn có thể xem lịch sử và tải hồ sơ.</p>
        </div>
        <a class="academic-button academic-button-primary" href="{{ route('iso.show', $workflow) }}">Quay lại hồ sơ rà soát</a>
        <a class="academic-button academic-button-secondary" href="{{ route('iso.index') }}">Danh sách rà soát</a>
    </div>
</x-app-layout>
