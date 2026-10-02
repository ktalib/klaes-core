# Large Subdivisions: Chunked Commissioning + Legal Search

Working log for the 2026-08-20 session. Covers the 500-plot subdivision workflow,
why a subdivided mother file returned nothing in Legal Search, and what is still
open.

Reference case: **CON-IND-2024-9** (DANTATA INDUSTRIAL LAYOUT), 530 plots, all
commissioned, mother prop_id **159842**, children `CON-IND-2026-257…` etc.
Dev repro: **RES-2026-3026**, 220 plots approved, 7 commissioned
(`IND-2026-257…263`), mother prop_id 147220.

---

## 1. Commissioning more plots than one batch allows

The MLS file-number generator mints at most **200** files per batch run
(`MlsFileNoController::generateBatch`, `batch_quantity` max:200). A 500-plot
subdivision therefore has to be commissioned across several runs: 200 + 200 + 100.

### What was wrong

The first chunk set the application's `status` to `commissioned`, which locked it
out of the generator's find-by-file lookup (that lookup only returns `approved`
applications). Runs 2 and 3 were impossible. `remarks` were also overwritten by
each chunk instead of accumulating.

### What was done

New columns on `plot_subdivision_applications`
(migration `2026_08_20_140000_add_commission_progress_to_plot_subdivision_applications`):

| Column | Purpose |
| --- | --- |
| `commissioned_count` | fragments minted so far |
| `commissioned_batches` | JSON log, one entry per chunk (`batch`, `quantity`, `first_file`, `last_file`, `files`, `at`, `by`) |
| `commissioning_completed_at` | stamped when the last plot is minted |

**Key rule: `status` stays `approved` while partially commissioned.** It only
flips to `commissioned` when `commissioned_count` reaches `num_plots`. Every
existing gate that keys off `approved` keeps working, and the next chunk can
still find the application.

`PlotSubdivisionApplication::recordCommissionedBatch()` books a run;
`remainingPlots()` / `commissionedCount()` / `isCommissioningComplete()` report
progress. Both the batch and single-file paths in `MlsFileNoController` book
through it, and both refuse a run that would overshoot `num_plots` (422).

Verified 200 + 200 + 100 against a throwaway row:

```
chunk 1: count=200 remaining=300 status=approved      batches=1
chunk 2: count=400 remaining=100 status=approved      batches=2
chunk 3: count=500 remaining=0   status=commissioned  batches=3
```

Separation (cap 50) and merger (N→1) are unchanged — neither can exceed 200.

### Decommissioning across chunks

Decommissioning is **flag-only; nothing is deleted**. `PlotWorkflowService` has
no delete statements: it archives to `decommissioned_files` + `deprecated_records`,
then stamps `is_decommissioned = 1` on `fileNumber`, `file_indexings`,
`entities_staging`, `customers_staging`, `kangis_grouping`, and deliberately
leaves the Legal Search staging rows alone.

The chunking bug on top of that: the batch path called `decommissionFiles`
unconditionally, so chunks 2 and 3 would each write another archive row for the
same event, listing only their own fragments. Now:

- chunk 1 archives the mother
- later chunks call `appendSuccessors()`, which only widens the successor CSV on
  rows that already exist

`isDecommissioned()` decides which path applies.

> **Historical note:** mothers decommissioned before ~2026-07-02 were *hard
> deleted* from `fileNumber` / `file_indexings` by the old version of the
> service. 5 of 6 subdivision mothers in dev are missing their live rows. Their
> data survives in `deprecated_records`, so a restore-and-flag repair is
> possible. Not done.

---

## 2. Why a subdivided mother returned nothing in Legal Search

Two separate causes were found. Both are fixed; the second is the one that
mattered on production.

### 2a. Missing TEMP → MLS link (dev only, repaired)

`RES-2026-3026` owned no `pra` row under its own number: its Occupancy Permit
row sat under `TEMP-124360` with `mlsFNo` empty, so nothing tied the file number
to prop_id 147220. Repaired with three guarded writes (`pra.mlsFNo`,
`fileNumber.temp_fileno`, `file_indexings.temp_file_no`) — search went 0 → 8.

Two things learned while doing it:

- `PropID_Master`'s column is **`mlsFNo`**, not `mls_file_no`.
- `file_indexings.prop_id` is **normally NULL** on correctly-linked files. Legal
  Search finds them through `pra.mlsFNo`. Do not "fix" it by stamping prop_id there.

**Possibly systemic:** 8 of 18 files commissioned in August (dev) have no row
under their own number — same shape. Not audited.

### 2b. Legal Search had no downward expansion (code fix)

Expansion runs sideways (same prop_id) and upward (child → ancestor), and every
expansion collects prop_ids from rows *already found*. A mother owning no row has
nothing to seed from, so its fragments — which point straight at it via
`parent_prop_id` — were unreachable.

Added `resolveSplitMotherPropId()` + a downward pull in `LegalSearchService`:

- fires **only** when all four tables returned nothing for the searched file
  **and** the file was decommissioned by a split (subdivision/merger/separation/
  fragment). A search that already returns rows cannot change shape.
- rename-type decommissions (recert, change of purpose/name) are excluded on
  purpose — there the successor inherits the same prop_id.
- queries `pra WHERE parent_prop_id = <mother prop>`; `pra` is the only one of
  the four tables carrying `parent_prop_id`.

Verified by reproducing the production shape in dev (mother's own row stripped
inside a rolled-back transaction): **0 → 7 children**.

### 2c. The 2100-parameter ceiling (the production 500)

The console showed `POST /onpremise/search 500`, and the production log gave it:

```
SQLSTATE[IMSSP]: Tried to bind parameter number 2101.
SQL Server supports a maximum of 2100 parameters.
```

The file-number filter built one `UPPER(LTRIM(RTRIM(col))) = ?` per column per
value. Mother + 530 successors, each also expanded to its `(T)` variant = 1061
values × 4 file columns = **4244 bind parameters**. The driver rejects the
statement, the search 500s, and the UI's error handler prints the misleading
"0 results found" — which is why deploying the 2b fix appeared to change nothing.

This predates the 2b work: the large list comes from the existing related-file /
SME expansion.

Fixed with `applyFileNumberMatch()`:

| Set size | Predicate | Parameters |
| --- | --- | --- |
| ≤ 1200 params | original per-value OR form, unchanged | values × columns |
| large, SQL 2016+ | `EXISTS (SELECT 1 FROM STRING_SPLIT(?, ?) …)` per column | **one per column** |
| large, older server | `EXISTS (SELECT 1 FROM (VALUES …) v(fn) …)` | values only |

Support probed once per request and cached; any failure takes the fallback.
Measured in dev at production scale: 4244 naive → **12** (STRING_SPLIT) or
**1065** (fallback), same row found. Regression over 7 real searches: counts
identical (3, 4, 8, 8, 16, 2, 2).

---

## 3. OPEN — Legal Search is slow on the 530-child file

**Status: not fixed. This is the thing to pick up next.**

After the parameter fix, production no longer errors — it *runs long*. Two probes
of `CON-IND-2024-9` via the diagnostic endpoint:

| Probe | Result |
| --- | --- |
| 90s timeout | no response |
| 420s timeout | no response (`http_code=000`, empty body) |

The host is healthy — the site root answers 200 in normal time — so the work
itself does not complete in seven minutes.

**Caveat before attributing all of that to the search:** the diagnostic ran four
`UPPER(LTRIM(RTRIM(col))) = ?` counts over `pra`, `file_history_staging`,
`CofO_staging` and `deed_registrations` *before* calling the search, and each is
a full scan. The 420s covers the whole sequence. Time the search on its own
(tinker on the server, or a probe with the counts skipped) before concluding
which part is slow.

Where to start:

1. **Index the downward pull.** `pra.parent_prop_id` is almost certainly
   unindexed, and the fix queries it directly:

   ```sql
   CREATE INDEX IX_pra_parent_prop_id ON dbo.pra (parent_prop_id);
   ```

2. **Check the file-number predicate's plan.** `UPPER(LTRIM(RTRIM(col))) = …`
   is non-sargable — it cannot seek, whichever form is used. The columns are very
   likely already case-insensitive (CI collation), in which case the wrapping is
   unnecessary and a plain comparison would allow an index seek. Same class of
   problem as the file-log table fix.
3. **Consider whether a 530-row timeline should paginate.** Even with fast SQL,
   the downstream row processing and rendering carry 530 fragments through
   arrangement, weighting and party normalization.
4. **Give the search a ceiling.** Whatever the cause, a request that can run for
   7+ minutes will tie up a PHP worker and time out at the web server. A cap on
   the downward pull (with an explicit "showing first N of 530 fragments" note)
   would bound the worst case while the real cause is dealt with.

Do not judge the timing from dev: dev has none of this subdivision's data.

---

## 4. Cleanup owed on production

A temporary diagnostic endpoint was uploaded during this session and is **still
on the production server**:

- `app/Http/Controllers/Diagnostics/LegalSearchDiagnosticsController.php` —
  **delete by hand**; an upload cannot remove it. It is unauthenticated (guarded
  only by a key in the query string).
- The route was removed from `routes/web.php` locally, so uploading that file
  removes the endpoint and leaves the controller inert.

---

## 5. Files touched

**Code**

- `app/Services/LegalSearchService.php` — downward expansion + parameter fix
- `app/Services/PlotWorkflowService.php` — `isDecommissioned()`, `appendSuccessors()`
- `app/Http/Controllers/MlsFileNoController.php` — chunked commissioning, overshoot guards
- `app/Http/Controllers/Deeds/ParcelUpdate/PlotSubdivisionController.php` — `BATCH_CAP`, progress in lookups, approve guard
- `app/Models/PlotSubdivisionApplication.php` — progress accounting
- `resources/views/deeds/parcel_update/subdivision.blade.php` — Approve button restored, progress badge
- `resources/views/generate_fileno/mlsfno.blade.php`, `mls_js.blade.php` — chunk sizing + progress banner

**Database**

- `database/migrations/2026_08_20_140000_add_commission_progress_to_plot_subdivision_applications.php`
- `database/sql/2026_08_20_subdivision_commission_progress.sql` (sqlsrv DDL + verify)
- `database/sql/2026_08_20_subdivision_commission_progress_ledger.mysql.sql` (MySQL ledger row)
- `database/sql/diagnose_subdivision_mother_search.sql` (read-only diagnostic)

Remember the split: artisan's migration ledger lives in **MySQL**, the tables in
**SQL Server**. Run the sqlsrv file first, then the ledger file. Never verify a
schema change by asking the ledger — query `INFORMATION_SCHEMA` / `sys.columns`.

The Legal Search fix needs **no** database change.
