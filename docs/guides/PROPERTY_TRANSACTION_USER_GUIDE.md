# Property Transaction Modal - User Guide

## Quick Start Guide for File Indexing with Transaction Capture

### Step-by-Step Process

#### 1. Create File Indexing
- Navigate to **File Indexing** module
- Click **"New File Index"** button
- Fill in the required fields:
  - ✅ File Number (required)
  - ✅ File Title (required)
  - Plot Number
  - TP Number
  - LPKN Number
  - Location/Property Description
  - Land Use Type
  - LGA
  - District
  - Registry
  - Batch Number
  - Shelf Location

#### 2. Submit File Indexing
- Click **"Create File Index"** button
- System validates and creates the indexing record
- Success message appears with two options:
  - **"Add Transaction Details"** ← Opens transaction modal
  - **"Skip for Now"** ← Closes without transactions

#### 3. Add Transaction Details (Optional but Recommended)

If you click **"Add Transaction Details"**, a modal opens with:

##### **Prefilled Information** (from your indexing form):
- File Number
- LGA
- Plot Number
- TP Number
- LPKN Number
- Property Description

##### **Transaction Fields to Complete:**

**Basic Transaction Info:**
1. **Instrument Type** (optional text field)
2. **Transaction Type** ✅ REQUIRED
   - Select from dropdown (Certificate of Occupancy, Deed of Assignment, etc.)
3. **Transaction/Certificate Date** ✅ REQUIRED

**Registration Number:**
4. **Serial No.** (e.g., "1")
5. **Page No.** (auto-fills from Serial No.)
6. **Volume No.** (e.g., "2")
7. **Reg Date** (defaults to today)
8. **Reg Time** (defaults to 09:00)

**Land Use & Tenancy:**
9. **Land Use** (select: Residential, Commercial, Agricultural, etc.)
10. **Period/Tenancy** (number + unit: Days/Months/Years)

**Transaction Parties:**
11. **First Party** (label changes based on transaction type)
    - For Deed of Assignment: "Assignor"
    - For Deed of Mortgage: "Mortgagor"
    - For Certificate of Occupancy: Auto-fills "KANO STATE GOVERNMENT"
12. **Second Party** (label changes based on transaction type)
    - For Deed of Assignment: "Assignee"
    - For Deed of Mortgage: "Mortgagee"
    - For Certificate of Occupancy: "Grantee"

#### 4. Add Multiple Transactions (Optional)

If the file has multiple transactions:
- Click **"Add Another Transaction"** button
- A new transaction form appears below
- Fill in the details for the second transaction
- Repeat as needed
- Click ❌ to remove any transaction (must have at least 1)

#### 5. Submit Transactions
- Click **"Save Transaction Details"** button
- System validates all transactions
- Creates entries in:
  - ✅ `fileNumber` table (if not already exists)
  - ✅ `property_records` table (one per transaction)
- Success message confirms save

---

## Transaction Type Party Labels

The party labels automatically change based on the selected transaction type:

| Transaction Type | First Party | Second Party |
|------------------|-------------|--------------|
| Deed of Assignment | Assignor | Assignee |
| ST Assignment | Assignor | Assignee |
| Deed of Mortgage | Mortgagor | Mortgagee |
| Tripartite Mortgage | Mortgagor | Mortgagee |
| Certificate of Occupancy | Grantor (auto: KANO STATE GOVT) | Grantee |
| ST Certificate of Occupancy | Grantor (auto: KANO STATE GOVT) | Grantee |
| SLTR Certificate of Occupancy | Grantor (auto: KANO STATE GOVT) | Grantee |
| Customary Right of Occupancy | Grantor (auto: KANO STATE GOVT) | Grantee |
| Deed of Lease | Lessor | Lessee |
| Deed of Sub Lease | Lessor | Lessee |
| Indenture of Lease | Lessor | Lessee |
| Tenancy Agreement | Landlord | Tenant |
| Deed of Transfer | Transferor | Transferee |
| Deed of Gift | Donor | Donee |
| Deed of Surrender | Surrenderor | Surrenderee |
| Deed of Release | Releasor | Releasee |
| Letter of Administration | Administrator | Beneficiary |
| Certificate of Purchase | Vendor | Purchaser |
| **Other Types** | Grantor | Grantee |

---

## Smart Features

### ✨ Auto-Fill Features
1. **File indexing data** → Automatically populates in modal
2. **Government transactions** → First party auto-fills "KANO STATE GOVERNMENT"
3. **Page Number** → Auto-syncs with Serial Number
4. **Registration Date** → Defaults to today's date
5. **Registration Time** → Defaults to 09:00

### 📝 Registration Number Preview
As you type Serial No, Page No, and Volume No, you'll see a **live preview**:
```
Registration Number: 1/1/2
```
Format: Serial/Page/Volume

### 🔄 Dynamic Party Labels
Party labels change in real-time as you select different transaction types.

### ✅ Validation
- At least one transaction must have Transaction Type and Date filled
- Cannot submit empty transactions
- Form prevents submission without required fields

---

## Common Scenarios

### Scenario 1: Single Certificate of Occupancy
1. Create file indexing
2. Choose "Add Transaction Details"
3. Select **"Certificate of Occupancy"** as Transaction Type
4. First party auto-fills as "KANO STATE GOVERNMENT"
5. Enter Grantee name
6. Fill registration details (Serial/Page/Volume)
7. Submit

### Scenario 2: Assignment + Mortgage
1. Create file indexing
2. Choose "Add Transaction Details"
3. **Transaction 1:**
   - Type: Deed of Assignment
   - Enter Assignor and Assignee
   - Fill registration details
4. Click **"Add Another Transaction"**
5. **Transaction 2:**
   - Type: Deed of Mortgage
   - Enter Mortgagor and Mortgagee
   - Fill registration details
6. Submit both transactions

### Scenario 3: Skip Transactions (Add Later)
1. Create file indexing
2. Choose **"Skip for Now"**
3. File indexing is saved
4. Transactions can be added later via Property Records module

---

## Tips & Best Practices

### ✅ Do's
- Always fill Transaction Type and Date (required)
- Use the Registration Number preview to verify format
- Add all known transactions at once to save time
- Review party labels to ensure correct data entry

### ❌ Don'ts
- Don't leave transactions partially filled
- Don't submit without at least one complete transaction
- Don't forget to verify auto-filled data is correct

---

## What Happens in the Database

### fileNumber Table
**New record created (if doesn't exist):**
- File number in appropriate format (MLS/KANGIS/NewKANGIS)
- File name from indexing
- Location, Plot No, TP No
- Type: "indexing"
- Source: "indexing"

**If record exists:** Skips creation (duplicate prevention)

### property_records Table
**New record per transaction:**
- All file number formats
- Transaction details (type, date, parties)
- Registration number (Serial/Page/Volume)
- Land use and period
- Property description and location
- Auto-determined title type
- Audit fields (created_by, updated_by, timestamps)

---

## Troubleshooting

### Modal doesn't open after indexing?
- Check browser console for JavaScript errors
- Ensure Alpine.js is loaded
- Verify SweetAlert2 is available

### "Skip for Now" doesn't close modal?
- This is expected - "Skip for Now" skips the transaction modal
- The file indexing dialog should close
- Refresh page if dialog persists

### Party fields not showing correct labels?
- Verify transaction type is selected
- Check that the transaction type exists in the dropdown
- Labels update immediately upon selection

### Cannot submit transactions?
- Ensure at least one transaction has Transaction Type filled
- Ensure at least one transaction has Transaction Date filled
- Check for validation error messages

### Database errors?
- Check `storage/logs/laravel.log` for detailed errors
- Verify SQL Server connection is active
- Ensure tables exist: `file_indexings`, `fileNumber`, `property_records`

---

## Support

For issues or questions:
1. Check the implementation documentation
2. Review Laravel logs: `storage/logs/laravel.log`
3. Check browser console for JavaScript errors
4. Verify database connection and tables exist

---

**Last Updated:** October 3, 2025  
**Feature Status:** ✅ Complete and Ready for Use
