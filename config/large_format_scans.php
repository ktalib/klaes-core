<?php

return [
    // Source only: selected images are copied into the file's existing EDMS layout.
    'folder' => env('MASTER_LFS_FOLDER', storage_path('app/master-lfs')),
    'max_bytes' => 512000 * 1024,

    // Ceiling on how many entries one folder may hand the picker. A drop folder
    // is meant to be browsed by eye; anything past this is a staging mistake or
    // a shared root, and sending it all would only stall the browser.
    'max_entries' => 2000,

    // Each module browses its own drop folder, so one module's staged images
    // can never be mistaken for another's. 'lfs' stays the default for the
    // Scan Upload and Page Typing workspaces that predate the split.
    'default_library' => 'lfs',
    'libraries' => [
        'lfs' => [
            'label' => 'Master LFS Folder',
            'folder' => env('MASTER_LFS_FOLDER', storage_path('app/master-lfs')),
        ],
        // File Archive page restores: ordinary document pages whose scanned
        // image went missing, recovered from the raw scans kept on the records
        // server. That share is laid out one folder per file number, with the
        // paper size below it:
        //
        //   \\10.50.1.1\land_registry_raw\RES-1981-17\A4\IMG_0001.jpg
        //
        // Its root holds >130,000 folders, so it is never listed: browsing
        // starts inside the folder belonging to the file being repaired.
        'archive' => [
            'label' => 'Archive Scan Folder',
            'folder' => env('ARCHIVE_SCANS_FOLDER', storage_path('app/archive-scans')),
            'layout' => 'file_number',
            'browse_root' => false,
            // Shown in place of the real path: operators do not need the
            // records server's address, and it is too long for the sidebar.
            'display' => 'Raw scans (records server)',
        ],
    ],
];
