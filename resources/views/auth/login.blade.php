<x-guest-layout>
    <x-slot name="loginVisual">
        <div class="login-brand"><img src="{{ asset('images/hvpnvn-mark.png') }}" alt="" width="42" height="42"><span><strong>VWA-TPMS</strong><small>Học viện Phụ nữ Việt Nam</small></span></div>
        <div class="login-visual-heading"><span>CỔNG QUẢN TRỊ ĐÀO TẠO</span><h2>Kết nối tri thức.<br><em>Đồng hành phát triển.</em></h2><p>Chương trình, học vụ và hồ sơ đào tạo trong một không gian làm việc.</p></div>
        <img class="login-campus" src="{{ asset('images/official/campus-a1.png') }}" alt="Tòa nhà Học viện Phụ nữ Việt Nam">
        <div class="login-image-caption"><span>HỌC VIỆN PHỤ NỮ VIỆT NAM</span><strong>Tri thức kết nối. Giá trị lan tỏa.</strong></div>
        <div class="login-visual-tags"><span>Chương trình</span><span>Học vụ</span><span>Rà soát</span><span>Văn bằng</span></div>
    </x-slot>
    <div class="login-form-content">
        <a class="login-home" href="{{ url('/') }}">← <span>Về trang chủ</span></a>
        <div class="login-heading"><span class="login-eyebrow">KHÔNG GIAN LÀM VIỆC</span><h1>Chào mừng trở lại.</h1><p>Đăng nhập bằng tài khoản được cấp<br>để tiếp tục công việc của bạn.</p></div>
        <form method="POST" action="{{ route('login') }}" class="login-form">
            @csrf
            <div class="login-field"><label for="email">Email hoặc tên tài khoản</label><input id="email" type="text" name="email" value="{{ old('email') }}" placeholder="Nhập email hoặc tên tài khoản" required autofocus autocomplete="username" aria-describedby="email-error" @if($errors->has('email')) aria-invalid="true" @endif><x-input-error id="email-error" :messages="$errors->get('email')" /></div>
            <div class="login-field" x-data="{ showPassword: false }">
                <div class="login-label-row"><label for="password">Mật khẩu</label>@if(Route::has('password.request'))<a href="{{ route('password.request') }}">Quên mật khẩu?</a>@endif</div>
                <div class="login-password"><input id="password" type="password" :type="showPassword ? 'text' : 'password'" name="password" placeholder="Nhập mật khẩu" required autocomplete="current-password" aria-describedby="password-error" @if($errors->has('password')) aria-invalid="true" @endif><button type="button" @click="showPassword = !showPassword" :aria-label="showPassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'" :aria-pressed="showPassword.toString()" aria-label="Hiện mật khẩu" aria-controls="password"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/><path x-show="showPassword" x-cloak d="m3 3 18 18"/></svg></button></div>
                <x-input-error id="password-error" :messages="$errors->get('password')" />
            </div>
            <label class="login-remember" for="remember_me"><input id="remember_me" type="checkbox" name="remember" value="1">Ghi nhớ đăng nhập</label>
            <button type="submit" class="login-submit">Đăng nhập <span aria-hidden="true">↗</span></button>
        </form>
        <div class="login-support"><span aria-hidden="true">i</span><p>Chưa có tài khoản hoặc cần hỗ trợ?<br>Vui lòng liên hệ <strong>Phòng Đào tạo</strong>.</p></div>
        <p class="login-form-footer">VWA-TPMS · Quản trị đào tạo số</p>
    </div>
</x-guest-layout>