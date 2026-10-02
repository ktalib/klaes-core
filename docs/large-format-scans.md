# Large-format scans

The Master LFS Folder is a source directory shared by Scan Upload and PageTyping. Its path is displayed on the upload screen and in the browser. It does not affect registry, file number or file-type folders.

Set `MASTER_LFS_FOLDER="F:/Scans/LargeFormat"` in `.env` to use a different server folder or accessible network share. The PHP service account needs read access. Without that setting, the source is `storage/app/master-lfs`. Create the configured directory and put scans there. Run `php artisan config:clear` after changing the setting (or rebuild the configuration cache in deployments that use it).

Run the history migration when deploying:

```shell
php artisan migrate --path=database/migrations/2026_09_22_120000_create_scan_image_versions_table.php --force
```

The browser supports JPG, PNG, WebP, GIF and BMP images up to 500 MB. Convert TIFF scans to PNG or JPG before placing them here. It supports subfolders and filtering the current folder. The folder must be available to the server, not just the officer's workstation.

In the Select Indexed File dialog, Blind scan browses the normal scanning folder and Direct upload uses the workstation file picker. Browse Large-Format Scans is in the same dialog, beneath the upload methods, and adds an image to the upload pages. Each staged page has its own replacement action; the comparison must be confirmed before changing it. The source image is copied, never moved.

For uploaded pages, use Replace with LF Scan from the uploaded-file actions (choose the page), a folder page card, or the page preview. PageTyping has Replace with LF Scan and Add LF Scan in its page toolbar. Added LF pages are appended and remain pending page typing.

Replacement preserves scanning ID, uploader, file association, status, folio, order and all typing/classification fields. It writes a verified new scan and new typed copies before committing image pointers. Previous images stay at their old paths. `scan_image_versions` records the scanning ID, officer (`replaced_by`), time, source, previous scan path, new scan path and previous scan/typing metadata. Failed database changes roll back and remove the newly written files. A stale page must be refreshed before replacement.

For administrator recovery, locate the latest version for the scanning ID and verify the recorded old scan/typed files exist. In one database transaction, restore `scannings.document_path`, `original_filename` and `file_size` from `old_metadata.scan`, and restore each recorded typing row's `file_path` from `old_metadata.page_typings`. Do not overwrite current classification fields. Keep the replacement files and version record as history. There is no officer-facing restore button.

Validation: `php vendor/bin/phpunit tests/Feature/LargeFormatScanTest.php` uses an in-memory SQLite database and fake storage, without changing operational records.
