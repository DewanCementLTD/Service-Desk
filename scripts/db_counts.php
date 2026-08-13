<?php
/**
 * Row counts per application table, plus a sample of generated document numbers.
 *
 * Usage: php scripts/db_counts.php
 */

use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$tables = [
    'organizations', 'companies', 'locations', 'departments', 'roles', 'permissions',
    'role_permissions', 'users', 'user_organizations', 'user_companies', 'user_locations',
    'ticket_statuses', 'ticket_priorities', 'ticket_types', 'ticket_categories',
    'sla_policies', 'tickets', 'ticket_comments', 'ticket_history',
    'development_statuses', 'development_requests', 'compliance_statuses',
    'compliance_categories', 'compliance_tasks', 'task_statuses', 'tasks',
    'notifications', 'audit_logs', 'doc_sequences',
];

echo "=== Row counts ===\n";
foreach ($tables as $table) {
    printf("%-24s %6d\n", $table, DB::connection('oracle')->table($table)->count());
}

echo "\n=== Sample document numbers ===\n";
foreach ([
    'tickets'              => 'ticket_no',
    'development_requests' => 'request_no',
    'compliance_tasks'     => 'task_no',
    'tasks'                => 'task_no',
] as $table => $column) {
    $numbers = DB::connection('oracle')->table($table)
        ->orderBy('id')->limit(3)->pluck($column)->implode(', ');

    printf("%-24s %s\n", $table, $numbers ?: '(none)');
}

echo "\n=== Duplicate document numbers per company ===\n";
$dupes = DB::connection('oracle')->select(
    "select 'tickets' as tbl, count(*) as dupes from (
         select company_id, ticket_no from tickets group by company_id, ticket_no having count(*) > 1)
     union all
     select 'development_requests', count(*) from (
         select company_id, request_no from development_requests group by company_id, request_no having count(*) > 1)
     union all
     select 'compliance_tasks', count(*) from (
         select company_id, task_no from compliance_tasks group by company_id, task_no having count(*) > 1)
     union all
     select 'tasks', count(*) from (
         select company_id, task_no from tasks group by company_id, task_no having count(*) > 1)"
);

foreach ($dupes as $row) {
    $row = array_change_key_case((array) $row, CASE_LOWER);
    printf("%-24s %d\n", $row['tbl'], $row['dupes']);
}
