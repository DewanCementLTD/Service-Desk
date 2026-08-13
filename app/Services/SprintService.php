<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Sprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Iteration lifecycle: open the current week and month, close the ones that have
 * expired, and return unfinished work to the backlog when they do.
 *
 * Sprints are named deterministically from the date ("Week 34 · 2026"), so the
 * roll is idempotent — running it twice on the same day changes nothing.
 */
class SprintService
{
    public function __construct(private AuditLogger $audit)
    {
    }

    /**
     * Bring every company's board up to date. Returns what changed, for the
     * scheduled command's output.
     *
     * @return array{opened: int, closed: int, returned: int}
     */
    public function roll(?Carbon $now = null): array
    {
        $now     = $now ?? Carbon::now();
        $summary = ['opened' => 0, 'closed' => 0, 'returned' => 0];

        foreach (Company::active()->pluck('id') as $companyId) {
            foreach ([Sprint::WEEKLY, Sprint::MONTHLY] as $cadence) {
                $result = $this->rollCompany((int) $companyId, $cadence, $now);

                $summary['opened']   += $result['opened'];
                $summary['closed']   += $result['closed'];
                $summary['returned'] += $result['returned'];
            }
        }

        return $summary;
    }

    /** @return array{opened: int, closed: int, returned: int} */
    private function rollCompany(int $companyId, string $cadence, Carbon $now): array
    {
        $summary = ['opened' => 0, 'closed' => 0, 'returned' => 0];

        return DB::connection('oracle')->transaction(function () use ($companyId, $cadence, $now, $summary) {
            // Anything whose window has passed is closed, and whatever was left
            // unfinished in it goes back to the backlog rather than disappearing
            // with the sprint.
            $expired = Sprint::forCompany($companyId)
                ->cadence($cadence)
                ->open()
                ->whereDate('ends_on', '<', $now->toDateString())
                ->get();

            foreach ($expired as $sprint) {
                $summary['returned'] += $this->close($sprint);
                $summary['closed']++;
            }

            $current = $this->currentWindow($cadence, $now);

            $sprint = Sprint::forCompany($companyId)
                ->cadence($cadence)
                ->where('name', $current['name'])
                ->first();

            if ($sprint === null) {
                Sprint::create([
                    'company_id' => $companyId,
                    'name'       => $current['name'],
                    'cadence'    => $cadence,
                    'starts_on'  => $current['starts_on'],
                    'ends_on'    => $current['ends_on'],
                    'state'      => Sprint::ACTIVE,
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]);

                $summary['opened']++;
            } elseif ($sprint->state === Sprint::PLANNED) {
                $sprint->update(['state' => Sprint::ACTIVE, 'updated_by' => Auth::id()]);
                $summary['opened']++;
            }

            return $summary;
        });
    }

    /**
     * Close a sprint and send its unfinished work back to the backlog. Finished
     * work keeps its sprint, which is what makes "what did we ship last week?"
     * answerable afterwards.
     *
     * @return int  how many items returned to the backlog
     */
    public function close(Sprint $sprint): int
    {
        return DB::connection('oracle')->transaction(function () use ($sprint) {
            $tickets = $sprint->tickets()
                ->whereHas('status', fn ($q) => $q->where('is_closed_state', 0))
                ->update(['sprint_id' => null, 'board_rank' => null]);

            $requests = $sprint->developmentRequests()
                ->whereHas('status', fn ($q) => $q->where('is_completed_state', 0))
                ->update(['sprint_id' => null, 'board_rank' => null]);

            $sprint->update([
                'state'      => Sprint::CLOSED,
                'closed_at'  => now(),
                'closed_by'  => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $returned = $tickets + $requests;

            $this->audit->log('SPRINT_CLOSE', 'SPRINT', $sprint->id,
                "Sprint {$sprint->name} closed; {$returned} item(s) returned to the backlog");

            return $returned;
        });
    }

    /** The sprint a company is currently working in, opening one if none exists. */
    public function current(int $companyId, string $cadence = Sprint::WEEKLY): ?Sprint
    {
        $sprint = Sprint::forCompany($companyId)->cadence($cadence)->active()
            ->orderByDesc('starts_on')
            ->first();

        if ($sprint !== null) {
            return $sprint;
        }

        $this->rollCompany($companyId, $cadence, Carbon::now());

        return Sprint::forCompany($companyId)->cadence($cadence)->active()
            ->orderByDesc('starts_on')
            ->first();
    }

    /** @return array{name: string, starts_on: Carbon, ends_on: Carbon} */
    private function currentWindow(string $cadence, Carbon $now): array
    {
        $starts = $cadence === Sprint::WEEKLY ? $now->copy()->startOfWeek() : $now->copy()->startOfMonth();
        $ends   = $cadence === Sprint::WEEKLY ? $now->copy()->endOfWeek() : $now->copy()->endOfMonth();

        return [
            'name'      => Sprint::nameFor($cadence, $starts),
            'starts_on' => $starts->startOfDay(),
            'ends_on'   => $ends->startOfDay(),
        ];
    }
}
