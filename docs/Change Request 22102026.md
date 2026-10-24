Here’s the cleaned-up and clarified Markdown version that incorporates both the original Change Request 22102026 and the explanations from the voice memo.

```markdown
# Change Request 22102026  
**Clarified from Voice Memo (22 Oct 2026)**

---

### 1. Survey Data Entry Forms
Create the following forms/registers:
- a. Beacon Register  
- b. Coordinate Register  
- c. List of Surveyors  
- d. Control Point Register  

---

### 2. Quick Search Interface
- Change the label **“Missing File(s)”** to **“Not With KLAES” (NWK)**.

---

### 3. Department & Office Dropdowns
- Add **“KLAES Project”** to the Department dropdown list.  
  *(Note: They are not a normal department – this is just so users can select it.)*
- Add **“KLAES Project Office”** to the Office dropdown list.

---

### 4–6. Collapsible Display of Related / Temporary Files
Make the display of files that have:
- Related File Number  
- Temporary File Number  

**collapsible** (expandable row) on:
- Indexing & FC Tables  
- Related Files Table  
- Regrant Table  

**Behaviour**
- Similar to the existing Sectional Title behaviour.
- When a record has a Temporary File or Related File(s), the row becomes expandable.
- Expanding it shows the additional related/temporary records on separate lines.
- Temporary File is **not** treated as a Related File.

---

### 7. SLTR – Original Holder → Root of Title
- Move **all existing SLTR entries** that are currently under “Original Holder” to **“Root of Title”**,  
  **but only if Root of Title (RoT) is currently blank**.
- Going forward, on the SLTR indexing form:
  - **Original Title Holder** field must be **blank and greyed out** (disabled).
- Reason: Original Holder is not considered a title holder. The three fields are:
  1. Root of Title (RoT)  
  2. Original Holder  
  3. Current Holder  

---

### 8. Print File Label – QR Code Export
- Add an **Export Generated QR Codes** feature based on File Numbers.
 

---

### 9. SLTR Indexing Form
- Original Title Holder field must be blank and greyed out going forward (same as point 7).

---

### 10. ST Scan Upload & Page Typing
- Activate **ST Scan Upload** and **Page Typing**.
- After the update is done, the EDMS section should become enabled (same behaviour as other modules).

---

### 11. Registration Table
- Enable **Ahmad** for Edit rights on the Registration Table.

---

### 12. Consent Card – Validation
- Add constraints so the user **cannot save** a Consent record unless the Bill values have been entered.

---

### 13. RoFO / Recommendation Batch Table – Inline Editing
- Allow entries to be edited **directly inside the table** (double-click / inline edit) instead of forcing the user to open the full Edit mode.
- Especially important for the batch users who need to quickly edit fields such as plot numbers, etc.

---

### 14–15. Batch RoFO Interface – Group by CreatedBy
- Update the Batch RoFO interface so that generation and printing can be **grouped by the CreatedBy** user.
- Apply the same grouping behaviour to **SLTR** and **ST**.

---

### 16–18. System Admin Sidebar
- Add a new sidebar item **“KLAES Documentation”** under System Admin.  
  Users should be able to access:
  - Manuals  
  - SoPs  
  - System Documentations  
- Upload all the **Quarterly Reports**.
- **Remove** the “Departments” and “Users” menu items from the System Admin sidebar (no longer needed in this application).

---

### 19. User Console – Filter by Department
- Add the ability to **filter Users by Department**.
- Currently there are only tabs for MDC / Ministry + name search.
- Add a Department dropdown so a user can quickly see e.g.:
  - All Cadastral staff  
  - All Survey staff  
  - etc.

---

### 20. User Profile – Auto Suspend on Leave
- When a user’s profile is set to **“On Leave”**, the system must **automatically suspend** the account.
- Reason: Staff on leave (e.g. travelling to Mecca, France, etc.) should not be able to access the system from outside.

---

### 21. Plot Merger – Source Not Showing
- Investigate why the **first record** under Plot Merger does not show the source when the merger was done from Deeds.
- Needs to be fixed.

---

### 22. DIIT
- **Remove Passport photo** from DIIT tracking sheet 

---

### 23. File Extension Handling
- a. Create an **on-the-fly TrackingID** for files that have an Extension (because we cannot pre-generate them).  
- b. Files with Extension must appear on a **separate row rom the mother file.  
- c. Perform **backfill** for all extensions that have already been created.

---

### 24. Decommissioned Files Table
- Move the **Cause / Reason of Decommissioning** to the **beginning** of the table so users do not have to scroll.
- Reasons include Subdivision, Change of Purpose, etc.

---

### 25. PRA & FH Interfaces – Change of Ownership Type
When the user selects **“Change of Ownership”**, a **Type** field must appear with these options:
- a. **LGA** → then show an LGA dropdown for selection  
- b. **Ordinary**  
- c. **Other (specify)**  

Apply to both PRA and FH interfaces.

---

### 26. Land RDS Updates
Change the order and labels as follows:
1. **Sale Amount** (instead of Consideration)  
2. Registration Fees  
3. Receipt Number  

- Completely **remove Stamp Duty**.

---

### 27. Registries
- Add **“Move NR”** action to **all** Registries.

---

### 28. Doc-Ware
- Add **Delete Files** functionality to Doc-Ware.  
  *(Control this carefully.)*

---

### 29. Recommendation Buttons
- Investigate why the **Create** and **Update Recommendation** buttons require **two clicks** before they work.

---

### 30. Doc-Ware
- Check the **auto file cover** generation.

---

### 31. Duplicate / Incorrect Records
- The records in `kangis_land_files_on_multiple_kangis_2026-10-17.xls` appear to have created **entire new rows** when records were edited instead of updating the existing row.  
  (Some duplicates have already been cleaned.)

---

### 32. Virtual File / Folder System (Folio)
- Currently only the passport is showing.  
- Investigate why the **FSC** and **LGA Confirmation sheet** are missing.

---

### 33. Naming Change
- Rename **“Virtual File System (Folio)”** to **“Virtual File/Folder System (Folio)”**.

---

### 34. Virtual File System
- Check the **“NOT INDEXED”** statement / logic.

---

### 35. Rev M – Auto Notifications
Update Rev M to send automatic notifications for:
- Bill generated  
- Payment made  
- Next payment due (for recurring bills)  

Also include:
- Response with response channels  
- Reconciliation  
- Payment cycle for recurring payments  

---

### 36–37. Bill Master – On-the-Fly Bills
- Add **on-the-fly Bills** to the Bill Master interface.  
  Must include **Bill Type** and **Revenue Codes**.
- Revenue Codes must be uploadable from **Configurable Entities**.  
  (Suggestion: add a tab under Configurable Entities for Revenue / Float items so they can be uploaded and used by the Revenue Management System.)

---

### 38. Survey Module Updates
- a. Check and adhere to the updated Sidebar  
- b. Change **“Register GKN”** → **“Register File”**  
- c. Change **“Land for Land”** → **“Land to Land”**  
- d. Add **“Size of Plot”** on both mobile and web forms  
- e. Add **“Size of Acquisition”** on both mobile and web forms  
- f. Allow selection of Surveyors who will work on the Project from the **List of Surveyors**  
- g. Add **“Portion Allocation”** under the Form (note the project)  
- h. Forms & Registers to create:  
  - i. Beacon Register  
  - ii. Coordinates List  
  - iii. Control Point Register  

*(Additional detailed workload for the Survey module will be provided later.)*

---

### 39. OP Source – Kano MDAs
- Add **“Kano MDAs”** to the OP Source list.
- Also add the **Kano MDAs dropdown** to:
  - File Indexing  
  - FH  
  - PRA interfaces  

---

### 40. FC & OSS – OP Serial Number
- Allow the user to **switch** between:
  - Normal OP Serial  
  - Auto-generated OP SerialNo  

---

### 41. Auto-generated SerialNo
- Concatenate the auto-generated SerialNo with the **Name** and **Location**.
```

Would you like me to save this as a `.md` file in the artifacts folder (e.g. `Change_Request_22102026_Clarified.md`)?