-- SQL script to create the user_activity_log_settings table
-- Run this directly in SQL Server Management Studio or your preferred SQL client

USE [klas]; -- Replace 'klas' with your actual database name

-- Create the user_activity_log_settings table
CREATE TABLE user_activity_log_settings (
    id BIGINT IDENTITY(1,1) PRIMARY KEY,
    user_id BIGINT NULL, -- null for global settings
    cleanup_interval NVARCHAR(20) NOT NULL DEFAULT 'weekly', -- daily, weekly, monthly
    retention_days INT NOT NULL DEFAULT 90, -- days to keep logs
    refresh_interval INT NOT NULL DEFAULT 30, -- seconds for auto-refresh
    records_per_page INT NOT NULL DEFAULT 25, -- records per page in table
    auto_logout_inactive BIT NOT NULL DEFAULT 0, -- auto-logout inactive sessions
    track_failed_logins BIT NOT NULL DEFAULT 1, -- track failed login attempts
    ip_based_alerts BIT NOT NULL DEFAULT 0, -- send alerts for new IP addresses
    email_notifications BIT NOT NULL DEFAULT 0, -- send email notifications
    settings_data NVARCHAR(MAX) NULL, -- additional settings as JSON
    created_at DATETIME2 NOT NULL DEFAULT GETDATE(),
    updated_at DATETIME2 NOT NULL DEFAULT GETDATE()
);

-- Create indexes for better performance
CREATE INDEX IX_user_activity_log_settings_user_id ON user_activity_log_settings (user_id);

-- Create unique constraint (one setting record per user, null for global)
CREATE UNIQUE INDEX UQ_user_activity_log_settings_user_id ON user_activity_log_settings (user_id);

-- Add foreign key constraint (if users table exists)
ALTER TABLE user_activity_log_settings 
ADD CONSTRAINT FK_user_activity_log_settings_user_id 
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

-- Create trigger for updated_at column
GO
CREATE TRIGGER TR_user_activity_log_settings_updated_at
ON user_activity_log_settings
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    UPDATE user_activity_log_settings 
    SET updated_at = GETDATE()
    FROM user_activity_log_settings u
    INNER JOIN inserted i ON u.id = i.id;
END;
GO

-- Insert default global settings
INSERT INTO user_activity_log_settings (
    user_id,
    cleanup_interval,
    retention_days,
    refresh_interval,
    records_per_page,
    auto_logout_inactive,
    track_failed_logins,
    ip_based_alerts,
    email_notifications
) VALUES (
    NULL, -- Global settings
    'weekly',
    90,
    30,
    25,
    0,
    1,
    0,
    0
);

PRINT 'User Activity Log Settings table created successfully with default global settings!';
PRINT 'You can now configure settings through the Settings modal in the User Activity Logs page.';