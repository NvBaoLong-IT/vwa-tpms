@php
    $notifications = [];
    $errorMessages = [];
    foreach ($errors->getBags() as $bag) {
        $errorMessages = array_merge($errorMessages, $bag->all());
    }
    if (count($errorMessages)) {
        $notifications[] = ['icon' => 'error', 'title' => 'Không thể hoàn tất thao tác', 'text' => implode("\n", array_unique($errorMessages)), 'toast' => false];
    }
    if (session('import_errors')) {
        $notifications[] = ['icon' => 'warning', 'title' => 'Có dòng dữ liệu chưa nhập được', 'text' => implode("\n", session('import_errors')), 'toast' => false];
    }
    foreach (['success' => 'Thành công', 'error' => 'Có lỗi xảy ra', 'warning' => 'Cần lưu ý', 'info' => 'Thông tin'] as $key => $title) {
        if (session($key)) {
            $notifications[] = ['icon' => $key, 'title' => $title, 'text' => (string) session($key), 'toast' => in_array($key, ['success', 'info'])];
        }
    }
    if (session('status')) {
        $statuses = ['profile-updated' => 'Đã cập nhật hồ sơ.', 'password-updated' => 'Đã cập nhật mật khẩu.', 'verification-link-sent' => 'Đã gửi liên kết xác minh email.'];
        $notifications[] = ['icon' => 'success', 'title' => 'Thành công', 'text' => $statuses[session('status')] ?? (string) session('status'), 'toast' => true];
    }
@endphp
@if(count($notifications))
    <div id="flash-notifications" hidden data-notifications="{{ json_encode($notifications, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) }}"></div>
    <noscript>@foreach($notifications as $notification)<div class="alert" role="status">{{ $notification['text'] }}</div>@endforeach</noscript>
@endif
