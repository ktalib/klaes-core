# File identity and syncing: damage, risk and plan

**Date:** 19 Nov 2026
**Status:** proposal, nothing built yet
**Scope:** every module that copies file details between tables. That covers Land FC, OSS FC, indexing, cadastral, survey, ST, SLTR, land recommendation, legal search, deeds, file tracking and bills.

---

## 1. Short answer

**The concern is right.** Records across KLAES are linked to each other by the **file number text** (for example `RES-2026-1604`). No stable ID ties them together. When one screen corrects a title, plot, TP No. or LGA, it finds the "same file" elsewhere by matching that text. Three things break that:

- a file number that is **shared** by two different files;
- a file number that is **changed** or corrected;
- a file number that is **stored in a different column**, such as KANGIS, temp or ST.

**The damage so far is limited, and most of it can be repaired.** The core tables mostly line up today. Section 3 measures the damage and gives the risk of it growing as more modules sync.

**Recommendation:** give every file a permanent **File ID** that never changes, even when its number does. Then move syncing onto that ID in phases, starting with a quick guard that stops the most dangerous case today. Details are in sections 5 and 6.

---

## 2. Why the file number is a poor key

A file number behaves like a name, not an ID:

| What happens | Example in KLAES | Effect on a sync keyed on the file number |
|---|---|---|
| The same number is given to two files | KANGIS numbers reused (`KNML 642`, `KNGP 163`, `COM-1991-46`) | An edit on one file is written onto the other |
| A number is corrected or replaced | indexing edits that change the number, successor files, re-grants, KANGIS → new number | The sync finds nothing under the new number, or still updates the old one |
| One file has several numbers in different columns | `mlsfNo`, `kangisFileNo`, `NewKANGISFileNo`, `temp_file_no`, `st_file_no`, correspondence no. | A sync that checks one column misses the copies under the others |
| A file has no proper number yet | (T) files indexed under `temp_file_no` only | Nothing to match on at all |
| Spelling and spacing | `RES-2026-1601` vs `RES-2026-1601 ` | Usually caught by trimming, but every query must remember to trim |

An ID has none of these problems. It is given once, never reused, and never edited.

---

## 3. Damage measured on production (read-only, 19 Nov 2026)

### 3.1 How widespread the file-number linking is

- **186 tables** on SQL Server carry a file-number column.
- **113 of them carry no ID at all** that could link them instead: no tracking ID, Prop ID, indexing ID or Land FC ID.

| Module | Tables with a file number | …with no ID |
|---|---|---|
| Cadastral | 17 | 11 |
| Survey | 17 | 14 |
| Deeds / instruments | 9 | 5 |
| Indexing / EDMS | 13 | 3 |
| Bills / payments | 7 | 2 |
| Recommendation / Planning | 5 | 4 |
| ST / Sectional Title | 5 | 4 |
| Legal search | 5 | 3 |
| File tracking / movement | 4 | 2 |
| SLTR | 3 | 2 |
| OSS | 2 | 2 |

The OSS application table has **no ID column of any kind**. It can only be found by file number.

### 3.2 The IDs that already exist, and whether we can trust them

| ID | Where it is | Coverage | Can it be the key? |
|---|---|---|---|
| **Land FC row ID** (`fileNumber.id`) | Land FC | every row | **No.** One file can have several Land FC rows (same number in different columns), so it identifies a row, not a file. |
| **Tracking ID** (`TRK-…`) | Land FC 98.5%, indexing 98.8%, MLS 100%, cadastral cases 100% | high | **Not as it is.** 971 tracking IDs are shared by more than one file, 959 of them by **different** file numbers. One tracking ID (`TRK-EDC8ADB9-EF038`) is on **49 different files** (RES-2026-1 … RES-2026-635). Batch commissioning appears to give the whole batch one tracking ID. |
| **Indexing link** (`file_indexings.file_number_id`) | indexing | **984 of 197,335** rows (0.5%) | **No.** Almost never filled, and 55 of the filled ones point at a Land FC row with a different number. |
| **Prop ID** | PRA (transactions) 99.7% | high | **Not for files.** It identifies a *property*, and a Transfer of Title deliberately gets a new one. The memory notes also record 2,174 files carrying another file's Prop ID. |
| **Cadastral case → indexing ID** | cadastral cases | 100% | Yes, for cadastral only. This is the one place an ID link was done right. |

### 3.3 Records that are already out of step

| Problem | Count | What it means |
|---|---|---|
| File numbers on more than one live Land FC row | **21 numbers / 68 rows** | **14 of these are different files** (different titles). A sync on these numbers can overwrite the wrong file. |
| Indexing records with no matching Land FC record | **830** | Edits on either side never meet. These are mostly numbers that changed or were typed differently. |
| Indexing records whose tracking ID differs from their Land FC record | **83** | Tracking-based joins disagree with number-based joins. |
| Indexing records with no file number, only a temp number | **221** | Invisible to every file-number sync. |
| Indexing edits that changed the file number (audit log) | **61** | Each one can leave copies behind under the old number. |
| Land FC rows pointing to a successor file | **174** | Copies may still be updated under the old number. |
| File numbers on more than one OSS application row | **19** | Fixed today so a title change renames all of them. Which row is the "real" application is unclear. |
| OSS applications with no matching Land FC record | **8** | Orphans. |
| File numbers on more than one customer row | **5** | Same risk as Land FC duplicates, on a small scale. |

### 3.4 What this has caused in practice (seen today)

- RES-2026-1601 and RES-2026-1607: the OP edit renamed the file to the OP holder, and the cadastral case kept stale copies. These were repaired by hand.
- RES-2026-1604: the TP No. typed in Capture OP was never sent. That one was a separate bug, now fixed. It shows how hard it is to see where a value lives when every module keeps its own copy.
- Indexing kept an old plot because the OSS edit wrote to a column that doesn't exist. Silent failures like this go unnoticed because nothing checks the records against one identity.

**The syncs added today** (`FileTitleSync`, `FileTpSync`, `CadastralCaseSync`) also match on the file number. They copy the same behaviour as the older code, including the 21-duplicate risk. They are an improvement for normal files, but section 5, step 1 must add a guard to them.

---

## 4. Risk if nothing changes

| Risk | Likelihood | Impact | Notes |
|---|---|---|---|
| An edit on one file overwrites another file that shares its number | Low per edit, certain over time | **High.** Wrong owner name, plot or TP on a legal record. | 14 known shared numbers today, and KANGIS reuse keeps adding more. |
| Corrections don't reach every copy | **High** | Medium | Already happening: 830 + 221 indexing records outside the sync, plus stale cadastral and FMC copies. |
| Each new module adds its own number-based copy | **High** | Medium, and growing | Survey, ST, SLTR and recommendation tables mostly have no ID. |
| Renaming or re-granting a file leaves orphans | Medium | Medium | 61 number changes and 174 successor links so far. |
| Fixes by hand become the normal way of working | **High** | Staff time and trust | As seen today on RES-2026-1601 / 1604 / 1607. |
| Slow or blocking queries | Medium | Medium | Matching across 3–6 text columns with OR is slow and can block. The memory notes already record `fileNumber` scans blocking on locks. |

---

## 5. Recommendation

### Option chosen: a permanent **File ID** with a register of every number the file has had

1. **A `files` register.** One row per real file, holding a permanent `file_id` that is never reused or edited.
2. **A `file_numbers_alias` table.** Every number a file is known by: MLS, KANGIS, new KANGIS, temp, ST, SLTR, correspondence, and old numbers after a correction. Each alias has a type and dates. A number change becomes *adding an alias*, not renaming copies everywhere.
3. **A `file_id` column added** (additive, nullable) to the source tables first:
   - Land FC (`fileNumber`)
   - indexing
   - the MLS record (`mls_file_no`)
   - OSS applications
   - PRA (beside `prop_id`)
   - cadastral cases

   The modules' own tables follow later.
4. **Sync by `file_id`**, with Prop ID kept for property and transaction lineage. File number text becomes a *display value*, still searchable, but never the join.

### Why not the alternatives

| Option | Why not |
|---|---|
| Keep file number, add guards only | Stops the worst case, but renames and multi-column numbers stay broken. Still worth doing first (step 1). |
| Use tracking ID | 959 tracking IDs already span different files. It would need cleaning and a uniqueness rule, which is about the same work as a proper File ID, but with a value staff already see and print. |
| Use the Land FC row ID | One file has several Land FC rows, and indexing-only or OSS-only files may have none. |
| Use Prop ID | It is the *property* identity and changes on every Transfer of Title by design. |

### What does not change

- File numbers stay exactly as they are on screens, prints, QR codes and search.
- Prop ID stays the property and transaction key.
- No existing column is removed or renamed. All changes are additive, in line with the production rules.

---

## 6. Plan, in phases

Each phase is built and tested in **klas-dev** first and released to production as a separate approved step (CLAUDE.md). Every data step is: backup → rollback test → apply → verify.

### Phase 0: Decide (this document)
- Approve the File ID approach, or pick another option.
- Agree the policy for **ambiguous numbers**: when a number belongs to two files, edits must stop and ask, never guess.

### Phase 1: Stop the bleeding (small, about 1 day)
- Add one **resolver**, `FileIdentity::resolve(number)`, that every sync calls. It returns *one* file, or "ambiguous", or "not found". It checks every number column and trims.
- `FileTitleSync`, `FileTpSync`, `CadastralCaseSync` and the older propagation code (MLS page, Land FC edit, indexing edit, FFR sync) **skip and log** an ambiguous number instead of writing to every match. The screen shows "This number belongs to more than one file — correct it in each record."
- A daily **report** (artisan command) listing shared numbers, orphans, shared tracking IDs and stale copies, so problems are seen, not stumbled on.

### Phase 2: Build the register (about 2–3 days, plus review)
- Additive migration: `files`, `file_numbers_alias`.
- **Backfill as a report first** (CSV, no writes). Every Land FC, indexing, MLS and OSS record is grouped into files by tracking ID **plus** number **plus** title checks.
- Anything that doesn't group cleanly goes to a **review list**, not into the register. Today's figures suggest roughly the 21 shared numbers, 830 orphans, 221 temp-only and 83 tracking mismatches. Staff confirm those, the same way duplicates are reviewed now.
- After approval, write the register.

### Phase 3: Stamp `file_id` on the source tables (about 2 days)
- Add a nullable `file_id` to Land FC, indexing, MLS, OSS, PRA and cadastral cases. Fill it from the register.
- **New records get it at creation:** Land FC commissioning, OSS commissioning, indexing, OP capture, cadastral intake. One shared helper, so no screen forgets.

### Phase 4: Move the syncs onto `file_id` (about 2–3 days)
- `FileTitleSync`, `FileTpSync`, `CadastralCaseSync`, the MLS/Land FC/indexing/FFR propagation, and the OP capture write by `file_id`.
- File-number matching remains only as a **logged fallback** for records not yet stamped.
- Correcting a file number = add an alias + update the display number. Copies no longer go stale.
- Re-run the same rollback test matrix used today (title, plot, district, LGA, TP across Indexing / Land FC / OSS FC / OP edit), plus the duplicate and renamed-number cases.

### Phase 5: Module by module (ongoing, about 1–2 days each)
In order of risk:
1. **Cadastral.** This is the most edited, and cases already hold an indexing ID.
2. **Survey.** It has 17 tables, 14 with no ID.
3. **Land recommendation.**
4. **ST and SLTR.**
5. **Legal search, deeds, file tracking and bills.**

Each module gets `file_id` on its main table and reads details **live from the source record**, rather than keeping a copy, wherever a copy isn't needed for history or printing.

### Phase 6: Lock it in
- A test in `tests/Feature` that fails if a new sync or join uses a file-number string without the resolver.
- A note in CLAUDE.md: "join files on `file_id`; file number is display only".

---

## 7. Risks of the change itself, and how they are handled

| Risk | Handling |
|---|---|
| Wrong grouping in the backfill merges two files | Report-first backfill. Anything uncertain goes to staff review. Nothing is merged on a guess. |
| Production writes go wrong | Dev first. Row backups before every write. Rollback tests. Additive columns only, with no drops or renames. |
| Old screens keep writing by number | Logged fallback in Phase 4, plus the daily report. The fallback can be removed once the logs are quiet. |
| Slower saves | One indexed integer lookup replaces multi-column text matching, so saves should get faster. |
| Staff confusion | Nothing visible changes except clearer "ambiguous number" messages. |

---

## 8. Decisions needed

1. **Approve the File ID approach**, or choose another option from section 5.
2. **Ambiguous numbers:** block the edit and ask (recommended), or update only the record being edited.
3. **Start with Phase 1 now in production** (small, guards only), or wait and do everything in dev first.
4. **Who reviews the backfill list** (about 1,100 records today): the same team that handles duplicate review?
5. **Tracking ID:** keep it as a scan and print reference only (recommended), or also clean it and make it unique.
