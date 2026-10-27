<?php

namespace App\Services;

use App\Models\DevelopmentRequest;
use App\Models\DevelopmentStatus;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
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
 *  3. Tasks and development requests both ride in the same status columns as
 *     tickets rather than a lane of their own, so their own statuses have to
 *     line up with the six ticket statuses that define the columns.
 *
 *     Four task statuses do — Pending/In Progress/Completed/Cancelled map onto
 *     Open/In Progress/Closed/Cancelled — so that is what TASK_COLUMN_MAP
 *     covers. On Hold tasks have no column to sit in and are left off the
 *     board entirely; they stay visible on the Tasks screen.
 *
 *     That leaves the Pending and Resolved columns with no task equivalent.
 *     Showing them on a board filtered to tasks alone offers a drop target
 *     that can only ever be refused, so columnStatuses() drops them from a
 *     tasks-only board — see the Pending/Resolved note there.
 *
 *     Every development status maps somewhere for display (DEV_COLUMN_MAP),
 *     since a request is always in one of them, and several statuses can
 *     share a column (e.g. Requested and Analysis both read as "Open"). A
 *     card dropped into a column is more specific than that: it needs one
 *     definite target status, so DEV_CANONICAL_STATUS_FOR_COLUMN picks the
 *     one status each column's drop actually means (mirrors what
 *     DevelopmentRequestController::update() would set from that column's
 *     equivalent status). Dragging a request updates it exactly like editing
 *     it on its own page would — audit entry, assignee notification and email
 *     included — see moveDevelopmentRequest().
 */
class BoardService
{
    public const KIND_TICKET = 'TICKET';
    public const KIND_DEV    = 'DEV';
    public const KIND_TASK   = 'TASK';

    /**
     * Column orderings. MANUAL is the hand-dragged board_rank order and the
     * only one where dropping a card in a new position means anything — the
     * others are derived from the cards' own fields, so a drag under them
     * changes status but not position.
     */
    public const SORT_MANUAL   = 'MANUAL';
    public const SORT_PRIORITY = 'PRIORITY';
    public const SORT_DUE      = 'DUE';
    public const SORT_NEWEST   = 'NEWEST';
    public const SORT_OLDEST   = 'OLDEST';

    public const SORTS = [
        self::SORT_MANUAL   => 'Manual (drag order)',
        self::SORT_PRIORITY => 'Priority (highest first)',
        self::SORT_DUE      => 'Due date (soonest first)',
        self::SORT_NEWEST   => 'Newest first',
        self::SORT_OLDEST   => 'Oldest first',
    ];

    public const KINDS = [
        self::KIND_TICKET => 'Tickets',
        self::KIND_TASK   => 'Tasks',
        self::KIND_DEV    => 'Development',
    ];

    private const RANK_STEP    = 1000.0;
    private const RANK_EPSILON = 0.0001;

    /** Task status code => the ticket status code whose column it rides in. */
    private const TASK_COLUMN_MAP = [
        TaskStatus::PENDING     => TicketStatus::OPEN,
        TaskStatus::IN_PROGRESS => TicketStatus::IN_PROGRESS,
        TaskStatus::COMPLETED   => TicketStatus::CLOSED,
        TaskStatus::CANCELLED   => TicketStatus::CANCELLED,
    ];

    /** Development status code => the ticket status code whose column it rides in. */
    private const DEV_COLUMN_MAP = [
        DevelopmentStatus::REQUESTED  => TicketStatus::OPEN,
        DevelopmentStatus::ANALYSIS   => TicketStatus::OPEN,
        DevelopmentStatus::APPROVED   => TicketStatus::IN_PROGRESS,
        DevelopmentStatus::DEVELOPING => TicketStatus::IN_PROGRESS,
        DevelopmentStatus::TESTING    => TicketStatus::IN_PROGRESS,
        DevelopmentStatus::UAT        => TicketStatus::PENDING,
        DevelopmentStatus::ON_HOLD    => TicketStatus::PENDING,
        DevelopmentStatus::DEPLOYED   => TicketStatus::RESOLVED,
        DevelopmentStatus::REJECTED   => TicketStatus::CANCELLED,
    ];

    /** Ticket status code => the one development status a card dropped in that column takes. */
    private const DEV_CANONICAL_STATUS_FOR_COLUMN = [
        TicketStatus::OPEN        => DevelopmentStatus::REQUESTED,
        TicketStatus::IN_PROGRESS => DevelopmentStatus::DEVELOPING,
        TicketStatus::PENDING     => DevelopmentStatus::ON_HOLD,
        TicketStatus::RESOLVED    => DevelopmentStatus::DEPLOYED,
        TicketStatus::CLOSED      => DevelopmentStatus::DEPLOYED,
        TicketStatus::CANCELLED   => DevelopmentStatus::REJECTED,
    ];

    public function __construct(
        private TicketService $tickets,
        private TaskService $tasks,
        private AuditLogger $audit,
        private NotificationService $notifications,
        private DevelopmentRequestMailService $developmentMail,
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

            if ($item instanceof DevelopmentRequest && ! empty($move['status_id']) && (int) $move['status_id'] !== (int) $item->development_status_id) {
                // Same translation as tasks — the controller has already turned
                // the column's ticket status id into a development status id via
                // devStatusIdForColumn().
                $this->moveDevelopmentStatus($item, (int) $move['status_id'], $actor);
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
     * belong in. Tasks and development requests both ride in with the tickets
     * (see TASK_COLUMN_MAP / DEV_COLUMN_MAP) rather than a lane of their own.
     * Tasks skip sprints entirely and are not filtered by $sprintId at all;
     * development requests are, same as tickets.
     *
     * @return array<int|string, \Illuminate\Support\Collection>
     */
    public function columns(?int $companyId, ?int $sprintId, User $user, array $filters = []): array
    {
        $statuses = $this->columnStatuses($filters['kinds'] ?? []);

        $assignedTo  = $filters['assigned_to'] ?? null;
        $priorityId  = $filters['priority_id'] ?? null;
        $overdueOnly = ! empty($filters['overdue']);
        $kinds       = $filters['kinds'] ?? [];
        $sort        = $filters['sort'] ?? self::SORT_MANUAL;

        // An empty kinds list means "no type filter", not "show nothing".
        $wants = fn (string $kind) => $kinds === [] || in_array($kind, $kinds, true);

        // The three queries share the same filters; only the assignee clause
        // differs, because a task can be held by several people at once.
        $common = function ($query) use ($priorityId, $overdueOnly) {
            return $query
                ->when($priorityId, fn ($q) => $q->where('priority_id', $priorityId))
                ->when($overdueOnly, fn ($q) => $q->where('is_overdue', 1))
                ->orderByRaw('CASE WHEN BOARD_RANK IS NULL THEN 1 ELSE 0 END')
                ->orderBy('board_rank')
                ->orderByDesc('created_at');
        };

        $tickets = ! $wants(self::KIND_TICKET) ? collect() : $common(
            Ticket::with(['status', 'priority', 'assignee', 'requester', 'category'])
                ->visibleTo($user)
                ->inScope($companyId)
                ->when($sprintId === null, fn ($q) => $q->whereNull('sprint_id'), fn ($q) => $q->where('sprint_id', $sprintId))
                ->when($assignedTo, fn ($q) => $q->where('assigned_to', $assignedTo))
        )->get()->groupBy('status_id');

        $tasks = ! $wants(self::KIND_TASK) ? collect() : $common(
            Task::with(['status', 'priority', 'assignee', 'department', 'assignees.assignee'])
                ->visibleTo($user)
                ->inScope($companyId)
                ->whereHas('status', fn ($q) => $q->whereIn('code', array_keys(self::TASK_COLUMN_MAP)))
                // Mirrors TaskController::index() — without assignment rights the
                // board should not surface tasks that screen would hide either.
                ->when(! $user->hasPermission('tasks.assign'), fn ($q) => $q->where(
                    fn ($w) => $w->where('assigned_to', $user->id)
                        ->orWhere('assigned_by', $user->id)
                        ->orWhereHas('assignees', fn ($sq) => $sq->where('user_id', $user->id))
                ))
                // A task filtered to one person shows up for its co-assignees too.
                ->when($assignedTo, fn ($q) => $q->forAssignee((int) $assignedTo))
        )->get()->groupBy(fn (Task $task) => self::TASK_COLUMN_MAP[$task->status?->code] ?? null);

        $development = ! $wants(self::KIND_DEV) ? collect() : $common(
            DevelopmentRequest::with(['status', 'priority', 'assignee', 'requester'])
                ->visibleTo($user)
                ->inScope($companyId)
                ->when($sprintId === null, fn ($q) => $q->whereNull('sprint_id'), fn ($q) => $q->where('sprint_id', $sprintId))
                ->when($assignedTo, fn ($q) => $q->where('assigned_to', $assignedTo))
        )->get()->groupBy(fn (DevelopmentRequest $request) => self::DEV_COLUMN_MAP[$request->status->code] ?? null);

        $columns = [];

        foreach ($statuses as $status) {
            $cards = $tickets->get($status->id, collect())
                ->concat($tasks->get($status->code, collect()) ?? collect())
                ->concat($development->get($status->code, collect()) ?? collect());

            // Sorting happens after the concat, not in SQL. Each query can only
            // order its own kind, which would leave a mixed column reading
            // "all tickets, then all tasks, then all dev" whatever was asked for.
            $columns[$status->id] = $this->sortCards($cards, $sort);
        }

        return $columns;
    }

    /**
     * The columns to render for a given kind filter, in board order.
     *
     * Normally that is every active ticket status. A board filtered to tasks
     * alone is the exception: tasks have no Pending or Resolved status, so
     * those two columns would sit there empty and reject every card dropped
     * on them (BoardController::move() refuses the move when
     * taskStatusIdForColumn() comes back null). They are left out instead.
     *
     * Kept in step with columns(), which keys its result off this list — a
     * status missing here has no column array, and the view skips it.
     *
     * @param  array<int, string>  $kinds  Selected kind filter; empty means "all kinds".
     * @return \Illuminate\Support\Collection<int, TicketStatus>
     */
    public function columnStatuses(array $kinds = []): Collection
    {
        $statuses = TicketStatus::active()->ordered()->get();

        if ($kinds !== [self::KIND_TASK]) {
            return $statuses;
        }

        return $statuses
            ->filter(fn (TicketStatus $status) => in_array($status->code, self::TASK_COLUMN_MAP, true))
            ->values();
    }

    /**
     * Order one column's cards. MANUAL keeps the hand-dragged board_rank the
     * queries already applied; everything else re-sorts the mixed collection.
     */
    private function sortCards(Collection $cards, string $sort): Collection
    {
        return match ($sort) {
            // sort_order runs LOW=10 .. CRITICAL=40, so most urgent is highest.
            self::SORT_PRIORITY => $cards->sortByDesc(fn ($card) => $card->priority?->sort_order ?? -1)->values(),
            // Soonest deadline first, with undated cards parked at the end
            // rather than sorting as though they were due in 1970.
            self::SORT_DUE      => $cards->sortBy(fn ($card) => $card->due_at?->getTimestamp() ?? PHP_INT_MAX)->values(),
            self::SORT_NEWEST   => $cards->sortByDesc(fn ($card) => $card->created_at?->getTimestamp() ?? 0)->values(),
            self::SORT_OLDEST   => $cards->sortBy(fn ($card) => $card->created_at?->getTimestamp() ?? PHP_INT_MAX)->values(),
            default             => $cards->values(),
        };
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

    /**
     * Translate a column's ticket status id into the one development status a
     * request dropped into that column should take — see
     * DEV_CANONICAL_STATUS_FOR_COLUMN. Every ticket status column has an
     * equivalent, unlike tasks, so this never returns null for a real column.
     */
    public function devStatusIdForColumn(int $ticketStatusId): ?int
    {
        $code    = TicketStatus::find($ticketStatusId)?->code;
        $devCode = $code ? (self::DEV_CANONICAL_STATUS_FOR_COLUMN[$code] ?? null) : null;

        return $devCode ? DevelopmentStatus::idByCode($devCode) : null;
    }

    /**
     * Apply a status change dragged in from the board. Mirrors the pipeline
     * bits of DevelopmentRequestController::update() — completion stamping,
     * audit entry, assignee notification and email — so a drag produces the
     * same record of what happened as editing the request would.
     */
    private function moveDevelopmentStatus(DevelopmentRequest $request, int $statusId, User $actor): void
    {
        $original = $request->getOriginal();
        $fromName = $request->status?->name;
        $status   = DevelopmentStatus::find($statusId);

        $changes = ['development_status_id' => $statusId, 'updated_by' => $actor->id];

        if ($status?->is_completed_state && $request->completed_at === null) {
            $changes['completed_at']     = now();
            $changes['progress_percent'] = 100;
        }

        $request->update($changes);

        $this->audit->updated('DEVELOPMENT_REQUEST', $request->id, $original, $request->getChanges(),
            "Development request {$request->request_no} moved to {$status?->name} on the board");

        if ($request->assigned_to) {
            $this->notifications->notify(
                (int) $request->assigned_to,
                "Development request {$request->request_no} updated",
                $request->title,
                'DEVELOPMENT_REQUEST',
                $request->id,
                route('development.show', $request->id)
            );
        }

        $this->developmentMail->notifyStatusChanged($request, $actor, $fromName, $status?->name ?? '—', 'Moved on the board');
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
