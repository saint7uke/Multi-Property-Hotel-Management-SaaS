<?php

namespace Tests\Feature;

use App\Filament\Resources\Properties\PropertyResource;
use App\Filament\Resources\Rooms\RoomResource;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use App\Services\HousekeepingWorkflow;
use App\Services\PaymentWorkflow;
use App\Services\PropertyLifecycleWorkflow;
use App\Services\ReservationWorkflow;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class HotelOperationsFilamentTest extends TestCase
{
    use RefreshDatabase;

    public function test_hotel_operations_filament_pages_render_for_authorized_roles(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@mahotels.test')->firstOrFail();
        $manager = User::where('email', 'manager@mahotels.test')->firstOrFail();
        $receptionist = User::where('email', 'reception@mahotels.test')->firstOrFail();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin)
            ->get('/admin/rooms/create')
            ->assertOk()
            ->assertSee('Room identity')
            ->assertSee('Pricing and occupancy');

        Filament::setCurrentPanel(Filament::getPanel('manager'));
        $this->actingAs($manager)
            ->get('/manager/properties/'.$manager->property_id.'/edit')
            ->assertOk()
            ->assertSee('Property CMS');

        $this->actingAs($manager)
            ->get('/manager/housekeeping-tasks/create')
            ->assertOk()
            ->assertSee('Room assignment')
            ->assertSee('Shift and room status');

        Filament::setCurrentPanel(Filament::getPanel('receptionist'));
        $this->actingAs($receptionist)
            ->get('/receptionist/reservations/create')
            ->assertOk()
            ->assertSee('Booking setup')
            ->assertSee('Stay dates and accommodation');
    }

    public function test_manager_can_edit_only_their_property_but_cannot_create_properties(): void
    {
        $this->seed(DatabaseSeeder::class);

        $manager = User::where('email', 'manager@mahotels.test')->firstOrFail();
        $ownProperty = $manager->property;
        $otherProperty = Property::whereKeyNot($ownProperty->id)->firstOrFail();

        $this->actingAs($manager);

        $this->assertTrue(PropertyResource::canEdit($ownProperty));
        $this->assertFalse(PropertyResource::canEdit($otherProperty));
        $this->assertFalse(PropertyResource::canCreate());

        $this->putJson('/api/properties/'.$ownProperty->id, [
            'name' => $ownProperty->name,
            'slug' => $ownProperty->slug,
            'address' => $ownProperty->address,
            'city' => $ownProperty->city,
            'country' => $ownProperty->country,
            'status' => 'inactive',
        ])->assertOk()->assertJsonPath('property.status', 'inactive');

        $this->putJson('/api/properties/'.$otherProperty->id, [
            'name' => $otherProperty->name,
            'slug' => $otherProperty->slug,
            'address' => $otherProperty->address,
            'city' => $otherProperty->city,
            'country' => $otherProperty->country,
            'status' => 'inactive',
        ])->assertForbidden();
    }

    public function test_housekeeping_workflow_scopes_assignees_and_synchronizes_room_status(): void
    {
        $this->seed(DatabaseSeeder::class);

        $manager = User::where('email', 'manager@mahotels.test')->firstOrFail();
        $housekeeper = User::where('email', 'housekeeping@mahotels.test')->firstOrFail();
        $room = Room::where('property_id', $manager->property_id)->whereDoesntHave('reservations')->firstOrFail();
        $room->housekeepingTask?->delete();

        $task = app(HousekeepingWorkflow::class)->save([
            'room_id' => $room->id,
            'assigned_to' => $housekeeper->id,
            'status' => 'cleaning',
            'shift_date' => today()->toDateString(),
            'notes' => 'Linen service in progress.',
        ], $manager);

        $this->assertSame('cleaning', $room->fresh()->status);
        $this->assertSame($housekeeper->id, $task->assigned_to);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'housekeeping.created',
            'subject_id' => $task->id,
        ]);

        $otherRoom = Room::where('property_id', '!=', $manager->property_id)->firstOrFail();

        $this->expectException(ValidationException::class);
        app(HousekeepingWorkflow::class)->save([
            'room_id' => $otherRoom->id,
            'assigned_to' => $housekeeper->id,
            'status' => 'cleaning',
        ], User::where('email', 'admin@mahotels.test')->firstOrFail(), $otherRoom->housekeepingTask);
    }

    public function test_reservation_workflow_enforces_capacity_and_status_transitions(): void
    {
        $this->seed(DatabaseSeeder::class);

        $manager = User::where('email', 'manager@mahotels.test')->firstOrFail();
        $room = Room::where('property_id', $manager->property_id)
            ->whereIn('status', ['available', 'ready'])
            ->whereDoesntHave('reservations')
            ->firstOrFail();

        try {
            app(ReservationWorkflow::class)->createStaffReservation([
                'booking_type' => 'personal',
                'property_id' => $room->property_id,
                'room_id' => $room->id,
                'guest_name' => 'Capacity Test',
                'email' => 'capacity@example.test',
                'phone' => '+63 900 000 0000',
                'check_in' => today()->addDays(10)->toDateString(),
                'check_out' => today()->addDays(12)->toDateString(),
                'adults' => $room->capacity + 1,
                'children' => 0,
                'status' => 'confirmed',
            ], $manager);

            $this->fail('An over-capacity reservation should not be created.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('adults', $exception->errors());
        }

        $reservation = Reservation::where('status', 'pending')->firstOrFail();

        try {
            app(ReservationWorkflow::class)->updateStatus($reservation, 'checked_out', $manager);
            $this->fail('A pending reservation should not jump directly to checked out.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        app(ReservationWorkflow::class)->updateStatus($reservation, 'confirmed', $manager);
        app(PaymentWorkflow::class)->markPaid($reservation->payments()->firstOrFail(), [
            'method' => 'gcash',
            'provider' => 'GCash test channel',
            'provider_reference' => 'TEST-CHECKIN-PAYMENT-001',
        ], $manager);
        app(ReservationWorkflow::class)->updateStatus($reservation->fresh(), 'checked_in', $manager);
        app(ReservationWorkflow::class)->updateStatus($reservation->fresh(), 'checked_out', $manager);

        $this->assertSame('dirty', $reservation->room->fresh()->status);
        $this->assertSame('dirty', $reservation->room->housekeepingTask->fresh()->status);
    }

    public function test_rooms_can_only_be_removed_without_operational_history(): void
    {
        $this->seed(DatabaseSeeder::class);

        $manager = User::where('email', 'manager@mahotels.test')->firstOrFail();
        $this->actingAs($manager);

        $unusedRoom = Room::create([
            'property_id' => $manager->property_id,
            'room_number' => 'TEST-DELETE',
            'type' => 'Test Room',
            'rate' => 1000,
            'capacity' => 2,
            'status' => 'available',
        ]);
        $reservedRoom = Reservation::whereNotNull('room_id')->firstOrFail()->room;

        $this->assertTrue(RoomResource::canDelete($unusedRoom));
        $this->assertFalse(RoomResource::canDelete($reservedRoom));

        $otherRoom = Room::where('property_id', '!=', $manager->property_id)->firstOrFail();
        $this->assertFalse(RoomResource::canDelete($otherRoom));
    }

    public function test_admin_can_delete_only_inactive_properties_without_operational_history(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@mahotels.test')->firstOrFail();
        $manager = User::where('email', 'manager@mahotels.test')->firstOrFail();
        $property = Property::create([
            'name' => 'M&A Temporary Property',
            'slug' => 'ma-temporary-property',
            'address' => 'Draft Address',
            'city' => 'Manila',
            'country' => 'Philippines',
            'status' => 'active',
        ]);

        $this->actingAs($admin);
        $this->assertFalse(PropertyResource::canDelete($property));

        app(PropertyLifecycleWorkflow::class)->setStatus($property, 'inactive', $admin);
        $this->assertTrue(PropertyResource::canDelete($property->fresh()));

        $this->actingAs($manager);
        $this->assertFalse(PropertyResource::canDelete($property->fresh()));

        $this->actingAs($admin);
        app(PropertyLifecycleWorkflow::class)->delete($property->fresh(), $admin);

        $this->assertDatabaseMissing('properties', ['id' => $property->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'properties.deleted',
            'subject_id' => $property->id,
        ]);
    }

    public function test_properties_with_operational_dependencies_must_be_kept_inactive(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@mahotels.test')->firstOrFail();
        $property = Property::whereHas('rooms')->firstOrFail();
        $property->update(['status' => 'inactive']);

        $this->actingAs($admin);

        $this->assertFalse(PropertyResource::canDelete($property));
        $this->assertContains('rooms', $property->deletionBlockers());
    }
}
