<?php

use App\Http\Controllers\Api\PublicBookingController;
use App\Http\Controllers\Api\PublicContactInquiryController;
use App\Http\Controllers\Api\PublicNewsletterController;
use App\Http\Controllers\Api\PublicReviewController;
use Illuminate\Support\Facades\Route;

Route::prefix('public')->group(function () {
    Route::get('/rooms/availability', [PublicBookingController::class, 'availability']);
    Route::get('/booking/estimate', [PublicBookingController::class, 'estimate']);
    Route::post('/bookings', [PublicBookingController::class, 'store'])->middleware('throttle:public-booking');
    Route::post('/booking/lookup', [PublicBookingController::class, 'lookup'])->middleware('throttle:public-booking');
    Route::post('/reviews', [PublicReviewController::class, 'store'])->middleware('throttle:public-booking');
    Route::post('/contact-inquiries', [PublicContactInquiryController::class, 'store'])->middleware('throttle:public-form');
    Route::post('/newsletter-subscriptions', [PublicNewsletterController::class, 'store'])->middleware('throttle:public-form');
});
