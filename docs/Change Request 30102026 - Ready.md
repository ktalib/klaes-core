# Change Request 30102026 — Ready to Build

Items from [Change Request 30102026](Change%20Request%2030102026.md) that are clear and can be done without further input. Original item numbers are kept.

1. Also include Misc KN and GKN files to the related file no under the CO File Indexing and Commissioning
3. Change Plot to Plot to Land to Land
4. Change Cash for Tree to Monetary Compensation
6. Let's change Economic Trees Assessment to Farm Assessment, then add these 2 tabs under it;
   - a. Economic Trees
   - b. Cash Crops
7. Change "Total plots to carve" to "Creatable Plots"
9. Change "Exact" under the Resettlement Calculator to "Creatable"
10. Move the Resettlement Calculator under Land-to-Land calculator
11. Add the Land-to-Land sharing ratio definition under the Configurable Entities
16. Add bearing and distance to the Computation Field Observations form with "add line" to add new records
17. Add a link to view uploaded files from the CO Files indexing table
18. Remove "Allocation Records" from Records
22. Change "Resettlement Plots" to "Resettlement Plots (Production)"
26. Add the Registration Particulars of the Registered Consent application under the FileNo under the Consent Table
27. Add the Registration Particulars of the Registered Valuation application under the FileNo under the Valuation Table
28. Remove the "Original Copy" from the SLTR Recommendation
30. Change the "SIT File" under the CO Files Table to "Related Files"
31. Add the Related File(s) Title under the Related FileNo
32. "Generate OP" under the OP (Resettlement) under Resettlement Plot Allocation should not be enable for existing records

## Status (2026-10-31)

| Item | Status |
|---|---|
| 1 | Done: CO form accepts SIT, GKN or Misc KN (selector opens SIT + Survey > GKN); "Has Related File" |
| 2 | Done (user, 2026-10-31): copy of the MLPP / OSS Commissioning Sheet headed Survey Department; printed from the CO Files table and GKN > Applications |
| 29 | Done (user, 2026-10-31): File Status opens a search page (file no. / OP serial / name) that checks every File Status list; lists stay as tabs; Survey's Title Status tab now view-only across registries |
| 3, 4 | Done: labels only (stored scheme values unchanged). Item 3 confirmed by the user 2026-10-31 (follow the CR) |
| 6 | Done: Farm Assessment with Economic Trees / Cash Crops tabs |
| 7, 9 | Done: "Creatable Plots", "Creatable" |
| 10 | Done: Resettlement Calculator opens from the Land to Land calculator; removed from the sidebar |
| 11 | On hold until the user confirms (2026-10-31); nothing built. The 50:50 split is computed live for every case, so changing the ratio would change past cases |
| 12 | Done: Computation Files (Field Jackets) page, blank, "Source pending" |
| 15 | Done: Survey Management group; Government / Private tabs on Surveyors (staff / licensed, from the Cadastral register), Computation Files (blank, LPKN / LPPL) and Instruction to Survey (Survey / Cadastral jobs) |
| 16 | Done: Add Reading is a multi-line table (bearing + distance per line, "Add line") |
| 17 | Done: "View Uploaded Files" opens Doc-WARE for the CO file number (no CO file is indexed in EDMS yet) |
| 18, 19, 20, 21, 22 | Done (sidebar per the outline); Passing & Checking shows as "Soon" |
| 26, 27, 28 | Already in place (Reg line under File No; SLTR Recommendation prints File Copy only) |
| 30, 31 | Done: "Related Files" column with the file's title under the number |
| 32 | Done: no Generate OP for existing records (imported, or captured from a paper list: new captured_from_paper flag), enforced on the server too |
| 24 | Done (from the user's notes): Apply to all is off in the Land Recommendation batch form when the selected files mix land uses |
| 25 | Done (from the user's notes): range entry of security paper codes per RofO batch, in print order; mismatches block |

Full status of all 36 items: [Change Request 30102026](Change%20Request%2030102026.md#status-updated-2026-10-31).
Open items only (remaining, on hold, follow-ups): [Change Request 30102026 - Remaining](Change%20Request%2030102026%20-%20Remaining.md).
