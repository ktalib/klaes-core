/* ============================================================================
   instrument_number_vaults.start_volume / start_serial — where a register began
   ----------------------------------------------------------------------------
   RUN THIS AGAINST SQL SERVER (the klaes sqlsrv database).

   Companion:
     database/sql/2026_09_08_add_register_origin_to_instrument_number_vaults_ledger.mysql.sql
     — run that one afterwards, against MYSQL, to mark the migration as applied.

   WHY
   The "Fill a missing registration number" picker on the instrument capture
   screen measures a register from the first number KLAES ever issued in it up to
   the number its vault stands on now, and offers everything in between that no
   registration holds. Until now that floor was INFERRED — the lowest surviving
   registration in the register's lowest volume.

   An inferred floor cannot tell "never issued" from "issued and then deleted".
   Delete the earliest registration in a register and the floor silently slides
   up to the next one, taking the hole with it. The picker then reports nothing
   missing, truthfully answering the only question it can ask.

   The Deed of Mortgage register is exactly this shape today: four KLAES-era
   registrations running 89/89/22 .. 92/92/22 with no break between them, so
   nothing is offered and the checkbox never appears.

   WHAT THIS DOES
   Adds TWO nullable columns to dbo.instrument_number_vaults:

     start_volume  INT NULL   The volume the book begins in.
     start_serial  INT NULL   The first serial KLAES issued in that volume.

   No existing value is touched and no vault moves. These are the floor of a
   SURVEY, not a starting point for issuing — the allocator never reads them.

   NOTHING CHANGES UNTIL SOMEBODY SETS THEM. Both are NULL after this script, and
   MissingRegistrationParticularsService falls back to the inferred floor
   whenever either is NULL, or when the pair describes no register at all (a
   floor above the vault's own position). The application checks for the columns
   separately, so the code can be uploaded before or after this script runs.

   HOW THEY GET SET
   On the "Registration Vault Configuration" panel under Manage Instrument Types,
   as "Register begins at" — Volume and Serial. Set them from the paper book: the
   first entry that KLAES itself wrote, not the first entry in the book.

   SAFETY
     - Re-runnable: each ALTER is guarded by a COL_LENGTH check.
     - Adds two columns. Modifies no row.
   ============================================================================ */

/* Preview — expect 0, 0 before the first run */
SELECT
    CASE WHEN COL_LENGTH('dbo.instrument_number_vaults', 'start_volume') IS NULL THEN 0 ELSE 1 END AS has_start_volume,
    CASE WHEN COL_LENGTH('dbo.instrument_number_vaults', 'start_serial') IS NULL THEN 0 ELSE 1 END AS has_start_serial;

IF COL_LENGTH('dbo.instrument_number_vaults', 'start_volume') IS NULL
    ALTER TABLE dbo.instrument_number_vaults ADD start_volume INT NULL;

IF COL_LENGTH('dbo.instrument_number_vaults', 'start_serial') IS NULL
    ALTER TABLE dbo.instrument_number_vaults ADD start_serial INT NULL;

/* Verify — expect 1, 1 */
SELECT
    CASE WHEN COL_LENGTH('dbo.instrument_number_vaults', 'start_volume') IS NULL THEN 0 ELSE 1 END AS has_start_volume,
    CASE WHEN COL_LENGTH('dbo.instrument_number_vaults', 'start_serial') IS NULL THEN 0 ELSE 1 END AS has_start_serial;

/* ----------------------------------------------------------------------------
   WHAT EACH REGISTER LOOKS LIKE RIGHT NOW — run this before setting anything.

   `inferred_origin` is the floor the survey is using today. Where it equals the
   number the office knows the book started at, there is nothing to record.
   Where it sits ABOVE it, everything below it is unreachable and that is what
   start_volume / start_serial are for.

   `stranded_above` counts registrations recorded PAST the vault's own position.
   Those are not gaps and are never offered, but they are the loudest sign that a
   register was re-based and left rows behind on the old numbering — Deed of
   Mortgage carries four (1/1/35 .. 4/4/35, captured before the vault was
   corrected down to volume 22), Deed of Assignment four at volume 76, and the
   Power of Attorney vault three at volume 17.
   ---------------------------------------------------------------------------- */
WITH reg AS (
    SELECT
        instrument_type,
        TRY_CONVERT(int, volume_no) AS v,
        TRY_CONVERT(int, serial_no) AS s
      FROM dbo.deed_registrations
     WHERE TRY_CONVERT(int, volume_no) >= 1
       AND TRY_CONVERT(int, serial_no) IS NOT NULL
),
vault AS (
    SELECT instrument_type, current_volume, current_serial
      FROM dbo.instrument_number_vaults
)
SELECT
    v.instrument_type                                                       AS vault_name,
    CONCAT(v.current_serial, '/', v.current_serial, '/', v.current_volume)   AS vault_stands_at,
    (SELECT COUNT(*) FROM reg r
      WHERE r.instrument_type = v.instrument_type
        AND r.v = v.current_volume)                                          AS rows_in_current_volume,
    (SELECT MIN(r.s) FROM reg r
      WHERE r.instrument_type = v.instrument_type
        AND r.v = v.current_volume)                                          AS lowest_serial_in_current_volume,
    (SELECT COUNT(*) FROM reg r
      WHERE r.instrument_type = v.instrument_type
        AND (r.v > v.current_volume
          OR (r.v = v.current_volume AND r.s > v.current_serial)))           AS stranded_above
  FROM vault v
 ORDER BY v.instrument_type;

/* The stranded rows themselves, named. Nothing here is wrong on its face — a
   registration at 1/1/35 is a real registration — but if the office decides
   those four mortgages belong on numbers in volume 22, they have to be
   re-numbered by hand; the picker will not offer their old numbers and must not.

   Occupancy Permit is excluded: its two registers (Direct Allocation and
   Resettlement) share one stored instrument_type and are separated only by
   volume range, so "above the vault" there is simply the other register. */
SELECT
    d.instrument_type,
    d.fileno,
    CONCAT(d.serial_no, '/', d.page_no, '/', d.volume_no) AS registered_as,
    d.created_at
  FROM dbo.deed_registrations d
  JOIN dbo.instrument_number_vaults v
    ON v.instrument_type = CASE
         WHEN d.instrument_type IN ('Deed of Gift')                    THEN 'Deed of Assignment'
         WHEN d.instrument_type IN ('Deed of Surrender and Release')   THEN 'Power of Attorney'
         WHEN d.instrument_type IN ('Tripartite Mortgage')             THEN 'Deed of Mortgage'
         ELSE d.instrument_type
       END
 WHERE d.instrument_type NOT LIKE '%Occupancy Permit%'
   AND (TRY_CONVERT(int, d.volume_no) > v.current_volume
     OR (TRY_CONVERT(int, d.volume_no) = v.current_volume
         AND TRY_CONVERT(int, d.serial_no) > v.current_serial))
 ORDER BY d.instrument_type, TRY_CONVERT(int, d.volume_no), TRY_CONVERT(int, d.serial_no);

/* ----------------------------------------------------------------------------
   SETTING A FLOOR BY HAND, if you would rather not use the screen. Example: the
   Deed of Mortgage book really begins at 85/85/22 —

     UPDATE dbo.instrument_number_vaults
        SET start_volume = 22, start_serial = 85, updated_at = SYSDATETIME()
      WHERE instrument_type = 'Deed of Mortgage';

   To go back to the inferred floor, set both back to NULL. A floor ABOVE the
   vault's own position is ignored by the application rather than obeyed.
   ---------------------------------------------------------------------------- */
