<?php

return [

    /*
    |--------------------------------------------------------------------------
    | File Location Registry Ranges
    |--------------------------------------------------------------------------
    | Drives the Quick Search & File Location module. Given a file number, the
    | FileLocationResolver parses it into a PREFIX and a 4-digit YEAR, then
    | matches the prefix here (LONGEST prefix first, so "CON-RES" wins over
    | "RES") and finds the range whose [from, to] year span contains the year.
    |
    | Each range declares:
    |   - zone     : "archive" (Digital Archive — scanned, with us, has a
    |                rack/shelf) or "pool" (Pool Office — within range but not
    |                yet processed; needs a physical search).
    |   - registry : human-readable physical registry label shown to the user.
    |   - from/to  : inclusive 4-digit year bounds.
    |
    | Source of truth: ranges supplied by KLAES Admin (RAW CHAT.md, 15/06/2026).
    | A file number whose prefix/year matches NOTHING here resolves to
    | REFER_TO_ORIGINAL_REGISTRY (never transferred to us).
    */

    'ranges' => [

        // ───────────────── Registry 1 — Digital Archive ─────────────────
        'RES'    => [
            ['zone' => 'archive', 'registry' => 'Registry 1', 'from' => 1981, 'to' => 1991],
            // 1992–2026 lives in the Pool Office (Registry 2).
            ['zone' => 'pool',    'registry' => 'Registry 2', 'from' => 1992, 'to' => 2026],
        ],
        'COM'    => [
            ['zone' => 'archive', 'registry' => 'Registry 1', 'from' => 1981, 'to' => 2026],
        ],
        'IND'    => [
            ['zone' => 'archive', 'registry' => 'Registry 1', 'from' => 1981, 'to' => 2026],
        ],
        'AG'     => [
            ['zone' => 'archive', 'registry' => 'Registry 1', 'from' => 1981, 'to' => 2026],
        ],
        'RES-RC' => [
            ['zone' => 'archive', 'registry' => 'Registry 1', 'from' => 1981, 'to' => 2026],
        ],
        'COM-RC' => [
            ['zone' => 'archive', 'registry' => 'Registry 1', 'from' => 1981, 'to' => 2026],
        ],
        'IND-RC' => [
            ['zone' => 'archive', 'registry' => 'Registry 1', 'from' => 1981, 'to' => 2026],
        ],
        'AG-RC'  => [
            ['zone' => 'archive', 'registry' => 'Registry 1', 'from' => 1981, 'to' => 2026],
        ],

        // ───────────────── Registry 2 — Pool Office ─────────────────
        'SIT'    => [
            ['zone' => 'pool', 'registry' => 'Registry 2', 'from' => 1981, 'to' => 2026],
        ],

        // ───────────────── Registry 3 — Digital Archive ─────────────────
        'CON-RES' => [
            ['zone' => 'archive', 'registry' => 'Registry 3', 'from' => 1981, 'to' => 2024],
            // CON-RES 2025–2026 is still in the Pool Office (Registry 3).
            ['zone' => 'pool',    'registry' => 'Registry 3', 'from' => 2025, 'to' => 2026],
        ],

        // ───────────────── Registry 3 — Pool Office ─────────────────
        'CON-COM'    => [
            ['zone' => 'pool', 'registry' => 'Registry 3', 'from' => 1981, 'to' => 2026],
        ],
        'CON-IND'    => [
            ['zone' => 'pool', 'registry' => 'Registry 3', 'from' => 1981, 'to' => 2026],
        ],
        'CON-AG'     => [
            ['zone' => 'pool', 'registry' => 'Registry 3', 'from' => 1981, 'to' => 2026],
        ],
        'CON-RES-RC' => [
            ['zone' => 'pool', 'registry' => 'Registry 3', 'from' => 1981, 'to' => 2026],
        ],
        'CON-COM-RC' => [
            ['zone' => 'pool', 'registry' => 'Registry 3', 'from' => 1981, 'to' => 2026],
        ],
        'CON-IND-RC' => [
            ['zone' => 'pool', 'registry' => 'Registry 3', 'from' => 1981, 'to' => 2026],
        ],
        'CON-AG-RC'  => [
            ['zone' => 'pool', 'registry' => 'Registry 3', 'from' => 1981, 'to' => 2026],
        ],
    ],

];

