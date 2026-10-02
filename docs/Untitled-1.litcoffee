Step 2: Capture Existing File – Conditions & Data Flow

Overview:
Captures Occupancy Permit (OP) data and returns allottee information to the "Capture Existing File" card.
Behavior depends on whether the user modifies the allottee name.

---

Scenario A: No Name Change (User Keeps Allottee Name)
Condition: User does not modify the returned allottee name.
Example: Allottee = "ALI Musa" → User keeps "ALI Musa"

Data returned to Step 2:
  temp_fileno: TEMP-11322
  mlsFNo: RES-1981-45
  Grantor: Kano State Government
  party_1: Kano State Government
  Grantee: ALI Musa
  party_2: ALI Musa
  instrument_type: Occupancy Permit (OP)
  transaction_type: Occupancy Permit (OP)
  prop_id: 124

Action: Proceed to Step 3.

---

Scenario B: Name Change (User Modifies Allottee Name)
Condition: User changes the allottee name to a different value.
Example: Allottee = "ALI Musa" → User changes to "Habiba Bello"

Data returned to Step 2:
  temp_fileno: TEMP-11322
  mlsFNo: RES-1981-45
  Grantor: ALI Musa
  party_1: ALI Musa
  Grantee: Habiba Bello
  party_2: Habiba Bello
  instrument_type: Transfer Of Title (OP)
  transaction_type: Transfer Of Title (OP)
  prop_id: 124
Action: Proceed to Step 3.

---
Step 3: Maintains same flow for both scenarios.