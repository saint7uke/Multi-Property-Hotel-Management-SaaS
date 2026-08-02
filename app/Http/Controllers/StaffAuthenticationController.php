<?php

namespace App\Http\Controllers;

use App\Http\Requests\StaffLoginRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StaffAuthenticationController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user() instanceof User) {
            return redirect($request->user()->preferredPanelPath());
        }

        return view('auth.staff-sign-in');
    }

    public function store(StaffLoginRequest $request): RedirectResponse
    {
        $email = Str::lower(trim($request->string('email')->toString()));
        $authenticated = Auth::guard('web')->attempt([
            'email' => $email,
            'password' => $request->string('password')->toString(),
            'status' => 'active',
        ], $request->boolean('remember'));

        if (! $authenticated) {
            $this->recordAttempt($request, null, 'auth.login_failed', $email);
            $this->throwLoginException();
        }

        $user = $request->user();

        if (! $user instanceof User || $user->preferredPanelPath() === '/' || ! $user->can('dashboard.view')) {
            $this->recordAttempt($request, $user, 'auth.login_denied', $email);
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $this->throwLoginException();
        }

        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        $this->recordAttempt($request, $user, 'auth.login_succeeded', $email);

        return redirect()->to($user->preferredPanelPath());
    }

    private function recordAttempt(Request $request, ?User $user, string $action, string $email): void
    {
        AuditLog::create([
            'user_id' => $user?->id,
            'action' => $action,
            'subject_type' => $user ? User::class : null,
            'subject_id' => $user?->id,
            'changes' => ['email_hash' => hash('sha256', $email)],
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);
    }

    private function throwLoginException(): never
    {
        throw ValidationException::withMessages([
            'email' => 'The sign-in details could not be verified.',
        ]);
    }
}
