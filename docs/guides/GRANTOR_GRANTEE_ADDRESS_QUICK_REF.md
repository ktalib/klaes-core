# Grantor/Grantee Address Rules – Quick Reference

**Status:** ✅ IMPLEMENTED & READY FOR TESTING  
**Date:** November 13, 2025

---

## Rule Summary

| Instrument Type | Grantor Address | Grantee Address | Source |
|---|---|---|---|
| **ST CofO** | `Government House, Kano` | Sub/Mother Address | `subapplications.address` or `mother_applications.address` |
| **ST Assignment (SUA)** | `allocation_source` | Sub/Mother Address | `subapplications.allocation_source` / `.address` |
| **ST Assignment (Non-SUA)** | (empty) | Sub/Mother Address | `subapplications.address` or `mother_applications.address` |
| **ST Fragmentation** | `Government House, Kano` | Mother Address | `mother_applications.address` |
| **Other Instruments** | As stored | As stored | `instrument_registration` table |

---

## Code Implementation

### 1. Helper Functions (app/Helper/helper.php)

```php
// Format an address with proper capitalization and comma spacing
formatAddress($address)

// Get Grantor address based on instrument type
getGrantorAddress($instrumentType, $applicationData)

// Get Grantee address from application (with fallbacks)
getGranteeAddress($applicationData)
```

### 2. Usage in Controller (InstrumentRegistrationController.php)

```php
// ST Assignment Record
'GrantorAddress' => getGrantorAddress('ST Assignment (Transfer of Title)', $subApp),
'GranteeAddress' => getGranteeAddress($subApp),

// ST CofO Record
'GrantorAddress' => getGrantorAddress('Sectional Titling CofO', $subApp),
'GranteeAddress' => getGranteeAddress($subApp),

// ST Fragmentation Record
'GrantorAddress' => getGrantorAddress('Sectional Titling CofO', $motherApp),
'GranteeAddress' => getGranteeAddress($motherApp),
```

---

## Database Fields Required

**subapplications Table:**
- `allocation_source` - For ST Assignment grantor address (SUA only)
- `address` - For grantee address (selected as `sub_address`)

**mother_applications Table:**
- `address` - For ST Fragmentation addresses (selected as `mother_address`)

**All queries updated** to include these fields ✅

---

## Test Quick Checklist

```
☐ ST CofO has GrantorAddress = "Government House, Kano"
☐ ST Assignment (SUA) has GrantorAddress = allocation_source
☐ ST Assignment (Non-SUA) has empty GrantorAddress
☐ All instruments have properly formatted GranteeAddress
☐ Addresses display in Instrument Registration table
☐ Addresses persist when registering instrument
☐ Addresses display in RDS print template
☐ Address formatting is correct (capitalization, commas)
☐ Null/empty addresses handled gracefully (no errors)
```

---

## Example Results

### ST CofO

**Before:**
```
GrantorAddress: ""
GranteeAddress: ""
```

**After:**
```
GrantorAddress: "Government House, Kano"
GranteeAddress: "Guda Abdullahi Road, Daurawa, Tarauni, Kano"
```

### ST Assignment (SUA)

**Before:**
```
GrantorAddress: ""
GranteeAddress: ""
```

**After:**
```
GrantorAddress: "State Government"
GranteeAddress: "111, Murtala Mohammed Way, Fagge, Fagge, Kano"
```

### ST Fragmentation

**Before:**
```
GrantorAddress: ""
GranteeAddress: ""
```

**After:**
```
GrantorAddress: "Government House, Kano"
GranteeAddress: "250 Bishop Aba Road, Alkali Ward, Tarauni, Kano"
```

---

## Files Changed

| File | Changes |
|---|---|
| `InstrumentRegistrationController.php` | Added address fields to queries; Updated 3 record creations to use helper functions |
| `helper.php` | Added formatAddress(), getGrantorAddress(), getGranteeAddress() |

**Total Lines Added:** ~150 lines across both files

---

## Verification Commands

```bash
# Check helper functions exist
grep -n "function formatAddress" app/Helper/helper.php
grep -n "function getGrantorAddress" app/Helper/helper.php
grep -n "function getGranteeAddress" app/Helper/helper.php

# Check controller uses helpers
grep -n "getGrantorAddress" app/Http/Controllers/InstrumentRegistrationController.php
grep -n "getGranteeAddress" app/Http/Controllers/InstrumentRegistrationController.php

# Clear caches if deployed
php artisan cache:clear
php artisan view:clear
```

---

## Full Documentation

See `GRANTOR_GRANTEE_ADDRESS_RULES.md` for:
- Detailed implementation notes
- Complete test cases
- Data flow diagrams
- Rollback procedures
- Edge case handling

---

## Support

**Questions or Issues?**
- Review GRANTOR_GRANTEE_ADDRESS_RULES.md for detailed documentation
- Check test cases for expected behavior
- Verify database has required address fields
- Ensure cache is cleared if deploying

---

**Status:** ✅ **COMPLETE – READY FOR QA TESTING**
