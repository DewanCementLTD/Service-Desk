<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Holds a user on the change-password screen until they replace the password IT
 * issued them. Without this the whole roster would keep running on one shared
 * password, which is the reason the flag exists at all.
 */
class RequirePasswordChange
{
    /** Routes that must stay reachable, or the user would be locked in a loop. */
    private const ALLOWED = ['password.edit', 'password.update', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user !== null
            && $user->must_change_password
            && ! in_array($request->route()?->getName(), self::ALLOWED, true)) {
            // An AJAX call (the board drag, notification polling) must never be
            // silently rewritten into the password page: fetch() follows the
            // redirect on its own and hands the caller a 200 with HTML in it,
            // which reads as success even though nothing happened. Answer with a
            // status the caller can actually detect and act on instead.
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'You must change your password before continuing. Reload the page to be taken there.',
                ], 423);
            }

            return redirect()->route('password.edit');
        }

        return $next($request);
    }
}
