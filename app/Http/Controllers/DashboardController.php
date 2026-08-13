<?php

namespace App\Http\Controllers;

use App\Models\DevelopmentRequest;
use App\Models\Role;
use App\Models\Task;
use App\Models\Ticket;
use App\Services\DashboardService;
use App\Services\ScopeContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboard,
        private ScopeContext $scope,
    ) {
    }

    /** Send each role to the dashboard that matches their job. */
    public function index(): View|RedirectResponse
    {
        $user = auth()->user();

        return match (true) {
            $user->hasRole(Role::DGM, Role::ADMIN, Role::IT_MANAGER) => redirect()->route('dashboard.dgm'),
            $user->hasRole(Role::MANAGEMENT)                         => redirect()->route('dashboard.management'),
            $user->hasRole(Role::DEVELOPER)                          => redirect()->route('dashboard.development'),
            default                                                  => $this->personal(),
        };
    }

    /** Service desk overview: the DGM / IT management view. */
    public function dgm(): View
    {
        $user = auth()->user();

        return view('dashboard.dgm', [
            'stats'            => $this->dashboard->ticketSummary(),
            'byStatus'         => $this->dashboard->ticketsByStatus(),
            'byPriority'       => $this->dashboard->ticketsByPriority(),
            'trend'            => $this->dashboard->ticketTrend(14),
            'departments'      => $this->dashboard->departmentBreakdown(),
            'team'             => $this->dashboard->teamPerformance(),
            'companyBreakdown' => $this->dashboard->companyBreakdown($user),
            'recentTickets'    => Ticket::with(['status', 'priority', 'requester', 'assignee'])
                ->visibleTo($user)
                ->inScope($this->scope->companyId(), $this->scope->locationId())
                ->orderByDesc('created_at')
                ->limit(10)
                ->get(),
            'slaBreaches'      => Ticket::with(['status', 'priority', 'assignee'])
                ->visibleTo($user)
                ->inScope($this->scope->companyId(), $this->scope->locationId())
                ->where('sla_status', Ticket::SLA_BREACHED)
                ->orderByDesc('due_at')
                ->limit(8)
                ->get(),
            // Management and IT leadership track the task register from here too,
            // not just the ticket queue.
            'taskStats'        => $this->dashboard->taskSummary(),
            'recentTasks'      => Task::with(['status', 'priority', 'assignee'])
                ->visibleTo($user)
                ->inScope($this->scope->companyId(), $this->scope->locationId())
                ->orderByDesc('created_at')
                ->limit(8)
                ->get(),
        ]);
    }

    public function management(): View
    {
        $user = auth()->user();

        return view('dashboard.management', [
            'taskStats'       => $this->dashboard->taskSummary(),
            'ticketStats'     => $this->dashboard->ticketSummary(),
            'devStats'        => $this->dashboard->developmentSummary(),
            'companyBreakdown' => $this->dashboard->companyBreakdown($user),
            'recentTasks'     => Task::with(['status', 'priority', 'assignee'])
                ->visibleTo($user)
                ->inScope($this->scope->companyId(), $this->scope->locationId())
                ->orderByDesc('created_at')
                ->limit(10)
                ->get(),
            'overdueTasks'    => Task::with(['status', 'assignee'])
                ->visibleTo($user)
                ->inScope($this->scope->companyId(), $this->scope->locationId())
                ->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->whereHas('status', fn ($q) => $q->where('is_completed_state', 0))
                ->orderBy('due_at')
                ->limit(8)
                ->get(),
        ]);
    }

    public function development(): View
    {
        $user = auth()->user();

        return view('dashboard.development', [
            'stats'    => $this->dashboard->developmentSummary(),
            'pipeline' => DevelopmentRequest::with(['status', 'requester', 'assignee', 'priority'])
                ->visibleTo($user)
                ->inScope($this->scope->companyId(), $this->scope->locationId())
                ->orderByDesc('created_at')
                ->limit(15)
                ->get(),
        ]);
    }

    /** Personal dashboard for employees, department heads and IT staff. */
    public function personal(): View
    {
        $user = auth()->user();

        // A plain employee never works a ticket or a task — they only ever raise
        // requests and follow them — so their dashboard answers one question:
        // what did I ask for, and where has it got to. Everyone else who lands
        // here (department heads, IT staff) still has a queue of their own and
        // keeps the fuller view below.
        if ($user->hasRole(Role::EMPLOYEE)) {
            return $this->employeePersonal($user);
        }

        return view('dashboard.personal', [
            'myTickets'   => Ticket::with(['status', 'priority', 'assignee'])
                ->where('requester_id', $user->id)
                ->orderByDesc('created_at')
                ->limit(10)
                ->get(),
            'assigned'    => Ticket::with(['status', 'priority', 'requester'])
                ->where('assigned_to', $user->id)
                ->whereHas('status', fn ($q) => $q->where('is_closed_state', 0))
                ->orderBy('due_at')
                ->limit(10)
                ->get(),
            'myTasks'     => Task::with(['status', 'priority'])
                ->where('assigned_to', $user->id)
                ->whereHas('status', fn ($q) => $q->where('is_completed_state', 0))
                ->orderBy('due_at')
                ->limit(10)
                ->get(),
            'counts'      => [
                'open_requests' => Ticket::where('requester_id', $user->id)
                    ->whereHas('status', fn ($q) => $q->where('is_closed_state', 0))->count(),
                'assigned_open' => Ticket::where('assigned_to', $user->id)
                    ->whereHas('status', fn ($q) => $q->where('is_closed_state', 0))->count(),
                'my_tasks'      => Task::where('assigned_to', $user->id)
                    ->whereHas('status', fn ($q) => $q->where('is_completed_state', 0))->count(),
                'overdue'       => Ticket::where('assigned_to', $user->id)
                    ->whereNotNull('due_at')->where('due_at', '<', now())
                    ->whereHas('status', fn ($q) => $q->where('is_closed_state', 0))->count(),
            ],
        ]);
    }

    /** Everything an employee raised, across both registers, with a status split. */
    private function employeePersonal($user): View
    {
        // A plain DB aggregate rather than an Eloquent selectRaw()->first(): the
        // latter hydrates a Ticket/DevelopmentRequest model from the row, and
        // casting that model to an array does not recover the aggregate columns
        // — it mangles the object's protected properties instead.
        $ticketRow = DB::connection('oracle')->selectOne(
            "select
                 COUNT(*)                                                       as total,
                 SUM(CASE WHEN s.CODE = 'OPEN'        THEN 1 ELSE 0 END)        as open_count,
                 SUM(CASE WHEN s.CODE = 'IN_PROGRESS' THEN 1 ELSE 0 END)        as in_progress,
                 SUM(CASE WHEN s.CODE = 'PENDING'     THEN 1 ELSE 0 END)        as pending,
                 SUM(CASE WHEN s.IS_CLOSED_STATE = 1  THEN 1 ELSE 0 END)        as closed
             from TICKETS t
             join TICKET_STATUSES s on s.ID = t.STATUS_ID
             where t.REQUESTER_ID = :requester_id",
            ['requester_id' => $user->id]
        );

        $devRow = DB::connection('oracle')->selectOne(
            "select
                 COUNT(*)                                                       as total,
                 SUM(CASE WHEN APPROVAL_STATUS = 'PENDING'  THEN 1 ELSE 0 END)  as awaiting_decision,
                 SUM(CASE WHEN APPROVAL_STATUS = 'REJECTED' THEN 1 ELSE 0 END)  as rejected
             from DEVELOPMENT_REQUESTS
             where REQUESTER_ID = :requester_id",
            ['requester_id' => $user->id]
        );

        $ticketCounts = $this->normaliseCounts($ticketRow);
        $devCounts    = $this->normaliseCounts($devRow);

        $devCounts['completed'] = DevelopmentRequest::where('requester_id', $user->id)
            ->whereHas('status', fn ($q) => $q->where('is_completed_state', 1))
            ->count();

        return view('dashboard.employee', [
            'ticketCounts' => $ticketCounts,
            'devCounts'    => $devCounts,
            'myTickets'    => Ticket::with(['status', 'priority'])
                ->where('requester_id', $user->id)
                ->orderByDesc('created_at')
                ->limit(8)
                ->get(),
            'myDevelopmentRequests' => DevelopmentRequest::with(['status', 'priority'])
                ->where('requester_id', $user->id)
                ->orderByDesc('created_at')
                ->limit(8)
                ->get(),
        ]);
    }

    /** @return array<string, int> */
    private function normaliseCounts(?object $row): array
    {
        $out = [];

        foreach ((array) ($row ?? []) as $key => $value) {
            $out[strtolower($key)] = (int) $value;
        }

        return $out;
    }
}
