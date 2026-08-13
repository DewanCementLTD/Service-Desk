<?php

use App\Support\OracleSchema;
use Illuminate\Database\Migrations\Migration;

/**
 * Cross-cutting tables: approvals, notifications, audit trail and IT assets.
 */
return new class extends Migration
{
    public function up(): void
    {
        OracleSchema::run([
            // ------------------------------------------------------------------ APPROVALS
            // Polymorphic by design: one approval workflow serves tickets,
            // development requests, compliance tasks and management tasks.
            "CREATE TABLE APPROVALS (
                ID              NUMBER(19)      NOT NULL,
                COMPANY_ID      NUMBER(19)      NOT NULL,
                ENTITY_TYPE     VARCHAR2(50)    NOT NULL,
                ENTITY_ID       NUMBER(19)      NOT NULL,
                LEVEL_NO        NUMBER(2)       DEFAULT 1 NOT NULL,
                REQUESTED_BY    NUMBER(19)      NOT NULL,
                APPROVER_ID     NUMBER(19)      NOT NULL,
                STATUS          VARCHAR2(20)    DEFAULT 'PENDING' NOT NULL,
                REMARKS         CLOB,
                ACTED_AT        TIMESTAMP,
                DUE_AT          TIMESTAMP,
                CREATED_AT      TIMESTAMP,
                UPDATED_AT      TIMESTAMP,
                CONSTRAINT PK_APPROVALS         PRIMARY KEY (ID),
                CONSTRAINT CK_APPROVALS_STATUS  CHECK (STATUS IN
                    ('PENDING','APPROVED','REJECTED','CANCELLED','DELEGATED')),
                CONSTRAINT CK_APPROVALS_ENTITY  CHECK (ENTITY_TYPE IN
                    ('TICKET','DEVELOPMENT_REQUEST','COMPLIANCE_TASK','TASK','ASSET')),
                CONSTRAINT FK_APPROVALS_COMPANY FOREIGN KEY (COMPANY_ID)
                    REFERENCES COMPANIES (ID),
                CONSTRAINT FK_APPROVALS_REQUESTER FOREIGN KEY (REQUESTED_BY)
                    REFERENCES USERS (ID),
                CONSTRAINT FK_APPROVALS_APPROVER  FOREIGN KEY (APPROVER_ID)
                    REFERENCES USERS (ID)
            )",

            // -------------------------------------------------------------- NOTIFICATIONS
            "CREATE TABLE NOTIFICATIONS (
                ID              NUMBER(19)      NOT NULL,
                USER_ID         NUMBER(19)      NOT NULL,
                COMPANY_ID      NUMBER(19),
                TITLE           VARCHAR2(200)   NOT NULL,
                MESSAGE         CLOB,
                NOTIFY_TYPE     VARCHAR2(50)    DEFAULT 'INFO' NOT NULL,
                ENTITY_TYPE     VARCHAR2(50),
                ENTITY_ID       NUMBER(19),
                URL             VARCHAR2(255),
                IS_READ         NUMBER(1)       DEFAULT 0 NOT NULL,
                READ_AT         TIMESTAMP,
                CREATED_AT      TIMESTAMP,
                UPDATED_AT      TIMESTAMP,
                CONSTRAINT PK_NOTIFICATIONS         PRIMARY KEY (ID),
                CONSTRAINT CK_NOTIFICATIONS_READ    CHECK (IS_READ IN (0,1)),
                CONSTRAINT FK_NOTIFICATIONS_USER    FOREIGN KEY (USER_ID)
                    REFERENCES USERS (ID) ON DELETE CASCADE,
                CONSTRAINT FK_NOTIFICATIONS_COMPANY FOREIGN KEY (COMPANY_ID)
                    REFERENCES COMPANIES (ID)
            )",

            // ----------------------------------------------------------------- AUDIT_LOGS
            // OLD_VALUES / NEW_VALUES hold JSON snapshots; CLOB keeps them unbounded
            // and the IS JSON check constraint stops malformed payloads getting in.
            "CREATE TABLE AUDIT_LOGS (
                ID              NUMBER(19)      NOT NULL,
                USER_ID         NUMBER(19),
                COMPANY_ID      NUMBER(19),
                LOCATION_ID     NUMBER(19),
                ACTION          VARCHAR2(60)    NOT NULL,
                ENTITY_TYPE     VARCHAR2(60)    NOT NULL,
                ENTITY_ID       NUMBER(19),
                DESCRIPTION     VARCHAR2(1000),
                OLD_VALUES      CLOB,
                NEW_VALUES      CLOB,
                IP_ADDRESS      VARCHAR2(45),
                USER_AGENT      VARCHAR2(400),
                CREATED_AT      TIMESTAMP,
                CONSTRAINT PK_AUDIT_LOGS        PRIMARY KEY (ID),
                CONSTRAINT CK_AUDIT_OLD_JSON    CHECK (OLD_VALUES IS NULL OR OLD_VALUES IS JSON),
                CONSTRAINT CK_AUDIT_NEW_JSON    CHECK (NEW_VALUES IS NULL OR NEW_VALUES IS JSON),
                CONSTRAINT FK_AUDIT_USER        FOREIGN KEY (USER_ID)
                    REFERENCES USERS (ID),
                CONSTRAINT FK_AUDIT_COMPANY     FOREIGN KEY (COMPANY_ID)
                    REFERENCES COMPANIES (ID)
            )",

            // --------------------------------------------------------------------- ASSETS
            "CREATE TABLE ASSETS (
                ID              NUMBER(19)      NOT NULL,
                ASSET_NO        VARCHAR2(30)    NOT NULL,
                COMPANY_ID      NUMBER(19)      NOT NULL,
                LOCATION_ID     NUMBER(19),
                NAME            VARCHAR2(200)   NOT NULL,
                CATEGORY        VARCHAR2(60),
                SERIAL_NO       VARCHAR2(120),
                MODEL           VARCHAR2(120),
                MANUFACTURER    VARCHAR2(120),
                DEPARTMENT_ID   NUMBER(19),
                ASSIGNED_TO     NUMBER(19),
                ASSET_STATUS    VARCHAR2(30)    DEFAULT 'IN_USE' NOT NULL,
                PURCHASE_DATE   DATE,
                PURCHASE_COST   NUMBER(14,2),
                WARRANTY_END    DATE,
                PLACEMENT       VARCHAR2(150),
                NOTES           CLOB,
                CREATED_AT      TIMESTAMP,
                UPDATED_AT      TIMESTAMP,
                CONSTRAINT PK_ASSETS            PRIMARY KEY (ID),
                CONSTRAINT UQ_ASSETS_ASSET_NO   UNIQUE (ASSET_NO),
                CONSTRAINT CK_ASSETS_STATUS     CHECK (ASSET_STATUS IN
                    ('IN_USE','IN_STOCK','UNDER_REPAIR','RETIRED','LOST','DISPOSED')),
                CONSTRAINT FK_ASSETS_COMPANY    FOREIGN KEY (COMPANY_ID)
                    REFERENCES COMPANIES (ID),
                CONSTRAINT FK_ASSETS_LOCATION   FOREIGN KEY (LOCATION_ID)
                    REFERENCES LOCATIONS (ID),
                CONSTRAINT FK_ASSETS_DEPARTMENT FOREIGN KEY (DEPARTMENT_ID)
                    REFERENCES DEPARTMENTS (ID),
                CONSTRAINT FK_ASSETS_ASSIGNEE   FOREIGN KEY (ASSIGNED_TO)
                    REFERENCES USERS (ID)
            )",

            'CREATE INDEX IX_APPROVALS_ENTITY    ON APPROVALS (ENTITY_TYPE, ENTITY_ID)',
            'CREATE INDEX IX_APPROVALS_APPROVER  ON APPROVALS (APPROVER_ID, STATUS)',
            'CREATE INDEX IX_APPROVALS_COMPANY   ON APPROVALS (COMPANY_ID)',
            'CREATE INDEX IX_NOTIFICATIONS_USER  ON NOTIFICATIONS (USER_ID, IS_READ)',
            'CREATE INDEX IX_NOTIFICATIONS_DATE  ON NOTIFICATIONS (CREATED_AT)',
            'CREATE INDEX IX_AUDIT_ENTITY        ON AUDIT_LOGS (ENTITY_TYPE, ENTITY_ID)',
            'CREATE INDEX IX_AUDIT_USER          ON AUDIT_LOGS (USER_ID)',
            'CREATE INDEX IX_AUDIT_CREATED_AT    ON AUDIT_LOGS (CREATED_AT)',
            'CREATE INDEX IX_AUDIT_COMPANY       ON AUDIT_LOGS (COMPANY_ID)',
            'CREATE INDEX IX_ASSETS_COMPANY      ON ASSETS (COMPANY_ID)',
            'CREATE INDEX IX_ASSETS_LOCATION     ON ASSETS (LOCATION_ID)',
            'CREATE INDEX IX_ASSETS_ASSIGNED_TO  ON ASSETS (ASSIGNED_TO)',
            'CREATE INDEX IX_ASSETS_STATUS       ON ASSETS (ASSET_STATUS)',
        ]);

        foreach ([
            'APPROVALS'     => 'SEQ_APPROVALS',
            'NOTIFICATIONS' => 'SEQ_NOTIFICATIONS',
            'AUDIT_LOGS'    => 'SEQ_AUDIT_LOGS',
            'ASSETS'        => 'SEQ_ASSETS',
        ] as $table => $sequence) {
            OracleSchema::createSequenceWithTrigger($table, $sequence);
        }
    }

    public function down(): void
    {
        OracleSchema::dropTables([
            'ASSETS'        => 'SEQ_ASSETS',
            'AUDIT_LOGS'    => 'SEQ_AUDIT_LOGS',
            'NOTIFICATIONS' => 'SEQ_NOTIFICATIONS',
            'APPROVALS'     => 'SEQ_APPROVALS',
        ]);
    }
};
