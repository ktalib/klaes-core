-- SQL Server script to create recertification tables
-- Run this in SQL Server Management Studio or similar tool

USE [klas];
GO

-- Create recertification_applications table
CREATE TABLE [recertification_applications] (
    [id] BIGINT IDENTITY(1,1) PRIMARY KEY,
    [application_reference] NVARCHAR(191) NOT NULL UNIQUE,
    [application_date] DATE NULL,
    [applicant_type] NVARCHAR(50) NULL,
    [organisation_name] NVARCHAR(191) NULL,
    [cac_registration_no] NVARCHAR(100) NULL,
    [type_of_organisation] NVARCHAR(191) NULL,
    [type_of_business] NVARCHAR(191) NULL,
    [payload] NVARCHAR(MAX) NOT NULL,
    [created_at] DATETIME2 NULL,
    [updated_at] DATETIME2 NULL,

    -- Step 1: Applicant Personal Details
    [surname] NVARCHAR(255) NULL,
    [first_name] NVARCHAR(255) NULL,
    [middle_name] NVARCHAR(255) NULL,
    [title] NVARCHAR(100) NULL,
    [occupation] NVARCHAR(255) NULL,
    [date_of_birth] DATE NULL,
    [nationality] NVARCHAR(100) NULL,
    [state_of_origin] NVARCHAR(255) NULL,
    [lga_of_origin] NVARCHAR(255) NULL,
    [nin] NVARCHAR(50) NULL,
    [gender] NVARCHAR(10) NULL,
    [marital_status] NVARCHAR(20) NULL,
    [maiden_name] NVARCHAR(255) NULL,

    -- Step 2: Contact Details (Applicant)
    [phone_no] NVARCHAR(50) NULL,
    [whatsapp_phone_no] NVARCHAR(50) NULL,
    [alternate_phone_no] NVARCHAR(50) NULL,
    [address_line1] NVARCHAR(255) NULL,
    [address_line2] NVARCHAR(255) NULL,
    [city_town] NVARCHAR(255) NULL,
    [state_name] NVARCHAR(255) NULL,
    [email_address] NVARCHAR(255) NULL,

    -- Step 2: Authorized Representative
    [rep_surname] NVARCHAR(255) NULL,
    [rep_first_name] NVARCHAR(255) NULL,
    [rep_middle_name] NVARCHAR(255) NULL,
    [rep_title] NVARCHAR(100) NULL,
    [rep_relationship] NVARCHAR(255) NULL,
    [rep_phone_no] NVARCHAR(50) NULL,

    -- Step 3: Title Holder Details & Registration
    [title_holder_surname] NVARCHAR(255) NULL,
    [title_holder_first_name] NVARCHAR(255) NULL,
    [title_holder_middle_name] NVARCHAR(255) NULL,
    [title_holder_title] NVARCHAR(100) NULL,
    [cofo_number] NVARCHAR(255) NULL,
    [reg_no] NVARCHAR(255) NULL,
    [reg_volume] NVARCHAR(255) NULL,
    [reg_page] NVARCHAR(255) NULL,
    [reg_number] NVARCHAR(255) NULL,
    [is_original_owner] BIT NULL,
    [instrument_type] NVARCHAR(50) NULL,
    [acquired_title_holder_name] NVARCHAR(255) NULL,
    [commencement_date] DATE NULL,
    [grant_term] NVARCHAR(255) NULL,

    -- Step 4: Mortgage & Encumbrance
    [is_encumbered] BIT NULL,
    [encumbrance_reason] NVARCHAR(MAX) NULL,
    [has_mortgage] BIT NULL,
    [mortgagee_name] NVARCHAR(255) NULL,
    [mortgage_registration_no] NVARCHAR(255) NULL,
    [mortgage_volume] NVARCHAR(255) NULL,
    [mortgage_page] NVARCHAR(255) NULL,
    [mortgage_number] NVARCHAR(255) NULL,
    [mortgage_released] BIT NULL,

    -- Step 5: Plot Details
    [plot_number] NVARCHAR(255) NULL,
    [file_number] NVARCHAR(255) NULL,
    [plot_size] DECIMAL(18,4) NULL,
    [layout_district] NVARCHAR(255) NULL,
    [lga_name] NVARCHAR(255) NULL,
    [current_land_use] NVARCHAR(100) NULL,
    [plot_status] NVARCHAR(100) NULL,
    [mode_of_allocation] NVARCHAR(100) NULL,
    [start_date] DATE NULL,
    [expiry_date] DATE NULL,
    [plot_description] NVARCHAR(MAX) NULL,

    -- Step 6: Payment & Terms
    [application_type] NVARCHAR(100) NULL,
    [application_reason] NVARCHAR(100) NULL,
    [other_reason] NVARCHAR(255) NULL,
    [payment_method] NVARCHAR(50) NULL,
    [receipt_no] NVARCHAR(255) NULL,
    [bank_name] NVARCHAR(255) NULL,
    [payment_amount] DECIMAL(18,2) NULL,
    [payment_date] DATE NULL,
    [documents_json] NVARCHAR(MAX) NULL,
    [agree_terms] BIT NULL,
    [confirm_accuracy] BIT NULL
);
GO

-- Create recertification_owners table
CREATE TABLE [recertification_owners] (
    [id] BIGINT IDENTITY(1,1) PRIMARY KEY,
    [application_id] BIGINT NOT NULL,
    [surname] NVARCHAR(255) NOT NULL,
    [first_name] NVARCHAR(255) NOT NULL,
    [middle_name] NVARCHAR(255) NULL,
    [title] NVARCHAR(100) NULL,
    [occupation] NVARCHAR(255) NULL,
    [date_of_birth] DATE NULL,
    [nationality] NVARCHAR(100) NULL,
    [state_of_origin] NVARCHAR(255) NULL,
    [lga_of_origin] NVARCHAR(255) NULL,
    [nin] NVARCHAR(50) NULL,
    [gender] NVARCHAR(10) NULL,
    [marital_status] NVARCHAR(20) NULL,
    [maiden_name] NVARCHAR(255) NULL,
    [passport_photo_path] NVARCHAR(255) NULL,
    [created_at] DATETIME2 NULL,
    [updated_at] DATETIME2 NULL,
    
    CONSTRAINT [FK_recertification_owners_application_id] 
        FOREIGN KEY ([application_id]) 
        REFERENCES [recertification_applications]([id]) 
        ON DELETE CASCADE
);
GO

-- Create indexes for better performance
CREATE INDEX [IX_recertification_applications_reference] 
    ON [recertification_applications]([application_reference]);
GO

CREATE INDEX [IX_recertification_applications_type] 
    ON [recertification_applications]([applicant_type]);
GO

CREATE INDEX [IX_recertification_applications_date] 
    ON [recertification_applications]([application_date]);
GO

CREATE INDEX [IX_recertification_owners_application_id] 
    ON [recertification_owners]([application_id]);
GO

PRINT 'Recertification tables created successfully!';