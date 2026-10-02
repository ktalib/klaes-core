/* ============================================================================
   Reset the wrong RofO "security paper codes"                          (sqlsrv)
   ----------------------------------------------------------------------------
   These OSS rows carry a backend-minted 5-digit serial in
   land_recommendations.land_rofo_serial_no, where a real 6-digit security paper
   code from global_security_paper_codes belongs:

       11094  11095  11096  11097  11098  11100  11101  11102

   11099 is deliberately NOT in the list.

   The statements below are keyed on those codes plus two guards, so nothing is
   matched by a file number typed from a screen:

       * the row must be an OSS row (type = 'OSS')
       * the code must NOT exist in global_security_paper_codes

   A row that has since been given a real pool code therefore cannot be caught by
   this, whatever its number looks like.

   READ THIS FIRST -- clearing the column does not hold on its own.
   LandsOneStopShop\ApplicationController@recommendationStatus re-mints a fresh
   5-digit serial for ANY OSS row whose land_rofo_serial_no is empty, the moment
   that application's recommendation modal is opened. Until that is changed, a
   cleared row will silently pick up a new wrong code (11103, 11104, ...). Either
   enter the real paper code straight after the reset, or have the re-mint removed
   first.
   ========================================================================== */


/* -- 1. BEFORE: what will be cleared, and what will be skipped -------------- */
SELECT
    lr.id,
    lr.file_number,
    ISNULL(NULLIF(LTRIM(RTRIM(lr.type)), ''), 'LAND')   AS source,
    lr.applicant_name,
    LTRIM(RTRIM(lr.land_rofo_serial_no))                AS code_on_row,
    CASE
        WHEN gs.paper_code IS NOT NULL                   THEN 'SKIPPED - real pool code'
        WHEN UPPER(ISNULL(lr.type, '')) <> 'OSS'         THEN 'SKIPPED - not an OSS row'
        ELSE 'will be cleared'
    END                                                 AS action,
    lr.rofo_print_count,
    lr.created_at                                       AS record_created_at
FROM land_recommendations lr
LEFT JOIN global_security_paper_codes gs
       ON LTRIM(RTRIM(gs.paper_code)) = LTRIM(RTRIM(lr.land_rofo_serial_no))
WHERE LTRIM(RTRIM(lr.land_rofo_serial_no)) IN
      ('11094', '11095', '11096', '11097', '11098', '11100', '11101', '11102')
ORDER BY LTRIM(RTRIM(lr.land_rofo_serial_no));

/* Expect 8 rows, all "will be cleared". Any other count, or any SKIPPED line,
   means stop and look before running step 2. */


/* -- 2. THE RESET -----------------------------------------------------------
   Run as one transaction. The UPDATE should report exactly the number of rows
   step 1 marked "will be cleared". If it reports anything else, ROLLBACK.
   -------------------------------------------------------------------------- */
BEGIN TRANSACTION;

UPDATE lr
   SET lr.land_rofo_serial_no = NULL,
       lr.updated_at          = GETDATE()
FROM land_recommendations lr
WHERE LTRIM(RTRIM(lr.land_rofo_serial_no)) IN
      ('11094', '11095', '11096', '11097', '11098', '11100', '11101', '11102')
  AND UPPER(ISNULL(lr.type, '')) = 'OSS'
  AND NOT EXISTS (
        SELECT 1 FROM global_security_paper_codes gs
        WHERE LTRIM(RTRIM(gs.paper_code)) = LTRIM(RTRIM(lr.land_rofo_serial_no))
      );

/* Housekeeping: these serials were never drawn from the pool, so normally there
   is nothing here to clean. Included because a code typed in by hand on another
   screen would leave a tracking row behind, and that row would keep the number
   looking assigned. Expect 0 rows affected. */
DELETE FROM security_codes
 WHERE LTRIM(RTRIM(security_paper_code)) IN
       ('11094', '11095', '11096', '11097', '11098', '11100', '11101', '11102');

-- Check the counts above, then:
COMMIT TRANSACTION;
-- ROLLBACK TRANSACTION;


/* -- 3. AFTER: nothing should come back ------------------------------------ */
SELECT id, file_number, land_rofo_serial_no, updated_at
FROM land_recommendations
WHERE LTRIM(RTRIM(land_rofo_serial_no)) IN
      ('11094', '11095', '11096', '11097', '11098', '11100', '11101', '11102');
/* Expect 0 rows. */


/* -- 4. Watch for the re-mint ----------------------------------------------
   Run this a day later. A 5-digit serial appearing above 11102 means a
   recommendation modal was opened and minted a new one.
   -------------------------------------------------------------------------- */
SELECT id, file_number, land_rofo_serial_no, updated_at
FROM land_recommendations
WHERE UPPER(ISNULL(type, '')) = 'OSS'
  AND land_rofo_serial_no IS NOT NULL
  AND LEN(LTRIM(RTRIM(land_rofo_serial_no))) = 5
  AND TRY_CONVERT(int, land_rofo_serial_no) >= 11094
ORDER BY TRY_CONVERT(int, land_rofo_serial_no);
