<?php

namespace Database\Seeders;

use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class StaffAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAccounts(
            password: (string) env('STAFF_SEED_PASSWORD', app()->environment('production') ? '' : 'password'),
            propertySlug: (string) env('STAFF_SEED_PROPERTY_SLUG', 'ma-grand-manila'),
            resetPasswords: filter_var(env('STAFF_SEED_RESET_PASSWORDS', false), FILTER_VALIDATE_BOOL),
        );
    }

    public function seedAccounts(string $password, string $propertySlug, bool $resetPasswords = false): void
    {
        if ($password === '') {
            throw new RuntimeException('Set STAFF_SEED_PASSWORD before seeding production staff accounts.');
        }

        if (app()->environment('production') && strlen($password) < 12) {
            throw new RuntimeException('STAFF_SEED_PASSWORD must contain at least 12 characters in production.');
        }

        DB::transaction(function () use ($password, $propertySlug, $resetPasswords): void {
            $this->call(RolesPermissionsSeeder::class);

            $property = Property::query()->where('slug', $propertySlug)->first();

            if (! $property) {
                throw new RuntimeException("No property exists with slug [{$propertySlug}].");
            }

            $accounts = [
                ['name' => 'System Admin', 'email' => 'admin@mahotels.test', 'role' => 'admin', 'property_id' => null],
                ['name' => 'Hotel Manager', 'email' => 'manager@mahotels.test', 'role' => 'manager', 'property_id' => $property->id],
                ['name' => 'Front Desk', 'email' => 'reception@mahotels.test', 'role' => 'receptionist', 'property_id' => $property->id],
                ['name' => 'Housekeeping', 'email' => 'housekeeping@mahotels.test', 'role' => 'housekeeping', 'property_id' => $property->id],
            ];

            foreach ($accounts as $account) {
                $user = User::query()->firstOrNew(['email' => $account['email']]);
                $isNewUser = ! $user->exists;

                $user->fill([
                    'name' => $account['name'],
                    'property_id' => $account['property_id'],
                    'status' => 'active',
                ]);

                if ($isNewUser || $resetPasswords) {
                    $user->password = Hash::make($password);
                }

                $user->save();
                $user->syncRoles([$account['role']]);
            }
        });
    }
}
