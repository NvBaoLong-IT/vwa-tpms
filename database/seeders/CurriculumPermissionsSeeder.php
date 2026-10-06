<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CurriculumPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (['programs.import'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (['Lãnh đạo phòng', 'Chuyên viên'] as $role) {
            Role::findOrCreate($role, 'web')->givePermissionTo('programs.import');
        }

    }
}
