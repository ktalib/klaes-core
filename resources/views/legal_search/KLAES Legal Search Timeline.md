1 | P a g e
KLAES Legal Search Timeline: Developer Implementation Flow
1. Data Model & Entity Extraction
Before sorting, the backend must aggregate all events linked to
the primary `TrackingID` / `FileNo`. The data fetch must
include:
 Primary File Events: Commissioning, Instruments, Updates.
 Linked/Child Files: For Subdivisions, Mergers, Extensions.
 Parent Files: If the current file was born from a
Decommissioned parent.
 KANGIS Linked Files: Files with prefixes `KNML`, `MLKN`,
`KNGP`, `KN`.
 DCIV Linked Files: Investigation files linked to the
primary file.
 Encumbrances: Caveats linked to specific instrument IDs.
2. Event Classification & Weight Assignment
Define an enumeration or constant map for the Timeline Weights.
Every transaction fetched must be mapped to a weight.
```javascript
const TIMELINE_WEIGHTS = {
 FILE_COMMISSIONING: 12,
 TEMP_FILE_COMMISSIONING: 12,
 OCCUPANCY_PERMIT: 11,
 TRANSFER_OF_TITLE_OP: 10,
 RIGHT_OF_OCCUPANCY: 9,
 KANGIS_RECERTIFICATION: 8, // Implicit weight to ensure it
sits above CofO (0)
 CERTIFICATE_OF_OCCUPANCY: 0,
 OTHER_INSTRUMENTS: 0, // Assignments, Mortgages, etc.
 PARCEL_UPDATE: null, // Variable/Chronological
 TITLE_STATUS_UPDATE: null, // Variable/Chronological
 FILE_DECOMMISSIONING: null,// Variable/Chronological
2 | P a g e
 DCIV_COMMISSIONING: null // Variable/Chronological
};
```
3. The Sorting Algorithm (The "Timeline Weighting Method")
The timeline is not purely chronological. It uses a Composite
Sort Key.
Sort Logic:
1. Primary Sort: `Weight` (Descending: `12` down to `0`).
2. Secondary Sort: `Event Timestamp` (Ascending: Oldest to
Newest).
3. Tertiary Sort: `Positional Override Flags` (Boolean/Integer
for special rules).
Note: Events with `null` weights (Parcel Updates, Title Status
Updates, Decommissioning) will be treated as "Floating Events".
They will be sorted purely chronologically but injected into the
timeline without disrupting the weighted hierarchy of the main
instruments.
4. Special Positional Rules & Overrides (Pre-Sort Processing)
Before applying the final sort, the backend must apply these
business rules to adjust the `sort_index` or `parent_id` of
specific events.
Rule A: Temporary File Nesting
 Condition: If a `Temporary File Commissioning` exists for
the main file.
 Action: Force its `sort_index` to be exactly `Main File
Commissioning Index + 1`. It must render directly
underneath the Main File Commissioning line.
Rule B: KANGIS Recertification & CofO Ingestion
 Condition: If the file is a KANGIS file (or ingested by
one) and has both a `KANGIS Recertification` event and a
`KANGIS CofO`.
 Action: The `KANGIS Recertification` timestamp/weight must
be strictly forced to precede the `KANGIS CofO`.
3 | P a g e
 Logic: If `KANGIS_Recert.date >= KANGIS_CofO.date`, adjust
`KANGIS_Recert.date` to `KANGIS_CofO.date - 1 second` for
sorting purposes only.
Rule C: File Decommissioning & Commissioning Pairs (Parcel
Updates)
 Condition: A Parcel Update (e.g., Subdivision) triggers the
Decommissioning of File A and the Commissioning of File B.
 Action:
 1. File A's original Commissioning stays at the top (Weight
12).
 2. The Parcel Update event, File A's Decommissioning, and
File B's Commissioning share the same chronological timestamp.
 3. Render Order at that timestamp: Parcel Update Event
$\rightarrow$ File A Decommissioning $\rightarrow$ File B
Commissioning (Weight 12).
Rule D: Encumbrances (Caveats)
 Condition: A Caveat is placed on the file.
 Action: DO NOT create a standalone timeline row for the
Caveat.
 Logic: Query the `Caveat` table, find the `Instrument_ID`
it was placed against, and append the Caveat details to the
`comments` array of that specific Instrument's timeline
object.
5. JSON Output Structure (API Response)
The backend should return a structured JSON array that the
frontend can render directly.
```json
{
 "fileNo": "RES-2020-1234",
 "trackingID": "TRK-998877",
 "timeline": [
 {
4 | P a g e
 "id": 1,
 "eventType": "FILE_COMMISSIONING",
 "eventName": "File Commissioning",
 "date": "2020-01-15T09:00:00Z",
 "weight": 12,
 "details": "Commissioned by Land Department",
 "comments": []
 },
 {
 "id": 2,
 "eventType": "TEMP_FILE_COMMISSIONING",
 "eventName": "Temporary File Commissioning",
 "date": "2020-01-16T10:00:00Z",
 "weight": 12,
 "details": "Temp File No: TEMP-5544",
 "comments": []
 },
 {
 "id": 3,
 "eventType": "OCCUPANCY_PERMIT",
 "eventName": "Occupancy Permit (OP)",
 "date": "2020-05-20T14:00:00Z",
 "weight": 11,
 "details": "Issued to John Doe",
 "comments": []
 },
 {
5 | P a g e
 "id": 4,
 "eventType": "TRANSFER_OF_TITLE_OP",
 "eventName": "Transfer of Title (OP)",
 "date": "2021-03-10T11:00:00Z",
 "weight": 10,
 "details": "Transferred to Jane Smith",
 "comments": []
 },
 {
 "id": 5,
 "eventType": "PARCEL_UPDATE",
 "eventName": "Subdivision",
 "date": "2022-06-15T09:00:00Z",
 "weight": null,
 "details": "Plot subdivided into 2 units",
 "comments": []
 },
 {
 "id": 6,
 "eventType": "FILE_DECOMMISSIONING",
 "eventName": "File Decommissioning (Parent)",
 "date": "2022-06-15T09:05:00Z",
 "weight": null,
 "details": "Decommissioned due to Subdivision",
 "comments": []
 },
 {
6 | P a g e
 "id": 7,
 "eventType": "RIGHT_OF_OCCUPANCY",
 "eventName": "Right of Occupancy (RofO)",
 "date": "2023-01-20T10:00:00Z",
 "weight": 9,
 "details": "RofO Issued",
 "comments": [
 {
 "type": "CAVEAT",
 "text": "Caveat placed by First Bank PLC on 2023-02-
01"
 }
 ]
 },
 {
 "id": 8,
 "eventType": "KANGIS_RECERTIFICATION",
 "eventName": "KANGIS Recertification",
 "date": "2024-08-10T08:00:00Z",
 "weight": 8,
 "details": "File ingested by KANGIS (KNML-2024-001)",
 "comments": []
 },
 {
 "id": 9,
 "eventType": "CERTIFICATE_OF_OCCUPANCY",
 "eventName": "Certificate of Occupancy (CofO)",
 "date": "2024-08-10T08:05:00Z",
7 | P a g e
 "weight": 0,
 "details": "KANGIS CofO Issued",
 "comments": []
 },
 {
 "id": 10,
 "eventType": "TITLE_STATUS_UPDATE",
 "eventName": "Amendment (Title)",
 "date": "2025-02-14T12:00:00Z",
 "weight": null,
 "details": "Name corrected on Title",
 "comments": []
 }
 ]
}
```
6. Frontend Rendering Guidelines
1. Vertical Timeline UI: Render the array sequentially from top
to bottom.
2. Weight Visuals:
 Weight `12` to `9`: Use prominent, bold icons (e.g.,
Gold/Green milestones).
 Weight `8`: Use a distinct KANGIS-branded icon.
 Weight `0` & `null`: Use standard, lighter timeline dots.
3. Caveat Display: If the `comments` array contains a `CAVEAT`
object, render it as a nested, highlighted warning block inside
the instrument's timeline card, not as a separate line.
8 | P a g e
4. Linked Files: If a `FILE_DECOMMISSIONING` or `PARCEL_UPDATE`
references a new `FileNo`, make that `FileNo` a clickable
hyperlink that opens the Legal Search for the new file.
Temporary Files should automatically display the File
Commissioning of the Main File and ALL the transaction of the
Main File likewise the Main File should also display the
commissioning of the Temporary File and also ALL the
transactions referencing the Temporary File.