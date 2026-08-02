<?php

namespace App\Services;

use App\Models\HousekeepingTask;
use App\Models\Room;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class HousekeepingWorkflow
{
    /** @var array<int, string> */
    public const STATUSES = ['dirty', 'cleaning', 'clean', 'inspected', 'ready', 'out_of_service'];

    public function save(array $data, User $actor, ?HousekeepingTask $task = null): HousekeepingTask
    {
        $room = Room::query()->findOrFail($data['room_id']);

        if (! $this->canAccessRoom($actor, $room)) {
            abort(403);
        }

        $this->validateAssignee($data['assigned_to'] ?? null, $room);

        if ($task && (int) $task->room_id !== (int) $room->id) {
            throw ValidationException::withMessages([
                'room_id' => ['A housekeeping task cannot be moved to a different room.'],
            ]);
        }

        $task ??= new HousekeepingTask;
        $action = $task->exists ? 'housekeeping.updated' : 'housekeeping.created';
        $before = $task->exists ? $task->toArray() : null;

        $task->fill([
            'room_id' => $room->id,
            'assigned_to' => $data['assigned_to'] ?? null,
            'status' => $data['status'],
            'shift_date' => $data['shift_date'] ?? now()->toDateString(),
            'notes' => $data['notes'] ?? null,
        ])->save();

        $room->update(['status' => $data['status']]);

        app(ReservationWorkflow::class)->audit($actor, $action, $task, [
            'before' => $before,
            'after' => $task->fresh(['room'])->toArray(),
        ]);

        return $task;
    }

    public function delete(HousekeepingTask $task, User $actor): void
    {
        if (! $this->canAccessRoom($actor, $task->room)) {
            abort(403);
        }

        app(ReservationWorkflow::class)->audit($actor, 'housekeeping.deleted', $task, [
            'before' => $task->toArray(),
        ]);

        $task->delete();
    }

    private function validateAssignee(int|string|null $assigneeId, Room $room): void
    {
        if (! $assigneeId) {
            return;
        }

        $isEligible = User::query()
            ->whereKey($assigneeId)
            ->where('property_id', $room->property_id)
            ->where('status', 'active')
            ->role('housekeeping')
            ->exists();

        if (! $isEligible) {
            throw ValidationException::withMessages([
                'assigned_to' => ['Choose an active housekeeper assigned to the room property.'],
            ]);
        }
    }

    private function canAccessRoom(User $user, Room $room): bool
    {
        return $user->hasRole('admin') || (int) $room->property_id === (int) $user->property_id;
    }
}
