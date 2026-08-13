{{--
    Shared create/edit form.

    The access section is the important part: a user may be granted any number of
    organisations, companies and locations, or given a blanket "all" flag that
    keeps them in step as new ones are added.
--}}
@php
    $user             = $user ?? null;
    $grantedOrgs      = $grantedOrgs ?? [];
    $grantedCompanies = $grantedCompanies ?? [];
    $grantedLocations = $grantedLocations ?? [];
@endphp

<x-card title="Account">
    <div class="grid grid-cols-1 gap-4 p-4 md:grid-cols-2" id="user-account-fields"
         data-company-options-template="{{ route('users.companyOptions', ['company' => '__ID__']) }}">
        <x-field label="Full name" name="name" :required="true">
            <input name="name" value="{{ old('name', $user?->name) }}" required
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
        </x-field>

        <x-field label="Company" name="default_company_id" :required="true"
                 help="Picked first — it decides which departments and designations you can choose below.">
            <select name="default_company_id" id="user-company-select" required
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                <option value="">Select a company…</option>
                @foreach($companies as $company)
                    <option value="{{ $company->id }}" @selected(old('default_company_id', $user?->default_company_id) == $company->id)>{{ $company->name }}</option>
                @endforeach
            </select>
        </x-field>

        <x-field label="Email address" name="email"
                 help="Optional — leave blank if this person has no mailbox yet. A placeholder is used internally until one is added.">
            <input type="email" name="email" value="{{ old('email', $user?->email) }}"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
        </x-field>

        <x-field label="Phone" name="phone" :required="true" help="Required — used to sign in and to reach this person directly.">
            <input name="phone" value="{{ old('phone', $user?->phone) }}" required
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
        </x-field>

        <x-field label="Employee number" name="employee_no">
            <input name="employee_no" value="{{ old('employee_no', $user?->employee_no) }}"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
        </x-field>

        <x-field label="Password" name="password" :required="$user === null"
                 help="{{ $user ? 'Leave blank to keep the current password.' : 'Minimum 8 characters.' }}">
            <input type="password" name="password" @required($user === null) minlength="8"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
        </x-field>

        <x-field label="Role" name="role_id" :required="true">
            <select name="role_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                @foreach($roles as $role)
                    <option value="{{ $role->id }}" @selected(old('role_id', $user?->role_id) == $role->id)>{{ $role->name }}</option>
                @endforeach
            </select>
        </x-field>

        <x-field label="Department" name="department_id"
                 help="Filled in once a company is picked above.">
            <select name="department_id" id="user-department-select" data-selected="{{ old('department_id', $user?->department_id) }}"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                <option value="">—</option>
                @foreach($departments as $department)
                    <option value="{{ $department->id }}" data-company="{{ $department->company_id }}"
                            @selected(old('department_id', $user?->department_id) == $department->id)>
                        {{ $department->company?->code }} · {{ $department->name }}
                    </option>
                @endforeach
            </select>
        </x-field>

        <x-field label="Designation" name="designation_id"
                 help="{{ $user?->legacy_designation ? 'Previously recorded as: ' . $user->legacy_designation : 'Filled in once a company is picked above.' }}">
            <select name="designation_id" id="user-designation-select" data-selected="{{ old('designation_id', $user?->designation_id) }}"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                <option value="">—</option>
                @foreach($designations as $designation)
                    <option value="{{ $designation->id }}" data-company="{{ $designation->company_id }}"
                            @selected(old('designation_id', $user?->designation_id) == $designation->id)>
                        {{ $designation->company?->code }} · {{ $designation->name }}
                    </option>
                @endforeach
            </select>
        </x-field>

        <x-field label="Account status" name="is_active">
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user?->is_active ?? true)) class="rounded border-slate-300">
                Active — the user can sign in
            </label>
        </x-field>
    </div>
</x-card>

<x-card title="Access scope" subtitle="Which organisations, companies and locations this user can work in.">
    <div class="space-y-5 p-4">

        {{-- ------------------------------------------------------ organisations --}}
        <div>
            <div class="mb-2 flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Organisations</p>
                <label class="flex items-center gap-1.5 text-xs">
                    <input type="checkbox" name="is_all_orgs" value="1" @checked(old('is_all_orgs', $user?->is_all_orgs)) class="rounded border-slate-300">
                    Grant all organisations
                </label>
            </div>
            <div class="grid grid-cols-2 gap-1.5 md:grid-cols-4">
                @foreach($organizations as $organization)
                    <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-2.5 py-1.5 text-sm dark:border-slate-700">
                        <input type="checkbox" name="organization_ids[]" value="{{ $organization->id }}"
                               @checked(in_array($organization->id, old('organization_ids', $grantedOrgs), false))
                               class="rounded border-slate-300">
                        <span class="truncate">{{ $organization->name }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        {{-- ---------------------------------------------------------- companies --}}
        <div>
            <div class="mb-2 flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Companies</p>
                <label class="flex items-center gap-1.5 text-xs">
                    <input type="checkbox" name="is_all_companies" value="1" @checked(old('is_all_companies', $user?->is_all_companies)) class="rounded border-slate-300">
                    Grant all companies
                </label>
            </div>
            <div class="grid grid-cols-2 gap-1.5 md:grid-cols-3">
                @foreach($companies as $company)
                    <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-2.5 py-1.5 text-sm dark:border-slate-700">
                        <input type="checkbox" name="company_ids[]" value="{{ $company->id }}"
                               @checked(in_array($company->id, old('company_ids', $grantedCompanies), false))
                               class="rounded border-slate-300">
                        <span class="truncate">{{ $company->name }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        {{-- ---------------------------------------------------------- locations --}}
        <div>
            <div class="mb-2 flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Locations</p>
                <label class="flex items-center gap-1.5 text-xs">
                    <input type="checkbox" name="is_all_locations" value="1" @checked(old('is_all_locations', $user?->is_all_locations)) class="rounded border-slate-300">
                    Grant all locations
                </label>
            </div>
            <div class="grid grid-cols-2 gap-1.5 md:grid-cols-3">
                @foreach($locations as $location)
                    <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-2.5 py-1.5 text-sm dark:border-slate-700">
                        <input type="checkbox" name="location_ids[]" value="{{ $location->id }}"
                               @checked(in_array($location->id, old('location_ids', $grantedLocations), false))
                               class="rounded border-slate-300">
                        <span class="truncate">{{ $location->company?->code }} · {{ $location->name }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        {{-- ------------------------------------------------------------ defaults --}}
        {{-- Company is chosen once, up in the Account card — it drives Department
             and Designation there, so it isn't repeated here. --}}
        <div class="grid grid-cols-1 gap-4 border-t border-slate-200 pt-4 md:grid-cols-2 dark:border-slate-800">
            <x-field label="Default organisation" name="default_org_id">
                <select name="default_org_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                    <option value="">—</option>
                    @foreach($organizations as $organization)
                        <option value="{{ $organization->id }}" @selected(old('default_org_id', $user?->default_org_id) == $organization->id)>{{ $organization->name }}</option>
                    @endforeach
                </select>
            </x-field>

            <x-field label="Default location" name="default_location_id">
                <select name="default_location_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                    <option value="">All locations</option>
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}" @selected(old('default_location_id', $user?->default_location_id) == $location->id)>
                            {{ $location->company?->code }} · {{ $location->name }}
                        </option>
                    @endforeach
                </select>
            </x-field>
        </div>
    </div>
</x-card>
