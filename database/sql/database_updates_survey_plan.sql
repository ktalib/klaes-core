-- =====================================================
-- MS SQL Server Database Update Script
-- Adding survey_plan_path column to all survey tables
-- =====================================================

-- Use the appropriate database
-- USE [YourDatabaseName];

-- =====================================================
-- 1. ALTER surveyCadastralRecord table (AttributionController)
-- =====================================================
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[surveyCadastralRecord]') AND name = 'survey_plan_path')
BEGIN
    ALTER TABLE [dbo].[surveyCadastralRecord] 
    ADD [survey_plan_path] NVARCHAR(500) NULL;
    
    PRINT 'Added survey_plan_path column to surveyCadastralRecord table';
END
ELSE
BEGIN
    PRINT 'survey_plan_path column already exists in surveyCadastralRecord table';
END

-- =====================================================
-- 2. ALTER surveyRecord table (SurveyAttributionController)
-- =====================================================
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[surveyRecord]') AND name = 'survey_plan_path')
BEGIN
    ALTER TABLE [dbo].[surveyRecord] 
    ADD [survey_plan_path] NVARCHAR(500) NULL;
    
    PRINT 'Added survey_plan_path column to surveyRecord table';
END
ELSE
BEGIN
    PRINT 'survey_plan_path column already exists in surveyRecord table';
END

-- =====================================================
-- 3. ALTER surveyCadastral table (SurveyCadastralAttributionController)
-- =====================================================
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[surveyCadastral]') AND name = 'survey_plan_path')
BEGIN
    ALTER TABLE [dbo].[surveyCadastral] 
    ADD [survey_plan_path] NVARCHAR(500) NULL;
    
    PRINT 'Added survey_plan_path column to surveyCadastral table';
END
ELSE
BEGIN
    PRINT 'survey_plan_path column already exists in surveyCadastral table';
END

-- =====================================================
-- 4. ALTER gisCapture table (GisController)
-- =====================================================
-- Note: This table already has SurveyPlan column, but we'll add survey_plan_path for consistency
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[gisCapture]') AND name = 'survey_plan_path')
BEGIN
    ALTER TABLE [dbo].[gisCapture] 
    ADD [survey_plan_path] NVARCHAR(500) NULL;
    
    PRINT 'Added survey_plan_path column to gisCapture table';
END
ELSE
BEGIN
    PRINT 'survey_plan_path column already exists in gisCapture table';
END

-- =====================================================
-- 5. ALTER gisDataCapture table (GisDataController)
-- =====================================================
-- Note: This table already has SurveyPlan column, but we'll add survey_plan_path for consistency
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[gisDataCapture]') AND name = 'survey_plan_path')
BEGIN
    ALTER TABLE [dbo].[gisDataCapture] 
    ADD [survey_plan_path] NVARCHAR(500) NULL;
    
    PRINT 'Added survey_plan_path column to gisDataCapture table';
END
ELSE
BEGIN
    PRINT 'survey_plan_path column already exists in gisDataCapture table';
END

-- =====================================================
-- 6. Create indexes for better performance (optional)
-- =====================================================

-- Index for surveyCadastralRecord
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE object_id = OBJECT_ID(N'[dbo].[surveyCadastralRecord]') AND name = 'IX_surveyCadastralRecord_survey_plan_path')
BEGIN
    CREATE NONCLUSTERED INDEX [IX_surveyCadastralRecord_survey_plan_path] 
    ON [dbo].[surveyCadastralRecord] ([survey_plan_path]);
    
    PRINT 'Created index IX_surveyCadastralRecord_survey_plan_path';
END

-- Index for surveyRecord
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE object_id = OBJECT_ID(N'[dbo].[surveyRecord]') AND name = 'IX_surveyRecord_survey_plan_path')
BEGIN
    CREATE NONCLUSTERED INDEX [IX_surveyRecord_survey_plan_path] 
    ON [dbo].[surveyRecord] ([survey_plan_path]);
    
    PRINT 'Created index IX_surveyRecord_survey_plan_path';
END

-- Index for surveyCadastral
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE object_id = OBJECT_ID(N'[dbo].[surveyCadastral]') AND name = 'IX_surveyCadastral_survey_plan_path')
BEGIN
    CREATE NONCLUSTERED INDEX [IX_surveyCadastral_survey_plan_path] 
    ON [dbo].[surveyCadastral] ([survey_plan_path]);
    
    PRINT 'Created index IX_surveyCadastral_survey_plan_path';
END

-- Index for gisCapture
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE object_id = OBJECT_ID(N'[dbo].[gisCapture]') AND name = 'IX_gisCapture_survey_plan_path')
BEGIN
    CREATE NONCLUSTERED INDEX [IX_gisCapture_survey_plan_path] 
    ON [dbo].[gisCapture] ([survey_plan_path]);
    
    PRINT 'Created index IX_gisCapture_survey_plan_path';
END

-- Index for gisDataCapture
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE object_id = OBJECT_ID(N'[dbo].[gisDataCapture]') AND name = 'IX_gisDataCapture_survey_plan_path')
BEGIN
    CREATE NONCLUSTERED INDEX [IX_gisDataCapture_survey_plan_path] 
    ON [dbo].[gisDataCapture] ([survey_plan_path]);
    
    PRINT 'Created index IX_gisDataCapture_survey_plan_path';
END

-- =====================================================
-- 7. Add comments to document the changes
-- =====================================================

-- Add extended properties to document the new columns
EXEC sys.sp_addextendedproperty 
    @name = N'MS_Description', 
    @value = N'File path to uploaded survey plan document (PDF, JPG, PNG, DWG, DXF)', 
    @level0type = N'SCHEMA', @level0name = N'dbo', 
    @level1type = N'TABLE', @level1name = N'surveyCadastralRecord', 
    @level2type = N'COLUMN', @level2name = N'survey_plan_path';

EXEC sys.sp_addextendedproperty 
    @name = N'MS_Description', 
    @value = N'File path to uploaded survey plan document (PDF, JPG, PNG, DWG, DXF)', 
    @level0type = N'SCHEMA', @level0name = N'dbo', 
    @level1type = N'TABLE', @level1name = N'surveyRecord', 
    @level2type = N'COLUMN', @level2name = N'survey_plan_path';

EXEC sys.sp_addextendedproperty 
    @name = N'MS_Description', 
    @value = N'File path to uploaded survey plan document (PDF, JPG, PNG, DWG, DXF)', 
    @level0type = N'SCHEMA', @level0name = N'dbo', 
    @level1type = N'TABLE', @level1name = N'surveyCadastral', 
    @level2type = N'COLUMN', @level2name = N'survey_plan_path';

EXEC sys.sp_addextendedproperty 
    @name = N'MS_Description', 
    @value = N'File path to uploaded survey plan document (PDF, JPG, PNG, DWG, DXF)', 
    @level0type = N'SCHEMA', @level0name = N'dbo', 
    @level1type = N'TABLE', @level1name = N'gisCapture', 
    @level2type = N'COLUMN', @level2name = N'survey_plan_path';

EXEC sys.sp_addextendedproperty 
    @name = N'MS_Description', 
    @value = N'File path to uploaded survey plan document (PDF, JPG, PNG, DWG, DXF)', 
    @level0type = N'SCHEMA', @level0name = N'dbo', 
    @level1type = N'TABLE', @level1name = N'gisDataCapture', 
    @level2type = N'COLUMN', @level2name = N'survey_plan_path';

-- =====================================================
-- 8. Verification queries
-- =====================================================

PRINT '=====================================================';
PRINT 'VERIFICATION: Checking if all columns were added successfully';
PRINT '=====================================================';

-- Check surveyCadastralRecord
IF EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[surveyCadastralRecord]') AND name = 'survey_plan_path')
    PRINT '✓ surveyCadastralRecord.survey_plan_path - ADDED SUCCESSFULLY'
ELSE
    PRINT '✗ surveyCadastralRecord.survey_plan_path - FAILED TO ADD'

-- Check surveyRecord
IF EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[surveyRecord]') AND name = 'survey_plan_path')
    PRINT '✓ surveyRecord.survey_plan_path - ADDED SUCCESSFULLY'
ELSE
    PRINT '✗ surveyRecord.survey_plan_path - FAILED TO ADD'

-- Check surveyCadastral
IF EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[surveyCadastral]') AND name = 'survey_plan_path')
    PRINT '✓ surveyCadastral.survey_plan_path - ADDED SUCCESSFULLY'
ELSE
    PRINT '✗ surveyCadastral.survey_plan_path - FAILED TO ADD'

-- Check gisCapture
IF EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[gisCapture]') AND name = 'survey_plan_path')
    PRINT '✓ gisCapture.survey_plan_path - ADDED SUCCESSFULLY'
ELSE
    PRINT '✗ gisCapture.survey_plan_path - FAILED TO ADD'

-- Check gisDataCapture
IF EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[gisDataCapture]') AND name = 'survey_plan_path')
    PRINT '✓ gisDataCapture.survey_plan_path - ADDED SUCCESSFULLY'
ELSE
    PRINT '✗ gisDataCapture.survey_plan_path - FAILED TO ADD'

PRINT '=====================================================';
PRINT 'DATABASE UPDATE COMPLETED';
PRINT 'All survey tables have been updated with survey_plan_path column';
PRINT 'Controllers have been updated to handle file uploads';
PRINT '=====================================================';

-- =====================================================
-- 9. Sample query to view table structures (optional)
-- =====================================================

/*
-- Uncomment to view the updated table structures

SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    DATA_TYPE,
    CHARACTER_MAXIMUM_LENGTH,
    IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME IN ('surveyCadastralRecord', 'surveyRecord', 'surveyCadastral', 'gisCapture', 'gisDataCapture')
    AND COLUMN_NAME = 'survey_plan_path'
ORDER BY TABLE_NAME;
*/