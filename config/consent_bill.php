<?php

/*
|--------------------------------------------------------------------------
| Consent bill — rates and fixed fees
|--------------------------------------------------------------------------
|
| The single place the consent bill's rates and fees are defined. The wizard
| displays these and the server recalculates from them on every save, so a
| tampered client total is discarded rather than trusted.
|
| Changing a value here affects bills computed from that point on. Bills
| already saved keep their own rates and amounts on the consent_applications
| row, so historical letters reprint with the figures they were issued under.
|
*/

return [

    // Percentages of the valuation amount (Section E, item 21A).
    'rates' => [
        'stamp_duty' => env('CONSENT_BILL_STAMP_DUTY_RATE', 3.0),
        'registration' => env('CONSENT_BILL_REGISTRATION_RATE', 5.0),
    ],

    // Flat amounts in naira, independent of the valuation.
    'fixed_fees' => [
        'processing' => env('CONSENT_BILL_PROCESSING_FEE', 12000),
    ],

    // Which valuation reports may be billed against. A report outside this set
    // is not silently skipped — the wizard reports that none is available.
    // 'Generated' is the only status valuation_reports currently uses; the list
    // stays configurable so an approval step can be added without code changes.
    'eligible_valuation_statuses' => ['Generated'],

];
