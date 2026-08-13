<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use App\Services\ScopeContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reporting.
 *
 * All aggregation happens in Oracle and results are paginated or streamed, so a
 * large date range never pulls whole tables into PHP memory.
 */
class ReportController extends Controller
{
    public function __construct(
        private DashboardService $dashboard,
        private ScopeContext $scope,
    ) {
    }

    public function index(): View
    {
        return view('reports.index');
    }

    /** Ticket summary report with a date range and grouping. */
    public function tickets(Request $request): View
    {
        [$from, $to] = $this->dateRange($request);

        $rows = DB::connection('oracle')->select(
            "select TO_CHAR(t.CREATED_AT, 'YYYY-MM') as period,
                    COUNT(*)                                                    as total,
                    SUM(CASE WHEN s.IS_CLOSED_STATE = 1 THEN 1 ELSE 0 END)      as closed,
                    SUM(CASE WHEN s.IS_CLOSED_STATE = 0 THEN 1 ELSE 0 END)      as active,
                    SUM(CASE WHEN t.SLA_STATUS = 'BREACHED' THEN 1 ELSE 0 END)  as sla_breached,
                    SUM(CASE WHEN p.IS_CRITICAL = 1 THEN 1 ELSE 0 END)          as critical,
                    ROUND(AVG(CASE WHEN t.RESOLVED_AT IS NOT NULL
                         THEN (CAST(t.RESOLVED_AT AS DATE) - CAST(t.CREATED_AT AS DATE)) * 24
                    END), 2)                                                    as avg_resolution_hours
               from TICKETS t
               join TICKET_STATUSES s   on s.ID = t.STATUS_ID
               join TICKET_PRIORITIES p on p.ID = t.PRIORITY_ID
              where t.CREATED_AT between :from_date and :to_date
                and (:company_id is null or t.COMPANY_ID = :company_id)
                and (:location_id is null or t.LOCATION_ID = :location_id)
              group by TO_CHAR(t.CREATED_AT, 'YYYY-MM')
              order by 1",
            $this->bindings($from, $to)
        );

        return view('reports.tickets', [
            'rows'        => $rows,
            'from'        => $from,
            'to'          => $to,
            'departments' => $this->dashboard->departmentBreakdown(),
            'team'        => $this->dashboard->teamPerformance(),
        ]);
    }

    /** SLA breach report, served straight from the reporting view. */
    public function sla(Request $request): View
    {
        $companyId  = $this->scope->companyId();
        $locationId = $this->scope->locationId();

        $breaches = DB::connection('oracle')->table('VW_SLA_BREACHES')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->orderByDesc('hours_overdue')
            ->paginate(25);

        return view('reports.sla', ['breaches' => $breaches]);
    }

    public function development(Request $request): View
    {
        [$from, $to] = $this->dateRange($request);

        $rows = DB::connection('oracle')->select(
            "select ds.NAME                                                     as status_name,
                    COUNT(*)                                                    as total,
                    SUM(CASE WHEN dr.APPROVAL_STATUS = 'APPROVED' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN dr.APPROVAL_STATUS = 'PENDING'  THEN 1 ELSE 0 END) as pending,
                    ROUND(AVG(dr.PROGRESS_PERCENT), 1)                          as avg_progress,
                    SUM(NVL(dr.ESTIMATED_HOURS,0))                              as estimated_hours,
                    SUM(NVL(dr.ACTUAL_HOURS,0))                                 as actual_hours
               from DEVELOPMENT_REQUESTS dr
               join DEVELOPMENT_STATUSES ds on ds.ID = dr.DEVELOPMENT_STATUS_ID
              where dr.CREATED_AT between :from_date and :to_date
                and (:company_id is null or dr.COMPANY_ID = :company_id)
                and (:location_id is null or dr.LOCATION_ID = :location_id)
              group by ds.NAME, ds.SORT_ORDER
              order by ds.SORT_ORDER",
            $this->bindings($from, $to)
        );

        return view('reports.development', ['rows' => $rows, 'from' => $from, 'to' => $to]);
    }

    /**
     * Stream the ticket register as CSV. Rows are fetched in chunks so the export
     * size is bounded by the chunk, not the result set.
     */
    public function exportTickets(Request $request): StreamedResponse
    {
        abort_unless($request->user()->hasPermission('reports.export'), 403);

        [$from, $to] = $this->dateRange($request);
        $bindings = $this->bindings($from, $to);

        $filename = 'tickets-' . $from->format('Ymd') . '-' . $to->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($bindings) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Ticket No', 'Company', 'Location', 'Subject', 'Type', 'Category',
                'Priority', 'Status', 'Requester', 'Department', 'Assignee',
                'Created', 'Due', 'Resolved', 'SLA Status',
            ]);

            DB::connection('oracle')->table('tickets as t')
                ->selectRaw("t.TICKET_NO, co.NAME as COMPANY_NAME, l.NAME as LOCATION_NAME,
                             t.SUBJECT, ty.NAME as TYPE_NAME, ca.NAME as CATEGORY_NAME,
                             p.NAME as PRIORITY_NAME, s.NAME as STATUS_NAME,
                             req.NAME as REQUESTER_NAME, d.NAME as DEPARTMENT_NAME,
                             asg.NAME as ASSIGNEE_NAME, t.CREATED_AT, t.DUE_AT,
                             t.RESOLVED_AT, t.SLA_STATUS")
                ->join('companies as co', 'co.ID', '=', 't.COMPANY_ID')
                ->join('ticket_statuses as s', 's.ID', '=', 't.STATUS_ID')
                ->join('ticket_priorities as p', 'p.ID', '=', 't.PRIORITY_ID')
                ->join('users as req', 'req.ID', '=', 't.REQUESTER_ID')
                ->leftJoin('locations as l', 'l.ID', '=', 't.LOCATION_ID')
                ->leftJoin('ticket_types as ty', 'ty.ID', '=', 't.TYPE_ID')
                ->leftJoin('ticket_categories as ca', 'ca.ID', '=', 't.CATEGORY_ID')
                ->leftJoin('departments as d', 'd.ID', '=', 't.DEPARTMENT_ID')
                ->leftJoin('users as asg', 'asg.ID', '=', 't.ASSIGNED_TO')
                ->whereBetween('t.CREATED_AT', [$bindings['from_date'], $bindings['to_date']])
                ->when($bindings['company_id'], fn ($q) => $q->where('t.COMPANY_ID', $bindings['company_id']))
                ->when($bindings['location_id'], fn ($q) => $q->where('t.LOCATION_ID', $bindings['location_id']))
                ->orderBy('t.ID')
                ->chunk(500, function ($rows) use ($handle) {
                    foreach ($rows as $row) {
                        fputcsv($handle, array_map(
                            fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d H:i') : $v,
                            array_values((array) $row)
                        ));
                    }
                });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** @return array{0: \Illuminate\Support\Carbon, 1: \Illuminate\Support\Carbon} */
    private function dateRange(Request $request): array
    {
        $from = $request->filled('from') ? $request->date('from')->startOfDay() : now()->subMonths(6)->startOfDay();
        $to   = $request->filled('to') ? $request->date('to')->endOfDay() : now()->endOfDay();

        return [$from, $to];
    }

    private function bindings($from, $to): array
    {
        return [
            'from_date'   => $from,
            'to_date'     => $to,
            'company_id'  => $this->scope->companyId(),
            'location_id' => $this->scope->locationId(),
        ];
    }
}
