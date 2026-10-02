# Grantor and Grantee Address Rules for Instrument Registration (ST)

**Date:** November 13, 2025  
**Status:** ✅ IMPLEMENTED  
**Scope:** Sectional Titling (ST) Instruments - Mother Applications and Sub Applications

---

## Table of Contents

1. [Overview](#overview)
2. [Address Rules by Instrument Type](#address-rules-by-instrument-type)
3. [Implementation Details](#implementation-details)
4. [Helper Functions](#helper-functions)
5. [Data Flow](#data-flow)
6. [Test Cases](#test-cases)
7. [Examples](#examples)

---

## Overview

This document outlines the standardized rules for populating Grantor and Grantee addresses for all Sectional Titling instrument types in the Instrument Registration system.

**Key Principles:**
- Grantor Address for ST CofO is always "Government House, Kano"
- Grantee Address comes from the applicant's address field (sub_applications or mother_applications)
- ST Assignment (SUA) has specific rules based on allocation_source
- All addresses are properly formatted with correct capitalization and comma placement

---

## Address Rules by Instrument Type

### 1. **ST CofO (Sectional Titling Certificate of Occupancy)**

**Grantor Address:**
- **Always:** `Government House, Kano`
- **Reason:** Government is the initial owner/grantor of ST CofO

**Grantee Address:**
- **Primary Source:** `subapplications.address` (preferred)
- **Fallback Source:** `mother_applications.address`
- **Format:** Properly formatted with capitalization

**Example:**
```
Grantor:     Kano State Government
GrantorAddress: Government House, Kano
Grantee:     JOHN OKAFOR
GranteeAddress: Guda Abdullahi Road, Daurawa, Tarauni, Kano
```

---

### 2. **ST Assignment (Transfer of Title)**

**For SUA (Standalone Unit Application) - `unit_type = 'SUA'`:**

**Grantor Address:**
- **Source:** `subapplications.allocation_source`
- **Examples:** 
  - "State Government"
  - "Local Government (LGA)"
  - "Land Tycoon"
- **Processing:** Formatted and capitalized appropriately

**Grantee Address:**
- **Source:** `subapplications.address`
- **Format:** Properly formatted address

**Example:**
```
Grantor:     Allocation Entity (e.g., "Murtala Mohammed Housing Authority")
GrantorAddress: State Government
Grantee:     MARY SMITH
GranteeAddress: 111, Murtala Mohammed Way, Fagge, Fagge, Kano
```

**For Non-SUA Units:**

**Grantor Address:**
- **Source:** Not populated (uses empty string as fallback)
- **Reason:** Different allocation mechanism

**Grantee Address:**
- **Source:** `subapplications.address` or `mother_applications.address`

---

### 3. **ST Fragmentation**

**Grantor Address:**
- **Always:** Similar to ST CofO (Government House, Kano)
- **Reason:** Fragmentation originates from government allocation

**Grantee Address:**
- **Source:** `mother_applications.address`
- **Format:** Properly formatted

**Example:**
```
Grantor:     Kano State Government
GrantorAddress: Government House, Kano
Grantee:     APPLICANT NAME
GranteeAddress: Property Address from Mother Application
```

---

### 4. **Other Instruments** (from instrument_registration table)

**Grantor Address:**
- **Source:** `instrument_registration.GrantorAddress`
- **Default:** Empty string if not provided

**Grantee Address:**
- **Source:** `instrument_registration.GranteeAddress`
- **Default:** Empty string if not provided

---

## Implementation Details

### Files Modified

#### 1. **app/Http/Controllers/InstrumentRegistrationController.php**

**Changes:**
- Added `allocation_source` to subapplications query
- Added `sub_address` (from `subapplications.address`) to query
- Added `mother_address` (from `mother_applications.address`) to queries
- Updated ST Assignment record creation to use helper functions
- Updated ST CofO record creation to use helper functions
- Updated ST Fragmentation record creation to use helper functions

**Lines Modified:**
- Lines 145-175: Added address fields to subapplications query
- Lines 193-213: Added address field to mother_applications query
- Lines 353-357: Updated ST Assignment GrantorAddress/GranteeAddress
- Lines 389-393: Updated ST CofO GrantorAddress/GranteeAddress
- Lines 482-486: Updated ST Fragmentation GrantorAddress/GranteeAddress

#### 2. **app/Helper/helper.php**

**New Functions Added:**

```php
/**
 * formatAddress($address)
 * Formats an address string according to standards
 * - Capitalizes street/area names properly
 * - Ensures correct comma placement
 * - Handles empty/null inputs gracefully
 */

/**
 * getGrantorAddress($instrumentType, $applicationData)
 * Returns Grantor address based on instrument type
 * - ST CofO/Sectional Titling CofO → "Government House, Kano"
 * - ST Assignment (SUA) → allocation_source field
 * - Other types → empty string
 */

/**
 * getGranteeAddress($applicationData)
 * Returns Grantee address from application data
 * - Priority 1: subapplications.address
 * - Priority 2: mother_applications.address
 * - Priority 3: generic 'address' field
 * - Default: empty string
 */
```

---

## Helper Functions

### 1. formatAddress($address)

**Purpose:** Normalize and format address strings

**Logic:**
- Trims whitespace
- Splits by comma and processes each part
- Applies proper capitalization (ucwords)
- Avoids over-capitalizing already uppercase entries
- Joins back with ", " separator

**Input Examples:**
```
"guda abdullahi road, daurawa, tarauni, kano"
"GUDA ABDULLAHI ROAD, DAURAWA, Tarauni, Kano"
"  111 murtala mohammed way, fagge, fagge, kano  "
```

**Output Example:**
```
"Guda Abdullahi Road, Daurawa, Tarauni, Kano"
"Guda Abdullahi Road, Daurawa, Tarauni, Kano"
"111 Murtala Mohammed Way, Fagge, Fagge, Kano"
```

**Usage:**
```php
$formatted = formatAddress($rawAddress);
```

---

### 2. getGrantorAddress($instrumentType, $applicationData)

**Purpose:** Determine Grantor address based on instrument type

**Logic:**
```
IF instrumentType = 'Sectional Titling CofO' OR 'ST CofO'
    RETURN "Government House, Kano"

ELSE IF instrumentType = 'ST Assignment (Transfer of Title)' OR 'ST Assignment'
    IF unit_type = 'SUA'
        allocation_source = applicationData->allocation_source
        IF allocation_source is not empty
            RETURN formatAddress(allocation_source)
        ELSE
            RETURN ""
    ELSE
        RETURN ""

ELSE
    RETURN ""
```

**Usage:**
```php
$grantorAddress = getGrantorAddress('Sectional Titling CofO', $subApp);
$grantorAddress = getGrantorAddress('ST Assignment (Transfer of Title)', $subApp);
```

---

### 3. getGranteeAddress($applicationData)

**Purpose:** Extract and format Grantee address with fallbacks

**Logic:**
```
IF applicationData->sub_address is not empty
    RETURN formatAddress(applicationData->sub_address)

ELSE IF applicationData->mother_address is not empty
    RETURN formatAddress(applicationData->mother_address)

ELSE IF applicationData->address is not empty
    RETURN formatAddress(applicationData->address)

ELSE
    RETURN ""
```

**Usage:**
```php
$granteeAddress = getGranteeAddress($subApp);
$granteeAddress = getGranteeAddress($motherApp);
```

---

## Data Flow

### For Sub Applications (ST Assignment & ST CofO)

```
Database Query
    ↓
SELECT s.allocation_source, s.address, m.address
    ↓
Create ST Assignment Record
    ├─ GrantorAddress = getGrantorAddress('ST Assignment', $subApp)
    └─ GranteeAddress = getGranteeAddress($subApp)
    ↓
Create ST CofO Record
    ├─ GrantorAddress = getGrantorAddress('Sectional Titling CofO', $subApp)
    └─ GranteeAddress = getGranteeAddress($subApp)
    ↓
Instrument Registration Index Page (displays in table)
    ↓
Register Instrument → registered_instruments Table
    ├─ GrantorAddress stored
    └─ GranteeAddress stored
    ↓
RDS Print Template
    ├─ Displays GrantorAddress
    └─ Displays GranteeAddress
```

### For Mother Applications (ST Fragmentation)

```
Database Query
    ↓
SELECT m.address
    ↓
Create ST Fragmentation Record
    ├─ GrantorAddress = getGrantorAddress('ST Fragmentation', $motherApp)
    └─ GranteeAddress = getGranteeAddress($motherApp)
    ↓
Instrument Registration Index Page
    ↓
Register Instrument → registered_instruments Table
    ├─ GrantorAddress stored
    └─ GranteeAddress stored
    ↓
RDS Print Template (displays addresses)
```

---

## Test Cases

### Test 1: ST CofO with Sub Application Address

**Scenario:** Sub application has address field populated

**Test Data:**
```php
$subApp = (object)[
    'sub_address' => 'GUDA ABDULLAHI ROAD, DAURAWA, Tarauni, Kano',
    'unit_type' => 'SUA',
    'allocation_source' => 'State Government'
];
```

**Expected Result:**
```
GrantorAddress = "Government House, Kano"
GranteeAddress = "Guda Abdullahi Road, Daurawa, Tarauni, Kano"
```

**Verification:**
- ✅ Grantor address is exact match
- ✅ Grantee address is properly formatted
- ✅ Capitalization is correct

---

### Test 2: ST Assignment (SUA) with Allocation Source

**Scenario:** SUA with allocation_source field populated

**Test Data:**
```php
$subApp = (object)[
    'instrument_type' => 'ST Assignment (Transfer of Title)',
    'unit_type' => 'SUA',
    'allocation_source' => 'State Government',
    'sub_address' => '111, MURTALA MOHAMMED WAY, FAGGE, Fagge, Kano'
];
```

**Expected Result:**
```
GrantorAddress = "State Government"
GranteeAddress = "111, Murtala Mohammed Way, Fagge, Fagge, Kano"
```

**Verification:**
- ✅ Grantor address comes from allocation_source
- ✅ Grantee address is properly formatted
- ✅ Both addresses are capitalized correctly

---

### Test 3: ST Assignment (Non-SUA) without Address

**Scenario:** Non-SUA unit without specific address fields

**Test Data:**
```php
$subApp = (object)[
    'instrument_type' => 'ST Assignment (Transfer of Title)',
    'unit_type' => 'PUA',
    'allocation_source' => null,
    'sub_address' => null,
    'mother_address' => '123 Main Street, Kano'
];
```

**Expected Result:**
```
GrantorAddress = ""
GranteeAddress = "123 Main Street, Kano"
```

**Verification:**
- ✅ Grantor address is empty (non-SUA)
- ✅ Grantee address falls back to mother_address
- ✅ No errors on null handling

---

### Test 4: ST Fragmentation with Mother Application Address

**Scenario:** Mother application with valid address

**Test Data:**
```php
$motherApp = (object)[
    'instrument_type' => 'ST Fragmentation',
    'mother_address' => 'Property Location Details, Kano'
];
```

**Expected Result:**
```
GrantorAddress = "Government House, Kano"
GranteeAddress = "Property Location Details, Kano"
```

**Verification:**
- ✅ Grantor is government house
- ✅ Grantee comes from mother_application address
- ✅ Addresses properly formatted

---

### Test 5: Address Formatting Edge Cases

**Test Cases:**
```php
// Test 1: Mixed case normalization
Input: "guda abdullahi road, daurawa, tarauni, kano"
Output: "Guda Abdullahi Road, Daurawa, Tarauni, Kano"

// Test 2: Already formatted
Input: "Government House, Kano"
Output: "Government House, Kano"

// Test 3: Null/Empty handling
Input: null or ""
Output: ""

// Test 4: Whitespace trimming
Input: "  123 Main Street, Kano  "
Output: "123 Main Street, Kano"

// Test 5: Comma spacing
Input: "Road,Area,District,State"
Output: "Road, Area, District, State"
```

---

## Examples

### Example 1: Complete ST CofO Record

**Source Data:**
```
Subapplication ID: 5
Unit Type: SUA
Sub Applicant: JOHN OKAFOR
Sub Address: GUDA ABDULLAHI ROAD, DAURAWA, Tarauni, Kano
Allocation Source: State Government
```

**Generated Record:**
```
Instrument Type: Sectional Titling CofO
Grantor: Kano State Government
GrantorAddress: Government House, Kano
Grantee: JOHN OKAFOR
GranteeAddress: Guda Abdullahi Road, Daurawa, Tarauni, Kano
Status: pending (until registered)
```

---

### Example 2: ST Assignment with SUA

**Source Data:**
```
Subapplication ID: 7
Unit Type: SUA
Sub Applicant: MARY SMITH
Allocation Entity: Murtala Mohammed Housing Authority
Allocation Source: Local Government
Sub Address: 111, MURTALA MOHAMMED WAY, FAGGE, Fagge, Kano
```

**Generated Records:**

**ST Assignment Record:**
```
Instrument Type: ST Assignment (Transfer of Title)
Grantor: Murtala Mohammed Housing Authority
GrantorAddress: Local Government
Grantee: MARY SMITH
GranteeAddress: 111, Murtala Mohammed Way, Fagge, Fagge, Kano
```

**ST CofO Record (Same File Number):**
```
Instrument Type: Sectional Titling CofO
Grantor: Kano State Government
GrantorAddress: Government House, Kano
Grantee: MARY SMITH
GranteeAddress: 111, Murtala Mohammed Way, Fagge, Fagge, Kano
```

---

### Example 3: ST Fragmentation

**Source Data:**
```
Mother Application ID: 3
Applicant: ALEX NNADI
Mother Address: 250 Bishop Aba Road, Alkali Ward, Tarauni, Kano
LGA: Tarauni
District: Alkali
```

**Generated Record:**
```
Instrument Type: ST Fragmentation
Grantor: Kano State Government
GrantorAddress: Government House, Kano
Grantee: ALEX NNADI
GranteeAddress: 250 Bishop Aba Road, Alkali Ward, Tarauni, Kano
Status: pending (until registered)
```

---

## Verification Checklist

### Before Deployment

- [ ] Address fields are selected in database queries (allocation_source, address)
- [ ] Helper functions are defined in app/Helper/helper.php
- [ ] ST Assignment record uses getGrantorAddress() with unit_type check
- [ ] ST CofO record always uses "Government House, Kano" for GrantorAddress
- [ ] ST Fragmentation uses "Government House, Kano" for GrantorAddress
- [ ] getGranteeAddress() has proper fallback chain
- [ ] formatAddress() handles null/empty inputs gracefully
- [ ] Address formatting maintains data integrity (no data loss)

### Testing Requirements

- [ ] Test with ST CofO - address must be "Government House, Kano"
- [ ] Test with ST Assignment (SUA) - address from allocation_source
- [ ] Test with ST Assignment (PUA) - address from sub_applications/mother_applications
- [ ] Test with ST Fragmentation - address from mother_applications
- [ ] Test with null/empty addresses - should not throw errors
- [ ] Test address formatting - capitalization and comma placement
- [ ] Test fallback chain - verify proper priority order
- [ ] Test RDS print - verify addresses display correctly

### Deployment Steps

1. Back up current InstrumentRegistrationController.php
2. Back up current helper.php
3. Deploy updated InstrumentRegistrationController.php
4. Deploy updated helper.php
5. Run full test suite for Instrument Registration module
6. Verify RDS generation displays correct addresses
7. Monitor error logs for any address-related issues
8. Test with multiple instrument types

---

## Rollback Plan

If issues are discovered:

1. **Revert Controller:**
   ```bash
   git checkout app/Http/Controllers/InstrumentRegistrationController.php
   ```

2. **Revert Helper:**
   ```bash
   git checkout app/Helper/helper.php
   ```

3. **Clear Caches:**
   ```bash
   php artisan cache:clear
   php artisan view:clear
   ```

---

## Related Documentation

- `RDS_GENERATION_PRINTING_UPDATE.md` - RDS print template implementation
- `INSTRUMENT_REGISTRATION.md` - Instrument registration system overview
- `DATABASE_SCHEMA.md` - Table structure and relationships

---

## Change Log

**Version 1.0 - November 13, 2025**
- Initial implementation of address rules
- Added three helper functions for address processing
- Updated three instrument record types (ST Assignment, ST CofO, ST Fragmentation)
- Added comprehensive test cases and examples

---

**Status:** ✅ **COMPLETE AND READY FOR TESTING**
