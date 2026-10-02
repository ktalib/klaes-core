# Duplex Parcel Update — Plan

**Source:** client sheet "Update August 17 2026" item 5 (plus items 6, 7, 8) and the
follow-up client conversation. Captured 2026-08-19. Supersedes the one-line
placeholder "Master Folder for Duplex Parcel Update" (item 13 of
`Update August 18 2026.md`).

**Status:** built and in use on the dev database. Capture, commissioning, the Land
step, the summary sheet and all three printed sheets are working and verified — see
§9 for the current state, which supersedes the older notes in §8.

---

## 1. The problem

Today the five parcel-update workflows (Change of Purpose, Subdivision, Merger,
Extension, Separation) are five separate applications. If one parcel needs three
things done to it, the officer runs three complete cycles — three captures, three
approvals, three memos, three commissionings, one after the other. Worse, each
cycle commissions real file numbers, so cycle 2 has to start from whatever cycle 1
produced, and the client gets three separate approvals for what is legally one
instruction.

The client wants **one instruction, one approval, one memo, one commissioning
event** — a "**Duplex**": a single record that carries several parcel updates in a
declared execution order, held on temporary numbers until the very end, then
committed in one shot.

> *"So even if there are 3 things, we do them one after the other. So now, because
> when you get approval, they get that approval altogether. And that also comes on
> the memo. So what they tick here will come on the memo in that order."*

---

## 2. Concept

- A Duplex is **its own register** — its own table and its own listing page, one
  row per duplex operation, with the component updates held as JSON on that row.
  It is not a flag scattered across the five existing tables.
  > *"I feel we should have this duplex as a table on its own… one row will
  > represent one duplex function, and then you can use your JSON to separate them."*
- Every file number produced inside a duplex before the final step is a
  **temporary holding number**. Nothing is commissioned, nothing is decommissioned,
  until the officer confirms the whole duplex in Land.
- The duplex carries a **Duplex ID** that threads every stage, every holding
  number and every resulting file number together.

---

## 3. Officer flow

### 3.1 Select and rank

A **Duplex** action opens the selector:

```
+-- Select Multiple Parcel Update ----+
|  [x] Change of Purpose         (3)  |
|  [x] Subdivision               (2)  |
|  [x] Merger                    (1)  |
|  [ ] Extension                      |
+-------------------------------------+
              [ Start Process ]
```

- Rank is **assigned automatically in the order the officer ticks**, and that rank
  is the execution order. First tick = 1, second = 2, third = 3.
- The rank badge is shown on the checkbox itself and colour-coded (1 red, 2 blue,
  3 …). Unticking re-numbers the rest.
- If the checkbox + badge proves awkward in practice, fall back to a per-row
  **dropdown** modelled on the existing HC/PS recommendation selector.
- Separation is not on the client's list. Decide whether it joins (§7, Q1).

### 3.2 Quantities

`Start Process` opens a second card asking **how many of each**: number of mergers,
number of subdivisions, number of change-of-purposes. Then `Continue Process`.

The counts are independent — the client's own example: subdivide one plot into
**four**, then change the purpose of only **two** of them. The pipeline must not
assume 1:1 between stages.

> *"…if he does subdivide into four, make two. So you subdivide into four, you have
> four originally, you have one plot. So you add into four? They change two."*

### 3.3 Run the stages in rank order

Each stage collects only what that stage needs, and each stage's output feeds the
next as **holding numbers**.

**Merger (rank 1 in the example)**
- Officer picks the source file numbers (file 1, 2, 3, 4 … from the global file
  selector).
- The system **automatically generates a temporary holding number** and pushes the
  record to Land for the next available number based on the serial of the files
  selected.
- The stage then shows the holding number plus a tracking ID.

**Subdivision (rank 2)**
- Input is the holding number produced by the merger, not a real file.
- Officer states the number of plots and the plot sizes (reuse the existing
  `num_plots` + `plot_sizes` range control).
- Holder names default to the mother's holder, but each child can be reassigned —
  in practice subdivided plots usually go to different people.
  > *"To ask for the name of the people, if they are not the default person. Most of
  > the time it's not the same person, they own-subdivide them."*
- Produces N holding numbers.

**Change of Purpose (rank 3)**
- Officer picks which of the subdivision children this applies to (2 of the 4 in
  the example). Unselected children are greyed out — nothing happens to them.
- The current land use is pre-filled from the mother; the officer picks the **new**
  land use.
- The system then fetches the available file numbers for that new land-use pool.
- At the end of this stage the **conveyance and the memo** are generated.

Stages that are not ticked never appear.

### 3.4 Hand-off to Land

Once the conveyance and memo are generated, the duplex appears **in Land as a
Duplex entry**, under commissioning, filterable/selectable as "Duplex" so duplex
rows do not sit mixed in with ordinary commissioning rows.

The Land view expands the duplex by Duplex ID and shows, per stage, the holding
numbers on the left and the file numbers they will become on the right:

```
Duplex ID: DPX-2026-0007

Merger        holding [ ...... ]  ->  new file [ ...... ]
Subdivision   holding [ ...... ]  ->  new file [ ...... ]   (1)
              holding [ ...... ]  ->  new file [ ...... ]   (2)
              holding [ ...... ]  ->  new file [ ...... ]   (3)
              holding [ ...... ]  ->  new file [ ...... ]   (4)
Change of     holding [ ...... ]  ->  new file [ ...... ]   (from subdivision 1)
Purpose       holding [ ...... ]  ->  new file [ ...... ]   (from subdivision 2)
```

### 3.5 Commit — one click

The officer confirms once and the system generates **all** the real file numbers
for the whole duplex in a single batch. Not one at a time.

> *"…click on Perfect. It will now generate — yes, this one, this one, this one,
> this one. I think this batch is also the best. Instead of one at a time, at once."*

The decommissioning prompt must state the full picture up front — the merger's
sources, the subdivision mother, and any intermediate holding files — **in
execution order**, and decommission them per the existing rules
(`docs/plans/PLOT_MANAGEMENT_WORKFLOW.md`): archive to `decommissioned_files` plus
`deprecated_records`, then remove from the active tables.

---

## 4. Data model (proposed)

| Table | Purpose |
|---|---|
| `duplex_parcel_updates` | One row per duplex. `duplex_id` (human ref), `status`, `stages` JSON (type + rank + counts), source file numbers, applicant, KAMMA fields, approval/memo/conveyance timestamps, audit columns matching the existing parcel tables. |
| `duplex_parcel_update_stages` | One row per stage instance: `duplex_id`, `type`, `rank`, `status`, `input_holding_no`, `payload` JSON (plots, sizes, holders, new land use), `tracking_id`. |
| `duplex_parcel_update_files` | One row per file the duplex touches: `duplex_id`, `stage_id`, `role` (source / holding / result), `holding_no`, `final_file_no`, `prop_id`, `parent_prop_id`, decommission flag. |

Notes and constraints carried over from the existing module:

- Holding numbers are a **new concept** — the closest existing things are
  `plot_merger_applications.temp_file_no` and `land_temporary_file_numbers`, but
  neither is a chainable allocation. Decide whether holding numbers reuse the
  `(T)` temporary-number convention (which `FileLocationResolver` and
  `FileIndexingPropagationService` already understand) or get their own namespace.
  The `(T)` route inherits a lot of working plumbing and is the recommendation.
- prop_id / `parent_prop_id` / `related_fileno` must end up exactly as the
  single-workflow paths produce them, since Legal Search reconstructs lineage from
  them (`LegalSearchService::getSmeAllowedFileNos`). A duplex creates a **chain** of
  lineage (sources -> merged -> children -> renamed children), so `parent_prop_id`
  must point one level up at each hop, not all the way back to the original sources.
- Commissioning must go through `MlsFileNoController::generateBatch()` — do not
  write a second commissioning path. That method already handles subdivision,
  merger, extension and separation and was fixed in the 2026-07-10 audit
  (`docs/reports/kangis-alias/05-parcel-update-workflows-audit.md`); a parallel
  implementation would re-introduce every defect listed there.
- The intermediate holding files themselves get decommissioned at commit — they
  exist only to carry the chain.

---

## 5. Implementation phases

1. **Schema + models** — the three tables, models, Duplex ID allocator, and the
   holding-number allocator (decide `(T)` vs own namespace first).
2. **Selector + rank UI** — the Duplex action, ordered checkboxes with colour
   badges, quantities card, `Start Process` / `Continue Process`.
3. **Stage runner** — a driver that walks the stages in rank order, each stage
   reusing the existing capture form fields, writing holding numbers, never
   touching the registry.
4. **Approval, memo, conveyance** — one approval for the whole duplex; the memo
   lists the component updates **in the ticked order**. Conveyance uses the
   conveyance template (sheet item 7).
5. **Land duplex listing** — the expandable Duplex view under commissioning, with
   its own filter.
6. **Batch commit** — one transaction: allocate every final file number via
   `generateBatch`, wire the lineage chain, decommission sources and holding files,
   emit the PRA rows per stage type.
7. **Legal Search verification** — search each resulting file and confirm the full
   chain resolves and sibling units stay excluded.

---

## 6. Related items from the same client sheet

Not part of the duplex itself, but the same sheet, same module:

- **6.** Change the Application and Memo under Parcel Update (Subdivision etc).
- **7.** Add **Conveyance** to the action menu and use the Conveyance template.
- **8.** File Commissioning table edit should allow attaching Related FileNo /
  Old FileNo.
- **4.** Customer Type defaults to "Individual" in the File Commissioning interface.
- **11–13.** Special Assignment: Change of Purpose sheet card, what the
  Commissioner Memo is, and where the memo is generated.

---

## 7. Open questions for the client

1. Does **Separation** join the duplex selector? It is absent from the sketch but
   is a live workflow (and no separation has ever been commissioned in production).
2. Can the same update type appear **twice** in one duplex (e.g. two subdivisions
   at different ranks), or is each type at most once?
3. If a stage is rejected at approval, does the whole duplex fail, or can the
   officer re-run that one stage while the rest hold?
4. Who approves a duplex — the same authority as the heaviest component, or a
   fixed authority regardless of composition?
5. Should the Land officer be able to **edit** stage details at the commissioning
   screen, or only confirm/reject the whole thing?
6. Holding numbers: reuse the `(T)` convention, or a distinct duplex namespace?


---

## 8. As built (2026-08-19)

Client answers that shaped the build:

| Q | Answer |
|---|---|
| Separation in the selector | Yes — all five types |
| Same type twice | Optional; ranks are 1..N and a type may repeat |
| Rejected stage | That stage alone reopens; the others hold their payloads and holding numbers |
| Approver | Same authority the single workflows already use |
| Land editing at commissioning | No — confirm or reject the whole duplex |
| Holding numbers | Distinct namespace: `DPX-2026-0007-H03` |

Also: the existing UI is untouched. The Duplex is **a page of its own**, and the Land
confirm/reject step lives on that page rather than as a tab on the commissioning screen —
partly because a duplex is often just one update, and the officer should still work here.

### Files

| Layer | Path |
|---|---|
| Schema | `database/migrations/2026_08_19_000000_create_duplex_parcel_update_tables.php` |
| Production SQL | `database/sql/2026_08_19_create_duplex_parcel_update_tables.sql` + `..._ledger.mysql.sql` + `verify_duplex_parcel_update_schema.sql` |
| Models | `app/Models/DuplexParcelUpdate{,Stage,File}.php` |
| Holding numbers | `app/Services/DuplexHoldingNumberService.php` |
| Commit | `app/Services/DuplexCommitService.php` |
| Controller | `app/Http/Controllers/Deeds/ParcelUpdate/DuplexParcelUpdateController.php` |
| Views | `resources/views/deeds/parcel_update/duplex/` (index, wizard, commission, js, 3 print templates) |
| Routes | `routes/app3.php` — `duplex-parcel-update.*`, 18 routes |
| Sidebar | one entry below the Parcel Update items in `lands.blade.php` |

### How the chain actually resolves at commit

Holding numbers are a **planning device**. At commit, stages run in rank order and each one
consumes the *real* file numbers the previous stage just produced, so by the time a stage runs
its input is always a real registry file — which is exactly what the commissioning engine
expects. Each stage materialises a row in the matching existing application table
(`plot_subdivision_applications`, `plot_merger_applications`, …), tagged
`[Duplex DPX-… · stage N]`, because that is where `generateBatch` reads its lineage from.

Decommissioning then falls out for free: each stage retires its own input, so the original
sources go with stage 1 and every intermediate file is retired by the stage that consumes it.

### Verified

- Migration applied; all three tables confirmed on **sqlsrv** via the catalog, not the ledger.
- 3-stage duplex (Merger → Subdivision ×4 → CoP on 2 of 4) captured end to end;
  holding chain came out `H01` → `H02..H05` → `H06..H09`.
- **Registry untouched by capture** — `fileNumber`, `file_indexings`, `mls_file_no`, `pra`,
  `decommissioned_files` row counts unchanged.
- Holding namespace clean — no `DPX-…-Hnn` value present in any registry table.
- Gates hold: memo blocked without KAMMA, send-to-land blocked without the conveyance.
- Test rows deleted by id afterwards.

### Commissioning — verified end to end (2026-08-22)

A full 3-stage duplex was committed against a throwaway `ZZT-`/`ZZC-` namespace and every
row deleted afterwards:

```
(1) Merger            ZZT-2026-9001, ZZT-2026-9002  ->  ZZT-2026-9006
(2) Subdivision       ZZT-2026-9006                 ->  ZZT-2026-9007/8/9
(3) Change of Purpose ZZT-2026-9007/8/9             ->  ZZC-2026-3, ZZC-2026-4, ZZT-2026-9009
```

Confirmed: merger `parent_prop_id` is the CSV of both source prop_ids; each subdivision child
points at the merged parcel; `related_fileno` correct at every hop; all five decommission rows
written with successor pointers; a PRA row per stage output (`Merger`, `Subdivision` x3,
`Change of Purpose` x2); `mls_file_no.source` correct per stage; the third child, left out of
the CoP, passed through untouched and stayed active. The parcel-update tables were backfilled
as intended and marked `commissioned` by the engine.

This was **the first merger ever commissioned in this database** — no merger existed in live
data before it.

**Two defects found and fixed by this run:**

1. Stages captured a Tracking ID and the commit discarded it. The single-file commissioning
   path refuses to run without one (`Tracking ID not found in grouping table`) and will not
   invent one, unlike the batch path — so every merger, extension, one-plot and Change of
   Purpose stage would have failed at the Land step. The commit now passes it through, the
   wizard collects it per stage, and both refuse early with a message naming the stage.
2. Change of Purpose renames file by file, so it always uses the strict single-file path
   however many files it covers. The UI gate had only asked for a Tracking ID when a stage
   produced fewer than two files, so a 2-file CoP would have passed capture and stalled at
   commissioning.

### Observed, pre-existing (not caused by the duplex)

`file_indexings.prop_id` is left NULL on batch-commissioned children while `pra.prop_id` is
populated. Verified against real subdivision children commissioned through the existing UI
(IND-2026-258..263) — same behaviour. Out of scope here; flagged for the module.

---

## 9. Current state (2026-08-24)

Everything below is verified against the dev database, not asserted.

### 9.1 Where it lives

| Piece | Path |
|---|---|
| Register + wizard | `resources/views/deeds/parcel_update/duplex/` |
| Summary sheet | `public/js/duplex-summary-card.js` |
| Controller | `app/Http/Controllers/Deeds/ParcelUpdate/DuplexParcelUpdateController.php` |
| Commit engine | `app/Services/DuplexCommitService.php` |
| Summary payload | `app/Services/DuplexSummaryService.php` |
| Holding numbers | `app/Services/DuplexHoldingNumberService.php` |
| Land-use parsing | `app/Support/FileNumberLandUse.php` |
| Rollback | `app/Console/Commands/DuplexRollback.php` |
| Commissioning modal | `resources/views/generate_fileno/mlsfno.blade.php` + `mls_js.blade.php` |
| Sidebar | `lands.blade.php` and `deeds.blade.php`, first entry under Parcel/Title Management |

20 routes under `duplex-parcel-update.*`.

### 9.2 The officer's path

1. **Deeds** → *Duplex Parcel Update* (`?mode=deeds`) → **New Duplex**.
2. **Step 1** — pick the source file(s), then add updates from a **dropdown**. Each pick
   appends to the plan and drops out of the dropdown; the list below shows **only what has
   been added**, in order, each row locked with an × to remove. Picking **more than one
   source file auto-adds Merger as leg 1**, locked (it cannot be removed while several
   files are selected), with its count set to the number of files. Location (plot,
   district, LGA) is captured here and composed into one `DISTRICT, LGA, KANO` string.
3. **Step 2** — quantities **and plot sizes**, with an `N → M` badge per stage so a
   Merger's "1" cannot be misread as "one file". See §9.8 for the running-area line.
4. **Step 3** — one panel per stage in rank order, running on holding numbers. Sizes seed
   from step 2; holders default to the applicant, except a **Merger, where each row
   defaults to that source file's own registered holder** (merged files usually come from
   different owners).
5. **Submit** opens the summary sheet, then the register.
6. KAMMA → Approve → Memo → Conveyance. The row menu **gates itself**: everything is
   disabled until Approve, and each step unlocks the next and closes behind itself.
7. **Land** commissions from the **MLS Commission New File Number** modal: File Type →
   **Duplex** → pick it → the whole plan renders inline → Confirm & Generate.

`?mode=land` opens the register **read-only** (no New Duplex, no action menu) — Land acts
from the commissioning modal, not from here. The summary sheet stays available.

### 9.3 Numbers: the arithmetic that matters

A 1-file duplex, subdivided into 5, then a Change of Purpose on 2:

```
issued 7 · decommissioned 3 · active 5
```

Seven numbers, because the CoP mints 2 new ones and retires the 2 children it renames —
plus the original mother. **Only files that get a NEW number count**; the three carried
through keep theirs (`role = carried`) and are never re-minted. Getting this wrong is the
single easiest mistake here: counting file *rows* gives 10, which is what the batch
quantity and the summary sheet both showed before it was fixed.

Serials **continue the existing series per land use** — a duplex never starts its own.
Each stage reads the live max for its land use at the moment it runs.

### 9.4 Verified

- 20 routes registered; every duplex PHP file and the summary card lint clean.
- Three tables present on **sqlsrv**; register (both modes), commission page, summary
  endpoint, picker endpoint and all three print sheets render.
- Commissioning modal carries the Duplex file type, selection panel, picker, plan review,
  batch breakdown, commit branch and the summary card script.
- On the committed DPX-2026-0007: **every file has its own unique tracking id**;
  `parent_prop_id` and `related_fileno` set on all; **7 PRA rows**; sources decommissioned.
- Legal Search resolves each chain — `COM-2026-292 + IND-2026-266`, sibling units excluded.

### 9.5 Traps worth remembering

- **`rank` comes back from sqlsrv as a string.** `st.rank === 1` silently never matched,
  and the whole "what does confirming do" ledger was wrong as a result. Cast it.
- **The Generate button is gated on a grouping Tracking ID** for a typed file number. A
  duplex has none, so `setActionButtonsDisabled()` short-circuits while one is driving the
  modal, and tracking ids are minted per stage at commit instead.
- **The single-file commissioning path refuses to invent a tracking id** (the batch path
  mints them freely). Merger, Extension, one-plot and every Change of Purpose file goes
  through it, so `DuplexCommitService::trackingIdFor()` resolves or mints one.
- **`CON` and `ST` are prefixes, not land uses.** `CON-AG-1995-15` is **AG**. A Change of
  Purpose on it must produce `CON-COM-…`, keeping the prefix. See `FileNumberLandUse`.
- **A Change of Purpose does not shrink the file count** — it renames some and passes the
  rest on, so all of them reach the next stage.
- **`file_indexings.prop_id` is NULL on batch-commissioned children.** Pre-existing and
  module-wide (confirmed against IND-2026-258..263 commissioned through the normal UI),
  not caused by the duplex. `pra.prop_id` and `parent_prop_id` are correct, which is what
  Legal Search reads.

### 9.6 Re-running a test

```
php artisan duplex:rollback DPX-2026-0007      # one duplex
php artisan duplex:rollback --all              # every committed duplex
php artisan duplex:rollback --all --dry-run    # report only
```

Deletes only rows keyed to the file numbers that duplex created, restores the source file,
frees the serials and returns the duplex to `in_land` with its plan and holding numbers
intact.

### 9.7 Printed sheets

- **Conveyance** — the official Ministry letter: title number centred, date right,
  addressee, bold-underlined RE line, justified prose. File numbers print with slashes
  (`IND/1990/63`). The RE line and body are composed from the stages.
- **Memo** — the same sheet as the single-workflow recommendations (PS / Honourable
  Commissioner blocks, signature lines, Approved/Not Approved). The application line lists
  **counts and names only** — "5 Subdivision, 3 Change of Purpose and 5 Merger" — with one
  lettered point per stage below.
- Both carry KLAES bottom-left and LAnd ADmin bottom-right, pinned to the foot of the page.

### 9.8 Still open

- **Separation** and **Extension** stages have never been run end to end. They share code
  paths with what has been tested (Extension is single-file like Merger, Separation is
  batch like Subdivision), but neither is proven.
- The conveyance has no signatory, postal-address or application-date field; it prints a
  blank rule, the parcel location, and the capture date respectively.
- `?mode=land` is a UI gate, not a permission — `?mode=deeds` typed by hand still gives
  the full page, exactly as the other parcel-update pages behave.

### 9.8 The wizard's arithmetic aids (added 2026-08-24)

Step 2 carries the **parcel area through the chain**, because a merger's parts add up to
the parcel the next stage divides and that figure was nowhere on screen:

```
Merger        [0.5] [0.5] [0.2] [0.8]      Merged parcel: 2 Ha        (line BELOW)

Subdivision   Parcel being divided: 2 Ha · plots entered: 0.8 Ha ·
              1.2 Ha remaining                                        (line ABOVE)
              [0.2] [0.2] [0.4]
```

The line sits **below** a merger (its total is *derived* from the boxes) and **above** a
subdivision or separation (it is *allocated* out of a parcel already known). It is coloured
as a whole: blue while reporting an area, green when a subdivision balances
("accounted for"), amber when it does not ("1.2 Ha remaining", or "… over"). Nothing is
blocked either way — a survey plan can legitimately differ.

A **Change of Purpose does not change any area**; it renames parcels, so it reports its
own figure but does not alter the carried one.

### 9.9 Row-menu gating (added 2026-08-24)

The menu walks the pipeline so the officer is never offered a step the server would refuse:

| Action | Unlocked by |
|---|---|
| Approve | every stage captured |
| KAMMA | Approve |
| Generate Application | KAMMA = Approved |
| Print Application | the application existing |
| Generate Memo | the application existing |
| Generate Conveyance | the memo existing |

Each *Generate* **closes behind itself**, so nothing can be run twice. **Rejected** and
**committed** duplexes are read-only apart from viewing and printing. Disabled rows grey
out rather than disappearing, each with a tooltip saying why. Approve and Reject both
confirm first, and neither dialog closes on an outside click.

**Send to Land and Commission (Land) were removed** from the menu — Land picks the duplex
up from the commissioning modal. `DuplexCommitService` therefore accepts **`approved`** as
well as `in_land`; requiring `in_land` after removing the only action that set it would
have refused every duplex. Note this also removed the gate that demanded a memo *and*
conveyance before hand-off.

### 9.10 What the sheets print

Both are composed from the stages in execution order, and both carry KLAES bottom-left and
LAnd ADmin bottom-right, pinned to the foot of the page.

- **Memo** — the application line lists **counts and names only**: "5 Subdivision, 3 Change
  of Purpose and 5 Merger". One lettered point per stage carries the detail, each naming
  its **plot sizes** as a list: "into 3 parcels of 2.5 + 2.6 + 3 Ha". **No totals** — a
  computed sum on a legal instrument only invites an argument with the survey plan.
- **Conveyance** — the official letter, file numbers with slashes (`IND/1990/63`), sizes
  listed the same way.
