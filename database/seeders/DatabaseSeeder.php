<?php

namespace Database\Seeders;

use App\Models\Guest;
use App\Models\HousekeepingTask;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolesPermissionsSeeder::class);

        $properties = collect([
            [
                'name' => 'M&A Grand Manila',
                'slug' => 'ma-grand-manila',
                'address' => 'Roxas Boulevard',
                'city' => 'Manila',
                'country' => 'Philippines',
                'offers_breakfast' => true,
            ],
            [
                'name' => 'M&A Skyline Cebu',
                'slug' => 'ma-skyline-cebu',
                'address' => 'Cebu Business Park',
                'city' => 'Cebu',
                'country' => 'Philippines',
                'offers_breakfast' => true,
            ],
            [
                'name' => 'M&A Skyline Singapore',
                'slug' => 'ma-skyline-singapore',
                'address' => 'Marina Bay',
                'city' => 'Singapore',
                'country' => 'Singapore',
                'offers_breakfast' => false,
            ],
        ])->map(fn (array $property) => Property::updateOrCreate(['slug' => $property['slug']], $property + ['status' => 'active']));

        $roomTypes = [
            ['type' => 'Deluxe King', 'rate' => 6800, 'capacity' => 2, 'amenities' => ['King bed', 'City view', 'Breakfast']],
            ['type' => 'Premier Twin', 'rate' => 7200, 'capacity' => 3, 'amenities' => ['Twin beds', 'Workspace', 'Breakfast']],
            ['type' => 'Executive Suite', 'rate' => 12800, 'capacity' => 4, 'amenities' => ['Lounge access', 'Living area', 'Bay view']],
            ['type' => 'Event Suite', 'rate' => 18500, 'capacity' => 8, 'amenities' => ['Board table', 'AV setup', 'Private pantry']],
        ];

        $properties->each(function (Property $property) use ($roomTypes) {
            foreach ($roomTypes as $index => $roomType) {
                $room = Room::updateOrCreate(
                    ['property_id' => $property->id, 'room_number' => (string) ($property->id * 100 + $index + 1)],
                    $roomType + ['status' => $index === 2 ? 'occupied' : 'available']
                );

                HousekeepingTask::updateOrCreate(
                    ['room_id' => $room->id],
                    ['status' => $index === 2 ? 'cleaning' : 'ready', 'shift_date' => now()->toDateString()]
                );
            }
        });

        $password = Hash::make('password');

        $accounts = [
            ['name' => 'System Admin', 'email' => 'admin@mahotels.test', 'role' => 'admin', 'property_id' => null],
            ['name' => 'Manila Manager', 'email' => 'manager@mahotels.test', 'role' => 'manager', 'property_id' => $properties[0]->id],
            ['name' => 'Front Desk Manila', 'email' => 'reception@mahotels.test', 'role' => 'receptionist', 'property_id' => $properties[0]->id],
            ['name' => 'Housekeeping Manila', 'email' => 'housekeeping@mahotels.test', 'role' => 'housekeeping', 'property_id' => $properties[0]->id],
        ];

        foreach ($accounts as $account) {
            $user = User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'property_id' => $account['property_id'],
                    'password' => $password,
                    'status' => 'active',
                ]
            );

            $user->syncRoles([$account['role']]);
        }

        $guest = Guest::updateOrCreate(
            ['email' => 'guest@example.test'],
            ['name' => 'Ari Santos', 'phone' => '+63 917 555 0142']
        );

        $room = Room::query()->where('status', 'available')->first();

        $reservation = Reservation::updateOrCreate(
            ['reference_number' => 'MAH-DEMO-'.Str::upper('001')],
            [
                'guest_id' => $guest->id,
                'property_id' => $room?->property_id,
                'room_id' => $room?->id,
                'booking_type' => 'personal',
                'check_in' => now()->addDays(3)->toDateString(),
                'check_out' => now()->addDays(5)->toDateString(),
                'adults' => 2,
                'children' => 0,
                'special_request' => 'Late arrival after 9 PM.',
                'status' => 'pending',
                'payment_status' => 'pending',
                'estimated_total' => $room ? ((float) $room->rate * 2) : 0,
                'source' => 'public',
            ]
        );

        Payment::updateOrCreate(
            ['provider_reference' => 'DEMO-PAYMENT-001'],
            [
                'reservation_id' => $reservation->id,
                'method' => 'gcash',
                'amount' => $reservation->estimated_total,
                'status' => 'pending',
                'provider' => 'manual-demo',
            ]
        );

        Review::updateOrCreate(
            ['guest_id' => $guest->id, 'reservation_id' => $reservation->id],
            [
                'property_id' => $room?->property_id,
                'rating' => 5,
                'message' => 'The Manila team handled our arrival with warmth and precision.',
                'status' => 'approved',
                'moderated_at' => now(),
            ]
        );
    }
}
