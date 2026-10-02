/*
================================================================================
 Fix: file_indexings.prop_id holds a sequential migration artifact, not the
      real property id — causing Legal Search to bleed foreign files into a
      searched file's timeline.
================================================================================

 SYMPTOM
   Searching CON-AG-1982-172 (Dandalama Village, Dawakin Tofa, farm land)
   surfaced CON-RES-1984-576 (Gunduwawa, Gezawa, plot 114) with an unrelated
   holder. The two files share no related_fileno, no parent_prop_id, no party.

 ROOT CAUSE
   LegalSearchService seeds its prop_id cross-table expansion from the searched
   file's file_indexings row (app/Services/LegalSearchService.php:96-107).
   file_indexings id 25986 (CON-AG-1982-172) carries prop_id 12281, but 12281
   is CON-RES-1984-576's prop_id in PropID_Master. The expansion therefore
   pulls CofO_staging id 17803 into the timeline, and the contamination guard
   (line ~339) cannot reject it because 12281 arrived AS one of the searched
   file's own prop_ids.

   The corruption is systematic, not a one-off: file_indexings.prop_id tracks
   the row's own id (offset clusters around -13,700), e.g.

       id=25984  CON-AG-1982-136       prop_id=12279   master=6895
       id=25986  CON-AG-1982-172       prop_id=12281   master=6913
       id=25987  CON-RES-RC-1982-662   prop_id=12282   master=18379

   Of 10,964 indexed files that resolve to a single PropID_Master prop_id,
   only 33 currently agree. 10,931 are wrong.

 SCOPE OF THIS SCRIPT
   Repairs ONLY rows that are (a) non-NULL, (b) resolve to exactly one
   PropID_Master prop_id, and (c) currently disagree with it.  => 10,931 rows.

   It deliberately does NOT populate the ~54,787 rows where prop_id IS NULL.
   Filling those would newly ACTIVATE prop_id expansion for 55k files that
   currently skip it — a behavioural change, not a conflict repair. Section 6
   is provided for that, commented out, to be run only after separate review.

 CONNECTION
   SQL Server (the 'sqlsrv' connection) — this is where the tables live.
   Run in SSMS / sqlcmd against the klas database. Not a migration; there is
   no artisan ledger entry to add.

 HOW TO RUN
   Sections 0-2 are read-only. Inspect their output, then run 3-5.
================================================================================
*/

SET NOCOUNT ON;
GO

/* ---------------------------------------------------------------------------
   0. BACKUP — mandatory. Keep this table until the fix is confirmed in the UI.
   --------------------------------------------------------------------------- */
IF OBJECT_ID('dbo.file_indexings_prop_id_backup_20260810', 'U') IS NOT NULL
    DROP TABLE dbo.file_indexings_prop_id_backup_20260810;

SELECT
    id,
    file_number,
    prop_id       AS prop_id_old,
    parent_prop_id,
    ancestral_prop_id,
    SYSDATETIME() AS backed_up_at
INTO dbo.file_indexings_prop_id_backup_20260810
FROM dbo.file_indexings
WHERE deleted_at IS NULL
  AND prop_id IS NOT NULL
  AND LTRIM(RTRIM(prop_id)) <> '';

PRINT 'Backed up ' + CAST(@@ROWCOUNT AS VARCHAR(20)) + ' rows.';
GO


/* ---------------------------------------------------------------------------
   Shared resolution logic.
   A file_indexings row is matched to PropID_Master on ANY of the five file
   number columns, because an indexed file may be keyed by its MLS number,
   its KANGIS number, its New KANGIS number, or a temp number.
   PropID_Master.prop_id is INT; file_indexings.prop_id is NVARCHAR(100).
   Verified: every non-null file_indexings.prop_id is numeric and canonical
   (no leading zeros, no whitespace), so the CAST comparison below is safe.
   --------------------------------------------------------------------------- */


/* ---------------------------------------------------------------------------
   1. DRY RUN — preview exactly what section 3 will change.
   --------------------------------------------------------------------------- */
WITH cand AS (
    SELECT fi.id, pm.prop_id AS master
    FROM dbo.file_indexings fi
    JOIN dbo.PropID_Master pm
      ON  pm.status <> 'inactive'
      AND ( pm.primary_file_number = fi.file_number
         OR pm.mlsFNo              = fi.file_number
         OR pm.kangisFileNo        = fi.file_number
         OR pm.NewKANGISFileno     = fi.file_number
         OR pm.temp_fileno         = fi.file_number )
    WHERE fi.deleted_at IS NULL
      AND fi.prop_id IS NOT NULL
      AND LTRIM(RTRIM(fi.prop_id)) <> ''
),
agg AS (
    SELECT id, COUNT(DISTINCT master) AS n, MIN(master) AS master
    FROM cand
    GROUP BY id
)
SELECT
    fi.id,
    fi.file_number,
    fi.file_title,
    fi.district,
    fi.prop_id                       AS prop_id_current,
    CAST(a.master AS NVARCHAR(100))  AS prop_id_corrected
FROM agg a
JOIN dbo.file_indexings fi ON fi.id = a.id
WHERE a.n = 1
  AND CAST(fi.prop_id AS INT) <> a.master
ORDER BY fi.id;
-- Expect 10,931 rows.
GO


/* ---------------------------------------------------------------------------
   2. AMBIGUITY REPORT — files whose number resolves to MORE than one
      PropID_Master prop_id. These are SKIPPED by section 3 and need a human
      decision (they are genuine data conflicts, not migration artifacts).
   --------------------------------------------------------------------------- */
WITH cand AS (
    SELECT fi.id, fi.file_number, pm.prop_id AS master
    FROM dbo.file_indexings fi
    JOIN dbo.PropID_Master pm
      ON  pm.status <> 'inactive'
      AND ( pm.primary_file_number = fi.file_number
         OR pm.mlsFNo              = fi.file_number
         OR pm.kangisFileNo        = fi.file_number
         OR pm.NewKANGISFileno     = fi.file_number
         OR pm.temp_fileno         = fi.file_number )
    WHERE fi.deleted_at IS NULL
)
SELECT
    id,
    MIN(file_number) AS file_number,
    COUNT(DISTINCT master) AS distinct_master_prop_ids,
    STRING_AGG(CAST(master AS NVARCHAR(20)), ', ') WITHIN GROUP (ORDER BY master) AS candidates
FROM cand
GROUP BY id
HAVING COUNT(DISTINCT master) > 1
ORDER BY COUNT(DISTINCT master) DESC, id;
-- Expect 29 rows. Resolve these manually.
GO


/* ---------------------------------------------------------------------------
   3. THE FIX — transactional. Roll back if the affected count is unexpected.
   --------------------------------------------------------------------------- */
BEGIN TRANSACTION;

WITH cand AS (
    SELECT fi.id, pm.prop_id AS master
    FROM dbo.file_indexings fi
    JOIN dbo.PropID_Master pm
      ON  pm.status <> 'inactive'
      AND ( pm.primary_file_number = fi.file_number
         OR pm.mlsFNo              = fi.file_number
         OR pm.kangisFileNo        = fi.file_number
         OR pm.NewKANGISFileno     = fi.file_number
         OR pm.temp_fileno         = fi.file_number )
    WHERE fi.deleted_at IS NULL
      AND fi.prop_id IS NOT NULL
      AND LTRIM(RTRIM(fi.prop_id)) <> ''
),
agg AS (
    SELECT id, COUNT(DISTINCT master) AS n, MIN(master) AS master
    FROM cand
    GROUP BY id
)
UPDATE fi
SET fi.prop_id    = CAST(a.master AS NVARCHAR(100)),
    fi.updated_at = SYSDATETIME()
FROM dbo.file_indexings fi
JOIN agg a ON a.id = fi.id
WHERE a.n = 1
  AND CAST(fi.prop_id AS INT) <> a.master;

PRINT 'Rows corrected: ' + CAST(@@ROWCOUNT AS VARCHAR(20));

-- Inspect the printed count. If it is 10,931, COMMIT. Otherwise ROLLBACK.
-- COMMIT TRANSACTION;
-- ROLLBACK TRANSACTION;
GO


/* ---------------------------------------------------------------------------
   4. VERIFY — the reported case.
      Expected after the fix:
        CON-AG-1982-172  -> 6913
        CON-RES-1984-576 -> 12281
   --------------------------------------------------------------------------- */
SELECT
    fi.id,
    fi.file_number,
    fi.district,
    fi.prop_id AS prop_id_now,
    (SELECT TOP 1 CAST(pm.prop_id AS NVARCHAR(20))
       FROM dbo.PropID_Master pm
      WHERE pm.primary_file_number = fi.file_number) AS master_prop_id
FROM dbo.file_indexings fi
WHERE fi.file_number IN ('CON-AG-1982-172', 'CON-RES-1984-576')
  AND fi.deleted_at IS NULL;
GO


/* ---------------------------------------------------------------------------
   5. VERIFY — global agreement rate. 'still_wrong' should be 0.
   --------------------------------------------------------------------------- */
WITH m AS (
    SELECT primary_file_number AS fn, MIN(prop_id) AS p, COUNT(DISTINCT prop_id) AS n
    FROM dbo.PropID_Master
    WHERE primary_file_number IS NOT NULL AND status <> 'inactive'
    GROUP BY primary_file_number
)
SELECT
    COUNT(*)                                                            AS checked,
    SUM(CASE WHEN CAST(fi.prop_id AS INT) =  m.p THEN 1 ELSE 0 END)     AS agree,
    SUM(CASE WHEN CAST(fi.prop_id AS INT) <> m.p THEN 1 ELSE 0 END)     AS still_wrong
FROM dbo.file_indexings fi
JOIN m ON m.fn = fi.file_number AND m.n = 1
WHERE fi.deleted_at IS NULL
  AND fi.prop_id IS NOT NULL
  AND LTRIM(RTRIM(fi.prop_id)) <> '';
GO


/* ---------------------------------------------------------------------------
   6. OPTIONAL / DO NOT RUN BLIND — backfill the ~54,787 NULL prop_id rows.

      This is NOT part of the conflict repair. Today a NULL prop_id makes
      LegalSearchService skip prop_id expansion for that file entirely; filling
      it in switches expansion ON for 55k files at once. Review the dry run and
      spot-check a sample of resulting timelines before committing.
   --------------------------------------------------------------------------- */
/*
WITH cand AS (
    SELECT fi.id, pm.prop_id AS master
    FROM dbo.file_indexings fi
    JOIN dbo.PropID_Master pm
      ON  pm.status <> 'inactive'
      AND ( pm.primary_file_number = fi.file_number
         OR pm.mlsFNo              = fi.file_number
         OR pm.kangisFileNo        = fi.file_number
         OR pm.NewKANGISFileno     = fi.file_number
         OR pm.temp_fileno         = fi.file_number )
    WHERE fi.deleted_at IS NULL
      AND (fi.prop_id IS NULL OR LTRIM(RTRIM(fi.prop_id)) = '')
),
agg AS (
    SELECT id, COUNT(DISTINCT master) AS n, MIN(master) AS master
    FROM cand
    GROUP BY id
)
UPDATE fi
SET fi.prop_id    = CAST(a.master AS NVARCHAR(100)),
    fi.updated_at = SYSDATETIME()
FROM dbo.file_indexings fi
JOIN agg a ON a.id = fi.id
WHERE a.n = 1;
*/


/* ---------------------------------------------------------------------------
   7. ROLLBACK (after commit) — restore from the section 0 backup.
   --------------------------------------------------------------------------- */
/*
UPDATE fi
SET fi.prop_id = b.prop_id_old
FROM dbo.file_indexings fi
JOIN dbo.file_indexings_prop_id_backup_20260810 b ON b.id = fi.id
WHERE ISNULL(fi.prop_id, '') <> ISNULL(b.prop_id_old, '');
*/
