<?php

use App\Support\OracleSchema;
use Illuminate\Database\Migrations\Migration;

/**
 * Management task module: assignable work items tracked on the management dashboard.
 */
return new class extends Migration
{
    public function up(): void
    {
        OracleSchema::run([
            // ---------------------------------------------------------------------- TASKS
            "CREATE TABLE TASKS (
                ID                  NUMBER(19)      NOT NULL,
                TASK_NO             VARCHAR2(30)    NOT NULL,
                COMPANY_ID          NUMBER(19)      NOT NULL,
                LOCATION_ID         NUMBER(19),
                TITLE               VARCHAR2(255)   NOT NULL,
                DESCRIPTION         CLOB,
                TASK_STATUS_ID      NUMBER(19)      NOT NULL,
                PRIORITY_ID         NUMBER(19),
                PARENT_TASK_ID      NUMBER(19),
                DEPARTMENT_ID       NUMBER(19),
                ASSIGNED_TO         NUMBER(19),
                ASSIGNED_BY         NUMBER(19),
                ASSIGNED_AT         TIMESTAMP,
                DUE_AT              TIMESTAMP,
                STARTED_AT          TIMESTAMP,
                COMPLETED_AT        TIMESTAMP,
                COMPLETED_BY        NUMBER(19),
                PROGRESS_PERCENT    NUMBER(3)       DEFAULT 0 NOT NULL,
                ESTIMATED_HOURS     NUMBER(8,2),
                ACTUAL_HOURS        NUMBER(8,2),
                IS_OVERDUE          NUMBER(1)       DEFAULT 0 NOT NULL,
                REMARKS             CLOB,
                CREATED_BY          NUMBER(19),
                UPDATED_BY          NUMBER(19),
                CREATED_AT          TIMESTAMP,
                UPDATED_AT          TIMESTAMP,
                CONSTRAINT PK_TASKS             PRIMARY KEY (ID),
                CONSTRAINT UQ_TASKS_TASK_NO     UNIQUE (TASK_NO),
                CONSTRAINT CK_TASKS_PROGRESS    CHECK (PROGRESS_PERCENT BETWEEN 0 AND 100),
                CONSTRAINT CK_TASKS_OVERDUE     CHECK (IS_OVERDUE IN (0,1)),
                CONSTRAINT FK_TASKS_COMPANY     FOREIGN KEY (COMPANY_ID)
                    REFERENCES COMPANIES (ID),
                CONSTRAINT FK_TASKS_LOCATION    FOREIGN KEY (LOCATION_ID)
                    REFERENCES LOCATIONS (ID),
                CONSTRAINT FK_TASKS_STATUS      FOREIGN KEY (TASK_STATUS_ID)
                    REFERENCES TASK_STATUSES (ID),
                CONSTRAINT FK_TASKS_PRIORITY    FOREIGN KEY (PRIORITY_ID)
                    REFERENCES TICKET_PRIORITIES (ID),
                CONSTRAINT FK_TASKS_PARENT      FOREIGN KEY (PARENT_TASK_ID)
                    REFERENCES TASKS (ID),
                CONSTRAINT FK_TASKS_DEPARTMENT  FOREIGN KEY (DEPARTMENT_ID)
                    REFERENCES DEPARTMENTS (ID),
                CONSTRAINT FK_TASKS_ASSIGNEE    FOREIGN KEY (ASSIGNED_TO)
                    REFERENCES USERS (ID),
                CONSTRAINT FK_TASKS_ASSIGNER    FOREIGN KEY (ASSIGNED_BY)
                    REFERENCES USERS (ID),
                CONSTRAINT FK_TASKS_COMPLETER   FOREIGN KEY (COMPLETED_BY)
                    REFERENCES USERS (ID)
            )",

            // ------------------------------------------------------------- TASK_COMMENTS
            "CREATE TABLE TASK_COMMENTS (
                ID              NUMBER(19)      NOT NULL,
                TASK_ID         NUMBER(19)      NOT NULL,
                USER_ID         NUMBER(19)      NOT NULL,
                BODY            CLOB            NOT NULL,
                PROGRESS_PERCENT NUMBER(3),
                CREATED_AT      TIMESTAMP,
                UPDATED_AT      TIMESTAMP,
                CONSTRAINT PK_TASK_COMMENTS     PRIMARY KEY (ID),
                CONSTRAINT FK_TASK_COMMENTS_TASK FOREIGN KEY (TASK_ID)
                    REFERENCES TASKS (ID) ON DELETE CASCADE,
                CONSTRAINT FK_TASK_COMMENTS_USER FOREIGN KEY (USER_ID)
                    REFERENCES USERS (ID)
            )",

            'CREATE INDEX IX_TASKS_COMPANY     ON TASKS (COMPANY_ID)',
            'CREATE INDEX IX_TASKS_LOCATION    ON TASKS (LOCATION_ID)',
            'CREATE INDEX IX_TASKS_STATUS      ON TASKS (TASK_STATUS_ID)',
            'CREATE INDEX IX_TASKS_ASSIGNED_TO ON TASKS (ASSIGNED_TO)',
            'CREATE INDEX IX_TASKS_DEPARTMENT  ON TASKS (DEPARTMENT_ID)',
            'CREATE INDEX IX_TASKS_DUE_AT      ON TASKS (DUE_AT)',
            'CREATE INDEX IX_TASKS_CREATED_AT  ON TASKS (CREATED_AT)',
            'CREATE INDEX IX_TASKS_PARENT      ON TASKS (PARENT_TASK_ID)',
            'CREATE INDEX IX_TASK_COMMENTS_TASK ON TASK_COMMENTS (TASK_ID, CREATED_AT)',
        ]);

        OracleSchema::createSequenceWithTrigger('TASKS', 'SEQ_TASKS', 'ID', 100001);
        OracleSchema::createSequenceWithTrigger('TASK_COMMENTS', 'SEQ_TASK_COMMENTS');
    }

    public function down(): void
    {
        OracleSchema::dropTables([
            'TASK_COMMENTS' => 'SEQ_TASK_COMMENTS',
            'TASKS'         => 'SEQ_TASKS',
        ]);
    }
};
