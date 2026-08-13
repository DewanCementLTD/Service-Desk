<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ScopeContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function __construct(
        private ScopeContext $scope,
        private AuditLogger $audit,
    ) {
    }

    /**
     * Served with no-store so the browser never replays a cached copy of this
     * page. A cached copy carries a dead CSRF token, and posting it lands the
     * user on "Page expired" until they refresh by hand.
     */
    public function showLoginForm(): Response
    {
        return response()
            ->view('auth.login')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    public function login(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'login'    => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $email = $this->resolveEmail($input['login']);

        if ($email === null || ! Auth::attempt(
            ['email' => $email, 'password' => $input['password']],
            $request->boolean('remember')
        )) {
            throw ValidationException::withMessages([
                'login' => 'These credentials do not match our records.',
            ]);
        }

        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();

            throw ValidationException::withMessages([
                'login' => 'This account has been deactivated. Please contact your administrator.',
            ]);
        }

        $request->session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        // Establish the working company/location before the first page renders.
        $this->scope->initialiseFor($user);

        $this->audit->log('LOGIN', 'USER', $user->id, "{$user->name} signed in");

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Employees imported from the HR roster sign in with their mobile number;
     * head-office and administrator accounts still use an email address. Both are
     * resolved to the stored email, which remains the authentication key.
     */
    private function resolveEmail(string $login): ?string
    {
        $login = trim($login);

        if (str_contains($login, '@')) {
            return $login;
        }

        // Mobile numbers are stored bare (e.g. 3018298234) but people type them in
        // all sorts of ways: 0301-829 8234, +92 301 8298234.
        $digits = preg_replace('/\D/', '', $login);
        $digits = preg_replace('/^(92|0)/', '', (string) $digits);

        return User::whereRaw("REGEXP_REPLACE(PHONE, '\\D', '') = ?", [$digits])
            ->value('email');
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if ($user !== null) {
            $this->audit->log('LOGOUT', 'USER', $user->id, "{$user->name} signed out");
        }

        Auth::logout();
        $this->scope->clear();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
