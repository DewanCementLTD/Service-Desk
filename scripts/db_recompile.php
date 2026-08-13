<?php
/**
 * Recompiles any SDESK object left INVALID after DDL changes.
 *
 * Oracle marks dependent views and packages invalid when their base tables are
 * altered, and only recompiles them lazily on next use. This makes that explicit
 * and surfaces any genuine compilation error.
 *
 * Usage: php scripts/db_recompile.php
 */

use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$invalid = DB::connection('oracle')->select(
    "select object_name, object_type from user_objects where status <> 'VALID' order by object_type"
);

if ($invalid === []) {
    echo "All objects already VALID.\n";
    exit(0);
}

foreach ($invalid as $object) {
    $row = array_change_key_case((array) $object, CASE_LOWER);
    echo "Recompiling {$row['object_type']} {$row['object_name']}\n";

    // PACKAGE BODY needs the two-word form; user_objects already reports it that way.
    DB::connection('oracle')->statement("ALTER {$row['object_type']} {$row['object_name']} COMPILE");
}

$errors = DB::connection('oracle')->select(
    'select name, line, text from user_errors order by name, sequence'
);

echo "\n";

if ($errors === []) {
    echo "No compilation errors.\n";
} else {
    foreach ($errors as $error) {
        $row = array_change_key_case((array) $error, CASE_LOWER);
        echo "  {$row['name']} line {$row['line']}: {$row['text']}";
    }
}

$remaining = DB::connection('oracle')->select(
    "select count(*) as c from user_objects where status <> 'VALID'"
);

// reset() needs a real variable, not an inline cast.
$row   = (array) $remaining[0];
$count = (int) reset($row);

echo $count === 0 ? "All objects VALID.\n" : "{$count} object(s) still invalid.\n";

exit($count === 0 ? 0 : 1);
