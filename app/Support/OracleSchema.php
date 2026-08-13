<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Thin helper around raw Oracle DDL.
 *
 * Laravel's schema builder cannot express everything this application needs from
 * Oracle (sequences, identity triggers, CLOB defaults, check constraints, package
 * bodies), so migrations issue controlled Oracle SQL through these helpers instead
 * of forcing portable-but-wrong schema syntax.
 */
class OracleSchema
{
    /** Oracle "object does not exist" style errors that are safe to ignore on drop. */
    private const IGNORABLE_DROP_CODES = [
        942,    // table or view does not exist
        2289,   // sequence does not exist
        4080,   // trigger does not exist
        1418,   // index does not exist
        1432,   // public synonym to be dropped does not exist
        4043,   // object does not exist
    ];

    /**
     * Execute a list of DDL statements in order. Each statement is sent separately
     * because Oracle does not accept multi-statement batches over OCI8.
     *
     * @param  array<int, string>  $statements
     */
    public static function run(array $statements): void
    {
        foreach ($statements as $statement) {
            $sql = trim($statement);

            if ($sql === '') {
                continue;
            }

            DB::connection('oracle')->statement($sql);
        }
    }

    /**
     * Execute DDL, swallowing "already exists" errors so a partially applied
     * migration can be re-run without manual cleanup.
     *
     * @param  array<int, string>  $statements
     */
    public static function runIgnoringExisting(array $statements): void
    {
        foreach ($statements as $statement) {
            $sql = trim($statement);

            if ($sql === '') {
                continue;
            }

            try {
                DB::connection('oracle')->statement($sql);
            } catch (\Throwable $e) {
                if (! self::isAlreadyExists($e)) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Create a sequence plus a BEFORE INSERT trigger that populates the primary key
     * when the application does not supply one. Oracle has no AUTO_INCREMENT, and a
     * trigger keeps Eloquent's "insert then read back the id" flow working through
     * the RETURNING clause.
     */
    public static function createSequenceWithTrigger(
        string $table,
        string $sequence,
        string $pkColumn = 'ID',
        int $startWith = 1
    ): void {
        self::runIgnoringExisting([
            "CREATE SEQUENCE {$sequence} START WITH {$startWith} INCREMENT BY 1 NOCACHE NOCYCLE",
        ]);

        $trigger = self::triggerName($table);

        self::run([
            "CREATE OR REPLACE TRIGGER {$trigger}
             BEFORE INSERT ON {$table}
             FOR EACH ROW
             WHEN (NEW.{$pkColumn} IS NULL)
             BEGIN
                 :NEW.{$pkColumn} := {$sequence}.NEXTVAL;
             END;",
        ]);
    }

    /** Drop a table (with its constraints) and its sequence, ignoring absence. */
    public static function dropTable(string $table, ?string $sequence = null): void
    {
        self::dropQuietly("DROP TABLE {$table} CASCADE CONSTRAINTS PURGE");

        if ($sequence !== null) {
            self::dropQuietly("DROP SEQUENCE {$sequence}");
        }
    }

    /**
     * Drop several tables. Order matters: pass children before parents.
     *
     * @param  array<string, string|null>  $tables  table name => sequence name (or null)
     */
    public static function dropTables(array $tables): void
    {
        foreach ($tables as $table => $sequence) {
            self::dropTable($table, $sequence);
        }
    }

    public static function dropView(string $view): void
    {
        self::dropQuietly("DROP VIEW {$view}");
    }

    public static function dropQuietly(string $sql): void
    {
        try {
            DB::connection('oracle')->statement($sql);
        } catch (\Throwable $e) {
            if (! self::isIgnorableDrop($e)) {
                throw $e;
            }
        }
    }

    public static function tableExists(string $table): bool
    {
        return DB::connection('oracle')->selectOne(
            'select count(*) as c from user_tables where table_name = ?',
            [strtoupper($table)]
        )->c > 0;
    }

    public static function triggerName(string $table): string
    {
        // Oracle 19c allows 128-byte identifiers, but keep names short and predictable.
        return 'TRG_' . strtoupper($table) . '_BI';
    }

    private static function isAlreadyExists(\Throwable $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'ORA-00955')   // name is already used
            || str_contains($message, 'ORA-01920')   // user name conflicts
            || str_contains($message, 'ORA-02260')   // table can have only one primary key
            || str_contains($message, 'ORA-01430')   // column being added already exists
            || str_contains($message, 'ORA-02275');  // referential constraint already exists
    }

    private static function isIgnorableDrop(\Throwable $e): bool
    {
        $message = $e->getMessage();

        foreach (self::IGNORABLE_DROP_CODES as $code) {
            if (str_contains($message, 'ORA-' . str_pad((string) $code, 5, '0', STR_PAD_LEFT))) {
                return true;
            }
        }

        return false;
    }
}
