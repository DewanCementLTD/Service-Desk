<?php

use App\Support\OracleSchema;
use Illuminate\Database\Migrations\Migration;

/**
 * Align the database with the application's wall clock (UTC+5 by default).
 *
 * The reporting views originally compared deadlines against SYSTIMESTAMP, which
 * is the *database server's* OS clock and ignores the session timezone. Laravel
 * writes timestamps using the application timezone, so the two only agreed by
 * accident. The views are rebuilt against LOCALTIMESTAMP / CURRENT_DATE, which
 * honour the session timezone pinned in config/database.php.
 *
 * The TIMEZONE columns on the organisation tree also default to the application
 * timezone rather than UTC.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Views use CREATE OR REPLACE, so re-running their definition is safe and
        // keeps a single source of truth for the SQL.
        $this->reportingViews()->up();

        $timezone = config('app.timezone', 'Asia/Karachi');

        foreach (['ORGANIZATIONS', 'COMPANIES', 'LOCATIONS'] as $table) {
            // Changes the default for future rows only; existing rows are updated
            // below so the seeded structure reflects the new default too.
            OracleSchema::run([
                "ALTER TABLE {$table} MODIFY (TIMEZONE VARCHAR2(60) DEFAULT '{$timezone}')",
            ]);

            \Illuminate\Support\Facades\DB::connection('oracle')
                ->table(strtolower($table))
                ->where('timezone', 'UTC')
                ->update(['timezone' => $timezone]);
        }
    }

    public function down(): void
    {
        foreach (['ORGANIZATIONS', 'COMPANIES', 'LOCATIONS'] as $table) {
            OracleSchema::run([
                "ALTER TABLE {$table} MODIFY (TIMEZONE VARCHAR2(60) DEFAULT 'UTC')",
            ]);
        }
    }

    /** The reporting-view migration, reused so the SQL is not duplicated. */
    private function reportingViews(): Migration
    {
        return require database_path('migrations/2026_01_01_000800_create_reporting_views.php');
    }
};
