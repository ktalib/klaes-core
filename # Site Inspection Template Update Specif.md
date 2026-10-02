# Site Inspection Template Update Specification

## 1. Purpose

Update the existing Site Inspection feature to support the revised inspection template and the following transaction purposes:

- Merger
- Extension
- Subdivision
- Change of Purpose
- Any valid combination of the purposes above

This is an incremental update to the existing implementation. Do not rebuild the feature from scratch, remove working fields, change existing file numbers, or modify unrelated workflows.

## 2. Instructions to the Implementation Agent

Before making changes:

1. Locate and review the existing Site Inspection routes, controllers/services, models, migrations, Blade views/components, JavaScript, validation rules, printable template, permissions, and database tables.
2. Document how the current form is created, edited, viewed, approved, and printed.
3. Reuse existing naming conventions, components, land-use configuration, property search, file-number lookup, audit fields, and approval workflow.
4. Compare the current database structure with this specification before creating migrations.
5. Add only the missing columns/tables. All migrations must be additive and safe for existing records.
6. Do not rename, regenerate, update, or otherwise alter existing file numbers.
7. Preserve existing Site Inspection records and ensure they can still be viewed, edited, and printed.
8. Present the proposed implementation plan and database changes for confirmation before applying destructive or structurally risky changes.

## 3. Form Structure

### 3.1 Application Information

| Field | Behaviour |
|---|---|
| Application/File Number | Use the existing record and file-number lookup. Auto-fill where available. |
| Applicant Name | Auto-fill from the selected application/property where available. |
| Inspection Date | Date field. |
| Inspection Officer | Auto-fill from the authenticated user where applicable. |
| Site Address/Location | Text field. |
| Local Government Area | Use the existing configured LGA list. |

### 3.2 Persons Present During Inspection

Label:

> A joint site inspection was conducted with:

Allow multiple selections:

- Applicant
- Applicant's Representative

When `Applicant's Representative` is selected, display a repeatable representative section containing:

- Representative name
- Phone number
- Relationship/capacity

The user must be able to add more than one representative.

### 3.3 Purpose of Site Inspection

Label:

> The site inspection is for:

Use a multi-select control or checkboxes:

- Merger
- Extension
- Subdivision
- Change of Purpose

More than one purpose may be selected for the same inspection. Examples include:

- Merger and Extension
- Subdivision and Separation, where Separation already exists as a configured option
- Extension and Subdivision
- Subdivision and Change of Purpose

Selecting a purpose must dynamically display its corresponding section. Multiple selected purposes must display all relevant sections within the same form.

Do not store combinations as hard-coded labels such as `Merger and Extension`. Store the selected purposes individually so that new combinations remain possible.

## 4. Conditional Purpose Sections

### 4.1 Merger Details

Display only when `Merger` is selected.

Allow the user to select two or more properties/plots to be merged. Each selected property should show available information such as:

- Property/file number
- Plot number
- Existing measurement
- Measurement unit

Provide `Add Another Property` and `Remove` controls.

Capture or calculate:

- Number of properties being merged
- Total existing area
- Recommended merged area
- Unit: square metres or hectares
- Proposed merged plot number, when applicable
- Merger remarks

#### Merger rules

- A merger requires at least two selected properties.
- Prevent the same property from being selected more than once.
- Calculate the total using a consistent base unit.
- Preserve the original property references; do not change their file numbers.

### 4.2 Extension Details

Display only when `Extension` is selected.

Provide a repeatable list containing:

- Extension number
- Description/location of the extension portion
- Measurement
- Measurement unit

Provide `Add Another Extension` and `Remove` controls.

Capture or calculate:

- Number of extensions
- Existing site area
- Total extension area
- Recommended total site area
- Extension remarks

`Number of extensions` means the number of separate land portions being added to the existing site. It does not mean the number of years.

Recommended total site area should normally equal:

`Existing Site Area + Total Extension Area`

If authorised officers may override the calculated value, require an override reason and retain the calculated value for audit purposes.

### 4.3 Subdivision Details

Display only when `Subdivision` is selected.

Capture:

- Existing/recommended site measurement
- Number of proposed plots
- Measurement unit

After the number of plots is entered, generate one row for each proposed plot containing:

- Subdivision sequence number
- Proposed plot number, when available
- Recommended measurement
- Measurement unit
- Optional remarks

Calculate:

- Total subdivided area
- Remaining/unallocated area

#### Subdivision rules

- The number of generated rows must correspond to the number of proposed plots.
- Measurements must be positive numbers.
- Total subdivided area must not exceed the available recommended site area.
- If a previously entered plot count is reduced, warn the user before removing populated rows.
- Existing subdivision records must not be silently deleted during editing.

### 4.4 Change of Purpose Details

Display only when `Change of Purpose` is selected.

Provide a repeatable list containing:

- Current land use/purpose
- Proposed land use/purpose
- Area affected
- Measurement unit
- Optional remarks

Use the existing configurable land-use/purpose entries for both purpose fields. Do not create a separate hard-coded list when a configured list already exists.

Provide `Add Another Purpose Change` and `Remove` controls because one inspection may cover more than one land-use change.

#### Change-of-purpose rules

- Current purpose and proposed purpose are required.
- The proposed purpose must be different from the current purpose.
- Area affected must be positive when supplied or required by the existing workflow.
- Multiple changes must be preserved as separate entries.

## 5. Site Characteristics

Capture the following fields, reusing existing fields where present:

- Northern boundary
- Southern boundary
- Eastern boundary
- Western boundary
- Road reservation from the centreline of the road to the building line, in metres
- Existing site measurement
- Total recommended site area
- Measurement unit: square metres or hectares

The interface may display both square metres and hectares, but calculations should use one consistent base unit to prevent errors.

## 6. Inspection Findings

Capture:

- Was the site found suitable? Yes/No
- Is the site accessible? Yes/No
- Does the development conform to the surrounding land uses? Yes/No/Not Applicable
- Development status: Developed/Undeveloped/Partially Developed
- Inspection officer's recommendation
- Further directions/remarks

When a negative response materially affects the recommendation, require an explanatory remark.

## 7. Supporting Evidence

Retain the existing document/upload mechanism and support the following evidence types where not already supported:

- Site photographs
- Location coordinates
- Survey plan
- Sketch/site layout
- Other supporting documents

Each uploaded photograph may include a description. Do not replace or delete an existing attachment unless the user explicitly performs that action and has permission.

## 8. Certification and Approval

Include:

- Inspection officer name
- Officer designation
- Digital signature or the system's existing signature mechanism
- Inspection/certification date
- Supervisor's remarks
- Supervisor decision: Approve, Return, or Reject

Use the existing approval and permission workflow where one already exists.

Suggested certification statement:

> I certify that the information contained in this Site Inspection Report represents the findings made during the physical inspection of the property.

## 9. Dynamic Interface Behaviour

1. Load existing inspection data correctly on both the view and edit pages.
2. Show a purpose section only when that purpose is selected.
3. Selecting an additional purpose must not clear data already entered in another section.
4. If a user deselects a purpose containing entered data, display a confirmation warning before clearing or deactivating that data.
5. Repeatable rows must support add and remove actions.
6. Server-side validation must enforce all rules even if client-side JavaScript is bypassed.
7. Validation errors must restore all selections and repeatable rows without losing user input.
8. The interface must work for create, edit, view, approval, and print modes.

## 10. Recommended Data Design

The agent must first inspect the existing schema. Reuse compatible tables and relationships. If the present structure cannot safely represent multiple purposes and repeatable details, use an additive parent-child design similar to the following:

### Parent inspection record

Retain the existing Site Inspection table as the parent record.

### Possible child records

- Inspection participants/representatives
- Inspection purposes
- Merger properties
- Extension portions
- Subdivision plots
- Change-of-purpose entries
- Supporting attachments, if an existing generic attachment relationship is unavailable

Each child record should reference the parent inspection and include the project's normal audit columns. Prefer normalized child records over storing repeatable data as comma-separated text. JSON may be used only if it matches the established project design and remains safely queryable and editable.

### Data safety requirements

- Use additive migrations.
- New columns should be nullable or have safe defaults for old records.
- Add foreign keys and indexes where compatible with the current SQL Server configuration.
- Do not drop or rename existing columns without explicit confirmation.
- Do not modify existing file-number values.
- Use a database transaction when saving the parent record and its related purpose details.
- Editing must update only the relevant child records and must not erase unrelated inspection information.

## 11. Printable Site Inspection Report

The printed report should display:

1. Application and property information
2. Persons present during inspection
3. Every selected inspection purpose
4. Only the relevant purpose-detail sections
5. Site boundaries and measurements
6. Inspection findings
7. Officer's recommendation and further directions
8. Supporting evidence references, where appropriate
9. Certification, signature, date, supervisor remarks, and decision

For combined purposes, print all applicable sections under the same Site Inspection Report. Do not generate separate reports unless the existing workflow explicitly requires them.

Suggested narrative opening:

> A joint site inspection was conducted with [selected participants]. The site is located at [site location], [LGA]. The inspection was conducted for [selected purposes].

Suggested narrative closing:

> The site was found [suitable/not suitable] and [accessible/not accessible]. The development [conforms/does not conform] to the surrounding land uses. For further direction, please refer to the inspection officer's recommendation below.

## 12. Validation Summary

| Condition | Required validation |
|---|---|
| Inspection submission | At least one inspection purpose is required. |
| Applicant's representative selected | At least one representative name is required. |
| Merger selected | At least two distinct properties are required. |
| Extension selected | At least one extension portion and its measurement are required. |
| Subdivision selected | Plot count and matching subdivision rows are required. |
| Change of Purpose selected | At least one current-to-proposed purpose entry is required. |
| Measurement entered | Value must be numeric and greater than zero. |
| Subdivision totals | Total must not exceed the available site area. |
| Same current and proposed purpose | Reject the entry. |
| Negative inspection finding | Require an explanatory remark where applicable. |

## 13. Backward Compatibility

- Existing inspections without the new child records must continue to load.
- Existing printed reports must remain available.
- Where old single-purpose fields exist, map them carefully to the updated display without modifying the original record unless the user edits and saves it.
- Do not perform automatic bulk conversion without a reviewed migration/backfill plan.
- Maintain current routes or provide backward-compatible redirects if route changes are unavoidable.
- Do not break existing permissions, approvals, notifications, or audit logs.

## 14. Testing Requirements

Test at minimum:

1. Create an inspection with each single purpose.
2. Create an inspection with multiple purposes.
3. Add multiple representatives.
4. Merge two and more than two properties.
5. Add multiple extension portions and verify area calculations.
6. Generate subdivision rows and verify totals and remaining area.
7. Add multiple change-of-purpose rows.
8. Edit an existing inspection without losing data.
9. Deselect a populated purpose and confirm the warning behaviour.
10. Submit invalid data and verify that entered rows are restored.
11. View and print old inspection records.
12. View and print the new single-purpose and combined-purpose reports.
13. Test role permissions for officer and supervisor actions.
14. Confirm that no file numbers or unrelated property records change.

Use the project's established Laravel testing approach and SQL Server-compatible test setup.

## 15. Acceptance Criteria

The update is complete when:

- Users can select one or more inspection purposes.
- Only the relevant dynamic sections are displayed.
- More than one participant/representative can be recorded.
- Merger supports multiple property selections.
- Extension supports multiple extension portions, not years.
- Subdivision generates and validates the required number of plots.
- Change of Purpose supports one or more current-to-proposed land-use changes.
- Calculations and measurement validation work on the client and server.
- Existing records, file numbers, permissions, approvals, and printing continue to work.
- Combined-purpose inspections produce one complete printable report.
- Automated tests cover the principal single-purpose, combined-purpose, editing, validation, compatibility, and printing scenarios.

## 16. Required Agent Deliverables

Before implementation, provide:

1. Existing implementation assessment
2. Files/components that require modification
3. Proposed database changes
4. Compatibility and risk notes
5. Step-by-step implementation plan

After implementation, provide:

1. Summary of changes made
2. Migration details
3. Updated routes/controllers/services/models/views/scripts
4. Validation and permission changes
5. Tests added and their results
6. Confirmation that existing file numbers and records were not altered
7. Screenshots or rendered examples of the create, edit, view, and print templates
