<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'ai_folder_search' => [
        'url' => env('AI_FOLDER_SEARCH_URL', 'http://10.50.1.2:7000/'),
    ],

    'google_maps' => [
        // Used by the File Indexing "Apply & Pin on Map" geocoder. Override in
        // .env with GOOGLE_MAPS_API_KEY once billing/API-restrictions are set up.
        'key' => env('GOOGLE_MAPS_API_KEY', 'AIzaSyCFb7XF_3_LCPlK-O5Yp4IZEO1w0ccgQJM'),

        // Server-side key for the Geocoding HTTP API (fileindexing:backfill-coordinates).
        // Must NOT have an HTTP-referrer restriction — Google rejects referrer-restricted
        // keys on server-to-server API calls. Restrict by IP or API instead.
        'geocoding_key' => env('GOOGLE_GEOCODING_API_KEY'),
    ],

    'paystack' => [
        'secret'   => env('PAYSTACK_SECRET_KEY'),
        'public'   => env('PAYSTACK_PUBLIC_KEY'),
        'base_url' => 'https://api.paystack.co',
    ],

    'ebulksms' => [
        'username' => env('EBULKSMS_USERNAME'),
        'apikey' => env('EBULKSMS_APIKEY'),
        'sender' => env('EBULKSMS_SENDER', 'Klaesedms'),
    ],

    // Gateway used for Special Assignment contravention notices (first / second serve)
    'betasms' => [
        'username' => env('BETASMS_USERNAME'),
        'password' => env('BETASMS_PASSWORD'),
        'sender'   => env('BETASMS_SENDER', 'KLASE'),
        // Set per server when the default plain-HTTP route is blocked outbound.
        // spa:sms-doctor probes the alternatives and prints what to put here.
        'endpoint' => env('BETASMS_ENDPOINT', 'http://login.betasms.com/api/'),
        'proxy'    => env('BETASMS_PROXY'),
        // Pin login.betasms.com to this IP, for a server whose DNS cannot
        // resolve it but whose network can still reach it.
        'ip'       => env('BETASMS_IP'),
    ],

    /*
     | Bulk-SMS.ng — the SPAS notice provider. Distinct from 'bulksmsnigeria'
     | below, which is a different vendor entirely. Credentials are the account
     | login, so they live in .env and are never committed; spa:sms-doctor says
     | exactly what to add when they are missing on a server.
     */
    'bulk_sms_ng' => [
        'email'    => env('BULK_SMS_NG_EMAIL'),
        'password' => env('BULK_SMS_NG_PASSWORD'),
        // KANOMLPP is the registered sender ID for SPAS notices. Kept as the
        // DEFAULT, not only in .env, because .env is gitignored and never
        // travels with a deployment — a server missing the key would
        // otherwise quietly fall back to an unregistered sender and the
        // gateway would refuse or relabel the messages.
        // Max 11 characters at the gateway; this is 8.
        'sender'   => env('BULK_SMS_NG_SENDER', 'KANOMLPP'),
        'gateway'  => env('BULK_SMS_NG_GATEWAY', '1'),
    ],

    'bulksmsnigeria' => [
        'api_token' => env('BULKSMSNG_API_TOKEN', '488|sKKj8eoZkgxFWLvdaBobT2aISYSZT055IcFJjZA994a7f693'),
        'sender' => env('BULKSMSNG_SENDER', 'KANGIS'),
        // When true, posts to /api/sandbox/v2/sms (no real SMS, no wallet
        // deduction). Useful for staging or before production access is
        // activated. Flip BULKSMSNG_SANDBOX=false in .env to send live SMS.
        'sandbox' => env('BULKSMSNG_SANDBOX', false),
    ],

    // Headless Chrome for print-faithful PDFs (App\Services\ChromePdfRenderer).
    // Blank means the usual install locations are searched.
    'chrome' => [
        'path' => env('CHROME_PATH'),
    ],

];
