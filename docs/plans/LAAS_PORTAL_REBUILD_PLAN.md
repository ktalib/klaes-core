# LAAS Portal: accounts, lineage and rebuild plan

Status: **APPROVED 2026-10-12**. All decisions in §7 were taken as recommended (§3.1–§3.3 confirmed "ok go"). Build in progress.
Scope: Land and OSS files only (`mls_file_no`, `system_sub_type` MLS / OSS). KANGIS, ST and SLTR are out of scope.

## 1. What exists today

- Commissioning (single, batch, Change of Purpose in `MlsFileNoController`) calls
  `LaasCommissioningAccountService::provisionForFiles()` after commit. It opens or reuses a
  `laas_applicants` account and creates one `laas_applications` row per file. The account
  is matched by email, or by phone **and** holder name.
- The backfill ran on 2026-10-05 to 2026-10-09. Every live `mls_file_no` row has a portal application.
- The folio (`LaasFolioService`) shows the file's own EDMS scans plus system copies (commissioning sheet, LGA,
  tracking, recommendation, RoFO, OSS acknowledgement, verification and change of ownership).
- Stage hooks (Land 12, recommendation, RoFO) advance the portal stage forward only.

### Measured on production (read-only, 2026-10-12)

| Item | Count |
|---|---|
| Live Land/OSS files (`mls_file_no`) | 10,147 (MLS 8,551 · OSS 1,591 · blank 5) |
| Portal accounts | 3,141 (3,137 from commissioning, 4 self-registered) |
| Portal applications | 10,155 (10,148 commissioning, 7 filed on the portal) |
| Files with a usable phone, **any** source | 2,519 (25%) |
| Files with an email anywhere | 0 |
| Accounts with no phone | 2,678 |
| Accounts with placeholder email | 3,135 |
| Accounts that ever signed in | 8 (9 changed their password) |
| Accounts whose holder was **texted credentials** | 129 (177 SMS sent, 1,598 skipped, 3 failed) |
| Applicant-uploaded documents (`laas_documents`) | 4 |
| Portal files that are retired (decommissioned) | 12 |
| Portal files with a parent (`parent_prop_id`) | 3,517 (1,565 parent is a Land/OSS file; 2,667 parent is outside Land/OSS, e.g. KANGIS/legacy) |
| Mergers (several parents) in `file_indexings` | 882 |
| Successors sitting on a different account from their parent | 7 |
| Registered transfers after commissioning (holder really changed) | ~2–3 (e.g. RES-2026-2803 → RAYMOND TERES) |

## 2. Gaps

1. **Retired files look live.** The portal never reads `decommissioned_files` / `is_decommissioned`. Of the 12 retired
   files, 1 is at "RoFO signed". The others were retired by CoP, ST Conversion, a subdivision mother (20+ children) and a re-grant.
2. **No lineage.** The portal never reads `parent_prop_id`, `ancestral_prop_id`, `successor_file_no` or `related_fileno`.
3. **Parcel updates never touch the portal.** Subdivision, merger, separation, extension, duplex, re-grant and ST conversion
   all retire files through `PlotWorkflowService::decommissionFiles()` / `appendSuccessors()`, and neither of them calls LAAS.
   Undoing them (`RestoreDecommissionedFile`, `RegrantCorrectionService`, `UndoSubdivision`) doesn't either.
4. **Ownership transfer isn't followed.** `applicationFor()` refuses to move a file between accounts, and nothing
   detects a registered assignment or purchase that happens after commissioning.
5. **Most accounts can't be delivered.** 75% of files have no phone in any source, and no file has an email. The
   backfill printed the temporary passwords to a console, and nobody kept them.
6. **Identity.** Grouping accounts by phone + name is weak, but no stronger key exists (see §3.4).
7. **The holder name has one source.** The account takes `mls_file_no.file_name`. `file_indexings.current_holder` is
   not reliable: it is JSON-quoted, and one layout owner's name sits on 1,563 files (estate or layout holder, not the plot buyer).
   After stripping the quotes, 95 files have a genuinely different name. They need a human review list, not automatic use.

## 3. Recommendations on the open decisions

### 3.1 Retired files: keep them on the holder's account, read-only, with a badge

Show "Retired on {date}: {reason}. Became {successor file numbers}". The folio stays visible because it is the holder's own
history, but stage hooks stop advancing a retired file. **Don't hide it.** Hiding makes the land look as if it vanished.
It would also break the project rule that decommissioning flags and never deletes. An undo
(`RestoreDecommissionedFile`, re-grant correction) clears the badge automatically. That works because §4.1 derives the status at read time.

### 3.2 Lineage across accounts: full links on the same account, minimal facts across accounts

- **Same account** (for example, a subdivision where the mother's holder keeps the plots): link both ways, so the parent shows
  its children and each child shows its parent, with folios.
- **Different account:** show only the other file's **file number, Plot No., District, LGA, Location**, and the event
  (Subdivision / Merger / CoP / Re-grant) with its date. Never show the other holder's name, phone or folio. The mother's
  folio carries the previous owner's passport.
- Parents outside Land/OSS (2,667, mostly KANGIS or legacy): show the parent file number as text, with no link.

### 3.3 Ownership transfer: move the file, staff-confirmed, and the old owner keeps history up to the transfer

- The file moves to the new owner's account, which is reused or created by the same matching rule.
- The old owner keeps a read-only entry: "Transferred on {date}". It shows the timeline and the documents dated up to the
  transfer and nothing after it.
- **Staff confirm the transfer; it never happens automatically.** On conversion files the Deed of Purchase names the buyer, who
  is *already* the holder, so automatic detection would wrongly move about 15 files. Detection only *queues* a candidate:
  a registered assignment-type instrument (Deed of Assignment / Purchase / Gift / Transfer of Title) whose
  `reg_date` falls after commissioning and whose transferee differs from the portal holder. Staff approve it with one action.
  Current volume is about 2–3.

### 3.4 Identity: not `entity_id`

`entity_id` is **one per file** (4,337 portal files map to 4,337 distinct entities, and none are shared), so it can't group a person's files.
Keep the existing rule (email, or phone **and** normalised name; never phone alone). Add two things:

- Store `customers_staging.entity_id` on each portal application, for traceability only.
- Add a staff **merge accounts / move file** tool to fix grouping by hand. This is the same tool §3.3 uses.

NIN would be the right key, but only 1 file has one, so it isn't usable yet.

### 3.5 Delivery: every file gets an account; missing contacts get system values and a flag (DECIDED 2026-10-12)

- A file with a usable, **unshared** phone gets an account as today.
- A file with no usable phone or email **still gets an account**, with system-generated contact details, flagged for later update:
  - **Email:** `<username>@portal.klaes.local`, as today. `PhoneOtpService::isPlaceholderEmail()` already stops mail going to it.
  - **Phone:** `SYS-` + the applicant id padded to 8 digits (e.g. `SYS-00003141`). It has **8 digits, so both
    `LaasApplicant::normalizePhone()` and `BulkSmsNgService::normalizeNumber()` return null and it can never be texted.**
    A plain-digit placeholder such as `00000003141` would be accepted by the gateway (any 10–11 digits starting with 0), so it must not be used.
    An explicit `isPlaceholderPhone()` guard is also added in front of every LAAS SMS/OTP send, so the safety doesn't depend on digit counts alone.
  - **Flag:** `laas_applicants.contact_status = 'pending_update'` (+ `contact_flagged_at`, `contact_flag_reason`:
    `no_phone` / `no_email` / `shared_phone`). Staff get a filterable list.
- **Clearing the flag:** staff enter the real phone, or the holder does at first sign-in. The holder is made to complete their contact on first sign-in,
  verified by the existing phone-change OTP (`pending_phone` / `verification_code`). Once verified, the flag clears and a fresh temporary password
  is texted to the real number.
- **Getting the first sign-in to a flagged holder:** a staff "Issue sign-in slip" action resets the temporary password and prints
  the username and password for the counter. The online route (file number + ID, reusing Online Legal Search's ID-name OCR check) can follow later.
- **Grouping without a phone:** name-only grouping must stay inside **one commissioning batch** (`mls_file_no.batch_no`, or the single file).
  The current code groups by name within one *call*, so a rebuild that passes all 10k files in one call would merge every "UMAR AHMAD" into one
  account. The rebuild therefore calls the service once per original batch.

## 4. Design

### 4.1 Status and lineage are read at view time, from the same source as Legal Search

Legal Search already resolves this in `LegalSearchService::search()['lineage']`: `is_superseded`, recursive
`successor_files` (with title and commissioning date) and `previous_file_nos`. That is built by
`resolveFileLineage()` + `enrichLineageWithCommissioning()`, which are private.

- Add one small public wrapper, `LegalSearchService::lineageFor(string $fileNo): array`, that calls those two methods.
  It must not change anything else in the 10k-line service.
- A new `App\Services\Laas\LaasLineageService` turns that into the portal view. It applies the §3.2 visibility rules per
  account and adds the parent from `file_indexings.parent_prop_id` (all parents for a merger).
- **Known Legal Search defect to fix first:** `resolveFileLineage()` finds predecessors with
  `UPPER(successor_file_no) = ?`. For a subdivision `successor_file_no` is a CSV list, so the mother is missed.
  `related_fileno` usually covers it, but not always. The fix is delimiter-aware matching (`',' + REPLACE(successor_file_no,' ','') + ',' LIKE '%,X,%'`).
  This also fixes Legal Search itself; regression-test it there.
- Nothing is copied into LAAS tables, so a correction or undo of a parcel update can't leave the portal stale.
  The only stored value is a cached `lifecycle_status` used for listing filters (§4.2). It is refreshed by the hooks in §4.3 and a nightly reconcile.

### 4.2 Schema (additive migration, `laas_applications`)

| Column | Purpose |
|---|---|
| `lifecycle_status` varchar(20) | `active` / `retired` / `transferred_out`. Cache only; §4.1 is the truth |
| `lifecycle_changed_at` datetime | when it last changed |
| `transferred_from_application_id` bigint null | on the new owner's row |
| `transferred_to_application_id` bigint null | on the old owner's row |
| `visible_until` datetime null | old owner sees timeline and folio up to this date |
| `entity_id` bigint null | traceability only (§3.4) |
| `holder_source` varchar(30) | where the holder name came from (`mls_file_no`, `transfer:{instrument_capture_id}`, `staff`) |

And on `laas_applicants` (additive):

| Column | Purpose |
|---|---|
| `contact_status` varchar(20) | `ok` / `pending_update` |
| `contact_flagged_at` datetime null | when it was flagged |
| `contact_flag_reason` varchar(40) null | `no_phone`, `no_email`, `shared_phone` (comma-joined) |

The uniqueness rule changes. Several rows may share a `file_number` (one per holder over time), but only one may be
`active`. `applicationFor()`, `LaasWorkflowService::advanceByFileNumber()` and the folio must pick the **active** row.

### 4.3 Hooks (after commit, never throw)

| Event | Where | Portal effect |
|---|---|---|
| Commissioning | `MlsFileNoController::openLaasPortalAccounts` (exists) | create the application; claimed only if deliverable (§3.5) |
| File retired | `PlotWorkflowService::decommissionFiles()` + `appendSuccessors()` | refresh `lifecycle_status`; record a timeline event "Retired → X" on the retired file |
| File restored / correction undone | `RestoreDecommissionedFile`, `RegrantCorrectionService` undo, `UndoSubdivision` | refresh status; timeline event |
| Transfer candidate | deeds registration of an assignment-type instrument | queue for staff (§3.3) |
| Transfer approved | new staff action | old row `transferred_out` + `visible_until`; new row on the new owner's account |

`decommissionFiles()` is the single choke point for subdivision, merger, separation, extension, CoP, re-grant and
duplex retirements. ST conversion retires with `event_type = 'ST Decommissioning'`; confirm its path calls
`flagDecommissioned()` before relying on it. The nightly `laas:reconcile` catches anything a hook missed.

### 4.4 Portal screens

- Dashboard: active files first. Retired and transferred files sit in a "Past files" section with their badge.
- File page: a lineage panel (parent(s) ↑, successors ↓, property facts in the order Plot No., District, LGA, Location).
- Staff (LAAS admin): an unclaimed-files list, the transfer queue, merge accounts / move file, and the 95-name review list.

### 4.5 Legal Search on the portal (decision needed)

The portal's file history and a Legal Search report are much the same thing. Online Legal Search is a **paid**
product (Paystack). Options:

- (a) The portal shows status + lineage only (free) and links to Online Legal Search for the full report (paid). **Recommended.**
- (b) The holder gets their own file's full report free.

Note that the report's duplicate filtering runs in the browser (`dedupeTransactionsForTimelineAndReport` in the legal search JS), so a
server-only rendering won't match the staff screen exactly. That is another reason to link to it rather than re-render it.

## 5. Delete and rebuild

Every rebuild step defaults to dry run and writes a CSV report first. There are **no backups**, so every delete is preceded by a full CSV
export of all five `laas_*` tables to `storage/app/laas_rebuild/{timestamp}/`.

### 5.1 Keep, do NOT delete (DECIDED 2026-10-12)

| Keep | Why |
|---|---|
| 4 self-registered accounts (ids 1, 4, 5, 6) and the 7 portal-filed applications (ids 1, 3, 6, 7, 50, 11181, 11182) | real portal use |
| The 9 accounts that changed their password / signed in (incl. `sani.yusuf66`, `engr.dayyabu32`, `hussein.ali98`, `adamu.abubakar50`) | real people are using them |
| The **129 accounts whose holders were texted a username and password** | deleting them breaks credentials people already hold. Keep the account (id, username, password) and rebuild only its file list |
| The 4 `laas_documents` rows and their events | applicant uploads |

`shuaibu.ali60` and `ibrahim.aminu98` are internal test accounts; delete or keep them as you prefer.
Keeping the rows above also keeps `LaasApplication::nextReference()` (MAX + 1) from reissuing a reference number that was already texted.

### 5.2 Delete

Everything else from commissioning: about 3,000 never-used accounts, their applications, and their system events and notifications.
The delete runs inside one sqlsrv transaction, with counts checked against the dry run before commit.

### 5.3 Rebuild (`php artisan laas:rebuild --dry-run` → review CSV → run)

1. For each live Land/OSS file: holder name = `mls_file_no.file_name` (normalised per field rules); phone via
   `ApplicantPhoneResolver` (shared numbers rejected); email from the commissioning email if present.
2. If the file is already on a kept account (§5.1), reattach it there.
3. Otherwise find or create the account (§3.4), one service call per original commissioning batch. Where there is no usable
   phone or email, use the system-generated values and the `pending_update` flag (§3.5).
4. Set stage from `registryProgress()` (as today), `lifecycle_status` from §4.1, and `entity_id`.
5. **No SMS during the rebuild.** New account holders are told at the counter or on claim (decision 5 below).
6. Output: CSV of accounts created, reused and flagged (with reason), the 95-name review list, and the transfer candidates.

Test with the rollback harness first (outer sqlsrv transaction, `Mail::fake()`, SMS off), then run for real.

## 6. Build order

1. `lineageFor()` wrapper + CSV-successor fix in Legal Search, with a regression test.
2. Migration (§4.2) + `LaasLineageService` + make all read paths active-row aware.
3. Hooks (§4.3) + `laas:reconcile` (nightly, in `Kernel.php`).
4. Portal screens (§4.4) + staff tools (claim, transfer queue, merge / move).
5. `laas:rebuild` command → dry run → your approval → export → delete → rebuild.
6. Online first sign-in for flagged holders (file number + ID, reusing the ID-name verification).

Steps 1–4 can ship before the rebuild. Step 5 then rebuilds onto the new schema once, instead of twice.

## 6a. Build status (2026-10-12)

Steps 1–4 are built and live in production code; step 5's command is built and its plan was reviewed; the **commit run has NOT been made**.

- Legal Search: CSV-successor predecessor fix + `lineageFor()` (5 of 3,559 subdivision children were missing their mother; now 0). Test: `tests/Feature/LegalSearchLineageTest.php`.
- Migration `2026_10_12_100000_add_lifecycle_and_contact_flags_to_laas_tables` **run** (additive).
- `LaasLineageService`, `LaasTransferService`, `LaasAccountAdminService`; portal file page banner + File history panel; dashboard "Past files"; folio/timeline cut-off for transferred files; read-only files take no uploads and no stage moves.
- SMS guard: `SYS-` phones are never texted (login OTP, commissioning login, stage notifications).
- Hooks: `PlotWorkflowService::decommissionFiles()` (after commit), `RegrantCorrectionService::apply()`, `RestoreDecommissionedFile`, Land Registration deed registration.
- `laas:reconcile` scheduled daily 03:30. Its first run will badge **14** retired files and queue **9** transfer candidates (2 real, 7 spelling variants for staff to dismiss).
- Staff: Applicants page (S/N, contact-pending filter, actions menu: Update contact, Issue sign-in slip, Merge, Suspend, Delete), Ownership Transfers page, printable slip; sidebar links.
- `laas:rebuild`: plan-only by default. **Lesson:** the first version ran in one transaction and, because `klas` has no snapshot isolation, it blocked LAAS reads; it was stopped and fully rolled back after about 20 minutes. The command now plans in memory and commits in short transactions.

Plan result (`storage/app/laas_rebuild/20261012_045426_plan/`): keep 134 accounts; delete 3,007 accounts / 9,874 applications; create 3,978 accounts (3,651 contact-pending). Rules: 274 stay on kept accounts, 1,538 join their parent file's account, 620 phone+name, 4,160 one-holder batches, 3,555 own account. 94 names to review.

## 7. Decisions needed

1. Retired files: keep, read-only, badged (§3.1)?
2. Cross-account lineage: number + property facts only (§3.2)?
3. Transfers: staff-confirmed move, old owner keeps history to the transfer date (§3.3)?
4. ~~Delivery~~: **decided**. Every file gets an account; missing contacts get system values + `pending_update` flag (§3.5).
5. ~~Kept accounts / SMS~~: **decided**. Keep the 129 texted, 9 signed-in, 7 portal-filed and 4 uploads (§5.1). **No SMS during the rebuild.**
6. ~~Legal Search~~: **decided**. Option (a): the portal shows status + lineage and links to the paid Online Legal Search (§4.5).
7. ~~Test accounts~~: **decided**. Delete `shuaibu.ali60` and `ibrahim.aminu98` with the rest.
