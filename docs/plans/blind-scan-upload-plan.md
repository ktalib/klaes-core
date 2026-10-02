# Blind Scan → Scan Upload Integration Plan

## 1. Objective
Enable users to pick "Blind Scan" as the upload method inside the Scan Uploads workflow and have the system automatically surface documents that already exist under `storage/app/public/EDMS/BLIND_SCAN/{FILE_NUMBER}`. When confirmed, the files should appear in the "Files Selected" section exactly as if the user dragged them in manually, and the originals must be cut (moved) into the Scan Uploads target structure (`storage/app/public/EDMS/SCAN_UPLOAD/{FILE_NUMBER}`).

---

## 2. Frontend Updates

### 2.1 Triggering the Blind Scan modal
- Listen for the upload method radio change in `resources/views/scan_uploads/assets/scripts.blade.php`.
- When method changes to `blind`, immediately open a dedicated modal (new Blade partial) that guides the user through Blind Scan ingestion.
- Disable the regular file picker while the Blind Scan modal is open to prevent mixed modes accidentally.

### 2.2 Modal UX
- New component under `resources/views/scan_uploads/partials/blind_scan_modal.blade.php` (or similar).
- Modal should:
  - Display the selected file number.
  - Show loading / success / error states while querying the server.
  - List matching Blind Scan folders (expected to be exactly one, but allow multiple matches gracefully).
  - Offer a "Load documents" CTA if a folder is found; otherwise present clear guidance ("No blind scan folder found" plus an action to close or refresh).

### 2.3 Injecting files into current upload state
- Extend the existing JS state (same file) with a method like `ingestBlindScanPayload(files)` that:
  - Accepts an array of file metadata from the backend.
  - Converts each entry into the same structure used for dropzone uploads (`state.uploadDocuments`, previews, etc.).
  - Marks the ingest mode as `blind` so the UI can show a badge and disable manual removal as needed (unless we want to let them remove). 
- Reuse existing rendering helpers to avoid duplicating DOM manipulation logic.

---

## 3. Backend Workflows

### 3.1 Discovery Endpoint
- New route: `POST /scan-uploads/blind-scan-discover` handled by `ScanUploadsController@discoverBlindScan` (if controller exists) or a new dedicated controller under the Scan Uploads namespace.
- Responsibilities:
  - Validate input (`file_number` required, string, sanitized via helper to remit unexpected characters).
  - Resolve full path: `storage_path('app/public/EDMS/BLIND_SCAN/' . $fileNumber)`.
  - Confirm folder exists and contains files. If not, return a structured JSON error.
  - Enumerate files recursively, building a manifest mirroring what the upload JS expects (name, size, paper size hint if available, relative location, etc.).
  - DO NOT move/copy files yet; this endpoint is read-only and should only gather metadata.
  - Optionally capture extra metadata (page counts) from `BlindScanning` table for richer UI.

### 3.2 Transfer Endpoint
- New route: `POST /scan-uploads/blind-scan-transfer`.
- Responsibilities:
  - Accept `file_number` and list of files (from the manifest) plus optional confirmation tokens.
  - Re-validate that the blind folder still exists and the files are present (avoid 404 race conditions).
  - Move each file from `EDMS/BLIND_SCAN/{fileNumber}/...` to `EDMS/SCAN_UPLOAD/{fileNumber}/...`.
    - Use `Storage::disk('public')` to perform the moves to keep Laravel's storage abstraction.
    - When a file is moved, update/insert database rows the same way the current manual upload flow does (review existing upload controller service, likely `ScanUploadsController@store` or a service class).
  - Remove the source folder once all files are moved (optional; might retain empty folder for auditing).
  - Return success response with new manifest for the frontend to inject.
- Ensure idempotency: track moves per session or rely on atomic renames to prevent duplicate ingestion.

### 3.3 Shared Services
- Consider extracting reusable file-to-manifest logic (used in discovery & transfer) into a service class (`App\Services\ScanUploads\BlindScanIngestionService`).
- Reuse helper methods (`detectPaperSizeFromPath`, etc.) from `BlindScanningController` if applicable; if duplicated, refactor into a shared trait/service.

---

## 4. Data Integrity & Logging
- Every move should log activity via `UserActivityLog` or existing logging helpers to preserve audit trail.
- Decide what to do with the matching entries in `blind_scannings` table:
  - Option A: mark rows as `moved_to_scan_uploads` with timestamp and user ID.
  - Option B: delete them (less preferable — we should retain for history).
- Update `_migrations.json` (if still relevant) to reflect the folder removal/move.

---

## 5. Validation & Security
- Validate that the current user has permission to access the file number (mirror checks used in blind-scanning / scan-uploads modules).
- Sanitize file number before using it in file system paths to prevent traversal attacks.
- Guard against concurrent transfers: wrap file moves in a database transaction combined with file locking if necessary (or rely on storage atomic rename).

---

## 6. Testing Strategy
- **Unit tests** (if harness exists) for new service methods (manifest creation, move operations).
- **Manual tests**:
  1. Manual file number with existing blind scan folder → ensure modal loads, files inject, folder disappears from blind scan.
  2. No folder present → modal shows informative message, manual upload still available.
  3. Partial failure (e.g., a file locked) → ensure rollback messaging and no partial move.
  4. Permissions edge case: non-admin user should only be able to move folders they own/are allowed to access.

---

## 7. Rollout Notes
- After deployment, align operations team so blind-scan operators know the folder will be moved automatically once ingestion happens.
- Confirm nightly jobs or other automation that expect the folder to stay in `BLIND_SCAN` are updated accordingly.
- Update any documentation referencing manual steps to reflect the streamlined modal workflow.
