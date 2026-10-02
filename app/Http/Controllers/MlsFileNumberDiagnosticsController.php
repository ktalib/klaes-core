<?php

namespace App\Http\Controllers;

use App\Support\MlsFileNumberLog as MlsLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Receiver for the Commission New File Number modal's own account of itself.
 *
 * The screen's failure message is a single sentence — "An error occurred while
 * generating the file number" — and the browser knows things about that failure the
 * server never hears: the HTTP status behind it, whether the body was JSON at all,
 * whether the request timed out client-side, whether the officer had already been
 * signed out, and whether the Generate that failed was a first attempt or the
 * Override retry. A 500 rendered before the route middleware even runs (an empty
 * APP_KEY does exactly this) leaves no server-side trace of the commissioning at
 * all; without this endpoint the log simply has no entry for the click.
 *
 * public/js/mls-file-number-diagnostics.js therefore records what the modal does —
 * opened/closed, application and allocation type, batch mode, prefix and land use,
 * serial and preview changes, related-file selection, the submit and its outcome,
 * script errors, unloads — and ships the batch here (by beacon on the way out, so a
 * trace survives the page that produced it).
 *
 * The entries land in storage/logs/mls_file_number.log alongside
 * {@see MlsFileNoController}'s own, under one trace id per page visit, so one
 * commissioning reads as one timeline.
 *
 * This endpoint accepts what a browser says about itself, which is not evidence of
 * anything beyond the browser: it is bounded (events per batch, length per field),
 * carries file numbers, serials and HTTP statuses rather than the typed capture, and
 * is written at info/warning only.
 */
class MlsFileNumberDiagnosticsController extends Controller
{
    /** Events accepted per batch; the client flushes well below this. */
    private const MAX_EVENTS = 60;

    /** Events that mean "the commissioning is in trouble" and deserve warning level. */
    private const NOTABLE = [
        'script_error',
        'unhandled_rejection',
        'session_expired',
        'generate_http_error',
        'generate_not_json',
        'generate_timeout',
        'generate_network_error',
        'generate_rejected',
        'generate_blocked',
        'serial_status_failed',
        'dependent_data_failed',
        'preview_empty_on_submit',
        'unload_with_unsaved_form',
    ];

    public function __construct()
    {
        // Deliberately NOT behind 'auth'. A session that dropped while the modal was
        // open is one of the things this trace exists to prove, and an
        // authenticated-only endpoint would bounce exactly that beacon. Nothing is
        // read here and nothing is written but bounded strings to a log file; the
        // throttle caps the noise, and every entry records whether a user was still
        // signed in.
        $this->middleware('throttle:240,1');
    }

    public function clientLog(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'trace_id' => 'nullable|string|max:64',
            // The tracking id is the one identifier that spans the browser, the
            // fileNumber row and both tracking lines, so it is lifted out of the
            // per-event detail and stamped on every entry in the batch.
            'tracking_id' => 'nullable|string|max:64',
            'file_number_preview' => 'nullable|string|max:120',
            'application_type' => 'nullable|string|max:40',
            'batch_mode' => 'nullable|string|max:10',
            'events' => 'required|array|min:1',
            'events.*.event' => 'required|string|max:60',
            'events.*.at' => 'nullable|string|max:40',
            'events.*.detail' => 'nullable|array',
        ]);

        $events = array_slice($validated['events'], 0, self::MAX_EVENTS);

        foreach ($events as $event) {
            $name = (string) $event['event'];
            $level = in_array($name, self::NOTABLE, true) ? 'warning' : 'info';

            MlsLog::{$level}('[client] ' . $name, [
                'trace_id' => $validated['trace_id'] ?? null,
                'tracking_id' => $validated['tracking_id'] ?? null,
                'file_number_preview' => $validated['file_number_preview'] ?? null,
                'application_type' => $validated['application_type'] ?? null,
                'batch_mode' => $validated['batch_mode'] ?? null,
                // False on a beacon that arrived after the session went — the
                // clearest single signal for a Generate that died on a 419.
                'authenticated' => Auth::check(),
                // The browser's own clock: a gap between this and the entry's
                // timestamp is what shows a beacon arriving after the fact.
                'client_at' => $event['at'] ?? null,
                'detail' => $this->trimDetail($event['detail'] ?? []),
            ]);
        }

        return response()->json(['success' => true, 'received' => count($events)]);
    }

    /**
     * Keep the detail bag small and flat. Anything nested or long is a page bug
     * report, not a payload dump — the point is to read it later, not to mirror the
     * form. The one field allowed to run long is body_excerpt, which is how a 500
     * rendered as HTML instead of JSON gets identified from the log alone.
     */
    private function trimDetail(array $detail): array
    {
        $trimmed = [];

        foreach (array_slice($detail, 0, 20, true) as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            } elseif (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }

            $key = substr((string) $key, 0, 40);
            $limit = $key === 'body_excerpt' ? 600 : 300;

            $trimmed[$key] = is_scalar($value) || $value === null
                ? substr((string) $value, 0, $limit)
                : null;
        }

        return $trimmed;
    }
}
