<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Models\DocumentQrCode;
use App\Models\User;
use App\Services\DocumentQr\DocumentQrService;
use App\Services\DocumentQr\InvalidQrToken;
use App\Services\DocumentQr\QrPayloadReader;
use App\Services\DocumentQr\QrTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Document verification endpoint behind the verification console.
 *
 * Answers one question and only one: "is this document legitimate, and was it
 * printed in KLAES?" Provenance, not title. Holder names, property details and
 * consideration are deliberately NOT returned — a scan response that carried
 * them would become a permission bypass, and on the public portal a state-wide
 * land-ownership lookup.
 */
class DocumentVerificationController extends Controller
{
    public function __construct(
        private QrTokenService $tokens,
        private QrPayloadReader $reader,
        private DocumentQrService $service,
    ) {
    }

    public function verify(Request $request): JsonResponse
    {
        $payload = trim((string) $request->input('reference', ''));
        $channel = $request->input('channel') === 'qr_scan' ? 'qr_scan' : 'manual';

        if ($payload === '') {
            return response()->json(['success' => false, 'message' => 'Nothing to verify.'], 422);
        }

        $read = $this->reader->read($payload);

        // --- Q1: authenticated token -------------------------------------
        if ($read['kind'] === QrPayloadReader::KIND_Q1) {
            try {
                $claim = $this->tokens->verify($payload);
            } catch (InvalidQrToken $e) {
                $this->service->recordScan(null, 'tampered', $channel, 'Q1', $payload, $e->getMessage());

                return response()->json([
                    'success' => true,
                    'data'    => $this->tamperedResult($payload, $e->getMessage()),
                ]);
            }

            $qr = DocumentQrCode::find($claim['document_qr_id'] ?? 0);

            if (! $qr) {
                $this->service->recordScan(null, 'notfound', $channel, 'Q1', $payload, 'Token valid, row missing');

                return response()->json([
                    'success' => true,
                    'data'    => $this->notFound($payload, 'Q1', 'The token authenticated but its document record no longer exists.'),
                ]);
            }

            $verdict = $qr->isSuperseded() ? 'review' : 'authentic';
            $this->service->recordScan($qr, $verdict, $channel, 'Q1', $payload);

            return response()->json(['success' => true, 'data' => $this->result($qr, $payload, 'Q1', $verdict)]);
        }

        // --- Q0: legacy payloads -----------------------------------------
        if ($read['kind'] === QrPayloadReader::KIND_Q0_EMPTY) {
            $this->service->recordScan(null, 'unverifiable', $channel, 'Q0', $payload, 'Placeholder QR (N/A)');

            return response()->json([
                'success' => true,
                'data'    => $this->unverifiable($payload),
            ]);
        }

        $needle = $read['tracking_id'] ?: ($read['file_number'] ?: $read['reference']);

        $qr = $needle
            ? DocumentQrCode::where('tracking_id', $needle)->orWhere('file_number', $needle)->first()
            : null;

        if (! $qr) {
            $this->service->recordScan(null, 'notfound', $channel, $read['version'], $payload, 'No matching document');

            return response()->json([
                'success' => true,
                'data'    => $this->notFound($payload, $read['version']),
            ]);
        }

        // A legacy payload resolves a record but proves nothing about the paper,
        // so it can never reach "authentic".
        $this->service->recordScan($qr, 'review', $channel, $read['version'], $payload);

        return response()->json([
            'success' => true,
            'data'    => $this->result($qr, $payload, $read['version'], 'review', $read),
        ]);
    }

    /* ------------------------------------------------------------------ */

    private function result(DocumentQrCode $qr, string $payload, string $version, string $verdict, array $read = []): array
    {
        $type    = $qr->type();
        $legacy  = ($version !== 'Q1');
        $numeric = isset($read['tracking_id']) && $read['tracking_id']
            && $this->reader->numericIsEnumerable($read['tracking_id']);

        $chain = [];

        $chain[] = ['pass', 'Issued by KLAES', ($type ? $type->label() : $qr->document_type)
            . ($qr->issued_at ? ' · ' . $qr->issued_at->format('d M Y') : '')];

        $chain[] = $legacy
            ? ['warn', 'QR token signature valid', $numeric
                ? 'Legacy QR carrying a bare sequential number — nothing to authenticate'
                : 'Legacy QR — unsigned payload, nothing to authenticate']
            : ['pass', 'QR token signature valid', 'AES-256-GCM authentication tag verified against the Ministry key'];

        // "Document record resolved" (document_qr_codes #N) and the raw tracking ID
        // were removed from the officer-facing chain: both surfaced internal
        // plumbing. The tracking ID in particular is a bare sequence counter with
        // no business meaning — see the note on numericIsEnumerable(). The checks
        // still run; they are simply not rendered as their own rows.
        $chain[] = $qr->file_number
            ? ['pass', 'File number matches the register', $qr->file_number]
            : ['warn', 'File number matches the register', 'No file number stored against this document'];

        // ST files have NO grouping table — their tracking ID is generated at
        // commissioning. Everywhere else "no grouping row" is a failure, so this
        // must be reported as informational or every ST document reads as broken.
        if ($qr->tracking_id_source === 'commissioning') {
            $chain[] = ['info', 'Grouping record — not applicable',
                'ST files have no grouping table; the tracking ID is auto-generated at commissioning'];
        }

        $prints = $qr->printLogs()->get();

        $chain[] = $prints->count()
            ? ['pass', 'Print record exists', $prints->count() . ' print(s), first ' . optional($prints->first()->printed_at)->format('d M Y H:i')]
            : ['warn', 'Print record exists', 'This document has no recorded printing event'];

        if ($qr->isSuperseded()) {
            $chain[] = ['warn', 'Superseded by a re-issuance',
                'Replaced by document #' . $qr->superseded_by_id . ' — this copy is genuine but no longer current'];
        }

        // Document revocation is deferred; only 'active' and 'superseded' are
        // written today. When it is picked up, add the revocation check here.

        $scans = $qr->scanLogs()->limit(12)->get();

        return [
            'reference'  => $payload,
            'verdict'    => $verdict,
            'product'    => $this->productKey($type),
            'confidence' => $this->confidence($chain),
            'token'      => [
                'version' => $version === 'Q1' ? 'Q1' : 'Q0',
                'payload' => $payload,
                'fields'  => [
                    'Token version'   => $version === 'Q1'
                        ? 'Q1 — authenticated (AES-256-GCM)'
                        : 'Q0 — legacy, unsigned',
                    'Document ID'     => (string) $qr->id,
                    'Document type'   => (string) $qr->document_type,
                    'Signing key'     => $version === 'Q1' ? '#' . $qr->key_id : 'None',
                    'Auth tag'        => $version === 'Q1' ? 'Verified' : 'Not present on legacy QR',
                ],
            ],
            'document'   => array_filter([
                'Document'        => $type ? $type->label() : $qr->document_type,
                'File No.'        => $qr->file_number,
                'Tracking ID'     => $qr->tracking_id,
                'Tracking Source' => $qr->tracking_id_source,
                'Issued'          => optional($qr->issued_at)->format('d M Y H:i'),
                'Issued By'       => $this->userName($qr->issued_by),
                'Times Printed'   => (string) $qr->print_count,
                'Status'          => ucfirst((string) $qr->status),
            ]),
            // Deliberately empty: verification answers provenance only. Holder
            // and property come from the module screens under the operator's
            // own permissions.
            'property'   => [],
            'checks'     => $chain,
            'prints'     => $prints->map(fn ($p) => [
                (int) $p->print_number,
                $this->userName($p->printed_by),
                optional($p->printed_at)->format('d M Y H:i'),
                $p->reason ?: '—',
                ucfirst((string) $p->copy_type),
            ])->all(),
            'scans'      => $scans->map(fn ($s) => [
                optional($s->scanned_at)->format('d M Y H:i'),
                $this->userName($s->scanned_by) ?: 'Public',
                $s->channel,
                $s->result,
                $s->failure_reason ?: '',
            ])->all(),
            'timeline'   => $this->timeline($qr, $prints),
        ];
    }

    private function timeline(DocumentQrCode $qr, $prints): array
    {
        $items = [];

        if ($qr->issued_at) {
            $items[] = ['QR identity issued', $qr->issued_at->format('d M Y H:i')
                . ($this->userName($qr->issued_by) ? ' · ' . $this->userName($qr->issued_by) : '')];
        }

        foreach ($prints as $p) {
            $items[] = [
                $p->print_number === 1 ? 'Original printed' : 'Reprint #' . $p->print_number,
                optional($p->printed_at)->format('d M Y H:i') . ' · ' . ($p->reason ?: '—'),
            ];
        }

        if ($qr->isSuperseded()) {
            $items[] = ['Superseded by re-issuance', 'Replaced by document #' . $qr->superseded_by_id, true];
        }

        return $items ?: [['No recorded history', 'This document has no issue or print events on file']];
    }

    private function tamperedResult(string $payload, string $reason): array
    {
        return [
            'reference'  => $payload,
            'verdict'    => 'tampered',
            'product'    => null,
            'confidence' => '0%',
            'token'      => [
                'version' => 'BAD',
                'payload' => $payload,
                'fields'  => [
                    'Token version' => 'Q1 header present',
                    'Document ID'   => 'Unreadable — payload failed authentication',
                    'Auth tag'      => 'REJECTED — does not match the Ministry key',
                ],
            ],
            'document'   => ['Payload scanned' => $payload, 'Failure' => $reason],
            'property'   => [],
            'checks'     => [
                ['fail', 'QR token signature valid', $reason],
                ['skip', 'Document record resolved', 'Not attempted — the token was rejected first'],
                ['skip', 'Tracking ID matches', 'Not attempted'],
                ['warn', 'Report to the registry', 'Retain the physical document and raise a flag'],
            ],
            'prints'     => [],
            'scans'      => [],
            'timeline'   => [['Tampered QR presented', 'Rejected at the verification console', true]],
        ];
    }

    private function unverifiable(string $payload): array
    {
        return [
            'reference'  => $payload,
            'verdict'    => 'review',
            'product'    => null,
            'confidence' => '0%',
            'token'      => ['version' => 'Q0', 'payload' => $payload, 'fields' => [
                'Token version' => 'Q0 — placeholder',
                'Document ID'   => 'Not encoded',
            ]],
            'document'   => ['Payload scanned' => $payload],
            'property'   => [],
            'checks'     => [
                ['warn', 'Placeholder QR', 'This QR was printed with the literal text "N/A" — a KLAES defect on genuine paper, not a suspicious document'],
                ['skip', 'Document record resolved', 'Nothing to resolve'],
                ['warn', 'Manual registry search required', 'Verify from the file number printed on the sheet'],
            ],
            'prints'     => [],
            'scans'      => [],
            'timeline'   => [['Unverifiable QR', 'The code carries no identifier', true]],
        ];
    }

    private function notFound(string $payload, ?string $version, ?string $note = null): array
    {
        return [
            'reference'  => $payload,
            'verdict'    => 'notfound',
            'product'    => null,
            'confidence' => '0%',
            'token'      => ['version' => $version, 'payload' => $payload, 'fields' => []],
            'document'   => array_filter([
                'Reference searched' => $payload,
                'Register match'     => 'None',
                'Note'               => $note,
            ]),
            'property'   => [],
            'checks'     => [
                ['fail', 'Reference found in register', $note ?: 'No matching record'],
                ['skip', 'QR token signature valid', 'Nothing to authenticate'],
                ['warn', 'Manual registry search advised', 'Check archived / pre-digital registers'],
            ],
            'prints'     => [],
            'scans'      => [],
            'timeline'   => [['No register history', 'This reference has no document record', true]],
        ];
    }

    private function confidence(array $chain): string
    {
        $total = count($chain) ?: 1;
        $score = 0;

        foreach ($chain as $step) {
            $score += match ($step[0]) {
                'pass'  => 1.0,
                'info'  => 1.0,
                'warn'  => 0.5,
                default => 0.0,
            };
        }

        return round(($score / $total) * 100) . '%';
    }

    /** JS keys are lowercase; SITE_PLAN is the one that is not a straight fold. */
    private function productKey(?DocumentType $type): ?string
    {
        if ($type === null) {
            return null;
        }

        return $type === DocumentType::SITE_PLAN ? 'siteplan' : strtolower($type->value);
    }

    private function userName(?int $id): ?string
    {
        static $cache = [];

        if (! $id) {
            return null;
        }

        if (! array_key_exists($id, $cache)) {
            $user = User::find($id);
            $cache[$id] = $user ? trim($user->name ?: ($user->first_name . ' ' . $user->last_name)) : null;
        }

        return $cache[$id] ?: null;
    }
}
