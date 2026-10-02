# PDF Conversion Study and Plan

## Current State (Scan Uploads and Blind Scan)
- **Blind scan ingestion (server-side):** `app/Http/Controllers/BlindScanningController.php` ingests folders under `storage/app/public/EDMS/BLIND_SCAN` (including ZIP uploads), persists rows per file via `saveFilesToDatabase()`, and only counts PDF pages with a lightweight regex parser (`getPdfPageCount`). No PDF conversion is performed; PDFs are stored as-is and marked `status=pending`.
- **Blind scan → Scan Uploads bridge:** `app/Services/ScanUploads/BlindScanIngestionService.php` moves staged blind-scan files into `EDMS/SCAN_UPLOAD/<registry>/<file_number>` and creates `scannings` rows. Files (including PDFs) are simply moved; `is_pdf_converted` is always `false`.
- **Scan Uploads UI (browser-side):** `resources/views/scan_uploads/index.blade.php` + `assets/scripts.blade.php` load pdf.js and already support client-side PDF→JPEG conversion before upload. It can also attempt to fetch blind-scan PDFs via URL and convert them in the browser. Converted pages are uploaded as images with `is_pdf_converted` metadata.
- **Gaps:** Server-driven blind-scan migration leaves PDFs untouched, so ingesting a folder that contains PDFs will still produce PDF documents in `blind_scannings` and later in `scannings`. Page counts are approximate (size-based cap) and there is no PNG page materialization for server-only flows or background automation.

## Goal
Convert any PDFs found inside blind-scan folders into PNG images (one per page) before or during ingestion, then upload/store the PNGs instead of the PDFs while keeping metadata coherent (`is_pdf_converted`, page counts, folder structure).

## Proposed Plan
1) **Select conversion backend:** Choose a server-side PDF→PNG tool available in the environment (e.g., Imagick/Ghostscript or `pdftoppm` from Poppler). Define DPI/quality defaults and max size limits aligned with existing caps (`PDF_PAGE_SCAN_LIMIT_BYTES`, `PDF_PAGE_SIZE_THRESHOLD_BYTES`).
2) **Add a converter service:** Implement a dedicated helper (e.g., `App\Services\PdfToImageService`) that accepts a PDF path and outputs numbered PNGs into a destination folder (e.g., `<folder>/<pdf-name>/page-001.png`), returning page metadata and handling errors/timeouts.
3) **Integrate into blind-scan migration:** In `saveFilesToDatabase()` (or a pre-pass), detect PDFs, run the converter, and enqueue DB rows for the generated PNG pages instead of the original PDF. Mark `is_pdf_converted=true` (via `notes` or an added column if needed) and set `document_path` to the PNG paths; optionally retain the source PDF in an `originals/` subfolder for audit.
4) **Bridge updates to Scan Uploads:** When `BlindScanIngestionService::transfer()` moves files, skip raw PDFs if PNG siblings exist; move the generated PNGs and propagate an `is_pdf_converted` flag into the created `scannings` rows. Keep display order stable (e.g., natural sort by page number).
5) **Background/health handling:** Respect size thresholds, log conversion failures with the folder name, and fall back to keeping the PDF when conversion fails. Add safeguards to avoid re-converting already-converted folders (e.g., marker file or DB flag).
6) **Verification and ops:** Add a small artisan command to batch-convert existing blind-scan folders and to re-sync DB rows. Document required binaries/dependencies and provide a quick health check endpoint/log entry for conversions.

## Open Questions / Assumptions
- Can we install/populate a reliable PDF rendering backend (Imagick/Ghostscript or Poppler) on the target host? 
<!-- use Poppler -->

- Should original PDFs be retained alongside generated PNGs, or fully replaced?
 <!-- fully replaced -->
- Is there a desired DPI/page cap (defaulting to something like 150–200 DPI to balance legibility vs. size)?
 <!-- do the right thing -->
- Do we need to expose conversion progress in the UI for blind-scan migrations, or is server-side logging sufficient?
<!-- yes conversion progress in the UI  -->

## Progress This Round
- Added `app/Services/PdfToImageService.php` that attempts Imagick first, then `pdftoppm`, and returns per-page PNG metadata with logging.
- Updated `BlindScanningController::saveFilesToDatabase()` to convert PDFs during migration, archive original PDFs under `_pdf_originals/`, and enqueue page-level records (one per PNG) while preserving document metadata; falls back to storing the original PDF when no converter is available.
- Updated `BlindScanIngestionService` to skip `_pdf_originals/` so archived PDFs are not re-imported.

## Deployment Notes
- Current host check: `imagick` PHP extension is not loaded and `pdftoppm` is not found, so conversions will currently fall back to storing PDFs.
- To enable conversions, install one of:
  - Imagick with Ghostscript and enable the `imagick` PHP extension, or
  - Poppler utilities (`pdftoppm` available on PATH).
