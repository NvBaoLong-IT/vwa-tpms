<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class StudentDirectoryPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permission = Permission::findOrCreate('students.view', 'web');

        foreach (['Lãnh đạo phòng', 'Chuyên viên', 'Xem'] as $role) {
            Role::findOrCreate($role, 'web')->givePermissionTo($permission);
        }
    }
}
