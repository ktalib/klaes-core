# Broken Page Images After Page Typing - Investigation Report

## Status
- Investigation completed.
- No application code or data changes were made.
- This report is intended for review before any implementation begins.

## Executive Summary
Broken page images are most likely caused by a storage-path consistency bug in the page-typing save flow. When a page is typed, the system attempts to standardize the underlying file into an EDMS page-typing location and then updates the database record to point at that new path. If the file is not actually present at the new location, or if the generated path does not match the file that was stored, the preview layers in the Page Typing, Scan Uploads, and File Archive modules will render broken images because the recorded path resolves to a missing file.

The issue is not primarily in the browser preview UI. The failure happens earlier in the storage lifecycle: the database record is updated to a path that is not guaranteed to be backed by a real file on disk.

## Scope Reviewed
The investigation focused on the following components:
- [app/Http/Controllers/PageTypingController.php](app/Http/Controllers/PageTypingController.php)
- [app/Http/Controllers/ScanUploadsController.php](app/Http/Controllers/ScanUploadsController.php)
- [app/Http/Controllers/FilearchiveController.php](app/Http/Controllers/FilearchiveController.php)
- [app/Services/PageTypingService.php](app/Services/PageTypingService.php)
- [app/Models/PageTyping.php](app/Models/PageTyping.php)

## Observed Workflow
1. Scan uploads are stored under the scan-upload storage area and their relative storage path is recorded in the scanning record.
2. During page typing save, the controller derives a standardized filename and target folder structure based on registry, file number, paper size, and definition code.
3. The code then tries to resolve the source file, copy or move it to the standardized page-typing path, and update both the page-typing record and the scanning record to point at the new path.
4. The preview layers later build viewer URLs from the stored path in the database and only show previews when the file exists at that location.

## Root Cause
The root cause is a mismatch between the database path and the physical file location during the page-typing save operation.

The relevant save flow in [app/Http/Controllers/PageTypingController.php](app/Http/Controllers/PageTypingController.php) performs these steps:
- derives a standardized target path;
- attempts to resolve the source file;
- copies or moves the file toward the standardized page-typing path;
- updates the page-typing record and the scanning record to the new path.

This is fragile because the code assumes the new path is valid once the database update is made. If the move/copy does not complete, if the source path is different from the inferred target path, or if the actual stored filename differs from the generated filename, the database still records a path that cannot be resolved to a real file.

That stale or incorrect path is then consumed by the preview code in [app/Http/Controllers/FilearchiveController.php](app/Http/Controllers/FilearchiveController.php), which only returns a viewer URL when the file exists at the normalized storage path. When it does not exist, the image preview is broken.

## Why the Problem Appears in Multiple Screens
The same bad storage-path value is reused across modules:
- Page Typing uses the recorded path when showing or reloading a page.
- Scan Uploads uses the scan record’s document path.
- File Archive uses both the scanning document path and the page-typing file path to build preview URLs.

Because all of them depend on the same stored path, the failure is visible in multiple places even though the underlying issue is shared.

## Affected Components
- Page Typing editor and save workflow
- Scan Uploads document preview and related links
- File Archive page viewer and thumbnail generation
- Any downstream process that relies on page-typing file paths or scanning document paths

## Permanent Fix Recommendation
The permanent fix should make file relocation atomic and verifiable before the database is updated.

Recommended approach:
1. Resolve the canonical source file first.
2. Copy the file to the standardized page-typing target location.
3. Verify that the target file exists and is readable.
4. Only then update the page-typing record and the scanning record to the new path.
5. Keep the old path as a fallback or archive reference until the new path is confirmed.
6. If the copy or rename fails, leave the original path unchanged and surface an explicit error rather than writing a broken path into the database.

Implementation should ideally be centralized in a dedicated service so that the save flow, replacement flow, and any future media migration logic all share the same behavior.

## Repair Guidance for Existing Broken Records
For already affected records, the repair should be data-safe and non-destructive:
1. Identify the affected page-typing record and scanning record.
2. Check whether the file exists at the path stored in the database.
3. If the file is missing, locate the original source file from the scan-upload or legacy storage location.
4. Recreate or copy the file into the expected page-typing location.
5. Update the record’s stored path to the verified real path.
6. If necessary, re-upload the file and re-save the page typing entry.

The repair should preserve the existing metadata and only correct the physical file location and the stored path reference.

## Validation Plan
The fix should be validated with a focused test checklist:
- Create or update a page-typing entry for a scan that has a valid source file.
- Confirm that the target file exists on disk after save.
- Confirm that the database path points to that real file.
- Open the record in Page Typing and verify the preview loads.
- Open the same file in Scan Uploads and verify the preview loads.
- Open the File Archive viewer and verify the page preview loads.
- Repeat for both image files and PDF files.
- Confirm that a failed relocation leaves the old path usable instead of corrupting the database reference.

## Conclusion
The investigation points to a root cause in the storage-path migration logic used during page typing. The preview failure is a consequence of the system recording a path that is not reliably backed by a real file on disk. The correct fix is to ensure the target file is physically present and verified before the database path is updated, and to make that behavior consistent across all relevant controllers and services.
