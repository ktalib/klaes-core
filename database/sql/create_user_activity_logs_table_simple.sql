-- Simple SQL script to create the user_activity_logs table (without foreign key constraint)
-- Run this directly in SQL Server Management Studio or your preferred SQL client

USE [klas]; -- Replace 'klas' with your actual database name

-- Create the user_activity_logs table
CREATE TABLE user_activity_logs (
    id BIGINT IDENTITY(1,1) PRIMARY KEY,
    user_id BIGINT NULL,
    ip_address NVARCHAR(45) NULL,
    user_agent NVARCHAR(MAX) NULL,
    login_time DATETIME2 NULL,
    logout_time DATETIME2 NULL,
    is_online BIT NOT NULL DEFAULT 0,
    session_id NVARCHAR(255) NULL,
    device_type NVARCHAR(50) NULL,
    browser NVARCHAR(100) NULL,
    platform NVARCHAR(100) NULL,
    location NVARCHAR(255) NULL,
    activity_type NVARCHAR(50) NOT NULL DEFAULT 'login',
    activity_description NVARCHAR(MAX) NULL,
    created_at DATETIME2 NOT NULL DEFAULT GETDATE(),
    updated_at DATETIME2 NOT NULL DEFAULT GETDATE()
);

PRINT 'Table user_activity_logs created successfully.';

-- Create indexes for better performance
CREATE INDEX IX_user_activity_logs_user_id ON user_activity_logs (user_id);
CREATE INDEX IX_user_activity_logs_is_online ON user_activity_logs (is_online);
CREATE INDEX IX_user_activity_logs_login_time ON user_activity_logs (login_time);
CREATE INDEX IX_user_activity_logs_logout_time ON user_activity_logs (logout_time);
CREATE INDEX IX_user_activity_logs_created_at ON user_activity_logs (created_at);
CREATE INDEX IX_user_activity_logs_user_online ON user_activity_logs (user_id, is_online);
CREATE INDEX IX_user_activity_logs_user_created ON user_activity_logs (user_id, created_at);

PRINT 'Indexes created successfully.';
GO

-- Create trigger for updated_at column
CREATE TRIGGER TR_user_activity_logs_updated_at
ON user_activity_logs
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    UPDATE user_activity_logs 
    SET updated_at = GETDATE()
    FROM user_activity_logs u
    INNER JOIN inserted i ON u.id = i.id;
END;
GO

PRINT 'Trigger created successfully.';

-- Insert some sample data for testing
INSERT INTO user_activity_logs (
    user_id, 
    ip_address, 
    user_agent, 
    login_time, 
    logout_time, 
    is_online, 
    session_id, 
    device_type, 
    browser, 
    platform, 
    location, 
    activity_type, 
    activity_description
) VALUES 
(1, '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36', GETDATE(), NULL, 1, 'laravel_session_9f4e2a7b8c1d3e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0', 'desktop', 'Chrome', 'Windows', 'Lagos, Nigeria', 'login', 'User logged in successfully'),
(1, '192.168.1.101', 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0 Mobile/15E148 Safari/604.1', DATEADD(HOUR, -1, GETDATE()), DATEADD(MINUTE, -30, GETDATE()), 0, 'laravel_session_a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2', 'mobile', 'Safari', 'iOS', 'Abuja, Nigeria', 'logout', 'User logged out successfully'),
(1, '192.168.1.102', 'Mozilla/5.0 (iPad; CPU OS 14_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0 Mobile/15E148 Safari/604.1', DATEADD(HOUR, -2, GETDATE()), NULL, 1, 'laravel_session_b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3', 'tablet', 'Safari', 'iOS', 'Port Harcourt, Nigeria', 'login', 'User logged in successfully');

PRINT 'Sample data inserted successfully.';
PRINT '';
PRINT 'User Activity Logs table created successfully with sample data!';
PRINT 'You can now access the User Activity Logs page at: /user-activity-logs';
PRINT '';
PRINT 'Note: Foreign key constraint was not added. You can add it later if needed:';
PRINT 'ALTER TABLE user_activity_logs ADD CONSTRAINT FK_user_activity_logs_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;';