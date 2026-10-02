<?php

namespace App\Http\Controllers;

use App\Services\DeedsPipelineStatus;
use Illuminate\Http\Request;

/**
 * Serves the Valuation -> Consent -> Print -> Registration status the Deeds screens
 * paint their workflow indicators from.
 *
 * Read-only. The screens ask for a page of file numbers at a time rather than
 * one per row, so a list of several hundred rows costs three queries.
 */
class DeedsPipelineController extends Controller
{
    public function __construct(private DeedsPipelineStatus $pipeline)
    {
    }

    /**
     * Stage status for a set of files.
     *
     * Accepts files[] (the list screens) or a single file_number with an
     * optional consent_type (the capture screen, which cares about one dealing).
     */
    public function status(Request $request)
    {
        $single = trim((string) $request->query('file_number', ''));

        if ($single !== '') {
            $consentType = trim((string) $request->query('consent_type', '')) ?: null;

            return response()->json([
                'success' => true,
                'gates' => $this->gates(),
                'data' => [
                    strtoupper($single) => $this->pipeline->forFile($single, $consentType),
                ],
            ]);
        }

        $files = (array) $request->query('files', []);

        // Capped so a crafted or runaway request cannot ask for the whole
        // register in one call.
        if (count($files) > 500) {
            $files = array_slice($files, 0, 500);
        }

        return response()->json([
            'success' => true,
            'gates' => $this->gates(),
            'data' => $this->pipeline->forFiles($files),
        ]);
    }

    /**
     * How strictly each hand-off is enforced, so the screens can word their
     * warnings to match what the server will actually do on save.
     *
     * @return array<string, string>
     */
    private function gates(): array
    {
        return [
            'valuation_before_consent' => $this->pipeline->gateMode('valuation_before_consent'),
            'consent_before_registration' => $this->pipeline->gateMode('consent_before_registration'),
            'consent_print_before_registration' => $this->pipeline->gateMode('consent_print_before_registration'),
        ];
    }
}
