<?php

/*
|--------------------------------------------------------------------------
| ID Name Verification
|--------------------------------------------------------------------------
|
| SCOPE — read this before changing anything here.
|
| This feature verifies ONE thing: that the full name an applicant typed also
| appears in the text OCR reads off the identification document they uploaded.
|
| It is NOT, and must never be presented as, proof that:
|   - the document is genuine;
|   - the document has not been altered;
|   - the uploader is the person the document depicts;
|   - the ID number is valid with the issuing authority.
|
| Everything below tunes that single name comparison.
|
*/

return [

    /*
    | Score bands. A score at or above `verified` passes; at or above `review`
    | needs a human look; anything lower fails. Kept here so the bands can be
    | retuned from configuration rather than hunted through the codebase.
    */
    'thresholds' => [
        'verified' => (int) env('ID_VERIFICATION_VERIFIED_THRESHOLD', 80),
        'review'   => (int) env('ID_VERIFICATION_REVIEW_THRESHOLD', 60),
    ],

    /*
    | A single matching name proves very little — "IBRAHIM" matches half the
    | register. At least this many components must match before a result can be
    | `verified`, no matter how the percentage lands.
    */
    'min_matching_parts' => (int) env('ID_VERIFICATION_MIN_PARTS', 2),

    /*
    | Name components this short are initials, not names, and are ignored on both
    | sides of the comparison.
    */
    'min_part_length' => 2,

    /*
    | Per-part fuzzy tolerance, as a Levenshtein distance. OCR reliably confuses
    | a handful of glyph pairs (0/O, 1/I, 5/S), which the normalizer folds away;
    | this covers the rest. Keep it low — at 2 unrelated short names start
    | matching each other.
    */
    'max_edit_distance' => (int) env('ID_VERIFICATION_MAX_EDIT_DISTANCE', 1),

    /*
    | Storing the raw OCR text makes a disputed result auditable, but it is a
    | second copy of the personal data already on the document. Off by default:
    | turn it on only where the audit trail is actually required.
    */
    'store_raw_text' => (bool) env('ID_VERIFICATION_STORE_RAW_TEXT', false),

    /*
    | Retention. Nothing deletes documents automatically yet — see the note in
    | docs/ID_NAME_VERIFICATION.md. When a scheduled purge is added, it reads
    | this value; a null means "keep indefinitely".
    */
    'retention_days' => env('ID_VERIFICATION_RETENTION_DAYS') !== null
        ? (int) env('ID_VERIFICATION_RETENTION_DAYS')
        : null,

    /*
    | Uploads. 5MB per image unless the deployment tightens it.
    */
    'uploads' => [
        'disk'          => 'ols_private',
        'directory'     => 'online-legal-search/identification',
        'max_kilobytes' => (int) env('ID_VERIFICATION_MAX_KB', 5120),
        'mimes'         => ['jpeg', 'jpg', 'png', 'webp'],
        'mime_types'    => ['image/jpeg', 'image/png', 'image/webp'],
    ],

    /*
    | OCR provider. `driver` selects the binding in OcrServiceProvider, so the
    | provider can be swapped without touching the payment workflow.
    */
    'ocr' => [
        'driver'   => env('ID_VERIFICATION_OCR_DRIVER', 'tesseract'),
        'language' => env('ID_VERIFICATION_OCR_LANG', 'eng'),
        'timeout'  => (int) env('ID_VERIFICATION_OCR_TIMEOUT', 30),

        /*
        | Absolute path to the tesseract binary. Leave null to use whatever is on
        | PATH (correct for a normal `apt install tesseract-ocr`). On Windows this
        | usually needs the full path to tesseract.exe.
        */
        'binary' => env('TESSERACT_PATH'),
    ],

    /*
    | Image preprocessing applied to a TEMPORARY copy before OCR. The stored
    | original is never touched.
    */
    'preprocess' => [
        'enabled'       => (bool) env('ID_VERIFICATION_PREPROCESS', true),
        'max_dimension' => 2000,
        'contrast'      => 15,
    ],

    /*
    | Who is submitting the search. Both types go through the same ID name check
    | and upload the same document; a lawyer additionally supplies a Call-to-Bar
    | number, which is checked as far as it can be (see `bar_number` below).
    */
    'customer_types' => [
        'individual' => [
            'label'              => 'Individual',
            'requires_bar_number' => false,
        ],
        'lawyer' => [
            'label'              => 'Lawyer / Legal Adviser',
            'requires_bar_number' => true,
        ],
    ],

    /*
    | Call-to-Bar number checking.
    |
    | READ THIS BEFORE CHANGING THE STATUS RULES. Nigerian general-purpose IDs -
    | the NIN slip, driver's licence, voter's card - do NOT carry a call-to-bar
    | number. So "look for it on the uploaded ID" finds nothing for most lawyers,
    | which is a normal outcome and not evidence of anything.
    |
    | That is why an UNCONFIRMED bar number never blocks payment: if it did, no
    | lawyer could ever complete a search. The number is recorded, its check
    | outcome is stored, and the approving officer sees both. Only an external
    | roll that positively REJECTS a number downgrades the result, and only to
    | `review` - a human decides, rather than an API quirk rejecting a real
    | practitioner.
    |
    | `lookup_driver` selects the roll implementation bound in AppServiceProvider.
    | It is 'none' because no Nigerian Bar Association / Supreme Court roll API is
    | wired up; implement BarRollLookup and add a branch to enable one.
    */
    'bar_number' => [
        'lookup_driver' => env('ID_VERIFICATION_BAR_LOOKUP', 'none'),

        // Minimum length after normalisation. Guards against a stray digit being
        // "found" everywhere in an OCR transcript.
        'min_length' => 4,
    ],

    /*
    | Accepted identification types.
    |
    | Only ONE image is collected per applicant - the side carrying the name. A
    | back image was originally required for two-sided cards, but the name is
    | always on the front, so the second upload cost every applicant an extra
    | step and a second OCR pass while adding nothing to the comparison.
    | `front_label` names that image where "Front of ID" would read oddly.
    | `requires_other_label` turns on the free-text "Identification Type" field.
    */
    'types' => [
        'nin' => [
            'label' => 'National Identification Number (NIN) slip/card',
        ],
        'drivers_licence' => [
            'label' => "Driver's Licence",
        ],
        'international_passport' => [
            'label'       => 'International Passport',
            'front_label' => 'Passport data page',
        ],
        'voters_card' => [
            'label' => "Voter's Card",
        ],
        'other' => [
            'label'                => 'Other government-issued ID',
            'requires_other_label' => true,
        ],
    ],

    /*
    | Applicant-facing messages. Deliberately free of raw OCR text and technical
    | detail — those go to the log, never to the browser.
    */
    'messages' => [
        'verified'   => 'Your identification name has been verified successfully.',
        'review'     => 'We could not confidently verify the full name on your identification. Please upload a clearer image or review the name you entered.',
        'failed'     => 'The full name entered does not match the name detected on the uploaded identification.',
        'unreadable' => 'We could not read the uploaded identification. Please upload a clear, well-lit image showing the complete document.',

        /*
        | The OCR engine itself failed — nothing the applicant can fix by
        | re-photographing their ID, so they must not be told to. Kept free of
        | technical detail; the cause goes to the log and to
        | `php artisan ols:id-verification-doctor`.
        */
        'unavailable' => 'Identification checks are temporarily unavailable. Please try again shortly — this is a problem on our side, not with your document.',

        /*
        | Appended to a lawyer's result. Deliberately neutral about an unconfirmed
        | number: most IDs do not print one, so saying nothing would be misleading
        | and warning about it would alarm a perfectly legitimate practitioner.
        */
        'bar_matched'     => 'Your Call-to-Bar number was also found on the document you uploaded.',
        'bar_unconfirmed' => 'Your Call-to-Bar number has been recorded. It does not appear on the document you uploaded, so the approving officer will confirm it.',
        'bar_rejected'    => 'The Call-to-Bar number you entered could not be confirmed against the roll of legal practitioners. The approving officer will review it.',
    ],
];
