<?php

use App\Support\OracleSchema;
use Illuminate\Database\Migrations\Migration;

/**
 * PKG_DOC_NUMBER - concurrency-safe human-readable document numbers.
 *
 * Surrogate keys come from plain Oracle sequences, but the user-facing number
 * (IT-2026-000001) has to be gapless-ish, restart per year and be unique per
 * company. A sequence cannot express that, so allocation is serialised through
 * a row lock on DOC_SEQUENCES inside an autonomous transaction: the counter is
 * committed immediately and the lock is released without waiting for the
 * surrounding business transaction to finish.
 */
return new class extends Migration
{
    public function up(): void
    {
        OracleSchema::run([
            "CREATE OR REPLACE PACKAGE PKG_DOC_NUMBER AS
                -- Reserve and return the next raw counter value.
                FUNCTION NEXT_VALUE(
                    P_COMPANY_ID IN NUMBER,
                    P_PREFIX     IN VARCHAR2,
                    P_YEAR       IN NUMBER
                ) RETURN NUMBER;

                -- Reserve and return a formatted number, e.g. IT-2026-000001.
                FUNCTION NEXT_DOC_NO(
                    P_COMPANY_ID IN NUMBER,
                    P_PREFIX     IN VARCHAR2,
                    P_YEAR       IN NUMBER DEFAULT NULL,
                    P_WIDTH      IN NUMBER DEFAULT 6
                ) RETURN VARCHAR2;
            END PKG_DOC_NUMBER;",

            "CREATE OR REPLACE PACKAGE BODY PKG_DOC_NUMBER AS

                FUNCTION NEXT_VALUE(
                    P_COMPANY_ID IN NUMBER,
                    P_PREFIX     IN VARCHAR2,
                    P_YEAR       IN NUMBER
                ) RETURN NUMBER
                IS
                    PRAGMA AUTONOMOUS_TRANSACTION;
                    V_NEXT NUMBER(10);
                BEGIN
                    -- Fast path: the counter row already exists for this company/year.
                    UPDATE DOC_SEQUENCES
                       SET LAST_NO    = LAST_NO + 1,
                           UPDATED_AT = SYSTIMESTAMP
                     WHERE COMPANY_ID = P_COMPANY_ID
                       AND PREFIX     = P_PREFIX
                       AND YEAR_NO    = P_YEAR
                    RETURNING LAST_NO INTO V_NEXT;

                    IF SQL%ROWCOUNT = 0 THEN
                        -- First document of the year for this company/prefix.
                        BEGIN
                            INSERT INTO DOC_SEQUENCES (
                                COMPANY_ID, PREFIX, YEAR_NO, LAST_NO, UPDATED_AT
                            ) VALUES (
                                P_COMPANY_ID, P_PREFIX, P_YEAR, 1, SYSTIMESTAMP
                            );
                            V_NEXT := 1;
                        EXCEPTION
                            WHEN DUP_VAL_ON_INDEX THEN
                                -- A concurrent session created the row first.
                                UPDATE DOC_SEQUENCES
                                   SET LAST_NO    = LAST_NO + 1,
                                       UPDATED_AT = SYSTIMESTAMP
                                 WHERE COMPANY_ID = P_COMPANY_ID
                                   AND PREFIX     = P_PREFIX
                                   AND YEAR_NO    = P_YEAR
                                RETURNING LAST_NO INTO V_NEXT;
                        END;
                    END IF;

                    COMMIT;
                    RETURN V_NEXT;
                EXCEPTION
                    WHEN OTHERS THEN
                        ROLLBACK;
                        RAISE;
                END NEXT_VALUE;

                FUNCTION NEXT_DOC_NO(
                    P_COMPANY_ID IN NUMBER,
                    P_PREFIX     IN VARCHAR2,
                    P_YEAR       IN NUMBER DEFAULT NULL,
                    P_WIDTH      IN NUMBER DEFAULT 6
                ) RETURN VARCHAR2
                IS
                    V_YEAR  NUMBER(4) := NVL(P_YEAR, TO_NUMBER(TO_CHAR(SYSDATE, 'YYYY')));
                    V_VALUE NUMBER(10);
                BEGIN
                    V_VALUE := NEXT_VALUE(P_COMPANY_ID, P_PREFIX, V_YEAR);

                    RETURN P_PREFIX || '-' || TO_CHAR(V_YEAR) || '-' ||
                           LPAD(TO_CHAR(V_VALUE), P_WIDTH, '0');
                END NEXT_DOC_NO;

            END PKG_DOC_NUMBER;",
        ]);
    }

    public function down(): void
    {
        OracleSchema::dropQuietly('DROP PACKAGE PKG_DOC_NUMBER');
    }
};
