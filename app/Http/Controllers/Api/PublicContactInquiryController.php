<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicContactInquiryRequest;
use App\Services\GuestCommunicationWorkflow;
use Illuminate\Http\JsonResponse;

class PublicContactInquiryController extends Controller
{
    public function store(PublicContactInquiryRequest $request, GuestCommunicationWorkflow $workflow): JsonResponse
    {
        $inquiry = $workflow->submitInquiry($request->validated());

        return response()->json([
            'message' => 'Thank you. Your inquiry has been sent to our team.',
            'reference_number' => $inquiry->reference_number,
        ], 201);
    }
}
