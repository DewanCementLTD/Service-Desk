<?php

namespace App\Console\Commands;

use App\Services\SprintService;
use Illuminate\Console\Command;

/**
 * Opens the current week and month for every company and closes the ones that
 * have expired, returning their unfinished work to the backlog.
 *
 * Named deterministically from the date, so running it twice in a day is a no-op.
 */
class RollSprints extends Command
{
    protected $signature = 'servicedesk:roll-sprints';

    protected $description = 'Open the current weekly and monthly iterations and close expired ones';

    public function handle(SprintService $sprints): int
    {
        $summary = $sprints->roll();

        $this->info("Opened {$summary['opened']}, closed {$summary['closed']}, "
            . "returned {$summary['returned']} item(s) to the backlog.");

        return self::SUCCESS;
    }
}
