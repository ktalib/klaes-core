/*
  Occupancy Permits in PRA matched to File Indexing BY FILE TITLE
  ---------------------------------------------------------------
  Occupancy Permit rows in `pra` paired with the File Indexing record that carries
  the same holder name, restricted to files commissioned in the MLPP File Number
  Generator.

  The join key is the NAME, never the file number: an OP captured against a system
  temporary number (TEMP-xxxxxx) and the indexing record for the same property can
  hold different temporary numbers, so a file-number join drops the pair. The rule
  is therefore

        pra.party_2   ==   file_indexings.file_title      (normalised)

  party_2 is the OP's grantee / allottee (the permit holder); file_title is the name
  File Indexing captured for the file.

  "Commissioned in the MLPP File Number Generator" is the same test the generator
  page itself uses (FileNumberController::getData, source = 'New'):
      a fileNumber row stamped MLS_Commissioned / MLS_Commissioned_Batch + MlsFileNO,
      OR any non-temporary mls_file_no row for that number,
      OR a temporary file that only ever reached mls_file_no,
  with rows stamped system_sub_type = 'OSS' excluded -- those are One Stop Shop
  commissionings, which the generator list hides (App\Support\OssOpCommissionFilter).

  Only OPs CAPTURED in the current window are considered: pra.created_at from
  13-08-2026 up to and including today (@captured_from / @captured_to below). This is
  the capture timestamp -- when the row was keyed into KLAES -- not transaction_date,
  which is the date on the paper permit and is usually years older. Widen the window
  by editing the two DECLAREs; set either one to NULL to drop that bound.

  TRAP: pra.created_at is nvarchar(50), NOT a datetime, and it is not written in one
  format -- most rows are '2026-08-13 12:55:48.163' but a handful are 'Apr 17 2026
  4:07AM'. So the window must never be compared as text: '>= ''2026-08-13''' is a
  string comparison in which every 'Apr...' row sorts after every '2026...' one and
  is silently pulled in. TRY_CONVERT(DATE, ...) below parses the value instead, and
  returns NULL rather than failing the whole batch on a string it cannot read. The
  cost is that the predicate cannot seek idx_pra_created_at -- fine here, the table
  holds ~37k OP rows and this is an ad-hoc report, not a page query.

  Normalisation before two names are compared: upper-cased, '.' ',' '-' and quotes
  turned into spaces, runs of spaces collapsed, trimmed. The database collation is
  case-insensitive, so casing alone never splits a pair. Honorifics (ALH, MAL, ...)
  are NOT stripped -- see the relaxations at the bottom.

  Read-only. One row per (OP row, indexing record) pair.
*/

DECLARE @captured_from DATE = '2026-08-13';              /* first capture date to include */
DECLARE @captured_to   DATE = CAST(GETDATE() AS DATE);   /* today, inclusive              */

;WITH commissioned AS (
    /* Every file number the MLPP File Number Generator lists as commissioned. */
    SELECT fn.mlsfNo AS file_number
    FROM fileNumber fn
    WHERE (fn.is_deleted IS NULL OR fn.is_deleted = 0)
      AND NULLIF(LTRIM(RTRIM(fn.mlsfNo)), '') IS NOT NULL
      AND (
            (fn.SOURCE IN ('MLS_Commissioned', 'MLS_Commissioned_Batch') AND fn.type = 'MlsFileNO')
            OR EXISTS (
                SELECT 1 FROM mls_file_no ms
                WHERE ms.full_file_number = fn.mlsfNo
                  AND (ms.is_deleted IS NULL OR ms.is_deleted = 0)
                  AND LOWER(ISNULL(LTRIM(RTRIM(ms.file_option)), '')) <> 'temporary'
            )
      )
      /* One Stop Shop commissionings are not generator files. */
      AND NOT EXISTS (
            SELECT 1 FROM mls_file_no oss
            WHERE oss.full_file_number = fn.mlsfNo
              AND oss.system_sub_type = 'OSS'
      )

    UNION

    /* Temporary files live only in mls_file_no; the generator lists them too. */
    SELECT m.full_file_number AS file_number
    FROM mls_file_no m
    WHERE m.file_option = 'temporary'
      AND (m.is_deleted IS NULL OR m.is_deleted = 0)
      AND ISNULL(m.system_sub_type, '') <> 'OSS'
      AND NOT EXISTS (
            SELECT 1 FROM fileNumber fn2
            WHERE fn2.mlsfNo = m.full_file_number
              AND (fn2.is_deleted IS NULL OR fn2.is_deleted = 0)
      )
),

op AS (
    /* Occupancy Permit rows in PRA. A row whose type says Transfer of Title is the
       transfer OFF the permit, not the permit itself -- excluded. */
    SELECT
        p.id,
        p.party_1,
        p.party_2,
        p.fileno,
        p.mlsFNo,
        p.temp_fileno,
        p.prop_id,
        p.transaction_date,
        p.instrument_type,
        p.transaction_type,
        p.op_serial_number,
        p.op_type,
        p.regNo,
        p.source,
        p.created_at,
        REPLACE(REPLACE(REPLACE(
            UPPER(LTRIM(RTRIM(
                REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(p.party_2, '.', ' '), ',', ' '), '-', ' '), CHAR(39), ' '), '"', ' ')
            ))),
        '  ', ' ' + CHAR(1)), CHAR(1) + ' ', ''), CHAR(1), '') AS holder_key
    FROM pra p
    WHERE ISNULL(p.is_deleted, 0) = 0
      AND (p.transaction_type LIKE '%Occupancy Permit%' OR p.instrument_type LIKE '%Occupancy Permit%')
      AND ISNULL(p.instrument_type, '')  NOT LIKE '%Transfer of Title%'
      AND ISNULL(p.transaction_type, '') NOT LIKE '%Transfer of Title%'
      AND NULLIF(LTRIM(RTRIM(p.party_2)), '') IS NOT NULL
      /* Capture window -- see the TRAP note in the header: created_at is nvarchar
         holding mixed formats, so it is parsed, never string-compared. Both bounds
         are inclusive days: a row keyed at 23:06 on @captured_to still counts. */
      AND (@captured_from IS NULL OR TRY_CONVERT(DATE, p.created_at) >= @captured_from)
      AND (@captured_to   IS NULL OR TRY_CONVERT(DATE, p.created_at) <= @captured_to)
),

idx AS (
    /* File Indexing records for generator-commissioned files only. */
    SELECT
        f.id,
        f.file_number,
        f.file_title,
        f.temp_file_no,
        f.prop_id,
        f.plot_number,
        f.district,
        f.lga,
        f.registry,
        REPLACE(REPLACE(REPLACE(
            UPPER(LTRIM(RTRIM(
                REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(f.file_title, '.', ' '), ',', ' '), '-', ' '), CHAR(39), ' '), '"', ' ')
            ))),
        '  ', ' ' + CHAR(1)), CHAR(1) + ' ', ''), CHAR(1), '') AS title_key
    FROM file_indexings f
    JOIN commissioned c ON c.file_number = f.file_number
    WHERE ISNULL(f.is_deleted, 0) = 0
      AND NULLIF(LTRIM(RTRIM(f.file_title)), '') IS NOT NULL
)

SELECT
    idx.file_number                                   AS indexed_file_number,
    idx.file_title                                    AS indexing_name,
    op.party_2                                        AS op_holder,
    op.id                                             AS op_pra_id,

    /* The OP's own numbers -- kept in view because they are what a file-number join
       would have used, and they routinely disagree with indexed_file_number. */
    op.fileno                                         AS op_fileno,
    op.mlsFNo                                         AS op_mlsfno,
    op.temp_fileno                                    AS op_temp_fileno,
    idx.temp_file_no                                  AS indexing_temp_fileno,
    CASE
        WHEN idx.file_number IN (op.mlsFNo, op.fileno)                 THEN 'same file number'
        WHEN NULLIF(idx.temp_file_no, '') = NULLIF(op.temp_fileno, '') THEN 'same temp number'
        ELSE 'different file numbers - matched on name only'
    END                                               AS number_agreement,

    op.created_at                                     AS op_captured_at,
    op.transaction_date                               AS op_date,
    op.op_serial_number,
    op.op_type,
    op.regNo                                          AS op_reg_no,
    op.instrument_type                                AS op_instrument_type,
    op.transaction_type                               AS op_transaction_type,
    op.source                                         AS op_source,
    op.party_1                                        AS op_grantor,
    op.prop_id                                        AS op_prop_id,
    idx.prop_id                                       AS indexing_prop_id,
    CASE WHEN ISNULL(CAST(op.prop_id AS VARCHAR(50)), '#') = ISNULL(CAST(idx.prop_id AS VARCHAR(50)), '~')
         THEN 'yes' ELSE 'no' END                     AS same_prop_id,

    idx.id                                            AS indexing_id,
    idx.plot_number,
    idx.district,
    idx.lga,
    idx.registry,

    /* How clean the pairing is. A common name can carry several OPs and several
       commissioned files, and every combination comes back as its own row -- these
       two counts say when that has happened, so an ambiguous name is never read as
       a confirmed one-to-one match. */
    COUNT(*) OVER (PARTITION BY op.id)                AS files_matching_this_op,
    COUNT(*) OVER (PARTITION BY idx.id)               AS ops_matching_this_file,
    CASE WHEN COUNT(*) OVER (PARTITION BY op.id) = 1
          AND COUNT(*) OVER (PARTITION BY idx.id) = 1
         THEN 'unique' ELSE 'ambiguous' END           AS match_quality

FROM op
JOIN idx ON idx.title_key = op.holder_key

ORDER BY match_quality, idx.file_number, op.id;

/*
  Relaxations, if the strict name match returns too little:

  1. Honorifics -- strip a leading ALH / ALHAJI / MAL / MALLAM / MR / MRS / DR /
     ENGR before comparing. App\Services\OpHolderMatchService::HONORIFICS holds the
     list this system uses (MOHD/MUHD included).

  2. Spelling -- replace the join with
         JOIN idx ON DIFFERENCE(idx.title_key, op.holder_key) = 4
     (SQL Server soundex; 4 = the two names sound identical). This is what finds the
     OZATAMGBO / OZOTAMGBO class of pair, and every hit needs a human to confirm it.

  3. The opposite question -- files whose OP holder is NOT the indexed holder, i.e.
     the missing Transfer of Title -- is answered by
     database/sql/2026_08_29_op_holder_vs_indexing_check.sql, which joins on file
     number and looks for the mismatch.
*/
