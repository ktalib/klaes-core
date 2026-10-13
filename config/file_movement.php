<?php

/*
|--------------------------------------------------------------------------
| File Movement (Department) — which office a user logs files for
|--------------------------------------------------------------------------
|
| A normal user never chooses their office: it comes from the Rank and
| Department on their user account (create-user card), resolved in this order.
| Super admins alone get an office picker. A user who resolves to nothing sees
| "no office assigned" and cannot log files.
|
|   1. user_offices        — per-user override, by users.id. For accounts the
|                            rules below cannot place, e.g. the secretaries of
|                            the HC (SC1/SC2) and PS (SPS).
|   2. rank_offices        — a rank that is an office on its own.
|   3. department_offices  — the user's department: a Deputy Director goes to
|                            the deputy office, everyone else (the Director and
|                            the officers/secretaries working for them) to the
|                            Director's office.
|
| Keys of department_offices are departments.name; values are offices.office_code.
*/

return [

    'user_offices' => [
        // 123 => 'SPS',
    ],

    'rank_offices' => [
        'Honorable Commissioner' => 'HC',
        'Permanent Secretary'    => 'PS',
        'Director ICT'           => 'ICT',
        'Director SLTR'          => 'SLTR-DIR',
        'Deputy Director SLTR'   => 'SLTR-DD',
    ],

    'deputy_ranks' => ['Deputy Director'],

    'department_offices' => [
        'Land'               => ['director' => 'DIR-L',    'deputy' => 'DDLND'],
        'Survey'             => ['director' => 'SUR-G',    'deputy' => 'DSG'],
        'GIS'                => ['director' => 'GIS',      'deputy' => 'DD-GIS'],
        'GIS/Survey'         => ['director' => 'GIS',      'deputy' => 'DD-GIS'],
        'KANGIS'             => ['director' => 'DG',       'deputy' => 'DG'],
        'Account/Finance'    => ['director' => 'DDFIN',    'deputy' => 'DDFIN'],
        'Deeds'              => ['director' => 'DEEDS',    'deputy' => 'DD-DEED'],
        'Physical Planning'  => ['director' => 'PPD',      'deputy' => 'DDPPD'],
        'Cadastral'          => ['director' => 'CAD',      'deputy' => 'DDCAD'],
        'Sectional Titling'  => ['director' => 'ST-DIR',   'deputy' => 'ST-DD'],
        'SLTR'               => ['director' => 'SLTR-DIR', 'deputy' => 'SLTR-DD'],
        'ICT'                => ['director' => 'ICT',      'deputy' => 'DDICT'],
        'DCIV'               => ['director' => 'DCIV',     'deputy' => 'DDCIV'],
        'PRS'                => ['director' => 'PRS',      'deputy' => 'DDPRS'],
        'AGS'                => ['director' => 'DAGS',     'deputy' => 'DAGS'],
        'Special Assignment' => ['director' => 'SD',       'deputy' => 'DDSD'],
        'Legal'              => ['director' => 'DL',       'deputy' => 'DDL'],
        // CSU, ALL, Public Enlightenment, Revenue: no office in `offices` yet.
    ],

    /*
    | Legacy hand-overs. A file sent to an office before this moment by anything
    | other than File Movement (Log a File, imports) was never received here, but
    | the office already has it. Such files are listed under "Held at my office",
    | not "Pending receipt", and can be sent on without receiving them first.
    | Hand-overs made after this moment wait for receipt as usual. Display only:
    | no stored log entry is changed until the file is sent on.
    */
    'legacy_pending_before' => '2026-10-13T04:37:00+01:00',

];
