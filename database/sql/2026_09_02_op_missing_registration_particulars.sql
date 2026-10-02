/* ============================================================================
   Occupancy Permits with no registration particulars
   ----------------------------------------------------------------------------
   REVIEW ONLY. Nothing here updates a row.

   Finds the OP transactions this feature is about: a permit that was never
   entered in the state deeds registry, which is what an LGA-issued permit looks
   like. From 2026-09-02 the capture screens stamp these as 0/0/0 with blank
   date and time; the rows below are the ones that already exist, so they can be
   reviewed and mapped to the Local Government that actually issued them.

   Run each query separately. Query 1 is the literal criterion (regNo = '0/0/0');
   query 2 is the same shape with the regNo never stamped at all, which is far
   more common and is almost certainly part of the same population.

   Connection: sqlsrv.
   ============================================================================ */


/* ---------------------------------------------------------------------------
   1) The literal criterion: regNo = '0/0/0', registration date and time blank.
   Dev run 2026-09-02: 17 rows (11 of them also have deeds_date/deeds_time blank).
   --------------------------------------------------------------------------- */
SELECT
    p.id                                        AS pra_id,
    COALESCE(NULLIF(LTRIM(RTRIM(p.mlsFNo)), ''), p.fileno) AS file_number,
    p.prop_id,
    p.op_type,
    p.op_serial_number,
    p.Grantor                                   AS party_1_current,   -- becomes the LGA
    p.Grantee                                   AS party_2_allottee,
    p.lgsaOrCity                                AS lga_hint,          -- best mapping hint
    p.districtName,
    p.location,
    p.regNo,
    p.reg_date,
    p.reg_time,
    p.deeds_date,
    p.deeds_time,
    p.transaction_date,
    p.system_source,
    p.created_at
FROM pra AS p
WHERE (p.instrument_type LIKE '%Occupancy Permit%' OR p.transaction_type LIKE '%Occupancy Permit%')
  AND (p.is_deleted IS NULL OR p.is_deleted = 0)
  AND LTRIM(RTRIM(ISNULL(p.regNo, ''))) = '0/0/0'
  AND (p.reg_date IS NULL OR LTRIM(RTRIM(p.reg_date)) = '')
  AND (p.reg_time IS NULL OR LTRIM(RTRIM(p.reg_time)) = '')
ORDER BY p.id;


/* ---------------------------------------------------------------------------
   2) Same shape, regNo never stamped (blank rather than '0/0/0'), and every
   date/time column empty. Dev run 2026-09-02: 1,003 rows.

   These are the same kind of permit — nothing was ever registered — recorded
   before 0/0/0 became the sentinel. Review before treating them as LGA permits:
   a blank regNo can also just mean the particulars were never keyed in.
   --------------------------------------------------------------------------- */
SELECT
    p.id                                        AS pra_id,
    COALESCE(NULLIF(LTRIM(RTRIM(p.mlsFNo)), ''), p.fileno) AS file_number,
    p.prop_id,
    p.op_type,
    p.op_serial_number,
    p.Grantor                                   AS party_1_current,
    p.Grantee                                   AS party_2_allottee,
    p.lgsaOrCity                                AS lga_hint,
    p.location,
    p.system_source,
    p.created_at
FROM pra AS p
WHERE (p.instrument_type LIKE '%Occupancy Permit%' OR p.transaction_type LIKE '%Occupancy Permit%')
  AND (p.is_deleted IS NULL OR p.is_deleted = 0)
  AND (p.regNo IS NULL OR LTRIM(RTRIM(p.regNo)) = '')
  AND (p.reg_date   IS NULL OR LTRIM(RTRIM(p.reg_date))   = '')
  AND (p.reg_time   IS NULL OR LTRIM(RTRIM(p.reg_time))   = '')
  AND (p.deeds_date IS NULL OR LTRIM(RTRIM(p.deeds_date)) = '')
  AND (p.deeds_time IS NULL OR LTRIM(RTRIM(p.deeds_time)) = '')
ORDER BY p.id;


/* ---------------------------------------------------------------------------
   3) Mapping readiness for query 1's rows: how many already carry an LGA in
   lgsaOrCity (mappable straight away) versus how many need the file pulled.
   --------------------------------------------------------------------------- */
SELECT
    CASE WHEN NULLIF(LTRIM(RTRIM(ISNULL(p.lgsaOrCity, ''))), '') IS NULL
         THEN '(no LGA on the row - needs review)'
         ELSE p.lgsaOrCity END                  AS lga_hint,
    COUNT(*)                                    AS records
FROM pra AS p
WHERE (p.instrument_type LIKE '%Occupancy Permit%' OR p.transaction_type LIKE '%Occupancy Permit%')
  AND (p.is_deleted IS NULL OR p.is_deleted = 0)
  AND LTRIM(RTRIM(ISNULL(p.regNo, ''))) = '0/0/0'
  AND (p.reg_date IS NULL OR LTRIM(RTRIM(p.reg_date)) = '')
  AND (p.reg_time IS NULL OR LTRIM(RTRIM(p.reg_time)) = '')
GROUP BY p.lgsaOrCity
ORDER BY COUNT(*) DESC;
