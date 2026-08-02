<?php

namespace Tests\Feature;

use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\StaffUserWorkflow;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdministrationFilamentTest extends TestCase
{
    use RefreshDatabase;

    public function test_administration_pages_render_with_scoped_staff_and_read_only_audit_records(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@mahotels.test')->firstOrFail();
        $manager = User::where('email', 'manager@mahotels.test')->firstOrFail();
        $auditLog = AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'administration.tested',
            'subject_type' => User::class,
            'subject_id' => $manager->id,
            'changes' => ['status' => 'active'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Administration test',
        ]);
        $otherManager = User::where('email', 'cebu.manager@example.test')->first();

        if (! $otherManager) {
            $otherManager = User::factory()->create([
                'name' => 'Other Property Manager',
                'email' => 'other.manager@example.test',
                'property_id' => $admin->property_id,
                'status' => 'active',
            ]);
            $otherManager->syncRoles(['manager']);
        }

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin)
            ->get('/admin/users/create')
            ->assertOk()
            ->assertSee('Staff identity')
            ->assertSee('Role and hotel access')
            ->assertSee('Account security');

        $this->actingAs($admin)
            ->get('/admin/audit-logs')
            ->assertOk()
            ->assertSee('Audit trail')
            ->assertSee('Details');

        $this->assertFalse(AuditLogResource::canCreate());
        $this->assertFalse(AuditLogResource::canEdit($auditLog));
        $this->assertFalse(AuditLogResource::canDelete($auditLog));
        $this->get('/admin/audit-logs/create')->assertNotFound();
        $this->get('/admin/audit-logs/1/edit')->assertNotFound();

        Filament::setCurrentPanel(Filament::getPanel('manager'));
        $this->actingAs($manager)
            ->get('/manager/users')
            ->assertOk()
            ->assertDontSee($admin->email)
            ->assertDontSee($otherManager->email);

        $this->assertFalse(UserResource::canEdit($manager));
        $this->actingAs($manager)->get("/manager/users/{$manager->id}/edit")->assertForbidden();
    }

    public function test_current_and_last_active_administrator_cannot_remove_their_own_access(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@mahotels.test')->firstOrFail();
        $workflow = app(StaffUserWorkflow::class);

        try {
            $workflow->update($admin, [
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => 'manager',
                'property_id' => $admin->property_id,
                'status' => 'active',
            ], $admin);
            $this->fail('An administrator must not demote their own account.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('role', $exception->errors());
        }

        $secondAdmin = User::factory()->create([
            'name' => 'Second Administrator',
            'email' => 'second.admin@example.test',
            'status' => 'active',
        ]);
        $secondAdmin->syncRoles(['admin']);

        try {
            $workflow->update($admin, [
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => 'admin',
                'property_id' => null,
                'status' => 'inactive',
            ], $admin);
            $this->fail('An administrator must not deactivate their own account.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('role', $exception->errors());
        }

        $workflow->update($secondAdmin, [
            'name' => $secondAdmin->name,
            'email' => $secondAdmin->email,
            'role' => 'admin',
            'property_id' => null,
            'status' => 'inactive',
        ], $admin);

        $this->assertSame('inactive', $secondAdmin->fresh()->status);

        $this->assertSame(1, User::role('admin')->where('status', 'active')->count());
    }

    public function test_deactivation_revokes_tokens_and_sessions_and_records_the_change(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@mahotels.test')->firstOrFail();
        $manager = User::where('email', 'manager@mahotels.test')->firstOrFail();
        $manager->createToken('administration-test');
        DB::table('sessions')->insert([
            'id' => 'manager-session-for-revocation',
            'user_id' => $manager->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Administration test',
            'payload' => 'test',
            'last_activity' => now()->timestamp,
        ]);

        app(StaffUserWorkflow::class)->update($manager, [
            'name' => $manager->name,
            'email' => $manager->email,
            'role' => 'manager',
            'property_id' => $manager->property_id,
            'status' => 'inactive',
        ], $admin);

        $this->assertSame('inactive', $manager->fresh()->status);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $manager->id]);
        $this->assertDatabaseMissing('sessions', ['id' => 'manager-session-for-revocation']);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'users.updated',
            'subject_id' => $manager->id,
        ]);
    }

    public function test_staff_creation_requires_a_strong_confirmed_password(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@mahotels.test')->firstOrFail();
        $propertyId = User::whereNotNull('property_id')->value('property_id');

        $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'Password Test User',
            'email' => 'password-test@example.test',
            'password' => 'weakpass',
            'password_confirmation' => 'differentpass',
            'role' => 'receptionist',
            'property_id' => $propertyId,
            'status' => 'active',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }
}
