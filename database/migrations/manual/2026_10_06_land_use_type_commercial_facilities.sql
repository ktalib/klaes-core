-- sqlsrv (klas): add COMMERCIAL (FACILITIES) to the land use lookup.
-- Feeds the Land Use Type select on create/edit file indexing (LandUseType model).
IF NOT EXISTS (SELECT 1 FROM land_use_types WHERE name = 'COMMERCIAL (FACILITIES)')
    INSERT INTO land_use_types (name, code, description, is_active, created_at, updated_at)
    VALUES ('COMMERCIAL (FACILITIES)', 'COM_FAC', 'Commercial facilities use', 1, GETDATE(), GETDATE());
