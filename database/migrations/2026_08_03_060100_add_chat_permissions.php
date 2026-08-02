<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $global = Permission::findOrCreate('chat.global', 'web');
        $property = Permission::findOrCreate('chat.property', 'web');

        foreach (['admin', 'manager', 'receptionist', 'housekeeping'] as $roleName) {
            Role::findOrCreate($roleName, 'web')->givePermissionTo($global);
        }

        foreach (['manager', 'receptionist', 'housekeeping'] as $roleName) {
            Role::findOrCreate($roleName, 'web')->givePermissionTo($property);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::query()->whereIn('name', ['chat.global', 'chat.property'])->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
