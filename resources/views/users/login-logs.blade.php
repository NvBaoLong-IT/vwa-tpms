<x-app-layout>
    <x-slot name="header"><div class="academic-heading"><div><p class="academic-eyebrow">Quản lý hệ thống</p><h1>Nhật ký hoạt động</h1><p>Theo dõi đăng nhập và các thao tác nghiệp vụ của người dùng.</p></div></div></x-slot>
    <div class="academic-page app-shell">
        <p class="editor-intro">Lịch sử đăng nhập trước đây vẫn được giữ. Nhật ký thao tác được ghi từ khi tính năng này được triển khai; chi tiết trước/sau nằm trong lịch sử từng nghiệp vụ.</p>
        <form method="GET" action="{{ route('users.login-logs') }}" class="academic-filters" aria-label="Bộ lọc nhật ký">
            <div class="academic-filter-grid">
                <div class="academic-filter"><label for="activity-user">Người dùng</label><select id="activity-user" name="user_id"><option value="">Tất cả người dùng</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected(request('user_id') == $user->id)>{{ $user->name }}</option>@endforeach</select></div>
                <div class="academic-filter"><label for="activity-module">Phân hệ</label><select id="activity-module" name="module"><option value="">Tất cả phân hệ</option>@foreach($modules as $key => $label)<option value="{{ $key }}" @selected(request('module') === $key)>{{ $label }}</option>@endforeach</select></div>
                <div class="academic-filter"><label for="activity-action">Thao tác</label><select id="activity-action" name="action"><option value="">Tất cả thao tác</option>@foreach($actions as $key => $label)<option value="{{ $key }}" @selected(request('action') === $key)>{{ $label }}</option>@endforeach</select></div>
                <div class="academic-filter"><label for="activity-result">Kết quả</label><select id="activity-result" name="successful"><option value="">Tất cả kết quả</option><option value="1" @selected(request('successful') === '1')>Thành công</option><option value="0" @selected(request('successful') === '0')>Không thành công</option></select></div>
                <div class="academic-filter"><label for="activity-from">Từ ngày</label><input id="activity-from" type="date" name="from" value="{{ request('from') }}"></div>
                <div class="academic-filter"><label for="activity-to">Đến ngày</label><input id="activity-to" type="date" name="to" value="{{ request('to') }}"></div>
            </div>
            <div class="academic-filter-actions"><button class="academic-button academic-button-primary">Lọc nhật ký</button><a class="academic-button academic-button-secondary" href="{{ route('users.login-logs') }}">Xóa lọc</a></div>
        </form>
        <div class="academic-section-heading"><h2>Hoạt động ghi nhận <span class="academic-list-count">{{ number_format($logs->total()) }}</span></h2><span>Theo bộ lọc đang chọn</span></div>
        <div class="academic-table-wrap"><table class="academic-table activity-table"><caption class="sr-only">Nhật ký đăng nhập và thao tác nghiệp vụ</caption><thead><tr><th>Thời gian</th><th>Người dùng</th><th>Phân hệ / thao tác</th><th>Kết quả</th><th>Thông tin đối chiếu</th></tr></thead><tbody>
            @forelse($logs as $log)
                <tr><td data-label="Thời gian">{{ \Carbon\Carbon::parse($log->logged_at)->format('d/m/Y H:i:s') }}</td><td data-label="Người dùng"><strong>{{ $log->actor_name ?: 'Không xác định' }}</strong>@if($log->identifier)<p>{{ $log->identifier }}</p>@endif</td><td data-label="Phân hệ / thao tác"><strong>{{ $actions[$log->action] ?? $log->action }}</strong><p>{{ $modules[$log->module] ?? $log->module }}</p></td><td data-label="Kết quả"><span class="academic-status {{ $log->successful ? 'academic-status-resolved' : 'academic-status-open' }}">{{ $log->successful ? 'Thành công' : 'Không thành công' }}</span></td><td data-label="Thông tin đối chiếu"><span>IP: {{ $log->ip_address ?: '—' }}</span>@if($log->route_name)<details><summary>Chi tiết thao tác</summary><p>Đường dẫn nghiệp vụ: {{ $log->route_name }}</p>@foreach(json_decode($log->subjects ?: '{}', true) ?? [] as $subject => $id)<p>Mã đối tượng {{ $subject }}: {{ $id }}</p>@endforeach<p>Mã phản hồi: {{ $log->status_code }}</p></details>@endif</td></tr>
            @empty<tr><td colspan="5" class="student-empty">Chưa có hoạt động phù hợp với bộ lọc.</td></tr>@endforelse
        </tbody></table></div>
        <div class="academic-pagination">{{ $logs->links() }}</div>
    </div>
</x-app-layout>
