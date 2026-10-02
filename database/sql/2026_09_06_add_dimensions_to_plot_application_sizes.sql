/* ============================================================================
   Somewhere to keep a parcel's sides, beside the area they give
   ----------------------------------------------------------------------------
   RUN THIS AGAINST SQL SERVER (the `klas` database on the sqlsrv connection).

   Companion: 2026_09_06_add_dimensions_to_plot_application_sizes_ledger.mysql.sql,
   which runs against MYSQL and marks the migration applied. Run this one FIRST.

   WHY THIS COLUMN
   The New Subdivision form now asks for plot sizes the way the Duplex parcel
   update (New APU) does: the officer types the SIDES off the survey plan, in
   metres, and the area is derived from them rather than typed. plot_size stays
   the figure everything downstream calculates with -- App\Support\
   ParcelSizeSummary prints the recommendation memo from it, in m2 -- and this
   column records what was actually measured, which is how the Ministry's memo
   states a parcel:

       60 x 21 x 46 x 21 x 42.71

   A string, not a pair of decimals: a parcel is a polygon of any number of
   sides, and the memo prints the run verbatim.

   SAFETY
     - Re-runnable: the ALTER is guarded by a COL_LENGTH check.
     - NULLable. Existing rows keep no sides, which is correct: they were
       captured as a single figure, and back-filling sides would be inventing
       data.
     - No existing column or row is touched.
   ============================================================================ */

IF OBJECT_ID('dbo.plot_application_sizes', 'U') IS NOT NULL
   AND COL_LENGTH('dbo.plot_application_sizes', 'dimensions') IS NULL
BEGIN
    ALTER TABLE dbo.plot_application_sizes ADD dimensions NVARCHAR(255) NULL;
    PRINT 'Added dbo.plot_application_sizes.dimensions';
END
ELSE
    PRINT 'dbo.plot_application_sizes.dimensions already present (or table missing) - skipped';
GO

/* ---------------------------------------------------------------------------
   Verify -- PASS means the New Subdivision form can save the sides it collects.
   A FAIL here does not break capture: the insert would fail outright, so the
   whole subdivision would be rejected with a 500 rather than saved without its
   dimensions.
   --------------------------------------------------------------------------- */
SELECT
    CASE WHEN COL_LENGTH('dbo.plot_application_sizes', 'dimensions') IS NULL
         THEN 'FAIL - dbo.plot_application_sizes.dimensions is missing; subdivision capture will 500 on save'
         ELSE 'PASS - dbo.plot_application_sizes.dimensions present'
    END AS dimensions_column;
GO
