# Caveat Autofill — Table-to-Form Field Mapping

When a file number is selected on the caveat form, the system queries **3 source tables** (+ 1 optional fallback) and maps their columns to caveat form fields. This document shows exactly which DB columns feed each form field.


THIS OCCUPANCY PERMIT (OP) IS REGISTERED AS

NO 63 AT PAGE 63 IN VOLUME 22

OF THE MINISTRY OF LAND AND PHYSICAL PLANNING

AT KANO STATE



to 


THIS OCCUPANCY PERMIT (OP) IS REGISTERED AS

NO 63 AT PAGE 63 IN VOLUME 22

OF DEEDS REGISTRY MINISTRY OF LAND AND PHYSICAL PLANNING

AT KANO STATE




---

## Source Tables (ordered by priority)

| Priority | Old Table Name          | New Table Name       | DB         |
|----------|------------------------|----------------------|------------|
| 90       | `property_records`     | **`pra`**            | sqlsrv     |
| 80       | `registered_instruments` | **`deed_registrations`** | sqlsrv |
| 70       | `CofO` _(variants)_   | **`CofO_staging`**   | sqlsrv     |
| 40       | `fileNumber`           | `fileNumber` _(fallback, no change)_ | sqlsrv |

> Higher-priority source wins when the same form field is populated by multiple tables.

---

## File Number Search Columns (used to match the selected file number)

| Table              | Columns Searched                                                                                            |
|--------------------|-------------------------------------------------------------------------------------------------------------|
| `pra`              | `fileno`, `mlsFNo`, `kangisFileNo`, `NewKANGISFileno`, `temp_fileno`                                       |
| `deed_registrations` | `fileno`, `parent_fileno`                                                                                 |
| `CofO_staging`     | `fileno`, `np_fileno`, `mlsFNo`, `kangisFileNo`, `NewKANGISFileno`, `cofo_no`, `temp_fileno`               |

---

## Form Field → Source Column Mapping

### Section A: Caveat Information

| Caveat Form Field       | `pra` Column(s)                                                                 | `deed_registrations` Column(s)      | `CofO_staging` Column(s)                                                  | Confidence |
|--------------------------|---------------------------------------------------------------------------------|--------------------------------------|---------------------------------------------------------------------------|------------|
| **Encumbrance Type**     | `transaction_type` + `instrument_type` → mapped via `mapEncumbranceType()`      | `instrument_type` → mapped           | Defaults to _"Government Acquisition/Reservation"_                        | 0.92 / 0.80 / 0.40 |
| **Instrument Type**      | `instrument_type` _(fallback: `transaction_type`)_                              | `instrument_type`                    | `instrument_type` → `cofo_type` → `transaction_type`                      | 0.90 / 0.82 / 0.60 |
| **Location**             | `property_description` → `location` → (`layout` + `lgsaOrCity`)                | `property_description` → (`plot_number` + `size`)  | `location` → `plot_no` → `property_description` → (`layout` + `lgsaOrCity`) | 0.85 / 0.68 / 0.55 |
| **Land Use**             | `land_use`                                                                      | —                                    | `land_use`                                                                | 0.40 / — / 0.50 |

### Section B: Parties Involved

| Caveat Form Field       | `pra` Column(s)                                                                           | `deed_registrations` Column(s) | `CofO_staging` Column(s) | Confidence |
|--------------------------|-------------------------------------------------------------------------------------------|--------------------------------|---------------------------|------------|
| **Applicant/Solicitor** (petitioner) | Heuristic from `Assignor`, `Lessor`, `Mortgagor`, `Grantor` based on `transaction_type` | `grantor`                      | `Grantor`                 | 0.88 / 0.78 / 0.62 |
| **Grantor**              | `Grantor`                                                                                 | `grantor`                      | `Grantor`                 | 0.88 / 0.78 / 0.62 |
| **Grantee**              | `Grantee`                                                                                 | `grantee`                      | `Grantee`                 | 0.88 / 0.78 / 0.62 |

#### Additional party columns read from `pra` (used for petitioner heuristic):

| Column         | Maps to     |
|----------------|-------------|
| `Assignor`     | assignor    |
| `Assignee`     | assignee    |
| `Mortgagor`    | mortgagor   |
| `Mortgagee`    | mortgagee   |
| `Surrenderor`  | surrenderor |
| `Surrenderee`  | surrenderee |
| `Lessor`       | lessor      |
| `Lessee`       | lessee      |
| `Grantor`      | grantor     |
| `Grantee`      | grantee     |

### Section C: Registration & Tracking

| Caveat Form Field         | `pra` Column(s)                    | `deed_registrations` Column(s)              | `CofO_staging` Column(s)        | Confidence |
|----------------------------|------------------------------------|---------------------------------------------|----------------------------------|------------|
| **Serial No.**             | `serialNo`                         | `serial_no`                                 | `serialNo`                       | 0.90 / 0.70 / 0.60 |
| **Page No.**               | `pageNo` _(fallback: `serialNo`)_  | `page_no` _(fallback: `serial_no`)_         | `pageNo` _(fallback: `serialNo`)_| 0.85 / 0.65 / 0.55 |
| **Volume No.**             | `volumeNo`                         | `volume_no`                                 | `volumeNo`                       | 0.90 / 0.70 / 0.60 |
| **Registration Number**    | Composed: `REG/{year}/P{page}`     | `registration_number`                       | Composed: `REG/{year}/P{page}`   | 0.92 / 0.72 / 0.62 |
| **Start Date**             | `transaction_date` → `regDate`     | `instrument_date` → `deeds_date`            | `transaction_date`               | 0.75 / 0.65 / 0.55 |

### Additional Fields

| Caveat Form Field  | `pra` Column(s)       | `deed_registrations` Column(s)  | `CofO_staging` Column(s)                 | Confidence |
|---------------------|-----------------------|---------------------------------|------------------------------------------|------------|
| **Instructions**    | `schedule`            | _(not available)_               | —                                        | 0.55 / — / — |
| **Remarks**         | `caveated_comment`    | _(not available)_               | `remarks` → `caveated_comment`           | 0.50 / — / 0.50 |

---

## Columns NOT Used by Caveat (per table)

These columns exist in the tables but are **not relevant** to the caveat autofill:

### `pra` — Unused Columns

| Column | Reason |
|--------|--------|
| `id`, `prop_id`, `caveat_id` | Internal IDs |
| `oldKNNo`, `related_file_number`, `tp_no`, `lpkn_no`, `approved_plan_no` | Alternative reference numbers, not needed for caveat form |
| `regNo` | Legacy reg number—form composes its own |
| `period`, `period_unit` | Lease period data, not on caveat form |
| `Mortgagor_2`, `Mortgagor_3` | Secondary party entries |
| `Donor`, `Donee`, `Releasor`, `Releasee` | Party types not mapped to caveat |
| `Vendor`, `Purchaser` | Sale parties not mapped |
| `party_1` … `party_5`, `Surrenderor_2` | Extra party slots |
| `plot_no`, `plot_size`, `house_no`, `streetName`, `layout`, `districtName`, `lgsaOrCity` | Sub-location fields (only used as fallback for Location) |
| `metric_sheet` | Survey data |
| `date_recommended`, `date_approved`, `lease_begins`, `lease_expires`, `date_expired` | Date lifecycle fields |
| `assignment_date`, `surrender_date`, `revoked_date`, `deeds_date`, `deeds_time` | Specific action dates |
| `is_caveated` | Boolean flag, not a form field |
| `regranted_from` | Provenance tracking |
| `source`, `migration_source`, `date_migrated`, `migrated_by` | Migration metadata |
| `created_at`, `created_by`, `date_created`, `updated_at`, `updated_by`, `deleted_at` | Timestamps/audit |
| `test_control`, `comments`, `remarks`, `is_mapped` | Admin/debug fields |

### `CofO_staging` — Unused Columns

| Column | Reason |
|--------|--------|
| `id`, `prop_id` | Internal IDs |
| `title_type` | Certificate classification |
| `transaction_time` | Time component not needed |
| `period`, `period_unit` | Lease period |
| `Assignor`, `Assignee`, `Mortgagor`, `Mortgagee`, `Surrenderor`, `Surrenderee`, `Lessor`, `Lessee` | Party types not mapped for CofO source |
| `regNo` | Legacy reg number |
| `cofo_type`, `cofo_no`, `cofo_date`, `application_id` | CofO-specific metadata |
| `oldKNNo`, `temp_fileno` | Alt file references |
| `migration_source`, `date_migrated`, `migrated_by` | Migration metadata |
| `created_at`, `updated_at`, `deleted_at`, `created_by`, `updated_by`, `date_created` | Timestamps/audit |
| `is_caveated`, `caveated_comment` | Only used as fallback for Remarks |
| `assignment_date`, `surrender_date`, `revoked_date`, `date_expired`, `regranted_from` | Action dates |
| `test_control`, `comments` | Admin/debug |

### `deed_registrations` — Unused Columns

| Column | Reason |
|--------|--------|
| `id` | Internal ID |
| `parent_fileno` | Only used for file number search |
| `deeds_time` | Time component not needed |
| `lga`, `district` | Sub-location fields (not separately mapped) |
| `size` | Only used as location fallback |
| `status` | Registration status |
| `data` | JSON blob |
| `rds_exists`, `cor_exists` | Flag fields |
| `instrument_capture_id` | Foreign key |
| `created_by`, `updated_by`, `created_at`, `updated_at` | Timestamps/audit |

---

## Column Name Changes Required in Controller

When migrating the controller from old → new table names, these column mappings must also be updated:

| Old Table → New Table            | Column Changes Needed |
|----------------------------------|-----------------------|
| `property_records` → `pra`       | Column names appear identical — no renames needed |
| `registered_instruments` → `deed_registrations` | `reg_serial` → `serial_no`, `reg_page` → `page_no`, `reg_volume` → `volume_no`, `instrumentDate` → `instrument_date` or `deeds_date`, `plotNumber` → `plot_number`, `Grantor` → `grantor`, `Grantee` → `grantee`, `file_number`/`file_no`/`fileno` → `fileno` |
| `CofO` → `CofO_staging`         | Column names appear identical — no renames needed |
