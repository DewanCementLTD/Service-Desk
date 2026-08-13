<?php

use App\Support\OracleSchema;
use Illuminate\Database\Migrations\Migration;

/**
 * Designations (job titles) were a free-text column on USERS, which made them
 * impossible to report on or keep consistent. They become a company-scoped
 * lookup here, modelled exactly like DEPARTMENTS and LOCATIONS so the legacy
 * DESIGNATION CODE values can be carried over as-is.
 *
 * The old free-text column is renamed rather than dropped: it keeps the values
 * of any user who has not been mapped to a lookup row yet, and it frees the
 * `designation` name for the new relationship on the User model.
 */
return new class extends Migration
{
    public function up(): void
    {
        OracleSchema::runIgnoringExisting([
            "CREATE TABLE DESIGNATIONS (
                ID              NUMBER(19)      NOT NULL,
                COMPANY_ID      NUMBER(19)      NOT NULL,
                CODE            VARCHAR2(30)    NOT NULL,
                NAME            VARCHAR2(120)   NOT NULL,
                DESCRIPTION     VARCHAR2(500),
                IS_ACTIVE       NUMBER(1)       DEFAULT 1 NOT NULL,
                SORT_ORDER      NUMBER(5)       DEFAULT 0 NOT NULL,
                CREATED_AT      TIMESTAMP,
                UPDATED_AT      TIMESTAMP,
                CONSTRAINT PK_DESIGNATIONS         PRIMARY KEY (ID),
                CONSTRAINT UQ_DESIGNATIONS_CODE    UNIQUE (COMPANY_ID, CODE),
                CONSTRAINT CK_DESIGNATIONS_ACTIVE  CHECK (IS_ACTIVE IN (0,1)),
                CONSTRAINT FK_DESIGNATIONS_COMPANY FOREIGN KEY (COMPANY_ID)
                    REFERENCES COMPANIES (ID)
            )",

            'CREATE INDEX IX_DESIGNATIONS_COMPANY ON DESIGNATIONS (COMPANY_ID)',
            'CREATE INDEX IX_DESIGNATIONS_ACTIVE  ON DESIGNATIONS (IS_ACTIVE)',

            'ALTER TABLE USERS ADD DESIGNATION_ID NUMBER(19)',
            'ALTER TABLE USERS ADD CONSTRAINT FK_USERS_DESIGNATION
                FOREIGN KEY (DESIGNATION_ID) REFERENCES DESIGNATIONS (ID)',
            'CREATE INDEX IX_USERS_DESIGNATION ON USERS (DESIGNATION_ID)',
        ]);

        OracleSchema::createSequenceWithTrigger('DESIGNATIONS', 'SEQ_DESIGNATIONS');

        if (self::userColumnExists('DESIGNATION')) {
            OracleSchema::run([
                'ALTER TABLE USERS RENAME COLUMN DESIGNATION TO LEGACY_DESIGNATION',
            ]);
        }
    }

    public function down(): void
    {
        if (self::userColumnExists('LEGACY_DESIGNATION')) {
            OracleSchema::run([
                'ALTER TABLE USERS RENAME COLUMN LEGACY_DESIGNATION TO DESIGNATION',
            ]);
        }

        OracleSchema::dropQuietly('DROP INDEX IX_USERS_DESIGNATION');
        OracleSchema::dropQuietly('ALTER TABLE USERS DROP CONSTRAINT FK_USERS_DESIGNATION');
        OracleSchema::dropQuietly('ALTER TABLE USERS DROP COLUMN DESIGNATION_ID');

        OracleSchema::dropQuietly('DROP TRIGGER ' . OracleSchema::triggerName('DESIGNATIONS'));
        OracleSchema::dropTable('DESIGNATIONS', 'SEQ_DESIGNATIONS');
    }

    private static function userColumnExists(string $column): bool
    {
        return \Illuminate\Support\Facades\DB::connection('oracle')->selectOne(
            'select count(*) as c from user_tab_columns
             where table_name = ? and column_name = ?',
            ['USERS', $column]
        )->c > 0;
    }
};
