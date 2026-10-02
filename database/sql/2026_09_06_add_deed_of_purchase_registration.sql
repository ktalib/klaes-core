/* ============================================================================
   Land Registration -- "Deed of Purchase"
   ----------------------------------------------------------------------------
   Implements the data half of
   docs/KLAES_Deed_of_Purchase_Land_Registration_Update.md.

   WHY
   The Land Registry registers deeds of sale and had no instrument of its own.
   Per section 2.1 of the spec there is deliberately NO new physical Land
   Registration table: new records go into the existing registration table and
   are separated from Digitization by a classification column. Historical deeds
   of purchase are NOT covered here -- they continue to be backfilled through
   PRA (section 4.1).

   A new instrument type is a ROW, not a code change, and there is no migration
   for any of this: the migrations ledger lives in MySQL while these tables are
   in SQL Server, so a migration would be recorded as run whether or not the
   changes landed. `php artisan land-registration:install` does the same work
   for dev and staging.

   WHAT
     dbo.deed_registrations.registration_category    NEW COLUMN varchar(50) NULL
     dbo.instrument_number_vaults.current_volume_count  NEW COLUMN int NULL
     dbo.new_instrument_types      'Deed of Purchase'
     dbo.InstrumentTypes           'Deed of Purchase'
     dbo.instrument_number_vaults  serial 4019 / page 4019 / volume 22 / 0 in volume
     dbo.user_roles                'Land Registration'

   Both lookup tables are required: they are separate, neither reads the other,
   and they feed different screens. Both new columns are nullable and additive,
   so every existing row and the whole Digitization workflow are unaffected.

   THE CLASSIFICATION (spec sections 4.2 and 5)
   registration_category tells a Land Registration row from a Digitization one.
   Existing rows stay NULL and are read as Digitization; the application writes
   'land_registration' or 'digitization' on every new registration. This column
   IS the separation the spec asks for -- there is no second table.

   THE NUMBERING (spec sections 9-14, 18)
   These rules are NOT the Deeds rules:

     * Serial and page are four-digit counters that roll 9999 -> 1.
     * The volume advances after 300 registrations have been written into it.
     * Those two are INDEPENDENT: a serial rolling over must NOT reset the
       volume. (Deeds ties them together; the Land register must not.)

   current_volume_count is what the 300-entry capacity counts against. It cannot
   be derived by counting registrations, because a deleted registration must not
   hand its slot back -- its number is never re-issued, so the volume it sat in
   is still that much closer to full.

   >>> VERIFY BEFORE YOU COMMIT <<<
   Section 18 gives the last manual entry as serial 4019 / page 4019 / volume 22,
   so the first system record is 4020/4020/22. Section 9 says the handwritten
   serial and page MUST be checked against the final manual entry first. The
   volume (22) is confirmed; the serial and page are not.

   @seed_vol_count is how many entries volume 22 ALREADY holds. It cannot be
   known from the paper register alone. Left at 0, volume 22 will accept 300
   more entries before advancing, which is almost certainly too many.

   SAFETY
     - Re-runnable: every ALTER and INSERT is guarded.
     - THE VAULT IS SEEDED ONCE. If the row already exists this script leaves it
       alone: re-seeding a live counter re-issues serials that may already be
       printed on certificates. STEP 0 tells you which case you are in.
     - No existing row is modified and no captured record is touched.

   USAGE
     Run against the SQL Server `klas` database, check STEP 3, COMMIT.
     There is NO MySQL ledger companion -- see above.
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @type           varchar(100) = 'Deed of Purchase';
DECLARE @role           varchar(100) = 'Land Registration';
DECLARE @seed_serial    int = 4019;   /* VERIFY against the final manual entry */
DECLARE @seed_page      int = 4019;   /* VERIFY against the final manual entry */
DECLARE @seed_volume    int = 22;     /* confirmed by the spec                 */
DECLARE @seed_vol_count int = 0;      /* entries volume 22 already holds       */
DECLARE @description    varchar(500) = 'Sale and purchase of an interest in land.';

BEGIN TRANSACTION;

/* ---------------------------------------------------------------------------
   STEP 0 -- Preview. Read the vault line carefully before committing.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 0: current state ===';

SELECT
    CASE WHEN COL_LENGTH('dbo.deed_registrations', 'registration_category') IS NULL
         THEN 'will be added' ELSE 'present' END AS deed_registrations_category_column,
    CASE WHEN COL_LENGTH('dbo.instrument_number_vaults', 'current_volume_count') IS NULL
         THEN 'will be added' ELSE 'present' END AS vaults_volume_count_column,
    CASE WHEN EXISTS (SELECT 1 FROM dbo.new_instrument_types WHERE name = @type)
         THEN 'present' ELSE 'will be added' END AS new_instrument_types,
    CASE WHEN EXISTS (SELECT 1 FROM dbo.InstrumentTypes WHERE LTRIM(RTRIM(InstrumentName)) = @type)
         THEN 'present' ELSE 'will be added' END AS InstrumentTypes,
    CASE WHEN EXISTS (SELECT 1 FROM dbo.user_roles WHERE name = @role)
         THEN 'present' ELSE 'will be added' END AS user_roles;

SELECT
    CASE WHEN EXISTS (SELECT 1 FROM dbo.instrument_number_vaults WHERE instrument_type = @type)
         THEN 'VAULT ALREADY EXISTS - it will NOT be touched. Check the numbers below.'
         ELSE 'vault will be seeded, so the first registration prints '
              + CAST(@seed_serial + 1 AS varchar(10)) + '/'
              + CAST(@seed_page   + 1 AS varchar(10)) + '/'
              + CAST(@seed_volume     AS varchar(10)) END AS vault_state;

SELECT instrument_type, current_serial, current_page, current_volume, updated_at
  FROM dbo.instrument_number_vaults
 WHERE instrument_type = @type;

/* How many rows already exist for this type. Expect 0 on a first install. */
SELECT
    (SELECT COUNT(*) FROM dbo.instrument_capture WHERE instrument_type = @type) AS captured_rows,
    (SELECT COUNT(*) FROM dbo.deed_registrations WHERE instrument_type = @type) AS registered_rows;

/* ---------------------------------------------------------------------------
   STEP 1 -- Columns. Both nullable and additive.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 1: columns ===';

IF COL_LENGTH('dbo.deed_registrations', 'registration_category') IS NULL
BEGIN
    ALTER TABLE dbo.deed_registrations ADD registration_category varchar(50) NULL;
    PRINT '  deed_registrations.registration_category added';
END
ELSE
    PRINT '  deed_registrations.registration_category already present';

IF COL_LENGTH('dbo.instrument_number_vaults', 'current_volume_count') IS NULL
BEGIN
    ALTER TABLE dbo.instrument_number_vaults ADD current_volume_count int NULL;
    PRINT '  instrument_number_vaults.current_volume_count added';
END
ELSE
    PRINT '  instrument_number_vaults.current_volume_count already present';

/* ---------------------------------------------------------------------------
   STEP 2 -- Reference data.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 2: reference data ===';

INSERT INTO dbo.new_instrument_types (name, description, created_at, updated_at)
SELECT @type, @description, SYSDATETIME(), SYSDATETIME()
 WHERE NOT EXISTS (SELECT 1 FROM dbo.new_instrument_types WHERE name = @type);
PRINT '  new_instrument_types rows inserted: ' + CAST(@@ROWCOUNT AS varchar(10));

INSERT INTO dbo.InstrumentTypes (InstrumentName, Description, IsActive)
SELECT @type, @description, 1
 WHERE NOT EXISTS (SELECT 1 FROM dbo.InstrumentTypes WHERE LTRIM(RTRIM(InstrumentName)) = @type);
PRINT '  InstrumentTypes rows inserted: ' + CAST(@@ROWCOUNT AS varchar(10));

INSERT INTO dbo.user_roles (name, guard_name, description, is_active, created_at, updated_at)
SELECT @role, 'web', 'Register instruments in the Land Registry', 1, SYSDATETIME(), SYSDATETIME()
 WHERE NOT EXISTS (SELECT 1 FROM dbo.user_roles WHERE name = @role);
PRINT '  user_roles rows inserted: ' + CAST(@@ROWCOUNT AS varchar(10));

/* The numbering vault. Seeded ONLY if absent -- see SAFETY above. */
INSERT INTO dbo.instrument_number_vaults
       (instrument_type, current_serial, current_page, current_volume, current_volume_count, updated_at)
SELECT @type, @seed_serial, @seed_page, @seed_volume, @seed_vol_count, SYSDATETIME()
 WHERE NOT EXISTS (SELECT 1 FROM dbo.instrument_number_vaults WHERE instrument_type = @type);
PRINT '  vault rows inserted: ' + CAST(@@ROWCOUNT AS varchar(10)) + '  (0 means it already existed and was left alone)';

/* ---------------------------------------------------------------------------
   STEP 3 -- Verify, then COMMIT or ROLLBACK.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 3: result ===';

SELECT 'new_instrument_types' AS source, name AS value FROM dbo.new_instrument_types WHERE name = @type
UNION ALL
SELECT 'InstrumentTypes', InstrumentName FROM dbo.InstrumentTypes WHERE LTRIM(RTRIM(InstrumentName)) = @type
UNION ALL
SELECT 'user_roles', name FROM dbo.user_roles WHERE name = @role;

SELECT instrument_type,
       current_serial,
       current_page,
       current_volume,
       current_volume_count,
       CAST(current_serial + 1 AS varchar(10)) + '/' +
       CAST(current_page   + 1 AS varchar(10)) + '/' +
       CAST(current_volume     AS varchar(10)) AS next_number_to_be_issued,
       300 - ISNULL(current_volume_count, 0)   AS entries_left_in_this_volume
  FROM dbo.instrument_number_vaults
 WHERE instrument_type = @type;

/* Expected on a first install: three reference rows, both columns present, and
   next_number_to_be_issued reading 4020/4020/22 (or whatever the verified
   manual continuation is).
   If so:                COMMIT TRANSACTION;
   If anything is off:   ROLLBACK TRANSACTION;                                  */

PRINT '=== Transaction left OPEN. Review STEP 3, then COMMIT or ROLLBACK. ===';

-- COMMIT TRANSACTION;
-- ROLLBACK TRANSACTION;
