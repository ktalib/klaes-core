/*
 * Adds two Purpose options under the COMMERCIAL land use (land_uses.id = 2),
 * which feed the Purpose dropdown on the Land Recommendation / RofO, Consent,
 * Deeds and DCIV/GKN forms (App\Models\Purpose, filtered by landuseid).
 *
 * Idempotent: re-running inserts nothing. Target: sqlsrv connection (klas).
 */
INSERT INTO purposes (landuseid, name, code, description)
SELECT 2, N'COMMERCIAL (MASJID & ISLAMIYYA)', N'COM_MI', N'Masjid and Islamiyya'
WHERE NOT EXISTS (
    SELECT 1 FROM purposes WHERE landuseid = 2 AND name = N'COMMERCIAL (MASJID & ISLAMIYYA)'
);

INSERT INTO purposes (landuseid, name, code, description)
SELECT 2, N'COMMERCIAL (NURSERY/PRIMARY SCHOOL)', N'COM_NPS', N'Nursery and primary school'
WHERE NOT EXISTS (
    SELECT 1 FROM purposes WHERE landuseid = 2 AND name = N'COMMERCIAL (NURSERY/PRIMARY SCHOOL)'
);
