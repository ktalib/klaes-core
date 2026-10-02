<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Digital File Request — Raw Source Folder
    |--------------------------------------------------------------------------
    | Absolute path on the server (or network share) where scanned/raw digital
    | files are stored. The system will look for a subfolder matching the
    | requested file number (case-insensitive) inside this directory.
    |
    | Example: C:\Scans\DigitalArchive   or   /mnt/nas/raw_files
    |
    */
    'raw_folder' => env('DFR_RAW_FOLDER_PATH', ''),

    /*
    |--------------------------------------------------------------------------
    | Temporary Storage
    |--------------------------------------------------------------------------
    | Disk and base-directory for temporary user copies.
    | Full path will be: storage/app/{temp_base}/{user_id}/{file_no}/
    |
    */
    'temp_disk' => 'local',
    'temp_base' => 'DFR',

    /*
    |--------------------------------------------------------------------------
    | Access Duration (working days)
    |--------------------------------------------------------------------------
    | How many working days (Monday–Friday) a digital copy remains accessible
    | before it is automatically cleaned up.
    |
    */
    'access_days' => (int) env('DFR_ACCESS_DAYS', 5),

    /*
    |--------------------------------------------------------------------------
    | Allowed File Extensions
    |--------------------------------------------------------------------------
    | File types that will be copied from the raw folder.
    |
    */
    'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'tif', 'tiff'],

];
