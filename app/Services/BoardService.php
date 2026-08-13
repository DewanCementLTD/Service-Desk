<?php

namespace App\Services;

use App\Models\DevelopmentRequest;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The board behind the drag-and-drop.
 *
 * Three rules hold this together:
 *
 *  1. A move never writes a ticket's (or task's) status or assignee directly.
 *     It calls the same TicketService/TaskService methods the detail pages
 *     call, so a card dragged across a column produces exactly the history,
 *     audit entry and notifications that page would have produced.
 *
 *  2. Position is a sparse fraction, not an index. Dropping between two cards
 *     stores the midpoint of their ranks, so one row is written per drag. When
 *     neighbouring ranks converge past RANK_EPSILON the column is renumbered in
 *     steps of RANK_STEP.
 *
 *  3. Tasks ride in the same status columns as tickets rather than a lane of
 *     their own (unlike development requests), so a task's five statuses have
 *     to line up with the six ticket statuses that define the columns. Only
 *     three do — Pending/In Progress/Completed map onto Open/In Progress/
 *     Closed — so that is what TASK_COLUMN_MAP covers. On Hold and Cancelled
 *     tasks have no column to sit in and are left off the board entirely; they
 *     stay visible on the Tasks screen.
 */
class BoardService
{
    public const KIND_TICKET = 'TICKET';
    public const KIND_DEV    = 'DEV';
    public const KIND_TASK   = 'TASK';

    private const RANK_STEP    = 1000.0;
    private const RANK_EPSILON = 0.0001;

    /** Task status code => the ticket status code whose column it rides in. */
    private const TASK_COLUMN_MAP = [
        TaskStatus::PENDING     => TicketStatus::OPEN,
        TaskStatus::IN_PROGRESS => TicketStatus::IN_PROGRESS,
        TaskStatus::COMPLETED   => TicketStatus::CLOSED,
    ];

    public function __construct(
        private TicketService $tickets,
        private TaskService $tasks,
        private AuditLogger $audit,
    ) {
    }

    /**
     * Apply a drag. Returns the moved record, refreshed.
     *
     * @param  array{kind: string, id: int, status_id?: int|null, sprint_id?: int|null,
     *               assigned_to?: int|null, before_id?: int|null, after_id?: int|null}  $move
     */
    public function move(array $move, User $actor): Model
    {
        $item = $this->find($move['kind'], (int) $move['id']);

        return DB::connection('oracle')->transaction(function () use ($item, $move, $actor) {
            // Sprint first: a card dragged from the backlog into the week is
            // committed to it before anything else is decided about it. Tasks
            // don't take part in sprints at all, so this is skipped for them.
            if (! $item instanceof Task && array_key_exists('sprint_id', $move)) {
                $this->applySprint($item, $move['sprint_id'] === null ? null : (int) $move['sprint_id'], $actor);
            }

            if ($item instanceof Ticket) {
                if (! empty($move['status_id']) && (int) $move['status_id'] !== (int) $item->status_id) {
                    $this->tickets->changeStatus($item, (int) $move['status_id'], $actor, 'Moved on the board');
                    $item->refresh();
                }

                if (array_key_exists('assigned_to', $move)
                    && (int) $move['assigned_to'] !== (int) $item->assigned_to
                    && ! empty($move['assigned_to'])) {
                    $this->tickets->assign($item, (int) $move['assigned_to'], $actor, 'Assigned on the board');
                    $item->refresh();
                }
            }

            if ($item instanceof Task && ! empty($move['status_id']) && (int) $move['status_id'] !== (int) $item->task_status_id) {
                // The controller has already translated the column's ticket
                // status id into a task status id via taskStatusIdForColumn() —
                // by the time it gets here, status_id means task_statuses.id.
                $this->tasks->changeStatus($item, (int) $move['status_id'], $actor, 'Moved on the board');
                $item->refresh();
            }

            $item->update([
                'board_rank' => $this->rankBetween(
                    $item,
                    isset($move['before_id']) ? (int) $move['before_id'] : null,
                    isset($move['after_id']) ? (int) $move['after_id'] : null,
                ),
                'updated_by' => $actor->id,
            ]);

            return $item->refresh();
        });
    }

    /** Move a set of backlog items into a sprint in one go. */
    public function commitToSprint(string $kind, array $ids, ?Sprint $sprint, User $actor): int
    {
        if ($ids === []) {
            return 0;
        }

        return DB::connection('oracle')->transaction(function () use ($kind, $ids, $sprint, $actor) {
            $moved = 0;
            $rank  = $this->nextRankFor($kind, fn ($q) => $sprint === null ? $q->whereNull('sprint_id') : $q->where('sprint_id', $sprint->id));

            foreach ($this->query($kind)->whereIn('id', $ids)->get() as $item) {
                $this->applySprint($item, $sprint?->id, $actor);

                $item->update(['board_rank' => $rank, 'updated_by' => $actor->id]);
                $rank += self::RANK_STEP;
                $moved++;
            }

            return $moved;
        });
    }

    /**
     * Cards for one sprint (or the backlog when null), grouped by the column they
     * belong in. Development requests ride in a column of their own; tasks ride
     * in with the tickets (see TASK_COLUMN_MAP) since they skip sprints entirely
     * and are not filtered by $sprintId at all.
     *
     * @return array<int|string, \Illuminate\Support\Collection>
     */
    public function columns(?int $companyId, ?int $sprintId, User $user, ?int $assignedTo = null): array
    {
        $statuses = TicketStatus::active()->ordered()->get();

        $tickets = Ticket::with(['status', 'priority', 'assignee', 'requester', 'category'])
            ->visibleTo($user)
            ->inScope($companyId)
            ->when($sprintId === null, fn ($q) => $q->whereNull('sprint_id'), fn ($q) => $q->where('sprint_id', $sprintId))
            ->when($assignedTo, fn ($q) => $q->where('assigned_to', $assignedTo))
            ->orderByRaw('CASE WHEN BOARD_RANK IS NULL THEN 1 ELSE 0 END')
            ->orderBy('board_rank')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('status_id');

        $tasks = Task::with(['status', 'priority', 'assignee', 'department'])
            ->visibleTo($user)
            ->inScope($companyId)
            ->whereHas('status', fn ($q) => $q->whereIn('code', array_keys(self::TASK_COLUMN_MAP)))
            // Mirrors TaskController::index() — without assignment rights the
            // board should not surface tasks that screen would hide either.
            ->when(! $user->hasPermission('tasks.assign'), fn ($q) => $q->where(
                fn ($w) => $w->where('assigned_to', $user->id)->orWhere('assigned_by', $user->id)
            ))
            ->when($assignedTo, fn ($q) => $q->where('assigned_to', $assignedTo))
            ->orderByRaw('CASE WHEN BOARD_RANK IS NULL THEN 1 ELSE 0 END')
            ->orderBy('board_rank')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy(fn (Task $task) => self::TASK_COLUMN_MAP[$task->status->code] ?? null);

        $columns = [];

        foreach ($statuses as $status) {
            $columns[$status->id] = $tickets->get($status->id, collect())
                ->concat($tasks->get($status->code, collect()));
        }

        return $columns;
    }

    /** Development requests placed on the same board, in their own lane. */
    public function developmentLane(?int $companyId, ?int $sprintId, User $user, ?int $assignedTo = null)
    {
        return DevelopmentRequest::with(['status', 'priority', 'assignee', 'requester'])
            ->visibleTo($user)
            ->inScope($companyId)
            ->when($sprintId === null, fn ($q) => $q->whereNull('sprint_id'), fn ($q) => $q->where('sprint_id', $sprintId))
            ->when($assignedTo, fn ($q) => $q->where('assigned_to', $assignedTo))
            ->orderByRaw('CASE WHEN BOARD_RANK IS NULL THEN 1 ELSE 0 END')
            ->orderBy('board_rank')
            ->orderByDesc('created_at')
            ->get();
    }

    // ------------------------------------------------------------------ helpers

    public function find(string $kind, int $id): Model
    {
        return $this->query($kind)->findOrFail($id);
    }

    private function query(string $kind)
    {
        return match (strtoupper($kind)) {
            self::KIND_TICKET => Ticket::query(),
            self::KIND_DEV    => DevelopmentRequest::query(),
            self::KIND_TASK   => Task::query(),
            default           => throw new \InvalidArgumentException("Unknown board item kind [{$kind}]."),
        };
    }

    /**
     * Translate a column's ticket status id into the task status a task
     * dropped into that column should take, or null when the column has no
     * task-status equivalent (Pending/Resolved/Cancelled ticket columns —
     * see TASK_COLUMN_MAP). The controller rejects the move when this is null.
     */
    public function taskStatusIdForColumn(int $ticketStatusId): ?int
    {
        $code     = TicketStatus::find($ticketStatusId)?->code;
        $taskCode = $code ? array_search($code, self::TASK_COLUMN_MAP, true) : false;

        return $taskCode ? TaskStatus::idByCode($taskCode) : null;
    }

    /** Record the sprint change, with an audit entry so the board is not a black box. */
    private function applySprint(Model $item, ?int $sprintId, User $actor): void
    {
        if ((int) $item->sprint_id === (int) $sprintId) {
            return;
        }

        $from = $item->sprint?->name ?? 'Backlog';
        $to   = $sprintId === null ? 'Backlog' : Sprint::find($sprintId)?->name;

        $item->update(['sprint_id' => $sprintId, 'updated_by' => $actor->id]);

        $entity = $item instanceof Ticket ? 'TICKET' : 'DEVELOPMENT_REQUEST';
        $number = $item instanceof Ticket ? $item->ticket_no : $item->request_no;

        if ($item instanceof Ticket) {
            $this->tickets->recordHistory($item, 'SPRINT_CHANGED', 'sprint_id', $from, $to,
                "Moved from {$from} to {$to}");
        }

        $this->audit->log('SPRINT_CHANGE', $entity, $item->id,
            "{$number} moved from {$from} to {$to}", ['sprint' => $from], ['sprint' => $to]);
    }

    /**
     * A rank that sorts between the two neighbouring cards. Falls back to the end
     * of the column when the card was dropped last, and renumbers the column when
     * the neighbours have converged too far to fit anything between them.
     */
    private function rankBetween(Model $item, ?int $beforeId, ?int $afterId): float
    {
        $kind       = $this->kindOf($item);
        $beforeRank = $beforeId ? $this->rankOf($kind, $beforeId) : null;
        $afterRank  = $afterId ? $this->rankOf($kind, $afterId) : null;

        if ($beforeRank === null && $afterRank === null) {
            return $this->nextRank($kind, $item);
        }

        if ($beforeRank === null) {
            return $afterRank - self::RANK_STEP;
        }

        if ($afterRank === null) {
            return $beforeRank + self::RANK_STEP;
        }

        if (abs($afterRank - $beforeRank) < self::RANK_EPSILON) {
            $this->rebalance($kind, $item->sprint_id ?? null, $kind === self::KIND_TASK ? (int) $item->company_id : null);

            $beforeRank = $this->rankOf($kind, $beforeId);
            $afterRank  = $this->rankOf($kind, $afterId);
        }

        return ($beforeRank + $afterRank) / 2;
    }

    private function kindOf(Model $item): string
    {
        return match (true) {
            $item instanceof Ticket => self::KIND_TICKET,
            $item instanceof Task   => self::KIND_TASK,
            default                 => self::KIND_DEV,
        };
    }

    private function rankOf(string $kind, int $id): ?float
    {
        $value = $this->query($kind)->whereKey($id)->value('board_rank');

        return $value === null ? null : (float) $value;
    }

    /**
     * Tasks aren't scoped by sprint — there is no SPRINT_ID column on TASKS —
     * so their rank space is the whole company instead of one sprint.
     */
    private function nextRank(string $kind, Model $item): float
    {
        return $kind === self::KIND_TASK
            ? $this->nextRankFor($kind, fn ($q) => $q->where('company_id', $item->company_id))
            : $this->nextRankFor($kind, fn ($q) => $item->sprint_id === null ? $q->whereNull('sprint_id') : $q->where('sprint_id', $item->sprint_id));
    }

    private function nextRankFor(string $kind, \Closure $scope): float
    {
        $max = $scope($this->query($kind))->max('board_rank');

        return (float) $max + self::RANK_STEP;
    }

    /** Renumber a lane in even steps so there is room to drop between cards again. */
    public function rebalance(string $kind, ?int $sprintId, ?int $companyId = null): void
    {
        $items = $this->query($kind)
            ->when($kind === self::KIND_TASK,
                fn ($q) => $q->where('company_id', $companyId),
                fn ($q) => $q->when($sprintId === null, fn ($q2) => $q2->whereNull('sprint_id'), fn ($q2) => $q2->where('sprint_id', $sprintId)))
            ->orderByRaw('CASE WHEN BOARD_RANK IS NULL THEN 1 ELSE 0 END')
            ->orderBy('board_rank')
            ->orderBy('id')
            ->get();

        $rank = self::RANK_STEP;

        foreach ($items as $item) {
            $item->update(['board_rank' => $rank]);
            $rank += self::RANK_STEP;
        }
    }
}
