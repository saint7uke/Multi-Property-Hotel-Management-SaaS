<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicNewsletterRequest;
use App\Services\GuestCommunicationWorkflow;
use Illuminate\Http\JsonResponse;

class PublicNewsletterController extends Controller
{
    public function store(PublicNewsletterRequest $request, GuestCommunicationWorkflow $workflow): JsonResponse
    {
        $result = $workflow->subscribe($request->validated());

        return response()->json([
            'message' => $result['already_subscribed']
                ? 'This email is already subscribed to Member Getaway Rates.'
                : 'You are subscribed to Member Getaway Rates.',
        ], $result['created'] ? 201 : 200);
    }
}
