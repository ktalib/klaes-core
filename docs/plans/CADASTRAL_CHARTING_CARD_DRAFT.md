# Charting Card: draft for client review (2026-10-10)

Step 8 of `CADASTRAL_DEPT_WORKFLOW.md`. Visual version: `CADASTRAL_CHARTING_CARD_DRAFT.html` (also a private artifact). Nothing is built until the client agrees the fields.

Source: the client walk-through ("small form, card: record charted yes, falls on existing plots no/yes, description, coordinates... design the card and send to me so I can edit") and the fields the old Cadastral Module's charting screen already records (`cadastral_charts`, `cadastral_chart_coordinates`, `ChartConflictScanner`).

## Where the card is used

| Situation | Desk | Extra on the card |
|---|---|---|
| Conversion | AD Cad Report | none (single charting) |
| Direct, 1st charting | AD Cad Info (after the layout check) | none |
| Direct, 2nd charting | AD Cad Report | 1st card shown read only; "Agrees with the 1st charting?" |
| Verification | AD Cad Info, then AD Cad Report | "Charting matches the record?" instead of charted / existing plot |

Saving the card ticks the step's charting task (charted / chart_first / chart_second / charting_checked).

## Proposed fields

| S/N | Field | Source | Rule |
|---|---|---|---|
| 1 | Charting No. | automatic | CH-YYYY-NNNNN on save |
| 2 | File No., FMC No., File Title | automatic | from the case, locked |
| 3 | Plot No., District, LGA, Location | automatic | from the file records, this order, locked |
| 4 | Record charted? Yes/No | brief | required; No asks why (list + one Other with specify) |
| 5 | Falls on an existing plot? Yes/No | brief | required; KLAES suggests matches by plot/block/layout (old ChartConflictScanner); Yes asks which file(s) (global selector) and fully/partly |
| 6 | Description | brief | free text |
| 7 | Coordinates (beacons) | brief | optional rows: beacon, easting, northing, remarks |
| 8 | Area (m²) | old screen | computed from 3+ beacons |
| 9 | Sheet (Metric/Imperial), Sheet No. | old screen | |
| 10 | Layout name/No.; Approved plan/TP No. | old screen | |
| 11 | Agrees with the 1st charting? | suggested | Direct 2nd only; No asks what differs |
| 12 | Charting matches the record? | suggested | Verification only; No asks what does not match |
| 13 | Charted by, date/time | automatic | signed-in officer, on save |
| 14 | Remarks | suggested | free text |

## Charting table

Columns: S/N, Charting No., File No., Plot No., District, LGA, Location, Stage, Charted, On existing plot, By, Date, Actions (Open card, Print card, Open file, Go to desk). Direct: 1st-charting table under Cadastral Information, 2nd under Cadastral Reports; Conversion once under Reports. Corrections make a new version and keep the old (as the old screen does).

## Questions for the client

1. Which fields are needed; anything missing from today's paper card?
2. Falls on an existing plot: stop, back to D Cad, or carry on with a note?
3. Not charted: can the file still move on, or must it go back?
4. Are beacon coordinates on the card today; UTM Zone 32N, Minna datum?
5. Sheet references: metric index, imperial sheet, or both?
6. Direct 2nd charting: confirm the 1st, or re-record everything?
7. Charting number format: CH-2026-00001, or an existing scheme?
8. Should the card print for the file jacket?
