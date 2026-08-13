<?php

use App\Support\OracleSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lets users change their own password.
 *
 * The employee roster was imported with a single shared starting password, which
 * is only acceptable if everyone is made to replace it. MUST_CHANGE_PASSWORD
 * forces that on the next sign-in; PASSWORD_CHANGED_AT records when it happened.
 */
return new class extends Migration
{
    public function up(): void
    {
        OracleSchema::runIgnoringExisting([
            'ALTER TABLE USERS ADD MUST_CHANGE_PASSWORD NUMBER(1) DEFAULT 0 NOT NULL',
            'ALTER TABLE USERS ADD PASSWORD_CHANGED_AT TIMESTAMP',
            'ALTER TABLE USERS ADD CONSTRAINT CK_USERS_MUST_CHANGE_PWD
                CHECK (MUST_CHANGE_PASSWORD IN (0,1))',
        ]);

        // Every account that still carries an IT-issued password must replace it.
        // Accounts that have already changed one are left alone.
        DB::connection('oracle')->statement(
            'UPDATE USERS SET MUST_CHANGE_PASSWORD = 1 WHERE PASSWORD_CHANGED_AT IS NULL'
        );
    }

    public function down(): void
    {
        OracleSchema::dropQuietly('ALTER TABLE USERS DROP CONSTRAINT CK_USERS_MUST_CHANGE_PWD');
        OracleSchema::dropQuietly('ALTER TABLE USERS DROP COLUMN MUST_CHANGE_PASSWORD');
        OracleSchema::dropQuietly('ALTER TABLE USERS DROP COLUMN PASSWORD_CHANGED_AT');
    }
};
