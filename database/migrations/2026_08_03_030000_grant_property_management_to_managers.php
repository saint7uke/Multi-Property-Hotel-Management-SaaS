<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $role = Role::query()->where('name', 'manager')->where('guard_name', 'web')->first();
        $permission = Permission::query()->where('name', 'properties.manage')->where('guard_name', 'web')->first();

        if ($role && $permission) {
            $role->givePermissionTo($permission);
        }
    }

    public function down(): void
    {
        $role = Role::query()->where('name', 'manager')->where('guard_name', 'web')->first();
        $permission = Permission::query()->where('name', 'properties.manage')->where('guard_name', 'web')->first();

        if ($role && $permission) {
            $role->revokePermissionTo($permission);
        }
    }
};
