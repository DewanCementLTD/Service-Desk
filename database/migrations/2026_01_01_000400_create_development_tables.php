<?php

use App\Support\OracleSchema;
use Illuminate\Database\Migrations\Migration;

/**
 * Software development request module.
 */
return new class extends Migration
{
    public function up(): void
    {
        OracleSchema::run([
            // -------------------------------------------------------- DEVELOPMENT_REQUESTS
            "CREATE TABLE DEVELOPMENT_REQUESTS (
                ID                  NUMBER(19)      NOT NULL,
                REQUEST_NO          VARCHAR2(30)    NOT NULL,
                COMPANY_ID          NUMBER(19)      NOT NULL,
                LOCATION_ID         NUMBER(19),
                TITLE               VARCHAR2(255)   NOT NULL,
                BUSINESS_PURPOSE    CLOB,
                REQUIREMENTS        CLOB,
                TECHNICAL_NOTES     CLOB,
                EXPECTED_BENEFIT    CLOB,
                REQUESTER_ID        NUMBER(19)      NOT NULL,
                DEPARTMENT_ID       NUMBER(19),
                PRIORITY_ID         NUMBER(19),
                DEVELOPMENT_STATUS_ID NUMBER(19)    NOT NULL,
                ASSIGNED_TO         NUMBER(19),
                ASSIGNED_BY         NUMBER(19),
                ASSIGNED_AT         TIMESTAMP,
                TICKET_ID           NUMBER(19),
                TARGET_DATE         DATE,
                STARTED_AT          TIMESTAMP,
                DUE_AT              TIMESTAMP,
                COMPLETED_AT        TIMESTAMP,
                RELEASED_AT         TIMESTAMP,
                PROGRESS_PERCENT    NUMBER(3)       DEFAULT 0 NOT NULL,
                ESTIMATED_HOURS     NUMBER(8,2),
                ACTUAL_HOURS        NUMBER(8,2),
                APPROVAL_STATUS     VARCHAR2(20)    DEFAULT 'PENDING' NOT NULL,
                APPROVED_BY         NUMBER(19),
                APPROVED_AT         TIMESTAMP,
                REJECTION_REASON    VARCHAR2(1000),
                IS_OVERDUE          NUMBER(1)       DEFAULT 0 NOT NULL,
                CREATED_BY          NUMBER(19),
                UPDATED_BY          NUMBER(19),
                CREATED_AT          TIMESTAMP,
                UPDATED_AT          TIMESTAMP,
                CONSTRAINT PK_DEVELOPMENT_REQUESTS      PRIMARY KEY (ID),
                CONSTRAINT UQ_DEV_REQUESTS_NO           UNIQUE (REQUEST_NO),
                CONSTRAINT CK_DEV_APPROVAL_STATUS       CHECK (APPROVAL_STATUS IN
                    ('PENDING','APPROVED','REJECTED','ON_HOLD','CANCELLED')),
                CONSTRAINT CK_DEV_PROGRESS              CHECK (PROGRESS_PERCENT BETWEEN 0 AND 100),
                CONSTRAINT FK_DEV_COMPANY               FOREIGN KEY (COMPANY_ID)
                    REFERENCES COMPANIES (ID),
                CONSTRAINT FK_DEV_LOCATION              FOREIGN KEY (LOCATION_ID)
                    REFERENCES LOCATIONS (ID),
                CONSTRAINT FK_DEV_REQUESTER             FOREIGN KEY (REQUESTER_ID)
                    REFERENCES USERS (ID),
                CONSTRAINT FK_DEV_DEPARTMENT            FOREIGN KEY (DEPARTMENT_ID)
                    REFERENCES DEPARTMENTS (ID),
                CONSTRAINT FK_DEV_PRIORITY              FOREIGN KEY (PRIORITY_ID)
                    REFERENCES TICKET_PRIORITIES (ID),
                CONSTRAINT FK_DEV_STATUS                FOREIGN KEY (DEVELOPMENT_STATUS_ID)
                    REFERENCES DEVELOPMENT_STATUSES (ID),
                CONSTRAINT FK_DEV_ASSIGNEE              FOREIGN KEY (ASSIGNED_TO)
                    REFERENCES USERS (ID),
                CONSTRAINT FK_DEV_APPROVER              FOREIGN KEY (APPROVED_BY)
                    REFERENCES USERS (ID),
                CONSTRAINT FK_DEV_TICKET                FOREIGN KEY (TICKET_ID)
                    REFERENCES TICKETS (ID)
            )",

            // --------------------------------------------------------- DEVELOPMENT_UPDATES
            "CREATE TABLE DEVELOPMENT_UPDATES (
                ID                  NUMBER(19)      NOT NULL,
                REQUEST_ID          NUMBER(19)      NOT NULL,
                USER_ID             NUMBER(19)      NOT NULL,
                NOTES               CLOB            NOT NULL,
                PROGRESS_PERCENT    NUMBER(3),
                HOURS_SPENT         NUMBER(8,2),
                STATUS_ID           NUMBER(19),
                CREATED_AT          TIMESTAMP,
                UPDATED_AT          TIMESTAMP,
                CONSTRAINT PK_DEVELOPMENT_UPDATES   PRIMARY KEY (ID),
                CONSTRAINT CK_DEV_UPD_PROGRESS      CHECK (PROGRESS_PERCENT BETWEEN 0 AND 100),
                CONSTRAINT FK_DEV_UPD_REQUEST       FOREIGN KEY (REQUEST_ID)
                    REFERENCES DEVELOPMENT_REQUESTS (ID) ON DELETE CASCADE,
                CONSTRAINT FK_DEV_UPD_USER          FOREIGN KEY (USER_ID)
                    REFERENCES USERS (ID),
                CONSTRAINT FK_DEV_UPD_STATUS        FOREIGN KEY (STATUS_ID)
                    REFERENCES DEVELOPMENT_STATUSES (ID)
            )",

            'CREATE INDEX IX_DEV_COMPANY     ON DEVELOPMENT_REQUESTS (COMPANY_ID)',
            'CREATE INDEX IX_DEV_LOCATION    ON DEVELOPMENT_REQUESTS (LOCATION_ID)',
            'CREATE INDEX IX_DEV_STATUS      ON DEVELOPMENT_REQUESTS (DEVELOPMENT_STATUS_ID)',
            'CREATE INDEX IX_DEV_REQUESTER   ON DEVELOPMENT_REQUESTS (REQUESTER_ID)',
            'CREATE INDEX IX_DEV_ASSIGNED_TO ON DEVELOPMENT_REQUESTS (ASSIGNED_TO)',
            'CREATE INDEX IX_DEV_DEPARTMENT  ON DEVELOPMENT_REQUESTS (DEPARTMENT_ID)',
            'CREATE INDEX IX_DEV_DUE_AT      ON DEVELOPMENT_REQUESTS (DUE_AT)',
            'CREATE INDEX IX_DEV_CREATED_AT  ON DEVELOPMENT_REQUESTS (CREATED_AT)',
            'CREATE INDEX IX_DEV_APPROVAL    ON DEVELOPMENT_REQUESTS (APPROVAL_STATUS)',
            'CREATE INDEX IX_DEV_UPD_REQUEST ON DEVELOPMENT_UPDATES (REQUEST_ID, CREATED_AT)',
        ]);

        OracleSchema::createSequenceWithTrigger('DEVELOPMENT_REQUESTS', 'SEQ_DEVELOPMENT_REQUESTS', 'ID', 100001);
        OracleSchema::createSequenceWithTrigger('DEVELOPMENT_UPDATES', 'SEQ_DEVELOPMENT_UPDATES');
    }

    public function down(): void
    {
        OracleSchema::dropTables([
            'DEVELOPMENT_UPDATES'  => 'SEQ_DEVELOPMENT_UPDATES',
            'DEVELOPMENT_REQUESTS' => 'SEQ_DEVELOPMENT_REQUESTS',
        ]);
    }
};
