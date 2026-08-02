<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpsertStaffUserRequest;
use App\Models\User;
use App\Services\StaffUserWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('users.manage'), 403);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:active,inactive'],
            'role' => ['nullable', 'in:admin,manager,receptionist,housekeeping'],
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        $query = User::query()
            ->with('property')
            ->with('roles')
            ->orderBy('name');

        if (! $request->user()->hasRole('admin')) {
            $query->where('property_id', $request->user()->property_id)
                ->whereDoesntHave('roles', fn ($roleQuery) => $roleQuery->where('name', 'admin'));
        }

        $query
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($validated['role'] ?? null, fn ($query, string $role) => $query->role($role))
            ->when($validated['property_id'] ?? null, fn ($query, int $propertyId) => $query->where('property_id', $propertyId));

        if ($search = trim((string) ($validated['search'] ?? ''))) {
            $query->where(function ($inner) use ($search) {
                $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhereHas('property', fn ($propertyQuery) => $propertyQuery->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'like', "%{$search}%"));
            });
        }

        $users = $query->paginate($validated['per_page'] ?? 10);
        $users->getCollection()->transform(fn (User $user) => $this->payload($user));

        return response()->json($users);
    }

    public function store(UpsertStaffUserRequest $request, StaffUserWorkflow $workflow): JsonResponse
    {
        $user = $workflow->create($request->validated(), $request->user());

        return response()->json(['user' => $this->payload($user)], 201);
    }

    public function update(UpsertStaffUserRequest $request, User $user, StaffUserWorkflow $workflow): JsonResponse
    {
        $user = $workflow->update($user, $request->validated(), $request->user());

        return response()->json(['user' => $this->payload($user)]);
    }

    private function payload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status,
            'property_id' => $user->property_id,
            'property' => $user->property,
            'roles' => $user->roles->pluck('name')->values(),
        ];
    }
}
