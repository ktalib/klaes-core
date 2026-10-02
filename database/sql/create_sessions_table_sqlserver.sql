-- Create sessions table for Laravel session management in SQL Server
-- Execute this script in your SQL Server Management Studio

USE [klas];
GO

-- Check if sessions table exists, if not create it
IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[sessions]') AND type in (N'U'))
BEGIN
    CREATE TABLE [dbo].[sessions] (
        [id] NVARCHAR(255) NOT NULL,
        [user_id] BIGINT NULL,
        [ip_address] NVARCHAR(45) NULL,
        [user_agent] NTEXT NULL,
        [payload] NTEXT NOT NULL,
        [last_activity] INT NOT NULL,
        CONSTRAINT [PK_sessions] PRIMARY KEY CLUSTERED ([id] ASC)
    );
    
    -- Create indexes for better performance
    CREATE NONCLUSTERED INDEX [IX_sessions_user_id] ON [dbo].[sessions] ([user_id] ASC);
    CREATE NONCLUSTERED INDEX [IX_sessions_last_activity] ON [dbo].[sessions] ([last_activity] ASC);
    
    PRINT 'Sessions table created successfully in SQL Server';
END
ELSE
BEGIN
    PRINT 'Sessions table already exists in SQL Server';
END

-- Optional: Add foreign key constraint if users table exists
-- Uncomment the following lines if you want to enforce referential integrity
/*
IF EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[users]') AND type in (N'U'))
BEGIN
    IF NOT EXISTS (SELECT * FROM sys.foreign_keys WHERE name = 'FK_sessions_user_id')
    BEGIN
        ALTER TABLE [dbo].[sessions]
        ADD CONSTRAINT [FK_sessions_user_id] 
        FOREIGN KEY ([user_id]) REFERENCES [dbo].[users]([id]) 
        ON DELETE CASCADE;
        
        PRINT 'Foreign key constraint added to sessions table';
    END
END
*/

GO