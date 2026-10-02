# RDS System - Quick Reference Card

## 🎯 What is RDS?
**RDS (Registered Document Sheet)** is an official printed document that summarizes all details of a registered instrument (Assignment, Mortgage, etc.) for record-keeping and verification purposes.

---

## 📋 Quick Commands

### Check Migration Status
```powershell
php artisan migrate:status --database=sqlsrv
```

### View RDS Routes
```powershell
php artisan route:list --name=rds
```

### Clear Caches
```powershell
php artisan config:clear && php artisan cache:clear && php artisan route:clear
```

---

## 🔗 API Endpoints

| Method | Endpoint | Purpose |
|--------|----------|---------|
| POST | `/instrument_registration/generate-rds/{id}` | Generate new RDS |
| GET | `/instrument_registration/view-rds/{id}` | View/open RDS |
| GET | `/instrument_registration/print-rds/{id}` | Print template |
| GET | `/instrument_registration/rds-status/{id}` | Check status |
| DELETE | `/instrument_registration/delete-rds/{id}` | Cancel RDS (admin) |
| GET | `/instrument_registration/list-rds` | List all RDS |

---

## 🎨 UI Integration

### Action Menu Order:
1. Edit Record
2. Register Instrument
3. **Generate RDS** 🟣 (purple icon)
4. **View RDS** 🔵 (indigo icon)
5. View CoR
6. Delete Record

### JavaScript Functions:
```javascript
generateRDS(instrumentId, stmRef)  // Generate new RDS
viewRDS(instrumentId, stmRef)      // View existing RDS
```

---

## 💾 Database

### Table: `rds_tracking`
- **Primary Key**: `id`
- **Unique Key**: `rds_reference`
- **Indexes**: 7 total (including composite)
- **Columns**: 21 fields

### Key Fields:
- `rds_reference` - RDS-YYYY-##### (unique)
- `instrument_id` - Links to registered instrument
- `stm_ref` - STM reference number
- `status` - generated | cancelled
- `print_count` - Number of times printed
- `generated_by` - User who created
- `generated_at` - Creation timestamp

---

## 🔐 Security

### Permissions:
- **Generate RDS**: Any authenticated user (for registered instruments)
- **View RDS**: Any authenticated user
- **Delete RDS**: Admin only (`type = 'super admin'`)

### Protection:
- CSRF tokens required for POST/DELETE
- Input validation on all endpoints
- Audit logging for all actions
- Duplicate prevention

---

## 🎯 Usage Flow

```
1. Instrument registered → status = 'registered', STM_Ref assigned
                ↓
2. User clicks "Generate RDS"
                ↓
3. System creates RDS record → RDS-2025-00001
                ↓
4. User clicks "View RDS"
                ↓
5. Print count incremented → Opens in new tab
                ↓
6. User prints document → ORIGINAL or COPY watermark
```

---

## 📊 RDS Reference Format

```
RDS-{YEAR}-{SEQUENCE}
```

Examples:
- `RDS-2025-00001`
- `RDS-2025-00002`
- `RDS-2026-00001` (resets each year)

Sequence: 5-digit zero-padded number

---

## 🖨️ Print Features

### Watermark Logic:
- **First print** (`print_count = 1`): "ORIGINAL"
- **Subsequent prints** (`print_count > 1`): "COPY"

### Print Template Sections:
1. Header (Logo, RDS Number)
2. Document Type (Instrument Type, STM Ref)
3. Registration Details
4. Parties Information (Grantor/Grantee)
5. Property Details
6. Financial Details
7. Legal Representative
8. Footer (Signatures, Print Count)

---

## 🧪 Testing

### Test URL:
Open `test_rds_system.html` in browser

### Manual Test Steps:
1. Go to `/instrument_registration`
2. Find registered instrument
3. Click action menu (⋮)
4. Test "Generate RDS"
5. Test "View RDS"
6. Verify print template
7. Check watermark on reprint

---

## 🐛 Troubleshooting

### Issue: "RDS not generated"
**Solution**: Generate RDS first using "Generate RDS" button

### Issue: "Instrument not found"
**Solution**: Verify instrument ID exists and is registered

### Issue: "Duplicate RDS"
**Solution**: RDS already exists - use "View RDS" instead

### Issue: Print count not incrementing
**Solution**: Check database connection and verify rds_tracking table

### Issue: Foreign key error
**Solution**: FK constraint is optional - table works without it

---

## 📁 Files Reference

### Core Files:
```
✓ RDSController.php               (460 lines)
✓ 2025_10_14_000001_create_rds.php (95 lines)
✓ print.blade.php                  (352 lines)
✓ apps2.php                        (modified)
✓ index.blade.php                  (modified)
```

### Documentation:
```
✓ RDS_IMPLEMENTATION_COMPLETE.md   (full docs)
✓ RDS_IMPLEMENTATION_SUMMARY.md    (summary)
✓ RDS_QUICK_REFERENCE.md           (this file)
```

### Testing:
```
✓ test_rds_system.html             (test interface)
✓ check_registered_instruments_structure.sql
✓ add_rds_foreign_key.sql          (optional)
```

---

## 📞 Support

### Check Logs:
```powershell
tail -f storage/logs/laravel.log
```

### Database Query:
```sql
SELECT * FROM rds_tracking ORDER BY generated_at DESC;
```

### Route Check:
```powershell
php artisan route:list --name=rds
```

---

## ✅ Status Checklist

Before going live:
- [x] Migration completed
- [x] Routes registered
- [x] Controller implemented
- [x] Print template created
- [x] Frontend integrated
- [x] Documentation complete
- [ ] Test with real data
- [ ] User training
- [ ] Production deployment

---

## 🎓 Key Concepts

### RDS vs CoR:
- **RDS**: Internal document sheet for registered instruments
- **CoR**: Certificate of Registration (different document)

### Status Values:
- `generated`: RDS successfully created
- `cancelled`: RDS cancelled by admin

### Print Count:
- Starts at 0
- Increments on each view/print
- Determines watermark (ORIGINAL/COPY)

---

## 🚀 Production Ready

**Implementation Status**: ✅ COMPLETE  
**Migration Status**: ✅ SUCCESS  
**Route Status**: ✅ REGISTERED  
**Testing Status**: ⚠️ PENDING USER TESTS  

---

**Version**: 1.0.0  
**Date**: October 14, 2025  
**Maintained by**: Development Team
