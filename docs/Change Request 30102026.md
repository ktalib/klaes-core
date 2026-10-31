# Change Request 30102026

1. Also include Misc KN and GKN files to the related file no under the CO File Indexing and Commissioning
2. Add Commissioning Sheet for CO Files & GKN, Misc KN, LPKN, LPPL, RDKN Files
3. Change Plot to Plot to Land to Land
4. Change Cash for Tree to Monetary Compensation
5. Add the Land Assessment part to the Monetary Compensation calculator, this should include;
   - a. rate based on unit i.e. square meters/hectare
   - b. plot size driven by the dimensions
   - c. pieces of land grouped by the farmer
6. Let's change Economic Trees Assessment to Farm Assessment, then add these 2 tabs under it;
   - a. Economic Trees
   - b. Cash Crops
7. Change "Total plots to carve" to "Creatable Plots"
8. Add a "Classmark" rating under the Land-to-Land calculator from approximation of decimal points - Survey to provide us with the Classmark i.e. what determines half plot and quarter plot
9. Change "Exact" under the Resettlement Calculator to "Creatable"
10. Move the Resettlement Calculator under Land-to-Land calculator
11. Add the Land-to-Land sharing ratio definition under the Configurable Entities
12. Add Computation File (File Jacket) under Survey under GKN (refer to the updated side bar)
13. The Survey Department will be commissioning Computation files that have LPKN-Serial No (for Government Survey)
14. Change the current nomenclature for the Field Jacket under Cadastral, update the Cadastral commissioning Computation files that have LPPL-Serial No - starting from 1 as it is a new register (for Private Survey)
15. Update the side bar under GKN to have Survey Management and the following will fall under the Survey Management side bar;
    - a. Surveyors - 2 tabs Government and Private
    - b. Computation Files - 2 tabs Government and Private
    - c. Instruction to Survey (I to S) - 2 tabs Government and Private
    - d. Surveyors' Report
      - i. List of Coordinates
      - ii. Beacon Nos
      - iii. Computation Field Observations
16. Add bearing and distance to the Computation Field Observations form with "add line" to add new records
17. Add a link to view uploaded files from the CO Files indexing table
18. Remove "Allocation Records" from Records
19. Add "Plot Showing Register" under Resettlement Plot Allocation and add these 2 under it;
    - a. OP
    - b. Letter of Grant
20. Change "Resettlement Plot Verification" under Records to "Plot Showing Register"
21. Add Passing & Checking (Final Survey Plan) under GKN
22. Change "Resettlement Plots" to "Resettlement Plots (Production)"
23. Separate "Register" Land from capture records under Land Assessment
24. Disable "Apply to All" when there is a mixture of Landuse Types
25. Update the RofO Batch enter security code to do batch too
26. Add the Registration Particulars of the Registered Consent application under the FileNo under the Consent Table
27. Add the Registration Particulars of the Registered Valuation application under the FileNo under the Valuation Table
28. Remove the "Original Copy" from the SLTR Recommendation
29. Make all the entries "Tabs" under File Status and make the search bar in such a way that once a file no is entered that request will parse through all the tables and the result will be presented also make it searchable based on name and OP Serial no under the Survey module
30. Change the "SIT File" under the CO Files Table to "Related Files"
31. Add the Related File(s) Title under the Related FileNo
32. "Generate OP" under the OP (Resettlement) under Resettlement Plot Allocation should not be enable for existing records!!!!!!!!!
33. Delete all the Entries under the TP No table
34. Include "In-Unit" to the KLAES File Tracking - this involves the Unit-to-Unit movement of Files within a Department
35. How do I return a generated OP?????????????????
36. I thought we agreed to add face recognition to the passport foto upload????

---

## Status (updated 2026-10-31)

**Done: 27 of 36** (3 of them were already in place) · **Partly done: 1** · **On hold: 1** · **Remaining: 7**

Open items only, with what each needs: [Change Request 30102026 - Remaining](Change%20Request%2030102026%20-%20Remaining.md).

### Done

| # | Item | Notes |
|---|---|---|
| 1 | Misc KN and GKN as the related file on CO files | The CO form accepts SIT, GKN or Misc KN numbers; LPKN and others are rejected with a message. "Has SIT File?" is now "Has Related File". |
| 2 | Commissioning Sheet for CO, GKN, Misc KN, LPKN, LPPL, RDKN files | A copy of the MLPP / OSS File Commissioning Sheet, headed **Survey Department**. Print from the row menu: CO Files table (CO files) and GKN > Applications (the other five). Shows file no. and type, related file, title, TP No, Plot No, District, Location, and when and by whom it was commissioned. A captured CO file with no commission date shows the date it was captured. |
| 3 | Plot to Plot → Land to Land | Labels only; stored values unchanged. Confirmed: follow the CR. |
| 4 | Cash for Tree → Monetary Compensation | Labels only. |
| 6 | Farm Assessment with Economic Trees / Cash Crops tabs | Economic Trees = Timber + Fruit; Cash Crops = Cash Crop. |
| 7 | "Total plots to carve" → "Creatable Plots" | |
| 9 | "Exact" → "Creatable" (Resettlement Calculator) | |
| 10 | Resettlement Calculator under the Land to Land calculator | Opens from a panel in the Land to Land calculator; removed from the sidebar. |
| 12 | Computation File (File Jacket) under GKN | Blank table marked **Source pending** until the source is confirmed. |
| 15 | Survey Management sidebar with Government / Private tabs | Surveyors: Government = Survey staff, Private = licensed surveyors from the Cadastral register (read-only). Computation Files: both tabs blank (source pending). Instruction to Survey: Government = Survey jobs, Private = Cadastral jobs. |
| 16 | Bearing and distance with "add line" on Computation Field Observations | Several readings saved at once. |
| 17 | Link to view uploaded files from the CO Files table | "View Uploaded Files" opens Doc-WARE for the CO number. No CO file has been scanned/indexed yet. |
| 18 | Remove "Allocation Records" from Records | |
| 19 | Plot Showing Register (OP, Letter of Grant) under Resettlement Plot Allocation | |
| 20 | "Resettlement Plot Verification" → "Plot Showing Register" | |
| 22 | "Resettlement Plots" → "Resettlement Plots (Production)" | |
| 23 | Separate imported lands from lands captured in the system (Lands Assessment) | Two tabs: **Imported** (from the compensation spreadsheets) and **Captured** (registered, or keyed in from a paper list), each with its own counts and list; they replace the All Records dropdown. |
| 24 | Disable "Apply to All" for mixed land uses | Land Recommendation batch form: every Apply to all is switched off when the selected files mix land uses (from the file prefix; CON-/RC- count as their base land use), with a note. Mixed batches are still allowed. |
| 25 | RofO batch security code entry | Batches tab → "Security paper codes" → enter a range (e.g. 001335–001400) → preview in print order with each letter's security serial → save all at once. A range of the wrong size, or a used / voided / unknown code, blocks the save for correction. |
| 26 | Registration particulars under the File No (Consent table) | Already in place ("Reg: …" line). |
| 27 | Registration particulars under the File No (Valuation table) | Already in place. |
| 28 | Remove "Original Copy" from the SLTR Recommendation | Already in place (prints the File Copy only). |
| 29 | File Status search across all lists | Survey > File Status now opens a search page (by file no., OP serial no. or name) instead of the RofO list. For the file it shows whether it is in RofO (printed or not), CofO, Decommissioned, Duplicates, the Regrant register and To be Regranted, Title Status, DCIV, Caveat, Mortgage and Surrender & Release, with the matching rows and an "Open in …" link that opens that tab filtered to the file. A name or OP serial matching several files lists them to pick from. The lists stay as tabs, with Search first. The Title Status tab under Survey now lists every registry's records (view only); it listed none before. Lien has no list yet. |
| 30 | "SIT File" → "Related Files" on the CO Files table | |
| 31 | Related file title under the related file number | |
| 32 | No "Generate OP" for existing records | Blocked for imported records and for lists captured from paper, on screen and on the server. |
| 36 | Face check on passport photo upload | Profile photo: already live (face-api.js, in the browser). MLPP file commissioning (page and shared modal): no face, several faces, an unclear or glare-covered face, or a drawing / placeholder is refused with the reason; a whole-cover scan is cropped to the applicant's face (passport proportions) and the crop is what is filed. Runs in the browser, nothing is sent out. |

### Partly done

| # | Item | What is left |
|---|---|---|
| 21 | Passing & Checking (Final Survey Plan) under GKN | The sidebar entry is there (marked "Soon"); the page needs its fields / workflow. |

### On hold

| # | Item | Why |
|---|---|---|
| 11 | Land to Land sharing ratio in Configurable Entities | Held until confirmed. The 50:50 split is worked out live for every case, so changing it would also change past cases. |

### Remaining

| # | Item | Needed before it can be built |
|---|---|---|
| 5 | Land Assessment in the Monetary Compensation calculator | Where the rate per m² / hectare comes from, and how "grouped by farmer" should look. |
| 8 | Classmark rating under the Land to Land calculator | Survey to provide the Classmark rules (half plot / quarter plot). |
| 13 | Survey commissioning Computation files with LPKN serial (Government) | The source for Computation Files and the starting LPKN serial. |
| 14 | Cadastral Field Jacket → Computation files with LPPL serial from 1 (Private) | The new name for the Field Jacket and the source. |
| 33 | Delete all entries in the TP No table | Confirmation; the table is refilled automatically from TP lookups, so that sync must be dealt with too. |
| 34 | "In-Unit" movement in KLAES File Tracking | A new unit-to-unit workflow within a department; to be scoped. |
| 35 | How to return a generated OP | A question: to be answered once current OP handling is checked. |

### Follow-ups found on the way

- 783 security paper codes are marked used but still read "available" (the old single entry never set the status). The entry is fixed for new codes; correcting the existing rows needs approval.
