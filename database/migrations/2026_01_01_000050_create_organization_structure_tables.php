<?php

use App\Support\OracleSchema;
use Illuminate\Database\Migrations\Migration;

/**
 * Organisation structure foundation.
 *
 *   ORGANIZATIONS -> COMPANIES -> LOCATIONS -> DEPARTMENTS
 *
 * Every operational record is stamped with COMPANY_ID and LOCATION_ID; the
 * organisation is reached through the company. Users are granted access to any
 * number of organisations, companies and locations (see USER_* grant tables).
 */
return new class extends Migration
{
    public function up(): void
    {
        OracleSchema::run([
            // -------------------------------------------------------------- ORGANIZATIONS
            "CREATE TABLE ORGANIZATIONS (
                ID                  NUMBER(19)      NOT NULL,
                CODE                VARCHAR2(20)    NOT NULL,
                NAME                VARCHAR2(200)   NOT NULL,
                LEGAL_NAME          VARCHAR2(250),
                DESCRIPTION         VARCHAR2(500),
                REGISTRATION_NO     VARCHAR2(60),
                TAX_NO              VARCHAR2(60),
                ADDRESS             VARCHAR2(500),
                CITY                VARCHAR2(120),
                COUNTRY             VARCHAR2(120),
                EMAIL               VARCHAR2(150),
                PHONE               VARCHAR2(30),
                WEBSITE             VARCHAR2(200),
                LOGO_PATH           VARCHAR2(255),
                TIMEZONE            VARCHAR2(60)    DEFAULT 'UTC' NOT NULL,
                IS_ACTIVE           NUMBER(1)       DEFAULT 1 NOT NULL,
                SORT_ORDER          NUMBER(5)       DEFAULT 0 NOT NULL,
                CREATED_AT          TIMESTAMP,
                UPDATED_AT          TIMESTAMP,
                CONSTRAINT PK_ORGANIZATIONS         PRIMARY KEY (ID),
                CONSTRAINT UQ_ORGANIZATIONS_CODE    UNIQUE (CODE),
                CONSTRAINT CK_ORGANIZATIONS_ACTIVE  CHECK (IS_ACTIVE IN (0,1))
            )",

            // ------------------------------------------------------------------ COMPANIES
            "CREATE TABLE COMPANIES (
                ID                  NUMBER(19)      NOT NULL,
                ORGANIZATION_ID     NUMBER(19)      NOT NULL,
                CODE                VARCHAR2(20)    NOT NULL,
                NAME                VARCHAR2(200)   NOT NULL,
                LEGAL_NAME          VARCHAR2(250),
                REGISTRATION_NO     VARCHAR2(60),
                TAX_NO              VARCHAR2(60),
                ADDRESS             VARCHAR2(500),
                CITY                VARCHAR2(120),
                COUNTRY             VARCHAR2(120),
                EMAIL               VARCHAR2(150),
                PHONE               VARCHAR2(30),
                WEBSITE             VARCHAR2(200),
                LOGO_PATH           VARCHAR2(255),
                TIMEZONE            VARCHAR2(60)    DEFAULT 'UTC' NOT NULL,
                CURRENCY_CODE       VARCHAR2(3)     DEFAULT 'USD' NOT NULL,
                TICKET_PREFIX       VARCHAR2(10)    DEFAULT 'IT' NOT NULL,
                IS_ACTIVE           NUMBER(1)       DEFAULT 1 NOT NULL,
                SORT_ORDER          NUMBER(5)       DEFAULT 0 NOT NULL,
                CREATED_AT          TIMESTAMP,
                UPDATED_AT          TIMESTAMP,
                CONSTRAINT PK_COMPANIES         PRIMARY KEY (ID),
                CONSTRAINT UQ_COMPANIES_CODE    UNIQUE (ORGANIZATION_ID, CODE),
                CONSTRAINT CK_COMPANIES_ACTIVE  CHECK (IS_ACTIVE IN (0,1)),
                CONSTRAINT FK_COMPANIES_ORG     FOREIGN KEY (ORGANIZATION_ID)
                    REFERENCES ORGANIZATIONS (ID)
            )",

            // ------------------------------------------------------------------ LOCATIONS
            "CREATE TABLE LOCATIONS (
                ID                  NUMBER(19)      NOT NULL,
                COMPANY_ID          NUMBER(19)      NOT NULL,
                CODE                VARCHAR2(20)    NOT NULL,
                NAME                VARCHAR2(200)   NOT NULL,
                LOCATION_TYPE       VARCHAR2(30)    DEFAULT 'BRANCH' NOT NULL,
                ADDRESS             VARCHAR2(500),
                CITY                VARCHAR2(120),
                STATE               VARCHAR2(120),
                COUNTRY             VARCHAR2(120),
                POSTAL_CODE         VARCHAR2(20),
                EMAIL               VARCHAR2(150),
                PHONE               VARCHAR2(30),
                TIMEZONE            VARCHAR2(60)    DEFAULT 'UTC' NOT NULL,
                MANAGER_USER_ID     NUMBER(19),
                IS_HEAD_OFFICE      NUMBER(1)       DEFAULT 0 NOT NULL,
                IS_ACTIVE           NUMBER(1)       DEFAULT 1 NOT NULL,
                SORT_ORDER          NUMBER(5)       DEFAULT 0 NOT NULL,
                CREATED_AT          TIMESTAMP,
                UPDATED_AT          TIMESTAMP,
                CONSTRAINT PK_LOCATIONS         PRIMARY KEY (ID),
                CONSTRAINT UQ_LOCATIONS_CODE    UNIQUE (COMPANY_ID, CODE),
                CONSTRAINT CK_LOCATIONS_ACTIVE  CHECK (IS_ACTIVE IN (0,1)),
                CONSTRAINT CK_LOCATIONS_TYPE    CHECK (LOCATION_TYPE IN
                    ('HEAD_OFFICE','BRANCH','PLANT','WAREHOUSE','OFFICE','REMOTE','OTHER')),
                CONSTRAINT FK_LOCATIONS_COMPANY FOREIGN KEY (COMPANY_ID)
                    REFERENCES COMPANIES (ID)
            )",

            'CREATE INDEX IX_COMPANIES_ORG     ON COMPANIES (ORGANIZATION_ID)',
            'CREATE INDEX IX_COMPANIES_ACTIVE  ON COMPANIES (IS_ACTIVE)',
            'CREATE INDEX IX_LOCATIONS_COMPANY ON LOCATIONS (COMPANY_ID)',
            'CREATE INDEX IX_LOCATIONS_ACTIVE  ON LOCATIONS (IS_ACTIVE)',
            'CREATE INDEX IX_LOCATIONS_MANAGER ON LOCATIONS (MANAGER_USER_ID)',
        ]);

        OracleSchema::createSequenceWithTrigger('ORGANIZATIONS', 'SEQ_ORGANIZATIONS');
        OracleSchema::createSequenceWithTrigger('COMPANIES', 'SEQ_COMPANIES');
        OracleSchema::createSequenceWithTrigger('LOCATIONS', 'SEQ_LOCATIONS');
    }

    public function down(): void
    {
        OracleSchema::dropTables([
            'LOCATIONS'     => 'SEQ_LOCATIONS',
            'COMPANIES'     => 'SEQ_COMPANIES',
            'ORGANIZATIONS' => 'SEQ_ORGANIZATIONS',
        ]);
    }
};
