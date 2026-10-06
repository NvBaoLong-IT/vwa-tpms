<section class="profile-section profile-section-danger" aria-labelledby="profile-delete-title">
    <header class="profile-section-heading">
        <span class="profile-section-number" aria-hidden="true">03</span>
        <h2 id="profile-delete-title">Xóa tài khoản</h2>
        <p>Thao tác này không thể hoàn tác.</p>
    </header>

    <div class="profile-danger-body">
        <p>Sau khi xóa, bạn sẽ không thể đăng nhập bằng tài khoản này. Hãy lưu lại thông tin cần thiết trước khi tiếp tục.</p>
        <button type="button" class="profile-button profile-button-danger-outline" x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')">Xóa tài khoản của tôi</button>
    </div>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" maxWidth="lg" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="profile-delete-dialog" role="dialog" aria-modal="true" aria-labelledby="profile-delete-dialog-title" aria-describedby="profile-delete-dialog-description">
            @csrf
            @method('delete')

            <h2 id="profile-delete-dialog-title">Xác nhận xóa tài khoản</h2>
            <p id="profile-delete-dialog-description">Bạn đang yêu cầu xóa vĩnh viễn tài khoản của mình. Nhập mật khẩu hiện tại để xác nhận. Thao tác này không thể hoàn tác.</p>

            <div class="profile-field">
                <x-input-label for="delete_account_password" value="Mật khẩu hiện tại" />
                <x-text-input id="delete_account_password" name="password" type="password" class="profile-input" required autocomplete="current-password" aria-describedby="profile-delete-password-error" />
                <x-input-error id="profile-delete-password-error" :messages="$errors->userDeletion->get('password')" />
            </div>

            <div class="profile-dialog-actions">
                <button type="button" class="profile-button profile-button-secondary" x-on:click="$dispatch('close')">Hủy bỏ</button>
                <button type="submit" class="profile-button profile-button-danger">Xóa vĩnh viễn tài khoản</button>
            </div>
        </form>
    </x-modal>
</section>
