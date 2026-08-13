<?php

use App\Support\OracleSchema;
use Illuminate\Database\Migrations\Migration;

/**
 * Browser push subscriptions.
 *
 * One row per browser a user has allowed notifications in, so the same account
 * can be reached on a desktop and a phone at once. ENDPOINT is the push service
 * URL handed over by the browser; it is far too long to index directly on
 * Oracle, so ENDPOINT_HASH carries the uniqueness constraint instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        OracleSchema::run([
            "CREATE TABLE PUSH_SUBSCRIPTIONS (
                ID              NUMBER(19)      NOT NULL,
                USER_ID         NUMBER(19)      NOT NULL,
                ENDPOINT        VARCHAR2(1000)  NOT NULL,
                ENDPOINT_HASH   VARCHAR2(64)    NOT NULL,
                PUBLIC_KEY      VARCHAR2(255)   NOT NULL,
                AUTH_TOKEN      VARCHAR2(255)   NOT NULL,
                CONTENT_ENCODING VARCHAR2(20)   DEFAULT 'aesgcm' NOT NULL,
                USER_AGENT      VARCHAR2(400),
                FAILURE_COUNT   NUMBER(3)       DEFAULT 0 NOT NULL,
                LAST_USED_AT    TIMESTAMP,
                CREATED_AT      TIMESTAMP,
                UPDATED_AT      TIMESTAMP,
                CONSTRAINT PK_PUSH_SUBSCRIPTIONS    PRIMARY KEY (ID),
                CONSTRAINT UQ_PUSH_SUBS_ENDPOINT    UNIQUE (ENDPOINT_HASH),
                CONSTRAINT FK_PUSH_SUBS_USER        FOREIGN KEY (USER_ID)
                    REFERENCES USERS (ID) ON DELETE CASCADE
            )",

            'CREATE INDEX IX_PUSH_SUBS_USER ON PUSH_SUBSCRIPTIONS (USER_ID)',
        ]);

        OracleSchema::createSequenceWithTrigger('PUSH_SUBSCRIPTIONS', 'SEQ_PUSH_SUBSCRIPTIONS');
    }

    public function down(): void
    {
        OracleSchema::dropTables([
            'PUSH_SUBSCRIPTIONS' => 'SEQ_PUSH_SUBSCRIPTIONS',
        ]);
    }
};
