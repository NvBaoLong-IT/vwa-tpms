@php
    $user = Auth::user();
    $navigation = [
        ['Tổng quan', 'dashboard', $user->can('dashboard.view'), 'dashboard'],
        ['Sinh viên', 'academic.students', $user->canAny(['students.view', 'academic.view']), 'academic.student*'],
        ['Học vụ', 'academic.index', $user->can('academic.view'), ['academic.index', 'academic.import*', 'academic.settings']],
        ['Thông báo', 'academic.notifications', $user->can('academic.view'), 'academic.notifications'],
        ['Chương trình', 'programs.index', $user->can('programs.view'), 'programs.*'],
        ['Học phần', 'courses.index', $user->can('programs.view') && $user->can('courses.view'), 'courses.*'],
        ['Rà soát chương trình', 'iso.index', $user->can('iso.view'), 'iso.*'],
        ['Báo cáo', 'reports.index', $user->canAny(['reports.view', 'dashboard.view']), 'reports.*'],
        ['Văn bằng, chứng chỉ', 'diplomas.index', $user->can('diploma.view'), ['diplomas.index', 'diplomas.import.*', 'diplomas.document']],
        ['Lịch sử văn bằng', 'diplomas.audit', $user->can('diploma.view'), 'diplomas.audit'],
        ['Người dùng', 'users.index', $user->can('users.manage'), 'users.index'],
        ['Nhật ký hoạt động', 'users.login-logs', $user->can('users.manage'), 'users.login-logs'],
        ['Khôi phục dữ liệu', 'records.deleted', $user->hasRole('Lãnh đạo phòng'), 'records.*'],
        ['Tình trạng vận hành', 'operations.status', $user->hasRole('Lãnh đạo phòng'), 'operations.*'],
    ];
    $primaryNavigation = array_slice($navigation, 0, 8);
    $managementNavigation = array_filter(array_slice($navigation, 8), fn ($item) => $item[2]);
    $managementActive = collect($managementNavigation)->contains(fn ($item) => request()->routeIs(...(array) $item[3]));
    $isActive = fn ($patterns) => request()->routeIs(...(array) $patterns);
@endphp
<nav x-data="{ open: false, managementOpen: false }" @keydown.escape.window="open = false; managementOpen = false" class="app-nav office-navigation" aria-label="Điều hướng chính">
    <div class="app-shell flex h-16 items-center justify-between gap-4">
        <a href="{{ route('dashboard') }}" class="dashboard-nav-brand shrink-0 rounded-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-700" aria-label="Về bảng điều hành Học viện Phụ nữ Việt Nam"><img src="{{ asset('images/hvpnvn-mark.png') }}" alt="" width="36" height="36"><span><strong>VWA-TPMS</strong><small>Quản trị đào tạo</small></span></a>
        <div class="office-user hidden items-center gap-3 lg:flex"><div class="office-user-name"><strong>{{ $user->name }}</strong><small>{{ $user->getRoleNames()->first() ?: 'Tài khoản nghiệp vụ' }}</small></div><a href="{{ route('profile.edit') }}" class="office-profile">Hồ sơ</a><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="office-logout">Đăng xuất</button></form></div>
        <button type="button" @click="open = !open" :aria-expanded="open.toString()" aria-controls="mobile-navigation" class="btn btn-icon lg:hidden" aria-label="Mở menu điều hướng"><svg x-show="!open" aria-hidden="true" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg><svg x-show="open" aria-hidden="true" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg></button>
    </div>
    <div class="office-menu-bar hidden lg:block"><div class="app-shell office-menu-inner">
        @foreach($primaryNavigation as [$label, $route, $visible, $active])
            @if($visible)<a href="{{ route($route) }}" @class(['office-menu-link', 'is-active' => $isActive($active)]) @if($isActive($active)) aria-current="page" @endif><x-office-nav-icon :route="$route"/><span>{{ $label }}</span></a>@endif
        @endforeach
        @if(count($managementNavigation))
            <div class="office-management" @click.outside="managementOpen = false">
                <button type="button" @click="managementOpen = !managementOpen" :aria-expanded="managementOpen.toString()" aria-controls="management-navigation" @class(['office-menu-link', 'is-active' => $managementActive])><x-office-nav-icon route="users.index"/><span>Quản lý & hồ sơ</span><span class="office-chevron" aria-hidden="true">⌄</span></button>
                <div id="management-navigation" class="office-management-panel" x-show="managementOpen" x-cloak>
                    <p>QUẢN LÝ & HỒ SƠ</p>
                    @foreach($managementNavigation as [$label, $route, $visible, $active])
                        <a href="{{ route($route) }}" @class(['office-menu-link', 'is-active' => $isActive($active)]) @if($isActive($active)) aria-current="page" @endif><x-office-nav-icon :route="$route"/><span>{{ $label }}</span></a>
                    @endforeach
                </div>
            </div>
        @endif
    </div></div>
    <div id="mobile-navigation" x-show="open" x-cloak class="border-t border-slate-200 bg-white lg:hidden"><div class="app-shell space-y-1 py-3">
        @foreach($navigation as [$label, $route, $visible, $active])
            @if($visible)<a href="{{ route($route) }}" @class(['office-menu-link', 'is-active' => $isActive($active)]) @if($isActive($active)) aria-current="page" @endif><x-office-nav-icon :route="$route"/><span>{{ $label }}</span></a>@endif
        @endforeach
        <x-responsive-nav-link :href="route('profile.edit')" :active="request()->routeIs('profile.*')">Hồ sơ</x-responsive-nav-link>
        <div class="mt-3 flex items-center justify-between border-t border-slate-200 pt-3"><span class="text-sm font-medium text-slate-700">{{ $user->name }}</span><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="btn btn-quiet">Đăng xuất</button></form></div>
    </div></div>
</nav>
