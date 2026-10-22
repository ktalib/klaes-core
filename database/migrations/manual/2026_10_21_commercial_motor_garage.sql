-- sqlsrv (klas): COMMERCIAL (GARAGE) under Commercial.
-- First added as COMMERCIAL (MOTOR GARAGE), renamed the same day by
-- 2026_10_21_commercial_garage_rename.sql; this file now uses the final name.
-- purposes        = the Purpose LUT (landuseid 2 = Commercial), id 35.
-- land_use_types  = feeds the Land Use Type select on create/edit file indexing (LandUseType model).
IF NOT EXISTS (SELECT 1 FROM purposes WHERE UPPER(LTRIM(RTRIM(name))) IN ('COMMERCIAL (GARAGE)', 'COMMERCIAL (MOTOR GARAGE)'))
    INSERT INTO purposes (landuseid, name, code, description)
    VALUES (2, 'COMMERCIAL (GARAGE)', 'COM_MG', 'Garage commercial use');

IF NOT EXISTS (SELECT 1 FROM land_use_types WHERE UPPER(LTRIM(RTRIM(name))) IN ('COMMERCIAL (GARAGE)', 'COMMERCIAL (MOTOR GARAGE)'))
    INSERT INTO land_use_types (name, code, description, is_active, created_at, updated_at)
    VALUES ('COMMERCIAL (GARAGE)', 'COM_MG', 'Garage commercial use', 1, GETDATE(), GETDATE());
