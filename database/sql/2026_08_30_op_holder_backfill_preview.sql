/* ============================================================================
   OP-holder backfill — PREVIEW (read-only)
   ----------------------------------------------------------------------------
   TARGET: SQL SERVER. Changes nothing. Run this on PRODUCTION before
   `php artisan op-match:backfill` to see exactly which files it would touch and
   what each new row would say.

   One output row = one Transfer of Title the backfill would write:

       party_1 (Grantor)  = would_transfer_from   the Occupancy Permit's grantee
       party_2 (Grantee)  = would_transfer_to     the File Indexing file title
       transaction_type   = 'Transfer of Title (OP)'
       instrument_type    = 'Transfer of Title (OP)'
       regNo/serialNo/pageNo/volumeNo = 0/0/0, 0, 0, 0   (never registered)
       prop_id            = inherited from the OP (same parcel)
       source             = 'OP Holder Match'
       system_source      = 'OPHOLDERMATCH'
       every other column = copied from the OP row named in op_pra_id

   SCOPE: files that appear in land_recommendations and are NOT part of a batch.
   The whole estate has thousands of files in this state; the backfill deliberately
   does not touch a file nobody is working.

   WHAT IS EXCLUDED HERE, and why each one matters:
     - an OP that already names the file title                (nothing moved)
     - a Transfer of Title that already names the file title  (already bridged)
     - an ownership-changing dealing in ANY of the four registers that reaches
       the file title — Deed of Assignment, Conveyance, Gift, Vesting, Sale.
       On the dev copy this alone removed 492 files that a pra-only rule would
       have "repaired" twice.
     - files belonging to a BATCH recommendation (subdivision children)
     - names that merely SOUND alike (soundex_match = 4), which are one person
       spelt two ways: "ALH KABIRU USMAN KULO" against "KABIRU USMAN KULO"
       would otherwise be recorded as transferring a title to himself.

   ONE DIFFERENCE FROM THE COMMAND, in your favour: the command reads each file's
   chain through the Legal Search report engine, which resolves KANGIS aliases and
   related files that SQL cannot follow here. It therefore writes FEWER rows than
   this preview lists — never more. Treat this as the upper bound.

   Sanity checks worth running first:
       SELECT COUNT(*) FROM pra WHERE system_source = 'OPHOLDERMATCH';   -- 0 before the first run
       SELECT COUNT(*) FROM file_indexings WHERE ISNULL(is_deleted,0) = 0;
   ============================================================================ */

/* SCOPE. The backfill only ever touches files that carry a RofO recommendation:
   those are the files an officer is working, and the ones the Match button on the
   capture form would eventually offer anyway. Batch recommendations are excluded —
   a subdivision child's holder differs from the mother's grantee because of the
   subdivision, not because a transfer went unrecorded. */
WITH recommended AS (
    SELECT DISTINCT LTRIM(RTRIM(lr.file_number)) AS file_number
    FROM land_recommendations lr
    WHERE LTRIM(RTRIM(ISNULL(lr.file_number, ''))) <> ''
      AND ISNULL(lr.rofo_batch_id, '') = ''
      AND ISNULL(lr.batch_mother_file_no, '') = ''
),

indexed AS (
    SELECT
        LTRIM(RTRIM(fi.file_number)) AS file_number,
        LTRIM(RTRIM(fi.file_title))  AS file_title
    FROM file_indexings fi
    JOIN recommended r
      ON r.file_number = LTRIM(RTRIM(fi.file_number))
    WHERE ISNULL(fi.is_deleted, 0) = 0
      AND fi.file_title IS NOT NULL
      AND LTRIM(RTRIM(fi.file_title)) <> ''
),

/* Every live Occupancy Permit, keyed to each file number it names. */
ops AS (
    SELECT
        i.file_number,
        i.file_title,
        p.id           AS op_pra_id,
        p.party_1      AS op_grantor,
        p.party_2      AS op_holder,
        p.transaction_date,
        p.prop_id,
        ROW_NUMBER() OVER (PARTITION BY i.file_number ORDER BY p.id) AS rn
    FROM indexed i
    JOIN pra p
      ON i.file_number IN (p.mlsFNo, p.fileno, p.kangisFileNo, p.NewKANGISFileno)
     AND ISNULL(p.is_deleted, 0) = 0
     AND (p.transaction_type LIKE '%Occupancy Permit%' OR p.instrument_type LIKE '%Occupancy Permit%')
)

SELECT
    o.file_number,
    o.op_holder                                   AS would_transfer_from,   -- new party_1
    o.file_title                                  AS would_transfer_to,     -- new party_2
    'Transfer of Title (OP)'                      AS would_write_type,
    '0/0/0'                                       AS would_write_reg_no,
    o.op_pra_id                                   AS copied_from_pra_id,
    o.prop_id,
    o.transaction_date                            AS op_date,
    DIFFERENCE(o.op_holder, o.file_title)         AS soundex_match           -- 0-4; 4 was excluded below
FROM ops o
WHERE o.rn = 1                                    -- the earliest OP is the grant

  /* the OP does not already name the indexed holder */
  AND LTRIM(RTRIM(o.op_holder)) <> LTRIM(RTRIM(o.file_title))

  /* ... and neither does any other OP on the file */
  AND NOT EXISTS (
      SELECT 1 FROM pra p2
      WHERE o.file_number IN (p2.mlsFNo, p2.fileno, p2.kangisFileNo, p2.NewKANGISFileno)
        AND ISNULL(p2.is_deleted, 0) = 0
        AND (p2.transaction_type LIKE '%Occupancy Permit%' OR p2.instrument_type LIKE '%Occupancy Permit%')
        AND LTRIM(RTRIM(p2.party_2)) = LTRIM(RTRIM(o.file_title))
  )

  /* ... and no dealing in pra moves the title to the indexed holder */
  AND NOT EXISTS (
      SELECT 1 FROM pra p3
      WHERE o.file_number IN (p3.mlsFNo, p3.fileno, p3.kangisFileNo, p3.NewKANGISFileno)
        AND ISNULL(p3.is_deleted, 0) = 0
        AND LTRIM(RTRIM(p3.party_2)) = LTRIM(RTRIM(o.file_title))
        AND (p3.transaction_type LIKE '%Transfer of Title%' OR p3.instrument_type LIKE '%Transfer of Title%'
          OR p3.transaction_type LIKE '%Assignment%'       OR p3.instrument_type LIKE '%Assignment%'
          OR p3.transaction_type LIKE '%Conveyance%'       OR p3.instrument_type LIKE '%Conveyance%'
          OR p3.transaction_type LIKE '%Gift%'             OR p3.instrument_type LIKE '%Gift%'
          OR p3.transaction_type LIKE '%Vesting%'          OR p3.instrument_type LIKE '%Vesting%'
          OR p3.transaction_type LIKE '%Sale%'             OR p3.instrument_type LIKE '%Sale%')
  )

  /* ... nor in file_history_staging — this is the one a pra-only rule misses */
  AND NOT EXISTS (
      SELECT 1 FROM file_history_staging f
      WHERE o.file_number IN (f.mlsFNo, f.fileno, f.kangisFileNo, f.NewKANGISFileno)
        AND ISNULL(f.is_deleted, 0) = 0
        AND LTRIM(RTRIM(o.file_title)) IN (LTRIM(RTRIM(f.party_2)), LTRIM(RTRIM(f.Assignee)), LTRIM(RTRIM(f.Grantee)))
        AND (f.transaction_type LIKE '%Transfer of Title%' OR f.instrument_type LIKE '%Transfer of Title%'
          OR f.transaction_type LIKE '%Assignment%'        OR f.instrument_type LIKE '%Assignment%'
          OR f.transaction_type LIKE '%Conveyance%'        OR f.instrument_type LIKE '%Conveyance%'
          OR f.transaction_type LIKE '%Gift%'              OR f.instrument_type LIKE '%Gift%'
          OR f.transaction_type LIKE '%Vesting%'           OR f.instrument_type LIKE '%Vesting%'
          OR f.transaction_type LIKE '%Sale%'              OR f.instrument_type LIKE '%Sale%')
  )

  /* ... nor in deed_registrations */
  AND NOT EXISTS (
      SELECT 1 FROM deed_registrations d
      WHERE o.file_number IN (d.fileno, d.parent_fileno)
        AND ISNULL(d.is_deleted, 0) = 0
        AND LTRIM(RTRIM(d.grantee)) = LTRIM(RTRIM(o.file_title))
  )

  /* ... and the two names are not one person spelt twice */
  AND DIFFERENCE(o.op_holder, o.file_title) < 4

  /* ... and the file is not part of a BATCH recommendation.

     A subdivision batch is one grant split into plots: a child's holder differs
     from the mother's grantee because of the subdivision, not because a transfer
     went unrecorded, so writing one would invent a dealing that never happened.
     The Match button on the capture form stands down in batch mode for the same
     reason, and the backfill has to agree with it. */
  AND NOT EXISTS (
      SELECT 1 FROM land_recommendations lr
      WHERE LTRIM(RTRIM(lr.file_number)) = o.file_number
        AND (ISNULL(lr.rofo_batch_id, '') <> '' OR ISNULL(lr.batch_mother_file_no, '') <> '')
  )

ORDER BY o.file_number;
