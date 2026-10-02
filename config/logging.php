<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that gets used when writing
    | messages to the logs. The name specified in this option should match
    | one of the channels defined in the "channels" configuration array.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    |
    | This option controls the log channel that should be used to log warnings
    | regarding deprecated PHP and library features. This allows you to get
    | your application ready for upcoming major versions of dependencies.
    |
    */

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Out of
    | the box, Laravel uses the Monolog PHP logging library. This gives
    | you a variety of powerful log handlers / formatters to utilize.
    |
    | Available Drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog",
    |                    "custom", "stack"
    |
    */

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['single', 'daily'],
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 14,
        ],

        'pra_submissions' => [
            'driver' => 'daily',
            'path' => storage_path('logs/pra_submissions.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
        ],

        'pic_submissions' => [
            'driver' => 'daily',
            'path' => storage_path('logs/pic_submissions.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
        ],

        'fileno_duplicates' => [
            'driver' => 'daily',
            'path' => storage_path('logs/fileno_duplicates.log'),
            'level' => 'info',
            'days' => 90,
        ],

        'mls_batch' => [
            'driver' => 'daily',
            'path' => storage_path('logs/mls_batch.log'),
            'level' => 'info',
            'days' => 90,
        ],

        'mls_batch_errors' => [
            'driver' => 'daily',
            'path' => storage_path('logs/mls_batch_errors.log'),
            'level' => 'error',
            'days' => 90,
        ],

        // OP Batch Commissioning — the remediation view for files commissioned through
        // the Batch Mode that was enabled by mistake. Kept on its own channel so the
        // payload sizes and timings for this one screen are readable in isolation.
        'op_batch' => [
            'driver' => 'daily',
            'path' => storage_path('logs/op_batch.log'),
            'level' => 'debug',
            'days' => 90,
        ],

        // Primary Application Form (Sectional Titling main application) — the form is
        // long, multi-step and heavily instrumented, so its debug dumps drown out
        // everything else in laravel.log. Kept on its own file end-to-end: draft
        // autosave, submission, buyer/EDMS processing and file-number allocation.
        'primary_form' => [
            'driver' => 'daily',
            'path' => storage_path('logs/primary_form.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],

        // Add/Edit Buyers (Sectional Titling buyers list). Officers report the
        // capture form emptying itself mid-entry without an error, which nothing
        // in laravel.log accounts for — so this channel carries both server-side
        // CRUD and the browser's own account of the session (rows added/removed,
        // submits, navigations, script errors, draft autosaves) on one timeline.
        'buyer_list' => [
            'driver' => 'daily',
            'path' => storage_path('logs/buyer_list.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],

        // Instrument capture/registration (/instruments/create). A failed capture
        // surfaces in the browser only as "invalid response format from the server",
        // and the reason is otherwise buried among every other request in
        // laravel.log — which is unusable on production, where the officer who hit
        // the error is not the person with log access. This channel carries the
        // submitted payload, the registration number issued, the party-name sync
        // outcome and the full failure (exception class, file:line, trace) for one
        // capture on one timeline.
        'instrument_capture' => [
            'driver' => 'daily',
            'path' => storage_path('logs/instrument_capture.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 60,
        ],

        // Recommendation capture (/land-recommendations/create). One screen saves a
        // single recommendation or a whole subdivision/regular batch, autosaving a
        // draft throughout — so a report of "I keyed 40 children and lost them" has
        // to be answered from several places at once (what was posted, what the
        // validator rejected, what the draft held, what the browser was doing).
        // This channel carries all of it, server side and the page's own trace,
        // on one timeline instead of scattered through laravel.log.
        'land_recommendation' => [
            'driver' => 'daily',
            'path' => storage_path('logs/land_recommendation.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 60,
        ],

        // MLPP File Number commissioning (/mls-fileno, the Commission New File
        // Number modal in resources/views/generate_fileno/mlsfno.blade.php).
        // One click on Generate runs the longest write path in the system: serial
        // reservation, prefix/land-use resolution, prop_id allocation, the fileNumber
        // row, PRA/instrument mirrors, tracking lines and the EDMS folder. When it
        // fails the officer is told only "An error occurred while generating the file
        // number", so the question is always which of those stages it reached — and
        // that answer was previously scattered through laravel.log between other
        // users' indexing traffic. This channel carries the whole screen, server side
        // and the page's own trace, on one timeline keyed by tracking id.
        //
        // Batch generation keeps its own mls_batch / mls_batch_errors channels; this
        // one is the single-file path and everything around it.
        'mls_file_number' => [
            'driver' => 'daily',
            'path' => storage_path('logs/mls_file_number.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 60,
        ],

        // Plot Subdivision (Deeds → Parcel Update). Subdivision mutates parcel
        // lineage, so a per-application audit trail of who captured/approved/rejected
        // what is worth keeping separate from the general log.
        'plot_subdivision' => [
            'driver' => 'daily',
            'path' => storage_path('logs/plot_subdivision.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 90,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => 'Laravel Log',
            'emoji' => ':boom:',
            'level' => env('LOG_LEVEL', 'critical'),
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => env('LOG_PAPERTRAIL_HANDLER', SyslogUdpHandler::class),
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
                'connectionString' => 'tls://'.env('PAPERTRAIL_URL').':'.env('PAPERTRAIL_PORT'),
            ],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'with' => [
                'stream' => 'php://stderr',
            ],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],
    ],

];
