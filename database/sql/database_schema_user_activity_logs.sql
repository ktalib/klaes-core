-- User Activity Logs Database Schema for Microsoft SQL Server
-- This script creates the user_activity_logs table with all necessary indexes and constraints

-- Create the user_activity_logs table
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='user_activity_logs' AND xtype='U')
BEGIN
    CREATE TABLE user_activity_logs (
        id BIGINT IDENTITY(1,1) PRIMARY KEY,
        user_id BIGINT NULL,
        ip_address NVARCHAR(45) NULL,
        user_agent NVARCHAR(MAX) NULL,
        login_time DATETIME2 NULL,
        logout_time DATETIME2 NULL,
        is_online BIT NOT NULL DEFAULT 0,
        session_id NVARCHAR(255) NULL,
        device_type NVARCHAR(50) NULL, -- mobile, tablet, desktop
        browser NVARCHAR(100) NULL,
        platform NVARCHAR(100) NULL,
        location NVARCHAR(255) NULL,
        activity_type NVARCHAR(50) NOT NULL DEFAULT 'login', -- login, logout, activity
        activity_description NVARCHAR(MAX) NULL,
        created_at DATETIME2 NOT NULL DEFAULT GETDATE(),
        updated_at DATETIME2 NOT NULL DEFAULT GETDATE()
    );
    
    PRINT 'Table user_activity_logs created successfully.';
END
ELSE
BEGIN
    PRINT 'Table user_activity_logs already exists.';
END

-- Create indexes for better performance
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_user_activity_logs_user_id')
BEGIN
    CREATE INDEX IX_user_activity_logs_user_id ON user_activity_logs (user_id);
    PRINT 'Index IX_user_activity_logs_user_id created.';
END

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_user_activity_logs_is_online')
BEGIN
    CREATE INDEX IX_user_activity_logs_is_online ON user_activity_logs (is_online);
    PRINT 'Index IX_user_activity_logs_is_online created.';
END

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_user_activity_logs_login_time')
BEGIN
    CREATE INDEX IX_user_activity_logs_login_time ON user_activity_logs (login_time);
    PRINT 'Index IX_user_activity_logs_login_time created.';
END

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_user_activity_logs_logout_time')
BEGIN
    CREATE INDEX IX_user_activity_logs_logout_time ON user_activity_logs (logout_time);
    PRINT 'Index IX_user_activity_logs_logout_time created.';
END

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_user_activity_logs_created_at')
BEGIN
    CREATE INDEX IX_user_activity_logs_created_at ON user_activity_logs (created_at);
    PRINT 'Index IX_user_activity_logs_created_at created.';
END

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_user_activity_logs_user_online')
BEGIN
    CREATE INDEX IX_user_activity_logs_user_online ON user_activity_logs (user_id, is_online);
    PRINT 'Index IX_user_activity_logs_user_online created.';
END

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_user_activity_logs_user_created')
BEGIN
    CREATE INDEX IX_user_activity_logs_user_created ON user_activity_logs (user_id, created_at);
    PRINT 'Index IX_user_activity_logs_user_created created.';
END

-- Add foreign key constraint (assuming users table exists)
IF NOT EXISTS (SELECT * FROM sys.foreign_keys WHERE name = 'FK_user_activity_logs_user_id')
BEGIN
    IF EXISTS (SELECT * FROM sysobjects WHERE name='users' AND xtype='U')
    BEGIN
        ALTER TABLE user_activity_logs 
        ADD CONSTRAINT FK_user_activity_logs_user_id 
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
        PRINT 'Foreign key constraint FK_user_activity_logs_user_id created.';
    END
    ELSE
    BEGIN
        PRINT 'Warning: users table not found. Foreign key constraint not created.';
    END
END

-- Create a trigger to automatically update the updated_at column
IF NOT EXISTS (SELECT * FROM sys.triggers WHERE name = 'TR_user_activity_logs_updated_at')
BEGIN
    EXEC('
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
    END
    ');
    PRINT 'Trigger TR_user_activity_logs_updated_at created.';
END

-- Insert some sample data for testing (optional)
-- Uncomment the following lines if you want to insert sample data

/*
-- Sample data insertion
IF NOT EXISTS (SELECT * FROM user_activity_logs WHERE user_id = 1)
BEGIN
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
    (1, '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', GETDATE(), NULL, 1, 'sample_session_1', 'desktop', 'Chrome', 'Windows', 'Lagos, Nigeria', 'login', 'User logged in successfully'),
    (2, '192.168.1.101', 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X)', DATEADD(HOUR, -1, GETDATE()), DATEADD(MINUTE, -30, GETDATE()), 0, 'sample_session_2', 'mobile', 'Safari', 'iOS', 'Abuja, Nigeria', 'logout', 'User logged out successfully'),
    (3, '192.168.1.102', 'Mozilla/5.0 (iPad; CPU OS 14_0 like Mac OS X)', DATEADD(HOUR, -2, GETDATE()), NULL, 1, 'sample_session_3', 'tablet', 'Safari', 'iOS', 'Port Harcourt, Nigeria', 'login', 'User logged in successfully');
    
    PRINT 'Sample data inserted successfully.';
END
*/

-- Create a stored procedure to clean up old activity logs
IF EXISTS (SELECT * FROM sys.procedures WHERE name = 'sp_cleanup_old_activity_logs')
BEGIN
    DROP PROCEDURE sp_cleanup_old_activity_logs;
END

EXEC('
CREATE PROCEDURE sp_cleanup_old_activity_logs
    @days_to_keep INT = 90
AS
BEGIN
    SET NOCOUNT ON;
    
    DECLARE @cutoff_date DATETIME2 = DATEADD(DAY, -@days_to_keep, GETDATE());
    DECLARE @deleted_count INT;
    
    DELETE FROM user_activity_logs 
    WHERE created_at < @cutoff_date;
    
    SET @deleted_count = @@ROWCOUNT;
    
    PRINT ''Deleted '' + CAST(@deleted_count AS NVARCHAR(10)) + '' old activity log records.'';
    
    RETURN @deleted_count;
END
');

PRINT 'Stored procedure sp_cleanup_old_activity_logs created.';

-- Create a view for active sessions
IF EXISTS (SELECT * FROM sys.views WHERE name = 'vw_active_user_sessions')
BEGIN
    DROP VIEW vw_active_user_sessions;
END

EXEC('
CREATE VIEW vw_active_user_sessions AS
SELECT 
    ual.id,
    ual.user_id,
    u.first_name + '' '' + u.last_name AS user_name,
    u.email,
    ual.ip_address,
    ual.device_type,
    ual.browser,
    ual.platform,
    ual.login_time,
    ual.session_id,
    DATEDIFF(MINUTE, ual.login_time, GETDATE()) AS session_duration_minutes
FROM user_activity_logs ual
LEFT JOIN users u ON ual.user_id = u.id
WHERE ual.is_online = 1
');

PRINT 'View vw_active_user_sessions created.';

-- Create a function to get user activity statistics
IF EXISTS (SELECT * FROM sys.objects WHERE name = 'fn_get_user_activity_stats' AND type = 'FN')
BEGIN
    DROP FUNCTION fn_get_user_activity_stats;
END

EXEC('
CREATE FUNCTION fn_get_user_activity_stats(@days INT = 30)
RETURNS TABLE
AS
RETURN
(
    SELECT 
        COUNT(*) AS total_sessions,
        COUNT(DISTINCT user_id) AS unique_users,
        SUM(CASE WHEN is_online = 1 THEN 1 ELSE 0 END) AS online_users,
        AVG(CASE 
            WHEN logout_time IS NOT NULL AND login_time IS NOT NULL 
            THEN DATEDIFF(MINUTE, login_time, logout_time) 
            ELSE NULL 
        END) AS avg_session_duration_minutes
    FROM user_activity_logs
    WHERE created_at >= DATEADD(DAY, -@days, GETDATE())
)
');

PRINT 'Function fn_get_user_activity_stats created.';

PRINT 'User Activity Logs database schema setup completed successfully!';
PRINT '';
PRINT 'Next steps:';
PRINT '1. Run the Laravel migration: php artisan migrate';
PRINT '2. Register the event listeners: php artisan event:generate';
PRINT '3. Add the middleware to your routes or globally';
PRINT '4. Access the user activity logs at: /user-activity-logs';
PRINT '';
PRINT 'Optional maintenance:';
PRINT '- Run cleanup procedure: EXEC sp_cleanup_old_activity_logs @days_to_keep = 90';
PRINT '- View active sessions: SELECT * FROM vw_active_user_sessions';
PRINT '- Get statistics: SELECT * FROM fn_get_user_activity_stats(30)';