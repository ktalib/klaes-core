# FINAL IMPLEMENTATION PROMPT
## ST C of O Workflow Update — KLAES

We are updating the existing **ST C of O** functionality in KLAES to include the complete C of O workflow.

IMPORTANT:
We are **not building a completely new C of O workflow from scratch**.

The existing **ALAES C of O workflow/files should be used as the implementation reference and reused/adapted for KLAES** wherever possible.

The goal is to carefully bring the relevant ALAES C of O components, logic, templates, workflow handling, and reusable files into KLAES without breaking the existing KLAES implementation.

Do not blindly copy files.
Inspect the existing KLAES structure first, then reuse/adapt the ALAES implementation to fit KLAES naming, routes, controllers, database structure, permissions, modules, and existing business logic.

Do NOT change existing KLAES file numbers or existing production data structures unnecessarily.

---

# 1. ST C of O WORKFLOW

There are two distinct parts, and they must not be built the same way.

## 1a. Pre-conditions — DISPLAY ONLY (chips)

By the time an ST record reaches this screen, these have normally already happened
elsewhere. They are **status chips**, not workflow steps. Show them; do not make the user
perform them here, and do not gate on them:

- Programme checked
- C of O authorisation checked
- RofO generated
- C of O registered (Deeds)

## 1b. The workflow proper

**The workflow proper begins at the generation of the Front Page.** Only these are stages:

1. **Front Page** — the existing `/programmes/certificates` interface. It IS the front page.
2. **C of O** — the complete certificate: **Front Page (front) + TDP (back)**.

TDP comes from KANGIS / GIS and is the **back page**. The front page is produced in
KLAES / ST. Together they are one two-sided document.

The system must show, per ST C of O record, which of the two stages is done and whether the
TDP is available yet.

### Ordering

Stage ordering is **not** strictly enforced. Specifically, the pre-conditions above and the
TDP are not required to happen before the front page — a front page may exist while its TDP
is still pending. Only the final combined C of O requires both sides to be present.

---

# 2. RofO — PRE-CONDITION, NOT A STAGE

RofO generation already exists and normally happens before the record reaches this screen.

Surface it as a chip (`RofO generated`) with a link to view it, and keep the existing RofO
linked to the C of O record. Do not rebuild RofO generation here, and do not block the front
page on it.

The sidebar keeps RofO as its own item under Certificate, beside the C of O Workflow group.

---

# 3. C OF O REGISTRATION (DEEDS) — PRE-CONDITION, NOT A STAGE

Registration under KLAES / Deeds is likewise expected to be done already, and is shown as a
chip (`C of O registered`).

Where it has not happened, it may be performed automatically rather than by sending the ST
user through the individual registration interface — but it is a background concern of this
screen, not a step the user walks through, and the front page is not gated on it.

If auto-registration is implemented it should:

- create/update the appropriate C of O registration record;
- link it to the ST record/property/file;
- preserve the relevant property ID/file ID;
- avoid duplicate registration;
- expose registration status to the ST workflow as the chip above.

---

# 4. GENERATION OF FRONT PAGE / WHITE COPY — KLAES / ST

The existing C of O interface **is** the Front Page interface. Concretely:

    route   programmes.certificates      ->  /programmes/certificates
    action  CofoController::CertificateOfOccupancy()

Relabel it as **Front Page**. Do not build a second front-page module — this is the "one
front page" that was asked for.

This stage should generate the C of O Front Page using data already available from:

- ST record
- property details
- applicant/allottee details
- RofO
- C of O registration
- survey information where available
- existing KLAES property/file data

Output:

**C of O Front Page — White Copy**

This remains the review/working version before the final original C of O is produced.

The generated document should remain attached to the ST C of O workflow record.

---

# 5. TDP GENERATION — KANGIS / GIS

The next stage belongs to:

**KANGIS / GIS**

GIS should generate the required **TDP** for the same ST property.

The system must maintain the link between:

- ST C of O record
- property/file
- survey/GIS record
- generated TDP

The TDP stage should have clear statuses such as:

- Pending TDP
- In Progress
- TDP Generated
- Returned/Requires Correction, if supported

Once the TDP is generated, it should be made available to the KLAES/ST workflow for the merging stage.

Do not require the ST user to manually recreate GIS/TDP information already available in KANGIS.

---

# 6. MERGE C OF O FRONT PAGE WITH TDP — KLAES / ST

After both documents are available:

- C of O Front Page / White Copy
- TDP

KLAES/ST combines them into **one two-sided certificate**:

    FRONT  =  C of O Front Page   (KLAES / ST,  programmes.certificates)
    BACK   =  TDP                 (KANGIS / GIS)

This is a front and a back of the same document, not two files kept side by side. The
combined output is what the sidebar calls **C of O**.

Reuse the document matching/merging workflow already implemented in the ALAES C of O workflow where possible.

The matching must use the same property/file/ST record so that a TDP cannot accidentally be attached to the wrong C of O.

The interface should clearly display both sides before confirmation:

### C of O Information
- File Number
- Property ID
- Applicant/Holder
- Plot details
- C of O registration particulars
- Front Page preview/status

### TDP Information
- Property ID
- Plot Number
- Plan Number
- Survey reference
- TDP preview/status

Provide an action such as:

**Merge / Match TDP to C of O**

Before confirming, validate that the records correspond to the same property.

Once merged, mark the workflow stage:

**Front Page + TDP Merged**

---

# 7. C OF O ORIGINAL — KLAES / ST

After successful merging, allow generation of the final:

**C of O — Original**

This should use the complete approved document set.

The Original C of O should only become available when all required preceding stages are completed.

At minimum confirm:

- RofO generated
- C of O registered
- Front Page / White Copy generated
- TDP generated
- Front Page and TDP matched/merged

Then enable:

**Generate C of O Original**

The final Original should be stored/linked to the same ST C of O workflow.

The system should retain the White Copy separately and should not overwrite it.

---

# 8. WORKFLOW STATUS / PROGRESS DISPLAY

Add a clear workflow progress component to the ST C of O interface.

Example:

[✓] RofO Generated
    ↓
[✓] C of O Auto-Registered
    ↓
[✓] Front Page / White Copy Generated
    ↓
[✓] TDP Generated
    ↓
[✓] Front Page + TDP Merged
    ↓
[✓] C of O Original Generated

Pending stages should display clearly.

Example:

[✓] RofO
[✓] Auto Registration
[✓] Front Page
[●] Awaiting TDP
[ ] Merge
[ ] Original

Do NOT blanket-gate the stages. The pre-conditions and the TDP are explicitly not required
to come first — a front page may be generated while its TDP is still pending, and that is a
normal state, not an error.

The single real constraint: the combined **C of O** cannot be produced until both the front
page and the TDP exist, because it has no back without the TDP.

---

# 9. SIDEBAR

The screenshot that accompanied this request describes the **sidebar**, not an action menu.
An earlier draft of this document invented a ten-item action menu; there was no such
instruction, and it has been removed.

Under **Sectional Titling -> Certificate**, the navigation becomes:

    i.  Final Conveyance
    j.  Certificate
            i.   RofO
            ii.  CofO Workflow
                     1. Front Page
                     2. CofO
    k.  Digital Archive

Where:

- **RofO** keeps its existing destination (`programmes.rofo`).
- **Front Page** points at the existing `programmes.certificates`.
- **CofO** is the combined front + back certificate.

`CofO` previously sat flat beside `RofO` and pointed at `programmes.certificates`. It moves
down one level into the new **CofO Workflow** group, and the route it used to own now
belongs to **Front Page**.

File: `resources/views/admin/menu/partials/modules/sectional_titling.blade.php`

---

# 10. REMOVE/DISABLE INDIVIDUAL ST C OF O REGISTRATION

Under the existing normal:

**C of O Registration**

the variant tabs are, verbatim from
`resources/views/instruments/partials/register_modal.blade.php`:

    ['regular', 'Regular', 'Certificate of Occupancy', 'red'],
    ['sltr',    'SLTR',    'SLTR Certificate of Occupancy', 'green'],
    ['st',      'ST',      'ST Certificate of Occupancy',   'blue'],     <- remove

(The middle one is **SLTR**. An earlier draft wrote "FLTR", which was a mishearing.)

Disable/remove the **ST** option from this normal individual-registration path.

Reason:

ST C of O must now be processed through the dedicated ST C of O workflow.

Users must NOT manually register ST C of O one-by-one through the normal registration interface.

Do not delete historical ST records.

Only prevent new ST C of O registration through that manual route.

---

# 11. REUSE ALAES C OF O WORKFLOW FILES

UNCONFIRMED. The recording this document was written from never mentions ALAES; the
instruction to reuse it came from the drafting tool, not from the brief. ALAES does exist in
this codebase (`AlaesController`, `AlaesPortalController`, `resources/views/alaes_portal/`),
so it may be genuine context from an earlier conversation — but confirm before treating any
of this section as a requirement.

Read what follows as "if ALAES has a reusable C of O implementation, prefer reusing it",
not as a mandate.

Inspect the existing **ALAES C of O workflow** and identify reusable components, including where applicable:

- controllers
- services
- actions
- Blade templates
- PDF/document templates
- frontend components
- routes
- validation rules
- document generation logic
- C of O registration logic
- white copy generation
- TDP matching/merging
- workflow status handling
- permissions
- models
- helper classes
- JavaScript/AJAX components

Reuse or adapt those components for KLAES rather than duplicating the same logic.

However:

### DO NOT simply replace KLAES files with ALAES files.

Carefully compare both implementations first.

Preserve KLAES-specific:

- database relationships
- existing routes
- naming conventions
- property IDs
- file numbers
- Deeds Registration logic
- ST records
- KANGIS/GIS integration
- permissions
- existing workflows

Where both systems use the same functionality, extract/reuse shared logic where practical.

---

# 12. DATABASE SAFETY

Do not modify or regenerate existing KLAES file numbers.

Do not perform destructive schema/data changes.

Before adding a migration, inspect whether an existing table/column can support the workflow.

Prefer extending the existing C of O/ST workflow structure.

If new columns are necessary, use nullable/non-destructive migrations and preserve all existing records.

Possible workflow fields may include equivalents of:

- rofo_generated_at
- cofo_registered_at
- front_page_generated_at
- tdp_generated_at
- tdp_matched_at
- original_generated_at
- workflow_status

But DO NOT automatically create these exact columns without first examining the current database/models.

Reuse existing fields/tables wherever possible.

---

# 13. FILE/DOCUMENT STORAGE

Reuse the current KLAES/ALAES document storage mechanism.

Each generated document must remain associated with the same ST/property/file.

Documents should be distinguishable by type, for example:

- RofO
- C of O White Copy / Front Page
- TDP
- C of O Original

Do not overwrite one document type with another.

Where document versioning already exists, preserve it.

---

# 14. ACCESS / RESPONSIBILITY

Respect module responsibilities:

### KLAES / ST
Responsible for:
- ST workflow
- Front Page generation
- matching/merging
- Original C of O

### KLAES / Deeds
Responsible for:
- C of O auto-registration

### KANGIS / GIS
Responsible for:
- TDP generation

The workflow may move between modules, but it must remain one connected ST C of O process.

---

# 15. AUDIT TRAIL

Every important action should retain an audit trail using the existing KLAES audit mechanism where available.

Track:

- action
- record
- user
- date/time
- previous stage
- new stage

Especially for:

- RofO generation
- auto-registration
- Front Page generation
- TDP matching
- C of O Original generation

---

# 16. IMPLEMENTATION APPROACH

Before making changes:

1. Inspect the current KLAES ST implementation.
2. Locate the ALAES C of O workflow.
3. Compare corresponding files/classes/routes/tables.
4. Identify what can safely be reused.
5. Identify KLAES-specific differences.
6. Prepare the smallest safe implementation.
7. Implement incrementally.
8. Test every workflow stage.
9. Confirm existing KLAES C of O functionality still works.
10. Confirm existing file numbers and historical records remain unchanged.

Do not make broad refactors unrelated to this task.

Do not rename existing tables, routes, or core models unless absolutely necessary.

---

# 17. EXPECTED FINAL WORKFLOW

Already done elsewhere, shown as chips:

    Programme checked  .  C of O authorised  .  RofO generated  .  C of O registered

The workflow proper:

    ST Record
        |
    Generate C of O FRONT PAGE          KLAES / ST   (programmes.certificates)
        |
        |   (TDP may be produced before, during or after the front page)
        |
    Generate TDP  =  BACK PAGE          KANGIS / GIS
        |
    Combine FRONT + BACK  ->  C of O    KLAES / ST
        |
    Completed

---

# FINAL REQUIREMENT

Implement this as an extension of the existing KLAES ST workflow while reusing the proven ALAES C of O workflow components.

The implementation must feel like one continuous KLAES workflow, not an ALAES module pasted into KLAES.

Preserve existing KLAES data, file numbers, business rules, and working functionality.

Before coding any major replacement, inspect the corresponding KLAES and ALAES files and choose the safest reuse/adaptation approach.
```

The key architecture here is **ALAES provides the reusable C of O implementation**, while **KLAES remains the source of truth for its existing ST records, Deeds registration, file/property relationships, permissions, and database structure**. That should keep the agent from copying the ALAES workflow wholesale and accidentally breaking KLAES.

---

# OPEN QUESTIONS

1. **TSI.** Asked about in the brief ("what is called TSI?") and never answered. It appears
   nowhere in this codebase. Clarify before anyone assumes it is a synonym for TDP.

2. **TDP has almost no footprint in KLAES.** It appears only in `CertificationController` and
   some pagetyping JavaScript. Section 5 is therefore mostly new work, not reuse — do not plan
   it as an integration with something that already exists.

3. **ALAES reuse** — see section 11.

---

# BUILT — 2026-09-27

The C of O Workflow is wired end to end, with the TDP arriving as an uploaded document.

| | |
| --- | --- |
| `st_cofo_tdp` (sqlsrv) | the back page, one active row per unit; supersedes rather than deletes |
| `StCofoTdpController` | the CofO screen, TDP attach, and the complete print |
| `programmes.cofo_complete` | Certificate -> CofO Workflow -> **CofO** |
| `programmes.cofo_complete.tdp.store` | attach/replace the TDP |
| `programmes.cofo_complete.print` | front page + TDP, one two-sided document |
| `programmes/cofo_complete.blade.php` | every generated front page beside its TDP state |
| `programmes/print_cofo_complete.blade.php` | page 1 front, page 2 TDP |

**The front page template was split, not duplicated.** `print_cofo_front_page.blade.php` now
includes `partials/_cofo_print_styles` and `partials/_cofo_front_page_body`, and the complete
certificate includes the same two. The front of the complete certificate therefore cannot
drift from the front page printed on its own. The split was verified output-identical against
the pre-refactor template.

Permissions: all C of O routes map to **ST - Certificate**, above the broad `programmes.*`
entry (first match wins, so Physical Planning would otherwise have swallowed them).
`programmes.certificates` carries an explicit `view` override — the action inference reads
`*certificate*` as a print.

## Still open

- **TDP intake is upload, not generation.** KANGIS/GIS has no TDP generator in this codebase;
  nothing in ST/GIS produced one. Upload makes the workflow real now and does not block
  generation replacing it later — a generator would write the same `st_cofo_tdp` row.
- **A PDF TDP is embedded, not rasterised.** Browsers print embedded PDFs inconsistently, so
  the screen offers a direct link as the reliable route. An image TDP prints cleanly.
- `st_cofo` held **one row** when this was built, so the screens are correct but lightly
  exercised against real volume.

## Provenance

Sections 1, 2, 3, 4, 6, 8, 9, 10 and 17 were corrected on 2026-09-27 against the original
voice brief. The earlier draft had: the workflow starting at RofO with all six steps gated;
a ten-item action menu that was never requested (the screenshot was the sidebar); "FLTR" for
SLTR; and the front/back nature of the certificate left vague.
