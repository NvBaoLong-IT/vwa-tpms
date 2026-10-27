<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $permissions): User
    {
        $user = User::factory()->create();
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user;
    }

    public function test_successful_and_invalid_writes_are_logged_without_request_secrets(): void
    {
        $user = $this->user(['programs.view', 'courses.create']);
        $this->actingAs($user)->post(route('courses.store'), ['course_code' => 'ACT101', 'name_vi' => 'Học phần', 'credits' => 3, 'theory_hours' => 30, 'practice_hours' => 0, 'password' => 'never-store-this', 'api_key' => 'private-key'])->assertRedirect();
        $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'module' => 'curriculum', 'action' => 'create', 'successful' => true]);
        $this->assertStringNotContainsString('never-store-this', ActivityLog::first()->toJson());
        $this->assertStringNotContainsString('private-key', ActivityLog::first()->toJson());
        $this->post(route('courses.store'), [])->assertSessionHasErrors('course_code');
        $this->assertDatabaseHas('activity_logs', ['route_name' => 'courses.store', 'successful' => false]);
        $this->assertDatabaseCount('courses', 1);
    }

    public function test_denied_write_is_logged_but_reads_and_background_ticks_are_not(): void
    {
        $user = $this->user(['dashboard.view']);
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->post(route('courses.store'), [])->assertForbidden();
        $this->assertDatabaseHas('activity_logs', ['route_name' => 'courses.store', 'status_code' => 403, 'successful' => false]);
        $this->assertDatabaseCount('activity_logs', 1);
        $this->post(route('schedule.tick'))->assertOk();
        $this->assertDatabaseCount('activity_logs', 1);
    }

    public function test_logout_retains_actor_and_export_is_recorded(): void
    {
        $user = $this->user(['programs.view', 'reports.export']);
        $this->actingAs($user)->get(route('programs.export.csv'))->assertOk();
        $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'action' => 'export']);
        $this->post(route('logout'))->assertRedirect('/');
        $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'actor_name' => $user->name, 'action' => 'logout']);
    }

    public function test_deleting_own_account_preserves_the_actor_name_without_a_foreign_key_error(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect('/');
        $this->assertDatabaseHas('activity_logs', ['user_id' => null, 'actor_name' => $user->name, 'route_name' => 'profile.destroy', 'successful' => true]);
    }

    public function test_manager_can_filter_old_logins_and_new_activity_and_viewers_are_denied(): void
    {
        $manager = $this->user(['users.manage']);
        LoginLog::create(['user_id' => $manager->id, 'identifier' => 'old-login@vwa.local', 'successful' => true, 'logged_at' => '2026-10-01 10:00:00']);
        ActivityLog::create(['user_id' => $manager->id, 'actor_name' => $manager->name, 'module' => 'curriculum', 'action' => 'update', 'route_name' => 'courses.update', 'successful' => true, 'status_code' => 302, 'created_at' => '2026-10-12 10:00:00']);
        $this->actingAs($manager)->get(route('users.login-logs'))->assertOk()->assertSee('Nhật ký hoạt động')->assertSee('old-login@vwa.local')->assertSee('Cập nhật');
        $this->get(route('users.login-logs', ['module' => 'curriculum', 'action' => 'update']))->assertOk()->assertDontSee('old-login@vwa.local')->assertViewHas('logs', fn ($logs) => $logs->total() === 1);
        $this->get(route('users.login-logs', ['from' => '2026-10-01', 'to' => '2026-10-01']))->assertSee('old-login@vwa.local')->assertViewHas('logs', fn ($logs) => $logs->total() === 1);
        $this->get(route('users.login-logs', ['module' => 'bad']))->assertSessionHasErrors('module');
        $this->actingAs($this->user(['dashboard.view']))->get(route('users.login-logs'))->assertForbidden();
    }
}
