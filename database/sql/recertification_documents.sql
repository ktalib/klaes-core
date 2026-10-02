-- MS SQL Server Script for Recertification Documents Table
-- This table stores document uploads for recertification applications

-- Create the recertification_documents table
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='recertification_documents' AND xtype='U')
BEGIN
    CREATE TABLE recertification_documents (
        id BIGINT IDENTITY(1,1) PRIMARY KEY,
        application_id BIGINT NOT NULL,
        document_type NVARCHAR(100) NOT NULL,
        document_name NVARCHAR(255) NOT NULL,
        original_filename NVARCHAR(255) NOT NULL,
        file_path NVARCHAR(500) NOT NULL,
        file_size BIGINT NOT NULL,
        mime_type NVARCHAR(100) NOT NULL,
        description NVARCHAR(MAX) NULL,
        uploaded_by BIGINT NULL,
        is_verified BIT DEFAULT 0,
        verified_by BIGINT NULL,
        verified_at DATETIME2 NULL,
        verification_notes NVARCHAR(MAX) NULL,
        created_at DATETIME2 DEFAULT GETDATE(),
        updated_at DATETIME2 DEFAULT GETDATE(),
        
        -- Foreign key constraint to recertification_applications table
        CONSTRAINT FK_recertification_documents_application_id 
            FOREIGN KEY (application_id) 
            REFERENCES recertification_applications(id) 
            ON DELETE CASCADE,
            
        -- Index for faster queries
        INDEX IX_recertification_documents_application_id (application_id),
        INDEX IX_recertification_documents_document_type (document_type),
        INDEX IX_recertification_documents_created_at (created_at)
    );
    
    PRINT 'Table recertification_documents created successfully';
END
ELSE
BEGIN
    PRINT 'Table recertification_documents already exists';
END

-- Add columns to recertification_applications table for new file numbers if they don't exist
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'recertification_applications' AND COLUMN_NAME = 'mlsFileNo')
BEGIN
    ALTER TABLE recertification_applications ADD mlsFileNo NVARCHAR(50) NULL;
    PRINT 'Added mlsFileNo column to recertification_applications';
END

IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'recertification_applications' AND COLUMN_NAME = 'kangisFileNo')
BEGIN
    ALTER TABLE recertification_applications ADD kangisFileNo NVARCHAR(50) NULL;
    PRINT 'Added kangisFileNo column to recertification_applications';
END

IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'recertification_applications' AND COLUMN_NAME = 'newKangisFileNo')
BEGIN
    ALTER TABLE recertification_applications ADD newKangisFileNo NVARCHAR(50) NULL;
    PRINT 'Added newKangisFileNo column to recertification_applications';
END

IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'recertification_applications' AND COLUMN_NAME = 'companyRcNumber')
BEGIN
    ALTER TABLE recertification_applications ADD companyRcNumber NVARCHAR(50) NULL;
    PRINT 'Added companyRcNumber column to recertification_applications';
END

-- Add document file path columns for the 10 document types
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'recertification_applications' AND COLUMN_NAME = 'right_of_occupancy_path')
BEGIN
    ALTER TABLE recertification_applications ADD right_of_occupancy_path NVARCHAR(500) NULL;
    PRINT 'Added right_of_occupancy_path column';
END

IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'recertification_applications' AND COLUMN_NAME = 'certificate_of_occupancy_path')
BEGIN
    ALTER TABLE recertification_applications ADD certificate_of_occupancy_path NVARCHAR(500) NULL;
    PRINT 'Added certificate_of_occupancy_path column';
END

IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'recertification_applications' AND COLUMN_NAME = 'deed_of_assignment_path')
BEGIN
    ALTER TABLE recertification_applications ADD deed_of_assignment_path NVARCHAR(500) NULL;
    PRINT 'Added deed_of_assignment_path column';
END

IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'recertification_applications' AND COLUMN_NAME = 'deed_of_sublease_path')
BEGIN
    ALTER TABLE recertification_applications ADD deed_of_sublease_path NVARCHAR(500) NULL;
    PRINT 'Added deed_of_sublease_path column';
END

IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'recertification_applications' AND COLUMN_NAME = 'deed_of_mortgage_path')
BEGIN
    ALTER TABLE recertification_applications ADD deed_of_mortgage_path NVARCHAR(500) NULL;
    PRINT 'Added deed_of_mortgage_path column';
END

IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'recertification_applications' AND COLUMN_NAME = 'deed_of_gift_path')
BEGIN
    ALTER TABLE recertification_applications ADD deed_of_gift_path NVARCHAR(500) NULL;
    PRINT 'Added deed_of_gift_path column';
END

IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'recertification_applications' AND COLUMN_NAME = 'power_of_attorney_path')
BEGIN
    ALTER TABLE recertification_applications ADD power_of_attorney_path NVARCHAR(500) NULL;
    PRINT 'Added power_of_attorney_path column';
END

IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'recertification_applications' AND COLUMN_NAME = 'devolution_order_path')
BEGIN
    ALTER TABLE recertification_applications ADD devolution_order_path NVARCHAR(500) NULL;
    PRINT 'Added devolution_order_path column';
END

IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'recertification_applications' AND COLUMN_NAME = 'letter_of_administration_path')
BEGIN
    ALTER TABLE recertification_applications ADD letter_of_administration_path NVARCHAR(500) NULL;
    PRINT 'Added letter_of_administration_path column';
END

IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'recertification_applications' AND COLUMN_NAME = 'other_documents_path')
BEGIN
    ALTER TABLE recertification_applications ADD other_documents_path NVARCHAR(500) NULL;
    PRINT 'Added other_documents_path column';
END

IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'recertification_applications' AND COLUMN_NAME = 'other_documents_description')
BEGIN
    ALTER TABLE recertification_applications ADD other_documents_description NVARCHAR(MAX) NULL;
    PRINT 'Added other_documents_description column';
END

-- Create indexes for better performance
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_recertification_applications_mlsFileNo')
BEGIN
    CREATE INDEX IX_recertification_applications_mlsFileNo ON recertification_applications(mlsFileNo);
    PRINT 'Created index on mlsFileNo';
END

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_recertification_applications_kangisFileNo')
BEGIN
    CREATE INDEX IX_recertification_applications_kangisFileNo ON recertification_applications(kangisFileNo);
    PRINT 'Created index on kangisFileNo';
END

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_recertification_applications_newKangisFileNo')
BEGIN
    CREATE INDEX IX_recertification_applications_newKangisFileNo ON recertification_applications(newKangisFileNo);
    PRINT 'Created index on newKangisFileNo';
END

-- Create a view for easy document access
IF EXISTS (SELECT * FROM sys.views WHERE name = 'vw_recertification_documents')
BEGIN
    DROP VIEW vw_recertification_documents;
END
GO

CREATE VIEW vw_recertification_documents AS
SELECT 
    ra.id as application_id,
    ra.application_reference,
    ra.applicant_type,
    ra.surname,
    ra.first_name,
    ra.organisation_name,
    ra.plot_number,
    ra.file_number,
    ra.mlsFileNo,
    ra.kangisFileNo,
    ra.newKangisFileNo,
    ra.companyRcNumber,
    
    -- Document paths
    ra.right_of_occupancy_path,
    ra.certificate_of_occupancy_path,
    ra.deed_of_assignment_path,
    ra.deed_of_sublease_path,
    ra.deed_of_mortgage_path,
    ra.deed_of_gift_path,
    ra.power_of_attorney_path,
    ra.devolution_order_path,
    ra.letter_of_administration_path,
    ra.other_documents_path,
    ra.other_documents_description,
    
    -- Document count
    (CASE WHEN ra.right_of_occupancy_path IS NOT NULL THEN 1 ELSE 0 END +
     CASE WHEN ra.certificate_of_occupancy_path IS NOT NULL THEN 1 ELSE 0 END +
     CASE WHEN ra.deed_of_assignment_path IS NOT NULL THEN 1 ELSE 0 END +
     CASE WHEN ra.deed_of_sublease_path IS NOT NULL THEN 1 ELSE 0 END +
     CASE WHEN ra.deed_of_mortgage_path IS NOT NULL THEN 1 ELSE 0 END +
     CASE WHEN ra.deed_of_gift_path IS NOT NULL THEN 1 ELSE 0 END +
     CASE WHEN ra.power_of_attorney_path IS NOT NULL THEN 1 ELSE 0 END +
     CASE WHEN ra.devolution_order_path IS NOT NULL THEN 1 ELSE 0 END +
     CASE WHEN ra.letter_of_administration_path IS NOT NULL THEN 1 ELSE 0 END +
     CASE WHEN ra.other_documents_path IS NOT NULL THEN 1 ELSE 0 END) as total_documents_uploaded,
    
    ra.created_at,
    ra.updated_at
FROM recertification_applications ra;

PRINT 'Created view vw_recertification_documents';

-- Sample queries for testing

-- Query to get all applications with their document counts
/*
SELECT 
    application_reference,
    CASE 
        WHEN applicant_type = 'Corporate' THEN organisation_name
        ELSE CONCAT(surname, ', ', first_name)
    END as applicant_name,
    plot_number,
    mlsFileNo,
    kangisFileNo,
    newKangisFileNo,
    total_documents_uploaded,
    created_at
FROM vw_recertification_documents
ORDER BY created_at DESC;
*/

-- Query to get applications with specific document types
/*
SELECT 
    application_reference,
    applicant_name,
    plot_number,
    CASE WHEN right_of_occupancy_path IS NOT NULL THEN 'Yes' ELSE 'No' END as has_right_of_occupancy,
    CASE WHEN certificate_of_occupancy_path IS NOT NULL THEN 'Yes' ELSE 'No' END as has_certificate_of_occupancy,
    CASE WHEN deed_of_assignment_path IS NOT NULL THEN 'Yes' ELSE 'No' END as has_deed_of_assignment
FROM vw_recertification_documents
WHERE total_documents_uploaded > 0;
*/

-- Query to find applications missing specific documents
/*
SELECT 
    application_reference,
    applicant_name,
    plot_number,
    total_documents_uploaded
FROM vw_recertification_documents
WHERE certificate_of_occupancy_path IS NULL
   OR right_of_occupancy_path IS NULL;
*/

PRINT 'Recertification documents database schema setup completed successfully!';
PRINT 'You can now use the following:';
PRINT '1. recertification_documents table for detailed document tracking';
PRINT '2. Additional columns in recertification_applications for file paths';
PRINT '3. vw_recertification_documents view for easy querying';
PRINT '4. Indexes for improved query performance';