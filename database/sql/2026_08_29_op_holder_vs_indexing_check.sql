/*
  OP holder vs File Indexing name — RofO Recommendation files
  ------------------------------------------------------------
  Every file that

    1. has a record in land_recommendations (the RofO Recommendation table), AND
    2. has a record in file_indexings, AND
    3. has an Occupancy Permit record in pra,

  where the OP's party_2 is NOT the file_title held in File Indexing, and no other
  OP or Transfer of Title on the file names that file_title either.

  Only files with REAL missing data are returned. The `finding` column says which:

    NEEDS ToT — no transfer on file        the OP names one holder, Indexing names
                                           another, and nothing explains the move.
    BROKEN ToT — self transfer             a transfer exists but its two parties are
                                           the same name, so it bridges nothing.

  Files whose transfer is already on file and merely SPELT differently from the file
  title (OZATAMGBO/OZOTAMGBO, MOHD/MUHD, ...) are deliberately excluded — the dealing
  is recorded, and generating a ToT for them would put a second transfer on a file
  that already has one. Drop the last NOT EXISTS block to see them.

  Batch recommendations (rofo_batch_id / batch_mother_file_no) are excluded - a batch
  is a subdivision mother's children, which do not originate in OSS.

  Runs in ~10s. Read-only.
*/

SELECT
    fi.file_number,
    fi.file_title                                   AS indexing_name,
    op.party_2                                      AS op_holder,
    tot.party_1                                     AS tot_from,
    tot.party_2                                     AS tot_to,
    CASE
        WHEN tot.id IS NULL                                             THEN 'NEEDS ToT - no transfer on file'
        ELSE 'BROKEN ToT - self transfer'
    END                                             AS finding,
    DIFFERENCE(ISNULL(tot.party_2, ''), ISNULL(fi.file_title, ''))      AS soundex_match,   -- 4 = names sound identical
    op.id                                           AS op_pra_id,
    tot.id                                          AS tot_pra_id,
    fi.id                                           AS indexing_id,
    lr.id                                           AS recommendation_id,
    lr.type                                         AS rec_type,
    lr.status                                       AS rec_status,
    lr.rofo_status,
    op.transaction_date                             AS op_date,
    op.prop_id
FROM land_recommendations lr
JOIN file_indexings fi
       ON fi.file_number = lr.file_number
      AND ISNULL(fi.is_deleted, 0) = 0

/* the file's Occupancy Permit (earliest, if it carries more than one) */
CROSS APPLY (
    SELECT TOP 1 p.*
    FROM pra p
    WHERE lr.file_number IN (p.mlsFNo, p.fileno, p.kangisFileNo, p.NewKANGISFileno)
      AND ISNULL(p.is_deleted, 0) = 0
      AND (p.transaction_type LIKE '%Occupancy Permit%' OR p.instrument_type LIKE '%Occupancy Permit%')
    ORDER BY p.id
) op

/* the file's Transfer of Title, if it has one at all */
OUTER APPLY (
    SELECT TOP 1 t.*
    FROM pra t
    WHERE lr.file_number IN (t.mlsFNo, t.fileno, t.kangisFileNo, t.NewKANGISFileno)
      AND ISNULL(t.is_deleted, 0) = 0
      AND (t.transaction_type LIKE '%Transfer of Title%' OR t.instrument_type LIKE '%Transfer of Title%')
    ORDER BY t.id DESC
) tot

WHERE
    /* Batch recommendations are out of scope: a batch is a subdivision mother's
       children, which do not come from OSS and inherit the mother's letter.
       (No batch row reaches this result set today - all 16 are filtered here
       purely so the rule is stated, not inferred.) */
    ISNULL(lr.rofo_batch_id, '') = ''
    AND ISNULL(lr.batch_mother_file_no, '') = ''

    /* the OP holder is not the indexed holder ... */
    AND LTRIM(RTRIM(op.party_2)) <> LTRIM(RTRIM(fi.file_title))

    /* ... and no OTHER OP on the file names the indexed holder either */
    AND NOT EXISTS (
        SELECT 1 FROM pra op2
        WHERE lr.file_number IN (op2.mlsFNo, op2.fileno, op2.kangisFileNo, op2.NewKANGISFileno)
          AND ISNULL(op2.is_deleted, 0) = 0
          AND (op2.transaction_type LIKE '%Occupancy Permit%' OR op2.instrument_type LIKE '%Occupancy Permit%')
          AND LTRIM(RTRIM(op2.party_2)) = LTRIM(RTRIM(fi.file_title))
    )

    /* ... and no transfer on the file lands exactly on the indexed holder */
    AND NOT EXISTS (
        SELECT 1 FROM pra t2
        WHERE lr.file_number IN (t2.mlsFNo, t2.fileno, t2.kangisFileNo, t2.NewKANGISFileno)
          AND ISNULL(t2.is_deleted, 0) = 0
          AND (t2.transaction_type LIKE '%Transfer of Title%' OR t2.instrument_type LIKE '%Transfer of Title%')
          AND LTRIM(RTRIM(t2.party_2)) = LTRIM(RTRIM(fi.file_title))
    )

    /* ... and the file carries no working transfer at all.

       This is what leaves the spelling cases out. A transfer whose two parties are
       different names HAS moved the title — the only thing wrong with it is how the
       new holder's name is spelt, which is a correction, not missing data. Written as
       NOT EXISTS over every transfer on the file rather than against the one row the
       OUTER APPLY picked, so a file holding both a real transfer and a self-transfer
       is judged on the real one. */
    AND NOT EXISTS (
        SELECT 1 FROM pra t3
        WHERE lr.file_number IN (t3.mlsFNo, t3.fileno, t3.kangisFileNo, t3.NewKANGISFileno)
          AND ISNULL(t3.is_deleted, 0) = 0
          AND (t3.transaction_type LIKE '%Transfer of Title%' OR t3.instrument_type LIKE '%Transfer of Title%')
          AND LTRIM(RTRIM(t3.party_1)) <> LTRIM(RTRIM(t3.party_2))
    )

ORDER BY finding, fi.file_number;
