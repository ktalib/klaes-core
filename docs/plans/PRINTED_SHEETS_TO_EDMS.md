# Printed Sheets to EDMS — Plan

**Source:** client request, 2026-10-02 — "once the commissioning sheet or LGA confirmation
sheet is printed, a copy should be saved in the EDMS folder… if printed again it should
just update and replace… and how about the others that they already printed?"

**Status:** plan only. Nothing built. Three decisions open (§6).

---

## 1. The requirement

- When a sheet is printed in Land (FC), an **exact copy** is filed in that file's EDMS
  folder, so it appears in Page Typing, the archive and the Virtual Folder System like
  any other page.
- Printing it **again replaces** the stored copy. One copy per file per sheet, never a pile.
- Sheets **already printed** before this exists are filed too.

First two sheets in scope:

| Sheet | Printed from | View |
|---|---|---|
| Commissioning Sheet | `CommissioningSheetController::print()` | `commissioning_sheet/pdf.blade.php` |
| LGA Confirmation Sheet ("Application for Conversion") | `FileNumberController::generateConversionApplication()` (+ the batch and date variants) | `generate_fileno/application_for_conversion.blade.php` |

Both are HTML pages printed by the browser (`window.print()`); neither produces a file today.

---

## 2. Format and renderer

**PDF, rendered by headless Chrome on the server** from the same Blade view the officer
prints. Same engine, same HTML and CSS, so the stored copy matches the paper: layout, QR,
watermark, page breaks. Multi-page sheets stay one document. EDMS already accepts PDF (site
plans ingest as PDF).

Verified 2026-10-02: Chrome at `C:\Program Files\Google\Chrome\Application\chrome.exe`
produces a PDF with `--headless=new --disable-gpu --no-pdf-header-footer --print-to-pdf=…`
(run as Administrator).

Rejected:

- **dompdf** (`barryvdh/laravel-dompdf` is installed). Weak CSS support: flex, Tailwind,
  web fonts. The copy would not match the paper.
- **Client-side capture** (html2canvas → upload). Depends on the officer's browser and
  screen, produces a raster, and only works for prints made after it ships, so it cannot
  backfill.

---

## 3. Flow on print

1. The print route renders the page to the browser exactly as now. **No change to what the
   officer sees, and no added wait.**
2. After the response is sent (`dispatch(...)->afterResponse()`), a job renders the same
   view to HTML, writes it to a temp file and runs Chrome on it to get a PDF. The view is
   rendered server-side, so Chrome needs no session or login.
3. The PDF is filed through `ParcelDocumentIngestService`, so there is still **one**
   definition of where a document lives and how it is classified (see
   `module-uploads-go-through-edms`).
4. A failure is logged and never surfaced to the officer. The print already happened. The
   backfill command (§5) picks up anything missed.

Batch prints (by batch number, by date) are split: **one PDF per file**, each filed under
its own file number.

---

## 4. Replace on reprint — the change to the ingest service

`ParcelDocumentIngestService::register()` always inserts: next position, new `scannings`
row, new `pagetypings` row. Reprinting would stack copies.

Add an **upsert** path keyed on `(file_indexing_id, scannings.document_type)`:

- **Found** → overwrite the bytes at the existing `document_path` and the page-typing copy,
  update `file_size` / `updated_at` / `uploaded_by` on both rows. Same position, same
  `page_code`. No new rows.
- **Not found** → current insert path.

New `DOCUMENT_MAP` entries, e.g. `commissioning_sheet`, `lga_confirmation_sheet`, with a
page type chosen from the existing `PageType` list and subtype `null` rather than a
near-miss (same rule as the map's docblock).

Fixed file name per sheet (`commissioning_sheet.pdf`, `lga_confirmation_sheet.pdf` inside the
file's folder) so the replacement is a true overwrite.

**Unindexed files** have no EDMS folder. They fall back to a legacy folder with no EDMS rows,
exactly as uploads do, and are picked up later by re-running the backfill.

---

## 5. Sheets already printed — backfill

`print_logs` (sqlsrv) records every print with `reference_number` and `document_type`:

| document_type | Prints logged | First | Last (at 2026-10-02) |
|---|---|---|---|
| Commissioning Sheet | 2,110 | 2026-05-06 | 2026-10-02 |
| Application for Conversion | 811 | 2026-05-21 | 2026-10-02 |

Command `edms:file-printed-sheets`:

- Distinct file numbers per document type from `print_logs`. Reprints collapse to one file.
- Re-render each sheet and file it through the same upsert.
- **Dry run by default**, `--apply` to write; `--type=`, `--limit=`, `--file=` to scope; chunked;
  skips files whose sheet is already filed unless `--force`.
- Reports: filed, replaced, fell back (unindexed), failed.

**Known limit:** a backfilled copy shows **today's** record, not the record on the day it was
printed. If a file was edited after printing, the stored copy differs from the paper in
those fields. Layout is identical. Prints made after go-live do not have this limit.

---

## 6. Open decisions

1. **Which copy is kept when the sheet carries a print-count watermark.** The conversion
   sheet computes `watermarkText` from the `print_logs` count, so "replace on every
   reprint" leaves the archive holding the *duplicate*-watermarked version. Recommended:
   keep the latest, rendered **without** the reprint watermark, since the archive copy is
   the record and not a reprint.
2. **Which other sheets follow.** The mechanism is generic over anything in `print_logs`:
   Land RofO (~31,800 prints), ROFO White Copy, Land Recommendation, Recommendation for
   Grant, Valuation Report, Bill Balance, and others. Recommended: ship the two sheets
   first and add others one at a time.
3. **Page type / subtype** for each sheet in `DOCUMENT_MAP`.

---

## 7. Risks to clear before building

- **Apache's service account.** Chrome was tested as Administrator. Under the account
  Apache runs as, it may need `--no-sandbox` and a writable `--user-data-dir`. Test it from
  a real web request, not tinker.
- **CDN assets.** The sheets load Tailwind (v2 CDN) and possibly other external assets.
  Chrome needs network access when rendering, or the assets must be inlined/local. A render
  with no network must not silently produce an unstyled sheet: check before filing.
- **Images by URL.** Anything referenced by a storage URL must resolve from the server
  (the commissioning passport photo is already a data URI for this reason).
- **Cost.** About 1–3 s per PDF. Fine after the response. Backfill of about 2,900 prints
  (fewer distinct files) runs after hours.
- **Production.** This directory is live and there is no backup. Test with the rollback
  harness on a handful of files (bytes written to a scratch folder, DB rows rolled back),
  then a `--limit=10 --apply` run checked by eye in the Virtual Folder System, then the
  full backfill.

---

## 8. Phases

1. Upsert path + `DOCUMENT_MAP` entries in `ParcelDocumentIngestService`.
2. `PrintedSheetRenderer` (view → HTML → Chrome → PDF) with the service-account and asset
   checks.
3. After-response job on the two print routes, including batch/date split.
4. Backfill command, dry-run first.
5. Verify: reprint replaces (row counts unchanged, bytes changed); a page appears in Page
   Typing and the VFS; unindexed file falls back; backfill report reconciles with
   `print_logs`.
