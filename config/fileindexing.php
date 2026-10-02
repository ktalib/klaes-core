<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Legacy edit form
    |--------------------------------------------------------------------------
    |
    | /fileindexing/{id}/edit renders the create form in update mode
    | (FileIndexUpdatePageController). Set this true to fall back to the old
    | resources/views/fileindexing/edit.blade.php screen for everyone.
    |
    | Note: .env changes do not survive a production code upload, so the
    | per-request escape hatch is the query parameter, which always works:
    |
    |     /fileindexing/{id}/edit?legacy=1
    |
    */

    'legacy_edit_form' => env('FILEINDEX_LEGACY_EDIT_FORM', false),

    /*
    |--------------------------------------------------------------------------
    | Demo fill (TEST DATA — never for production)
    |--------------------------------------------------------------------------
    |
    | Adds a "Fill demo data" button to the Create File Index form that loads a
    | REAL, still-unindexed grouping row (so its file number and tracking ID are
    | genuine) and pads out the human fields — holder names, address, NIN, phone —
    | with invented local sample data, so a tester is not hand-typing forty fields
    | for every run.
    |
    | TWO INDEPENDENT LOCKS, because the risk here is not a broken page: it is an
    | operator indexing a real file against invented particulars.
    |
    |   1. FILEINDEX_DEMO_FILL must be explicitly true. Absent or 0 => off.
    |   2. APP_ENV must not be `production`. This one cannot be overridden from
    |      .env, so the feature stays off on production even if the flag is copied
    |      there by accident.
    |
    | Note: .env is gitignored, so a production code upload never carries the flag
    | with it — the default here (false) is what production actually gets.
    |
    | The pool is deliberately narrow (one registry, one land use) so demo records
    | are recognisable as a group and easy to clean up afterwards.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Years that may not be indexed
    |--------------------------------------------------------------------------
    |
    | File indexing captures the paper backlog. A file numbered in one of these
    | years is raised by the file-number generation module with its particulars
    | already captured, so indexing one here would open a second, thinner record
    | for a file the registry already holds.
    |
    | The File Number selector on the indexing form refuses a number carrying one
    | of these years and says why. Only the registry formats that actually carry a
    | year are affected -- MLS (IND-2026-2222), SIT, DCIV/LPCC and ST. KANGIS,
    | New KANGIS, SLTR, Old MLS (KN) and GKN put a serial where the year would
    | otherwise sit, so "KN 2026" is a serial, not a 2026 file, and stays indexable.
    |
    | Set FILEINDEX_NON_INDEXABLE_YEARS to a comma-separated list to change the
    | years, or to an empty string to lift the restriction entirely.
    |
    */

    'non_indexable_years' => array_values(array_filter(
        array_map(
            static fn ($year) => (int) trim($year),
            explode(',', (string) env('FILEINDEX_NON_INDEXABLE_YEARS', '2026'))
        )
    )),

    'demo_fill' => [
        'enabled' => env('FILEINDEX_DEMO_FILL', false),

        // Which unindexed grouping rows may be handed out. Matched exactly
        // against grouping.registry / grouping.landuse.
        'registry' => env('FILEINDEX_DEMO_FILL_REGISTRY', 'Lands Registry'),
        'land_use' => env('FILEINDEX_DEMO_FILL_LAND_USE', 'INDUSTRIAL'),
    ],

];
