-- Create sessions table in SQL Server for Laravel
-- Run this in SQL Server Management Studio

USE [klas];
GO

-- Drop table if it exists (optional - remove this line if you want to keep existing data)
-- DROP TABLE IF EXISTS [dbo].[sessions];

-- Create sessions table
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
GO