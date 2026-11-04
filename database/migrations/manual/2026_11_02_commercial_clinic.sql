-- sqlsrv (klas): add Clinic as a Commercial purpose and Land Use Type.
-- Idempotent: safe to run again if either lookup row already exists.
SET XACT_ABORT ON;
BEGIN TRY
    BEGIN TRANSACTION;

    IF NOT EXISTS (SELECT 1 FROM purposes WHERE landuseid = 2 AND UPPER(LTRIM(RTRIM(name))) = 'COMMERCIAL (CLINIC)')
        INSERT INTO purposes (landuseid, name, code, description)
        VALUES (2, 'COMMERCIAL (CLINIC)', 'COM_CL', 'Clinic commercial use');

    IF NOT EXISTS (SELECT 1 FROM land_use_types WHERE UPPER(LTRIM(RTRIM(name))) = 'COMMERCIAL (CLINIC)')
        INSERT INTO land_use_types (name, code, description, is_active, created_at, updated_at)
        VALUES ('COMMERCIAL (CLINIC)', 'COM_CL', 'Clinic commercial use', 1, GETDATE(), GETDATE());

    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    THROW;
END CATCH;
