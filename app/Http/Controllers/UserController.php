<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Location;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private AuditLogger $audit)
    {
    }

    public function index(Request $request): View
    {
        $query = User::with(['role', 'department', 'defaultCompany'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . strtoupper($request->string('q')) . '%';
                $q->where(fn ($w) => $w->whereRaw('UPPER(NAME) LIKE ?', [$term])
                    ->orWhereRaw('UPPER(EMAIL) LIKE ?', [$term])
                    ->orWhereRaw('UPPER(EMPLOYEE_NO) LIKE ?', [$term]));
            })
            ->when($request->filled('role_id'), fn ($q) => $q->where('role_id', $request->integer('role_id')))
            // A user flagged "all companies"/"all locations" has no pivot row for
            // any single company/location, so the filter has to admit them too —
            // otherwise IT staff and admins silently vanish from every company-
            // or location-scoped search.
            ->when($request->filled('company_id'), function ($q) use ($request) {
                $companyId = $request->integer('company_id');
                $q->where(fn ($w) => $w->where('is_all_companies', 1)
                    ->orWhereHas('companies', fn ($c) => $c->where('companies.id', $companyId)));
            })
            ->when($request->filled('location_id'), function ($q) use ($request) {
                $locationId = $request->integer('location_id');
                $q->where(fn ($w) => $w->where('is_all_locations', 1)
                    ->orWhereHas('locations', fn ($l) => $l->where('locations.id', $locationId)));
            })
            ->when($request->input('status') === 'inactive', fn ($q) => $q->where('is_active', 0))
            ->when($request->input('status') === 'active', fn ($q) => $q->where('is_active', 1));

        return view('users.index', [
            'users'     => $query->orderBy('name')->paginate(20)->withQueryString(),
            'roles'     => Role::active()->orderBy('sort_order')->get(),
            'companies' => Company::active()->orderBy('name')->get(),
            'locations' => Location::active()->with('company')->orderBy('name')->get(),
            'filters'   => $request->only(['q', 'role_id', 'company_id', 'location_id', 'status']),
        ]);
    }

    public function create(): View
    {
        return view('users.create', $this->formData());
    }

    /**
     * Departments and designations that belong to one company, for the
     * create/edit form's Company → Department cascade (see
     * resources/js/user-form.js). JSON, not a page.
     */
    public function companyOptions(Company $company): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'departments'  => Department::active()->where('company_id', $company->id)
                ->orderBy('name')->get(['id', 'name']),
            'designations' => Designation::active()->where('company_id', $company->id)
                ->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('users.manage'), 403);

        $data = $this->validateUser($request);

        $user = DB::connection('oracle')->transaction(function () use ($data, $request) {
            $model = User::create([
                'employee_no'         => $data['employee_no'] ?? null,
                'name'                => $data['name'],
                'email'               => $data['email'],
                'password'            => Hash::make($data['password']),
                // An administrator knows this password, so the user must replace it.
                'must_change_password' => 1,
                'role_id'             => $data['role_id'],
                'department_id'       => $data['department_id'] ?? null,
                'designation_id'      => $data['designation_id'] ?? null,
                'phone'               => $data['phone'] ?? null,
                'default_org_id'      => $data['default_org_id'] ?? null,
                'default_company_id'  => $data['default_company_id'] ?? null,
                'default_location_id' => $data['default_location_id'] ?? null,
                'is_all_orgs'         => $request->boolean('is_all_orgs') ? 1 : 0,
                'is_all_companies'    => $request->boolean('is_all_companies') ? 1 : 0,
                'is_all_locations'    => $request->boolean('is_all_locations') ? 1 : 0,
                'is_active'           => $request->boolean('is_active', true) ? 1 : 0,
            ]);

            $this->syncGrants($model, $request);

            $this->audit->created('USER', $model->id, $model->getAttributes(), "User {$model->email} created");

            return $model;
        });

        return redirect()->route('users.edit', $user)->with('success', 'User created.');
    }

    public function edit(User $user): View
    {
        return view('users.edit', array_merge($this->formData(), [
            'user'          => $user,
            'grantedCompanies' => $user->companies()->pluck('companies.id')->all(),
            'grantedLocations' => $user->locations()->pluck('locations.id')->all(),
            'grantedOrgs'      => $user->organizations()->pluck('organizations.id')->all(),
        ]));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('users.manage'), 403);

        $data = $this->validateUser($request, $user);
        $original = $user->getOriginal();

        DB::connection('oracle')->transaction(function () use ($user, $data, $request, $original) {
            $attributes = [
                'employee_no'         => $data['employee_no'] ?? null,
                'name'                => $data['name'],
                'email'               => $data['email'],
                'role_id'             => $data['role_id'],
                'department_id'       => $data['department_id'] ?? null,
                'designation_id'      => $data['designation_id'] ?? null,
                'phone'               => $data['phone'] ?? null,
                'default_org_id'      => $data['default_org_id'] ?? null,
                'default_company_id'  => $data['default_company_id'] ?? null,
                'default_location_id' => $data['default_location_id'] ?? null,
                'is_all_orgs'         => $request->boolean('is_all_orgs') ? 1 : 0,
                'is_all_companies'    => $request->boolean('is_all_companies') ? 1 : 0,
                'is_all_locations'    => $request->boolean('is_all_locations') ? 1 : 0,
                'is_active'           => $request->boolean('is_active') ? 1 : 0,
            ];

            if (! empty($data['password'])) {
                $attributes['password'] = Hash::make($data['password']);
                // A reset issued from here is known to the administrator who typed it.
                $attributes['must_change_password'] = 1;
            }

            $user->update($attributes);
            $this->syncGrants($user, $request);

            $this->audit->updated('USER', $user->id, $original, $user->getChanges(), "User {$user->email} updated");
        });

        return back()->with('success', 'User updated.');
    }

    /**
     * Issue a one-time password so an administrator can get a locked-out user back
     * in. The generated password is shown to the administrator once, in the flash
     * message, and is never stored in readable form or written to the audit trail.
     * The user must replace it the moment they sign in.
     */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('users.manage'), 403);
        abort_if($user->id === $request->user()->id, 400,
            'Use Change password to set your own password.');

        $temporary = $this->temporaryPassword();

        $user->forceFill([
            'password'             => Hash::make($temporary),
            'must_change_password' => 1,
            'password_changed_at'  => null,
        ])->save();

        $this->audit->log('PASSWORD_RESET', 'USER', $user->id,
            "{$request->user()->name} issued a temporary password to {$user->name}");

        return back()->with('temporaryPassword', [
            'name'     => $user->name,
            'login'    => $user->phone ?: $user->email,
            'password' => $temporary,
        ]);
    }

    /**
     * Readable enough to be dictated over the phone: no ambiguous characters, and
     * it satisfies the letters-and-numbers rule the change screen enforces.
     */
    private function temporaryPassword(): string
    {
        $letters = 'abcdefghjkmnpqrstuvwxyz';   // no i, l, o
        $digits  = '23456789';                  // no 0, 1

        $password = '';

        for ($i = 0; $i < 4; $i++) {
            $password .= $letters[random_int(0, strlen($letters) - 1)];
        }

        for ($i = 0; $i < 4; $i++) {
            $password .= $digits[random_int(0, strlen($digits) - 1)];
        }

        return $password;
    }

    /** Deactivate rather than delete, so historical references stay intact. */
    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('users.manage'), 403);
        abort_if($user->id === $request->user()->id, 400, 'You cannot deactivate your own account.');

        $user->update(['is_active' => $user->is_active ? 0 : 1]);

        $this->audit->log($user->is_active ? 'ACTIVATE' : 'DEACTIVATE', 'USER', $user->id,
            "User {$user->email} " . ($user->is_active ? 'activated' : 'deactivated'));

        return back()->with('success', 'User ' . ($user->is_active ? 'activated' : 'deactivated') . '.');
    }

    // ------------------------------------------------------------------ helpers

    private function validateUser(Request $request, ?User $user = null): array
    {
        // Phone is how the DML roster signs in and the only contact detail
        // guaranteed on file, so it is required; email is not. USERS.EMAIL stays
        // NOT NULL/UNIQUE at the database level, so a blank email is backed by
        // the same placeholder-address convention DmlEmployeeSeeder already uses
        // for the bulk-imported roster — User::hasReachableEmail() already knows
        // to skip these when sending mail.
        if ($request->filled('phone') && ! $request->filled('email')) {
            $request->merge(['email' => $this->placeholderEmail($request->string('phone')->toString())]);
        }

        return $request->validate([
            'name'                => ['required', 'string', 'max:150'],
            'email'               => ['required', 'email', 'max:150',
                Rule::unique('oracle.users', 'email')->ignore($user?->id)],
            'employee_no'         => ['nullable', 'string', 'max:30',
                Rule::unique('oracle.users', 'employee_no')->ignore($user?->id)],
            'password'            => [$user === null ? 'required' : 'nullable', 'string', 'min:8'],
            'role_id'             => ['required', 'integer', Rule::exists('oracle.roles', 'id')],
            'department_id'       => ['nullable', 'integer'],
            'designation_id'      => ['nullable', 'integer', Rule::exists('oracle.designations', 'id')],
            'phone'               => ['required', 'string', 'max:30',
                Rule::unique('oracle.users', 'phone')->ignore($user?->id)],
            'default_org_id'      => ['nullable', 'integer'],
            // Required: it drives the Department/Designation choices right below
            // it on the form (see resources/js/user-form.js).
            'default_company_id'  => ['required', 'integer', Rule::exists('oracle.companies', 'id')],
            'default_location_id' => ['nullable', 'integer'],
            'company_ids'         => ['nullable', 'array'],
            'company_ids.*'       => ['integer'],
            'location_ids'        => ['nullable', 'array'],
            'location_ids.*'      => ['integer'],
            'organization_ids'    => ['nullable', 'array'],
            'organization_ids.*'  => ['integer'],
        ]);
    }

    /** A stable, never-mailed placeholder for an account with no real mailbox yet. */
    private function placeholderEmail(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        return ($digits !== '' ? $digits : $phone) . '@' . config('servicedesk.placeholder_mail_domain');
    }

    /** Replace the user's organisation/company/location grants. */
    private function syncGrants(User $user, Request $request): void
    {
        $defaultCompany  = $request->integer('default_company_id') ?: null;
        $defaultLocation = $request->integer('default_location_id') ?: null;
        $defaultOrg      = $request->integer('default_org_id') ?: null;

        $user->organizations()->sync(
            collect($request->input('organization_ids', []))
                ->mapWithKeys(fn ($id) => [(int) $id => [
                    'is_default' => (int) $id === $defaultOrg ? 1 : 0,
                    'granted_by' => $request->user()->id,
                    'granted_at' => now(),
                ]])->all()
        );

        $user->companies()->sync(
            collect($request->input('company_ids', []))
                ->mapWithKeys(fn ($id) => [(int) $id => [
                    'is_default' => (int) $id === $defaultCompany ? 1 : 0,
                    'granted_by' => $request->user()->id,
                    'granted_at' => now(),
                ]])->all()
        );

        // The pivot carries company_id so location grants can be filtered per company.
        $companyByLocation = Location::whereIn('id', $request->input('location_ids', []))
            ->pluck('company_id', 'id');

        $user->locations()->sync(
            collect($request->input('location_ids', []))
                ->mapWithKeys(fn ($id) => [(int) $id => [
                    'company_id' => $companyByLocation[(int) $id] ?? null,
                    'is_default' => (int) $id === $defaultLocation ? 1 : 0,
                    'granted_by' => $request->user()->id,
                    'granted_at' => now(),
                ]])->all()
        );
    }

    private function formData(): array
    {
        return [
            'roles'         => Role::active()->orderBy('sort_order')->get(),
            'organizations' => Organization::active()->orderBy('name')->get(),
            'companies'     => Company::active()->with('organization')->orderBy('name')->get(),
            'locations'     => Location::active()->with('company')->orderBy('name')->get(),
            'departments'   => Department::active()->with('company')->orderBy('name')->get(),
            'designations'  => Designation::active()->with('company')->orderBy('name')->get(),
        ];
    }
}
