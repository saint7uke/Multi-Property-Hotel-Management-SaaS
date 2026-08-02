<?php

namespace App\Http\Middleware;

use Filament\Http\Middleware\AuthenticateSession;

class AuthenticateStaffSession extends AuthenticateSession
{
    protected function redirectTo($request): ?string
    {
        return route('staff.login');
    }
}
