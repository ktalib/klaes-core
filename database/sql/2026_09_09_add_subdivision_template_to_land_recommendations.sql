/* ============================================================================
   land_recommendations — "Use Subdivision Template"  —  RUN THIS AGAINST SQL SERVER
   ----------------------------------------------------------------------------
   Companion: database/sql/2026_09_09_add_subdivision_template_to_land_recommendations_ledger.mysql.sql
   RUN THIS ONE FIRST, then that one against MySQL.

   WHY TWO FILES
   `php artisan migrate` keeps its ledger in the MySQL `klas` database, while
   this table lives on SQL Server. The sqlsrv `migrations` table is a legacy
   copy artisan no longer writes to — marking THAT one changes nothing.

   WHAT THIS DOES
   Adds the single-file subdivision option to the Recommendation stage.

     use_subdivision_template   the Yes/No answer on the capture form

   A subdivision child is normally captured through Batch Mode, which picks the
   mother from the register. That is impossible when the mother has never been
   indexed, so this flag lets ONE file print as a subdivision child without a
   batch, a linkage, or a mother record existing anywhere.

   ONE COLUMN, NOT TWO
   The mother file number needs no column of its own — it is the record's
   existing `old_file_number`, which is exactly where a child saved through a
   real subdivision batch keeps it. The only difference is that this one is
   typed by hand rather than picked from the register.

   SAFETY
     - Re-runnable: guarded by a COL_LENGTH check.
     - One new column. No existing row is rewritten: the flag defaults to 0,
       which is the behaviour every record has today.
   ============================================================================ */

/* Preview — expect the column to be absent (NULL) before the change */
SELECT COL_LENGTH('dbo.land_recommendations', 'use_subdivision_template') AS use_subdivision_template_before;

IF COL_LENGTH('dbo.land_recommendations', 'use_subdivision_template') IS NULL
BEGIN
    ALTER TABLE dbo.land_recommendations
        ADD use_subdivision_template BIT NOT NULL CONSTRAINT DF_land_recommendations_use_subdivision_template DEFAULT (0);
END
GO

/* Verify — expect 1 */
SELECT COL_LENGTH('dbo.land_recommendations', 'use_subdivision_template') AS use_subdivision_template_after;

/* Verify — expect every existing record to be OFF, i.e. unchanged behaviour */
SELECT COUNT(*) AS rows_total,
       SUM(CASE WHEN use_subdivision_template = 1 THEN 1 ELSE 0 END) AS rows_flagged
  FROM dbo.land_recommendations;
