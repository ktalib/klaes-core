<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Reference dataset for canonical file numbers
    |--------------------------------------------------------------------------
    |
    | The FastAPI importer ships with a curated JSON reference that lists every
    | valid catalog, category, and sample file number. We reuse that file so
    | Laravel QC reaches parity out of the gate.
    |
    */
    'correct_fileno_path' => env(
        'CSV_IMPORTER_CORRECT_FILENO',
        base_path('folder_watcher/static/correct_fileno.json')
    ),

    /*
    |--------------------------------------------------------------------------
    | Catalog cache TTL
    |--------------------------------------------------------------------------
    |
    | The catalog does not change frequently, so we keep a short-lived cache
    | in memory to avoid re-reading and decoding the JSON file for every
    | validation request.
    |
    */
    'catalog_cache_ttl' => (int) env('CSV_IMPORTER_CATALOG_CACHE_TTL', 3600),

    /*
    |--------------------------------------------------------------------------
    | Database connection used for grouping lookups
    |--------------------------------------------------------------------------
    |
    | Tracking IDs and registry metadata must come from the grouping table.
    | Configure the connection name here so deployments that split reads and
    | writes can point to the appropriate SQL Server instance.
    |
    */
    'grouping_connection' => env('CSV_IMPORTER_GROUPING_CONNECTION', 'sqlsrv'),

    /*
    |--------------------------------------------------------------------------
    | Required role for CSV import modules
    |--------------------------------------------------------------------------
    |
    | Every importer (File Number, File Indexing, PRA, PIC, File History) is
    | restricted to System Admin users. The guard service uses this value to
    | assert access before any import logic runs. Set CSV_IMPORTER_ENFORCE_ROLE=false
    | to temporarily relax the enforcement during testing.
    |
    */
    'enforce_role' => (bool) env('CSV_IMPORTER_ENFORCE_ROLE', false),
    'required_role' => env('CSV_IMPORTER_REQUIRED_ROLE', 'System Admin'),
];
