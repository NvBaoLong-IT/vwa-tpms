<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_view_does_not_grant_export_access(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->givePermissionTo(Permission::findOrCreate('dashboard.view', 'web'));

        $this->actingAs($user)->get(route('reports.index'))->assertOk();
        $this->actingAs($user)->get(route('reports.export', ['programs', 'csv']))->assertForbidden();
    }

    public function test_manager_can_open_reports_and_export_csv(): void
    {
        $role = Role::create(['name' => 'Lãnh đạo phòng', 'guard_name' => 'web']);
        $role->givePermissionTo([
            Permission::findOrCreate('dashboard.view', 'web'),
            Permission::findOrCreate('reports.export', 'web'),
        ]);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole($role);
        $this->actingAs($user)->get(route('reports.index'))->assertOk();
        $this->actingAs($user)->get(route('reports.export', ['programs', 'csv']))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertDatabaseHas('report_histories', ['report_type' => 'programs', 'format' => 'csv']);
        $this->actingAs($user)->get(route('reports.index'))
            ->assertOk()
            ->assertSee('<td class="p-3">Chương trình đào tạo</td>', false)
            ->assertSee('<td class="p-3">Dữ liệu CSV</td>', false);
    }
}
