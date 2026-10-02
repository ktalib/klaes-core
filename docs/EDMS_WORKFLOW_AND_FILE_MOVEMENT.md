# EDMS — Document Workflow and File Movement

**System:** KLAES Electronic Document Management
**Scope:** how a scanned document enters the system, where it is stored, and every operation that moves it afterwards.
**Written:** 2026-08-18

---

## 1. The short version

A document travels through three storage trees, in one direction:

```
BLIND_SCAN  ──move──▶  SCAN_UPLOAD  ──copy──▶  PAGETYPING
                        (original)   ──copy──▶  ARCHIVE_Doc_WARE  (Doc-WARE)
```

* **SCAN_UPLOAD holds the original.** Nothing downstream may move it out.
* **PAGETYPING and ARCHIVE_Doc_WARE hold copies.** Page typing *copies*; it never moves.
* **BLIND_SCAN is a staging area.** Ingestion *moves* out of it, because a blind scan that has been matched to a file no longer belongs in the unmatched pile.

Every stored path is a *hint*, not an authority. `EdmsDocumentPathResolver` re-derives the real location on read, which is why years of layout changes have not stranded documents.

---

## 2. Storage layout

```
storage/app/public/EDMS/
├── SCAN_UPLOAD/      {Registry}/{Type?}/{FILE NUMBER}/{PAPER}/{file}   ← authoritative original
├── PAGETYPING/       {Registry}/{Type?}/{FILE NUMBER}/{PAPER}/{file}   ← typed copy
├── ARCHIVE_Doc_WARE/ {Registry}/{Type?}/{FILE NUMBER}/{PAPER}/{file}   ← Doc-WARE archive copy
├── BLIND_SCAN/       {Registry}_Raw/{FILE NUMBER}/{PAPER}/{file}       ← pre-ingestion staging
├── THUMBNAILS/       generated previews (PageTypingService)
├── COVERS/           static registry cover images (land/sltr/st) — not per-file
└── UPLOAD/           a SEPARATE tree for Cadastral Index Cards and DCIV generation.
                      Not part of this workflow; do not confuse it with SCAN_UPLOAD.
```

### Path segments

| Segment | Source | Notes |
|---|---|---|
| `{Registry}` | `file_indexings.registry` | Slugged via `registryMap()`; aliases resolve `SLTR` → `SLTR Registry` → `SLTR_Registry`. Land-use ids `1/2/3` all mean Lands Registry. |
| `{Type?}` | `file_indexings.edms_file_type` | **Optional.** The EDMS master folder. Absent = unclassified = legacy layout, directly under the registry. |
| `{FILE NUMBER}` | `file_indexings.file_number` | Windows-illegal characters folded to `-`. May contain spaces (`KNML 1093`). |
| `{PAPER}` | `scannings.paper_size` | Uppercased; defaults to `A4`. |

`BLIND_SCAN` is deliberately **untyped** — the file type is read off the document's cover during ingestion, so it is not known while the file is still in staging.

### The master folders

`App\Services\Edms\EdmsFileType` is the single source of truth. Nineteen types, each a folder under every registry in all three managed trees:

| Key (stored in DB) | Folder on disk | Label |
|---|---|---|
| `regular` | `Regular` | Regular |
| `merger_children` | `Merger/Children` | Merger — Children |
| `merger_new` | `Merger/New_File` | Merger — New File |
| `subdivision_mother` | `Subdivision/Mother` | Subdivision — Mother |
| `subdivision_children` | `Subdivision/Children` | Subdivision — Children |
| `extension_old` | `Extension/Old` | Extension — Old |
| `extension_new` | `Extension/New` | Extension — New |
| `temporary` | `Temporary_File` | Temporary File |
| `change_of_purpose_old` | `Change_of_Purpose/Old` | Change of Purpose — Old |
| `change_of_purpose_new` | `Change_of_Purpose/New` | Change of Purpose — New |
| `regrant` | `Regrant` | Regrant |
| `resettlement` | `Resettlement` | Resettlement |
| `surrender` | `Surrender` | Surrender |
| `separation` | `Separation` | Separation |
| `title_status_litigation` | `Title_Status/Litigation` | Title Status — Litigation |
| `title_status_revocation` | `Title_Status/Revocation` | Title Status — Revocation |
| `title_status_cancellation` | `Title_Status/Cancellation` | Title Status — Cancellation |
| `title_status_withdrawal` | `Title_Status/Withdrawal` | Title Status — Withdrawal |
| `title_status_amendment` | `Title_Status/Amendment` | Title Status — Amendment |

Created by `php artisan edms:create-file-type-folders` (idempotent; supports `--dry-run`, `--registry`, `--tree`). It builds the same skeleton under **every** registry in all three trees, so an operator browsing any of them sees a consistent shape.

> **`NULL` is a supported state, not a defect.** An unclassified file keeps the legacy layout and everything still works. There is deliberately **no backfill** — guessing a file's nature from its number would file documents into the wrong master folder, which is the exact problem the folders exist to fix.

---

## 3. Where the state lives

| Table | Column | Meaning |
|---|---|---|
| `file_indexings` | `registry`, `edms_file_type` | The file's classification — **canonical**. |
| `scannings` | `document_path`, `registry`, `edms_file_type` | The SCAN_UPLOAD original. `document_path` must always point at SCAN_UPLOAD. |
| `pagetypings` | `file_path`, `registry`, `edms_file_type` | The typed copy in PAGETYPING. |
| `blind_scannings` | `document_path`, `status` | Staging register. `pending` → `converted` → `archived`. |
| `scan_reassignment_logs` | `from_path`, `to_path`, `reason`, `reassigned_by` | Audit trail for **every** movement operation. |

**Why registry and type are duplicated onto `scannings` and `pagetypings`:** they are path segments. If a move is interrupted, the child rows still describe where their own file actually sits, so the resolver finds it. The indexing record is updated *last*, on purpose.

> ⚠️ `file_indexings.file_type` already existed and means something else entirely — the **applicant** type (Individual / Corporate / Government). The EDMS concept is `edms_file_type`. Never conflate them.

---

## 4. The workflow

### Stage 0 — Folder pre-creation

`EdmsScanUploadFolderService` creates a file's SCAN_UPLOAD folder as early as possible, so it is waiting before anyone scans into it:

* at MLS commissioning (`MlsFileNoController`)
* at ST commissioning (`CommissionNewSTController`)
* at indexing (`FileIndexingController`), for files never commissioned through KLAES

Best-effort: a storage failure is logged, never thrown. The paper-size subfolder is **not** created here — an empty `A4/` would imply a scan session that never happened.

### Stage 1 — Capture

Two routes in, both ending in a `scannings` row.

**(a) Direct upload** — `POST /scan-uploads/upload`
Operator picks registry → file → **File Type** → uploads. Written straight to `SCAN_UPLOAD/{Registry}/{Type?}/{FILE NUMBER}/`.

**(b) Blind scan** — scanned before anyone knew which file it belonged to.

```
POST /scan-uploads/blind-scan/discover   → manifest of files found in BLIND_SCAN
POST /scan-uploads/blind-scan/transfer   → MOVES them into SCAN_UPLOAD, creates
                                            scannings rows, marks blind_scannings
                                            as `converted`, cleans the empty folder
```

UI flow: *Blind scan → **Load Documents** → pages render in the Preview card → pick File Type → **Confirm Selection** → Start Upload.*

The cover preview matters here: **the instruction that decides a file's type ("Subdivision — Mother", "Extension") is handwritten on the file's cover.** The operator reads it in the dialog rather than guessing from the file number.

Both paths follow the same rule: the operator's pick wins; otherwise the file keeps whatever it is already classified as, so a second batch lands beside the first.

### Stage 2 — Page typing

`PageTypingController` classifies each page and then **copies** it:

```
SCAN_UPLOAD/…/{file}  ──copy──▶  PAGETYPING/…/{definition_code}.{ext}
                      ──copy──▶  ARCHIVE_Doc_WARE/…/{definition_code}.{ext}
```

* `scannings.document_path` is **not** touched — it must keep naming the original.
* `pagetypings.file_path` names the typed copy.
* Both copies inherit the scan's registry **and** master folder, so the three trees stay aligned.

### Stage 3 — Doc-WARE (File Archive)

The QC and retrieval surface, reading `ARCHIVE_Doc_WARE`. QC edits back up the original to `SCAN_UPLOAD/_qc_backups/…{timestamp}.{ext}` before overwriting.

---

## 5. File movement operations

Four distinct operations. They answer different questions, and conflating them is how documents get lost.

| Operation | What changes | Copy or move | Entry point |
|---|---|---|---|
| **Registry transfer** | Registry segment | **Move** | `/edms/registry-transfer/*` |
| **Master folder (file type)** | Type segment | **Move** | `/edms/file-type/*` |
| **Scan reassignment** | Which *file* a scan belongs to | **Move** | `/scan-uploads/reassign` |
| **Page typing** | Nothing — derives copies | **Copy** | Page Typing workspace |

### 5.1 The shared move engine

Registry transfer and master-folder filing are the **same move** — both change one folder segment. They share `EdmsDocumentRelocationService`; the two wrapper services exist only so each interface presents one decision at a time.

```
EdmsDocumentRelocationService          ← the engine: preview() + relocate()
├── EdmsRegistryTransferService        ← varies registry, fixes type
└── EdmsFileTypeTransferService        ← varies type, fixes registry
```

**Guarantees:**

1. **Preview before commit.** `preview()` touches nothing and returns every affected scan and typed page, its resolved source, its destination, whether it is on disk, and whether something already occupies the destination.
2. **Blockers, not surprises.** Already-in-destination, or any destination collision, blocks the move with a plain-language reason.
3. **One DB transaction** across `file_indexings`, `scannings` and `pagetypings`.
4. **Filesystem journal + rollback.** The filesystem is not transactional, so every move and every created file is journalled. If the transaction fails, `undo()` puts each file back and removes what it created.
5. **Doc-WARE kept in step.** The archive copy moves with the typed page — or is minted from it if the file never had one.
6. **Empty folders cleaned up**, but never a tree root, a registry folder, or one of the master folders (those are meant to sit empty).
7. **Audited.** Every moved scan writes a `scan_reassignment_logs` row.

**Order of writes is deliberate:** scans → typed pages → indexing record. The canonical record is updated last, so an interruption leaves children that still describe their own true location.

**Side effect worth knowing:** a move always rewrites `registry` to the resolved display name, even on a type-only move. The destination folder is built from that resolved name, so leaving the column saying `''` or a numeric land-use id would put record and disk out of step.

### 5.2 Scan reassignment

Different in kind: the document was filed under the **wrong file number**. The scan moves to another `file_indexings` record and therefore into the *target's* registry and master folder. Typed pages follow; the Doc-WARE copy is mirrored.

### 5.3 Where operators find these

| Surface | Registry transfer | Master folder |
|---|---|---|
| Scan Upload | — | Header button + per-upload action menu |
| Page Typing | Header + per-file action menu | Header + per-file action menu |
| Doc-WARE | Stats card, file cards, viewer toolbar | Stats card, file cards, viewer toolbar |

Both dialogs also open standalone — the operator searches for a file inside them. The master-folder dialog adds an **"only show files not yet filed"** filter for working the backlog, and shows the file's cover beside the dropdown.

---

## 6. Path resolution — why nothing gets lost

`EdmsDocumentPathResolver::candidates()` is the reason this system survives its own history. A stored path is tried first; if it misses, the resolver walks every layout the system has ever written:

1. The stored path, normalised (legacy prefixes `storage/app/public/`, `public/` stripped)
2. Canonical **typed** layout — SCAN_UPLOAD, PAGETYPING, ARCHIVE
3. Canonical **untyped** layout — the same three (covers pre-master-folder paths, and a file whose type was set before its documents were moved)
4. Legacy archive with no registry and no paper size
5. BLIND_SCAN
6. Layouts missing the paper-size segment
7. Layouts missing the registry segment
8. Last resort: straight out of `public/`

The first candidate that **exists on disk** wins. Consequence: a stored path being wrong is survivable; the file being gone is not.

---

## 7. Operational notes

### Deploying a schema change

The migration ledger is **MySQL** while the tables live on **SQL Server**. The SQL Server `migrations` table is a stale artefact that artisan no longer writes to. Every production change therefore ships as a pair:

```
database/sql/<name>.sql              → run against SQL SERVER
database/sql/<name>_ledger.mysql.sql → run against MYSQL
```

(Or simply `php artisan migrate`, which does both.)

### Adding a registry

1. Add it to `EdmsDocumentPathResolver::registryMap()` (and an alias if the short form is stored anywhere).
2. Re-run `php artisan edms:create-file-type-folders`.

### Adding a file type

Add one entry to `EdmsFileType::TYPES`. The catalogue drives the folder skeleton, validation rules, every dropdown, and the resolver — nothing else needs touching.

### Health checks

```bash
php artisan edms:create-file-type-folders --dry-run   # folder skeleton drift
```

Anything reported as *missing on disk* in a move preview is a pre-existing data problem (a record whose file is genuinely gone), not a fault of the move. Those records are still re-pointed, so they heal if the file reappears.

---

## 8. Invariants

Break these and documents go missing.

1. **SCAN_UPLOAD holds the original.** Page typing and Doc-WARE copy; they never move it.
2. **`scannings.document_path` always names a SCAN_UPLOAD path.** Never a typed copy.
3. **Registry and master folder are path segments.** Changing either on a record without moving the files strands every one of them — always go through the relocation service.
4. **Update children before the parent.** Scans and typed pages first, indexing record last.
5. **`edms_file_type = NULL` is valid** and means the legacy layout. Never coerce it to a default.
6. **Never delete during a move** beyond a verified-empty folder, and never a tree root, registry folder, or master folder.
7. **`file_type` ≠ `edms_file_type`.** Applicant type versus EDMS master folder.

---

## 9. Code map

| Concern | File |
|---|---|
| Path layouts, resolution, fallbacks | `app/Services/Edms/EdmsDocumentPathResolver.php` |
| Master folder catalogue | `app/Services/Edms/EdmsFileType.php` |
| Move engine (transaction, journal, undo) | `app/Services/Edms/EdmsDocumentRelocationService.php` |
| Registry transfer wrapper | `app/Services/Edms/EdmsRegistryTransferService.php` |
| Master folder wrapper | `app/Services/Edms/EdmsFileTypeTransferService.php` |
| Scan reassignment | `app/Services/ScanUploads/ScanReassignmentService.php` |
| Blind scan ingestion | `app/Services/ScanUploads/BlindScanIngestionService.php` |
| Folder pre-creation | `app/Services/EdmsScanUploadFolderService.php` |
| Folder skeleton command | `app/Console/Commands/CreateEdmsFileTypeFolders.php` |
| Capture endpoints | `app/Http/Controllers/ScanUploadsController.php` |
| Typing + derived copies | `app/Http/Controllers/PageTypingController.php` |
| Doc-WARE / archive | `app/Http/Controllers/FilearchiveController.php` |
| Move endpoints | `app/Http/Controllers/EdmsRegistryTransferController.php`, `app/Http/Controllers/EdmsFileTypeTransferController.php` |
| Shared move dialogs | `resources/views/components/edms/registry-transfer-modal.blade.php`, `file-type-transfer-modal.blade.php` |

---

## 10. Known gaps

* **No bulk classification.** Filing the backlog into master folders is one file at a time. A console command for bulk moves would be a natural next step.
* **Legacy QC writes.** `PageTypingQCController` still writes the old `ARCHIVE_Doc_WARE/{file_number}/{file}` layout (no registry, no type). The resolver covers it, but those paths are not standardised on write.
* **`scannings.definition` is `nvarchar`** and holds file numbers on some rows despite looking like a page ordinal. Ordering by it numerically fails outright on SQL Server — order by `display_order` then `id`.
* **`EDMS/UPLOAD`** is a parallel tree (Cadastral Index Cards, DCIV) outside this workflow and outside the resolver.
