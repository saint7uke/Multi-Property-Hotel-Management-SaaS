<?php

namespace App\Providers;

use App\Http\Responses\StaffLogoutResponse;
use App\Models\Property;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LogoutResponse::class, StaffLogoutResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn (): string => view('components.chat.widget')->render(),
        );

        View::composer('layouts.public', function ($view) {
            $view->with('footerProperties', Property::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['name', 'slug']));
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            $email = strtolower(trim((string) $request->input('email')));

            return [
                Limit::perMinute(20)->by('staff-login-ip|'.$request->ip()),
                Limit::perMinute(5)->by('staff-login-account|'.$request->ip().'|'.hash('sha256', $email)),
            ];
        });

        RateLimiter::for('public-booking', function (Request $request) {
            return Limit::perHour(5)->by($request->ip());
        });

        RateLimiter::for('public-form', function (Request $request) {
            $email = strtolower(trim((string) $request->input('email')));

            return [
                Limit::perMinute(10)->by('public-form-ip|'.$request->ip()),
                Limit::perHour(5)->by('public-form-email|'.hash('sha256', $email)),
            ];
        });

        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        RateLimiter::for('chat-message', function (Request $request) {
            return Limit::perMinute(30)->by('chat-message|'.$request->user()?->id);
        });

        RateLimiter::for('chat-state', function (Request $request) {
            return Limit::perMinute(180)->by('chat-state|'.$request->user()?->id);
        });
    }
}
