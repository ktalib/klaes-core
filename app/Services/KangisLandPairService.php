<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Pairs a land file number with its KANGIS number (and the reverse), so a printed
 * sheet can name both in one line: "RES-1991-772 (KNML 9213)".
 *
 * A KANGIS file is an alias of a land file, not a file of its own: it lives in a
 * separate file_indexings row (registry = 'KANGIS') whose related_fileno JSON
 * back-links to the land file it was recertified from. Whichever of the two numbers
 * is quoted on a document, the reader expects to see the pair.
 */
class KangisLandPairService
{
    /**
     * Resolved pairings for this request, keyed by the normalised number.
     *
     * Each lookup is a LIKE scan over file_indexings.related_fileno, and a listing
     * asks for the same numbers repeatedly across a page, so the answer is kept.
     * A number with no pair memoises as null and is not asked for twice.
     *
     * @var array<string,?string>
     */
    private array $pairCache = [];

    /**
     * The OTHER number of the pair, with no formatting: the KANGIS alias of a land
     * file, or the land file behind a KANGIS number. Null when the file has no pair.
     *
     * format() exists for print, where the two belong on one line. This is for
     * callers that need the numbers apart — a table cell that shows both, say.
     */
    public function aliasFor(?string $fileNo): ?string
    {
        $fileNo = trim((string) $fileNo);
        if ($fileNo === '') {
            return null;
        }

        $key = strtoupper(preg_replace('/\s+/', '', $fileNo));
        if (array_key_exists($key, $this->pairCache)) {
            return $this->pairCache[$key];
        }

        try {
            $conn = DB::connection('sqlsrv');
            $pair = $this->isKangisFormat($fileNo)
                ? $this->landFileForKangis($conn, $fileNo)
                : $this->kangisFileForLand($conn, $fileNo);
        } catch (\Throwable $e) {
            $pair = null;
        }

        // A pairing that comes back as the number itself is not a second name for it.
        if ($pair !== null && $this->isSameNumber($pair, $fileNo)) {
            $pair = null;
        }

        return $this->pairCache[$key] = $pair;
    }

    /**
     * Resolve a whole page's pairings in two queries instead of two per number.
     *
     * aliasFor() on its own costs a LIKE scan of file_indexings.related_fileno each
     * time — fine for the one number a printed sheet quotes, far too slow for a
     * listing, where it measured ~0.15s per number. The scan is the same width
     * whether it carries one LIKE or forty, so the page asks once and shares.
     *
     * Afterwards every number given here is in the cache (null when it has no pair),
     * so aliasFor() answers without touching the database.
     *
     * @param array<int,?string> $fileNos
     */
    public function warmPairs(array $fileNos): void
    {
        $norm = fn ($v) => strtoupper(preg_replace('/\s+/', '', trim((string) $v)));

        // Only what is still unknown, split by which direction it has to be looked up.
        $land = [];    // normalised key => number as given
        $kangis = [];
        foreach ($fileNos as $fileNo) {
            $fileNo = trim((string) $fileNo);
            if ($fileNo === '') {
                continue;
            }
            $key = $norm($fileNo);
            if (array_key_exists($key, $this->pairCache)) {
                continue;
            }
            if ($this->isKangisFormat($fileNo)) {
                $kangis[$key] = $fileNo;
            } else {
                $land[$key] = $fileNo;
            }
        }

        if (empty($land) && empty($kangis)) {
            return;
        }

        try {
            $conn = DB::connection('sqlsrv');

            if (!empty($kangis)) {
                $this->warmKangisToLand($conn, $kangis, $norm);
            }
            if (!empty($land)) {
                $this->warmLandToKangis($conn, $land, $norm);
            }
        } catch (\Throwable $e) {
            // Fail-open, exactly as the single lookups do: an unresolved pairing just
            // prints the number on its own.
        }

        // Anything the batch could not place is settled as "no pair", so it is not
        // then asked for one number at a time.
        foreach (array_merge($land, $kangis) as $key => $_) {
            if (!array_key_exists($key, $this->pairCache)) {
                $this->pairCache[$key] = null;
            }
        }
    }

    /** KANGIS numbers → their land files, in one indexed lookup. */
    private function warmKangisToLand($conn, array $kangis, callable $norm): void
    {
        $keys = [];
        foreach ($kangis as $key => $_) {
            $keys[] = $key;
            // "MLKN 01516" and "MLKN 1516" are the same number written twice.
            $keys[] = preg_replace('/^([A-Z]+)0*(\d+)$/', '$1$2', $key);
        }
        $keys = array_values(array_unique($keys));

        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $rows = $conn->table('file_indexings')
            ->whereRaw("UPPER(REPLACE(LTRIM(RTRIM(ISNULL(file_number,''))),' ','')) IN ($placeholders)", $keys)
            ->whereNull('deleted_at')
            ->get(['file_number', 'related_fileno']);

        foreach ($rows as $r) {
            $rowKey = $norm($r->file_number ?? '');
            foreach ($this->parseRelatedFileno($r->related_fileno ?? null) as $cand) {
                $cand = trim((string) $cand);
                if ($cand === '' || $cand === '-' || $this->isKangisFormat($cand)) {
                    continue;
                }
                // Match back to the number as it was asked for, zero-padding and all.
                foreach ($kangis as $key => $asked) {
                    if ($key === $rowKey || preg_replace('/^([A-Z]+)0*(\d+)$/', '$1$2', $key) === $rowKey) {
                        $this->pairCache[$key] = $this->pairCache[$key] ?? $cand;
                    }
                }
                break;
            }
        }
    }

    /**
     * Land files → their KANGIS numbers: one scan carrying every number's LIKE, then
     * each matched row is handed back to the land file it names. Legacy KANGIS wins
     * over new-KANGIS, as the single lookup has always preferred it.
     */
    private function warmLandToKangis($conn, array $land, callable $norm): void
    {
        // The quoted token is matched so "RES-1991-772" cannot hit "RES-1991-7720".
        $variantsByKey = [];
        foreach ($land as $key => $asked) {
            $variantsByKey[$key] = $this->fileNumberVariants($asked);
        }

        $rows = $conn->table('file_indexings')
            ->where(function ($q) use ($variantsByKey) {
                foreach ($variantsByKey as $variants) {
                    foreach ($variants as $v) {
                        $q->orWhere('related_fileno', 'like', '%"' . $v . '"%');
                    }
                }
            })
            ->whereNull('deleted_at')
            ->get(['file_number', 'related_fileno', 'kangis_fileno_resolved', 'kangis_file_no', 'new_kangis_file_no']);

        $legacy = [];
        $newk   = [];
        foreach ($rows as $r) {
            // Which of the asked-for land files does this KANGIS row back-link to?
            $links = array_map($norm, $this->parseRelatedFileno($r->related_fileno ?? null));
            if (empty($links)) {
                continue;
            }

            foreach ($variantsByKey as $key => $variants) {
                $hit = false;
                foreach ($variants as $v) {
                    if (in_array($norm($v), $links, true)) { $hit = true; break; }
                }
                if (!$hit) {
                    continue;
                }

                foreach ([$r->kangis_fileno_resolved ?? null, $r->kangis_file_no ?? null, $r->file_number ?? null, $r->new_kangis_file_no ?? null] as $value) {
                    $v = trim((string) $value);
                    if ($v === '' || $v === '-' || !$this->isKangisFormat($v)) {
                        continue;
                    }
                    if (preg_match('/^KN[\s-]?\d/i', $v)) {
                        $newk[$key] = $newk[$key] ?? $v;
                    } else {
                        $legacy[$key] = $legacy[$key] ?? $v;
                    }
                }
            }
        }

        foreach ($land as $key => $asked) {
            $pair = $legacy[$key] ?? $newk[$key] ?? null;
            if ($pair !== null && !$this->isSameNumber($pair, $asked)) {
                $this->pairCache[$key] = $pair;
            }
        }
    }

    /** Legacy KANGIS (MLKN/KNML/KNGP, optionally unit-suffixed) or new-KANGIS (KN…). */
    public function isKangisFormat(?string $fileNo): bool
    {
        $fileNo = trim((string) $fileNo);

        return $fileNo !== ''
            && (bool) preg_match('/^((MLKN|KNML|KNGP)\s?\d{1,6}([-_]\d{1,3})?|KN[\s-]?\d{2,6})$/i', $fileNo);
    }

    /**
     * "land file no (kangis file no)" when the pairing is known, in either direction;
     * otherwise the number exactly as given. Never throws — a display helper.
     *
     * `$excludeFileNo` is the number the document already names elsewhere — on a
     * commissioning sheet, the file the sheet is for. A recertification is its own
     * KANGIS alias's land file, so pairing "KN 6873" produced
     * "RES-RC-2026-8 (KN 6873)" on the Related File line of the sheet for
     * RES-RC-2026-8, which reads as a second, different file. Naming it excludes it:
     * the alias prints alone, and a related number that IS the file itself prints as
     * nothing at all so the row can be left off.
     */
    public function format(?string $fileNo, ?string $excludeFileNo = null): string
    {
        $fileNo = trim((string) $fileNo);
        if ($fileNo === '') {
            return '';
        }

        try {
            $conn = DB::connection('sqlsrv');

            if ($this->isKangisFormat($fileNo)) {
                $land = $this->landFileForKangis($conn, $fileNo);

                if ($land === null || $this->isSameNumber($land, $excludeFileNo)) {
                    return $fileNo;
                }

                return "{$land} ({$fileNo})";
            }

            if ($this->isSameNumber($fileNo, $excludeFileNo)) {
                return '';
            }

            $kangis = $this->kangisFileForLand($conn, $fileNo);

            return $kangis !== null ? "{$fileNo} ({$kangis})" : $fileNo;
        } catch (\Throwable $e) {
            return $fileNo;
        }
    }

    /**
     * Same, for a stored related_fileno — a JSON array on newer rows, a comma list on
     * older ones, a bare number otherwise. Each number is paired, then joined for print.
     *
     * Numbers that collapse to nothing (the document's own file) are dropped, so a list
     * holding only that comes back empty and the caller can leave the row out.
     */
    public function formatList($raw, ?string $excludeFileNo = null): string
    {
        $numbers = $this->parseRelatedFileno($raw);
        if (empty($numbers)) {
            return '';
        }

        $formatted = array_filter(
            array_map(fn ($n) => $this->format($n, $excludeFileNo), $numbers),
            fn ($v) => trim($v) !== ''
        );

        return implode(', ', $formatted);
    }

    /**
     * Two written forms of the same file number. Registry numbers are quoted with and
     * without spaces ("KN 6873" / "KN6873"), so the comparison ignores whitespace.
     */
    private function isSameNumber(?string $a, ?string $b): bool
    {
        $norm = fn ($v) => strtoupper(preg_replace('/\s+/', '', trim((string) $v)));

        $a = $norm($a);
        $b = $norm($b);

        return $a !== '' && $a === $b;
    }

    /**
     * The land file a KANGIS number is an alias of: the KANGIS row's own related_fileno
     * back-link first, then a KANGIS Recertification link at either endpoint.
     */
    private function landFileForKangis($conn, string $kangisNo): ?string
    {
        $key = strtoupper(preg_replace('/\s+/', '', $kangisNo));
        $keyNoZero = preg_replace('/^([A-Z]+)0*(\d+)$/', '$1$2', $key);

        $pickLand = function ($candidates) use ($kangisNo): ?string {
            foreach ($candidates as $cand) {
                $cand = trim((string) $cand);
                if ($cand !== '' && $cand !== '-' && !$this->isKangisFormat($cand)
                    && strcasecmp($cand, $kangisNo) !== 0) {
                    return $cand;
                }
            }
            return null;
        };

        try {
            $own = $conn->table('file_indexings')
                ->whereRaw("UPPER(REPLACE(LTRIM(RTRIM(ISNULL(file_number,''))),' ','')) IN (?, ?)", [$key, $keyNoZero])
                ->whereNull('deleted_at')
                ->value('related_fileno');
            $land = $pickLand($this->parseRelatedFileno($own));
            if ($land !== null) {
                return $land;
            }
        } catch (\Throwable $e) { /* fail-open */ }

        try {
            $links = $conn->table('related_file_number')
                ->where('transaction_type', 'like', '%Recertification%')
                ->where(function ($q) use ($key, $keyNoZero) {
                    $q->whereRaw("UPPER(REPLACE(LTRIM(RTRIM(ISNULL(file_number,''))),' ','')) IN (?, ?)", [$key, $keyNoZero])
                      ->orWhereRaw("UPPER(REPLACE(LTRIM(RTRIM(ISNULL(related_fileno,''))),' ','')) IN (?, ?)", [$key, $keyNoZero]);
                })
                ->get(['file_number', 'related_fileno']);
            foreach ($links as $l) {
                $land = $pickLand([$l->file_number ?? null, $l->related_fileno ?? null]);
                if ($land !== null) {
                    return $land;
                }
            }
        } catch (\Throwable $e) { /* fail-open */ }

        return null;
    }

    /**
     * The KANGIS number of a land file: the reverse lookup — which KANGIS-registry
     * indexing row lists this land file in its related_fileno? Legacy KANGIS is
     * preferred over new-KANGIS, as the land file's first recertification number.
     */
    private function kangisFileForLand($conn, string $landFileNo): ?string
    {
        $variants = $this->fileNumberVariants($landFileNo);
        $legacy = null;
        $newk = null;
        $take = function ($value) use (&$legacy, &$newk): void {
            $v = trim((string) $value);
            if ($v === '' || $v === '-' || !$this->isKangisFormat($v)) {
                return;
            }
            if (preg_match('/^KN[\s-]?\d/i', $v)) {
                $newk = $newk ?? $v;
            } else {
                $legacy = $legacy ?? $v;
            }
        };

        // The quoted token is matched so "RES-1991-772" cannot partial-match "RES-1991-7720".
        try {
            $rows = $conn->table('file_indexings')
                ->where(function ($q) use ($variants) {
                    foreach ($variants as $v) {
                        $q->orWhere('related_fileno', 'like', '%"' . $v . '"%');
                    }
                })
                ->whereNull('deleted_at')
                ->get(['file_number', 'kangis_fileno_resolved', 'kangis_file_no', 'new_kangis_file_no']);
            foreach ($rows as $r) {
                $take($r->kangis_fileno_resolved ?? null);
                $take($r->kangis_file_no ?? null);
                $take($r->file_number ?? null);
                $take($r->new_kangis_file_no ?? null);
            }
        } catch (\Throwable $e) { /* fail-open */ }

        if ($legacy === null && $newk === null) {
            try {
                $links = $conn->table('related_file_number')
                    ->where('transaction_type', 'like', '%Recertification%')
                    ->where(function ($q) use ($variants) {
                        foreach ($variants as $v) {
                            $q->orWhere('file_number', $v)->orWhere('related_fileno', $v);
                        }
                    })
                    ->get(['file_number', 'related_fileno']);
                foreach ($links as $l) {
                    $take($l->file_number ?? null);
                    $take($l->related_fileno ?? null);
                }
            } catch (\Throwable $e) { /* fail-open */ }
        }

        return $legacy ?? $newk;
    }

    /** related_fileno is a JSON array on newer rows and a comma list on older ones. */
    private function parseRelatedFileno($raw): array
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return [];
        }

        if ($raw[0] === '[') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return array_values(array_filter(array_map(fn ($v) => trim((string) $v), $decoded), fn ($v) => $v !== ''));
            }
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw)), fn ($v) => $v !== ''));
    }

    /** A temporary file is stored both as "X" and "X(T)"; match either. */
    private function fileNumberVariants(string $fileNo): array
    {
        $variants = [$fileNo => $fileNo];
        $base = trim((string) preg_replace('/\s*\(\s*T\s*\)\s*$/i', '', $fileNo));
        if ($base !== '') {
            $variants[$base] = $base;
            $variants[$base . '(T)'] = $base . '(T)';
        }

        return array_values($variants);
    }
}
