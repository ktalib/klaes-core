# User Import - Quick Reference Guide

## At a Glance

**Purpose:** Import multiple users with comprehensive profile information in one operation  
**Max Users:** 50 per upload  
**File Type:** CSV or TXT  
**Max File Size:** 1MB  
**Required Columns:** 5  
**Optional Columns:** 4  

---

## CSV Column Reference

### Quick Columns List

| Column | Type | Required | Example | Notes |
|--------|------|----------|---------|-------|
| first_name | Text | ✅ YES | John | Required, any text |
| last_name | Text | ✅ YES | Doe | Required, any text |
| email | Email | ✅ YES | john@company.com | Must be unique |
| username | Text | ✅ YES | john.doe | 3-50 chars, alphanumeric._- only, unique |
| type | Text | ✅ YES | user | Available: user, super admin, etc. |
| phone_number | Text | ❌ NO | 08030000001 | Min 7 digits |
| department_id | Number | ❌ NO | 5 | From department lookup |
| user_level | Number | ❌ NO | 3 | Any number |
| assign_role | Text | ❌ NO | Dashboard; GIS - Records | Semicolon-separated |

---

## Example CSV

```csv
first_name,last_name,email,username,type,phone_number,department_id,user_level,assign_role
John,Doe,john@example.com,john.doe,user,08030000001,1,3,Dashboard; GIS - Records
Jane,Smith,jane@example.com,jane.smith,user,08030000002,5,2,ST - Overview; ST - Applications
Bob,Johnson,bob@example.com,bob.johnson,user,,2,1,Dashboard
```

---

## Step-by-Step Import

### 1. Prepare CSV File
- [ ] Create CSV with required columns
- [ ] Fill in required fields for each user
- [ ] Optional: Add optional columns
- [ ] Download department lookup if using department_id

### 2. Download Department Lookup (if needed)
- Click "Download Department Lookup" button
- Save the CSV file
- Note the ID numbers for each department

### 3. Upload CSV
- Click "Select a file" or drag-drop CSV
- Select TEST or PRO environment
- Click "Import"

### 4. Review Results
- See number of successful imports
- Check any error messages
- Fix errors and retry if needed

---

## Validation Rules

### Email
- ✅ Must be valid format: `user@domain.com`
- ✅ Must be unique in database
- ✅ Must be unique within CSV file

### Username
- ✅ Must be 3-50 characters
- ✅ Can contain: letters, numbers, dots (.), underscores (_), hyphens (-)
- ✅ Examples: `john.doe`, `jane_smith`, `bob-johnson`
- ✅ Must be unique in database
- ✅ Must be unique within CSV file

### Phone Number
- ✅ If provided, must be at least 7 digits
- ✅ Optional - can be left blank

### Department ID
- ✅ If provided, must be a valid ID from departments table
- ✅ Download department lookup to find correct IDs
- ✅ Optional - can be left blank

### User Type
- ✅ Must be filled in (required)
- ✅ Common values: `user`, `super admin`
- ✅ Check your system for available types

---

## Frequently Asked Questions

### Q: What's the default password?
**A:** All imported users get the default password: `password`  
Users should change it on first login.

### Q: Can I import 100 users at once?
**A:** No, maximum is 50 users per import.  
Split into multiple files if you have more.

### Q: What if I don't know the department ID?
**A:** Click "Download Department Lookup" button - it shows all departments with their IDs.

### Q: Can I import roles for users?
**A:** Yes, use the `assign_role` column. Example: `Dashboard; GIS - Records; ST - Overview`

### Q: What happens if there's an error?
**A:** The system shows up to 10 errors per import.  
Fix the issues and upload again.

### Q: Can I delete imported users?
**A:** Yes, if they were imported to TEST environment.  
Use "Clear Test Data" button to delete all TEST users.  
PRO users cannot be deleted this way.

### Q: What's the difference between TEST and PRO?
**A:** TEST users are for testing - they can be deleted with "Clear Test Data" button.  
PRO users are permanent and must be deleted manually.

---

## Error Messages

| Error | Cause | Fix |
|-------|-------|-----|
| First name is required | Missing first_name | Add first_name to CSV |
| Last name is required | Missing last_name | Add last_name to CSV |
| Email is required | Missing email | Add email to CSV |
| Username is required | Missing username | Add username to CSV |
| User type is required | Missing type | Add type to CSV |
| Invalid email format | Email not valid | Use format: user@domain.com |
| Username must be 3-50 characters... | Username invalid | Use 3-50 chars, only alphanumeric._- |
| Email already exists | Email used before | Use different email |
| Username already exists | Username used before | Use different username |
| Duplicate email in CSV | Email appears twice | Remove duplicate row |
| Duplicate username in CSV | Username appears twice | Remove duplicate row |
| Phone number must be at least 7 digits | Phone too short | Provide 7+ digit phone or leave blank |
| Invalid department ID: XXX | Department not found | Check department lookup, use correct ID |

---

## CSV Template Download

The import system provides a pre-formatted CSV template:
1. Click "Download CSV Template"
2. Opens in Excel or text editor
3. Replace sample data with your data
4. All 9 columns ready to use
5. Save and import

---

## Department Lookup Format

When you download the department lookup, you get:

```csv
ID,Department Name,Code,Description
1,Land Administration,LA,Core land administration services
2,GIS Operations,GIS,Geographic information systems
5,Surveying,SUR,Land surveying department
10,Finance,FIN,Financial management
```

Use the ID number in your CSV import file for department_id column.

---

## Tips for Success

✅ **Do:**
- Download the CSV template first
- Download department lookup if using department_id
- Validate email addresses before uploading
- Test with small batch (5-10 users) first
- Use TEST environment for testing
- Clear test data between test runs
- Save failed user data to retry

❌ **Don't:**
- Don't use more than 50 users per import
- Don't upload files larger than 1MB
- Don't use spaces in username (use dots/underscores)
- Don't reuse email addresses (must be unique)
- Don't leave required fields blank
- Don't forget semicolons in assign_role (use ; not comma)
- Don't delete important users using "Clear Test Data"

---

## Required Permissions

To access user import, you need one of:
- User type: `super admin`
- Permission: `create user`

Contact your administrator if you don't have access.

---

## Common Use Cases

### Use Case 1: Import New Department Team
```csv
first_name,last_name,email,username,type,phone_number,department_id,user_level,assign_role
Alice,Brown,alice@company.com,alice.brown,user,08030000101,2,2,Dashboard; GIS - Records
Bill,Davis,bill@company.com,bill.davis,user,08030000102,2,2,Dashboard; GIS - Records
Carol,Evans,carol@company.com,carol.evans,user,08030000103,2,3,Dashboard; GIS - Records; ST - Overview
```

### Use Case 2: Import Basic Users (No Departments)
```csv
first_name,last_name,email,username,type
David,Frank,david@company.com,david.frank,user
Eve,Green,eve@company.com,eve.green,user
Frank,Harris,frank@company.com,frank.harris,user
```

### Use Case 3: Import with Full Details
```csv
first_name,last_name,email,username,type,phone_number,department_id,user_level,assign_role
Grace,Jones,grace@company.com,grace.jones,user,08030000201,3,4,ST - Overview; ST - Applications; ST - Approvals (Other Departments)
Henry,King,henry@company.com,henry.king,super admin,08030000202,1,5,Dashboard; GIS - Records; ST - Overview; ST - Applications; ST - Field Data Integration; ST - Bills & Payments; ST - Approvals (Other Departments)
```

---

## Testing Checklist

Before importing real data:

- [ ] CSV file created with correct format
- [ ] All required columns present
- [ ] Sample data looks correct
- [ ] Department IDs verified (if used)
- [ ] Email addresses valid and unique
- [ ] Usernames follow rules (3-50 chars, alphanumeric._- only)
- [ ] First 1-5 users imported successfully
- [ ] Users appear in system correctly
- [ ] TEST environment used for testing
- [ ] Test users cleared after testing

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | Nov 11, 2025 | Initial release with extended fields |

---

## Support

**Questions?** Contact your system administrator.

**Found a bug?** Report to the development team.

**Need help?** See full documentation: `USER_IMPORT_EXTENDED_FIELDS_IMPLEMENTATION.md`

---

Last Updated: November 11, 2025
