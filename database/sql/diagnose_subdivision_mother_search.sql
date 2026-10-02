/* ============================================================================
   Why does a subdivided mother file return nothing in Legal Search?  READ-ONLY
   ----------------------------------------------------------------------------
   Set @File below and run the whole script. Nothing is written.

   WHAT WE ARE TESTING
   Legal Search only finds a file if at least ONE row is keyed to that file's own
   number in pra / file_history_staging / CofO_staging / deed_registrations.
   Verified on a dev repro: stamping file_indexings.prop_id alone still returned
   0 rows, so a missing own-number row is the whole story. Children are then
   pulled in by their parent_prop_id matching the mother's prop_id.

   Step 3 is the verdict: if TOTAL_OWN_ROWS = 0, that is why the search is empty.
   Step 5 then says whether an orphaned row exists that can simply be re-pointed.
   ========================================================================== */

DECLARE @File NVARCHAR(100) = 'CON-IND-2024-9';   -- <== mother file number

/* ---- 1. Does the mother still exist in the live tables? ------------------- */
SELECT '1. LIVE ROWS' AS step,
       (SELECT COUNT(*) FROM fileNumber      WHERE mlsfNo = @File)      AS fileNumber_rows,
       (SELECT COUNT(*) FROM file_indexings  WHERE file_number = @File) AS file_indexing_rows,
       (SELECT MAX(CAST(is_decommissioned AS INT)) FROM fileNumber     WHERE mlsfNo = @File)      AS fn_decommissioned,
       (SELECT MAX(CAST(is_decommissioned AS INT)) FROM file_indexings WHERE file_number = @File) AS fi_decommissioned,
       (SELECT MAX(prop_id) FROM file_indexings WHERE file_number = @File) AS fi_prop_id;

/* ---- 2. What prop_id does the mother resolve to? -------------------------- */
/* Same source order the Decommissioned Files screen uses for its PropID column. */
SELECT '2. PROP_ID SOURCES' AS step, src, prop_id
FROM (
    SELECT 'PropID_Master.primary_file_number' AS src, CAST(prop_id AS NVARCHAR(50)) AS prop_id FROM PropID_Master WHERE primary_file_number = @File
    UNION ALL SELECT 'PropID_Master.mlsFNo',            CAST(prop_id AS NVARCHAR(50)) FROM PropID_Master      WHERE mlsFNo = @File
    UNION ALL SELECT 'deprecated_records',              CAST(prop_id AS NVARCHAR(50)) FROM deprecated_records WHERE file_number = @File
    UNION ALL SELECT 'file_history_staging',            CAST(prop_id AS NVARCHAR(50)) FROM file_history_staging WHERE mlsfNo = @File
    UNION ALL SELECT 'CofO_staging',                    CAST(prop_id AS NVARCHAR(50)) FROM CofO_staging       WHERE mlsFNo = @File OR fileno = @File
    UNION ALL SELECT 'pra',                             CAST(prop_id AS NVARCHAR(50)) FROM pra                WHERE mlsFNo = @File OR fileno = @File
) s
WHERE prop_id IS NOT NULL AND LTRIM(RTRIM(prop_id)) <> ''
GROUP BY src, prop_id;

/* ---- 3. THE VERDICT: rows keyed to the mother's OWN number ---------------- */
SELECT '3. OWN-NUMBER ROWS' AS step,
       (SELECT COUNT(*) FROM pra                  WHERE mlsFNo = @File OR fileno = @File OR temp_fileno = @File) AS pra_rows,
       (SELECT COUNT(*) FROM file_history_staging WHERE mlsfNo = @File OR fileno = @File)                        AS file_history_rows,
       (SELECT COUNT(*) FROM CofO_staging         WHERE mlsFNo = @File OR fileno = @File)                        AS cofo_rows,
       (SELECT COUNT(*) FROM deed_registrations   WHERE fileno = @File)                                          AS deed_rows,
       (SELECT COUNT(*) FROM pra                  WHERE mlsFNo = @File OR fileno = @File OR temp_fileno = @File)
     + (SELECT COUNT(*) FROM file_history_staging WHERE mlsfNo = @File OR fileno = @File)
     + (SELECT COUNT(*) FROM CofO_staging         WHERE mlsFNo = @File OR fileno = @File)
     + (SELECT COUNT(*) FROM deed_registrations   WHERE fileno = @File)                                          AS TOTAL_OWN_ROWS;
/* TOTAL_OWN_ROWS = 0  ->  this is exactly why the mother search is empty.     */

/* ---- 4. Are the children linked correctly? -------------------------------- */
DECLARE @Prop NVARCHAR(50) = (
    SELECT TOP 1 CAST(prop_id AS NVARCHAR(50)) FROM PropID_Master
     WHERE (primary_file_number = @File OR mlsFNo = @File) AND prop_id IS NOT NULL
);

SELECT '4. CHILDREN' AS step,
       @Prop                                                                       AS mother_prop_id,
       (SELECT COUNT(*) FROM pra            WHERE parent_prop_id = @Prop)          AS pra_children,
       (SELECT COUNT(*) FROM file_indexings WHERE parent_prop_id = @Prop)          AS indexed_children,
       (SELECT COUNT(*) FROM decommissioned_files WHERE file_no = @File)           AS decommission_rows,
       (SELECT TOP 1 LEN(successor_file_no) - LEN(REPLACE(successor_file_no, ',', '')) + 1
          FROM decommissioned_files WHERE file_no = @File
         ORDER BY id DESC)                                                         AS successors_listed;

/* ---- 5. Is there an orphan row that can be re-pointed at the mother? ------ */
/* This is the shape found on the dev repro: the file's Occupancy Permit row sat
   under its TEMP number with mlsFNo empty, so the mother owned no row. Setting
   pra.mlsFNo to the mother's number restored the search (0 -> 8 rows).
   Any row listed here is a candidate; NONE means the mother genuinely has no
   transaction of its own and one has to be created, not re-pointed.           */
SELECT '5. ORPHAN CANDIDATES' AS step,
       id, mlsFNo, fileno, temp_fileno, prop_id, parent_prop_id,
       transaction_type, transaction_date, system_source
  FROM pra
 WHERE CAST(prop_id AS NVARCHAR(50)) = @Prop
   AND (mlsFNo IS NULL OR LTRIM(RTRIM(mlsFNo)) = '' OR mlsFNo <> @File)
 ORDER BY id;

/* ---- 6. Sample of the children, to confirm they are fine ----------------- */
SELECT TOP 5 '6. CHILD SAMPLE' AS step,
       p.mlsFNo, p.transaction_type, p.prop_id, p.parent_prop_id,
       (SELECT COUNT(*) FROM file_indexings fi WHERE fi.file_number = p.mlsFNo) AS has_indexing
  FROM pra p
 WHERE p.parent_prop_id = @Prop
 ORDER BY p.id;
