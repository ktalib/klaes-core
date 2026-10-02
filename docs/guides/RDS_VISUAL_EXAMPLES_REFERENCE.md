# RDS Print Template – Visual Reference & Examples

**Purpose:** Quick visual reference for RDS document rendering  
**Date:** November 13, 2025

---

## Example 1: ASSIGNMENT Document

### Database Values
```sql
instrument_type: "Assignment (Transfer of Title)"
Grantor: "Mr. John Okafor"
GrantorAddress: "123 Main Street, Kano, Nigeria"
Grantee: "Mrs. Jane Olawale"
GranteeAddress: "456 Oak Avenue, Lagos, Nigeria"
StFileNo: "ST-RES-2025-001"
registration_date: "2025-11-12"
consideration: "₦500,000.00"
stamp_duty: "₦5,000.00"
registration_fee: "₦10,000.00"
print_count: 0
```

### Rendered Output
```
                    ASSIGNMENT
        THIS IS A DEED OF Assignment (Transfer of Title)

Dated: 12
day of November
2025

Executed by: Mr. John Okafor
Of: 123 Main Street, Kano, Nigeria

The Right of Occupancy No. ST-RES-2025-001 is Assigned
to: Mrs. Jane Olawale
of: 456 Oak Avenue, Lagos, Nigeria

From: _________________ day of _________________ 20_______


                          CONSIDERATION    ₦500,000.00
                          STAMP DUTY:      ₦5,000.00
                          REGISTRATION FEE ₦10,000.00

                      ...to be REGISTERED
```

### Screen Display (Non-Printable Elements)
```
[Blue Print Button] (Top-right corner)

...to be REGISTERED

RDS Reference: RDS-2025-00001
Generated: 13/11/2025 10:30:45
Print Count: 0 (ORIGINAL)
STM Reference: STM-2025-001
Instrument ID: 1234
Status: registered

[Dark gray text, hidden when printing]
```

---

## Example 2: MORTGAGE Document

### Database Values
```sql
instrument_type: "Mortgage"
Grantor: "Mr. Ahmed Hassan"
GrantorAddress: "789 Hill Road, Abuja"
Grantee: "First Bank PLC"
GranteeAddress: "Bank House, Victoria Island, Lagos"
StFileNo: "ST-COM-2025-042"
registration_date: "2025-11-10"
consideration: "₦2,000,000.00"
stamp_duty: "₦20,000.00"
registration_fee: "₦25,000.00"
print_count: 1  # Second print
```

### Rendered Output
```
[Watermark: "COPY" - semi-transparent, -45° angle, center of page]

                      MORTGAGE
              THIS IS A DEED OF Mortgage

Dated: 10
day of November
2025

Executed by: Mr. Ahmed Hassan
Of: 789 Hill Road, Abuja

The Right of Occupancy No. ST-COM-2025-042 is Mortgaged
to: First Bank PLC
of: Bank House, Victoria Island, Lagos

From: _________________ day of _________________ 20_______


                          CONSIDERATION    ₦2,000,000.00
                          STAMP DUTY:      ₦20,000.00
                          REGISTRATION FEE ₦25,000.00

                      ...to be REGISTERED
```

### Key Differences from ASSIGNMENT
- Title: "MORTGAGE" (not "ASSIGNMENT")
- Subtitle: "THIS IS A DEED OF Mortgage"
- Action: "is Mortgaged" (not "is Assigned")
- Party Labels: Mortgagor / Mortgagee (internally, in the logic)
- Watermark: "COPY" appears (since print_count = 1)

---

## Example 3: LEASE Document

### Database Values
```sql
instrument_type: "Lease Agreement"
Grantor: "Kano State Government"
GrantorAddress: "Government House, Kano"
Grantee: "Mr. Ibrahim Mohammed"
GranteeAddress: "2024 Park Lane, Kano"
StFileNo: "ST-IND-2025-098"
registration_date: "2025-09-15"
consideration: "₦1,000,000.00"
stamp_duty: "₦10,000.00"
registration_fee: "₦15,000.00"
print_count: 0
```

### Rendered Output
```
                    LEASE AGREEMENT
          THIS IS A DEED OF Lease Agreement

Dated: 15
day of September
2025

Executed by: Kano State Government
Of: Government House, Kano

The Right of Occupancy No. ST-IND-2025-098 is Leased
to: Mr. Ibrahim Mohammed
of: 2024 Park Lane, Kano

From: _________________ day of _________________ 20_______


                          CONSIDERATION    ₦1,000,000.00
                          STAMP DUTY:      ₦10,000.00
                          REGISTRATION FEE ₦15,000.00

                      ...to be REGISTERED
```

### Key Differences
- Title: "LEASE AGREEMENT"
- Action: "is Leased"

---

## Example 4: Missing Data (Error Handling)

### Database Values
```sql
instrument_type: "Assignment"    -- Provided
Grantor: NULL                     -- Missing
GrantorAddress: NULL              -- Missing
Grantee: "Jane Smith"             -- Provided
GranteeAddress: NULL              -- Missing
StFileNo: NULL                    -- Missing
registration_date: NULL           -- Missing
fileno: "KANGIS/2025/001"         -- Fallback
consideration: NULL               -- Missing
print_count: 0
```

### Rendered Output
```
                    ASSIGNMENT
          THIS IS A DEED OF Assignment

Dated: 
day of 
[blank]

Executed by: [blank line]
Of: [blank line]

The Right of Occupancy No. KANGIS/2025/001 is Assigned
to: Jane Smith
of: [blank line]

From: _________________ day of _________________ 20_______


                          CONSIDERATION    [blank line]
                          STAMP DUTY:      [blank line]
                          REGISTRATION FEE [blank line]

                      ...to be REGISTERED
```

### Notes
- Missing Grantor: Shows blank field
- Falls back to fileno (KANGIS format) for file number
- Missing address fields: Empty but document still renders
- No error message shown (graceful degradation)

---

## Example 5: JSON Array Party Names

### Database Values
```sql
Grantor: '["John Okafor", "Mary Okafor"]'    -- JSON array
Grantee: '["Akintola Mohammed"]'              -- JSON array with one element
```

### Processing
```php
// extractPartyName() function processes arrays
Input:  ["John Okafor", "Mary Okafor"]
Output: John Okafor (first element extracted)

Input:  ["Akintola Mohammed"]
Output: Akintola Mohammed (single element)
```

### Rendered Output
```
Executed by: John Okafor
(Note: Only the first name displayed, even though Grantor is multiple people)

to: Akintola Mohammed
```

---

## Example 6: Date Formatting Variations

### Different Dates

**January 1st, 2025:**
```
Dated: 01
day of January
2025
```

**December 31st, 2025:**
```
Dated: 31
day of December
2025
```

**Mid-month date:**
```
Dated: 15
day of June
2025
```

### Date Processing Logic
```php
Input:  "2025-01-01 08:30:00"
Output: 
  day:   "01"
  month: "January"
  year:  "2025"
```

---

## Watermark Display Comparison

### First Print (print_count = 0)

**On Screen:**
```
[Clean document, no watermark]
Metadata shows: Print Count: 0 (ORIGINAL)
```

**Print Preview:**
```
[Completely clean, no watermark, no metadata]
```

**Printed Page:**
```
[Clean professional document]
```

---

### Subsequent Prints (print_count >= 1)

**On Screen:**
```
              COPY
       (semi-transparent, large, -45° angle)
       (centered on page behind content)

[Document content visible underneath]

Metadata shows: Print Count: 2 (COPY)
```

**Print Preview:**
```
[Clean document, watermark NOT visible]
[Metadata NOT visible]
```

**Printed Page:**
```
[Clean professional document - no watermark printed]
```

---

## Print Output Example

### Browser Print Preview (Ctrl+P)
```
┌─────────────────────────────────────────────────┐
│                                                   │
│                    ASSIGNMENT                    │
│        THIS IS A DEED OF Assignment              │
│                                                   │
│ Dated: 12                                         │
│ day of November                                   │
│ 2025                                              │
│                                                   │
│ Executed by: John Okafor                         │
│ Of: 123 Main Street, Kano, Nigeria               │
│                                                   │
│ The Right of Occupancy No. ST-RES-2025-001       │
│ is Assigned to: Jane Olawale                     │
│ of: 456 Oak Avenue, Lagos, Nigeria               │
│                                                   │
│ From: _________________ day of ___________ 20___ │
│                                                   │
│                      CONSIDERATION [___________] │
│                      STAMP DUTY: [_______________│
│                      REGISTRATION FEE [_________] │
│                                                   │
│                   ...to be REGISTERED             │
│                                                   │
└─────────────────────────────────────────────────┘
```

**Format:** A4 Portrait, 0.5" margins all sides

---

## CSS Classes & Visual Elements

### Printable Elements
```css
.document-title           /* Underlined heading */
.document-subtitle        /* Italicized subtitle */
.underline-field          /* Blank fill-in field with bottom border */
.form-line                /* Content area with bottom border */
.document-content         /* Main container for printing */
```

### Non-Printable Elements
```css
.no-print                 /* Hidden during print */
.watermark                /* Visible on screen, hidden from print */
print button              /* Hidden via .no-print */
metadata footer           /* Hidden via .no-print */
```

---

## Responsive Display Examples

### Desktop (1920x1080)
```
[Centered document in viewport]
[Print button visible top-right]
[Full document width ~800px]
```

### Tablet (768x1024)
```
[Document with slight zoom-out]
[Print button still accessible]
[Vertical scrolling may be needed]
```

### Mobile (375x667)
```
[Document responsive to narrow width]
[Print button accessible but smaller]
[Significant scrolling needed]
[Layout preserved, not cut off]
```

---

## Dynamic Subtitle Examples

| Instrument Type | Rendered Subtitle |
|---|---|
| `"Assignment"` | `"THIS IS A DEED OF Assignment"` |
| `"Mortgage"` | `"THIS IS A DEED OF Mortgage"` |
| `"Lease Agreement"` | `"THIS IS A DEED OF Lease Agreement"` |
| `"Release"` | `"THIS IS A DEED OF Release"` |
| `"Surrender"` | `"THIS IS A DEED OF Surrender"` |
| `"ST Transfer"` | `"THIS IS A DEED OF ST Transfer"` |

---

## Action Text Examples

| Instrument Type | Rendered Action |
|---|---|
| Contains "MORTGAGE" | "is Mortgaged" |
| Contains "ASSIGNMENT" | "is Assigned" |
| Contains "LEASE" | "is Leased" |
| Contains "RELEASE" | "is Released" |
| Contains "SURRENDER" | "is Surrendered" |
| Other types | "is [Type]" |

---

## Party Label Mapping

| Instrument Type | Executed By | To Field |
|---|---|---|
| Mortgage | Mortgagor | Mortgagee |
| Assignment | Assignor | Assignee |
| Lease | Lessor | Lessee |
| Other | Grantor | Grantee |

---

## Metadata Footer Display

```
RDS Reference: RDS-2025-00042
Generated: 13/11/2025 09:15:30
Print Count: 1 (COPY)
STM Reference: STM-2025-055
Instrument ID: 567
Status: registered
```

**Appearance:**
- Font size: Extra small (xs)
- Color: Gray (#6B7280)
- Alignment: Right-aligned
- Hidden from print: Yes (`.no-print` class)

---

## Browser Rendering Comparison

### Chrome/Edge
```
Perfect rendering, exact colors, clean watermark
Print quality: Excellent
```

### Firefox
```
Perfect rendering, exact colors, clean watermark
Print quality: Excellent
```

### Safari
```
Perfect rendering, watermark may appear slightly different shade
Print quality: Good
```

### IE 11
```
Not supported - CSS Grid issues, may not render correctly
```

---

## Common Printing Scenarios

### Scenario 1: Print to Physical Printer
```
Browser → Print Dialog → Printer → A4 Paper
Result: Professional-grade printout, single page
```

### Scenario 2: Print to PDF
```
Browser → Print Dialog → Save as PDF → PDF File
Result: ~75-100 KB PDF file with full document
```

### Scenario 3: Print Preview
```
Browser → Print Dialog → Preview Mode
Result: Document preview without watermark/metadata
```

### Scenario 4: Screen Display (No Print)
```
Browser → View in Viewport
Result: Document with watermark (if applicable) and metadata footer
```

---

## Quick Visual Checklist

When viewing an RDS document, verify:

- [ ] Title matches instrument type (not "ASSIGNMENT" for all)
- [ ] Subtitle reads "THIS IS A DEED OF [type]"
- [ ] Date properly formatted (day/month/year separated)
- [ ] Grantor name displays in "Executed by" field
- [ ] Grantor address displays in "Of" field
- [ ] File number displays in Right of Occupancy field
- [ ] Grantee name displays in "to" field
- [ ] Grantee address displays in "of" field
- [ ] Action text matches type (is Assigned/Mortgaged/Leased)
- [ ] Financial fields show values or blank lines
- [ ] Watermark displays as "COPY" if print_count >= 1
- [ ] Metadata footer visible (but hidden when printing)
- [ ] Print button visible in top-right corner

---

## Troubleshooting Visual Examples

### Problem: Title Shows "ASSIGNMENT" for All Documents
**Cause:** `instrument_type` field not populated or query failing
**Fix:** Verify database query in RDSController

### Problem: Date Fields All Blank
**Cause:** `registration_date` NULL and `instrumentDate` NULL
**Fix:** Ensure at least one date field has a value

### Problem: Watermark Visible When Printing
**Cause:** CSS `@media print` not applied correctly
**Fix:** Check CSS media query is loading properly

### Problem: Party Names Show JSON Syntax
**Cause:** `extractPartyName()` function not called or failing
**Fix:** Verify function is defined and JSON is valid

---

**Reference Created:** November 13, 2025  
**Status:** Ready for visual verification during testing
