<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function __construct(private AuditLogger $audit)
    {
    }

    public function index(): View
    {
        return view('admin.organizations.index', [
            'organizations' => Organization::withCount('companies')->orderBy('sort_order')->orderBy('name')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.organizations.form', [
            'organization' => new Organization(['is_active' => true, 'timezone' => config('app.timezone')]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $organization = Organization::create($data);

        $this->audit->created('ORGANIZATION', $organization->id, $organization->getAttributes(),
            "Organisation {$organization->name} created");

        return redirect()->route('admin.organizations.index')->with('success', 'Organisation created.');
    }

    public function edit(Organization $organization): View
    {
        return view('admin.organizations.form', ['organization' => $organization]);
    }

    public function update(Request $request, Organization $organization): RedirectResponse
    {
        $original = $organization->getOriginal();

        $organization->update($this->validated($request, $organization));

        $this->audit->updated('ORGANIZATION', $organization->id, $original, $organization->getChanges(),
            "Organisation {$organization->name} updated");

        return redirect()->route('admin.organizations.index')->with('success', 'Organisation updated.');
    }

    private function validated(Request $request, ?Organization $organization = null): array
    {
        $data = $request->validate([
            'code'        => ['required', 'string', 'max:20',
                Rule::unique('oracle.organizations', 'code')->ignore($organization?->id)],
            'name'        => ['required', 'string', 'max:200'],
            'legal_name'  => ['nullable', 'string', 'max:250'],
            'description' => ['nullable', 'string', 'max:500'],
            'email'       => ['nullable', 'email', 'max:150'],
            'phone'       => ['nullable', 'string', 'max:30'],
            'city'        => ['nullable', 'string', 'max:120'],
            'country'     => ['nullable', 'string', 'max:120'],
            'address'     => ['nullable', 'string', 'max:500'],
            'website'     => ['nullable', 'string', 'max:200'],
            'timezone'    => ['nullable', 'string', 'max:60'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
        ]);

        // TIMEZONE and SORT_ORDER are NOT NULL in Oracle. An untouched form field
        // arrives as null, which overrides the column default instead of falling
        // back to it, so both are filled in here.
        $data['code']       = strtoupper($data['code']);
        $data['timezone']   = $data['timezone'] ?: config('app.timezone');
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active']  = $request->boolean('is_active') ? 1 : 0;

        return $data;
    }
}
