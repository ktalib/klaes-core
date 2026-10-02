-- SQL Script to Update Temp File Numbers for Occupancy Permit (OP)s
-- This script calculates the next available TEMP-XXXXX number across all tables
-- then assigns them to OP records that are missing file numbers.

BEGIN TRANSACTION;

DECLARE @MaxTemp INT = 0;
DECLARE @Prefix VARCHAR(5) = 'TEMP-';

-- 1. CALCULATE GLOBAL MAX TEMP NUMBER
-- Check pra
IF EXISTS (SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'pra')
BEGIN
    SELECT @MaxTemp = MAX(val) FROM (
        SELECT @MaxTemp as val
        UNION ALL
        SELECT ISNULL(MAX(TRY_CONVERT(INT, REPLACE(CAST(temp_fileno AS VARCHAR(50)), @Prefix, ''))), 0) FROM pra WHERE temp_fileno LIKE @Prefix + '%'
        UNION ALL
        SELECT ISNULL(MAX(TRY_CONVERT(INT, REPLACE(CAST(mlsFNo AS VARCHAR(50)), @Prefix, ''))), 0) FROM pra WHERE mlsFNo LIKE @Prefix + '%'
    ) t;
END

-- Check pic
IF EXISTS (SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'pic')
BEGIN
    SELECT @MaxTemp = MAX(val) FROM (
        SELECT @MaxTemp as val
        UNION ALL
        SELECT ISNULL(MAX(TRY_CONVERT(INT, REPLACE(CAST(temp_fileno AS VARCHAR(50)), @Prefix, ''))), 0) FROM pic WHERE temp_fileno LIKE @Prefix + '%'
        UNION ALL
        SELECT ISNULL(MAX(TRY_CONVERT(INT, REPLACE(CAST(mlsFNo AS VARCHAR(50)), @Prefix, ''))), 0) FROM pic WHERE mlsFNo LIKE @Prefix + '%'
    ) t;
END

-- Check instrument_capture
IF EXISTS (SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'instrument_capture')
BEGIN
    SELECT @MaxTemp = MAX(val) FROM (
        SELECT @MaxTemp as val
        UNION ALL
        SELECT ISNULL(MAX(TRY_CONVERT(INT, REPLACE(CAST(temp_fileno AS VARCHAR(50)), @Prefix, ''))), 0) FROM instrument_capture WHERE temp_fileno LIKE @Prefix + '%'
        UNION ALL
        SELECT ISNULL(MAX(TRY_CONVERT(INT, REPLACE(CAST(mlsFNo AS VARCHAR(50)), @Prefix, ''))), 0) FROM instrument_capture WHERE mlsFNo LIKE @Prefix + '%'
    ) t;
END

-- Check deed_registrations
IF EXISTS (SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'deed_registrations')
BEGIN
    SELECT @MaxTemp = MAX(val) FROM (
        SELECT @MaxTemp as val
        UNION ALL
        SELECT ISNULL(MAX(TRY_CONVERT(INT, REPLACE(CAST(fileno AS VARCHAR(50)), @Prefix, ''))), 0) FROM deed_registrations WHERE fileno LIKE @Prefix + '%'
    ) t;
END

PRINT 'Global Max TEMP index: ' + CAST(@MaxTemp AS VARCHAR);
PRINT 'Starting new assignments from: ' + CAST(@MaxTemp + 1 AS VARCHAR);

-- 2. ASSIGN SEQUENTIAL NUMBERS
-- Use a temporary table to map assignments
IF OBJECT_ID('tempdb..#Assignments') IS NOT NULL DROP TABLE #Assignments;
CREATE TABLE #Assignments (
    RecordID INT,
    RegistrationNo VARCHAR(100),
    NewTempNo VARCHAR(20)
);

INSERT INTO #Assignments (RecordID, RegistrationNo, NewTempNo)
SELECT 
    id, 
    registration_number,
    @Prefix + RIGHT('00000' + CAST(@MaxTemp + ROW_NUMBER() OVER (ORDER BY id) AS VARCHAR), 5)
FROM instrument_capture
WHERE instrument_type = 'Occupancy Permit (OP)'
AND (temp_fileno IS NULL OR temp_fileno = '');

DECLARE @Count INT = (SELECT COUNT(*) FROM #Assignments);
PRINT 'Total records to update: ' + CAST(@Count AS VARCHAR);

IF @Count > 0
BEGIN
    -- Update instrument_capture
    UPDATE ic
    SET ic.temp_fileno = ta.NewTempNo,
        ic.updated_at = GETDATE()
    FROM instrument_capture ic
    JOIN #Assignments ta ON ic.id = ta.RecordID;

    -- Update deed_registrations
    UPDATE dr
    SET dr.fileno = ta.NewTempNo,
        dr.updated_at = GETDATE()
    FROM deed_registrations dr
    JOIN #Assignments ta ON dr.registration_number = ta.RegistrationNo
    WHERE dr.instrument_type = 'Occupancy Permit (OP)'
    AND (dr.fileno IS NULL OR dr.fileno = '');

    PRINT 'Successfully updated ' + CAST(@Count AS VARCHAR) + ' records with new TEMP file numbers.';
END
ELSE
BEGIN
    PRINT 'No records found needing update.';
END

DROP TABLE #Assignments;

COMMIT;
