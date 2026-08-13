<?php

namespace Database\Seeders;

use App\Models\DevelopmentStatus;
use App\Models\TaskStatus;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\TicketType;
use Illuminate\Database\Seeder;

/**
 * Master data. Everything here is administrable from the UI later; these are the
 * defaults the system ships with.
 */
class LookupSeeder extends Seeder
{
    public function run(): void
    {
        $this->ticketStatuses();
        $this->ticketPriorities();
        $this->ticketTypesAndCategories();
        $this->taskStatuses();
        $this->developmentStatuses();
    }

    private function ticketStatuses(): void
    {
        // code, name, colour, open?, closed?, stops SLA clock?
        $rows = [
            [TicketStatus::OPEN,        'Open',        'blue',   1, 0, 0],
            [TicketStatus::IN_PROGRESS, 'In Progress', 'amber',  1, 0, 0],
            [TicketStatus::PENDING,     'Pending',     'purple', 1, 0, 1],
            [TicketStatus::RESOLVED,    'Resolved',    'green',  0, 0, 1],
            [TicketStatus::CLOSED,      'Closed',      'slate',  0, 1, 1],
            [TicketStatus::CANCELLED,   'Cancelled',   'red',    0, 1, 1],
        ];

        foreach ($rows as $i => [$code, $name, $color, $open, $closed, $stops]) {
            TicketStatus::updateOrCreate(['code' => $code], [
                'name'            => $name,
                'color'           => $color,
                'is_open_state'   => $open,
                'is_closed_state' => $closed,
                'stops_sla_clock' => $stops,
                'is_active'       => 1,
                'sort_order'      => ($i + 1) * 10,
            ]);
        }
    }

    private function ticketPriorities(): void
    {
        // code, name, colour, response hours, resolution hours, critical?
        $rows = [
            [TicketPriority::LOW,      'Low',      'slate', 24, 120, 0],
            [TicketPriority::MEDIUM,   'Medium',   'blue',   8,  48, 0],
            [TicketPriority::HIGH,     'High',     'amber',  4,  24, 0],
            [TicketPriority::CRITICAL, 'Critical', 'red',    1,   8, 1],
        ];

        foreach ($rows as $i => [$code, $name, $color, $response, $resolution, $critical]) {
            TicketPriority::updateOrCreate(['code' => $code], [
                'name'             => $name,
                'color'            => $color,
                'response_hours'   => $response,
                'resolution_hours' => $resolution,
                'is_critical'      => $critical,
                'is_active'        => 1,
                'sort_order'       => ($i + 1) * 10,
            ]);
        }
    }

    private function ticketTypesAndCategories(): void
    {
        $types = [
            'INCIDENT'       => ['Incident', 'Something is broken or not working.'],
            'SERVICE_REQUEST' => ['Service Request', 'A request for a standard service.'],
            'CHANGE'         => ['Change Request', 'A change to an existing system.'],
            'PROBLEM'        => ['Problem', 'Root cause investigation for recurring incidents.'],
            'ACCESS'         => ['Access Request', 'Access or permission requests.'],
        ];

        $typeIds = [];
        $sort = 0;

        foreach ($types as $code => [$name, $description]) {
            $typeIds[$code] = TicketType::updateOrCreate(['code' => $code], [
                'name'        => $name,
                'description' => $description,
                'is_active'   => 1,
                'sort_order'  => $sort += 10,
            ])->id;
        }

        // category code => [name, parent type]
        $categories = [
            'HARDWARE'      => ['Hardware', 'INCIDENT'],
            'SOFTWARE'      => ['Software', 'INCIDENT'],
            'NETWORK'       => ['Network & Connectivity', 'INCIDENT'],
            'PRINTER'       => ['Printer & Peripherals', 'INCIDENT'],
            'EMAIL'         => ['Email', 'INCIDENT'],
            'ERP'           => ['ERP / Business Application', 'INCIDENT'],
            'ACCOUNT'       => ['Account & Password', 'ACCESS'],
            'PERMISSION'    => ['System Permission', 'ACCESS'],
            'NEW_EQUIPMENT' => ['New Equipment', 'SERVICE_REQUEST'],
            'SOFTWARE_INST' => ['Software Installation', 'SERVICE_REQUEST'],
            'DATA_REQUEST'  => ['Data / Report Request', 'SERVICE_REQUEST'],
            'SYSTEM_CHANGE' => ['System Change', 'CHANGE'],
            'OTHER'         => ['Other', 'SERVICE_REQUEST'],
        ];

        $sort = 0;

        foreach ($categories as $code => [$name, $typeCode]) {
            TicketCategory::updateOrCreate(['code' => $code], [
                'type_id'    => $typeIds[$typeCode] ?? null,
                'name'       => $name,
                'is_active'  => 1,
                'sort_order' => $sort += 10,
            ]);
        }
    }

    private function taskStatuses(): void
    {
        $rows = [
            [TaskStatus::PENDING,     'Pending',     'slate',  0],
            [TaskStatus::IN_PROGRESS, 'In Progress', 'blue',   0],
            [TaskStatus::ON_HOLD,     'On Hold',     'amber',  0],
            [TaskStatus::COMPLETED,   'Completed',   'green',  1],
            [TaskStatus::CANCELLED,   'Cancelled',   'red',    1],
        ];

        foreach ($rows as $i => [$code, $name, $color, $completed]) {
            TaskStatus::updateOrCreate(['code' => $code], [
                'name'               => $name,
                'color'              => $color,
                'is_completed_state' => $completed,
                'is_active'          => 1,
                'sort_order'         => ($i + 1) * 10,
            ]);
        }
    }

    private function developmentStatuses(): void
    {
        $rows = [
            [DevelopmentStatus::REQUESTED,  'Requested',   'slate',  0],
            [DevelopmentStatus::ANALYSIS,   'Analysis',    'purple', 0],
            [DevelopmentStatus::APPROVED,   'Approved',    'blue',   0],
            [DevelopmentStatus::DEVELOPING, 'In Development', 'amber', 0],
            [DevelopmentStatus::TESTING,    'Testing',     'cyan',   0],
            [DevelopmentStatus::UAT,        'UAT',         'indigo', 0],
            [DevelopmentStatus::DEPLOYED,   'Deployed',    'green',  1],
            [DevelopmentStatus::ON_HOLD,    'On Hold',     'orange', 0],
            [DevelopmentStatus::REJECTED,   'Rejected',    'red',    1],
        ];

        foreach ($rows as $i => [$code, $name, $color, $completed]) {
            DevelopmentStatus::updateOrCreate(['code' => $code], [
                'name'               => $name,
                'color'              => $color,
                'is_completed_state' => $completed,
                'is_active'          => 1,
                'sort_order'         => ($i + 1) * 10,
            ]);
        }
    }

}
