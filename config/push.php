<?php

/*
 * Push notifications to the KLAES FTM Android app through Firebase Cloud
 * Messaging (HTTP v1 API).
 *
 * Off until a Firebase service-account key is in place: download it from
 * Firebase console > Project settings > Service accounts > Generate new
 * private key, save it outside public/ (e.g. storage/app/firebase/fcm-service-account.json)
 * and set FCM_CREDENTIALS to that path. The project id is read from the key
 * unless FCM_PROJECT_ID overrides it.
 */
return [
    'enabled' => env('FCM_ENABLED', true),

    'credentials' => env('FCM_CREDENTIALS', storage_path('app/firebase/fcm-service-account.json')),

    'project_id' => env('FCM_PROJECT_ID'),

    // Must match the channel the app creates (lib/services/push_service.dart).
    'android_channel_id' => 'klaes_ftm_alerts',

    // Seconds per FCM call; pushes are sent after the response, but on mod_php the
    // browser still waits for the script to finish, so keep this short.
    'timeout' => 5,

    // Where a tap on the notification takes the app.
    'open_path' => '/mobile/dashboard#notifications',
];
