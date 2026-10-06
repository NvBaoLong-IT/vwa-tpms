<section class="profile-section" aria-labelledby="profile-password-title">
    <header class="profile-section-heading">
        <span class="profile-section-number" aria-hidden="true">02</span>
        <h2 id="profile-password-title">Bảo mật tài khoản</h2>
        <p>Chọn mật khẩu riêng cho tài khoản này và không chia sẻ với người khác.</p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="profile-form">
        @csrf
        @method('put')

        <div class="profile-field">
            <x-input-label for="update_password_current_password" value="Mật khẩu hiện tại" />
            <x-text-input id="update_password_current_password" name="current_password" type="password" class="profile-input profile-input-narrow" required autocomplete="current-password" aria-describedby="profile-current-password-error" />
            <x-input-error id="profile-current-password-error" :messages="$errors->updatePassword->get('current_password')" />
        </div>

        <div class="profile-fields">
            <div class="profile-field">
                <x-input-label for="update_password_password" value="Mật khẩu mới" />
                <x-text-input id="update_password_password" name="password" type="password" class="profile-input" required autocomplete="new-password" aria-describedby="profile-password-error" />
                <x-input-error id="profile-password-error" :messages="$errors->updatePassword->get('password')" />
            </div>
            <div class="profile-field">
                <x-input-label for="update_password_password_confirmation" value="Nhập lại mật khẩu mới" />
                <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" class="profile-input" required autocomplete="new-password" aria-describedby="profile-password-confirmation-error" />
                <x-input-error id="profile-password-confirmation-error" :messages="$errors->updatePassword->get('password_confirmation')" />
            </div>
        </div>

        <div class="profile-form-actions">
            <button type="submit" class="profile-button profile-button-primary">Cập nhật mật khẩu</button>
            @if (session('status') === 'password-updated')
                <p class="profile-save-status" role="status">Đã cập nhật mật khẩu.</p>
            @endif
        </div>
    </form>
</section>
