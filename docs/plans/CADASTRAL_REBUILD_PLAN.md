# Cadastral Module — Rebuild Plan

Status: **for review, nothing built yet.** Written 2026-10-01.

Inputs:
- Functional brief: [cadasral_implementation.md](cadasral_implementation.md), which reconstructs the UI video and the concept note
- Official print layouts: `docs/templates/cadastral/` (the brief says `doc/`, but there is no such folder)
- What exists today: the 2026-09-28 Cadastral Module (`routes/cadastral_module.php`, `config/cadastral_module.php`, `app/{Http/Controllers,Services,Models}/Cadastral/`, `resources/views/cadastral_module/`)

---

## 1. Decisions already made

| # | Decision | Consequence |
|---|---|---|
| D1 | **The official fee sheet is authoritative**, not the video's calculator | The bill is built from the line items on *Right of Occupancy – Cadastral Fees and Area*. The video's "area × ₦/Ha + 5% service + description fee" is dropped, and so is the current "m² × land-use × zone" formula. |
| D2 | **The area fee uses the "Proposed fees" column** of the S.L.N. No. 3 of 1983 schedule | The schedule is stored in config. Changing it affects new bills only, because each bill keeps a copy of the rates it was issued at. |
| D3 | **Work phase by phase, starting with this plan** | Each phase is shown working before the next one starts. No migration runs without explicit approval. |
| D4 | **Every configurable value is editable in System Admin → Configurable Entries** (2026-10-01) | Admins edit these in a new **Cadastral** tab: fee-sheet rates, the area-fee schedule, transport bands, number formats, file prefixes, source registries, and which officer holds each job post. `config/cadastral_module.php` becomes the fallback when nothing has been saved. The Bill Formulas tab can't do this, because its formulas only add and subtract and have no rate × quantity or schedule lookup. |
| D5 | **Property and person locations use the address builder exactly as the Survey forms do** (2026-10-01) | All cadastral forms use one builder, and the location string is put together in one place on the server. A property location is District, LGA, State and never includes the plot. |

## 2. Where we stand

The 13 `cadastral_*` tables exist in production and are **all empty**, so changing their schema costs nothing. The module is currently hidden from the sidebar (`$showCadastralModule = false`).

### Keep (works, matches the brief)
- **Report stage chains and `ReportWorkflow`:** seeded once per report, forward only, with a job post required per step. The 7- and 8-step chains already match the brief.
- **`AreaCalculator`:** converts between m², hectares and acres.
- **`LandDescriptionGenerator` and the description templates in config**
- **`ChartConflictScanner`, charts with version history, chart coordinates**
- **Index cards:** movement history is read live from `file_tracker.movement_log`.
- **Surveyor directory, survey jobs, ITS print**
- **File status events:** previous and new status, user, time and remarks.
- **`CadastralRegistryLookup`:** duplicate and double-allocation checks plus movements, read from existing KLAES tables without copying.
- **Hooks into `AuditService` and `UserNotificationService`**
- **The `cadastral-module.*` route prefix and the route-name rules for permissions** (see the header of `routes/cadastral_module.php`)

### Rebuild or add

| Area | Gap |
|---|---|
| Sidebar | Hidden, and the grouping differs from the brief. |
| Intake | The file number is typed and only checked for plausibility. The brief requires choosing the source first, then **choosing** the file from that source, with the other fields filled in automatically. |
| Correspondence | Read-only list. Nothing is created, there is no link from source to cadastral file, and duplicates can't be put on hold. |
| Reports | One list with a type filter. Needs separate Verification, Customary and Statutory pages, Start, Advance and Return actions, colour-coded stages, and the **Report on Application** questionnaire. Nowhere currently stores the questionnaire answers. |
| Plans & Descriptions | One combined screen. Needs three: Area & Pillars, Descriptions, Fee Calculator. |
| Fees | Wrong formula (D1). The bill columns don't match the official sheet's line items. |
| Revenue | `cadastral_bills.billing_id` exists but nothing ever fills it. |
| Print | Neither official template is used. |
| Analytics, Property History | Not built. |

## 3. The two official templates

### 3a. *Report on Application – Cadastral Department*
This is the print layout for the **Report** stage. Every field needs somewhere to be stored:

| Template field | Source |
|---|---|
| R. of O. No. LKN/… | file number (Land) |
| SLTR/… | SLTR file number, if any |
| Government Item No. GKN/… | **new column** |
| SIT/… | ST/SIT file number, if any |
| Q1 Plan sufficient to identify plot? | **new** yes/no |
| Q2 Ground open correctly? / if no, which title lies over it | **new** yes/no plus text |
| Q3 Completely beaconed? (a) Tracing No. (b) Deposition Plan No.; if not: unapproved Town Plan No., Lay-Out No., separate survey required? | **new**: yes/no, 4 texts, yes/no. `beacon_numbers` already exists. |
| Q4 Covered by a Town Plan? (a) Town Plan No. (b) shape agrees? (c) purpose (d) area shown for that purpose? | **new**: yes/no, text, yes/no, land use, yes/no |
| Q5 Previously held or applied for under statutory title? + details | **new** yes/no plus text |
| Q6 Railway/siding alongside? | **new** yes/no |
| Q7 Alongside a Federal/State trunk road? | **new** yes/no |
| Q8 Area applied for (acres / hectares) | `AreaCalculator`, from the plan-description area |
| Date / "For:" signature | approval step: date and approving officer |
| QR code, coat of arms | QR links to the report's verify URL; coat of arms comes from the existing asset |

These become additive nullable columns on `cadastral_reports`, with the questionnaire filled in on the **Report** step. The print view is a Blade copy of the HTML that keeps its CSS, page size and layout unchanged and replaces the blank lines with data.

### 3b. *Right of Occupancy – Cadastral Fees and Area* (the bill)

Line items, each one copied onto the bill when it's issued:

| Line | Rate (config) | Quantity from |
|---|---|---|
| Investigation and Search | ₦4,000 flat | 1 |
| Beacons | ₦4,000 each | count of `cadastral_pillars` rows |
| Area fee | Proposed-fees schedule (D2) | area in hectares |
| Delay | ₦350 per day | days, entered by officer |
| Transport | distance band | band, chosen by officer |
| Additional field work | ₦350 per day | days, entered by officer |
| Office work | ₦10,000 per day | days, entered by officer |
| Plan prints | ₦400 per file | count, entered by officer |
| **Total** | | sum of the lines, each rounded first |

The header carries Re CAD/…, the date, R. of O. No. and the area in hectares, and is signed "For Director Cadastral".

`cadastral_bills` is reshaped to hold these lines (it's empty, see §6). The old `area_rate_per_sqm`, `land_use_multiplier`, `zone_multiplier`, `service_charge*` and `description_fee*` columns are dropped.

## 4. Phases

Every phase ends with a check in the real app, a run through the **rollback test harness** for anything that writes data, and a short note back to you before the next phase starts.

### Phase 1 — Sidebar and dashboard
- Replace the hidden block in `admin/menu/partials/modules/cadastral.blade.php` with the brief's grouping: **Main** (Dashboard), **Registry** (Intake Queue, Correspondence Files), **Reports** (All Reports, Verification, Customary, Statutory), **Information** (Index Cards, Charting, Survey Jobs (I to S), File Status), **Plans & Descriptions** (Area & Pillars, Descriptions, Fee Calculator), **Analytics**.
- Keep the main KLAES sidebar shell and its classes. Don't adopt the video's navy sidebar (the Survey module made the same choice).
- Show pending counts as badges, taken from `DashboardMetrics`.
- Dashboard tiles: Intake Queue, Open Reports, Survey Jobs, Index Cards, Fees YTD, Duplicates. Below them: the latest open report as a stage tracker, recent activity, and quick links.
- The separate per-unit dashboards are folded into this one dashboard plus Analytics.
- Schema: none.

### Phase 2 — Intake from source
- The Log Incoming File modal: pick the **source department**, then a **file number** in a Select2 box searched via AJAX against that source. Owner, type and location fill in and can't be edited. Typing a free-text file number is no longer possible.
- How each source is looked up (to confirm, see Q4):

| Source | Table / filter |
|---|---|
| Land | `file_indexings` where `registry` in `1`, `2`, `3` (Lands physical registries) |
| SLTR | `file_indexings.registry = 'SLTR'` |
| ST | `file_indexings.registry = 'ST Registry'`, matched on `st_file_no` |
| KANGIS | `file_indexings.registry = 'KANGIS'` / `kangis_file_no` |
| DCIV | `file_indexings.registry = 'DCIV'` |
| Deeds | to confirm |

- Store `file_indexing_id` on the receipt. Matching on the file number must check every file-number column, not just one, because a file can have several rows.
- Block a second **open** receipt for the same source file. This is enforced in PHP inside a transaction, because a filtered unique index on sqlsrv is risky alongside the hand-maintained data.
- Type is set automatically from the file-number prefix. A conversion file is marked `charting not required`.
- Queue statuses: Queued, then In Progress, then Correspondence Done.
- Schema: none expected. Every column needed is already on `cadastral_file_receipts`.

### Phase 3 — Correspondence and duplicate hold
- When a receipt is registered, create its correspondence record **once**, using the existing `cadastral_shadow_file` / MLS-match mechanism. That pointer column is already on the receipt, so no second register is built.
- Columns: Cadastral File No., Source File No., Owner, Created, Duplicate, Status.
- If any duplicate or double-allocation check finds a match, the receipt goes **On Hold – Investigation**, and it can't be registered until an officer clears it with a remark. Placing and clearing a hold are both audited.
- Boundary conflicts come from `ChartConflictScanner` once the file is charted. KLAES stores no parcel geometry before charting, so this check can't run earlier.
- Schema: possibly a `hold_*` set of nullable columns on `cadastral_file_receipts` (who placed the hold, when, and the clearing remark).

### Phase 4 — Reports
- All Reports page: search by file number or report ID, filter by type and status, and show assignee, current stage and status.
- Verification, Customary and Statutory pages share one stage-tracker partial. Completed stages are green, the active stage is amber, waiting stages are slate.
- Actions: Start Report, Advance, Return (the existing `steps.mark-done` and `steps.mark-returned`), Assign, Approve/Reject, and Dispatch with dispatch details. A step is enforced against the job post it requires.
- The Report on Application questionnaire is filled in on the **Report** step, and the print is §3a.
- Schema: the questionnaire columns on `cadastral_reports` (§3a).

### Phase 5 — Information
- **Index cards:** a Commission modal (file number from intake, owner and location filled in automatically, survey job number, initial movement stage). Plus print, view and movement update.
- **Charting:** the existing register, versions and conflicts, plus "No charting required" for conversion files. **GIS:** link to the existing KANGIS/GIS capture views by `origin`/`origin_id`. A map isn't built inside this module; Q6 asks whether you want one.
- **Survey Jobs (I to S):** keep the existing pages, and write the survey job number back onto the index card.
- **File Status:** the existing page, plus effective date, supporting document upload, and a notification to the source department.
- Schema: possibly `effective_date` and a document path on `cadastral_file_status_events`.

### Phase 6 — Area & Pillars, then Descriptions
- **Area & Pillars:** choose a file, enter the area (or take it from the chart's coordinates when charted), see hectares, acres and m², and add government and private pillars with coordinates.
- **Descriptions:** file, template, location text, boundary notes, and an editable textarea. Buttons: Generate from Template, Regenerate, Save, and Next: Fee Calculator. A table of saved descriptions sits below.
- Both pages reuse `cadastral_plan_descriptions` and `cadastral_pillars`. The combined screen is split into two pages.
- Schema: none.

### Phase 7 — Fee Calculator and the official bill
- Rewrite `CadastralBillCalculator` to the §3b lines. The page shows a card per line, the totals are calculated on the server, and each bill keeps a copy of the rates it was issued at.
- Print with the official Fees and Area template, the proposed-fees schedule table included.
- Schema: reshape `cadastral_bills` (§6).

### Phase 8 — Push to Revenue
- Insert a row into the existing `billing` table and store its id in `cadastral_bills.billing_id`, following the existing `BettermentBillController` pattern. No separate payment system is built.
- Blocked on Q5: the exact `billing` columns and the transaction-token rules the brief mentions.

### Phase 9 — Analytics and Property History
- Analytics: Reports Total, Dispatched, Duplicates Blocked, Bills Raised, plus the metrics table from the brief, filtered by date range.
- Property History Search: add a Cadastral section listing reports, charts and the index card for the file.

### Phase 10 — Permissions and switch-over
- Check every route name against the action rules in `module_permissions.php`. Officers get their `post_code` values (this is your data to supply). Then run `cadastral-module:install --dry-run` until it reports no problems.
- Decide what happens to the old Cadastral sidebar entries (Q7).

## 5. Open questions

| # | Question | Blocks | Default if not answered |
|---|---|---|---|
| Q1 | How is an area that falls **between** two schedule rows charged (e.g. 0.45 Ha)? Next row up (₦4,200), or interpolated (₦3,800)? | Phase 7 | Next row up, flagged as unconfirmed |
| Q2 | What is charged **above 9 Ha**? The sheet's "additional per hectare" columns are garbled ("100,00. per add hctr"). | Phase 7 | Refuse to bill above 9 Ha until answered |
| Q3 | What are the transport bands? The sheet reads "1.1 km / 11-5 km / over 50 km". I read it as 1–10 km ₦200, 11–50 km ₦300, over 50 km ₦500. | Phase 7 | That reading, flagged as unconfirmed |
| Q4 | Is the source-to-table mapping in Phase 2 right? Where do **Deeds** files come from? | Phase 2 | Deeds hidden from the source dropdown |
| Q5 | What is the Revenue contract: `billing` columns, revenue code and transaction token? | Phase 8 | Bill stays "Issued"; the Push button is hidden |
| Q6 | Should Charting include a real map (existing KANGIS layers), or link out to the GIS capture? | Phase 5 | Link out |
| Q7 | Once the new module is live, do the old Cadastral sidebar entries stay, move under a "Legacy" heading, or get hidden? | Phase 10 | They stay where they are |
| Q8 | What is the SURCON survey job number format? It's still a placeholder. | Go-live | Placeholder, with the install command warning |
| Q9 | Who holds each of the 13 job posts? | Go-live | Nobody can complete a step |

## 6. Schema changes (all additive or on empty tables)

One new migration, run with `--path` only, with every change guarded by `hasColumn`/`hasTable`:

1. `cadastral_reports`: add the questionnaire columns from §3a (`govt_item_no`, `q1_plan_sufficient` … `q7_trunk_road`, plus their text fields). All nullable.
2. `cadastral_file_receipts`: add `hold_reason`, `hold_placed_by`, `hold_placed_at`, `hold_cleared_by`, `hold_cleared_at`, `hold_clear_note`. All nullable.
3. `cadastral_file_status_events`: add `effective_date` and `document_path`. Both nullable.
4. `cadastral_bills`: drop the formula columns that no longer apply and add the §3b lines, with the unit rate, quantity and amount stored for each. **The table must have 0 rows when this runs**, and the migration checks that and refuses to run otherwise.

## 7. Ground rules

- Production is live and has no backups. Every migration runs with `--path` and only after it has been shown to you; `--pretend` is not used.
- Write paths are tested in an outer transaction that is rolled back.
- Records are flagged, never deleted. History rows (status events, steps, movements) are never edited or removed.
- Property location never includes the plot number.
- New Tailwind classes must exist in the v2 CDN build the app loads.
- The `survey_module` partials are shared and must not be edited.
