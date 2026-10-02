# MLS File Commission — Edit & Master Delete Audit

**Screen:** `resources/views/generate_fileno/mlsfno.blade.php` (+ `mls_js.blade.php`)
**Backend:** `app/Http/Controllers/FileNumberController.php`
**Date:** 2026-09-02
**Status:** IMPLEMENTED — see §7 for what shipped, what was skipped, and how it was verified.
The findings below are kept as written so the reasoning behind each change stays on record.

---

## 1. What was checked

Every field on the Edit modal was traced from the input, through `submitEditForm()`, into
`FileNumberController::update()`, and out to each of the five tables the screen claims to
maintain. The same was done for the row-level **Delete Record** and the toolbar
**Global Delete**. Column lists were read from the live `sqlsrv` schema, not assumed.

Three write paths reach the same endpoint and all three were checked:

| Path | UI | Function | Endpoint |
|---|---|---|---|
| Edit modal | row menu → Edit | `submitEditForm()` [mls_js:4239] | `file-numbers.update` |
| Update Allocation data | row menu → Update Allocation data | `submitDirectAllocationForm()` [mls_js:4423] | `file-numbers.update` |
| Master Delete | row menu → Delete / toolbar Global Delete | `deleteRecord()` [mls_js:4499] / `bulkDeleteSelectedRecords()` [mls_js:4638] | `file-numbers.destroy` / `file-numbers.bulk-destroy` |

**Batch rows were checked in a second pass** (findings F-12 to F-15). They are the most
consequential part of this audit and are covered in section 3.5.

---

## 2. Edit — field-by-field propagation matrix

Form fields actually submitted by the Edit modal (`name=` attributes):
`id, entity, passport, related_fileno, is_old_fileno, file_name, customer_type,
purpose_id, phone_no, address, tp_no, plot_no, district, lga, location,
rep_phone_no, rep_address`.

`editMlsfNo` has **no** `name` attribute — the file number itself is display-only and is
never submitted. That is correct and intentional.

Legend: ✅ written · ❌ column exists but is not written · — column does not exist on that table

| Edit field | `fileNumber` | `mls_file_no` | `file_indexings` | `customers_staging` | `entities_staging` |
|---|---|---|---|---|---|
| **file_name** (applicant/title) | ✅ `FileName` | ✅ `file_name` | ✅ `file_title`, `current_holder` | ❌ `customer_name` | ❌ `entity_name` |
| **plot_no** | ✅ `plot_no` | ✅ `plot_no` | ✅ `plot_number` | — | — |
| **tp_no** | ✅ `tp_no` | ✅ `tp_no` | ✅ `tp_no` | — | — |
| **district** | ✅ `district` | ✅ `district` | ✅ `district` | — | — |
| **lga** | ✅ `lga` | ✅ `lga` | ✅ `lga` | — | — |
| **location** | ✅ `location` | ✅ `location` | ✅ `location` | — | — |
| **address** | ✅ `address` | ✅ `address` | ❌ `residence_address` | ❌ `property_address` / `residential_address` | — |
| **phone_no** | ✅ `phone_no` | ✅ `phone_no` | ❌ `phone` | ❌ `phone` | — |
| **customer_type** | — (no column; `Schema::hasColumn` guard correctly skips) | ✅ `customer_type` | — | ❌ `customer_type` | ❌ `entity_type` |
| **purpose_id** | — (no column; guard skips) | ✅ `purpose_id` | — | — | — |
| **rep_phone_no** | ✅ | ✅ | — | — | — |
| **rep_address** | ✅ | ✅ | — | — | — |
| **related_fileno** (box unticked) | ✅ `related_fileno` (JSON array) | — | ❌ `related_fileno` | — | — |
| **related_fileno** (box ticked = Old File No) | ✅ cleared to `NULL` | ✅ `old_fileno` | ✅ `old_fileno` (via `OldFileNumberService`) | — | — |
| **passport** | — | — | ✅ `scannings` row + `oss_applications.passport_photo` | — | ❌ `passport_photo` |

### Summary of the matrix

* **Property fields (plot, TP, district, LGA, location) are fine.** They reach
  `fileNumber`, `mls_file_no` and `file_indexings` correctly. Your main worry here is
  unfounded — with one exception, finding **F-6** below.
* **The applicant / file name never reaches Customers or Entity.** This is the headline gap.
* Phone and address stop at `fileNumber` / `mls_file_no`; the equivalent columns on
  `file_indexings` and `customers_staging` are left stale.
* The Old-File-Number path is the *best*-covered field on the form (ledger +
  `mls_file_no` + `file_indexings`, via `OldFileNumberService`). The *Related*-file-number
  path is the weakest — it lands only on `fileNumber.related_fileno`.

---

## 3. Findings

### F-1 — Name edits never reach `customers_staging` / `entities_staging` (HIGH)

`FileNumberController::update()` writes `fileNumber` [:1817], `mls_file_no` [:1823] and
`file_indexings` [:1898]. There is **no** write to `customers_staging` or
`entities_staging` anywhere in the method. Change the applicant name here and the
Customer and Entity records keep the old name permanently.

This was clearly intended to work. `submitEditForm()` already handles a
`409 requires_confirmation` reply [mls_js:4286] whose message reads *"…will also update
the name on the linked customer, entity and file indexing records"* — but
`FileNumberController::update()` never returns that status, so **that dialog is dead code
on this screen**. The working implementation lives on the *other* page:
`MlsFileNoController::update()` [:983] → `propagateFileNameChange()` [:1057], which writes
`customers_staging.customer_name` and `entities_staging.entity_name`. The front-end half
was copied to this screen; the back-end half was not.

### F-2 — Delete and Update-Allocation act on the wrong record for non-`fileNumber` rows (CRITICAL)

The list is a UNION of three row sources [FileNumberController:337, :379, :398], all
present in the default "New" view [:334, and `d.source = 'New'` at mls_js:1041]:

| Branch | `row.type` | `row.id` comes from |
|---|---|---|
| `F` | (normal) | `fileNumber.id` |
| `T` | `Temporary` | **`mls_file_no.id`** |
| `P` | `Plot Extension` | **`plot_extensions.id`** |

Every row gets Edit [mls_js:1429], Update Allocation data [:1436] and Delete [:1446].
Only **Edit** passes `row.type` and resolves the right table (`entity=plot_extension`,
handled in `show()` [:1502] and `update()` [:1658]). **Delete and Update Allocation send
the bare `id` with no type**, and `destroy()` / `bulkDestroy()` / the non-plot-extension
branch of `update()` look that id up in `fileNumber` unconditionally.

> Note: `show()` has no Temporary branch either, so **Edit is also wrong for `T` rows** —
> it loads a different file into the modal and saves over it. Only Plot Extension was ever
> fixed.

This is not theoretical. Live data:

```
fileNumber.id       range 8 … 144790
mls_file_no.id      max 67653   → every temporary row's id also exists in fileNumber
plot_extensions.id  max 6       → next rows will be ids 7, 8, … and fileNumber id 8 is live
```

The three live Temporary rows **all** collide with real, unrelated commissioned files:

| Temporary row (`mls_file_no.id`) | Deleting/editing it actually hits `fileNumber.id` = |
|---|---|
| `RES-1993-2644(T)` — id 1166 | **`CON-AG-1987-57`** |
| `RES-2006-1448(T)` — id 12337 | **`CON-RES-1995-140`** |
| `RES-1986-2377(T)` — id 35663 | **`RES-1986-2416`** |

So: **Delete on a Temporary row hard-purges a completely different file from all five
tables**, and **Update Allocation data on a Temporary row writes the plot/LGA/district of
one file onto another.** Plot Extensions are safe *today* only because `fileNumber` ids
1–6 no longer exist (min id is 8) — the 8th plot extension created will land on
`fileNumber` id 8 = `AG-RC-1981-54` / *ALH. BALA AHMADU BABA*.

Global Delete makes it worse: the checkbox value is the bare `id` [mls_js:945], up to 200
per batch, all in one transaction.

### F-3 — `bulkDestroy` silently skips unresolvable ids (MEDIUM)

`bulkDestroy()` [:2031] collects ids that matched no `fileNumber` row into `missing_ids`
and returns them, but the success handler in `bulkDeleteSelectedRecords()` never displays
them. A Temporary row selected in a batch is skipped with a green "Deleted!" toast and no
warning.

### F-4 — Delete leaves `old_file_numbers` behind (MEDIUM)

The Edit modal *writes* the `old_file_numbers` ledger through `OldFileNumberService`
[FileNumberController:1878], but `cascadeDeleteFileRecord()` [:2113] never purges it.
Delete a file that had an old number recorded and the ledger row survives, still keyed on
the now-deleted file number. This is an internal inconsistency in this one screen, not a
scope question.

### F-5 — Blanket `fileNumber` delete by `mlsfNo` (MEDIUM)

`cascadeDeleteFileRecord()` step 5 deletes by `id`, then **also** deletes *every*
`fileNumber` row sharing the same `mlsfNo`, with no `is_deleted` guard [:2170]. Where the
same MLS number legitimately has more than one physical-file row, this removes all of
them from a single-row delete. Needs a decision, not necessarily a code change.

### F-6 — Typing in **Plot Number** wipes the **Location** field (MEDIUM)

`updateEditLocation()` [mls_js:4210] rebuilds Location as `DISTRICT, LGA, KANO` and is
bound to `input change` on `#editPlotNo`, `#editLga`, `#editDistrict` [mls_js:4231]. The
function's own comment says the plot number must not affect the location — but the plot
number still *triggers* the rebuild. So correcting a plot number silently overwrites a
hand-typed location such as `NO 5 AHMADU BELLO WAY, NASSARAWA`, and the wipe is then
saved to all three tables. This is the one real defect in the property-field path.

### F-7 — `file_indexings.current_holder` is overwritten on *every* save (MEDIUM)

The `file_indexings` sync [:1898] is gated on `isset($updateData['FileName'])`, which is
always true because `file_name` is a required field. It then sets
`current_holder = FileName` unconditionally. So an edit that only changes the plot number
resets `current_holder` back to the original file name — discarding a holder that a later
Deed of Assignment or Transfer of Title had legitimately changed. `MlsFileNoController`
gets this right: it propagates only when `$nameChanged`.

### F-8 — `mls_file_no` matched only by `full_file_number` (LOW)

The edit syncs `mls_file_no` on `full_file_number = $record->mlsfNo` [:1868]. Delete
matches on `full_file_number` **and** `tracking_id` [:2124]. Rows reachable only by
`tracking_id` are updated by neither. Asymmetric.

### F-9 — `file_indexings` lookup misses number variants (LOW)

The propagation keys on the literal `mlsfNo` / `kangisFileNo` / `NewKANGISFileNo`
[:1900]. It will not match a `(T)` temporary variant or a KANGIS `_N` suffix row, both of
which are known to exist as separate indexing rows. Same limitation on the delete side.

### F-10 — Duplicate route group (LOW / hygiene)

`routes/web.php:1087-1133` re-declares the entire `file-numbers` group that
`routes/file_numbers.php` already defines (required at `routes/web.php:198`). Harmless
today — the earlier registration wins URI matching and both point at the same controller
— but the `web.php` copy is missing `/stats`, `/bulk-destroy` and `/clear-cache`, so
editing the wrong file will look like it works and change nothing.

### F-11 — `update()` has no permission check (INFO — confirm intent)

Delete is `Supper Admin` only [:1979, :2035]. Edit has no role check at all: any
authenticated user can rename a file and rewrite its plot/LGA/district. Flagging in case
that is not deliberate.

---

## 3.5 Batch rows — the biggest gap on this screen

### How a batch is displayed

Files sharing a `mls_file_no.batch_no` are collapsed into **one** DataTable row
[FileNumberController:337-367]:

```sql
ROW_NUMBER() OVER (PARTITION BY COALESCE(NULLIF(mls.batch_no,''), fn.id)
                   ORDER BY fn.id DESC) AS group_rn
...
WHERE w.group_rn = 1
```

So the surviving row is the **newest member** (highest `fileNumber.id`), while
`batch_first_file` is the **oldest**. The MLS File No cell then renders a *range*
`PREFIX-firstSerial-lastSerial` plus a `Group (N)` button [mls_js:1142-1183].

`row.id` — the value Edit, Update Allocation, Delete and the Global Delete checkbox all
use — is the id of that **one newest member**, not the batch.

### Verified against live data

`BATCH-20260209-1770642630` — 7 files, each with its own `tracking_id`:

```
fn.id 60380  COM-2026-78   trk TRK-311968E8-8116A   plot 'PIECE OF LAND'
fn.id 60381  COM-2026-79   trk TRK-5D9083AD-6C0AD   plot 'PIECE OF LAND'
fn.id 60382  COM-2026-80   trk TRK-5FCF5DBF-B6A4A   plot 'PIECE OF LAND'
fn.id 60383  COM-2026-81   trk TRK-0C83FCC2-48B2C   plot 'PIECE OF LAND'
fn.id 60384  COM-2026-82   trk TRK-F3EB3087-14932   plot 'PIECE OF LAND'
fn.id 60385  COM-2026-83   trk TRK-AD0DA775-CA12E   plot 'PIECE OF LAND'
fn.id 60386  COM-2026-84   trk TRK-EFDFBA1E-380BE   plot 'PIECE OF LAND'
```

The list shows **one** row reading `COM-2026-78-84` / `Group (7)`. Edit and Delete both
resolve to `fn.id 60386` = `COM-2026-84` — the last file — and nothing on screen says so.

Scale across the database:

```
multi-file batches                 141
files hidden inside them         3,533
batch sizes                    2 … 100  (40 batches of 2, 11 of 50, one of 99, 8 of 100)
```

**Good news:** each batch member has its own `tracking_id` and its own
`full_file_number`, so `cascadeDeleteFileRecord()` does **not** spill across the batch via
its `tracking_id` clause. The per-file cascade is sound; only the *selection* is wrong.

### F-12 — Editing a batch row updates 1 file out of N (HIGH)

Change the plot number on the `COM-2026-78-84` row and only `COM-2026-84` is updated
across `fileNumber` / `mls_file_no` / `file_indexings`. The other six keep the old plot
number, with no warning and no indication in the modal.

This matters because **most batches are genuinely one property**:

| All members share… | Batches (of 141) |
|---|---|
| the same `plot_no` | 111 (79%) |
| the same `file_name` (applicant) | 125 (89%) |
| the same `lga` | 112 (79%) |
| the same `location` | 110 (78%) |

So the expectation that editing a batch edits the whole batch is right about 8 times out
of 10 — but **30 batches do have differing plot numbers and 16 differing names**, so a
blanket "always apply to all" would corrupt those. The fix has to be an explicit choice,
not a silent behaviour change.

### F-13 — Deleting a batch row deletes 1 file, and the row comes back (HIGH)

`deleteRecord(row.id)` master-deletes only `COM-2026-84`. The grouping is then recomputed,
`group_rn = 1` lands on `fn.id 60385`, and the row **reappears** as `COM-2026-78-83` /
`Group (6)`. To the user the delete looks like it silently failed. Clearing a 50-file batch
means clicking Delete fifty times.

**Global Delete is worse:** the checkbox value is the bare `row.id` [mls_js:945]. Tick five
batch rows and the dialog says *"Delete 5 record(s)?"* and *"purging them from 5 tables"* —
then deletes **5 files out of a possible 250**, reports success, and leaves five batch rows
still on screen. The 200-record cap also counts rows, not files, so it does not bound the
real blast radius.

There is **no batch delete anywhere in the codebase** — no route, no controller method.

### F-14 — The `Group (N)` modal has no Delete, and its Edit can fire with `null` (MEDIUM)

`renderBatchTable()` [mls_js:880-912] lists every member with an **Edit** and a **Print**
button. There is **no Delete button**, so non-head members cannot be deleted at all except
by repeatedly deleting the head.

Its Edit button calls `editRecord(record.filenumber_id)`. `filenumber_id` comes from a join
on `tracking_id` restricted to `type = 'MlsFileNO'` [MlsFileNoController:5755], and **3 of
the 3,533 batch members resolve to NULL**. Those rows fire `GET /file-numbers/null`, which
fails an int conversion in SQL Server, returns a 500, and leaves the modal open with
`editId = "undefined"` — saving then 500s as well. Not destructive, but broken.

### F-15 — The Edit card gives no hint that it is part of a batch (MEDIUM)

`editMlsfNo` is populated with the single member's number [mls_js:3830]. The user clicks a
row labelled `COM-2026-78-84` and gets a form headed `COM-2026-84`, with nothing saying the
other six files exist or will be left behind.

### The screen already knows how to do this

**Print is batch-aware.** `openFilePrinterManager(row.id, row.mlsfNo, batchNo)` [mls_js:1416]
receives `batch_no`, and `getBatchRecords()` [MlsFileNoController:5732] already expands a
`batch_no` into its member list with `filenumber_id` for each. Edit and Delete were simply
never taught the same trick — the expansion query needed for the fix already exists.

---

## 4. Proposed fix

Ordered by risk. **F-2 should ship on its own, first** — it is the only finding that
destroys unrelated production data.

### Phase 1 — Stop the wrong-record writes (F-2, F-3)

**1.1 Carry the row type on every action.** Pass `row.type` to the two callers that drop
it today, exactly as Edit already does:

```js
// mls_js.blade.php:1436, :1446
<button onclick="directAllocation(${row.id}, '${row.type || ''}')" …>
<button onclick="deleteRecord(${row.id}, '${row.type || ''}')" …>
```

and carry it on the checkbox so bulk delete keeps it:

```js
// mls_js.blade.php:945 — encode both, not just the id
const key = (row.type === 'Plot Extension' ? 'P:' : row.type === 'Temporary' ? 'T:' : 'F:') + data;
```

`bulkDeleteSelectedRecords()` then posts `[{entity, id}, …]` instead of `[id, …]`.

**1.2 Refuse rather than guess, server-side.** This is the part that actually protects the
data — the UI change alone is not enough, because an old cached page would still post bare
ids. Add an `entity` parameter to `destroy()`, `bulkDestroy()` and the `update()`
fallthrough:

* `entity` absent or `file_number` → current behaviour (look up `fileNumber.id`).
* `entity = plot_extension` → resolve `plot_extensions`.
* `entity = temporary` → resolve `mls_file_no`.

Add the missing Temporary branch to `show()` at the same time, so Edit stops loading the
wrong record for `T` rows.

**1.3 Block the operations that have no meaning for T / P rows.** A Temporary row has no
`fileNumber` row to cascade from, so a 5-table Master Delete is not a coherent operation on
it. Rather than invent one, `destroy()`/`bulkDestroy()` should return a clear
`422 "Temporary files cannot be master-deleted from this screen"`, and the row menu should
hide Delete and Update-Allocation for `T` and `P` rows entirely. Same for Plot Extensions,
which have their own table and their own lifecycle.

**1.4 Surface `missing_ids`.** Show them in the Global Delete result toast instead of
reporting an unqualified success.

**Decision needed from you:** should Temporary and Plot Extension rows get a *correct*
delete of their own (purging `mls_file_no` / `plot_extensions` and their indexing rows), or
should Delete simply be hidden for them? I recommend **hiding it** for now — a correct
cascade for those two row types is separate work with its own rules.

### Phase 1.5 — Make Edit and Delete batch-aware (F-12 … F-15)

The per-file cascade and the per-file update are both already correct. What is missing is
**expansion** (one row → N member ids) and **an explicit scope choice**. Reuse the member
list `getBatchRecords()` already builds.

**1.5.1 Carry the batch on every action.** `row.batch_no` and `row.batch_count` are already
on the row object; pass them to `openEditModalFromAction`, `deleteRecord` and the checkbox
the same way `batchNo` is already passed to `openFilePrinterManager` [mls_js:1416].

**1.5.2 Show the batch on the Edit card (F-15).** When `batch_count > 1`, a banner above
the form:

> This file number belongs to batch **BATCH-20260209-1770642630** — **7 files**
> (`COM-2026-78` … `COM-2026-84`). You are editing **`COM-2026-84`**.

with a scope radio:

* ○ **This file only** (`COM-2026-84`) — default, preserves today's behaviour
* ○ **All 7 files in this batch**

**1.5.3 Server-side batch update.** `update()` accepts `batch_no` + `apply_to_batch`.
When set, expand `batch_no` → member `fileNumber` ids, then run the existing per-file update
for each **inside one transaction**. Two guards:

* **Divergence check.** If members currently hold *different* values for a field being
  changed (true for 30 of 141 batches on `plot_no`, 16 on `file_name`), return `409` listing
  the divergent values and require confirmation. Do not silently flatten them.
* **Per-member transaction check.** The `has_transaction` confirmation from Phase 2 must run
  across **all** members before a name is pushed batch-wide, not just the head.

**1.5.4 Server-side batch delete.** `destroy()` / `bulkDestroy()` accept `batch_no` +
`scope=single|batch`. For `batch`, expand to member ids and loop the **existing**
`cascadeDeleteFileRecord()` for each, inside the transaction that is already there. No new
cascade logic is needed — each member has its own `tracking_id` and `full_file_number`, so
the current per-file cascade is already correct in isolation.

**1.5.5 Say what will actually be deleted.** The confirm dialog must expand the count:

> This row represents **7 files** (`COM-2026-78` … `COM-2026-84`).
> ○ Delete this file only (`COM-2026-84`) ○ Delete all 7 files in the batch

and Global Delete must report **files**, not rows — "5 batches / 250 files" — and apply the
200 cap to the **expanded file count**, otherwise three selected 100-file batches become a
silent 300-file transaction.

**1.5.6 Fix the `Group (N)` modal (F-14).** Add a per-member Delete button (Supper Admin
only) calling `destroy()` with `scope=single`, so non-head members are reachable. And when
`filenumber_id` is null (3 rows today), disable that row's Edit button with a tooltip
instead of firing `GET /file-numbers/null`.

**Decision needed from you:** should the scope default be **This file only** (safe, matches
today) or **All N files** (matches how batches are actually used ~80% of the time)? I
recommend defaulting to *this file only* and letting the user opt into the batch — an
accidental batch-wide delete is unrecoverable, an accidental single delete is not.

### Phase 2 — Complete the edit propagation (F-1, and the address/phone gaps)

Add a single private method on `FileNumberController`, modelled on
`MlsFileNoController::propagateFileNameChange()` [:1057] so the two screens behave
identically, and call it from `update()` right after the `file_indexings` block:

```php
private function propagateToStaging(array $fileNoCandidates, ?string $newName,
                                    ?string $customerType, ?string $phone, ?string $address): void
```

writing, each in its own try/catch so a missing column never aborts the save:

| Table | Columns to write | Condition |
|---|---|---|
| `customers_staging` | `customer_name` | only when the name actually changed |
| `customers_staging` | `customer_type`, `phone`, `property_address` | when those fields were submitted |
| `entities_staging` | `entity_name` | only when the name actually changed |
| `entities_staging` | `entity_type` | when `customer_type` was submitted |
| `file_indexings` | `phone`, `residence_address` | when submitted |

Keyed on `whereIn('file_number', $fileNoCandidates)` — the same candidate list the
`file_indexings` sync already builds.

**Guard the name write behind a real change test**, as `MlsFileNoController` does:

```php
$nameChanged = trim((string) $record->FileName) !== trim($request->file_name);
```

**And wire the confirmation the UI already expects.** When `$nameChanged`, the file has
`file_indexings.has_transaction = 1`, and `confirm_transaction_change` was not sent, return
`409` with `requires_confirmation => true`. `submitEditForm()` already handles this
[mls_js:4286] and re-submits with the flag — no front-end work needed. This turns the
existing dead branch into the intended safety gate: a name is only pushed across Customer,
Entity and Indexing after the user confirms on a file that already has transactions.

### Phase 3 — The remaining correctness fixes

* **F-6:** drop `#editPlotNo` from the `updateEditLocation()` listener [mls_js:4231], and
  stop auto-rebuilding Location once the user has typed into it (track a `dirty` flag). The
  plot number was already excluded from the *content* of the string; it should not trigger
  the rebuild either.
* **F-7:** move the `current_holder` write inside an `if ($nameChanged)` block. `file_title`,
  `location`, `lga`, `district`, `plot_number`, `tp_no` keep syncing on every save — only
  the holder identity becomes conditional.
* **F-4:** add `old_file_numbers` to `cascadeDeleteFileRecord()`, keyed on `file_number` in
  the same candidate set, and update the dialog list (it then reads "6 tables").
  Alternatively call `OldFileNumberService::clear()` — cheaper, but it leaves the ledger
  history, which for a *deleted* file is probably wrong.
* **F-8:** match `mls_file_no` on `full_file_number` OR `tracking_id` in `update()`, to
  mirror the delete path.
* **F-9:** run the candidate list through the existing temp-`(T)` / KANGIS-suffix variant
  resolution before both the update and the delete key on it.
* **F-10:** delete the duplicate group from `routes/web.php:1087-1133`; keep
  `routes/file_numbers.php` as the single definition.

### Explicitly NOT proposed

* **F-5** (blanket delete by `mlsfNo`) — needs your ruling on whether one MLS number may
  legitimately have several `fileNumber` rows on this screen before I touch it.
* **F-11** (no role check on edit) — flagged only; I have not assumed it is a bug.
* **Widening the delete scope beyond 5 tables.** `pra`, `PropID_Master` and the deed /
  registration tables are *not* purged. Both dialogs say five tables, so I am treating that
  as a deliberate boundary, not an omission. Say the word if it should be wider.
* **`entities_staging.passport_photo`.** The column exists and is never written by the
  passport upload, which goes to `scannings` + `oss_applications.passport_photo`. It looks
  like a legacy column; I would leave it alone unless something reads it.

---

## 5. Verification plan

Before/after, scoped to ids the test itself creates — the dev DB is shared with live UI
testing, so no blanket predicates:

1. **F-2:** delete Temporary `RES-1993-2644(T)`; assert `CON-AG-1987-57` is still present in
   all five tables. This fails today.
2. **F-1:** rename a file that has `customers_staging` / `entities_staging` rows; assert
   `customer_name` and `entity_name` both changed, and that the 409 confirmation fired first
   on a file with `has_transaction = 1`.
3. **F-6:** open Edit on a file with a hand-typed Location, change only the plot number,
   save; assert Location is unchanged in all three tables.
4. **F-7:** on a file whose `current_holder` differs from `FileName` (post-assignment), edit
   only the TP number; assert `current_holder` is untouched.
5. **F-4:** record an old file number via Edit, then Master Delete; assert no
   `old_file_numbers` row survives.
6. **F-12:** on `BATCH-20260209-1770642630` (7 files), edit the plot number with scope
   "All 7"; assert all of `COM-2026-78` … `COM-2026-84` changed in all three tables. With
   scope "this file only", assert exactly one changed.
7. **F-13:** delete that batch row with scope "batch"; assert all 7 members are gone from
   all five tables and the row does not reappear. With scope "single", assert exactly one
   member is gone and the row correctly redisplays as `COM-2026-78-83 / Group (6)`.
8. **F-13 (bulk):** tick two multi-file batch rows; assert the dialog names the real file
   count, and that the 200 cap is measured against expanded files.

---

## 6. Answer to the original question, in one line

**Edit:** property fields (plot, TP, district, LGA, location) propagate correctly to
`fileNumber`, `mls_file_no` and `file_indexings` — but the **applicant name never reaches
Customers or Entity**, and editing the plot number silently wipes Location.
**Delete:** the 5-table cascade itself is correct and transactional, but on Temporary and
Plot Extension rows it resolves the **wrong record** and purges an unrelated file.
**Batch:** a batch of N files is one row, and Edit, Delete and Global Delete all act on
**one member only** — the newest — with nothing on screen saying so. 3,533 files sit inside
141 such rows.

---

## 7. Implementation record (2026-09-02)

### Shipped

| # | Finding | What changed |
|---|---|---|
| F-2 | Wrong-record writes | New `App\Support\MlsRowTarget` is the single place a row becomes an unambiguous `{entity, id}`. Checkbox values are now `F:123` / `T:1166` / `P:4`; Edit, Delete and Update-Allocation all carry `row.type`. `destroy()` / `bulkDestroy()` refuse any non-`fileNumber` entity, and the menu withholds those buttons. `show()` and `update()` gained the missing **Temporary** branch (`updateTemporaryFile()`), so editing a temporary row no longer loads and overwrites a different file. |
| F-3 | Silent skips | `bulkDestroy` returns `skipped[]` alongside `missing_ids[]`, and the toast now reports "Partially deleted" listing what was left behind. |
| F-12/13 | Batch Edit & Delete | New `App\Services\FileNumber\BatchExpansionService` expands one row into its member files. Edit shows a "Part of a batch of N files" panel with a **This file only / All N files** choice (defaults to the safe one, and resets on every open). Delete offers the same choice. `bulkDestroy` expands batches and applies the 200 cap to the **expanded file count**. |
| F-12 | Flattening a divergent batch | New `App\Support\BatchDivergence` compares members on the fields being written and returns `409 requires_batch_confirmation` with the competing values. Verified against `BATCH-0001-1770386822` (48 files) where `plot_no` really is split between blank and `PIECE OF LAND`. |
| F-14 | Group modal | Per-member **Delete** added (Supper Admin, `scope=single`). A member whose `filenumber_id` is null now renders a disabled control instead of firing `GET /file-numbers/null`. |
| F-1 | Customers / Entity mirrors | New `propagateToStaging()` writes `customers_staging.customer_name` / `.customer_type` / `.phone` / `.property_address` and `entities_staging.entity_name` / `.entity_type`. The **name** only moves when it actually changed, and the existing `409 requires_confirmation` dialog is now genuinely wired — it was dead code before. |
| — | phone / address gaps | Also propagated to `file_indexings.phone` and `.residence_address`. |
| F-6 | Location wipe | `#editPlotNo` removed from the `updateEditLocation()` listener, plus a `userEdited` flag so a hand-typed Location is never auto-recomposed. Reset per record on modal open. |
| F-7 | `current_holder` | Now written only when the name changed, so a plot-number correction no longer reinstates a previous owner. |
| F-4 | `old_file_numbers` | Purged by `cascadeDeleteFileRecord()`; both dialogs and the audit line now say 6 tables. |
| F-8 | `mls_file_no` match | `update()` matches `full_file_number` OR `tracking_id`, mirroring the delete cascade. |
| F-10 | Duplicate routes | The duplicated group in `routes/web.php` is gone; only the three `api/*` routes that exist nowhere else remain. `routes/file_numbers.php` is the single definition. |

Also shipped, by later request: three new columns on this list — **Passport** (thumbnail, click to enlarge; batched through `FilePassportService::prime()` so it stays one query per page), **RoT**, and **Related / Old File No** (one column badged `REL` or `OLD`, since those are the two halves of a single field on the Edit modal). Added to all three row formatters, not just the `fileNumber` one.

### Layout follow-up (2026-09-03)

RoT and Related/Old File No were folded out of their own columns: the related/old number now
renders beneath the MLS File No it belongs to (badged `REL` / `OLD`), and the Root of Title
beneath the File Title (badged `RoT`). Both are attributes of those values, not independent
columns. The sort-fallback index was corrected from 15/16 to 13/14 to match.

### Edit now reaches the OSS applications list (2026-09-03)

Commissioning publishes every MLS file into `oss_applications` via
`MlsCommissioningOssApplicationService` — **5,102** rows carry
`system_source = MLS_FILE_NUMBER_GENERATOR` — and that table is what
`/lands-one-stop-shop/applications?type=no-change-of-name` reads. The edit stopped at the
file-number screens, so that page kept showing whatever was captured at commissioning.

`propagateToOssApplications()` now mirrors the edit onto that row from both the normal and
the temporary-file save paths, using the same column mapping commissioning wrote:
`applicant_name` (name changes only), `plot_no`, `plan_no` **fed from the TP number**,
`location`, `district`, `lga`, `address`, `phone`. Wrapped so a missing column cannot fail a
save that has already succeeded on the registers.

The drift was visible in live data: `IND-2026-230` reads *MAKA ADO* on `fileNumber` and
*MAKA ADO ALI* on its OSS row.

### Cascade extended to OSS and File Tracking (2026-09-03)

Reported from live use: `IND-2026-272` was master-deleted, vanished from all six cascade
tables — and still appeared on `/lands-one-stop-shop/applications?type=no-change-of-name`
and in File Tracking.

Commissioning does two things beyond the file-number registers: it **publishes** the file
into `oss_applications`, and it opens a **`file_tracker`** request. Neither was purged.

The cascade now covers both, taking it from 6 tables to 8:

| Added | Keyed on | Notes |
|---|---|---|
| `oss_applications` | `file_no` | the applications listing |
| `file_tracker` | `file_number` | orphans survived as **ACTIVE** requests sitting in a department |
| `rds_tracking` | `file_number` | |
| `digital_file_tracking_requests` | `file_no` | |

`file_tracker`'s children are resolved first (`kangis_checkout_approvals`,
`file_tracker_department_backfill`). `indexing_duplicates` has its `file_tracker_id`
**cleared rather than the row deleted** — it documents indexing, not the tracking request.
There are no FK constraints on either table, so the ordering is ours to choose.

Both dialogs and the audit line now name all 8.

**Existing orphans** are repaired by `php artisan mls:purge-delete-orphans` — reports by
default, `--force` to apply, `--file=` to scope to one number. Candidates come from the
master-delete audit trail, never a blanket predicate, and any number that is **live again**
is skipped: re-issue is real (CON-COM-2026-333 was deleted at 12:09 and re-commissioned at
12:45 the same day), and purging then would destroy the new file's records. As at
2026-09-03 the dry run reports **18 orphaned files — 18 `oss_applications` rows and 9
`file_tracker` rows** — with 6 numbers skipped as re-issued.

### Deliberately not done

* **F-5** — the blanket `fileNumber` delete by `mlsfNo` is untouched, pending a ruling on whether one MLS number may legitimately have several rows.
* **F-9** — `(T)` and KANGIS `_N` variant resolution on the propagation key.
* **F-11** — no role check added to `update()`; flagged only.

### Verification

* `tests/Unit/Support/MlsRowTargetTest.php` — 13 tests. Covers the real collision (`T:1166` must not become fileNumber 1166), refusal of unknown prefixes, and that `12abc` is not coerced to id 12.
* `tests/Unit/Support/BatchDivergenceTest.php` — 13 tests. Covers both failure modes: missing a real divergence, and inventing one (casing/padding/null-vs-blank).
* `tests/Feature/FileNumber/MlsFcListAndDeleteGuardTest.php` — 7 tests, **read-only**, run against the real database. Asserts the three new columns are present on every row type, that a temporary/plot-extension Master Delete is refused, that the collision victim survives, and that Supper Admin is still required.
* Both blades compile; every changed JS function parses; the file-level JS parse is unchanged from baseline.
* `php artisan route:list` — all routes resolve, no duplicates.

Full Unit suite: **222 passed, 59 failed**. All 59 failures are pre-existing and environmental — `audit_logs` / `activity_logs` do not exist on the MySQL default connection, and `folder_watcher/static/correct_fileno.json` is absent. None touch this screen.

### Note on the RoT column

Only **2 of 133,827** `file_indexings` rows currently carry a `root_of_title`, so the column reads `N/A` almost everywhere. That is the data, not the plumbing — the field is hand-keyed on the File Indexing form and was added recently. `old_fileno` (2 rows) and `related_fileno` (27 rows) are similarly sparse but do render correctly where present.

---

## 8. Serial-number gap reuse (2026-09-03)

### What was already there, and why it did nothing

The gap-filling feature was half-built and had been abandoned:

* `FileNumberReservationService::findAvailableGaps()` and `GET /api/file-numbers/find-gaps` exist — but **nothing calls them**, and every call **throws**: the model expects `prefix` / `reservation_uuid` / `session_id`, while the live `file_number_reservations` table has `file_number` / `land_use_type` / `draft_id`. Two conflicting migrations exist for that table and the real one matches neither — it was created by hand from `database/sql/create_file_number_reservations.sql`.
* `STFileNumberService` says outright that it *"replaces the complex FileNumberReservationService"*. Those tables went dormant in Nov 2025.
* The live MLS allocator, `MlsSerialControl::getNextSerial()`, is a plain `last_serial + 1` counter that never looks at a gap, and Master Delete never rewinds it.

None of the above was touched. ST is explicitly out of scope.

### What shipped

Manual, on-request reuse of MLS serials, in `MlsSerialAllocationService`:

| Piece | Behaviour |
|---|---|
| `findReclaimableSerials($landUse, $year, $limit)` | Serials **below the counter** that nothing holds. Ordered freed-by-delete first, then never-issued. |
| `blockedFreedSerials($landUse, $year)` | Numbers a delete released that are still **not** safe, and which table holds each. |
| `isSerialReclaimable($landUse, $year, $serial)` | Single-serial re-check, called inside the commissioning transaction. |
| `GET /mls-fileno/reclaimable-serials` | `land_use` + `year` → `serials`, `blocked`, `current_serial`. |
| Commissioning form | A **"Use a missing serial number"** checkbox; when ticked, a dropdown of that prefix's available serials, each labelled *freed by delete* or *never issued*. Nothing is auto-assigned. |
| `generateMlsFileNumber` | Accepts `reclaimed_serial`, re-verifies it in-transaction, and returns **409** if it was taken while the form was open. |

### The digital floor — numbering did not start at 1

Each prefix ran on **paper** first; the platform was switched on part-way through and continued from wherever the manual register had reached. Read from `mls_file_no` (the digital register):

| Prefix (2026) | First digital serial | Counter |
|---|---|---|
| RES | **565** (first commissioned 2026-02-03) | 3028 |
| COM | 77 | 293 |
| CON-COM | **48** | 492 |
| CON-RES | 33 | 2577 |
| CON-AG | 18 | 119 |
| IND | 1 (genuinely starts at 1) | 272 |

Everything below that floor is a physical file the system has never seen — not a free number. Before the floor existed the dropdown listed RES-2026-211 … 231 as "never issued"; every one of them is paper.

The floor is read from `mls_file_no` **only**. `fileNumber` also holds captured and imported legacy records, and scanning both returns 1 for CON-COM instead of 48. `serial_number = 0` placeholder rows (2 on RES, 1 each on COM and IND) are excluded or they drag the floor to zero.

Effect on what is offered:

| Prefix | Before the floor | After |
|---|---|---|
| RES-2026 | 1, 2, 3, 4, 5, 6, 7, 8 … (paper) | **2** real gaps: 1868, 3027 |
| COM-2026 | 1 … 7, 27, 28, 34, 59, 60 (paper) | **4**: 288–291 |
| CON-COM-2026 | — | **6**, led by 491 *freed by delete* |

The window is stated in the UI (`CON-COM-2026-48 to 491`) so a low number an officer expects to see is explained rather than silently missing. The floor is enforced on the single-serial check too, since the serial arrives as a request field and need not have come from the dropdown.

### Four traps found and handled

1. **A hole is not proof a number is free.** Measured live: 112 of 569 apparent RES-2026 holes and **49 of 81** COM-2026 holes are in use elsewhere, mostly `file_indexings` rows with no `fileNumber` row. Every candidate is therefore checked against five tables — both registers, `file_indexings`, `pra` and `PropID_Master`.

2. **The register maximum is the wrong ceiling.** IND-2026 has the counter at 272 while `fileNumber` holds `IND-2026-3635` from a separate import. Using the highest register serial would advertise 273…3634 as "missing". The **counter** alone sets the ceiling.

3. **Filling a gap must not rewind the counter.** The neighbouring `force_file_number` branch calls `MlsSerialControl::initialize()`, which *sets* `last_serial` to the number given. Doing that for a reclaimed serial would wind RES-2026 back from 3028 to 5 and collide for the next dozen commissionings. The reclaimed branch deliberately leaves `mls_serial_control` untouched — asserted by a test that reads the branch's source with comments stripped.

4. **`LIKE 'COM-2026-1%'` also matches `COM-2026-100`.** The first version reported serial 1 as occupied by a hundred unrelated files, so it could never be reclaimed. Single-serial checks now match on `= number OR LIKE 'number[^0-9]%'`, which still counts `(T)` and `AND EXTENSION` suffixes as occupying the serial. Caught by a test, not by review.

Performance: the first implementation timed out (>120s) because the CRLF-tolerant indexing check is non-sargable and ran per candidate. Rewritten as one indexable range scan per table — now **0.2–1.1s** per prefix.

### Follow-up fixes

**The picked serial did not reach the field or the preview.** Choosing IND-2026-231 left the Serial No. box and the preview both reading IND-2026-273. Four separate code paths auto-fill that field from the counter — the component's `updatePreview()`, the reservation branch in the global `updatePreview()`, `updateGenerateForm()` and `updateAlpineSerialNumber()` — and each one silently undid the choice. They now all consult a shared `window.mlsfHoldingReclaimedSerial()` guard first.

`refreshSerialNumber()` also cleared the pick unconditionally; it now tracks which prefix/year the list was loaded for (`reclaimedLoadedFor`) and only discards the selection when the prefix actually changes.

**The blocked-numbers detail is hidden.** The *"N deleted number(s) cannot be reused yet: …"* line was too noisy for the panel. The endpoint still returns `blocked` and it is logged to the console, so it can be surfaced again without a backend change.

### Known limitation — worth a decision

The five numbers freed by the Master Delete of `IND-2026-257`…`261` are **not** offered, because each still holds a `pra` row and a `PropID_Master` row; `IND-2026-257` carries a Subdivision instrument on prop_id 147224. Reissuing would attach a new file to that history.

Rather than hide this, the dropdown reports it: *"5 deleted number(s) cannot be reused yet: IND-2026-257 (pra, PropID_Master); …"*.

To actually recover those numbers, Master Delete would have to purge `pra` / `PropID_Master` as well — a widening of the cascade beyond the six tables, which has not been approved. Left as-is pending that call.

### Verification

`tests/Feature/FileNumber/ReclaimableSerialTest.php` — 16 tests, read-only, against the real database: nothing at or above the counter, nothing below the digital floor, the floor matches `mls_file_no` and is not dragged down by `fileNumber`, a below-floor serial refused even when asked for directly, every offered serial absent from all five tables, a `pra`-held serial refused, a live serial refused, sibling prefixes never leak, listing never moves the counter, and the generate branch never calls `initialize()`.
