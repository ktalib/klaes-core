# Duplex — Change of Purpose as Leg 1

**Worked against a real application:** PREFAB NIGERIA LIMITED, 21 May 2026, received by the
Ministry 26/05/2026. File jacket `KES/RC/85/58`.

**Source of the requirement:** call with the supervising officer, transcribed 2026-08-24.
Companion to `docs/plans/DUPLEX_PARCEL_UPDATE.md`, which stays the full record of the module.

---

## 1. The application

> **APPLICATION FOR CHANGE OF PURPOSE, MERGER AND SUB-DIVISION**
> **KNML08080 (FORMALLY RES/RC/2004/06), MLKN03235 (FORMALLY COM/RC/82/420) AND (RES/RC/85/58)**
>
> *"I wish to apply for the above subject matter change of purpose, merger and the
> sub-division in to thirty-nine (39) potions."*

This is a **three-file duplex whose first leg is a Change of Purpose** — the exact case the
call was about. The letter even states the legs in that order in its own subject line.

| # | As written | Stored form | Land use |
|---|---|---|---|
| 1 | `KNML08080` (formerly `RES/RC/2004/06`) | `RES-RC-2004-6` | **RES** |
| 2 | `MLKN03235` (formerly `COM/RC/82/420`) | `COM-RC-1982-420` | **COM** |
| 3 | `RES/RC/85/58` | `RES-RC-1985-58` | **RES** |

Two Residential, one Commercial, merged into one parcel and split into 39 portions.

### Why the Change of Purpose has to come first

The call did not say, but this letter answers it: **you cannot merge parcels of different
land uses.** Two RES and one COM have to be brought to a single purpose before the merger,
which is why the applicant lists change of purpose first and why the module has to allow it.
That was inference last time; the letter makes it the plain reading.

---

## 2. What the letter does not say — answered by the second call

The letter says only "change of purpose". The call of 2026-08-24 settles it:

> *"One of the three primary files is commercial, while the other two are residential.
> They want to change the commercial file to residential… the parcelled plots would be
> residential. That is why they want to change the commercial file to residential in the
> first place."*

So it is **COM → RES on one file** — reading (a). And the layout adds a second step the
letter never mentions:

> *"According to the layout, out of the thirty-nine plots they want to create, three will
> be commercial… Change of Purpose is going to occur at two different stages."*

**Change of Purpose runs twice**: once before the merger to bring all three files to
residential, once after the subdivision to make 3 of the 39 plots commercial.

Still open:

1. **`RES/RC/85/58` vs the jacket's `KES/RC/85/58`.** The typed letter says RES; the
   handwritten number on the red cover reads KES. `KES` is not a land use in
   `FileNumberLandUse::LABELS`. Indexed as **RES** per the typed letter; confirm against
   the physical file.
2. **Which 3 of the 39** become commercial, and their plot numbers — on the layout, not in
   the letter.
3. The call also mentions these are **land resuscitation files**, two of which now have
   cadastral file numbers, and that one record had been **duplicated** (a "KN number" that
   turned out to be the old KN number on a Lands file). Nothing in the duplex depends on
   this, but it is why `RES-RC-2004-6` already carries a related `KN 4938` — see §3.4.

---

## 3. What the dev database holds — and what was indexed

### 3.1 What was there before

| Number | Before |
|---|---|
| `RES-RC-2004-6` | **indexed** — `file_indexings` 122698, `FileName = GREEN PALACE HOTELS`, `prop_id` **NULL** in file_indexings while `PropID_Master` held 68246 |
| `COM-RC-1982-420` | absent everywhere |
| `RES-RC-1985-58` | absent everywhere |
| `KNML 8080` | **`kangis_grouping` id 32317** — registered, never indexed |
| `MLKN 3235` | **`kangis_grouping` id 12938** — registered, never indexed |

**The letter's zero padding is just padding.** `KNML08080` is `KNML 8080` and `MLKN03235`
is `MLKN 3235` — both were already sitting in `kangis_grouping` from a 2026-03-04 seed,
awaiting indexing. That settles open question 3 from the earlier draft.

Likewise `RES/RC/2004/06` is stored `RES-RC-2004-6`, with **no leading zero**. A literal
lookup for `RES-RC-2004-06` finds nothing — the same silent-miss class as the `(T)` variant
bugs.

### 3.2 Two different indexing paths, because these are two different generations of file

- **KANGIS parents** went through `FileIndexingController::store()` — the real screen's
  path — because their `kangis_grouping` rows exist. store() writes `file_indexings` +
  `fileNumber`, allocates the prop_id and runs `KangisParentLinkService::linkOnIndex()`.
  It also enforces that `sys_batch_no`, `registry_batch_no` and shelf/rack come **from the
  grouping row**, which is why they are carried across rather than chosen.
- **Land files** could not use it. No `-RC-` file has a grouping row — **0 of 4,498** — that
  whole generation was bulk-imported straight into `file_indexings`, so store() refuses them
  with *"Awaiting file number must match an existing grouping record"*. They were created the
  way their siblings were: a `file_indexings` row and a `fileNumber` row cloned from
  `RES-RC-2004-6`, with a prop_id from `PropertyIdAllocationService`.

### 3.3 As indexed

```
KNML 8080          prop_id 147247   root       related ["RES-RC-2004-6"]
  └── RES-RC-2004-6      prop_id  68246   parent 147247

MLKN 3235          prop_id 147248   root       related ["COM-RC-1982-420"]
  └── COM-RC-1982-420    prop_id 147245   parent 147248

RES-RC-1985-58     prop_id  67342   standalone — pre-existing file, no KANGIS number quoted
```

**Three** rows added to each of `file_indexings`, `fileNumber` and `PropID_Master`, plus two
`file_indexing_links`. Only `COM-RC-1982-420`, `KNML 8080` and `MLKN 3235` were created;
`RES-RC-2004-6` and `RES-RC-1985-58` already existed and were left as they are, apart from
backfilling their NULL `file_indexings.prop_id` from `PropID_Master`. All five are found by
the duplex source picker — the three land files on the MLS tab, the two KANGIS numbers on
the KANGIS tab.

### 3.4 Two things to check against the physical file

1. **`RES-RC-2004-6` is not held by PREFAB.** `current_holder` is **GREEN PALACE HOTELS**,
   `original_holder` **ALH. UBA KASSIM**, location *PLOT 10, BARGERY ROAD, NASARAWA*. Either
   PREFAB acquired it — normal enough on a change of purpose — or this is not the file the
   letter means. It was left exactly as it was; only the NULL `prop_id` was backfilled from
   `PropID_Master`.
2. **`RES-RC-1985-58` is not held by PREFAB either.** Holder **NASIR A.S. DANTATA**,
   indexed 2026-02-11, prop_id 67342, also in Nasarawa. So two of the three primary files
   are registered to other names — consistent with an acquisition, but worth confirming.
3. **`RES-RC-2004-6` already had a related file, `KN 4938`**, indexed 2026-05-20. That is a
   legacy MLS "KN" number, not a KANGIS one, and it was left untouched.

Plot sizes (2.5 / 1.8 / 1.2 Ha), district and LGA are **placeholders** — the letter gives the
applicant's address, not the parcel's, and the sizes are on the site plans.

---

## 4. Two defects this application hits head-on

### 4.1 A KANGIS number resolves as a land use

`FileNumberLandUse::codeFor()` splits on `-`. A KANGIS number has no dash, so the whole number
comes back as the land-use code:

```
AG-RC-1981-12     code = AG          prefix = ''     correct
RES-RC-2004-06    code = RES         prefix = ''     correct
MLKN 139          code = 'MLKN 139'  prefix = ''     WRONG
KNML 8080         code = 'KNML 8080' prefix = ''     WRONG
```

`KN` is in `PREFIXES`, but it never matches because there is no dash to split on. There are
**920 `KNML…` and 2,082 `MLKN…`** numbers in `fileNumber`. Two of this application's three
files are KANGIS-numbered, so if the officer picks them by that identity, the stage's land use
is garbage and the new file number is built from it.

**Either** the source picker resolves a KANGIS number to its MLS number before the stage sees
it (the reverse-lookup via the `registry = KANGIS` indexing row already exists), **or**
`codeFor()` learns to return `''` for a KANGIS-shaped value so it fails loudly instead of
silently. It should probably do both.

### 4.2 `RC` is dropped from the new number

`prefixFor('RES-RC-2004-6')` returns `''` because `RC` is not in `PREFIXES` — so a Change of
Purpose on it produces `RES-2026-3026`, and the `RC` segment is gone. `CON` and `ST` *are*
preserved. **Is `RC` meant to survive a change of purpose, or is dropping it correct?** With
4,498 files carrying it this is not a corner case, and the answer belongs to the Ministry, not
to me.

---

## 5. The trace — capture to commissioning

Serials below continue the **live RES series**, which currently stands at `RES-2026-3025`.
They are illustrative: the real serials are allocated at commit, in order, per land use.

### Step 1 — sources and plan

Officer picks the three files. The system auto-adds **Merger** as leg 1 — the officer
**removes it** (it now carries an ×, and confirms before going), then picks **Change of
Purpose**, which prompts:

> *You have selected 3 files. Do you want to change the purpose of all 3 selected files, or
> are some of the files for merger?*   **[ Yes, all 3 ]  [ No, only some ]**

**No** → the three-column table:

| File Number | Current Purpose | New Purpose |
|---|---|---|
| `COM-RC-1982-420` | Commercial (COM) *(auto)* | **Residential (RES)** |
| | | *[ + Add More ]* |

The two RES files are never added — they carry through untouched. Then Merger, then
Sub-division, then **Change of Purpose a second time**:

```
1. Change of Purpose   — 1 of 3 files, COM -> RES
2. Merger              — 3 files -> 1
3. Sub-division        — 1 -> 39 portions
4. Change of Purpose   — 3 of the 39, RES -> COM
```

### The chain

Serials continue the **live series**: RES stands at `RES-2026-3025`, COM at `COM-2026-293`.
Illustrative — the real serials are allocated at commit, per land use.

| Leg | Input | Final | Note |
|---|---|---|---|
| 1 · Change of Purpose | `COM-RC-1982-420` | `RES-2026-3026` | renamed |
| | `RES-RC-2004-6` | `RES-RC-2004-6` | **carried** |
| | `RES-RC-1985-58` | `RES-RC-1985-58` | **carried** |
| 2 · Merger | all three | `RES-2026-3027` | one parcel |
| 3 · Sub-division | `RES-2026-3027` | `RES-2026-3028` … `RES-2026-3066` | 39 portions |
| 4 · Change of Purpose | 3 of those 39 | `COM-2026-294` … `COM-2026-296` | the layout's commercial plots |
| | the other 36 | unchanged | **carried** |

Each leg retires its own input:

```
COM-RC-1982-420   -> RES-2026-3026          (leg 1)
RES-2026-3026   -> RES-2026-3027          (leg 2, merged)
RES-RC-2004-6   -> RES-2026-3027          (leg 2, merged)
RES-RC-1985-58    -> RES-2026-3027          (leg 2, merged)
RES-2026-3027   -> RES-2026-3028..3066    (leg 3, subdivided)
3 of RES-2026-3028..3066 -> COM-2026-294..296   (leg 4)
```

### The arithmetic

```
issued 44 · decommissioned 8 · active 39
```

`1 + 1 + 39 + 3 = 44` issued. Eight retired: the COM source, the three files the merger
swallowed, the merged parcel, and the three plots leg 4 renames. Thirty-nine active — **36
residential and 3 commercial**, which is the layout.

Cross-check: 3 sources + 44 issued = 47 rows; 8 retired + 39 active = 47.

---

## 6. The summary card, as it would render

`public/js/duplex-summary-card.js` — the sheet that opens after Submit and again from the
register. Same sections, same order, in text (the 39 portions elided in the middle):

```
+--------------------------------- Duplex Summary ----------------------------------+
|                                                                                   |
|  +-----------------------------------------------------------------------------+  |
|  |  DUPLEX ID                        STATUS                                    |  |
|  |  DPX-2026-00XX                    COMMISSIONED                              |  |
|  |  LAND USE                         FILE NAME                                 |  |
|  |  RES                              PREFAB NIGERIA LIMITED                    |  |
|  +-----------------------------------------------------------------------------+  |
|                                                                                   |
|  PARCEL FILES THIS DUPLEX STARTED FROM                                            |
|     RES-RC-2004-6      COM-RC-1982-420      RES-RC-1985-58                            |
|                                                                                   |
|  STAGES - IN EXECUTION ORDER                                                       |
|  +-------------------------------------------------------------------------+ #1   |
|  |  Change of Purpose  ->  RES                                              |      |
|  |    DPX-2026-00XX-H01   ->  RES-2026-3026                                 |      |
|  |    RES-RC-2004-6       ->  RES-RC-2004-6        UNCHANGED                |      |
|  |    RES-RC-1985-58        ->  RES-RC-1985-58         UNCHANGED                |      |
|  +-------------------------------------------------------------------------+      |
|  +-------------------------------------------------------------------------+ #2   |
|  |  Merger                                                                  |      |
|  |    DPX-2026-00XX-H02   ->  RES-2026-3027                                 |      |
|  +-------------------------------------------------------------------------+      |
|  +-------------------------------------------------------------------------+ #3   |
|  |  Sub-division                                                            |      |
|  |    DPX-2026-00XX-H03   ->  RES-2026-3028                                 |      |
|  |    DPX-2026-00XX-H04   ->  RES-2026-3029                                 |      |
|  |    DPX-2026-00XX-H05   ->  RES-2026-3030                                 |      |
|  |            . . .  (39 portions in all)  . . .                            |      |
|  |    DPX-2026-00XX-H40   ->  RES-2026-3065                                 |      |
|  |    DPX-2026-00XX-H41   ->  RES-2026-3066                                 |      |
|  +-------------------------------------------------------------------------+      |
|                                                                                   |
|  FILE NUMBERS GENERATED                                                           |
|     RES-2026-3026   RES-2026-3027                                                 |
|     RES-2026-3028   RES-2026-3029   RES-2026-3030   RES-2026-3031  ...            |
|     ...                                             RES-2026-3065  RES-2026-3066  |
|                                                                                   |
|  DECOMMISSIONED                                                                   |
|     COM-RC-1982-420   -> RES-2026-3026    superseded by change of purpose           |
|     RES-2026-3026   -> RES-2026-3027    merged                                    |
|     RES-RC-2004-6   -> RES-2026-3027    merged                                    |
|     RES-RC-1985-58    -> RES-2026-3027    merged                                    |
|     RES-2026-3027   -> RES-2026-3028    subdivided                                |
|                                                                                   |
|  LOCATION DETAILS                                                                 |
|     PLOT  <from site plans>          LOCATION  <DISTRICT, LGA, KANO>              |
|     CAPTURED  2026-08-__             COMMISSIONED  2026-08-__                     |
|                                                                                   |
|  WHERE THE RECORDS WENT                                                           |
|     ... (renderRecordSummaryGroups, shared with file commissioning)               |
|                                                                                   |
|  +-----------------------------------------------------------------------------+  |
|  |  Commissioned. 41 file number(s) issued, 39 active, 5 decommissioned.        |  |
|  +-----------------------------------------------------------------------------+  |
+-----------------------------------------------------------------------------------+
```

**Before** commissioning the same sheet shows *Files To Be Generated* with the 41 holding
numbers, no Decommissioned box, and the amber strip:

```
  Nothing commissioned yet. The numbers above are holding numbers; real file
  numbers are issued at the Land step.
```

Carried files render greyed with an `UNCHANGED` tag (`duplex-summary-card.js:66-74`) — which
is how the officer sees at a glance that the two RES sources were never re-minted, and are
therefore not among the 41.

**Worth noting for a 39-portion duplex:** the stage card lists every file, so leg 3 renders 39
rows and the sheet gets long. A count badge with a collapse would help, but that is cosmetic
and not part of this change.

---

## 7. What Land sees

Unchanged. **MLS Commission New File Number** → File Type → **Duplex** → pick `DPX-2026-00XX`
→ the whole plan renders inline → **Confirm & Generate**, one click, all 41 numbers.
`?mode=land` on the duplex page stays read-only.

The printed **memo** application line would read:
*"1 Change of Purpose, 1 Merger and 39 Sub-division"* — counts and names only, with one
lettered point per leg carrying the detail.

---

## 8. What the call changed, against what is built

Today, ticking more than one source file **forces a Merger as leg 1 and locks it**:

| # | Requirement | Status |
|---|---|---|
| 1 | An auto-added Merger must be **removable** | ✓ built — confirmed, then it stays dismissed |
| 2 | **Change of Purpose** may then be leg 1 over several files | ✓ built |
| 3 | On picking CoP with >1 file, ask **Yes / No** at once | ✓ built |
| 4 | **Yes** → applies to every selected file | ✓ built — opens the table with a row per file |
| 5 | **No** → a table, one row per file, **Add More** | ✓ built |
| 6 | Columns: file number · current purpose (auto) · new purpose | ✓ built — per-file purposes end to end |
| 7 | Answered at **step 1**, not Quantities — holding numbers mint there | ✓ built |
| 8 | Then leg 2 Merger, leg 3 Sub-division | ✓ stage order was already free |

### Requirement 6 was a payload change, not a screen change

A Change of Purpose used to carry **one** new land use for the whole stage:

- `DuplexParcelUpdateController.php:227` — `'new_land_use' => 'nullable|string|max:50'`, one scalar
- `DuplexParcelUpdateController.php:244` — the stage is refused if that one value is empty
- `js.blade.php:1061` — one `<select id="dx-new-landuse">` for the panel
- `DuplexCommitService.php:428-437` — read once, applied to every target
- `DuplexCommitService.php:437` — the prefix is taken from **`$inputs[0]` only**

The payload is now a list of `{file_no, current_land_use, new_land_use}` rows, and the commit
loop reads the new land use **and the prefix** per file. `applies_to` is derived from that
list and kept in step, so everything reading the older field still agrees. A stage captured
before this change still commissions exactly as it was captured.

---

## 9. To settle before building

1. **Which direction is the change of purpose** — COM→RES, or RES→COM? (§2)
2. **`RES/RC/85/58` or `KES/RC/85/58`?** (§2)
3. **Where are `COM-RC-1982-420` and `RES-RC-1985-58`?** Neither is on dev. (§3)
4. **Should `RC` survive a change of purpose,** the way `CON` and `ST` do? (§4.2)
5. **Mixed land uses** — should the Yes/No prompt warn when the selected files do not share a
   purpose, given a merger needs them aligned?
6. **Add More beyond the selection** — may a row name a file that was not among the sources?

---

## 10. Not yet proven

**Separation** and **Extension** have still never been run end to end (main plan §9.8). A
CoP-first duplex does not change that.

---

## 11. As built (2026-08-24)

### Files changed

| Layer | Path | What |
|---|---|---|
| Land-use rule | `app/Support/FileNumberLandUse.php` | `isKangisNumber()`; `codeFor()` returns `''` for a KANGIS number instead of the number itself |
| Wizard | `resources/views/deeds/parcel_update/duplex/partials/wizard.blade.php` | the three-column card on step 1 |
| Wizard JS | `resources/views/deeds/parcel_update/duplex/js.blade.php` | Merger removable, the Yes/No question, the table, the step-3 read-back, the same KANGIS guard |
| Controller | `…/DuplexParcelUpdateController.php` | `cop_rows` on store and saveStage; first-leg CoP carried-file branch; land-use fallback |
| Commit | `app/Services/DuplexCommitService.php` | per-file new land use **and per-file prefix**; `landUseFor()` parsed through the shared rule |
| Stage model | `app/Models/DuplexParcelUpdateStage.php` | `copRows()`, `newLandUseLabel()`, `hasMixedNewLandUses()` — one rule, five readers |
| Summary | `app/Services/DuplexSummaryService.php` | stage label follows the same rule |
| Sheets | `print/conveyance.blade.php`, `print/recommendation.blade.php`, `print/application.blade.php`, `commission.blade.php` | all read the shared rule |

### Three defects found while building, beyond the requirement

1. **A first-leg Change of Purpose retired files it never touched.** The carried-file branch
   in `saveStage` was guarded on `$previous`, so a rank-1 CoP fell through to the generic
   branch, minted a holding number for *every* source file and marked all of them for
   decommissioning. It only ever ran at rank 2+, so nothing had exercised it.

2. **`landUseFor()` read `RES-RC-2004-6` as land use `RES-RC`.** It took the first two
   segments whenever the second was non-numeric — right for `CON-RES-…`, wrong for the 4,498
   `RC` files, and it would have allocated a merger into a series that does not exist.

3. **The conveyance and the memo printed the wrong "from" purpose.** Both read the *duplex's*
   land use, which comes from the first source file — very often not the file being changed.
   On this application it printed **"from residential to residential"**. Both now read the
   changing file's own current purpose, and where the files disagree they name each file
   rather than stating one purpose on behalf of all of them.

### Verified

Run against the dev database; every row created was deleted by id afterwards.

- Land-use parsing: `MLKN 139`, `MLKN3235`, `KNML 8080`, `KNML08080`, `MLKNGP 12` → `''`.
  `CON-AG-1995-15` → `AG`/prefix `CON` and `ST-RES-2025-0001` → `RES`/prefix `ST` unchanged.
  `KNMLX-RES-2020-1` is **not** treated as KANGIS.
- Per-file targets resolve in four shapes: first-leg against source files; different purposes
  per file; rows captured against holding numbers matched onto the real numbers at commit;
  and a legacy single-`new_land_use` payload, which still commissions as captured.
- Per-file prefix: `CON-AG-1995-15` + COM → `CON-COM`; `RES-RC-2004-6` + COM → `COM`;
  `ST-RES-2025-1` + IND → `ST-IND`.
- `landUseFor`: `RES-RC-2004-6` → `RES` (was `RES-RC`), `CON-RES-1984-248` → `CON-RES`
  (unchanged), `MLKN 3235` → falls back to the duplex's land use.
- **Capture of the PREFAB plan end to end**: CoP(1 of 3) → Merger(3) → Sub-division(39).
  Stage 1 minted **1** holding number and carried **2** files as `role=carried`,
  `will_decommission=0` — the arithmetic in §5.
- **Capture is registry-free**: `fileNumber`, `file_indexings`, `mls_file_no`, `pra` and
  `decommissioned_files` row counts all unchanged; zero `DPX-` values in the registry.
- A KANGIS-first source list still resolves a duplex land use (falls through to the third file).
- Conveyance now prints *"change of purpose of 1 Ha from commercial to residential use"*, and
  for mixed purposes *"(COM/RC/82/420 from commercial to residential, RES/RC/85/58 from
  residential to industrial)"*. Memo: *"Change of purpose of 1 parcel of 2.5 Ha from
  commercial to residential use in favour of …"*.
- All changed PHP lints clean; all six Blade templates compile; the wizard JS parses.

### NOT verified

**No commissioning run.** Everything above is capture, payload and rendering. The commit path
was exercised only through its resolver functions, not by minting real file numbers, because
two of this application's three source files are absent from dev (§3). A full run needs those
files and the answer to §2.1 first.

**Separation and Extension** still have never been run end to end (main plan §9.8).

---

## 12. A prop_id defect found while indexing (2026-08-24)

Indexing the two Old KANGIS parents through the real path produced **the parent and its
child sharing one prop_id**, and then the child pointing at itself:

```
KNML 8080        prop_id 68246
RES-RC-2004-6    prop_id 68246   parent_prop_id 68246   <- self-parent
```

**Cause.** `FileIndexingController::determinePropIdForIndexing()` (line ~5830) passes
`related_fileno` into `PropertyIdAllocationService::allocateOrRetrievePropId()` as an
**alternate identifier**:

```php
return $this->allocatePropIdForFileIndexing(
    $primaryCandidate,
    $existingRecord->file_number ?? null,
    $validated['related_fileno'] ?? null,      // <- the child's file number
    $existingRecord->related_fileno ?? null
);
```

So indexing `KNML 8080` with `related_fileno = ["RES-RC-2004-6"]` matched the child's
existing `PropID_Master` row and reused its prop_id. `linkOnIndex()` then set the child's
`parent_prop_id` to that same value.

This is the collapse `createStandaloneNewKangisRecord()` explicitly guards against:

> *Allocate a DISTINCT prop_id for the New KANGIS file (**skip_lookup** so it never
> collapses onto the parent's/sibling's prop_id via cross-identifier matching)*

The main `store()` path has no such guard. It contradicts the Option A model, where each of
the three files carries its **own** prop_id and the children point up at a **different** one.

**Repaired for these files** — each parent given its own prop_id with `skip_lookup`, the
children repointed, `ancestral_prop_id` and the `fileNumber` mirror updated:

```
KNML 8080     147247  root        RES-RC-2004-6   68246  parent 147247
MLKN 3235     147248  root        COM-RC-1982-420  147245  parent 147248
```

`PropID_Master` was **not** contaminated — no identifiers had been written across rows.

**It is latent, not live.** A sweep of `file_indexings` for `prop_id = parent_prop_id`
returns **0 rows**, so nothing in the database carries this shape today — the two created
here were the first, and both are repaired. It bites only when someone indexes an Old KANGIS
parent naming its land file as a related file, which is exactly the flow this application
needs and evidently has not been used before.

**Not fixed in code.** The guard belongs in `determinePropIdForIndexing()` — a `skip_lookup`
for the KANGIS case, as `createStandaloneNewKangisRecord()` already does — but that path
serves every registry, so the blast radius is wider than this application. Worth deciding
separately, and worth re-running the self-parent sweep after any change.

---

## 13. Change of Purpose may run more than once (2026-08-24, second call)

> *"When an update has already been selected, it normally disappears from the selection
> list. You should create an exception for Change of Purpose. Change of Purpose must always
> remain available and should be selectable more than once."*

This application needs it twice — COM→RES on the primary file before the merger, and RES→COM
on 3 of the 39 plots after the subdivision. Every other type is still once-only.

### What changed

The schema already allowed it: `(duplex, type)` is deliberately **not** unique, only
`(duplex, rank)` is (migration line 102). Nothing in the backend needed touching. The wizard
was the constraint, and it assumed one entry per type in six places:

| Was | Now |
|---|---|
| `addTypeFromPicker` refused a duplicate type | refuses all but `change_of_purpose` |
| the picker dropped a chosen type | keeps Change of Purpose, labelled *"(again, later)"* |
| `removeTypeFromPlan(type)` filtered the plan **by type** | takes an **index** — filtering by type removed both legs at once |
| size inputs carried `data-type` | `data-idx` |
| quantity inputs carried `data-type` | `data-idx` |
| stage↔plan matched on `stage.type` | matches on **rank**, cast on both sides (sqlsrv returns rank as a string) |
| `copEntry()` found any CoP in the plan | returns the **first leg only** — the step-1 table belongs to it, because only it works on real source files |

A later Change of Purpose is captured on its own panel at step 3, on the previous stage's
holding numbers, exactly as before.

### One defect this exposed

`DuplexSummaryService` built `planned` — the "Files To Be Generated" list, captioned
*"N file number(s) will be issued at the Land step"* — from the **last stage's** files. That
is what exists at the end, not what gets issued. On this plan it read **39** where 44 numbers
are issued: the 39 the subdivision minted, less the 3 renamed, plus the 3 new ones. It now
sums every stage's new holding numbers. `totals.issued` was already correct.

### Verified

Captured end to end on the real indexed files, registry untouched, rows deleted afterwards:

```
#1 Change of Purpose  -> RES    mints  1  carries  2   (3 files on)
#2 Merger                       mints  1  carries  0   (1 file on)
#3 Subdivision                  mints 39  carries  0   (39 files on)
#4 Change of Purpose  -> COM    mints  3  carries 36   (39 files on)

planned numbers to be issued: 44
```

`fileNumber`, `file_indexings`, `mls_file_no`, `pra` and `decommissioned_files` all unchanged.

**The memo** names a repeated type once in its subject — *"CHANGE OF PURPOSE, MERGER,
SUBDIVISION"*, the way the applicant's own letter reads — while the lettered points below
carry both legs separately and in full:

- *Change of purpose of 1 parcel of 1.8 Ha **from commercial to residential** use…*
- *Change of purpose of 3 parcels of 0.14 + 0.14 + 0.14 Ha **from residential to commercial** use…*

---

## 14. Four-digit years — and a duplicate that surfaced (2026-08-24)

The registry writes RC file numbers with a **four-digit year**: of 4,499 indexed `-RC-`
files, **3,639 carry four digits and one carries two** — `KN RES-RC-88-40`, a pre-existing
oddity. I had taken the letter's `COM/RC/82/420` and `RES/RC/85/58` literally, which was
wrong.

```
COM-RC-82-420  ->  COM-RC-1982-420
RES-RC-85-58   ->  RES-RC-1985-58
RES-RC-2004-6      already a full year — untouched
```

### The rename revealed a duplicate

**`RES-RC-1985-58` already existed** — `file_indexings` 78569, `fileNumber` 61664, holder
**NASIR A.S. DANTATA**, indexed 2026-02-11, `prop_id` 67342, located in Nasarawa. The
letter's `RES/RC/85/58` is that file. What I had created under the two-digit name was a
duplicate of it.

So the correction was not a rename on that one:

- **`COM-RC-82-420` → `COM-RC-1982-420`** — a straight rename. `COM-RC-*-420` matched only
  my row, so nothing was displaced. Updated across `file_indexings.file_number`,
  `corresponding_fileno`, `fileNumber.mlsfNo`, four `PropID_Master` columns (including the
  `_norm` pair), `file_indexing_links`, and inside `MLKN 3235`'s `related_fileno` JSON.
- **`RES-RC-85-58` deleted** — `file_indexings` 166045, `fileNumber` 143777 and
  `PropID_Master` prop_id 147246 removed, and the real file's NULL
  `file_indexings.prop_id` backfilled to **67342**.

A guard refused the rename outright if the target already existed, which is what caught it.

Renamed by hand rather than through `FileIndexingController::cascadeFileNumberRename()`,
which writes the new number into `kangisFileNo` **and** `NewKANGISFileNo` unconditionally —
correct for a KANGIS file, wrong for a Lands file, and a side effect these rows should not
acquire.

### After

```
FILE NUMBER        PROP_ID  PARENT   HOLDER                  RELATED
RES-RC-2004-6       68246   147247   GREEN PALACE HOTELS     ["KN 4938"]
COM-RC-1982-420    147245   147248   PREFAB NIGERIA LIMITED  -
RES-RC-1985-58      67342   -        NASIR A.S. DANTATA      -
KNML 8080          147247   -        PREFAB NIGERIA LIMITED  ["RES-RC-2004-6"]
MLKN 3235          147248   -        PREFAB NIGERIA LIMITED  ["COM-RC-1982-420"]
```

No rows remain under either old name. Land use parses `RES` / `COM` / `RES`, all three are
found by the picker, and the duplex resolves its land use as **RES**.

---

## 15. Apply-to-all on plot sizes (2026-08-24)

Thirty-nine plots meant typing the same figure thirty-nine times. The same gesture the
batch commissioning screen uses — fill the first, copy it down — is now offered wherever a
stage asks for more than one value:

| Screen | Button |
|---|---|
| Step 2, Quantities | **Apply Plot 1 to all N** above the size grid |
| Step 3, subdivision / separation / extension | **Apply Plot 1 size to all N** · **Apply Plot 1 holder to all N** |
| Step 3, Change of Purpose | **Apply first size to all N** · **Apply first holder to all N**, sized to the selection |

Each writes straight to the inputs rather than re-rendering, so nothing being typed loses
focus, and step 2 refreshes the running-area line afterwards. An empty first box gives
"Nothing to copy" instead of blanking the rest.

**Deliberately not offered on a Merger.** Its boxes are the existing areas of different
files, which are almost never equal, and its holders default to each source file's own
registered holder (§9.2 of the main plan). Copying one value across them would be wrong data
in a single click rather than a shortcut — the one case where the officer should type each
figure.

---

## 16. The wizard no longer closes on a stray click (2026-08-24)

Reported from testing: the card was filled in, a click landed outside it, and everything
went. The backdrop carried `onclick="closeDuplexWizard()"`.

- **Wizard backdrop** — no longer closes anything. It closes by the **X**, or by finishing.
- **KAMMA modal backdrop** — same; it closes by **Cancel** or the **X**. It holds typed
  fields too.
- **View modal** — left as it was. It is read-only, so a click outside costs nothing.

The **X now asks first**, but only once something has been entered — a source file, an
update, or the applicant name. A wizard opened and closed straight away still just closes.
The wording tells the officer what is actually at stake: before the duplex row exists,
"nothing has been saved yet"; after it, only the unsaved part of the current screen.

The post-submit path calls `closeDuplexWizard(true)`, which skips the prompt — closing after
a successful save is not a discard.

There is no Escape-key handler, so that route was never a risk.

---

## 17. Holding numbers are now visible on the Change of Purpose (2026-08-24)

Reported from testing: the Change of Purpose gave no sight of its holding numbers, which is
the thing the first call singled out — *"the reason I am explaining this is because of the
holding file number for the Change of Purpose."*

They were being minted correctly and were reaching the client (`show()` eager-loads
`stageRows.files`); nothing displayed them.

| Where | Now shows |
|---|---|
| Step 1, the per-file table | A line saying each listed file is issued its own holding number when the stage is captured on step 3. They cannot exist earlier — the duplex has no reference until the plan is submitted. |
| Step 3, first-leg CoP table | A fourth column, **Holding No.**, against each changing file — indigo once assigned, "assigned on save" before |
| Step 3, later CoP cards | Each card reads `incoming → DPX-…-Hnn` under its title |
| On save | A toast naming the numbers just issued, capped at six with "and N more" |

### A mapping bug caught while building it

The obvious implementation — take the *n*th minted number for the *n*th row — is wrong.
`saveStage()` walks the **input** list and hands the next holding number to each file that
is changing, so the numbers follow **input order**, not the order the officer listed the
rows in. Proved against the server with rows entered in reverse:

```
source order : RES-RC-2004-6, COM-RC-1982-420, RES-RC-1985-58
row order    : RES-RC-1985-58, RES-RC-2004-6

server:  seq 0 RES-RC-2004-6  -> H01      seq 2 RES-RC-1985-58 -> H02

by file  (correct)   RES-RC-1985-58 -> H02   RES-RC-2004-6 -> H01
by row   (wrong)     RES-RC-1985-58 -> H01   RES-RC-2004-6 -> H02
```

`holdingByFile()` therefore rebuilds the map the way the server assigns it — walk the input
list, hand out the next number to each changing file — rather than indexing by row.

### 17.1 The number is shown before the stage is saved

"Assigned on save" was not what was asked for — the officer wants the actual number while
filling the stage in. By step 3 the duplex has its reference (`DPX-2026-0018`), and the
allocator is deterministic: the highest `-Hnn` already issued to that duplex, plus one. So
the number can be shown truthfully before it is stored.

`DuplexHoldingNumberService::previewHoldingNumbers()` now holds that computation and
`allocateHoldingNumbers()` delegates to it — **one implementation**, so a preview cannot
drift from what is actually minted. `GET duplex-parcel-update/{id}/stages/{stage}/holding-preview?count=N`
exposes it; it writes nothing.

`$excludeStageId` drops the stage's own rows from the count, because `saveStage()` clears
them before allocating — so a stage being corrected reclaims the numbers it already holds
instead of skipping ahead.

The column shows a reserved number in italic indigo ("Reserved for this file — issued when
the stage is saved") and a solid one once issued. A preview never overwrites a number that
has actually been issued.

Verified against the server:

```
preview, stage 1, before save     ["DPX-2026-0019-H01"]
actually issued on save           ["DPX-2026-0019-H01"]      match
preview again after save          ["DPX-2026-0019-H01"]      match (re-save reclaims)
preview, stage 3, 4 plots         H02, H03, H04, H05         continues past stage 1
```

---

## 18. The second Change of Purpose asks for its quantity (2026-08-24)

A first-leg Change of Purpose can name its files, because they are the real source files
sitting on the screen. A **later** one cannot: it works on the subdivision's plots, which
have no numbers yet — and at step 1 even the subdivision's own quantity has not been
entered. So the previous behaviour added it to the plan and asked nothing, leaving the stage
with no count and no way to say how many holding numbers it would mint.

Adding a Change of Purpose at any rank but the first now asks the one question that settles
that:

> *This one runs **after the Subdivision**, so it works on plots that do not have numbers
> yet. How many of those plots will **change purpose**?*  `[ 3 ]`

- Only asked when something upstream actually splits the parcel. After a Merger or an
  Extension exactly one file arrives, so the count is set to 1 and nothing is asked.
- **Cancel removes the update** rather than leaving an unanswered stage in the plan.
- The plan row then reads *"Runs fourth · **3 plots** take a new purpose"*.

### It feeds the Quantities step both ways, by construction

The prompt writes to `entry.count` — the **same property** the Quantities step reads and
writes. There is no second copy and no synchronisation step, so a figure entered here shows
up on step 2, and a correction made on step 2 is what the stage is captured with. Which
files take the new purpose is still chosen on the stage panel at step 3, from the holding
numbers the subdivision actually produced.

### One wording fix that came with it

A Change of Purpose does not shrink the file count — it renames some and passes the rest on
— so its Quantities badge reads `39 → 3`, which invites "thirty-nine plots become three".
The hint beside it now says what the number means: *"How many of the 39 take a new purpose ·
the rest keep theirs and carry on."*

Re-ran the four-leg capture afterwards: 44 issued, 39 active, registry untouched.

---

## 19. Two Change of Purpose legs no longer collide (2026-08-24)

Reported from testing: filling in "Which files change purpose?" for the first leg, then
adding the second Change of Purpose, **cleared the table**.

**Cause.** The guard asked `operatesOnSourceFiles('change_of_purpose')` — "does the plan
*start* with a Change of Purpose?" — which stays true forever once a first leg exists. So
adding a second one re-ran the *first* one's prompt, and `openCopTable()` overwrote
`cop_rows` with a fresh blank row. The question it should ask is whether the entry **just
added** is the first leg, which is `state.plan.length === 1`.

### What each leg asks for now

| | First leg | Later leg |
|---|---|---|
| Works on | the real source files, on screen | the previous stage's plots, which have no numbers yet |
| Asked at step 1 | which files, and what each becomes | **how many** plots change, and **what they become** |
| Asked at step 3 | confirm, adjust the purpose | **which** of the holding numbers |

The later leg's prompt states what the plots arrive as rather than asking — they come out of
the previous stage, and the first leg exists precisely to bring everything to one land use.
`laterCopCurrentUse()` walks back through the plan for the most recent answer, falling back
to the duplex's own land use.

The step-1 card also names its leg once a second one exists — *"Which files change purpose?
— the FIRST one, runs 1st"* — because on its own it does not say which it means.

### Carried through to the stage

`stages.*.new_land_use` / `current_land_use` are accepted by `store()` and seeded onto the
stage payload, so step 3 opens with the purpose already chosen and asks only which plots.
Verified:

```
rank 1  change_of_purpose  count=1   payload={"cop_rows":[{...,"new_land_use":"RES"}]}
rank 2  merger             count=3   payload=null
rank 3  subdivision        count=39  payload=null
rank 4  change_of_purpose  count=3   payload={"new_land_use":"COM","current_land_use":"RES"}

leg 4 newLandUseLabel() -> COM
```

### Also fixed in passing

The step-3 chip panel's **New land use** select had no `selected` handling, so a stage
reopened after saving showed "— Select —" instead of the purpose it had been given. It now
restores from the payload.

---

## 20. The later Change of Purpose asks in the right order (2026-08-24)

The count cannot come first. "Three of them" means nothing until there are thirty-nine to
take three from — and at step 1 the Subdivision has not been sized either. Adding a later
Change of Purpose now walks three cards, in the order the answers become knowable:

1. **Size the split** — *"Before this Change of Purpose can say how many plots it touches,
   the Subdivision that feeds it has to be sized. How many plots will it produce?"* `[39]`
   Written onto the **Subdivision's** own plan entry, which is the property the Quantities
   step reads — so it arrives there already filled in.
2. **Scope** — *"That gives 39 plots. Change the purpose of all 39, or only some?"*
3. **The Change of Purpose card** — how many (skipped when "all"), and what they become,
   with what they arrive as stated rather than asked.

Cancelling any card takes the update back out rather than leaving an unanswered stage in the
plan. A later CoP with no split before it still resolves to one file and goes straight to
card 3.

### Test scaffolding — remove before production

`DEMO_SOURCES` at the top of `js.blade.php` pre-loads the application's three files so a
duplex can be rebuilt in one click while this is being tested. It only fills an **empty**
picker, so it never overrides a real selection, and setting it to `[]` turns it off.

> **It is marked `>>> DELETE THIS BLOCK AND THE prefillDemoSources() CALL BEFORE
> PRODUCTION <<<` and must go before release.**

### Test data cleared

The six duplexes captured during this evening's testing (`DPX-2026-0016` … `0021`, PREFAB
and DANTATA, 311 file rows between them) were deleted. `DPX-2026-0001`–`0015` belong to
other work and were left alone, including the committed `DPX-2026-0007`. The registry was
untouched throughout — capture never writes to it, and there are no `DPX-` values in
`file_indexings`.

**Note:** deleting duplexes frees their reference numbers. `allocateDuplexId()` takes the
highest **existing** row plus one, so the next duplex will be `DPX-2026-0016` again — the
docblock's claim that ids "are never re-used, even when a duplex is deleted" only holds
while the row survives. Harmless for test data; worth knowing before deleting anything real.

---

## 21. "Nothing captured yet" on the last stage (2026-08-24)

Reported from testing, on `DPX-2026-0016`: the summary showed stage 4 as *"Nothing captured
yet"* and listed **41** files to be generated rather than 44.

**Not a defect.** Stages 1–3 were `done`; stage 4 was still `pending` with no files, its
seeded `{"new_land_use":"COM"}` intact. 1 + 1 + 39 = 41 is exactly right for three completed
stages, and it becomes 44 when the fourth is captured. The wizard had simply been closed
before the last stage was saved.

The resume path was checked and is sound — the row menu's **Continue capture** on a draft
lands on the first stage not done and hands it everything it needs:

```
lands on           rank 4, change_of_purpose
chips rendered     39   (DPX-2026-0016-H03 … H41), from the previous stage's rows
ticked by default  first 3
new purpose        COM, pre-selected from the step-1 answer
```

`carryFor()` reads the previous stage's files out of the reloaded payload rather than the
in-session `state.carry`, which is what makes resuming work at all after a reload.

### What was actually wrong: nothing said a stage was outstanding

The register showed only "draft", and *"Nothing captured yet"* sat three panels up the
summary sheet, easy to scroll past. The sheet now carries a red band naming the stage and
the action that finishes it:

> **1 stage still to capture:** Change of Purpose (runs 4). Until then this duplex stays a
> draft and cannot be approved — reopen it from the register with **Continue capture**.

Shown only before commissioning, and only while something is outstanding.

---

## 22. Series labels, no size on a rename, square metres (2026-08-25)

### The later Change of Purpose is named

`Change of Purpose (2nd Series)` — in the dropdown, the plan list, the order rail, the
stage track, the stage header and the Done list. Two rows both reading "Change of Purpose"
was the most confusing thing on the screen once the plan carried both. The numbering is
computed from position, so a third would read *(3rd Series)*.

### A Change of Purpose records no plot size

It renames a parcel and leaves its area alone, so asking for a size invited a figure that
contradicted the parcel the plot came from. Dropped from step 2's size grid and from the
stage panel's cards, along with the size half of the copy-down toolbar. The holder is still
asked for — a renamed parcel still has one.

`refreshSizeNotes()` was unaffected: its Change-of-Purpose branch never touched the carried
area, so the merger → subdivision balance still works.

### Areas are in SQUARE METRES

Confirmed as a unit change, not a format change. Every one of the registry's existing
`plot_size` values is an area — `0.0276HA`, `0.112 ACRES`, `1.586 HA` — and **not one** of
133,000+ rows uses the `135.01m x 40.01m` dimension form, which belongs to a survey plan
rather than this field. Keeping a single number means the balance check, the totals and the
`decimal(18,4)` column all stand: **no migration needed**.

- `area()` replaces `ha()`, formatting `1410` as `1,410 m²`.
- Labels read *Plot sizes (m²)* / *Size (m²)*; inputs step by whole metres.
- The balance tolerance was `±0.009 Ha` — **90 m²**, far too loose. It is now `±0.5 m²`.
- Both printed sheets carry thousands separators: *"measuring 25,000 + 18,000 + 12,000 m²"*.

### Demo scaffold now loads the whole application

`DEMO` in `js.blade.php` opens the wizard with the PREFAB application complete — three
files, applicant, plot 10, NASARAWA DISTRICT / Nasarawa, and all four legs with counts,
purposes and sizes (25,000 + 18,000 + 12,000 m² merging; 39 × 1,410 m²). It fills only an
**empty** wizard.

> **Marked `>>> DELETE THIS BLOCK AND THE prefillDemoDuplex() CALL BEFORE PRODUCTION <<<`.**

Verified end to end: four stages saved, status `captured`, **44 planned**, CoP stages
carrying no sizes.

### Worth a decision later

The memo lists every plot size individually, so a 39-plot subdivision prints
*"of 1,410 + 1,410 + …"* thirty-nine times. Fine at three plots, unwieldy at thirty-nine —
a count plus a total would read better, but §9.10 deliberately rejected totals on a legal
instrument, so this needs the Ministry's call rather than mine.
