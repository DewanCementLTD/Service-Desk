<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route guard: `->middleware('permission:tickets.view,tickets.manage')` passes
 * when the user holds any one of the listed permission codes.
 */
class CheckPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = Auth::user();

        if ($user === null) {
            return redirect()->route('login');
        }

        if (! $user->is_active) {
            Auth::logout();

            return redirect()->route('login')->withErrors([
                'email' => 'This account has been deactivated.',
            ]);
        }

        if ($permissions !== [] && ! $user->hasAnyPermission(...$permissions)) {
            abort(403, 'You do not have permission to access this area.');
        }

        return $next($request);
    }
}
