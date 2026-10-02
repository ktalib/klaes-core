/* ============================================================================
   OP → File Property ID Matching — the audit trail
   ----------------------------------------------------------------------------
   RUN THIS AGAINST SQL SERVER (the klaes sqlsrv database).

   Companion:
     database/sql/2026_09_03_create_op_propid_matches_ledger.mysql.sql
     — run that one afterwards, against MYSQL, to mark the migration as applied.

   WHAT THIS DOES
   Creates op_propid_matches. The OP → File Property ID Matching page rewrites
   prop_id on Occupancy Permit rows in bulk: the officer picks the confirmed file
   on the left, picks the permits that belong to it on the right, and every
   selected permit is moved onto the file's Property ID.

   WHY IT IS NEEDED
   The old prop_id is OVERWRITTEN IN PLACE. Nothing in `pra` or
   `instrument_capture` remembers what it used to be, so without this table a
   mis-keyed batch is both unrecoverable and unattributable. This is also what
   the page's Undo reads.

   SHAPE
   One row per record touched, not per batch — a batch that moved eight permits
   and their four companion Transfer of Title rows writes twelve rows sharing one
   batch_ref. Per-record is what lets an undo skip a single record that something
   else has moved on since.

     record_kind = 'op'         the permit the officer ticked
     record_kind = 'companion'  a Transfer of Title carried along with it, because
                                a permit and its transfer are one parcel and
                                leaving the transfer behind splits the file's
                                Legal Search timeline in two

   previous_prop_id is NVARCHAR, not INT: pra.prop_id is nvarchar(100) and holds
   blanks and non-canonical long ids in the wild, and the whole point of the
   column is to restore EXACTLY what was there.

   SAFETY
     - Re-runnable: guarded by OBJECT_ID checks.
     - Creates one new table. Touches no existing data.
   ============================================================================ */

IF OBJECT_ID('dbo.op_propid_matches', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.op_propid_matches (
        id                  BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,

        /* One batch of work, as the officer performed it. Undo is addressed by this. */
        batch_ref           NVARCHAR(40)   NOT NULL,

        /* The control record: the file whose Property ID everything was moved onto. */
        target_file_number  NVARCHAR(100)  NULL,
        target_prop_id      INT            NOT NULL,

        /* Which table the moved record lives in, and which row. */
        source_table        NVARCHAR(40)   NOT NULL,
        record_id           BIGINT         NOT NULL,

        /* 'op' — ticked by the officer; 'companion' — carried along with one. */
        record_kind         NVARCHAR(20)   NOT NULL CONSTRAINT DF_op_propid_matches_kind DEFAULT ('op'),

        /* Denormalised, so a row still reads on its own once the record moves again. */
        op_serial_number    NVARCHAR(100)  NULL,
        record_file_number  NVARCHAR(100)  NULL,

        previous_prop_id    NVARCHAR(100)  NULL,
        new_prop_id         NVARCHAR(100)  NOT NULL,

        matched_by          BIGINT         NULL,

        /* Set when the batch is reversed. A reverted row is kept, not deleted: that a
           batch was undone is itself part of the file's history. */
        reverted_at         DATETIME2(0)   NULL,
        reverted_by         BIGINT         NULL,

        created_at          DATETIME2(0)   NULL,
        updated_at          DATETIME2(0)   NULL
    );
END;
GO

IF OBJECT_ID('dbo.op_propid_matches', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'op_propid_matches_batch_ref_index'
                     AND object_id = OBJECT_ID('dbo.op_propid_matches'))
BEGIN
    CREATE INDEX op_propid_matches_batch_ref_index ON dbo.op_propid_matches (batch_ref);
END;
GO

IF OBJECT_ID('dbo.op_propid_matches', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'op_propid_matches_record_idx'
                     AND object_id = OBJECT_ID('dbo.op_propid_matches'))
BEGIN
    CREATE INDEX op_propid_matches_record_idx ON dbo.op_propid_matches (source_table, record_id);
END;
GO

IF OBJECT_ID('dbo.op_propid_matches', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'op_propid_matches_target_idx'
                     AND object_id = OBJECT_ID('dbo.op_propid_matches'))
BEGIN
    CREATE INDEX op_propid_matches_target_idx ON dbo.op_propid_matches (target_prop_id);
END;
GO

/* Verify — expect one row describing the table. */
SELECT COUNT(*) AS table_present
  FROM sys.tables
 WHERE name = 'op_propid_matches';
GO
