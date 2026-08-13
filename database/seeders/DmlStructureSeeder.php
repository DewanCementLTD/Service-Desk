<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Location;
use Illuminate\Database\Seeder;

/**
 * Real organisation structure for Dewan Motors (Pvt) Limited (company code DML):
 * its locations, departments and designations. Codes match the legacy system's
 * LOCATION CODE / DEPART_CODE / DESIGNATION CODE values so imported records
 * line up.
 *
 * Idempotent - re-running updates the existing rows instead of duplicating.
 */
class DmlStructureSeeder extends Seeder
{
    /** code => [name, type, city, is head office?] */
    private const LOCATIONS = [
        '1'  => ['Dewan Centre H.O',                  'HEAD_OFFICE', 'Karachi',   1],
        '17' => ['DMMC Korangi',                      'BRANCH',      'Karachi',   0],
        '4'  => ['PSC Lahore',                        'BRANCH',      'Lahore',    0],
        '2'  => ['PSC Korangi',                       'BRANCH',      'Karachi',   0],
        '18' => ['Body Shop Karachi',                 'BRANCH',      'Karachi',   0],
        '3'  => ['Display Center Karachi',            'BRANCH',      'Karachi',   0],
        '7'  => ['PSC Motorrad Lahore',               'BRANCH',      'Lahore',    0],
        '6'  => ['PSC Motorrad Karachi',              'BRANCH',      'Karachi',   0],
        '13' => ['Display Center Lahore',             'BRANCH',      'Lahore',    0],
        '11' => ['Display Center Motorrad Lahore',    'BRANCH',      'Lahore',    0],
        '14' => ['Display Center Islamabad',          'BRANCH',      'Islamabad', 0],
        '5'  => ['PSC Islamabad',                     'BRANCH',      'Islamabad', 0],
        '12' => ['Display Center Motorrad Islamabad', 'BRANCH',      'Islamabad', 0],
        '8'  => ['PSC Motorrad Islamabad',            'BRANCH',      'Islamabad', 0],
    ];

    /** code => [name, is IT team?] */
    private const DEPARTMENTS = [
        '33' => ['Supply Chain Management', 0],
        '4'  => ['Service', 0],
        '38' => ['D.C. Group Employees', 0],
        '35' => ['Archive', 0],
        '32' => ['Information Technology', 1],
        '6'  => ['Parts', 0],
        '2'  => ['Finance', 0],
        '39' => ['Group Admin', 0],
        '27' => ['Customer Relations', 0],
        '34' => ['Compliance', 0],
        '24' => ['Human Resource', 0],
        '26' => ['Imports', 0],
        '40' => ['Product & Planning', 0],
        '5'  => ['Admin', 0],
        '30' => ['Sales', 0],
        '3'  => ['Sales Admin', 0],
        '1'  => ['Marketing', 0],
        '25' => ['Group Employees', 0],
        '31' => ['Management', 0],
    ];

    /** code => name */
    private const DESIGNATIONS = [
        '84'  => 'Assistant Manager',
        '5'   => 'Technician',
        '46'  => 'Sr. Paint Technician',
        '35'  => 'Driver',
        '12'  => 'Jr. Technician',
        '3'   => 'Deputy Manager',
        '2'   => 'Manager',
        '4'   => 'Supervisor',
        '83'  => 'Deputy General Manager',
        '11'  => 'General Manager',
        '10'  => 'Asst. Manager - I',
        '6'   => 'Executive',
        '45'  => 'Jr. Executive',
        '15'  => 'D.G.M.',
        '41'  => 'Electrician',
        '71'  => 'Support Staff',
        '42'  => 'Cashier',
        '37'  => 'Asst. Manager - II',
        '38'  => 'Team Leader',
        '29'  => 'Sr. Technician',
        '7'   => 'Team Member',
        '13'  => 'Office Assistant',
        '21'  => 'Washman',
        '61'  => 'Office Boy',
        '20'  => 'Service Advisor',
        '47'  => 'Asst. Team Leader',
        '66'  => 'Cleaner',
        // Code 36 duplicates the name of code 10 in the legacy system; both are
        // kept so historic employee records resolve to the code they were filed under.
        '36'  => 'Asst. Manager - I',
        '54'  => 'Group Leader',
        '19'  => 'Body Technician',
        '8'   => 'Paint Technician',
        '100' => 'Senior Body Technician',
        '44'  => 'Dispatcher',
        '67'  => 'Rider',
        '32'  => 'Sr. Denter',
        '43'  => 'Jr. Paint Technician',
        '99'  => 'Assistant Service Advisor',
        '76'  => 'General Technician',
        '17'  => 'Management Trainee',
        '75'  => 'Assistant Accounts',
        '102' => 'Tea Boy',
        '91'  => 'AC Technician',
        '87'  => 'Helper',
        '89'  => 'Sales Consultant',
        '88'  => 'Sweeper',
        '72'  => 'Receptionist',
        '90'  => 'Apprentice Technician',
        '92'  => 'Auto Electrician',
        '82'  => 'Customer Representative',
        '95'  => 'Waiter',
        '78'  => 'Maintenance Supervisor',
        '59'  => 'Sales Coordinator',
        '24'  => 'Director Operations',
    ];

    public function run(): void
    {
        $company = Company::where('code', 'DML')->firstOrFail();

        $headOfficeId = null;
        $sort         = 0;

        foreach (self::LOCATIONS as $code => [$name, $type, $city, $isHead]) {
            $location = Location::updateOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                [
                    'name'           => $name,
                    'location_type'  => $type,
                    'city'           => $city,
                    'country'        => 'Pakistan',
                    'timezone'       => config('app.timezone'),
                    'is_head_office' => $isHead,
                    'is_active'      => 1,
                    'sort_order'     => $sort += 10,
                ]
            );

            if ($isHead) {
                $headOfficeId = $location->id;
            }
        }

        $sort = 0;

        foreach (self::DEPARTMENTS as $code => [$name, $isIt]) {
            Department::updateOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                [
                    'location_id' => $headOfficeId,
                    'name'        => $name,
                    'is_it_team'  => $isIt,
                    'is_active'   => 1,
                    'sort_order'  => $sort += 10,
                ]
            );
        }

        $sort = 0;

        foreach (self::DESIGNATIONS as $code => $name) {
            Designation::updateOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                [
                    'name'       => $name,
                    'is_active'  => 1,
                    'sort_order' => $sort += 10,
                ]
            );
        }
    }
}
