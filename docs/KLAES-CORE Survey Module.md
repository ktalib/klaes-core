**KLAES-CORE Survey Module – Full Implementation Specification**

**Important note (as instructed):**  
Subdivision, Merger, Extension, and Change of Purpose (Parcel Updates) are **completely separate** from the main Survey Module sidebar and workflows. They will be implemented as their own module/area and only referenced where needed for routing or visibility. They are not nested inside Compensation, GKN, Geo-Informatics, or Examination/Verification.

---

### 1. Final Survey Module Sidebar Structure

```
15. SURVEY
├── a. Dashboard
├── b. Compensation
│   ├── i. Records
│   │   ├── 1. CO Files (table)
│   │   ├── 2. Allocation Records
│   │   └── 3. OP Verification
│   ├── ii. Project Management
│   │   ├── 1. Commission CO File
│   │   └── 2. All Projects
│   ├── iii. Compensation Cases
│   │   ├── 1. Beneficiaries
│   │   ├── 2. Economic Trees
│   │   ├── 3. Cases
│   │   ├── 4. Compensation Calculator
│   │   └── 5. OP Allocation
│   └── iv. Complaints
├── c. GKN
│   ├── i. Applications (tabs or typed table)
│   │   ├── GKN Files
│   │   ├── LPKN Files
│   │   ├── LPPL Files
│   │   └── Misc KN Files
│   ├── ii. Layout Plan
│   ├── iii. Instruction to Survey
│   ├── iv. Surveyors Report
│   ├── v. List of Coordinates
│   └── vi. Computation Field Observations
├── d. Geo-Informatics
│   ├── i. Tools
│   ├── ii. Plot Allocation
│   └── iii. Survey Plan Extraction
├── e. Examination / Verification
│   ├── i. Register File
│   ├── ii. Boundary Dispute
│   ├── iii. Old Surveys
│   ├── iv. Control Points
│   ├── v. Beacon Register
│   ├── vi. Coordinate Register
│   └── vii. Surveyors Report (Changes)
├── f. File Digital Library – Doc-WARE
├── g. Digital Archive
│   ├── i. File Tracker Dashboard
│   ├── ii. File Tracker (Archive)
│   ├── iii. Quick Search
│   ├── iv. Log a File
│   └── v. DMS Update
└── h. Survey Reports (graphs, charts & tables)
```

Parcel Updates (Subdivision / Merger / Extension / Change of Purpose) live **outside** this tree as a separate top-level or departmental area.

---

### 2. Compensation Unit – Full Implementation

**Purpose:** Government Acquisition, Compensation processing, and Occupancy Permit (OP) generation.

#### 2.1 Records Section
- **CO Files Table**  
  Columns: CO File No, Project ID, Project Name, Commission Date, Status, Surveyor(s), Total Portions, Size of Acquisition, Last Updated, Actions.  
  - Supports data-capture for existing/legacy CO files.  
  - Filter by Project, Status, Date range.  
  - Action: View / Edit / Open Project / Send to OP Verification.

- **Allocation Records**  
  Linked to projects. Shows portion allocations per surveyor.

- **OP Verification**  
  - Receives verified OPs.  
  - Routes to Deeds OP Verification when ready.  
  - Status colours and freeze logic apply.

#### 2.2 Project Management
- **Commission CO File**  
  Format: `Prj-YYYY-SerialNo` (auto-generated).  
  Creates the master CO file and links it to a Project.

- **All Projects / Create Project**  
  Fields:
  - Project ID (auto)
  - Project Name / Title
  - Project Type
  - Location
  - Size of Acquisition (mandatory)
  - Surveyor Assignment (multi-select)
    - For each surveyor: Number of Portions allocated + Size per portion
  - Compensation Type (Cash-for-Trees / Plot-for-Plot / both)
  - Status (Draft → Commissioned → Active → Closed)
  - Created By / Date

  When a surveyor opens their field form, the system generates exactly the number of portion forms allocated to them. Each form carries its own size.

#### 2.3 Compensation Cases
- **Beneficiaries**  
  Table + Create Beneficiary form.  
  Fields: Full Name, Phone, Address, Bank Details section  
  - Account Name (pre-filled from beneficiary name, editable)  
  - Account Number  
  - Bank (dropdown)  
  Future: Name verification API hook.

- **Economic Trees**  
  Pre-populated master list (Common Name + Botanical Name).  
  Create / Edit / Import. Used by calculator and field app.

- **Cases**  
  - Create Case (desktop form identical to Mobile App 1).  
  - Mobile App 1 (Fieldwork): offline/online, geo-tagged photos of assets, economic tree verification, beneficiary data capture → auto-sync to Cases.

- **Compensation Calculator** (two tabs)  
  **Tab 1 – Cash for Trees**  
  - Rates table (configurable).  
  - Per beneficiary: Number of each tree type × rate → Total.  
  **Tab 2 – Plot for Plot**  
  - Columns: Beneficiary (appears once), Owner Plots, Government Plots, Ratio (e.g. 50/50).  
  - Calculation driven by project compensation type and portion data.

- **OP Allocation**  
  After successful compensation + asset verification → Generate Occupancy Permits.  
  Generator produces OPs linked to beneficiaries and project.  
  Status moves to OP Verification.

#### 2.4 Complaints
- Log grievances arising from compensation or OP allocation.  
- Status-driven: Open → Under Investigation → Resolved / Escalated.  
- Link to original Case / Beneficiary / Project.

#### Compensation Workflow Phases
1. File Commissioning & Records Management  
2. Compensation Case Processing (+ Mobile App 1)  
3. OP Allocation & Dispute Resolution

---

### 3. GKN Unit – Full Implementation (Government Files Only)

**Purpose:** Government layouts, LPKN, LPPL, Misc KN, and Government Survey Instructions.

#### 3.1 Applications
- Single table or tabbed view with Type (GKN / LPKN / LPPL / Misc KN) + colour coding.  
- **Commission Application Files** button (primary action).  
- Columns: File No, Type, Project/Reference, Date Received, Status, Source, Actions.

#### 3.2 Layout Plan
- Table + Create Layout Plan.  
- Linked to commissioned application files.

#### 3.3 Instruction to Survey (I to S)
- Table.  
- Sources:  
  - Auto-received from Cadastral  
  - Manually created inside GKN  
- Source field clearly shown.  
- Create form reuses the Cadastral “Instruction to Survey” pattern.

#### 3.4 Surveyors Report
- Table + Create SR (form based on the Word document template already shared).  
- Generate / Curate / Archive.

#### 3.5 List of Coordinates
- Table + Create Coordinates.  
- Official list stored and linked to the survey.

#### 3.6 Computation Field Observations
- Receives data from Mobile App 2 (Field Observations).  
- Desktop Create also available for paper-to-digital entry.  
- Processes raw field data for both Government and Private acquisitions.

#### GKN Workflow Phases
1. File Reception & Layout Planning  
2. Survey Instruction & Field Execution (Mobile App 2)  
3. Computation & Reporting

---

### 4. Geo-Informatics Unit

**Purpose:** Purely geospatial / spatial data handling.

- **Tools** – System GIS tools management.  
- **Plot Allocation** – Generates Plot Allocation List from approved layouts.  
- **Survey Plan Extraction**  
  - Application Plan → Final Survey Plan  
  - Export formats: PDF, DWG, Shapefile  
  - Integration targets: KLAES-GIS (KANGIS) and Cadastral Department.

Leave largely as-is for the first implementation pass; expand only when spatial tooling is ready.

---

### 5. Examination / Verification Unit – Full Implementation

**Purpose:** Quality assurance, verification, and boundary management for files from Cadastral and internal GKN files.

#### 5.1 Register File
- Reuse the exact same register pattern as Cadastral (Conversion / Direct / Verification).  
- **Only Verification path is active** by default (grey out or hide Conversion & Direct if not needed; keep them available for future).  
- Auto-registration rules:  
  - Any file sent from Cadastral for Verification appears automatically.  
  - The same file can later appear again for Examination (two separate lines / records preferred).  
  - Direct registration also allowed.  
- Status colours and freeze logic identical to Cadastral.  
- Files from Cadastral sent for Verification & Examination show here with clear source and purpose.

#### 5.2 Boundary Dispute
- Table + Create.  
- Capture complaint / dispute details → investigation workflow.

#### 5.3 Old Surveys
- Verification against historical survey records.  
- Create / Search / Link.

#### 5.4 Control Points
- Separate from Beacon Register.  
- Manage official Survey Control Points used for geodetic referencing.  
- Structure can mirror Beacon Register but is distinct.

#### 5.5 Beacon Register (see dedicated section below)

#### 5.6 Coordinate Register
- Consolidated view of all coordinates generated across the module.

#### 5.7 Surveyors Report (Changes)
- Generated when discrepancies are found between submitted plan and existing records.  
- Printable report detailing required changes.

#### Final Clearance
- Once verified and cleared → route file back to Cadastral Department or the original requesting unit.

#### Examination / Verification Phases
1. File Registration & Verification  
2. Dispute & Control Management  
3. Beacon Generation & Final Reporting

---

### 6. Digital SURCON Beacon Register – Full Implementation

**Register Header**  
- Register No: 201845 (or system-generated)  
- Quarter: [Select]  
- Month: [Select]  
- Total Number Issued Per Page: auto-calculated

**Table Columns**  
TE | Issued Number | Plan No | Surveyor’s Name | Registration | Location | Teller No | Amount (₦) | Date Received | Signature | Remark

**System Behaviour**
1. **Auto-Population**  
   When Examination/Verification Unit generates a Beacon:  
   - Issued Number, Plan No, Surveyor’s Name, Location, Amount  
   are pulled automatically from the linked Survey File + Payment (Rev-M) module.

2. **Digital Signatures**  
   Signature column + footer use KLAES-CONTROL digital signatory module.  
   Officers authenticate via 2FA / Biometrics.

3. **Audit Trail**  
   Entered By / Reviewed By / Audited By automatically log:  
   User ID, Timestamp, IP Address.  
   No physical signature required.

4. **Export & Print**  
   Registry/Records can export a PDF that perfectly mimics the physical layout for official archiving or external audits.  
   Table expands dynamically as new beacons are issued.

---

### 7. Supporting Modules

- **File Digital Library – Doc-WARE**  
  Document storage and retrieval for all survey-related files.

- **Digital Archive**  
  File Tracker Dashboard, Archive view, Quick Search, Log a File, DMS Update.

- **Survey Reports**  
  Graphs, charts, and tables only (no free-text heavy reports in this section).

---

### 8. Parcel Updates (Separate Module)

**These are independent of the Survey Module sidebar.**

Supported update types:
- Subdivision → Mother / Child relationship
- Merger → Child relationship
- Extension → Adult (1:1)
- Change of Purpose → Adult (no child)

Implementation notes:
- Own menu / area (exposed under Deeds, Land, Cadastral, Survey, Physical Planning, etc. as needed).
- Mother–Child linking logic.
- For Subdivision with many plots: each child is saved individually under the mother.
- Status and action menus are independent.
- Only referenced by Survey Module when a file needs to be routed or when historical relationships must be shown.

---

### 9. Cross-Cutting Rules

- Status-driven action menus and record freezing (same pattern used in Cadastral).
- Source tracking on every file (Cadastral, GKN internal, Direct, Mobile App, etc.).
- Mobile App 1 → Compensation Cases  
  Mobile App 2 → GKN Field Observations / Computation.
- All Create forms that already exist in Cadastral (Instruction to Survey, Register File, etc.) are reused with minimal variation.
- Digital signatures and full audit trail everywhere official documents are produced.
- Project ID / CO File / Beacon Issued Number generation follows the established serial patterns (Prj-YYYY-SerialNo, etc.).

---

### 10. Implementation Priority Order (recommended)

1. Compensation → Project Management + CO Files + Beneficiaries + Cases + Calculator + OP Allocation  
2. Examination/Verification → Register File (auto-ingest) + Beacon Register (auto-population + digital sign + PDF export)  
3. GKN → Applications (tabs/typed table) + Instruction to Survey + Surveyors Report + Field Observations  
4. Geo-Informatics (basic Plot Allocation + Plan Extraction)  
5. Supporting (Digital Archive, Survey Reports, Doc-WARE)  
6. Parcel Updates (separate) as a parallel track
