<?php

use App\Support\OracleSchema;
use Illuminate\Database\Migrations\Migration;

/**
 * The IT board: iterations, plus the two columns that place a piece of work on it.
 *
 * Everything here is additive. SPRINTS is new, and the columns added to TICKETS
 * and DEVELOPMENT_REQUESTS are nullable with no default, so every existing row
 * reads as "in the backlog, unranked" and nothing that runs today changes
 * behaviour until the board is opened.
 *
 * BOARD_RANK is a sparse fraction rather than a position: dropping a card between
 * two others stores the midpoint of their ranks, so a drag writes one row instead
 * of renumbering the column. BoardService::rebalance renumbers in steps of 1000
 * when neighbouring ranks converge.
 */
return new class extends Migration
{
    public function up(): void
    {
        OracleSchema::run([
            "CREATE TABLE SPRINTS (
                ID              NUMBER(19)      NOT NULL,
                COMPANY_ID      NUMBER(19)      NOT NULL,
                NAME            VARCHAR2(100)   NOT NULL,
                CADENCE         VARCHAR2(10)    NOT NULL,
                STARTS_ON       DATE            NOT NULL,
                ENDS_ON         DATE            NOT NULL,
                STATE           VARCHAR2(10)    DEFAULT 'PLANNED' NOT NULL,
                GOAL            VARCHAR2(500),
                CLOSED_AT       TIMESTAMP,
                CLOSED_BY       NUMBER(19),
                CREATED_BY      NUMBER(19),
                UPDATED_BY      NUMBER(19),
                CREATED_AT      TIMESTAMP,
                UPDATED_AT      TIMESTAMP,
                CONSTRAINT PK_SPRINTS           PRIMARY KEY (ID),
                CONSTRAINT UQ_SPRINTS_NAME      UNIQUE (COMPANY_ID, NAME),
                CONSTRAINT CK_SPRINTS_CADENCE   CHECK (CADENCE IN ('WEEKLY','MONTHLY')),
                CONSTRAINT CK_SPRINTS_STATE     CHECK (STATE IN ('PLANNED','ACTIVE','CLOSED')),
                CONSTRAINT CK_SPRINTS_DATES     CHECK (ENDS_ON >= STARTS_ON),
                CONSTRAINT FK_SPRINTS_COMPANY   FOREIGN KEY (COMPANY_ID)
                    REFERENCES COMPANIES (ID),
                CONSTRAINT FK_SPRINTS_CLOSER    FOREIGN KEY (CLOSED_BY)
                    REFERENCES USERS (ID),
                CONSTRAINT FK_SPRINTS_CREATOR   FOREIGN KEY (CREATED_BY)
                    REFERENCES USERS (ID)
            )",

            'CREATE INDEX IX_SPRINTS_CO_STATE ON SPRINTS (COMPANY_ID, STATE)',
            'CREATE INDEX IX_SPRINTS_DATES    ON SPRINTS (STARTS_ON, ENDS_ON)',
        ]);

        OracleSchema::createSequenceWithTrigger('SPRINTS', 'SEQ_SPRINTS');

        // Placement on the board. Re-runnable: ORA-01430 (column already exists)
        // is swallowed, so a half-applied migration can simply be run again.
        OracleSchema::runIgnoringExisting([
            'ALTER TABLE TICKETS ADD (SPRINT_ID NUMBER(19), BOARD_RANK NUMBER(18,6))',
            'ALTER TABLE DEVELOPMENT_REQUESTS ADD (SPRINT_ID NUMBER(19), BOARD_RANK NUMBER(18,6))',

            'ALTER TABLE TICKETS ADD CONSTRAINT FK_TICKETS_SPRINT
                FOREIGN KEY (SPRINT_ID) REFERENCES SPRINTS (ID)',
            'ALTER TABLE DEVELOPMENT_REQUESTS ADD CONSTRAINT FK_DEV_REQ_SPRINT
                FOREIGN KEY (SPRINT_ID) REFERENCES SPRINTS (ID)',

            // Serves the board (one sprint, ordered within a column) and the
            // backlog (SPRINT_ID IS NULL for a company).
            'CREATE INDEX IX_TICKETS_BOARD ON TICKETS (COMPANY_ID, SPRINT_ID, STATUS_ID, BOARD_RANK)',
            'CREATE INDEX IX_DEV_REQ_BOARD ON DEVELOPMENT_REQUESTS (COMPANY_ID, SPRINT_ID, BOARD_RANK)',
        ]);
    }

    public function down(): void
    {
        OracleSchema::dropQuietly('DROP INDEX IX_TICKETS_BOARD');
        OracleSchema::dropQuietly('DROP INDEX IX_DEV_REQ_BOARD');

        OracleSchema::dropQuietly('ALTER TABLE TICKETS DROP CONSTRAINT FK_TICKETS_SPRINT');
        OracleSchema::dropQuietly('ALTER TABLE DEVELOPMENT_REQUESTS DROP CONSTRAINT FK_DEV_REQ_SPRINT');

        OracleSchema::dropQuietly('ALTER TABLE TICKETS DROP (SPRINT_ID, BOARD_RANK)');
        OracleSchema::dropQuietly('ALTER TABLE DEVELOPMENT_REQUESTS DROP (SPRINT_ID, BOARD_RANK)');

        OracleSchema::dropTable('SPRINTS', 'SEQ_SPRINTS');
    }
};
