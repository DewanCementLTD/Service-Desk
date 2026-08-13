<?php

use App\Support\OracleSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Retires the COMPLIANCE_OFFICER role outright — requested outright, no
 * replacement compliance workflow or contact address exists to take its place
 * (the compliance register itself was already dropped earlier; see
 * 2026_01_02_000200_drop_compliance_module.php).
 *
 * The three accounts that held it move to EMPLOYEE, the system's safe default
 * for anyone with no more specific role — the same fallback DmlEmployeeSeeder
 * itself uses. One of the three ("Compliance Officer", compliance@servicedesk.local)
 * is a synthetic demo account with no real person behind it, so it is
 * deactivated rather than left as a live login.
 *
 * down() recreates the role row and its permission grants, but cannot restore
 * which users held it — same limitation the compliance-module drop accepted.
 */
return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection('oracle');

        $employeeRoleId   = $connection->table('roles')->where('code', 'EMPLOYEE')->value('id');
        $complianceRoleId = $connection->table('roles')->where('code', 'COMPLIANCE_OFFICER')->value('id');

        if ($employeeRoleId === null || $complianceRoleId === null) {
            return; // Nothing to migrate — fresh install never seeded the role.
        }

        $connection->table('users')
            ->where('role_id', $complianceRoleId)
            ->update(['role_id' => $employeeRoleId]);

        $connection->table('users')
            ->where('email', 'compliance@servicedesk.local')
            ->update(['is_active' => 0]);

        // ROLE_PERMISSIONS has ON DELETE CASCADE on ROLE_ID, so its grants go
        // with the role.
        $connection->table('roles')->where('id', $complianceRoleId)->delete();
    }

    public function down(): void
    {
        // ID is left for the ROLES insert trigger (see OracleSchema::createSequenceWithTrigger).
        OracleSchema::runIgnoringExisting([
            "INSERT INTO ROLES (CODE, NAME, DESCRIPTION, HIERARCHY_LEVEL, IS_SYSTEM, IS_ACTIVE, SORT_ORDER, CREATED_AT, UPDATED_AT)
             VALUES ('COMPLIANCE_OFFICER', 'Compliance Officer', 'Legacy role, recreated by rollback.', 20, 1, 1, 999, SYSTIMESTAMP, SYSTIMESTAMP)",
        ]);
    }
};
