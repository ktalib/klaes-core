<?php

namespace App\Http\Controllers;

use App\Support\BuyerListLog as Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Receiver for the Add/Edit Buyers screen's own account of itself.
 *
 * Officers report that the buyers form "just closes" mid-entry and the typed rows
 * are gone. Nothing reaches the server when that happens, so the server log is
 * silent and there is nothing to read back. public/js/buyer-list-diagnostics.js
 * therefore records what the page does — rows added and removed, the Alpine
 * component initialising, submits and their responses, script errors, unloads and
 * navigations — and ships the batch here (by beacon on the way out, so a trace
 * survives the page that produced it).
 *
 * The entries land in storage/logs/buyer_list.log alongside
 * {@see BuyerListController}'s own, so one file reads as one timeline.
 *
 * This endpoint accepts what a browser says about itself, which is not evidence
 * of anything beyond the browser: it is bounded (events per batch, length per
 * field), carries no free-typed buyer data beyond field names and counts, and is
 * written at info/warning only.
 */
class BuyerListDiagnosticsController extends Controller
{
    /** Events accepted per batch; the client flushes well below this. */
    private const MAX_EVENTS = 60;

    /** Events that mean "the capture was lost" and deserve the warning level. */
    private const NOTABLE = [
        'component_reinitialised',
        'rows_dropped',
        'form_emptied',
        'script_error',
        'unhandled_rejection',
        'submit_failed',
        'submit_http_error',
        'session_expired',
        'unload_with_unsaved_rows',
        'draft_save_failed',
    ];

    public function __construct()
    {
        // Deliberately NOT behind 'auth'. A dropped session is the leading suspect
        // for the form emptying itself, and an authenticated-only endpoint would
        // bounce the one beacon that could prove it. Nothing is read here and
        // nothing is written but bounded strings to a log file; the throttle caps
        // the noise, and every entry records whether a user was still signed in.
        $this->middleware('throttle:240,1');
    }

    public function clientLog(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'trace_id' => 'nullable|string|max:64',
            'application_id' => 'nullable|integer',
            'file_no' => 'nullable|string|max:120',
            'events' => 'required|array|min:1',
            'events.*.event' => 'required|string|max:60',
            'events.*.at' => 'nullable|string|max:40',
            'events.*.detail' => 'nullable|array',
        ]);

        $traceId = $validated['trace_id'] ?? null;
        $events = array_slice($validated['events'], 0, self::MAX_EVENTS);

        foreach ($events as $event) {
            $name = (string) $event['event'];
            $level = in_array($name, self::NOTABLE, true) ? 'warning' : 'info';

            Log::{$level}('[client] ' . $name, [
                'trace_id' => $traceId,
                'application_id' => $validated['application_id'] ?? null,
                'file_no' => $validated['file_no'] ?? null,
                // False on a beacon that arrived after the session went: the
                // clearest single signal for the reported symptom.
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
     * report, not a payload dump — the point is to read it later, not to mirror
     * the form.
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
                ? substr((string) $value, 0, 500)
                : null;
        }

        return $trimmed;
    }
}
