-- KLAES Land Admin System - User Types and Levels Tables
-- MS SQL Server Script

-- Drop existing tables if they exist (in correct order for foreign keys)
IF OBJECT_ID('user_levels', 'U') IS NOT NULL
    DROP TABLE user_levels;

IF OBJECT_ID('user_types', 'U') IS NOT NULL
    DROP TABLE user_types;

-- Create user_types table
CREATE TABLE user_types (
    id BIGINT IDENTITY(1,1) PRIMARY KEY,
    name NVARCHAR(255) NOT NULL,
    code NVARCHAR(50) NOT NULL,
    description NVARCHAR(MAX) NULL,
    level_priority INT NOT NULL DEFAULT 1,
    is_active BIT NOT NULL DEFAULT 1,
    created_at DATETIME2(0) NOT NULL DEFAULT GETDATE(),
    updated_at DATETIME2(0) NOT NULL DEFAULT GETDATE()
);

-- Create user_levels table
CREATE TABLE user_levels (
    id BIGINT IDENTITY(1,1) PRIMARY KEY,
    name NVARCHAR(255) NOT NULL,
    code NVARCHAR(50) NOT NULL,
    description NVARCHAR(MAX) NULL,
    user_type_id BIGINT NOT NULL,
    priority INT NOT NULL DEFAULT 1,
    is_active BIT NOT NULL DEFAULT 1,
    created_at DATETIME2(0) NOT NULL DEFAULT GETDATE(),
    updated_at DATETIME2(0) NOT NULL DEFAULT GETDATE(),
    CONSTRAINT FK_user_levels_user_type_id FOREIGN KEY (user_type_id) REFERENCES user_types(id) ON DELETE CASCADE
);

-- Insert User Types
INSERT INTO user_types (name, code, description, level_priority, is_active, created_at, updated_at)
VALUES 
    ('Management', 'MGT', 'Management level with highest access', 4, 1, GETDATE(), GETDATE()),
    ('Operations', 'OPS', 'Operational staff with high access level', 3, 1, GETDATE(), GETDATE()),
    ('ALL', 'ALL', 'Universal access for all users', 2, 1, GETDATE(), GETDATE()),
    ('User', 'USER', 'Basic user with lowest access level', 1, 1, GETDATE(), GETDATE()),
    ('System', 'SYS', 'System administrator with highest privileges', 5, 1, GETDATE(), GETDATE());

-- Insert User Levels
INSERT INTO user_levels (name, code, description, user_type_id, priority, is_active, created_at, updated_at)
VALUES 
    -- Management levels
    ('Highest', 'HIGHEST', 'Highest level access for management', 1, 4, 1, GETDATE(), GETDATE()),
    
    -- Operations levels
    ('Administrative', 'ADMIN', 'Administrative operations level', 2, 3, 1, GETDATE(), GETDATE()),
    ('Technical', 'TECH', 'Technical operations level', 2, 3, 1, GETDATE(), GETDATE()),
    ('Finance', 'FIN', 'Finance operations level', 2, 3, 1, GETDATE(), GETDATE()),
    ('High', 'HIGH', 'High level operations access', 2, 3, 1, GETDATE(), GETDATE()),
    
    -- ALL levels
    ('Lowest', 'LOWEST', 'Lowest level access for all users', 3, 1, 1, GETDATE(), GETDATE()),
    
    -- User levels
    ('Lowest', 'LOWEST', 'Lowest level access for basic users', 4, 1, 1, GETDATE(), GETDATE()),
    
    -- System levels
    ('Highest', 'HIGHEST', 'Highest system access', 5, 5, 1, GETDATE(), GETDATE()),
    ('High', 'HIGH', 'High system access', 5, 4, 1, GETDATE(), GETDATE());

-- Create indexes for better performance
CREATE INDEX IX_user_levels_user_type_id ON user_levels(user_type_id);
CREATE INDEX IX_user_types_is_active ON user_types(is_active);
CREATE INDEX IX_user_levels_is_active ON user_levels(is_active);

-- Verify the data
SELECT 'User Types' as TableName, COUNT(*) as RecordCount FROM user_types
UNION ALL
SELECT 'User Levels' as TableName, COUNT(*) as RecordCount FROM user_levels;

-- Show the structure
SELECT 
    ut.name as UserType,
    ul.name as UserLevel,
    ul.code as LevelCode,
    ul.priority as Priority
FROM user_types ut
LEFT JOIN user_levels ul ON ut.id = ul.user_type_id
ORDER BY ut.level_priority DESC, ul.priority DESC;