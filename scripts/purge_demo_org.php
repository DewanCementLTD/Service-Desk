<?php

/**
 * Removes the seeded demo organisation tree — the 'GRP' group (Group Holdings)
 * with its companies, locations and departments — from a live schema.
 *
 * Organisations created by hand (anything outside the 'GRP' group) are left
 * alone. Users are kept; only their grants and default selections that point
 * at the demo tree are cleared.
 *
 * Run scripts/purge_demo_data.php first — this refuses to run while any
 * transaction still references the tree.
 *
 *     php scripts/purge_demo_org.php --dry-run    report only
 *     php scripts/purge_demo_org.php              back up, then delete
 */

$base = __DIR__ . '/..';
require $base . '/vendor/autoload.php';
$app = require_once $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$dryRun = in_array('--dry-run', $argv, true);
$db = DB::connection('oracle');

// Default target is the seeded 'GRP' group; --all clears every organisation.
$all = in_array('--all', $argv, true);

$orgs = $all
    ? $db->table('organizations')->get()
    : $db->table('organizations')->where('code', 'GRP')->get();

if ($orgs->isEmpty()) {
    echo $all ? "No organisations found — nothing to do.\n" : "No 'GRP' organisation found — nothing to do.\n";
    exit(0);
}

$orgIds      = $orgs->pluck('id')->map(fn ($i) => (int) $i)->all();
$companyIds  = $db->table('companies')->whereIn('organization_id', $orgIds)->pluck('id')->map(fn ($i) => (int) $i)->all();
$locationIds = $companyIds ? $db->table('locations')->whereIn('company_id', $companyIds)->pluck('id')->map(fn ($i) => (int) $i)->all() : [];
$deptIds     = $companyIds ? $db->table('departments')->whereIn('company_id', $companyIds)->pluck('id')->map(fn ($i) => (int) $i)->all() : [];

printf("Target: %s\n  organisations %s\n  companies     %s\n  locations     %s\n  departments   %s\n\n",
    $all ? 'every organisation' : "the seeded 'GRP' group",
    implode(', ', $orgs->map(fn ($o) => $o->id . ' (' . $o->name . ')')->all()),
    $companyIds ? implode(', ', $companyIds) : '-',
    $locationIds ? implode(', ', $locationIds) : '-',
    $deptIds ? implode(', ', $deptIds) : '-');

// Anything still referencing the tree blocks the delete — report it rather than
// letting Oracle raise ORA-02292 halfway through.
$blockers = [];
foreach ([
    ['tickets', 'company_id', $companyIds],
    ['development_requests', 'company_id', $companyIds],
    ['compliance_tasks', 'company_id', $companyIds],
    ['tasks', 'company_id', $companyIds],
    ['approvals', 'company_id', $companyIds],
    ['notifications', 'company_id', $companyIds],
    ['audit_logs', 'company_id', $companyIds],
    ['doc_sequences', 'company_id', $companyIds],
    ['assets', 'company_id', $companyIds],
] as [$table, $column, $ids]) {
    $count = $ids ? (int) $db->table($table)->whereIn($column, $ids)->count() : 0;
    if ($count > 0) {
        $blockers[] = "{$table} ({$count} rows)";
    }
}

if ($blockers) {
    fwrite(STDERR, "Still referenced by: " . implode(', ', $blockers) . "\nRun scripts/purge_demo_data.php first.\n");
    exit(1);
}

$otherOrgs = $db->table('organizations')->whereNotIn('id', $orgIds)->get();
echo "Kept as-is:\n";
foreach ($otherOrgs as $other) {
    $companies = $db->table('companies')->where('organization_id', $other->id)->pluck('name')->all();
    printf("  organisation %d (%s)%s\n", $other->id, $other->name, $companies ? ' — ' . implode(', ', $companies) : '');
}
if ($otherOrgs->isEmpty()) {
    echo "  (nothing — the schema will have no organisation left)\n";
}
echo "\n";

$grants = [
    'user_organizations' => $db->table('user_organizations')->whereIn('organization_id', $orgIds)->count(),
    'user_companies'     => $companyIds ? $db->table('user_companies')->whereIn('company_id', $companyIds)->count() : 0,
    'user_locations'     => $locationIds ? $db->table('user_locations')->whereIn('location_id', $locationIds)->count() : 0,
];
foreach ($grants as $table => $count) {
    printf("grants  %-20s %4d\n", $table, $count);
}

if ($dryRun) {
    echo "\nDry run — nothing was changed.\n";
    exit(0);
}

// ---------------------------------------------------------------- backup
$dir = $base . '/storage/app/backups/demo-org-purge-' . date('Ymd-His');
if (! is_dir($dir) && ! mkdir($dir, 0775, true)) {
    fwrite(STDERR, "Could not create backup directory {$dir}\n");
    exit(1);
}

// The whole table is captured for each of these — they are small, and a full
// copy is easier to restore from than a filtered one.
foreach (['organizations', 'companies', 'locations', 'departments',
          'user_organizations', 'user_companies', 'user_locations', 'users'] as $table) {
    $rows = array_map(fn ($row) => array_change_key_case((array) $row, CASE_LOWER), $db->table($table)->get()->all());
    $json = json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($json === false || file_put_contents("{$dir}/{$table}.json", $json) === false) {
        fwrite(STDERR, "Backup of {$table} failed — aborting before any delete.\n");
        exit(1);
    }
    printf("backed up %-20s %4d rows\n", $table, count($rows));
}

echo "\nBackup written to {$dir}\n\n";

// ---------------------------------------------------------------- delete
$db->transaction(function () use ($db, $orgIds, $companyIds, $locationIds, $deptIds) {
    // Clear the pointers into the tree before removing it.
    if ($deptIds) {
        printf("cleared   %-28s %4d\n", 'ticket_categories.dept', $db->table('ticket_categories')->whereIn('default_team_dept_id', $deptIds)->update(['default_team_dept_id' => null]));
        printf("cleared   %-28s %4d\n", 'users.department_id', $db->table('users')->whereIn('department_id', $deptIds)->update(['department_id' => null]));
    }
    if ($locationIds) {
        printf("cleared   %-28s %4d\n", 'users.default_location_id', $db->table('users')->whereIn('default_location_id', $locationIds)->update(['default_location_id' => null]));
    }
    if ($companyIds) {
        printf("cleared   %-28s %4d\n", 'users.default_company_id', $db->table('users')->whereIn('default_company_id', $companyIds)->update(['default_company_id' => null]));
        printf("cleared   %-28s %4d\n", 'sla_policies.company_id', $db->table('sla_policies')->whereIn('company_id', $companyIds)->update(['company_id' => null]));
    }
    printf("cleared   %-28s %4d\n", 'users.default_org_id', $db->table('users')->whereIn('default_org_id', $orgIds)->update(['default_org_id' => null]));

    // Grants.
    if ($locationIds) {
        printf("deleted   %-28s %4d\n", 'user_locations', $db->table('user_locations')->whereIn('location_id', $locationIds)->delete());
    }
    if ($companyIds) {
        printf("deleted   %-28s %4d\n", 'user_companies', $db->table('user_companies')->whereIn('company_id', $companyIds)->delete());
    }
    printf("deleted   %-28s %4d\n", 'user_organizations', $db->table('user_organizations')->whereIn('organization_id', $orgIds)->delete());

    // The tree itself, children first.
    if ($deptIds) {
        printf("deleted   %-28s %4d\n", 'departments', $db->table('departments')->whereIn('id', $deptIds)->delete());
    }
    if ($locationIds) {
        printf("deleted   %-28s %4d\n", 'locations', $db->table('locations')->whereIn('id', $locationIds)->delete());
    }
    if ($companyIds) {
        printf("deleted   %-28s %4d\n", 'companies', $db->table('companies')->whereIn('id', $companyIds)->delete());
    }
    printf("deleted   %-28s %4d\n", 'organizations', $db->table('organizations')->whereIn('id', $orgIds)->delete());
});

echo "\nDone. Users, roles, permissions, lookups and SLA policies are untouched.\n";
