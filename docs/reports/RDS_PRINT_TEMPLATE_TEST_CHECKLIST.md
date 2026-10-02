# RDS Print Template – Comprehensive Testing Checklist

**Document:** Test & Validation Checklist  
**File:** `resources/views/instrument_registration/rds/print.blade.php`  
**Status:** Ready for Testing  
**Date:** November 13, 2025

---

## Pre-Testing Requirements

### Database Setup
- [ ] `rds_tracking` table created with all required columns
- [ ] `registered_instruments` table populated with test data
- [ ] Foreign key relationships established
- [ ] Sample RDS records generated for testing

### Environment Setup
- [ ] Laravel application running
- [ ] SQL Server connection working
- [ ] RDSController properly deployed
- [ ] Routes configured correctly

### Test Data Preparation
- [ ] Create test ASSIGNMENT instrument
- [ ] Create test MORTGAGE instrument
- [ ] Create test LEASE instrument
- [ ] Create test instrument with JSON array party names
- [ ] Create RDS records for each

---

## Unit Testing Checklist

### Section 1: Document Title & Subtitle Logic

#### Test 1.1: ASSIGNMENT Document Type
```
Data: instrument_type = 'Assignment (Transfer of Title)'
Expected Title: 'Assignment (Transfer of Title)'
Expected Subtitle: 'THIS IS A DEED OF Assignment (Transfer of Title)'
Expected Action: 'is Assigned'
[ ] Title matches instrument_type
[ ] Subtitle properly formatted
[ ] Action text is correct
```

#### Test 1.2: MORTGAGE Document Type
```
Data: instrument_type = 'Mortgage'
Expected Title: 'Mortgage'
Expected Subtitle: 'THIS IS A DEED OF Mortgage'
Expected Action: 'is Mortgaged'
[ ] Title displays correctly
[ ] Subtitle includes MORTGAGE phrase
[ ] Action text shows "is Mortgaged"
```

#### Test 1.3: LEASE Document Type
```
Data: instrument_type = 'Lease Agreement'
Expected Title: 'Lease Agreement'
Expected Subtitle: 'THIS IS A DEED OF Lease Agreement'
Expected Action: 'is Leased'
[ ] Title matches exactly
[ ] Subtitle is correct
[ ] Action text is "is Leased"
```

#### Test 1.4: RELEASE Document Type
```
Data: instrument_type = 'Release'
Expected Title: 'Release'
Expected Subtitle: 'THIS IS A DEED OF Release'
Expected Action: 'is Released'
[ ] Title populated correctly
[ ] Subtitle formatted properly
[ ] Action text shows "is Released"
```

#### Test 1.5: SURRENDER Document Type
```
Data: instrument_type = 'Surrender'
Expected Title: 'Surrender'
Expected Subtitle: 'THIS IS A DEED OF Surrender'
Expected Action: 'is Surrendered'
[ ] All fields match expected values
```

#### Test 1.6: Fallback Behavior
```
Data: instrument_type = NULL
Expected Title: 'ASSIGNMENT' (fallback)
Expected Subtitle: 'THIS IS A DEED OF ASSIGNMENT'
Expected Action: 'is Assigned'
[ ] Fallback to ASSIGNMENT works
[ ] No error displayed
[ ] Document still renders
```

---

### Section 2: Date Formatting & Display

#### Test 2.1: Valid Registration Date
```
Database: 2025-11-12 14:30:00.000
Expected Display:
  Dated: 12
  day of November
  2025
[ ] Day formatted as "12" (2-digit)
[ ] Month displays as "November" (full name)
[ ] Year displays as "2025" (4-digit)
```

#### Test 2.2: Edge Case: January 1st
```
Database: 2025-01-01 00:00:00.000
Expected Display:
  Dated: 01
  day of January
  2025
[ ] Day formatted correctly with leading zero
[ ] Month is "January"
[ ] Year is "2025"
```

#### Test 2.3: Edge Case: December 31st
```
Database: 2025-12-31 23:59:59.999
Expected Display:
  Dated: 31
  day of December
  2025
[ ] Day is "31"
[ ] Month is "December"
[ ] Year is "2025"
```

#### Test 2.4: Missing Registration Date
```
Database: registration_date = NULL, instrumentDate = NULL
Expected Display: All date fields blank
[ ] No error on page
[ ] Date section displays cleanly
[ ] Empty field underlines visible
```

#### Test 2.5: Fallback to instrumentDate
```
Database: registration_date = NULL
           instrumentDate = '2025-10-15 09:00:00'
Expected Display:
  Dated: 15
  day of October
  2025
[ ] Falls back to instrumentDate correctly
[ ] Date formatted properly
[ ] No error messages
```

---

### Section 3: Party Names & Addresses

#### Test 3.1: Simple Party Names (Strings)
```
Database: 
  Grantor = 'Musa Ali'
  GrantorAddress = '123 Main Street, Lagos'
  Grantee = 'Jane Smith'
  GranteeAddress = '456 Oak Avenue, Abuja'

Expected Display:
  Executed by: Musa Ali
  Of: 123 Main Street, Lagos
  to: Jane Smith
  of: 456 Oak Avenue, Abuja

[ ] Grantor name displays correctly
[ ] Grantor address displays correctly
[ ] Grantee name displays correctly
[ ] Grantee address displays correctly
```

#### Test 3.2: JSON Array Party Names (Single)
```
Database:
  Grantor = '["Musa Ali"]'
  Grantee = '["Jane Smith"]'

Expected Display:
  Executed by: Musa Ali
  to: Jane Smith

[ ] JSON array is properly decoded
[ ] First element extracted and displayed
[ ] No JSON syntax visible
```

#### Test 3.3: JSON Array Party Names (Multiple)
```
Database:
  Grantor = '["Musa Ali", "Mary Johnson"]'
  Grantee = '["Jane Smith", "Robert Brown"]'

Expected Display:
  Executed by: Musa Ali (first element)
  to: Jane Smith (first element)

[ ] Multiple elements in array
[ ] Only first element displayed
[ ] Remaining elements not shown
```

#### Test 3.4: Missing Party Names
```
Database:
  Grantor = NULL
  Grantee = NULL
  GrantorAddress = NULL
  GranteeAddress = NULL

Expected Display:
  Executed by: [empty]
  Of: [empty]
  to: [empty]
  of: [empty]

[ ] Fields are blank (not "null" or "N/A")
[ ] No error on page
[ ] Form lines display cleanly
```

#### Test 3.5: Mixed Cases (Some NULL, Some Not)
```
Database:
  Grantor = 'Musa Ali'
  GrantorAddress = NULL
  Grantee = 'Jane Smith'
  GranteeAddress = '789 Park Lane'

Expected Display:
  Executed by: Musa Ali
  Of: [empty]
  to: Jane Smith
  of: 789 Park Lane

[ ] Non-null values display
[ ] Null values remain blank
[ ] Layout not disrupted
```

---

### Section 4: File Number Display

#### Test 4.1: Primary File Number Field
```
Database: StFileNo = 'ST-RES-2025-001'
Expected Display:
  The Right of Occupancy No. ST-RES-2025-001

[ ] File number displays in correct field
[ ] Formatting preserved
[ ] Number visible and readable
```

#### Test 4.2: Fallback to Secondary File Number
```
Database: StFileNo = NULL, fileno = 'ST-IND-2025-042'
Expected Display:
  The Right of Occupancy No. ST-IND-2025-042

[ ] Falls back to fileno correctly
[ ] Number displays properly
[ ] No error on page
```

#### Test 4.3: Legacy Format File Number
```
Database: StFileNo = NULL, fileno = 'KANGIS/2025/001'
Expected Display:
  The Right of Occupancy No. KANGIS/2025/001

[ ] Legacy format accepted
[ ] Format preserved
[ ] Display correct
```

#### Test 4.4: Missing File Number
```
Database: StFileNo = NULL, fileno = NULL, file_number = NULL
Expected Display:
  The Right of Occupancy No. [empty field]

[ ] No error on page
[ ] Field appears blank
[ ] Document still prints properly
```

---

### Section 5: Action Text (is Assigned/Mortgaged/etc.)

#### Test 5.1: ASSIGNMENT Action
```
Condition: instrument_type contains 'ASSIGNMENT'
Expected: 'is Assigned'
[ ] Action text matches
[ ] Placed correctly in document
[ ] Grammar correct
```

#### Test 5.2: MORTGAGE Action
```
Condition: instrument_type contains 'MORTGAGE'
Expected: 'is Mortgaged'
[ ] Action text displays
[ ] Correct placement
[ ] Proper grammar
```

#### Test 5.3: LEASE Action
```
Condition: instrument_type contains 'LEASE'
Expected: 'is Leased'
[ ] Action text correct
[ ] Position correct
[ ] No typos
```

#### Test 5.4: RELEASE Action
```
Condition: instrument_type contains 'RELEASE'
Expected: 'is Released'
[ ] Action text displays
[ ] Correct format
```

#### Test 5.5: SURRENDER Action
```
Condition: instrument_type contains 'SURRENDER'
Expected: 'is Surrendered'
[ ] Action text correct
[ ] Placement correct
```

#### Test 5.6: Unknown Type Action
```
Condition: instrument_type = 'Custom Type'
Expected: 'is Custom Type'
[ ] Generic action used
[ ] Type name substituted
[ ] No error
```

---

### Section 6: Financial Fields

#### Test 6.1: All Financial Fields Present
```
Database:
  consideration = '₦500,000.00'
  stamp_duty = '₦5,000.00'
  registration_fee = '₦10,000.00'

Expected Display:
  CONSIDERATION: ₦500,000.00
  STAMP DUTY: ₦5,000.00
  REGISTRATION FEE: ₦10,000.00

[ ] All values display correctly
[ ] Currency symbols preserved
[ ] Proper formatting maintained
[ ] Alignment correct (right-aligned)
```

#### Test 6.2: Missing Consideration
```
Database:
  consideration = NULL
  stamp_duty = '₦5,000.00'
  registration_fee = '₦10,000.00'

Expected Display:
  CONSIDERATION: [empty]
  STAMP DUTY: ₦5,000.00
  REGISTRATION FEE: ₦10,000.00

[ ] Null field is blank
[ ] Other fields display
[ ] No error
```

#### Test 6.3: All Financial Fields NULL
```
Database:
  consideration = NULL
  stamp_duty = NULL
  registration_fee = NULL

Expected Display: All three fields blank
[ ] Fields render cleanly
[ ] No error on page
[ ] Underlines visible for blank fields
```

#### Test 6.4: Financial Fields Fallback
```
Database (registered_instruments):
  consideration = NULL
Database (details array):
  consideration = '₦250,000.00'

Expected Display:
  CONSIDERATION: ₦250,000.00

[ ] Fallback to details array works
[ ] Value displays correctly
```

---

### Section 7: Watermark Functionality

#### Test 7.1: First Print (ORIGINAL)
```
Database: print_count = 0
Controller: $watermark = 'ORIGINAL'

Expected Display:
  [ ] No visible watermark
  [ ] Document renders cleanly
  [ ] Print preview shows no overlay
  [ ] Metadata shows "ORIGINAL"
```

#### Test 7.2: Second Print (COPY)
```
Database: print_count = 1
Controller: $watermark = 'COPY'

Expected Display:
  [ ] "COPY" watermark visible on screen
  [ ] Watermark positioned center, rotated -45°
  [ ] Watermark is semi-transparent
  [ ] Watermark NOT in printed output
  [ ] Metadata shows "COPY" (print count = 1)
```

#### Test 7.3: Nth Print (Multiple COPY)
```
Database: print_count = 5
Controller: $watermark = 'COPY'

Expected Display:
  [ ] Watermark still visible on screen
  [ ] Watermark hidden from print preview
  [ ] Metadata shows "COPY (print count = 5)"
```

#### Test 7.4: Print Count Increments
```
Action: Print document multiple times
Expected:
  [ ] First print: metadata shows 0 (original)
  [ ] Second print: metadata shows 1 (copy)
  [ ] Third print: metadata shows 2 (copy)
  [ ] Print count increments correctly in database
```

---

### Section 8: Metadata Display (Non-Printable Footer)

#### Test 8.1: All Metadata Present
```
Display Elements:
  [ ] RDS Reference: RDS-2025-00123
  [ ] Generated: 13/11/2025 10:30:45
  [ ] Print Count: 0 (ORIGINAL)
  [ ] STM Reference: STM-2025-001

Appearance:
  [ ] Text is small (xs size)
  [ ] Color is gray (text-gray-500)
  [ ] .no-print class applied
```

#### Test 8.2: Metadata Hidden from Print
```
Action: Open print preview (Ctrl+P / Cmd+P)
Expected:
  [ ] Metadata footer NOT visible in print preview
  [ ] Only document content visible
  [ ] Watermark NOT visible in preview
```

#### Test 8.3: Missing Metadata Fields
```
Database: stm_ref = NULL
Expected:
  [ ] RDS Reference displays
  [ ] Generated date displays
  [ ] Print Count displays
  [ ] STM Reference line not shown (or shows empty)
  [ ] No error on page
```

---

### Section 9: Print Output & PDF Export

#### Test 9.1: Chrome/Edge Print Preview
```
Action: Open browser, click "Print Document" button
Expected:
  [ ] Print preview opens
  [ ] Page shows A4 format
  [ ] Content properly formatted for A4
  [ ] Margins correct (0.5 inches)
  [ ] Watermark NOT in preview
  [ ] Metadata footer NOT in preview
  [ ] All document content visible
```

#### Test 9.2: Firefox Print Preview
```
Action: Open Firefox, click "Print Document"
Expected:
  [ ] Same as Chrome (see 9.1)
  [ ] Page orientation: Portrait
  [ ] Content properly sized
```

#### Test 9.3: Safari Print Preview
```
Action: Open Safari, click "Print Document"
Expected:
  [ ] Print preview opens
  [ ] Document content visible
  [ ] Formatting preserved
  [ ] No display issues
```

#### Test 9.4: Save as PDF
```
Action: Print → Save as PDF
Expected File Properties:
  [ ] File size: 50-100 KB
  [ ] Format: PDF
  [ ] Title: [Document Title] - Registered Document Sheet
  [ ] All content preserved in PDF
  [ ] Watermark NOT in PDF (first print)
```

#### Test 9.5: Physical Printing
```
Action: Print to physical printer
Expected Output:
  [ ] Single-sided A4 page
  [ ] All text readable
  [ ] Images/graphics clear
  [ ] Borders and lines visible
  [ ] Quality professional-grade
```

---

### Section 10: Responsive Design & Screen Display

#### Test 10.1: Desktop Display (1920x1080)
```
Action: View document on desktop
Expected:
  [ ] Document centered on page
  [ ] Content properly formatted
  [ ] Print button visible in top-right
  [ ] All fields readable
  [ ] No horizontal scroll needed
```

#### Test 10.2: Tablet Display (768x1024)
```
Action: View on tablet device
Expected:
  [ ] Document readable on smaller screen
  [ ] Print button accessible
  [ ] Content not cut off
  [ ] Font sizes appropriate
```

#### Test 10.3: Mobile Display (375x667)
```
Action: View on mobile device
Expected:
  [ ] Document still displays
  [ ] Content may need scrolling
  [ ] Print button accessible
  [ ] Information not lost
```

#### Test 10.4: Zoom Functionality
```
Action: Browser zoom (100% → 75% → 125% → 150%)
Expected:
  [ ] 75%: Document fits wider
  [ ] 100%: Normal display
  [ ] 125%: Slightly larger
  [ ] 150%: Still readable, some scroll
  [ ] No layout breaks at any zoom level
```

---

### Section 11: Browser Compatibility

#### Test 11.1: Chrome (Latest)
```
[ ] Document renders correctly
[ ] All styles applied
[ ] Print output clean
[ ] No console errors
```

#### Test 11.2: Edge (Latest)
```
[ ] Document renders correctly
[ ] Watermark displays properly
[ ] Print quality excellent
[ ] No issues
```

#### Test 11.3: Firefox (Latest)
```
[ ] Document displays
[ ] All features work
[ ] Print preview functional
[ ] No errors
```

#### Test 11.4: Safari (Latest)
```
[ ] Document renders
[ ] Watermark may appear slightly different
[ ] Print functional
[ ] Overall compatibility OK
```

---

### Section 12: Error Handling & Edge Cases

#### Test 12.1: Missing $rds Variable
```
Expected: Document loads, metadata section not shown (graceful degradation)
[ ] No error thrown
[ ] Document still displays
[ ] Title/content visible
```

#### Test 12.2: Missing $instrument Variable
```
Expected: Document partially loads with defaults
[ ] No fatal error
[ ] Default values used
[ ] No exception on page
```

#### Test 12.3: Corrupted JSON in Party Field
```
Database: Grantor = '[invalid json'
Expected: Falls back to original string
[ ] No JSON parse error
[ ] Original string displayed
[ ] Document still renders
```

#### Test 12.4: Very Long Party Names
```
Database: Grantor = 'Very Long Corporate Name Inc. Holding Company and Associates Limited Partnership'
Expected:
[ ] Text wraps appropriately
[ ] Field expands to fit content
[ ] Layout not broken
[ ] Text remains readable
```

#### Test 12.5: Special Characters in Names
```
Database: Grantor = 'Adé Osunkunlé & Sons Ltd.'
Expected:
[ ] Special characters display correctly
[ ] No encoding issues
[ ] Name readable
```

#### Test 12.6: Unicode Characters
```
Database: Grantor = 'محمد علي' (Arabic name)
Expected:
[ ] Unicode renders correctly
[ ] Text displays properly
[ ] Print output clean
```

---

### Section 13: Database Connection Issues

#### Test 13.1: rds_tracking Not Found
```
Expected: Error message or graceful 404
[ ] No generic error shown
[ ] User-friendly message
[ ] Redirect to appropriate page
```

#### Test 13.2: registered_instruments Not Found
```
Expected: Document renders with limited data
[ ] Page still loads
[ ] Uses fallback data
[ ] No fatal crash
```

#### Test 13.3: Database Timeout
```
Expected: Timeout error with user message
[ ] Graceful error handling
[ ] User informed
[ ] Option to retry
```

---

## Integration Testing Checklist

### Test I1: Full Workflow – ASSIGNMENT Document
```
Steps:
1. Create registered_instruments record with:
   - instrument_type = 'Assignment'
   - Grantor = 'Musa Ali'
   - Grantee = 'Jane Smith'
   - consideration = '₦500,000'
   - stamp_duty = '₦5,000'
   - registration_fee = '₦10,000'
   - instrumentDate = '2025-11-12 10:00:00'
   - StFileNo = 'ST-RES-2025-001'

2. Create rds_tracking record:
   - registration_date = '2025-11-12'
   - print_count = 0

3. Navigate to print page
4. Verify all fields populate correctly
5. Click "Print Document" button
6. Verify print preview shows no metadata/watermark
7. Print to PDF

Expected Results:
[ ] Step 1: Record created successfully
[ ] Step 2: RDS record created successfully
[ ] Step 3: Page loads without error
[ ] Step 4: All fields match database values
[ ] Step 5: Print preview opens
[ ] Step 6: Preview is clean (no debug info)
[ ] Step 7: PDF generated successfully
[ ] PDF contains correct content
[ ] PDF file size reasonable (50-100 KB)
```

### Test I2: Full Workflow – MORTGAGE Document
```
Similar to I1, but with:
   - instrument_type = 'Mortgage'
   - Expected title: 'Mortgage'
   - Expected action: 'is Mortgaged'
   - Party labels: Mortgagor / Mortgagee

Expected Results:
[ ] Document title shows "Mortgage"
[ ] Subtitle shows "THIS IS A DEED OF Mortgage"
[ ] Action text shows "is Mortgaged"
[ ] All other fields populate correctly
[ ] Print output clean
```

### Test I3: Multiple Prints (Watermark Progression)
```
Steps:
1. Load RDS document (print_count = 0)
2. Click "Print Document" - generates print
3. Check database - print_count should be 1
4. Reload page
5. Verify watermark shows "COPY"
6. Click "Print Document" again
7. Check database - print_count should be 2
8. Reload page
9. Verify metadata still shows print count

Expected Results:
[ ] Step 2: Print preview has no watermark
[ ] Step 3: Database shows print_count = 1
[ ] Step 5: Page displays "COPY" watermark
[ ] Step 6: Print preview still clean
[ ] Step 7: Database shows print_count = 2
[ ] Step 9: Metadata accurate at each step
```

---

## Performance Testing Checklist

#### Test P1: Page Load Time
```
Measurement: Time from request to full render
Expected: < 2 seconds
[ ] Page loads quickly
[ ] No noticeable delay
[ ] Database queries fast
```

#### Test P2: Print Generation Time
```
Measurement: Time from clicking "Print" to preview appearing
Expected: < 1 second
[ ] Browser print dialog opens quickly
[ ] No lag
[ ] Smooth experience
```

#### Test P3: Memory Usage
```
Measurement: Browser memory with page loaded
Expected: < 50 MB
[ ] No memory leaks
[ ] Page remains responsive
[ ] No slowdown over time
```

---

## Security Testing Checklist

#### Test S1: XSS Prevention
```
Test: Inject <script>alert('XSS')</script> in party name
Expected: Script does not execute
[ ] Data displayed as text
[ ] HTML encoded
[ ] No alert appears
```

#### Test S2: SQL Injection
```
Test: Try SQL injection in ID parameter
Expected: No database compromise
[ ] Query properly parameterized
[ ] No error output
[ ] Access denied or safe error
```

#### Test S3: Authorization
```
Test: Try accessing RDS without proper permissions
Expected: Denied access
[ ] 403 Forbidden returned
[ ] Redirect to login if not authenticated
[ ] No data exposure
```

---

## Final Verification

### Sign-Off Checklist
- [ ] All unit tests passed
- [ ] All integration tests passed
- [ ] Performance meets requirements
- [ ] Security tests passed
- [ ] Cross-browser testing complete
- [ ] Edge cases handled
- [ ] Documentation complete
- [ ] Code reviewed and approved
- [ ] Ready for production deployment

### Known Issues / Limitations
```
[List any known issues found during testing]
```

### Recommendations for Future
```
[Any improvements noted during testing]
```

---

## Test Results Summary

| Test Area | Total Tests | Passed | Failed | Status |
|---|---|---|---|---|
| Document Title/Subtitle | 6 | - | - | ⏳ Pending |
| Date Formatting | 5 | - | - | ⏳ Pending |
| Party Names | 5 | - | - | ⏳ Pending |
| File Numbers | 4 | - | - | ⏳ Pending |
| Action Text | 6 | - | - | ⏳ Pending |
| Financial Fields | 4 | - | - | ⏳ Pending |
| Watermarking | 4 | - | - | ⏳ Pending |
| Metadata Display | 3 | - | - | ⏳ Pending |
| Print Output | 5 | - | - | ⏳ Pending |
| Responsive Design | 4 | - | - | ⏳ Pending |
| Browser Compatibility | 4 | - | - | ⏳ Pending |
| Error Handling | 6 | - | - | ⏳ Pending |
| Integration | 3 | - | - | ⏳ Pending |
| Performance | 3 | - | - | ⏳ Pending |
| Security | 3 | - | - | ⏳ Pending |
| **TOTAL** | **74** | **-** | **-** | **⏳ Pending** |

---

**Test Plan Created:** November 13, 2025  
**Test Status:** Ready for Execution  
**Approver:** [To be completed]  
**Execution Date:** [To be filled]  
**Completion Date:** [To be filled]
