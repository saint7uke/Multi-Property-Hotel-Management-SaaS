<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RecordsAudit;
use App\Http\Controllers\Concerns\ScopesPropertyAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpsertPropertyRequest;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    use RecordsAudit;
    use ScopesPropertyAccess;

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('properties.view') || $request->user()?->can('rooms.view') || $request->user()?->can('reservations.view'), 403);

        $query = Property::query()->orderBy('name');

        if (! $request->user()->hasRole('admin')) {
            $query->where('id', $request->user()->property_id);
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(UpsertPropertyRequest $request): JsonResponse
    {
        $property = Property::create($request->validated());
        $this->audit($request, 'properties.created', $property, $property->toArray());

        return response()->json(['property' => $property], 201);
    }

    public function update(UpsertPropertyRequest $request, Property $property): JsonResponse
    {
        $before = $property->toArray();
        $property->update($request->validated());
        $this->audit($request, 'properties.updated', $property, ['before' => $before, 'after' => $property->fresh()->toArray()]);

        return response()->json(['property' => $property->fresh()]);
    }
}
