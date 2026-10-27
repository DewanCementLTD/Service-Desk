<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Team Reports Dashboard - Shows Team Lead progress metrics for their team.
 */
class TeamReportsController extends Controller
{
    /**
     * Display team reports dashboard with filtering.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        // Only Team Leads and Admins can view reports
        abort_unless($user->hasAnyPermission('team.manage') || $user->hasRole(Role::ADMIN), 403,
            'Only Team Leads can access team reports.');

        // Get filters from request. string() hands back a Stringable, which is
        // truthy even when empty — these have to be real strings or the status
        // filter matches on '' and every count comes back zero.
        $status = $request->filled('status') ? $request->string('status')->toString() : null;
        // Defaults to the whole history rather than the current week. The range
        // filters on ASSIGNED_AT, so a narrow default hid every task handed out
        // before this week — including work still open and being done right now.
        $period = $request->string('period', 'all')->toString(); // week, month, all
        $memberFilter = $request->integer('member');

        // Get team members (if admin, show all; if team lead, show own team).
        // A Team Lead carries their own tasks alongside the people they manage,
        // so they belong in the report as a member too: listing only IT_STAFF
        // dropped every task assigned to a lead from the figures entirely.
        $teamMembers = collect($user->hasRole(Role::ADMIN)
            ? \App\Models\User::active()
                ->whereHas('role', fn ($q) => $q->whereIn('code', [Role::IT_STAFF, Role::TEAM_LEAD]))
                ->orderBy('name')
                ->get()
            : $user->teamMembers()->prepend($user))
            ->unique(fn ($m) => (int) $m->id)
            ->values();

        // Filter to specific member if requested
        if ($memberFilter && $teamMembers->pluck('id')->contains($memberFilter)) {
            $teamMembers = $teamMembers->filter(fn ($m) => (int) $m->id === $memberFilter)->values();
        }

        // Build date range for period filter
        $dateRange = match ($period) {
            'week' => [now()->startOfWeek(), now()->endOfWeek()],
            'month' => [now()->startOfMonth(), now()->endOfMonth()],
            default => [null, null],
        };

        // Calculate metrics for each team member
        $metrics = $teamMembers->map(function ($member) use ($dateRange, $status) {
            // Tasks live in their own table, so there is no ticket type to filter
            // on here — everything TASKS holds is already a task. forAssignee()
            // counts the primary assignee and the TASK_ASSIGNEES co-assignees alike.
            $tasksQuery = Task::forAssignee((int) $member->id);

            $ticketsQuery = $member->assignedTickets()->whereHas('type', fn ($q) => $q->where('code', '!=', 'TASK'));

            // Apply date range if specified
            if ($dateRange[0]) {
                $tasksQuery->whereBetween('assigned_at', $dateRange);
                $ticketsQuery->whereBetween('assigned_at', $dateRange);
            }

            // Apply status filter if specified
            if ($status) {
                $tasksQuery->whereHas('status', fn ($q) => $q->where('code', $status));
                $ticketsQuery->whereHas('status', fn ($q) => $q->where('code', $status));
            }

            $totalTasks = $tasksQuery->count();
            $totalTickets = $ticketsQuery->count();

            // Get completed/closed counts
            $completedTasks = Task::forAssignee((int) $member->id)
                ->whereHas('status', fn ($q) => $q->where('is_completed_state', 1))
                ->when($dateRange[0], fn ($q) => $q->whereBetween('assigned_at', $dateRange))
                ->count();

            // Tickets close, tasks complete — the two status tables use different
            // flags for it (IS_CLOSED_STATE vs IS_COMPLETED_STATE).
            $resolvedTickets = $member->assignedTickets()
                ->whereHas('type', fn ($q) => $q->where('code', '!=', 'TASK'))
                ->whereHas('status', fn ($q) => $q->where('is_closed_state', 1))
                ->when($dateRange[0], fn ($q) => $q->whereBetween('assigned_at', $dateRange))
                ->count();

            // Get overdue count (tickets and tasks)
            $ticketsOverdue = $member->assignedTickets()
                ->where('is_overdue', 1)
                ->when($dateRange[0], fn ($q) => $q->whereBetween('assigned_at', $dateRange))
                ->count();

            $tasksOverdue = Task::forAssignee((int) $member->id)
                ->where('is_overdue', 1)
                ->when($dateRange[0], fn ($q) => $q->whereBetween('assigned_at', $dateRange))
                ->count();

            $overdueCount = $ticketsOverdue + $tasksOverdue;

            // How far along the work itself is, as reported on each task —
            // distinct from the completion rate above, which only counts tasks
            // that finished. Cancelled tasks are left out: TaskService stamps
            // them 100% because CANCELLED is a completed state, which would
            // read as work delivered.
            $avgProgress = Task::forAssignee((int) $member->id)
                ->whereHas('status', fn ($q) => $q->where('code', '!=', TaskStatus::CANCELLED))
                ->when($dateRange[0], fn ($q) => $q->whereBetween('assigned_at', $dateRange))
                ->avg('progress_percent');

            return [
                'member' => $member,
                'total_tasks' => $totalTasks,
                'completed_tasks' => $completedTasks,
                'total_tickets' => $totalTickets,
                'resolved_tickets' => $resolvedTickets,
                'avg_progress' => (int) round((float) $avgProgress),
                'overdue' => $overdueCount,
                'task_completion_rate' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0,
                'ticket_resolution_rate' => $totalTickets > 0 ? round(($resolvedTickets / $totalTickets) * 100) : 0,
                'pending_work' => ($totalTasks - $completedTasks) + ($totalTickets - $resolvedTickets),
            ];
        })->sortByDesc(fn ($m) => $m['pending_work'])->values();

        // Calculate team totals
        $totals = [
            'total_work' => $metrics->sum('pending_work'),
            'total_completed_tasks' => $metrics->sum('completed_tasks'),
            'total_tasks' => $metrics->sum('total_tasks'),
            'total_resolved_tickets' => $metrics->sum('resolved_tickets'),
            'total_tickets' => $metrics->sum('total_tickets'),
            'total_overdue' => $metrics->sum('overdue'),
            'avg_task_rate' => $metrics->count() > 0 ? round($metrics->avg('task_completion_rate')) : 0,
            'avg_ticket_rate' => $metrics->count() > 0 ? round($metrics->avg('ticket_resolution_rate')) : 0,
            // Averaged over members who actually hold tasks, so one idle member
            // does not drag the team figure to zero.
            'avg_progress' => $metrics->where('total_tasks', '>', 0)->count() > 0
                ? round($metrics->where('total_tasks', '>', 0)->avg('avg_progress'))
                : 0,
        ];

        return view('team-reports.index', [
            'metrics' => $metrics,
            'totals' => $totals,
            'teamMembers' => $teamMembers,
            'filters' => [
                'status' => $status,
                'period' => $period,
                'member' => $memberFilter,
            ],
            'isAdmin' => $user->hasRole(Role::ADMIN),
            'availableStatuses' => $this->getAvailableStatuses(),
        ]);
    }

    /**
     * Statuses offered in the filter. The two sides of the report use separate
     * status tables, so both go in the list — offering only the ticket ones
     * silently zeroed every task count whenever a filter was applied.
     */
    private function getAvailableStatuses(): array
    {
        return \App\Models\TicketStatus::active()->orderBy('sort_order')->pluck('name', 'code')
            ->union(\App\Models\TaskStatus::active()->orderBy('sort_order')->pluck('name', 'code'))
            ->toArray();
    }
}
