<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Organization;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function __construct(private AuditLogger $audit)
    {
    }

    public function index(Request $request): View
    {
        return view('admin.companies.index', [
            'companies' => Company::with('organization')
                ->withCount(['locations', 'departments'])
                ->when($request->filled('organization_id'),
                    fn ($q) => $q->where('organization_id', $request->integer('organization_id')))
                ->orderBy('name')->paginate(20)->withQueryString(),
            'organizations' => Organization::active()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.companies.form', [
            'company'       => new Company([
                'is_active'     => true,
                'ticket_prefix' => 'IT',
                'currency_code' => 'PKR',
                'timezone'      => config('app.timezone'),
            ]),
            'organizations' => Organization::active()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = Company::create($this->validated($request));

        $this->audit->created('COMPANY', $company->id, $company->getAttributes(), "Company {$company->name} created");

        return redirect()->route('admin.companies.index')->with('success', 'Company created.');
    }

    public function edit(Company $company): View
    {
        return view('admin.companies.form', [
            'company'       => $company,
            'organizations' => Organization::active()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $original = $company->getOriginal();

        $company->update($this->validated($request, $company));

        $this->audit->updated('COMPANY', $company->id, $original, $company->getChanges(),
            "Company {$company->name} updated");

        return redirect()->route('admin.companies.index')->with('success', 'Company updated.');
    }

    private function validated(Request $request, ?Company $company = null): array
    {
        $data = $request->validate([
            'organization_id' => ['required', 'integer', Rule::exists('oracle.organizations', 'id')],
            // Company codes are unique within their organisation.
            'code'            => ['required', 'string', 'max:20',
                Rule::unique('oracle.companies', 'code')
                    ->where(fn ($q) => $q->where('organization_id', $request->integer('organization_id')))
                    ->ignore($company?->id)],
            'name'            => ['required', 'string', 'max:200'],
            'legal_name'      => ['nullable', 'string', 'max:250'],
            // Drives the ticket number series, e.g. IT-2026-000001.
            'ticket_prefix'   => ['required', 'string', 'max:10', 'alpha_dash'],
            'currency_code'   => ['nullable', 'string', 'size:3'],
            'email'           => ['nullable', 'email', 'max:150'],
            'phone'           => ['nullable', 'string', 'max:30'],
            'city'            => ['nullable', 'string', 'max:120'],
            'country'         => ['nullable', 'string', 'max:120'],
            'address'         => ['nullable', 'string', 'max:500'],
            'timezone'        => ['nullable', 'string', 'max:60'],
            'sort_order'      => ['nullable', 'integer', 'min:0'],
        ]);

        // TIMEZONE, CURRENCY_CODE and SORT_ORDER are NOT NULL in Oracle. An
        // untouched form field arrives as null, which overrides the column default
        // instead of falling back to it, so each is filled in here.
        $data['code']          = strtoupper($data['code']);
        $data['ticket_prefix'] = strtoupper($data['ticket_prefix']);
        $data['currency_code'] = strtoupper($data['currency_code'] ?: 'PKR');
        $data['timezone']      = $data['timezone'] ?: config('app.timezone');
        $data['sort_order']    = $data['sort_order'] ?? 0;
        $data['is_active']     = $request->boolean('is_active') ? 1 : 0;

        return $data;
    }
}
