**CHANGE REQUEST**  
**KLAES – Survey Module Workflow Updates**  

**Reference:** KLAES Survey Module – Workflow_V02 + New Recording 286 + Compensation Complaint Form  
**Date:** 29 October 2026  
**Priority:** High  

---

### 1. Sidebar / Menu Structure Changes

**Compensation**
- Rename **Compensation Cases** → **Compensation Management**
- Move **Resettlement Plot Allocation** out of Compensation Cases and make it a **standalone** menu item under Compensation
- Complaints: Change from sidebar items to **three tabs** inside Complaints

**GKN**
- Rename **Layout Plan** → **Public Survey**
  - Public Layout Plans
  - Layout Records
- Add **Passing & Checking (Final Survey Plan)**
- Sketch Plan must be an **attachment** under Computation Field Observations (not a sidebar item)

**Geo-informatics**
- Rename **Plot Allocation** → **Resettlement Plots**
- Add **Basemap (Satellite Imagery)**
- Add **SDI**
  - Map Request
  - Production Unit

**Examination/Verification**
- Rename **Compensation Check** → **Compensation Status**
- Boundary Dispute must support:
  - Property Boundary
  - Public Boundary → LGA Boundary / Interstate Boundary

**Other Departments (Cadastral)**
- Compensation Status link must be updated
- Survey attachments and responses must be visible/receivable from Cadastral side

---

### 2. Compensation Unit – Detailed Changes

#### 2.1 Renames & Structure
| Old Name              | New Name                        | Notes |
|-----------------------|----------------------------------|-------|
| Beneficiaries         | Farmers                          | Treat as Customers |
| Cases                 | Lands                            | One Farmer can own multiple Lands |
| Economic Trees        | Economic Trees + Category        | Add **Mature** and **Early** |
| OP Allocation         | Resettlement Plot Allocation     | Standalone menu |
| Compensation Cases    | Compensation Management          | — |

#### 2.2 Farmers ↔ Lands Relationship
- Farmer is the primary entity (like Customer).
- One Farmer can have many Lands.
- Each Land must have its own Land ID.
- System must support linking one Farmer ID to multiple Land IDs.
- Compensation Calculator must correctly handle multi-land farmers.

#### 2.3 Resettlement Plot Allocation (Standalone)
- Contains:
  - **OP** → for Resettlement only
  - **Letter of Grant** → for Direct Allocation
  - **Resettlement Calculator** → land-use based formulas (High Density / Low Density residential, etc.)

**Resettlement Calculator formulas (from the SG, 29 Oct 2026)**

Number of plots = Area in hectares ÷ plot size for the density:

| Density | Formula | Plot size |
|---|---|---|
| HD – High Density | Area (ha) ÷ 0.0454 | 0.0454 ha (454 m²) |
| MD – Medium Density | Area (ha) ÷ 0.0714 | 0.0714 ha (714 m²) |
| LD – Low Density | Area (ha) ÷ 0.1667 | 0.1667 ha (1,667 m²) |

*Example: 2.5 ha at HD = 2.5 ÷ 0.0454 = 55.07 plots.* Rounding (down to whole plots?) and how the 50:50 Government/farmer split applies are still to be confirmed. The **Compensation Calculation** formula has not been received yet.

#### 2.4 Complaints (Three Tabs)
Use the printed **Compensation Complaint Form**, as amended by hand on 29 Oct 2026. Each tab is one Section 3 category; the form itself is shared.

**Section 1 – Complainant Details** (additions marked ✚)
- Full Name of Farm/Property Owner *
- ✚ Farm No.
- ✚ Section
- ✚ Project – **Layout**, or a **Facility / Public** project (Road, School, etc.)
- Phone Number *
- Email Address (Optional)
- Name of Authorised Representative (if applicable)
- Relationship to Owner

**Section 2 – Property & Compensation Details**
- File Reference / CO Number *
- Map Sheet No (MS No) *
- Location / District *
- ✚ Compensation is one of:
  - **A – Monetary** → Original Compensation Amount (₦) *
  - **B – Land to Land** → No. of Plots *

**Tab 1 – Category 1: Inadequate Compensation**
- Details of Discrepancy *
- **A (Monetary)** → Expected / Correct Valuation (₦) *
- **B (Land to Land)** → Expected / Correct No. of Plots *

**Tab 2 – Category 2: Misidentification of Farmland/Property**
- ✎ **Name of Field Officer** (was "Name of the Authorized Person Who Made the Error")
- Correct Plot / Coordinates of the Actual Farm
- Details of the Misidentification

**Tab 3 – Category 3: Boundary Dispute** (✎ heading was "Boundary Dispute for Compensation")
- ✎ Type of Boundary Dispute (replaces Property Boundary / Public Boundary (LGA) / Public Boundary (Interstate)):
  - **Overlap**
  - **Absent**
  - **Mis-identification**
- Name of Affected Neighbor/Party (if applicable)
- Details of the Encroachment/Dispute

> The Property / LGA / Interstate boundary types still apply to **Examination/Verification → Boundary Dispute** (§1, §5.2). Only the complaint category changed.

**Section 4 – Supporting Documents**
- ✎ Copy of Original Compensation **Card** (was "Receipt")
- Valid Identification (NIN / Voter's Card / Passport)
- Proof of Ownership / Allocation Letter
- Photographs of the Property/Farm
- Any Other Relevant Documents → Other relevant documents / reference

**Section 5 – Declaration & Signature**: unchanged.

**For Official Use Only (Survey Department)**
- Action Taken By · Date Received · Assigned To · Status · Remarks
- Status: Pending · Under Review · Resolved, ✚ plus **Overpaid** · **Underpaid** · **Adequately Compensated**
- Signature of Receiving Officer / Date

**System behaviour (built 29 Oct 2026)**
- Status flow: Pending → Under Review → Overpaid / Underpaid / Adequately Compensated / Resolved. From a finding, the complaint goes back to Under Review or on to Resolved. Every step after Under Review needs a remark, and a Resolved complaint is locked.
- Picking a Farmer, Case or Project fills in the complainant's name and phone, the location, the compensation type (from the project scheme), the original amount (the case's economic-tree total) or plots (the farmer's half of the 50:50 split), and the CO number. The compensation type and CO number lock when they come from a record.
- Every complaint prints on the official two-page layout, filled in for signing (row menu → **Print Form**). **Print Blank Form** gives the empty form for walk-ins.

---

### 3. GKN Unit Changes

- Public Survey section must display:
  - Public Layout Plans (the plans themselves)
  - Layout Records (table of records attached to each plan)
- Surveyors Report (Government) must include:
  - List of Coordinates
  - Beacon Nos
  - Computation Field Observations
  - Sketch Plan (as **attachment**)
- Add full **Passing & Checking** workflow for Final Survey Plan

---

### 4. Geo-informatics Unit Changes

- Resettlement Plots (renamed)
- Basemap (Satellite Imagery) – bring existing functionality
- SDI module:
  - Map Request
  - Production Unit  
  (Map Request feeds into Production Unit)

**SDI – built 29 Oct 2026 (ported from the ALAES / Abia SDI)**
- **Map Request** (Geo-informatics → SDI): survey staff log a request for the requesting **Kano MDA**, or type the body under Other. The form captures the contact person and phone, map type, map name and reference, formats (PDF / printed copy / DWG / Shapefile), priority and due date. The area is **drawn on the map** (rectangle or polygon), and its X/Y boundary points and bounding box are saved. A request can be edited or deleted only while it is Pending.
- **Production Unit**: the GIS work queue (high priority first, then earliest due date). Pending → **Start Production** (assign an officer) → **Upload Map & Fulfil**, or **Reject** with a reason. Closed requests can't be reopened. The produced map downloads from the request.
- **MDA & Map Type Registry**: seeded with 43 Kano State MDAs in ALAES's 7 thematic categories (custodian: Ministry of Land and Physical Planning) and 6 map types. Rows are retired rather than deleted, and duplicates are refused. **The MDA list was compiled from public sources and must be checked by the department.**
- **Land Use Map overlay (from File Indexing)**: choosing *Land Use Map* switches to satellite imagery and draws the indexed files on top. That is 130,290 files with coordinates, shown as bubbles coloured by main land use and sized by file count, with a legend and a hover breakdown. The count of land uses inside the drawn area is shown on the form and on the request page, for the Production Unit. **Caveat:** File Indexing coordinates mark the locality, not the plot (130k files on about 4,100 points), so this shows the land-use mix by locality, not parcels. Refreshed hourly.
- Not ported: the ALAES thematic viewer, layer catalogue/ingest, MDA layer grants, API/WFS and SDI dashboards.
- Produced maps are stored on the private disk (`app/survey/sdi/…`), like Survey field photos. They are not filed in EDMS, because an MDA's map has no land file.

---

### 5. Examination/Verification Unit Changes

#### 5.1 Compensation Status – Verification (Records)
- After OP registration, files come here for verification.
- Implement **Showing History** with the following rules:
  - Latest showing always appears at the top
  - Previous showings are pushed down
  - Capture per showing:
    - Surveyor Name
    - Date
    - Time
    - Images / Photographs
  - From 2nd showing onward, mandatory reason field:
    - Change of Ownership
    - Verification

#### 5.2 Boundary Disputes
- Property Boundary (private land-to-land)
- Public Boundary
  - LGA Boundary
  - Interstate Boundary

#### 5.3 Surveyors Report
- Government version remains under GKN
- Private version lives under Examination/Verification
- Both versions must contain the same core attachments (Coordinates, Beacon Nos, Computation Field Observations, Sketch Plan)

---

### 6. Cross-Module Requirements

- Cadastral module must be able to receive and display Survey attachments and responses.
- All movement of files between Cadastral and Survey units must continue to update the File Tracker / movement log.
- Compensation Status in Examination must be clearly distinguishable from the version available under Other Departments → Cadastral.

---

### 7. Implementation Notes

- Farmers should reuse or closely follow the existing Customer entity pattern.
- Showing History should be designed so it does not grow infinitely long on the UI (latest on top, older ones collapsed or limited).
- Resettlement Calculator formulas received 29 Oct 2026 (HD / MD / LD, see 2.3); the Compensation Calculation formula is still to come.
- Complaint form fields must match the printed official form exactly, as amended on 29 Oct 2026 (see 2.4).

### 8. Gaps found on review (29 Oct 2026)

Checked against *KLAES Survey Module -Workflow_V02.docx* and the transcript of *New Recording 286* (`docs/Raw Transcript – New Recording 286.m4a`, a text file despite the extension). These points are in those sources but not covered above:

1. **Economic Trees / Lands, Mature vs Early – the sources disagree.** The recording says rename the existing tree *Category* (Cash crop / Fruit crop) to **Type**, and add a new **Category = Mature / Early** on Economic Trees. The .docx instead says **Lands** are "categorized strictly as either Matured or Early" to apply the differentiated rates. *To confirm which.*
2. **Public Survey → Layout Records** (.docx): grouped by **Layout No.**, and logging *"No. of times a surveyor shows the plot to the allottee"* with the date and surveyor's name for every visit. §5.1 has the showing history only under Examination → Compensation Status.
3. **Geo-informatics plan generation** (.docx): **Application Plan** (from the plot allocation) and **Final Survey Plan** generation. **Survey Plan Extraction** exports to **PDF / DWG / Shapefile**.
4. **Examination – Final Clearance** (.docx): once verified, the file is routed back to Cadastral or the requesting unit. Compensation Status must be cleared **before final survey approval**.
5. **Basemap (Satellite Imagery)** (recording): reuse the existing map built earlier (the Sectional Titling BaseMap, `map.index`). Don't build a new one.
6. **Letter of Grant** (recording, end): it must be **generated** (a printable letter) for Direct Allocation.
7. **Compensation Complaint – Section 4 (Supporting Documents)**: neither the recording nor the .docx mentions it, but the scanned form carries a hand edit there (Receipt → Card). It is **kept**.

---
 **Updated Survey Module Sidebar Hierarchy**  
 
```
SURVEY
├── Dashboard
├── Compensation
│   ├── Records
│   │   ├── CO Files
│   │   ├── Allocation Records
│   │   └── OP Verification
│   ├── Project Management
│   │   ├── Commission CO File
│   │   └── Projects
│   ├── Compensation Management
│   │   ├── Farmers
│   │   ├── Economic Trees          (with Mature / Early category)
│   │   ├── Lands
│   │   ├── Compensation Calculator
│   │   └── Map Sheet No
│   ├── Resettlement Plot Allocation   ← Standalone
│   │   ├── OP                      (Resettlement only)
│   │   ├── Letter of Grant         (Direct Allocation)
│   │   └── Resettlement Calculator
│   └── Complaints                  ← Three Tabs
│       ├── Inadequate Compensation
│       ├── Misidentification of Farmland/Property
│       └── Boundary Dispute        (Overlap / Absent / Mis-identification)
│
├── GKN
│   ├── Applications
│   │   ├── GKN Files
│   │   ├── LPKN
│   │   ├── LPPL
│   │   └── Misc KN
│   ├── Public Survey
│   │   ├── Public Layout Plans
│   │   └── Layout Records
│   ├── Surveyors
│   ├── Instruction to Survey
│   ├── Surveyors Report (Government)
│   │   ├── List of Coordinates
│   │   ├── Beacon Nos
│   │   ├── Computation Field Observations
│   │   └── Sketch Plan             ← Attachment only
│   └── Passing & Checking (Final Survey Plan)
│
├── Geo-informatics
│   ├── Tools
│   ├── Resettlement Plots
│   ├── Basemap (Satellite Imagery)
│   ├── SDI
│   │   ├── Map Request
│   │   └── Production Unit
│   └── Survey Plan Extraction
│
├── Examination/Verification
│   ├── Register File
│   ├── Compensation Status         ← Verification (Records) + Showing History
│   ├── Boundary Dispute
│   │   ├── Property Boundary
│   │   └── Public Boundary
│   │       ├── LGA Boundary
│   │       └── Interstate Boundary
│   ├── Old Surveys
│   ├── Control Points
│   ├── Beacon Register
│   ├── Coordinate Register
│   └── Surveyors’ Report (Private)
│       ├── List of Coordinates
│       ├── Beacon Nos
│       ├── Computation Field Observations
│       └── Sketch Plan             ← Attachment only
│
├── Other Departments
│   └── Cadastral
│       ├── Compensation Status
│       ├── DSG (Approval)
│       ├── Examination Check
│       └── SG (Approval)
│
├── File Digital Library – Doc-WARE
├── Digital Archive
│   ├── File Tracker Dashboard
│   ├── File Tracker (Archive)
│   ├── Quick Search
│   ├── Log a File
│   └── DMS Update
└── Survey Reports
```

T 