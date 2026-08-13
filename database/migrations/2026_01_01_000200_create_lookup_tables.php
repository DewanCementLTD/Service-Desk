<?php

use App\Support\OracleSchema;
use Illuminate\Database\Migrations\Migration;

/**
 * Configurable master/lookup tables.
 *
 * Statuses, priorities, types and categories live in real tables rather than a
 * fixed column constraint so they can be administered from the application.
 */
return new class extends Migration
{
    public function up(): void
    {
        OracleSchema::run([
            // ----------------------------------------------------------- TICKET_STATUSES
            "CREATE TABLE TICKET_STATUSES (
                ID              NUMBER(19)      NOT NULL,
                CODE            VARCHAR2(40)    NOT NULL,
                NAME            VARCHAR2(120)   NOT NULL,
                DESCRIPTION     VARCHAR2(500),
                COLOR           VARCHAR2(20)    DEFAULT 'gray' NOT NULL,
                IS_OPEN_STATE   NUMBER(1)       DEFAULT 1 NOT NULL,
                IS_CLOSED_STATE NUMBER(1)       DEFAULT 0 NOT NULL,
                STOPS_SLA_CLOCK NUMBER(1)       DEFAULT 0 NOT NULL,
                IS_ACTIVE       NUMBER(1)       DEFAULT 1 NOT NULL,
                SORT_ORDER      NUMBER(5)       DEFAULT 0 NOT NULL,
                CREATED_AT      TIMESTAMP,
                UPDATED_AT      TIMESTAMP,
                CONSTRAINT PK_TICKET_STATUSES       PRIMARY KEY (ID),
                CONSTRAINT UQ_TICKET_STATUSES_CODE  UNIQUE (CODE),
                CONSTRAINT CK_TICKET_STATUSES_ACT   CHECK (IS_ACTIVE IN (0,1))
            )",

            // --------------------------------------------------------- TICKET_PRIORITIES
            "CREATE TABLE TICKET_PRIORITIES (
                ID                  NUMBER(19)      NOT NULL,
                CODE                VARCHAR2(40)    NOT NULL,
                NAME                VARCHAR2(120)   NOT NULL,
                DESCRIPTION         VARCHAR2(500),
                COLOR               VARCHAR2(20)    DEFAULT 'gray' NOT NULL,
                RESPONSE_HOURS      NUMBER(6)       DEFAULT 8 NOT NULL,
                RESOLUTION_HOURS    NUMBER(6)       DEFAULT 48 NOT NULL,
                IS_CRITICAL         NUMBER(1)       DEFAULT 0 NOT NULL,
                IS_ACTIVE           NUMBER(1)       DEFAULT 1 NOT NULL,
                SORT_ORDER          NUMBER(5)       DEFAULT 0 NOT NULL,
                CREATED_AT          TIMESTAMP,
                UPDATED_AT          TIMESTAMP,
                CONSTRAINT PK_TICKET_PRIORITIES         PRIMARY KEY (ID),
                CONSTRAINT UQ_TICKET_PRIORITIES_CODE    UNIQUE (CODE),
                CONSTRAINT CK_TICKET_PRIORITIES_ACT     CHECK (IS_ACTIVE IN (0,1)),
                CONSTRAINT CK_TICKET_PRIORITIES_HRS     CHECK (RESOLUTION_HOURS > 0)
            )",

            // ------------------------------------------------------------- TICKET_TYPES
            "CREATE TABLE TICKET_TYPES (
                ID              NUMBER(19)      NOT NULL,
                CODE            VARCHAR2(40)    NOT NULL,
                NAME            VARCHAR2(120)   NOT NULL,
                DESCRIPTION     VARCHAR2(500),
                IS_ACTIVE       NUMBER(1)       DEFAULT 1 NOT NULL,
                SORT_ORDER      NUMBER(5)       DEFAULT 0 NOT NULL,
                CREATED_AT      TIMESTAMP,
                UPDATED_AT      TIMESTAMP,
                CONSTRAINT PK_TICKET_TYPES      PRIMARY KEY (ID),
                CONSTRAINT UQ_TICKET_TYPES_CODE UNIQUE (CODE),
                CONSTRAINT CK_TICKET_TYPES_ACT  CHECK (IS_ACTIVE IN (0,1))
            )",

            // -------------------------------------------------------- TICKET_CATEGORIES
            "CREATE TABLE TICKET_CATEGORIES (
                ID                  NUMBER(19)      NOT NULL,
                TYPE_ID             NUMBER(19),
                CODE                VARCHAR2(40)    NOT NULL,
                NAME                VARCHAR2(120)   NOT NULL,
                DESCRIPTION         VARCHAR2(500),
                DEFAULT_TEAM_DEPT_ID NUMBER(19),
                IS_ACTIVE           NUMBER(1)       DEFAULT 1 NOT NULL,
                SORT_ORDER          NUMBER(5)       DEFAULT 0 NOT NULL,
                CREATED_AT          TIMESTAMP,
                UPDATED_AT          TIMESTAMP,
                CONSTRAINT PK_TICKET_CATEGORIES         PRIMARY KEY (ID),
                CONSTRAINT UQ_TICKET_CATEGORIES_CODE    UNIQUE (CODE),
                CONSTRAINT CK_TICKET_CATEGORIES_ACT     CHECK (IS_ACTIVE IN (0,1)),
                CONSTRAINT FK_TICKET_CAT_TYPE           FOREIGN KEY (TYPE_ID)
                    REFERENCES TICKET_TYPES (ID),
                CONSTRAINT FK_TICKET_CAT_DEPT           FOREIGN KEY (DEFAULT_TEAM_DEPT_ID)
                    REFERENCES DEPARTMENTS (ID)
            )",

            // ------------------------------------------------------------- TASK_STATUSES
            "CREATE TABLE TASK_STATUSES (
                ID              NUMBER(19)      NOT NULL,
                CODE            VARCHAR2(40)    NOT NULL,
                NAME            VARCHAR2(120)   NOT NULL,
                DESCRIPTION     VARCHAR2(500),
                COLOR           VARCHAR2(20)    DEFAULT 'gray' NOT NULL,
                IS_COMPLETED_STATE NUMBER(1)    DEFAULT 0 NOT NULL,
                IS_ACTIVE       NUMBER(1)       DEFAULT 1 NOT NULL,
                SORT_ORDER      NUMBER(5)       DEFAULT 0 NOT NULL,
                CREATED_AT      TIMESTAMP,
                UPDATED_AT      TIMESTAMP,
                CONSTRAINT PK_TASK_STATUSES         PRIMARY KEY (ID),
                CONSTRAINT UQ_TASK_STATUSES_CODE    UNIQUE (CODE),
                CONSTRAINT CK_TASK_STATUSES_ACT     CHECK (IS_ACTIVE IN (0,1))
            )",

            // ------------------------------------------------------ DEVELOPMENT_STATUSES
            "CREATE TABLE DEVELOPMENT_STATUSES (
                ID              NUMBER(19)      NOT NULL,
                CODE            VARCHAR2(40)    NOT NULL,
                NAME            VARCHAR2(120)   NOT NULL,
                DESCRIPTION     VARCHAR2(500),
                COLOR           VARCHAR2(20)    DEFAULT 'gray' NOT NULL,
                IS_COMPLETED_STATE NUMBER(1)    DEFAULT 0 NOT NULL,
                IS_ACTIVE       NUMBER(1)       DEFAULT 1 NOT NULL,
                SORT_ORDER      NUMBER(5)       DEFAULT 0 NOT NULL,
                CREATED_AT      TIMESTAMP,
                UPDATED_AT      TIMESTAMP,
                CONSTRAINT PK_DEVELOPMENT_STATUSES      PRIMARY KEY (ID),
                CONSTRAINT UQ_DEVELOPMENT_STATUSES_CODE UNIQUE (CODE),
                CONSTRAINT CK_DEVELOPMENT_STATUSES_ACT  CHECK (IS_ACTIVE IN (0,1))
            )",

            // ------------------------------------------------------- COMPLIANCE_STATUSES
            "CREATE TABLE COMPLIANCE_STATUSES (
                ID              NUMBER(19)      NOT NULL,
                CODE            VARCHAR2(40)    NOT NULL,
                NAME            VARCHAR2(120)   NOT NULL,
                DESCRIPTION     VARCHAR2(500),
                COLOR           VARCHAR2(20)    DEFAULT 'gray' NOT NULL,
                IS_COMPLETED_STATE NUMBER(1)    DEFAULT 0 NOT NULL,
                IS_ACTIVE       NUMBER(1)       DEFAULT 1 NOT NULL,
                SORT_ORDER      NUMBER(5)       DEFAULT 0 NOT NULL,
                CREATED_AT      TIMESTAMP,
                UPDATED_AT      TIMESTAMP,
                CONSTRAINT PK_COMPLIANCE_STATUSES       PRIMARY KEY (ID),
                CONSTRAINT UQ_COMPLIANCE_STATUSES_CODE  UNIQUE (CODE),
                CONSTRAINT CK_COMPLIANCE_STATUSES_ACT   CHECK (IS_ACTIVE IN (0,1))
            )",

            // ------------------------------------------------------ COMPLIANCE_CATEGORIES
            "CREATE TABLE COMPLIANCE_CATEGORIES (
                ID              NUMBER(19)      NOT NULL,
                CODE            VARCHAR2(40)    NOT NULL,
                NAME            VARCHAR2(120)   NOT NULL,
                DESCRIPTION     VARCHAR2(500),
                REGULATION_REF  VARCHAR2(150),
                IS_ACTIVE       NUMBER(1)       DEFAULT 1 NOT NULL,
                SORT_ORDER      NUMBER(5)       DEFAULT 0 NOT NULL,
                CREATED_AT      TIMESTAMP,
                UPDATED_AT      TIMESTAMP,
                CONSTRAINT PK_COMPLIANCE_CATEGORIES         PRIMARY KEY (ID),
                CONSTRAINT UQ_COMPLIANCE_CATEGORIES_CODE    UNIQUE (CODE),
                CONSTRAINT CK_COMPLIANCE_CATEGORIES_ACT     CHECK (IS_ACTIVE IN (0,1))
            )",

            // -------------------------------------------------------------- SLA_POLICIES
            "CREATE TABLE SLA_POLICIES (
                ID                  NUMBER(19)      NOT NULL,
                CODE                VARCHAR2(40)    NOT NULL,
                NAME                VARCHAR2(120)   NOT NULL,
                DESCRIPTION         VARCHAR2(500),
                COMPANY_ID          NUMBER(19),
                PRIORITY_ID         NUMBER(19),
                TYPE_ID             NUMBER(19),
                RESPONSE_HOURS      NUMBER(6)       NOT NULL,
                RESOLUTION_HOURS    NUMBER(6)       NOT NULL,
                IS_ACTIVE           NUMBER(1)       DEFAULT 1 NOT NULL,
                CREATED_AT          TIMESTAMP,
                UPDATED_AT          TIMESTAMP,
                CONSTRAINT PK_SLA_POLICIES      PRIMARY KEY (ID),
                CONSTRAINT UQ_SLA_POLICIES_CODE UNIQUE (CODE),
                CONSTRAINT FK_SLA_COMPANY       FOREIGN KEY (COMPANY_ID)
                    REFERENCES COMPANIES (ID),
                CONSTRAINT FK_SLA_PRIORITY      FOREIGN KEY (PRIORITY_ID)
                    REFERENCES TICKET_PRIORITIES (ID),
                CONSTRAINT FK_SLA_TYPE          FOREIGN KEY (TYPE_ID)
                    REFERENCES TICKET_TYPES (ID)
            )",

            // ------------------------------------------------------------- DOC_SEQUENCES
            // Backing store for human-readable document numbers (IT-2026-000001).
            // Kept separate from the surrogate-key sequences so numbering can restart
            // per company, per prefix and per year without touching primary keys.
            "CREATE TABLE DOC_SEQUENCES (
                COMPANY_ID  NUMBER(19)      NOT NULL,
                PREFIX      VARCHAR2(20)    NOT NULL,
                YEAR_NO     NUMBER(4)       NOT NULL,
                LAST_NO     NUMBER(10)      DEFAULT 0 NOT NULL,
                UPDATED_AT  TIMESTAMP,
                CONSTRAINT PK_DOC_SEQUENCES PRIMARY KEY (COMPANY_ID, PREFIX, YEAR_NO),
                CONSTRAINT FK_DOC_SEQ_COMPANY FOREIGN KEY (COMPANY_ID)
                    REFERENCES COMPANIES (ID)
            )",

            'CREATE INDEX IX_TICKET_CAT_TYPE ON TICKET_CATEGORIES (TYPE_ID)',
            'CREATE INDEX IX_SLA_PRIORITY    ON SLA_POLICIES (PRIORITY_ID)',
            'CREATE INDEX IX_SLA_COMPANY     ON SLA_POLICIES (COMPANY_ID)',
        ]);

        foreach ([
            'TICKET_STATUSES'       => 'SEQ_TICKET_STATUSES',
            'TICKET_PRIORITIES'     => 'SEQ_TICKET_PRIORITIES',
            'TICKET_TYPES'          => 'SEQ_TICKET_TYPES',
            'TICKET_CATEGORIES'     => 'SEQ_TICKET_CATEGORIES',
            'TASK_STATUSES'         => 'SEQ_TASK_STATUSES',
            'DEVELOPMENT_STATUSES'  => 'SEQ_DEVELOPMENT_STATUSES',
            'COMPLIANCE_STATUSES'   => 'SEQ_COMPLIANCE_STATUSES',
            'COMPLIANCE_CATEGORIES' => 'SEQ_COMPLIANCE_CATEGORIES',
            'SLA_POLICIES'          => 'SEQ_SLA_POLICIES',
        ] as $table => $sequence) {
            OracleSchema::createSequenceWithTrigger($table, $sequence);
        }
    }

    public function down(): void
    {
        OracleSchema::dropTables([
            'DOC_SEQUENCES'         => null,
            'SLA_POLICIES'          => 'SEQ_SLA_POLICIES',
            'COMPLIANCE_CATEGORIES' => 'SEQ_COMPLIANCE_CATEGORIES',
            'COMPLIANCE_STATUSES'   => 'SEQ_COMPLIANCE_STATUSES',
            'DEVELOPMENT_STATUSES'  => 'SEQ_DEVELOPMENT_STATUSES',
            'TASK_STATUSES'         => 'SEQ_TASK_STATUSES',
            'TICKET_CATEGORIES'     => 'SEQ_TICKET_CATEGORIES',
            'TICKET_TYPES'          => 'SEQ_TICKET_TYPES',
            'TICKET_PRIORITIES'     => 'SEQ_TICKET_PRIORITIES',
            'TICKET_STATUSES'       => 'SEQ_TICKET_STATUSES',
        ]);
    }
};
