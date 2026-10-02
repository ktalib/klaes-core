<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Staff attendance SMS
    |--------------------------------------------------------------------------
    |
    | One sign-in SMS and one sign-out SMS per member of staff per day, sent
    | through Bulk-SMS.ng (sender ID KANOMLPP — see config/services.php).
    |
    | Every value here has a working DEFAULT rather than living only in .env.
    | .env is gitignored and does NOT travel with a code upload, so a key that
    | exists only there is simply absent on a freshly deployed server.
    |
    */

    // Master switch. Turn the whole feature off without touching code.
    'enabled' => env('STAFF_SMS_ENABLED', true),

    'login' => [
        'enabled' => env('STAFF_SMS_LOGIN_ENABLED', true),
    ],

    'logout' => [
        'enabled' => env('STAFF_SMS_LOGOUT_ENABLED', true),

        /*
         | The sign-out SMS only goes out once the member of staff has reached
         | the end of their own shift, so a lunch-break sign-out does not spend
         | the day's message. Shift times come from config('attendance.shifts'),
         | which every user row already carries a shift_code for:
         |
         |   full_day  09:00-17:00   (1,296 staff — this is the "5pm" case)
         |   morning   09:00-13:00
         |   afternoon 13:00-17:00
         |   night     17:00-21:00
         |   overnight 21:00-04:00   (ends the following morning)
         |
         | Used when a user's shift_code matches nothing in that list.
         */
        'default_shift_end' => env('STAFF_SMS_DEFAULT_SHIFT_END', '17:00'),
    ],

    /*
     | THE CLOCK THIS FEATURE RUNS ON.
     |
     | config('app.timezone') is UTC on this deployment while Kano is WAT
     | (UTC+1), so now() reads 16:00 when the wall clock in the office says
     | 17:00. Comparing a shift end of "17:00" against a UTC clock would hold
     | every sign-out SMS back by an hour, and would put a 00:30 WAT sign-out on
     | the wrong day. Shift-end tests and the once-a-day date are therefore both
     | evaluated in this timezone, not the app default.
     */
    'timezone' => env('STAFF_SMS_TIMEZONE', 'Africa/Lagos'),

];
