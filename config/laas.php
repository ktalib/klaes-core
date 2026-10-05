<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Portal accounts opened by File Commissioning
    |--------------------------------------------------------------------------
    |
    | When a file is commissioned — Land (MLPP File Number Generator) or OSS —
    | the applicant gets a LAAS Portal account holding one application per
    | commissioned file, and an SMS with the username and a temporary password.
    | See App\Services\Laas\LaasCommissioningAccountService.
    |
    | `enabled` switches the whole hook off (commissioning itself is unaffected).
    | `sms` keeps the accounts but stops the login text, e.g. while the SMS
    | wallet is empty; the office can then hand the details over in person.
    |
    */

    'commissioning_accounts' => [
        'enabled' => env('LAAS_COMMISSIONING_ACCOUNTS', true),
        'sms'     => env('LAAS_COMMISSIONING_ACCOUNT_SMS', true),

        // Generated when the officer leaves the email blank. Must stay a domain
        // config('phone_verification.placeholder_email_domains') treats as a
        // placeholder, so the login OTP never tries to email it.
        'placeholder_email_domain' => 'portal.klaes.local',
    ],

    /*
    |--------------------------------------------------------------------------
    | Folio
    |--------------------------------------------------------------------------
    |
    | Folio documents held back from applicants, by key (see
    | App\Services\Laas\LaasFolioService): neither listed nor served. Remove a
    | key to bring the document back.
    |
    */

    'folio' => [
        'hidden' => ['tracking-sheet'],
    ],

];
