<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Phone-number verification (OTP)
    |--------------------------------------------------------------------------
    |
    | Staff sign in as before, and are then held at a card that asks them to
    | confirm the mobile number on their account. A code is sent to it and typed
    | back; users.is_otp_verified goes to 1 and the system unlocks. The gate is
    | the same shape as the mandatory passport photo (RequireProfilePhoto) and
    | runs immediately after it, so a user never meets both cards at once.
    |
    | Nothing here lives only in .env. .env is gitignored and does NOT travel
    | with a code upload, so a key that existed only there would be absent on a
    | freshly deployed server — and the value that decides whether 1,562 people
    | can use the system is not a value to leave to chance.
    |
    */

    /*
     | THE MASTER SWITCH.
     |
     | Turning this off lets everybody straight through and stops any code being
     | sent; nothing is un-verified and no row changes, so it can be switched
     | back on at any time. This is the lever to pull if the SMS wallet empties
     | or the gateway goes down — see `php artisan phone:verify-status`.
     */
    'enabled' => env('PHONE_VERIFICATION_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Which way the code travels
    |--------------------------------------------------------------------------
    |
    | Two routes to the same code. EMAIL LEADS, and the reason is the data —
    | counted on 2026-09-07 across 1,562 active accounts:
    |
    |     495  have an email address that is a real mailbox
    |     282  have a mobile number the SMS gateway can reach
    |   1,061  have neither, and must type something real into the card
    |
    | and the SMS route is worse than that near-parity suggests. It is
    | promotional traffic: the telcos hold it between 19:45 and 08:00 (status
    | 609) and never deliver it to a DND-blocked handset, so a code asked for at
    | nine in the evening arrives at half past eight the next morning, and for
    | some staff never at all. Email costs nothing per message and reaches a
    | laptop.
    |
    | 1,555 accounts hold SOMETHING address-shaped; the gap between that and 495
    | is a thousand seeded placeholders (see 'placeholder_email_domains' below).
    | SMS therefore stays on the card underneath, because neither route reaches
    | everybody. The user picks; the default is what the card opens on.
    |
    */

    'default_channel' => env('PHONE_VERIFICATION_DEFAULT_CHANNEL', 'email'),

    /*
     | Either route can be closed off entirely. Turning EMAIL off falls the card
     | back to the SMS-only behaviour it had before; turning SMS off leaves email
     | as the only way through, which is the lever to pull when the SMS wallet
     | empties rather than switching the whole gate off. Closing both is treated
     | as closing the gate — nobody is held by a card with no button.
     */
    'channels' => [
        'email' => env('PHONE_VERIFICATION_EMAIL_ENABLED', true),
        'sms' => env('PHONE_VERIFICATION_SMS_ENABLED', true),
    ],

    /*
     | Addresses the code is REFUSED to. About a thousand accounts were seeded
     | with example.com / example.org / example.net addresses and another sixty
     | with klaes.local — none of which is a mailbox. Sending there "succeeds":
     | the SMTP server accepts it, the card says a code is on its way, and the
     | user waits for something that will never arrive, with no way through.
     | Refusing outright lets the card say the one useful thing instead — type
     | your real address in — which is the whole point of the exercise.
     |
     | Matched on the domain, case-insensitively. A leading dot means "and every
     | subdomain of it".
     */
    'placeholder_email_domains' => [
        'example.com',
        'example.org',
        'example.net',
        'example.edu',
        'test.com',
        'email.com',
        'domain.com',
        'localhost',
        'klaes.local',
        '.local',
        '.test',
        '.invalid',
        '.localdomain',
        '.example',
    ],

    /*
     | How long a code stays usable, in minutes. Short on purpose: the code is
     | stored in plain text on the user row so ICT can read it back to somebody
     | whose handset is out of coverage, and a long-lived plain code sitting in
     | a column is a standing key to the account.
     */
    'ttl_minutes' => env('PHONE_VERIFICATION_TTL_MINUTES', 15),

    /*
     | How long before the user may ask for another one. Each request costs a
     | page of SMS credit, and the telcos can take a minute or two to deliver —
     | without a cooldown a user who has not received one yet simply presses the
     | button again and spends the wallet.
     */
    'resend_cooldown_seconds' => env('PHONE_VERIFICATION_RESEND_SECONDS', 60),

    /*
     | Digits in the code. Six is what people expect to be reading off a handset.
     */
    'code_length' => env('PHONE_VERIFICATION_CODE_LENGTH', 6),

    /*
     | Wrong codes accepted before the account must request a fresh one. Counted
     | in the cache, so it survives nothing worse than a cache flush; the route
     | throttle is the backstop that does not depend on the cache at all.
     */
    'max_attempts' => env('PHONE_VERIFICATION_MAX_ATTEMPTS', 5),

    /*
     | THE OFFICE CLOCK. config('app.timezone') is UTC on this deployment while
     | Kano is WAT (UTC+1), so anything shown to a user in local terms — "sent
     | at 09:12" — has to be rendered here rather than from now().
     */
    'timezone' => env('PHONE_VERIFICATION_TIMEZONE', 'Africa/Lagos'),

    /*
    |--------------------------------------------------------------------------
    | The message
    |--------------------------------------------------------------------------
    |
    | Sent through Bulk-SMS.ng (App\Services\BulkSmsNgService, sender ID
    | KANOMLPP) — the same gateway as the attendance SMS, and DELIBERATELY NOT
    | through the SMS Control Centre catalogue: every message there ships
    | disabled and can be switched off from a web page. A sign-in code that
    | somebody can turn off by accident locks the whole Ministry out.
    |
    | Two wordings. The gateways here run keyword filters that have refused
    | words outright while answering HTTP 200 — BetaSMS rejects "code" and
    | "approved" — so the fallback avoids the word entirely and is tried when
    | the gateway refuses the first on its one re-wordable status (602).
    | {code} is the only token.
    */
    'message' => env(
        'PHONE_VERIFICATION_MESSAGE',
        'KLAES verification: {code}. Type it into the confirmation card to finish setting up your account. Do not share it with anyone.'
    ),

    'message_fallback' => env(
        'PHONE_VERIFICATION_MESSAGE_FALLBACK',
        'KLAES: {code}'
    ),

    /*
    |--------------------------------------------------------------------------
    | The email
    |--------------------------------------------------------------------------
    |
    | Sent through the ordinary Laravel mailer (App\Mail\AccountVerificationCode)
    | on whatever MAIL_* settings the server carries — which is the one thing to
    | check before enabling this route, because .env does not travel with a code
    | upload and an unconfigured mailer throws rather than failing quietly.
    |
    | DELIVERED SYNCHRONOUSLY. QUEUE_CONNECTION is `database` on this deployment
    | and there is no guarantee a worker is running; a sign-in code that sits in
    | a jobs table until somebody notices is the same as a code that never came.
    | See the mailable — it must never implement ShouldQueue.
    |
    */

    'email_subject' => env(
        'PHONE_VERIFICATION_EMAIL_SUBJECT',
        'Your KLAES verification code'
    ),

    /* Login OTP is deliberately controlled through the environment. */
    'login_otp' => [
        'enabled' => env('LOGIN_OTP_ENABLED', true),
        // Which route the FIRST sign-in code takes: 'sms' (default) or 'email'.
        // 'email' still falls back to SMS for an account with no real address,
        // and either way the sign-in screen lets the user ask for the other one.
        'channel' => env('LOGIN_OTP_CHANNEL', 'sms'),
        'ttl_minutes' => env('LOGIN_OTP_TTL_MINUTES', 10),
        'challenge_minutes' => env('LOGIN_OTP_CHALLENGE_MINUTES', 30),
        'reuse_minutes' => env('LOGIN_OTP_REUSE_MINUTES', 5),
        'max_sms_per_hour' => env('LOGIN_OTP_MAX_SMS_PER_HOUR', 6),
        'quiet_hours_email_first' => env('LOGIN_OTP_QUIET_HOURS_EMAIL_FIRST', true),
        'message' => env('LOGIN_OTP_MESSAGE', 'KLAES sign-in: {code}. Expires in {minutes} minutes. Do not share it with anyone.'),
        'message_fallback' => env('LOGIN_OTP_MESSAGE_FALLBACK', 'KLAES: {code}'),
        'email_subject' => env('LOGIN_OTP_EMAIL_SUBJECT', 'Your KLAES sign-in code'),
    ],

    /*
    |--------------------------------------------------------------------------
    | LAAS Portal sign-in code (public applicants)
    |--------------------------------------------------------------------------
    |
    | The same second step for the public portal, kept on its OWN keys rather
    | than sharing 'login_otp' above. The two populations have nothing in
    | common: staff are 1,562 seeded accounts, a thousand of which hold a
    | placeholder address, while an applicant typed a real email and a real
    | mobile into the registration form ten minutes ago. Sharing a switch would
    | mean that emptying the SMS wallet for the Ministry also silently changes
    | how the public signs in, which is not a trade anybody would choose.
    |
    | SMS LEADS here, unlike the staff card. The applicant's mobile number IS
    | their username, every workflow message already goes to it, and a code
    | arriving in the same thread as "your application has been approved" is the
    | one they will look for. Email is always one click away on the code screen,
    | and takes over automatically during the quiet hours below.
    |
    */
    'laas_login_otp' => [

        /*
         | THE MASTER SWITCH. Off lets applicants sign in on password alone,
         | exactly as before this was added. Nothing is stored on the applicant
         | row either way, so it can be flipped back and forth freely.
         */
        'enabled' => env('LAAS_LOGIN_OTP_ENABLED', true),

        // Which route the FIRST code takes: 'sms' (default) or 'email'.
        'channel' => env('LAAS_LOGIN_OTP_CHANNEL', 'sms'),

        /*
         | The name the text arrives under. Blank follows the staff sign-in code
         | (SmsSetting phone_otp, currently KLAES), which is the sender the
         | gateway is actually delivering. It is NOT the server default:
         | Bulk-SMS.ng accepts an unregistered sender ID, bills for it and
         | silently delivers nothing, and the first LAAS codes were lost exactly
         | that way. Max 11 characters at the gateway.
         */
        'sender' => env('LAAS_LOGIN_OTP_SENDER'),

        'ttl_minutes' => env('LAAS_LOGIN_OTP_TTL_MINUTES', 10),

        /*
         | How long the half-finished sign-in is held before the applicant must
         | type their password again. Longer than the staff 30 minutes: an
         | applicant is often on one handset, switching to the SMS app and back,
         | sometimes hunting for a network bar.
         */
        'challenge_minutes' => env('LAAS_LOGIN_OTP_CHALLENGE_MINUTES', 45),

        /*
         | A second sign-in attempt within this many minutes reuses the code
         | already sent instead of texting another. See LoginOtpService::begin()
         | for the night this was written: overnight retries piled up at the
         | gateway and arrived together at 08:30.
         */
        'reuse_minutes' => env('LAAS_LOGIN_OTP_REUSE_MINUTES', 5),

        /* Per account, per rolling hour. Protects the handset and the wallet. */
        'max_sms_per_hour' => env('LAAS_LOGIN_OTP_MAX_SMS_PER_HOUR', 6),

        /*
         | The telcos hold promotional traffic between 19:45 and 08:00 and
         | release it around 08:30. A code sent at nine in the evening is
         | therefore useless, so inside those hours the first one goes by email
         | when the account has a usable address.
         */
        'quiet_hours_email_first' => env('LAAS_LOGIN_OTP_QUIET_HOURS_EMAIL_FIRST', true),

        /*
         | Two wordings, friendliest first. Bulk-SMS.ng's promotional route runs
         | keyword filters that have refused "code" and "do not share" outright
         | while answering HTTP 200, so the fallback says almost nothing. Tokens:
         | {code} and {minutes}. Same shape as LaasProfileController::sendCode().
         */
        'message' => env(
            'LAAS_LOGIN_OTP_MESSAGE',
            'KLAES LAAS: {code} is your sign-in number. It is valid for {minutes} minutes. Do not share it with anyone.'
        ),

        'message_fallback' => env('LAAS_LOGIN_OTP_MESSAGE_FALLBACK', 'KLAES LAAS: {code}'),

        'email_subject' => env('LAAS_LOGIN_OTP_EMAIL_SUBJECT', 'Your LAAS Portal sign-in code'),
    ],

    /*
    |--------------------------------------------------------------------------
    | PHS Portal sign-in code (institutional members)
    |--------------------------------------------------------------------------
    |
    | Sign-in codes are sent only to the member's email address.
    | Phone collection and SMS verification are not part of PHS sign-in.
    |
    */
    'phs_login_otp' => [

        /*
         | THE MASTER SWITCH. Off lets members sign in on password alone, exactly
         | as before this was added. Nothing is stored on the member row either
         | way, so it can be flipped back and forth freely.
         */
        'enabled' => env('PHS_LOGIN_OTP_ENABLED', true),

        'ttl_minutes' => env('PHS_LOGIN_OTP_TTL_MINUTES', 10),

        /*
         | How long the half-finished sign-in is held before the member must type
         | their password again. These are office users at a desk with the
         | mailbox already open, so this is shorter than the LAAS portal's 45.
         */
        'challenge_minutes' => env('PHS_LOGIN_OTP_CHALLENGE_MINUTES', 30),

        /*
         | A second sign-in attempt within this many minutes reuses the code
         | already sent rather than emailing another, so two open tabs do not
         | invalidate each other's code.
         */
        'reuse_minutes' => env('PHS_LOGIN_OTP_REUSE_MINUTES', 5),

        'email_subject' => env('PHS_LOGIN_OTP_EMAIL_SUBJECT', 'Your PHS Portal sign-in code'),
    ],

    /*
    |--------------------------------------------------------------------------
    | PHS Portal: collecting the member's mobile number
    |--------------------------------------------------------------------------
    |
    | phs_members.phone was added on 2026-10-01 and every existing row has none.
    | Rather than leave the column to fill itself, a member without a PROVED
    | number is held at a card on their next sign-in: type the mobile, receive a
    | code on it, type the code back. Nothing else in the portal opens until that
    | is done, which is the only way a column added to a live table ever gets
    | populated with numbers that actually work.
    |
    | The number is proved, not merely typed. An unproved number is worse than no
    | number: it looks like a usable second factor and is not. phs_institutions
    | .phone is evidence enough -- most of its rows are seeded test data that the
    | gateway would accept, bill for and never deliver.
    |
    */
    'phs_phone_setup' => [

        /*
         | THE MASTER SWITCH for the blocking card. Off lets members straight
         | through with no number; nothing is un-verified and no row changes, so
         | it can be switched back on at any time. This is the lever to pull if
         | the card turns out to be stopping people getting on with their work.
         */
        'enabled' => env('PHS_PHONE_SETUP_ENABLED', true),

        'ttl_minutes' => env('PHS_PHONE_SETUP_TTL_MINUTES', 10),

        /* Per member, per rolling hour, while they are trying to get a number confirmed. */
        'max_sms_per_hour' => env('PHS_PHONE_SETUP_MAX_SMS_PER_HOUR', 6),

        'message' => env(
            'PHS_PHONE_SETUP_MESSAGE',
            'KLAES PHS: {code} is your confirmation number for the mobile number on your PHS Portal account. It is valid for {minutes} minutes.'
        ),

        'message_fallback' => env('PHS_PHONE_SETUP_MESSAGE_FALLBACK', 'KLAES PHS: {code}'),
    ],

];
