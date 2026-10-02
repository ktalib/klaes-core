<?php

namespace App\Http\Controllers;

use App\Support\LandRecommendationLog as RecLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Receiver for the Recommendation capture screen's own account of itself.
 *
 * A batch capture runs for as long as it takes to key 40+ children, which is long
 * enough for the session to drop, a draft restore to paint the table back wrongly,
 * or a script error to leave the form half-wired. None of that reaches the server
 * on its own, so the log would show a capture that simply stopped.
 * public/js/land-recommendation-diagnostics.js therefore records what the page
 * does — batch mode and kind changes, mother selection, rows loaded/checked, draft
 * saves and restores, submits and their outcome, script errors, unloads — and
 * ships the batch here (by beacon on the way out, so a trace survives the page
 * that produced it).
 *
 * The entries land in storage/logs/land_recommendation.log alongside
 * {@see LandRecommendationController}'s and
 * {@see LandRecommendationBatchDraftController}'s own, so one capture reads as one
 * timeline.
 *
 * This endpoint accepts what a browser says about itself, which is not evidence of
 * anything beyond the browser: it is bounded (events per batch, length per field),
 * carries counts and file numbers rather than the typed capture, and is written at
 * info/warning only.
 */
class LandRecommendationDiagnosticsController extends Controller
{
    /** Events accepted per batch; the client flushes well below this. */
    private const MAX_EVENTS = 60;

    /** Events that mean "the capture is in trouble" and deserve the warning level. */
    private const NOTABLE = [
        'script_error',
        'unhandled_rejection',
        'session_expired',
        'draft_save_failed',
        'draft_restore_failed',
        'rows_dropped',
        'children_load_failed',
        'submit_http_error',
        'submit_blocked',
        'unload_with_unsaved_rows',
        'validation_errors_shown',
    ];

    public function __construct()
    {
        // Deliberately NOT behind 'auth'. A session that dropped part-way through a
        // long batch capture is one of the things this trace exists to prove, and an
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
            'draft_key' => 'nullable|string|max:64',
            'mother_file_no' => 'nullable|string|max:120',
            'batch_kind' => 'nullable|string|max:20',
            'events' => 'required|array|min:1',
            'events.*.event' => 'required|string|max:60',
            'events.*.at' => 'nullable|string|max:40',
            'events.*.detail' => 'nullable|array',
        ]);

        $events = array_slice($validated['events'], 0, self::MAX_EVENTS);

        foreach ($events as $event) {
            $name = (string) $event['event'];
            $level = in_array($name, self::NOTABLE, true) ? 'warning' : 'info';

            RecLog::{$level}('[client] ' . $name, [
                'trace_id' => $validated['trace_id'] ?? null,
                'draft_key' => $validated['draft_key'] ?? null,
                'mother_file_no' => $validated['mother_file_no'] ?? null,
                'batch_kind' => $validated['batch_kind'] ?? null,
                // False on a beacon that arrived after the session went — the
                // clearest single signal for a capture that died on a 419.
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
     * form.
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

            $trimmed[substr((string) $key, 0, 40)] = is_scalar($value) || $value === null
                ? substr((string) $value, 0, 300)
                : null;
        }

        return $trimmed;
    }
}
