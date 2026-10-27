<section class="profile-section" aria-labelledby="profile-information-title">
    <header class="profile-section-heading">
        <span class="profile-section-number" aria-hidden="true">01</span>
        <h2 id="profile-information-title">Thông tin cá nhân</h2>
        <p>Họ tên và email sử dụng cho tài khoản của bạn.</p>
    </header>

    <form method="post" action="{{ route('profile.update') }}" class="profile-form">
        @csrf
        @method('patch')

        <div class="profile-fields">
            <div class="profile-field">
                <x-input-label for="name" value="Họ và tên" />
                <x-text-input id="name" name="name" type="text" :value="old('name', $user->name)" required autocomplete="name" aria-describedby="profile-name-error" class="profile-input" />
                <x-input-error id="profile-name-error" :messages="$errors->get('name')" />
            </div>
            <div class="profile-field">
                <x-input-label for="email" value="Địa chỉ email" />
                <x-text-input id="email" name="email" type="email" :value="old('email', $user->email)" required autocomplete="email" aria-describedby="profile-email-error" class="profile-input" />
                <x-input-error id="profile-email-error" :messages="$errors->get('email')" />
            </div>
        </div>

        <div class="profile-form-actions">
            <button type="submit" class="profile-button profile-button-primary">Lưu thông tin</button>
        </div>
    </form>
</section>
