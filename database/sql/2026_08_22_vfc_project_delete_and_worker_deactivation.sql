/* ============================================================================
   VFC: project soft-delete + worker deactivation bookkeeping
   ----------------------------------------------------------------------------
   RUN THIS AGAINST SQL SERVER (the klas sqlsrv database).
   Companion ledger file (MySQL):
     database/sql/2026_08_22_vfc_project_delete_and_worker_deactivation_ledger.mysql.sql

   Mirrors:
     2026_08_22_120000_add_soft_delete_to_vfc_projects_table
     2026_08_22_120100_add_deactivation_fields_to_vfc_workers_table

   Re-runnable — every ALTER is guarded by COL_LENGTH.
   ============================================================================ */

/* --- vfc_projects: is_deleted / deleted_at / deleted_by ------------------- */
IF COL_LENGTH('vfc_projects', 'is_deleted') IS NULL
    ALTER TABLE vfc_projects ADD is_deleted BIT NOT NULL CONSTRAINT DF_vfc_projects_is_deleted DEFAULT 0;
GO

IF COL_LENGTH('vfc_projects', 'deleted_at') IS NULL
    ALTER TABLE vfc_projects ADD deleted_at DATETIME NULL;
GO

IF COL_LENGTH('vfc_projects', 'deleted_by') IS NULL
    ALTER TABLE vfc_projects ADD deleted_by BIGINT NULL;
GO

/* --- vfc_workers: deactivated_at / deactivation_reason -------------------- */
IF COL_LENGTH('vfc_workers', 'deactivated_at') IS NULL
    ALTER TABLE vfc_workers ADD deactivated_at DATETIME NULL;
GO

IF COL_LENGTH('vfc_workers', 'deactivation_reason') IS NULL
    ALTER TABLE vfc_workers ADD deactivation_reason NVARCHAR(255) NULL;
GO

/* --- Verify — expect 5 rows ---------------------------------------------- */
SELECT t.name AS table_name, c.name AS column_name, ty.name AS data_type, c.is_nullable
  FROM sys.columns c
  JOIN sys.tables  t  ON t.object_id = c.object_id
  JOIN sys.types   ty ON ty.user_type_id = c.user_type_id
 WHERE (t.name = 'vfc_projects' AND c.name IN ('is_deleted', 'deleted_at', 'deleted_by'))
    OR (t.name = 'vfc_workers'  AND c.name IN ('deactivated_at', 'deactivation_reason'))
 ORDER BY t.name, c.name;
