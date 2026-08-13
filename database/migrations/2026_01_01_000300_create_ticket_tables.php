<?php

use App\Support\OracleSchema;
use Illuminate\Database\Migrations\Migration;

/**
 * Service desk ticket module: tickets, comments, history and attachments.
 */
return new class extends Migration
{
    public function up(): void
    {
        OracleSchema::run([
            // -------------------------------------------------------------------- TICKETS
            "CREATE TABLE TICKETS (
                ID                  NUMBER(19)      NOT NULL,
                TICKET_NO           VARCHAR2(30)    NOT NULL,
                COMPANY_ID          NUMBER(19)      NOT NULL,
                LOCATION_ID         NUMBER(19),
                SUBJECT             VARCHAR2(255)   NOT NULL,
                DESCRIPTION         CLOB,
                TYPE_ID             NUMBER(19),
                CATEGORY_ID         NUMBER(19),
                PRIORITY_ID         NUMBER(19)      NOT NULL,
                STATUS_ID           NUMBER(19)      NOT NULL,
                REQUESTER_ID        NUMBER(19)      NOT NULL,
                DEPARTMENT_ID       NUMBER(19),
                ASSIGNED_TO         NUMBER(19),
                ASSIGNED_BY         NUMBER(19),
                ASSIGNED_AT         TIMESTAMP,
                DUE_AT              TIMESTAMP,
                FIRST_RESPONSE_AT   TIMESTAMP,
                RESOLVED_AT         TIMESTAMP,
                RESOLVED_BY         NUMBER(19),
                CLOSED_AT           TIMESTAMP,
                CLOSED_BY           NUMBER(19),
                RESOLUTION          CLOB,
                SLA_STATUS          VARCHAR2(20)    DEFAULT 'ON_TRACK' NOT NULL,
                SLA_RESPONSE_DUE_AT TIMESTAMP,
                SLA_BREACH_AT       TIMESTAMP,
                IS_OVERDUE          NUMBER(1)       DEFAULT 0 NOT NULL,
                REOPEN_COUNT        NUMBER(3)       DEFAULT 0 NOT NULL,
                SATISFACTION_RATING NUMBER(1),
                SATISFACTION_NOTE   VARCHAR2(500),
                SOURCE              VARCHAR2(20)    DEFAULT 'WEB' NOT NULL,
                CREATED_BY          NUMBER(19),
                UPDATED_BY          NUMBER(19),
                CREATED_AT          TIMESTAMP,
                UPDATED_AT          TIMESTAMP,
                CONSTRAINT PK_TICKETS           PRIMARY KEY (ID),
                CONSTRAINT UQ_TICKETS_TICKET_NO UNIQUE (TICKET_NO),
                CONSTRAINT CK_TICKETS_SLA_STATUS CHECK (SLA_STATUS IN
                    ('ON_TRACK','AT_RISK','BREACHED','MET','PAUSED')),
                CONSTRAINT CK_TICKETS_SOURCE    CHECK (SOURCE IN
                    ('WEB','EMAIL','PHONE','WALK_IN','MOBILE','SYSTEM')),
                CONSTRAINT CK_TICKETS_OVERDUE   CHECK (IS_OVERDUE IN (0,1)),
                CONSTRAINT CK_TICKETS_RATING    CHECK (SATISFACTION_RATING BETWEEN 1 AND 5),
                CONSTRAINT FK_TICKETS_COMPANY   FOREIGN KEY (COMPANY_ID)
                    REFERENCES COMPANIES (ID),
                CONSTRAINT FK_TICKETS_LOCATION  FOREIGN KEY (LOCATION_ID)
                    REFERENCES LOCATIONS (ID),
                CONSTRAINT FK_TICKETS_TYPE      FOREIGN KEY (TYPE_ID)
                    REFERENCES TICKET_TYPES (ID),
                CONSTRAINT FK_TICKETS_CATEGORY  FOREIGN KEY (CATEGORY_ID)
                    REFERENCES TICKET_CATEGORIES (ID),
                CONSTRAINT FK_TICKETS_PRIORITY  FOREIGN KEY (PRIORITY_ID)
                    REFERENCES TICKET_PRIORITIES (ID),
                CONSTRAINT FK_TICKETS_STATUS    FOREIGN KEY (STATUS_ID)
                    REFERENCES TICKET_STATUSES (ID),
                CONSTRAINT FK_TICKETS_REQUESTER FOREIGN KEY (REQUESTER_ID)
                    REFERENCES USERS (ID),
                CONSTRAINT FK_TICKETS_DEPARTMENT FOREIGN KEY (DEPARTMENT_ID)
                    REFERENCES DEPARTMENTS (ID),
                CONSTRAINT FK_TICKETS_ASSIGNEE  FOREIGN KEY (ASSIGNED_TO)
                    REFERENCES USERS (ID),
                CONSTRAINT FK_TICKETS_ASSIGNER  FOREIGN KEY (ASSIGNED_BY)
                    REFERENCES USERS (ID),
                CONSTRAINT FK_TICKETS_RESOLVER  FOREIGN KEY (RESOLVED_BY)
                    REFERENCES USERS (ID),
                CONSTRAINT FK_TICKETS_CLOSER    FOREIGN KEY (CLOSED_BY)
                    REFERENCES USERS (ID),
                CONSTRAINT FK_TICKETS_CREATOR   FOREIGN KEY (CREATED_BY)
                    REFERENCES USERS (ID)
            )",

            // ------------------------------------------------------------ TICKET_COMMENTS
            "CREATE TABLE TICKET_COMMENTS (
                ID              NUMBER(19)      NOT NULL,
                TICKET_ID       NUMBER(19)      NOT NULL,
                USER_ID         NUMBER(19)      NOT NULL,
                BODY            CLOB            NOT NULL,
                IS_INTERNAL     NUMBER(1)       DEFAULT 0 NOT NULL,
                IS_SYSTEM       NUMBER(1)       DEFAULT 0 NOT NULL,
                CREATED_AT      TIMESTAMP,
                UPDATED_AT      TIMESTAMP,
                CONSTRAINT PK_TICKET_COMMENTS       PRIMARY KEY (ID),
                CONSTRAINT CK_TICKET_COMMENTS_INT   CHECK (IS_INTERNAL IN (0,1)),
                CONSTRAINT FK_TICKET_COMMENTS_TKT   FOREIGN KEY (TICKET_ID)
                    REFERENCES TICKETS (ID) ON DELETE CASCADE,
                CONSTRAINT FK_TICKET_COMMENTS_USER  FOREIGN KEY (USER_ID)
                    REFERENCES USERS (ID)
            )",

            // ------------------------------------------------------------- TICKET_HISTORY
            "CREATE TABLE TICKET_HISTORY (
                ID              NUMBER(19)      NOT NULL,
                TICKET_ID       NUMBER(19)      NOT NULL,
                USER_ID         NUMBER(19),
                ACTION          VARCHAR2(50)    NOT NULL,
                FIELD_NAME      VARCHAR2(60),
                OLD_VALUE       VARCHAR2(1000),
                NEW_VALUE       VARCHAR2(1000),
                NOTES           CLOB,
                CREATED_AT      TIMESTAMP,
                CONSTRAINT PK_TICKET_HISTORY        PRIMARY KEY (ID),
                CONSTRAINT FK_TICKET_HISTORY_TKT    FOREIGN KEY (TICKET_ID)
                    REFERENCES TICKETS (ID) ON DELETE CASCADE,
                CONSTRAINT FK_TICKET_HISTORY_USER   FOREIGN KEY (USER_ID)
                    REFERENCES USERS (ID)
            )",

            // --------------------------------------------------------- TICKET_ATTACHMENTS
            // Files live on the Laravel disk; only metadata is kept in Oracle.
            "CREATE TABLE TICKET_ATTACHMENTS (
                ID              NUMBER(19)      NOT NULL,
                TICKET_ID       NUMBER(19)      NOT NULL,
                COMMENT_ID      NUMBER(19),
                UPLOADED_BY     NUMBER(19)      NOT NULL,
                FILE_NAME       VARCHAR2(255)   NOT NULL,
                FILE_PATH       VARCHAR2(500)   NOT NULL,
                MIME_TYPE       VARCHAR2(120),
                FILE_SIZE       NUMBER(19),
                CREATED_AT      TIMESTAMP,
                UPDATED_AT      TIMESTAMP,
                CONSTRAINT PK_TICKET_ATTACHMENTS        PRIMARY KEY (ID),
                CONSTRAINT FK_TICKET_ATTACH_TKT         FOREIGN KEY (TICKET_ID)
                    REFERENCES TICKETS (ID) ON DELETE CASCADE,
                CONSTRAINT FK_TICKET_ATTACH_COMMENT     FOREIGN KEY (COMMENT_ID)
                    REFERENCES TICKET_COMMENTS (ID) ON DELETE CASCADE,
                CONSTRAINT FK_TICKET_ATTACH_USER        FOREIGN KEY (UPLOADED_BY)
                    REFERENCES USERS (ID)
            )",

            // -------------------------------------------------------------------- INDEXES
            // Chosen to serve the dashboard/report filters rather than every column.
            'CREATE INDEX IX_TICKETS_COMPANY     ON TICKETS (COMPANY_ID)',
            'CREATE INDEX IX_TICKETS_LOCATION    ON TICKETS (LOCATION_ID)',
            'CREATE INDEX IX_TICKETS_STATUS      ON TICKETS (STATUS_ID)',
            'CREATE INDEX IX_TICKETS_PRIORITY    ON TICKETS (PRIORITY_ID)',
            'CREATE INDEX IX_TICKETS_REQUESTER   ON TICKETS (REQUESTER_ID)',
            'CREATE INDEX IX_TICKETS_ASSIGNED_TO ON TICKETS (ASSIGNED_TO)',
            'CREATE INDEX IX_TICKETS_DEPARTMENT  ON TICKETS (DEPARTMENT_ID)',
            'CREATE INDEX IX_TICKETS_CATEGORY    ON TICKETS (CATEGORY_ID)',
            'CREATE INDEX IX_TICKETS_CREATED_AT  ON TICKETS (CREATED_AT)',
            'CREATE INDEX IX_TICKETS_DUE_AT      ON TICKETS (DUE_AT)',
            'CREATE INDEX IX_TICKETS_SLA_STATUS  ON TICKETS (SLA_STATUS)',
            // Composite index for the most common dashboard slice.
            'CREATE INDEX IX_TICKETS_CO_STAT_DT  ON TICKETS (COMPANY_ID, STATUS_ID, CREATED_AT)',
            'CREATE INDEX IX_TICKET_COMMENTS_TKT ON TICKET_COMMENTS (TICKET_ID)',
            'CREATE INDEX IX_TICKET_HISTORY_TKT  ON TICKET_HISTORY (TICKET_ID, CREATED_AT)',
            'CREATE INDEX IX_TICKET_ATTACH_TKT   ON TICKET_ATTACHMENTS (TICKET_ID)',
        ]);

        foreach ([
            'TICKETS'            => 'SEQ_TICKETS',
            'TICKET_COMMENTS'    => 'SEQ_TICKET_COMMENTS',
            'TICKET_HISTORY'     => 'SEQ_TICKET_HISTORY',
            'TICKET_ATTACHMENTS' => 'SEQ_TICKET_ATTACHMENTS',
        ] as $table => $sequence) {
            OracleSchema::createSequenceWithTrigger($table, $sequence, 'ID', $table === 'TICKETS' ? 100001 : 1);
        }
    }

    public function down(): void
    {
        OracleSchema::dropTables([
            'TICKET_ATTACHMENTS' => 'SEQ_TICKET_ATTACHMENTS',
            'TICKET_HISTORY'     => 'SEQ_TICKET_HISTORY',
            'TICKET_COMMENTS'    => 'SEQ_TICKET_COMMENTS',
            'TICKETS'            => 'SEQ_TICKETS',
        ]);
    }
};
