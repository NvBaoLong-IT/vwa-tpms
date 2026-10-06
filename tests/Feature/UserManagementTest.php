<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        $role = Role::create(['name' => 'Lãnh đạo phòng', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::findOrCreate('users.manage', 'web'));
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_manager_can_lock_unlock_and_assign_an_office_role(): void
    {
        $manager = $this->manager();
        $target = User::factory()->create(['is_active' => true]);
        Role::findOrCreate('Xem', 'web');
        $this->actingAs($manager)->put(route('users.update', $target), ['role' => 'Xem', 'is_active' => 0])->assertRedirect();
        $this->assertGuestAfterLockedLogin($target);
        $this->actingAs($manager)->put(route('users.update', $target), ['role' => 'Xem', 'is_active' => 1])->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $target->id, 'is_active' => 1]);
        $this->assertTrue($target->fresh()->hasRole('Xem'));
        $this->put('/users/'.$target->id.'/permissions', ['permissions' => []])->assertNotFound();
    }

    public function test_role_list_uses_vietnamese_labels(): void
    {
        $manager = $this->manager();
        Role::findOrCreate('Chuyên viên', 'web');
        Role::findOrCreate('Xem', 'web');

        $this->actingAs($manager)->get(route('users.index'))
            ->assertOk()
            ->assertSee('Chuyên viên Phòng Đào tạo')
            ->assertSee('Chỉ xem')
            ->assertDontSee('Quyền cấp riêng');
    }

    public function test_first_login_requires_password_change_and_then_allows_dashboard(): void
    {
        $user = User::factory()->create(['password' => 'password', 'must_change_password' => true]);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('password.first.edit'));
        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('password.first.edit'));
        $this->actingAs($user)->put(route('password.first.update'), ['password' => 'new-password', 'password_confirmation' => 'new-password'])->assertRedirect(route('dashboard'));
        $this->assertFalse($user->fresh()->must_change_password);
    }

    public function test_login_history_is_visible_to_manager_and_records_failures(): void
    {
        $manager = $this->manager();
        $user = User::factory()->create(['email' => 'history@example.test', 'password' => 'password']);
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        $this->assertDatabaseHas('login_logs', ['identifier' => $user->email, 'successful' => 0]);
        $this->actingAs($manager)->get(route('users.login-logs'))->assertOk()->assertSee($user->email);
    }

    public function test_three_roles_reach_only_their_module_entry_points(): void
    {
        $matrix = [
            'Lãnh đạo phòng' => ['dashboard.view', 'programs.view', 'academic.view', 'diploma.view', 'iso.view'],
            'Chuyên viên' => ['programs.view', 'academic.view'],
            'Xem' => ['programs.view', 'academic.view'],
        ];
        foreach ($matrix as $roleName => $permissions) {
            $role = Role::create(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions(collect($permissions)->map(fn ($permission) => Permission::findOrCreate($permission, 'web'))->all());
            $user = User::factory()->create();
            $user->assignRole($role);
            foreach (['curriculum' => 'programs.view', 'academic' => 'academic.view', 'diploma' => 'diploma.view'] as $module => $permission) {
                $response = $this->actingAs($user)->get(route('modules.'.$module));
                $response->assertStatus($user->can($permission) ? 200 : 403);
            }
        }
    }

    private function assertGuestAfterLockedLogin(User $user): void
    {
        Auth::logout();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseHas('login_logs', ['user_id' => $user->id, 'successful' => 0]);
    }
}
