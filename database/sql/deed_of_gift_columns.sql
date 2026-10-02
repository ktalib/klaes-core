-- SQL Script to add additional columns for Deed of Gift instrument type
-- Run this script on your SQL Server database

-- Note: Some columns already exist in the table, so we only add the missing ones
-- Existing columns that will be reused:
-- - surveyPlanNo (already exists)
-- - size (will be used for propertySize)

-- Add columns for Deed of Gift specific fields to the instrument_registration table
ALTER TABLE [klas].[dbo].[instrument_registration] ADD 
    -- Section A - Instrument Metadata
    instrumentNo NVARCHAR(100) NULL,
    landUse NVARCHAR(255) NULL,
    dateOfExecution DATE NULL,
    dateOfRegistration DATE NULL,
    
    -- Section B - Donor (Giver) Details
    donorPhone NVARCHAR(100) NULL,
    donorNationality NVARCHAR(100) NULL,
    donorIdDocument NVARCHAR(100) NULL,
    donorIdNumber NVARCHAR(100) NULL,
    
    -- Section C - Donee (Receiver) Details  
    doneePhone NVARCHAR(100) NULL,
    doneeNationality NVARCHAR(100) NULL,
    doneeIdDocument NVARCHAR(100) NULL,
    doneeIdNumber NVARCHAR(100) NULL,
    
    -- Section D - Gifted Property Information
    -- Note: surveyPlanNo already exists in the table
    propertySize NVARCHAR(100) NULL,
    consideration NVARCHAR(255) NULL,
    encumbrances NVARCHAR(500) NULL,
    supportingDocs NVARCHAR(MAX) NULL,
    
    -- Section E - Registration
    registrarName NVARCHAR(255) NULL,
    registrarSignature NVARCHAR(255) NULL,
    volumePageNo NVARCHAR(100) NULL,
    blockchainHash NVARCHAR(255) NULL;

-- Add comments to document the new columns
EXEC sp_addextendedproperty 
    @name = N'MS_Description', 
    @value = N'Instrument number for Deed of Gift', 
    @level0type = N'SCHEMA', @level0name = N'dbo', 
    @level1type = N'TABLE', @level1name = N'instrument_registration', 
    @level2type = N'COLUMN', @level2name = N'instrumentNo';

EXEC sp_addextendedproperty 
    @name = N'MS_Description', 
    @value = N'Land use classification', 
    @level0type = N'SCHEMA', @level0name = N'dbo', 
    @level1type = N'TABLE', @level1name = N'instrument_registration', 
    @level2type = N'COLUMN', @level2name = N'landUse';

EXEC sp_addextendedproperty 
    @name = N'MS_Description', 
    @value = N'Date when the deed was executed', 
    @level0type = N'SCHEMA', @level0name = N'dbo', 
    @level1type = N'TABLE', @level1name = N'instrument_registration', 
    @level2type = N'COLUMN', @level2name = N'dateOfExecution';

EXEC sp_addextendedproperty 
    @name = N'MS_Description', 
    @value = N'Date when the deed was registered', 
    @level0type = N'SCHEMA', @level0name = N'dbo', 
    @level1type = N'TABLE', @level1name = N'instrument_registration', 
    @level2type = N'COLUMN', @level2name = N'dateOfRegistration';

EXEC sp_addextendedproperty 
    @name = N'MS_Description', 
    @value = N'Donor phone number or email', 
    @level0type = N'SCHEMA', @level0name = N'dbo', 
    @level1type = N'TABLE', @level1name = N'instrument_registration', 
    @level2type = N'COLUMN', @level2name = N'donorPhone';

EXEC sp_addextendedproperty 
    @name = N'MS_Description', 
    @value = N'Donor nationality', 
    @level0type = N'SCHEMA', @level0name = N'dbo', 
    @level1type = N'TABLE', @level1name = N'instrument_registration', 
    @level2type = N'COLUMN', @level2name = N'donorNationality';

EXEC sp_addextendedproperty 
    @name = N'MS_Description', 
    @value = N'Type of identification document for donor', 
    @level0type = N'SCHEMA', @level0name = N'dbo', 
    @level1type = N'TABLE', @level1name = N'instrument_registration', 
    @level2type = N'COLUMN', @level2name = N'donorIdDocument';

EXEC sp_addextendedproperty 
    @name = N'MS_Description', 
    @value = N'Donor identification document number', 
    @level0type = N'SCHEMA', @level0name = N'dbo', 
    @level1type = N'TABLE', @level1name = N'instrument_registration', 
    @level2type = N'COLUMN', @level2name = N'donorIdNumber';

EXEC sp_addextendedproperty 
    @name = N'MS_Description', 
    @value = N'Donee phone number or email', 
    @level0type = N'SCHEMA', @level0name = N'dbo', 
    @level1type = N'TABLE', @level1name = N'instrument_registration', 
    @level2type = N'COLUMN', @level2name = N'doneePhone';

EXEC sp_addextendedproperty 
    @name = N'MS_Description', 
    @value = N'Donee nationality', 
    @level0type = N'SCHEMA', @level0name = N'dbo', 
    @level1type = N'TABLE', @level1name = N'instrument_registration', 
    @level2type = N'COLUMN', @level2name = N'doneeNationality';

EXEC sp_addextendedproperty 
    @name = N'MS_Description', 
    @value = N'Type of identification document for donee', 
    @level0type = N'SCHEMA', @level0name = N'dbo', 
    @level1type = N'TABLE', @level1name = N'instrument_registration', 
    @level2type = N'COLUMN', @level2name = N'doneeIdDocument';

EXEC sp_addextendedproperty 
    @name = N'MS_Description', 
    @value = N'Donee identification document number', 
    @level0type = N'SCHEMA', @level0name = N'dbo', 
    @level1type = N'TABLE', @level1name = N'instrument_registration', 
    @level2type = N'COLUMN', @level2name = N'doneeIdNumber';

EXEC sp_addextendedproperty 
    @name = N'MS_Description', 
    @value = N'Size of the property in square meters or hectares', 
    @level0type = N'SCHEMA', @level0name = N'dbo', 
    @level1type = N'TABLE', @level1name = N'instrument_registration', 
    @level2type = N'COLUMN', @level2name = N'propertySize';

EXEC sp_addextendedproperty 
    @name = N'MS_Description', 
    @value = N'Consideration for the gift (usually love and affection)', 
    @level0type = N'SCHEMA', @level0name = N'dbo', 
    @level1type = N'TABLE', @level1name = N'instrument_registration', 
    @level2type = N'COLUMN', @level2name = N'consideration';

EXEC sp_addextendedproperty 
    @name = N'MS_Description', 
    @value = N'Any encumbrances on the property', 
    @level0type = N'SCHEMA', @level0name = N'dbo', 
    @level1type = N'TABLE', @level1name = N'instrument_registration', 
    @level2type = N'COLUMN', @level2name = N'encumbrances';

EXEC sp_addextendedproperty 
    @name = N'MS_Description', 
    @value = N'List of supporting documents', 
    @level0type = N'SCHEMA', @level0name = N'dbo', 
    @level1type = N'TABLE', @level1name = N'instrument_registration', 
    @level2type = N'COLUMN', @level2name = N'supportingDocs';

EXEC sp_addextendedproperty 
    @name = N'MS_Description', 
    @value = N'Name of the registrar', 
    @level0type = N'SCHEMA', @level0name = N'dbo', 
    @level1type = N'TABLE', @level1name = N'instrument_registration', 
    @level2type = N'COLUMN', @level2name = N'registrarName';

EXEC sp_addextendedproperty 
    @name = N'MS_Description', 
    @value = N'Registrar signature reference', 
    @level0type = N'SCHEMA', @level0name = N'dbo', 
    @level1type = N'TABLE', @level1name = N'instrument_registration', 
    @level2type = N'COLUMN', @level2name = N'registrarSignature';

EXEC sp_addextendedproperty 
    @name = N'MS_Description', 
    @value = N'Volume and page number in the register', 
    @level0type = N'SCHEMA', @level0name = N'dbo', 
    @level1type = N'TABLE', @level1name = N'instrument_registration', 
    @level2type = N'COLUMN', @level2name = N'volumePageNo';

EXEC sp_addextendedproperty 
    @name = N'MS_Description', 
    @value = N'Blockchain hash if applicable for digital verification', 
    @level0type = N'SCHEMA', @level0name = N'dbo', 
    @level1type = N'TABLE', @level1name = N'instrument_registration', 
    @level2type = N'COLUMN', @level2name = N'blockchainHash';

-- Create an index on instrument_type for better query performance
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_instrument_registration_instrument_type')
BEGIN
    CREATE INDEX IX_instrument_registration_instrument_type 
    ON [klas].[dbo].[instrument_registration] (instrument_type);
END

PRINT 'Successfully added Deed of Gift columns to instrument_registration table';

-- Show the columns that were added
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    CHARACTER_MAXIMUM_LENGTH
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'instrument_registration' 
AND COLUMN_NAME IN (
    'instrumentNo', 'landUse', 'dateOfExecution', 'dateOfRegistration',
    'donorPhone', 'donorNationality', 'donorIdDocument', 'donorIdNumber',
    'doneePhone', 'doneeNationality', 'doneeIdDocument', 'doneeIdNumber',
    'propertySize', 'consideration', 'encumbrances', 'supportingDocs',
    'registrarName', 'registrarSignature', 'volumePageNo', 'blockchainHash'
)
ORDER BY COLUMN_NAME;