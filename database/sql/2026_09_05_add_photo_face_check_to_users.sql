/* ============================================================================
   users.photo_face_* — persist the profile-picture face-check verdict
   ----------------------------------------------------------------------------
   RUN THIS AGAINST SQL SERVER (the klaes sqlsrv database).

   Companion:
     database/sql/2026_09_05_add_photo_face_check_to_users_ledger.mysql.sql
     — run that one afterwards, against MYSQL, to mark the migration as applied.

   WHY
   The mandatory-photo gate asked only "is there a file behind users.profile?".
   A stock cartoon avatar answers yes, so those accounts were never held —
   even though the picture identifies nobody on a file or a request. Face
   detection runs in the browser (face-api.js; there is no PHP detector), so the
   verdict has to be stored for the server-side gate to be able to read it.

   WHAT THIS DOES
   Adds four nullable columns to dbo.users. No existing row is modified: every
   account starts as "not yet checked", which is NOT a rejection — the gate only
   holds a user once their picture has actually been judged and failed. The
   check is then written once per picture, on the owner's next page load.

     photo_face_status      'pass' | 'fail' | 'override' (NULL = not yet checked)
     photo_face_reason      the detector's own words, shown to the user
     photo_face_checked_at  when it was judged
     photo_face_path        WHICH stored picture the verdict speaks for

   SAFETY
     - Re-runnable: every ALTER is guarded by a COL_LENGTH check.
     - Adds nullable columns only. No data is written, changed or deleted.
     - Deploying the code before this script simply means no verdict is ever
       recorded (the write path checks the column exists first); nobody is
       locked out by the gap.
   ============================================================================ */

/* Preview — expect 4 zeros before the first run */
SELECT
    CASE WHEN COL_LENGTH('dbo.users', 'photo_face_status')     IS NULL THEN 0 ELSE 1 END AS has_status,
    CASE WHEN COL_LENGTH('dbo.users', 'photo_face_reason')     IS NULL THEN 0 ELSE 1 END AS has_reason,
    CASE WHEN COL_LENGTH('dbo.users', 'photo_face_checked_at') IS NULL THEN 0 ELSE 1 END AS has_checked_at,
    CASE WHEN COL_LENGTH('dbo.users', 'photo_face_path')       IS NULL THEN 0 ELSE 1 END AS has_path;

IF COL_LENGTH('dbo.users', 'photo_face_status') IS NULL
    ALTER TABLE dbo.users ADD photo_face_status NVARCHAR(12) NULL;

IF COL_LENGTH('dbo.users', 'photo_face_reason') IS NULL
    ALTER TABLE dbo.users ADD photo_face_reason NVARCHAR(160) NULL;

IF COL_LENGTH('dbo.users', 'photo_face_checked_at') IS NULL
    ALTER TABLE dbo.users ADD photo_face_checked_at DATETIME NULL;

IF COL_LENGTH('dbo.users', 'photo_face_path') IS NULL
    ALTER TABLE dbo.users ADD photo_face_path NVARCHAR(255) NULL;

/* Verify — expect 4 ones */
SELECT
    CASE WHEN COL_LENGTH('dbo.users', 'photo_face_status')     IS NULL THEN 0 ELSE 1 END AS has_status,
    CASE WHEN COL_LENGTH('dbo.users', 'photo_face_reason')     IS NULL THEN 0 ELSE 1 END AS has_reason,
    CASE WHEN COL_LENGTH('dbo.users', 'photo_face_checked_at') IS NULL THEN 0 ELSE 1 END AS has_checked_at,
    CASE WHEN COL_LENGTH('dbo.users', 'photo_face_path')       IS NULL THEN 0 ELSE 1 END AS has_path;

/* How many accounts this actually concerns: rows carrying a picture file, which
   are the only ones the check ever runs on. Everything else (blank, or the
   legacy 'avatar.png' placeholder that has no file behind it) is already held by
   the existing missing-photo rule. */
SELECT
    COUNT(*) AS users_total,
    SUM(CASE WHEN LTRIM(RTRIM(COALESCE(profile, ''))) NOT IN ('', 'avatar.png')
              OR LTRIM(RTRIM(COALESCE(passport_photo_path, ''))) NOT IN ('', 'avatar.png')
             THEN 1 ELSE 0 END) AS users_with_a_picture_file
  FROM dbo.users;
