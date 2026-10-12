# Cadastral sidebar: current structure

As it stands on 2026-10-11, from `resources/views/admin/menu/partials/modules/cadastral.blade.php`.

- **Old module** means a screen from the earlier Cadastral Module (`cadastral-module.*` or older routes).
- **New workflow** means a screen from the Cadastral Department workflow (`cadastral-dept.*`).
- **Gate** is the role that shows the entry. Supper Admin sees everything gated by `Cad - Records`, `Cadastral Reports` or `Cad - Digital Archive`.

## Tree

```
Cadastral
├── Dashboard
├── Cadastral Department            (new group, step 13)
│   ├── Registry
│   ├── D Cad
│   ├── DD Cad
│   ├── AD Cad Info
│   ├── AD Cad Report
│   ├── AD P&D
│   └── AD Cad
├── Registry
│   ├── Intake Queue
│   ├── Create Correspondence File
│   ├── Correspondence Files
│   └── Cadastral Print File Label
├── Cadastral Report
│   ├── All Reports
│   ├── Verification
│   │   ├── Customary
│   │   └── Statutory
│   ├── Index Cards for File Movement
│   └── Property Index Cards Assistant (Legacy Records)
│       ├── Table
│       └── Scans
├── Cadastral Information
│   ├── Charting
│   ├── Survey Jobs (I to S)
│   └── File Status
├── Plans & Descriptions
│   ├── Area & Pillars
│   ├── Descriptions
│   └── Fee Calculator
├── Other Departments
│   ├── Title Status Update
│   └── Cadastral to Land (Land 12)
├── Records
├── GIS
│   └── Survey Plan Extraction
├── Digital Archive
│   ├── File Digital Library – Doc-WARE
│   ├── File Tracker Dashboard
│   ├── File Tracker (Archive)
│   ├── Quick Search
│   ├── Log a File
│   └── DMS Update
└── Reports
```

## Entry by entry

| S/N | Group | Entry | Opens | Source | Gate |
|---|---|---|---|---|---|
| 1 | — | Dashboard | `cadastral-module.dashboard` | Old module | Cad - Records |
| 2 | Cadastral Department | Registry | desk inbox `cadastral-dept.desks.show/registry` | New workflow | Cad - Records |
| 3 | Cadastral Department | D Cad | desk inbox `…/director_cadastral` | New workflow | Cad - Records |
| 4 | Cadastral Department | DD Cad | desk inbox `…/deputy_director_cadastral` | New workflow | Cad - Records |
| 5 | Cadastral Department | AD Cad Info | desk inbox `…/ad_information` | New workflow | Cad - Records |
| 6 | Cadastral Department | AD Cad Report | desk inbox `…/ad_report` | New workflow | Cad - Records |
| 7 | Cadastral Department | AD P&D | desk inbox `…/ad_pnd` | New workflow | Cad - Records |
| 8 | Cadastral Department | AD Cad | desk inbox `…/ad_cadastral` | New workflow | Cad - Records |
| 9 | Registry | Intake Queue | `cadastral-module.registry.receipts` | Old module | Cad - Records |
| 10 | Registry | Create Correspondence File | `mls-file-no-matching.index` | Old screen, renamed 2026-10-10 | Match Existing FileNo (MLSFileNo) |
| 11 | Registry | Correspondence Files | `cadastral-module.registry.correspondence` | Old module | Cad - Records |
| 12 | Registry | Cadastral Print File Label | `cadastral_printlabel.index` | Old screen | anyone who sees the Cadastral menu |
| 13 | Cadastral Report | All Reports | `cadastral-module.reports.index` | Old module | Cad - Records |
| 14 | Cadastral Report | Verification | `cadastral-module.reports.verification` | Old module | Cad - Records |
| 15 | Cadastral Report › Verification | Customary | `cadastral-module.reports.customary` | Old module | Cad - Records |
| 16 | Cadastral Report › Verification | Statutory | `cadastral-module.reports.statutory` | Old module | Cad - Records |
| 17 | Cadastral Report | Index Cards for File Movement | `cadastral-module.index-cards.index` | Old module, moved here in step 13 | Cad - Records |
| 18 | Cadastral Report | Property Index Cards Assistant › Table | `property_index_card.index` | Old screen, moved here in step 13 | Deeds – Property Index Cards Assistant (Legacy Records) |
| 19 | Cadastral Report | Property Index Cards Assistant › Scans | `cadastral.index-cards.index` | Old screen, moved here in step 13 | same as above |
| 20 | Cadastral Information | Charting | `cadastral-module.charting.index` | Old module | Cad - Records |
| 21 | Cadastral Information | Survey Jobs (I to S) | `cadastral-module.survey-jobs.index` (Surveyors reached from here) | Old module | Cad - Records |
| 22 | Cadastral Information | File Status | `cadastral-module.file-status.index` | Old module | Cad - Records |
| 23 | Plans & Descriptions | Area & Pillars | `cadastral-module.plan-description.area` | Old module | Cad - Records |
| 24 | Plans & Descriptions | Descriptions | `cadastral-module.plan-description.descriptions` | Old module | Cad - Records |
| 25 | Plans & Descriptions | Fee Calculator | `cadastral-module.plan-description.fees` | Old module | Cad - Records |
| 26 | Other Departments | Title Status Update | `title-status.index?url=cadastral` | Old screen | anyone who sees the Cadastral menu |
| 27 | Other Departments | Cadastral to Land (Land 12) | `survey-report.index?url=cadastral` | Old screen | anyone who sees the Cadastral menu |
| 28 | — | Records | `survey_cadastral.index` | Old screen (restored 2026-10-10) | Cad - Records only, not Supper Admin |
| 29 | GIS | Survey Plan Extraction | `survey_plan_extraction.index?url=cadastral` | Old screen | Cadastral Reports |
| 30 | Digital Archive | File Digital Library – Doc-WARE | `filearchive.index?url=cadastral` | Old screen; moved here 2026-10-11 (**not committed yet**) | Cad - Digital Archive |
| 31 | Digital Archive | File Tracker Dashboard | `file-tracker.dashboard?url=cadastral` | Old screen | Cad - Digital Archive |
| 32 | Digital Archive | File Tracker (Archive) | `track-file-archive.index?url=cadastral` | Old screen | Cad - Digital Archive |
| 33 | Digital Archive | Quick Search | `create-file-tracker.quick-search?url=cadastral` | Old screen | Cad - Digital Archive |
| 34 | Digital Archive | Log a File | `create-file-tracker.index?url=cadastral` | Old screen | Cad - Digital Archive |
| 35 | Digital Archive | DMS Update | `#`, no page behind it | Placeholder | Cad - Digital Archive |
| 36 | — | Reports | `cadastral-module.analytics` | Old module | Cad - Records |

## Who sees the Cadastral menu at all

Anyone with at least one of these roles:
- Cad - Records
- Cad - GIS
- Cad - Approvals
- Cad - E-Registry
- Cadastral Reports
- Match Existing FileNo (MLSFileNo)
- Deeds – Property Index Cards Assistant (Legacy Records)
- Cad - Digital Archive
- Supper Admin

## New workflow pages with no sidebar entry

These open only from a desk or a case page:

| Page | Route |
|---|---|
| All cases list | `cadastral-dept.cases.index` |
| Register a file (intake form) | `cadastral-dept.cases.create` |
| Charting cards list | `cadastral-dept.charting.index` |
| Field jackets list | `cadastral-dept.field-jackets.index` |
| Fees P&D bills list | `cadastral-dept.bills.index` |
| Workflow home / map | `cadastral-dept.index`, `cadastral-dept.map` |


Final Proposed Sidebar

CADASTRAL
├── Dashboard
│
├── Cadastral Department
│   ├── Registry
│   │   ├── Intake Queue
│   │   ├── Attach Land File
│   │   ├── Detach Correspondence
│   │   ├── Send to Deeds
│   │   ├── Move to Compensation
│   │   ├── Move to PS
│   │   └── Move to Cad Info
│   │
│   ├── D Cad
│   │   ├── Recommend / Not Recommend
│   │   ├── Move to DD Cad
│   │   ├── Move to Compensation
│   │   ├── Move to PS
│   │   └── Move to KAMMA / Physical Planning
│   │
│   ├── DD Cad
│   │   ├── Recommend / Not Recommend
│   │   └── Move to Cad Info
│   │
│   ├── Cadastral Information          ← AD Cad Info
│   │   ├── Commission FMC
│   │   ├── 1st Charting
│   │   └── Send to Report
│   │
│   ├── Cadastral Report               ← AD Cad Report
│   │   ├── Charting
│   │   ├── Commission Correspondence File
│   │   └── Send
│   │
│   ├── Plans & Descriptions           ← AD P&D
│   │   ├── Commission Field Jacket
│   │   ├── Assign to Surveyor (I to S)
│   │   ├── Fees, Plan & Description
│   │   └── Send
│   │
│   └── AD Cad
│
├── Other Departments
│   ├── Title Status Update
│   └── Cadastral to Land (Land 12)
│
├── Records
│
├── GIS
│   └── Survey Plan Extraction
│
├── Digital Archive
│   ├── File Digital Library – Doc-WARE
│   ├── File Tracker Dashboard
│   ├── File Tracker (Archive)
│   ├── Quick Search
│   ├── Log a File
│   └── DMS Update
│
└── Reports

## Proposed sidebar: built, not enabled (2026-10-11)

- **File:** `resources/views/admin/menu/partials/modules/cadastral_workflow.blade.php`. `admin/menu.blade.php` includes it inside `@if(false)`. To switch to it, change that `false` to `true` and leave the old `cadastral` include off.
- **What each desk entry opens:** the desk's inbox filtered to the files where that action is due now (`?queue=`). The lists are defined in `config/cadastral_dept.php` under `queues`. Counts are cached for a minute.
- **Added:** an "All files at …" first entry for each desk except Registry, whose Intake Queue is already the whole inbox. Without it, some files would be on no list, for example D Cad's "Send to Cadastral Information" step.
- **Left out of the proposal (2026-10-11, approved):** Registry › Move to Compensation, Move to PS and Move to Cad Info. Registry never sends files to those desks:
  - Compensation is sent from AD Cad Report and recorded by D Cad.
  - The PS is reached after KAMMA / Physical Planning, recorded by D Cad.
  - Cad Info is reached from DD Cad / D Cad.

  D Cad keeps its own Compensation and PS lists.
- **Old screens added back (approved):**
  - Cadastral Print File Label, under Registry.
  - Index Cards for File Movement, and Property Index Cards Assistant (Legacy Records) › Table / Scans, under Cadastral Report. A user with only the legacy role sees just this entry.
  - Surveyors, under Plans & Descriptions.
- **Still left out:** the old Intake Queue, Correspondence Files, All Reports / Verification, Charting, Survey Jobs, File Status, Area & Pillars, Descriptions and Fee Calculator. Their pages still open by URL.
- **Yes/No form:** it has no entry of its own. It opens from the case page, on the "Land 12 and Yes/No form" task.
- **Wording (2026-10-11, user):** the "Move to …" entries are labelled **"Send to …"** (Send to DD Cad, Send to Compensation, Send to PS, Send to KAMMA / Physical Planning, Send to Cad Info).
- **Desk pages (2026-10-11, user):** each desk page has one table. It lists the files at the desk, then any file out with another department that the desk takes back (status "With KAMMA" and so on). Columns: S/N, Ref, File No., Title, Type, Status, Date created, Created by, Last moved, Action. Below them come the open files the desk has already sent on, view only, with the status "Sent to D Cad" (etc.) and the step they are at now; newest 200, search finds older ones. On Registry that is every open file it registered. In row menus, "Open file" is now **View**, and the "Go to … desk" link was removed.
- **D Cad and DD Cad, one link each (2026-10-11, user):** their lists (All files, Recommend / Not Recommend, Send to DD Cad / Compensation / PS / KAMMA / Cad Info) were merged into a single "D Cad" and a single "DD Cad" link. The table's Status column shows what each file needs, including files out with Compensation, PS or KAMMA. In the row menu a decision is one item: **Recommendation**, whose pop-up offers Recommend or Not recommended (the reason is required for Not recommended). The other decision steps work the same way: **Acquisition check** (Compensation, recorded by D Cad) and **Planning Advice route** (D Cad, 10 km buffer).
- **2026-10-12, user:**
  - **Create Correspondence File (Match MLSFileNo)**, the old `mls-file-no-matching.index` page, is back, under Cadastral Report next to the "Commission Correspondence File" list. Users with only the "Match Existing FileNo (MLSFileNo)" role get it as a top-level entry.
  - **Cadastral Print File Label** is a top-level entry again, open to everyone who sees the Cadastral menu, as in the old layout. It was under Registry, which only Cad - Records users see.
  - **Title Status Update** and **Cadastral to Land (Land 12)** are top-level entries. The "Other Departments" group was removed.

- 2026-10-12: new sidebar switched on for everyone; the "Cadastral Department" group was removed, so the desks (Registry, D Cad, DD Cad, Cadastral Information, Cadastral Report, Plans & Descriptions, AD Cad) sit on their own in the Cadastral menu. The Cadastral Dept header shortcuts are hidden.
- 2026-10-12: Registry is a single link too. Attach Land File, Detach Correspondence and Send to Deeds moved from the sidebar into the Registry row menu (shown on the files at that step).
