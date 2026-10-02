<?php

/**
 * Requester seniority hierarchy for File Search Requests.
 *
 * When multiple people request the same file, the front desk honors the most
 * senior requester at the log step (/create-file-tracker). Priority is derived
 * from the Receiving Officer chosen on a Digital File Request (the senior person
 * the file is being requested FOR) — matched case-insensitively (substring) against
 * the keys below. Higher weight = honored first.
 *
 * Keys may be a designation/rank (matched against the officer's name or users.rank)
 * — refine these to your Management hierarchy. 'default' applies when nothing matches
 * (e.g. front-desk / Quick Search requests with no receiving officer).
 */
return [
    // Office Priority Search (OFS) hierarchy. A logged-in user is an OFS requester
    // when their users.rank matches one of these titles; the matched weight sets how
    // their File Search Request is prioritised (higher = honored first).
    //
    // ORDER MATTERS: FileSearchRequest::priorityFor() returns the FIRST substring
    // match, so the more specific titles ("Assistant/Deputy Director") MUST be listed
    // before the broader "Director", or e.g. "Deputy Director Deeds" would wrongly
    // match "Director".
    'ranks' => [
        'Honorable Commissioner' => 100,   // HC
        'Hon. Commissioner'      => 100,
        'Commissioner'           => 100,
        'Permanent Secretary'    => 90,    // PS
        'Assistant Director'     => 60,    // before "Director"
        'Deputy Director'        => 70,    // before "Director"
        'Director'               => 80,    // Directors
        'Officer'                => 10,    // Officers
    ],

    'default' => 0,

    // Canonical rank options for the user create/edit forms, most senior first.
    // Each must substring-match a key in 'ranks' above so FileSearchRequest::priorityFor()
    // resolves the saved users.rank to the right seniority weight.
    'options' => [
        'Honorable Commissioner',
        'Permanent Secretary',
        'Director',
        'Deputy Director',
        'Assistant Director',
        'Officer',
    ],
];
