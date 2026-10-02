/* ============================================================================
   Staff attendance SMS — one sign-in and one sign-out message per person, per day
   ----------------------------------------------------------------------------
   RUN THIS AGAINST SQL SERVER (the klaes sqlsrv database).

   Companion:
     database/sql/2026_09_04_create_staff_sms_logs_ledger.mysql.sql
     — run that one afterwards, against MYSQL, to mark the migration as applied.

   WHAT THIS DOES
   Creates staff_sms_logs. Staff receive one SMS when they sign in and one when
   they sign out, sent through Bulk-SMS.ng under the sender ID KANOMLPP.

   This table is BOTH the once-a-day throttle and the audit trail. The unique
   index UX_staff_sms_logs_daily on (user_id, sms_type, sent_on) is what
   actually enforces "once a day" — two simultaneous sign-ins race to insert and
   the loser is rejected by the database, rather than by an application check
   that could interleave between its read and its write.

   A row is claimed BEFORE the gateway is called and updated with the outcome
   afterwards. A send that fails leaves status='failed', which the next sign-in
   that day is allowed to retry: the promise is one DELIVERED message a day, not
   one attempt.

   sent_on is a DATE on the Africa/Lagos clock, not UTC. config('app.timezone')
   is UTC on this deployment while the office runs on WAT (UTC+1), so a 00:30
   sign-out would otherwise be filed under the following day.

   SAFETY
     - Re-runnable: guarded by an OBJECT_ID check.
     - Creates one new table. Touches no existing data.
   ============================================================================ */

IF OBJECT_ID('dbo.staff_sms_logs', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.staff_sms_logs (
        id              BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,

        user_id         BIGINT        NOT NULL,

        /* 'login' | 'logout' */
        sms_type        NVARCHAR(20)  NOT NULL,

        /* The Africa/Lagos working day this message belongs to. */
        sent_on         DATE          NOT NULL,

        /* 'pending' | 'sent' | 'failed' */
        status          NVARCHAR(20)  NOT NULL
                        CONSTRAINT DF_staff_sms_logs_status DEFAULT ('pending'),

        /* The normalised 234XXXXXXXXXX handed to the gateway. */
        phone           NVARCHAR(20)  NULL,

        message         NVARCHAR(MAX) NULL,
        gateway_code    NVARCHAR(20)  NULL,
        failure_reason  NVARCHAR(MAX) NULL,
        attempts        TINYINT       NOT NULL
                        CONSTRAINT DF_staff_sms_logs_attempts DEFAULT (0),

        /* The sign-in / sign-out moment the message reports, in local time. */
        event_at        DATETIME      NULL,

        created_at      DATETIME      NULL,
        updated_at      DATETIME      NULL
    );

    /* The throttle. */
    CREATE UNIQUE INDEX staff_sms_logs_daily_unique
        ON dbo.staff_sms_logs (user_id, sms_type, sent_on);

    /* Supports the daily counts on `php artisan staff:sms-doctor`. */
    CREATE INDEX staff_sms_logs_day_status_idx
        ON dbo.staff_sms_logs (sent_on, status);
END
GO

/* Verify — expect the table, 1 unique index and 1 helper index. */
SELECT
    (SELECT COUNT(*) FROM sys.tables WHERE name = 'staff_sms_logs')                     AS table_created,
    (SELECT COUNT(*) FROM sys.indexes
      WHERE object_id = OBJECT_ID('dbo.staff_sms_logs') AND name = 'staff_sms_logs_daily_unique')  AS unique_index,
    (SELECT COUNT(*) FROM sys.indexes
      WHERE object_id = OBJECT_ID('dbo.staff_sms_logs') AND name = 'staff_sms_logs_day_status_idx') AS helper_index;
GO
