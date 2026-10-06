<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::findOrCreate('diploma.approve', 'web');
        Role::where('name', 'Lãnh đạo phòng')->where('guard_name', 'web')->first()?->givePermissionTo($permission);
    }

    public function down(): void
    {
        Role::where('name', 'Lãnh đạo phòng')->where('guard_name', 'web')->first()?->revokePermissionTo('diploma.approve');
    }
};
