<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'print-manager/*',
        'api/file-trackers/*',
        'api/mobile/*',

        // Diagnostics beacon for the Add/Edit Buyers screen. A stale token is one
        // of the failures it exists to report, and it is sent by sendBeacon on
        // unload — where a 419 would silently discard the last thing the page had
        // to say. Writes nothing but bounded strings to a log file.
        'buyer/client-log',

        // Same for the Recommendation capture screen's trace: sent by sendBeacon on
        // the way out, and a stale token part-way through a long batch capture is
        // itself one of the things it exists to report.
        'land-recommendations/client-log',

        // Same for the Commission New File Number modal's trace: sent by sendBeacon
        // on the way out, and a stale token at the moment of Generate is itself one
        // of the things it exists to report.
        'mls-fileno/client-log',
    ];
}
