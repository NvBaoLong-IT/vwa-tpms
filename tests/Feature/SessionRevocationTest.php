<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestWith;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SessionRevocationTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        $manager = User::factory()->create();
        $manager->givePermissionTo(Permission::findOrCreate('users.manage', 'web'));
        Role::findOrCreate('Xem', 'web');
        Role::findOrCreate('Chuyên viên', 'web');

        return $manager;
    }

    #[TestWith([false, 'Xem'])]
    #[TestWith([true, 'Chuyên viên'])]
    public function test_lock_or_role_change_revokes_target_sessions_and_remember_token(bool $active, string $role): void
    {
        config(['session.driver' => 'database']);
        $manager = $this->manager();
        $target = User::factory()->create(['remember_token' => 'old-token']);
        $target->assignRole('Xem');
        foreach (['target-a' => $target->id, 'target-b' => $target->id, 'manager-other' => $manager->id] as $id => $userId) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $userId, 'payload' => base64_encode(serialize([])), 'last_activity' => time()]);
        }

        $this->actingAs($manager)->put(route('users.update', $target), ['role' => $role, 'is_active' => $active])->assertRedirect();

        $this->assertDatabaseMissing('sessions', ['user_id' => $target->id]);
        $this->assertDatabaseHas('sessions', ['id' => 'manager-other']);
        $this->assertSame(1, (int) $target->fresh()->session_version);
        $this->assertNotSame('old-token', $target->fresh()->getRememberToken());
        $this->assertTrue($target->fresh()->hasRole($role));
    }

    public function test_lock_then_unlock_does_not_restore_old_session_but_fresh_login_works(): void
    {
        $manager = $this->manager();
        $target = User::factory()->create();
        $this->actingAs($manager)->put(route('users.update', $target), ['is_active' => false])->assertRedirect();
        $this->put(route('users.update', $target), ['is_active' => true])->assertRedirect();

        $this->actingAs($target->fresh())->withSession(['auth_session_version' => 0, 'secret' => 'old'])
            ->get(route('profile.edit'))->assertRedirect(route('login'))->assertSessionMissing('secret');
        $this->assertGuest();
        $this->post(route('login'), ['email' => $target->email, 'password' => 'password'])->assertRedirect();
        $this->get(route('profile.edit'))->assertOk();
        $this->assertSame(1, session('auth_session_version'));
    }

    public function test_unchanged_role_preserves_session_and_non_manager_cannot_revoke(): void
    {
        $manager = $this->manager();
        $target = User::factory()->create(['remember_token' => 'valid-token']);
        $target->assignRole('Xem');
        $this->actingAs($manager)->put(route('users.update', $target), ['role' => 'Xem', 'is_active' => true])->assertRedirect();
        $this->assertSame(0, (int) $target->fresh()->session_version);
        $this->assertSame('valid-token', $target->fresh()->getRememberToken());
        $this->actingAs($target)->put(route('users.update', $manager), ['is_active' => false])->assertForbidden();
        $this->assertTrue($manager->fresh()->is_active);
    }

    public function test_valid_remember_cookie_can_create_a_new_session_after_revocation(): void
    {
        $user = User::factory()->create(['remember_token' => 'new-token']);
        $user->forceFill(['session_version' => 3])->save();
        $cookieName = Auth::guard('web')->getRecallerName();
        Auth::forgetGuards();
        $this->withCookie($cookieName, $user->id.'|new-token|'.$user->password)->get(route('profile.edit'))->assertOk();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(3, session('auth_session_version'));
    }

    public function test_file_session_without_stamp_is_rejected_after_revocation(): void
    {
        config(['session.driver' => 'file']);
        $user = User::factory()->create();
        $user->forceFill(['session_version' => 1])->save();
        $this->actingAs($user)->get(route('profile.edit'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_replayed_remember_cookie_cannot_reauthenticate_after_role_change(): void
    {
        $manager = $this->manager();
        $target = User::factory()->create(['remember_token' => 'stolen-token']);
        $target->assignRole('Chuyên viên');
        $cookieName = Auth::guard('web')->getRecallerName();
        $cookieValue = $target->id.'|stolen-token|'.$target->password;
        $this->actingAs($manager)->put(route('users.update', $target), ['role' => 'Xem', 'is_active' => true])->assertRedirect();
        $this->flushSession();
        Auth::forgetGuards();

        $this->withCookie($cookieName, $cookieValue)->get(route('profile.edit'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
