<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class OfficeRolesSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'dashboard.view', 'reports.view', 'reports.export',
            'programs.view', 'programs.create', 'programs.update', 'programs.delete', 'programs.import',
            'courses.view', 'courses.create', 'courses.update', 'courses.delete', 'courses.assign',
            'iso.view', 'iso.create', 'iso.update', 'iso.assign', 'iso.approve', 'iso.close', 'iso.upload',
            'students.view', 'students.manage', 'academic.view', 'academic.import', 'academic.process',
            'diploma.view', 'diploma.issue', 'diploma.update', 'diploma.approve',
            'users.view', 'users.manage',
        ];

        DB::transaction(function () use ($permissions): void {
            foreach ($permissions as $permission) {
                Permission::findOrCreate($permission, 'web');
            }

            $roles = [
                'Lãnh đạo phòng' => $permissions,
                'Chuyên viên' => [
                    'dashboard.view', 'reports.view', 'reports.export',
                    'programs.view', 'programs.create', 'programs.update', 'programs.import',
                    'courses.view', 'courses.create', 'courses.update', 'courses.assign',
                    'students.view', 'students.manage', 'academic.view', 'academic.import', 'academic.process',
                ],
                'Xem' => [
                    'dashboard.view', 'reports.view', 'programs.view', 'courses.view',
                    'students.view', 'academic.view',
                ],
            ];

            foreach ($roles as $name => $grants) {
                Role::findOrCreate($name, 'web')->syncPermissions($grants);
            }

            User::with('roles')->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    $names = $user->roles->pluck('name');
                    if ($names->contains('Lãnh đạo phòng')) {
                        $user->syncRoles(['Lãnh đạo phòng']);
                    } elseif ($names->contains(fn ($name) => in_array($name, ['Chuyên viên CTĐT', 'Chuyên viên học vụ', 'Chuyên viên VBCC', 'Chuyên viên'], true))) {
                        $user->syncRoles(['Chuyên viên']);
                    } elseif ($names->contains('Cán bộ Thư viện')) {
                        $user->syncRoles(['Xem']);
                        $user->update(['is_active' => false]);
                    }

                    $user->syncPermissions([]);
                }
            });

            Role::whereIn('name', ['Chuyên viên CTĐT', 'Chuyên viên học vụ', 'Chuyên viên VBCC', 'Cán bộ Thư viện'])->delete();
            Permission::where('name', 'materials.confirm')->delete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
