<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Registry Source — Scan Base Path
    |--------------------------------------------------------------------------
    | Absolute path on the server where the registry upload folders live. Each
    | registry below is a sub-folder of this path. On production this is the
    | EDMS upload root (e.g. F:\storage\app\public\EDMS\UPLOAD); locally it
    | defaults to the project's public storage so the same code works in dev.
    |
    | Layout scanned:  {base_path}/{folder}/{file_number}/{category}/{image…}
    | e.g.             …/SLTR_Registry/SLTR-220944/A4/scan-001.jpg
    */
    'base_path' => env('REGISTRY_SOURCE_BASE_PATH', storage_path('app/public/EDMS/UPLOAD')),

    /*
    |--------------------------------------------------------------------------
    | Public URL Prefix
    |--------------------------------------------------------------------------
    | Path of the upload root RELATIVE to the "public" storage disk root. Used
    | to build browser URLs (Storage::disk('public')->url(prefix/…)). The files
    | live under storage/app/public/EDMS/UPLOAD, served at /storage/EDMS/UPLOAD.
    */
    'public_prefix' => env('REGISTRY_SOURCE_PUBLIC_PREFIX', 'EDMS/UPLOAD'),

    /*
    |--------------------------------------------------------------------------
    | Allowed Document Extensions
    |--------------------------------------------------------------------------
    | Only files with these extensions are imported as registry documents.
    | "image_extensions" drives the inline image preview vs. a document link.
    */
    'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tif', 'tiff', 'pdf'],
    'image_extensions'   => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tif', 'tiff'],

    /*
    |--------------------------------------------------------------------------
    | Registries (lookup definitions)
    |--------------------------------------------------------------------------
    | The source registries seeded into the `registry_sources` table. "folder"
    | is the directory name under base_path. "code" is a short stable key used
    | to match a user's selected registry to its source. "aliases" are extra
    | strings (matched case-insensitively, contains) used to resolve the
    | physical_registries dropdown value to this source.
    |
    | NOTE: The existing FileIndexing-based digital library remains the source
    | for the Land registries — these definitions only cover the registries
    | whose digital copies are stored as raw folders on disk.
    */
    'registries' => [
        [
            'name'    => 'SLTR Registry',
            'code'    => 'SLTR',
            'folder'  => 'SLTR_Registry',
            'aliases' => ['sltr'],
        ],
        [
            'name'    => 'Cadastral Registry',
            'code'    => 'CAD',
            'folder'  => 'Cadastral_Registry',
            'aliases' => ['cadastral'],
        ],
        [
            'name'    => 'KANGIS Registry',
            'code'    => 'KANGIS',
            'folder'  => 'KANGIS_Registry',
            'aliases' => ['kangis'],
        ],
        [
            'name'    => 'Physical Planning Registry',
            'code'    => 'PP',
            'folder'  => 'Physical_Planning_Registry',
            'aliases' => ['physical planning', 'physical_planning', 'planning'],
        ],
    ],

];
