<?php

use App\Support\OracleSchema;
use Illuminate\Database\Migrations\Migration;

/**
 * Core identity + organisation structure.
 *
 * Departments belong to a company. Users have one default company/location for
 * convenience, plus explicit grants in USER_COMPANIES / USER_LOCATIONS that
 * decide which companies and locations they may switch into and see data for.
 */
return new class extends Migration
{
    public function up(): void
    {
        OracleSchema::run([
            // ---------------------------------------------------------------- DEPARTMENTS
            "CREATE TABLE DEPARTMENTS (
                ID              NUMBER(19)      NOT NULL,
                COMPANY_ID      NUMBER(19)      NOT NULL,
                LOCATION_ID     NUMBER(19),
                CODE            VARCHAR2(30)    NOT NULL,
                NAME            VARCHAR2(150)   NOT NULL,
                DESCRIPTION     VARCHAR2(500),
                HEAD_USER_ID    NUMBER(19),
                EMAIL           VARCHAR2(150),
                PHONE           VARCHAR2(30),
                IS_IT_TEAM      NUMBER(1)       DEFAULT 0 NOT NULL,
                IS_ACTIVE       NUMBER(1)       DEFAULT 1 NOT NULL,
                SORT_ORDER      NUMBER(5)       DEFAULT 0 NOT NULL,
                CREATED_AT      TIMESTAMP,
                UPDATED_AT      TIMESTAMP,
                CONSTRAINT PK_DEPARTMENTS        PRIMARY KEY (ID),
                CONSTRAINT UQ_DEPARTMENTS_CODE   UNIQUE (COMPANY_ID, CODE),
                CONSTRAINT CK_DEPARTMENTS_ACTIVE CHECK (IS_ACTIVE IN (0,1)),
                CONSTRAINT FK_DEPARTMENTS_COMPANY  FOREIGN KEY (COMPANY_ID)
                    REFERENCES COMPANIES (ID),
                CONSTRAINT FK_DEPARTMENTS_LOCATION FOREIGN KEY (LOCATION_ID)
                    REFERENCES LOCATIONS (ID)
            )",

            // ---------------------------------------------------------------------- ROLES
            "CREATE TABLE ROLES (
                ID              NUMBER(19)      NOT NULL,
                CODE            VARCHAR2(40)    NOT NULL,
                NAME            VARCHAR2(120)   NOT NULL,
                DESCRIPTION     VARCHAR2(500),
                HIERARCHY_LEVEL NUMBER(3)       DEFAULT 50 NOT NULL,
                IS_ACTIVE       NUMBER(1)       DEFAULT 1 NOT NULL,
                IS_SYSTEM       NUMBER(1)       DEFAULT 0 NOT NULL,
                SORT_ORDER      NUMBER(5)       DEFAULT 0 NOT NULL,
                CREATED_AT      TIMESTAMP,
                UPDATED_AT      TIMESTAMP,
                CONSTRAINT PK_ROLES             PRIMARY KEY (ID),
                CONSTRAINT UQ_ROLES_CODE        UNIQUE (CODE),
                CONSTRAINT CK_ROLES_ACTIVE      CHECK (IS_ACTIVE IN (0,1)),
                CONSTRAINT CK_ROLES_SYSTEM      CHECK (IS_SYSTEM IN (0,1))
            )",

            // ---------------------------------------------------------------- PERMISSIONS
            "CREATE TABLE PERMISSIONS (
                ID              NUMBER(19)      NOT NULL,
                CODE            VARCHAR2(80)    NOT NULL,
                NAME            VARCHAR2(150)   NOT NULL,
                MODULE          VARCHAR2(60)    NOT NULL,
                DESCRIPTION     VARCHAR2(500),
                CREATED_AT      TIMESTAMP,
                UPDATED_AT      TIMESTAMP,
                CONSTRAINT PK_PERMISSIONS       PRIMARY KEY (ID),
                CONSTRAINT UQ_PERMISSIONS_CODE  UNIQUE (CODE)
            )",

            // ----------------------------------------------------------- ROLE_PERMISSIONS
            "CREATE TABLE ROLE_PERMISSIONS (
                ID              NUMBER(19)      NOT NULL,
                ROLE_ID         NUMBER(19)      NOT NULL,
                PERMISSION_ID   NUMBER(19)      NOT NULL,
                CREATED_AT      TIMESTAMP,
                UPDATED_AT      TIMESTAMP,
                CONSTRAINT PK_ROLE_PERMISSIONS  PRIMARY KEY (ID),
                CONSTRAINT UQ_ROLE_PERMISSION   UNIQUE (ROLE_ID, PERMISSION_ID),
                CONSTRAINT FK_ROLEPERM_ROLE     FOREIGN KEY (ROLE_ID)
                    REFERENCES ROLES (ID) ON DELETE CASCADE,
                CONSTRAINT FK_ROLEPERM_PERM     FOREIGN KEY (PERMISSION_ID)
                    REFERENCES PERMISSIONS (ID) ON DELETE CASCADE
            )",

            // ---------------------------------------------------------------------- USERS
            "CREATE TABLE USERS (
                ID                  NUMBER(19)      NOT NULL,
                EMPLOYEE_NO         VARCHAR2(30),
                NAME                VARCHAR2(150)   NOT NULL,
                EMAIL               VARCHAR2(150)   NOT NULL,
                PASSWORD            VARCHAR2(255)   NOT NULL,
                ROLE_ID             NUMBER(19),
                DEPARTMENT_ID       NUMBER(19),
                DEFAULT_ORG_ID      NUMBER(19),
                DEFAULT_COMPANY_ID  NUMBER(19),
                DEFAULT_LOCATION_ID NUMBER(19),
                DESIGNATION         VARCHAR2(120),
                PHONE               VARCHAR2(30),
                AVATAR_PATH         VARCHAR2(255),
                IS_ALL_ORGS         NUMBER(1)       DEFAULT 0 NOT NULL,
                IS_ALL_COMPANIES    NUMBER(1)       DEFAULT 0 NOT NULL,
                IS_ALL_LOCATIONS    NUMBER(1)       DEFAULT 0 NOT NULL,
                IS_ACTIVE           NUMBER(1)       DEFAULT 1 NOT NULL,
                LAST_LOGIN_AT       TIMESTAMP,
                LAST_LOGIN_IP       VARCHAR2(45),
                EMAIL_VERIFIED_AT   TIMESTAMP,
                REMEMBER_TOKEN      VARCHAR2(100),
                CREATED_AT          TIMESTAMP,
                UPDATED_AT          TIMESTAMP,
                CONSTRAINT PK_USERS             PRIMARY KEY (ID),
                CONSTRAINT UQ_USERS_EMAIL       UNIQUE (EMAIL),
                CONSTRAINT UQ_USERS_EMPLOYEE_NO UNIQUE (EMPLOYEE_NO),
                CONSTRAINT CK_USERS_ACTIVE      CHECK (IS_ACTIVE IN (0,1)),
                CONSTRAINT CK_USERS_ALL_ORGS    CHECK (IS_ALL_ORGS IN (0,1)),
                CONSTRAINT CK_USERS_ALL_COMP    CHECK (IS_ALL_COMPANIES IN (0,1)),
                CONSTRAINT CK_USERS_ALL_LOC     CHECK (IS_ALL_LOCATIONS IN (0,1)),
                CONSTRAINT FK_USERS_ROLE        FOREIGN KEY (ROLE_ID)
                    REFERENCES ROLES (ID),
                CONSTRAINT FK_USERS_DEPARTMENT  FOREIGN KEY (DEPARTMENT_ID)
                    REFERENCES DEPARTMENTS (ID),
                CONSTRAINT FK_USERS_DEF_ORG     FOREIGN KEY (DEFAULT_ORG_ID)
                    REFERENCES ORGANIZATIONS (ID),
                CONSTRAINT FK_USERS_DEF_COMPANY FOREIGN KEY (DEFAULT_COMPANY_ID)
                    REFERENCES COMPANIES (ID),
                CONSTRAINT FK_USERS_DEF_LOCATION FOREIGN KEY (DEFAULT_LOCATION_ID)
                    REFERENCES LOCATIONS (ID)
            )",

            // Circular references resolved once both sides exist.
            "ALTER TABLE DEPARTMENTS ADD CONSTRAINT FK_DEPARTMENTS_HEAD
                FOREIGN KEY (HEAD_USER_ID) REFERENCES USERS (ID)",
            "ALTER TABLE LOCATIONS ADD CONSTRAINT FK_LOCATIONS_MANAGER
                FOREIGN KEY (MANAGER_USER_ID) REFERENCES USERS (ID)",

            // --------------------------------------------------------- USER_ORGANIZATIONS
            "CREATE TABLE USER_ORGANIZATIONS (
                ID              NUMBER(19)      NOT NULL,
                USER_ID         NUMBER(19)      NOT NULL,
                ORGANIZATION_ID NUMBER(19)      NOT NULL,
                IS_DEFAULT      NUMBER(1)       DEFAULT 0 NOT NULL,
                GRANTED_BY      NUMBER(19),
                GRANTED_AT      TIMESTAMP,
                CREATED_AT      TIMESTAMP,
                UPDATED_AT      TIMESTAMP,
                CONSTRAINT PK_USER_ORGANIZATIONS PRIMARY KEY (ID),
                CONSTRAINT UQ_USER_ORGANIZATION  UNIQUE (USER_ID, ORGANIZATION_ID),
                CONSTRAINT CK_USER_ORG_DEFAULT   CHECK (IS_DEFAULT IN (0,1)),
                CONSTRAINT FK_USERORG_USER       FOREIGN KEY (USER_ID)
                    REFERENCES USERS (ID) ON DELETE CASCADE,
                CONSTRAINT FK_USERORG_ORG        FOREIGN KEY (ORGANIZATION_ID)
                    REFERENCES ORGANIZATIONS (ID) ON DELETE CASCADE,
                CONSTRAINT FK_USERORG_GRANTER    FOREIGN KEY (GRANTED_BY)
                    REFERENCES USERS (ID)
            )",

            // ------------------------------------------------------------- USER_COMPANIES
            "CREATE TABLE USER_COMPANIES (
                ID              NUMBER(19)      NOT NULL,
                USER_ID         NUMBER(19)      NOT NULL,
                COMPANY_ID      NUMBER(19)      NOT NULL,
                IS_DEFAULT      NUMBER(1)       DEFAULT 0 NOT NULL,
                GRANTED_BY      NUMBER(19),
                GRANTED_AT      TIMESTAMP,
                CREATED_AT      TIMESTAMP,
                UPDATED_AT      TIMESTAMP,
                CONSTRAINT PK_USER_COMPANIES    PRIMARY KEY (ID),
                CONSTRAINT UQ_USER_COMPANY      UNIQUE (USER_ID, COMPANY_ID),
                CONSTRAINT CK_USER_COMPANY_DEF  CHECK (IS_DEFAULT IN (0,1)),
                CONSTRAINT FK_USERCOMP_USER     FOREIGN KEY (USER_ID)
                    REFERENCES USERS (ID) ON DELETE CASCADE,
                CONSTRAINT FK_USERCOMP_COMPANY  FOREIGN KEY (COMPANY_ID)
                    REFERENCES COMPANIES (ID) ON DELETE CASCADE,
                CONSTRAINT FK_USERCOMP_GRANTER  FOREIGN KEY (GRANTED_BY)
                    REFERENCES USERS (ID)
            )",

            // ------------------------------------------------------------- USER_LOCATIONS
            "CREATE TABLE USER_LOCATIONS (
                ID              NUMBER(19)      NOT NULL,
                USER_ID         NUMBER(19)      NOT NULL,
                LOCATION_ID     NUMBER(19)      NOT NULL,
                COMPANY_ID      NUMBER(19)      NOT NULL,
                IS_DEFAULT      NUMBER(1)       DEFAULT 0 NOT NULL,
                GRANTED_BY      NUMBER(19),
                GRANTED_AT      TIMESTAMP,
                CREATED_AT      TIMESTAMP,
                UPDATED_AT      TIMESTAMP,
                CONSTRAINT PK_USER_LOCATIONS    PRIMARY KEY (ID),
                CONSTRAINT UQ_USER_LOCATION     UNIQUE (USER_ID, LOCATION_ID),
                CONSTRAINT CK_USER_LOCATION_DEF CHECK (IS_DEFAULT IN (0,1)),
                CONSTRAINT FK_USERLOC_USER      FOREIGN KEY (USER_ID)
                    REFERENCES USERS (ID) ON DELETE CASCADE,
                CONSTRAINT FK_USERLOC_LOCATION  FOREIGN KEY (LOCATION_ID)
                    REFERENCES LOCATIONS (ID) ON DELETE CASCADE,
                CONSTRAINT FK_USERLOC_COMPANY   FOREIGN KEY (COMPANY_ID)
                    REFERENCES COMPANIES (ID),
                CONSTRAINT FK_USERLOC_GRANTER   FOREIGN KEY (GRANTED_BY)
                    REFERENCES USERS (ID)
            )",

            // ------------------------------------------------------ PASSWORD_RESET_TOKENS
            "CREATE TABLE PASSWORD_RESET_TOKENS (
                EMAIL       VARCHAR2(150)   NOT NULL,
                TOKEN       VARCHAR2(255)   NOT NULL,
                CREATED_AT  TIMESTAMP,
                CONSTRAINT PK_PASSWORD_RESET_TOKENS PRIMARY KEY (EMAIL)
            )",

            // -------------------------------------------------------------------- INDEXES
            'CREATE INDEX IX_USERS_ROLE          ON USERS (ROLE_ID)',
            'CREATE INDEX IX_USERS_DEPARTMENT    ON USERS (DEPARTMENT_ID)',
            'CREATE INDEX IX_USERS_DEF_ORG       ON USERS (DEFAULT_ORG_ID)',
            'CREATE INDEX IX_USERS_DEF_COMPANY   ON USERS (DEFAULT_COMPANY_ID)',
            'CREATE INDEX IX_USERS_DEF_LOCATION  ON USERS (DEFAULT_LOCATION_ID)',
            'CREATE INDEX IX_USERS_ACTIVE        ON USERS (IS_ACTIVE)',
            'CREATE INDEX IX_DEPARTMENTS_COMPANY ON DEPARTMENTS (COMPANY_ID)',
            'CREATE INDEX IX_DEPARTMENTS_LOCATION ON DEPARTMENTS (LOCATION_ID)',
            'CREATE INDEX IX_DEPARTMENTS_HEAD    ON DEPARTMENTS (HEAD_USER_ID)',
            'CREATE INDEX IX_ROLEPERM_PERM       ON ROLE_PERMISSIONS (PERMISSION_ID)',
            'CREATE INDEX IX_PERMISSIONS_MODULE  ON PERMISSIONS (MODULE)',
            'CREATE INDEX IX_USERORG_ORG         ON USER_ORGANIZATIONS (ORGANIZATION_ID)',
            'CREATE INDEX IX_USERCOMP_COMPANY    ON USER_COMPANIES (COMPANY_ID)',
            'CREATE INDEX IX_USERLOC_LOCATION    ON USER_LOCATIONS (LOCATION_ID)',
            'CREATE INDEX IX_USERLOC_COMPANY     ON USER_LOCATIONS (COMPANY_ID)',
        ]);

        foreach ([
            'DEPARTMENTS'      => 'SEQ_DEPARTMENTS',
            'ROLES'            => 'SEQ_ROLES',
            'PERMISSIONS'      => 'SEQ_PERMISSIONS',
            'ROLE_PERMISSIONS' => 'SEQ_ROLE_PERMISSIONS',
            'USERS'              => 'SEQ_USERS',
            'USER_ORGANIZATIONS' => 'SEQ_USER_ORGANIZATIONS',
            'USER_COMPANIES'   => 'SEQ_USER_COMPANIES',
            'USER_LOCATIONS'   => 'SEQ_USER_LOCATIONS',
        ] as $table => $sequence) {
            OracleSchema::createSequenceWithTrigger($table, $sequence);
        }
    }

    public function down(): void
    {
        OracleSchema::dropQuietly('ALTER TABLE DEPARTMENTS DROP CONSTRAINT FK_DEPARTMENTS_HEAD');
        OracleSchema::dropQuietly('ALTER TABLE LOCATIONS DROP CONSTRAINT FK_LOCATIONS_MANAGER');

        OracleSchema::dropTables([
            'PASSWORD_RESET_TOKENS' => null,
            'USER_LOCATIONS'        => 'SEQ_USER_LOCATIONS',
            'USER_COMPANIES'        => 'SEQ_USER_COMPANIES',
            'USER_ORGANIZATIONS'    => 'SEQ_USER_ORGANIZATIONS',
            'ROLE_PERMISSIONS'      => 'SEQ_ROLE_PERMISSIONS',
            'USERS'                 => 'SEQ_USERS',
            'DEPARTMENTS'           => 'SEQ_DEPARTMENTS',
            'PERMISSIONS'           => 'SEQ_PERMISSIONS',
            'ROLES'                 => 'SEQ_ROLES',
        ]);
    }
};
