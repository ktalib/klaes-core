# PropID Remediation — Progress Tracker

**Started:** 2026-09-25 · **Last updated:** 2026-09-26
**Connection:** `sqlsrv` (the tables live on SQL Server; artisan's migration ledger is on MySQL)

## Why this exists

`file_indexings.prop_id` is used across KLAES to find a file's relatives, but it is not
unique and nothing enforces that it should be. A bulk import stamped thousands of rows
with a **row ordinal** instead of the real property id, so unrelated parcels share a
prop_id and any join on it can pull a stranger's file into the result.

This was found when the Virtual File Explorer showed `CON-RES-1994-611`
(ABDULLAHI INUSA KURNA, Kurnar Qrts) as the parent of two files belonging to
**MR. PETER EFUGHU** (Plot 36, Airport Road) — both sides were sitting on prop_id 16072.

The VFS itself no longer reads prop_id (see [Related files](#related-files)), so nothing
in the explorer is waiting on this work.

## THE RULE (confirmed 2026-09-27)

**Every distinct file number gets its own prop_id.** Sharing is a violation.

This is the system's own documented model — `KangisParentLinkService` states that each
of the three KANGIS files "carries its OWN prop_id", and the relationship between them
is expressed through `parent_prop_id`, not by duplicating the identity.

**Two exemptions**, both documented in the code that creates them:
- **OP/ToT pairs** — `ReconcileIndexingPropIds`: "shared prop_id is legit"
- **ST mothers** adopting the old land file's id — `BackfillStPropIds`

**Files with no prop_id get a new one minted.**

### What this corrected

An earlier version of the audit treated "one parcel, several files" and "linked in the
register" as legitimate, and passed **905 + 289 clusters that are in fact violations**.
That was my assumption, not the system's design. Corrected 2026-09-27.

### Scale

| | files |
|---|---|
| No prop_id — need minting | 115,525 |
| Excess holders on a shared id | ~4,661 |
| **Total needing a new prop_id** | **~120,186** |

Note `PropID_Master` has **12,462 rows carrying more than one file number**, each handing
one prop_id to every number it lists. That is the mechanism generating the sharing, so
this is not only a `file_indexings` fix.

**Rollout decision: staged batches, verified between.** Not one run. 115,525 files
switching Legal Search expansion on simultaneously is the risk being managed.

## Step 4c — downstream sync ✅ (2026-09-28)

`propid:mint` corrected `file_indexings` only. The transaction tables still held the OLD
shared prop_id, and Legal Search searches *those* — so the contamination survived the
repair until this step.

```
php artisan propid:sync-downstream --snapshot   # before-image, 8,984 rows
php artisan propid:sync-downstream              # dry-run
php artisan propid:sync-downstream --apply      # run 20260928-085718
php artisan propid:sync-downstream --rollback   # undo
```

**525 rows synced** — 304 `CofO_staging` · 188 `pra` · 29 `file_history_staging` ·
2 `instrument_capture` · 2 `deed_registrations` · 0 `pic`. Backup: `downstream_propid_backup`.

### The proof

`RES-2005-446` (plot 29) was the worked example that pulled 23 unrelated plots onto its
timeline:

| | before | after |
|---|---|---|
| timeline rows | 27 | **4** |
| distinct files on it | 25 | **2** |

The two remaining are `RES-2005-446` itself and `CON-AG-1992-17`, which is its genuine
`related_fileno`. `pra` rows still on 7358: exactly those two.

### An estimate I got wrong

I first reported this gap as **~9,000 rows**. That counted every row sitting on a
re-assigned id — but most belong to the **keeper**, the file that legitimately kept the
id, and are correct where they are. Only a *moved* file's rows need to follow it. Of
3,621 moved files, only ~246 appear in `pra` at all; the rest have no transactions.

### The matching rule that makes it safe

A row is touched only when BOTH hold: it is still on the old prop_id, AND the file number
appears in one of that table's own number columns (`mlsFNo`, `kangisFileNo`,
`NewKANGISFileno`, `fileno`, `temp_fileno`). Matching on prop_id alone would drag the
keeper's transactions along — the exact bug being fixed.

## Ordering principle

**Fix what is actively wrong before activating what is dormant.**

A NULL prop_id is *inert* — `LegalSearchService` skips prop_id expansion entirely for that
file. A *wrong* prop_id actively contaminates timelines. That is why step 8 is last
despite being the largest number, and why step 4 matters more than its count suggests.

---

## Progress

| # | Step | Kind | Status |
|---|------|------|--------|
| 0 | Audit + reconcile against PropID_Master | write, reversible | ✅ **done** 2026-09-26 |
| 1 | NULL the squatters | write, reversible | ✅ **done** 2026-09-26 |
| 2 | Re-audit and confirm the drop | read-only | ✅ **done** 2026-09-26 |
| 3 | Fix the audit classifier (plot/district) | code only | ✅ **done** 2026-09-26 |
| 4a | duplicates — every file number its own id | write, reversible | ✅ **done** 2026-09-28 · 3,621 minted |
| 4c | sync downstream tables (525 rows) | write, reversible | ✅ **done** 2026-09-28 |
| 4b | 84 clusters needing a rule | decision needed | ⬜ **highest value** |
| 5 | dangling parent_prop_id — **1,927 → 863** | write, split first | ⬜ not started |
| 6a | KANGIS prop_id by **lookup** (2,265) | write, reversible | ✅ **done** 2026-09-26 |
| 6b | KANGIS prop_id by **minting** (2,442) | write, new identities | ⬜ **separate decision** |
| 7 | 15,356 "no master" rows | decision needed | ⬜ not started |
| 8 | 116,820 NULL prop_ids | write, LAST, in slices | ⬜ blocked on 1–7 |
| 9 | Guard rails (constraints + checks) | write, schema | ⬜ not started |

---

## Step 0 — Audit + reconcile ✅

Ran `propid:audit-indexing` (new, read-only), then `propid:reconcile-indexing --apply`.

| Metric | Before | After |
|---|---|---|
| Agrees with PropID_Master | 25,838 | **36,046** |
| Disagrees | 11,432 | **1,224** |

Run `20260926-050031` · 10,216 repaired · backup in `file_indexings_propid_backup`
· undo with `propid:reconcile-indexing --rollback`

**Guards held.** 998 OP/ToT skipped · 222 parent-references skipped · 0 multi-valued
lineage touched · 0 errors. 400 skipped rows checked individually — none moved.

**Regression introduced:** see step 1.

## Step 1 — NULL the squatters ✅

The reconciler moved a file onto its canonical prop_id, but a **second file was already
squatting on that id** with a stale ordinal. The squatter has no `PropID_Master` row, so
it landed in the "skip no-master" bucket (15,356 rows) and was never examined.

```
prop_id 11977
  CON-RES-1983-423   plot 419, Kawaji    was 21423 → repaired to 11977   master: 11977 ✓
  CON-AG-1984-184    FARMLAND, Yelwa     unchanged, squatting on 11977   master: no row
```

**878 rows** are in that state. Measured across all 927 genuinely-distinct-parcel
collisions: 878 created by the run (95%), 49 pre-existing (5%).

**Fix:** set the squatter's prop_id to NULL. It holds an id that provably belongs to
another parcel and has no authority to repair toward, so NULL converts "wrong and
potentially contaminating" into "absent and harmless".

**Do NOT roll back step 0 to fix this** — that restores 10,216 known-wrong ordinals.

### Dry-run result — 2026-09-26

```
php artisan propid:clear-squatters            # read-only, default
php artisan propid:clear-squatters --apply    # writes, backed up
php artisan propid:clear-squatters --rollback # undo
```

| clusters | same parcel | no repaired row | **to clear** | skip parent-ref | skip OP/ToT-ST | skip has-master |
|---|---|---|---|---|---|---|
| 4,937 | 3,717 | 233 | **909** | 8 | 0 | 87 |

Report: `storage/app/propid-remediation/squatters-dryrun-20260926-182537.csv`

What each column means:
- **same parcel** (3,717) — one parcel across several files, correct, untouched
- **no repaired row** (233) — pre-existing bulk-allocation clusters, that is step 4
- **to clear** (909) — the squatters
- **skip has-master** (87) — has its own PropID_Master row, so it needs the reconciler, not NULL
- **skip parent-ref** (8) — load-bearing as someone's parent, left for a human

Backup goes to `file_indexings_squatter_backup`. The write is conditional
(`where prop_id = <the value we read>`), so a concurrent edit is left alone rather than
silently overwritten.

### Applied — 2026-09-26

Run `20260926-183355` · **909 rows cleared** · backup in `file_indexings_squatter_backup`
· undo with `propid:clear-squatters --rollback`

Verified:
- 909 backed up, and **0** of them still hold a prop_id
- **0** of step 0's repairs were reverted or nulled (400 sampled)
- agreement with PropID_Master unchanged at 36,052 / 1,224 — clearing touched only rows
  that had no master row, exactly as intended
- shared prop_id values 4,937 → **4,039**
- VFS regression suite green

## Step 2 — Re-audit ✅

| Measure | After step 0 | After step 1 |
|---|---|---|
| Collisions reported by the audit | 3,013 | **2,135** |
| — genuinely distinct parcels | 927 | **49** |
| — same parcel, classifier too strict | 2,005 | 2,005 |
| — undecidable, no plot recorded | 81 | 81 |

**The regression is fully removed.** The 878 collisions the reconcile created are gone;
the 49 that remain are the pre-existing bulk-allocation clusters, which are step 4.

The 2,005 "same parcel" figure is a classifier artefact, not a data problem — step 3
fixes the measurement so the number means something.

## Step 3 — Fix the audit classifier ✅

The audit was reporting 2,135 collisions when only a fraction were real. It called any
shared prop_id a collision unless the members were linked in the file register — but one
property is routinely indexed three times (Old KANGIS + Land + New KANGIS) and those
three *should* share a prop_id. Register links are sparse, so correct clusters were
drowning the wrong ones.

Now classified by whether the members are actually the same parcel:

> **Superseded 2026-09-27** by THE RULE above: `legitimate-same-parcel` and
> `legitimate-linked` are now counted as violations. Current totals: **3,752 violations**
> (195 different parcels · 2,217 review · 146 undecidable · rest same-parcel/linked) and
> **493 allowed** (OP/ToT and ST only).

| verdict | count | meaning |
|---|---|---|
| **collision** | **195** | more than one real plot — confirmed wrong |
| review-district-mismatch | 2,035 | district disagrees, plot data too thin to be sure |
| review-weak-plot | 15 | only plot value is a non-identifying descriptor |
| legitimate-same-parcel | 882 | one parcel, several files |
| legitimate-optot | 480 | OP/ToT pair, shares by design |
| legitimate-linked | 287 | linked in the register |
| undecidable | 145 | no plot recorded anywhere |

**Headline number: 195 confirmed collisions**, down from a misleading 2,135.

Two design decisions worth keeping:

- **Plot placeholders are excluded.** `PIECE OF LAND`, `PLOT`, `N/A` and similar identify
  nothing; treating them as a plot would make every unsurveyed parcel look like the same
  parcel. `PIECE OF LAND` (40,901 files) and `A PIECE OF LAND` (2,038) are normalised
  together — they are the same thing.
- **`FARMLAND` is NOT a placeholder** (domain correction, 2026-09-26). It is a real
  descriptor — 1,171 files, 97% of them land_use `AGRICULTURAL` — so it counts as a
  *difference* against a numbered plot: `FARMLAND | 23 | 23 | 23` is a collision, and
  was being missed. But it does not identify *which* farm, so a cluster whose only plot
  value is a descriptor like this goes to `review-weak-plot` rather than passing as one
  parcel. **If other plot_number values behave this way, add them to `WEAK_PLOT_VALUES`.**
- **District is a review signal, not a verdict.** The column holds spelling variants for
  one place (`YAN MATA` / `YANMATA` / `YAMMATA`). An earlier version treated a mismatch
  as proof of collision and reclassified 2,234 clusters — wrong. It is normalised
  (punctuation and spacing stripped) and now only routes a cluster to review.

Validated against known cases: all four clusters proven to contaminate Legal Search
(7358, 10228, 54544, 160531) are classified `collision`. prop_id 16072 — the cluster
that started this whole investigation — lands in **review**, not `legitimate`, because
one member's plot is a placeholder so the data cannot prove it automatically. That is the
honest answer rather than a false pass.

## Step 4 — Analysis (2026-09-27)

**195 collision clusters · 608 files.** My earlier description of these as "bulk
allocations" was wrong — that was generalised from the largest clusters. The real shape:

| | |
|---|---|
| 2 files | 98 clusters |
| 3–5 files | 86 |
| 6–10 | 6 |
| 11–25 | 3 |
| 26+ | 2 |

- **164 clusters have SEVERAL holders** — genuine mix-ups between different people
- **31 have one holder** — the bulk allocations
- **103 of 120 sampled have PRA transactions**, so ~86% can actually contaminate a timeline

### Who legitimately owns the id?

Checking each member against PropID_Master:

| | clusters | |
|---|---|---|
| **exactly ONE member authoritative** | **111** | mechanically fixable — 212 rows to clear |
| NO member authoritative | 61 | everyone is squatting; needs a rule |
| SEVERAL members authoritative | 12 | PropID_Master itself is ambiguous |
| ALL members authoritative | 11 | master says they all own it — likely genuine, my classifier over-flagged |

**So 111 of 195 (57%) resolve by the same rule step 1 used:** keep the member
PropID_Master names, NULL the others, which hold an id that demonstrably is not theirs.

### The consequence to be explicit about

For a **mix-up**, clearing is a straight repair — those files never owned the id.

For a **bulk allocation** it is only *containment*. `prop_id 172830` is one holder across
12 plots; keeping `CON-RES-2022-683` and clearing 11 makes them inert and stops the
contamination, but those 11 are real parcels that still need identities of their own.
They would join the minting queue (step 6b). Clearing is strictly better than leaving
them wrong, but it is not a cure.

### What 4b still needs a human for

- **61 clusters where nobody is authoritative** — no PropID_Master row anywhere, so there
  is no fact to repair toward. Rule needed: clear all of them, or mint?
- **12 where several members are authoritative** — PropID_Master maps several file
  numbers to one prop_id. That is a registry problem, not a `file_indexings` problem.
- **11 where all are authoritative** — probably correct and my classifier is over-strict.
  Worth inspecting before touching.

## Step 6a — KANGIS prop_id by lookup ✅

**Why:** the KANGIS model makes the Old KANGIS file the parent, and the Land and New
KANGIS files point at its prop_id. `KangisParentLinkService` cannot write that link when
the parent has no prop_id — it returns null and gives up silently. Of 400 sampled, **267
are already named as another file's related file**, so children really are waiting.

**Split, because the two halves are different risks:**

| | count | |
|---|---|---|
| resolvable from PropID_Master — **lookup** | 2,350 | step 6a, this command |
| would need a **new id minted** | 2,442 | step 6b, deferred |

Minting creates a new identity, writes to `PropID_Master`, and can give a parcel a
second id if it already exists under another spelling. This command **never mints**.

```
php artisan propid:backfill-kangis            # read-only, default
php artisan propid:backfill-kangis --apply    # writes, backed up
php artisan propid:backfill-kangis --rollback # undo
```

### Dry-run — 2026-09-26

| examined | to fill | skip no-master (6b) | skip ambiguous | **skip collision** | errors |
|---|---|---|---|---|---|
| 4,794 | **2,265** | 2,442 | 2 | **85** | 0 |

**The collision guard is the lesson from step 0.** That run repaired toward
PropID_Master without checking whether a second file was already squatting on the target
id, and created 878 new cross-parcel collisions that took a whole extra pass to clear.
This command checks first: 85 rows would have landed on an id already held by a file on a
different plot, and were refused.

### Applied — 2026-09-26

Run `20260926-231235` · **2,265 filled** · backup in `file_indexings_kangis_propid_backup`
· undo with `propid:backfill-kangis --rollback`

| Measure | Before | After |
|---|---|---|
| **Collisions** | 195 | **195 — unchanged** |
| **Dangling parent pointers** | 1,927 | **863** |
| KANGIS files with no prop_id | 4,794 | 2,529 (the 6b population) |
| Agrees with PropID_Master | 36,052 | 37,536 |
| Disagrees | 1,224 | 1,224 |

**Zero new collisions.** That is the headline: step 0 created 878 doing the same kind of
work without a guard; this created none. The 85 rows the guard refused were the ones that
would have caused them.

**Dangling pointers more than halved** — 1,064 previously-dangling `parent_prop_id`
values now resolve, because their KANGIS parent finally has an id to point at. That is
the whole reason this step exists, and it shrinks step 5 before it starts.

Verified:
- 2,265 backed up; **0** still empty, **0** holding a different value
- step 1's cleared rows still NULL (300 sampled)
- Legal Search spot-check on newly-filled files: every one is alone on its prop_id, and
  **no foreign parcel appeared** on any timeline
- VFS regression suite green

### Known behaviour change

A NULL prop_id makes `LegalSearchService` skip prop_id expansion entirely. Filling it
switches expansion **on** for those 2,265 files. That is the same concern that puts step
8 last. Accepted here because the scale is small, the values are authoritative rather
than invented, and the guard refuses any contested id — but **verify Legal Search on a
sample after applying.**

## Step 8 — the NULL population ⬜ LAST

116,820 rows have no prop_id and are therefore inert. Filling them switches expansion
**on** for 116k files at once — a behaviour change, not a repair. The repo's own script
refuses to do it blind ([see below](#related-files)).

- 62,824 can be **resolved** from PropID_Master (a lookup) — do these first, in batches
- **53,996 would need a new id minted** — minting for a parcel that already exists under
  another spelling creates a second identity for one parcel

---

## Measured baseline

| Fact | Count |
|---|---|
| `file_indexings` rows | 169,901 |
| — with no prop_id (inert) | 116,820 |
| — with a prop_id (active) | 53,081 |
| PropID_Master rows | 180,564 |
| PropID_Master prop_ids used by >1 row | **0** (it is internally clean) |
| prop_id values shared by >1 file | 4,937 |
| — same parcel, several files (correct) | 2,005 (67%) |
| — genuinely distinct parcels (wrong) | **927** (31%) |
| — undecidable, no plot recorded | 81 |
| Dangling `parent_prop_id` | 1,927 |
| KANGIS files with no prop_id | 4,789 |

---

## Related files

### Commands
| Path | Purpose |
|---|---|
| `app/Console/Commands/AuditIndexingPropIds.php` | **new** — read-only audit: shared ids, dangling parents, missing KANGIS ids |
| `app/Console/Commands/ReconcileIndexingPropIds.php` | repairs prop_id against PropID_Master · dry-run default · `--apply` · `--rollback` |
| `app/Console/Commands/BackfillAncestralPropIds.php` | recomputes `ancestral_prop_id` · idempotent |
| `app/Console/Commands/LinkKangisParentPropIds.php` | fills missing KANGIS parent links · forward-only |
| `app/Console/Commands/BackfillKangisRecertLinks.php` | creates missing recertification edges |
| `app/Console/Commands/BackfillRelatedFileNumbers.php` | replays `related_fileno` JSON into the register |

### Services
| Path | Note |
|---|---|
| `app/Services/PropertyIdAllocationService.php` | the only minter · MAX+1 over `PropID_Master` under a lock |
| `app/Services/PropIdLineageService.php` | **assumes prop_id is unique** — caches keyed by prop_id, so duplicates poison the cache |
| `app/Services/KangisParentLinkService.php` | writes `parent_prop_id` with **no existence check** — source of the dangling pointers |
| `app/Services/LegalSearchService.php` | seeds expansion from prop_id; `isForeignIndexingPropId` is the contamination guard |
| `app/Services/Vfs/VfsFolioPresenter.php` | **no longer reads prop_id** — uses the file register instead |

### Prior analysis (read before acting)
| Path | Note |
|---|---|
| `database/sql/2026_08_10_fix_file_indexings_prop_id_conflicts.sql` | the original diagnosis; section 6 refuses the NULL backfill |
| `database/sql/2026_08_10_fix_prop_id_con_ag_1982_172.sql` | 4-row surgical version, "mirror bleed" analysis |
| `docs/plans/PROP_ID_ALLOCATION_AUDIT_AND_SOLUTION.md` | ⚠️ partly stale — its "prop_id removed from file_indexings" premise was reverted |
| `docs/plans/PROP_ID_ALLOCATION_MASTER_PLAN.md` | ⚠️ item 1 is now false |
| `docs/reports/PROP_ID_AGENT_GOVERNANCE.md` | the actual allocation rules |

### Reports and backups
| Path | Note |
|---|---|
| `storage/app/propid-remediation/` | every audit and reconcile CSV |
| `file_indexings_propid_backup` (sqlsrv) | step 0 backup · run `20260926-050031` · 10,216 rows |
| `vw_prop_id_conflicts` (sqlsrv view) | detects the **inverse** (file → several prop_ids); surfaced at `/propid-master` |

### Constraints that do NOT exist
- no unique index on `file_indexings.prop_id`
- no FK from `file_indexings.prop_id` → `PropID_Master.prop_id`
- no write-time check preventing two unrelated files taking one id
- no referential check that `parent_prop_id` points at a real prop_id

### Legitimate sharing — never "fix" these
- **OP ↔ ToT pairs** share one prop_id by design
- **ST mothers** adopt the old land file's prop_id
- **KANGIS three-file sets** — one parcel indexed as Old KANGIS + Land + New KANGIS

---

## Migration convention

Schema changes (step 9 only) ship as a **pair**, because artisan's ledger is on MySQL
while the tables are on SQL Server:

```
database/sql/<name>.sql               → run against SQL Server
database/sql/<name>_ledger.mysql.sql  → run against MySQL
```

Never verify a schema change by asking the ledger — query `INFORMATION_SCHEMA` / `sys.columns`.
