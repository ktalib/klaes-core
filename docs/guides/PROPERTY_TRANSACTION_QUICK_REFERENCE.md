# Property Transaction Modal - Quick Reference Card

## 🚀 Quick Access
**Location:** File Indexing → New File Index → Submit → "Add Transaction Details"

## ✅ Required Fields
| Field | Required? | Notes |
|-------|-----------|-------|
| File Number | ✅ Yes | From file indexing form |
| File Title | ✅ Yes | From file indexing form |
| Transaction Type | ✅ Yes | Per transaction |
| Transaction Date | ✅ Yes | Per transaction |

## 📋 Transaction Types & Party Labels

### Government Transactions (Auto-fills "KANO STATE GOVERNMENT")
- Certificate of Occupancy → Grantor / Grantee
- ST Certificate of Occupancy → Grantor / Grantee
- SLTR Certificate of Occupancy → Grantor / Grantee
- Customary Right of Occupancy → Grantor / Grantee
- Occupation Permit → Grantor / Grantee

### Private Transactions
- **Assignment** → Assignor / Assignee
- **Mortgage** → Mortgagor / Mortgagee
- **Lease** → Lessor / Lessee
- **Transfer** → Transferor / Transferee
- **Gift** → Donor / Donee
- **Surrender** → Surrenderor / Surrenderee
- **Release** → Releasor / Releasee
- **Tenancy** → Landlord / Tenant
- **Letter of Admin** → Administrator / Beneficiary
- **Purchase** → Vendor / Purchaser

## 🔢 Registration Number Format
```
Serial No. / Page No. / Volume No.
Example: 1 / 1 / 2
```
**Note:** Page No. auto-fills from Serial No.

## ⚡ Smart Features
- ✨ File indexing data auto-populates
- ✨ Government first party auto-fills
- ✨ Page No syncs with Serial No
- ✨ Party labels change with transaction type
- ✨ Live registration number preview
- ✨ Multiple transactions supported

## 🎯 Quick Actions
| Action | Button |
|--------|--------|
| Add Another Transaction | "Add Another Transaction" |
| Remove Transaction | ❌ (appears when >1 transaction) |
| Submit All | "Save Transaction Details" |
| Cancel | "Cancel" or ✕ |

## 💾 Database Impact

### fileNumber Table
- **Created:** If file number doesn't exist
- **Skipped:** If file number already exists
- **Fields:** mlsfNo, kangisFileNo, NewKANGISFileNo, FileName, location, plot_no, tp_no

### property_records Table
- **Created:** One record per transaction
- **Fields:** All file numbers, transaction details, parties, registration info, property details

## 🐛 Quick Troubleshooting

| Issue | Solution |
|-------|----------|
| Modal won't open | Check browser console, verify SweetAlert2 loaded |
| Can't submit | Fill Transaction Type & Date for at least 1 transaction |
| Wrong party labels | Select transaction type first, labels update automatically |
| Duplicate file number | System prevents duplicates automatically in fileNumber table |

## 📞 Support Files
- **Implementation Docs:** `PROPERTY_TRANSACTION_FROM_INDEXING_IMPLEMENTATION.md`
- **User Guide:** `PROPERTY_TRANSACTION_USER_GUIDE.md`
- **Laravel Logs:** `storage/logs/laravel.log`

## 🔗 File Locations
```
resources/views/fileindexing/
├── index.blade.php
└── partial/
    ├── file_indexing_dialog.blade.php
    └── property_transaction_modal.blade.php

app/Http/Controllers/
└── PropertyRecordController.php
    └── storeFromIndexing() method

routes/
└── apps2.php
    └── property-records.storeFromIndexing route
```

## ⚙️ Technical Stack
- **Frontend:** Alpine.js 3.x, Tailwind CSS, SweetAlert2, jQuery
- **Backend:** Laravel 9, SQL Server (sqlsrv)
- **Database:** file_indexings, fileNumber, property_records

---

**Version:** 1.0  
**Status:** ✅ Production Ready  
**Last Updated:** October 3, 2025
