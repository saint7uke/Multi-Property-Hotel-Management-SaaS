<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class StaffUserWorkflow
{
    public const ROLE_OPTIONS = [
        'admin' => 'System administrator',
        'manager' => 'Hotel manager',
        'receptionist' => 'Receptionist',
        'housekeeping' => 'Housekeeping staff',
    ];

    public function create(array $data, User $actor): User
    {
        $this->assertActorCanAssign($actor, $data['role'], $data['property_id'] ?? null);

        return DB::transaction(function () use ($data, $actor): User {
            $user = User::create([
                'name' => trim($data['name']),
                'email' => strtolower(trim($data['email'])),
                'password' => Hash::make($data['password']),
                'property_id' => $data['role'] === 'admin' ? null : $data['property_id'],
                'status' => $data['status'],
            ]);
            $user->syncRoles([$data['role']]);

            $this->audit($actor, 'users.created', $user, [
                'role' => $data['role'],
                'property_id' => $user->property_id,
                'status' => $user->status,
            ]);

            return $this->fresh($user);
        });
    }

    public function update(User $user, array $data, User $actor): User
    {
        $this->assertCanManageTarget($actor, $user);

        $currentRole = $user->getRoleNames()->sole();

        if ($actor->is($user) && ($data['role'] !== $currentRole || $data['status'] !== $user->status)) {
            throw ValidationException::withMessages([
                'role' => ['You cannot change your own role or account status. Ask another administrator.'],
            ]);
        }

        $this->assertActorCanAssign($actor, $data['role'], $data['property_id'] ?? null);

        if ($user->hasRole('admin')
            && $user->status === 'active'
            && ($data['role'] !== 'admin' || $data['status'] !== 'active')
            && User::role('admin')->where('status', 'active')->count() <= 1) {
            throw ValidationException::withMessages([
                'status' => ['At least one active system administrator must remain.'],
            ]);
        }

        return DB::transaction(function () use ($user, $data, $actor, $currentRole): User {
            $before = $this->auditState($user);
            $propertyId = $data['role'] === 'admin' ? null : $data['property_id'];
            $accessChanged = $currentRole !== $data['role']
                || (int) $user->property_id !== (int) $propertyId
                || $user->status !== $data['status']
                || filled($data['password'] ?? null);

            $user->fill([
                'name' => trim($data['name']),
                'email' => strtolower(trim($data['email'])),
                'property_id' => $propertyId,
                'status' => $data['status'],
            ]);

            if (filled($data['password'] ?? null)) {
                $user->password = Hash::make($data['password']);
            }

            $user->save();
            $user->syncRoles([$data['role']]);

            if ($accessChanged) {
                $this->revokeAccess($user, $actor);
            }

            $this->audit($actor, 'users.updated', $user, [
                'before' => $before,
                'after' => $this->auditState($user->fresh()->load('roles')),
                'credentials_changed' => filled($data['password'] ?? null),
                'access_revoked' => $accessChanged,
            ]);

            return $this->fresh($user);
        });
    }

    public function canManage(User $actor, User $target): bool
    {
        if ($actor->status !== 'active' || ! $actor->can('users.manage')) {
            return false;
        }

        if ($actor->hasRole('admin')) {
            return true;
        }

        return ! $actor->is($target)
            && ! $target->hasRole('admin')
            && $actor->property_id
            && (int) $target->property_id === (int) $actor->property_id;
    }

    private function assertCanManageTarget(User $actor, User $target): void
    {
        abort_unless($this->canManage($actor, $target), 403);
    }

    private function assertActorCanAssign(User $actor, string $role, int|string|null $propertyId): void
    {
        if ($actor->status !== 'active' || ! $actor->can('users.manage') || ! array_key_exists($role, self::ROLE_OPTIONS)) {
            abort(403);
        }

        if (! $actor->hasRole('admin') && $role === 'admin') {
            abort(403);
        }

        if ($role === 'admin') {
            return;
        }

        $property = Property::query()->whereKey($propertyId)->where('status', 'active')->first();

        if (! $property) {
            throw ValidationException::withMessages([
                'property_id' => ['Choose an active hotel property.'],
            ]);
        }

        if (! $actor->hasRole('admin') && (int) $property->id !== (int) $actor->property_id) {
            throw ValidationException::withMessages([
                'property_id' => ['Managers can only assign staff to their own hotel property.'],
            ]);
        }
    }

    private function revokeAccess(User $user, User $actor): void
    {
        $user->tokens()->delete();

        $sessions = DB::table('sessions')->where('user_id', $user->id);

        if ($actor->is($user) && $user->status === 'active' && request()->hasSession()) {
            $sessions->where('id', '!=', request()->session()->getId());
        }

        $sessions->delete();
    }

    private function fresh(User $user): User
    {
        return $user->fresh()->load('property', 'roles');
    }

    private function auditState(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->getRoleNames()->first(),
            'property_id' => $user->property_id,
            'status' => $user->status,
        ];
    }

    private function audit(User $actor, string $action, User $user, array $changes): void
    {
        AuditLog::create([
            'user_id' => $actor->id,
            'action' => $action,
            'subject_type' => $user::class,
            'subject_id' => $user->id,
            'changes' => $changes,
            'ip_address' => request()->ip(),
            'user_agent' => (string) request()->userAgent(),
        ]);
    }
}
