**Change Request 22102026** – full clean Markdown version.

```markdown
# Change Request 22102026

## 1. Create the Survey Data Entry Forms
- a. Beacon Register  
- b. Coordinate Register  
- c. List of Surveyors  
- d. Create a Control Point Register  

## 2. Quick Search Interface
- Change “Missing File(s)” to “Not With KLAES” (NWK)

## 3. Department & Office Dropdowns
- Add “KLAES Project” to the Department dropdown list  
- Add “KLAES Project Office” to the Office dropdown list  

## 4–6. Collapsible Related Files Display
- Change the display of files with Related FileNo, Temporary File, etc. on the Indexing & FC Tables to collapsible  
- Apply the same to the Related Files Table  
- Apply the same to the Regrant Table  

## 7. SLTR Data Migration
- Move all SLTR entries under “Original Holder” to “Root of Title” (**ONLY if RoT is blank**)

## 8. Print File Label – QR Code Export
- Add an Export “Generated QRCodes based on FileNos” to the Print File Label interface  
- It should follow a simple query of `where print file label = 1`

## 9. SLTR Indexing Form
- Original Title Holder should be blank and greyed out going forward

## 10. ST Scan Upload and Page Typing
- ST Scan upload and PageTyping??????????????

## 11. Registration Table
- Enable Ahmad for Edit on the Registration Table

## 12. Consent Card
- Add constraints that will prevent the user from saving the Consent without entering the Bill values

## 13. RoFO / Recommendation Batch Table
- Update so entries can easily be edited from the table instead of opening Edit mode

## 14–15. Batch RoFO Interface
- Update the Batch RoFO interface so that generation and printing can be grouped based on the CreatedBy entry  
- Apply it to SLTR & ST

## 16–18. System Admin Sidebar
- Add a sidebar for **KLAES Documentation** under System Admin (users will have access to Manuals, SoPs and System Documentations)  
- Upload all the Quarter Reports  
- Remove Departments and Users under the System Admin Sidebar

## 19. User Console
- We should be able to filter the Users based on Departments from the User Console

## 20. User Profile
- Update the KLAES User profile to automatically suspend the user once his profile is set to “on leave”

## 21. Plot Merger
- Why is the first record under Plot Merger not showing the source when it was done from deeds?

## 22. DIIT
- Remove Passport photo from DIIT immediately

## 23. File Extension Issues
- a. Create an on-the-fly TrackingID for files with Extension  
- b. Files with Extension should occupy a separate line from the mother file  
- c. Do backfill  

## 24. Decommissioned Files Table
- Bring the cause of Decommissioning to the beginning of the Decommissioned Files Table

## 25. PRA and FH Interfaces – Change of Ownership
- When “change of ownership” is selected, the “Type” field will show:  
  - a. LGA (if selected → user selects LGA from dropdown)  
  - b. Ordinary  
  - c. Other (specify)

## 26. Land RDS Updates
- a. Sale Amount instead of Consideration  
- b. then Registration fees will follow  
- c. Receipt No lastly  
- d. Stamp Duty will be removed

## 27. Registries
- Add “Move NR” to all the Registries

## 28. Doc-Ware
- Add Delete Files to Doc-Ware

## 29. Recommendation Buttons
- The Create and Update Recommendation buttons require clicking them 2× before they work – why?

## 30. Doc-Ware
- Check the Doc-Ware auto file cover

## 31. Data Issue
- The records under the `kangis_land_files_on_multiple_kangis_2026-10-17.xls` table look like edited records created an entire new row instead of editing the existing record

## 32. Virtual File/Folder System (Folio)
- Why is this only showing the passport? Where is the FSC and the LGA Confirmation sheet?

## 33. Naming
- Change “Virtual File System (Folio)” to “Virtual File/Folder System (Folio)”

## 34. Virtual File System
- Check the “NOT INDEXED” statement on Virtual File System

## 35. Rev M – Auto-Notification
- Update Rev M to have auto-notification (send notification when a bill is generated, payment is made, and when the next payment is due for recurring bills)  
- Include response with response channels, reconciliation, and the payment cycle for recurring payments

## 36–37. Bill Master Interface
- Add the on-the-fly Bills to the Bill Master interface (include Bill Type and the Revenue Codes)  
- Do it in such a way that the Revenue Codes can be uploaded from Configurable Entities

## 38. Survey Module Update
- a. Check and adhere to the updated Sidebar  
- b. Change “Register GKN” to “Register File”  
- c. Change “Land for Land” to “Land to Land”  
- d. Add “Size of Plot” on the mobile and the web form  
- e. Add “Size of Acquisition” on the mobile and the web form  
- f. Where the Surveyors to work on the Project can be selected from the List of Surveyors  
- g. Add “Portion Allocation” under the Form (note the project)  
- h. See the Forms and Registers:  
  - i. Create a Beacon Register  
  - ii. Create a Coordinates List  
  - iii. Create a Control Point Register  

## 39. OP Source
- Add “Kano MDAs” to the OP Source  
- Also add the Kano MDAs dropdown list to the File Indexing, FH and PRA interfaces

## 40. FC and OSS
- Update FC and OSS so that the user can switch between the normal OP serial and auto-generated OP SerialNo

## 41. Auto-generated SerialNo
- Concatenate the auto-generated SerialNo with the name and location
```

Would you like me to save this as a `.md` file in the artifacts folder?