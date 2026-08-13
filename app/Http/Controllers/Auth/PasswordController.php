<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Self-service password change.
 *
 * Users imported from the HR roster start on a shared password issued by IT and
 * are forced here on their first sign-in (see RequirePasswordChange). Everyone
 * else can reach the same screen from the account menu at any time.
 */
class PasswordController extends Controller
{
    public function __construct(private AuditLogger $audit)
    {
    }

    public function edit(Request $request): View
    {
        return view('auth.change-password', [
            'forced' => (bool) $request->user()->must_change_password,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [
            'password.confirmed' => 'The two new passwords do not match.',
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'That is not your current password.',
            ]);
        }

        if (Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'Your new password must be different from the current one.',
            ]);
        }

        $user->forceFill([
            'password'             => Hash::make($data['password']),
            'must_change_password' => 0,
            'password_changed_at'  => now(),
        ])->save();

        // The password hash is part of the session guard's recaller, so refresh it.
        $request->session()->put('password_hash_web', $user->getAuthPassword());

        // Never log the password itself - only that it changed.
        $this->audit->log('PASSWORD_CHANGE', 'USER', $user->id, "{$user->name} changed their password");

        return redirect()->route('dashboard')->with('success', 'Your password has been changed.');
    }
}
