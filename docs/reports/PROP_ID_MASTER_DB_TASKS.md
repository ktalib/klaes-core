# PropID Master Database Tasks

**Related files**
- Plan: `docs/PROP_ID_MASTER_IMPLEMENTATION_PLAN.md`
- SQL script to run: `database_scripts/prop_id_master_table.sql`
- Prior reference: `docs/doc_1_2/PROP_ID_ALLOCATION_AUDIT_AND_SOLUTION.md`

## Execution order
1. **Create master schema**: run `database_scripts/prop_id_master_table.sql` on `sqlsrv`. This creates `PropID_Master`, normalized columns, uniqueness indexes, and the conflict view `vw_prop_id_conflicts`.
2. **Seed master rows** (safe mode):
   - Review conflicts: `SELECT * FROM vw_prop_id_conflicts;` — resolve any file numbers that carry multiple prop_ids before enforcing FKs.
   - Seed from authoritative tables in this order: `file_history_staging` → `pra` → `pic` → `CofO_staging`. For each distinct file number, keep the most recent non-null `prop_id`; insert into `PropID_Master` only when it is absent. (Sample upsert block is included in the SQL script.)
3. **Wire allocation service**: update `PropertyIdAllocationService` to read/write `PropID_Master` first, then cascade prop_id to the caller table + `file_history_staging` inside one transaction. Reject allocations when only `temp_fileno` is provided.
4. **Backfill dependents**: after seeding, update `file_history_staging`, `pra`, `pic`, and `CofO_staging` rows whose `prop_id` is NULL to use the value from `PropID_Master` for their file number. Stop short of adding FKs until conflicts are cleared.
5. **(Optional) Enforce FKs**: once conflicts are zero, add foreign keys from `file_history_staging`, `pra`, `pic`, and `CofO_staging` to `PropID_Master.prop_id` (ON UPDATE CASCADE, ON DELETE NO ACTION) to prevent future drift.
6. **Validate**: rerun the validation block from `PROP_ID_ALLOCATION_AUDIT_AND_SOLUTION.md` plus the new master-specific checks below. No NULL `prop_id` should remain in the scoped tables and no file number should map to more than one prop_id.

## Master-specific verification queries
- Duplicate mapping check (should return zero rows after cleanup):
```sql
SELECT * FROM vw_prop_id_conflicts;
```
- Confirm master coverage for target tables:
```sql 
SELECT COUNT(*) AS total_master_rows FROM PropID_Master;
SELECT COUNT(DISTINCT COALESCE(mlsFNo, kangisFileNo, NewKANGISFileno, fileno)) AS file_history_keys
FROM file_history_staging;
SELECT COUNT(*) AS missing_in_master
FROM file_history_staging fh
WHERE NOT EXISTS (
    SELECT 1 FROM PropID_Master pm
    WHERE pm.primary_file_number_norm = UPPER(LTRIM(RTRIM(COALESCE(fh.mlsFNo, fh.fileno))))
);
```
- Temp file numbers stay prop_id-less by policy: verify none were seeded:
```sql
SELECT * FROM PropID_Master WHERE temp_fileno IS NOT NULL AND prop_id IS NULL;
```

## Notes
- Do not assign prop_id when only `temp_fileno` is known; the master row should be created only when an official MLS/KANGIS/ST file number exists.
- Keep workflow helpers (`file_indexings`, `fileNumber`) prop_id-free; they should join to `PropID_Master` by file number when they need the identifier.
