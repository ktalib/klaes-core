/*
================================================================================
 Targeted fix: prop_id collision cluster around CON-AG-1982-172
================================================================================

 Scoped alternative to 2026_08_10_fix_file_indexings_prop_id_conflicts.sql
 (which repairs all 10,931 corrupted rows). This script touches 4 rows only.

 THE CLUSTER
   Each file_indexings row below holds a prop_id that belongs to a DIFFERENT
   property, and they chain into each other:

     id     file_number            district              prop_id  ->  correct
     -----  --------------------  --------------------  -------  ---  -------
     25986  CON-AG-1982-172       DANDALAMA VILLAGE       12281   ->     6913
     31780  CON-RES-1984-576      GUNDUWAWA               17976   ->    12281
     20597  RES-1992-3157         K/WAIKA K/DAWANAU        6913   ->    39278
     20365  CON-RES-RC-1982-201   TARAUNI                  6681   ->    17976

   "correct" = PropID_Master.prop_id for that file number (all status=active,
   all single-valued, so there is no ambiguity to resolve here).

 WHY ALL FOUR
   Row 25986 alone is what puts CON-RES-1984-576 into CON-AG-1982-172's Legal
   Search timeline — fixing it stops the reported symptom.

   But 20597 is the same bug pointing the other way: RES-1992-3157 wrongly
   claims 6913, so searching RES-1992-3157 seeds CON-AG-1982-172's prop_id and
   drags ITS history into the wrong timeline. Same for 20365 vs 17976. Fixing
   only the reported direction leaves the mirror bleed live.

 SAFETY
   Verified: no unique index or constraint on file_indexings.prop_id, so the
   rows may be updated in a single statement — no ordering or staging needed
   despite 12281 and 6913 moving between rows.

 CONNECTION
   SQL Server ('sqlsrv' connection), database klas. Not a migration.
================================================================================
*/

SET NOCOUNT ON;
GO

/* ---------------------------------------------------------------------------
   0. BEFORE — record the current state. Keep this output.
   --------------------------------------------------------------------------- */
SELECT
    fi.id,
    fi.file_number,
    fi.file_title,
    fi.district,
    fi.prop_id AS prop_id_before,
    (SELECT TOP 1 CAST(pm.prop_id AS NVARCHAR(20))
       FROM dbo.PropID_Master pm
      WHERE pm.primary_file_number = fi.file_number
        AND pm.status <> 'inactive') AS prop_id_should_be
FROM dbo.file_indexings fi
WHERE fi.id IN (25986, 31780, 20597, 20365)
ORDER BY fi.id;
GO


/* ---------------------------------------------------------------------------
   1. THE FIX

      Ids are pinned AND the file_number is asserted in the same predicate, so
      the statement is inert if an id ever refers to a different row than it
      did when this script was written.
   --------------------------------------------------------------------------- */
BEGIN TRANSACTION;

UPDATE dbo.file_indexings
SET prop_id = '6913', updated_at = SYSDATETIME()
WHERE id = 25986 AND file_number = 'CON-AG-1982-172'     AND prop_id = '12281';

UPDATE dbo.file_indexings
SET prop_id = '12281', updated_at = SYSDATETIME()
WHERE id = 31780 AND file_number = 'CON-RES-1984-576'    AND prop_id = '17976';

-- The two mirror rows. Drop these if you want the reported direction only.
UPDATE dbo.file_indexings
SET prop_id = '39278', updated_at = SYSDATETIME()
WHERE id = 20597 AND file_number = 'RES-1992-3157'       AND prop_id = '6913';

UPDATE dbo.file_indexings
SET prop_id = '17976', updated_at = SYSDATETIME()
WHERE id = 20365 AND file_number = 'CON-RES-RC-1982-201' AND prop_id = '6681';

-- Each statement should report 1 row. If any reports 0, the data has moved
-- since this script was written — ROLLBACK and re-check section 0.
-- COMMIT TRANSACTION;
-- ROLLBACK TRANSACTION;
GO


/* ---------------------------------------------------------------------------
   2. AFTER — prop_id_now must equal prop_id_should_be on all four rows.
   --------------------------------------------------------------------------- */
SELECT
    fi.id,
    fi.file_number,
    fi.district,
    fi.prop_id AS prop_id_now,
    (SELECT TOP 1 CAST(pm.prop_id AS NVARCHAR(20))
       FROM dbo.PropID_Master pm
      WHERE pm.primary_file_number = fi.file_number
        AND pm.status <> 'inactive') AS prop_id_should_be
FROM dbo.file_indexings fi
WHERE fi.id IN (25986, 31780, 20597, 20365)
ORDER BY fi.id;
GO


/* ---------------------------------------------------------------------------
   3. CONFIRM IN THE UI
      Re-run Legal Search on CON-AG-1982-172. The timeline should drop to 4
      rows — the two CON-RES-1984-576 rows (its "File Commissioning 1984"
      synthetic header and its Certificate of Occupancy, reg 268/268/32,
      Alhaji Bello Ahmed) must be gone. Rows 1-4 are genuine and stay:
        1  File Commissioning 1982
        2  Deed of Assignment   197/197/2   Idi Bako -> Dantata
        3  Assignment           297/297/66  Dantata  -> Sadi Hamisu Zakari
        4  CofO                 217/217/9   Kano State Govt -> Idi Bako
   --------------------------------------------------------------------------- */


/* ---------------------------------------------------------------------------
   4. ROLLBACK (after commit) — restores the pre-fix values verbatim.
   --------------------------------------------------------------------------- */
/*
UPDATE dbo.file_indexings SET prop_id = '12281' WHERE id = 25986;
UPDATE dbo.file_indexings SET prop_id = '17976' WHERE id = 31780;
UPDATE dbo.file_indexings SET prop_id = '6913'  WHERE id = 20597;
UPDATE dbo.file_indexings SET prop_id = '6681'  WHERE id = 20365;
*/
