# Conversion Applications – Joint Site Inspection Report Integration

## Requirement
Display Joint Site Inspection Reports in the Conversion Applications table by:
1. Querying `joint_site_inspection_reports` table where `source = "PP_CONVERSION"`
2. Showing table headers and relevant data columns in the view
3. Adding a "JSI Report Details" button that open the  report details for data entry
3. if the file number the system should atomaticaly pupolate the feilds 
see exampple usage in Generate Consent Application


## Implementation Steps
1. Update the Conversion Applications controller to load matching JSI reports
2. Add table columns to display key JSI report fields
3. Create a "Enter JSI Report Details" button above  the table
4. Build/link a modal (triggered via AJAX) to display complete report information
5. Ensure proper permission checks and data validation



 # Bills & Payments Integration for Conversion Applications

## Overview
Integrate betterment bill generation and payment tracking into the Bills & Payments subsystem, filtered by conversion application data from Joint Site Inspection Reports.

## Scope

### Bills Management (Betterment Bills)
- **Source filter**: Display only applicants from `joint_site_inspection_reports` where `source = "PP_CONVERSION"`
- **Bill type**: Betterment bills only
- **Generation**: On "Generate Bill", save records to `billing` table with:
    - `ref_id` format: `PPBB-{YYYY}-{SEQUENTIAL}` (e.g., `PPBB-2026-001`)
    - `source = "PP_BETTERMENT_BILL"`
- **URL pattern**: `programmes/bills?source=pp_conversion`

### Payments
- **Filter condition**: `source = "PP_BETTERMENT_BILL"`
- **Display**: Link to bills generated from betterment workflow
- **URL pattern**: `programmes/payments?source=pp_betterment_bill`

### Payment Reports
- **Filter condition**: `source = "PP_BETTERMENT_BILL"`
- **Display**: Aggregated payment status and history
- **URL pattern**: `programmes/payment-reports?source=pp_betterment_bill`

## Betterment Fee Calculation

### Formula
```
Total Fee = Base Fee + max(0, (Total Area − Base Area) × ₦5)
```

### Rate Table by Category
| Category | Base Area | Base Fee |
|----------|-----------|----------|
| Industrial | 100 m² | ₦35,000 |
| Commercial | 100 m² | ₦25,000 |
| Petrol Filling Station | 100 m² | ₦50,000 |
| Agricultural | 5,000 m² | ₦25,000 |
| Residential | 100 m² | ₦15,000 |

### Example (Residential, 350 m²)
- Base Area: 100 m², Base Fee: ₦15,000
- Excess Area: 250 m²
- **Total: ₦15,000 + (250 × ₦5) = ₦16,250**

## Implementation Notes
- No UI changes; conditions and data sources only
- Reuse existing Bills & Payments module structure
- Validate `source` parameter on all list/filter endpoints
