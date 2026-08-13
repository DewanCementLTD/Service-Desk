<?php

namespace App\Http\Controllers;

use App\Services\ScopeContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Lets a user move between the companies and locations they have access to.
 */
class ScopeController extends Controller
{
    public function __construct(private ScopeContext $scope)
    {
    }

    public function switchCompany(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'integer'],
        ]);

        if (! $this->scope->switchCompany($request->user(), (int) $data['company_id'])) {
            return back()->with('error', 'You do not have access to that company.');
        }

        return back()->with('success', 'Switched to ' . $this->scope->company()?->name . '.');
    }

    public function switchLocation(Request $request): RedirectResponse
    {
        $data = $request->validate([
            // An empty value means "all locations in this company".
            'location_id' => ['nullable', 'integer'],
        ]);

        $locationId = $data['location_id'] === null || $data['location_id'] === ''
            ? null
            : (int) $data['location_id'];

        if (! $this->scope->switchLocation($request->user(), $locationId)) {
            return back()->with('error', 'You do not have access to that location.');
        }

        $name = $this->scope->location()?->name ?? 'All locations';

        return back()->with('success', "Viewing {$name}.");
    }
}
