<?php

/*
|--------------------------------------------------------------------------
| Consent letter — letterhead, signatory and approved wording
|--------------------------------------------------------------------------
|
| Everything the consent bill letter prints that is not a figure from the bill.
| Kept out of the Blade template so the Ministry's wording, deadline and fees
| can change without touching layout.
|
| The clause_* keys override a clause wholesale. Leave one empty and the
| template composes that clause from the values below it.
|
| NOTE: the deadline, the daily penalty and the counterpart fee were carried
| over from the FCTA reference letter and translated to Kano naming. They have
| not been confirmed against Kano State's own schedule.
|
*/

return [

    'organisation_name' => 'Ministry of Land and Physical Planning, Kano State',
    'department_name' => 'Department of Deeds',
    'office_address' => 'No. 2 Dr Bala Muhammad Road, Nassarawa GRA, Kano State, Nigeria',

    'crest_asset' => 'assets/logo/Nigerian-Coat-of-Arms.png',
    'footer_logo' => 'assets/logo/klaes1.png',

    // A scan of the pre-printed stationery, laid under the letter so the layout
    // can be proofed on screen before it goes onto real paper. The artwork has a
    // header band across the top and a vertical spine down the left edge, which
    // is what .page-sheet's padding is sized to clear.
    //
    // The stationery laid under the letter so the layout can be proofed on
    // screen. A full URL is used as given; a bare path resolves against the
    // app's own public directory.
    //
    // Set this to '' when printing onto the real stationery: the paper already
    // carries the artwork, and printing it again lands it twice.
    //
    // Off: the letter prints onto the real stationery, which already carries
    // the artwork. Uncomment the line below only to proof alignment on screen.
    // 'letterhead_image' => 'https://i.ibb.co/G3rnsZJQ/bg.png',
    'letterhead_image' => '',

    // The same scan, used ONLY by the preview page when it is opened with
    // ?letterhead=1 (consent-applications/preview-demo?type=...&letterhead=1),
    // so the layout can be proofed against the stationery without switching
    // the background on for real letters.
    'letterhead_proof_image' => 'https://i.ibb.co/G3rnsZJQ/bg.png',

    'approving_authority' => 'the Honourable Commissioner',
    'fees_payable_label' => 'Fees Payable',

    // The symbol printed before every naira figure. The signed specimen of the
    // revised Assignment letter shows "#20,000,000.00" — which is either the
    // house convention or a font substituting for the naira sign. Left as the
    // naira sign until the Ministry confirms; set this to '#' to match the
    // specimen exactly.
    'currency_symbol' => '₦',

    // Left empty until an authorised name is supplied: the letter then prints
    // the signature rule and the office alone, never a placeholder name.
    'signatory_name' => 'ALH. ABDULJABBAR M. UMAR',
    'signatory_designation' => 'Honourable Commissioner',
    'ministry_name' => 'Ministry of Land and Physical Planning',
    'state_name' => 'Kano State',

    'registry_name' => 'Kano State Land Registry',
    'payee_name' => 'the Kano State Government',
    'payment_centre_name' => 'KANGIS',

    // The instrument the penalty clause names, by consent type: an Assignment
    // is perfected by a Deed of Assignment, a Gift by a Deed of Gift, a
    // Mortgage by a Deed of Mortgage. A type not listed falls back to the
    // Assignment wording.
    'deed_names' => [
        'Assignment' => 'Deed of Assignment',
        'Gift' => 'Deed of Gift',
        'Mortgage' => 'Deed of Mortgage',
        'Tripartite Mortgage' => 'Deed of Tripartite Mortgage',
    ],

    'deadline_days' => 84,
    'deadline_days_words' => 'eighty-four',
    'penalty_per_day' => 100,
    'penalty_per_day_words' => 'One Hundred Naira',
    'counterpart_fee' => 2000,
    'counterpart_fee_words' => 'Two Thousand Naira',

    // Point this at a verification page and the QR encodes that URL instead of
    // the letter's own identifiers.
    'verify_url' => env('CONSENT_LETTER_VERIFY_URL', ''),
    'qr_caption' => 'Scan to verify',

    // Set true to print onto pre-printed letterhead stationery: the drawn
    // heading is suppressed and blank space reserved in its place.
    'preprinted_letterhead' => false,

];
