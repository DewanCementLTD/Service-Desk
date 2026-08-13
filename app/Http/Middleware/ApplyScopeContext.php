<?php

namespace App\Http\Middleware;

use App\Services\ScopeContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active organisation/company/location for the signed-in user and
 * shares it with every view, so the layout can render the scope switcher and
 * controllers can filter without repeating themselves.
 */
class ApplyScopeContext
{
    public function __construct(private ScopeContext $scope)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user !== null) {
            $this->scope->initialiseFor($user);

            View::share([
                'activeOrganization'   => $this->scope->organization(),
                'activeCompany'        => $this->scope->company(),
                'activeLocation'       => $this->scope->location(),
                'availableCompanies'   => $user->accessibleCompanies($this->scope->organizationId()),
                'availableLocations'   => $user->accessibleLocations($this->scope->companyId()),
                'availableOrganizations' => $user->accessibleOrganizations(),
            ]);
        }

        return $next($request);
    }
}
