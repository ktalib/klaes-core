/* ============================================================================
   Security paper code reset reasons — SQL SERVER schema change
   ----------------------------------------------------------------------------
   RUN THIS FIRST, against SQL SERVER (`klas`). Then run the companion
   database/sql/2026_08_12_add_void_status_to_global_security_paper_codes_ledger.mysql.sql
   against MYSQL to register the migration.

   WHY
   Resetting a security paper code now records why. The three reasons do not
   mean the same thing for the physical sheet:

     mistake_output – printed wrong / mutilated. The sheet is destroyed, so the
                      code is retired and must never return to the pool.
     spc_mismatch   – wrong code keyed in; the sheet is intact, code returns.
     drop_spc       – code detached from the record; code returns.

   HOW THE POOL STAYS CORRECT
   A voided code deliberately keeps is_used = 1, so every existing pool query
   (`WHERE is_used = 0` in the Land RofO / ST RofO / SLTR controllers and the
   shared /security-paper-codes/available endpoint) excludes it with no change.

   SAFETY
     - Re-runnable: every ALTER is guarded by a COL_LENGTH check.
     - Additive only; no existing column or row is modified except the one-off
       status backfill, which only fills NULLs.
   ============================================================================ */

/* Preview — expect 5 zeros before the first run */
SELECT
    CASE WHEN COL_LENGTH('dbo.global_security_paper_codes', 'status')      IS NULL THEN 0 ELSE 1 END AS has_status,
    CASE WHEN COL_LENGTH('dbo.global_security_paper_codes', 'void_reason') IS NULL THEN 0 ELSE 1 END AS has_void_reason,
    CASE WHEN COL_LENGTH('dbo.global_security_paper_codes', 'void_note')   IS NULL THEN 0 ELSE 1 END AS has_void_note,
    CASE WHEN COL_LENGTH('dbo.global_security_paper_codes', 'voided_by')   IS NULL THEN 0 ELSE 1 END AS has_voided_by,
    CASE WHEN COL_LENGTH('dbo.global_security_paper_codes', 'voided_at')   IS NULL THEN 0 ELSE 1 END AS has_voided_at;

IF COL_LENGTH('dbo.global_security_paper_codes', 'status') IS NULL
    ALTER TABLE dbo.global_security_paper_codes ADD status NVARCHAR(20) NULL;
GO

IF COL_LENGTH('dbo.global_security_paper_codes', 'void_reason') IS NULL
    ALTER TABLE dbo.global_security_paper_codes ADD void_reason NVARCHAR(40) NULL;
GO

IF COL_LENGTH('dbo.global_security_paper_codes', 'void_note') IS NULL
    ALTER TABLE dbo.global_security_paper_codes ADD void_note NVARCHAR(500) NULL;
GO

IF COL_LENGTH('dbo.global_security_paper_codes', 'voided_by') IS NULL
    ALTER TABLE dbo.global_security_paper_codes ADD voided_by NVARCHAR(191) NULL;
GO

IF COL_LENGTH('dbo.global_security_paper_codes', 'voided_at') IS NULL
    ALTER TABLE dbo.global_security_paper_codes ADD voided_at DATETIME NULL;
GO

/* Backfill from the existing flag — nothing is voided at deploy time. */
UPDATE dbo.global_security_paper_codes
   SET status = CASE WHEN is_used = 1 THEN 'assigned' ELSE 'available' END
 WHERE status IS NULL;
GO

/* Verify — expect only 'assigned' and 'available', and no NULL status */
SELECT status, COUNT(*) AS n
  FROM dbo.global_security_paper_codes
 GROUP BY status;
