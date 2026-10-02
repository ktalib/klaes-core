<?php

namespace App\Services\DocumentQr;

/**
 * Classifies a scanned payload before anything is looked up.
 *
 * Printed paper cannot be recalled, so legacy (Q0) support is permanent. The
 * audit found FOUR live legacy shapes, not one:
 *
 *   1. A raw JSON blob            (tracking-sheet.blade.php, print-tracking-sheet.blade.php)
 *   2. A /verify-file/{fn}/{tid}  (batch-tracking-sheet.blade.php — route never existed)
 *   3. A bare tracking ID         (kangis-tracking-sheet.blade.php, rofo_print.blade.php)
 *   4. The literal string 'N/A'   (kangis-tracking-sheet.blade.php, when tracking_id was null)
 *
 * Detection CANNOT be done by prefix. There are two tracking-ID generators and
 * only one prefixes with TRK-:
 *
 *   FileTracker::generateTrackingId()        TRK-{ymdHis}-{RAND4}[-{REGISTRY}]
 *   FileIndexController::generateTrackingId() ABCD-EFGH2345   (no prefix at all)
 *
 * So the rule is "anything that is not a KLAES-Q1 token, tried against every
 * known legacy shape" — never a TRK- match.
 */
class QrPayloadReader
{
    public const KIND_Q1           = 'Q1';
    public const KIND_Q0_JSON      = 'Q0_JSON';
    public const KIND_Q0_URL       = 'Q0_URL';
    public const KIND_Q0_TRACKING  = 'Q0_TRACKING';
    public const KIND_Q0_EMPTY     = 'Q0_EMPTY';
    public const KIND_REFERENCE    = 'REFERENCE';

    public function __construct(private QrTokenService $tokens)
    {
    }

    /**
     * @return array{kind:string, version:string, tracking_id:?string,
     *               file_number:?string, reference:?string, raw:string}
     */
    public function read(string $payload): array
    {
        $raw   = trim($payload);
        $blank = [
            'kind'        => self::KIND_REFERENCE,
            'version'     => 'REF',
            'tracking_id' => null,
            'file_number' => null,
            'reference'   => $raw === '' ? null : $raw,
            'raw'         => $raw,
        ];

        if ($raw === '') {
            return $blank;
        }

        if ($this->tokens->looksLikeQ1($raw)) {
            return array_merge($blank, ['kind' => self::KIND_Q1, 'version' => 'Q1']);
        }

        // Shape 4 — a scannable QR whose content is literally 'N/A'. This is a
        // KLAES defect on genuine paper, not a suspicious document, so it must
        // resolve to "unverifiable" rather than "not in register".
        if (preg_match('/^(N\/A|NA|NULL|-)$/i', $raw)) {
            return array_merge($blank, [
                'kind'      => self::KIND_Q0_EMPTY,
                'version'   => 'Q0',
                'reference' => null,
            ]);
        }

        // Shape 1 — JSON blob. Only the tracking ID and file number are taken;
        // the rest is unverified claimed data printed on the page, not evidence.
        if (str_starts_with($raw, '{')) {
            $json = json_decode($raw, true);

            if (is_array($json)) {
                return array_merge($blank, [
                    'kind'        => self::KIND_Q0_JSON,
                    'version'     => 'Q0',
                    'tracking_id' => $this->clean($json['tracking_id'] ?? null),
                    'file_number' => $this->clean($json['file_number'] ?? null),
                    'reference'   => null,
                ]);
            }
        }

        // Shape 2 — the /verify-file/{file_number}/{tracking_id} URL. The route
        // never existed, but the payload is well-formed and still resolvable.
        if (preg_match('~/verify-file/(.+?)/([^/?#]+)~i', $raw, $m)) {
            return array_merge($blank, [
                'kind'        => self::KIND_Q0_URL,
                'version'     => 'Q0',
                'file_number' => $this->clean(urldecode($m[1])),
                'tracking_id' => $this->clean(urldecode($m[2])),
                'reference'   => null,
            ]);
        }

        // Shape 3 — a bare tracking ID in either grammar.
        if ($this->looksLikeTrackingId($raw)) {
            return array_merge($blank, [
                'kind'        => self::KIND_Q0_TRACKING,
                'version'     => 'Q0',
                'tracking_id' => $raw,
                'reference'   => null,
            ]);
        }

        return $blank;
    }

    /**
     * All FOUR live tracking-ID grammars. Verified against production data,
     * not just against the generators in code — two of these are not produced
     * by any generator still in the codebase.
     *
     *   1. TRK-{ymdHis}-{RAND4}[-{REGISTRY}]   file_tracker
     *   2. TRK-{8 alnum}-{5 alnum}             mls_file_no, file_indexings,
     *                                          land_recommendations
     *   3. {4}-{8} confusable-free, no prefix  FileIndexController generator
     *   4. a bare integer                      land_recommendations (348 of 375
     *                                          rows) — see numericIsEnumerable()
     *
     * NOTE: the trailing segment of a grammar-1 id is the ORIGIN REGISTRY CODE
     * (physical_registries.registry_code, R001…R018) — it is not padding and
     * must never be stripped or normalised away. It is optional: several call
     * sites mint an id with no registry, so a 3-segment id is valid too.
     */
    public function looksLikeTrackingId(string $value): bool
    {
        // 1. TRK-{ymdHis}-{RAND4} with an optional -{REGISTRY} tail.
        if (preg_match('/^TRK-\d{12}-[A-Z0-9]{4}(-[A-Za-z0-9]{1,20})?$/i', $value)) {
            return true;
        }

        // 2. TRK-{8}-{5}. This is what mls_file_no and file_indexings actually
        //    hold, and it is what a printed RofO carries.
        if (preg_match('/^TRK-[A-Z0-9]{8}-[A-Z0-9]{5}$/i', $value)) {
            return true;
        }

        // 3. FileIndexController grammar: 4 + 8 chars from a confusable-free
        //    alphabet (no 0, O, I, 1), no prefix.
        if (preg_match('/^[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{8}$/i', $value)) {
            return true;
        }

        // 4. A bare integer.
        if ($this->numericIsEnumerable($value)) {
            return true;
        }

        return false;
    }

    /**
     * A tracking ID that is nothing but a sequential number — e.g. 179239.
     *
     * This is the weakest thing printed on any Ministry document. Scanning one
     * RofO yields a number whose neighbours are other people's live documents,
     * so the register can be walked by counting. It is also trivially forged:
     * there is nothing to check.
     *
     * Treated as a tracking ID so genuine paper still resolves, but callers
     * must cap the verdict — a numeric Q0 payload can never mean "authentic",
     * only "this number exists in the register".
     */
    public function numericIsEnumerable(string $value): bool
    {
        return (bool) preg_match('/^\d{1,12}$/', trim($value));
    }

    /**
     * The origin registry stamped into a tracking ID, if it carries one.
     *
     * This is a HISTORICAL stamp — the registry the file originated from at
     * creation. The editable current value lives in file_tracker.registry_code
     * and is what resolvers should read. A mismatch between the two is a
     * legitimate record of a corrected origin, not corruption: do not "repair"
     * either from the other.
     */
    public function originRegistryCode(string $trackingId): ?string
    {
        if (preg_match('/^TRK-\d{12}-[A-Z0-9]{4}-([A-Za-z0-9]{1,20})$/i', trim($trackingId), $m)) {
            return strtoupper($m[1]);
        }

        return null;
    }

    private function clean($value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }
}
