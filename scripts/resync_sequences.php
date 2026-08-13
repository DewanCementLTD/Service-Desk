<?php

/**
 * Realigns every SDESK sequence with its table.
 *
 * An empty table's sequence is rewound to 1; a populated one is set to
 * MAX(ID) + 1. Never below the current maximum — that would hand out a key
 * that already exists.
 *
 * Oracle cannot assign a sequence a value directly, so each one is stepped by
 * the required delta and then put back on INCREMENT BY 1.
 *
 * Reading a sequence means calling NEXTVAL, which consumes a value — so a run
 * of this script (--dry-run included) always burns one number per sequence.
 * Running it twice will therefore report a one-off drift the second time; that
 * is the measurement, not a failure to align.
 *
 *     php scripts/resync_sequences.php --dry-run    report only
 *     php scripts/resync_sequences.php              apply
 */

$base = __DIR__ . '/..';
require $base . '/vendor/autoload.php';
$app = require_once $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$dryRun = in_array('--dry-run', $argv, true);
$db = DB::connection('oracle');

$sequences = array_map(
    fn ($row) => array_values((array) $row)[0],
    $db->select("SELECT SEQUENCE_NAME FROM ALL_SEQUENCES WHERE SEQUENCE_OWNER = 'SDESK' AND SEQUENCE_NAME LIKE 'SEQ\\_%' ESCAPE '\\' ORDER BY 1")
);

printf("%-28s %8s %8s %8s\n", 'SEQUENCE', 'CURRENT', 'MAX(ID)', 'TARGET');
echo str_repeat('-', 56) . "\n";

foreach ($sequences as $sequence) {
    $table = strtolower(substr($sequence, 4));

    $exists = (int) array_values((array) $db->selectOne(
        "SELECT COUNT(*) AS C FROM ALL_TABLES WHERE OWNER = 'SDESK' AND TABLE_NAME = ?", [strtoupper($table)]
    ))[0];

    if ($exists === 0) {
        printf("%-28s %8s %8s %8s   (no matching table — skipped)\n", $sequence, '-', '-', '-');
        continue;
    }

    $max = (int) array_values((array) $db->selectOne("SELECT NVL(MAX(ID), 0) AS M FROM SDESK.\"" . strtoupper($table) . "\""))[0];
    $target = $max + 1;

    // NEXTVAL is the only way to read a sequence, and it consumes a value —
    // the delta below accounts for that.
    $current = (int) array_values((array) $db->selectOne("SELECT {$sequence}.NEXTVAL AS V FROM DUAL"))[0];
    $next = $current + 1;

    printf("%-28s %8d %8d %8d", $sequence, $next, $max, $target);

    if ($next === $target) {
        echo "   already aligned\n";
        continue;
    }

    if ($dryRun) {
        echo "   would move\n";
        continue;
    }

    $step = $target - $next;
    $db->statement("ALTER SEQUENCE {$sequence} INCREMENT BY {$step} MINVALUE 0");
    $db->selectOne("SELECT {$sequence}.NEXTVAL AS V FROM DUAL");
    $db->statement("ALTER SEQUENCE {$sequence} INCREMENT BY 1");

    echo "   moved\n";
}

echo "\n" . ($dryRun ? "Dry run — nothing was changed.\n" : "Done.\n");
