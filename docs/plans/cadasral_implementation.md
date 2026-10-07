Understood. The objective is to reconstruct the KLAES Cadastral Module implementation from the UI recording and the supporting concept note, so you can give your VS Code agent a complete implementation instruction and rebuild the module without losing the intended functionality.

I'll structure the developer specification around:

* Sidebar navigation and menu hierarchy.

* Dashboard and individual page descriptions.

* Forms, fields, dropdowns, buttons and actions.

* Workflow and approval stages.

* HTML document templates and how they should be populated.

* Database and existing KLAES module integration.

* Permissions, audit trails and notifications.

The supporting concept note identifies four principal functional units:

| Unit                  | Main responsibility                                               |
| --------------------- | ----------------------------------------------------------------- |
| Cadastral Registry    | Incoming files, correspondence files, registration and archiving. |
| Cadastral Report      | Verification, Customary and Statutory reports.                    |
| Cadastral Information | Charting, index cards, survey job numbers and file status.        |
| Plan & Description    | Area calculations, pillars/beacons, fees and land descriptions.   |

These four units will form the foundation of the implementation specification.


## Sidebar navigation reconstructed from the video

The recording shows the following navigation structure. This is the UI hierarchy to reproduce.

MAIN

* Dashboard

  REGISTRY

* Intake Queue

* Correspondence Files

  REPORTS

* All Reports

* Verification

  * Customary

  * Statutory

    INFORMATION

* Index Cards

* Charting

* Survey Jobs (I to S)

* File Status

  PLANS & DESCRIPTIONS

* Area & Pillars

* Descriptions

* Fee Calculator

  ANALYTICS

* Reports / Analytics

## Page-by-page UI and functional specification

### Dashboard

![Oxland — The Operating System for Modern Land Acquisition Teams](https://images.openai.com/static-rsc-4/ZLl3nqOGNGfpnCcYQlDA40F4lVJewdHnKjom13zo6R8jpv0_vMXq590WCAEQvptY1tzGEb-6H5ET-TtOdrKsVpwC2EwZB73UjLAmjxNVmqyV1fJqAn9wXMXx5jhIOYAElck-hnI4FRXmCqAzMx8OF1MlQ3FV74HChiVUesOCKM0?purpose=inline)

The dashboard is the central monitoring page.

Summary cards visible in the video:

* Intake Queue – pending incoming files.

* Open Reports – reports currently being processed.

* Survey Jobs – active survey jobs.

* Index Cards – commissioned cards.

* Fees YTD – accumulated fees.

* Duplicates – detected duplicate records.

Other sections:

* Latest Open Report: vertical workflow progress tracker.

* Recent Activity: latest file receipts, index card commissions, survey job issuance and duplicate flags.

* Quick navigation to Registry, Verification, Index Cards and Fee Calculator.

All summary figures should be dynamic, retrieved from the database.

### Registry – Intake Queue

The page lists incoming files received from other departments.

Table columns:

| Column   | Description                             |
| -------- | --------------------------------------- |
| File No  | Original file number                    |
| Source   | Land, SLTR, ST, etc.                    |
| Type     | Direct RES, Direct COM, Conversion, ST  |
| Owner    | File owner                              |
| Location | Property location                       |
| Received | Date received                           |
| Status   | Queue, In Progress, Correspondence Done |
| Actions  | View and process                        |

Log Incoming File modal:

* Source Department (dropdown).

* File No (dropdown populated from selected source).

* Type (automatically populated).

* Owner / Title (automatically populated).

* Location (automatically populated).

* Log File button.

Important behaviour shown in the video: the file number must be selected from the source system. It must not be manually typed. The owner, location and type should be retrieved automatically.

Conversion files should skip charting and proceed to index card commissioning after correspondence processing.

### Registry – Correspondence Files

This page manages cadastral copies of original files.

Table columns:

* Cadastral File No.

* Source File No.

* Owner.

* Created.

* Duplicate status.

* File status.

* Actions.

The system should automatically generate correspondence records and link them to their source files.

Duplicate detection must identify:

* Duplicate file numbers.

* Double allocations.

* Conflicting plot boundaries.

A duplicate record should be flagged and placed on hold for investigation rather than proceeding normally.

> **Change (2026-10-07):** duplicates are flagged only; they are no longer placed on hold automatically. An officer places a hold by hand when investigation is needed. "PIECE OF LAND" is not treated as a plot number for double-allocation detection.

### Reports – All Reports

A consolidated list of Verification, Customary and Statutory reports.

It should allow users to:

* Search by file number or report ID.

* Filter by report type.

* Filter by workflow status.

* View assigned officers.

* Open report details.

* Track checking, approval and dispatch.

### Reports – Verification / Customary / Statutory

The recording shows separate pages for Customary and Statutory reports.

Each report detail page contains:

Report information

* Report ID.

* File number.

* Owner.

* Location.

* Assignee.

* Notes.

Workflow tracker

Customary and Statutory have seven stages:

| Stage             | Purpose                          |
| ----------------- | -------------------------------- |
| Registration      | Register the report              |
| Chart Upload      | Upload scanned chart             |
| Digital Chart     | Create or validate digital chart |
| Report Generation | Generate report document         |
| Checking          | Review report                    |
| Approval          | Obtain approval                  |
| Dispatch          | Dispatch completed report        |

Verification has eight stages, adding Field Inspection between Registration and Chart Upload.

The page should show:

* Completed stages in green.

* Active stage in amber.

* Waiting stages in neutral blue/grey.

* Advance Stage action.

* Return action.

* Start Report action.

Each stage must record its responsible officer, date, status and notes.

### Information – Index Cards

This page manages cadastral index cards.

Table columns:

* Card ID.

* File No.

* Name.

* Location.

* Survey Job.

* Last Movement.

* Actions.

Commission Index Card modal:

* File No. (dropdown).

* Name / Owner (auto-populated).

* Location (auto-populated).

* Survey Job No.

* Initial Movement Stage.

The initial movement can include Commissioned.

After commissioning, the system should generate a unique card ID and maintain the file's movement history.

Actions:

* Print.

* View.

* Update movement.

The card must be printable using the appropriate official index card layout.

### Information – Charting

The video displays this as a sidebar item, but does not show its complete page.

Based on the concept note, the page should support:

* Digital charting for Direct RES, COM and IND files.

* Geo-database and satellite imagery integration.

* Digital plot covering using red file-number overlays.

* Chart upload.

* Chart validation.

* Boundary conflict detection.

* Chart version history.

Conversion files should be automatically flagged as requiring no charting.

### Information – Survey Jobs (I to S)

The concept note specifies SURCON-compliant survey job management.

The page should support:

* Generate unique survey job numbers.

* Register surveyors and survey firms.

* Generate Instructions to Surveyors (I to S).

* Record job scope and location.

* Capture officer approval/signature.

* Track field progress and completion.

* Record survey job number against the relevant index card.

### Information – File Status

A page for managing changes to cadastral file status.

Supported operations:

* Revoked.

* Reinstated.

* Withdrawn.

* Change of Purpose.

* Open File.

* Close File.

Every update must record the previous status, new status, user, timestamp and remarks.

### Plans & Descriptions – Area & Pillars

The concept note requires automated area and pillar calculations.

This page should provide:

* File selection.

* Area input or GIS-derived area.

* Hectares, acres and square metres.

* Government pillar quantity.

* Private pillar quantity.

* Pillar/beacon coordinates.

* Calculation results.

The concept note specifies ₦4,000 per government pillar and ₦4,000 per private pillar. These rates should be configurable rather than hardcoded throughout the application.

### Plans & Descriptions – Descriptions

This is one of the clearest pages demonstrated in the recording.

![Highway 78, Lavon, TX 75166 | LoopNet](https://images.openai.com/static-rsc-4/2Ly7WbiHR9RK2ZJW7xZAJfqBDEWZ7b0gYKmnbN5zIz4wcKPlJQb5VDkL6D-odqUUjYBUb3Ld_1V2l96dsrRoEopHeWoKL2YY7LlFW0n4Lt1cxT5tPiKgmUhyAVNTPs5ONB-Ac7xIRzkqvMEykaVSVvBhLM63zODZXbrctx0PhvY?purpose=inline)

Form fields:

* File (dropdown showing file number and owner).

* Template (e.g. Commercial plot).

* Location / Text.

* Boundary Notes.

* Description Text (large editable textarea).

The description should automatically populate:

* File number.

* Property location.

* Land use.

* Area.

* Boundary descriptions.

* Pillar information, where available.

Buttons:

* Generate From Template.

* Regenerate.

* Save Description.

* Next: Fee Calculator.

Below the form, show a table of saved descriptions with:

* File No.

* Template.

* Preview.

* Saved date.

* Actions.

### Plans & Descriptions – Fee Calculator

This is a calculation and billing page.

Input fields:

| Field                      | Example in recording |
| -------------------------- | -------------------- |
| File                       | Selected file        |
| Zone                       | Urban                |
| Area (Ha)                  | 0.45                 |
| Area Rate (₦/Ha)           | 50,000               |
| Government Pillars         | 4                    |
| Private Pillars            | 2                    |
| Service Charge %           | 5                    |
| Description Complexity (₦) | 2,500                |

Calculation cards:

* Area Fee.

* Pillar Fee.

* Service Fee.

* Description Fee.

* Grand Total.

The sample in the recording calculates:

| Component       | Amount  |
| --------------- | ------- |
| Area fee        | ₦22,500 |
| Pillar fee      | ₦24,000 |
| Service fee     | ₦2,325  |
| Description fee | ₦2,500  |
| Grand total     | ₦51,325 |

Formula:

* Area Fee = Area × Area Rate.

* Pillar Fee = (Government Pillars + Private Pillars) × ₦4,000.

* Service Fee = Configured percentage × applicable subtotal.

* Grand Total = Area Fee + Pillar Fee + Service Fee + Description Fee.

Buttons:

* Push to Revenue.

* Save Bill.

Below this is a bills table with Bill ID, File No, Area Fee, Pillars, Service, Description, Total, Status and Actions.

### Analytics – Reports / Analytics

The recording shows four summary cards:

* Reports Total.

* Dispatched.

* Duplicates Blocked.

* Bill Raised.

There is also a metrics table covering:

* Intake queued.

* Correspondence.

* Verification open.

* Customary open.

* Statutory open.

* Index cards.

* Survey jobs active.

* Charts completed.

* Fee bills.

This page should aggregate actual data and provide a monitoring view for management.


## Document templates and print generation

The recording demonstrates several official document previews. These are not ordinary web forms; they should be generated as printable documents using HTML templates.

The visible document types include:

* Description of Right of Occupancy.

* R. of O./JG Item Card.

* Cadastral Fees and Area.

* Report on Application.

* Cadastral Index Card.

Important: existing HTML templates

You mentioned that you already have two HTML templates in:

`doc/templates/cadastral`

The VS Code agent must inspect this directory before implementing the template functionality.

The instructions should be:

* Identify both existing HTML templates and understand their structure.

* Preserve their existing layout, official formatting, tables, headings, logos, signatures and styling.

* Do not recreate these templates from scratch.

* Identify the dynamic placeholders required by each template.

* Populate the templates using database records.

* Support HTML preview and browser printing.

* Ensure printed output matches the original official document as closely as possible.

* Keep the templates separate from Blade page layouts and reusable across the module.

The video also shows a Description of Right of Occupancy document with boundary bearings and distances. Its content should come from the description and survey data, not manually duplicated in the print view.

## Important integration and business rules

These requirements come from the supporting concept note and should be implemented alongside the visible interface.

| Existing KLAES module | Integration requirement                                          |
| --------------------- | ---------------------------------------------------------------- |
| Land                  | Receive Direct and Conversion files and synchronize file status. |
| Deeds                 | Process Change of Purpose and validate boundaries.               |
| SLTR                  | Receive files and synchronize file-number mapping.               |
| Sectional Titling     | Receive ST files and validate boundaries.                        |
| KANGIS LIS            | GIS synchronization and spatial validation.                      |
| DCIV                  | Receive files for investigation reports.                         |
| Digital File Archive  | Archive reports, charts and index cards; support QR tracking.    |
| Revenue Management    | Generate payment requests from cadastral fee bills.              |

Additional implementation rules:

* All incoming files must reference their original source records.

* Never generate a duplicate correspondence file for an already processed source file.

* Every workflow transition must be auditable.

* Restrict actions according to user roles and departmental hierarchy.

* Keep historical records of file movements.

* Provide notification support for workflow milestones.

* Integrate cadastral reports and charts into Property History Search.

* Feed the appropriate cadastral metrics into management dashboards.

The concept note also identifies the departmental approval hierarchy: Director, Deputy Director, Assistant Directors, Officers in Charge, Chart Officers, Report Officers and Field Officers.


## Ready-to-use VS Code agent implementation prompt

I recommend giving the agent the following as its primary task. It combines the video reconstruction, concept note and existing template requirements.

TASK: Rebuild and Fully Implement the KLAES Cadastral Module

# TASK: Rebuild and Fully Implement the KLAES Cadastral Module

## 1. Project Objective

Rebuild the KLAES Cadastral Module based on the supplied UI demonstration video and the supporting document:

`KLAES Cadastral Module Concept Note`

The previous implementation was completed before the UI demonstration was received. The objective is to reconstruct the module to match the demonstrated interface and workflows while implementing the functional requirements in the concept note.

This is not a request to create a standalone application or a static UI prototype.

The module must be implemented inside the existing KLAES Laravel application, using its existing authentication, database, layouts, navigation, permissions, and architectural conventions.

## 2. Mandatory Preliminary Inspection

Before writing or modifying code:

1. Inspect the existing KLAES application structure.

2. Identify the existing Cadastral Module implementation, including controllers, models, routes, migrations, Blade views, JavaScript and CSS.

3. Inspect the existing database schema and identify relevant tables and relationships.

4. Review the existing authentication and role/permission implementation.

5. Inspect existing modules for reusable components, especially file selection, file tracking, document generation, revenue integration and digital archiving.

6. Inspect both HTML templates inside:

   `doc/templates/cadastral`

7. Open and examine the supplied concept note and UI demonstration.

Do not assume that the existing implementation is completely wrong. Reuse working components where appropriate, but reconstruct pages and workflows that do not match the demonstrated design.

Do not introduce duplicate functionality or unnecessary database tables.

## 3. Sidebar Navigation

Reconstruct the Cadastral sidebar with the following hierarchy:

### MAIN

* Dashboard

### REGISTRY

* Intake Queue

* Correspondence Files

### REPORTS

* All Reports

* Verification

  * Customary

  * Statutory

### INFORMATION

* Index Cards

* Charting

* Survey Jobs (I to S)

* File Status

### PLANS & DESCRIPTIONS

* Area & Pillars

* Descriptions

* Fee Calculator

### ANALYTICS

* Reports / Analytics

The sidebar must:

* Support expandable menu groups.

* Highlight the active page.

* Display relevant pending-item badges.

* Preserve the existing KLAES sidebar and navigation conventions.

* Be responsive.

* Enforce permissions for individual pages and actions.

## 4. UI Design Requirements

Follow the supplied video closely.

The design uses:

* Dark navy-blue sidebar.

* Green accent colour for active navigation and successful actions.

* Light grey page background.

* White content cards.

* Rounded card borders.

* Compact tables.

* Clearly visible status badges.

* Consistent form spacing.

* Modal-based data entry.

* Search and filter controls.

* Breadcrumb navigation.

* Top-right global search and notification icons.

Use the existing KLAES frontend stack and components.

Do not introduce a completely different UI framework or redesign the application.

Maintain consistency with the existing Laravel Blade, Tailwind/CSS, Select2, SweetAlert and AJAX conventions where applicable.

## 5. Functional Pages

Implement the following pages.

### A. Dashboard

Create dynamic summary cards for:

* Intake Queue

* Open Reports

* Survey Jobs

* Index Cards

* Fees YTD

* Duplicates

Additional sections:

* Latest Open Report with workflow progress.

* Recent Activity.

* Quick navigation to Registry, Verification, Index Cards and Fee Calculator.

All statistics must come from actual database records.

### B. Intake Queue

Display incoming cadastral files.

Table columns:

* File No

* Source

* Type

* Owner

* Location

* Received

* Status

* Actions

Implement a Log Incoming File modal.

Fields:

* Source Department

* File No

* Type

* Owner / Title

* Location

Business rules:

* Source Department must be selected first.

* File No must be selected from the relevant source module.

* Type, Owner and Location should populate automatically.

* Do not allow arbitrary manual file-number entry.

* Prevent duplicate intake records.

* Maintain source-file relationships.

Support files from Land, SLTR, ST, DCIV and KANGIS.

Conversion files must skip charting and proceed to index card commissioning after correspondence processing.

### C. Correspondence Files

Implement automatic cadastral correspondence file generation.

Display:

* Cadastral File No

* Source File No

* Owner

* Created

* Duplicate

* Status

* Actions

Implement:

* Automatic correspondence creation.

* Source-to-cadastral file mapping.

* Duplicate detection.

* Double-allocation detection.

* Conflicting plot-boundary detection.

* Duplicate investigation and hold functionality.

* Audit history.

Do not create multiple correspondence records for the same source transaction.

### D. All Reports

Create a consolidated report-management page.

Include:

* Report ID

* File No

* Report Type

* Owner

* Location

* Assigned Officer

* Current Stage

* Status

* Actions

Provide search, filtering and report-detail navigation.

### E. Verification, Customary and Statutory Reports

Create individual report pages with a shared reusable workflow component.

Report details:

* Report ID

* File No

* Owner

* Location

* Assignee

* Notes

Customary workflow (7 stages):

1. Registration

2. Chart Upload

3. Digital Chart

4. Report Generation

5. Checking

6. Approval

7. Dispatch

Statutory workflow (7 stages):

1. Registration

2. Chart Upload

3. Digital Chart

4. Report Generation

5. Checking

6. Approval

7. Dispatch

Verification workflow (8 stages):

1. Registration

2. Field Inspection

3. Chart Upload

4. Digital Chart

5. Report Generation

6. Checking

7. Approval

8. Dispatch

Implement:

* Start Report.

* Advance Stage.

* Return to Previous Stage.

* Assign Officer.

* Stage Notes.

* Upload supporting documents.

* Generate report documents.

* Approve and reject.

* Dispatch and record dispatch details.

The workflow must be role-based.

Do not allow users to bypass approval stages without the appropriate authorization.

Use green for completed stages, amber for the active stage and neutral styling for waiting stages.

### F. Index Cards

Create an index-card management page.

Table columns:

* Card ID

* File No

* Name

* Location

* Survey Job

* Last Movement

* Actions

Commission Index Card modal:

* File No

* Name / Owner (auto-populated)

* Location (auto-populated)

* Survey Job No

* Initial Movement Stage

Implement:

* Unique card ID generation.

* Automatic file-information retrieval.

* Commissioning.

* Movement history.

* Print.

* View.

* Movement updates.

Each card must retain its movement history and be linked to its original file.

### G. Charting

Implement the digital charting interface.

Requirements:

* Direct file charting.

* Conversion file handling.

* Chart upload.

* Digital chart creation.

* GIS integration.

* Satellite imagery support.

* Digital red file-number overlays.

* Boundary validation.

* Duplicate and boundary-conflict alerts.

* Chart version history.

Direct RES, COM and IND files require charting.

Conversion files should automatically be flagged as not requiring charting.

Do not implement a fake GIS interface that only displays static images. Integrate with the existing GIS/KANGIS capabilities where available.

### H. Survey Jobs (I to S)

Implement survey job management.

Requirements:

* Unique SURCON-compliant job numbering.

* Surveyor registration.

* Survey firm directory.

* Job scope and location.

* Instructions to Surveyors (I to S) generation.

* Officer authorization.

* Digital signature support where available.

* Survey job status tracking.

* Index-card synchronization.

* Job completion history.

### I. File Status

Provide a dedicated interface for:

* Revocation.

* Reinstatement.

* Withdrawal.

* Change of Purpose.

* File Opening.

* File Closing.

Maintain:

* Previous status.

* New status.

* Effective date.

* Officer.

* Remarks.

* Supporting documents.

* Audit history.

Notify relevant departments when a file status changes.

### J. Area & Pillars

Implement area and pillar management.

Requirements:

* File selection.

* Area measurements.

* Hectares.

* Acres.

* Square metres.

* Plot measurements.

* Government pillars.

* Private pillars.

* Pillar/beacon coordinates.

* GIS-derived area where available.

* Boundary validation.

Allow area and pillar information to be used by the Description and Fee Calculator pages.

### K. Descriptions

Reconstruct the Land Descriptions page as shown in the video.

Form fields:

* File (dropdown).

* Template (dropdown).

* Location / Text.

* Boundary Notes.

* Description Text (editable textarea).

Automatically populate available information:

* File Number.

* Owner.

* Location.

* Land Use.

* Area.

* Boundary information.

* Pillar information.

Buttons:

* Generate From Template.

* Regenerate.

* Save Description.

* Next: Fee Calculator.

Below the form, provide a saved descriptions table:

* File No

* Template

* Preview

* Saved

* Actions

Allow users to preview, edit and print saved descriptions according to their permissions.

### L. Fee Calculator

Reconstruct the Fee Calculator page.

Fields:

* File.

* Zone.

* Area (Ha).

* Area Rate (₦/Ha).

* Government Pillars.

* Private Pillars.

* Service Charge %.

* Description Complexity (₦).

Calculation cards:

* Area Fee.

* Pillar Fee.

* Service Fee.

* Description Fee.

* Grand Total.

Calculation rules:

Area Fee = Area × Area Rate

Pillar Fee = (Government Pillars + Private Pillars) × Configured Pillar Rate

Service Fee = Configured Service Percentage × Applicable Subtotal

Grand Total = Area Fee + Pillar Fee + Service Fee + Description Fee.

Use ₦4,000 as the initial government and private pillar rate, based on the concept note.

Rates and fee parameters must be configurable.

Buttons:

* Push to Revenue.

* Save Bill.

Below the calculator, provide a bills table:

* Bill ID

* File No

* Area Fee

* Pillars

* Service

* Description

* Total

* Status

* Actions

Ensure that calculations are performed server-side and validated before saving.

Integrate with the existing KLAES Revenue Management System and its transaction-token security.

Do not create an independent payment system.

### M. Reports / Analytics

Create a dynamic management dashboard.

Summary cards:

* Reports Total.

* Dispatched.

* Duplicates Blocked.

* Bill Raised.

Metrics table:

* Intake Queued.

* Correspondence.

* Verification Open.

* Customary Open.

* Statutory Open.

* Index Cards.

* Survey Jobs Active.

* Charts Completed.

* Fee Bills.

Support filtering, refresh and reporting.

## 6. HTML Document Templates

This is a critical requirement.

There are already TWO HTML templates in:

`doc/templates/cadastral`

Inspect both templates carefully before implementing document generation.

Do not recreate them or replace their layouts with generic documents.

For each template:

1. Identify its purpose.

2. Identify every static and dynamic section.

3. Map its required placeholders to actual database fields.

4. Preserve official formatting.

5. Populate the template dynamically.

6. Support preview and printing.

7. Maintain the original document dimensions, margins, tables and typography.

The video also demonstrates official document previews, including:

* Description of Right of Occupancy.

* R. of O./JG Item Card.

* Cadastral Fees and Area.

* Report on Application.

* Index Card.

Determine how the two existing HTML templates relate to these outputs. Do not assume they correspond to any particular two documents without inspecting their contents.

Keep document generation reusable and separate from the page controllers.

## 7. Database and Backend Requirements

Inspect the current database schema before creating migrations.

Identify reusable existing tables and relationships.

Implement missing schema only where necessary.

Use the application's existing database connection and Laravel conventions.

The database is Microsoft SQL Server, so:

* Ensure migration compatibility with SQL Server.

* Avoid MySQL-specific SQL syntax.

* Use appropriate indexes and foreign keys.

* Prevent duplicate records through database constraints where appropriate.

* Use database transactions for multi-step operations.

Implement proper:

* Models.

* Relationships.

* Controllers.

* Form Requests.

* Services.

* Policies/permissions.

* Routes.

* Blade views.

* AJAX endpoints.

* Validation.

* Audit logging.

Avoid placing complex business logic directly inside controllers.

## 8. Integration with Existing KLAES Modules

Integrate with:

* Land.

* Deeds.

* SLTR.

* Sectional Titling.

* KANGIS LIS.

* DCIV.

* Digital File Archive.

* File Tracking.

* Revenue Management.

Use existing APIs, services and database relationships wherever possible.

Do not duplicate the source modules' file records.

Maintain original file numbers and their relationships throughout the cadastral workflow.

Synchronize:

* File status.

* Correspondence mappings.

* Index card information.

* Survey job information.

* Cadastral reports.

* Chart records.

* Revenue transactions.

Include cadastral information in Property History Search and the appropriate management dashboards.

## 9. Security and Audit Trail

Implement role-based access for:

* Registration.

* Charting.

* Report processing.

* Checking.

* Approval.

* Dispatch.

* Index card commissioning.

* Fee calculation.

* Management reporting.

Maintain an audit trail for:

* File reception.

* Correspondence creation.

* Duplicate flags.

* Chart updates.

* Index card commissioning.

* File movements.

* Survey job issuance.

* Report workflow transitions.

* Approvals.

* Fee calculations.

* Revenue submissions.

Do not permit unauthorized modifications or deletion of historical records.

## 10. Implementation Strategy

Implement in logical phases.

Phase 1: Existing codebase, schema and HTML template inspection.

Phase 2: Sidebar, dashboard and common UI components.

Phase 3: Registry and correspondence.

Phase 4: Reports and approval workflows.

Phase 5: Index cards, charting and survey jobs.

Phase 6: File status management.

Phase 7: Area, pillars and descriptions.

Phase 8: Fee Calculator and Revenue integration.

Phase 9: Document generation and printing.

Phase 10: Analytics, integration testing and permissions.

## 11. Critical Development Instructions

* Do not implement static mock data as the final solution.

* Do not break existing KLAES functionality.

* Do not replace existing working integrations with dummy implementations.

* Do not create duplicate database tables when suitable tables already exist.

* Do not manually hardcode file owners, locations or file numbers.

* Do not bypass existing authentication and authorization.

* Preserve the existing KLAES visual conventions.

* Reuse existing components wherever practical.

* Make every button and action functional.

* Use AJAX where appropriate to avoid unnecessary full-page reloads.

* Ensure the UI works with actual production-style data volumes.

* Maintain proper validation and error handling.

* Do not mark incomplete integrations as completed.

## 12. Completion Requirements

The implementation is considered complete only when:

1. All sidebar items open their correct pages.

2. The pages match the supplied UI demonstration.

3. Forms save and retrieve actual database records.

4. File selection integrates with the appropriate source modules.

5. Correspondence generation and duplicate detection work.

6. Report workflows enforce their correct stages.

7. Index cards can be commissioned and printed.

8. Charting and survey job functionality are implemented.

9. Descriptions use the existing HTML templates where applicable.

10. Fee calculations are accurate and bills can be saved.

11. Revenue integration works through the existing system.

12. Audit trails and permissions are enforced.

13. Analytics display actual figures.

14. Existing KLAES functionality remains intact.

## FINAL INSTRUCTION

Start by inspecting the existing codebase, database schema and both HTML templates in `doc/templates/cadastral`.

Produce a concise implementation assessment identifying:

* What already exists.

* What works and should be reused.

* What is incomplete.

* What needs to be rebuilt.

* What database changes are required.

Then proceed with implementation phase by phase.

Do not stop after producing the assessment. Continue with the actual implementation, testing each phase before moving to the next.

The supplied video is the visual reference. The Cadastral Module Concept Note is the functional reference. The two existing HTML templates are the document-format reference. The existing KLAES codebase is the architectural reference.

## My recommendation before running the agent

Give Copilot/your VS Code agent access to all three references:

* The WhatsApp UI demonstration video.

* The Cadastral Module Concept Note.

* The project directory containing `doc/templates/cadastral`.

One particularly important instruction: Make the agent inspect the two HTML templates before it implements document generation. Otherwise, it might build new printable layouts that look similar but do not preserve your existing official document formats.

Also, implement the module in phases rather than asking the agent to change every controller, migration and Blade file simultaneously. This will make it easier to identify regressions and preserve the existing KLAES functionality.
