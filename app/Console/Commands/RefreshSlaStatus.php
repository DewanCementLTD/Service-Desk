<?php

namespace App\Console\Commands;

use App\Services\TicketService;
use Illuminate\Console\Command;

/**
 * Recalculates SLA flags on open tickets.
 *
 * SLA state is time-dependent, so it is refreshed on a schedule rather than
 * being recomputed on every page load.
 */
class RefreshSlaStatus extends Command
{
    protected $signature = 'servicedesk:refresh-sla';

    protected $description = 'Recalculate SLA status and overdue flags for open tickets';

    public function handle(TicketService $tickets): int
    {
        $this->info('Refreshing SLA flags…');

        $changed = $tickets->refreshSlaFlags();

        $this->info("{$changed} ticket(s) updated.");

        return self::SUCCESS;
    }
}
