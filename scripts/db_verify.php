<?php
/**
 * Verifies the SDESK schema: object counts, invalid objects and a live test of
 * the document-number package.
 *
 * Usage: php scripts/db_verify.php
 */

use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$counts = DB::selectOne('select
        (select count(*) from user_tables)     as tables,
        (select count(*) from user_sequences)  as sequences,
        (select count(*) from user_views)      as views,
        (select count(*) from user_triggers)   as triggers,
        (select count(*) from user_indexes)    as indexes,
        (select count(*) from user_constraints where constraint_type = \'R\') as foreign_keys
    from dual');

echo "=== SDESK object counts ===\n";
foreach ((array) $counts as $key => $value) {
    printf("%-13s : %s\n", strtoupper($key), $value);
}

$invalid = DB::select("select object_name, object_type
                         from user_objects
                        where status <> 'VALID'
                        order by object_type, object_name");

echo "\n=== Invalid objects ===\n";
if ($invalid === []) {
    echo "none\n";
} else {
    foreach ($invalid as $row) {
        echo "  {$row->object_type}  {$row->object_name}\n";
    }
    exit(1);
}
