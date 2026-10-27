<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class FlashNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_success_and_profile_status_are_available_on_the_shared_layout(): void
    {
        $this->actingAs(User::factory()->create())->withSession(['success' => 'Đã lưu dữ liệu.', 'status' => 'profile-updated'])->get(route('profile.edit'))->assertOk()->assertSee('data-notifications=', false)->assertSee('Đã lưu dữ liệu.')->assertSee('Đã cập nhật hồ sơ.');
    }

    public function test_named_error_bags_are_included_and_notification_payload_is_escaped(): void
    {
        $errors = new ViewErrorBag;
        $errors->put('updatePassword', new MessageBag(['password' => 'Cần nhập mật khẩu hợp lệ.']));
        $this->actingAs(User::factory()->create())->withSession(['errors' => $errors, 'warning' => '<script>bad()</script>'])->get(route('profile.edit'))->assertOk()->assertSee('Cần nhập mật khẩu hợp lệ.')->assertDontSee('<script>bad()</script>', false)->assertSee('&lt;script&gt;', false);
    }

    public function test_login_errors_are_available_for_the_popup_and_the_field(): void
    {
        $this->post(route('login'), ['email' => 'missing@vwa.local', 'password' => 'bad'])->assertSessionHasErrors('email');
        $this->get(route('login'))->assertOk()->assertSee('Không thể hoàn tất thao tác')->assertSee('id="email-error"', false);
    }
}
