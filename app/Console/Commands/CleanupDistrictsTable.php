<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupDistrictsTable extends Command
{
    protected $signature = 'district:clean
        {--dry-run : Show what would happen without writing anything}
        {--lev-threshold=2 : Max Levenshtein distance to consider near-duplicate (0 = skip near-dedup)}';

    protected $description = 'Delete invalid + duplicate entries from dbo.districts.';

    public function handle(): int
    {
        $conn      = DB::connection('sqlsrv');
        $dryRun    = (bool) $this->option('dry-run');
        $levLimit  = (int) $this->option('lev-threshold');

        $lgaNames = $conn->table('lgas')
            ->whereNotNull('name')
            ->pluck('name')
            ->map(fn ($n) => strtoupper(trim($n)))
            ->flip();

        // Occurrence lookup from district_staging (used to pick canonical in a group)
        $stagingOcc = [];
        if (DB::connection('sqlsrv')->getSchemaBuilder()->hasTable('district_staging')) {
            $stagingOcc = $conn->table('district_staging')
                ->get(['name', 'occurrence'])
                ->mapWithKeys(fn ($r) => [strtoupper(trim($r->name)) => (int) $r->occurrence])
                ->all();
        }

        $all = $conn->table('districts')
            ->whereNotNull('name')
            ->orderBy('id')
            ->get(['id', 'name']);

        // ------------------------------------------------------------------
        // Step 1 — delete invalid rows
        // ------------------------------------------------------------------
        $this->info('=== Step 1: Delete invalid entries ===');

        $invalid = $all->filter(fn ($r) => $this->isInvalid(strtoupper(trim($r->name)), $lgaNames));
        $valid   = $all->filter(fn ($r) => !$this->isInvalid(strtoupper(trim($r->name)), $lgaNames));

        $this->line("  Total rows:   {$all->count()}");
        $this->line("  Invalid:      {$invalid->count()}");
        $this->line("  Valid so far: {$valid->count()}");

        if ($invalid->isNotEmpty()) {
            $this->table(['id', 'name'], $invalid->map(fn ($r) => [$r->id, $r->name])->values()->toArray());
        }

        if (!$dryRun && $invalid->isNotEmpty()) {
            foreach (array_chunk($invalid->pluck('id')->all(), 500) as $chunk) {
                $conn->table('districts')->whereIn('id', $chunk)->delete();
            }
            $this->info("  Deleted {$invalid->count()} invalid rows.");
        }

        // ------------------------------------------------------------------
        // Step 2 — exact dedup (after normalising special chars)
        // ------------------------------------------------------------------
        $this->newLine();
        $this->info('=== Step 2: Exact dedup (apostrophes / slashes / hyphens) ===');

        $working = $dryRun ? $valid : $conn->table('districts')
            ->whereNotNull('name')->orderBy('id')->get(['id', 'name']);

        $groups      = [];
        foreach ($working as $r) {
            $key           = $this->dedupKey($r->name);
            $groups[$key][] = $r;
        }

        $exactDeleteIds = [];
        $exactReport    = [];

        foreach ($groups as $key => $rows) {
            if (count($rows) < 2) {
                continue;
            }
            // Pick canonical: highest staging occurrence, fewest special chars, shortest, then alphabetical
            usort($rows, function ($a, $b) use ($stagingOcc) {
                $aOcc = $stagingOcc[strtoupper(trim($a->name))] ?? 0;
                $bOcc = $stagingOcc[strtoupper(trim($b->name))] ?? 0;
                if ($aOcc !== $bOcc) {
                    return $bOcc - $aOcc;
                }
                $aSpec = preg_match_all('/[^A-Z0-9\s]/i', $a->name);
                $bSpec = preg_match_all('/[^A-Z0-9\s]/i', $b->name);
                if ($aSpec !== $bSpec) {
                    return $aSpec - $bSpec;
                }
                if (strlen($a->name) !== strlen($b->name)) {
                    return strlen($a->name) - strlen($b->name);
                }
                return strcmp($a->name, $b->name);
            });

            $canonical = array_shift($rows);
            foreach ($rows as $dup) {
                $exactDeleteIds[] = $dup->id;
                $exactReport[]    = ['keep' => $canonical->name, 'delete' => $dup->name, 'id' => $dup->id];
            }
        }

        $this->line("  Exact duplicates to remove: " . count($exactDeleteIds));
        if (!empty($exactReport)) {
            $this->table(['KEEP', 'DELETE (id)'], array_map(
                fn ($r) => [$r['keep'], $r['delete'] . '  (id=' . $r['id'] . ')'],
                $exactReport
            ));
        }

        if (!$dryRun && !empty($exactDeleteIds)) {
            foreach (array_chunk($exactDeleteIds, 500) as $chunk) {
                $conn->table('districts')->whereIn('id', $chunk)->delete();
            }
            $this->info("  Deleted " . count($exactDeleteIds) . " exact duplicates.");
        }

        // ------------------------------------------------------------------
        // Step 2b — space-stripped dedup
        //   Catches: NOMANSLAND vs NOMANS LAND, NOMANS LAND vs NOMANSLANDS, etc.
        //   Key: remove ALL spaces from the dedup key, then compare.
        // ------------------------------------------------------------------
        $this->newLine();
        $this->info('=== Step 2b: Space-stripped dedup ===');

        $afterStep2 = $dryRun
            ? $valid->filter(fn ($r) => !in_array($r->id, $exactDeleteIds))->values()
            : $conn->table('districts')->whereNotNull('name')->orderBy('id')->get(['id', 'name']);

        $spaceGroups    = [];
        foreach ($afterStep2 as $r) {
            $spaceKey = str_replace(' ', '', $this->dedupKey($r->name));
            $spaceGroups[$spaceKey][] = $r;
        }

        $spaceDeleteIds = [];
        $spaceReport    = [];
        foreach ($spaceGroups as $key => $rows) {
            if (count($rows) < 2) {
                continue;
            }
            usort($rows, function ($a, $b) use ($stagingOcc) {
                $aOcc = $stagingOcc[strtoupper(trim($a->name))] ?? 0;
                $bOcc = $stagingOcc[strtoupper(trim($b->name))] ?? 0;
                if ($aOcc !== $bOcc) {
                    return $bOcc - $aOcc;
                }
                $aSpec = preg_match_all('/[^A-Z0-9\s]/i', $a->name);
                $bSpec = preg_match_all('/[^A-Z0-9\s]/i', $b->name);
                if ($aSpec !== $bSpec) {
                    return $aSpec - $bSpec;
                }
                return strlen($a->name) - strlen($b->name);
            });
            $canonical = array_shift($rows);
            foreach ($rows as $dup) {
                $spaceDeleteIds[] = $dup->id;
                $spaceReport[]    = ['keep' => $canonical->name, 'delete' => $dup->name, 'id' => $dup->id];
            }
        }

        $this->line("  Space-variant duplicates to remove: " . count($spaceDeleteIds));
        if (!empty($spaceReport)) {
            $this->table(['KEEP', 'DELETE (id)'], array_map(
                fn ($r) => [$r['keep'], $r['delete'] . '  (id=' . $r['id'] . ')'],
                $spaceReport
            ));
        }
        if (!$dryRun && !empty($spaceDeleteIds)) {
            foreach (array_chunk($spaceDeleteIds, 500) as $chunk) {
                $conn->table('districts')->whereIn('id', $chunk)->delete();
            }
            $this->info("  Deleted " . count($spaceDeleteIds) . " space-variant duplicates.");
        }

        // ------------------------------------------------------------------
        // Step 3 — near-dedup via Levenshtein
        //   Grouped by first 5 chars of the dedup key so that typos in the
        //   first word (NORTHAN/NORTHEN/NORTHERN) all land in the same bucket.
        // ------------------------------------------------------------------
        if ($levLimit > 0) {
            $this->newLine();
            $this->info("=== Step 3: Near-dedup (Levenshtein ≤ {$levLimit}) ===");

            $alreadyGone  = array_merge($exactDeleteIds, $spaceDeleteIds);
            $remaining    = $dryRun
                ? $valid->filter(fn ($r) => !in_array($r->id, $alreadyGone))->values()
                : $conn->table('districts')->whereNotNull('name')->orderBy('id')->get(['id', 'name']);

            $levDeleteIds = [];
            $levReport    = [];
            $deleted      = [];

            // Group by first 3 chars of the full dedup key so heavy first-word
            // typos land in the same bucket (KUDID… / KUDUD… / KUDUN… → "KUD").
            $byPrefix = [];
            foreach ($remaining as $r) {
                $key    = $this->dedupKey($r->name);
                $prefix = substr($key, 0, 3);
                $byPrefix[$prefix][] = ['id' => $r->id, 'name' => $r->name, 'key' => $key];
            }

            foreach ($byPrefix as $fw => $rows) {
                if (count($rows) < 2) {
                    continue;
                }
                // Process higher-occurrence names first so they survive as canonical.
                usort($rows, function ($a, $b) use ($stagingOcc) {
                    $aOcc = $stagingOcc[strtoupper(trim($a['name']))] ?? 0;
                    $bOcc = $stagingOcc[strtoupper(trim($b['name']))] ?? 0;
                    return $bOcc - $aOcc;
                });
                // Compare all pairs within the group
                for ($i = 0; $i < count($rows); $i++) {
                    for ($j = $i + 1; $j < count($rows); $j++) {
                        $a = $rows[$i];
                        $b = $rows[$j];

                        if (isset($deleted[$a['id']]) || isset($deleted[$b['id']])) {
                            continue;
                        }

                        // Tolerance policy:
                        //  - Differing word counts: very strict (≤1) — blocks
                        //    different-qualifier compounds (KOFAR WAIKA vs KOFAR NA ISA).
                        //  - Short names (≤7 chars): strict (≤1) — 2 edits on a short
                        //    word usually means a DIFFERENT place (YANMATA vs YANKABA).
                        //  - Longer same-count names: proportional, capped at 4 — lets
                        //    heavy typos merge (KUDIDDIFAWA vs KUDUNDUFAWA).
                        $shorter   = min(strlen($a['key']), strlen($b['key']));
                        $sameCount = substr_count($a['key'], ' ') === substr_count($b['key'], ' ');

                        if (!$sameCount || $shorter <= 7) {
                            $allowed = 1;
                        } else {
                            $allowed = min(4, max($levLimit, (int) floor($shorter * 0.3)));
                        }

                        $dist = levenshtein($a['key'], $b['key']);
                        if ($dist > 0 && $dist <= $allowed && !$this->shouldNotMerge($a['key'], $b['key'])) {
                            // Keep the one with higher occurrence; delete the other
                            $aOcc = $stagingOcc[strtoupper(trim($a['name']))] ?? 0;
                            $bOcc = $stagingOcc[strtoupper(trim($b['name']))] ?? 0;

                            if ($aOcc >= $bOcc) {
                                $keep   = $a;
                                $remove = $b;
                            } else {
                                $keep   = $b;
                                $remove = $a;
                            }

                            $levDeleteIds[]        = $remove['id'];
                            $deleted[$remove['id']] = true;
                            $levReport[]           = [
                                'keep'   => $keep['name'],
                                'delete' => $remove['name'],
                                'id'     => $remove['id'],
                                'dist'   => $dist,
                            ];
                        }
                    }
                }
            }

            $this->line("  Near-duplicates to remove: " . count($levDeleteIds));
            if (!empty($levReport)) {
                $this->table(
                    ['KEEP', 'DELETE (id)', 'LEV'],
                    array_map(fn ($r) => [$r['keep'], $r['delete'] . '  (id=' . $r['id'] . ')', $r['dist']], $levReport)
                );
            }

            if (!$dryRun && !empty($levDeleteIds)) {
                foreach (array_chunk($levDeleteIds, 500) as $chunk) {
                    $conn->table('districts')->whereIn('id', $chunk)->delete();
                }
                $this->info("  Deleted " . count($levDeleteIds) . " near-duplicates.");
            }
        }

        // ------------------------------------------------------------------
        // Final count
        // ------------------------------------------------------------------
        $this->newLine();
        $finalCount = $conn->table('districts')->count();
        $this->info("=== Done. dbo.districts now has {$finalCount} rows. ===");

        return self::SUCCESS;
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Returns true when two keys look similar by distance but are DIFFERENT places
     * and must never be merged automatically.
     *
     * Rules:
     *  - Cardinal direction words differ (NORTH↔SOUTH, EAST↔WEST)
     *  - Part/section suffix differs (A↔B, PART A↔PART B, 1↔2 as last word)
     *  - Meaningfully different gate/landmark words (MATA↔FADA, AREWA↔KUDU)
     */
    private function shouldNotMerge(string $a, string $b): bool
    {
        $directions = [
            'NORTH', 'SOUTH', 'EAST', 'WEST',
            'NORTHERN', 'SOUTHERN', 'EASTERN', 'WESTERN',
            'NORTHWEST', 'NORTHEAST', 'SOUTHWEST', 'SOUTHEAST',
            'AREWA', 'KUDU', 'GABAS', 'YAMMA',
        ];
        $wordsA = explode(' ', $a);
        $wordsB = explode(' ', $b);

        // Find which words differ
        $maxLen = max(count($wordsA), count($wordsB));
        $sameWordCount = count($wordsA) === count($wordsB);

        for ($i = 0; $i < $maxLen; $i++) {
            $wA = $wordsA[$i] ?? '';
            $wB = $wordsB[$i] ?? '';
            if ($wA === $wB) {
                continue;
            }
            // If one or both differing words are cardinal direction words → don't merge
            if (in_array($wA, $directions, true) || in_array($wB, $directions, true)) {
                return true;
            }
            // If the differing word is a single letter (A/B/C part marker) → don't merge
            if ((strlen($wA) === 1 && ctype_alpha($wA)) || (strlen($wB) === 1 && ctype_alpha($wB))) {
                return true;
            }
            // If the differing word is a single digit (1/2 section marker) → don't merge
            if (ctype_digit($wA) || ctype_digit($wB)) {
                return true;
            }
            // Same word count + same position differs: only treat as a typo when the
            // two words share most of their letters. A large relative edit distance
            // means they are genuinely DIFFERENT words (e.g. YOLA vs WADA), not a typo.
            if ($sameWordCount && $wA !== '' && $wB !== '') {
                $wordDist = levenshtein($wA, $wB);
                $longer   = max(strlen($wA), strlen($wB));
                if ($longer > 0 && ($wordDist / $longer) > 0.4) {
                    return true;
                }
            }
        }

        return false;
    }

    private function dedupKey(string $name): string
    {
        $key = strtoupper($name);
        $key = preg_replace('/[^A-Z0-9\s]/', ' ', $key); // remove apostrophes, /, -, (, ), etc.
        $key = preg_replace('/\s+/', ' ', $key);
        return trim($key);
    }

    private function isInvalid(string $n, $lgaNames): bool
    {
        if (isset($lgaNames[$n])) {
            return true;
        }
        if (strlen($n) < 2) {
            return true;
        }
        // Starts with digit
        if (preg_match('/^\d/', $n)) {
            return true;
        }
        // Starts with noise char: ( ` ' "
        if (preg_match('/^[\(`\'"]/', $n)) {
            return true;
        }
        // Plot/unit ranges: 14A & 14B, E2 & E3, A1 & B2
        if (preg_match('/[A-Z0-9]\s*&\s*[A-Z0-9]/', $n)) {
            return true;
        }
        if (preg_match('/[A-Z0-9]\s+AND\s+[A-Z0-9]/', $n)) {
            return true;
        }
        // TP/ reference codes
        if (preg_match('/^TP\//', $n)) {
            return true;
        }
        // Address prefix words
        if (preg_match('/^(ALONG|NEAR|OFF|OPP|OPPOSITE|BEHIND|BESIDE|VIA|JUNCTION)\s/i', $n)) {
            return true;
        }
        // Western Bypass variants
        if (preg_match('/^W\/(BYE?[-\s]?PASS|BP?|BYEPASS|BYPASS)$/i', $n)) {
            return true;
        }
        if (preg_match('/^WBP$/i', $n)) {
            return true;
        }
        if (preg_match('/^W\/B$/i', $n)) {
            return true;
        }
        if (preg_match('/^WESTERN\s+BYE?[-\s]?PASS/i', $n)) {
            return true;
        }
        // Short road/street names (≤3 words ending in road token)
        $wordCount = count(preg_split('/\s+/', $n));
        if ($wordCount <= 3 && preg_match('/\b(RD|ST|AVE|ROAD|STREET|AVENUE|HWY|HIGHWAY|BYPASS|CRESCENT|CRS|CLOSE|WAY|LINK)\s*$/i', $n)) {
            return true;
        }
        // Facilities / landmarks (not districts): MARKET, MOTOR PARK, LORRY PARK,
        // X STATION, GARAGE, ABATTOIR, FILLING STATION
        if (preg_match('/\b(MARKET|STATION|GARAGE|ABATTOIR|PARK)\s*$/i', $n)) {
            return true;
        }
        // "* CENTER/CENTRE" facilities — but KEEP the real districts
        // FARM CENTRE and CITY CENTRE.
        if (preg_match('/\bCENT(ER|RE)\s*$/i', $n)
            && !preg_match('/^(FARM|CITY)\s+CENT(ER|RE)$/i', $n)) {
            return true;
        }
        // Municipal (LGA-level) and its common misspellings:
        // MUNICIPAL, MUNCIPAL, MINICIPAL, MUNNICIPAL, MINICIPAL, MUNCIPL ...
        if ($wordCount === 1 && strlen($n) >= 6 && levenshtein($n, 'MUNICIPAL') <= 2) {
            return true;
        }
        // Terminal references
        if (preg_match('/^[SN]\/(TERMINAL|TERMINUS|TERMINIUS|TERMINL)S?$/i', $n)) {
            return true;
        }
        // BLOCK / PHASE / MILE / KM
        if (preg_match('/^(BLOCK|PHASE|MILE)\s+\d/i', $n)) {
            return true;
        }
        if (preg_match('/^KM[-\/]\d/i', $n)) {
            return true;
        }
        // Ordinal street: 9TH ST, 10TH LINK
        if (preg_match('/^\d+(ST|ND|RD|TH)\s+(ST|AV|LINK|RD|WAY|CLOSE|ROAD)\b/i', $n)) {
            return true;
        }
        // Noise suffix leftovers
        if (preg_match('/\s+(LAYOUT|QRTS|QTRS|QUARTERS|SCHEME|WARD)\s*$/i', $n)) {
            return true;
        }
        // Standalone tokens that are not district names
        if (preg_match('/^(S\/G(ARI)?|S\/N|GRA|ROAD|WAY|OFF|OLD|NEAR)$/i', $n)) {
            return true;
        }
        // State / country level tokens
        if (preg_match('/^KANO\s*(STATE|STAT)?$/i', $n)) {
            return true;
        }
        if (preg_match('/^(NIGERIA|FCT|FEDERAL CAPITAL TERRITORY)$/i', $n)) {
            return true;
        }
        // Date patterns
        if (preg_match('/^\d{1,4}[-\/]\d{1,2}[-\/]\d{2,4}$/', $n)) {
            return true;
        }
        // Consonant-only short codes: BUK, KTI, WBP, AKK (2-3 chars, no vowels)
        if (preg_match('/^[B-DF-HJ-NP-TV-Z]{2,3}$/', $n)) {
            return true;
        }
        // NO / N0 prefixed house numbers
        if (preg_match('/^N[O0]\.?\s*\d/i', $n)) {
            return true;
        }
        // Letter-dash-number codes: C-5, A-113
        if (preg_match('/^[A-Z]{1,2}-\d/', $n)) {
            return true;
        }
        // Landmark phrases
        if (preg_match('/^(A\s+(CAR|THE)|AT\s+THE)/i', $n)) {
            return true;
        }

        return false;
    }
}
