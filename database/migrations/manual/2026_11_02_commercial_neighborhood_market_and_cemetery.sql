-- sqlsrv (klas): add Neighborhood Market and Cemetery as Commercial land-use purposes.
-- purposes        = purpose choices for Commercial (landuseid 2).
-- land_use_types  = Land Use Type choices on file indexing and related forms.
-- Idempotent: safe to run again if either lookup row already exists.
SET XACT_ABORT ON;
BEGIN TRY
    BEGIN TRANSACTION;

    IF NOT EXISTS (SELECT 1 FROM purposes WHERE landuseid = 2 AND UPPER(LTRIM(RTRIM(name))) = 'COMMERCIAL (NEIGHBORHOOD MARKET)')
        INSERT INTO purposes (landuseid, name, code, description)
        VALUES (2, 'COMMERCIAL (NEIGHBORHOOD MARKET)', 'COM_NM', 'Neighborhood market commercial use');

    IF NOT EXISTS (SELECT 1 FROM land_use_types WHERE UPPER(LTRIM(RTRIM(name))) = 'COMMERCIAL (NEIGHBORHOOD MARKET)')
        INSERT INTO land_use_types (name, code, description, is_active, created_at, updated_at)
        VALUES ('COMMERCIAL (NEIGHBORHOOD MARKET)', 'COM_NM', 'Neighborhood market commercial use', 1, GETDATE(), GETDATE());

    IF NOT EXISTS (SELECT 1 FROM purposes WHERE landuseid = 2 AND UPPER(LTRIM(RTRIM(name))) = 'COMMERCIAL (CEMETERY)')
        INSERT INTO purposes (landuseid, name, code, description)
        VALUES (2, 'COMMERCIAL (CEMETERY)', 'COM_CEM', 'Cemetery use');

    IF NOT EXISTS (SELECT 1 FROM land_use_types WHERE UPPER(LTRIM(RTRIM(name))) = 'COMMERCIAL (CEMETERY)')
        INSERT INTO land_use_types (name, code, description, is_active, created_at, updated_at)
        VALUES ('COMMERCIAL (CEMETERY)', 'COM_CEM', 'Cemetery use', 1, GETDATE(), GETDATE());

    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    THROW;
END CATCH;
