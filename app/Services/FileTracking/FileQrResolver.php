<?php

namespace App\Services\FileTracking;

use App\Services\DocumentQr\QrPayloadReader;
use App\Services\DocumentQr\QrTokenService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Turns ANY QR printed by KLAES back into the file it belongs to.
 *
 * Tracking sheets, RofOs, recommendations, commissioning sheets, consent letters,
 * labels, bills and the rest each encode something different: a bare tracking ID
 * (four grammars), a JSON blob, a KLAES-Q1 token, a /verify-file/ URL, pipe text,
 * a prefixed application id, a URL-encoded SLTR number, or the file number itself.
 *
 * normalize() is pure string work. resolve() then tries the extracted values
 * against every table that issues them. READ-ONLY: nothing here writes, so a
 * scan preview can never create a tracker by accident.
 *
 * Some payloads can never be resolved and are reported as such: the legal-search
 * / PHS HMAC (one-way, never stored), the fixed survey-report string, the OSS OP
 * acknowledgement text, and the literal 'N/A'.
 */
class FileQrResolver
{
    /** Payloads that are printed verbatim on every copy and identify nothing. */
    private const FIXED_STRINGS = ['KANOLANDSURVEY12', 'KANOLAND16'];

    public function __construct(private QrPayloadReader $reader, private QrTokenService $tokens)
    {
    }

    /**
     * Pure parse — no DB. Returns the identifiers the payload carries.
     *
     * @return array{raw:string, kind:string, file_number:?string, tracking_id:?string,
     *               prefixed:?array{type:string,id:string}, candidates:string[],
     *               unresolvable:?string}
     */
    public function normalize(string $payload): array
    {
        $raw = trim($payload);
        $out = [
            'raw'          => $raw,
            'kind'         => 'REFERENCE',
            'file_number'  => null,
            'tracking_id'  => null,
            'prefixed'     => null,
            'candidates'   => [],
            'unresolvable' => null,
        ];

        if ($raw === '') {
            $out['unresolvable'] = 'Empty scan.';
            return $out;
        }

        // The SLTR RofO urlencodes its number before drawing the QR, so a scanned
        // value can arrive as SLTR%2F123%2F2024. Only decode when it plainly is
        // percent-encoded — a real file number never contains %XX.
        if (preg_match('/%[0-9A-F]{2}/i', $raw)) {
            $raw = trim(rawurldecode($raw));
            $out['raw'] = $raw;
        }

        // OSS OP acknowledgement: "OP Ack | name | Plot: x | Plan: y | date". No file.
        if (preg_match('/^OP\s+Ack\s*\|/i', $raw)) {
            $out['kind'] = 'OP_ACK';
            $out['unresolvable'] = 'OSS OP acknowledgement slips carry no file reference. Log this file manually.';
            return $out;
        }

        // OSS OP verification / change of ownership: "OP-VERIFICATION: x".
        if (preg_match('/^OP-(VERIFICATION|CHANGE-OF-OWNERSHIP)\s*:\s*(.+)$/i', $raw, $m)) {
            $out['kind'] = 'OP_' . strtoupper($m[1]);
            $out['candidates'] = [trim($m[2])];
            return $out;
        }

        // Change of Purpose / Change of Name acknowledgements: COP-12, CON-5.
        if (preg_match('/^(COP|CON)-(\d+)$/i', $raw, $m)) {
            $out['kind'] = strtoupper($m[1]);
            $out['prefixed'] = ['type' => strtoupper($m[1]), 'id' => $m[2]];
            return $out;
        }

        // Legal / property search report: "File Number: MLSF: x | KANGIS: y | New KANGIS: z".
        if (preg_match('/MLSF\s*:/i', $raw) && str_contains($raw, '|')) {
            $out['kind'] = 'SEARCH_REPORT';
            foreach (explode('|', preg_replace('/^\s*File Number\s*:/i', '', $raw)) as $part) {
                $value = trim(preg_replace('/^[^:]*:/', '', $part));
                if ($this->meaningful($value)) {
                    $out['candidates'][] = $value;
                }
            }
            $out['file_number'] = $out['candidates'][0] ?? null;
            if (!$out['candidates']) {
                $out['unresolvable'] = 'The search report QR holds no file number.';
            }
            return $out;
        }

        // Old /filetracker prints: "TRK:TRK-000123|FILE:fileno|KANGIS:..|LOC:..|RFID:.."
        // and the detail panel's "TRK-000123|fileno|location|rfid".
        if (str_contains($raw, '|') && !str_starts_with($raw, '{')) {
            $parts = array_map('trim', explode('|', $raw));
            $labelled = [];
            foreach ($parts as $part) {
                if (preg_match('/^([A-Z]+)\s*:\s*(.*)$/i', $part, $m)) {
                    $labelled[strtoupper($m[1])] = trim($m[2]);
                }
            }
            $out['kind'] = 'PIPE';
            if ($labelled) {
                $out['file_number'] = $this->meaningful($labelled['FILE'] ?? null) ? $labelled['FILE'] : null;
                foreach (['KANGIS'] as $key) {
                    if ($this->meaningful($labelled[$key] ?? null)) {
                        $out['candidates'][] = $labelled[$key];
                    }
                }
            } elseif (isset($parts[1]) && $this->meaningful($parts[1])) {
                $out['file_number'] = $parts[1];
            }
            if ($out['file_number']) {
                array_unshift($out['candidates'], $out['file_number']);
            }
            if (!$out['candidates']) {
                $out['unresolvable'] = 'This tracker QR holds no file number.';
            }
            return $out;
        }

        // The consent letter's optional verify_url prefix: {verify_url}/{id}.
        $verifyUrl = rtrim((string) config('consent_letter.verify_url', ''), '/');
        if ($verifyUrl !== '' && str_starts_with($raw, $verifyUrl . '/')) {
            $raw = rawurldecode(substr($raw, strlen($verifyUrl) + 1));
        }

        // Fixed strings printed identically on every copy.
        if (in_array(strtoupper($raw), self::FIXED_STRINGS, true)) {
            $out['kind'] = 'FIXED';
            $out['unresolvable'] = 'This document prints the same QR on every copy, so it cannot identify a file. Log it manually.';
            return $out;
        }

        // Legal search / PHS slip: 11 hex chars, a truncated HMAC that is never stored.
        if (preg_match('/^[0-9a-f]{11}$/', $raw) && preg_match('/[a-f]/', $raw)) {
            $out['kind'] = 'HMAC';
            $out['unresolvable'] = 'Legal search / PHS slip codes are one-way and cannot be traced to a file. Log it manually.';
            return $out;
        }

        // Everything the document-verification reader already understands:
        // Q1 tokens, N/A, JSON blobs, /verify-file/ URLs and bare tracking IDs.
        $read = $this->reader->read($raw);
        $out['kind'] = $read['kind'];

        switch ($read['kind']) {
            case QrPayloadReader::KIND_Q0_EMPTY:
                $out['unresolvable'] = 'The QR on this document says "N/A" — it was printed without a reference. Log it manually.';
                return $out;

            case QrPayloadReader::KIND_Q1:
                return $out; // decrypted in resolve()

            case QrPayloadReader::KIND_Q0_JSON:
                $json = json_decode($raw, true) ?: [];
                $out['tracking_id'] = $read['tracking_id'];
                $out['file_number'] = $read['file_number']
                    ?? $this->firstMeaningful($json, ['fileNumber', 'file_no', 'fileno', 'reference']);
                // The /filetracker JSON uses "id" for its TRK number; recertification uses it for the app id.
                if (!$out['tracking_id'] && !empty($json['id']) && is_string($json['id'])) {
                    $out['tracking_id'] = trim($json['id']);
                }
                break;

            case QrPayloadReader::KIND_Q0_URL:
            case QrPayloadReader::KIND_Q0_TRACKING:
                $out['tracking_id'] = $read['tracking_id'];
                $out['file_number'] = $read['file_number'];
                break;

            default:
                // A bare value that is not a known tracking-ID grammar: most often
                // the file number itself (CoR, conversion recommendation, labels).
                break;
        }

        foreach ([$out['tracking_id'], $out['file_number']] as $value) {
            if ($this->meaningful($value)) {
                $out['candidates'][] = $value;
            }
        }
        if (!$out['candidates'] && $this->meaningful($raw)) {
            $out['candidates'][] = $raw;
        }
        $out['candidates'] = array_values(array_unique($out['candidates']));

        return $out;
    }

    /**
     * Normalize, then look the identifiers up.
     *
     * @return array{resolved:bool, message:?string, kind:string, raw:string,
     *               matches: array<int, array{file_number:?string, tracking_id:?string,
     *               file_title:?string, source:string, indexed:bool}>}
     */
    public function resolve(string $payload): array
    {
        $n = $this->normalize($payload);
        $result = [
            'resolved' => false,
            'message'  => $n['unresolvable'],
            'kind'     => $n['kind'],
            'raw'      => $n['raw'],
            'matches'  => [],
        ];

        if ($n['unresolvable']) {
            return $result;
        }

        $db = DB::connection('sqlsrv');
        $matches = [];

        try {
            if ($n['kind'] === QrPayloadReader::KIND_Q1) {
                $matches = $this->fromQ1Token($n['raw']);
                if (!$matches) {
                    $result['message'] = 'This KLAES QR token could not be verified.';
                    return $result;
                }
            } elseif ($n['prefixed']) {
                $matches = $this->fromPrefixed($n['prefixed']['type'], $n['prefixed']['id']);
            } else {
                foreach ($n['candidates'] as $candidate) {
                    $matches = array_merge($matches, $this->lookup($candidate));
                    if ($matches) {
                        break;
                    }
                }
                // A JSON/pipe payload that claims a file number we could not find
                // still names the file — keep it so it can be logged as unindexed.
                if (!$matches && $n['file_number']) {
                    $matches[] = $this->match($n['file_number'], $n['tracking_id'], null, 'qr_payload', false);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('FileQrResolver lookup failed', ['raw' => $n['raw'], 'error' => $e->getMessage()]);
            $result['message'] = 'Lookup failed: ' . $e->getMessage();
            return $result;
        }

        $matches = $this->dedupe($matches);
        $matches = array_map(fn ($m) => $this->enrich($db, $m), $matches);

        $result['matches'] = $matches;
        $result['resolved'] = count($matches) > 0;
        if (!$result['resolved']) {
            $result['message'] = 'No KLAES file matches this QR. Log the file manually.';
        } elseif (count($matches) > 1) {
            $result['message'] = 'This QR matches more than one file — pick the right one.';
        }

        return $result;
    }

    /**
     * Try one bare identifier against every table that issues tracking IDs,
     * in order of how authoritative the table is, then as a file number.
     */
    private function lookup(string $value): array
    {
        $db = DB::connection('sqlsrv');
        $v = trim($value);
        $isInt = (bool) preg_match('/^\d{1,12}$/', $v);

        // 1. The document register.
        $row = $db->table('document_qr_codes')
            ->where(fn ($q) => $q->where('tracking_id', $v)->orWhere('file_number', $v))
            ->orderByDesc('id')->first(['file_number', 'tracking_id']);
        if ($row && $row->file_number) {
            return [$this->match($row->file_number, $row->tracking_id, null, 'document_qr_codes')];
        }

        if (!$isInt) {
            // 2. Live file trackers.
            $row = $db->table('file_tracker')->where('tracking_id', $v)
                ->orderByDesc('id')->first(['file_number', 'tracking_id', 'file_title']);
            if ($row) {
                return [$this->match($row->file_number, $row->tracking_id, $row->file_title, 'file_tracker')];
            }

            // 3. Indexing.
            $row = $db->table('file_indexings')->where('tracking_id', $v)
                ->orderByDesc('id')->first(['file_number', 'tracking_id', 'file_title']);
            if ($row) {
                return [$this->match($row->file_number, $row->tracking_id, $row->file_title, 'file_indexings', true)];
            }

            // 4. Indexed tracking sheets.
            $row = $db->table('indexed_file_trackers as t')
                ->join('file_indexings as f', 'f.id', '=', 't.file_indexing_id')
                ->where('t.tracking_id', $v)
                ->first(['f.file_number', 't.tracking_id', 'f.file_title']);
            if ($row) {
                return [$this->match($row->file_number, $row->tracking_id, $row->file_title, 'indexed_file_trackers', true)];
            }

            // 5. Commissioning registers.
            foreach (['mls_file_no', 'dciv_file_no'] as $table) {
                $row = $db->table($table)->where('tracking_id', $v)
                    ->orderByDesc('id')->first(['full_file_number', 'tracking_id', 'file_name']);
                if ($row) {
                    return [$this->match($row->full_file_number, $row->tracking_id, $row->file_name, $table)];
                }
            }

            $row = $db->table('conversion_applications')->where('tracking_id', $v)
                ->orderByDesc('id')->first(['full_file_number', 'tracking_id']);
            if ($row && $row->full_file_number) {
                return [$this->match($row->full_file_number, $row->tracking_id, null, 'conversion_applications')];
            }

            // 6. Cadastral grouping (7.4M rows; cadastral_tracking_id is unindexed,
            //    so only query it for its own shape: TRK-{8}-{5}-{4 digits}).
            //    grouping.encryption_key is printed on physical-planning sheets but
            //    no row holds one, so it is not searched.
            if (preg_match('/^TRK-[A-Z0-9]{8}-[A-Z0-9]{5}-\d{4}$/i', $v)) {
                $row = $db->table('grouping')->where('cadastral_tracking_id', $v)
                    ->first(['awaiting_fileno', 'mls_fileno', 'tracking_id']);
                if ($row && ($row->mls_fileno || $row->awaiting_fileno)) {
                    return [$this->match($row->mls_fileno ?: $row->awaiting_fileno, $row->tracking_id, null, 'grouping')];
                }
            }
        }

        // 7. fileNumber: tracking_id plus every file-number column. One file can
        //    have several rows, so match across columns, never positionally.
        //    (The sqlsrv collation is case-insensitive, so plain = matches any case
        //    and still uses the indexes; UPPER() on a column would not.)
        $rows = $db->table('fileNumber')
            ->where(function ($q) use ($v) {
                $q->where('tracking_id', $v)
                  ->orWhere('mlsfNo', $v)
                  ->orWhere('kangisFileNo', $v)
                  ->orWhere('NewKANGISFileNo', $v)
                  ->orWhere('st_file_no', $v)
                  ->orWhere('dciv_fileno', $v);
            })
            ->limit(5)
            ->get(['mlsfNo', 'kangisFileNo', 'NewKANGISFileNo', 'st_file_no', 'tracking_id', 'FileName']);
        if ($rows->isNotEmpty()) {
            $up = strtoupper($v);
            return $rows->map(function ($r) use ($up) {
                $number = collect([$r->mlsfNo, $r->kangisFileNo, $r->NewKANGISFileNo, $r->st_file_no])
                    ->first(fn ($c) => $c && strtoupper(trim($c)) === $up)
                    ?: ($r->mlsfNo ?: ($r->kangisFileNo ?: ($r->NewKANGISFileNo ?: $r->st_file_no)));
                return $this->match($number, $r->tracking_id, $r->FileName, 'fileNumber');
            })->all();
        }

        // 8. Indexing by file number (any of its number columns).
        $row = $db->table('file_indexings')
            ->where(function ($q) use ($v) {
                $q->where('file_number', $v)
                  ->orWhere('st_fillno', $v)
                  ->orWhere('mls_file_no', $v)
                  ->orWhere('kangis_file_no', $v)
                  ->orWhere('new_kangis_file_no', $v);
            })
            ->orderByDesc('id')->first(['file_number', 'tracking_id', 'file_title']);
        if ($row) {
            return [$this->match($row->file_number, $row->tracking_id, $row->file_title, 'file_indexings', true)];
        }

        // 9. A file tracker logged by file number (unindexed files included).
        $row = $db->table('file_tracker')->where('file_number', $v)
            ->orderByDesc('id')->first(['file_number', 'tracking_id', 'file_title']);
        if ($row) {
            return [$this->match($row->file_number, $row->tracking_id, $row->file_title, 'file_tracker')];
        }

        if ($isInt) {
            // 10. A bare integer: legacy RofO tracking ids, ST acknowledgement ids.
            //     Both can match — return both and let the user pick.
            $found = [];
            $row = $db->table('land_recommendations')->where('tracking_id', $v)
                ->orderByDesc('id')->first(['file_number', 'tracking_id', 'file_title']);
            if ($row && $row->file_number) {
                $found[] = $this->match($row->file_number, $row->tracking_id, $row->file_title, 'land_recommendations');
            }
            $row = $db->table('mother_applications')->where('id', (int) $v)->first(['fileno', 'np_fileno']);
            if ($row && ($row->fileno || $row->np_fileno)) {
                $found[] = $this->match($row->fileno ?: $row->np_fileno, null, null, 'st_application');
            }
            return $found;
        }

        // 11. OP numbers (OP verification / change slips) and SLTR numbers on the RofO.
        $row = $db->table('land_recommendations')->where('tracking_id', $v)
            ->orderByDesc('id')->first(['file_number', 'tracking_id', 'file_title']);
        if ($row && $row->file_number) {
            return [$this->match($row->file_number, $row->tracking_id, $row->file_title, 'land_recommendations')];
        }

        return [];
    }

    private function fromQ1Token(string $token): array
    {
        try {
            $claims = $this->tokens->verify($token);
        } catch (\Throwable $e) {
            return [];
        }

        $id = $claims['document_qr_id'] ?? null;
        if (!$id) {
            return [];
        }

        $row = DB::connection('sqlsrv')->table('document_qr_codes')->where('id', $id)
            ->first(['file_number', 'tracking_id']);
        if (!$row) {
            return [];
        }

        return [$this->match($row->file_number, $row->tracking_id, null, 'document_qr_codes')];
    }

    private function fromPrefixed(string $type, string $id): array
    {
        if ($type === 'COP') {
            $row = DB::connection('sqlsrv')->table('change_of_purpose_applications')->where('id', (int) $id)
                ->first(['file_no']);
            return $row && $row->file_no ? [$this->match($row->file_no, null, null, 'change_of_purpose')] : [];
        }

        // CON-{id}: change_of_name_applications is not on sqlsrv; try the default connection.
        try {
            $row = DB::table('change_of_name_applications')->where('id', (int) $id)->first();
            $number = $row->file_no ?? $row->file_number ?? $row->fileno ?? null;
            return $number ? [$this->match($number, null, null, 'change_of_name')] : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Fill in the title and whether the file is indexed, for the preview card. */
    private function enrich($db, array $m): array
    {
        if (!$m['file_number']) {
            return $m;
        }

        if (!$m['indexed'] || !$m['file_title']) {
            $idx = $db->table('file_indexings')->where('file_number', $m['file_number'])
                ->orderByDesc('id')->first(['file_title', 'tracking_id']);
            if ($idx) {
                $m['indexed'] = true;
                $m['file_title'] = $m['file_title'] ?: $idx->file_title;
                $m['tracking_id'] = $m['tracking_id'] ?: $idx->tracking_id;
            }
        }

        return $m;
    }

    private function match(?string $fileNumber, ?string $trackingId, ?string $title, string $source, bool $indexed = false): array
    {
        return [
            'file_number' => $fileNumber !== null ? trim($fileNumber) : null,
            'tracking_id' => $trackingId !== null && trim($trackingId) !== '' ? trim($trackingId) : null,
            'file_title'  => $title !== null && trim($title) !== '' ? trim($title) : null,
            'source'      => $source,
            'indexed'     => $indexed,
        ];
    }

    private function dedupe(array $matches): array
    {
        $seen = [];
        $out = [];
        foreach ($matches as $m) {
            $key = strtoupper((string) $m['file_number']);
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $m;
        }
        return $out;
    }

    private function meaningful($value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        $value = trim($value);
        return $value !== '' && !preg_match('/^(N\/A|NA|NULL|-|undefined)$/i', $value);
    }

    private function firstMeaningful(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && $this->meaningful($data[$key])) {
                return trim($data[$key]);
            }
        }
        return null;
    }
}
