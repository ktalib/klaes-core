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

    /*
    | Transaction types — chosen at the top of the consent wizard.
    |
    | Each type fixes the rates the bill is raised under, the authority stamp
    | duty is paid to, and the letter that prints. Stamp duty is NOT part of the
    | Ministry's bill total: the letter acknowledges registration + processing
    | and then directs the applicant to pay stamp duty to the payee separately.
    |
    | consent_type is the older Assignment / Gift / Mortgage classification that
    | the deeds pipeline, instruments and reports still read. It is derived from
    | the transaction type so those consumers keep working unchanged.
    |
    | Rates are percentages of the assessed amount; processing is in naira.
    | A consent saved without a transaction type falls back to 'rates' and
    | 'fixed_fees' above.
    */
    'transaction_types' => [
        'individual_to_individual' => [
            'label' => 'Individual to Individual',
            'consent_type' => 'Assignment',
            'registration_rate' => 5.0,
            'processing_fee' => 12000,
            'stamp_duty_rate' => 3.0,
            'payee' => 'KIRS',
            'template' => 'consent_assignment_2026',
        ],
        'individual_to_company' => [
            'label' => 'Individual to Company',
            'consent_type' => 'Assignment',
            'registration_rate' => 5.0,
            'processing_fee' => 12000,
            'stamp_duty_rate' => 1.5,
            'payee' => 'FIRS',
            'template' => 'consent_assignment_2026',
        ],
        // Same rates, payee and letter as Individual to Company: any
        // assignment with a company on either side pays 1.5% to FIRS.
        'company_to_individual' => [
            'label' => 'Company to Individual',
            'consent_type' => 'Assignment',
            'registration_rate' => 5.0,
            'processing_fee' => 12000,
            'stamp_duty_rate' => 1.5,
            'payee' => 'FIRS',
            'template' => 'consent_assignment_2026',
        ],
        'company_to_company' => [
            'label' => 'Company to Company',
            'consent_type' => 'Assignment',
            'registration_rate' => 5.0,
            'processing_fee' => 12000,
            'stamp_duty_rate' => 1.5,
            'payee' => 'FIRS',
            'template' => 'consent_assignment_2026',
        ],
        'mortgage' => [
            'label' => 'Mortgage',
            'consent_type' => 'Mortgage',
            'registration_rate' => 2.0,
            'processing_fee' => 32000,
            'stamp_duty_rate' => 0.375,
            'payee' => 'FIRS',
            'template' => 'consent_mortgage_2026',
        ],
        // A gift is billed and worded exactly as an individual-to-individual
        // assignment; it keeps its own consent_type for the pipeline.
        'gift' => [
            'label' => 'Gift',
            'consent_type' => 'Gift',
            'registration_rate' => 5.0,
            'processing_fee' => 12000,
            'stamp_duty_rate' => 3.0,
            'payee' => 'KIRS',
            'template' => 'consent_assignment_2026',
        ],
    ],

    /*
    | Consent types that already settle the transaction type.
    |
    | A Gift or a Mortgage needs no further choice, so the wizard locks the
    | Transaction Type to the value here and only offers the four party options
    | (Individual / Company) for an Assignment. The server applies the same map,
    | so a Gift is always billed as 'gift' whatever the form posted.
    */
    'consent_type_transactions' => [
        'Gift' => 'gift',
        'Mortgage' => 'mortgage',
        'Tripartite Mortgage' => 'mortgage',
    ],

    // The authorities stamp duty is directed to, as the letter names them.
    'payees' => [
        'KIRS' => 'Kano State Board of Internal Revenue (KIRS)',
        'FIRS' => 'Federal Inland Revenue Service (FIRS)',
    ],

    // Which valuation reports may be billed against. A report outside this set
    // is not silently skipped — the wizard reports that none is available.
    // 'Generated' is the only status valuation_reports currently uses; the list
    // stays configurable so an approval step can be added without code changes.
    'eligible_valuation_statuses' => ['Generated'],

];
