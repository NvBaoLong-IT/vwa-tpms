<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ActiveSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_locked_user_is_logged_out_on_the_next_request(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $this->post(route('login'), ['email' => $user->email, 'password' => 'password', 'remember' => '1'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $this->withSession(['private_marker' => 'old-session'])->get(route('profile.edit'))->assertOk();
        DB::table('users')->where('id', $user->id)->update(['is_active' => false]);

        $this->get(route('profile.edit'))->assertRedirect(route('login'))
            ->assertSessionHasErrors('email')->assertSessionMissing('private_marker');
        $this->assertGuest();
        $this->get(route('profile.edit'))->assertRedirect(route('login'));
    }

    public function test_locked_user_cannot_write_even_when_a_password_change_is_required(): void
    {
        $user = User::factory()->create(['name' => 'Tên cũ', 'is_active' => true, 'must_change_password' => true]);
        $this->actingAs($user);
        DB::table('users')->where('id', $user->id)->update(['is_active' => false]);

        $this->patch(route('profile.update'), ['name' => 'Tên mới', 'email' => $user->email])->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Tên cũ']);
    }

    public function test_active_and_guest_requests_keep_their_existing_behavior(): void
    {
        $this->get(route('login'))->assertOk();
        $user = User::factory()->create(['is_active' => true]);
        $this->actingAs($user)->get(route('profile.edit'))->assertOk();
        $this->assertAuthenticatedAs($user);
    }
}
