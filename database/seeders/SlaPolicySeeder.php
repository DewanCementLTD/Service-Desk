<?php

namespace Database\Seeders;

use App\Models\SlaPolicy;
use App\Models\TicketPriority;
use Illuminate\Database\Seeder;

/**
 * Group-wide default SLA policies (company_id null = applies to every company).
 * A company-specific policy created later automatically takes precedence.
 */
class SlaPolicySeeder extends Seeder
{
    public function run(): void
    {
        $priorities = TicketPriority::pluck('id', 'code');

        $rows = [
            [TicketPriority::CRITICAL, 'Critical - 1h response / 8h resolution',  1,   8],
            [TicketPriority::HIGH,     'High - 4h response / 24h resolution',     4,  24],
            [TicketPriority::MEDIUM,   'Medium - 8h response / 48h resolution',   8,  48],
            [TicketPriority::LOW,      'Low - 24h response / 120h resolution',   24, 120],
        ];

        foreach ($rows as [$priorityCode, $name, $response, $resolution]) {
            SlaPolicy::updateOrCreate(
                ['code' => 'DEFAULT_' . $priorityCode],
                [
                    'name'             => $name,
                    'description'      => 'Group default service level for ' . strtolower($priorityCode) . ' priority tickets.',
                    'company_id'       => null,
                    'priority_id'      => $priorities[$priorityCode] ?? null,
                    'type_id'          => null,
                    'response_hours'   => $response,
                    'resolution_hours' => $resolution,
                    'is_active'        => 1,
                ]
            );
        }
    }
}
