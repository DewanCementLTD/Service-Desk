<?php

use App\Support\OracleSchema;
use Illuminate\Database\Migrations\Migration;

/**
 * Document numbers are allocated per company (see PKG_DOC_NUMBER), so their
 * uniqueness constraints must be scoped the same way. A global unique index made
 * two companies collide the moment they shared a module prefix.
 */
return new class extends Migration
{
    /** table => [constraint name, number column] */
    private const TARGETS = [
        'TICKETS'              => ['UQ_TICKETS_TICKET_NO',   'TICKET_NO'],
        'DEVELOPMENT_REQUESTS' => ['UQ_DEV_REQUESTS_NO',     'REQUEST_NO'],
        'COMPLIANCE_TASKS'     => ['UQ_COMPLIANCE_TASK_NO',  'TASK_NO'],
        'TASKS'                => ['UQ_TASKS_TASK_NO',       'TASK_NO'],
        'ASSETS'               => ['UQ_ASSETS_ASSET_NO',     'ASSET_NO'],
    ];

    public function up(): void
    {
        foreach (self::TARGETS as $table => [$constraint, $column]) {
            // Dropping the constraint also drops its supporting unique index.
            OracleSchema::dropQuietly("ALTER TABLE {$table} DROP CONSTRAINT {$constraint}");

            OracleSchema::run([
                "ALTER TABLE {$table} ADD CONSTRAINT {$constraint}
                    UNIQUE (COMPANY_ID, {$column})",
            ]);

            // The number alone is still the main lookup key in the UI, so keep a
            // non-unique index on it for direct searches.
            OracleSchema::runIgnoringExisting([
                "CREATE INDEX IX_{$table}_NO ON {$table} ({$column})",
            ]);
        }
    }

    public function down(): void
    {
        foreach (self::TARGETS as $table => [$constraint, $column]) {
            OracleSchema::dropQuietly("DROP INDEX IX_{$table}_NO");
            OracleSchema::dropQuietly("ALTER TABLE {$table} DROP CONSTRAINT {$constraint}");

            OracleSchema::run([
                "ALTER TABLE {$table} ADD CONSTRAINT {$constraint} UNIQUE ({$column})",
            ]);
        }
    }
};
