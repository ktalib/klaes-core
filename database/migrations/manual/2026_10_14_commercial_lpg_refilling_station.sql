-- sqlsrv (klas): add COMMERCIAL (LPG REFILLING STATION) under Commercial.
-- purposes        = the Purpose LUT (landuseid 2 = Commercial), used by commissioning etc.
-- land_use_types  = feeds the Land Use Type select on create/edit file indexing (LandUseType model).
IF NOT EXISTS (SELECT 1 FROM purposes WHERE UPPER(LTRIM(RTRIM(name))) = 'COMMERCIAL (LPG REFILLING STATION)')
    INSERT INTO purposes (landuseid, name, code, description)
    VALUES (2, 'COMMERCIAL (LPG REFILLING STATION)', 'COM_LPG', 'LPG refilling station');

IF NOT EXISTS (SELECT 1 FROM land_use_types WHERE UPPER(LTRIM(RTRIM(name))) = 'COMMERCIAL (LPG REFILLING STATION)')
    INSERT INTO land_use_types (name, code, description, is_active, created_at, updated_at)
    VALUES ('COMMERCIAL (LPG REFILLING STATION)', 'COM_LPG', 'LPG refilling station', 1, GETDATE(), GETDATE());
