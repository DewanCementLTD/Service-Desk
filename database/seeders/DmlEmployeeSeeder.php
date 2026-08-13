<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Location;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Import the Dewan Motors (DML) employee roster from database/data/dml_employees.tsv.
 *
 * The source system identifies people by mobile number, so that is what they sign
 * in with (see LoginController). USERS.EMAIL is NOT NULL and UNIQUE, and nobody in
 * the roster has a mailbox on file, so each account gets a placeholder address
 * derived from the mobile number - it is never sent to.
 *
 * Idempotent: re-running updates existing accounts rather than duplicating them,
 * and it deliberately does NOT reset passwords of accounts that already exist.
 */
class DmlEmployeeSeeder extends Seeder
{
    private const DATA_FILE = __DIR__ . '/../data/dml_employees.tsv';

    private const DEFAULT_PASSWORD = 'abcd1234';

    /** Placeholder mail domain - these addresses are identifiers, not mailboxes. */
    private const MAIL_DOMAIN = 'dml.local';

    private const IT_DEPARTMENT   = '32';

    /** Designation codes that carry management seniority. */
    private const GENERAL_MANAGER = '11';
    private const DIRECTOR        = '24';
    private const DEPUTY_GM       = ['83', '15'];
    private const IT_LEADERSHIP   = ['2', '11', '15', '83'];

    public function run(): void
    {
        $company = Company::where('code', 'DML')->firstOrFail();

        $roles        = Role::pluck('id', 'code');
        $locations    = Location::where('company_id', $company->id)->pluck('id', 'code');
        $departments  = Department::where('company_id', $company->id)->pluck('id', 'code');
        $designations = Designation::where('company_id', $company->id)->pluck('id', 'code');

        $imported = 0;
        $skipped  = [];

        foreach ($this->rows() as $row) {
            $locationId = $locations[$row['location_code']] ?? null;

            if ($locationId === null) {
                $skipped[] = "{$row['name']} (unknown location {$row['location_code']})";
                continue;
            }

            $email = $row['mobile'] . '@' . self::MAIL_DOMAIN;
            $role  = $this->roleFor($row);

            // Tickets are filtered by location, so an engineer tied to a single
            // branch could neither see nor work a ticket raised anywhere else -
            // and would be refused access to one assigned to them. The IT team
            // therefore covers every location of the company they are granted.
            $allLocations = in_array($role, [Role::IT_MANAGER, Role::IT_STAFF], true);

            $user = User::where('email', $email)->first();

            $attributes = [
                'employee_no'         => $row['emp_no'] !== '' ? $row['emp_no'] : null,
                'name'                => $row['name'],
                'email'               => $email,
                'phone'               => $row['mobile'],
                'role_id'             => $roles[$role],
                'department_id'       => $departments[$row['department_code']] ?? null,
                'designation_id'      => $designations[$row['designation_code']] ?? null,
                'default_org_id'      => $company->organization_id,
                'default_company_id'  => $company->id,
                'default_location_id' => $locationId,
                'is_all_orgs'         => 0,
                'is_all_companies'    => 0,
                'is_all_locations'    => $allLocations ? 1 : 0,
                'is_active'           => 1,
            ];

            // Only set the starting password when the account is being created, so a
            // re-run never overwrites a password the employee has since changed.
            if ($user === null) {
                $attributes['password']             = Hash::make(self::DEFAULT_PASSWORD);
                $attributes['must_change_password'] = 1;
            }

            $user = User::updateOrCreate(['email' => $email], $attributes);

            $user->organizations()->syncWithoutDetaching([
                $company->organization_id => ['is_default' => 1, 'granted_at' => now()],
            ]);

            $user->companies()->syncWithoutDetaching([
                $company->id => ['is_default' => 1, 'granted_at' => now()],
            ]);

            $user->locations()->syncWithoutDetaching([
                $locationId => [
                    'company_id' => $company->id,
                    'is_default' => 1,
                    'granted_at' => now(),
                ],
            ]);

            $imported++;
        }

        $this->command?->info("DML employees imported/updated: {$imported}");

        foreach ($skipped as $note) {
            $this->command?->warn("Skipped: {$note}");
        }
    }

    /**
     * Map a row onto an application role. The roster carries no role of its own,
     * so seniority is inferred from department and designation; anything the rules
     * do not recognise lands on the least-privileged role.
     *
     * @param  array<string, string>  $row
     */
    private function roleFor(array $row): string
    {
        if ($row['department_code'] === self::IT_DEPARTMENT) {
            return in_array($row['designation_code'], self::IT_LEADERSHIP, true)
                ? Role::IT_MANAGER
                : Role::IT_STAFF;
        }

        if (in_array($row['designation_code'], [self::GENERAL_MANAGER, self::DIRECTOR], true)) {
            return Role::MANAGEMENT;
        }

        if (in_array($row['designation_code'], self::DEPUTY_GM, true)) {
            return Role::DGM;
        }

        return Role::EMPLOYEE;
    }

    /**
     * Read the tab-separated roster, keyed by its header row.
     *
     * @return \Generator<int, array<string, string>>
     */
    private function rows(): \Generator
    {
        $handle = fopen(self::DATA_FILE, 'r');

        if ($handle === false) {
            throw new \RuntimeException('Cannot read ' . self::DATA_FILE);
        }

        $header = null;

        while (($line = fgets($handle)) !== false) {
            $fields = explode("\t", rtrim($line, "\r\n"));

            if ($header === null) {
                $header = $fields;
                continue;
            }

            if (count($fields) !== count($header)) {
                continue;
            }

            yield array_map('trim', array_combine($header, $fields));
        }

        fclose($handle);
    }
}
