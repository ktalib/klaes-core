# Blind Scanning Workflow

Purpose
-------
This document describes the goals and implementation contract for the Blind Scanning feature implemented by `resources/views/scanning/blind_scans.blade.php`.

Goal
----
Provide a simple browser-based workflow for operators to package scanned documents on their local PC (organized per-file-number folders with `A4` and `A3` subfolders), preview them in the browser, and migrate them to the server storage and database in a controlled, auditable way.

High-level user flow
--------------------
1. Operator chooses a File Number in the UI (for example: `COM-2025-4554`).
2. Operator clicks "Browse to Folder" and selects the local parent folder named exactly the file number (e.g. `C:\edms\COM-2025-4554` or directly the folder on the desktop).
3. The client analyzes the folder for `A4` and `A3` subfolders and counts files.
4. Operator clicks "Preview Files" to render grouped file lists (A4 / A3 / root).
5. Operator clicks "Migrate to Server" which:
   - Zips the selected parent folder (including A4/A3 and any files beneath).
   - Posts the zip to the Laravel migration endpoint with `file_no`, `folderName`, and CSRF token.
6. Server extracts files under `storage/app/public/EDMS/BLIND_SCAN/{Parent}/{A4|A3}` and inserts records into the `blind_scannings` table.
7. Migration events are logged and available in the UI under Migration Logs (persisted server-side in `_migrations.json` and/or DB).

Expected local folder layout (example)
--------------------------------------
C:\edms\COM-2025-4554\
- A4\
  - page001.pdf
  - page002.pdf
- A3\
  - page001.pdf
- some-other-file.pdf (optional root file)

Client responsibilities (browser)
--------------------------------
- Allow picking a folder via the File System Access API (`showDirectoryPicker`).
- Analyze folder structure (detect `A4` and `A3`, count files, collect names/sizes).
- Render preview grouped by A4/A3/root within the `#file-preview-container` element.
- Create a ZIP of the selected parent folder client-side using JSZip.
- POST a multipart/form-data to the migrate endpoint with fields:
  - `_token` (CSRF)
  - `file_no` (e.g., `COM-2025-4554`)
  - `folderName` (parent folder name)
  - `zip` (file blob, filename `${folderName}.zip`)
- Display progress and show toasts for success/failure.

Client ↔ Server contract
------------------------
- Endpoint: `POST` to `route('blind_scan.migrate')` (the Blade sets MIGRATE_ENDPOINT accordingly).
- Request: multipart/form-data with fields shown above.
- Expected server JSON success response:
  ```json
  {"ok": true, "serverPath": "storage/app/public/EDMS/BLIND_SCAN/COM-2025-4554"}
  ```
- Expected server failure response should include an error message, e.g.:
  ```json
  {"ok": false, "error": "Extraction failed: invalid zip"}
  ```

Server responsibilities (Laravel)
---------------------------------
- Accept the uploaded zip and validate:
  - CSRF token
  - `file_no` is present and matches expected format
  - Size limits
- Create the destination directory under `storage_path('app/public/EDMS/BLIND_SCAN/{folderName}')`.
- Extract the zip content preserving `A4` and `A3` subfolders. Final disk layout should be:
  - `storage/app/public/EDMS/BLIND_SCAN/COM-2025-4554/A4/...`
  - `storage/app/public/EDMS/BLIND_SCAN/COM-2025-4554/A3/...`
- For each extracted file, insert a row into `blind_scannings` (or other indexing table). The UI expects the table to contain the following columns (example query used in the project):
  ```sql
  SELECT TOP (1000) [id]
        ,[temp_file_id]
        ,[original_filename]
        ,[document_path]
        ,[paper_size]
        ,[document_type]
        ,[notes]
        ,[status]
        ,[uploaded_by]
        ,[file_indexing_id]
        ,[converted_at]
        ,[created_at]
        ,[updated_at]
    FROM [klas].[dbo].[blind_scannings]
  ```
  Minimal required fields to populate per-file:
  - `original_filename`: the file name
  - `document_path`: relative server path under `storage/app/public/EDMS/BLIND_SCAN/{Parent}/...`
  - `paper_size`: e.g., `A4` or `A3` (derived from subfolder)
  - `status`: set to an initial value like `uploaded` or `pending`
  - `uploaded_by`: user id or system id performing the migration
  - `created_at` / `updated_at` timestamps

- Append a migration log entry to `_migrations.json` (or equivalent persistent store) with: when, folder, serverPath.
- Return `{ ok: true, serverPath: '...' }` on success.

Implementation notes / validations
---------------------------------
- Validate `folderName` and `file_no` on the server against a whitelist or regular expression to prevent path traversal.
- Enforce a maximum upload size (both webserver/PHP and the application) and reject overly large uploads.
- If extracting the zip, run extraction atomically into a temp dir and then move into the final destination.
- Normalize file names to avoid invalid characters on target filesystem.

Troubleshooting & common issues
-------------------------------
- "Cannot read properties of null (reading 'classList')": ensure the File Number UI component exists and exposes the expected DOM ids (`selected-file-info`, `selected-file-no`, `selected-file-name`). The Blade view was updated to include these.
- File System Access API not available: requires a Chromium-based browser (Chrome/Edge) on HTTPS or localhost. Safari/Firefox do not fully support `showDirectoryPicker`.
- Large ZIP memory: zipping large folders client-side may OOM the browser. Consider server-side zipping if folders are huge.

Testing checklist
-----------------
- [ ] UI: pick a parent folder containing `A4` and `A3` and verify file listing in the preview panel.
- [ ] Server: POST a small test ZIP and verify files are extracted to `storage/app/public/EDMS/BLIND_SCAN/{Parent}`.
- [ ] DB: verify `blind_scannings` rows are inserted for each extracted file with correct `document_path` and `paper_size`.
- [ ] Logs: verify new entry appears in Migration Logs UI.

 
 
 
