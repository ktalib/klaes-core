COM# File Number System Design & Normalization Plan

**Date:** December 6, 2025  
**Status:** Design Phase  
**Priority:** High

---

## Executive Summary

The KLAES GIS EDMS system manages multiple file number formats across legacy and modern systems. This document provides a comprehensive design for:

1. **File Number Format Standardization** - Consolidate all formats into clear, valid patterns
2. **Normalization Rules** - Correct common OCR/input errors  
3. **Validation Framework** - Ensure data quality at point of entry
4. **Data Migration Strategy** - Gradually migrate legacy records
5. **API & Service Layer** - Unified file number handling across the system

---

## Part 1: Current File Number Ecosystem

### 1.1 File Number Systems in Use

#### **New ST Format (Sectional Titling)**
Modern, standardized format introduced in 2025.

| Type | Format | Pattern | Example |
|------|--------|---------|---------|
| **Primary** | `ST-{LAND_USE}-{YEAR}-{SERIAL}` | `ST-RES-2025-1` | ST-RES-2025-1 |
| **SUA (Standalone Unit App)** | `ST-{LAND_USE}-{YEAR}-{SERIAL}-001` | `ST-COM-2025-5-001` | ST-COM-2025-5-001 |
| **PUA (Parented Unit App)** | `ST-{LAND_USE}-{YEAR}-{SERIAL}-{UNIT_SEQ}` | `ST-RES-2025-3-002` | ST-RES-2025-3-002 |

**Land Use Codes:**
- `RES` = Residential
- `COM` = Commercial
- `IND` = Industrial
- `MIXED` = Mixed Use

#### **MLS Format (Modern Legacy)**
Used in conversion properties and general land acquisitions.

| Type | Format | Pattern | Example |
|------|--------|---------|---------|
| **Standard** | `{PREFIX}-{YEAR}-{SERIAL}` | `RES-2024-5` | RES-2024-5 |
| **Conversion** | `CON-{PREFIX}-{YEAR}-{SERIAL}` | `CON-RES-1982-1263` | CON-RES-1982-1263 |
| **RC (Registered Cases)** | `{PREFIX}-RC-{YEAR}-{SERIAL}` | `RES-RC-2002-5` | RES-RC-2002-5 |
| **Conversion RC** | `CON-{PREFIX}-RC-{YEAR}-{SERIAL}` | `CON-AG-RC-1992-12` | CON-AG-RC-1992-12 |
| **Without Year** | `{PREFIX}-{SERIAL}` | `RES-1992-1176` | RES-1992-1176 |
| **3-Part Conversion** | `CON-{PREFIX}-{CATEGORY}-{YEAR}-{SERIAL}` | `CON-AG-RC-2025-10` | CON-AG-RC-2025-10 |
| **Agriculture Standard** | `AG-{YEAR}-{SERIAL}` | `AG-2025-11` | AG-2025-11 |

**Prefixes:**
- `RES` = Residential
- `COM` = Commercial
- `IND` = Industrial
- `AG` = Agricultural
- `CON-RES` = Conversion to Residential
- `CON-COM` = Conversion to Commercial
- `CON-IND` = Conversion to Industrial
- `CON-AG` = Conversion to Agricultural
- `{PREFIX}-RC` = Registered Cases variant

#### **KANGIS Format (Legacy)**
Very old format, rarely used in modern workflows.

| Type | Format | Pattern | Example |
|------|--------|---------|---------|
| **Old KANGIS** | `{4-LETTER_CODE}{5-DIGIT_SERIAL}` | `KNML 00001` | KNML 00001 |
| **New KANGIS** | `KN{4-DIGIT_SERIAL}` | `KN1586` | KN1586 |

---

## Part 2: Common Data Quality Issues

### 2.1 Common OCR & Input Errors

#### **Problem: Zero vs Slashed Zero**
- **Wrong:** `C$\emptyset$N` (Slashed zero as middle character)
- **Correct:** `CON` (Letter O)
- **Impact:** Prevents pattern matching and database queries
- **Fix:** Replace `Ø`, `∅`, `⊘` with `O`

#### **Problem: Year Abbreviation Confusion**
- **Wrong:** `RES-'92-1234` or `RES-92-1234` (abbreviated year)
- **Wrong:** `RES-'08-5678` (two-digit year prefix with quote)
- **Correct:** `RES-1992-1234`, `RES-2008-5678`
- **Impact:** Loses century information, ambiguous
- **Fix:** Detect 2-digit years, convert to 4-digit using context
  - `'00-'99` → `2000-2099` (default assumption)
  - `'92, '95, '99` → `1992, 1995, 1999` (if before current year by >50 years)

#### **Problem: Letter O Confusion in Serial Numbers**
- **Wrong:** `RES-1992-12O5` (Letter O instead of zero)
- **Correct:** `RES-1992-1205`
- **Impact:** Invalid serial, doesn't match records
- **Fix:** In serial number positions, replace letter `O` with `0`

#### **Problem: 18XX Year Corrections**
- **Wrong:** `RES-1883-1176` (1883, impossible for recent land issues)
- **Correct:** `RES-1983-1176`
- **Pattern:** Year starts with "18" → flip to "19"
- **Fix:** If year starts with `18`, auto-correct to `19`

#### **Problem: Letter L vs Number 1**
- **Wrong:** `RES-1992-I176` (Letter I instead of number 1)
- **Correct:** `RES-1992-1176`
- **Fix:** In serial positions, replace `I`, `l` with `1`

#### **Problem: CN vs CON**
- **Wrong:** `CN-RES-1992-345` (CN prefix)
- **Correct:** `CON-RES-1992-345`
- **Impact:** Doesn't match conversion pattern
- **Fix:** Replace `CN-` with `CON-`

#### **Problem: Character Replacement Issues**
- **Wrong:** `/` in file number (slash as separator)
- **Wrong:** `=` as underscore replacement
- **Correct:** Use `-` (hyphen) consistently
- **Fix:** 
  - Replace `/` with `-`
  - Replace `=` with `-`
  - Replace `_` with `-`

#### **Problem: Duplicate Prefixes**
- **Wrong:** `RES-2024-0001RES-1992-1174` (two file numbers concatenated)
- **Correct:** `RES-2024-0001` or `RES-1992-1174` (separate entries)
- **Impact:** Invalid format, doesn't parse
- **Fix:** Detect and split into separate records

#### **Problem: LKN- Prefix**
- **Wrong:** `LKN-XXXX` (old/invalid prefix)
- **Action:** Research or mark as ambiguous for manual review
- **Fix:** Log for manual correction

#### **Problem: Prefix Character Confusion (C0M, R3S, etc.)**
- **Wrong:** `C0M-RES-1992-345` (Zero in place of letter O in prefix)
- **Wrong:** `C0M` (Zero instead of O), `R3S` (3 instead of E), `1ND` (1 instead of I)
- **Correct:** `COM-RES-1992-345` (Letter O, not zero)
- **Impact:** Doesn't match prefix validation, breaks lookups
- **Fix:** Replace numeric characters with likely letters in prefix position:
  - `0` → `O` (in COM, CON, etc.)
  - `3` → `E` (in RES, etc.)
  - `1` → `I` (in IND, etc.)
  - `5` → `S` (in RES, etc.)
- **Pattern Detection:** If character appears in prefix before hyphen and doesn't match known prefixes, attempt letter substitution

#### **Problem: Spaces Between Characters**
- **Wrong:** `C O M - R E S - 1 9 9 2 - 1 2 3 4` (spaces between letters/numbers)
- **Wrong:** `COM - RES - 1992 - 1234` (inconsistent spacing around hyphens)
- **Correct:** `COM-RES-1992-1234`
- **Impact:** Fails pattern matching, appears invalid
- **Fix:** 
  - Remove all spaces: `$fileNumber = str_replace(' ', '', $fileNumber);`
  - Then normalize: convert to uppercase and apply standard hyphens
  - Apply after initial cleanup but before other corrections

#### **Problem: Multiple Consecutive Hyphens**
- **Wrong:** `RES--1992--1234` (double hyphens)
- **Wrong:** `RES---1992---1234` (triple hyphens)
- **Correct:** `RES-1992-1234`
- **Impact:** Doesn't match patterns, appears malformed
- **Fix:** Replace multiple hyphens with single: `preg_replace('/-{2,}/', '-', $fileNumber);`

#### **Problem: Leading/Trailing Hyphens or Spaces**
- **Wrong:** `-RES-1992-1234` (leading hyphen)
- **Wrong:** `RES-1992-1234-` (trailing hyphen)
- **Wrong:** ` RES-1992-1234 ` (leading/trailing spaces)
- **Correct:** `RES-1992-1234`
- **Fix:** Applied in initial cleanup with `trim()`

### 2.2 Valid Examples from Data

**Correct MLS Formats:**
```
RES-1992-1176          # Oldest style, no year
RES-1992-1178          # Year-based, standard format
RES-1992-1179
COM-1989-55            # Commercial residential
COM-1989-58
COM-1989-68
CON-AG-1982-151        # Conversion to Agricultural
CON-AG-1982-192
CON-COM-2015-123       # Conversion to Commercial (new year)
CON-COM-2015-113
CON-RES-1982-1263      # Conversion to Residential
CON-RES-1982-1266
CON-RES-1982-1279
CON-RES-1991-672
CON-RES-2009-461
AG-RC-2002-5           # Agricultural Registered Case
AG-2025-11             # Agriculture simple
CON-AG-RC-1992-12      # Conversion to Ag with RC
CON-AG-RC-2025-10
IND-RC-1992-121        # Industrial Registered Case
CON-IND-RC-2025-912    # Conversion to Ind with RC
CON-IND-RC-2025-911
IND-2025-911           # Industrial simple
RES-1991-2599
RES-1991-2604
```

---

## Part 3: Normalization Framework

### 3.1 Normalization Pipeline

```
Raw Input
    ↓
[Step 1: Trim & Uppercase & Remove Spaces]
    - Remove leading/trailing whitespace
    - Convert to uppercase
    - Remove ALL spaces (including between characters)
    - Normalize multiple spaces to single
    ↓
[Step 2: Character Corrections]
    - Replace Ø/∅/⊘ with O
    - Replace / with -
    - Replace = with -
    - Replace _ with -
    - Replace multiple hyphens with single hyphen
    - Attempt character substitution in prefixes (0→O, 3→E, 1→I, 5→S)
    ↓
[Step 3: Split Detection]
    - Detect concatenated file numbers
    - Split if needed
    ↓
[Step 4: Prefix Normalization]
    - Detect CN → CON-
    - Validate/correct prefixes with character confusion
    ↓
[Step 5: Year Normalization]
    - Detect 2-digit years, expand to 4-digit
    - Correct 18XX to 19XX
    ↓
[Step 6: Serial Number Cleaning]
    - Replace O with 0 in serial position
    - Replace I/l with 1 in serial position
    - Replace other letters with likely numbers (3→8, 5→5, etc.)
    - Remove leading zeros (normalize to integer, then back)
    ↓
[Step 7: Pattern Matching & Classification]
    - Identify file number type
    - Validate against expected pattern
    ↓
[Step 8: Validation & Error Reporting]
    - Report if still invalid
    - Suggest corrections
```

### 3.2 Detailed Normalization Rules

The normalization pipeline follows these 6 steps:

1. **Initial Cleanup** - Trim, uppercase, remove all spaces
2. **Character Corrections** - Replace special characters, fix slashed zeros, normalize hyphens
3. **Concatenation Detection** - Identify and split concatenated file numbers
4. **Prefix Normalization** - Fix CN→CON, correct character confusion (C0M→COM, R3S→RES, etc.)
5. **Year Normalization** - Expand 2-digit years, correct 18XX→19XX
6. **Serial Number Cleaning** - Replace O→0, I→1 in serial positions

### 3.3 Classification & Pattern Matching

After normalization, identify the file number type by matching against these patterns:

**ST Format Patterns:**
- Primary: `ST-{LAND_USE}-{YEAR}-{SERIAL}`
- Unit: `ST-{LAND_USE}-{YEAR}-{SERIAL}-{UNIT_SEQ}`

**MLS Format Patterns:**
- Standard: `{PREFIX}-{YEAR}-{SERIAL}`
- Conversion: `CON-{PREFIX}-{YEAR}-{SERIAL}`
- RC: `{PREFIX}-RC-{YEAR}-{SERIAL}`
- Conversion RC: `CON-{PREFIX}-RC-{YEAR}-{SERIAL}`
- Legacy: `{PREFIX}-{SERIAL}` (without year)

**KANGIS Format Patterns:**
- Old: `{4-LETTER}{5-DIGIT}`
- New: `KN{4-DIGIT}`

**Reference:** For detailed implementation code, see the Folder Watcher Python implementation in `FOLDER_WATCHER_SYSTEM_DESIGN.md` Part 4.2 (FileNumberValidator class).

---

## Part 4: Database Schema for Validation

### 4.1 Validation Tracking

A validation tracking table should store:
- Original input and normalized value
- File number type classification
- Validation status and confidence score
- Parsed components (prefix, year, serial, unit sequence)
- Corrections applied for audit trail
- Error messages and suggestions
- Metadata links to property_cards and mother_applications

### 4.2 Error Catalog

Maintain a comprehensive error catalog documenting:
- Error code (ERR001-ERR017)
- Error description and common cause
- Detection pattern and correction rule
- Examples of wrong vs correct formats
- Severity level and auto-fixability
- Reference to the 17 documented error types in Part 2

---

## Part 5: Validation Service Implementation

### 5.1 Service Architecture

A FileNumberValidator service should implement:

**Core Methods:**
- `validateAndNormalize()` - Main validation pipeline (8 steps)
- `classifyFileNumber()` - Type classification
- `correctCharacters()` - Character replacements
- `normalizePrefixes()` - Prefix fixes
- `normalizeYear()` - Year expansion/correction
- `cleanSerialNumber()` - Serial number fixes

**Error Handling:**
- Detailed error reporting with error codes
- Actionable suggestions for corrections
- Tracking of corrections applied

### 5.2 Error Reporting

The service should report:
- Detected errors with error codes (ERR001-ERR017)
- Severity levels (critical, warning, info)
- Clear descriptions of what went wrong
- Suggested corrections
- Auto-fixability status

---

## Part 6: Data Migration Strategy

### 6.1 Three-Phase Approach

#### **Phase 1: Audit & Assessment (Week 1)**
1. Scan all `property_cards`, `mother_applications`, `file_indexings` for file numbers
2. Classify by format type (ST, MLS, KANGIS)
3. Identify which records are valid vs need correction
4. Build error catalog with real examples from database
5. Estimate impact scope

#### **Phase 2: Validation & Correction (Week 2-3)**
1. For valid records → mark as `validated`
2. For correctable records → auto-normalize and flag for review
3. For ambiguous records → create review queue with suggestions
4. Manual correction by data team for complex cases
5. Build audit trail of all changes

#### **Phase 3: Integration & Enforcement (Week 4)**
1. Update all data entry forms with validation
2. Integrate normalization into API layer
3. Add validation middleware to controllers
4. Update bulk import processes
5. Enable strict validation on new entries

### 6.2 Migration Process

The migration strategy should:
- Backup original values before any changes
- Create audit trail of all modifications
- Flag records requiring manual review
- Generate reports by normalization status
- Support rollback if needed

---

## Part 7: API Endpoints for Validation

### 7.1 Validation Endpoints

Implement the following API endpoints:
- `POST /api/file-numbers/validate` - Validate single file number
- `POST /api/file-numbers/normalize` - Get normalized version
- `POST /api/file-numbers/batch-validate` - Validate multiple file numbers
- `GET /api/file-numbers/classify/{fileNumber}` - Classify file number type
- `POST /api/file-numbers/suggestions` - Get correction suggestions

### 7.2 Validation Response Format

The API should return consistent responses with:
- Original input and normalized value
- Validation status (valid/invalid)
- File number type classification
- Parsed components (prefix, year, serial, unit)
- Corrections applied
- Error messages and suggestions
- Warnings (e.g., unusual years)

---

## Part 8: UI/UX Improvements

### 8.1 Form-Level Validation

Implement real-time validation as users type:
- Show validation status (checkmark/error icon)
- Display normalized value when valid
- Show error messages immediately
- Offer clickable suggestions for corrections

### 8.2 Error Message Design

Use clear, actionable error messages:
- State what went wrong in plain language
- Show the user's input vs expected format
- Provide one-click corrections where possible
- Example: "CN prefix detected - Did you mean: CON-RES-1992-345? [Apply]"

---

## Part 9: Implementation Roadmap

### **Week 1: Foundation**
- [ ] Create `FileNumberValidator` service
- [ ] Create `file_number_normalizations` table
- [ ] Build error catalog with database examples
- [ ] Write comprehensive unit tests
- [ ] Document all patterns & rules

### **Week 2: Integration**
- [ ] Create validation API endpoints
- [ ] Add middleware to form submissions
- [ ] Update commission interface with real-time validation
- [ ] Build batch validation tool
- [ ] Create validation dashboard

### **Week 3: Migration**
- [ ] Audit all existing file numbers
- [ ] Run normalization on all records
- [ ] Manual review of ambiguous cases
- [ ] Backup original values
- [ ] Update records in production

### **Week 4: Enforcement**
- [ ] Enable strict validation on new entries
- [ ] Add validation to bulk import
- [ ] Train users on new system
- [ ] Monitor error reports
- [ ] Fine-tune patterns based on real data

---

## Part 10: Error Reference Manual

### Common Errors Quick-Reference

| Error Code | Issue | Wrong | Right | Auto-Fix? |
|-----------|-------|-------|-------|-----------|
| ERR001 | Slashed zero | `C$\emptyset$N-RES` | `CON-RES` | ✅ Yes |
| ERR002 | CN prefix | `CN-RES-92-1` | `CON-RES-1992-1` | ✅ Yes |
| ERR003 | 2-digit year | `RES-92-1234` | `RES-1992-1234` | ⚠️ Context |
| ERR004 | 18XX error | `RES-1883-123` | `RES-1983-123` | ✅ Yes |
| ERR005 | Letter O in serial | `RES-1992-12O5` | `RES-1992-1205` | ✅ Yes |
| ERR006 | Letter I in serial | `RES-1992-I175` | `RES-1992-1175` | ✅ Yes |
| ERR007 | Concatenation | `RES-2024-1RES-92-2` | Split into two | ✅ Yes |
| ERR008 | Character swap | `RES/1992-123` | `RES-1992-123` | ✅ Yes |
| ERR009 | Missing digit | `RES-1992-` | `RES-1992-1234` | ❌ No |
| ERR010 | LKN prefix | `LKN-1234` | `KN-1234` | ⚠️ Unknown |
| ERR011 | Zero in prefix | `C0M-RES-1992-1` | `COM-RES-1992-1` | ✅ Yes |
| ERR012 | Letter E as 3 | `R3S-1992-1234` | `RES-1992-1234` | ✅ Yes |
| ERR013 | Letter I as 1 | `1ND-1992-1234` | `IND-1992-1234` | ✅ Yes |
| ERR014 | Letter S as 5 | `RE5-1992-1234` | `RES-1992-1234` | ✅ Yes |
| ERR015 | Spaces in number | `C O M - R E S` | `COM-RES` | ✅ Yes |
| ERR016 | Multiple hyphens | `RES--1992--1234` | `RES-1992-1234` | ✅ Yes |
| ERR017 | Spaces around hyphen | `COM - RES - 1992 - 1` | `COM-RES-1992-1` | ✅ Yes |

---

## Implementation Checklist

- [ ] **Design Complete** - All formats documented
- [ ] **Service Created** - `FileNumberValidator` service
- [ ] **Database Ready** - Tables and indexes created
- [ ] **API Endpoints** - Validation endpoints implemented
- [ ] **UI Integration** - Real-time validation in forms
- [ ] **Tests Written** - Unit & integration tests
- [ ] **Data Audit** - Current data assessed
- [ ] **Migration Complete** - All records normalized
- [ ] **Documentation** - User guide written
- [ ] **Training Done** - Team trained on new system
- [ ] **Go Live** - Strict validation enabled

---

## Success Metrics

- **Data Quality:** >99% of file numbers in valid format
- **User Experience:** <1% validation errors on new entries
- **System Performance:** <100ms validation response time
- **Migration Success:** 100% of legacy data migrated/corrected
- **User Adoption:** >95% compliance with validation rules

---

## References & Related Documents

- `ST_FILE_NUMBER_IMPLEMENTATION_COMPLETE.md` - ST system details
- `.github/instructions/ST-FILE-NUMBER-INSTRUCTIONS.instructions.md` - Backend guidelines
- `app/Services/STFileNumberService.php` - Current ST implementation
- `app/Http/Controllers/Api/FileNumberApiController.php` - Current API

---

**Next Steps:**
1. Review this design with stakeholders
2. Prioritize implementation phases
3. Allocate resources for migration
4. Schedule training sessions
5. Plan go-live date

