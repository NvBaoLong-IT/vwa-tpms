<x-app-layout>
    <x-slot name="header">
        <div class="profile-heading">
            <div>
                <p class="profile-eyebrow">Tài khoản cá nhân</p>
                <h1>Hồ sơ của tôi</h1>
                <p>Thông tin tài khoản và bảo mật đăng nhập.</p>
            </div>
            @can('dashboard.view')
                <a class="profile-back" href="{{ route('dashboard') }}">Về bảng điều hành <span aria-hidden="true">&rarr;</span></a>
            @endcan
        </div>
    </x-slot>

    <div class="profile-page app-shell">
        <section class="profile-identity" aria-label="Thông tin tài khoản">
            <span class="profile-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
            <div class="profile-identity-main">
                <h2>{{ $user->name }}</h2>
                <p>{{ $user->email }}</p>
            </div>
            <dl class="profile-account-meta">
                <div><dt>Vai trò</dt><dd>{{ $user->getRoleNames()->map(fn ($role) => config('role_labels')[$role] ?? $role)->join(', ') ?: 'Chưa được phân công' }}</dd></div>
                <div><dt>Ngày tạo tài khoản</dt><dd>{{ $user->created_at?->format('d/m/Y') ?? 'Chưa có thông tin' }}</dd></div>
            </dl>
        </section>

        @include('profile.partials.update-profile-information-form')
        @include('profile.partials.update-password-form')
        @include('profile.partials.delete-user-form')
    </div>
</x-app-layout>
