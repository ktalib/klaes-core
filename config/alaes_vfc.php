<?php

return [
    /*
     | ALAES VFC login notifications. The recipient is intentionally fixed: a
     | browser must never be able to choose a number to message through this
     | endpoint.
     */
    'login_sms' => [
        'enabled'          => env('ALAES_VFC_LOGIN_SMS_ENABLED', true),
        'recipient'        => env('ALAES_VFC_LOGIN_SMS_RECIPIENT', '08034989043'),
        'sender'           => env('ALAES_VFC_LOGIN_SMS_SENDER', 'ALAES'),
        'cooldown_minutes' => env('ALAES_VFC_LOGIN_SMS_COOLDOWN', 5),
        'timezone'         => env('ALAES_VFC_TIMEZONE', 'Africa/Lagos'),
    ],
];
