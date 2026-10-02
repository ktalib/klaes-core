# KLAES Update Specification --- Deed of Purchase / Land Registration

**Document Type:** Implementation & Business Rules Specification\
**Project:** KLAES\
**Update Area:** PRA, Registration, Land Registration, Legal Search,
File History, Serialization\
**Status:** Consolidated from project discussion and manual-register
reference\
**Date:** 6 September 2026

------------------------------------------------------------------------

## 1. Purpose

This update introduces **Deed of Purchase Registration** as a supported
land-registration instrument and provides a digital workflow for both
historical and new records.

The implementation must distinguish between:

-   **Historical/old records**, which will be backfilled through
    **PRA**.
-   **New registrations**, which will be stored in the existing
    **Registration table** and classified as **Land Registration**.

The update must reuse the existing registration infrastructure where
practical while keeping Land Registration functionally separate from
Digitization on the frontend.

------------------------------------------------------------------------

## 2. Final Architectural Decision

### 2.1 Do Not Create a New Physical Land Registration Table

An earlier proposal considered creating a separate Land Registration
database table and adding it as a fifth source to the multi-source
aggregation model.

**That proposal is superseded.**

The final decision is:

-   Continue using the **existing Registration table** for new records.
-   Add/use a **registration type/category** to distinguish Land
    Registration records from Digitization records.
-   Present Land Registration as a **separate frontend table/view**,
    even though its data comes from the existing Registration table.
-   Historical records continue to be stored through **PRA**.

This avoids unnecessary duplication of registration storage while
maintaining a clear functional separation in the user interface.

------------------------------------------------------------------------

## 3. New Instrument Type

Add the following instrument under **Instrument Type**:

> **Deed of Purchase Registration**

For the new **Other Land / Land Registration** workflow, this should
initially be the **only instrument type displayed**.

The existing registration workflow/interface should be reused as the
basis for this feature. However, the copied implementation must be
adjusted to comply with the Land Registration rules in this
specification.

------------------------------------------------------------------------

## 4. Historical vs. New Record Storage

### 4.1 Historical / Existing Manual Records

Historical Deed of Purchase records that are being digitized/backfilled
must continue through the normal **PRA capture workflow**.

Expected historical flow:

``` text
PRA
  ↓
File History
  ↓
Deed of Purchase Registration
```

These records must not be treated as newly registered instruments merely
because they are being entered into KLAES now.

### 4.2 New Registrations

All new Deed of Purchase registrations must be stored in the existing
**Registration table**.

They must be distinguishable from Digitization registrations using a
registration classification/type.

Conceptually:

``` text
Registration
├── Digitization
└── Land Registration
```

Recommended logical classification:

``` text
type = "digitization"
type = "land_registration"
```

The exact database field/value may follow the project's existing naming
conventions.

------------------------------------------------------------------------

## 5. Frontend Separation

Although Digitization and Land Registration share the same backend
Registration table, they must **not appear as the same operational table
on the frontend**.

Create a separate frontend representation for **Land Registration**.

Conceptually:

``` text
Existing Registration Table
        │
        ├── type = Digitization
        │      └── Digitization frontend/table
        │
        └── type = Land Registration
               └── Land Registration frontend/table
```

The Land Registration frontend can therefore behave as though it has its
own table/module, while actually querying filtered records from the
existing Registration table.

This separation is important because **Land Registration is not
Digitization**.

------------------------------------------------------------------------

## 6. Instrument Interface

When the user selects **Deed of Purchase Registration**:

1.  Open the registration interface based on the existing registration
    implementation.
2.  Apply the Land Registration classification automatically or
    according to the final UI design.
3.  Ensure the record is stored as a Land Registration record rather
    than an ordinary Digitization record.
4.  Preserve applicable existing registration features such as
    serialization and CRS.
5.  Apply the numbering rules defined in this specification.

The existing registration implementation should be copied/reused
carefully so that rules intended only for Lands/Digitization are not
unintentionally inherited.

------------------------------------------------------------------------

## 7. Serialization and CRS

The Deed of Purchase Registration workflow must support the existing
applicable:

-   **Serialization mechanism**
-   **CRS**
-   **Configurable numbering/serialization setup**
-   **Manual serial-number assignment**, where required

Manual assignment should be available as a controlled option where
operationally necessary, consistent with the previously discussed manual
assignment functionality for registration/conversion workflows.

Automatic numbering remains the normal system-controlled process.

------------------------------------------------------------------------

## 8. Official Numbering Fields

The manual register confirms that the three relevant numbering values
are:

1.  **Serial Number**
2.  **Page Number**
3.  **Volume Number**

Throughout the implementation, documentation, database mapping and
frontend, these terms should be used consistently.

------------------------------------------------------------------------

## 9. Current Numbering Baseline

The supplied manual register is the reference for transitioning from
manual registration to digital Land Registration.

The current active:

> **Volume Number = 22**

The digital workflow must **continue from the existing manual
sequence**. It must not arbitrarily restart the numbering from the
beginning when the system goes live.

The exact final Serial Number and Page Number used for production
continuation should be verified against the final manual entry before
initialization if their handwritten values are not sufficiently clear.

------------------------------------------------------------------------

## 10. Volume Number Rule

### 10.1 Current Volume

The current active volume is:

``` text
Volume 22
```

### 10.2 Volume Capacity

Each volume has a capacity of:

``` text
300 registrations
```

When Volume 22 reaches its 300-entry capacity, the next registration
must move to:

``` text
Volume 23
```

The sequence then continues normally:

``` text
22 → 23 → 24 → 25 → ...
```

### 10.3 Volume Must Never Reset with Page/Serial

The Volume Number has its own progression.

It must **not reset to 1** when the Page Number or Serial Number reaches
its maximum.

------------------------------------------------------------------------

## 11. Serial Number Rule

The Serial Number supports a maximum four-digit value:

``` text
1 ... 9999
```

When the current Serial Number reaches:

``` text
9999
```

the next Serial Number becomes:

``` text
1
```

Example:

``` text
9997
9998
9999
1
2
3
...
```

This rollover must be enforced automatically by the system.

------------------------------------------------------------------------

## 12. Page Number Rule

The Page Number follows the same four-digit maximum:

``` text
1 ... 9999
```

When Page Number reaches:

``` text
9999
```

the next Page Number becomes:

``` text
1
```

The system must enforce the rollover automatically.

------------------------------------------------------------------------

## 13. Independence of Volume and Page/Serial Rollover

This is an important business rule.

**Page Number and Serial Number rollover is independent of Volume
Number.**

Reaching `9999` for Page/Serial must not cause the Volume Number to
reset.

For example:

``` text
Before rollover:
Serial Number: 9999
Page Number:   9999
Volume Number: 27

After rollover:
Serial Number: 1
Page Number:   1
Volume Number: 27   ← does not reset because of P/S rollover
```

The Volume Number continues according to its own 300-registration
capacity rule.

Likewise, moving from one volume to another must not be confused with
the independent four-digit limit on Page/Serial numbering.

------------------------------------------------------------------------

## 14. Numbering Constraints

The backend must enforce the numbering rules rather than relying solely
on frontend validation.

At minimum:

-   Serial Number must support values from `1` through `9999`.
-   Page Number must support values from `1` through `9999`.
-   Volume Number must continue sequentially.
-   Volume rollover occurs after the configured 300-entry capacity.
-   Serial/Page rollover occurs after `9999`.
-   Serial/Page rollover must not reset Volume.
-   The production starting point must continue from the manual
    register.
-   Automatic numbering should be protected against accidental duplicate
    assignment.
-   Manual serial assignment, when permitted, must not silently create
    conflicts with existing registration numbers.

Where multiple users can register instruments concurrently, numbering
should be allocated atomically/transactionally to avoid two users
receiving the same sequence.

------------------------------------------------------------------------

## 15. Legal Search Update

**Legal Search must be updated** to understand the Land Registration
classification.

When registration data is aggregated or displayed:

-   Land Registration records must be recognized as Land Registration.
-   They must not be incorrectly represented as Digitization simply
    because both are stored in the Registration table.
-   Historical Deed of Purchase records originating from PRA must
    continue to appear correctly in the relevant file history/search
    result.
-   New Land Registration records must be sourced from the Registration
    table using the appropriate type/filter.

Any existing multi-source aggregation logic should therefore be updated
by **filtering/classifying the existing Registration source**, rather
than introducing a fifth physical registration table.

------------------------------------------------------------------------

## 16. File History

Historical records captured through PRA must continue to feed the normal
File History mechanism.

New Land Registration records should also appear in the appropriate file
history/legal-search representation according to the existing
transaction aggregation rules.

The displayed source/type must make it clear whether the record
represents **Land Registration** rather than Digitization.

------------------------------------------------------------------------

## 17. Conversion File Scope

The discussion identified this Land Registration handling as applicable
to **Conversion files** in the relevant aggregation/search workflow.

Therefore:

-   Apply the new Land Registration source/classification to Conversion
    files where required.
-   Do not unintentionally change unrelated file categories.
-   Existing behavior for other registration/file types must remain
    intact unless explicitly covered by another requirement.

------------------------------------------------------------------------

## 18. Manual-to-Digital Transition

The supplied manual register represents the final/manual numbering
sequence used before the digital workflow takes over.

The implementation must support a controlled transition:

``` text
Existing Manual Register
        ↓
Last manual record: Serial 4019 / Page 4019 / Volume 22
        ↓
Initialize digital numbering
        ↓
First system record: Serial 4020 / Page 4020 / Volume 22
        ↓
Continue numbering in KLAES
```

Once the Land Registration workflow becomes operational, new Deed of
Purchase instruments should be registered through the system rather than
continuing the manual register, subject to operational approval/go-live.

Do not initialize the digital sequence from `1/1/1` unless the business
explicitly instructs otherwise.

------------------------------------------------------------------------

## 19. Data Migration / Backfill Rule

Do not mix historical backfill with live/new registration.

### Historical

``` text
Old/manual record
→ PRA capture
→ File History
```

### New

``` text
New Deed of Purchase
→ Land Registration interface
→ Existing Registration table
→ type = Land Registration
→ File History / Legal Search as applicable
```

This distinction should remain visible in audit/source information where
the existing architecture supports it.

------------------------------------------------------------------------

## 20. Existing Data Aggregation Model

The earlier suggestion to change the multi-source model from four
physical tables to five is cancelled.

The aggregation model should instead recognize the additional **logical
registration category** within the existing Registration source.

In other words:

``` text
DO NOT:
4 physical sources → 5 physical sources solely for Land Registration

DO:
Keep existing Registration source
+
recognize/filter Land Registration type inside that source
```

PRA remains the source for historical records.

------------------------------------------------------------------------

## 21. Validation and Testing Requirements

Before deployment, test at least the following scenarios:

### Instrument and Storage

-   Deed of Purchase Registration appears under the correct Instrument
    Type.
-   Other Land/Land Registration initially displays only the required
    instrument.
-   New record saves to the existing Registration table.
-   New record receives the Land Registration classification.
-   New Land Registration record does not appear incorrectly in the
    Digitization frontend.
-   Historical capture saves through PRA.

### Frontend

-   Digitization frontend filters Digitization records correctly.
-   Land Registration frontend filters Land Registration records
    correctly.
-   Both can use the same physical table without mixing their
    operational views.

### Numbering

-   Continue from the configured manual-register starting point.
-   Serial Number increments correctly.
-   Page Number increments correctly.
-   Volume 22 is recognized as the current volume.
-   Volume moves from 22 to 23 after the configured 300-entry capacity.
-   Serial rollover from `9999 → 1` works.
-   Page rollover from `9999 → 1` works.
-   Volume does not reset when Page/Serial resets.
-   Concurrent registration does not generate duplicate numbers.
-   Manual serial assignment detects/prevents conflicts.

### Search and History

-   Historical PRA Deed of Purchase appears correctly in File History.
-   New Land Registration appears correctly in File History.
-   Legal Search identifies Land Registration correctly.
-   Conversion-file aggregation includes the correct registration
    information.
-   Existing unrelated registration types continue to behave normally.

------------------------------------------------------------------------

## 22. Implementation Checklist

-   [ ] Add **Deed of Purchase Registration** Instrument Type.
-   [ ] Configure the Land Registration/Other Land instrument list to
    initially show only this instrument.
-   [ ] Reuse/adapt the existing registration interface.
-   [ ] Keep historical capture in **PRA**.
-   [ ] Keep new records in the existing **Registration table**.
-   [ ] Add/use a Registration **type/category** for
    `Land Registration`.
-   [ ] Preserve existing `Digitization` classification.
-   [ ] Create a separate filtered **Land Registration frontend/table
    view**.
-   [ ] Ensure the Land Registration frontend is not presented as
    Digitization.
-   [ ] Retain/configure **CRS** where applicable.
-   [ ] Retain serialization functionality.
-   [ ] Support controlled manual serial-number assignment.
-   [ ] Set current Volume baseline to **22**.
-   [ ] Configure **300 registrations per volume**.
-   [ ] Implement automatic Volume rollover (`22 → 23 → ...`).
-   [ ] Support four-digit Serial Number.
-   [ ] Support four-digit Page Number.
-   [ ] Implement Serial rollover (`9999 → 1`).
-   [ ] Implement Page rollover (`9999 → 1`).
-   [ ] Ensure Page/Serial rollover does **not** reset Volume.
-   [ ] Continue digital numbering from the last confirmed manual
    register sequence.
-   [ ] Protect automatic numbering against concurrency/duplicate
    assignment.
-   [ ] Update **File History** integration.
-   [ ] Update **Legal Search**.
-   [ ] Update Conversion-file aggregation logic.
-   [ ] Do **not** create a fifth physical Land Registration table
    solely for this feature.
-   [ ] Regression-test existing Digitization and Registration
    workflows.

------------------------------------------------------------------------

## 23. Final Business Rule Summary

> **Deed of Purchase Registration** is a Land Registration instrument.
> Historical records are backfilled through PRA, while new records use
> the existing Registration table with a Land Registration type. The
> frontend must present Land Registration separately from Digitization
> even though they share the same physical backend table. The current
> Volume is 22, each Volume advances after 300 registrations, and Serial
> Number/Page Number use four-digit sequences that reset from 9999 to 1
> independently of the Volume. Legal Search, File History and
> Conversion-file aggregation must recognize the new Land Registration
> classification.

------------------------------------------------------------------------

## 24. Superseded Requirement

The following earlier proposal must **not** be implemented:

> Create a separate Land Registration physical database table and
> increase the multi-source aggregation model from four tables to five.

It was replaced by the final decision to use the **existing Registration
table plus a Land Registration type/filter**.

------------------------------------------------------------------------

**End of Specification**
