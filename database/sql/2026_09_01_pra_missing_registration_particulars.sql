/*
  PRA rows with no registration particulars and no dates
  ------------------------------------------------------
  Every `pra` record whose registration particulars are 0/0/0 (or null / blank)
  AND which carries no registration date, no transaction date and no time.

  Column notes -- all six of these columns are nvarchar, not date/time, so a blank
  is any of: NULL, '', whitespace, or a zero sentinel ('0', '00:00', '0/0/0'):

      regNo                          the particulars as one string, "serial/page/volume"
      serialNo, pageNo, volumeNo     the same three numbers held separately
      transaction_date               the date of the dealing
      reg_date  / deeds_date         registration date -- the register writes EITHER
                                     (InstrumentController treats a row as dated when
                                     deeds_date OR reg_date is set, so both must be
                                     blank before a row counts as undated)
      reg_time  / deeds_time         registration time, same pairing

  '0/0/0' is the register's own placeholder for "not registered yet", so the
  particulars test accepts any string that is only zeros and slashes: 0/0/0,
  00/00/00, 0, '' and NULL all qualify.

  Read-only.
*/

;WITH flagged AS (
    SELECT
        p.id,
        p.mlsFNo,
        p.fileno,
        p.temp_fileno,
        p.kangisFileNo,
        p.NewKANGISFileno,
        p.prop_id,
        p.party_1,
        p.party_2,
        p.instrument_type,
        p.transaction_type,
        p.regNo,
        p.serialNo,
        p.pageNo,
        p.volumeNo,
        p.transaction_date,
        p.reg_date,
        p.reg_time,
        p.deeds_date,
        p.deeds_time,
        p.source,
        p.system_source,
        p.created_at,
        p.date_created,
        p.created_by,

        /* --- registration particulars --- */
        CASE WHEN p.regNo IS NULL THEN 1 ELSE 0 END AS regno_is_null,
        CASE WHEN p.regNo IS NOT NULL
              AND REPLACE(REPLACE(REPLACE(LTRIM(RTRIM(p.regNo)), '0', ''), '/', ''), ' ', '') = ''
             THEN 1 ELSE 0 END AS regno_is_zero,
        CASE WHEN (p.serialNo IS NULL OR LTRIM(RTRIM(p.serialNo)) IN ('', '0', '00', '000'))
              AND (p.pageNo   IS NULL OR LTRIM(RTRIM(p.pageNo))   IN ('', '0', '00', '000'))
              AND (p.volumeNo IS NULL OR LTRIM(RTRIM(p.volumeNo)) IN ('', '0', '00', '000'))
             THEN 1 ELSE 0 END AS parts_are_blank,

        /* --- dates and times --- */
        CASE WHEN NULLIF(LTRIM(RTRIM(ISNULL(p.transaction_date, ''))), '') IS NULL
              OR LTRIM(RTRIM(p.transaction_date)) IN ('0', '-', 'N/A', 'NA', 'NULL', '0000-00-00', '00/00/0000')
              OR p.transaction_date LIKE '1900%'
             THEN 1 ELSE 0 END AS tx_date_blank,
        CASE WHEN NULLIF(LTRIM(RTRIM(ISNULL(p.reg_date, ''))), '') IS NULL
              OR LTRIM(RTRIM(p.reg_date)) IN ('0', '-', 'N/A', 'NA', 'NULL', '0000-00-00', '00/00/0000')
              OR p.reg_date LIKE '1900%'
             THEN 1 ELSE 0 END AS reg_date_blank,
        CASE WHEN NULLIF(LTRIM(RTRIM(ISNULL(p.deeds_date, ''))), '') IS NULL
              OR LTRIM(RTRIM(p.deeds_date)) IN ('0', '-', 'N/A', 'NA', 'NULL', '0000-00-00', '00/00/0000')
              OR p.deeds_date LIKE '1900%'
             THEN 1 ELSE 0 END AS deeds_date_blank,
        CASE WHEN NULLIF(LTRIM(RTRIM(ISNULL(p.reg_time, ''))), '') IS NULL
              OR LTRIM(RTRIM(p.reg_time)) IN ('0', '-', 'N/A', 'NA', 'NULL', '00:00', '00:00:00')
             THEN 1 ELSE 0 END AS reg_time_blank,
        CASE WHEN NULLIF(LTRIM(RTRIM(ISNULL(p.deeds_time, ''))), '') IS NULL
              OR LTRIM(RTRIM(p.deeds_time)) IN ('0', '-', 'N/A', 'NA', 'NULL', '00:00', '00:00:00')
             THEN 1 ELSE 0 END AS deeds_time_blank
    FROM pra p
    WHERE ISNULL(p.is_deleted, 0) = 0

    /* ---- optional narrowing, uncomment as needed ----------------------------
    AND (p.transaction_type LIKE '%Occupancy Permit%' OR p.instrument_type LIKE '%Occupancy Permit%')
    AND p.created_at >= '2026-01-01'
    ------------------------------------------------------------------------- */
)

SELECT
    id                          AS pra_id,
    COALESCE(NULLIF(mlsFNo, ''), NULLIF(fileno, ''), NULLIF(temp_fileno, ''),
             NULLIF(kangisFileNo, ''), NULLIF(NewKANGISFileno, '')) AS file_number,
    temp_fileno,
    prop_id,
    party_1,
    party_2,
    instrument_type,
    transaction_type,

    ISNULL(regNo, '(null)')     AS reg_no,
    ISNULL(serialNo, '(null)')  AS serial_no,
    ISNULL(pageNo, '(null)')    AS page_no,
    ISNULL(volumeNo, '(null)')  AS volume_no,

    /* Which of the two ways the particulars are empty. */
    CASE WHEN regno_is_null = 1 THEN 'regNo NULL'
         WHEN regno_is_zero = 1 THEN 'regNo zeros (' + LTRIM(RTRIM(regNo)) + ')'
         ELSE 'regNo present, serial/page/volume all zero'
    END                         AS particulars_state,

    transaction_date,
    reg_date,
    deeds_date,
    reg_time,
    deeds_time,

    source,
    system_source,
    created_at,
    created_by
FROM flagged
WHERE
    /* particulars are 0/0/0, null or blank ... */
    (regno_is_null = 1 OR regno_is_zero = 1 OR parts_are_blank = 1)

    /* ... and nothing on the row carries a registration date, a transaction date
       or a time. Both storage columns of each pair must be empty. */
    AND tx_date_blank    = 1
    AND reg_date_blank   = 1
    AND deeds_date_blank = 1
    AND reg_time_blank   = 1
    AND deeds_time_blank = 1

ORDER BY id DESC;


/* ---------------------------------------------------------------------------
   Variant A -- count only, grouped by where the rows came from. Run this first
   to see which capture flow is leaving the particulars empty.

;WITH flagged AS ( ...same CTE as above... )
SELECT
    ISNULL(instrument_type, '(null)')          AS instrument_type,
    ISNULL(source, '(null)')                   AS source,
    ISNULL(system_source, '(null)')            AS system_source,
    COUNT(*)                                   AS rows_affected,
    MIN(created_at)                            AS first_seen,
    MAX(created_at)                            AS last_seen
FROM flagged
WHERE (regno_is_null = 1 OR regno_is_zero = 1 OR parts_are_blank = 1)
  AND tx_date_blank = 1 AND reg_date_blank = 1 AND deeds_date_blank = 1
  AND reg_time_blank = 1 AND deeds_time_blank = 1
GROUP BY instrument_type, source, system_source
ORDER BY rows_affected DESC;

   ---------------------------------------------------------------------------
   Variant B -- particulars empty but SOME date present (the partial captures).
   Swap the five date conditions for:

  AND (tx_date_blank = 0 OR reg_date_blank = 0 OR deeds_date_blank = 0)

   ---------------------------------------------------------------------------
   Variant C -- restrict to files commissioned in the MLPP File Number Generator
   by joining the `commissioned` CTE from
   database/sql/2026_09_01_op_pra_vs_indexing_by_file_title.sql on
   COALESCE(mlsFNo, fileno).
   --------------------------------------------------------------------------- */
