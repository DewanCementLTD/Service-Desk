<?php

use App\Support\OracleSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Add HOD email column to users table for direct HOD notifications.
 *
 * This allows users to have a direct HOD email address stored in the users table,
 * enabling ticket emails to be sent to the HOD without relying solely on the
 * department's HEAD_USER_ID relationship.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Check if column already exists before adding
        if (!Schema::hasColumn('users', 'hod_phone')) {
            OracleSchema::run([
                "ALTER TABLE USERS ADD (
                    HOD_PHONE VARCHAR2(30)
                )",
            ]);
        }

        // Check if index already exists before creating
        // Oracle indexes are case-insensitive, so we check lowercase
        try {
            OracleSchema::run(['CREATE INDEX IX_USERS_HOD_PHONE ON USERS (HOD_PHONE)']);
        } catch (\Exception $e) {
            // Index might already exist, skip
        }
    }

    public function down(): void
    {
        OracleSchema::run([
            'DROP INDEX IX_USERS_HOD_PHONE',
            'ALTER TABLE USERS DROP COLUMN HOD_PHONE',
        ]);
    }
};
