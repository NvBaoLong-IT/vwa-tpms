<x-guest-layout>
    <div class="mb-8"><p class="text-xs font-bold uppercase tracking-[.16em] text-cyan-700">Cổng quản trị đào tạo</p><h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900">Đăng nhập hệ thống</h1><p class="mt-2 text-sm leading-6 text-slate-600">Sử dụng email hoặc tên tài khoản được cấp để tiếp tục.</p></div>
    <x-auth-session-status class="mb-4" :status="session('status')" />
    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf
        <div><x-input-label for="email" value="Email hoặc tên tài khoản" /><x-text-input id="email" class="mt-2 block w-full" type="text" name="email" :value="old('email')" required autofocus autocomplete="username" aria-describedby="email-error" /><x-input-error id="email-error" :messages="$errors->get('email')" class="mt-2" /></div>
        <div><div class="flex items-center justify-between"><x-input-label for="password" value="Mật khẩu" />@if (Route::has('password.request'))<a class="text-sm font-medium text-cyan-700 underline-offset-4 hover:text-cyan-900 hover:underline" href="{{ route('password.request') }}">Quên mật khẩu?</a>@endif</div><x-text-input id="password" class="mt-2 block w-full" type="password" name="password" required autocomplete="current-password" aria-describedby="password-error" /><x-input-error id="password-error" :messages="$errors->get('password')" class="mt-2" /></div>
        <label for="remember_me" class="flex items-center gap-3 text-sm text-slate-700"><input id="remember_me" type="checkbox" class="rounded border-slate-300 text-cyan-700 shadow-sm focus:ring-cyan-600" name="remember"><span>Ghi nhớ đăng nhập</span></label>
        <button type="submit" class="btn btn-primary w-full bg-slate-900 py-3 text-sm uppercase tracking-[.12em] hover:bg-cyan-700">Đăng nhập</button>
    </form>
</x-guest-layout>
