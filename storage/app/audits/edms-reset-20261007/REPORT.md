# EDMS reset review — 7 October 2026

**Database reset completed and verified on 7 October 2026 at 18:40 Africa/Lagos. Upload-folder work is deferred; all upload folders remain untouched and maintenance mode is off.** The audit tables later in this report are historical snapshots; the committed results immediately below are authoritative.

## Execution status

**Subsequent passport typing completed at 18:45:47 Africa/Lagos:** created 2,278 Image / Passport page-typing entries (Front Cover, serial 0a), attributed individually to each scan's valid `uploaded_by` user. All 2,278 classifications and user links passed a separate database verification. Original passport images remain unchanged; typed/archive copies were SHA-256 checked. QC remains pending. Twenty-three passports remain untyped because their file-indexing records are missing; no speculative relinking was performed. Evidence and exact exception IDs: `passport-typing/plan.json`, `created-pages.jsonl`, `result.json`, and `verification.json` in the backup destination. This adds 2,278 pages to the reset's retained 40, and affected files can now display Typed as requested. Maintenance stayed off; no folder archive/rename occurred.

### Committed database-only result

| Item | Final result |
|---|---:|
| Scanning rows deleted | 268,613 |
| Page-typing rows deleted | 218,249 |
| Protected scans retained | 2,374 |
| Protected typed pages retained | 40 |
| Blind-scanning rows retained | 1,733,212 |
| Blind statuses changed from converted to pending | 7,227 |
| File-indexing records reset to Indexed / indexed | 175,729 |

All blind rows now show pending. All file-indexing rows have the requested stored statuses, and a sample of files with retained scans also displays Indexed through the application model. Blind-scanning and indexing non-status values were compared before and after and remained unchanged. Blind/indexing totals increased from the early audit while the site was live; the transaction validated the actual current rows. Protected scanning/page-typing values were unchanged. All 19 PageType definitions and seven mother-recommendation upload rows remain. Deletion also removed only candidate-dependent history: 85 scan reassignment logs, five page-typing tool logs, and one scan-image version.

The fresh database backup `klas_verified_20261007_182646.bak` completed and passed SQL Server checksum/RESTORE VERIFYONLY checks. At the user's instruction, the additional test restore was interrupted to proceed immediately; it is **not** claimed as complete. The earlier backup had already passed a full test restore and an independent local-copy SHA-256 comparison. Before-images of all affected tables are saved in the backup destination's `execution` directory. Transaction committed at 18:39:09; independent post-reset verification completed at 18:40:01. Evidence: `cleanup-committed.json`, `post-reset-verification.json`, and `execution-state.json` at the backup destination.

**No folder rename, move, archive switch, or upload-file deletion occurred. `EDMS/BLIND_SCAN` remains at its original path.** Folder handling requires the next user instruction; the stopped folder coordinators must not be resumed against this completed database transaction.

**Latest user instruction supersedes the folder-switch stage: database first, no maintenance mode, leave every upload folder untouched, then report before doing folders.** The folder-archive coordinator is stopped. A fresh database backup/checksum/test restore and audit are running for the database-only reset. The existing cleanup transaction locks its affected tables, compares the final ID/path manifests, exports before-images, and validates retained rows/status-only changes before committing. If live uploads invalidate the manifest it aborts rather than deleting a stale selection. `ready-for-cleanup.json` explicitly records `database_only_keep_all_files`; no folder-copy completion is claimed or required for this phase because no upload files are changed. All folder work remains deferred.

**Revised strategy authorized by the user:** stop the 4.76 TB folder copy and archive only `EDMS/SCAN_UPLOAD`, `EDMS/PAGETYPING`, and `EDMS/ARCHIVE_Doc_WARE` by same-volume directory moves on each applicable storage root. Original folders go to `EDMS_RESET_ARCHIVE_20261007` under the same public-storage root. Fresh active folders receive hash-verified copies of all protected references, including blind-scanning pointers. `EDMS/BLIND_SCAN`, recommendations, parcel uploads, passports outside those three folders, and every unrelated module folder remain in place. The partial folder copy is retained but is **not** a complete backup. Folder archives provide rollback on the same disk; they do not protect against disk failure.

The initial SQL backup now has a verified independent local copy. Before the reset, a fresh server-side COPY_ONLY/CHECKSUM backup is verified and fully test-restored. To avoid another lengthy network transfer, that fresh backup remains on the database server. The archive coordinator enables maintenance, refreshes the audit and cross-module reference checks, switches the three folders, restores and hashes protected files, and executes the transactionally verified cleanup. If the database transaction fails, it restores the original folders before reopening the application. Progress: `execution-state.json`; worker: `continue-archive-cleanup.ps1`. The earlier full-copy coordinator has been stopped.

The current rollback rehearsal passed: 268,613 scans and 218,249 pages were removed in the restored database, 7,227 blind statuses reset, and 175,721 file-indexing statuses reset. All 1,390,504 blind rows and all protected scan/page values survived unchanged apart from the explicitly permitted status fields. Rehearsal changes were rolled back. Final live counts may differ due to uploads made before maintenance.

Backup destination: `F:\KlaesBackups\edms-reset-20261007-170622`. The SQL Server backup has passed checksum verification and a full isolated test restore. Its initial local copy passed SHA-256 comparison. The C: upload folder copy is complete. The F: upload folder contains approximately **4.76 TB across 7,483,207 files**; its full copy was stopped following the authorized strategy change.

The original full-copy/quarantine procedure below is superseded by the authorized directory-archive procedure above. No full-copy completion is asserted. A verification failure stops cleanup. Live machine-readable progress is in `execution-state.json` at the backup destination.

All file-indexing rows will retain their IDs and business data; only `status = Indexed` and `workflow_status = indexed` are reset. The displayed status will also restart at Indexed while retained documents remain accessible. New scanning/page typing will advance progress normally.

Every blind-scanning row and file pointer is retained. Converted rows eligible for reprocessing are identified using indexed-file lineage because the legacy conversion paths no longer match current scan paths. Only their status changes to pending; all other values are checked before and after. Wholly preserved files are excluded from requeueing.

New commissioning/OSS passport uploads now create their Passport page-typing entry automatically. The legacy local-folder fallback resolves all seven audited mother-recommendation uploads, including Extant RofO uploads. Nineteen previously missing ST path references remain a pre-existing issue; their rows are preserved.

## Confirmed blind-scanning rule and backup/delete plan

User clarification: **retain every `blind_scannings` row and every raw blind-scan file. Change only the `status` column for affected records.** For the restart, the proposed status transition is `converted` to `pending` for rows whose derived scans are being removed. Already-pending rows need no update; rows supporting retained scans must not be requeued automatically. Match the affected IDs before execution. Preserve all other columns, including IDs, document paths, file-indexing links, and conversion timestamps.

| Item | Backup | Planned reset action |
|---|---|---|
| Entire application database | Fresh full backup, checksum, and restore verification; separately export affected rows and history | Targeted changes only after backup verification |
| `scannings` | Full rows, schema, and dependent history | Delete approved candidate IDs; retain ST, passports, parcel uploads, and protected references |
| `pagetypings` | Full rows, schema, and dependent history | Delete approved candidate IDs; retain pages belonging to protected uploads, including newly auto-typed passports |
| `blind_scannings` | Full table plus a before/after status manifest | **No deletion. Status-only update of affected converted rows to pending** |
| `file_indexings` | Full table and before/after comparison excluding the two status fields | **Retain all rows; reset every status to Indexed and workflow_status to indexed** |
| `F:\storage\app\public` | Full recursive folder backup, including every EDMS tree and module-upload folder | Only approved unprotected derived/upload files may be removed after reference checks; never remove the raw blind-scan files |
| `C:\xampp\htdocs\klas\storage\app\public` | Full recursive folder backup, including legacy recommendation uploads | Preserve protected files and references; evaluate any removal by explicit path |
| `EDMS/BLIND_SCAN` under either root | Include all raw images and the complete directory structure in the folder backup | **Keep the entire raw-scan collection in place** |
| ST, passports, mother/extant recommendation letters, parcel site plans | Include source records, all copies, and both storage roots in backup | Preserve records, relationships, and files |
| Classification and business tables | Covered by the full database backup | Preserve `PageType`, subtypes, covers, file indexes, commissioning, applications, and recommendation records |

Folder backups must be written outside both source trees, with enough verified free space. Record source/destination paths, file counts, byte totals, and hashes; verify the copied files before cleanup. `public/storage` is a junction to the local C: root, so follow its real target deliberately rather than treating the junction itself as a backup. No whole-folder delete or blanket `TRUNCATE` is part of this plan.

The previously audited 7,227 converted blind rows are an upper bound for status-reset candidates, not an approved update count. The exact status-only set must be reconciled with the final scan/page removal manifest. The 1,383,277 already-pending rows remain unchanged. Existing audit CSVs are review artifacts, not backups.

## Audit boundary and totals

The main audit ran from 16:27:41 to 16:28:23 Africa/Lagos. The application remained live: another scan was uploaded during subsequent checks. These are observed audit counts, not a transactionally frozen database snapshot. Rebuild the manifests after stopping uploads before any reset.

| Table | Audited total | Proposed keep | Candidate removal |
|---|---:|---:|---:|
| scannings | 270,982 | 2,369 | 268,613 |
| pagetypings | 218,288 | 39 | 218,249 |

The proposed keep set conservatively includes all passports, all identified parcel uploads (including one application letter), ST records, and scans referenced by protected module uploads. This is a proposal, not an executed deletion or an assertion that the remaining records are safe to delete without the checks below.

`PageType` has 19 classification definitions. Preserve it and related classification tables. File-indexing business data, commissioning, applications, ownership, and recommendations are preserved; only the two file-indexing status fields participate in this reset.

## Identified records to preserve

| Category | Scan rows | Page-typing rows | Evidence |
|---|---:|---:|---|
| ST files, including linked original/mother files | 39 | 6 | ST registry, ST number/source, main/subapplication IDs, st_fillno, and exact application file-number matches |
| Passport photographs | 2,297 | 0 | Passport Photograph document type and direct OSS passport references |
| Parcel module uploads | 33 | 33 | Module upload paths, ingestion notes, and migration lineage |
| Mother recommendation uploads | 0 | 0 | 7 separate land_recommendation_batch_documents records |

ST scans belong to 13 indexing records; the six typed pages belong to two. ST records can use ordinary Land or KANGIS file numbers and registry values. A registry-only delete filter is insufficient.

Passport origin, using the latest exact `mls_file_no.full_file_number` match and `system_sub_type`:

- Land FC / MLS: 2,085 passport scans.
- OSS: 188 passport scans.
- Origin unresolved: 24 scans; 23 have no surviving indexing join and one has an unstamped MLS row. All are included in KEEP.
- Of the audited passport scans, 756 were captured at commissioning, 1,526 came from the duplex batch passport backfill, and 15 were updated from the file-number edit form. A later query saw one additional commissioning upload.
- `oss_applications.passport_photo` has 2,739 populated references, including photos outside the scanning keep set. These must be preserved independently. OSS application membership does not itself establish OSS commissioning origin.

All documents attached to commissioned file numbers, irrespective of upload origin: 2,105 scans link to MLS files; 188 to OSS files; two to unstamped commissioning files. Seven page-typing rows link to MLS files and zero to stamped OSS files. These membership counts overlap the categories above; they do not mean every document was uploaded by commissioning.

Parcel module source rows contain 37 distinct uploads, all found on the configured public disk:

| Module | Site plans | Other uploads | Uploads without a matching scan path |
|---|---:|---:|---:|
| Change of Purpose | 6 | 0 | 2 |
| Subdivision | 20 | 1 application letter | 0 |
| Merger | 2 | 0 | 0 |
| Duplex | 8 | 0 | 2 |
| Extension / Separation | 0 | 0 | 0 |

Thus there are 36 parcel site-plan files, of which 32 are represented in scannings, plus one application letter. Keep the four site-plan uploads with no matching scan row as well. Two are in `site_plans/` and two in the EDMS scan tree. The 28 historical parcel migration-log rows all reference kept scans.

## Storage findings

The configured public disk is `F:\storage\app\public`. Protected upload references also resolve under `C:\xampp\htdocs\klas\storage\app\public`. Back up both locations and inspect junction targets; do not assume that backing up one captures the other.

All seven mother recommendation uploads were located: five on the configured F: disk and two at the corresponding local C: paths. They are not represented by scanning/page-typing rows and would be missed by a backup based only on those two tables.

There are also 22 individual approved recommendation uploads. Twenty-one were found on F: and one on C:. They are separately referenced business documents, outside the proposed two-table reset. The follow-up clarification explicitly includes these Extant RofO letters in the preservation inventory, along with all seven mother uploads.

The protected inventory contains 3,327 distinct relative paths, approximately 623 MiB present on F:. This is only the protected subset, NOT the size of the full EDMS backup. Twenty-two paths were absent from the configured disk; three recommendation files were found on C:. Nineteen ST-related paths remain unresolved in the locations checked. They include nine scan paths, one page-typing path, and nine application-upload paths. No claim is made that those files do not exist elsewhere. Resolve/recover these before physical cleanup.

The manifests distinguish row removal from file deletion. Multiple rows share file paths: 268,613 candidate scan rows reference 268,513 distinct paths; 218,249 candidate page rows reference 216,143 distinct paths. No candidate path exactly matched the inventoried protected path set. This check does not cover every possible reference in every other application table; physical removal requires a full cross-module path-reference pass and backup.

## Related records and reset scope

- `blind_scannings`: 1,390,504 rows — 1,383,277 pending and 7,227 converted, all labelled Lands. **All rows and raw files are retained.** The only permitted table change is the affected records' status; see the confirmed rule above. These rows are excluded from every deletion total.
- 85 scan-reassignment logs reference candidate scans and have a cascading foreign key. They will be deleted automatically if their parent scans are deleted; include them in the backup/export.
- Five page-typing tool logs and one scan-image-version row reference candidate scans. Handle their history and associated image paths explicitly.
- `cadastral_documents` and `thumbnails` contain zero records at audit time.
- Scanning parent references use NO ACTION, so deletion must respect retained parents and child ordering. The proposed keep closure protects parents of kept scans and scans belonging to kept pages.
- Existing integrity issues: 117 scans and 51 page-typing rows have no matching indexing row; one typed page has a nonzero scanning ID with no matching scan. These are existing issues, not changes made by this audit.
- Some non-ST candidates are Cadastral scans/pages. If the intended reset is Lands-only, those require a separate exclusion. The current proposal interprets the request as the whole two-table scan/page collection except the stated protected uploads.
- Review affected indexing workflow, archive, QC, and update flags so files do not continue to appear completed after their pages are removed. Preserve indexing and business records themselves.

## Backup readiness and proposed execution sequence

SQL Server backup history records a full backup completed on 7 October at 06:01:35 (server-recorded time), approximately 12.18 GB, and an earlier copy-only backup. SQL Server is on a different machine from this application workspace. The recorded paths were not found from the app host, and SQL Server `xp_fileexist` also returned File Exists = 0 for both latest recorded backup paths. This may reflect moved files or permissions; neither backup is verified available or restorable. Backup history alone is insufficient.

1. Apply the confirmed blind-scanning status-only rule. Finalize whether Cadastral scans are included; conservatively retain the parcel application letter, unresolved passports, and all separately stored mother/extant recommendation documents.
2. Pause relevant uploads, typing, conversion jobs, and background writers; rebuild keep/candidate manifests and reconcile counts in a consistent snapshot.
3. Take a fresh full database backup with checksums, copy it to a verified backup destination, and perform restore verification plus a test restore. Export the affected tables and dependent histories, including every blind-scanning row and the status-update before state.
4. Back up both complete public-storage folders listed above, including `EDMS/BLIND_SCAN`, `EDMS/SCAN_UPLOAD`, `EDMS/PAGETYPING`, `EDMS/ARCHIVE_Doc_WARE`, ST application uploads, passports, parcel documents, recommendations, and versioned images. Produce a path/size/hash manifest and verify the copy. Audit CSVs are not a database or file backup.
5. Resolve missing protected ST paths and perform the full cross-module file-reference check. Prefer a reversible quarantine for candidate files over immediate physical deletion.
6. Only after final scope review and verified backups, delete the approved page-typing/scanning IDs in dependency order and handle their dependent history in a transaction. Update **only `blind_scannings.status`** for the reconciled converted-to-pending set. Do not delete blind-scanning rows, change their other columns, TRUNCATE, or reseed tables.
7. Verify protected IDs/paths, deletion totals, and the exact status changes. The blind-scanning row count and every non-status value must match the before snapshot. Keep all raw blind-scan files. Physically remove or quarantine only approved unprotected scan/page/archive paths after confirming they are not referenced by retained records.
8. Verify ST, commissioning passports, mother/extant recommendations, parcel site plans, and the intended scan/typing queues in the application before reopening uploads.

## Evidence files

- `summary.json`: main audit aggregates and backup history.
- `scannings-manifest.csv`, `pagetypings-manifest.csv`: every audited ID/path and KEEP/CANDIDATE decision.
- `st-indexing-evidence.json`: ST identity evidence.
- `external-upload-evidence.json`: module-specific upload references and F: existence checks.
- `parcel-reconciliation.json`: module breakdown and four uploads with no scan path match.
- `protected-missing-paths.json`, `missing-paths-alternate-locations.json`: missing-path and alternate-root checks.
- `passport_unresolved.json`, `supplementary.json`: unresolved origin, dependencies, and integrity checks.
- `audit.php`: read-only main audit implementation. It writes reports locally; it does not perform a backup or deletion.

## Follow-up: automatic passport typing and Extant RofO

The commissioning and passport-edit upload paths now create a scanning row and a page-typing row together: Front Cover / Image (PageType 11) / Passport (PageSubType 28), serial number 0 with suffix a, page code `FC-I-P-0a`. The code resolves the classification IDs from their names rather than assuming IDs across deployments. The original scan remains in place; typed and archive copies are created. QC stays pending. A failed copy or page insert rolls back the scan/page pair and removes only newly created copies. Existing passport uploads have NOT been bulk-backfilled or reclassified.

The real Land Recommendation Extant tab query currently returns **25 entries**: **22 existing recommendation letters** and **3 entries using mother recommendation letters**. The three mother-based entries are:

| Extant file | Mother file | Document key |
|---|---|---|
| CON-COM-2026-17 | CON-COM-2021-267 | SUBTPL-43628 |
| CON-COM-2026-16 | CON-COM-2021-268 | SUBTPL-43629 |
| COM-2026-321 | COM-1985-6 | SUBTPL-43725 |

Those three letters are already within the seven mother uploads counted above; the other four are batch mother uploads. Do not add 25 to 29: these are different views of overlapping records. Preserve both document tables, their underlying files on both drives, and their recommendation/batch links.

Two mother files existed under local C: storage but the application resolver only checked the configured F: roots. A local-storage read fallback was added, and a read-only verification now resolves **all 7 of 7 mother uploads** through the model used by the viewer. No recommendation files or database rows were moved or rewritten. See `extant-recommendation-evidence.json` and `mother-recommendation-resolution-after.json`.

Validation: eight isolated SQLite/fake-storage tests passed (44 assertions), covering new commissioning and edit uploads, classification, preserving originals, replacement copies, filename collisions, copy failure, database rollback, and explicit passport removal. PHP syntax checks passed. There was no production test commissioning or historic data backfill.
