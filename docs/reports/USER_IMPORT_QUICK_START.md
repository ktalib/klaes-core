# User Import - 5-Minute Quick Start

**Time Required:** 5 minutes  
**Difficulty:** Easy  
**Latest Update:** November 11, 2025

---

## 🎯 What You'll Learn

- How to download the import template
- How to fill in the CSV file
- How to import users
- How to handle errors

---

## Step 1️⃣ Download the Template

1. Go to **Users** → **Import Users**
2. Click **"Download CSV Template"**
3. File downloads: `user-import-template.csv`
4. Open in Excel or text editor

---

## Step 2️⃣ Check Required vs Optional Fields

### ✅ You MUST Fill (Required)
- **first_name** - John
- **last_name** - Doe
- **email** - john@example.com
- **username** - john.doe
- **type** - user

### ✔️ You CAN Fill (Optional)
- **phone_number** - 08030000001
- **department_id** - 5 (from lookup)
- **user_level** - 3
- **assign_role** - Dashboard; GIS - Records

---

## Step 3️⃣ Get Department IDs (If Needed)

1. Click **"Download Department Lookup"**
2. Opens: `department-lookup-YYYY-MM-DD.csv`
3. Find your department ID
4. Use that ID in your CSV

Example:
```
ID    Department Name         Code
1     Land Administration     LA
5     Surveying              SUR
10    Finance                FIN
```

---

## Step 4️⃣ Fill Your CSV

### Minimum (Required Only)
```csv
first_name,last_name,email,username,type
John,Doe,john@example.com,john.doe,user
Jane,Smith,jane@example.com,jane.smith,user
```

### Complete (All Fields)
```csv
first_name,last_name,email,username,type,phone_number,department_id,user_level,assign_role
John,Doe,john@example.com,john.doe,user,08030000001,1,3,Dashboard; GIS - Records
Jane,Smith,jane@example.com,jane.smith,user,08030000002,5,2,ST - Overview
```

---

## ⚠️ Important Tips

### Semicolons for Roles
✅ **RIGHT:** `Dashboard; GIS - Records; ST - Overview`  
❌ **WRONG:** `Dashboard, GIS - Records, ST - Overview` (comma won't work!)

### Email Rules
✅ Must be valid: `user@company.com`  
✅ Must be unique (not used before)

### Username Rules
✅ 3-50 characters  
✅ Only: letters, numbers, dots (.), underscores (_), hyphens (-)  
✅ Examples: `john.doe`, `jane_smith`, `bob-johnson`

### Phone Rules
✅ Optional - can leave blank  
✅ If you fill it: minimum 7 digits

---

## Step 5️⃣ Upload Your CSV

1. Click **"Import Users from CSV"**
2. Select **TEST** or **PRO** environment
   - **TEST** - For testing, can be deleted later
   - **PRO** - Permanent, cannot be deleted via "Clear" button
3. Choose your CSV file
4. Click **"Import"**

---

## ✅ What Happens Next

### If Successful ✓
```
✓ Import completed successfully: 3 users imported
Users appear in system immediately
Can start using their accounts
```

### If Failed ✗
```
✗ Error: Row 2: Email already exists
Fix the issue and upload again
```

### If Some Errors
```
✓ Imported: 2 users
✗ Failed: 1 user
Row 3: Invalid department ID: 999
```

---

## 🆘 Common Errors & Fixes

| Error | Fix |
|-------|-----|
| First name is required | Add first_name to column |
| Invalid email format | Use: user@domain.com |
| Email already exists | Use different email |
| Username already exists | Use different username |
| Invalid department ID: 999 | Check department lookup, use correct ID |
| Username must be 3-50 chars | Username too short/long |

---

## 💡 Pro Tips

✅ **Start Small** - Test with 1-2 users first  
✅ **Use Template** - Start from download template  
✅ **Save Backup** - Keep a copy of your CSV  
✅ **TEST First** - Use TEST environment to practice  
✅ **Verify Data** - Check department IDs before uploading  
✅ **Don't Use Commas** - Use semicolons for roles  

---

## 📋 Field Quick Reference

| Field | Required | Example |
|-------|----------|---------|
| first_name | ✅ YES | John |
| last_name | ✅ YES | Doe |
| email | ✅ YES | john@company.com |
| username | ✅ YES | john.doe |
| type | ✅ YES | user |
| phone_number | ❌ NO | 08030000001 |
| department_id | ❌ NO | 5 |
| user_level | ❌ NO | 3 |
| assign_role | ❌ NO | Dashboard; GIS |

---

## ❓ FAQ

### Q: What's the default password?
**A:** `password` - Users must change on first login

### Q: How many users can I import at once?
**A:** Maximum 50 users per file

### Q: Can I import 100 users?
**A:** No, max is 50. Split into 2 files.

### Q: Can I change department after import?
**A:** Yes, edit the user after creation

### Q: What does TEST vs PRO mean?
**A:** 
- TEST users can be deleted with "Clear Test Data"
- PRO users are permanent

### Q: Can I use commas in roles?
**A:** No, must use semicolons: `Role1; Role2`

### Q: What if the department doesn't exist?
**A:** Download department lookup to verify

---

## 🚀 Next Steps

1. ✅ Download CSV template
2. ✅ Get department IDs (if needed)
3. ✅ Fill in your user data
4. ✅ Upload to TEST environment
5. ✅ Verify users created correctly
6. ✅ Upload to PRO environment (final)

---

## 📞 Need Help?

- **User Guide:** See `USER_IMPORT_QUICK_REFERENCE.md`
- **Technical Docs:** See `USER_IMPORT_EXTENDED_FIELDS_IMPLEMENTATION.md`
- **Troubleshooting:** See `USER_IMPORT_QUICK_REFERENCE.md` FAQ section

---

## ✨ Summary

| Step | Time | Action |
|------|------|--------|
| 1 | 30s | Download template |
| 2 | 30s | Get department lookup |
| 3 | 2m | Fill CSV file |
| 4 | 30s | Upload CSV |
| 5 | 1m | Verify results |
| **Total** | **~5 min** | **Users imported!** |

---

## Example: Complete Import

### Your CSV File
```csv
first_name,last_name,email,username,type,phone_number,department_id,user_level,assign_role
Alice,Brown,alice@company.com,alice.brown,user,08030000101,2,2,Dashboard; GIS - Records
Bob,Davis,bob@company.com,bob.davis,user,08030000102,2,2,Dashboard; GIS - Records
Carol,Evans,carol@company.com,carol.evans,user,08030000103,2,3,Dashboard; GIS - Records; ST - Overview
```

### Result
✅ 3 users imported  
✅ Alice → Department 2, Level 2, Roles assigned  
✅ Bob → Department 2, Level 2, Roles assigned  
✅ Carol → Department 2, Level 3, Extra roles assigned  
✅ All users active and ready to use  

---

**You're ready to import users! 🎉**

For more details, see the comprehensive documentation.

Last Updated: November 11, 2025
