<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ScopesPropertyAccess;
use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Services\HousekeepingWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HousekeepingController extends Controller
{
    use ScopesPropertyAccess;

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('housekeeping.view'), 403);

        $rooms = Room::query()->with('property', 'housekeepingTask.assignee');
        $this->scopeRoomsFor($request->user(), $rooms);

        $data = $rooms->orderBy('property_id')->orderBy('room_number')->get()->groupBy(function (Room $room) {
            return $room->housekeepingTask?->status ?? $room->status;
        });

        return response()->json(['data' => $data]);
    }

    public function update(Request $request, Room $room, HousekeepingWorkflow $workflow): JsonResponse
    {
        abort_unless($request->user()->can('housekeeping.manage'), 403);
        abort_unless($this->canAccessRoom($request->user(), $room), 403);

        $validated = $request->validate([
            'status' => ['required', 'in:dirty,cleaning,clean,inspected,ready,out_of_service'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $workflow->save([
            'room_id' => $room->id,
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? null,
            'shift_date' => now()->toDateString(),
            'assigned_to' => $request->user()->hasRole('housekeeping') ? $request->user()->id : null,
        ], $request->user(), $room->housekeepingTask);

        return response()->json(['room' => $room->fresh()->load('property', 'housekeepingTask.assignee')]);
    }
}
