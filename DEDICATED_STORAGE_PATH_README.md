# Dedicated Storage Path Notes

## Purpose
This project uses a dedicated storage root via `STORAGE_PATH` in `.env` so EDMS files can live outside the default Laravel `storage/` folder.

## Current Setup
- `.env`:
  - `STORAGE_PATH=F:\\storage`
- Expected EDMS base folders:
  - `F:\storage\app\public\EDMS\BLIND_SCAN`
  - `F:\storage\app\public\EDMS\SCAN_UPLOAD`

## How It Works
- Helper `file_storage_path()` resolves storage locations from Laravel config.
- `config/filesystems.php` uses `STORAGE_PATH` for:
  - `local` disk root
  - `public` disk root
  - `storage:link` target

## Important Rule
After changing `STORAGE_PATH` in `.env`, always refresh cached config:

```bash
php artisan config:clear
php artisan config:cache
php artisan storage:link
```

If queue workers are running, restart them so they load the new config.

## Common Errors and Meaning
- `BLIND_SCAN storage path is missing`
  - The app cannot find `.../app/public/EDMS/BLIND_SCAN` under the resolved storage root.
- `Blind scan folder not found for <file-number>`
  - Base path is visible, but the specific folder is not found in BLIND_SCAN registry directories.

## Quick Troubleshooting Checklist
1. Confirm `.env` has the correct value:
   - `STORAGE_PATH=F:\\storage`
2. Confirm path exists on host:
   - `F:\storage\app\public\EDMS\BLIND_SCAN`
3. Rebuild config cache:
   - `php artisan config:clear && php artisan config:cache`
4. Recreate symlink if needed:
   - `php artisan storage:link`
5. Confirm service/runtime can access `F:`:
   - Web server user must have permission and visibility to drive `F:`.
6. Confirm folder exists for target file number:
   - Example: `F:\storage\app\public\EDMS\BLIND_SCAN\Lands_Registry_Raw\CON-AG-2026-39`

## Notes
- A path may exist in your file explorer but still fail in the web app if the service account cannot access that drive.
- UNC paths can be safer than mapped drive letters for server services.
