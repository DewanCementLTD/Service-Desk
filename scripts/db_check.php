<?php
/**
 * Quick Oracle connectivity check for the SDESK schema.
 * Usage: php scripts/db_check.php
 */

use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$row = DB::selectOne("select user as db_user, sys_context('userenv','con_name') as con_name from dual");
echo "Connected as : {$row->db_user}\n";
echo "Container    : {$row->con_name}\n";

$counts = DB::selectOne('select
        (select count(*) from user_tables) as tables,
        (select count(*) from user_sequences) as sequences,
        (select count(*) from user_views) as views,
        (select count(*) from user_triggers) as triggers,
        (select count(*) from user_indexes) as indexes
    from dual');

foreach ((array) $counts as $key => $value) {
    printf("%-12s : %s\n", strtoupper($key), $value);
}
