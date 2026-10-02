/* ============================================================================
   users.otp_channel — the verification gate learns to send codes by EMAIL
   ----------------------------------------------------------------------------
   RUN THIS AGAINST SQL SERVER (the klaes sqlsrv database).

   Companion:
     database/sql/2026_09_07_add_otp_channel_to_users_ledger.mysql.sql
     — run that one afterwards, against MYSQL, to mark the migration as applied.

   WHY
   The gate was built to prove a mobile number. The data says that route cannot
   carry it on its own: of 1,562 active accounts only about 282 hold a number the
   SMS gateway can reach, and that gateway is on the PROMOTIONAL route — held by
   the telcos between 19:45 and 08:00, never delivered to a DND-blocked handset.
   495 of the same accounts hold an email address that is a real mailbox (1,555
   hold something address-shaped; the difference is the seeded placeholders the
   query at the bottom of this file counts). So email is now the default route
   for the code, and SMS is the alternative offered underneath it.

   WHAT THIS DOES
   Adds ONE nullable column to dbo.users:

     otp_channel  NVARCHAR(10) NULL   'email' or 'sms' — how the last code was
                                      sent. NULL means the SMS card, which is
                                      what every account verified before today
                                      used; every reader maps NULL to 'sms'.

   No existing value is touched and nobody is un-verified: an account that has
   already proved its number stays verified, with otp_channel NULL.

   THE GATE DOES NOT DEPEND ON THIS COLUMN. The application checks for it
   separately and simply does not write it when it is absent, so the code can be
   uploaded before this script is run — codes still go out, the card still works,
   it just cannot remember which route it used.

   BEFORE YOU RUN IT, READ THIS
   Email delivery has to work on the server for the default route to be any use:
   MAIL_HOST / MAIL_USERNAME / MAIL_PASSWORD live in .env, and .env does NOT
   travel with a code upload. Check with

       php artisan email:test you@example.com
       php artisan phone:verify-status

   before switching anybody over. If mail is not configured the card still
   offers the SMS route, and staff who can receive neither are let through by
   hand with `php artisan phone:verify-status --user=<id> --verify`.

   SAFETY
     - Re-runnable: the ALTER is guarded by a COL_LENGTH check.
     - Adds one column. Modifies no row.
   ============================================================================ */

/* Preview — expect 0 before the first run */
SELECT CASE WHEN COL_LENGTH('dbo.users', 'otp_channel') IS NULL THEN 0 ELSE 1 END AS has_otp_channel;

IF COL_LENGTH('dbo.users', 'otp_channel') IS NULL
    ALTER TABLE dbo.users ADD otp_channel NVARCHAR(10) NULL;

/* Verify — expect 1 */
SELECT CASE WHEN COL_LENGTH('dbo.users', 'otp_channel') IS NULL THEN 0 ELSE 1 END AS has_otp_channel;

/* ----------------------------------------------------------------------------
   WHO THE EMAIL ROUTE CAN ACTUALLY REACH — run it before you enable the gate.

   `placeholder_email` is the number that matters. Roughly a thousand accounts
   were seeded with addresses at example.com / example.org / example.net and
   klaes.local. Those are not mailboxes. The application REFUSES to send a code
   to them and asks the user to type their real address into the card instead —
   which is the point of the exercise, but it means those people need a working
   address to hand, or the SMS route, or an ICT override.
   ---------------------------------------------------------------------------- */
WITH addr AS (
    SELECT
        id,
        is_active,
        LOWER(LTRIM(RTRIM(COALESCE(email, '')))) AS e
      FROM dbo.users
)
SELECT
    COUNT(*)                                                            AS users_total,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END)                      AS users_active,
    SUM(CASE WHEN is_active = 1 AND e = '' THEN 1 ELSE 0 END)           AS no_email_at_all,
    SUM(CASE WHEN is_active = 1
              AND e LIKE '%_@_%._%'
              AND (e LIKE '%@example.com' OR e LIKE '%@example.org'
                OR e LIKE '%@example.net' OR e LIKE '%@%.local'
                OR e LIKE '%@%.test'      OR e LIKE '%@%.invalid'
                OR e LIKE '%@%.localhost')
             THEN 1 ELSE 0 END)                                         AS placeholder_email,
    SUM(CASE WHEN is_active = 1
              AND e LIKE '%_@_%._%'
              AND NOT (e LIKE '%@example.com' OR e LIKE '%@example.org'
                    OR e LIKE '%@example.net' OR e LIKE '%@%.local'
                    OR e LIKE '%@%.test'      OR e LIKE '%@%.invalid'
                    OR e LIKE '%@%.localhost')
             THEN 1 ELSE 0 END)                                         AS reachable_by_email_now
  FROM addr;

/* Addresses shared by more than one account. The card REFUSES to accept an
   address that already belongs to somebody else — email is also a sign-in
   identifier here (AuthPageController matches on email OR username), so letting
   two accounts claim one address makes signing in by email ambiguous. These
   rows pre-date the rule and are left exactly as they are; the list is here so
   ICT knows which accounts will be asked to pick a different address. */
SELECT LOWER(email) AS email, COUNT(*) AS accounts
  FROM dbo.users
 WHERE is_active = 1 AND email LIKE '%_@_%._%'
 GROUP BY LOWER(email)
HAVING COUNT(*) > 1
 ORDER BY accounts DESC;

/* ----------------------------------------------------------------------------
   ESCAPE HATCH, unchanged by this script. Marks one account verified without
   any code being sent:

     UPDATE dbo.users SET is_otp_verified = 1, otp = NULL WHERE id = <id>;

   Also `php artisan phone:verify-status --user=<id> --verify`, and the whole
   gate still lifts with PHONE_VERIFICATION_ENABLED=false.
   ---------------------------------------------------------------------------- */
