/* ============================================================================
   KANGIS FileNo  <->  Land FileNo  reverse-link backfill      (SQL Server)
   ----------------------------------------------------------------------------
   The edge is currently one-way: the KANGIS-registry row in file_indexings
   carries the land file number in related_fileno (JSON array), e.g.

        file_number = 'KNML 1093'   related_fileno = ["RES-2008-3663"]

   ...but the land file's own row does not name the KANGIS number back:

        file_number = 'RES-2008-3663'   related_fileno = [] / NULL / [other]

   STEP 1 builds the working set into #temp tables (run once per session)
   STEP 2 / 3 are read-only reports over that set
   STEP 4 writes the backfill, inside an open transaction

   Why temp tables: the first all-in-one CTE version re-parsed the JSON of every
   one of the ~133k file_indexings rows once per output row. Same result,
   seconds instead of minutes.
   ============================================================================ */


-- ============================================================================
-- STEP 1 - build the working set   (run once; the other steps reuse it)
-- ============================================================================
IF OBJECT_ID('tempdb..#kangis_pairs') IS NOT NULL DROP TABLE #kangis_pairs;
IF OBJECT_ID('tempdb..#land_keys')    IS NOT NULL DROP TABLE #land_keys;
IF OBJECT_ID('tempdb..#matched')      IS NOT NULL DROP TABLE #matched;
IF OBJECT_ID('tempdb..#to_add')       IS NOT NULL DROP TABLE #to_add;
IF OBJECT_ID('tempdb..#merged')       IS NOT NULL DROP TABLE #merged;

/* 1a. One row per (KANGIS file, land file) pair named in the KANGIS row.
       'KN%' / 'MLKN%' tokens are skipped - those are KANGIS numbers, not land
       files. '(T)' temp-number variants are normalised to the base number. */
SELECT  fi.id                                        AS kangis_id,
        LTRIM(RTRIM(fi.file_number))                 AS kangis_fileno,
        CONVERT(nvarchar(255), LTRIM(RTRIM(j.[value])))              AS land_fileno,
        CONVERT(nvarchar(255),
            UPPER(REPLACE(REPLACE(LTRIM(RTRIM(j.[value])), '(T)', ''), '( T )', '')))
                                                                     AS land_key
INTO    #kangis_pairs
FROM    dbo.file_indexings fi
CROSS APPLY OPENJSON(fi.related_fileno) j
WHERE   fi.general_registry LIKE 'KANGIS%'
  AND   ISJSON(fi.related_fileno) = 1
  AND   LTRIM(RTRIM(j.[value])) <> ''
  AND   LTRIM(RTRIM(j.[value])) NOT LIKE 'KN%'
  AND   LTRIM(RTRIM(j.[value])) NOT LIKE 'MLKN%';

CREATE INDEX ix_pairs_key ON #kangis_pairs (land_key);

/* 1b. Normalised key for every indexed file, so the join below is an index seek
       instead of a scan with UPPER()/TRIM() wrapped round file_number. */
SELECT  land.id                                      AS land_id,
        CONVERT(nvarchar(255), UPPER(LTRIM(RTRIM(land.file_number)))) AS land_key,
        land.file_number,
        land.general_registry,
        land.related_fileno
INTO    #land_keys
FROM    dbo.file_indexings land;

CREATE INDEX ix_landkeys_key ON #land_keys (land_key);
CREATE INDEX ix_landkeys_id  ON #land_keys (land_id);

/* 1c. Pairs resolved to a real land row.
       The match is by FILE NUMBER, so a KANGIS row pointing at a SIT/SLTR file
       resolves too (3 such rows at time of writing). Uncomment the WHERE to
       restrict the backfill to Lands Registry targets only. */
SELECT  DISTINCT l.land_id, k.kangis_fileno
INTO    #matched
FROM    #kangis_pairs k
JOIN    #land_keys l ON l.land_key = k.land_key
-- WHERE   l.general_registry LIKE 'Land%'    -- Lands Registry targets only
;

CREATE INDEX ix_matched ON #matched (land_id);

/* 1d. Drop the ones the land row already lists -> what actually needs writing. */
SELECT  m.land_id, m.kangis_fileno
INTO    #to_add
FROM    #matched m
WHERE   NOT EXISTS (
            SELECT 1
            FROM   #land_keys l
            CROSS APPLY OPENJSON(l.related_fileno) j
            WHERE  l.land_id = m.land_id
              AND  ISJSON(l.related_fileno) = 1
              AND  UPPER(LTRIM(RTRIM(j.[value]))) = UPPER(m.kangis_fileno)
        );

CREATE INDEX ix_toadd ON #to_add (land_id);

/* 1e. Rebuild each affected land row's array: existing tokens UNION new ones. */
SELECT  t.land_id,
        '[' + STRING_AGG('"' + STRING_ESCAPE(v.tok, 'json') + '"', ',')
                WITHIN GROUP (ORDER BY v.tok) + ']'  AS new_json
INTO    #merged
FROM   (SELECT DISTINCT land_id FROM #to_add) t
CROSS APPLY (
        SELECT LTRIM(RTRIM(j.[value])) AS tok
        FROM   #land_keys l
        CROSS APPLY OPENJSON(l.related_fileno) j
        WHERE  l.land_id = t.land_id
          AND  ISJSON(l.related_fileno) = 1
          AND  LTRIM(RTRIM(j.[value])) <> ''
        UNION
        SELECT a.kangis_fileno FROM #to_add a WHERE a.land_id = t.land_id
) v
GROUP BY t.land_id;

CREATE INDEX ix_merged ON #merged (land_id);

SELECT  (SELECT COUNT(*) FROM #kangis_pairs)                    AS kangis_land_pairs,
        (SELECT COUNT(*) FROM #matched)                         AS pairs_resolved_to_a_land_row,
        (SELECT COUNT(*) FROM #to_add)                          AS links_to_add,
        (SELECT COUNT(*) FROM #matched) - (SELECT COUNT(*) FROM #to_add)
                                                                AS already_linked,
        (SELECT COUNT(*) FROM #merged)                          AS land_rows_to_update;
GO


-- ============================================================================
-- STEP 2 - the query: every KANGIS FileNo with its Land FileNo + what happens
-- ============================================================================
/* Column meanings:
     kangis_file_no          - the KANGIS file (KN.. / KNML.. / MLKN..) doing the pointing
     land_file_no            - the land file it names inside its related_fileno
     land_file_currently_has - what that LAND file's own related_fileno holds right now
     action                  - what STEP 4 will do about this pair                     */
SELECT      k.kangis_fileno                          AS kangis_file_no,
            k.land_fileno                            AS land_file_no,
            l.related_fileno                         AS land_file_currently_has,
            CASE
                WHEN l.land_id IS NULL          THEN 'LAND FILE NOT INDEXED - nothing to write to'
                WHEN a.land_id IS NOT NULL      THEN 'WILL BE BACKFILLED'
                ELSE                                 'ALREADY LINKED - skipped'
            END                                      AS action,
            k.kangis_id                              AS kangis_row_id,
            l.land_id                                AS land_row_id,
            l.general_registry                       AS land_registry
FROM        #kangis_pairs k
LEFT JOIN   #land_keys l ON l.land_key = k.land_key
LEFT JOIN   #to_add    a ON a.land_id  = l.land_id
                        AND a.kangis_fileno = k.kangis_fileno
ORDER BY    action, k.land_fileno, k.kangis_fileno;
GO


-- ============================================================================
-- STEP 3 - preview: ONLY the land rows STEP 4 will change (before vs after)
-- ============================================================================
/* Column meanings:
     land_file_no     - the land file being edited
     kangis_no_to_add - the KANGIS number(s) missing from it
     before / after   - its related_fileno now, and what STEP 4 will set it to,
                        as a plain comma list; NULL means the field is empty    */
SELECT      l.file_number                            AS land_file_no,
            add_list.kangis_no_to_add,
            b.readable                               AS [before],
            a.readable                               AS [after],
            l.general_registry                       AS land_registry
FROM        #merged m
JOIN        #land_keys l ON l.land_id = m.land_id
CROSS APPLY (
            SELECT STRING_AGG(t.kangis_fileno, ', ')
                     WITHIN GROUP (ORDER BY t.kangis_fileno) AS kangis_no_to_add
            FROM   #to_add t
            WHERE  t.land_id = m.land_id
) add_list
/* JSON array -> "A, B, C" for reading.  The CASE keeps OPENJSON from erroring on
   the rows whose related_fileno is NULL or a legacy CSV string. */
OUTER APPLY (
            SELECT STRING_AGG(LTRIM(RTRIM(j.[value])), ', ')
                     WITHIN GROUP (ORDER BY LTRIM(RTRIM(j.[value]))) AS readable
            FROM   OPENJSON(CASE WHEN ISJSON(l.related_fileno) = 1
                                 THEN l.related_fileno ELSE '[]' END) j
            WHERE  LTRIM(RTRIM(j.[value])) <> ''
) b
OUTER APPLY (
            SELECT STRING_AGG(LTRIM(RTRIM(j.[value])), ', ')
                     WITHIN GROUP (ORDER BY LTRIM(RTRIM(j.[value]))) AS readable
            FROM   OPENJSON(m.new_json) j
) a
ORDER BY    l.file_number;
GO


-- ============================================================================
-- STEP 4 - backfill.  Appends the missing KANGIS numbers to the land row's
--          related_fileno, keeping whatever was already there. Idempotent.
-- ============================================================================
BEGIN TRANSACTION;

UPDATE  land
SET     land.related_fileno = m.new_json,
        land.updated_at     = SYSDATETIME()
FROM    dbo.file_indexings land
JOIN    #merged m ON m.land_id = land.id;

SELECT @@ROWCOUNT AS land_rows_updated;

-- ROLLBACK TRANSACTION;   -- while testing
COMMIT TRANSACTION;
GO
