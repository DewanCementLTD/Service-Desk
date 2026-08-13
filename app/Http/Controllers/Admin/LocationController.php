<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Location;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LocationController extends Controller
{
    public function __construct(private AuditLogger $audit)
    {
    }

    public function index(Request $request): View
    {
        return view('admin.locations.index', [
            'locations' => Location::with(['company', 'manager'])
                ->when($request->filled('company_id'),
                    fn ($q) => $q->where('company_id', $request->integer('company_id')))
                ->orderBy('company_id')->orderBy('sort_order')->orderBy('name')
                ->paginate(20)->withQueryString(),
            'companies' => Company::active()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.locations.form', $this->formData(new Location([
            'is_active' => true, 'location_type' => 'BRANCH', 'timezone' => config('app.timezone'),
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $location = Location::create($this->validated($request));

        $this->audit->created('LOCATION', $location->id, $location->getAttributes(),
            "Location {$location->name} created");

        return redirect()->route('admin.locations.index')->with('success', 'Location created.');
    }

    public function edit(Location $location): View
    {
        return view('admin.locations.form', $this->formData($location));
    }

    public function update(Request $request, Location $location): RedirectResponse
    {
        $original = $location->getOriginal();

        $location->update($this->validated($request, $location));

        $this->audit->updated('LOCATION', $location->id, $original, $location->getChanges(),
            "Location {$location->name} updated");

        return redirect()->route('admin.locations.index')->with('success', 'Location updated.');
    }

    private function validated(Request $request, ?Location $location = null): array
    {
        $data = $request->validate([
            'company_id'      => ['required', 'integer', Rule::exists('oracle.companies', 'id')],
            // Location codes are unique within their company.
            'code'            => ['required', 'string', 'max:20',
                Rule::unique('oracle.locations', 'code')
                    ->where(fn ($q) => $q->where('company_id', $request->integer('company_id')))
                    ->ignore($location?->id)],
            'name'            => ['required', 'string', 'max:200'],
            'location_type'   => ['required', Rule::in(array_keys(Location::TYPES))],
            'manager_user_id' => ['nullable', 'integer'],
            'email'           => ['nullable', 'email', 'max:150'],
            'phone'           => ['nullable', 'string', 'max:30'],
            'address'         => ['nullable', 'string', 'max:500'],
            'city'            => ['nullable', 'string', 'max:120'],
            'state'           => ['nullable', 'string', 'max:120'],
            'country'         => ['nullable', 'string', 'max:120'],
            'postal_code'     => ['nullable', 'string', 'max:20'],
            'timezone'        => ['nullable', 'string', 'max:60'],
            'sort_order'      => ['nullable', 'integer', 'min:0'],
        ]);

        // TIMEZONE and SORT_ORDER are NOT NULL in Oracle. An untouched form field
        // arrives as null, which overrides the column default instead of falling
        // back to it, so both are filled in here.
        $data['code']           = strtoupper($data['code']);
        $data['timezone']       = $data['timezone'] ?: config('app.timezone');
        $data['sort_order']     = $data['sort_order'] ?? 0;
        $data['is_head_office'] = $request->boolean('is_head_office') ? 1 : 0;
        $data['is_active']      = $request->boolean('is_active') ? 1 : 0;

        return $data;
    }

    private function formData(Location $location): array
    {
        return [
            'location'  => $location,
            'companies' => Company::active()->orderBy('name')->get(),
            'managers'  => User::active()->orderBy('name')->get(),
            'types'     => Location::TYPES,
        ];
    }
}
