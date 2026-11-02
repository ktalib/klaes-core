# Indexing Piece-Rate Payroll Calculator — Plan

**Status:** v3 (2026-11-01). Rate card approved; Kunle's decisions applied (§11). **Phase 1 built and live 2026-11-01** (see "Build log" at the end). Not built yet: spot-checks, nightly freeze/ledger, month lock → payroll_salaries, ₦ column in the Activity Monitor.
**Sources:**
- Kunle, WhatsApp 01/11/2026 01:23–01:43 (the brief).
- The "FINAL APPROVED RATE CARD — KLAES MDC INDEXING" message.
- The two rate-card screenshots in Downloads (`WhatsApp Image 2026-11-01 at 11.42.34 AM*.jpeg`).
- The user's answers on 2026-11-01.

**Go-live:** Monday 2 November 2026. Pay months: **November and December 2026**.

---

## 1. What was asked

- There are **8 weeks** left to push indexing, above all the **Land Files**. The years 2012–2025 were given only to show how far we have come; **files of any year are paid**.
- Pay **₦ per file** plus **₦ per record** captured, so output goes up, and check quality at the same time.
- File types: Regular Land File, SLTR File, In-transit File (ITF, **dormant for now**), and KANGIS (Land File(s) + the KANGIS File).
- Indexers see their **progress and earnings**:
  1. on login,
  2. on their **profile page**,
  3. on the **Create File Indexing** page.
- Reuse the existing **Activity Monitor for Indexing**.
- Show **Ground Rent** and **Bill Balance** record counts.
- Rates and switches are kept in **System Admin › Configurable Entries** (§8).

---

## 2. Approved rate card

### 2.1 Unit rates for records (all file types)

| S/N | Record | Rate |
|---|---|---|
| 1 | Ground Rent (GR) | ₦2 per record |
| 2 | Bill Balance (BB) | ₦2 per record |
| 3 | Transaction (Trans) | ₦3 per record |

### 2.2 Base fee per file type

| S/N | File type | Base fee | Formula | Status |
|---|---|---|---|---|
| 1 | Regular Land File | ₦20 | 20 + (GR × 2) + (BB × 2) + (Trans × 3) | Active |
| 2 | SLTR File | ₦35 | 35 + (Trans × 3). *GR/BB not applicable* | Active |
| 3 | In-transit File (ITF) | ₦35 | 35 + (GR × 2) + (BB × 2) + (Trans × 3) | **Dormant.** The rate is stored but switched off. ITF files are paid as Regular until it is switched on (§4.1). |
| 4a | KANGIS Land File | ₦35 | 35 + (GR × 2) + (BB × 2) + (Trans × 3) | Active |
| 4b | KANGIS File | ₦40 | 40 + (GR × 2) + (BB × 2) + (Trans × 3) | Active |

- **KANGIS bundle** = each Land File in it (priced on its own counts) + the KANGIS File.
- **No per-file cap.** Pay is ₦ per file + ₦ per record with no ceiling. A cap setting exists in Configurable Entries but is empty (off).

```
day earning   = base fees for files the user indexed that day
              + GR/BB/Trans records the user captured that day
month earning = Σ day earnings in the month, weekends included
```

The transaction ranges in the original brief (0 / 1–3 / >3) are **display and filter only** (the classmark view). They do not change pay.

### 2.3 Worked examples (checked)

| S/N | Example | Pay |
|---|---|---|
| 1 | Regular, 2 GR, 2 BB, 3 Trans | 20+4+4+9 = **₦37** |
| 2 | SLTR, 5 Trans | 35+15 = **₦50** |
| 3 | ITF, 4 GR, 3 BB, 10 Trans *(when ITF is switched on)* | 35+8+6+30 = **₦79** |
| 4 | KANGIS bundle: Land (2 GR, 2 BB, 4 Trans) + KANGIS (1 GR, 1 BB, 2 Trans) | 55 + 50 = **₦105** |
| 5 | Large bundle, 5 GR, 5 BB, 15 Trans on each file | Land ₦100 + KANGIS ₦105 = **₦205** *(corrected from ₦209, agreed)* |

### 2.4 Monthly target (agreed)

Management's projection for November (21 working days) stands as the **target**:

| S/N | Type | Daily output | Month | Avg pay | Month earning |
|---|---|---|---|---|---|
| 1 | Regular | 100 files/day | 2,100 files | ₦40 | **₦84,000** |
| 2 | SLTR | 75 files/day | 1,575 files | ₦47 | **₦74,025** |
| 3 | ITF | 55 files/day | 1,155 files | ₦55 | **₦63,525** |
| 4 | KANGIS | 30 bundles/day | 630 bundles | ₦105 | **₦66,150** |

**Baseline, for tracking only.** The same rates applied to 19–30 Oct output (read-only simulation) give:
- a median of 16 files/day, at **₦21.8** per Regular file;
- about **₦520/day**, or roughly **₦11k/month**.

The supervisor sheet and the profile card show each person's pace against the target, so the gap is visible from week 1.

---

## 3. What already exists (inspected 2026-11-01)

| S/N | Piece | Where | Use for this |
|---|---|---|---|
| 1 | Activity Monitor | `app/Services/ActivityMonitoringService.php`, `ActivityMonitoringController`, `routes/app3.php:850`, `resources/views/activity-monitoring/*` | Reuse the user list, filters, leaderboard and `personalAlertFor()` (login alert). |
| 2 | Indexed files | `file_indexings` (sqlsrv) | Base fee. |
| 3 | Transactions | `pra` (`created_by` = user id; `mlsFNo`, `kangisFileNo`, `NewKANGISFileno`) and `CofO_staging`, written by `FileIndexingController::syncFileIndexingTransactions()` and by File History | ₦3 per record, to the capturer. |
| 4 | Ground Rent / Bill Balance | `file_indexing_bills` (`bill_type` = `grant-rent` / `bill-balance`, `created_by` = user id) | ₦2 per record, to the capturer. |
| 5 | KANGIS links | `registry = 'KANGIS'` / `indexing_type` in (`KANGIS Block`, `Block`) = KANGIS File; `kangis_link_count > 0` = KANGIS Land File | Automatic type. |
| 6 | Payroll | `payroll_periods` (2026-11 and 2026-12 exist as `draft`), `payroll_salaries`, `payroll_rates`, `app/Services/Payroll/*` | Month lock → `payroll_salaries`. Piece-rate **replaces** the daily rate for these months. |
| 7 | Configurable Entries | `routes/configurable_entries.php`, `SystemAdmin/ConfigurableEntriesController` (tabs via `availableTabs()`; Survey uses its own `SurveySettingsController` + `Survey\SurveySettings::tabData()`) | A new **Indexing Pay** tab, built the same way (§8). |
| 8 | Profile page | `profile.index` → `ProfileController@index` → `resources/views/profile/index.blade.php` (+ `profile/partials/`) | New earnings card. |
| 9 | Create File Indexing | `fileindexing.create` → `FileIndexCreatePageController` → `resources/views/fileindexing/addons/create_indexing.blade.php` | Earnings strip + per-save toast. |
| 10 | Header | `resources/views/admin/header.blade.php` | Today / month figure. |

### Gaps that affect pay

1. **`file_indexings.created_by` holds the user's *name*, not their id.** Not safe for money.
   - **`created_by` stays exactly as it is**: a name, still written by `store()`. Many screens, reports and the Activity Monitor read it, so it is **not** converted to an int and **no existing values are changed**.
   - A **new, separate** column `indexed_by_user_id` (int, nullable) is added and filled by `store()` from `Auth::id()` alongside `created_by`. Pay reads `indexed_by_user_id`.
   - Rows saved before go-live have no id, so pay falls back to the existing name match.
2. **`has_transaction` cannot be trusted** (it is set on 1,740 of 185k files). Count the transaction records instead.
3. **Record capturers** (checked 2026-11-01):
   - `CofO_staging.created_by` and `file_indexing_bills.created_by` hold user ids, stored as text in nvarchar columns.
   - `pra.created_by` mostly holds ids, but one indexing path writes `Auth::user()->name ?? Auth::id()`, so some rows carry a name.
   - The ledger reads a numeric value as a user id and falls back to the name match otherwise. Rows that match no user, or more than one, are listed as **Unattributed** for the supervisor to assign; they are not paid silently to anyone.
   - The record tables are not changed.

---

## 4. Who earns what

### 4.1 File type (for the base fee)

| S/N | Type | Rule |
|---|---|---|
| 1 | KANGIS File | `registry = 'KANGIS'` or `indexing_type` in (`KANGIS Block`, `Block`) |
| 2 | SLTR File | `registry = 'SLTR'` or the file number starts `SLTR-` |
| 3 | KANGIS Land File | land file linked to a KANGIS file (`kangis_link_count > 0` / KANGIS Block indexing) |
| 4 | Regular Land File | everything else |

The rules are applied in that order. All of them are automatic; the indexer does nothing extra.

**ITF is dormant.** In KLAES, "In-transit" is a File Tracker state, so the data cannot identify an ITF file (a tracker rule would tag ~80% of files). Nothing about ITF is shown or used for now:
- no picker,
- no ITF column on the screens,
- no ITF pay.

When it is wanted, the plan is: switch "ITF enabled" on in Configurable Entries. That shows an "In-transit File" choice on the Create File Indexing page, and every ITF claim goes to the QC sample.

### 4.2 Who is paid

- **Base fee** → the user who **indexed** the file (`indexed_by_user_id`). Each file is paid **once**; edits and re-saves earn nothing.
- **GR / BB / Trans** → the user who **captured that record**, on the day it was captured. This holds whoever indexed the file and whenever the file was indexed.
  - Each record is paid **once** (unique on its source table + id).
  - Transactions count from the indexing form and from File History (`pra` / `CofO_staging`).
  - SLTR GR/BB records are shown but earn ₦0.
  - A record counts only if it has real content: a transaction needs an instrument type + a serial, page or volume; a bill needs an amount or receipt no. Empty rows earn nothing.
- **Eligible users:** **anyone who can index**, meaning any user with File Indexing access through the module/role system. The eligible module(s) are set in Configurable Entries. Others see nothing and earn nothing.
- **Which files:** **every indexed file, of any year.**
- **Which days:** **every day, weekends included.** There is no attendance minimum; a day counts if the user earned on it.
- **Excluded:** `is_deleted`, `is_duplicate`, `file_classification` Dummy/Temporary, and system-created rows (`source` set by backfills/commissioning). Each exclusion is listed with its reason.

---

## 5. Quality controls

1. **Daily freeze** at 23:30 (`indexing:earnings-close`). Each day's earnings become fixed.
2. **Supervisor spot-check (QC).** The supervisor does **not** approve files one by one, and **nothing waits for the supervisor**: every file is paid automatically. Each day the system picks a small sample per indexer (default 5, set in Configurable Entries; 0 turns spot-checks off). The supervisor marks each sampled file **OK** or **Has mistakes** with a note, and can tick several and mark them OK together. Unchecked samples simply stay unchecked; pay is unaffected.
   - **Has mistakes** → **the file is still paid** (decision §11 Q5). The mistake goes on the indexer's **quality record**: a mistakes count and a mistake rate (mistakes ÷ files checked). The indexer sees it on their profile card ("2 files with mistakes — RES-2014-1203: wrong plot no.") so they can fix them, and the supervisor sees it on the payroll sheet.
   - When the indexer fixes the file, the check is marked **Fixed**. The mistake stays in the count, so the record shows both.
   - Anyone whose mistake rate is over 10% (set in Configurable Entries) gets more files checked the next day. Pay is never deducted.
   - `has_qc_issues = 1` files are listed on the quality record the same way. Pay is unaffected.
3. **Unusual days are flagged, not blocked.** A day above 2× the person's 2-week average, or a file with more than 15 transactions, goes into the next spot-check.
4. **Month lock.** After QC the supervisor locks the month. Earnings post to `payroll_salaries` (2026-11, 2026-12) and can no longer change.

---

## 6. Where indexers see progress and earnings

Every screen reads from one `EarningsLedgerService`, so the figures always match.

### 6.1 On login and in the header
- A login card (via `personalAlertFor()`): *"Today ₦X from N files · November ₦Y · Pace Z% of target"*.
- In the header profile dropdown: Today ₦ / Month ₦.

### 6.2 Profile page — new `profile/partials/indexing-earnings-card.blade.php`
- Tiles:
  - **Today:** files, records, ₦.
  - **This week:** ₦.
  - **This month:** ₦, days worked.
  - **Projected month:** average day so far × days left.
  - **Quality:** files checked, mistakes, mistake rate, and the files still to fix.
- **Progress** bars: files today against the daily target, and month ₦ against the monthly target (both set in Configurable Entries).
- Breakdown table: S/N · Type (Regular / SLTR / KANGIS Land / KANGIS) · Files · Base ₦. Then rows for GR, BB and Trans records with their ₦.
- **Month calendar** (Nov / Dec, weekends included): ₦ per day. Clicking a day lists what was earned.

### 6.3 Create File Indexing page (`create_indexing.blade.php`)
- An **earnings strip** under the title:
  `Today: 14 files · 9 records · ₦412   |   November: ₦6,890   |   Target 14/100 files`
- **Live estimate** by the Save button while filling the form: *"This file: ₦35 (SLTR) + 3 Trans ₦9 = ₦44"*.
- After each save, a **toast** shows what the save earned, and the strip refreshes from the save response.

### 6.4 "My Indexing Earnings" page (`indexing/earnings/me`)
- **Files** table: S/N · File No. · Type · Base ₦ · Status (Counted / Excluded + reason) · Spot-check (— / OK / Has mistakes / Fixed).
- **Records** table: S/N · File No. · Record (GR / BB / Trans) · ₦ · Status.
- Day / week / month filters; PDF export.

### 6.5 Supervisor: "Indexing Payroll Sheet"
- Filters: month, date range, user, type (search is case-insensitive).
- One row per user: S/N · Name · Days worked · Regular · SLTR · KANGIS Land · KANGIS · Trans · **Ground Rent** · **Bill Balance** · ₦ earned · Files checked · Mistakes · Mistake rate · Pace vs target.
- Row actions in the three-dots dropdown (`more-vertical`, `aria-label="Actions"`): View detail, Spot-check, Quality record, Export.
- **Spot-check queue** with OK / Has mistakes. Bulk OK uses the sticky footer bar (`batch_check` / `batch_decisions`).
- Export to Excel/PDF. **Lock month** → `payroll_salaries`.

### 6.6 Activity Monitor
- Add a **₦ Earned** column to the leaderboard and the File Indexing card.

---

## 7. Payroll link

- For **2026-11 and 2026-12**, eligible users' pay **comes from the piece rate and replaces** the daily-rate figure.
- On month lock, each user's total is written to `payroll_salaries` (`base_salary`, with `note` = "Indexing piece rate").
- The normal payroll screens then show it. Other staff and other months are untouched.

---

## 8. Configurable Entries › Indexing Pay (new tab)

Built like the Survey tab: an entry in `availableTabs()`, an `IndexingPaySettingsController` for saves, and `IndexingPay\Settings::tabData()` for the view. Every change is **effective-dated** (applies from a chosen date forward; past days keep their rate) and logged to `payroll_audit_logs`.

| S/N | Setting | Initial value |
|---|---|---|
| 1 | Base fee — Regular Land File | ₦20 |
| 2 | Base fee — SLTR File | ₦35 |
| 3 | Base fee — KANGIS Land File | ₦35 |
| 4 | Base fee — KANGIS File | ₦40 |
| 5 | Base fee — In-transit File | ₦35 |
| 6 | ITF enabled | **Off** |
| 7 | Rate — Ground Rent / Bill Balance / Transaction | ₦2 / ₦2 / ₦3 |
| 8 | SLTR pays GR/BB | Off |
| 9 | Per-file cap | empty (none) |
| 10 | Programme start / end | 2026-11-02 / 2026-12-31 |
| 11 | Daily file target / monthly ₦ target | 100 / ₦70,000 |
| 12 | Eligible module(s) (who can earn) | File Indexing |
| 13 | Spot-check files per user per day / mistake rate that triggers more checks | 5 / 10% |
| 14 | Unusual-day multiplier / max transactions before review | 2× / 15 |
| 15 | Show earnings on login / header / profile / create page | On / On / On / On |

---

## 9. Data changes (sqlsrv, additive only)

| S/N | Change | Why |
|---|---|---|
| 1 | **New** column `file_indexings.indexed_by_user_id` (int, nullable), set in `store()` **in addition to** `created_by`. `created_by` keeps holding the name, unchanged, and is not converted. | Reliable attribution from day 1 without touching anything that reads `created_by`. Earlier rows fall back to the existing name match. |
| 2 | New `indexing_pay_settings` (id, key, value, effective_from, effective_to, created_by, timestamps) | The Configurable Entries settings (§8), effective-dated. |
| 3 | New `indexing_earnings_ledger` (id, earner_user_id, work_date, item_type [file_base / gr / bb / trans], source_table, source_id, file_indexing_id, file_number, file_type, amount, setting_version, status [provisional / counted / excluded], status_reason, frozen_at, period_code, timestamps). **Unique (source_table, source_id).** | One row per paid file or record, so every naira is traceable. Supports "indexer gets the base, capturer gets the records". |
| 4 | New `indexing_qc_checks` (ledger_id, checked_on, reason_picked, checker_id, result [ok / mistakes], note, fixed_at) | Spot-check trail and quality record. It never changes pay. |

- Migrations go in `database/migrations/` with `Schema::connection('sqlsrv')`, and each is run with `--path=`.
- No changes to existing data. The ITF column/picker is **not** added until ITF is switched on.

---

## 10. Code layout and timeline

### Code
| S/N | File | Purpose |
|---|---|---|
| 1 | `app/Services/IndexingPay/Settings.php` | reads the effective settings; `tabData()` for Configurable Entries |
| 2 | `app/Services/IndexingPay/FileClassifier.php` | type rules (§4.1), pure |
| 3 | `app/Services/IndexingPay/PieceRateCalculator.php` | applies the rates, pure, unit-tested against §2.3 |
| 4 | `app/Services/IndexingPay/EarningsLedgerService.php` | collects files and records per earner/day, the freeze, totals, quality record; feeds every screen |
| 5 | `app/Console/Commands/IndexingEarningsClose.php` | `indexing:earnings-close {--date=}` at 23:30; safe to re-run |
| 6 | `app/Http/Controllers/IndexingPay/MyEarningsController.php` | my page, profile card, header/strip JSON |
| 7 | `app/Http/Controllers/IndexingPay/PayrollSheetController.php` | supervisor sheet, spot-checks, export, lock |
| 8 | `app/Http/Controllers/SystemAdmin/IndexingPaySettingsController.php` + route in `routes/configurable_entries.php` | settings tab |
| 9 | views: `resources/views/indexing_pay/*`, `profile/partials/indexing-earnings-card.blade.php`, strip partial in `fileindexing/addons/partials/`, Configurable Entries tab partial | Tailwind v2 classes only |
| 10 | `FileIndexingController::store()` | set `indexed_by_user_id`; return the save's earnings in the JSON |
| 11 | routes in `routes/app3.php` beside `activity-monitoring` | `['auth','XSS']`; grep every route file first |
| 12 | module **Indexing Earnings** in `config/module_permissions.php` | `view_all`, `spot_check`, `lock` (`@canDo`). Own earnings need no extra permission. |

### Timeline
| S/N | When | Deliverable |
|---|---|---|
| 1 | **Mon 2 Nov** | `indexed_by_user_id` live; settings table + Configurable Entries tab seeded with §8. **Pay can be recalculated back to Monday**, because every file and record is already saved with its user and date. |
| 2 | Tue 3 – Wed 4 Nov | Calculator, ledger, nightly close; strip on Create File Indexing, profile card, login/header. |
| 3 | Thu 5 – Fri 6 Nov | My Earnings page, supervisor sheet, spot-checks, export, ₦ in the Activity Monitor. |
| 4 | Fri 6 Nov | Week-1 review: pace against the §2.4 targets. |
| 5 | 30 Nov → early Dec | Spot-check catch-up, lock November, post to `payroll_salaries`. |
| 6 | 31 Dec | Lock December, post, close-out report. |

### Testing
- **Unit:** every §2.3 example (incl. ₦205); SLTR ignores GR/BB; ITF off → paid as Regular; a mid-month rate change; base fee goes to the indexer and records to the capturer; each file/record paid once; empty rows ignored; a "Has mistakes" check leaves pay unchanged.
- **Feature:** users see only their own figures; supervisor/lock gates; the settings tab saves and is effective-dated; S/N on tables; sqlsrv read-only guard.
- **Rollback harness** (outer sqlsrv transaction + `Mail::fake()`) for `store()`, the nightly close and the month lock.
- **Reconciliation:** ledger file count per user/day = Activity Monitor count, with every difference listed with its reason.

---

## 11. Decisions (2026-11-01)

| S/N | Question | Decision |
|---|---|---|
| 1 | Expected pay vs current pace | Keep the card; the management projection (§2.4) is the target. |
| 2 | In-transit Files | **Keep quiet:** stored but switched off; no picker; paid as Regular. |
| 3 | Per-file cap | None. ₦ per file + ₦ per record. |
| 4 | Who is paid for GR/BB/Trans | **The person who captured the record.** |
| 5 | Spot-check finds a mistake | **Paid anyway**; the mistake goes on the indexer's quality record (option c). |
| 6 | Which files | Every indexed file, any year. |
| 7 | Which days | Every day, weekends included. |
| 8 | Who can earn | Anyone who can index (File Indexing access). |
| 9 | Daily rate | Piece rate **replaces** it for Nov/Dec. |
| 10 | Remaining counts per year | Not needed; the years were for information only. |
| 11 | Max-bundle example | Corrected to ₦205. |
| 12 | Where settings live | Configurable Entries › Indexing Pay. |
| 13 | `file_indexings.created_by` | **Keep it as the name, unchanged.** Add a separate `indexed_by_user_id` for pay. |

The last open check is done: CofO and bill records store user ids (§3). Unmatched rows go to the supervisor as Unattributed.

---

## 12. Out of scope

- Piece rates for Page Typing, Scanning, PRA and PIC. The design allows them later.
- Switching on ITF (a later setting change).
- Rewriting historic `created_by` values.

---

## Build log

### Phase 1 — 2026-11-01 (live)

**Database (sqlsrv, additive, both run):**
- `2026_11_01_120000_add_indexed_by_to_file_indexings`: `file_indexings.indexed_by_user_id` and `indexed_at` (both nullable), with index `ix_file_indexings_indexed_by`. `created_by` is unchanged and still holds the name.
- `2026_11_01_120100_create_indexing_pay_settings`: versions of the rate card. No rows yet; the approved card in `config/indexing_pay.php` applies until one is saved.

**How pay is worked out (`App\Services\IndexingPay\EarningsService`):**
- **Files.** Rows with `indexed_by_user_id` set, which only `FileIndexingController::store()` does. A completed temp record counts too, unless someone already indexed it. Excluded: `is_deleted`, `is_duplicate`, Dummy File/Temporary. Type rules: KANGIS registry → KANGIS File; SLTR → SLTR File; `kangis_link_count > 0` → KANGIS Land File; anything else → Regular. ITF is never applied.
- **Transactions.**
  - `pra` rows count when the instrument is named and the serial, page or volume is filled. This excludes the blank Right of Occupancy rows created with each index.
  - `CofO_staging` rows count when the serial, page or volume is filled.
  - Both are matched on `created_by` = user id. A repeat of an earlier record (same file, instrument, serial, page and volume) is not paid again, and a CofO row already in `pra` is not paid twice.
- **Ground Rent / Bill Balance.** `file_indexing_bills` rows that have an amount or a receipt number, matched on `created_by`. Bills on an SLTR file are counted at ₦0.
- **Rates.** Each item is priced with the card in force on its own day.

**Change to the bill save path (`FileIndexingBillService`).** Re-saving a file used to delete and re-insert all its bills under whoever pressed Save, which would have paid that person again. An unchanged bill (same type and year range, or same receipt and amount) now keeps its first `created_by` and `created_at`.

**Screens:**
- Strip on Create File Indexing (`indexing_pay/partials/create_strip`): today, the month, target progress, and a message after each save showing what it earned (event `klaes:indexing-saved` from `showIndexingSavedCard`).
- Card on the profile page (`profile_section` + `earnings_card`).
- Header chip (`header_chip`).
- My Indexing Earnings: `/indexing-earnings`.
- Supervisor sheet with CSV export: `/indexing-earnings/sheet`, for Super Admin or the "Indexing Earnings" role.
- Configurable Entries › Indexing Pay.

**Who can earn.** Holders of the **Indexing** module: 86 users on 2026-11-01. The sheet lists anyone who indexed through the form without the module.

**Tests:**
- `tests/Unit/IndexingPayCalculatorTest` covers every worked example, including ₦205.
- `tests/Feature/IndexingEarningsPagesTest` uses real data read-only: totals agree across the screens and no record is paid twice.
- Rollback harnesses checked the base fee per type, the exclusion of duplicate-flagged files, bills keeping their first capturer on re-save, and rate card versions.

**Not built yet:**
- daily spot-check queue (§5);
- nightly freeze and ledger;
- month lock → `payroll_salaries`;
- ₦ column in the Activity Monitor;
- sidebar link.

### Supervisor Monitor — 2026-11-01 (live)
- The EDMS page "Indexing Activity Log" (`fileindexing.activity-log`, `FileIndexingActivityLogController`) is renamed **Supervisor Monitor**: page title, heading and sidebar label. The route and the "Indexing Activity Log" role are unchanged, so access is the same.
- **New "Earned (₦)" column, "Total Earned" card and footer total**, all from `EarningsService` for the same dates. For each person, the file rows plus "Records captured on other files" add up to their earned figure. People who earned only from records (no file indexed in the range) get a row of their own. The Top 10 Leaderboard shows Earned too.
- **Shared names.** Files are grouped by the name in `created_by`, and two accounts can share a name (e.g. MUHAMMED YAKUBU: 50087 holds Indexing, 50518 does not). A row therefore takes the earnings of every account carrying its name.
- **Reconciliation.** For 26–30 Oct (start date moved back for the test only), the monitor total ₦5,234 equals the Indexing Earnings sheet, and every person matches.
- **Layout.** The table now scrolls sideways instead of clipping the last columns.

### Admin view — 2026-11-01 (live)
- Admins (Super Admin or the "Indexing Earnings" role) now see the progress screens: the Create File Index strip, the profile card and the header chip. These show their own figures, marked **Admin preview**, because they are not on indexing pay.
- **My Indexing Earnings** (`/indexing-earnings`) has an **Admin view** bar to pick any indexer (`?user=ID`). It then shows exactly that indexer's card, breakdown, calendar and day list.
- The Indexing Earnings sheet rows have a new action, **Open progress page**.
- **Who can see whose figures.** `?user=` is honoured only for admins; an indexer passing it still gets their own figures. Users who are neither indexers nor admins see nothing. These checks were run read-only against real users.

### Activity Monitoring tab, sidebar link, create-page strip off — 2026-11-01 (live)
- **Activity Monitoring › Indexing Earnings tab.** Uses the page's own date range and shows the same figures as the Indexing Earnings sheet: totals, a per-person table, a search box, Export CSV, Full sheet, and a three-dots menu on each row (Open progress page, Items on the sheet). Data comes from `indexing-earnings.sheet.data`.
- **Supervisor access.** People holding **Activity Monitoring** now count as supervisors for earnings (`canViewAll`), alongside Super Admin and "Indexing Earnings".
- **Sidebar.** "My Earnings" sits under DMS › Indexing, right after Supervisor Monitor, for indexers and admins.
- **Create File Index strip hidden for everyone** (user): the config default `show.create_page` is now `false`, and config was re-cached. The header chip and profile card stay. It can be switched back on in Configurable Entries › Indexing Pay.

### MDC only — 2026-11-02 (live)
- Earning and the header chip are limited to users whose staff type (`users.staff_type_category`) is **MDC** and who hold **Indexing**: 64 users. The 19 MLPP and 3 MDCM staff holding Indexing earn nothing.
- This is the new Indexing Pay setting "…and staff type(s)" (`eligible_staff_types`, default `MDC`) in Configurable Entries.
- **MDCM added (user, 2026-11-02):** eligible staff types are now MDC + MDCM, so 68 users earn (65 MDC, 3 MDCM). The header chip follows the same rule. MLPP still earn nothing.
- **Work station rule (user, 2026-11-02):** a user also earns without the Indexing module if their work station is "File Indexing" (setting `eligible_work_stations`), still MDC/MDCM only. This adds Maryam Ibrahim Jibril and Mubarak Dauda Mukhtar (both Super Admins) and Farida Abubakar Muhammad and Amina Abdullahi Ibrahim (neither holds Indexing): 72 users in all.
