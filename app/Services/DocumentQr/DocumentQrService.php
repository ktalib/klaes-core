<?php

namespace App\Services\DocumentQr;

use App\Enums\DocumentType;
use App\Models\DocumentPrintLog;
use App\Models\DocumentQrCode;
use App\Models\DocumentScanLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Issues QR identities for printable documents and records print/scan audit.
 *
 * Approach A: one QR per document instance. A reprint (certified copy, damaged
 * original) SHARES the QR and adds a print-log row; a re-issuance on fresh
 * security paper mints a new instance (see supersede()). The test is whether a
 * new security-paper serial was consumed.
 */
class DocumentQrService
{
    public function __construct(
        private QrTokenService $tokens,
    ) {
    }

    /**
     * Get the existing QR for a document, or mint one.
     *
     * Idempotent per (document_type, source_id) — enforced by the filtered
     * unique index, so a concurrent second print cannot create a rival token.
     *
     * @param  array{file_indexing_id?:int|null, file_number?:string|null,
     *               tracking_id?:string|null, tracking_id_source?:string|null,
     *               source_table?:string|null}  $identity
     */
    public function issue(DocumentType $type, ?int $sourceId, array $identity = []): DocumentQrCode
    {
        if ($sourceId !== null) {
            // Only an ACTIVE row is reusable. Superseded generations stay in the
            // table and keep verifying, but a reprint must never resurrect one.
            $existing = DocumentQrCode::query()
                ->where('document_type', $type->value)
                ->where('source_id', $sourceId)
                ->where('status', 'active')
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        return DB::connection('sqlsrv')->transaction(function () use ($type, $sourceId, $identity) {
            $qr = DocumentQrCode::create([
                'document_type'      => $type->value,
                'source_table'       => $identity['source_table'] ?? null,
                'source_id'          => $sourceId,
                'file_indexing_id'   => $identity['file_indexing_id'] ?? null,
                'file_number'        => $identity['file_number'] ?? null,
                'tracking_id'        => $identity['tracking_id'] ?? null,
                'tracking_id_source' => $identity['tracking_id_source'] ?? $type->trackingIdSource(),
                'qr_version'         => (int) config('document_qr.version', 1),
                'key_id'             => $this->tokens->activeKeyId(),
                'token_hash'         => str_repeat('0', 64),   // placeholder, replaced below
                'status'             => 'active',
                'issued_at'          => now(),
                'issued_by'          => Auth::id(),
                'print_count'        => 0,
            ]);

            // The token encodes the row id, so it can only be built after the
            // insert. Refresh first so the mint uses the value as STORED — the
            // token embeds the issue date, and reading it back from the column
            // keeps later re-mints byte-identical. Store the hash, never the
            // token itself.
            $qr->refresh();

            $token = $this->tokens->mint($qr->id, $type, $qr->issued_at?->timestamp);
            $qr->update(['token_hash' => $this->tokens->hash($token)]);

            return $qr->refresh();
        });
    }

    /**
     * The string to encode into the printed QR image.
     */
    public function tokenFor(DocumentQrCode $qr): string
    {
        $type = $qr->type();

        if ($type === null) {
            throw new \RuntimeException("Unknown document type '{$qr->document_type}' on QR #{$qr->id}.");
        }

        return $this->tokens->mint($qr->id, $type, $qr->issued_at?->timestamp);
    }

    /**
     * Record a printing event. Call this from the print path, not the render
     * path — a preview is not a print.
     */
    public function recordPrint(
        DocumentQrCode $qr,
        ?string $reason = null,
        ?string $batchReference = null
    ): DocumentPrintLog {
        return DB::connection('sqlsrv')->transaction(function () use ($qr, $reason, $batchReference) {
            $next = ((int) $qr->print_count) + 1;

            $log = DocumentPrintLog::create([
                'document_qr_id'  => $qr->id,
                'print_number'    => $next,
                'copy_type'       => $next === 1 ? 'original' : 'reprint',
                'printed_by'      => Auth::id(),
                'printed_at'      => now(),
                'reason'          => $reason ?: ($next === 1 ? 'Original issue' : 'Reprint'),
                'batch_reference' => $batchReference,
                'ip_address'      => request()?->ip(),
                'user_agent'      => substr((string) request()?->userAgent(), 0, 1000),
            ]);

            $qr->update([
                'print_count'     => $next,
                'last_printed_at' => now(),
                'last_printed_by' => Auth::id(),
            ]);

            return $log;
        });
    }

    /**
     * Record a scan attempt, successful or not.
     */
    public function recordScan(
        ?DocumentQrCode $qr,
        string $result,
        string $channel = 'manual',
        ?string $versionSeen = null,
        ?string $rawPayload = null,
        ?string $failureReason = null
    ): DocumentScanLog {
        // Evidence on failure only — never keep a plaintext copy of a valid
        // token, which would amount to a forgery kit.
        $failed = ! in_array($result, ['authentic', 'review'], true);

        return DocumentScanLog::create([
            'document_qr_id'  => $qr?->id,
            'qr_version_seen' => $versionSeen,
            'raw_payload'     => $failed ? substr((string) $rawPayload, 0, 512) : null,
            'scanned_at'      => now(),
            'scanned_by'      => Auth::id(),
            'channel'         => $channel,
            'ip_address'      => request()?->ip(),
            'user_agent'      => substr((string) request()?->userAgent(), 0, 1000),
            'result'          => $result,
            'failure_reason'  => $failureReason,
        ]);
    }

    /**
     * Re-issuance on fresh security paper: mint a new instance and point the
     * old one at it.
     *
     * The superseded token keeps verifying by design and reports "authentic,
     * but superseded by …". Making it stop resolving is the one outcome to
     * avoid — a dead QR is indistinguishable from a forgery, so someone holding
     * the earlier paper in good faith would be told their genuine document is
     * fake.
     */
    public function supersede(DocumentQrCode $old, ?int $newSourceId = null): DocumentQrCode
    {
        $type = $old->type();

        if ($type === null) {
            throw new \RuntimeException("Unknown document type '{$old->document_type}' on QR #{$old->id}.");
        }

        return DB::connection('sqlsrv')->transaction(function () use ($old, $type, $newSourceId) {
            // Retire the old row FIRST. The uniqueness rule is "one active QR per
            // source", so leaving it active while the replacement is inserted
            // would put two active rows on the same source and collide.
            $old->update(['status' => 'superseded']);

            $new = $this->issue($type, $newSourceId ?? $old->source_id, [
                'source_table'       => $old->source_table,
                'file_indexing_id'   => $old->file_indexing_id,
                'file_number'        => $old->file_number,
                'tracking_id'        => $old->tracking_id,
                'tracking_id_source' => $old->tracking_id_source,
            ]);

            $old->update(['superseded_by_id' => $new->id]);

            return $new;
        });
    }
}
