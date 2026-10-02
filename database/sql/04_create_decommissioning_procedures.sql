-- =============================================
-- Script: Create Stored Procedures for File Decommissioning
-- Description: Creates stored procedures for common decommissioning operations
-- Date: 2024-12-19
-- =============================================

USE [klas]; -- Replace with your actual database name if different
GO

-- Procedure to decommission a file
IF EXISTS (SELECT * FROM sys.procedures WHERE name = 'sp_DecommissionFile')
BEGIN
    DROP PROCEDURE sp_DecommissionFile;
    PRINT 'Dropped existing sp_DecommissionFile procedure';
END
GO

CREATE PROCEDURE sp_DecommissionFile
    @FileId BIGINT,
    @DecommissioningReason NVARCHAR(MAX),
    @DecommissioningDate DATETIME = NULL,
    @CommissioningDate DATETIME = NULL,
    @DecommissionedBy NVARCHAR(255)
AS
BEGIN
    SET NOCOUNT ON;
    
    DECLARE @ErrorMessage NVARCHAR(4000);
    DECLARE @ErrorSeverity INT;
    DECLARE @ErrorState INT;
    
    BEGIN TRY
        BEGIN TRANSACTION;
        
        -- Set default decommissioning date if not provided
        IF @DecommissioningDate IS NULL
            SET @DecommissioningDate = GETDATE();
        
        -- Check if file exists and is not already decommissioned
        IF NOT EXISTS (
            SELECT 1 FROM fileNumber 
            WHERE id = @FileId 
              AND (is_deleted IS NULL OR is_deleted = 0)
              AND (is_decommissioned IS NULL OR is_decommissioned = 0)
        )
        BEGIN
            RAISERROR('File not found or already decommissioned', 16, 1);
            RETURN;
        END
        
        -- Get file details for the decommissioned_files record
        DECLARE @MlsfNo NVARCHAR(255), @KangisFileNo NVARCHAR(255), @NewKangisFileNo NVARCHAR(255), @FileName NVARCHAR(500);
        
        SELECT 
            @MlsfNo = mlsfNo,
            @KangisFileNo = kangisFileNo,
            @NewKangisFileNo = NewKANGISFileNo,
            @FileName = FileName
        FROM fileNumber 
        WHERE id = @FileId;
        
        -- Update the fileNumber record
        UPDATE fileNumber 
        SET 
            commissioning_date = @CommissioningDate,
            decommissioning_date = @DecommissioningDate,
            decommissioning_reason = @DecommissioningReason,
            is_decommissioned = 1,
            updated_at = GETDATE()
        WHERE id = @FileId;
        
        -- Insert record into decommissioned_files table
        INSERT INTO decommissioned_files (
            file_number_id,
            file_no,
            mls_file_no,
            kangis_file_no,
            new_kangis_file_no,
            file_name,
            commissioning_date,
            decommissioning_date,
            decommissioning_reason,
            decommissioned_by,
            created_at,
            updated_at
        )
        VALUES (
            @FileId,
            CAST(@FileId AS NVARCHAR(255)),
            @MlsfNo,
            @KangisFileNo,
            @NewKangisFileNo,
            @FileName,
            @CommissioningDate,
            @DecommissioningDate,
            @DecommissioningReason,
            @DecommissionedBy,
            GETDATE(),
            GETDATE()
        );
        
        COMMIT TRANSACTION;
        
        SELECT 
            'SUCCESS' as Status,
            'File decommissioned successfully' as Message,
            @FileId as FileId,
            @MlsfNo as MlsfNo;
            
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0
            ROLLBACK TRANSACTION;
            
        SELECT @ErrorMessage = ERROR_MESSAGE(),
               @ErrorSeverity = ERROR_SEVERITY(),
               @ErrorState = ERROR_STATE();
               
        SELECT 
            'ERROR' as Status,
            @ErrorMessage as Message,
            @FileId as FileId;
            
        RAISERROR (@ErrorMessage, @ErrorSeverity, @ErrorState);
    END CATCH
END
GO

PRINT 'Created sp_DecommissionFile procedure';
GO

-- Procedure to get decommissioning statistics
IF EXISTS (SELECT * FROM sys.procedures WHERE name = 'sp_GetDecommissioningStats')
BEGIN
    DROP PROCEDURE sp_GetDecommissioningStats;
    PRINT 'Dropped existing sp_GetDecommissioningStats procedure';
END
GO

CREATE PROCEDURE sp_GetDecommissioningStats
AS
BEGIN
    SET NOCOUNT ON;
    
    SELECT 
        (SELECT COUNT(*) FROM fileNumber WHERE (is_deleted IS NULL OR is_deleted = 0)) as total_files,
        (SELECT COUNT(*) FROM fileNumber WHERE (is_deleted IS NULL OR is_deleted = 0) AND (is_decommissioned IS NULL OR is_decommissioned = 0)) as active_files,
        (SELECT COUNT(*) FROM fileNumber WHERE (is_deleted IS NULL OR is_deleted = 0) AND is_decommissioned = 1) as decommissioned_files,
        (SELECT COUNT(*) FROM fileNumber WHERE (is_deleted IS NULL OR is_deleted = 0) AND is_decommissioned = 1 AND decommissioning_date >= DATEADD(day, -30, GETDATE())) as recent_decommissioned,
        (SELECT COUNT(*) FROM fileNumber WHERE (is_deleted IS NULL OR is_deleted = 0) AND is_decommissioned = 1 AND YEAR(decommissioning_date) = YEAR(GETDATE()) AND MONTH(decommissioning_date) = MONTH(GETDATE())) as this_month_decommissioned;
END
GO

PRINT 'Created sp_GetDecommissioningStats procedure';
GO

-- Procedure to search active files
IF EXISTS (SELECT * FROM sys.procedures WHERE name = 'sp_SearchActiveFiles')
BEGIN
    DROP PROCEDURE sp_SearchActiveFiles;
    PRINT 'Dropped existing sp_SearchActiveFiles procedure';
END
GO

CREATE PROCEDURE sp_SearchActiveFiles
    @SearchTerm NVARCHAR(255) = NULL,
    @Limit INT = 20
AS
BEGIN
    SET NOCOUNT ON;
    
    SELECT TOP (@Limit)
        id,
        mlsfNo,
        kangisFileNo,
        NewKANGISFileNo,
        FileName,
        type,
        created_at
    FROM fileNumber
    WHERE (is_deleted IS NULL OR is_deleted = 0)
      AND (is_decommissioned IS NULL OR is_decommissioned = 0)
      AND (
          @SearchTerm IS NULL 
          OR mlsfNo LIKE '%' + @SearchTerm + '%'
          OR kangisFileNo LIKE '%' + @SearchTerm + '%'
          OR NewKANGISFileNo LIKE '%' + @SearchTerm + '%'
          OR FileName LIKE '%' + @SearchTerm + '%'
      )
    ORDER BY 
        CASE 
            WHEN mlsfNo LIKE @SearchTerm + '%' THEN 1
            WHEN kangisFileNo LIKE @SearchTerm + '%' THEN 2
            WHEN NewKANGISFileNo LIKE @SearchTerm + '%' THEN 3
            WHEN FileName LIKE @SearchTerm + '%' THEN 4
            ELSE 5
        END,
        id DESC;
END
GO

PRINT 'Created sp_SearchActiveFiles procedure';
GO

PRINT 'All stored procedures created successfully!';
GO