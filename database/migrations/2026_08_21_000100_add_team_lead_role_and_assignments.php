<?php

use App\Support\OracleSchema;
use Illuminate\Database\Migrations\Migration;

/**
 * Add Team Lead role and team assignment tracking.
 *
 * Team Leads can manage tasks for their assigned team members.
 * The TEAM_ASSIGNMENTS table tracks which users are managed by which Team Leads.
 */
return new class extends Migration
{
    public function up(): void
    {
        OracleSchema::run([
            // --------------------------------------------------------- TEAM_ASSIGNMENTS
            "CREATE TABLE TEAM_ASSIGNMENTS (
                ID              NUMBER(19)      NOT NULL,
                TEAM_LEAD_ID    NUMBER(19)      NOT NULL,
                TEAM_MEMBER_ID  NUMBER(19)      NOT NULL,
                ASSIGNED_AT     TIMESTAMP,
                ASSIGNED_BY     NUMBER(19),
                CREATED_AT      TIMESTAMP,
                UPDATED_AT      TIMESTAMP,
                CONSTRAINT PK_TEAM_ASSIGNMENTS      PRIMARY KEY (ID),
                CONSTRAINT UQ_TEAM_ASSIGNMENT       UNIQUE (TEAM_LEAD_ID, TEAM_MEMBER_ID),
                CONSTRAINT CK_TEAM_ASSIGNMENT_DIFF  CHECK (TEAM_LEAD_ID != TEAM_MEMBER_ID),
                CONSTRAINT FK_TEAM_LEAD            FOREIGN KEY (TEAM_LEAD_ID)
                    REFERENCES USERS (ID) ON DELETE CASCADE,
                CONSTRAINT FK_TEAM_MEMBER           FOREIGN KEY (TEAM_MEMBER_ID)
                    REFERENCES USERS (ID) ON DELETE CASCADE,
                CONSTRAINT FK_TEAM_ASSIGNER         FOREIGN KEY (ASSIGNED_BY)
                    REFERENCES USERS (ID)
            )",

            // ---------------------------------------------------------------- INDEXES
            'CREATE INDEX IX_TEAM_ASSIGN_LEAD      ON TEAM_ASSIGNMENTS (TEAM_LEAD_ID)',
            'CREATE INDEX IX_TEAM_ASSIGN_MEMBER    ON TEAM_ASSIGNMENTS (TEAM_MEMBER_ID)',
            'CREATE INDEX IX_TEAM_ASSIGN_ASSIGNED  ON TEAM_ASSIGNMENTS (ASSIGNED_AT)',
        ]);

        OracleSchema::createSequenceWithTrigger('TEAM_ASSIGNMENTS', 'SEQ_TEAM_ASSIGNMENTS');
    }

    public function down(): void
    {
        OracleSchema::dropTables([
            'TEAM_ASSIGNMENTS' => 'SEQ_TEAM_ASSIGNMENTS',
        ]);
    }
};
