<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Signing keys
    |--------------------------------------------------------------------------
    |
    | The QR signing key NEVER lives in the database — if it did, anyone with
    | read access to document_qr_codes would hold both the tokens and the key
    | that authenticates them.
    |
    | Keys are keyed by an integer key_id which is stamped into every token and
    | recorded on the document_qr_codes row, so a rotation does not invalidate
    | paper that has already been printed. To rotate: add a new id below, point
    | `active_key` at it, and leave the old entry in place forever.
    |
    | Each value is a base64-encoded 32-byte key. Generate one with:
    |   php artisan qr:doctor --generate-key
    |
    */

    'keys' => [
        1 => env('DOCUMENT_QR_KEY_1'),
    ],

    'active_key' => (int) env('DOCUMENT_QR_ACTIVE_KEY', 1),

    'cipher' => 'aes-256-gcm',

    /*
    |--------------------------------------------------------------------------
    | Token format
    |--------------------------------------------------------------------------
    |
    | Emitted tokens look like: KLAES-Q1:<base64url payload>
    |
    | Legacy QR codes carry a raw tracking ID, a JSON blob, a /verify-file/ URL
    | or the literal string 'N/A'. Those are version 0 (Q0) and can never
    | verify above "review" — see the Q0 decoder in QrPayloadReader.
    |
    */

    'prefix'  => 'KLAES-Q1:',
    'version' => 1,

    /*
    |--------------------------------------------------------------------------
    | Rendering
    |--------------------------------------------------------------------------
    |
    | QR images are rendered locally with bacon/bacon-qr-code. They were
    | previously fetched from api.qrserver.com, which sent the payload to a
    | third party on every print and broke whenever the server had no outbound
    | internet access.
    |
    */

    'render' => [
        'size'   => 140,
        'margin' => 1,
    ],

];
