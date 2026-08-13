<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard statistics.
 *
 * Every figure is aggregated by Oracle and returned as a single row; nothing is
 * counted in PHP and nothing is hard-coded. Each query is scoped to the active
 * company/location and, where relevant, to the companies the user may see.
 */
class DashboardService
{
    public function __construct(private ScopeContext $scope)
    {
    }

    /**
     * Headline ticket counters for the DGM dashboard.
     *
     * @return array<string, int|float|null>
     */
    public function ticketSummary(?int $companyId = null, ?int $locationId = null): array
    {
        $companyId ??= $this->scope->companyId();
        $locationId ??= $this->scope->locationId();

        $row = DB::connection('oracle')->selectOne(
            "select
                 COUNT(*)                                                       as total_tickets,
                 SUM(CASE WHEN s.CODE = 'OPEN'         THEN 1 ELSE 0 END)       as open_tickets,
                 SUM(CASE WHEN s.CODE = 'IN_PROGRESS'  THEN 1 ELSE 0 END)       as in_progress_tickets,
                 SUM(CASE WHEN s.CODE = 'PENDING'      THEN 1 ELSE 0 END)       as pending_tickets,
                 SUM(CASE WHEN s.CODE = 'RESOLVED'     THEN 1 ELSE 0 END)       as resolved_tickets,
                 SUM(CASE WHEN s.CODE = 'CLOSED'       THEN 1 ELSE 0 END)       as closed_tickets,
                 SUM(CASE WHEN s.IS_CLOSED_STATE = 0
                           AND t.DUE_AT IS NOT NULL
                           AND t.DUE_AT < LOCALTIMESTAMP THEN 1 ELSE 0 END)       as overdue_tickets,
                 SUM(CASE WHEN t.SLA_STATUS = 'BREACHED' THEN 1 ELSE 0 END)     as sla_breached_tickets,
                 SUM(CASE WHEN p.IS_CRITICAL = 1
                           AND s.IS_CLOSED_STATE = 0 THEN 1 ELSE 0 END)         as critical_tickets,
                 SUM(CASE WHEN t.ASSIGNED_TO IS NULL
                           AND s.IS_CLOSED_STATE = 0 THEN 1 ELSE 0 END)         as unassigned_tickets,
                 SUM(CASE WHEN t.CREATED_AT >= TRUNC(CURRENT_DATE) THEN 1 ELSE 0 END) as today_tickets,
                 ROUND(AVG(CASE WHEN t.RESOLVED_AT IS NOT NULL
                      THEN (CAST(t.RESOLVED_AT AS DATE) - CAST(t.CREATED_AT AS DATE)) * 24
                 END), 2)                                                       as avg_resolution_hours
             from TICKETS t
             join TICKET_STATUSES s   on s.ID = t.STATUS_ID
             join TICKET_PRIORITIES p on p.ID = t.PRIORITY_ID
             where (:company_id is null or t.COMPANY_ID = :company_id)
               and (:location_id is null or t.LOCATION_ID = :location_id)",
            ['company_id' => $companyId, 'location_id' => $locationId]
        );

        return $this->normalise($row);
    }

    /** @return array<string, int|float|null> */
    public function taskSummary(?int $companyId = null, ?int $locationId = null): array
    {
        $companyId ??= $this->scope->companyId();
        $locationId ??= $this->scope->locationId();

        $row = DB::connection('oracle')->selectOne(
            "select
                 COUNT(*)                                                        as total_tasks,
                 SUM(CASE WHEN ts.IS_COMPLETED_STATE = 1 THEN 1 ELSE 0 END)      as completed_tasks,
                 SUM(CASE WHEN ts.CODE = 'PENDING'       THEN 1 ELSE 0 END)      as pending_tasks,
                 SUM(CASE WHEN ts.CODE = 'IN_PROGRESS'   THEN 1 ELSE 0 END)      as in_progress_tasks,
                 SUM(CASE WHEN ts.IS_COMPLETED_STATE = 0
                           AND t.DUE_AT IS NOT NULL
                           AND t.DUE_AT < LOCALTIMESTAMP THEN 1 ELSE 0 END)        as overdue_tasks,
                 SUM(CASE WHEN p.IS_CRITICAL = 1
                           AND ts.IS_COMPLETED_STATE = 0 THEN 1 ELSE 0 END)      as critical_tasks,
                 ROUND(AVG(t.PROGRESS_PERCENT), 1)                               as avg_progress
             from TASKS t
             join TASK_STATUSES ts         on ts.ID = t.TASK_STATUS_ID
             left join TICKET_PRIORITIES p on p.ID = t.PRIORITY_ID
             where (:company_id is null or t.COMPANY_ID = :company_id)
               and (:location_id is null or t.LOCATION_ID = :location_id)",
            ['company_id' => $companyId, 'location_id' => $locationId]
        );

        return $this->normalise($row);
    }

    /** @return array<string, int|float|null> */
    public function developmentSummary(?int $companyId = null, ?int $locationId = null): array
    {
        $companyId ??= $this->scope->companyId();
        $locationId ??= $this->scope->locationId();

        $row = DB::connection('oracle')->selectOne(
            "select
                 COUNT(*)                                                        as total_requests,
                 SUM(CASE WHEN ds.IS_COMPLETED_STATE = 1 THEN 1 ELSE 0 END)      as completed_requests,
                 SUM(CASE WHEN ds.CODE = 'DEVELOPING'    THEN 1 ELSE 0 END)      as developing_requests,
                 SUM(CASE WHEN ds.CODE IN ('TESTING','UAT') THEN 1 ELSE 0 END)   as testing_requests,
                 SUM(CASE WHEN dr.APPROVAL_STATUS = 'PENDING' THEN 1 ELSE 0 END) as pending_approval,
                 SUM(CASE WHEN ds.IS_COMPLETED_STATE = 0
                           AND dr.DUE_AT IS NOT NULL
                           AND dr.DUE_AT < LOCALTIMESTAMP THEN 1 ELSE 0 END)       as overdue_requests,
                 ROUND(AVG(dr.PROGRESS_PERCENT), 1)                              as avg_progress,
                 SUM(NVL(dr.ESTIMATED_HOURS,0))                                  as estimated_hours,
                 SUM(NVL(dr.ACTUAL_HOURS,0))                                     as actual_hours
             from DEVELOPMENT_REQUESTS dr
             join DEVELOPMENT_STATUSES ds on ds.ID = dr.DEVELOPMENT_STATUS_ID
             where (:company_id is null or dr.COMPANY_ID = :company_id)
               and (:location_id is null or dr.LOCATION_ID = :location_id)",
            ['company_id' => $companyId, 'location_id' => $locationId]
        );

        return $this->normalise($row);
    }

    /**
     * Ticket volume per day for the trend chart, scoped the same way as the
     * headline counters so the two always agree.
     */
    public function ticketTrend(int $days = 14, ?int $companyId = null, ?int $locationId = null): array
    {
        $companyId ??= $this->scope->companyId();
        $locationId ??= $this->scope->locationId();

        return DB::connection('oracle')->select(
            "select TO_CHAR(d.DAY, 'YYYY-MM-DD') as day_label,
                    NVL(t.CREATED_COUNT, 0)      as created_count,
                    NVL(t.RESOLVED_COUNT, 0)     as resolved_count
               from (select TRUNC(CURRENT_DATE) - LEVEL + 1 as DAY
                       from dual connect by LEVEL <= :days) d
               left join (
                    select TRUNC(CREATED_AT) as DAY,
                           COUNT(*)          as CREATED_COUNT,
                           SUM(CASE WHEN RESOLVED_AT IS NOT NULL THEN 1 ELSE 0 END) as RESOLVED_COUNT
                      from TICKETS
                     where CREATED_AT >= TRUNC(CURRENT_DATE) - :days
                       and (:company_id is null or COMPANY_ID = :company_id)
                       and (:location_id is null or LOCATION_ID = :location_id)
                     group by TRUNC(CREATED_AT)
               ) t on t.DAY = d.DAY
              order by d.DAY",
            ['days' => $days, 'company_id' => $companyId, 'location_id' => $locationId]
        );
    }

    /** Ticket counts grouped by status, for the donut chart and status strip. */
    public function ticketsByStatus(?int $companyId = null, ?int $locationId = null): array
    {
        $companyId ??= $this->scope->companyId();
        $locationId ??= $this->scope->locationId();

        return DB::connection('oracle')->select(
            'select s.CODE as code, s.NAME as name, s.COLOR as color, COUNT(t.ID) as total
               from TICKET_STATUSES s
               left join TICKETS t on t.STATUS_ID = s.ID
                    and (:company_id is null or t.COMPANY_ID = :company_id)
                    and (:location_id is null or t.LOCATION_ID = :location_id)
              where s.IS_ACTIVE = 1
              group by s.CODE, s.NAME, s.COLOR, s.SORT_ORDER
              order by s.SORT_ORDER',
            ['company_id' => $companyId, 'location_id' => $locationId]
        );
    }

    public function ticketsByPriority(?int $companyId = null, ?int $locationId = null): array
    {
        $companyId ??= $this->scope->companyId();
        $locationId ??= $this->scope->locationId();

        return DB::connection('oracle')->select(
            'select p.CODE as code, p.NAME as name, p.COLOR as color, COUNT(t.ID) as total
               from TICKET_PRIORITIES p
               left join TICKETS t on t.PRIORITY_ID = p.ID
                    and (:company_id is null or t.COMPANY_ID = :company_id)
                    and (:location_id is null or t.LOCATION_ID = :location_id)
              where p.IS_ACTIVE = 1
              group by p.CODE, p.NAME, p.COLOR, p.SORT_ORDER
              order by p.SORT_ORDER',
            ['company_id' => $companyId, 'location_id' => $locationId]
        );
    }

    /**
     * Ticket load per requesting department.
     *
     * The VW_DEPARTMENT_TICKET_SUMMARY view is grouped per location, so rolling
     * it up to company level would mean applying SUM() to a column that is
     * already a SUM - which Oracle rejects (ORA-00935). The dashboard therefore
     * aggregates the base tables directly and the view stays for reports that
     * want the per-location grain.
     */
    public function departmentBreakdown(?int $companyId = null, int $limit = 10): array
    {
        $companyId ??= $this->scope->companyId();

        return DB::connection('oracle')->select(
            'select d.NAME as department_name,
                    COUNT(t.ID)                                             as total_tickets,
                    SUM(CASE WHEN s.IS_CLOSED_STATE = 0 THEN 1 ELSE 0 END)  as active_tickets,
                    SUM(CASE WHEN s.IS_CLOSED_STATE = 0
                              AND t.DUE_AT IS NOT NULL
                              AND t.DUE_AT < LOCALTIMESTAMP THEN 1 ELSE 0 END) as overdue_tickets
               from DEPARTMENTS d
               join TICKETS t          on t.DEPARTMENT_ID = d.ID
               join TICKET_STATUSES s  on s.ID = t.STATUS_ID
              where (:company_id is null or t.COMPANY_ID = :company_id)
              group by d.NAME
              order by COUNT(t.ID) desc
              fetch first :row_limit rows only',
            ['company_id' => $companyId, 'row_limit' => $limit]
        );
    }

    /** IT agent leaderboard, aggregated from the base tables for the same reason. */
    public function teamPerformance(?int $companyId = null, int $limit = 10): array
    {
        $companyId ??= $this->scope->companyId();

        return DB::connection('oracle')->select(
            'select u.NAME as agent_name, dep.NAME as department_name,
                    COUNT(t.ID)                                             as assigned_tickets,
                    SUM(CASE WHEN s.IS_CLOSED_STATE = 1 THEN 1 ELSE 0 END)  as completed_tickets,
                    SUM(CASE WHEN s.IS_CLOSED_STATE = 0 THEN 1 ELSE 0 END)  as active_tickets,
                    SUM(CASE WHEN s.IS_CLOSED_STATE = 0
                              AND t.DUE_AT IS NOT NULL
                              AND t.DUE_AT < LOCALTIMESTAMP THEN 1 ELSE 0 END) as overdue_tickets,
                    ROUND(AVG(CASE WHEN t.RESOLVED_AT IS NOT NULL
                         THEN (CAST(t.RESOLVED_AT AS DATE) - CAST(t.CREATED_AT AS DATE)) * 24
                    END), 2)                                                as avg_resolution_hours
               from TICKETS t
               join USERS u             on u.ID = t.ASSIGNED_TO
               join TICKET_STATUSES s   on s.ID = t.STATUS_ID
               left join DEPARTMENTS dep on dep.ID = u.DEPARTMENT_ID
              where (:company_id is null or t.COMPANY_ID = :company_id)
              group by u.NAME, dep.NAME
              order by COUNT(t.ID) desc
              fetch first :row_limit rows only',
            ['company_id' => $companyId, 'row_limit' => $limit]
        );
    }

    /** Per-company roll-up for users who span several companies. */
    public function companyBreakdown(User $user): array
    {
        $companyIds = $user->accessibleCompanyIds();

        if ($companyIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($companyIds), '?'));

        return DB::connection('oracle')->select(
            "select c.NAME as company_name, c.CODE as company_code,
                    COUNT(t.ID) as total_tickets,
                    SUM(CASE WHEN s.IS_CLOSED_STATE = 0 THEN 1 ELSE 0 END) as active_tickets,
                    SUM(CASE WHEN s.IS_CLOSED_STATE = 0
                              AND t.DUE_AT IS NOT NULL
                              AND t.DUE_AT < LOCALTIMESTAMP THEN 1 ELSE 0 END) as overdue_tickets
               from COMPANIES c
               left join TICKETS t        on t.COMPANY_ID = c.ID
               left join TICKET_STATUSES s on s.ID = t.STATUS_ID
              where c.ID in ({$placeholders})
              group by c.NAME, c.CODE
              order by c.NAME",
            $companyIds
        );
    }

    /**
     * Oracle returns aggregate columns in whatever case the driver reports and
     * SUM() over no rows yields null, so normalise both here.
     *
     * @return array<string, int|float|null>
     */
    private function normalise(?object $row): array
    {
        $out = [];

        foreach ((array) ($row ?? []) as $key => $value) {
            $key = strtolower($key);

            if ($value === null) {
                $out[$key] = str_contains($key, 'avg') ? null : 0;
                continue;
            }

            $out[$key] = str_contains($key, 'avg') || str_contains($key, 'hours')
                ? (float) $value
                : (int) $value;
        }

        return $out;
    }
}
