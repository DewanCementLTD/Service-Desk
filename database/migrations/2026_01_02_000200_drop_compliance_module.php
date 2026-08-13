<?php

use App\Support\OracleSchema;
use Illuminate\Database\Migrations\Migration;

/**
 * Removes the compliance register entirely.
 *
 * Requested outright: the business runs employee-to-IT support directly, with no
 * separate compliance workflow in between. This is destructive — the one pilot
 * obligation already raised (see the BRD's rollout position) is dropped with the
 * table. down() recreates the schema shape from the original migrations so a
 * rollback restores the structure, but not the data that was in it.
 */
return new class extends Migration
{
    public function up(): void
    {
        OracleSchema::dropView('VW_COMPLIANCE_SUMMARY');

        OracleSchema::dropTables([
            'COMPLIANCE_UPDATES'    => 'SEQ_COMPLIANCE_UPDATES',
            'COMPLIANCE_TASKS'      => 'SEQ_COMPLIANCE_TASKS',
            'COMPLIANCE_CATEGORIES' => 'SEQ_COMPLIANCE_CATEGORIES',
            'COMPLIANCE_STATUSES'   => 'SEQ_COMPLIANCE_STATUSES',
        ]);
    }

    public function down(): void
    {
        OracleSchema::run([
            "CREATE TABLE COMPLIANCE_STATUSES (
                ID                  NUMBER(19)      NOT NULL,
                CODE                VARCHAR2(40)    NOT NULL,
                NAME                VARCHAR2(100)   NOT NULL,
                DESCRIPTION         VARCHAR2(255),
                COLOR               VARCHAR2(20),
                IS_COMPLETED_STATE  NUMBER(1)       DEFAULT 0 NOT NULL,
                IS_ACTIVE           NUMBER(1)       DEFAULT 1 NOT NULL,
                SORT_ORDER          NUMBER(5)       DEFAULT 0 NOT NULL,
                CREATED_AT          TIMESTAMP,
                UPDATED_AT          TIMESTAMP,
                CONSTRAINT PK_COMPLIANCE_STATUSES       PRIMARY KEY (ID),
                CONSTRAINT UQ_COMPLIANCE_STATUSES_CODE  UNIQUE (CODE),
                CONSTRAINT CK_COMPLIANCE_STATUSES_ACT   CHECK (IS_ACTIVE IN (0,1))
            )",

            "CREATE TABLE COMPLIANCE_CATEGORIES (
                ID                  NUMBER(19)      NOT NULL,
                CODE                VARCHAR2(40)    NOT NULL,
                NAME                VARCHAR2(100)   NOT NULL,
                REGULATION_REF      VARCHAR2(200),
                IS_ACTIVE           NUMBER(1)       DEFAULT 1 NOT NULL,
                SORT_ORDER          NUMBER(5)       DEFAULT 0 NOT NULL,
                CREATED_AT          TIMESTAMP,
                UPDATED_AT          TIMESTAMP,
                CONSTRAINT PK_COMPLIANCE_CATEGORIES         PRIMARY KEY (ID),
                CONSTRAINT UQ_COMPLIANCE_CATEGORIES_CODE    UNIQUE (CODE),
                CONSTRAINT CK_COMPLIANCE_CATEGORIES_ACT     CHECK (IS_ACTIVE IN (0,1))
            )",

            "CREATE TABLE COMPLIANCE_TASKS (
                ID                  NUMBER(19)      NOT NULL,
                TASK_NO             VARCHAR2(30)    NOT NULL,
                COMPANY_ID          NUMBER(19)      NOT NULL,
                LOCATION_ID         NUMBER(19),
                TITLE               VARCHAR2(255)   NOT NULL,
                DESCRIPTION         CLOB,
                CATEGORY_ID         NUMBER(19),
                COMPLIANCE_STATUS_ID NUMBER(19)     NOT NULL,
                RISK_LEVEL          VARCHAR2(20)    DEFAULT 'MEDIUM' NOT NULL,
                REGULATION_REF      VARCHAR2(200),
                ASSIGNED_TO         NUMBER(19),
                ASSIGNED_BY         NUMBER(19),
                ASSIGNED_AT         TIMESTAMP,
                DEPARTMENT_ID       NUMBER(19),
                REVIEWER_ID         NUMBER(19),
                DUE_AT              TIMESTAMP,
                STARTED_AT          TIMESTAMP,
                COMPLETED_AT        TIMESTAMP,
                COMPLETED_BY        NUMBER(19),
                VERIFIED_AT         TIMESTAMP,
                VERIFIED_BY         NUMBER(19),
                PROGRESS_PERCENT    NUMBER(3)       DEFAULT 0 NOT NULL,
                FINDINGS            CLOB,
                REMEDIATION         CLOB,
                EVIDENCE_PATH       VARCHAR2(500),
                IS_RECURRING        NUMBER(1)       DEFAULT 0 NOT NULL,
                RECURRENCE          VARCHAR2(20),
                NEXT_DUE_AT         TIMESTAMP,
                IS_OVERDUE          NUMBER(1)       DEFAULT 0 NOT NULL,
                CREATED_BY          NUMBER(19),
                UPDATED_BY          NUMBER(19),
                CREATED_AT          TIMESTAMP,
                UPDATED_AT          TIMESTAMP,
                CONSTRAINT PK_COMPLIANCE_TASKS      PRIMARY KEY (ID),
                CONSTRAINT UQ_COMPLIANCE_TASK_NO    UNIQUE (TASK_NO),
                CONSTRAINT CK_COMPLIANCE_RISK       CHECK (RISK_LEVEL IN
                    ('LOW','MEDIUM','HIGH','CRITICAL')),
                CONSTRAINT CK_COMPLIANCE_RECURRENCE CHECK (RECURRENCE IN
                    ('DAILY','WEEKLY','MONTHLY','QUARTERLY','HALF_YEARLY','YEARLY')),
                CONSTRAINT CK_COMPLIANCE_PROGRESS   CHECK (PROGRESS_PERCENT BETWEEN 0 AND 100),
                CONSTRAINT FK_COMPLIANCE_COMPANY    FOREIGN KEY (COMPANY_ID)
                    REFERENCES COMPANIES (ID),
                CONSTRAINT FK_COMPLIANCE_LOCATION   FOREIGN KEY (LOCATION_ID)
                    REFERENCES LOCATIONS (ID),
                CONSTRAINT FK_COMPLIANCE_CATEGORY   FOREIGN KEY (CATEGORY_ID)
                    REFERENCES COMPLIANCE_CATEGORIES (ID),
                CONSTRAINT FK_COMPLIANCE_STATUS     FOREIGN KEY (COMPLIANCE_STATUS_ID)
                    REFERENCES COMPLIANCE_STATUSES (ID),
                CONSTRAINT FK_COMPLIANCE_ASSIGNEE   FOREIGN KEY (ASSIGNED_TO)
                    REFERENCES USERS (ID),
                CONSTRAINT FK_COMPLIANCE_DEPARTMENT FOREIGN KEY (DEPARTMENT_ID)
                    REFERENCES DEPARTMENTS (ID),
                CONSTRAINT FK_COMPLIANCE_REVIEWER   FOREIGN KEY (REVIEWER_ID)
                    REFERENCES USERS (ID),
                CONSTRAINT FK_COMPLIANCE_COMPLETER  FOREIGN KEY (COMPLETED_BY)
                    REFERENCES USERS (ID),
                CONSTRAINT FK_COMPLIANCE_VERIFIER   FOREIGN KEY (VERIFIED_BY)
                    REFERENCES USERS (ID)
            )",

            "CREATE TABLE COMPLIANCE_UPDATES (
                ID                  NUMBER(19)      NOT NULL,
                COMPLIANCE_TASK_ID  NUMBER(19)      NOT NULL,
                USER_ID             NUMBER(19)      NOT NULL,
                NOTES               CLOB            NOT NULL,
                PROGRESS_PERCENT    NUMBER(3),
                STATUS_ID           NUMBER(19),
                CREATED_AT          TIMESTAMP,
                UPDATED_AT          TIMESTAMP,
                CONSTRAINT PK_COMPLIANCE_UPDATES    PRIMARY KEY (ID),
                CONSTRAINT FK_COMP_UPD_TASK         FOREIGN KEY (COMPLIANCE_TASK_ID)
                    REFERENCES COMPLIANCE_TASKS (ID) ON DELETE CASCADE,
                CONSTRAINT FK_COMP_UPD_USER         FOREIGN KEY (USER_ID)
                    REFERENCES USERS (ID),
                CONSTRAINT FK_COMP_UPD_STATUS       FOREIGN KEY (STATUS_ID)
                    REFERENCES COMPLIANCE_STATUSES (ID)
            )",

            'CREATE INDEX IX_COMPLIANCE_COMPANY   ON COMPLIANCE_TASKS (COMPANY_ID)',
            'CREATE INDEX IX_COMPLIANCE_LOCATION  ON COMPLIANCE_TASKS (LOCATION_ID)',
            'CREATE INDEX IX_COMPLIANCE_STATUS    ON COMPLIANCE_TASKS (COMPLIANCE_STATUS_ID)',
            'CREATE INDEX IX_COMPLIANCE_ASSIGNEE  ON COMPLIANCE_TASKS (ASSIGNED_TO)',
            'CREATE INDEX IX_COMPLIANCE_DUE_AT    ON COMPLIANCE_TASKS (DUE_AT)',
            'CREATE INDEX IX_COMPLIANCE_RISK      ON COMPLIANCE_TASKS (RISK_LEVEL)',
            'CREATE INDEX IX_COMPLIANCE_CATEGORY  ON COMPLIANCE_TASKS (CATEGORY_ID)',
            'CREATE INDEX IX_COMPLIANCE_CREATED   ON COMPLIANCE_TASKS (CREATED_AT)',
            'CREATE INDEX IX_COMP_UPD_TASK        ON COMPLIANCE_UPDATES (COMPLIANCE_TASK_ID, CREATED_AT)',
        ]);

        OracleSchema::createSequenceWithTrigger('COMPLIANCE_STATUSES', 'SEQ_COMPLIANCE_STATUSES');
        OracleSchema::createSequenceWithTrigger('COMPLIANCE_CATEGORIES', 'SEQ_COMPLIANCE_CATEGORIES');
        OracleSchema::createSequenceWithTrigger('COMPLIANCE_TASKS', 'SEQ_COMPLIANCE_TASKS', 'ID', 100001);
        OracleSchema::createSequenceWithTrigger('COMPLIANCE_UPDATES', 'SEQ_COMPLIANCE_UPDATES');

        OracleSchema::run([
            "CREATE OR REPLACE VIEW VW_COMPLIANCE_SUMMARY AS
             SELECT
                 c.ORGANIZATION_ID,
                 ct.COMPANY_ID,
                 ct.LOCATION_ID,
                 COUNT(*)                                                        AS TOTAL_TASKS,
                 SUM(CASE WHEN cs.IS_COMPLETED_STATE = 1 THEN 1 ELSE 0 END)      AS COMPLETED_TASKS,
                 SUM(CASE WHEN cs.CODE = 'PENDING'     THEN 1 ELSE 0 END)        AS PENDING_TASKS,
                 SUM(CASE WHEN cs.CODE = 'IN_PROGRESS' THEN 1 ELSE 0 END)        AS IN_PROGRESS_TASKS,
                 SUM(CASE WHEN cs.IS_COMPLETED_STATE = 0
                           AND ct.DUE_AT IS NOT NULL
                           AND ct.DUE_AT < LOCALTIMESTAMP THEN 1 ELSE 0 END)       AS OVERDUE_TASKS,
                 SUM(CASE WHEN ct.RISK_LEVEL = 'HIGH'     THEN 1 ELSE 0 END)     AS HIGH_RISK_TASKS,
                 SUM(CASE WHEN ct.RISK_LEVEL = 'CRITICAL' THEN 1 ELSE 0 END)     AS CRITICAL_RISK_TASKS,
                 ROUND(AVG(ct.PROGRESS_PERCENT), 2)                              AS AVG_PROGRESS
             FROM COMPLIANCE_TASKS ct
             JOIN COMPANIES c            ON c.ID = ct.COMPANY_ID
             JOIN COMPLIANCE_STATUSES cs ON cs.ID = ct.COMPLIANCE_STATUS_ID
             GROUP BY c.ORGANIZATION_ID, ct.COMPANY_ID, ct.LOCATION_ID",
        ]);
    }
};
