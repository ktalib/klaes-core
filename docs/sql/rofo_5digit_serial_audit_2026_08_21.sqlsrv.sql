/* ============================================================================
   Land / OSS RofO — 5-digit "security paper code" audit                (sqlsrv)
   ----------------------------------------------------------------------------
   The column shown in red under the file number on the RofO Management table is
   land_recommendations.land_rofo_serial_no.

   It holds two different things:

     * a REAL security paper code, taken from the global_security_paper_codes
       pool by LandRofoController@assignSecurityPaperCode. Those are 6 digits
       (011091) and leave a trail: global_security_paper_codes.assigned_by /
       assigned_at, plus a security_codes row.

     * an OSS-generated SERIAL. The OSS recommendation save mints it itself —
       str_pad(MAX(serial) + 1, 5, '0') in
       LandsOneStopShop\ApplicationController (saveRecommendation, and again in
       recommendationStatus) — so it is 5 digits, was never in the paper pool,
       and no one entered it.

   Query 1 is the count by source and length: it should show every 5-digit value
   sitting on an OSS row.
   Queries 2 and 3 answer "who printed it" and "who entered the code" for the
   5-digit ones — expect the entered-by columns to come back NULL, because the
   backend generated them.
   ========================================================================== */


/* -- 1. Shape of the problem: how many, which source, what length ---------- */
SELECT
    ISNULL(NULLIF(LTRIM(RTRIM(lr.type)), ''), 'LAND')          AS source,
    LEN(LTRIM(RTRIM(lr.land_rofo_serial_no)))                  AS code_length,
    COUNT(*)                                                   AS records,
    MIN(LTRIM(RTRIM(lr.land_rofo_serial_no)))                  AS lowest_code,
    MAX(LTRIM(RTRIM(lr.land_rofo_serial_no)))                  AS highest_code
FROM land_recommendations lr
WHERE lr.land_rofo_serial_no IS NOT NULL
  AND LTRIM(RTRIM(lr.land_rofo_serial_no)) <> ''
GROUP BY ISNULL(NULLIF(LTRIM(RTRIM(lr.type)), ''), 'LAND'),
         LEN(LTRIM(RTRIM(lr.land_rofo_serial_no)))
ORDER BY source, code_length;


/* -- 2. The 5-digit rows: who printed, who entered the code ---------------- */
SELECT
    lr.id,
    lr.file_number,
    ISNULL(NULLIF(LTRIM(RTRIM(lr.type)), ''), 'LAND')          AS source,
    lr.applicant_name,
    LTRIM(RTRIM(lr.land_rofo_serial_no))                       AS code_on_row,

    /* Is it a real sheet from the pool, or something the backend made up? */
    CASE WHEN gs.paper_code IS NULL
         THEN 'NOT IN PAPER POOL - backend-generated serial'
         ELSE 'from global_security_paper_codes' END           AS code_origin,

    /* Who entered the security paper code (NULL = nobody did) */
    ent.username                                               AS code_entered_by,
    LTRIM(RTRIM(ISNULL(ent.first_name, '') + ' ' + ISNULL(ent.last_name, ''))) AS code_entered_by_name,
    gs.assigned_at                                             AS code_entered_at,

    /* Who printed it, first and last run */
    fp.first_printed_at,
    fpu.username                                               AS first_printed_by,
    LTRIM(RTRIM(ISNULL(fpu.first_name, '') + ' ' + ISNULL(fpu.last_name, ''))) AS first_printed_by_name,
    fp.print_type                                              AS first_print_type,
    lp.last_printed_at,
    lpu.username                                               AS last_printed_by,
    pc.print_runs,

    lr.rofo_print_count,
    lr.created_at                                              AS record_created_at,
    crt.username                                               AS record_created_by
FROM land_recommendations lr

LEFT JOIN global_security_paper_codes gs
       ON LTRIM(RTRIM(gs.paper_code)) = LTRIM(RTRIM(lr.land_rofo_serial_no))
LEFT JOIN users ent ON ent.id = gs.assigned_by
LEFT JOIN users crt ON crt.id = lr.created_by

OUTER APPLY (
    SELECT TOP 1 pl.created_at AS first_printed_at, pl.user_id, pl.print_type
    FROM print_logs pl
    WHERE pl.document_type = 'Land ROFO'
      AND UPPER(LTRIM(RTRIM(pl.reference_number))) = UPPER(LTRIM(RTRIM(lr.file_number)))
    ORDER BY pl.created_at ASC
) fp
LEFT JOIN users fpu ON fpu.id = fp.user_id

OUTER APPLY (
    SELECT TOP 1 pl.created_at AS last_printed_at, pl.user_id
    FROM print_logs pl
    WHERE pl.document_type = 'Land ROFO'
      AND UPPER(LTRIM(RTRIM(pl.reference_number))) = UPPER(LTRIM(RTRIM(lr.file_number)))
    ORDER BY pl.created_at DESC
) lp
LEFT JOIN users lpu ON lpu.id = lp.user_id

OUTER APPLY (
    SELECT COUNT(*) AS print_runs
    FROM print_logs pl
    WHERE pl.document_type = 'Land ROFO'
      AND UPPER(LTRIM(RTRIM(pl.reference_number))) = UPPER(LTRIM(RTRIM(lr.file_number)))
) pc

WHERE lr.land_rofo_serial_no IS NOT NULL
  AND LEN(LTRIM(RTRIM(lr.land_rofo_serial_no))) = 5
ORDER BY lr.id DESC;


/* -- 3. Same question, summarised per user -------------------------------- */
SELECT
    ISNULL(u.username, '(no print log)')                       AS printed_by,
    COUNT(*)                                                   AS five_digit_rofos,
    MIN(pl.created_at)                                         AS first_print,
    MAX(pl.created_at)                                         AS last_print
FROM land_recommendations lr
OUTER APPLY (
    SELECT TOP 1 p.user_id, p.created_at
    FROM print_logs p
    WHERE p.document_type = 'Land ROFO'
      AND UPPER(LTRIM(RTRIM(p.reference_number))) = UPPER(LTRIM(RTRIM(lr.file_number)))
    ORDER BY p.created_at ASC
) pl
LEFT JOIN users u ON u.id = pl.user_id
WHERE lr.land_rofo_serial_no IS NOT NULL
  AND LEN(LTRIM(RTRIM(lr.land_rofo_serial_no))) = 5
GROUP BY ISNULL(u.username, '(no print log)')
ORDER BY five_digit_rofos DESC;


/* -- 4. Proof either way: do any 5-digit values exist in the paper pool? --- */
SELECT
    LEN(LTRIM(RTRIM(paper_code)))                              AS code_length,
    COUNT(*)                                                   AS codes_in_pool,
    SUM(CASE WHEN is_used = 1 THEN 1 ELSE 0 END)               AS used
FROM global_security_paper_codes
GROUP BY LEN(LTRIM(RTRIM(paper_code)))
ORDER BY code_length;


/* -- 5. The tracking table's own view of these codes ----------------------- */
SELECT
    sc.security_paper_code,
    sc.file_number,
    sc.assigned_to,
    su.username                                                AS assigned_by,
    sc.assigned_at
FROM security_codes sc
LEFT JOIN users su ON su.id = sc.assigned_by
WHERE LEN(LTRIM(RTRIM(sc.security_paper_code))) = 5
ORDER BY sc.assigned_at DESC;
