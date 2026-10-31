# Change Request 30102026: Remaining, On Hold and Follow-ups

Updated 2026-10-31. Of the 36 items, **27 are done**, **1 is partly done**, **1 is on hold** and **7 remain**. This file lists only the open ones and what is needed to close each.

Full list and status: [Change Request 30102026](Change%20Request%2030102026.md#status-updated-2026-10-31).

---

## Partly done

| # | Item | Done so far | Needed to finish |
|---|---|---|---|
| 21 | Add Passing & Checking (Final Survey Plan) under GKN | The sidebar entry is in place under GKN, marked "Soon". | The fields on the page and the steps of the workflow (who passes, who checks, what is recorded, what is printed). |

## On hold

| # | Item | Why it is held | Needed to release it |
|---|---|---|---|
| 11 | Add the Land to Land sharing ratio definition under Configurable Entities | Held until confirmed. The 50:50 split is worked out live for every case, so changing it in one place would also change the figures on past cases. | Confirmation to go ahead, and whether a new ratio should apply to new cases only (past cases keep 50:50) or to all cases. |

## Remaining

| # | Item | Needed before it can be built | From |
|---|---|---|---|
| 5 | Add Land Assessment to the Monetary Compensation calculator: (a) rate per unit (m² / hectare), (b) plot size from the dimensions, (c) pieces of land grouped by farmer | Where the rate per m² / hectare comes from (a fixed rate, by land use, by location?), and how "grouped by farmer" should look on screen and on the printout. | Survey |
| 8 | Add a Classmark rating under the Land to Land calculator | The Classmark rules: which decimal fractions count as a half plot and which as a quarter plot. | Survey |
| 13 | Survey Department to commission Computation files with an LPKN serial (Government Survey) | The source for the Computation Files table (item 12 is built but blank, marked **Source pending**), and the LPKN serial to start from. | Survey |
| 14 | Rename the Cadastral Field Jacket and commission Computation files with an LPPL serial starting from 1 (Private Survey) | The new name for the Field Jacket, and the source for the Private tab of Computation Files. | Cadastral |
| 33 | Delete all the entries in the TP No table | Confirmation to delete. The table refills itself from the TP lookups (the `survey:sync-tp-lookups` sync), so we also need to know whether that sync should stop or keep running. A copy of the rows is taken before any delete. | Management |
| 34 | Add "In-Unit" movement to KLAES File Tracking (unit-to-unit movement of files within a department) | Scoping: which departments and units, whether an in-unit move needs receiving like a normal hand-over, and how it shows on the file's movement history. | File Tracking / Management |
| 35 | How do I return a generated OP? | A question, not a build. To be answered once we have checked how a generated OP is held today and whether a "return" means cancel, correct or send back to a step. | Answer to be given |

## Follow-ups found on the way

These are not CR items, but came up while doing them and need a decision.

| Item | What was found | Decision needed |
|---|---|---|
| RofO security paper codes (item 25) | 783 security paper codes are marked used but still read "available", because the old single-entry screen never set the status. New entries now set it. | Approve correcting the 783 existing rows (a preview of the rows will be shown first). |
| Passport face check (item 36) | A passport photo with heavy glare from a phone flash is refused as "not clear enough". | Keep refusing, or accept with a warning? |
| Passport face check (item 36) | Passports uploaded before the check went live were not re-checked; some are whole-cover scans. | Produce a review list of old commissioning passports that fail the check? |
| Survey Commissioning Sheet (item 2) | The heading reads **SURVEY DEPARTMENT**. The other sheets read "DEPARTMENT OF …". | Keep, or change to "DEPARTMENT OF SURVEY"? |
| Survey Commissioning Sheet (item 2) | No RDKN or LPPL file exists yet, so the sheet for those two types has not been seen on a real file. | None; check the first one printed. |
| File Status search (item 29) | Survey's own resettlement OPs carry plot numbers, not a file number, so they cannot be found by OP serial until they reach Deeds. Lien has no list yet, so it shows "No Lien list yet". | None for now; noted. |
| File Status search (item 29) | Not yet clicked through by a logged-in user on the live site (tested offline). | A quick check by a Survey user. |

## Waiting on, by whom

- **Survey:** items 5, 8, 13, 21 (fields), and the Computation Files source.
- **Cadastral:** item 14 (Field Jacket name, Private source).
- **Management / user:** items 11, 33, 34, the 783-code correction and the passport questions.
- **To answer:** item 35.
