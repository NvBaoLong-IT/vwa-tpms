<x-app-layout>
    <x-slot name="header">
        <div class="academic-heading"><div><p class="academic-eyebrow">Quản trị hệ thống</p><h1>Quản lý người dùng</h1><p>Theo dõi tài khoản, vai trò và trạng thái truy cập.</p></div><div class="academic-heading-links"><a href="{{ route('users.login-logs') }}">Nhật ký hoạt động &rarr;</a></div></div>
    </x-slot>
    <div class="academic-page user-directory-page app-shell">
        <section aria-labelledby="user-create-title">
            <details class="program-management" @if($errors->hasAny(['name','email'])) open @endif>
                <summary id="user-create-title">Tạo tài khoản mới</summary>
                <p>Tài khoản mới sử dụng mật khẩu tạm thời và cần đổi mật khẩu ở lần đăng nhập đầu tiên.</p>
                <form method="POST" action="{{ route('users.store') }}" class="user-create-form">
                    @csrf
                    <label class="program-form-field" for="user-name">Họ tên<input id="user-name" name="name" value="{{ old('name') }}" maxlength="255" required autocomplete="name"><x-input-error :messages="$errors->get('name')" /></label>
                    <label class="program-form-field" for="user-email">Email đăng nhập<input id="user-email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email"><x-input-error :messages="$errors->get('email')" /></label>
                    <label class="program-form-field" for="user-role">Vai trò<select id="user-role" name="role">@foreach($roles as $role)<option value="{{ $role->name }}" @selected(old('role', 'Xem') === $role->name)>{{ config('role_labels')[$role->name] ?? $role->name }}</option>@endforeach</select><x-input-error :messages="$errors->get('role')" /></label>
                    <button type="submit" class="academic-button academic-button-primary">Tạo tài khoản</button>
                </form>
            </details>
        </section>
        <section class="user-directory-list" aria-labelledby="user-list-title">
            <div class="academic-section-heading"><h2 id="user-list-title">Danh sách tài khoản <span class="academic-list-count">{{ number_format($users->total()) }}</span></h2><span>{{ $users->total() ? $users->firstItem().'–'.$users->lastItem().' / '.$users->total() : 'Chưa có tài khoản' }}</span></div>
            <div class="academic-table-wrap"><table class="academic-table program-table">
                <caption class="sr-only">Tài khoản, vai trò, trạng thái và thao tác quản lý</caption>
                <thead><tr><th scope="col">Tài khoản</th><th scope="col">Vai trò</th><th scope="col">Trạng thái truy cập</th><th scope="col">Quản lý</th></tr></thead>
                <tbody>@forelse($users as $user)<tr>
                    <td data-label="Tài khoản"><strong>{{ $user->name }}</strong>@if($user->id === auth()->id())<span class="academic-status user-self">Bạn</span>@endif<small>{{ $user->email }}</small><small>Ngày tạo: {{ $user->created_at?->format('d/m/Y') }}</small></td>
                    <td data-label="Vai trò">{{ $user->roles->map(fn ($role) => config('role_labels')[$role->name] ?? $role->name)->join(', ') ?: 'Chưa gán vai trò' }}</td>
                    <td data-label="Trạng thái truy cập"><span class="academic-status {{ $user->is_active ? 'academic-status-resolved' : 'academic-status-open' }}">{{ $user->is_active ? 'Đang hoạt động' : 'Đã khóa' }}</span><small>{{ $user->must_change_password ? 'Cần đổi mật khẩu lần đầu' : 'Không yêu cầu đổi mật khẩu' }}</small></td>
                    <td data-label="Quản lý">
                        <details class="user-manage"><summary>Đổi vai trò</summary>
                            <form method="POST" action="{{ route('users.update', $user) }}" class="user-manage-form">
                                @csrf @method('PUT')
                                <input type="hidden" name="is_active" value="{{ $user->is_active ? 1 : 0 }}">
                                <label for="user-role-{{ $user->id }}">Vai trò của {{ $user->name }}</label>
                                <select id="user-role-{{ $user->id }}" name="role">@foreach($roles as $role)<option value="{{ $role->name }}" @selected($user->roles->first()?->name === $role->name)>{{ config('role_labels')[$role->name] ?? $role->name }}</option>@endforeach</select>
                                <button type="submit" class="academic-button academic-button-secondary">Lưu vai trò</button>
                            </form>
                        </details>
                        <details class="user-manage"><summary @class(['user-lock-action'=>$user->is_active])>{{ $user->is_active ? 'Khóa tài khoản' : 'Mở tài khoản' }}</summary>
                            <p>{{ $user->is_active ? 'Tài khoản này sẽ bị chặn truy cập hệ thống.' : 'Cho phép tài khoản này truy cập lại hệ thống.' }}</p>
                            <form method="POST" action="{{ route('users.update', $user) }}">
                                @csrf @method('PUT')
                                <input type="hidden" name="role" value="{{ $user->roles->first()?->name }}">
                                <input type="hidden" name="is_active" value="{{ $user->is_active ? 0 : 1 }}">
                                <button type="submit" class="academic-button academic-button-secondary">{{ $user->is_active ? 'Xác nhận khóa' : 'Xác nhận mở' }}</button>
                            </form>
                        </details>
                    </td>
                </tr>@empty<tr><td colspan="4" class="academic-empty">Chưa có tài khoản.</td></tr>@endforelse</tbody>
            </table></div>
            <div class="academic-pagination">{{ $users->links() }}</div>
        </section>
    </div>
</x-app-layout>
