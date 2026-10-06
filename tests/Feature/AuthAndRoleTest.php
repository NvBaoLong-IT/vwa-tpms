<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthAndRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Lãnh đạo phòng', 'Chuyên viên CTĐT', 'Chuyên viên học vụ', 'Chuyên viên VBCC'] as $role) {
            $item = Role::create(['name' => $role, 'guard_name' => 'web']);
            $rolePermissions = match ($role) {
                'Lãnh đạo phòng' => ['programs.view', 'academic.view', 'diploma.view'],
                'Chuyên viên CTĐT' => ['programs.view'],
                'Chuyên viên học vụ' => ['academic.view'],
                default => ['diploma.view'],
            };
            foreach ($rolePermissions as $permission) {
                $item->givePermissionTo(Permission::findOrCreate($permission, 'web'));
            }
        }
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_user_can_log_in_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'officer@example.test',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);

        $this->post('/login', [
            'email' => 'officer@example.test',
            'password' => 'password',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_can_log_in_with_account_name(): void
    {
        $user = User::factory()->create(['name' => 'baolonglocal', 'password' => 'password', 'email_verified_at' => now()]);
        $this->post('/login', ['email' => 'baolonglocal', 'password' => 'password'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_curriculum_module_requires_curriculum_or_manager_role(): void
    {
        $academic = User::factory()->create();
        $academic->assignRole('Chuyên viên học vụ');
        $this->actingAs($academic)->get('/modules/curriculum')->assertForbidden();

        $curriculum = User::factory()->create();
        $curriculum->assignRole('Chuyên viên CTĐT');
        $this->actingAs($curriculum)->get('/modules/curriculum')
            ->assertOk()
            ->assertJsonPath('status', 'authorized');
    }

    public function test_manager_can_access_all_initial_module_routes(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('Lãnh đạo phòng');

        $this->actingAs($manager)->get('/modules/curriculum')->assertOk();
        $this->actingAs($manager)->get('/modules/academic')->assertOk();
        $this->actingAs($manager)->get('/modules/diploma')->assertOk();
    }
}
