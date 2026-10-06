<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\OfficeRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OfficeRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_accounts_move_to_three_roles_without_direct_grants(): void
    {
        $curriculum = User::factory()->create();
        $library = User::factory()->create();
        $manager = User::factory()->create();
        $curriculum->assignRole(Role::findOrCreate('Chuyên viên CTĐT', 'web'));
        $library->assignRole(Role::findOrCreate('Cán bộ Thư viện', 'web'));
        $manager->assignRole(Role::findOrCreate('Lãnh đạo phòng', 'web'));
        $curriculum->givePermissionTo(Permission::findOrCreate('diploma.approve', 'web'));

        $this->seed(OfficeRolesSeeder::class);
        $this->seed(OfficeRolesSeeder::class);

        $this->assertEqualsCanonicalizing(['Lãnh đạo phòng', 'Chuyên viên', 'Xem'], Role::pluck('name')->all());
        $this->assertTrue($curriculum->fresh()->hasRole('Chuyên viên'));
        $this->assertFalse($curriculum->fresh()->can('diploma.approve'));
        $this->assertFalse($library->fresh()->is_active);
        $this->assertTrue($library->fresh()->hasRole('Xem'));
        $this->assertTrue($manager->fresh()->can('diploma.update'));
        $this->assertDatabaseMissing('permissions', ['name' => 'materials.confirm']);
    }

    public function test_navigation_and_routes_match_office_roles(): void
    {
        $this->seed(OfficeRolesSeeder::class);
        $manager = User::factory()->create(['email_verified_at' => now()]);
        $manager->assignRole('Lãnh đạo phòng');
        $specialist = User::factory()->create(['email_verified_at' => now()]);
        $specialist->assignRole('Chuyên viên');
        $viewer = User::factory()->create(['email_verified_at' => now()]);
        $viewer->assignRole('Xem');

        $this->actingAs($manager)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('diplomas.index').'"', false);
        $this->get(route('diplomas.index'))->assertOk();

        $this->actingAs($specialist)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('programs.index').'"', false)
            ->assertSee('href="'.route('academic.index').'"', false)
            ->assertDontSee('href="'.route('diplomas.index').'"', false)
            ->assertDontSee('href="'.route('iso.index').'"', false);
        $this->get(route('diplomas.index'))->assertForbidden();
        $this->get(route('iso.index'))->assertForbidden();

        $this->actingAs($viewer)->get(route('programs.index'))->assertOk();
        $this->post(route('programs.store'), [])->assertForbidden();
        $this->post(route('academic.scan'))->assertForbidden();
    }

    public function test_authenticated_account_without_office_role_cannot_view_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
    }
}
