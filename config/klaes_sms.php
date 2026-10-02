<?php

use App\Models\SmsSetting;

return [

    /*
    |--------------------------------------------------------------------------
    | Transactional SMS catalogue
    |--------------------------------------------------------------------------
    |
    | The nine messages the Ministry sends when a file is commissioned, a Letter
    | of Grant is generated, a file is requested or logged out, an instrument is
    | registered, or a caveat is placed or lifted.
    |
    | THIS FILE IS THE DEFAULT, NOT THE LIVE SETTING. Whether a message is on,
    | and what its wording is, are read from the `sms_settings` table so the
    | Ministry can change both from the SMS Control Centre without a deploy.
    | What is here is what a message falls back to when that table has no row
    | for it (a fresh server, or a key added by a later code upload).
    |
    | Nothing here lives only in .env: .env is gitignored and does NOT travel
    | with a code upload, so a key that exists only there is simply absent on a
    | freshly deployed server. Same reasoning as config/staff_sms.php.
    |
    | EVERY MESSAGE SHIPS DISABLED. A deploy sends nothing until somebody opens
    | the control page and turns a message on.
    |
    | THE GATEWAY
    | Bulk-SMS.ng, via App\Services\BulkSmsNgService, under one of two sender
    | IDs -- see the Sender IDs block below. The
    | account is on the PROMOTIONAL route only -- /transactional/v2/send answers
    | 608 "not authorised" -- which has two consequences worth knowing before
    | enabling anything:
    |
    |   * promotional traffic does not reach DND-blocked handsets;
    |   * messages sent between 19:45 and 08:00 are held by the telcos until
    |     08:30 and come back as code 609 (accepted, deferred).
    |
    | WORDING AND MONEY
    | One page is 160 characters; a longer message simply bills as more pages.
    | Most of these wordings are fixed by the Ministry and run to two pages --
    | that is deliberate and must not be "fixed" by truncating them. Each entry
    | also carries a `fallback`: a plainer wording tried only when the gateway
    | refuses the first on code 602 (BulkSmsNgService::CODE_REWORDABLE). These
    | gateways run keyword filters that have refused words like "approved" and
    | "notice" outright, returning HTTP 200 while delivering to nobody.
    |
    */

    /*
     | THE CLOCK EVERY [DateTime] TOKEN IS RENDERED ON.
     |
     | config('app.timezone') is UTC on this deployment while Kano is WAT
     | (UTC+1), so now() reads 16:00 when the office wall clock says 17:00.
     | A message telling an applicant their file was logged out at 16:12 when
     | the counter clock said 17:12 is worse than no message at all.
     */
    'timezone' => env('KLAES_SMS_TIMEZONE', 'Africa/Lagos'),

    'datetime_format' => 'd/m/Y h:i A',
    'date_format' => 'd/m/Y',

    /*
     | SHARED-NUMBER GUARD.
     |
     | file_indexings.phone is the only phone column in this database with real
     | volume, and it is polluted: one number sits on 1,211 different files and
     | another on 492. Those are indexing clerks and agents, not applicants.
     | Prefilling from it blindly would text one clerk a thousand times.
     |
     | A number found on more than this many DISTINCT file numbers is offered to
     | the officer with a warning instead of being trusted, and is not sent to
     | until somebody confirms it. Set to 0 to disable the guard entirely.
     */
    'shared_phone_threshold' => env('KLAES_SMS_SHARED_PHONE_THRESHOLD', 5),

    // How long the shared-file count for one number stays cached (seconds).
    'shared_phone_cache_ttl' => 3600,

    /*
     | Master switch, consulted before any individual message. The live value is
     | the `master` row in sms_settings; this is only its default.
     */
    'enabled' => env('KLAES_SMS_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Sender IDs
    |--------------------------------------------------------------------------
    |
    | Bulk-SMS.ng takes the sender ID as a per-request field, so one account can
    | send under more than one name. The Ministry uses two, and the split is by
    | WHO RECEIVES THE MESSAGE, not by which module raised it:
    |
    |   KLAES     internal traffic to a member of staff -- sign-in and sign-out,
    |             a Quick Search request, a file logged out to an office, a
    |             verification code. The recipient works here and knows the
    |             system by that name.
    |
    |   KANOMLPP  departmental traffic to a member of the public -- commissioning,
    |             Letters of Grant, deed registrations, caveats. The recipient is
    |             an applicant who knows the Ministry, not the software.
    |
    |   KANGIS    traffic about a file the applicant knows by its KANGIS number,
    |             from the registry of that name. Added for the SMS Management
    |             sub-module; it carries no message until one is moved onto it.
    |
    | A SENDER ID THAT IS NOT REGISTERED WITH THE VENDOR IS ACCEPTED, BILLED AND
    | NEVER DELIVERED. That is indistinguishable from every other delivery failure
    | on this gateway, so confirm a new ID with Bulk-SMS.ng before routing real
    | traffic under it. The gateway also truncates anything past 11 characters.
    |
    | Per-message overrides live in sms_settings.sender, so the SMS Control Centre
    | can move one message between them without a deploy.
    */
    'senders' => [
        'staff' => env('KLAES_SMS_SENDER_STAFF', 'KLAES'),
        'department' => env('KLAES_SMS_SENDER_DEPARTMENT', 'KANOMLPP'),
        'kangis' => env('KLAES_SMS_SENDER_KANGIS', 'KANGIS'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Which of those the vendor has actually registered
    |--------------------------------------------------------------------------
    |
    | Purely so the SMS Management page can WARN. It changes no routing: an
    | unregistered ID still sends, because the only thing that settles the
    | question is the vendor, and a hard block here would be a second way for
    | messages to stop arriving with no explanation.
    |
    | Read the header note above before adding one. On this gateway an
    | unregistered sender is accepted, billed and delivered to nobody, and that
    | is indistinguishable from every other delivery failure -- so a new name
    | belongs here only once Bulk-SMS.ng has confirmed it, not when it is added
    | to 'senders'.
    */
    'registered_senders' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('KLAES_SMS_REGISTERED_SENDERS', 'staff,department'))
    ))),

    /*
    |--------------------------------------------------------------------------
    | The messages
    |--------------------------------------------------------------------------
    |
    | Each entry:
    |   group            Which card group it appears under in the control page.
    |   label            Human name, shown in the UI and the doctor command.
    |   audience         Who receives it -- 'applicant' or 'officer'. Display
    |                    only; the recipient is resolved at the call site.
    |   recipient        One line describing that, for the control page.
    |   default_enabled  Always false. See the note above.
    |   tokens           Every placeholder this message MAY use. A template that
    |                    uses anything outside this list is rejected on save,
    |                    because an unknown token renders as a literal "[Foo]"
    |                    in somebody's inbox.
    |   sample           Example token values, used to render the live preview
    |                    and the page/cost estimate in the control page.
    |   template         The Ministry's wording.
    |   fallback         Plainer wording, tried only on gateway code 602.
    |
    */

    'messages' => [

        /* ---------------------------------------------------------------- */
        /* Commissioning                                                    */
        /* ---------------------------------------------------------------- */

        SmsSetting::KEY_LAND_FC => [
            // To a member of the public -- goes out as KANOMLPP.
            'sender' => 'department',
            'group' => 'Commissioning',
            'label' => 'File Commissioned - Land',
            'audience' => 'applicant',
            'recipient' => 'The applicant, on the phone number captured at commissioning.',
            'default_enabled' => false,
            'tokens' => ['FileNo', 'Application Type', 'Department'],
            // Real values, read out of the live tables. mls_file_no numbers
            // carry NO 'KN-' prefix -- they are <land use>-<year>-<serial>.
            // 'Application Type' renders mls_file_no.source, whose commonest
            // values are Conversion and Direct Allocation.
            'sample' => [
                'FileNo' => 'RES-2026-3026',
                'Application Type' => 'Direct Allocation',
                'Department' => 'Land Department',
            ],
            // What the batch preview shows: five consecutive files for one
            // applicant, written the way the commissioning screen writes them.
            'sample_plural' => [
                'FileNo' => 'RES-2026-3029-3033',
            ],
            'template' => 'A New File with FileNo [FileNo] has been commissioned for under the [Application Type] Application with the [Department] of the Kano State Ministry of Land and Physical Planning',

            /*
             | BATCH WORDING. Used when one message covers more than one file --
             | a batch commissioned for a single applicant. [FileNo] then holds a
             | RANGE ("RES-2026-3029-3033") rather than one number, so the plural
             | form is a fixed length no matter how many files it covers: 200
             | files cost the same two pages as two files.
             |
             | Only the singular/plural words differ. Keeping both on one card in
             | the control centre is deliberate -- reworded separately they drift.
             */
            'plural_template' => 'New Files with FileNos [FileNo] have been commissioned for under the [Application Type] Application with the [Department] of the Kano State Ministry of Land and Physical Planning',
            'plural_fallback' => 'KLAES: Files [FileNo] have been commissioned for your [Application Type] application at the Kano State Ministry of Land and Physical Planning.',
            'fallback' => 'KLAES: File [FileNo] has been commissioned for your [Application Type] application at the Kano State Ministry of Land and Physical Planning.',
        ],

        SmsSetting::KEY_OSS_FC => [
            // To a member of the public -- goes out as KANOMLPP.
            'sender' => 'department',
            'group' => 'Commissioning',
            'label' => 'File Commissioned - OSS Change of Ownership',
            'audience' => 'applicant',
            'recipient' => 'The applicant (the incoming owner), on the phone number captured at commissioning.',
            'default_enabled' => false,
            'tokens' => ['FileNo', 'Original Allottee'],
            'sample' => [
                'FileNo' => 'IND-2026-256',
                'Original Allottee' => 'Musa Ibrahim',
            ],
            'sample_plural' => [
                'FileNo' => 'IND-2026-256-260',
            ],
            'template' => 'A New File with FileNo [FileNo] has been commissioned for under Change of Ownership Application from Original Allottee "[Original Allottee]" with the One Stop Shop Unit (Land Department) of the Kano State Ministry of Land and Physical Planning',

            // Batch wording -- see the note on the Land entry above.
            'plural_template' => 'New Files with FileNos [FileNo] have been commissioned for under Change of Ownership Application from Original Allottee "[Original Allottee]" with the One Stop Shop Unit (Land Department) of the Kano State Ministry of Land and Physical Planning',
            'plural_fallback' => 'KLAES: Files [FileNo] have been commissioned under Change of Ownership from Original Allottee [Original Allottee] at the One Stop Shop Unit.',
            'fallback' => 'KLAES: File [FileNo] has been commissioned under Change of Ownership from Original Allottee [Original Allottee] at the One Stop Shop Unit.',
        ],

        SmsSetting::KEY_ST_FC => [
            // To a member of the public -- goes out as KANOMLPP.
            'sender' => 'department',
            'group' => 'Commissioning',
            'label' => 'File Commissioned - Sectional Titling',
            'audience' => 'applicant',
            'recipient' => 'The applicant, on the phone number captured at ST commissioning.',
            'default_enabled' => false,
            'tokens' => ['FileNo', 'ST Application Type'],
            /*
             | ST primaries are ST-<use>-<year>-<serial>, and a CONVERSION is
             | numbered exactly the same way -- it takes the next serial from the
             | shared ST pool for its land use, just like a direct allocation
             | (CommissionNewSTController::commission, the comment at the
             | "$npFileNo = ST-{landUseCode}-..." line).
             |
             | Do NOT use the ST-CON-COM-... form seen on two rows from
             | 2026-08-09: that predates the change, and the newest conversion
             | (ST-RES-2026-11) carries the plain form. The CON- number belongs to
             | the mother LAND file, which is stored separately in
             | st_file_numbers.mls_fileno and is not what the applicant is told.
             */
            'sample' => [
                // A real Direct Allocation row, so the number and the type on
                // the preview belong to the same file. ST-RES-2026-11 is a
                // Conversion, so it cannot carry this wording.
                'FileNo' => 'ST-COM-2026-15',
                'ST Application Type' => 'Direct Allocation',
            ],
            'template' => 'A New File with FileNo [FileNo] has been commissioned for your [ST Application Type] Application with the Department of Sectional Titling of the Kano State Ministry of Land and Physical Planning',
            'fallback' => 'KLAES: File [FileNo] has been commissioned for your [ST Application Type] application with the Department of Sectional Titling.',
        ],

        /* ---------------------------------------------------------------- */
        /* Letter of Grant                                                  */
        /* ---------------------------------------------------------------- */

        SmsSetting::KEY_ROFO_GENERATED => [
            // To a member of the public -- goes out as KANOMLPP.
            'sender' => 'department',
            'group' => 'Letter of Grant',
            'label' => 'Letter of Grant (RofO) generated',
            'audience' => 'applicant',
            'recipient' => 'The applicant on the recommendation, resolved from the file when no number was captured.',
            'default_enabled' => false,
            'tokens' => ['FileNo', 'Department'],
            'sample' => [
                'FileNo' => 'RES-2026-1016',
                'Department' => 'Lands Department',
            ],
            'template' => 'Your Letter of Grant (RofO) for FileNo [FileNo] has been generated & it is ready for signing & collection @ the [Department] of the Kano State Ministry of Land and Physical Planning. Congratulations',
            'fallback' => 'KLAES: The Letter of Grant for FileNo [FileNo] is ready for signing and collection at the [Department].',
        ],

        /* ---------------------------------------------------------------- */
        /* File requests                                                    */
        /* ---------------------------------------------------------------- */

        SmsSetting::KEY_FILE_REQUEST_RECEIVED => [
            // To a member of staff -- goes out as KLAES.
            'sender' => 'staff',
            'group' => 'File Requests',
            'label' => 'File request received (after Quick Search)',
            'audience' => 'officer',
            'recipient' => 'The member of staff who raised the request, on users.phone_number.',
            'default_enabled' => false,
            'tokens' => ['FileNo(s)'],
            // File requests are overwhelmingly raised against older land
            // files, which is what this sample should look like.
            'sample' => [
                'FileNo(s)' => 'RES-2016-3546',
            ],
            'template' => 'Your File Request including [FileNo(s)] has been received & your request has been forwarded to our SCB Monitor for onward retrieval. You will be updated accordingly, let us know immediately if this request was not placed by you. Thank you',
            'fallback' => 'KLAES: Your file request for [FileNo(s)] has been received and forwarded to the SCB Monitor for retrieval.',
        ],

        SmsSetting::KEY_FILE_REQUEST_LOGGED => [
            // To a member of staff -- goes out as KLAES.
            'sender' => 'staff',
            'group' => 'File Requests',
            'label' => 'File logged out to requester office',
            'audience' => 'officer',
            'recipient' => 'The requesting officer on the tracker, on users.phone_number.',
            'default_enabled' => false,
            'tokens' => ['FileNo(s)', 'Requester Office', 'DateTime', 'Purpose', 'Timeline'],
            // Office and purpose are real values from file_tracker and
            // request_purposes; 5 days is the seeded default turnaround.
            'sample' => [
                'FileNo(s)' => 'RES-2016-3546',
                'Requester Office' => 'Director, Lands Office',
                'DateTime' => '06/09/2026 10:24 AM',
                'Purpose' => 'ASSIGNMENT',
                'Timeline' => '5 days',
            ],
            'template' => 'Your File Request including [FileNo(s)] has been logged to your office - [Requester Office] @ [DateTime] for the [Purpose] for a duration of [Timeline]. Let us know immediately if this request was not placed by you. Thank you',
            'fallback' => 'KLAES: File [FileNo(s)] was logged to [Requester Office] on [DateTime] for [Purpose], duration [Timeline].',
        ],

        /* ---------------------------------------------------------------- */
        /* Deeds                                                            */
        /* ---------------------------------------------------------------- */

        SmsSetting::KEY_DEED_REGISTERED => [
            // To a member of the public -- goes out as KANOMLPP.
            'sender' => 'department',
            'group' => 'Deeds',
            'label' => 'Instrument registered',
            'audience' => 'applicant',
            'recipient' => 'The grantee (instrument_capture.party_2_phone), or the number captured on the registration screen.',
            'default_enabled' => false,
            'tokens' => ['Instrument Type', 'Registration Particulars', 'DateTime', 'FileNo'],
            /*
             | One real row, not three values borrowed from different places:
             | deed_registrations id for RES-2016-957, a Deed of Assignment
             | registered 13/07/2026 as 96/96/74.
             |
             | Particulars are serial/page/volume as the Deeds Registry writes
             | them. The volume is a running book number in the 70s -- NOT a year,
             | and not the 1 that the ST Fragmentation rows happen to carry.
             */
            'sample' => [
                'Instrument Type' => 'Deed of Assignment',
                'Registration Particulars' => '96/96/74',
                'DateTime' => '13/07/2026 10:24 AM',
                'FileNo' => 'RES-2016-957',
            ],
            'template' => 'Your Instrument, [Instrument Type] has been Registered @ the Deeds Department of the Kano State Ministry of Land and Physical Planning, with Registration Particulars [Registration Particulars] @ [DateTime]. Thank you',
            'fallback' => 'KLAES: Your [Instrument Type] was registered at the Deeds Department on [DateTime]. Particulars: [Registration Particulars].',
        ],

        /* ---------------------------------------------------------------- */
        /* Caveats                                                          */
        /* ---------------------------------------------------------------- */

        SmsSetting::KEY_CAVEAT_PLACED => [
            // To a member of the public -- goes out as KANOMLPP.
            'sender' => 'department',
            'group' => 'Caveats',
            'label' => 'Caveat placed',
            'audience' => 'applicant',
            'recipient' => 'The caveator / solicitor, on the phone captured on the Place Caveat form.',
            'default_enabled' => false,
            'tokens' => ['FileNo', 'Instrument Type', 'DateTime', 'Applicant'],
            /*
             | Caveats sit on land files under either their MLS or their KANGIS
             | number. 'Instrument Type' renders an InstrumentTypes.InstrumentName,
             | falling back to caveats.encumbrance_type.
             |
             | 'Deed of Mortgage' is InstrumentTypes id 21 -- the mortgage a
             | caveat is most often lodged against. There is no bare 'Mortgage'
             | row, so that wording could never appear in a real message; the
             | other mortgage instrument is 'Tripartite Mortgage' (id 22).
             */
            'sample' => [
                'FileNo' => 'RES-2016-307',
                'Instrument Type' => 'Deed of Mortgage',
                'DateTime' => '06/09/2026 10:24 AM',
                'Applicant' => 'ABDULLAHI MOHAMMED & CO',
            ],
            'template' => 'A Caveat has been placed on property with FileNo [FileNo] on the instrument [Instrument Type] on [DateTime] by [Applicant]. Thank you',
            'fallback' => 'KLAES: A caveat was placed on FileNo [FileNo] ([Instrument Type]) on [DateTime] by [Applicant].',
        ],

        SmsSetting::KEY_CAVEAT_LIFTED => [
            // To a member of the public -- goes out as KANOMLPP.
            'sender' => 'department',
            'group' => 'Caveats',
            'label' => 'Caveat lifted (by hand)',
            'audience' => 'applicant',
            'recipient' => 'The caveator / solicitor on the caveat record.',
            'default_enabled' => false,
            'tokens' => ['FileNo', 'Instrument Type', 'DateTime', 'Applicant'],
            /*
             | Caveats sit on land files under either their MLS or their KANGIS
             | number. 'Instrument Type' renders an InstrumentTypes.InstrumentName,
             | falling back to caveats.encumbrance_type.
             |
             | 'Deed of Mortgage' is InstrumentTypes id 21 -- the mortgage a
             | caveat is most often lodged against. There is no bare 'Mortgage'
             | row, so that wording could never appear in a real message; the
             | other mortgage instrument is 'Tripartite Mortgage' (id 22).
             */
            'sample' => [
                'FileNo' => 'RES-2016-307',
                'Instrument Type' => 'Deed of Mortgage',
                'DateTime' => '06/09/2026 10:24 AM',
                'Applicant' => 'ABDULLAHI MOHAMMED & CO',
            ],
            'template' => 'The Caveat on property with FileNo [FileNo] on the instrument [Instrument Type] has been lifted on [DateTime] by [Applicant]. Thank you',
            'fallback' => 'KLAES: The caveat on FileNo [FileNo] ([Instrument Type]) was lifted on [DateTime] by [Applicant].',
        ],

        SmsSetting::KEY_CAVEAT_AUTO_LIFTED => [
            // To a member of the public -- goes out as KANOMLPP.
            'sender' => 'department',
            'group' => 'Caveats',
            'label' => 'Caveat auto-lifted after 6 months',
            'audience' => 'applicant',
            'recipient' => 'The caveator / solicitor on the caveat record.',
            'default_enabled' => false,
            'tokens' => ['FileNo', 'Instrument Type', 'DateTime'],
            'sample' => [
                'FileNo' => 'RES-2016-307',
                'Instrument Type' => 'Deed of Mortgage',
                'DateTime' => '06/09/2026 10:24 AM',
            ],
            'template' => 'The Caveat on property with FileNo [FileNo] on the instrument [Instrument Type] has been automatically lifted on [DateTime] after 6 months. Thank you',
            'fallback' => 'KLAES: The caveat on FileNo [FileNo] ([Instrument Type]) was automatically lifted on [DateTime] after 6 months.',
        ],

        /* ---------------------------------------------------------------- */
        /* Account security                                                 */
        /* ---------------------------------------------------------------- */

        SmsSetting::KEY_PHONE_OTP => [
            // To a member of staff -- goes out as KLAES.
            'sender' => 'staff',
            'group' => 'Account Security',
            'label' => 'Phone verification code',
            'audience' => 'officer',
            'recipient' => 'The member of staff setting up or correcting their own number.',

            /*
             | ALWAYS ON, AND NOT SWITCHABLE FROM THIS PAGE.
             |
             | This is the code people need in order to get INTO the system. A
             | toggle that silences it locks the Ministry out of its own
             | application, and the person who would have to switch it back on is
             | on the far side of the gate. The wording is editable and the sends
             | are listed like any other message; only the switch is withheld.
             |
             | To pause the gate itself -- which lets everyone through rather than
             | leaving them stuck at a card with no code -- set
             | PHONE_VERIFICATION_ENABLED=false.
             */
            'always_on' => true,
            'default_enabled' => true,

            'tokens' => ['Code', 'Minutes'],
            'sample' => [
                'Code' => '481902',
                'Minutes' => '15',
            ],
            'template' => 'KLAES verification: [Code]. Type it into the confirmation card to finish setting up your account. Do not share it with anyone.',
            'fallback' => 'KLAES: [Code]',
        ],


        /* ---------------------------------------------------------------- */
        /* Staff attendance -- wording lives in StaffAttendanceSmsService   */
        /* ---------------------------------------------------------------- */

        /*
         | These two are listed so the control page can switch them on and off
         | alongside everything else -- the .env-only flags they used to depend
         | on never reach production. They are NOT dispatched through
         | KlaesSmsDispatcher: StaffAttendanceSmsService keeps its own once-a-day
         | claim on staff_sms_logs and its own shift-end rule, and only asks this
         | catalogue whether it is switched on. Their wording is therefore not
         | editable here, which is why they carry no template.
         */

        SmsSetting::KEY_ATTENDANCE_LOGIN => [
            // To a member of staff -- goes out as KLAES.
            'sender' => 'staff',
            'group' => 'Staff Attendance',
            'label' => 'Staff sign-in confirmation',
            'audience' => 'officer',
            'recipient' => 'The member of staff signing in. Once per person per day.',
            'default_enabled' => false,
            'toggle_only' => true,
            'tokens' => [],
            'sample' => [],
            'template' => null,
            'fallback' => null,
        ],

        SmsSetting::KEY_ATTENDANCE_LOGOUT => [
            // To a member of staff -- goes out as KLAES.
            'sender' => 'staff',
            'group' => 'Staff Attendance',
            'label' => 'Staff sign-out confirmation',
            'audience' => 'officer',
            'recipient' => 'The member of staff signing out, once their own shift has ended. Once per person per day.',
            'default_enabled' => false,
            'toggle_only' => true,
            'tokens' => [],
            'sample' => [],
            'template' => null,
            'fallback' => null,
        ],
    ],
];
