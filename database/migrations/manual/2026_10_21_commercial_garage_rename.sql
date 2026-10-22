-- sqlsrv (klas): rename COMMERCIAL (MOTOR GARAGE) -> COMMERCIAL (GARAGE) in both
-- lookups (purposes id 35, land_use_types id 30). Saved records that hold the old
-- text (land_recommendations 44081, pra 228352) are NOT touched here.
-- Rollback: swap the two names below.
UPDATE purposes
   SET name = 'COMMERCIAL (GARAGE)', description = 'Garage commercial use'
 WHERE UPPER(LTRIM(RTRIM(name))) = 'COMMERCIAL (MOTOR GARAGE)'
   AND NOT EXISTS (SELECT 1 FROM purposes WHERE UPPER(LTRIM(RTRIM(name))) = 'COMMERCIAL (GARAGE)');

UPDATE land_use_types
   SET name = 'COMMERCIAL (GARAGE)', description = 'Garage commercial use', updated_at = GETDATE()
 WHERE UPPER(LTRIM(RTRIM(name))) = 'COMMERCIAL (MOTOR GARAGE)'
   AND NOT EXISTS (SELECT 1 FROM land_use_types WHERE UPPER(LTRIM(RTRIM(name))) = 'COMMERCIAL (GARAGE)');
