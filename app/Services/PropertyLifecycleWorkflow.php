<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Property;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PropertyLifecycleWorkflow
{
    public function setStatus(Property $property, string $status, User $actor): Property
    {
        $this->authorizeAdministrator($actor);

        if (! in_array($status, ['active', 'inactive'], true)) {
            throw ValidationException::withMessages(['status' => 'Choose a valid property status.']);
        }

        if ($property->status === $status) {
            return $property;
        }

        $before = $property->status;
        $property->update(['status' => $status]);

        $this->audit($actor, $property, 'properties.'.$status, [
            'before' => ['status' => $before],
            'after' => ['status' => $status],
        ]);

        return $property->refresh();
    }

    public function delete(Property $property, User $actor): bool
    {
        $this->authorizeAdministrator($actor);

        $mediaPaths = collect([$property->hero_image_path, ...($property->gallery_images ?? [])])
            ->filter()
            ->unique()
            ->values()
            ->all();

        DB::transaction(function () use ($property, $actor): void {
            $lockedProperty = Property::query()->lockForUpdate()->findOrFail($property->getKey());

            if ($lockedProperty->status !== 'inactive') {
                throw ValidationException::withMessages([
                    'property' => 'Deactivate the property before permanently deleting it.',
                ]);
            }

            $blockers = $lockedProperty->deletionBlockers();

            if ($blockers !== []) {
                throw ValidationException::withMessages([
                    'property' => 'This property cannot be deleted because it has '.implode(', ', $blockers).'. Keep it inactive to preserve operational history.',
                ]);
            }

            $snapshot = $lockedProperty->toArray();
            $this->audit($actor, $lockedProperty, 'properties.deleted', ['before' => $snapshot]);
            $lockedProperty->delete();
        });

        if ($mediaPaths !== []) {
            Storage::disk('public')->delete($mediaPaths);
        }

        return true;
    }

    private function authorizeAdministrator(User $actor): void
    {
        if (! $actor->hasRole('admin') || ! $actor->can('properties.manage')) {
            throw new AuthorizationException('Only system administrators can change the property lifecycle.');
        }
    }

    /** @param array<string, mixed> $changes */
    private function audit(User $actor, Property $property, string $action, array $changes): void
    {
        AuditLog::create([
            'user_id' => $actor->getKey(),
            'action' => $action,
            'subject_type' => Property::class,
            'subject_id' => $property->getKey(),
            'changes' => $changes,
            'ip_address' => request()?->ip(),
            'user_agent' => (string) request()?->userAgent(),
        ]);
    }
}
