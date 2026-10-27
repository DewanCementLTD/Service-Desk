<?php

namespace App\Http\Controllers;

use App\Models\DevelopmentRequest;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Services\AgentDirectory;
use App\Services\BoardService;
use App\Services\ScopeContext;
use App\Services\SprintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The IT team's board: a backlog, an iteration, and columns you can drag between.
 *
 * The controller only decides what may be moved and by whom — every state change
 * goes through BoardService, which routes it back into the existing ticket
 * lifecycle so nothing on the board bypasses history, audit or notifications.
 */
class BoardController extends Controller
{
    public function __construct(
        private BoardService $board,
        private SprintService $sprints,
        private ScopeContext $scope,
        private AgentDirectory $agents,
    ) {
    }

    public function index(Request $request): View
    {
        $user      = $request->user();
        $companyId = $this->scope->companyId();
        $cadence   = $request->string('cadence', Sprint::WEEKLY)->upper()->toString();
        $cadence   = in_array($cadence, [Sprint::WEEKLY, Sprint::MONTHLY], true) ? $cadence : Sprint::WEEKLY;

        $sprint = $request->filled('sprint_id')
            ? Sprint::forCompany($companyId)->find($request->integer('sprint_id'))
            : ($companyId ? $this->sprints->current($companyId, $cadence) : null);

        // Filter by assigned person - default to showing all tasks
        $assignedTo = null;
        if ($request->filled('assigned_to')) {
            $assignedTo = (int) $request->integer('assigned_to');
        }

        // Keep backward compatibility with "mine" parameter
        if ($request->boolean('mine')) {
            $assignedTo = $user->id;
        }

        $priorityId  = $request->filled('priority_id') ? $request->integer('priority_id') : null;
        $overdueOnly = $request->boolean('overdue');

        // Unrecognised values fall back to the default rather than 404ing —
        // these arrive from a hand-editable query string.
        $sort = strtoupper($request->string('sort', BoardService::SORT_MANUAL)->toString());
        $sort = isset(BoardService::SORTS[$sort]) ? $sort : BoardService::SORT_MANUAL;

        $kinds = array_values(array_intersect(
            array_map('strtoupper', (array) $request->input('kinds', [])),
            array_keys(BoardService::KINDS),
        ));

        $filters = [
            'assigned_to' => $assignedTo,
            'priority_id' => $priorityId,
            'overdue'     => $overdueOnly,
            'kinds'       => $kinds,
            'sort'        => $sort,
        ];

        return view('board.index', [
            'sprint'      => $sprint,
            'cadence'     => $cadence,
            'assignedTo'  => $assignedTo,
            'priorityId'  => $priorityId,
            'overdueOnly' => $overdueOnly,
            'sort'        => $sort,
            'kinds'       => $kinds,
            'priorities'  => TicketPriority::active()->ordered()->get(),
            'sprints'     => Sprint::forCompany($companyId)->open()->orderByDesc('starts_on')->limit(12)->get(),
            // Not every status is a column on every board — a tasks-only board
            // drops Pending and Resolved, which no task can ever sit in.
            'statuses'    => $this->board->columnStatuses($kinds),
            'columns'     => $this->board->columns($companyId, $sprint?->id, $user, $filters),
            'agents'      => $this->agents->forCompany($companyId),
            'backlogSize' => $this->backlogCount($user, $companyId),
            'canManage'   => $user->hasPermission('board.manage'),
            'canSeePipeline' => $user->hasAnyPermission('development.approve', 'development.assign', 'development.update'),
        ]);
    }

    public function backlog(Request $request): View
    {
        $user      = $request->user();
        $companyId = $this->scope->companyId();

        return view('board.backlog', [
            'tickets'     => Ticket::with(['status', 'priority', 'requester', 'assignee', 'category'])
                ->visibleTo($user)
                ->inScope($companyId)
                ->inBacklog()
                ->orderByRaw('CASE WHEN BOARD_RANK IS NULL THEN 1 ELSE 0 END')
                ->orderBy('board_rank')
                ->orderByDesc('created_at')
                ->paginate(50)
                ->withQueryString(),
            'development' => DevelopmentRequest::with(['status', 'priority', 'requester', 'assignee'])
                ->visibleTo($user)
                ->inScope($companyId)
                ->inBacklog()
                ->orderByDesc('created_at')
                ->limit(50)
                ->get(),
            'sprints'     => Sprint::forCompany($companyId)->open()->orderBy('starts_on')->get(),
            'canManage'   => $user->hasPermission('board.manage'),
        ]);
    }

    /**
     * One drag. Answers JSON so the board can revert the card in place when the
     * server refuses the move.
     */
    public function move(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kind'        => ['required', Rule::in([BoardService::KIND_TICKET, BoardService::KIND_DEV, BoardService::KIND_TASK])],
            'id'          => ['required', 'integer'],
            // The board always sends the column's ticket status id, whatever kind
            // of card was dropped — see below for the task_statuses translation.
            'status_id'   => ['nullable', 'integer', Rule::exists('oracle.ticket_statuses', 'id')],
            'sprint_id'   => ['nullable', 'integer'],
            'assigned_to' => ['nullable', 'integer', Rule::in($this->agents->idsForCompany($this->scope->companyId()))],
            'before_id'   => ['nullable', 'integer'],
            'after_id'    => ['nullable', 'integer'],
        ]);

        $item = $this->board->find($data['kind'], (int) $data['id']);

        $this->authoriseItem($request, $item);

        if (! empty($data['sprint_id'])) {
            $sprint = Sprint::find($data['sprint_id']);

            abort_if($sprint === null || (int) $sprint->company_id !== (int) $item->company_id, 422,
                'That iteration belongs to another company.');
            abort_if($sprint->isClosed(), 422, 'That iteration is closed.');
        }

        // Tasks don't have their own board columns — they ride in four of the
        // ticket columns (see BoardService::TASK_COLUMN_MAP). Dropped into
        // Pending or Resolved, there is no task status to move it to; a
        // tasks-only board hides those two, but a mixed board still shows them.
        if ($item instanceof Task && ! empty($data['status_id'])) {
            $taskStatusId = $this->board->taskStatusIdForColumn((int) $data['status_id']);

            abort_if($taskStatusId === null, 422, 'Tasks cannot be moved to that column.');

            $data['status_id'] = $taskStatusId;
        }

        // Same translation for a development request — every column has an
        // equivalent development status (see BoardService::DEV_CANONICAL_STATUS_FOR_COLUMN).
        if ($item instanceof DevelopmentRequest && ! empty($data['status_id'])) {
            $data['status_id'] = $this->board->devStatusIdForColumn((int) $data['status_id']);
        }

        $moved = $this->board->move($data, $request->user());

        return response()->json([
            'ok'         => true,
            'id'         => $moved->id,
            'sprint_id'  => $moved instanceof Task ? null : $moved->sprint_id,
            'board_rank' => $moved->board_rank,
            'status_id'  => match (true) {
                $moved instanceof Ticket             => $moved->status_id,
                $moved instanceof Task               => $moved->task_status_id,
                $moved instanceof DevelopmentRequest => $moved->development_status_id,
                default                               => null,
            },
        ]);
    }

    /** Send a selection from the backlog into an iteration. */
    public function commit(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('board.manage'), 403);

        $data = $request->validate([
            'kind'      => ['required', Rule::in([BoardService::KIND_TICKET, BoardService::KIND_DEV])],
            'ids'       => ['required', 'array', 'max:100'],
            'ids.*'     => ['integer'],
            'sprint_id' => ['nullable', 'integer'],
        ]);

        $sprint = null;

        if (! empty($data['sprint_id'])) {
            $sprint = Sprint::forCompany($this->scope->companyId())->findOrFail($data['sprint_id']);
            abort_if($sprint->isClosed(), 422, 'That iteration is closed.');
        }

        // Only items the user may actually reach are committed; anything else in
        // the posted list is silently dropped rather than failing the whole batch.
        $allowed = $this->board->find($data['kind'], (int) $data['ids'][0]) instanceof Ticket
            ? Ticket::visibleTo($request->user())->inScope($this->scope->companyId())
                ->whereIn('id', $data['ids'])->pluck('id')->all()
            : DevelopmentRequest::visibleTo($request->user())->inScope($this->scope->companyId())
                ->whereIn('id', $data['ids'])->pluck('id')->all();

        $moved = $this->board->commitToSprint($data['kind'], $allowed, $sprint, $request->user());

        return back()->with('success', $sprint === null
            ? "{$moved} item(s) returned to the backlog."
            : "{$moved} item(s) added to {$sprint->name}.");
    }

    // ------------------------------------------------------------------ sprints

    public function storeSprint(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('sprints.manage'), 403);

        $companyId = $this->scope->companyId();
        abort_if($companyId === null, 400, 'Select a company first.');

        $data = $request->validate([
            'name'      => ['required', 'string', 'max:100',
                Rule::unique('oracle.sprints', 'name')->where(fn ($q) => $q->where('company_id', $companyId))],
            'cadence'   => ['required', Rule::in([Sprint::WEEKLY, Sprint::MONTHLY])],
            'starts_on' => ['required', 'date'],
            'ends_on'   => ['required', 'date', 'after_or_equal:starts_on'],
            'goal'      => ['nullable', 'string', 'max:500'],
        ]);

        Sprint::create($data + [
            'company_id' => $companyId,
            'state'      => Sprint::PLANNED,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', "Iteration {$data['name']} created.");
    }

    public function startSprint(Request $request, Sprint $sprint): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('sprints.manage'), 403);
        $this->authoriseCompany($request, (int) $sprint->company_id);
        abort_if($sprint->isClosed(), 422, 'That iteration is already closed.');

        $sprint->update(['state' => Sprint::ACTIVE, 'updated_by' => $request->user()->id]);

        return back()->with('success', "{$sprint->name} is now active.");
    }

    public function closeSprint(Request $request, Sprint $sprint): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('sprints.manage'), 403);
        $this->authoriseCompany($request, (int) $sprint->company_id);
        abort_if($sprint->isClosed(), 422, 'That iteration is already closed.');

        $returned = $this->sprints->close($sprint);

        return back()->with('success',
            "{$sprint->name} closed. {$returned} unfinished item(s) went back to the backlog.");
    }

    // ------------------------------------------------------------------ helpers

    private function authoriseItem(Request $request, $item): void
    {
        $this->authoriseCompany($request, (int) $item->company_id);

        // Moving work is a management action; reading the board is not.
        abort_unless($request->user()->hasPermission('board.manage'), 403,
            'You do not have permission to move work on the board.');

        // A dragged development request changes its pipeline status the same
        // way editing it on its own page would (see BoardService::moveDevelopmentStatus()),
        // so it needs the same permission that page requires.
        if ($item instanceof DevelopmentRequest) {
            abort_unless($request->user()->hasPermission('development.update'), 403,
                'You do not have permission to move development requests on the board.');
        }
    }

    private function authoriseCompany(Request $request, int $companyId): void
    {
        abort_unless($request->user()->canAccessCompany($companyId), 403,
            'That belongs to a company you do not have access to.');
    }

    private function backlogCount($user, ?int $companyId): int
    {
        return Ticket::visibleTo($user)->inScope($companyId)->inBacklog()->count()
            + DevelopmentRequest::visibleTo($user)->inScope($companyId)->inBacklog()->count();
    }
}
