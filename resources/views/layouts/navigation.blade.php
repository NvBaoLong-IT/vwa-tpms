@php
    $user = Auth::user();
    $navigation = [
        ['Tổng quan', 'dashboard', $user->can('dashboard.view'), 'dashboard'],
        ['Sinh viên', 'academic.students', $user->canAny(['students.view', 'academic.view']), 'academic.student*'],
        ['Học vụ', 'academic.index', $user->can('academic.view'), 'academic.index'],
        ['Thông báo', 'academic.notifications', $user->can('academic.view'), 'academic.notifications'],
        ['Chương trình', 'programs.index', $user->can('programs.view'), 'programs.*'],
        ['Học phần', 'courses.index', $user->can('programs.view') && $user->can('courses.view'), 'courses.*'],
        ['Rà soát chương trình', 'iso.index', $user->can('iso.view'), 'iso.index'],
        ['Báo cáo', 'reports.index', $user->canAny(['reports.view', 'dashboard.view']), 'reports.*'],
        ['Văn bằng, chứng chỉ', 'diplomas.index', $user->can('diploma.view'), 'diplomas.index'],
        ['Lịch sử văn bằng', 'diplomas.audit', $user->can('diploma.view'), 'diplomas.audit'],
        ['Người dùng', 'users.index', $user->can('users.manage'), 'users.index'],
        ['Lịch sử đăng nhập', 'users.login-logs', $user->can('users.manage'), 'users.login-logs'],
    ];
@endphp
<nav x-data="{ open: false }" class="app-nav border-b border-slate-200" aria-label="Điều hướng chính">
    <div class="app-shell flex h-16 items-center justify-between gap-4">
        <a href="{{ route('dashboard') }}" class="dashboard-nav-brand shrink-0 rounded-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-700" aria-label="Về bảng điều hành Học viện Phụ nữ Việt Nam"><img src="{{ asset('images/hvpnvn-mark.png') }}" alt="" width="36" height="36"><span><strong>VWA-TPMS</strong><small>Quản trị đào tạo</small></span></a>
        <div class="hidden items-center gap-3 lg:flex"><span class="text-sm font-medium text-slate-700">{{ $user->name }}</span><a href="{{ route('profile.edit') }}" class="text-sm text-indigo-700">Hồ sơ</a><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="btn btn-quiet">Đăng xuất</button></form></div>
        <button type="button" @click="open = !open" :aria-expanded="open.toString()" aria-controls="mobile-navigation" class="btn btn-icon lg:hidden" aria-label="Mở menu điều hướng"><svg x-show="!open" aria-hidden="true" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg><svg x-show="open" aria-hidden="true" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg></button>
    </div>
    <div class="hidden border-t border-slate-100 lg:block"><div class="app-shell flex flex-wrap items-center gap-x-5 gap-y-1 py-1">
        @foreach($navigation as [$label, $route, $visible, $active])
            @if($visible)<x-nav-link :href="route($route)" :active="request()->routeIs($active)">{{ $label }}</x-nav-link>@endif
        @endforeach
    </div></div>
    <div id="mobile-navigation" x-show="open" x-cloak class="border-t border-slate-200 bg-white lg:hidden"><div class="app-shell space-y-1 py-3">
        @foreach($navigation as [$label, $route, $visible, $active])
            @if($visible)<x-responsive-nav-link :href="route($route)" :active="request()->routeIs($active)">{{ $label }}</x-responsive-nav-link>@endif
        @endforeach
        <x-responsive-nav-link :href="route('profile.edit')" :active="request()->routeIs('profile.*')">Hồ sơ</x-responsive-nav-link>
        <div class="mt-3 flex items-center justify-between border-t border-slate-200 pt-3"><span class="text-sm font-medium text-slate-700">{{ $user->name }}</span><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="btn btn-quiet">Đăng xuất</button></form></div>
    </div></div>
</nav>
