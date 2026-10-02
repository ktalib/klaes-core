/* ============================================================================
   users.otp / otp_sent_at / is_otp_verified — the phone-number verification gate
   ----------------------------------------------------------------------------
   RUN THIS AGAINST SQL SERVER (the klaes sqlsrv database).

   Companion:
     database/sql/2026_09_06_add_phone_otp_verification_to_users_ledger.mysql.sql
     — run that one afterwards, against MYSQL, to mark the migration as applied.

   WHY
   The phone numbers on staff accounts cannot be trusted. Attendance SMS, file
   notices and everything else aim at users.phone_number, and a number nobody has
   ever proved reaches its owner is worse than a blank one — it looks like a way
   of contacting the person. The only proof is a code sent to the number and
   typed back by whoever is holding the handset.

   WHAT THIS DOES
   Adds three columns to dbo.users:

     otp              NVARCHAR(10) NULL  the code last sent (plain text, short-lived)
     otp_sent_at      DATETIME     NULL  when it was sent — drives expiry and the
                                         resend cooldown
     is_otp_verified  BIT NOT NULL DEFAULT 0

   EVERY EXISTING ROW GETS is_otp_verified = 0, which is the intent: the gate
   sweeps the whole staff list once. Signing in is NOT blocked — the account is
   held at a card that asks the user to confirm their number, exactly like the
   mandatory passport photo.

   BEFORE YOU RUN IT, READ THIS
   Verification depends on an SMS actually arriving. On this account that means:
     * the Bulk-SMS.ng wallet must hold credit;
     * the account is on the PROMOTIONAL route, which does not reach DND-blocked
       handsets and which the telcos hold between 19:45 and 08:00 (code 609) —
       a code requested at 21:00 does not arrive until 08:30 the next morning.
   Staff who cannot receive a code are let through by hand:
       php artisan phone:verify-status --user=<id> --verify
   and the whole gate is switched off with PHONE_VERIFICATION_ENABLED=false (or
   by editing config/phone_verification.php, which does not depend on .env).

   SAFETY
     - Re-runnable: every ALTER is guarded by a COL_LENGTH check.
     - Adds columns only. No existing value is modified.
     - Deploying the code before this script holds nobody: the gate checks that
       is_otp_verified exists and stays open while it does not.
   ============================================================================ */

/* Preview — expect 3 zeros before the first run */
SELECT
    CASE WHEN COL_LENGTH('dbo.users', 'otp')             IS NULL THEN 0 ELSE 1 END AS has_otp,
    CASE WHEN COL_LENGTH('dbo.users', 'otp_sent_at')     IS NULL THEN 0 ELSE 1 END AS has_otp_sent_at,
    CASE WHEN COL_LENGTH('dbo.users', 'is_otp_verified') IS NULL THEN 0 ELSE 1 END AS has_is_otp_verified;

IF COL_LENGTH('dbo.users', 'otp') IS NULL
    ALTER TABLE dbo.users ADD otp NVARCHAR(10) NULL;

IF COL_LENGTH('dbo.users', 'otp_sent_at') IS NULL
    ALTER TABLE dbo.users ADD otp_sent_at DATETIME NULL;

/* NOT NULL with a named default: SQL Server backfills every existing row with 0
   as part of the ALTER, so an unverified account and an account created
   tomorrow are the same thing. The constraint is named so it can be dropped
   again by name if this is ever rolled back. */
IF COL_LENGTH('dbo.users', 'is_otp_verified') IS NULL
    ALTER TABLE dbo.users
        ADD is_otp_verified BIT NOT NULL
        CONSTRAINT DF_users_is_otp_verified DEFAULT (0);

/* Verify — expect 3 ones */
SELECT
    CASE WHEN COL_LENGTH('dbo.users', 'otp')             IS NULL THEN 0 ELSE 1 END AS has_otp,
    CASE WHEN COL_LENGTH('dbo.users', 'otp_sent_at')     IS NULL THEN 0 ELSE 1 END AS has_otp_sent_at,
    CASE WHEN COL_LENGTH('dbo.users', 'is_otp_verified') IS NULL THEN 0 ELSE 1 END AS has_is_otp_verified;

/* ----------------------------------------------------------------------------
   WHO THIS ACTUALLY CONCERNS — run it before you enable the gate.

   `reachable_now` is the number of active accounts whose stored number is
   already a usable Nigerian mobile (11 digits starting 0, or 13 starting 234).
   Everybody else has to type a real number into the card before a code can be
   sent to them, and anybody who cannot receive one at all needs
   `phone:verify-status --user=<id> --verify`.
   ---------------------------------------------------------------------------- */
WITH digits AS (
    SELECT
        id,
        is_active,
        /* strip everything that is not a digit, in the small number of passes a
           Nigerian number needs — no regex in T-SQL */
        REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
            COALESCE(phone_number, ''), ' ', ''), '-', ''), '(', ''), ')', ''), '+', ''), '.', '') AS d
      FROM dbo.users
)
SELECT
    COUNT(*)                                                        AS users_total,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END)                  AS users_active,
    SUM(CASE WHEN d = '' THEN 1 ELSE 0 END)                         AS no_number_at_all,
    SUM(CASE WHEN is_active = 1
              AND ((LEN(d) = 11 AND LEFT(d, 1) = '0')
                OR (LEN(d) = 13 AND LEFT(d, 3) = '234'))
             THEN 1 ELSE 0 END)                                     AS reachable_now,
    SUM(CASE WHEN is_active = 1
              AND d <> ''
              AND NOT ((LEN(d) = 11 AND LEFT(d, 1) = '0')
                    OR (LEN(d) = 13 AND LEFT(d, 3) = '234'))
             THEN 1 ELSE 0 END)                                     AS number_present_but_unusable
  FROM digits;

/* ----------------------------------------------------------------------------
   ESCAPE HATCH, kept here so it is to hand during a deployment rather than
   remembered later. Marks one account verified without an SMS:

     UPDATE dbo.users SET is_otp_verified = 1, otp = NULL WHERE id = <id>;

   And, if the gate has to be lifted for everybody at once:

     UPDATE dbo.users SET is_otp_verified = 1;

   Both are also available as `php artisan phone:verify-status --user=<id>
   --verify` and `--verify-all`, which say what they changed.
   ---------------------------------------------------------------------------- */
