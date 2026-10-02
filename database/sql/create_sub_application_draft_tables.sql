-- Create Sub Application Draft Tables
-- This script creates the necessary tables for the sub-application draft system

-- 1. Create sub_application_draft table
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='sub_application_draft' AND xtype='U')
BEGIN
    CREATE TABLE sub_application_draft (
        id BIGINT IDENTITY(1,1) PRIMARY KEY,
        draft_id UNIQUEIDENTIFIER NOT NULL UNIQUE,
        sub_application_id BIGINT NULL,
        main_application_id BIGINT NULL,
        form_state NVARCHAR(MAX) NULL, -- JSON data
        progress_percent DECIMAL(5,2) DEFAULT 0.00,
        last_completed_step INT DEFAULT 1,
        auto_save_frequency INT DEFAULT 30,
        is_locked BIT DEFAULT 0,
        locked_by BIGINT NULL,
        locked_at DATETIME2 NULL,
        version INT DEFAULT 1,
        last_saved_by BIGINT NOT NULL,
        last_saved_at DATETIME2 NULL,
        analytics NVARCHAR(MAX) NULL, -- JSON data
        collaborators NVARCHAR(MAX) NULL, -- JSON data
        last_error NVARCHAR(MAX) NULL,
        unit_file_no NVARCHAR(255) NULL,
        is_sua BIT DEFAULT 0,
        created_at DATETIME2 DEFAULT GETDATE(),
        updated_at DATETIME2 DEFAULT GETDATE()
    );

    -- Create indexes
    CREATE INDEX IX_sub_application_draft_last_saved_by_is_sua ON sub_application_draft (last_saved_by, is_sua);
    CREATE INDEX IX_sub_application_draft_main_application_id_is_sua ON sub_application_draft (main_application_id, is_sua);
    CREATE INDEX IX_sub_application_draft_unit_file_no ON sub_application_draft (unit_file_no);
    CREATE INDEX IX_sub_application_draft_sub_application_id ON sub_application_draft (sub_application_id);
    
    PRINT 'Table sub_application_draft created successfully';
END
ELSE
BEGIN
    PRINT 'Table sub_application_draft already exists';
END

-- 2. Create sub_application_draft_versions table
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='sub_application_draft_versions' AND xtype='U')
BEGIN
    CREATE TABLE sub_application_draft_versions (
        id BIGINT IDENTITY(1,1) PRIMARY KEY,
        draft_id UNIQUEIDENTIFIER NOT NULL,
        version INT NOT NULL,
        snapshot NVARCHAR(MAX) NOT NULL, -- JSON data
        created_by BIGINT NOT NULL,
        created_at DATETIME2 DEFAULT GETDATE(),
        updated_at DATETIME2 DEFAULT GETDATE()
    );

    -- Create indexes
    CREATE INDEX IX_sub_application_draft_versions_draft_id_version ON sub_application_draft_versions (draft_id, version);
    CREATE INDEX IX_sub_application_draft_versions_created_by ON sub_application_draft_versions (created_by);
    
    PRINT 'Table sub_application_draft_versions created successfully';
END
ELSE
BEGIN
    PRINT 'Table sub_application_draft_versions already exists';
END

-- 3. Create sub_application_draft_collaborators table
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='sub_application_draft_collaborators' AND xtype='U')
BEGIN
    CREATE TABLE sub_application_draft_collaborators (
        id BIGINT IDENTITY(1,1) PRIMARY KEY,
        draft_id UNIQUEIDENTIFIER NOT NULL,
        user_id BIGINT NOT NULL,
        role NVARCHAR(50) DEFAULT 'editor',
        invited_at DATETIME2 NULL,
        accepted_at DATETIME2 NULL,
        created_at DATETIME2 DEFAULT GETDATE(),
        updated_at DATETIME2 DEFAULT GETDATE()
    );

    -- Create indexes
    CREATE INDEX IX_sub_application_draft_collaborators_draft_id_user_id ON sub_application_draft_collaborators (draft_id, user_id);
    CREATE INDEX IX_sub_application_draft_collaborators_user_id ON sub_application_draft_collaborators (user_id);
    
    PRINT 'Table sub_application_draft_collaborators created successfully';
END
ELSE
BEGIN
    PRINT 'Table sub_application_draft_collaborators already exists';
END

PRINT 'All sub-application draft tables created successfully!';