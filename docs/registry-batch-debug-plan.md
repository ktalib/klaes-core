# Registry Batch Debug & Helper Plan

## Immediate Diagnostics
- Add a temporary forced-preview endpoint parameter (`cache_bust` already wired) plus a one-time `debug=1` flag to log the exact SQL bindings: registry, registry_batch_no list, year, start, per_batch_limit, total_requested, table hint used, and row count returned.
- Run the exact SQL locally via the controller (not SSMS) by capturing the generated SQL and bindings from the log; verify the results match SSMS.
- Check whether `registry` normalization is altering the value (`isLandsRegistry` treats numeric as LANDS); confirm that passing registry `1` stays numeric and is sent to the DB.
- Confirm the year filter uses `[year] = ?` and that the column is not NULL/whitespace padded; test without year filter to see if rows return.
- Verify `exclude_assigned` is false so the shelf filter is not excluding rows with `shelf_rack` populated.

## SQL-Level Checks
- Verify `grouping` has `registry` stored as `1` (string vs int); ensure the query binds the same type.
- Inspect for trailing spaces in `registry_batch_no` or `year`; consider using `RTRIM(LTRIM())` in the query if needed.
- Confirm `registry_batch_no` indexes and index hint `idx_grouping_registry_batch_no_id` are valid; remove hint if causing plan issues.

## Code Adjustments (proposed)
- Add `debug=1` support in `previewGroupingBatch` to:
  - Log: cache key, registry list, year, start/range, per_batch_limit, table, whereClause, bindings, and returned row count.
  - Optionally return the first 5 rows’ key fields for verification when empty.
- Add a helper method `buildGroupingWhereClause($registryBatchValues, $registry, $yearFilter, $isLands, $excludeAssigned)` to centralize filters and make them auditable.
- Add a small service/utility (e.g., `GroupingLookupService`) with methods:
  - `fetchBatchWindow($batches, $registry, $year, $start, $limit, $excludeAssigned)`
  - `yearHints($batches, $registry)`
  - `progress($batch, $registry)` (wraps current progress calc)
  This keeps controller slim and makes per-registry behavior overridable.

## Registry-Specific Helper Stubs
- For Lands (registry 1/2/3): implement a helper that normalizes registry codes, trims batch/year, and applies `[year]` filter with RTRIM/LTRIM for safety.
- For Non-Lands: ensure general_registry mapping (`resolveGeneralRegistryValue`) is reused; allow ALL-UNASSIGNED behavior.

## Verification Steps
- Test with (registry=1, batch=1, year=1981) with `debug=1&cache_bust=ts` and capture logs for returned row count.
- Test without year filter to confirm rows return; then add year filter to isolate the issue.
- Test `exclude_assigned=true/false` to ensure shelf_rack filtering is not stripping rows.

## Rollback/cleanup
- Remove `debug` logging or guard it behind an env flag once resolved.
