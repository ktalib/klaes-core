<?php

return [
    'early_check_in_minutes' => 30,
    'late_tolerance_minutes' => 0,
    'max_consecutive_absences' => 3,
    'max_monthly_absences' => 5,
    'auto_logout' => [
        'enabled' => env('ATTENDANCE_AUTO_LOGOUT', true),
        'grace_minutes' => env('ATTENDANCE_AUTO_LOGOUT_GRACE', 10),
        'chunk_size' => env('ATTENDANCE_AUTO_LOGOUT_CHUNK', 100),
        'track_overtime' => env('ATTENDANCE_AUTO_LOGOUT_ALLOW_OVERTIME', true),
        'overtime_extension_minutes' => env('ATTENDANCE_AUTO_LOGOUT_OVERTIME_EXTENSION', 120),
    ],
    /*
    |--------------------------------------------------------------------------
    | Sign-in / sign-out notifications (in-app)
    |--------------------------------------------------------------------------
    |
    | The bell entry that goes with the attendance SMS (config/staff_sms.php).
    | It fires on EVERY sign-in and EVERY sign-out — a notification is a local
    | row, so none of the cost-driven rules the SMS carries (one a day, held
    | until shift end, skipped without a phone number) apply here.
    |
    | Defaults are ON, and they are defaults rather than .env-only keys: .env is
    | gitignored and does not travel with a code upload, so a feature that lived
    | only there would be silently off on a freshly deployed server.
    |
    */
    'notifications' => [
        'enabled' => env('ATTENDANCE_NOTIFICATIONS_ENABLED', true),
        'login' => env('ATTENDANCE_NOTIFY_LOGIN', true),
        'logout' => env('ATTENDANCE_NOTIFY_LOGOUT', true),

        /*
         | The office clock. config('app.timezone') is UTC on this deployment
         | while Kano is WAT (UTC+1), so a body built from now() would report a
         | 09:12 sign-in as 08:12.
         */
        'timezone' => env('ATTENDANCE_TIMEZONE', 'Africa/Lagos'),

        /*
         | Collapse identical notices raised within this many seconds of each
         | other. ActivityLogService finalises sessions row by row, so a user
         | holding several stale session rows is auto-logged-out several times
         | in one loop. Set to 0 to record every single event.
         */
        'duplicate_window_seconds' => env('ATTENDANCE_NOTIFY_DEDUPE_SECONDS', 60),
    ],
    'shifts' => [
        'morning' => [
            'label' => 'Morning (9:00 AM - 1:00 PM)',
            'start' => '09:00',
            'end' => '13:00',
            'overnight' => false,
        ],
        'afternoon' => [
            'label' => 'Afternoon (1:00 PM - 5:00 PM)',
            'start' => '13:00',
            'end' => '17:00',
            'overnight' => false,
        ],
        'night' => [
            'label' => 'Night (5:00 PM - 9:00 PM)',
            'start' => '17:00',
            'end' => '21:00',
            'overnight' => false,
        ],
        'overnight' => [
            'label' => 'Overnight (9:00 PM - 4:00 AM)',
            'start' => '21:00',
            'end' => '04:00',
            'overnight' => true,
        ],
        'full_day' => [
            'label' => 'Full Day (9:00 AM - 5:00 PM)',
            'start' => '09:00',
            'end' => '17:00',
            'overnight' => false,
        ],
    ],
    'holidays' => [
        // Add YYYY-MM-DD entries here or plug into a dedicated holiday provider service.
    ],
];
