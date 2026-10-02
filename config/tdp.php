<?php

/*
|--------------------------------------------------------------------------
| GIS → Title Deed Plan (TDP) store — KANGIS
|--------------------------------------------------------------------------
|
| The Title Deed Plans live on the GIS server in one folder per LGA:
|
|     C:\Kano State\TDP\[LGA]\<file number>.pdf
|
| The LGA folders are the 44 Kano LGAs held in `lgas` (App\Models\Lga).
|
| Ported from the ALAES module of the same name, with three deliberate
| differences: the root is Kano rather than Abia, the roles are the ST /
| KANGIS names used in this system, and the library carries its own file
| number normalisation instead of depending on ALIS's
| LegacyFileNumberNormalizer, which does not exist here.
|
| The store is the BACK PAGE of the ST Certificate of Occupancy. Every screen
| degrades to a "not configured / not reachable" panel when the folder is
| missing; nothing here may raise, because on a development machine the
| folder does not exist.
|
*/

return [

    // Absolute path to the TDP store. Empty means "not configured".
    'root' => env('TDP_ROOT_PATH', 'C:\Kano State\TDP'),

    // File types the library will list, preview and accept.
    'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'tif', 'tiff'],

    // Upload cap in kilobytes (25 MB by default — scanned plans are large).
    'max_upload_kb' => (int) env('TDP_MAX_UPLOAD_KB', 25600),

    // Writing back to the GIS server can be switched off entirely, leaving a
    // read-only library. The upload card disappears and the POST is refused.
    'uploads_enabled' => filter_var(env('TDP_UPLOADS_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    // Rows per page in the search results.
    'per_page' => (int) env('TDP_PER_PAGE', 25),

    // Hard ceiling on how many files one scan will read, so a mis-configured
    // root (say C:\) can never hang the page.
    'scan_limit' => (int) env('TDP_SCAN_LIMIT', 20000),

    // Suffix given to the copy kept when a plan is replaced:
    //   ST-KN-7655-KMC.2026-09-27-143000.bak.pdf
    'backup_suffix' => 'bak',

    /*
    | Who may use the module. Supper Admin always passes. 'view' opens the
    | library and the preview; 'manage' additionally uploads and replaces
    | plans.
    |
    | 'ST - Certificate' is included on purpose: the ST officer producing the
    | certificate has to see whether the back page has arrived, which is the
    | whole point of the TDP chip on the CofO workflow screen.
    */
    'roles' => [
        // SLTR - CofO: the SLTR CofO screen's "Find TDP" opens the library.
        'view' => ['GIS - Title Deed Plan', 'ST - Certificate', 'ST - e-Registry', 'SLTR - CofO'],
        'manage' => ['GIS - Title Deed Plan'],
    ],

    /*
    | Roles created by `php artisan tdp:install`: name => description.
    */
    'role_catalogue' => [
        'GIS - Title Deed Plan' => 'KANGIS: Title Deed Plan library — search, preview, upload and replace plans',
    ],

];
