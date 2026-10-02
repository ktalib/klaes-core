# Planning Recommendation for Conversion Applications

## Overview

The Planning Recommendation for Conversion Applications module is designed to manage and process conversion files (land use type changes) within the Physical Planning department. This system provides a streamlined interface for viewing, inspecting, and making planning recommendations on conversion applications.

**Module Path:** Physical Planning → Conversion Applications → Planning Recommendation

**Route:** `/physical-planning/regular/planning-recommendation`

---

## Access & Permissions

### Required Roles
- **PP - Conversion Applications** - Primary role for accessing the module
- **Planning Recommendation** - For making planning recommendations
- **Bills & Payments** - For billing-related actions
- **ST eRegistry** - For registry access

### Menu Location
The feature is accessible from the sidebar under:
```
Physical Planning
  └── Conversion Applications
      ├── i. Planning Recommendation
      ├── ii. Memo
      ├── iii. Bills & Payments
      └── iv. ST eRegistry
```

---

## Features & Functionality

### 1. Conversion Files Display

The module displays conversion files from the `file_indexings` table that match the following patterns:

- **CON-RES** - Residential Conversions
- **CON-COM** - Commercial Conversions  
- **CON-IND** - Industrial Conversions
- **CON-AG** - Agricultural Conversions

Including their RC (Recertification) variants:
- **CON-RES-RC**, **CON-COM-RC**, **CON-IND-RC**, **CON-AG-RC**

### 2. Data Columns Displayed

| Column | Description |
|--------|-------------|
| File Number | Full file number (e.g., CON-COM-2024-123) |
| File Title | Name/title of the file |
| Land Use | Land use type with color-coded badges |
| Plot No | Plot number |
| District | Administrative district |
| LGA | Local Government Area |
| Registry | Registry location |
| Location | Physical location description |
| TP No | Town Planning number |
| LPKN No | LPKN reference number |
| Phone | Contact phone number |
| Address | Residence address |
| Created | File creation date |
| Actions | Dropdown menu with available actions |

### 3. Land Use Type Color Coding

- **Residential** - Blue badge
- **Commercial** - Purple badge
- **Industrial** - Orange badge
- **Agricultural** - Green badge

---

## Action Menu

Each conversion file has a dropdown action menu with the following options:

### 1. View Application
**Icon:** Eye (Sky Blue)  
**Purpose:** View complete file details and application information

### 2. Enter Inspection Details
**Icon:** Clipboard List (Purple)  
**Purpose:** Open the Joint Site Inspection (JSI) modal to capture new inspection data

### 3. View/Edit Inspection Details
**Icon:** Dashboard (Indigo)  
**Purpose:** View or edit existing inspection details

### 4. Submit Planning Recommendation
**Icon:** Check Circle (Emerald)  
**Purpose:** Submit the final planning recommendation for approval

---

## Joint Site Inspection (JSI) Modal

The JSI modal is integrated from the ST One Stop Shop system and provides comprehensive inspection data capture.

### Current Features (Standard JSI)
- **Basic Information** - Application ID, File Number, Applicant Name
- **Inspection Details** - Date, Inspector Name, Location
- **Boundary Descriptions** - North, East, South, West boundaries
- **Site Measurements** - Dynamic table for measurements
- **Inspector's Notes** - Detailed observations
- **Recommendations** - Inspector's recommendations

---

## Additional Fields Required for Conversion Applications

The following fields should be added to the JSI card **ONLY for Conversion Applications**:

### 1. Purpose
**Type:** Dropdown (Single Select)  
**Options:**
- Conversion
- Continuation
- Extension
- Subdivision
- Merger
- Private Layout

**Conditional Field:**
- If "Private Layout" is selected, show **Private Layout Number** text field

### 2. Location & Address
**Type:** Address Builder Component  
**Fields:**
- Plot Number
- Street Name
- District
- LGA
- State
- **Residential Address** (auto-formatted from above fields)

### 3. Conformity
**Type:** Radio Button / Checkbox  
**Options:**
- [ ] Yes
- [ ] No

**Purpose:** Indicates if the proposed use conforms to the development plan

### 4. Accessibility
**Type:** Radio Button / Checkbox  
**Options:**
- [ ] Yes
- [ ] No

**Purpose:** Indicates if the site has adequate access

### 5. Existing Road Reservation
**Type:** Text Field  
**Purpose:** Record existing road setback/reservation

### 6. Recommended Road Reservation
**Type:** Text Field  
**Purpose:** Recommended road setback/reservation for the conversion

### 7. Adequate Size Requirement
**Type:** Radio Button / Checkbox  
**Options:**
- [ ] Yes
- [ ] No

**Purpose:** Indicates if the plot size meets requirements for the proposed use

### 8. Land Traversing Utility
**Type:** Radio Button / Checkbox  
**Options:**
- [ ] Yes
- [ ] No

**Purpose:** Indicates if any utilities traverse the land

### 9. Existing Site Measurement
**Type:** Calculated Field  
**Input:** `length × width`  
**Auto-Calculate:** Area in m²  
**Display:** Area Covered: ___ m²

### 10. Recommended Site Measurement
**Type:** Calculated Field  
**Input:** `length × width × height`  
**Auto-Calculate:** Volume and area  
**Display:** Area Covered: ___ m²

---

## Visibility Rules for Conversion Applications

When the JSI modal is opened for a conversion file:

### Hide the Following Sections:
- ❌ "Shared Utilities & Measurements"
- ❌ "No. of Sections"
- ❌ "Utilities Measurement Summary"

### Remove Validation For:
- Hidden fields should not be required
- This prevents blocking the Planning Recommendation workflow

### Detection Logic:
```javascript
// Check if this is a conversion file
const isConversionFile = options.isConversionFile === true || 
                         fileNumber.startsWith('CON-');

if (isConversionFile) {
    // Show conversion-specific fields
    // Hide ST-specific fields
    // Adjust validation rules
}
```

---

## Backend Implementation

### Controller
**File:** `app/Http/Controllers/ConversionPlanningRecommendationController.php`

#### Methods

**index()**
- Returns the main view with filters and DataTables layout
- Passes districts, LGAs, registries, and user data to view

**getData()**
- Server-side processing for DataTables
- Filters conversion files by pattern matching
- Supports search across all columns
- Returns JSON response for AJAX requests

### Routes
**File:** `routes/app3.php`

```php
Route::prefix('physical-planning')->name('physical-planning.')->group(function () {
    Route::prefix('regular')->name('regular.')->group(function () {
        Route::get('/planning-recommendation', [ConversionPlanningRecommendationController::class, 'index'])
            ->name('planning-recommendation');
        Route::get('/planning-recommendation/data', [ConversionPlanningRecommendationController::class, 'getData'])
            ->name('planning-recommendation.data');
    });
});
```

### View
**File:** `resources/views/physical_planning/conversion/planning_recommendation.blade.php`

#### Includes
- DataTables CSS and JS
- Select2 for enhanced dropdowns
- Joint Site Inspection modal partial
- Joint Site Inspection JavaScript partial
- Alpine.js for dropdown interactions

---

## Database Structure

### Primary Table: `file_indexings`

**Columns Used:**
```sql
id, file_number, file_title, land_use_type, plot_number,
district, lga, registry, location, tp_no, lpkn_no,
phone, residence_address, created_at
```

### Related Table: `joint_site_inspection_reports`

**Standard Columns:**
```sql
id, application_id, sub_application_id, inspection_date,
lkn_number, applicant_name, location, plot_number,
scheme_number, available_on_ground, boundary_description,
sections_count, unit_dimension, unit_number, road_reservation,
prevailing_land_use, applied_land_use, shared_utilities,
compliance_status, has_additional_observations,
additional_observations, inspection_officer,
existing_site_measurement_summary,
existing_site_measurement_entries, is_generated,
is_submitted, is_approved, generated_at, submitted_at,
generated_by, submitted_by, approved_by, approved_at,
created_by, updated_by, created_at, updated_at
```

**Migration:** `2026_03_01_120000_add_conversion_fields_to_joint_site_inspection_reports_table.php`

**Additional Columns for Conversion Applications:**
```sql
source                          VARCHAR(100)    -- Module source identifier
purpose                         VARCHAR(50)     -- Conversion, Continuation, Extension, etc.
private_layout_number           VARCHAR(100)    -- Shown only if Purpose = 'Private Layout'
conformity                      BIT             -- Does proposed use conform to plan?
accessibility                   BIT             -- Does site have adequate access?
existing_road_reservation       VARCHAR(255)    -- Existing road setback
recommended_road_reservation    VARCHAR(255)    -- Recommended road setback
adequate_size_requirement       BIT             -- Does plot size meet requirements?
land_traversing_utility         BIT             -- Do utilities traverse the land?
existing_site_length            DECIMAL(10,2)   -- Existing length in meters
existing_site_width             DECIMAL(10,2)   -- Existing width in meters
existing_site_area              DECIMAL(10,2)   -- Auto-calculated: length × width
recommended_site_length         DECIMAL(10,2)   -- Recommended length in meters
recommended_site_width          DECIMAL(10,2)   -- Recommended width in meters
recommended_site_height         DECIMAL(10,2)   -- Recommended height in meters
recommended_site_area           DECIMAL(10,2)   -- Auto-calculated: length × width
```

**Source Field Values:**
- `ST One Stop Shop` - For Sectional Titling applications
- `Conversion Applications` - For land conversion files
- Additional modules can be added as needed

---

## Technology Stack

### Frontend
- **Blade Templates** - Laravel templating engine
- **Tailwind CSS** - Utility-first CSS framework
- **Alpine.js** - Lightweight JavaScript framework
- **DataTables** - Server-side table processing
- **Select2** - Enhanced select dropdowns
- **Lucide Icons** - Icon library
- **jQuery** - JavaScript library

### Backend
- **Laravel 9** - PHP framework
- **SQL Server** - Primary database (sqlsrv connection)
- **Eloquent ORM** - Database abstraction layer

---

## User Workflow

### Step 1: Access Module
1. Navigate to **Physical Planning** in sidebar
2. Click on **Conversion Applications**
3. Select **Planning Recommendation**

### Step 2: Review Files
1. View list of conversion files in table
2. Use search to find specific files

### Step 3: Conduct Site Inspection
1. Click action menu (three dots) for a file
2. Select **Enter Inspection Details**
3. Fill in all required inspection information
4. Complete conversion-specific fields
5. Click **Submit Inspection Report**

### Step 4: Review Inspection
1. Use **View/Edit Inspection Details**
2. Review or update inspection data

### Step 5: Submit Recommendation
1. Click **Submit Planning Recommendation**
2. System validates that inspection is complete
3. Recommendation is submitted for approval

---

## Implementation Tasks

### Phase 1: Database Schema Update ✅ COMPLETED
- [x] Add new columns to `joint_site_inspection_reports` table
- [x] Create migration file: `2026_03_01_120000_add_conversion_fields_to_joint_site_inspection_reports_table.php`
- [x] Update model with new fillable fields (`JointSiteInspectionReport.php`)
- [x] Add type casts for boolean and decimal fields
- [ ] Run migration on development database: `php artisan migrate --database=sqlsrv`
- [ ] Verify columns exist in database

### Phase 2: JSI Modal Enhancement ✅ COMPLETED
- [x] Detect conversion files in JSI modal (options flag + CON- prefix)
- [x] Hide ST-specific fields for conversion files (sections count, shared utilities, measurement summary)
- [x] Add 10 new conversion-specific fields
- [x] Implement conditional Private Layout Number field
- [x] Add auto-calculation for area measurements (existing/recommended)
- [x] Update validation rules for conversion context

### Phase 3: Backend Updates ✅ COMPLETED
- [x] Update JSI save endpoint to handle new fields
- [x] Add validation for conversion-specific fields (required when source = Conversion Applications)
- [x] Update JSI report retrieval to include new fields (populate from saved report)
- [ ] Test data persistence

### Phase 4: Testing
- [ ] Test JSI modal with conversion files
- [ ] Test JSI modal with ST files (ensure no regression)
- [ ] Verify field visibility rules
- [ ] Test validation rules
- [ ] Test area calculations

---

## Future Enhancements

### Planned Features
1. **Enable Advanced Filters** - Unhide filter section
2. **Bulk Actions** - Process multiple files simultaneously
3. **Export Functionality** - Export to Excel/PDF
4. **Status Tracking** - Visual workflow indicators
5. **Notification System** - Alerts for pending inspections
6. **Dashboard Analytics** - Statistics and charts
7. **Document Management** - Upload supporting documents
8. **Workflow Automation** - Automatic routing
9. **Mobile Responsiveness** - Enhanced mobile view
10. **Audit Trail** - Complete action history

---

## Support & Maintenance

### Version History
- **v1.1.0** (Planned) - Add conversion-specific JSI fields
- **v1.0.0** (March 2026) - Initial release

### Contact
For issues or feature requests, contact the KLAES development team.

---

*Last Updated: March 1, 2026*  
*Document Version: 1.1.0*  
*Module Version: 1.0.0*
