<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Location;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seed users and their organisation/company/location grants.
 *
 * Development credentials only - every account below uses the same password and
 * must be changed before the system is used with real data.
 */
class UserSeeder extends Seeder
{
    private const DEFAULT_PASSWORD = 'Password@123';

    public function run(): void
    {
        $org       = Organization::where('code', 'GRP')->firstOrFail();
        $mfg       = Company::where('code', 'MFG')->firstOrFail();
        $trd       = Company::where('code', 'TRD')->firstOrFail();
        $roles     = Role::pluck('id', 'code');

        /**
         * employee_no => [name, email, role, company codes (or '*'),
         *                 location codes (or '*'), department code, designation]
         */
        $users = [
            'EMP0001' => ['System Administrator', 'admin@servicedesk.local', Role::ADMIN,
                '*', '*', ['MFG' => 'IT'], 'System Administrator'],

            'EMP0002' => ['Deputy General Manager', 'dgm@servicedesk.local', Role::DGM,
                ['MFG', 'TRD'], '*', ['MFG' => 'ADMIN'], 'Deputy General Manager'],

            'EMP0003' => ['Executive Management', 'management@servicedesk.local', Role::MANAGEMENT,
                ['MFG', 'TRD'], '*', ['MFG' => 'ADMIN'], 'General Manager'],

            'EMP0004' => ['IT Manager', 'itmanager@servicedesk.local', Role::IT_MANAGER,
                ['MFG', 'TRD'], '*', ['MFG' => 'IT'], 'IT Manager'],

            'EMP0005' => ['Support Engineer', 'support@servicedesk.local', Role::IT_STAFF,
                ['MFG'], ['MFG-HO', 'MFG-PL1', 'MFG-WH'], ['MFG' => 'IT'], 'IT Support Engineer'],

            'EMP0006' => ['Support Engineer (Trading)', 'support2@servicedesk.local', Role::IT_STAFF,
                ['TRD'], ['TRD-HO', 'TRD-BR1'], ['TRD' => 'IT'], 'IT Support Engineer'],

            'EMP0007' => ['Application Developer', 'developer@servicedesk.local', Role::DEVELOPER,
                ['MFG', 'TRD'], '*', ['MFG' => 'IT'], 'Software Developer'],

            'EMP0009' => ['Finance Head', 'finance.head@servicedesk.local', Role::DEPT_HEAD,
                ['MFG'], ['MFG-HO'], ['MFG' => 'FIN'], 'Head of Finance'],

            'EMP0010' => ['Operations Head', 'ops.head@servicedesk.local', Role::DEPT_HEAD,
                ['MFG', 'TRD'], '*', ['MFG' => 'OPS'], 'Head of Operations'],

            'EMP0011' => ['Sara Employee', 'employee@servicedesk.local', Role::EMPLOYEE,
                ['MFG'], ['MFG-HO'], ['MFG' => 'SALES'], 'Sales Executive'],

            'EMP0012' => ['Omar Employee', 'employee2@servicedesk.local', Role::EMPLOYEE,
                ['TRD'], ['TRD-HO'], ['TRD' => 'OPS'], 'Operations Officer'],
        ];

        $companiesByCode = ['MFG' => $mfg, 'TRD' => $trd];

        foreach ($users as $employeeNo => [$name, $email, $roleCode, $companyCodes, $locationCodes, $dept, $designation]) {
            [$deptCompanyCode, $deptCode] = [array_key_first($dept), reset($dept)];
            $deptCompany = $companiesByCode[$deptCompanyCode];

            $department = Department::where('company_id', $deptCompany->id)
                ->where('code', $deptCode)->first();

            $allCompanies = $companyCodes === '*';
            $allLocations = $locationCodes === '*';

            // Resolve the granted companies and the default (first) one.
            $grantCompanies = $allCompanies
                ? Company::pluck('id')->all()
                : collect($companyCodes)->map(fn ($c) => $companiesByCode[$c]->id)->all();

            $defaultCompanyId = $grantCompanies[0] ?? null;

            $grantLocations = $allLocations
                ? Location::whereIn('company_id', $grantCompanies)->pluck('id')->all()
                : Location::whereIn('code', (array) $locationCodes)->pluck('id')->all();

            $defaultLocationId = Location::whereIn('id', $grantLocations)
                ->where('company_id', $defaultCompanyId)
                ->orderByDesc('is_head_office')
                ->value('id');

            $user = User::updateOrCreate(['email' => $email], [
                'employee_no'         => $employeeNo,
                'name'                => $name,
                'password'            => Hash::make(self::DEFAULT_PASSWORD),
                'role_id'             => $roles[$roleCode],
                'department_id'       => $department?->id,
                'default_org_id'      => $org->id,
                'default_company_id'  => $defaultCompanyId,
                'default_location_id' => $defaultLocationId,
                'designation_id'      => $this->designationId($defaultCompanyId, $designation),
                'is_all_orgs'         => $roleCode === Role::ADMIN ? 1 : 0,
                'is_all_companies'    => $allCompanies ? 1 : 0,
                'is_all_locations'    => $allLocations ? 1 : 0,
                'is_active'           => 1,
            ]);

            // Explicit grants are written even for wildcard users so the
            // administration screens show a complete picture.
            $user->organizations()->syncWithoutDetaching([
                $org->id => ['is_default' => 1, 'granted_at' => now()],
            ]);

            $user->companies()->sync(
                collect($grantCompanies)
                    ->mapWithKeys(fn ($id) => [$id => ['is_default' => $id === $defaultCompanyId ? 1 : 0]])
                    ->all()
            );

            $user->locations()->sync(
                collect($grantLocations)
                    ->mapWithKeys(fn ($id) => [$id => [
                        'company_id' => Location::where('id', $id)->value('company_id'),
                        'is_default' => $id === $defaultLocationId ? 1 : 0,
                    ]])
                    ->all()
            );
        }

        $this->assignDepartmentHeads();
    }

    /**
     * Resolve a job title to a designation row on the user's default company,
     * creating it on demand. Designations are company-scoped, so the demo
     * companies get their own set rather than borrowing another company's codes.
     */
    private function designationId(?int $companyId, string $title): ?int
    {
        if ($companyId === null) {
            return null;
        }

        return Designation::firstOrCreate(
            ['company_id' => $companyId, 'code' => Str::upper(Str::slug($title))],
            ['name' => $title, 'is_active' => 1, 'sort_order' => 0]
        )->id;
    }

    /** Point each seeded department at its head, where one exists. */
    private function assignDepartmentHeads(): void
    {
        $heads = [
            'FIN' => 'finance.head@servicedesk.local',
            'OPS' => 'ops.head@servicedesk.local',
            'IT'  => 'itmanager@servicedesk.local',
            'COMP' => 'compliance@servicedesk.local',
        ];

        foreach ($heads as $deptCode => $email) {
            $userId = User::where('email', $email)->value('id');

            if ($userId !== null) {
                Department::where('code', $deptCode)->update(['head_user_id' => $userId]);
            }
        }
    }
}
