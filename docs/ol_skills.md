---
name: klaes-expert
description: Specialized skills for managing the KLAES GIS EDMS Laravel project, including SQL Server integration, ST file numbering, and EDMS workflows.
---

# KLAES Expert Skill

This skill enables the agent to operate as an expert developer on the KLAES GIS EDMS system. It encapsulates project-specific architectural patterns, database conventions, and specialized workflows.

## 🏗️ Architecture & Core Components
- **Framework**: Laravel 9 Monolith.
- **Database**: Primary data is on **SQL Server (`sqlsrv`)**. MySQL is for legacy/read-only use.
- **Prop ID (Unique Identifier)**: A critical 12-digit identifier that links `pra`, `pic`, `CofO_staging`, `caveats`, and `file_history_staging`. Always use `PropertyIdAllocationService` to ensure consistency.

## 💾 Database Conventions
- **Explicit Connection**: Every Eloquent model must declare `protected $connection = 'sqlsrv';`.
- **Primary Keys**: Avoid simple `MAX(id) + 1` logic. Use services for sequential ID generation to ensure concurrency safety.

## 🏢 Application Hierarchy & Domain Logic

### 1. Application Types (PUA vs SUA)
- **Parented Unit Applications (PUA) `is_sua_unit = 0`.**: Linked to `mother_applications` via `main_application_id or application_id in some places`.
  - **Planning Inheritance**: PUA units can inherit/bypass individual planning recommendation status if the **Primary (Mother) Application's** Planning Recommendation Status is 'Approved'.
- **Standalone Unit Applications (SUA) `is_sua_unit = 1`.**: Processed independently. `is_sua_unit = 1`.

### 2. Entity-Customer Management
- **Relationship**: 1 Entity can have multiple Customers.
- **Types**: `Individual` (stores passport), `Corporate` (stores logo), `Multiple`.
  - **Convention**: Always store as `'Multiple'` in the database, but display as `'Multiple Owners'` in the UI. Ensure `EntityService` mappings enforce this on input.
- **Similarity Detection**: Always check for similar entities before creation (find-similar API) to prevent data duplication.

### 3. Registration Workflow (RDS & CoR)
- **Strict Sequence**: **RDS (Registered Document Sheet)** must be generated BEFORE **CoR (Certificate of Registration)**.
- **ST Assignment Requirement**: `Sectional Titling CofO` RDS generation is DISABLED until the corresponding `ST Assignment (Transfer of Title)` RDS for that `fileno` is exists.

### 4. Caveat System
- **Registration**: Format `REG/YYYY/P{page_number}`.
- **Caveat Number**: Format `CAV/YYYY/{sequential}`.
- **Linkage**: Must always link to a `prop_id` to ensure caveats show up in property history and legal searches.

## 📂 File Numbering & Normalization

### 1. Supported Formats
- **ST**: `ST-{LAND_USE}-{YEAR}-{SERIAL}` (Primary) or `ST-{LAND_USE}-{YEAR}-{SERIAL}-{SEQ}` (PUA/SUA).
- **MLS**: `{PREFIX}-{YEAR}-{SERIAL}` or `CON-{PREFIX}-{YEAR}-{SERIAL}` for conversions.
- **KANGIS**: Legacy patterns like `KN{4-DIGIT}`.

### 2. Normalization Pipeline (8-Step Integrity)
Always normalize file numbers before querying or saving:
1. **Trim & Uppercase**: Remove spaces and standardise case.
2. **Character Correction**: Replace `Ø/∅/⊘` with `O`; `/`, `=`, `_` with `-`.
3. **Split Detection**: Identify and separate concatenated file numbers.
4. **Prefix Normalization**: Fix `CN` → `CON`; correct `C0M` → `COM`, `R3S` → `RES`.
5. **Year Normalization**: Expand 2-digit years. Correct `18XX` years to `19XX`.
6. **Serial Cleaning**: Replace `O` with `0` and `I/l` with `1` in numeric positions.
7. **Pattern Matching**: Classify as ST, MLS, or KANGIS.
8. **Validation**: Enforce pattern integrity.

## �️ EDMS & Registry Architecture

### 1. Dynamic Registry Model
- **Source of Truth**: Registries are defined in the `registries` table, NOT hardcoded in arrays.
- **Key Columns**: `name` (Display Name), `code` (Folder Prefix), `is_active`.
- **Usage**: Always fetch active registries via `Registry::where('is_active', true)->get()` for dropdowns.

### 2. Standardized Folder Structure
- **Root**: `storage/app/public/EDMS/`.
- **Sub-Directories**:
  - **Blind Scans**: `EDMS/BLIND_SCAN/{Registry_Name}_Raw/{FileNumber}/{PaperSize}/`.
    - *Usage*: Initial upload point for unindexed raw scans.
    - *Example*: `EDMS/BLIND_SCAN/Lands_Registry_Raw/ST-RES-2024-1/A4/`.
  - **Scan Uploads**: `EDMS/SCAN_UPLOAD/{Registry_Name}/{FileNumber}/{PaperSize}/`.
    - *Usage*: Verified uploads ready for processing.
    - *Example*: `EDMS/SCAN_UPLOAD/Lands_Registry/ST-RES-2024-1/A4/`.
  - **Page Typing**: `EDMS/PAGETYPING/{Registry_Name}/{FileNumber}/{PaperSize}/`.
    - *Usage*: Files currently being indexed/typed.
    - *Example*: `EDMS/PAGETYPING/Lands_Registry/ST-RES-2024-1/A4/`.

### 3. Workflow
- **Flow**: `Blind Scan` → `Scan Upload` → `Page Typing`.
- **Registry Names**: Folder names use the registry name with spaces replaced by underscores (e.g., "Lands Registry" → "Lands_Registry").
- **Paper Sizes**: standard subfolders `A4` or `A3` or both are created inside the file number folder.

## 📊 Operational Intelligence
- **Activity Monitoring**: System tracks real-time sessions (`user_activity_logs`) with heartbeat polling.
- **Audit Trails**: Every CRU operation should be logged via `AuditService::logAction()`.

##  Routing Strategy
- **Organization**: `routes/app3.php` (New Features), `routes/apps.php` (ST), `routes/apps2.php` (Subsystems).
- **Precedence**: Place AJAX/JSON endpoints ABOVE wildcard routes to prevent capture.

## 🛠️ Maintenance Workflow
- **EDMS Pipeline**: Indexed → Uploaded → Pagetyped → QC Passed → Archived (Doc-WARE).
- **Cache Clearing**: `php artisan config:clear` followed by `php artisan cache:clear`.

## 🗂️ MLS File Number Generator

### 1. Core Functionality
- **Purpose**: Generates, manages, and tracks MLS file numbers (`AG-YYYY-SERIAL`, `CON-YYYY-SERIAL`, etc.).
- **Modes**:
  - **New Generation**: Creates single or batch file numbers.
  - **Capture Existing**: Records legacy file numbers manually.
  - **Migration**: Tools for migrating from old systems.

### 2. Batch Management
- **Grouping**: Files generated together are grouped by `batch_no`.
- **Display**:
  - **Ranges**: Batched files are displayed as ranges (e.g., `AG-2026-8-12`) using `batch_first_file` and current file number logic.
  - **Badging**: Ranges are visually distinct with specific label colors (e.g., Indigo badge).
  - **Tracking ID**: Hidden for batch records (displayed as `-`), only shown for single records.

### 3. Printing Logic (Printer Manager)
- **Modal Interface**: `PrinterManager` modal handles all print operations.
- **Modes**:
  - **Individual (Single Sheet)**: Prints the specific selected file.
  - **Batch**: Prints the entire batch (if applicable).
  - **Constraint**: When opening Printer Manager from "Batch Details" view, **Batch Mode is DISABLED** to force single-sheet printing, ensuring correct file number context.
- **Document Types**:
  - `Commissioning Sheet`: For internal processing.
  - `Application for Conversion`: For applicant use.
- **Watermarking & Status**:
  - **Logic**: 
    - **1st Print** (Count 0 -> 1) = **ORIGINAL** status/watermark.
    - **Subsequent Prints** (Count > 1) = **CERTIFIED TRUE COPY** status/watermark.
  - **Synchronization**: Batch prints count towards the individual file's print history.
  - **Real-time Sync**: The Printer Manager modal automatically refreshes its status immediately after a print is recorded (`checkPrintStatus()` called on success).

### 4. Database Structure
- **Table**: `fileNumber` (Primary storage).
- **Columns**: `mlsfNo` (File Number), `batch_no` (Grouping), `SOURCE` ('MLS_Commissioned', 'MLS_Commissioned_Batch').
- **Tracking**: `print_logs` table records every print action (`reference_number`, `print_type`, `document_type`).
 
### 5.  LAND USE BASED FILE NUMBER PREFIX MAPPING
       direct mappings
        'RES': 'Residential',
        'COM': 'Commercial',
        'IND': 'Industrial',
        'AG': 'Agriculture',
        ### conversion mappings
        'CON-RES': 'Residential',
        'CON-COM': 'Commercial',
        'CON-IND': 'Industrial',
        'CON-AG': 'Agriculture',
        ### Recertification mappings
        'RES-RC': 'Residential',
        'COM-RC': 'Commercial',
        'IND-RC': 'Industrial',
        'AG-RC': 'Agriculture',
        ### CON-RECERTIFICATION mappings
        'CON-RES-RC': 'Residential',
        'CON-COM-RC': 'Commercial',
        'CON-IND-RC': 'Industrial',
        'CON-AG-RC': 'Agriculture',
        'CON-RES-RC': 'Residential',
        'CON-COM-RC': 'Commercial',

   
Document logo 
 header right 
http://app.klaes.ng/assets/logo/ministry1.jpg

header left
http://app.klaes.ng/assets/logo/ministry2.png


FOOTER LOGOS
right logo  
http://app.klaes.ng/assets/logo/Left_Logo.png
 left logo

 http://app.klaes.ng/storage/upload/logo/logo.png

kangis logo
http://app.klaes.ng/assets/logo/kangis.jpg