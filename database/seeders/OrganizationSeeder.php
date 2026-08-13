<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Department;
use App\Models\Location;
use App\Models\Organization;
use Illuminate\Database\Seeder;

/**
 * Baseline organisation structure: one group with two companies, each with
 * several locations and a standard department set.
 */
class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $group = Organization::updateOrCreate(['code' => 'GRP'], [
            'name'        => 'Group Holdings',
            'legal_name'  => 'Group Holdings Limited',
            'description' => 'Parent organisation for all operating companies.',
            'city'        => 'Head Office',
            'country'     => 'United Arab Emirates',
            'email'       => 'info@group.example',
            'timezone'    => config('app.timezone'),
            'is_active'   => 1,
            'sort_order'  => 10,
        ]);

        // company code => [name, ticket prefix, [location code => [name, type, city, head office?]]]
        $companies = [
            'MFG' => ['Group Manufacturing', 'IT', [
                'MFG-HO'  => ['Manufacturing Head Office', 'HEAD_OFFICE', 'Dubai', 1],
                'MFG-PL1' => ['Plant 1',                   'PLANT',       'Jebel Ali', 0],
                'MFG-WH'  => ['Central Warehouse',         'WAREHOUSE',   'Jebel Ali', 0],
            ]],
            'TRD' => ['Group Trading', 'TRD', [
                'TRD-HO'  => ['Trading Head Office', 'HEAD_OFFICE', 'Abu Dhabi', 1],
                'TRD-BR1' => ['Branch - Sharjah',    'BRANCH',      'Sharjah', 0],
            ]],
        ];

        // Standard department set applied to each company's head office.
        $departments = [
            'IT'      => ['Information Technology', 1],
            'FIN'     => ['Finance', 0],
            'HR'      => ['Human Resources', 0],
            'OPS'     => ['Operations', 0],
            'SALES'   => ['Sales & Marketing', 0],
            'COMP'    => ['Compliance & Risk', 0],
            'ADMIN'   => ['Administration', 0],
        ];

        $sort = 0;

        foreach ($companies as $code => [$name, $prefix, $locations]) {
            $company = Company::updateOrCreate(
                ['organization_id' => $group->id, 'code' => $code],
                [
                    'name'          => $name,
                    'legal_name'    => $name . ' LLC',
                    'ticket_prefix' => $prefix,
                    'country'       => 'United Arab Emirates',
                    'currency_code' => 'AED',
                    'timezone'      => config('app.timezone'),
                    'is_active'     => 1,
                    'sort_order'    => $sort += 10,
                ]
            );

            $headOfficeId = null;
            $locSort = 0;

            foreach ($locations as $locCode => [$locName, $type, $city, $isHead]) {
                $location = Location::updateOrCreate(
                    ['company_id' => $company->id, 'code' => $locCode],
                    [
                        'name'           => $locName,
                        'location_type'  => $type,
                        'city'           => $city,
                        'country'        => 'United Arab Emirates',
                        'timezone'       => config('app.timezone'),
                        'is_head_office' => $isHead,
                        'is_active'      => 1,
                        'sort_order'     => $locSort += 10,
                    ]
                );

                if ($isHead) {
                    $headOfficeId = $location->id;
                }
            }

            $deptSort = 0;

            foreach ($departments as $deptCode => [$deptName, $isIt]) {
                Department::updateOrCreate(
                    ['company_id' => $company->id, 'code' => $deptCode],
                    [
                        'location_id' => $headOfficeId,
                        'name'        => $deptName,
                        'is_it_team'  => $isIt,
                        'is_active'   => 1,
                        'sort_order'  => $deptSort += 10,
                    ]
                );
            }
        }
    }
}
