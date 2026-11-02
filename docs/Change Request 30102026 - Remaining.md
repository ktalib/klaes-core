# Change Request 30102026: Remaining and Follow-ups

Updated 2026-10-31, after building the answers from the 31 Oct discussion ([Full Transcripts](Full%20Transcripts.md)). All 36 items are done (item 34 on 2026-11-01). Full list and what was built: [Change Request 30102026](Change%20Request%2030102026.md#status-updated-2026-10-31).

---

## Remaining

None. Item 34 was built 2026-11-01:

- On File Movement (Department), every Send (single, batch, and logging an outgoing file) has an optional **Unit** after the office: the units of that office's department, or Other (specify) until the unit is added. A send between two offices of the same department is marked **In-Unit**; to another department, In Transit. The lists show the unit and an In-Unit tag. Units are kept in Configurable Entities > Units (add, edit, switch off, delete one never used), first entered (user, 2026-11-01): Land: File Commissioning, Recommendation, RofO, One Stop Shop (OSS), Land Registration; Deeds: Bill Balance, Legal Search. Not their own `departments` rows, which would appear as departments on user forms and get a Director each.
- **Not yet:** the unit in Quick Search's movement timeline and an In-Unit count on the File Tracking dashboard; say if they are wanted.
- **Add more units** in Configurable Entities > Units as departments give them.

## Decisions to check (from the build)

| Item | What was done | Check |
|---|---|---|
| 8. Classmark | First ranges: .00–.09 → nothing, .10–.24 → ¼, .25–.59 → ½, .60–.99 → 1. | Replace with Survey's own ranges in Configurable Entities > Survey > Classmark when they give them. |
| 5. Land rates | The Land Assessment lines read their rate from Configurable Entities > Survey > Land Rates. | **No rate is set yet.** Add the rates (e.g. per hectare); until then the officer types the rate on each line. |
| 13. LPKN serial | Survey's Computation Files start at LPKN-2026-1. | When Survey gives its last serial, set it in Configurable Entities > Serial Initialization > Computation Files > LPKN. |
| 33. TP No | The table is not queried and shows empty; the 2,100 rows are kept and the refill from TP lookups is stopped. | When the new TP lookup table is brought in, connect it and switch off `TpNumberController::LIST_PENDING`. |
| 35. Returned OP | Its number is used by the next OP generated (the lowest free number first). | None. |
| 21. Passing & Checking | A first version of the form. | Survey to give its own checklist fields. |
| Kano schedule: cash crops by the unit typed | Area in m² uses the per-m² rate, in hectares the sheet's per-hectare rate (user). For some crops the sheet's per-hectare figure is not per-m² × 10,000 (e.g. Pineapple 1,550 per m² but 3,875,000 per ha), so 1 ha and 10,000 m² give different amounts. | None; check the sheet if a figure looks off. |
| Kano schedule: figures read from the scan | The sheets are OCR of a scan; two cash-crop seedling prices read 5.663 / 5.445 were loaded as 5,663 / 5,445 (as in the Economic Trees sheet), and "5-Mar" / "5-Jan" (economic life) as 3-5 / 1-5. Ziziphus's Immature / Seedling per-hectare figures differ between the two sheets; each list keeps its own. | Survey to spot-check the catalogue against the printed schedule. |
| Land Rates list (item 5) | The Monetary calculator now values land by each crop's / tree's cost of production per hectare, so Configurable Entities > Survey > Land Rates is no longer used. | Hidden for now (user, 2026-11-01); the rows are kept. |

## Follow-ups from earlier work

| Item | What was found | Decision / status |
|---|---|---|
| RofO security paper codes (item 25) | 783 codes were marked used but still read "available" (750 Land, 33 SLTR). | **Fixed 2026-11-01** (user): the 780 still held by their recommendation now read "assigned" (backup: storage/app/backups/global_security_paper_codes_status_before_fix_20261101_014044.csv). Land, SLTR and ST single entry now set the status, and a replaced code goes back to "available". **Left as is:** 012821, 012823, 012824, taken 2026-09-09 for Land recommendations that were later deleted; still marked used, so not reissued. |
| Passport face check (item 36) | A passport photo with heavy flash glare is refused as "not clear enough". | **Keep refusing** (user, 2026-11-01). |
| Passport face check (item 36) | Passports uploaded before the check went live were not re-checked. | **Leave the old ones** (user, 2026-11-01); no review list. |
| Survey Commissioning Sheet (item 2) | The heading read SURVEY DEPARTMENT. | **Now DEPARTMENT OF SURVEY** (user, 2026-11-01). |
| Survey Commissioning Sheet (item 2) | No RDKN or LPPL file exists yet, so those two sheets have not been seen on a real file. | The user will commission a file and test. |
| File Status search (item 29, and Cadastral) | Not yet clicked through by a logged-in user on the live site (tested offline). | A quick check by a Survey and a Cadastral user. |

## Answer to give: field mapping app (rec. 298)

The question was whether the field team can trace a farm boundary on a phone: the app closes the polygon, numbers it, works out the area and perimeter, takes the farmer's name, and syncs to the office as **GeoJSON that opens in the GIS software**.

- **Answer:** yes, on mobile, alongside the compensation, field observation and plot showing apps.
- **Condition:** the GeoJSON export is to be opened and checked in QGIS / ArcGIS before handover, given that an earlier JSON export could not be used.
- **Status:** not built yet; to be scoped as its own piece of work.
