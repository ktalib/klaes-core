# Cadastral Department Workflow — build notes

Source: *Cadastral Department Workflow (1).docx* (user, 2026-10-09). Scaffolded on 2026-10-10. It is **not in the sidebar** and **the migration has not run**.

## What exists

| Piece | Where |
|---|---|
| The three workflows as data (desks, phases, steps, tasks, decisions, sub-type routing) | `config/cadastral_dept.php` |
| Graph reader + validator | `app/Services/CadastralDept/WorkflowDefinition.php` |
| Decides the next step (pure, no DB) | `app/Services/CadastralDept/WorkflowEngine.php` |
| Writes moves: intake, task ticks, forward/decide/close, sub-type | `app/Services/CadastralDept/CaseWorkflow.php` |
| Models | `app/Models/CadastralDept/` (`CadastralCase`, `CadastralCaseMovement`, `CadastralCaseTask`) |
| Migration (**draft, not run**) | `database/migrations/2026_10_10_100000_create_cadastral_dept_workflow_tables.php` |
| Routes (`cadastral-dept.*`, mapped to Cad - Records) | `routes/cadastral_dept.php` |
| Controllers | `app/Http/Controllers/CadastralDept/` (Workflow, Desk, Case) |
| Pages | `resources/views/cadastral_dept/`: dashboard, workflow map, desk inbox, all files, receive file, case page |
| Tests | `tests/Unit/CadastralDept/WorkflowDefinitionTest.php`: each path from the doc, walked desk by desk |

URLs (typed, no menu link yet): `/cadastral-dept`, `/cadastral-dept/workflow-map`, `/cadastral-dept/cases`, `/cadastral-dept/cases/create`, `/cadastral-dept/desks/{desk}`.

How it works: a **case** is one main file's journey. It sits at one step at a time, and each step belongs to a desk. A step can have a task checklist, which must be complete before the file moves on. A step then does one of four things: forwards the file, asks for a decision (Recommended / Not Recommended, inside / outside 10 km), routes it by sub-type (Resettlement / Direct Allocation), or closes the case (completed or returned). Every move writes an append-only movement row.

For an external department (Compensation, DSG, KAMMA, Physical Planning, Perm Sec, Land, field survey), the file is shown as "out". The Cadastral desk named `recorded_by` records its return.

Checked with an outer-transaction rollback against sqlsrv. The tables were created, two cases were walked to completion and to return, every guard refused what it should, and the rollback left no tables or audit rows behind.

## To go live
1. Approve and run the migration (with `--path`).
2. Answer the questions below that block the first desks.
3. Add the sidebar entry.

## Open questions

| # | Question | Default used now |
|---|---|---|
| Q1 | DCIV/LPCC can be Conversion or Direct. Who decides, and on what basis? | The registry officer picks the application type at intake |
| Q2 | Which users sit at each desk (Director, DDC, the ADs, Registry, Record Section)? | Anyone with Cad - Records can act at any desk |
| Q3 | External departments (Compensation/DSG, KAMMA, Physical Planning, Perm Sec, Land, Deeds): do they act in KLAES on their own screens, or does Cadastral only record the file going out and coming back? | Cadastral records it (Registry, or AD P&D for field survey) |
| Q4 | Buffer system (10 km): does the Director choose, or should KLAES compute it from coordinates? From which point? | The Director chooses |
| Q5 | FMC: what fields, and what print layout? Is it the same as the old Index Card? | A checklist tick only |
| Q6 | Correspondence file: is it the existing MLS-match "Commission Correspondence File" (`cadastral_shadow_files`), or a new register? Numbering? | A checklist tick only |
| Q7 | Land 12: reuse the existing "Cadastral to Land (Land 12)" screen? Where does the Yes/No form template come from? | A checklist tick only |
| Q8 | Charting: what is recorded for UTM-sheet (spatial) and table (non-spatial) charting, and how does 1st charting differ from 2nd? | A checklist tick only |
| Q9 | Field jacket, Job No. format, and the I to S template | A checklist tick only |
| Q10 | Fees, Plan & Description bill: the official fee sheet in `docs/templates/cadastral/` again? How does it reach Deeds for Bill Balance? | A checklist tick only |
| Q11 | Duplicate check at registration: what counts as a duplicate? (Placeholder plots such as "PIECE OF LAND" and "FARMLAND" broke the last one.) | A checklist tick; the only hard block is a second open case for the same file number |
| Q12 | Verification: where does Registry retrieve the correspondence file from? | A checklist tick only |
| Q13 | "Asst. Director Cadastral" (Conversion phase 5, and the billing step of both): a separate post, or one of the other ADs? | A separate desk |
| Q14 | Is the Record Section a Cadastral unit? | Yes, an internal desk |
| Q15 | Direct/Resettlement updates the FMC with "Planning Advice details", but the doc shows no KAMMA/Physical Planning step for it. Is a step missing? | Followed the doc as written |
| Q16 | Sidebar placement and role | Not linked; routes mapped to Cad - Records |

Each "checklist tick" becomes a real form or print once its question is answered. The tick stays in place as the step's completion record.

## Client brief (2026-10-10)

Source: recorded walk-through of the workflow doc with the client (`Downloads/Transcribe.md`, two recordings, AI-transcribed; `Downloads/workflow_extracted.md` is the same doc as before). The Grok summary at the end of the transcript adds details that were never said (e.g. "Allottee Name" on the Conversion FMC); the notes below come from the spoken parts only.

### Decided

| Topic | What the client said | Answers |
|---|---|---|
| Intake types | Pick the application type, then the file type. Conversion: conversion file, Quarry, SIT, DCIV (conversion). Direct: GKN, Misc KN, DCIV (direct), **OP**. Resettlement / Direct Allocation are sub-types of **OP** only. Verification can be on any file type. | Q1 |
| Registration | Reuse the old Intake Queue. The file must already be indexed or commissioned. Duplicate = the same file number already taken in. | Q11 |
| File source | Intake records where the file came from (Land, DCIV, Survey, SLTR, Sectional Titling, ...). "Not recommended" sends it back there. | |
| Hand-off | Registering auto-sends to D Cad. The registry row is frozen (view only) while the file is upstream. "Not recommended" unfreezes it and enables "Send back to source". | |
| DD Cad refusal | DD Cad "Not recommended" goes back to **D Cad with a reason**, not to Registry (differs from the doc). | |
| Short forms | D Cad, DD Cad, AD Cad Info, AD Cad Report, AD P&D, FMC. | |
| Actions | Every "Send to ..." is an action-menu item, enabled only at the right step. Recommend / Not recommend sit on one line; recommending pops up "Send to <next>". | |
| Sidebar | One entry per desk: Cadastral Registry (Intake Queue), D Cad, DD Cad, Cadastral Information, Cadastral Reports, AD P&D. Index Cards move under Cadastral Reports; Digital Registry back under Registry (access-controlled). | Q16 (layout; still not linked) |
| FMC | Like the file-tracking log, with a QR code issued at commissioning; units log the file in and out with it, including outside Cadastral. Header: name, file title, file number, location, district, block no., commissioned date/time. Log lines: unit, reason, date/time, officer. Updates are automatic from the movements, so the "update FMC" desk steps are not manual tasks. | Q5 |
| Charting | Spatial charting is on the sheet, outside KLAES. Non-spatial: a small card (charted yes/no, falls on existing plot yes/no, description, coordinates, ...) with a charting number that links to the table row. Client wants a draft card to edit. Direct: 1st charting table under Information, 2nd under Report. Conversion charts once, at Report. | Q8 |
| Land 12 / Yes-No | Land 12 starts in Land; link to the existing Land 12. Yes/No form already exists. Client will confirm. | Q7 (partly) |
| Correspondence file | Rename "Commission Correspondence File (Match MLSFileNo)" to "Create Correspondence File"; same function (pick the file number, find the indexed/commissioned file). Add correspondence and shadow columns to the MLS (fileNumber) and OSS tables, as on indexing; temporary numbers go under related file number. | Q6 |
| Compensation | Question on sending: "Land falls on Government acquisition? Yes/No". Yes = rejected, goes back the same way. No = DSG, then D Cad (not through Registry). | |
| Buffer | D Cad does it on his GIS. Question: "Falls within the 10 km buffer?" Yes = KAMMA, No = Physical Planning. | Q4 |
| Field jacket / I to S | AD P&D commissions a field jacket (a small file). Creating it creates a job; job ID + surveyor ID + field-jacket ID are linked; the surveyor is locked but can be reassigned. The issued job is the I to S. Update the old Survey Jobs (I to S) screen. | Q9 (partly) |
| Bill | Merge the old three screens (Area & Pillars, Descriptions, Fee Calculator) into one Fees P&D bill. No payment. A surveyor mobile app will backfill field data later. The bill total auto-fills the "survey processing fee" on Deeds Bill Balance when the file number is picked. | Q10 |
| Send to Deeds | Last action only, after the correspondence file is detached. Checks: FMC exists, charting done, correspondence file created, bill generated. | |
| Registry menu | Adds Attach / Detach correspondence file. | |
| Verification | At intake the screen switches to "search correspondence file" and shows the match. Ends by detaching and returning the main file to the requester. | Q12 |
| Recommendations | A plain yes/no, not the brief-recommendation letter templates. | |

### Still open

- Direct files that are **not** OP (GKN, Misc KN, DCIV-direct): which Phase 3 path do they take?
- After DD Cad returns a file to D Cad, does D Cad re-decide, or does it go on to Registry and the source?
- Q2 who sits at each desk (the sidebar now implies one role per desk); Q3 whether external desks get their own screens (compensation "to be harmonised"); Q13 Asst. Director Cadastral; Q14 Record Section; Q15 Planning Advice in Direct.
- Charting card fields (client edits our draft); Land 12 link; field-jacket and job number formats.

## Build plan (2026-10-10)

Visual version: `docs/plans/CADASTRAL_DEPT_WORKFLOW_PLAN.html` (open in a browser; also published as a private artifact).

### Old screens and where they go (each waits for an OK by name)

| Old screen | Becomes | Change |
|---|---|---|
| Intake Queue | Registry registration | Application type, file type (OP → Resettlement / Direct Allocation), file source, auto-send to D Cad, lock while upstream |
| Commission Correspondence File (Match MLSFileNo) | Create Correspondence File | Rename, same matching; correspondence + shadow columns on fileNumber and OSS |
| Index Cards | Under Cadastral Reports | Sidebar move |
| Digital Registry | Under Registry | Sidebar move, access-controlled |
| Charting | Charting card + table | Card with charting number; 1st / 2nd charting (draft to client first) |
| Survey Jobs (I to S), Surveyors | Field Jacket / I to S | Field jacket ↔ job ↔ surveyor; surveyor locked, reassignable |
| Area & Pillars, Descriptions, Fee Calculator | One Fees P&D bill | Merge; no payment; total feeds Deeds Bill Balance survey processing fee |
| Cadastral to Land (Land 12) | Linked from Cadastral Report | Client to confirm |

### Order

1. Done: restore the old Cadastral Module (code, sidebar, tables, data, Records link).
2. Done: workflow scaffold (`cadastral-dept.*`, unit tests), not linked.
3. Done: capture the client brief.
4. Next: update `config/cadastral_dept.php` to the brief (short forms, DD Cad refusal → D Cad, OP sub-types, yes/no questions, automatic FMC steps).
5. Next: Registry intake on the Intake Queue (type, file type, source, duplicate check, auto-send, lock, send back to source).
6. D Cad and DD Cad desks with Recommend / Not recommend and the buffer question.
7. File Movement Card with QR and automatic log.
8. Charting card draft for the client.
9. Create Correspondence File (rename + new columns), after OK.
10. Field Jacket, Jobs, Surveyors, I to S.
11. Fees P&D bill → Deeds Bill Balance.
12. Send to Deeds with its checks.
13. Sidebar per desk, plus Index Cards / Digital Registry moves: needs go-ahead, entry by entry.
14. Run the workflow migration with `--path`: needs go-ahead, after the config settles.

## Answers, round 2 (2026-10-10)

- **Non-OP Direct files** (GKN, Misc KN, DCIV-direct) take the Direct Allocation path: Report → Director Land (3B), skipping Compensation. OP files still choose Resettlement or Direct Allocation (per the recording).
- **DD Cad "Not recommended"** goes back to D Cad with a reason, and D Cad decides again (recommend again, or not recommend → Registry → source). The user answered "yes" to an either/or; this reading covers both.
- **Q2 people:** normal users; whoever is given a desk's sidebar entry in user roles acts at that desk.
- **Q3 outside departments:** the user leans to their own screens; the recording only clearly says so for Compensation ("to be harmonised"). Others log in/out with the FMC QR. To confirm.
- **Q13 / Q14** reworded for the user: is "Asst. Director Cadastral" (bill → AD Cadastral → Registry) another officer with a screen, or one of the three ADs? Does the Record Section need a screen, or does AD P&D tick "survey plan attached"?

## Built (2026-10-10, after round 2)

Defaults the user accepted: Asst. Director Cadastral is a separate desk (receives the billed file, sends it to Registry); the Record Section is a tick at AD P&D ("Survey plan attached").

- **Step 4, config** (`config/cadastral_dept.php`): short forms; every workflow starts at D Cad (registering sends it there); `reason` outcomes (D Cad / DD Cad refusals, acquisition "yes"); DD Cad refusal → D Cad; `question` on decision steps; Compensation "Falls on Government acquisition?" (yes → Registry → source); buffer question; outside departments recorded by the desk that receives the file back (mostly D Cad; Land R of O by Registry); no manual "update FMC" tasks; `file_types` (OP asks the sub-type, other Direct files get `default_sub_type` direct_allocation) and `file_sources`. Record Section desk removed.
- **Step 5, Registry intake** (`cadastral-dept.cases.create`): a copy, the old Intake Queue is untouched. Application type → file type → OP sub-type → file from the global selector → checked by `IntakeLookup` (indexed via the old module's `CadastralRegistryLookup`, or a live `fileNumber` row = commissioned; decommissioned refused; second open case refused; earlier cases, old intake receipts, duplicate register and same-plot files flagged) → file source (preselected from the file's registry) → "Register and send to D Cad". Verification shows the correspondence-file match and refuses a file without one. The server re-reads the file on save.
- **Registry desk** (`cadastral-dept.desks.show`, registry): rows at Registry with an action menu (only the current step's actions; refusals needing a reason open the file page); every other open file listed, locked, view only.
- **Migration draft changed** (still NOT run): `file_type`, `file_source`, `file_indexing_id`, `location`, `correspondence_fileno`, `duplicate_flag`, `duplicate_note`; `source_type` and `originating_department` dropped.
- Checked: 13 unit tests; all pages load without the tables; a full run in a rolled-back transaction (tables created, registered, refused, sent back, re-registered, all rolled back).

## Built: step 6 and the tables (2026-10-10)

- **Tables created**: `2026_10_10_100000_create_cadastral_dept_workflow_tables` run with `--path` (3 empty tables on sqlsrv).
- **Desk pages** (`cadastral-dept.desks.show`, all desks, D Cad and DD Cad first): search and application-type filter; files at the desk grouped by step ("Recommend or not recommend", "Route for Planning Advice", ...); each row's Actions menu lists only the current step's choices and opens a confirm pop-up with the question, where the file goes ("Sends the file to DD Cad") and a reason box that is required for refusals; a row sent back shows "Sent back by DD Cad: reason" (also on the file page); "Out with other departments" lists files D Cad receives back, with labels such as "DSG done: received back" and "KAMMA done: now with PS".
- Checked: a Conversion file walked from registration through D Cad, DD Cad refusal and re-recommendation, AD Info, AD Report, Compensation "No", DSG, the buffer, KAMMA and PS, in a rolled-back transaction; every desk page loads; 13 unit tests.

## Built: step 7, File Movement Card (2026-10-10)

- **Migration** `2026_10_10_110000_create_cadastral_dept_fmc_tables` (NOT run yet): `cadastral_fmcs` (header snapshot, `fmc_no` FMC-YYYY-NNNNN, `qr_token`, block/layout no., print count), `cadastral_fmc_logs` (QR log-ins/outs, append-only), and `plot_no` / `district` / `lga` on `cadastral_cases`. The code works before it runs (FMC hidden, property columns skipped).
- **Commission** at AD Cad Info: the "FMC commissioned" task can no longer be ticked by hand; "Commission FMC" opens a pop-up whose fields come from the file records (file_indexings via the old module's lookup, or the fileNumber row) and are locked; only blanks, block no. and layout no. are typed (codes upper-cased). It ticks the task.
- **Card page / print / QR**: `cases.fmc.show`, `cases.fmc.print` (A4, copied from the old index-card print, QR in the corner, ruled blank lines), `fmc.scan` (the QR target, phone-friendly). The log merges workflow movements (automatic) with QR scans, numbered S/N.
- **QR log rule**: log in needs a unit and a reason; the file must be logged out before the next log-in. Units = every desk + one "Other" (required name, normalised; "kamma" typed as Other resolves to KAMMA, so no duplicate units). Does not write `file_tracker.movement_log`.
- **Standards applied** to the workflow's pages: S/N on every table; Plot No., District, LGA, Location order on intake, file page and card; row actions as dropdowns (shared `partials/_row_actions`, fixed-position so tables can't clip it); text normalisation helper `App\Services\CadastralDept\Normalise`.
- Checked: 16 unit tests; full FMC run in a rolled-back transaction; all pages load before the migration; old module pages still load.
- Remaining: existing records mix "KUYAN TA INNA" (district, upper case) with "Kumbotso" (LGA, title case). Normalising stored data needs a previewed, approved clean-up (standard 3); not done.

## Step 7 tables created, step 8 drafted (2026-10-10)

- `2026_10_10_110000_create_cadastral_dept_fmc_tables` run: `cadastral_fmcs`, `cadastral_fmc_logs`, and `plot_no` / `district` / `lga` on `cadastral_cases`. The FMC is live (still not in the sidebar).
- Step 8: charting card draft for the client, `CADASTRAL_CHARTING_CARD_DRAFT.html` / `.md`. Waiting on the client's marks and 8 questions before building.

## Built: steps 8 and 9 (2026-10-10)

User: "9 OKAY"; action menus use a three-dots icon (now in CLAUDE.md standard 4); charting questions: "go with what we have for now".

- **Step 9, Create Correspondence File.** Migration `2026_10_10_120000_add_correspondence_columns` (run): `corresponding_fileno` + `shadow_fileno` on `fileNumber` and `oss_applications`, `shadow_fileno` on `cadastral_cases`. No backfill. `CorrespondenceColumns::stamp()` fills them wherever a correspondence file is created or matched: the legacy screen (`MlsFileNoMatchingController::store`), the old module's `CorrespondenceFiles::ensureFor`, and the new workflow. Legacy screen renamed "Create Correspondence File" (sidebar label, page title, button). At AD Cad Report, "Create Correspondence File" runs the old module's `ensureFor` unchanged (matches first, creates only when none) and ticks the task; manual ticking is refused.
- **Step 8, charting card.** Migration `2026_10_10_130000_create_cadastral_dept_charting_tables` (run): `cadastral_chartings`, `cadastral_charting_beacons`. Saving the card ticks the step's charting task (charted / chart_first / chart_second / charting_checked; manual ticks refused). Stages: Conversion single, Direct 1st (AD Cad Info) and 2nd (AD Cad Report, shows the 1st, "Agrees?"), Verification check at both desks ("Matches the record?"). Not charted needs a reason (list + one Other); falls on an existing plot needs files picked with the selector; KLAES suggests other charted files on the same plot + district (new cards and old `cadastral_charts`; placeholder plots ignored). Beacons optional; area by shoelace (old `AreaCalculator`) from 3+. Corrections are new versions (same charting no.). Flags show on the file page; the file still moves on. Tables: Charting tab (Information · 1st, Reports · charting, Verification checks) with S/N and dots menus. Card view and A4 print.
- Defaults standing in for the 8 client questions: carry on with flags; UTM 32N Minna; metric and imperial; 2nd confirms but can correct; CH-YYYY-NNNNN; printable.
- Checked: 16 unit tests; full run in a rolled-back transaction (charting at all stages, versioning, matched and created correspondence with the columns stamped, legacy screen title); all pages load after both migrations; old module 38 pages load.

## Built: step 10, Field Jacket / job / surveyor / I to S (2026-10-10)

- **Migration** `2026_10_10_140000_create_cadastral_dept_field_jackets` (NOT run yet): `cadastral_field_jackets` (one per case, `field_jacket_no` FJ-YYYY-NNNNN, links job + current surveyor, property snapshot, print count), `cadastral_field_jacket_assignments` (surveyor history with the reassignment reason), and `cadastral_case_id` + `field_jacket_no` on the old module's `cadastral_survey_jobs`. The code works before it runs (jacket buttons hidden).
- **At AD P&D** (Conversion `pnd_instruction`): "Commission field jacket" picks a surveyor from the old directory (`cadastral_surveyors`; inactive ones hidden, licence problems shown) and registers the survey job in `cadastral_survey_jobs` with the old numbering (`KN/CAD/YYYY/NNNN`, still the placeholder format). "Issue I to S" writes the job's I to S (`ITS/YYYY/NNNN`, old columns), prefilled text, refused for a surveyor whose licence is not current. Each ticks its task; manual ticks refused.
- **Surveyor locked, reassignable**: only via the jacket page, reason required, history kept; after the I to S is issued the instruction is re-addressed and the new surveyor must be licensed.
- **Leaving the field step** marks the job Submitted.
- **Pages**: Field Jackets tab (S/N, filters, dots menu), jacket page (details, assignment history, reassign), jacket cover print (A5, QR to the file), I to S print (copied from the old module, adds the jacket number, plot first).
- **Old Survey Jobs screen**: S/N, Field Jacket and Plot No. columns, dots menu (replacing loose icons); a workflow job's surveyor is locked there and its I to S can only be issued from the workflow.
- Defaults standing in for Q9: FJ-YYYY-NNNNN; job/I to S formats unchanged from the old module; I to S text is the old module's printed instrument.
- Checked: full run in a rolled-back transaction (all refusals, commission, reassignment, I to S, prints, old screen, field return); all pages load before the migration; 16 unit tests.

## Step 10 migrated; built: step 11, Fees P&D bill (2026-10-10)

- `2026_10_10_140000_create_cadastral_dept_field_jackets` run (field jackets live; surveyors directory still empty).
- **Step 11, no migration.** At AD P&D's billing step (Conversion and Direct), "Make the Fees P&D bill" opens one page (`cases.bill.show`) merging the old Area & Pillars, Descriptions and Fee Calculator screens. It writes the old module's own records: one `cadastral_plan_descriptions` row per file (matched by file number, so the old screens show the same record), `cadastral_pillars` (replaced rows soft-deleted), and `cadastral_bills` issued by `CadastralBillCalculator::issue()` from the official sheet (Proposed column; Q1–Q3 flags as before). No payment.
  1. Area and beacons: prefilled from the file's charting card (2nd > single > 1st > check); area typed (m², ha, acres) or worked out from 3+ beacons; ownership government/private.
  2. Description: written from the old templates, then edited and saved.
  3. Fees: delay, transport band or km, field work, office work, plan prints; live server preview; "Issue bill" ticks `bill_generated` (manual tick refused). Re-issuing supersedes the unpaid bill (old rule) and updates the task note.
- P&D Bills tab (S/N, filters, dots menu); bill print copied from the old module.
- **Deeds Bill Balance**: `bill-balance.cadastral-fee` returns the newest Issued cadastral bill; picking a file fills "Survey Fees" with its total, locks it and names the bill under the field (the cadastral bill wins over the ST fee mapping for that field). Without a bill the field stays editable.
- Checked: full run in a rolled-back transaction (refusals, prefill, area from beacons and typed, description, preview, issue, re-issue, Deeds lookup, prints, listings, read-only after moving on); pages load; 16 unit tests. The Deeds form's browser fill is syntax-checked, not run in a browser.

## Built: step 12, Send to Deeds with checks (2026-10-10)

- **Migration** `2026_10_10_150000_create_cadastral_correspondence_custody` (NOT run yet): `cadastral_correspondence_custody`, one row per Attach / Detach (step, correspondence and CSF numbers, note such as where it was archived, who, when). Before it runs, the detach/attach tasks stay manual ticks and the "detached" check reads "not tracked yet".
- **Registry Attach / Detach** (`cases.correspondence.custody.save`): at `registry_to_land` and `registry_dispatch` "Detach correspondence file", at `registry_receive_back` "Attach correspondence file", and at Verification's `registry_return_requester` "Detach". Each ticks its task (manual ticks refused once the table exists). Refused with no correspondence file, detaching twice, or attaching something not detached. History table on the file page.
- **Send to Deeds**: `registry_dispatch` now has `checks` (fmc, charting, correspondence, bill, detached) and `action_label` "Send to Deeds"; Verification's return has `checks: [detached]`, label "Return main file to requester". `DeedsDispatchChecks` runs them: FMC commissioned; a current card for every charting stage the workflow uses; correspondence file on the case; an Issued Fees P&D bill; correspondence file detached. `CaseWorkflow::advance` refuses with the failing list; the file page shows a pass/fail list with what was found; the Registry desk menu shows "Not ready: …" instead of the action until all pass.
- Config keys documented in the config header; unit test checks every configured check is known and the labels.
- Checked: full run in a rolled-back transaction (detach without correspondence, manual tick refused, detach/attach round trip through Land, checks failing then passing, Send to Deeds closes the case as completed, Verification return); pages load; 17 unit tests.
- Note: a Verification case takes its correspondence number from intake but not its CSF number, so its custody rows show the main file number.

## Step 12 migrated; built: step 13, sidebar (2026-10-10)

- `2026_10_10_150000_create_cadastral_correspondence_custody` run.
- User's choices: desk entries only; Cad - Records gate (no new roles); move both Index Cards entries; "Digital Registry" = File Digital Library – Doc-WARE.
- `admin/menu/partials/modules/cadastral.blade.php` (CRLF kept):
  - New **Cadastral Department** group after Dashboard: one entry per internal desk (Registry, D Cad, DD Cad, AD Cad Info, AD Cad Report, AD P&D, AD Cad), each with its open-file count (cached 60 s; a failure hides counts only). The group shows for `$cadModule` (Cad - Records or Supper Admin). The other workflow pages are reached from the desk pages' tabs.
  - **Index Cards for File Movement** and **Property Index Cards Assistant (Legacy Records)** moved from Cadastral Information to Cadastral Report, same gates; Cadastral Report now shows for the legacy role too (its report links keep the module gate); Cadastral Information is module-only.
  - **File Digital Library – Doc-WARE** moved into the Registry group, still gated by Cad - Digital Archive.
  - Nothing removed or hidden; old Intake Queue, Survey Jobs and Plans & Descriptions entries unchanged.
- Checked: sidebar rendered for Cad - Records, legacy-only, archive-only and combined roles (entries and placement as above); desk page lights its entry; dashboards and index cards pages load. Pre-change copy kept in the session scratchpad.
