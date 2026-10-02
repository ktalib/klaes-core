 
---

# ✅  LOOK UP MAPPING

### 1️⃣ **Property Location**

* **ONLY** comes from **registry tables**:

  * `CofO_staging.location`
  * `pra.location`
  * `deed_registrations` → ❌ (not available)
* **NEVER** from `file_indexings`

---

### 2️⃣ **Applicant / Solicitor**

* **ONLY** comes from:

  * `file_indexings.file_title`
* **NEVER** from registry tables

---

# 🧩 FINAL FIELD MAPPING (ONLY NECESSARY FIELDS)

| Form Field            | Source Table       | Source Column                      |
| --------------------- | ------------------ | ---------------------------------- |
| file_number           | file_indexings     | file_number                        |
| applicant / solicitor | file_indexings     | file_title                         |
| party_1               | CofO / pra / deeds | Grantor / party_1 / grantor        |
| party_2               | CofO / pra / deeds | Grantee / party_2 / grantee        |
| instrument_type       | CofO / pra / deeds | instrument_type                    |
| property_location     | CofO / pra         | location                           |
| serial_no             | CofO / pra / deeds | serialNo / serial_no               |
| page_no               | CofO / pra / deeds | pageNo / page_no                   |
| volume_no             | CofO / pra / deeds | volumeNo / volume_no               |
| registration_number   | CofO / pra / deeds | regNo / registration_number        |
| transaction_date      | CofO / pra / deeds | transaction_date / instrument_date |

---

# ✅ LOOKUP FLOW (END-TO-END)

## Step 1: User selects **File Number**

```sql
SELECT
    file_number,
    file_title AS applicant_solicitor
FROM file_indexings
WHERE file_number = @FileNumber;
```

✔ Populates **Applicant / Solicitor**

---

## Step 2: Lookup registry records (Property data ONLY)

```sql id="x2n4b7"
SELECT
    r.source_table,
    r.party_1,
    r.party_2,
    r.instrument_type,
    r.location AS property_location,
    r.serial_no,
    r.page_no,
    r.volume_no,
    r.registration_number,
    r.sort_date
FROM (
    -- CofO
    SELECT
        'CofO_staging' AS source_table,
        Grantor  AS party_1,
        Grantee  AS party_2,
        instrument_type,
        location,
        serialNo AS serial_no,
        pageNo   AS page_no,
        volumeNo AS volume_no,
        regNo    AS registration_number,
        COALESCE(transaction_date, cofo_date) AS sort_date
    FROM CofO_staging
    WHERE np_fileno = @FileNumber

    UNION ALL

    -- PRA
    SELECT
        'pra',
        party_1,
        party_2,
        instrument_type,
        location,
        serialNo,
        pageNo,
        volumeNo,
        regNo,
        COALESCE(transaction_date, deeds_date)
    FROM pra
    WHERE fileno = @FileNumber

    UNION ALL

    -- Deeds
    SELECT
        'deed_registrations',
        grantor,
        grantee,
        instrument_type,
        NULL,
        serial_no,
        page_no,
        volume_no,
        registration_number,
        instrument_date
    FROM deed_registrations
    WHERE fileno = @FileNumber
) r
ORDER BY r.sort_date DESC;
```

---

# 🖥️ UI BEHAVIOUR (AS REQUIRED)

### 🔹 If **ONE** record returned

* Auto-populate:

  * party 1
  * party 2
  * instrument type
  * **property location**
  * serial / page / volume
  * registration number

### 🔹 If **MULTIPLE** records returned

* Display all
* Sorted by **most recent date first**
* Checkbox per row
* On select:

  * Populate form
  * Disable & grey-out other checkboxes

 

 