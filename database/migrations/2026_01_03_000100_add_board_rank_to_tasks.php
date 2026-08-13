<?php

use App\Support\OracleSchema;
use Illuminate\Database\Migrations\Migration;

/**
 * Lets Task cards live on the same board as tickets (see BoardService).
 *
 * Tasks don't take part in sprints — unlike TICKETS/DEVELOPMENT_REQUESTS (see
 * 2026_01_02_000100_create_board_tables.php) there is no SPRINT_ID column here,
 * only BOARD_RANK for ordering cards within a column. Nullable with no default,
 * so every existing row reads as "unranked" and nothing changes until the board
 * is opened.
 */
return new class extends Migration
{
    public function up(): void
    {
        OracleSchema::runIgnoringExisting([
            'ALTER TABLE TASKS ADD (BOARD_RANK NUMBER(18,6))',

            // Serves the board: one company, grouped by status, ordered within it.
            'CREATE INDEX IX_TASKS_BOARD ON TASKS (COMPANY_ID, TASK_STATUS_ID, BOARD_RANK)',
        ]);
    }

    public function down(): void
    {
        OracleSchema::dropQuietly('DROP INDEX IX_TASKS_BOARD');
        OracleSchema::dropQuietly('ALTER TABLE TASKS DROP (BOARD_RANK)');
    }
};
