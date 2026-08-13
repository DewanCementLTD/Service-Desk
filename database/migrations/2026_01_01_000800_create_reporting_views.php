<?php

use App\Support\OracleSchema;
use Illuminate\Database\Migrations\Migration;

/**
 * Reporting views.
 *
 * Only the genuinely reusable, join-heavy aggregations live here; simple listing
 * queries stay in the Laravel query builder. Every view is company/location aware
 * so the application can filter to the user's active scope.
 */
return new class extends Migration
{
    public function up(): void
    {
        OracleSchema::run([
            // Ticket counts per company/location, used by the DGM dashboard.
            "CREATE OR REPLACE VIEW VW_DGM_TICKET_SUMMARY AS
             SELECT
                 c.ORGANIZATION_ID,
                 t.COMPANY_ID,
                 t.LOCATION_ID,
                 COUNT(*)                                                    AS TOTAL_TICKETS,
                 SUM(CASE WHEN s.CODE = 'OPEN'        THEN 1 ELSE 0 END)     AS OPEN_TICKETS,
                 SUM(CASE WHEN s.CODE = 'IN_PROGRESS' THEN 1 ELSE 0 END)     AS IN_PROGRESS_TICKETS,
                 SUM(CASE WHEN s.CODE = 'PENDING'     THEN 1 ELSE 0 END)     AS PENDING_TICKETS,
                 SUM(CASE WHEN s.CODE = 'RESOLVED'    THEN 1 ELSE 0 END)     AS RESOLVED_TICKETS,
                 SUM(CASE WHEN s.CODE = 'CLOSED'      THEN 1 ELSE 0 END)     AS CLOSED_TICKETS,
                 SUM(CASE WHEN s.IS_CLOSED_STATE = 0
                           AND t.DUE_AT IS NOT NULL
                           AND t.DUE_AT < LOCALTIMESTAMP THEN 1 ELSE 0 END)    AS OVERDUE_TICKETS,
                 SUM(CASE WHEN t.SLA_STATUS = 'BREACHED' THEN 1 ELSE 0 END)  AS SLA_BREACHED_TICKETS,
                 SUM(CASE WHEN p.IS_CRITICAL = 1 AND s.IS_CLOSED_STATE = 0
                          THEN 1 ELSE 0 END)                                 AS CRITICAL_TICKETS,
                 SUM(CASE WHEN t.ASSIGNED_TO IS NULL AND s.IS_CLOSED_STATE = 0
                          THEN 1 ELSE 0 END)                                 AS UNASSIGNED_TICKETS,
                 ROUND(AVG(CASE WHEN t.RESOLVED_AT IS NOT NULL
                      THEN (CAST(t.RESOLVED_AT AS DATE) - CAST(t.CREATED_AT AS DATE)) * 24
                 END), 2)                                                    AS AVG_RESOLUTION_HOURS
             FROM TICKETS t
             JOIN COMPANIES c         ON c.ID = t.COMPANY_ID
             JOIN TICKET_STATUSES s   ON s.ID = t.STATUS_ID
             JOIN TICKET_PRIORITIES p ON p.ID = t.PRIORITY_ID
             GROUP BY c.ORGANIZATION_ID, t.COMPANY_ID, t.LOCATION_ID",

            // Per-agent workload and throughput for the IT team view.
            "CREATE OR REPLACE VIEW VW_IT_TEAM_PERFORMANCE AS
             SELECT
                 t.COMPANY_ID,
                 t.LOCATION_ID,
                 u.ID                                                        AS USER_ID,
                 u.NAME                                                      AS AGENT_NAME,
                 u.EMAIL                                                     AS AGENT_EMAIL,
                 d.NAME                                                      AS DEPARTMENT_NAME,
                 COUNT(*)                                                    AS ASSIGNED_TICKETS,
                 SUM(CASE WHEN s.IS_CLOSED_STATE = 1 THEN 1 ELSE 0 END)      AS COMPLETED_TICKETS,
                 SUM(CASE WHEN s.IS_CLOSED_STATE = 0 THEN 1 ELSE 0 END)      AS ACTIVE_TICKETS,
                 SUM(CASE WHEN s.IS_CLOSED_STATE = 0
                           AND t.DUE_AT IS NOT NULL
                           AND t.DUE_AT < LOCALTIMESTAMP THEN 1 ELSE 0 END)    AS OVERDUE_TICKETS,
                 SUM(CASE WHEN t.SLA_STATUS = 'BREACHED' THEN 1 ELSE 0 END)  AS SLA_BREACHED_TICKETS,
                 ROUND(AVG(CASE WHEN t.RESOLVED_AT IS NOT NULL
                      THEN (CAST(t.RESOLVED_AT AS DATE) - CAST(t.CREATED_AT AS DATE)) * 24
                 END), 2)                                                    AS AVG_RESOLUTION_HOURS,
                 ROUND(AVG(t.SATISFACTION_RATING), 2)                        AS AVG_SATISFACTION
             FROM TICKETS t
             JOIN USERS u            ON u.ID = t.ASSIGNED_TO
             JOIN TICKET_STATUSES s  ON s.ID = t.STATUS_ID
             LEFT JOIN DEPARTMENTS d ON d.ID = u.DEPARTMENT_ID
             GROUP BY t.COMPANY_ID, t.LOCATION_ID, u.ID, u.NAME, u.EMAIL, d.NAME",

            // Ticket load broken down by requesting department.
            "CREATE OR REPLACE VIEW VW_DEPARTMENT_TICKET_SUMMARY AS
             SELECT
                 d.COMPANY_ID,
                 t.LOCATION_ID,
                 d.ID                                                        AS DEPARTMENT_ID,
                 d.CODE                                                      AS DEPARTMENT_CODE,
                 d.NAME                                                      AS DEPARTMENT_NAME,
                 COUNT(t.ID)                                                 AS TOTAL_TICKETS,
                 SUM(CASE WHEN s.IS_CLOSED_STATE = 0 THEN 1 ELSE 0 END)      AS ACTIVE_TICKETS,
                 SUM(CASE WHEN s.IS_CLOSED_STATE = 1 THEN 1 ELSE 0 END)      AS CLOSED_TICKETS,
                 SUM(CASE WHEN s.IS_CLOSED_STATE = 0
                           AND t.DUE_AT IS NOT NULL
                           AND t.DUE_AT < LOCALTIMESTAMP THEN 1 ELSE 0 END)    AS OVERDUE_TICKETS,
                 SUM(CASE WHEN p.IS_CRITICAL = 1 THEN 1 ELSE 0 END)          AS CRITICAL_TICKETS
             FROM DEPARTMENTS d
             LEFT JOIN TICKETS t          ON t.DEPARTMENT_ID = d.ID
             LEFT JOIN TICKET_STATUSES s  ON s.ID = t.STATUS_ID
             LEFT JOIN TICKET_PRIORITIES p ON p.ID = t.PRIORITY_ID
             GROUP BY d.COMPANY_ID, t.LOCATION_ID, d.ID, d.CODE, d.NAME",

            // Compliance dashboard counters including risk breakdown.
            "CREATE OR REPLACE VIEW VW_COMPLIANCE_SUMMARY AS
             SELECT
                 c.ORGANIZATION_ID,
                 ct.COMPANY_ID,
                 ct.LOCATION_ID,
                 COUNT(*)                                                        AS TOTAL_TASKS,
                 SUM(CASE WHEN cs.IS_COMPLETED_STATE = 1 THEN 1 ELSE 0 END)      AS COMPLETED_TASKS,
                 SUM(CASE WHEN cs.CODE = 'PENDING'     THEN 1 ELSE 0 END)        AS PENDING_TASKS,
                 SUM(CASE WHEN cs.CODE = 'IN_PROGRESS' THEN 1 ELSE 0 END)        AS IN_PROGRESS_TASKS,
                 SUM(CASE WHEN cs.IS_COMPLETED_STATE = 0
                           AND ct.DUE_AT IS NOT NULL
                           AND ct.DUE_AT < LOCALTIMESTAMP THEN 1 ELSE 0 END)       AS OVERDUE_TASKS,
                 SUM(CASE WHEN ct.RISK_LEVEL = 'HIGH'     THEN 1 ELSE 0 END)     AS HIGH_RISK_TASKS,
                 SUM(CASE WHEN ct.RISK_LEVEL = 'CRITICAL' THEN 1 ELSE 0 END)     AS CRITICAL_RISK_TASKS,
                 ROUND(AVG(ct.PROGRESS_PERCENT), 2)                              AS AVG_PROGRESS
             FROM COMPLIANCE_TASKS ct
             JOIN COMPANIES c            ON c.ID = ct.COMPANY_ID
             JOIN COMPLIANCE_STATUSES cs ON cs.ID = ct.COMPLIANCE_STATUS_ID
             GROUP BY c.ORGANIZATION_ID, ct.COMPANY_ID, ct.LOCATION_ID",

            // Management dashboard counters.
            "CREATE OR REPLACE VIEW VW_MANAGEMENT_TASK_SUMMARY AS
             SELECT
                 c.ORGANIZATION_ID,
                 t.COMPANY_ID,
                 t.LOCATION_ID,
                 COUNT(*)                                                        AS TOTAL_TASKS,
                 SUM(CASE WHEN ts.IS_COMPLETED_STATE = 1 THEN 1 ELSE 0 END)      AS COMPLETED_TASKS,
                 SUM(CASE WHEN ts.CODE = 'PENDING'     THEN 1 ELSE 0 END)        AS PENDING_TASKS,
                 SUM(CASE WHEN ts.CODE = 'IN_PROGRESS' THEN 1 ELSE 0 END)        AS IN_PROGRESS_TASKS,
                 SUM(CASE WHEN ts.IS_COMPLETED_STATE = 0
                           AND t.DUE_AT IS NOT NULL
                           AND t.DUE_AT < LOCALTIMESTAMP THEN 1 ELSE 0 END)        AS OVERDUE_TASKS,
                 SUM(CASE WHEN p.IS_CRITICAL = 1 AND ts.IS_COMPLETED_STATE = 0
                          THEN 1 ELSE 0 END)                                     AS CRITICAL_TASKS,
                 ROUND(AVG(t.PROGRESS_PERCENT), 2)                               AS AVG_PROGRESS
             FROM TASKS t
             JOIN COMPANIES c              ON c.ID = t.COMPANY_ID
             JOIN TASK_STATUSES ts         ON ts.ID = t.TASK_STATUS_ID
             LEFT JOIN TICKET_PRIORITIES p ON p.ID = t.PRIORITY_ID
             GROUP BY c.ORGANIZATION_ID, t.COMPANY_ID, t.LOCATION_ID",

            // Development pipeline by status.
            "CREATE OR REPLACE VIEW VW_DEVELOPMENT_STATUS AS
             SELECT
                 c.ORGANIZATION_ID,
                 dr.COMPANY_ID,
                 dr.LOCATION_ID,
                 ds.ID                                                           AS STATUS_ID,
                 ds.CODE                                                         AS STATUS_CODE,
                 ds.NAME                                                         AS STATUS_NAME,
                 ds.SORT_ORDER,
                 COUNT(dr.ID)                                                    AS TOTAL_REQUESTS,
                 SUM(CASE WHEN dr.APPROVAL_STATUS = 'PENDING'  THEN 1 ELSE 0 END) AS PENDING_APPROVAL,
                 SUM(CASE WHEN dr.APPROVAL_STATUS = 'APPROVED' THEN 1 ELSE 0 END) AS APPROVED_REQUESTS,
                 SUM(CASE WHEN ds.IS_COMPLETED_STATE = 0
                           AND dr.DUE_AT IS NOT NULL
                           AND dr.DUE_AT < LOCALTIMESTAMP THEN 1 ELSE 0 END)       AS OVERDUE_REQUESTS,
                 ROUND(AVG(dr.PROGRESS_PERCENT), 2)                              AS AVG_PROGRESS,
                 SUM(NVL(dr.ESTIMATED_HOURS, 0))                                 AS TOTAL_ESTIMATED_HOURS,
                 SUM(NVL(dr.ACTUAL_HOURS, 0))                                    AS TOTAL_ACTUAL_HOURS
             FROM DEVELOPMENT_REQUESTS dr
             JOIN COMPANIES c             ON c.ID = dr.COMPANY_ID
             JOIN DEVELOPMENT_STATUSES ds ON ds.ID = dr.DEVELOPMENT_STATUS_ID
             GROUP BY c.ORGANIZATION_ID, dr.COMPANY_ID, dr.LOCATION_ID,
                      ds.ID, ds.CODE, ds.NAME, ds.SORT_ORDER",

            // Row-level list of breached / at-risk tickets for the SLA report.
            "CREATE OR REPLACE VIEW VW_SLA_BREACHES AS
             SELECT
                 t.ID                        AS TICKET_ID,
                 t.TICKET_NO,
                 t.COMPANY_ID,
                 t.LOCATION_ID,
                 co.NAME                     AS COMPANY_NAME,
                 l.NAME                      AS LOCATION_NAME,
                 t.SUBJECT,
                 s.NAME                      AS STATUS_NAME,
                 p.NAME                      AS PRIORITY_NAME,
                 p.IS_CRITICAL,
                 req.NAME                    AS REQUESTER_NAME,
                 asg.NAME                    AS ASSIGNEE_NAME,
                 d.NAME                      AS DEPARTMENT_NAME,
                 t.CREATED_AT,
                 t.DUE_AT,
                 t.RESOLVED_AT,
                 t.SLA_STATUS,
                 t.SLA_BREACH_AT,
                 ROUND((CAST(LOCALTIMESTAMP AS DATE) - CAST(t.DUE_AT AS DATE)) * 24, 2) AS HOURS_OVERDUE
             FROM TICKETS t
             JOIN COMPANIES co           ON co.ID = t.COMPANY_ID
             JOIN TICKET_STATUSES s      ON s.ID = t.STATUS_ID
             JOIN TICKET_PRIORITIES p    ON p.ID = t.PRIORITY_ID
             JOIN USERS req              ON req.ID = t.REQUESTER_ID
             LEFT JOIN LOCATIONS l       ON l.ID = t.LOCATION_ID
             LEFT JOIN USERS asg         ON asg.ID = t.ASSIGNED_TO
             LEFT JOIN DEPARTMENTS d     ON d.ID = t.DEPARTMENT_ID
             WHERE t.SLA_STATUS IN ('BREACHED','AT_RISK')
                OR (s.IS_CLOSED_STATE = 0 AND t.DUE_AT IS NOT NULL AND t.DUE_AT < LOCALTIMESTAMP)",

            // Flattened company/location access per user, honouring the
            // IS_ALL_ORGS / IS_ALL_COMPANIES / IS_ALL_LOCATIONS wildcards.
            "CREATE OR REPLACE VIEW VW_USER_ACCESS_SCOPE AS
             SELECT u.ID AS USER_ID, c.ORGANIZATION_ID, l.COMPANY_ID, l.ID AS LOCATION_ID
             FROM USERS u
             JOIN LOCATIONS l  ON 1 = 1
             JOIN COMPANIES c  ON c.ID = l.COMPANY_ID
             WHERE u.IS_ACTIVE = 1
               AND l.IS_ACTIVE = 1
               AND c.IS_ACTIVE = 1
               AND (
                     u.IS_ALL_LOCATIONS = 1
                  OR EXISTS (SELECT 1 FROM USER_LOCATIONS ul
                              WHERE ul.USER_ID = u.ID AND ul.LOCATION_ID = l.ID)
                   )
               AND (
                     u.IS_ALL_COMPANIES = 1
                  OR EXISTS (SELECT 1 FROM USER_COMPANIES uc
                              WHERE uc.USER_ID = u.ID AND uc.COMPANY_ID = c.ID)
                   )",
        ]);
    }

    public function down(): void
    {
        foreach ([
            'VW_USER_ACCESS_SCOPE',
            'VW_SLA_BREACHES',
            'VW_DEVELOPMENT_STATUS',
            'VW_MANAGEMENT_TASK_SUMMARY',
            'VW_COMPLIANCE_SUMMARY',
            'VW_DEPARTMENT_TICKET_SUMMARY',
            'VW_IT_TEAM_PERFORMANCE',
            'VW_DGM_TICKET_SUMMARY',
        ] as $view) {
            OracleSchema::dropView($view);
        }
    }
};
