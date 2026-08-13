<?php

/**
 * Removes the sample transactions loaded by DemoDataSeeder from a live schema.
 *
 * Only transactional tables are touched. Roles, permissions, lookups, SLA
 * policies, users and the organisation tree are left exactly as they are, so
 * the system stays usable straight afterwards.
 *
 * Every affected table is written to a JSON file first, and the deletes run in
 * one transaction — either all of it goes or none of it does.
 *
 *     php scripts/purge_demo_data.php --dry-run    report only
 *     php scripts/purge_demo_data.php              back up, then delete
 */

$base = __DIR__ . '/..';
require $base . '/vendor/autoload.php';
$app = require_once $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$dryRun = in_array('--dry-run', $argv, true);

/** Children first — the foreign keys are enforced. */
$tables = [
    'ticket_attachments',
    'ticket_comments',
    'ticket_history',
    'development_updates',
    'compliance_updates',
    'task_comments',
    'approvals',
    'notifications',
    'tickets',
    'development_requests',
    'compliance_tasks',
    'tasks',
    'doc_sequences',
    'audit_logs',
];

/** Reset to 1 so the first real record does not start at 81. */
$sequences = [
    'SEQ_TICKETS', 'SEQ_TICKET_COMMENTS', 'SEQ_TICKET_HISTORY', 'SEQ_TICKET_ATTACHMENTS',
    'SEQ_DEVELOPMENT_REQUESTS', 'SEQ_DEVELOPMENT_UPDATES',
    'SEQ_COMPLIANCE_TASKS', 'SEQ_COMPLIANCE_UPDATES',
    'SEQ_TASKS', 'SEQ_TASK_COMMENTS',
    'SEQ_APPROVALS', 'SEQ_NOTIFICATIONS', 'SEQ_AUDIT_LOGS',
];

$connection = DB::connection('oracle');

echo "Rows to remove\n--------------\n";
$total = 0;
$counts = [];
foreach ($tables as $table) {
    $counts[$table] = (int) $connection->table($table)->count();
    $total += $counts[$table];
    printf("%-24s %6d\n", $table, $counts[$table]);
}
printf("%-24s %6d\n\n", 'TOTAL', $total);

if ($dryRun) {
    echo "Dry run — nothing was changed.\n";
    exit(0);
}

if ($total === 0) {
    echo "Nothing to remove.\n";
    exit(0);
}

// ---------------------------------------------------------------- backup
$dir = $base . '/storage/app/backups/demo-purge-' . date('Ymd-His');
if (! is_dir($dir) && ! mkdir($dir, 0775, true)) {
    fwrite(STDERR, "Could not create backup directory {$dir}\n");
    exit(1);
}

foreach ($tables as $table) {
    if ($counts[$table] === 0) {
        continue;
    }

    $rows = array_map(fn ($row) => array_change_key_case((array) $row, CASE_LOWER), $connection->table($table)->get()->all());
    $json = json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($json === false || file_put_contents("{$dir}/{$table}.json", $json) === false) {
        fwrite(STDERR, "Backup of {$table} failed — aborting before any delete.\n");
        exit(1);
    }

    printf("backed up %-24s %6d rows\n", $table, $counts[$table]);
}

echo "\nBackup written to {$dir}\n\n";

// ---------------------------------------------------------------- delete
$connection->transaction(function () use ($connection, $tables) {
    foreach ($tables as $table) {
        $deleted = $connection->table($table)->delete();
        printf("deleted   %-24s %6d rows\n", $table, $deleted);
    }
});

// ------------------------------------------------------------- sequences
// Oracle cannot set a sequence's value directly; stepping it by the negative
// of its current value and back is the standard way to rewind it.
foreach ($sequences as $sequence) {
    try {
        $current = (int) array_values((array) $connection->selectOne("SELECT {$sequence}.NEXTVAL AS V FROM DUAL"))[0];
        if ($current <= 1) {
            continue;
        }
        $step = -1 * ($current - 1);
        $connection->statement("ALTER SEQUENCE {$sequence} INCREMENT BY {$step} MINVALUE 0");
        $connection->selectOne("SELECT {$sequence}.NEXTVAL AS V FROM DUAL");
        $connection->statement("ALTER SEQUENCE {$sequence} INCREMENT BY 1");
        echo "reset     {$sequence}\n";
    } catch (\Throwable $e) {
        // A sequence that does not exist in this schema is not an error here.
        echo "skipped   {$sequence} ({$e->getMessage()})\n";
    }
}

echo "\nDone. Master data (users, organisations, roles, lookups, SLA policies) untouched.\n";
