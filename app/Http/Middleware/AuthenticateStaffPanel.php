<?php

namespace App\Http\Middleware;

use Filament\Http\Middleware\Authenticate;

class AuthenticateStaffPanel extends Authenticate
{
    protected function redirectTo($request): ?string
    {
        return route('staff.login');
    }
}
