# Testing the Commissioning Sheet Implementation

## Issues Fixed

### ✅ **Problem 1: File number showing as undefined**
**Cause**: DataTable columns were using different field names than expected
**Solution**: Updated action buttons to use correct field names from controller:
- `mlsfNo` - MLS File Number
- `kangisFileNo` - KANGIS File Number  
- `NewKANGISFileNo` - New KANGIS File Number
- `FileName` - File Name

### ✅ **Problem 2: Other information not showing**
**Cause**: Field mapping between DataTable and form was incorrect
**Solution**: Added proper field mapping for all fields:
- `plot_no` → Plot Number
- `tp_no` → TP Number
- `location` → Location

### ✅ **Problem 3: 'N/A' values being treated as data**
**Cause**: Controller returns 'N/A' for empty fields, which was being used as actual data
**Solution**: Added filtering to replace 'N/A' with empty strings

## How to Test the Fixed Implementation

### 1. **Check Browser Console**
Open browser developer tools (F12) and check the Console tab when clicking the green commissioning sheet button. You should see:

```javascript
Button data attributes: {
  mlsfNo: "MLS/2025/001",
  kangisNo: "KANGIS123", 
  newKangisNo: "NEW456",
  fileName: "Sample Document",
  plotNo: "Plot 123",
  tpNo: "TP456", 
  location: "Victoria Island"
}

Opening commissioning sheet with data: {
  fileNumber: "MLS/2025/001",
  fileName: "Sample Document", 
  plotNo: "Plot 123",
  tpNo: "TP456",
  location: "Victoria Island"
}
```

### 2. **Test Form Pre-filling**
1. Navigate to MLS File Number Generator page
2. Click the green 📄 button on any row in the table
3. Commissioning sheet modal should open with:
   - **File Number**: Auto-filled from table row
   - **File Name**: Auto-filled from table row  
   - **Plot Number**: Auto-filled if available
   - **TP Number**: Auto-filled if available
   - **Location**: Auto-filled if available
   - **Date Created**: Today's date
   - **Created By**: Current user name

### 3. **Test File Number Priority**
The system prioritizes file numbers in this order:
1. **MLS File No** (mlsfNo) - First priority
2. **KANGIS File No** (kangisFileNo) - Second priority  
3. **New KANGIS File No** (NewKANGISFileNo) - Third priority

### 4. **Visual Feedback**
When data is pre-filled, you should see a blue info box appear briefly saying:
> ℹ️ "Data loaded from selected file number record"

This confirms that data was successfully loaded from the table row.

### 5. **Test Data Filtering**
The system now properly handles:
- Empty fields → Shows as blank in form
- 'N/A' values → Converted to blank in form
- Null values → Shows as blank in form
- Valid data → Shows correctly in form

## Testing Different Scenarios

### Scenario 1: Complete Record
**Table Row Has**: MLS File No, File Name, Plot No, TP No, Location
**Expected Result**: All fields pre-filled correctly

### Scenario 2: Partial Record
**Table Row Has**: Only KANGIS File No and File Name
**Expected Result**: File Number and File Name filled, others blank

### Scenario 3: Empty Record
**Table Row Has**: Only ID (all other fields are 'N/A')
**Expected Result**: All form fields blank, no error

### Scenario 4: Mixed Data Types
**Table Row Has**: MLS File No + 'N/A' for other fields
**Expected Result**: Only File Number filled, others blank

## Debug Commands

### Check DataTable Data Structure
```javascript
// In browser console, after table loads:
console.log('Table data:', table.data().toArray());
```

### Check Button Data Attributes
```javascript
// Click any green button and check console output
// Should show clean data without 'N/A' values
```

### Verify Form Pre-filling
```javascript
// After clicking green button, check form values:
console.log('Form values:', {
  fileNumber: document.getElementById('cs_file_number').value,
  fileName: document.getElementById('cs_file_name').value,
  plotNo: document.getElementById('cs_plot_number').value,
  tpNo: document.getElementById('cs_tp_number').value,
  location: document.getElementById('cs_location').value
});
```

## Expected Behavior After Fix

1. **No more "undefined" values** in form fields
2. **Proper data extraction** from table rows  
3. **Clean field values** (no 'N/A' strings)
4. **Correct file number priority** (MLS > KANGIS > New KANGIS)
5. **Visual confirmation** when data is loaded
6. **Graceful fallback** if no data available

The implementation now correctly handles all edge cases and provides a smooth user experience for generating commissioning sheets from existing file number records.
