<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\HousekeepingController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\ReviewModerationController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\StaffUserController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\StaffAuthenticationController;
use App\Models\Property;
use App\Models\Review;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('public.home', [
        'properties' => rescue(function () {
            return Property::query()->where('status', 'active')->orderBy('name')->get();
        }, collect()),
        'reviews' => rescue(function () {
            return Review::query()->with('guest', 'property', 'reservation')->where('status', 'approved')->latest()->take(6)->get();
        }, collect()),
    ]);
})->name('home');

Route::get('/hotels/{property:slug}', function (Property $property) {
    abort_unless($property->status === 'active', 404);

    $property->load([
        'rooms' => fn ($query) => $query
            ->whereIn('status', ['available', 'ready'])
            ->orderBy('room_number'),
        'reviews' => fn ($query) => $query
            ->with('guest', 'reservation')
            ->where('status', 'approved')
            ->latest()
            ->take(3),
    ]);

    return view('public.hotel', [
        'property' => $property,
        'properties' => collect([$property]),
        'rooms' => $property->rooms,
        'reviews' => $property->reviews,
    ]);
})->name('hotels.show');

Route::view('/about', 'public.about')->name('about');
Route::view('/blog', 'public.blog')->name('blog');
Route::get('/contact', function () {
    return view('public.contact', [
        'properties' => rescue(function () {
            return Property::query()->where('status', 'active')->orderBy('name')->get();
        }, collect()),
    ]);
})->name('contact');
Route::get('/book-now', function () {
    return view('public.book', [
        'properties' => rescue(function () {
            return Property::query()->where('status', 'active')->orderBy('name')->get();
        }, collect()),
    ]);
})->name('book.now');

Route::get('/staff/sign-in', [StaffAuthenticationController::class, 'create'])->name('staff.login');
Route::post('/staff/sign-in', [StaffAuthenticationController::class, 'store'])
    ->middleware('throttle:login')
    ->name('staff.authenticate');
Route::redirect('/staff/login', '/staff/sign-in');

foreach (['admin' => 'admin', 'manager' => 'manager'] as $panel => $role) {
    Route::middleware(['auth', "role:{$role}"])->prefix("{$panel}/reports")->group(function () use ($panel) {
        Route::get('/reservations.csv', [ReportController::class, 'csv'])->name("{$panel}.reports.csv");
        Route::get('/reservations.xlsx', [ReportController::class, 'excel'])->name("{$panel}.reports.xlsx");
    });
}

Route::prefix('api')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
        });
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/chat/conversations', [ChatController::class, 'conversations']);
        Route::get('/chat/conversations/{conversation}/messages', [ChatController::class, 'messages']);
        Route::post('/chat/conversations/{conversation}/messages', [ChatController::class, 'store'])
            ->middleware('throttle:chat-message');
        Route::post('/chat/conversations/{conversation}/read', [ChatController::class, 'read'])
            ->middleware('throttle:chat-state');
        Route::post('/chat/conversations/{conversation}/typing', [ChatController::class, 'typing'])
            ->middleware('throttle:chat-state');
        Route::post('/chat/presence', [ChatController::class, 'presence'])
            ->middleware('throttle:chat-state');
        Route::get('/chat/messages/{message}/attachment', [ChatController::class, 'attachment'])
            ->name('chat.attachment');

        Route::get('/dashboard', DashboardController::class);

        Route::apiResource('properties', PropertyController::class)->only(['index', 'store', 'update']);
        Route::apiResource('users', StaffUserController::class)->only(['index', 'store', 'update']);
        Route::apiResource('rooms', RoomController::class)->only(['index', 'store', 'update']);

        Route::get('/reservations', [ReservationController::class, 'index']);
        Route::post('/reservations', [ReservationController::class, 'store']);
        Route::get('/reservations/{reservation}', [ReservationController::class, 'show']);
        Route::patch('/reservations/{reservation}/status', [ReservationController::class, 'updateStatus']);

        Route::get('/housekeeping', [HousekeepingController::class, 'index']);
        Route::patch('/housekeeping/rooms/{room}', [HousekeepingController::class, 'update']);

        Route::get('/reviews', [ReviewModerationController::class, 'index']);
        Route::patch('/reviews/{review}', [ReviewModerationController::class, 'update']);

        Route::get('/reports/summary', [ReportController::class, 'summary']);
        Route::get('/reports/reservations.csv', [ReportController::class, 'csv']);
        Route::get('/reports/reservations.xlsx', [ReportController::class, 'excel']);
    });
});
