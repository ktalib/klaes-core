-- Create thumbnails table for storing PDF page thumbnails
-- This table stores thumbnail information for split PDF pages

USE [klas]
GO

IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='thumbnails' AND xtype='U')
BEGIN
    CREATE TABLE [dbo].[thumbnails](
        [id] [int] IDENTITY(1,1) NOT NULL,
        [file_indexing_id] [int] NOT NULL,
        [scanning_id] [int] NULL,
        [file_number] [nvarchar](255) NOT NULL,
        [page_number] [int] NOT NULL,
        [page_type_id] [int] NULL,
        [thumbnail_path] [nvarchar](500) NOT NULL,
        [original_filename] [nvarchar](255) NULL,
        [file_size] [bigint] NULL,
        [mime_type] [nvarchar](100) NULL,
        [is_active] [bit] NOT NULL DEFAULT 1,
        [created_at] [datetime2](7) NOT NULL DEFAULT GETDATE(),
        [updated_at] [datetime2](7) NOT NULL DEFAULT GETDATE(),
     CONSTRAINT [PK_thumbnails] PRIMARY KEY CLUSTERED
    (
        [id] ASC
    )WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
    ) ON [PRIMARY]
END
GO

-- Create indexes for better performance
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE object_id = OBJECT_ID(N'[dbo].[thumbnails]') AND name = N'IX_thumbnails_file_indexing_id')
BEGIN
    CREATE NONCLUSTERED INDEX [IX_thumbnails_file_indexing_id] ON [dbo].[thumbnails]
    (
        [file_indexing_id] ASC
    )WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
END
GO

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE object_id = OBJECT_ID(N'[dbo].[thumbnails]') AND name = N'IX_thumbnails_file_number')
BEGIN
    CREATE NONCLUSTERED INDEX [IX_thumbnails_file_number] ON [dbo].[thumbnails]
    (
        [file_number] ASC
    )WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
END
GO

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE object_id = OBJECT_ID(N'[dbo].[thumbnails]') AND name = N'IX_thumbnails_page_type_id')
BEGIN
    CREATE NONCLUSTERED INDEX [IX_thumbnails_page_type_id] ON [dbo].[thumbnails]
    (
        [page_type_id] ASC
    )WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
END
GO

-- Add foreign key constraints (uncomment if the referenced tables exist)
-- ALTER TABLE [dbo].[thumbnails]  WITH CHECK ADD  CONSTRAINT [FK_thumbnails_file_indexing] FOREIGN KEY([file_indexing_id])
-- REFERENCES [dbo].[file_indexing] ([id])
-- GO

-- ALTER TABLE [dbo].[thumbnails] CHECK CONSTRAINT [FK_thumbnails_file_indexing]
-- GO

-- ALTER TABLE [dbo].[thumbnails]  WITH CHECK ADD  CONSTRAINT [FK_thumbnails_scanning] FOREIGN KEY([scanning_id])
-- REFERENCES [dbo].[scannings] ([id])
-- GO

-- ALTER TABLE [dbo].[thumbnails] CHECK CONSTRAINT [FK_thumbnails_scanning]
-- GO

-- ALTER TABLE [dbo].[thumbnails]  WITH CHECK ADD  CONSTRAINT [FK_thumbnails_page_type] FOREIGN KEY([page_type_id])
-- REFERENCES [dbo].[page_types] ([id])
-- GO

-- ALTER TABLE [dbo].[thumbnails] CHECK CONSTRAINT [FK_thumbnails_page_type]
-- GO

-- Create trigger for updated_at timestamp
IF NOT EXISTS (SELECT * FROM sys.triggers WHERE object_id = OBJECT_ID(N'[dbo].[tr_thumbnails_updated_at]'))
BEGIN
    EXEC('
    CREATE TRIGGER [dbo].[tr_thumbnails_updated_at]
    ON [dbo].[thumbnails]
    AFTER UPDATE
    AS
    BEGIN
        SET NOCOUNT ON;
        UPDATE [dbo].[thumbnails]
        SET [updated_at] = GETDATE()
        FROM [dbo].[thumbnails] t
        INNER JOIN inserted i ON t.id = i.id;
    END
    ');
END
GO

PRINT 'Thumbnails table created successfully!'
GO
