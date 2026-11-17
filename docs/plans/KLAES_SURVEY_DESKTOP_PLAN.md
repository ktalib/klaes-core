# KLAES Survey Desktop: report and implementation plan

Status: **planned, not started.** The user approved the approach on 2026-11-16 and asked to hold the build until the
pending Survey updates and corrections on the web and phone are finished. Nothing in this document has been built.

---

## 1. Report: why a desktop app

### 1.1 What exists today

| Where | What it does | Files |
|---|---|---|
| Web: Survey Plan Check card (KLAES Upload Survey Plan pop-up, Check page, MLPP-SG portal upload) | Coordinate system, reference coordinate, traverse (bearing and distance), map with Beacon Register, Kano GIS parcels and beacons (PMTiles), Kano imagery tiles, scanned plans (GeoTIFF), Add layer (shapefile, GeoJSON, KML), export (GeoJSON, KML, shapefile, DXF, CSV, SQL, File Geodatabase) | `resources/views/survey_module/survey_plans/_plan_check*.blade.php`, `public/js/survey-plan-check.js`, `public/js/survey-plan-scan.js`, `App\Services\Survey\PlanCheckGdb` |
| Phone: Field Observations app (cfo) | Per job: Plot (traverse cards + map, Walk & mark GPS), Readings, Beacons (Beacon Number dropdown), Checks, Sketch Plan | `resources/views/survey_module/mobile/observations_job.blade.php`, `_fo_plot.blade.php`, `public/js/fo-field.js`, `MobileObservationController` |
| Server GIS tools | Python venv `C:\xampp\klas-tools\gis-py` (Python 3.14, pyogrio / GDAL 3.12), tile makers `resources/gis/make_tiles.py`, `make_vector_tiles.py`, `geojson_to_gdb.py` | |
| Kano GIS data | `F:\GIS Data` (ORIGINAL GEOMETRY.gdb: Plot_Parcel 41,180, Beacon_Name 87,064; roads; LGA; control beacons; imagery TIFFs 6 GB and 12 GB) | |

### 1.2 What the user found

The browser map is "not better" for serious survey work. A browser cannot comfortably:

- open multi-gigabyte GeoTIFFs and orthophotos (we have to cut them into web tiles first);
- do full GIS editing: snapping, arcs (cul-de-sac radius / chord), CAD-style drawing, printing to scale;
- read every GIS format directly (a File Geodatabase is refused by Add layer today);
- work offline with the full Kano data on the machine.

### 1.3 Options considered

| Option | Users install | Verdict |
|---|---|---|
| A. QGIS + a KLAES plugin | QGIS (free, ~1 GB) on each PC, then our plugin | Less to build, but users face the full QGIS screen; two installs; harder to control the workflow. **Kept as a future option, with a small test plugin (see 2.4)** (user, 2026-11-16) |
| **B. Our own app: "KLAES Survey Desktop"** | One installer (.exe) | **Chosen (user, 2026-11-16).** Our screens and branding, only the tools surveyors need; uses the same free GIS libraries as QGIS inside |
| C. Keep web only | Nothing | Stays, for checking, approval, MLPP-SG portal and quick use |

**Decision:** build B in Python, packaged as one Windows installer. The web Survey Plan card and the phone apps stay;
the desktop app is the heavy-work tool and sends its work to KLAES.

---

## 2. Goals and scope

### 2.1 Who uses it

1. Surveyor General's office staff: Survey, GKN / Examination, Checking & Passing, SG / DSG.
2. Cadastral desk staff working survey jobs.
3. Later (decide): private surveyors registered on the MLPP-SG portal, signing in with their portal account.

### 2.2 Version 1 (first release)

- Sign in with the KLAES account; OTP the same as the web.
- Open a job: I to S (Survey / Cadastral), Surveyor's Report (Private), or an uploaded Survey Plan.
- A map with the Beacon Register, Kano GIS parcels / beacons / roads / LGA / control beacons, Kano imagery, and an online basemap.
- Coordinate systems: Minna / UTM 32N (EPSG:26332, the default), NTM West / Mid / East belts (26391 / 26392 / 26393), and UTM 32N WGS 84 (32632). Same proj strings and Minna shift (-92, -93, +122) as the web, so the numbers agree.
- Traverse entry: From / To beacon, bearing (N/S … E/W up to 90°, whole circle above), distance. Closing error, precision, area, clockwise check. Same rules as the web and phone.
- Beacon Number pickers with search, plus one Other (specify), from the same server search.
- Import: shapefile, GeoJSON, KML/KMZ, DXF, CSV (X/Y), File Geodatabase, GeoTIFF (any size).
- Export: the same set, plus a printable plan sheet (PDF) at a chosen scale.
- Send to KLAES: readings, List of Coordinates and the boundary, under the same checks as the web. Pull jobs, plans and register beacons down.
- Offline: work saved locally, sent when connected.
- Auto-update from KLAES.

### 2.3 Later versions

- Arcs (radius / chord) for cul-de-sac plots.
- Scanned plan georeferencing: control points, grid reading; the prototype work in `public/maps/survey-plan-scan.js` is the reference.
- Plan sheet / Application Plan printing (template `survey_module/survey_plans/application_plan.blade.php`, on hold on the web).
- Topology checks against neighbouring Kano parcels (overlaps, gaps).
- MLPP-SG private surveyor edition.

### 2.4 QGIS plugin: future option and a small test plugin (user, 2026-11-16)

The QGIS route is **not dropped**. It stays a future option, and we build a **small test plugin** to see how a plugin
fits our workflow and design next to our own app.

- **Same API, same rules.** The plugin calls the same `/api/desktop/v1/*` routes as the desktop app (section 5), so
  anything entered in QGIS lands in KLAES exactly as from the app, the web or the phone (section 5A).
- **Test plugin scope (small):** sign in (token); list open jobs; load a job's List of Coordinates and readings as QGIS
  layers in the job's coordinate system; enter or edit a traverse in a KLAES panel; press Send to KLAES. The Beacon
  Register and Kano GIS parcels load as layers.
- **Tech:** PyQGIS on QGIS 3.40 LTR (free), Qt dialogs via `qgis.PyQt`. The survey maths module from the desktop app is
  reused unchanged (one source of the maths).
- **Install for the test:** a `.zip` loaded through QGIS > Plugins > Install from ZIP on 1–2 PCs. QGIS goes on those
  users' PCs only, never on the server.
- **What we learn:** whether users prefer QGIS's tools with a KLAES panel, or our own simpler app; and what each needs
  that the other lacks.
- **When:** after the desktop app's sync works (phase 5), as phase P in section 6. Optional.

---

## 3. Architecture

```
┌──────────────────────── KLAES Survey Desktop (Windows PC) ─────────────────────────┐
│  UI: PySide6 (Qt 6)  ── screens, forms, tables, dialogs                             │
│  Map: Qt WebEngine + MapLibre GL (same map code style as the web)                   │
│       ← local tile server inside the app (big GeoTIFFs via GDAL, PMTiles, MBTiles) │
│  GIS core: GDAL / pyogrio / rasterio, pyproj, shapely, ezdxf                        │
│  Survey maths: one Python module (traverse, bearings, closure, area), unit-tested │
│                against the same cases as the web (parcel 234 closes 0.000)         │
│  Local store: SQLite (jobs, drafts, outbox of changes not yet sent)                 │
│  Sync client: HTTPS + token (httpx); outbox with client_uuid per change            │
└───────────────────────────────────────┬────────────────────────────────────────────┘
                                        │ HTTPS (JSON), token per user + device
┌───────────────────────────────────────▼────────────────────────────────────────────┐
│  KLAES (Laravel 9, app.klaes.ng): new /api/desktop/v1/* routes                       │
│  Sanctum tokens · same permissions as the web (module_permissions) · audit log      │
│  Writes through the SAME services as the web/phone (e.g. MobileObservationController│
│  ::storeTraverse rules) → survey_its_observations, survey_its_coordinates, …         │
└────────────────────────────────────────────────────────────────────────────────────┘
```

### 3.1 KLAES is the master copy

- The desktop never writes to the database directly; only through the API.
- Every change carries a `client_uuid`, so a resend is never doubled. The phone already works this way.
- Every changed record carries `updated_at`. If the web changed it after the desktop pulled it, the desktop shows the difference and asks; it never silently overwrites.
- Beacons already on a List of Coordinates are kept, not overwritten (the rule already used by the phone).
- Every push is logged with the user, device, app version and time.

### 3.2 Offline

- Jobs opened online are cached in SQLite with their beacons and the map area's data.
- Work done offline goes to an **outbox**. The app sends it in order when online; failures stay in the outbox with the server's message.

---

## 4. Technology and tools

### 4.1 Desktop app

| Need | Tool | Notes |
|---|---|---|
| Language | **Python 3.12** (64-bit) | 3.12, not 3.14: the GIS wheels (rasterio, GDAL) and packagers are most stable on 3.12 |
| UI | **PySide6** (Qt 6, LGPL) | Native Windows look, tables, dialogs, docking panels. LGPL is fine for an installed app |
| Map | **Qt WebEngine + MapLibre GL JS** (bundled, offline) | Reuses what we know from the web card; fast vector + raster. Alternative if needed: Qt's own canvas |
| Vector GIS read/write | **pyogrio** (GDAL) | Shapefile, GeoJSON, KML, GPKG, File GDB (OpenFileGDB driver), CSV |
| Raster | **rasterio** (GDAL) | Big GeoTIFFs: overviews, reprojection, tiles on demand |
| Projections | **pyproj** (PROJ) | Same Minna / NTM / UTM definitions as the web |
| Geometry | **shapely 2** | Area, overlap, buffer, snapping |
| CAD | **ezdxf** | DXF import / export |
| PDF plans | **ReportLab** (or Qt's PDF printer) | Plan sheet at scale |
| Local store | **SQLite** (built in) | Cache + outbox |
| HTTP | **httpx** | Timeouts, retries |
| Token storage | **keyring** (Windows Credential Manager) | The token is never in a plain file |
| Settings | `platformdirs` → `%APPDATA%\KLAES Survey Desktop` | |
| Logs | Python `logging`, rotating files | For support |
| Tests | **pytest** (+ pytest-qt for the UI) | Survey maths tested against the web's known cases |
| Code quality | ruff (lint + format), mypy (types) | |

### 4.2 Building and packaging into one installer

| Step | Tool | Output |
|---|---|---|
| 1. Freeze Python + all libraries | **PyInstaller** (one-folder build) | `dist\KLAES Survey Desktop\` with `KLAESSurvey.exe` and the GDAL / PROJ data folders |
| 2. Make the setup | **Inno Setup 6** | `KLAES-Survey-Desktop-Setup-1.0.0.exe`: install to Program Files, Start menu and desktop shortcuts, uninstaller, file associations (optional) |
| 3. Sign the files (optional) | Our own certificate from the office domain (`Dc-01`) + `signtool`, free | Office PCs trust the installer with no warning. **No paid certificate needed** (see 4.5) |
| 4. Publish | KLAES server: `/downloads/desktop/` + a version file | The app checks it at start and offers the update |

Notes:

- GDAL and PROJ need their data folders bundled (`gdal-data`, `proj` database); PyInstaller hooks handle most of this, and we test on a clean PC.
- Qt WebEngine adds about 150 MB. Expected installer size is 250–400 MB, and the installed size about 700 MB.
- Alternative packagers, if PyInstaller has problems: Nuitka (compiles to C, faster start-up), or Briefcase (makes an MSI).
- Windows 10 / 11, 64-bit only.

### 4.3 Developer machine

Python 3.12, Git, VS Code, Inno Setup 6, the Windows SDK (signtool), and a clean Windows virtual machine for install tests.
Source in a new repository (proposed `ktalib/klaes-survey-desktop`), separate from `klaes-core`.

### 4.4 Code signing: not required (user, 2026-11-16)

A signed installer only shows a verified publisher name. Without signing, Windows shows "Windows protected your PC:
unknown publisher" once at install; the user clicks **More info → Run anyway** and the app then works normally. No
budget is planned for a paid certificate.

| Option | Cost | Result |
|---|---|---|
| **A. No signing** | Free | One warning at install, on every PC |
| **B. Internal certificate** (office domain `Dc-01`, pushed to office PCs by Group Policy) | Free (IT time) | No warning on office PCs; outside PCs still see it once |
| C. Paid signing (e.g. Microsoft Trusted Signing, about US$10/month; or an OV certificate) | Paid | No warning anywhere; only if outside users later need it |

Plan: start with A, move to B when IT can set it up. C is not planned.

### 4.5 Branding and icons

| Item | Spec |
|---|---|
| App name | KLAES Survey Desktop (to confirm) |
| App icon | `.ico` with 16, 24, 32, 48, 64, 128, 256 px; from the KLAES / MLPP-SG artwork (`public/assets/logo/`), redrawn simple enough to read at 16 px |
| Installer graphics | Inno Setup wizard image 164×314 and small image 55×58 (plus 2× sizes for high-DPI screens) |
| Splash screen | 600×360 PNG, KLAES-CORE logo + version |
| In-app icons | One icon set (e.g. Lucide or Font Awesome Free, both free) as SVG, so they are sharp on every screen |
| File icon | `.klaes-survey` project files (optional) |
| Colours | Same tokens as the web (KLAES primary + Survey colours); light and dark themes |
| Footer | "Powered by VALUE MAPS & APPS LIMITED (VMAL)", as on the portal |

---

## 5. KLAES server work (API)

New routes `routes/api.php` → `/api/desktop/v1/*`, with Sanctum tokens, rate limits, and the existing module permissions.

| Method | Route | Purpose |
|---|---|---|
| POST | `/auth/login` | username / email + password → OTP → token for this device (name = PC + app version) |
| POST | `/auth/logout` | revoke this device's token |
| GET | `/me` | user, roles, allowed modules |
| GET | `/jobs?status=open` | I to S (survey / cadastral) and private reports: the anchors `SurveyItsAnchors` already lists |
| GET | `/jobs/{its}` | job details + readings + List of Coordinates + Beacon Nos check |
| POST | `/jobs/{its}/traverse` | readings + coordinates (reuses `storeTraverse` rules) |
| POST | `/jobs/{its}/coordinates` | one or more beacons |
| GET | `/beacons/search?q=` | `SurveyPlanUploads::searchBeacons` |
| GET | `/register/beacons?since=` | Beacon Register changes since a date (for the offline cache) |
| GET | `/plans`, `/plans/{id}` | Survey Plan uploads + files |
| POST | `/plans/{id}/check` | send the checked boundary back (to decide) |
| GET | `/gis/{layer}` | Kano GIS PMTiles (the existing `GisTilesController`) |
| GET | `/app/version` | latest version, download link, notes, minimum version allowed |

Rules:

- Shared rules: controllers call the same services as the web and phone; no second copy of the validation.
- Tokens expire (proposed 30 days); the SG / Super Admin can see and revoke devices.
- Audit: `desktop_sync_log` (user, device, route, client_uuid, result) on SQL Server.

---

## 5A. Value mapping and sync (the most important part; user, 2026-11-16)

Every value must mean the same thing and arrive unchanged whether it was entered on the **web**, the **phone**, the
**desktop app** or the **QGIS plugin**. KLAES (SQL Server `klas`) is the single store; no client keeps its own version
of the truth. The column types below are read from the live tables (2026-11-16).

### 5A.1 Global rules

| Value | Stored as | Rule on every client |
|---|---|---|
| Coordinates | `easting` = X, `northing` = Y, `decimal(15,3)`, metres | X is the Easting (6 digits in Kano), Y the Northing (7 digits). A 7-digit X is switched back with a note (the web and phone already do this). Rounded to 3 decimals (mm) only when saved, never in between |
| Coordinate system | EPSG code as text: `26332` (default), `26391`, `26392`, `26393`, `32632` | **Every coordinate carries its system.** Clients convert for display only; a value is saved in the system it was entered in, with that code beside it |
| Bearing | `observed_bearing decimal(12,6)`: whole-circle azimuth in decimal degrees, 0 ≤ b < 360, from grid north | Entry may be N/S d° m′ s″ E/W (up to 90°) or whole circle (over 90°). Every client converts to the azimuth with the same function and shows both forms. D/M/S ↔ decimal agree to 1″ |
| Distance | `observed_distance decimal(12,3)`, metres, > 0 | Horizontal distance, as on the plan |
| Beacon number | `beacon_id`, `from_station`, `to_station`, `nvarchar(50)` | Trimmed, inner spaces collapsed, UPPER CASE. Matching ignores case and spaces. Picked from the shared search, or Other (specify) |
| GPS fix | `gps_latitude`, `gps_longitude` `decimal(10,7)` (WGS 84); `gps_accuracy_m decimal(8,2)` | Kept beside the official X / Y; never replaces them |
| Dates | `observed_on date`; `created_at` / `updated_at` set by the server (Africa/Lagos) | Clients send `YYYY-MM-DD`; no future dates; the client never sets the timestamps |
| Who / where from | `created_by`, `updated_by` (KLAES user id); `source` = `web` / `mobile` / `desktop` / `qgis` | Set by the server from the sign-in token, never taken from the client |
| Resend protection | `client_uuid nvarchar(40)` per row | A new id per change on the client; a resend with the same id returns the saved row, never a duplicate |
| Order | `sort_order` | Traverse order and beacon order, as entered |
| Delete | `deleted_at` (soft delete) | Deletes sync as tombstones; clients hide them; nothing is hard-deleted from a client |

### 5A.2 Field-by-field mapping

**Readings: `survey_its_observations`**

| Desktop / plugin field | API field | Column | Notes |
|---|---|---|---|
| Job | `its` (in the route) | `its_source` + `survey_its_id` / `cadastral_survey_job_id` / `survey_change_report_id`, `its_number`, `file_number` | Resolved by the server from the job (`SurveyItsAnchors`); raw ids are never sent |
| Line order | position in the list | `sort_order` | |
| From beacon | `from_station` | `from_station` | Normalised (5A.1) |
| To beacon | `to_station` | `to_station` | Must differ from From |
| Bearing | `bearing` (decimal degrees) | `observed_bearing` | From N/S·E/W or whole circle |
| Distance | `distance` | `observed_distance` | |
| Date observed | `observed_on` | `observed_on` | Today or earlier |
| Observer | from the token | `observer` | The user's name |
| Remarks | `remarks` | `remarks` | |
| Coordinate system | `coord_system` | **`coord_system`: new column, see 5A.4** | Today only written into `remarks` |
| Change id | `client_uuid` | `client_uuid` | |

**List of Coordinates: `survey_its_coordinates`**

| Desktop / plugin field | API field | Column | Notes |
|---|---|---|---|
| Beacon Number | `beacon_id` | `beacon_id` | One per job (case and spaces ignored); an existing one is **kept, not overwritten** |
| X (Easting) | `easting` | `easting` | 3 decimals |
| Y (Northing) | `northing` | `northing` | 3 decimals |
| Elevation | `elevation` | `elevation` | Optional |
| GPS fix | `gps_latitude`, `gps_longitude`, `gps_accuracy_m` | same names | GPS walk / Record GPS here |
| Coordinate system | `coord_system` | **`coord_system`: new column, see 5A.4** | |
| Remarks, change id, order | `remarks`, `client_uuid` | `remarks`, `client_uuid`, `sort_order` | |

**Survey Plan uploads: `survey_plan_uploads`** (read and check; uploading stays on the web for now)

| Field | Column | Notes |
|---|---|---|
| Upload ref, plan type, unit | `upload_ref`, `plan_type`, `unit` | Read only |
| Plot No., District, LGA, Location | `plot_no`, `district`, `lga`, `location` | Shown in this order everywhere |
| Coordinate system | `coord_system` | Already exists |
| Map sheets | `map_sheet_metric`, `map_sheet_imperial` | `App\Services\Survey\MapSheets` lists |
| Reference beacon(s) | `survey_plan_reference_beacons` | |
| Stage / status | `stage`, `status` | Changed only by the web workflow |

The Beacon Register, the Kano GIS layers and the imagery are **read only** on the desktop and the plugin.

### 5A.3 How sync works

1. **Pull:** `GET /jobs/{its}` returns every row with its `id`, `updated_at` and `client_uuid`; `GET …?since=` returns only the rows changed since the last pull, deletions included.
2. **Work offline:** changes go to the local outbox, each with a new `client_uuid` and the `updated_at` the client last saw.
3. **Push:** the server runs the **same validation services** as the web and the phone (one copy of each rule). If the row's `updated_at` is newer than the one sent, the server answers **conflict** with both versions; the client shows them side by side and the user chooses. Nothing is overwritten silently.
4. **Confirm:** the server returns the saved rows, and the client replaces its local copy with them. The client therefore always ends up holding exactly what KLAES stored, rounding included.
5. **The web and the phone** keep writing as they do now; their changes reach the desktop at its next pull.

### 5A.4 Gaps to close before the build (need approval: these are database changes)

1. **Add `coord_system nvarchar(10) NULL`** to `survey_its_observations` and `survey_its_coordinates` (an additive migration; existing rows stay NULL, meaning "not recorded"). The web, phone and desktop then save the system with every value instead of writing it into `remarks`.
2. Make the **web** Readings and List of Coordinates forms send a `client_uuid` too, so all four clients have the same resend protection (the phone already does).
3. Set `source` the same way everywhere: `web`, `mobile`, `desktop`, `qgis`.
4. One **shared set of survey-maths test cases** (parcel 234 and others) run against the web JS, the phone JS and the Python, so all three give the same answers.

### 5A.5 Proving that the values sync correctly

- **Round trips:** enter on the desktop → read on the web; enter on the web → read on the desktop; enter on the phone → read on the desktop and the plugin. Every field is compared to the last decimal.
- **Conversions:** one traverse in each of the 5 systems; N/S·E/W against whole circle; the 7-digit X switch-back; D/M/S edge cases (59′ 59.9″, 0°, 90°, 359°).
- **Conflicts:** the same row edited on the web and on the desktop; deleted on one and edited on the other.
- **Sync check screen** in the app: per job, local and KLAES row counts plus a checksum (count, and sums of E, N, bearings and distances). A mismatch lists exactly which rows differ.
- **Sync log** on the server (`desktop_sync_log`): user, device, client, route, `client_uuid` and result, viewable by Super Admin.

---

## 6. Phases

| Phase | Content | Done when |
|---|---|---|
| 0. Prepare | Repo, Python 3.12 environment, coding rules, icon brief; ask IT about an internal signing certificate (free) | Repo builds an empty window into an installer |
| 1. API | First close the 5A.4 gaps (with approval); then Sanctum login + OTP, `/me`, `/jobs`, `/jobs/{its}`, beacon search, version check; tests | Postman / pytest calls pass against dev (`klas-dev`) |
| 2. Core | Survey maths module + tests; coordinate systems; SQLite cache + outbox | Parcel 234 traverse closes 0.000 and matches the web to the millimetre |
| 3. Map | MapLibre in Qt; Beacon Register, Kano GIS layers, imagery, big GeoTIFF via the local tile server | A 6 GB TIFF pans smoothly on a normal office PC |
| 4. Screens | Sign-in, job list, job workspace (traverse, beacons, map), import / export | Staff can do a full job without the web |
| 5. Sync | Push / pull, conflicts, offline outbox, sync check screen, the value-mapping tests in 5A.5 | Offline work arrives correctly, and every round trip matches to the last decimal |
| 6. Package | PyInstaller + Inno Setup + signing + auto-update | Clean Windows 10 and 11 VMs install, run and update |
| 7. Pilot | 2–3 SG office users for 2 weeks; fix list | Sign-off by the user |
| 8. Roll-out | Download page, short guide (PDF + video), support process | |
| P. QGIS test plugin (optional) | The small plugin on the same API (2.4); 1–2 users compare it with the app | A decision: keep QGIS as an option, or the app only |

Work happens on **dev first** (`C:\xampp\klas-dev`, its own DBs). The API reaches production only as a separately
authorised deploy, per `DEVELOPMENT.md`.

---

## 7. Testing

- **Maths:** pytest cases shared with the web (parcel 234; whole-circle vs quadrant bearings; anticlockwise warning; NTM ↔ UTM conversions against ArcMap values).
- **Formats:** round-trip import / export of each format; File GDB written by the app opens in ArcGIS / QGIS.
- **Big data:** the 6 GB and 12 GB Kano TIFFs; 41,180 parcels.
- **Sync and values:** everything in 5A.5: round trips between the web, phone, desktop and plugin; resend (no duplicates); conflict (the web edited meanwhile); offline outbox; expired token.
- **Install:** clean Windows 10 / 11 VMs, a non-admin user, upgrade from the previous version, uninstall; check the unsigned / internally-signed install message is clear to users.
- **Security:** token theft (revoke works), no password stored, HTTPS only.

---

## 8. Risks

| Risk | Mitigation |
|---|---|
| GDAL / PROJ packaging problems | Python 3.12 + tested wheels; clean-VM install test in every build |
| Large installer | One-folder build; offer imagery as a separate optional download |
| SmartScreen "unknown publisher" warning at install | Free: internal certificate trusted on office PCs via Group Policy, or users click More info → Run anyway once. A paid certificate only if outside users need it later |
| Two interfaces drift apart (web vs desktop) | The server holds the rules; the maths tests are shared; one change log |
| Production DB load from sync | Pull only changes since the last sync; small pages; rate limits |
| App on many PCs, old versions | Minimum version enforced by `/app/version` |
| The server has no HTTPS redirect (memory: SSL via win-acme, no forced HTTPS) | The API refuses plain HTTP |

---

## 9. Decisions needed from the user

1. App name and icon artwork (who designs: in-house or VMAL).
2. Who uses version 1: SG office only, or MLPP-SG private surveyors too.
3. Signing (no cost): use an internal certificate from the office domain (IT to confirm), or no signing at first. A paid certificate is **not required** (user, 2026-11-16: no budget for it).
4. Where the installer is downloaded from: KLAES (`app.klaes.ng/downloads`) or elsewhere.
5. The new repository name and owner.
6. Whether the desktop may also **approve** or only **enter and send** (approval staying on the web).
7. Approval for the database changes in 5A.4 (`coord_system` on readings and coordinates; `client_uuid` from the web forms).
8. The QGIS test plugin (2.4): when, and which 1–2 users try it.

---

## 10. Before the build starts

The user has more Survey updates and corrections coming for the web Survey Plan card and the Field Observations phone
app. Those come first. When they are done, return to this plan: confirm section 9, then start Phase 0.
