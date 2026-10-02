# CSV Import Modal - User Guide & Testing

## Visual Layout

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         Import Users from CSV                         [X]   │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                               │
│ [📥 Download CSV Template] [📋 Download Department Lookup] [🗑️ Clear Test] │
│                                                                               │
│ ℹ️  CSV Import Instructions                                                  │
│     • Import up to 50 users per upload                                      │
│     • Default password: password                                            │
│     • Emails auto-generated: username@klaes.ng                              │
│     • Required: first_name, last_name, username, type                       │
│     • Optional: department_id, user_level, assign_role                      │
│                                                                               │
│ ⚠️  Important: Default Password                                              │
│     All imported users will have default password: password                 │
│     Users should change on first login                                      │
│                                                                               │
│ Environment: [Select environment...]                                        │
│     ○ TEST - For testing (can be cleared)                                   │
│     ● PRO - For production use                                              │
│                                                                               │
│ CSV File: [Drag file here or click] ⬇️                                     │
│                                                                               │
│ 📋 View CSV Format Example                                                   │
│   ├─ Header Row: first_name*, last_name*, username*, type* ...              │
│   └─ Sample: John,Doe,john.doe,user,1,High,Dashboard; GIS                   │
│                                                                               │
│ ▶  CSV Preview & Review                                                     │
│   [✓ 5 records] │ [Valid: 3] │ [Issues: 2]                                 │
│                                                                               │
│   Actions: [Clear All] [Add Row] 💡 Click to edit • Double-click confirm    │
│                                                                               │
│   ┌──┬──┬──────────┬──────────┬─────────┬────────┬────────┬──────┬────┬─────┐
│   │✓ │# │First Name│Last Name │Username │ Type   │Dept ID │Level │Role│Acts │
│   ├──┼──┼──────────┼──────────┼─────────┼────────┼────────┼──────┼────┼─────┤
│   │☑ │1 │[John   ]│[Doe    ]│[john.d]│[user  ]│[1    ]│[High]│[..]│[🗑]│
│   │  │  │         │         │       │        │       │     │    │      │
│   ├──┼──┼──────────┼──────────┼─────────┼────────┼────────┼──────┼────┼─────┤
│   │☑ │2 │[Jane   ]│[       ]│[jane.s]│[admin ]│[5    ]│[Low]│[..]│[🗑]│
│   │  │  │✓ Valid  │⚠ Error  │✓ Valid │✓ Valid │       │      │    │      │
│   │  │  │         │  Required│       │        │       │     │    │      │
│   └──┴──┴──────────┴──────────┴─────────┴────────┴────────┴──────┴────┴─────┘
│                                                                               │
│ Ready to Import?                                                            │
│   ✓ Review all data above                                                   │
│   ✓ Fix any issues or delete problematic rows                               │
│   ✓ Click Import when ready                                                 │
│                                                                               │
│ Uploading...  [████████████████████░░] 75%                                  │
│                                                                               │
├─────────────────────────────────────────────────────────────────────────────┤
│                                          [Cancel]  [Import →]               │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Step-by-Step Usage

### Step 1: Prepare CSV File
```csv
first_name,last_name,username,type,department_id,user_level,assign_role
John,Doe,john.doe,User,1,High,Dashboard; GIS - Records
Jane,Smith,jane.smith,User,5,Low,ST - Overview; ST - Applications
Bob,Johnson,bob.johnson,User,2,High,Dashboard
```

### Step 2: Select Environment
- Choose between TEST (can be cleared) or PRO (permanent)
- TEST is recommended for initial testing

### Step 3: Upload CSV
- Click the file zone or drag-and-drop your CSV file
- File name displays when selected
- CSV is automatically parsed

### Step 4: Review Preview Table
Preview automatically shows with:
- ✓ Valid rows highlighted in green
- ⚠ Error rows highlighted in red
- Statistics updated: Total, Valid, Issues count
- Error messages display for each problematic row

### Step 5: Edit Data (if needed)
- Click any cell to edit inline
- Fix required fields:
  - `first_name` - must not be empty
  - `last_name` - must not be empty
  - `username` - 3-50 chars, alphanumeric + . _ -
  - `type` - must not be empty
- Press Tab or click outside to save changes
- Status updates immediately

### Step 6: Manage Rows
- **Delete Row**: Click 🗑️ button on the right
- **Add Row**: Click [Add Row] button to add empty row
- **Clear All**: Click [Clear All] to start over
- **Select Multiple**: Use checkboxes for batch operations

### Step 7: Final Check
- Ensure all rows show ✓ Valid status
- Statistics should show Issues = 0
- All error messages resolved

### Step 8: Submit
- Click [Import →] button
- Progress bar shows upload status
- Success message displays on completion
- Page reloads automatically with new users

---

## Editing Examples

### Fixing Required Fields
```
Before Edit:
│ Jane │ [blank] │ jane.smith │ user │
│      │ ⚠ Error │            │      │
│      │ last_name is required

After Edit:
│ Jane │ Smith   │ jane.smith │ user │
│      │ ✓ Valid │            │      │
```

### Username Validation
```
Invalid Usernames:
- "ab" → Too short (min 3 chars)
- "john.doe@123" → Invalid characters (@ not allowed)
- "Musa Ali" → Contains space (not allowed)

Valid Usernames:
- "john.doe" ✓
- "john_doe" ✓
- "john-doe" ✓
- "johndoe123" ✓
```

### Optional Fields
```
These can be left empty:
│ John │ Doe │ john.doe │ user │ [   ] │ [    ] │ [        ] │
│      │     │          │      │ Dept  │ Level  │ Role       │
│      │     │          │      │ (opt) │ (opt)  │ (opt)      │
```

---

## Common Scenarios

### Scenario 1: Correct Data on First Upload
1. Upload CSV with all valid data
2. All rows show ✓ Valid
3. Statistics: Total: 5, Valid: 5, Issues: 0
4. Click Import immediately
5. Success!

### Scenario 2: Some Invalid Data
1. Upload CSV with mixed valid/invalid
2. Invalid rows show ⚠ Error with explanations
3. Edit cells directly in table
4. Fix all errors
5. Recheck statistics
6. Click Import

### Scenario 3: Adding Manual Records
1. Upload CSV with 2 records
2. Click [Add Row] button
3. New empty row appears
4. Fill in all required fields
5. Row status changes to ✓ Valid
6. Proceed with import

### Scenario 4: Removing Problem Records
1. Upload CSV with 10 records
2. Find problematic row (#7 has invalid username format)
3. Click 🗑️ button on row 7
4. Row deleted from table
5. Statistics update (now 9 records)
6. Import remaining 9 records

---

## Error Messages

| Message | Cause | Solution |
|---------|-------|----------|
| "first_name is required" | Field is empty | Click cell and enter value |
| "last_name is required" | Field is empty | Click cell and enter value |
| "username is required" | Field is empty | Click cell and enter value |
| "username must be 3-50 chars..." | Username too short or invalid chars | Use 3-50 alphanumeric + . _ - |
| "type is required" | Field is empty | Click cell and enter value |
| "No valid records to import" | All rows have errors | Fix all errors first |
| "Please upload and verify a CSV" | No file selected | Select CSV file |
| "Upload failed: ..." | Server error | Check browser console, try again |

---

## Tips & Tricks

1. **Bulk Edit**: Select multiple rows with checkboxes for future bulk edit feature
2. **Tab Navigation**: Press Tab to move between cells (left to right, wrapping)
3. **Quick Delete**: Delete problematic rows one by one
4. **Template Download**: Use "Download CSV Template" button for correct format
5. **Department Lookup**: Download PDF to find correct department IDs
6. **Test First**: Always use TEST environment for initial testing
7. **Batch Size**: Keep imports under 50 records per upload
8. **Password Reset**: Users can change default password on first login

---

## Testing Checklist

### Pre-Upload Testing
- [ ] CSV file format is correct (UTF-8 encoded)
- [ ] All required columns present: first_name, last_name, username, type
- [ ] No extra spaces in column headers
- [ ] Sample usernames follow format: alphanumeric + . _ -
- [ ] Environment selection working

### Preview Display Testing
- [ ] CSV parses correctly (correct number of rows shown)
- [ ] All columns visible in table
- [ ] Statistics update correctly
- [ ] Error rows highlighted in red
- [ ] Error messages display under problematic rows
- [ ] Row numbers display correctly

### Editing Testing
- [ ] Can click any cell to edit
- [ ] Changes save on blur/tab
- [ ] Validation updates immediately
- [ ] Status changes from Error → Valid when fixed
- [ ] Statistics update after each change

### Row Management Testing
- [ ] Delete button removes row
- [ ] Add Row button creates new empty row
- [ ] Clear All button removes all rows
- [ ] Select All checkbox works
- [ ] Individual checkboxes toggle selection

### Import Testing
- [ ] Can't import with validation errors present
- [ ] Only valid records submitted to server
- [ ] Progress bar shows during upload
- [ ] Success message displays on completion
- [ ] Page reloads with new users in table
- [ ] TEST environment users can be cleared
- [ ] PRO environment users remain permanent

### Mobile Testing
- [ ] Table scrolls horizontally on small screens
- [ ] Buttons remain accessible
- [ ] Modal width adapts to viewport
- [ ] Touch targets (buttons, inputs) are adequate size

---

## Backend Changes Required

If updating the controller, note:

### Old Format (Form Data)
```
POST /users/import/process
Content-Type: multipart/form-data
csv_file: [file]
environment: TEST
```

### New Format (JSON)
```
POST /users/import/process
Content-Type: application/json
{
    "environment": "TEST",
    "records": [
        {
            "first_name": "John",
            "last_name": "Doe",
            "username": "john.doe",
            "type": "user",
            "department_id": "1",
            "user_level": "High",
            "assign_role": "Dashboard; GIS - Records"
        }
    ],
    "_token": "csrf-token"
}
```

**Important**: Records are already validated. You can proceed with direct insertion without CSV re-parsing.
