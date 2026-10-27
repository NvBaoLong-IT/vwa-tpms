<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class CurriculumApprovalBrowserSeeder extends Seeder
{
    public function run(): void
    {
        $database = str_replace('\\', '/', (string) config('database.connections.sqlite.database'));
        if (! app()->environment('testing') || config('database.default') !== 'sqlite' || ! str_starts_with($database, str_replace('\\', '/', storage_path('framework/testing/'))) || ! str_contains(basename($database), 'curriculum-browser-')) {
            throw new RuntimeException('Chỉ tạo tài khoản demo trong SQLite kiểm thử riêng.');
        }
        $this->call(CurriculumOperationsBrowserSeeder::class);
        $reviewer = User::factory()->create(['name' => 'DEMO - Lãnh đạo thứ hai', 'email' => 'manager2@curriculum.test', 'is_active' => true, 'must_change_password' => false]);
        $reviewer->assignRole('Lãnh đạo phòng');
    }
}
