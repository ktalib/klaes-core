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

**Done: 36 of 36** (3 of them were already in place) · **Remaining: 0**

Open items only, with what each needs: [Change Request 30102026 - Remaining](Change%20Request%2030102026%20-%20Remaining.md).

### Done

| # | Item | Notes |
|---|---|---|
| 1 | Misc KN and GKN as the related file on CO files | The CO form accepts SIT, GKN or Misc KN numbers; LPKN and others are rejected with a message. "Has SIT File?" is now "Has Related File". |
| 2 | Commissioning Sheet for CO, GKN, Misc KN, LPKN, LPPL, RDKN files | A copy of the MLPP / OSS File Commissioning Sheet, headed **Survey Department**. Print from the row menu: CO Files table (CO files) and GKN > Applications (the other five). Shows file no. and type, related file, title, TP No, Plot No, District, Location, and when and by whom it was commissioned. A captured CO file with no commission date shows the date it was captured. |
| 3 | Plot to Plot → Land to Land | Labels only; stored values unchanged. Confirmed: follow the CR. |
| 4 | Cash for Tree → Monetary Compensation | Labels only. |
| 5 | Land Assessment in the Monetary Compensation calculator | After the tree lines, a Land Assessment section: one line per piece of the farmer's land (Add Land), size × rate. The size is entered in hectares or m², or from length × width in metres; the rate comes from the Land Rates list in Configurable Entities > Survey (still editable on the line). Subtotal for trees, subtotal for land, and the total compensation. No land rate is set yet: add them in Configurable Entities > Survey > Land Rates. |
| 6 | Farm Assessment with Economic Trees / Cash Crops tabs | Economic Trees = Timber + Fruit; Cash Crops = Cash Crop. |
| 7 | "Total plots to carve" → "Creatable Plots" | |
| 8 | Classmark rating under the Land to Land calculator | The Classmark ranges live in Configurable Entities > Survey > Classmark, so Survey's own can replace them. First ranges (user, 2026-10-31): part plot .00–.09 → nothing, .10–.24 → ¼, .25–.59 → ½, .60–.99 → 1 full plot (e.g. 1.49 → 1½, 2.12 → 2¼). Shown in the Resettlement Calculator next to the creatable figure, and as a Classmark reader in the Land to Land calculator. |
| 9 | "Exact" → "Creatable" (Resettlement Calculator) | |
| 10 | Resettlement Calculator under the Land to Land calculator | Opens from a panel in the Land to Land calculator; removed from the sidebar. |
| 11 | Land to Land sharing ratio in Configurable Entities | Sharing Ratios in Configurable Entities > Survey: 50:50 is the general ratio, with 40:60, 60:40, 30:70 and 70:30. A Land to Land project picks its ratio when it is set up (default 50:50); only that project's cases use it, and it locks once the project has cases. Every existing project and case stays 50:50. The split, labels, schedules, reports, plot register and both calculators follow the project's ratio. |
| 12 | Computation File (File Jacket) under GKN | Settled 2026-10-31: Government tab = Computation Files commissioned by Survey (item 13); Private tab = Cadastral's (item 14), read-only. The Source pending banner is gone. |
| 13 | Survey commissioning Computation files with LPKN serial (Government) | "Commission Computation File" on the Government tab: numbered **LPKN-serial** with no year, e.g. LPKN-123 (one series for all years; changed 2026-11-02), with file no. (global selector, details backfilled), Plot No, District, LGA, Location, surveyor and I to S. To continue from Survey's last serial, set it under Configurable Entities > Serial Initialization > Computation Files — Government (LPKN). |
| 14 | Cadastral Field Jacket → Computation files with LPPL serial from 1 (Private) | Cadastral already reads "Computation File (Field Jacket)". Each one Cadastral commissions now also gets **LPPL-year-serial** from 1, shown first; FJ-year-00001 stays as the system code. None commissioned yet. |
| 15 | Survey Management sidebar with Government / Private tabs | Surveyors: Government = Survey staff, Private = licensed surveyors from the Cadastral register (read-only). Computation Files: both tabs blank (source pending). Instruction to Survey: Government = Survey jobs, Private = Cadastral jobs. |
| 16 | Bearing and distance with "add line" on Computation Field Observations | Several readings saved at once. |
| 17 | Link to view uploaded files from the CO Files table | "View Uploaded Files" opens the CO file's raw blind-scan pages (as asked, until page typing is done), with a link to Doc-WARE. 270 scanned pages under CO numbers so far, e.g. CO-9-2018 has 49. |
| 18 | Remove "Allocation Records" from Records | |
| 19 | Plot Showing Register (OP, Letter of Grant) under Resettlement Plot Allocation | |
| 20 | "Resettlement Plot Verification" → "Plot Showing Register" | |
| 21 | Passing & Checking (Final Survey Plan) under GKN | A first version of the form, as agreed: file no., survey plan no., computation file, surveyor, then the lines checked (from beacon, to beacon, distance on the plan, distance measured; the difference is worked out and lines over the tolerance are marked) and the result (Passed / Failed / Returned for Correction). Survey's own fields to follow. |
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
| 32 | No "Generate OP" for existing records | Blocked for imported records and for lists captured from paper, on screen and on the server. Generating for several new records at once ("batch") was already in place: tick them and press Generate Selected. |
| 33 | Delete all entries in the TP No table | Not deleted (user, 2026-10-31): the table is no longer queried and shows empty with a "TP list pending" note until the new TP lookup table is brought in; the automatic refill from TP lookups is stopped and adding is off meanwhile. The 2,100 rows are kept. To show them again: TpNumberController::LIST_PENDING. |
| 35 | How to return a generated OP | "Return" on the OP (Resettlement) table (row menu): the OP and its pipeline are removed, the case / beneficiary is back in the table to generate again, and the number is used by the next OP generated. An issued OP, or one already sent to Deeds, cannot be returned. The row actions are now one menu. |
| 34 | "In-Unit" movement in KLAES File Tracking | On File Movement (Department), every Send (single, batch, and logging an outgoing file) has an optional **Unit** after the office: the units of that office's department, or Other (specify) until the unit is added. A send between two offices of the same department is marked **In-Unit**; to another department, In Transit. The lists show the unit and an In-Unit tag. Units are kept in Configurable Entities > Units (add, edit, switch off, delete one never used), first entered (user, 2026-11-01): Land: File Commissioning, Recommendation, RofO, One Stop Shop (OSS), Land Registration; Deeds: Bill Balance, Legal Search. Not their own `departments` rows, which would appear as departments on user forms and get a Director each. Quick Search's movement timeline and the File Tracking dashboard do not show the unit yet. |
| 36 | Face check on passport photo upload | Profile photo: already live (face-api.js, in the browser). MLPP file commissioning (page and shared modal): no face, several faces, an unclear or glare-covered face, or a drawing / placeholder is refused with the reason; a whole-cover scan is cropped to the applicant's face (passport proportions) and the crop is what is filed. Runs in the browser, nothing is sent out. |

### Remaining

None.

### Also done from the 31 Oct discussion

| Request | Notes |
|---|---|
| Issue Beacon No (Beacon Register) | Button on the Beacon Register: pick a Beacon Prefix (Configurable Entities > Survey: PBB, PBD, AAA, ABC), the number of beacons, the serial (automatic = next after the last issued for that prefix, or manual) and an optional letter after it; Continue fills the range into a new entry, where the surveyor, land and payment are added. Numbers already issued are blocked, as before. |
| File Status search under Cadastral | Cadastral > Verification > File Status > Search: the same search, opening the lists view only for Cadastral. Cadastral's Title Status list also now shows every registry's records. |
| Cash crops: Mature = fruit stage | On the Cash Crops tab, Mature reads "Mature (Fruit stage)". |
| Configurable Entities > Survey | New tab: Sharing Ratios, Classmark, Land Rates, Beacon Prefixes. |

### Kano valuation schedule (1 Nov, recording 302)

| Change | Notes |
|---|---|
| Farm Assessment catalogue replaced by the Kano schedule | From the two spreadsheets (Cash Crops, Economic Trees): 41 cash crops priced per m² and 51 economic trees priced per stand, each in **Mature, Immature and Seedling** (Early is gone), with the per-hectare price and the **cost of production per hectare**. 276 rows. The old 32 trees (no rates, never used on a case) were switched off after a CSV backup. Guava is in twice (S/N 25 loaded as "Guava (improved)"); Gum arabic's duplicate loaded once; White acacia and Ziziphus are in both lists. |
| Monetary calculator: one table in four sections | One line per cash crop or economic tree, with the land under it on the same line (a cash crop's land follows its area until changed). Below, one table grouped into **Cash Crops, Cash Crop Land, Economic Trees, Economic Tree Land**, each with a subtotal, and the **grand total**. Cash crop = area × rate (per m², or per hectare when the area is typed in hectares); tree = stands × rate per stand; land = hectares × cost of production per hectare (m² ÷ 10,000). |
| Register Land wizard, mobile register, case details | Offer Mature / Immature / Seedling. |

### Follow-ups found on the way

- 783 security paper codes were marked used but still read "available" (the old single entry never set the status). Fixed 2026-11-01: the 780 still held now read "assigned" (CSV backup first); Land, SLTR and ST entry set the status. Three (012821, 012823, 012824) belong to deleted recommendations and were left as they are, still blocked.
- Passport face check: glare stays refused; passports uploaded earlier are left as they are (user, 2026-11-01).
- Survey Commissioning Sheet heading changed to DEPARTMENT OF SURVEY (user, 2026-11-01).
