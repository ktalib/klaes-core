-- =============================================
-- Script: Create Views for File Decommissioning
-- Description: Creates useful views for reporting and data access
-- Date: 2024-12-19
-- =============================================

USE [klas]; -- Replace with your actual database name if different
GO

-- View for Active Files (non-decommissioned)
IF EXISTS (SELECT * FROM sys.views WHERE name = 'vw_active_files')
BEGIN
    DROP VIEW vw_active_files;
    PRINT 'Dropped existing vw_active_files view';
END

CREATE VIEW vw_active_files AS
SELECT 
    f.id,
    f.kangisFileNo,
    f.mlsfNo,
    f.NewKANGISFileNo,
    f.FileName,
    f.type,
    f.created_by,
    f.updated_by,
    f.created_at,
    f.updated_at,
    f.commissioning_date,
    f.location,
    f.SOURCE
FROM fileNumber f
WHERE (f.is_deleted IS NULL OR f.is_deleted = 0)
  AND (f.is_decommissioned IS NULL OR f.is_decommissioned = 0);

PRINT 'Created vw_active_files view';
GO

-- View for Decommissioned Files
IF EXISTS (SELECT * FROM sys.views WHERE name = 'vw_decommissioned_files')
BEGIN
    DROP VIEW vw_decommissioned_files;
    PRINT 'Dropped existing vw_decommissioned_files view';
END

CREATE VIEW vw_decommissioned_files AS
SELECT 
    f.id as file_id,
    f.kangisFileNo,
    f.mlsfNo,
    f.NewKANGISFileNo,
    f.FileName,
    f.type,
    f.created_by as file_created_by,
    f.created_at as file_created_at,
    f.commissioning_date,
    f.decommissioning_date,
    f.decommissioning_reason,
    d.id as decommission_record_id,
    d.decommissioned_by,
    d.created_at as decommissioned_at,
    DATEDIFF(day, f.commissioning_date, f.decommissioning_date) as days_active
FROM fileNumber f
INNER JOIN decommissioned_files d ON f.id = d.file_number_id
WHERE f.is_decommissioned = 1;

PRINT 'Created vw_decommissioned_files view';
GO

-- View for File Decommissioning Statistics
IF EXISTS (SELECT * FROM sys.views WHERE name = 'vw_decommissioning_stats')
BEGIN
    DROP VIEW vw_decommissioning_stats;
    PRINT 'Dropped existing vw_decommissioning_stats view';
END

CREATE VIEW vw_decommissioning_stats AS
SELECT 
    'Total Files' as metric,
    COUNT(*) as count
FROM fileNumber 
WHERE (is_deleted IS NULL OR is_deleted = 0)

UNION ALL

SELECT 
    'Active Files' as metric,
    COUNT(*) as count
FROM fileNumber 
WHERE (is_deleted IS NULL OR is_deleted = 0)
  AND (is_decommissioned IS NULL OR is_decommissioned = 0)

UNION ALL

SELECT 
    'Decommissioned Files' as metric,
    COUNT(*) as count
FROM fileNumber 
WHERE (is_deleted IS NULL OR is_deleted = 0)
  AND is_decommissioned = 1

UNION ALL

SELECT 
    'Recent Decommissioned (30 days)' as metric,
    COUNT(*) as count
FROM fileNumber 
WHERE (is_deleted IS NULL OR is_deleted = 0)
  AND is_decommissioned = 1
  AND decommissioning_date >= DATEADD(day, -30, GETDATE())

UNION ALL

SELECT 
    'This Month Decommissioned' as metric,
    COUNT(*) as count
FROM fileNumber 
WHERE (is_deleted IS NULL OR is_deleted = 0)
  AND is_decommissioned = 1
  AND YEAR(decommissioning_date) = YEAR(GETDATE())
  AND MONTH(decommissioning_date) = MONTH(GETDATE());

PRINT 'Created vw_decommissioning_stats view';
GO

PRINT 'All views created successfully!';
GO