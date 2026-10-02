<?php

return [
    'leaderboard_limit' => env('ACTIVITY_MONITORING_LEADERBOARD_LIMIT', 60),

    'daily_targets' => [
        'file_indexing' => 100,
        'pic' => 150,
        'pra' => 120,
    ],

    'workday' => [
        'start_hour' => env('ACTIVITY_WORKDAY_START', 9),
        'end_hour' => env('ACTIVITY_WORKDAY_END', 17),
    ],
];
