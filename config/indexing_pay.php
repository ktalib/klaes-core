<?php

/*
|--------------------------------------------------------------------------
| Indexing piece-rate pay — defaults
|--------------------------------------------------------------------------
| The FINAL APPROVED RATE CARD — KLAES MDC INDEXING (Kunle, 2026-11-01).
| These are only the starting values: the live card is edited in
| System Admin > Configurable Entries > Indexing Pay and stored, effective-dated,
| in indexing_pay_settings. See docs/plans/INDEXING_PIECE_RATE_PAYROLL_PLAN.md.
|
| Amounts are whole naira.
*/

return [
    'defaults' => [
        // Base fee per file, paid to the user who indexed it.
        'base' => [
            'regular'     => 20,  // Regular Land File
            'sltr'        => 35,  // SLTR File
            'kangis_land' => 35,  // Land file inside a KANGIS bundle
            'kangis'      => 40,  // KANGIS File
            'itf'         => 35,  // In-transit File — dormant (user, 2026-11-01): stored, never applied
        ],

        // Per record, paid to the user who captured the record.
        'rates' => [
            'gr'    => 2,  // Ground Rent
            'bb'    => 2,  // Bill Balance
            'trans' => 3,  // Transaction
        ],

        // "GR/BB not applicable" to SLTR files on the approved card.
        'sltr_pays_gr_bb' => false,

        // The programme: November and December 2026, weekends included.
        'start_date' => '2026-11-02',
        'end_date'   => '2026-12-31',

        // Progress bars only; they never change pay.
        'daily_file_target' => 100,
        'monthly_target'    => 70000,

        // Who can earn: holders of one of these modules (users.assign_role) whose staff type
        // (users.staff_type_category) is one of these. MDC and MDCM (user, 2026-11-02).
        'eligible_modules' => ['Indexing'],
        'eligible_staff_types' => ['MDC', 'MDCM'],
        // ...or, instead of the module, one of these work stations (users.work_station): covers
        // Super Admins and others who index without holding the module (user, 2026-11-02).
        'eligible_work_stations' => ['File Indexing'],

        // Where an indexer sees progress and earnings.
        'show' => [
            'header'      => true,
            'profile'     => true,
            // Hidden for everyone for now (user, 2026-11-01); switch back on in Configurable Entries.
            'create_page' => false,
        ],
    ],

    // Seconds a user's summary is cached. A save on the indexing page asks for a fresh one.
    'cache_seconds' => 60,
];
