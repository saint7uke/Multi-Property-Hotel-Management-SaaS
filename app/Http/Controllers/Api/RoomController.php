<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RecordsAudit;
use App\Http\Controllers\Concerns\ScopesPropertyAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpsertRoomRequest;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    use RecordsAudit;
    use ScopesPropertyAccess;

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('rooms.view'), 403);

        $validated = $request->validate([
            'property_id' => ['nullable', 'exists:properties,id'],
            'status' => ['nullable', 'string', 'max:40'],
            'search' => ['nullable', 'string', 'max:120'],
            'available' => ['nullable', 'boolean'],
            'check_in' => ['nullable', 'date'],
            'check_out' => ['nullable', 'date', 'after:check_in'],
            'guests' => ['nullable', 'integer', 'min:1', 'max:40'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:80'],
        ]);

        $query = Room::query()->with('property', 'housekeepingTask');
        $this->scopeRoomsFor($request->user(), $query);

        $query
            ->when($validated['property_id'] ?? null, fn ($query, $propertyId) => $query->where('property_id', $propertyId))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['guests'] ?? null, fn ($query, $guests) => $query->where('capacity', '>=', $guests))
            ->when($request->boolean('available'), fn ($query) => $query->whereIn('status', ['available', 'ready']))
            ->when(isset($validated['check_in'], $validated['check_out']), function ($query) use ($validated) {
                $query->whereDoesntHave(
                    'reservations',
                    fn ($reservation) => $reservation->overlapping($validated['check_in'], $validated['check_out'])
                );
            })
            ->when($validated['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('room_number', 'like', "%{$search}%")
                        ->orWhere('type', 'like', "%{$search}%");
                });
            });

        return response()->json($query->orderBy('property_id')->orderBy('room_number')->paginate($validated['per_page'] ?? 12));
    }

    public function store(UpsertRoomRequest $request): JsonResponse
    {
        abort_unless($request->user()->hasRole('admin') || (int) $request->input('property_id') === (int) $request->user()->property_id, 403);

        $room = Room::create($request->validated());
        $room->housekeepingTask()->create(['status' => $room->status === 'ready' ? 'ready' : 'dirty']);
        $this->audit($request, 'rooms.created', $room, $room->toArray());

        return response()->json(['room' => $room->load('property', 'housekeepingTask')], 201);
    }

    public function update(UpsertRoomRequest $request, Room $room): JsonResponse
    {
        abort_unless($this->canAccessRoom($request->user(), $room), 403);
        abort_unless($request->user()->hasRole('admin') || (int) $request->input('property_id') === (int) $request->user()->property_id, 403);

        $before = $room->toArray();
        $room->update($request->validated());
        $this->audit($request, 'rooms.updated', $room, ['before' => $before, 'after' => $room->fresh()->toArray()]);

        return response()->json(['room' => $room->fresh()->load('property', 'housekeepingTask')]);
    }
}
