<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'dashboard.view',
            'properties.view',
            'properties.manage',
            'rooms.view',
            'rooms.manage',
            'reservations.view',
            'reservations.manage',
            'housekeeping.view',
            'housekeeping.manage',
            'users.manage',
            'reports.view',
            'reports.export',
            'reviews.moderate',
            'payments.manage',
            'settings.manage',
            'audit.view',
            'chat.global',
            'chat.property',
            'inquiries.manage',
            'newsletter.manage',
        ];

        $permissionModels = collect($permissions)
            ->mapWithKeys(fn (string $permission) => [$permission => Permission::findOrCreate($permission, 'web')]);

        Role::findOrCreate('admin', 'web')->syncPermissions($permissionModels->values());

        Role::findOrCreate('manager', 'web')->syncPermissions($permissionModels->only([
            'dashboard.view',
            'properties.view',
            'properties.manage',
            'rooms.view',
            'rooms.manage',
            'reservations.view',
            'reservations.manage',
            'housekeeping.view',
            'housekeeping.manage',
            'users.manage',
            'reports.view',
            'reports.export',
            'reviews.moderate',
            'payments.manage',
            'audit.view',
            'chat.global',
            'chat.property',
        ])->values());

        Role::findOrCreate('receptionist', 'web')->syncPermissions($permissionModels->only([
            'dashboard.view',
            'properties.view',
            'rooms.view',
            'reservations.view',
            'reservations.manage',
            'housekeeping.view',
            'chat.global',
            'chat.property',
        ])->values());

        Role::findOrCreate('housekeeping', 'web')->syncPermissions($permissionModels->only([
            'dashboard.view',
            'properties.view',
            'rooms.view',
            'housekeeping.view',
            'housekeeping.manage',
            'chat.global',
            'chat.property',
        ])->values());
    }
}
