<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\User;
use Database\Seeders\StaffAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffAccountsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_property_scoped_staff_without_resetting_existing_passwords(): void
    {
        $property = Property::create([
            'name' => 'M&A Grand Manila',
            'slug' => 'ma-grand-manila',
            'address' => 'Roxas Boulevard',
            'city' => 'Manila',
            'country' => 'Philippines',
            'status' => 'active',
        ]);

        $this->seed(StaffAccountsSeeder::class);

        $expectedRoles = [
            'admin@mahotels.test' => 'admin',
            'manager@mahotels.test' => 'manager',
            'reception@mahotels.test' => 'receptionist',
            'housekeeping@mahotels.test' => 'housekeeping',
        ];

        foreach ($expectedRoles as $email => $role) {
            $user = User::query()->where('email', $email)->firstOrFail();

            $this->assertTrue(Hash::check('password', $user->password));
            $this->assertTrue($user->hasRole($role));
            $this->assertSame($role === 'admin' ? null : $property->id, $user->property_id);
        }

        $manager = User::query()->where('email', 'manager@mahotels.test')->firstOrFail();
        $manager->password = 'A-different-existing-password';
        $manager->save();

        $this->seed(StaffAccountsSeeder::class);

        $this->assertTrue(Hash::check('A-different-existing-password', $manager->fresh()->password));
    }
}
