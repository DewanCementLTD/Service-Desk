<?php

use App\Support\OracleSchema;
use Illuminate\Database\Migrations\Migration;

/**
 * Add support for multiple task assignees (up to 3 per task).
 *
 * Tasks can now be assigned to multiple users. The TASK_ASSIGNEES table
 * tracks all assignees, while ASSIGNED_TO on tasks is kept for backward
 * compatibility (primary assignee or NULL if using multi-assign).
 */
return new class extends Migration
{
    public function up(): void
    {
        OracleSchema::run([
            // --------------------------------------------------------- TASK_ASSIGNEES
            "CREATE TABLE TASK_ASSIGNEES (
                ID              NUMBER(19)      NOT NULL,
                TASK_ID         NUMBER(19)      NOT NULL,
                USER_ID         NUMBER(19)      NOT NULL,
                ASSIGNED_AT     TIMESTAMP,
                ASSIGNED_BY     NUMBER(19),
                SORT_ORDER      NUMBER(3)       DEFAULT 0 NOT NULL,
                CREATED_AT      TIMESTAMP,
                UPDATED_AT      TIMESTAMP,
                CONSTRAINT PK_TASK_ASSIGNEES        PRIMARY KEY (ID),
                CONSTRAINT UQ_TASK_ASSIGNEE         UNIQUE (TASK_ID, USER_ID),
                CONSTRAINT CK_TASK_ASSIGNEE_MAX     CHECK (SORT_ORDER <= 2),
                CONSTRAINT FK_TASK_ASSIGNEE_TASK    FOREIGN KEY (TASK_ID)
                    REFERENCES TASKS (ID) ON DELETE CASCADE,
                CONSTRAINT FK_TASK_ASSIGNEE_USER    FOREIGN KEY (USER_ID)
                    REFERENCES USERS (ID) ON DELETE CASCADE,
                CONSTRAINT FK_TASK_ASSIGNEE_ASSIGNER FOREIGN KEY (ASSIGNED_BY)
                    REFERENCES USERS (ID)
            )",

            // ---------------------------------------------------------------- INDEXES
            'CREATE INDEX IX_TASK_ASSIGNEE_TASK    ON TASK_ASSIGNEES (TASK_ID)',
            'CREATE INDEX IX_TASK_ASSIGNEE_USER    ON TASK_ASSIGNEES (USER_ID)',
            'CREATE INDEX IX_TASK_ASSIGNEE_ORDER   ON TASK_ASSIGNEES (SORT_ORDER)',
        ]);

        OracleSchema::createSequenceWithTrigger('TASK_ASSIGNEES', 'SEQ_TASK_ASSIGNEES');
    }

    public function down(): void
    {
        OracleSchema::dropTables([
            'TASK_ASSIGNEES' => 'SEQ_TASK_ASSIGNEES',
        ]);
    }
};
