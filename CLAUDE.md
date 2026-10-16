# CLAUDE.md

KLAES (Kano Land Administration & Electronic System): a Laravel 9 / PHP 8.2 monolith served by XAMPP on Windows (`C:\xampp\htdocs\klas`, `http://app.klaes.ng`). It covers land records end to end: file numbers (MLS / KANGIS / ST / SLTR), file indexing, EDMS scanning and page typing, file tracking, CofO / ROFO generation, instrument and deeds registration, cadastral and survey, GIS, legal search, the one-stop shop (OSS), PHS, LAAS, payroll and attendance.

## This directory is live production

- **Edits here are live.** SQL Server `klas` has full `.bak` backups (06:00 and 18:00 since 2026-10-14) on `\\Dc-01\dbbackuup200261003`, but a restore rolls back the whole DB, so still back up the affected rows before a write. MySQL `klaes` and the files on disk have no backup, binlog or shadow copy, so treat writes to them as unrecoverable. Confirm the target DB before running anything destructive.
- **Build in the dev environment instead (since 2026-10-07).** `C:\xampp\klas-dev` is a separate clone of `github.com/ktalib/klaes-core`, served at `http://127.0.0.1:8088` (start it with `start-dev.ps1`). It uses its own DBs, MySQL `klaes_dev_20261007` and SQL Server `klas_dev_20261007` on 10.50.1.1. Those DBs hold real snapshot data and share hardware with production. In dev, mail/OTP goes to the log and the `F:\storage` archive is absent. Its `DEVELOPMENT.md` and `AGENTS.md` describe the workflow: feature branch, push, merge to `main`, then deploy to production only as a separately authorised step. Never copy the dev folder or either `.env` across.
- **Production has uncommitted work.** It is not a clean checkout of `main`. Reconcile before any pull, and never `git reset --hard`.
- **Two DB connections.** `mysql` (`klaes`, localhost) is the default. **`sqlsrv`** (SQL Server `klas` @ 10.50.1.1) holds nearly all business data. Most models set `protected $connection = 'sqlsrv'`, `User` included, and the code calls `DB::connection('sqlsrv')` about 5,500 times. New code should do the same.
- PHP CLI is `C:\xampp\php\php.exe` (8.2). Composer pins `"php": "^8.0.2"`, so avoid 8.3+ syntax.
- **Migrations.** Always run them with `php artisan migrate --path=database/migrations/<file>.php`; many unrelated migrations are pending. `--pretend` does **not** protect sqlsrv, because it executes for real. Migrations that touch sqlsrv use `Schema::connection('sqlsrv')`. One-off SQL lives in `database/migrations/manual/`.
- **sqlsrv query-builder traps.** There is no `insertOrIgnore`, and `newQuery()` drops the table name.
- **Ad-hoc write testing.** Run the real code inside an outer `DB::connection('sqlsrv')->beginTransaction()`, then roll back. Fake `Mail` first: acting as a real user can email a real login OTP.
- `.env` contains **two MAIL blocks**, and the second one wins.

## Tests

- `php artisan test`, or `vendor/bin/phpunit --filter Name`. Suites are `tests/Unit` and `tests/Feature`.
- `.env.testing` points `mysql` at `klaes_testing`. `tests/CreatesApplication.php` aborts if the mysql DB is `klaes`/`klas`. This guard exists because of a 2026-09-24 incident in which `RefreshDatabase` wiped production.
- `sqlsrv` has **no test copy**. During tests it is swapped for `tests/Support/ReadOnlySqlServerConnection.php`, which allows reads (real data) and throws on every write. Don't weaken this guard; `tests/Feature/ProductionSafetyTest.php` enforces it.
- JS tests in `tests/js/*.test.js` are plain Node scripts (`node tests/js/<file>.test.js`). Some of them extract methods straight out of Blade files.
- `tests/browser/*.cjs` are Playwright checks run against headless Edge. They need `playwright` (plus `sweetalert2`, `jquery`, `datatables.net` as noted in each file) on `NODE_PATH`; none of these are in `package.json`.

## Layout

- **Routes.** `app/Providers/RouteServiceProvider.php` loads `api.php`, `web.php`, `apps.php`, `apps2.php`, `app3.php`, `survey_module.php`, `cadastral_module.php`, `phs.php`, `online_legal_search.php`, `laas.php`, `bills_api.php`, `land_registration.php`, `configurable_entries.php`, `vfs.php`, `tdp.php` and `routes/mobile/*.php`. `web.php` also `require`s `auth`, `session_lock`, `recertification_routes`, `file_numbers`, `file_decommissioning`, `mls_fileno`, `caveat`, `buyer_list`, `allocation_list` and `instrument_batch_fix`. Before adding a route, grep all of these files. `* - Copy.php` files are dead and not loaded. Authenticated routes use the `['auth', 'XSS']` middleware; `XSS` also sets the locale and timezone.
- **Controllers** (~390 files) are in `app/Http/Controllers`, grouped by module subfolder. Business logic belongs in `app/Services` (~220 services) rather than in controllers. A few stray controllers sit directly in `app/Http/`.
- **Global helpers** are in `app/Helper/helper.php` (autoloaded), e.g. `getSettingsValByName()`.
- **Views** are Blade, in `resources/views/<module>/`. The main shell is `layouts/app.blade.php` with `admin.menu` (sidebar) and `admin.content`; pages use `@section`, `@push('scripts')` and `@push('styles')`. Page JS lives in plain files under `public/js/` (not bundled), plus Alpine.js, jQuery and DataTables from CDNs.
- **Tailwind is the v2.2.19 CDN** loaded in the layout. Tailwind v3 in `node_modules` / `webpack.mix.js` styles nothing, so v3-only utility classes silently do nothing.
- **Permissions.** Spatie roles/permissions plus a module system: `user_roles` are modules, view access comes from `assign_role`, and actions come from `module_permissions`. Audit commands include `AuditModulePermissions`, `UngatedModuleViews` and `UnmappedModuleRoutes`.
- **Scheduler.** See `app/Console/Kernel.php`. It runs `propid:backfill` (01:00), `propid:reconcile-indexing` (01:30), `drafts:cleanup` (02:00), `dfr:cleanup-expired` (03:00), `op:serial-integrity` (03:15), `attendance:process` (05:30), `phs:low-balance-reminders` (07:00), `spa:trigger-second-service` (08:00) and `activity:auto-logout` (every 10 min). Production registers it via `setup_scheduler.ps1`; the dev clone has no scheduler or queue worker. About 120 artisan commands are in `app/Console/Commands`.
- **Docs and plans** are in `docs/` (`docs/plans`, `docs/guides`, `docs/handoff`). The many `*.md` reports at the repo root are historical notes, not specs.

## Domain conventions

- **File numbers** come from the global selector component and are never typed into free-text fields. When a file number is prefilled, the field locks. One file can have several `fileNumber` rows (the same number in different columns), so match across columns. Join `fileNumber` to `file_indexings` on the file-number **string**, not `file_number_id`; ST joins on `st_file_no`.
- **Decommissioning flags, it never deletes.** OSS `checkDuplicate` ignores `is_deleted`.
- `is_allocated` means "consumed by MLS", not "land allocated". Set it to 0 on import.
- Location is district + LGA (+ state) and never includes the plot number.
- Module uploads go through EDMS (a `scannings` row in SCAN_UPLOAD) so that Page Typing sees them.
- When porting screens to another module, copy the views. Don't parameterise the original.
- Ground rent is a unit rate (rate × plot size). Never sum stored `ground_rent`.
- Imports load every source row, including dirty ones.
- **Survey module.** It renders in the main KLAES shell. Never re-run `docs/_extract_survey_pages.ps1` or the other survey generator scripts, because they regenerate broken views.

- **Bulk actions on a listing use the sticky footer bar** (row checkboxes + a floating "N selected" bar), the pattern the user chose. Reuse `resources/views/deeds/parcel_update/partials/batch_check.blade.php` and `batch_decisions.blade.php`; the `batch-actions` skill (`.claude/skills/batch-actions/SKILL.md`) has the recipe. Rows carry their own menu conditions, already-decided rows are disabled, and a button opens only when every selected row is at that step.

## Blade gotchas

- Module action checks use the custom `@canDo('Module', 'action') ... @elsecanDo ... @endcanDo` directive (defined in `AppServiceProvider`), not `@can`.
- Never put a directive name or a raw PHP open tag inside a Blade comment.
- Use the `@php ... @endphp` block form.

## Housekeeping

The repo root and `app/` contain a lot of debris: `*.bak*` files, `*.phpold*` files, `sync.ffs_db`, `* - Copy` dirs, one-off `fix_*.php` / `debug_*.php` scripts, and `klaes_prod_backup_*` dumps. Leave it alone unless asked, and don't model new code on it. Put temporary scripts in a scratch location, not the project root.

## Development standards (user rules, 2026-10-10)

These apply app-wide, to new features and to any existing module you modify. Inspect first, reuse established patterns, make the smallest safe change, and don't redesign working screens.

1. **S/N column.** Every records table starts with an S/N column numbered by displayed row order (never the DB id), correct across pagination, search, filter and sort (e.g. `$rows->firstItem() + $loop->index`; for DataTables, a render based on `meta.row + meta.settings._iDisplayStart + 1`).
2. **Property order.** Wherever property details appear (tables, form summaries, documents): **Plot No., District, LGA, Location**, in that order.
3. **Capitalisation / normalisation.** Normalise text before saving, with a rule per field (names, places, free text), never blanket upper/title case. File numbers, acronyms, phone numbers, emails and other identifiers keep their exact format. Search and filters are case-insensitive. Normalising existing records is a data change: preview the affected rows and get approval first (no backups here).
4. **Record actions are a dropdown.** Row-level actions go in one dropdown action menu (icons + labels), not loose buttons/links. The trigger is a three-dots icon (lucide `more-vertical`, with `aria-label="Actions"`), never a text "Actions" button. It must open, position and dismiss correctly; watch table `overflow`, `z-index`, event propagation and DataTables redraws. Keep existing permission checks so users only see actions they may perform. Fix broken menus you come across in modules you touch.
5. **Backfill on selection.** When a file/property/applicant is picked, fill every common detail the records already hold (file no./title, owner, address, phone, gender, plot, district, LGA, location, ...) from the authoritative tables, reusing existing lookups. Handle gaps gracefully; allow manual entry only where the workflow permits; clear the previous record's values when the selection changes; save the backfilled values.
6. **Dropdowns and "Other".** Where users may need an unlisted value, offer exactly one "Other" option that reveals a required "specify" field (hidden/disabled otherwise), validated on save. Prevent duplicate options from case/whitespace variants; reuse existing lookup tables/config rather than new option sources, and check existing rows before inserting options.
7. **Inspect before implementing, report after.** Check code, schema, migrations, routes, views, JS and APIs; look for an existing (possibly broken) implementation; test validation, saving, editing, filters, pagination, permissions and errors; report files changed, DB changes, tests run and remaining issues.
