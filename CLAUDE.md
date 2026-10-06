# CLAUDE.md

KLAES (Kano Land Administration & Electronic System): a Laravel 9 / PHP 8.2 monolith served by XAMPP on Windows (`C:\xampp\htdocs\klas`, `http://app.klaes.ng`). It covers land records end to end: file numbers (MLS / KANGIS / ST / SLTR), file indexing, EDMS scanning and page typing, file tracking, CofO / ROFO generation, instrument and deeds registration, cadastral and survey, GIS, legal search, the one-stop shop (OSS), PHS, LAAS, payroll and attendance.

## This directory is live production

- **No staging copy and no backups.** Edits here are live, and there is no binlog, shadow copy or backup job. Treat every DB write, migration and file delete as unrecoverable. Confirm the target DB before running anything destructive.
- **Two DB connections.** `mysql` (`klaes`, localhost) is the default. **`sqlsrv`** (SQL Server `klas` @ 10.50.1.1) holds nearly all business data. Most models set `protected $connection = 'sqlsrv'`, `User` included, and the code calls `DB::connection('sqlsrv')` about 4,700 times. New code should do the same.
- **Migrations.** Always run them with `php artisan migrate --path=database/migrations/<file>.php`; many unrelated migrations are pending. `--pretend` does **not** protect sqlsrv, because it executes for real. Migrations that touch sqlsrv use `Schema::connection('sqlsrv')`. One-off SQL lives in `database/migrations/manual/`.
- **sqlsrv query-builder traps.** There is no `insertOrIgnore`, and `newQuery()` drops the table name.
- **Ad-hoc write testing.** Run the real code inside an outer `DB::connection('sqlsrv')->beginTransaction()`, then roll back. Fake `Mail` first: acting as a real user can email a real login OTP.
- `.env` contains **two MAIL blocks**, and the second one wins.

## Tests

- `php artisan test`, or `vendor/bin/phpunit --filter Name`. Suites are `tests/Unit` and `tests/Feature`.
- `.env.testing` points `mysql` at `klaes_testing`. `tests/CreatesApplication.php` aborts if the mysql DB is `klaes`/`klas`. This guard exists because of a 2026-09-24 incident in which `RefreshDatabase` wiped production.
- `sqlsrv` has **no test copy**. During tests it is swapped for `tests/Support/ReadOnlySqlServerConnection.php`, which allows reads (real data) and throws on every write. Don't weaken this guard; `tests/Feature/ProductionSafetyTest.php` enforces it.
- JS tests in `tests/js/*.test.js` are plain Node scripts (`node tests/js/<file>.test.js`). Some of them extract methods straight out of Blade files.

## Layout

- **Routes.** `app/Providers/RouteServiceProvider.php` loads `web.php`, `apps.php`, `apps2.php`, `app3.php`, `survey_module.php`, `cadastral_module.php`, `phs.php`, `online_legal_search.php`, `laas.php`, `land_registration.php`, `configurable_entries.php`, `bills_api.php` and `routes/mobile/*.php`. `web.php` also `require`s several smaller files. `* - Copy.php` files are dead and not loaded. Authenticated routes use the `['auth', 'XSS']` middleware; `XSS` also sets the locale and timezone.
- **Controllers** (~300) are in `app/Http/Controllers`, grouped by module subfolder. Business logic belongs in `app/Services` (~150 services) rather than in controllers. A few stray controllers sit directly in `app/Http/`.
- **Global helpers** are in `app/Helper/helper.php` (autoloaded), e.g. `getSettingsValByName()`.
- **Views** are Blade, in `resources/views/<module>/`. The main shell is `layouts/app.blade.php` with `admin.menu` (sidebar) and `admin.content`; pages use `@section`, `@push('scripts')` and `@push('styles')`. Page JS lives in plain files under `public/js/` (not bundled), plus Alpine.js, jQuery and DataTables from CDNs.
- **Tailwind is the v2.2.19 CDN** loaded in the layout. Tailwind v3 in `node_modules` / `webpack.mix.js` styles nothing, so v3-only utility classes silently do nothing.
- **Permissions.** Spatie roles/permissions plus a module system: `user_roles` are modules, view access comes from `assign_role`, and actions come from `module_permissions`. Audit commands include `AuditModulePermissions`, `UngatedModuleViews` and `UnmappedModuleRoutes`.
- **Scheduler.** See `app/Console/Kernel.php` (propid backfill/reconcile, OP serial integrity, drafts cleanup, attendance, reservations cleanup). About 120 artisan commands are in `app/Console/Commands`.
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

## Blade gotchas

- Module action checks use the custom `@canDo('Module', 'action') ... @elsecanDo ... @endcanDo` directive (defined in `AppServiceProvider`), not `@can`.
- Never put a directive name or a raw PHP open tag inside a Blade comment.
- Use the `@php ... @endphp` block form.

## Housekeeping

The repo root and `app/` contain a lot of debris: `*.bak*` files, `*.phpold*` files, `sync.ffs_db`, `* - Copy` dirs, one-off `fix_*.php` / `debug_*.php` scripts, and `klaes_prod_backup_*` dumps. Leave it alone unless asked, and don't model new code on it. Put temporary scripts in a scratch location, not the project root.
