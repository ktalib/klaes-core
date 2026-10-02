<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TimelineWeightingService
{
    /** Targets per batched query - see getRawRecordsForMany(). */
    private const BATCH_CHUNK = 300;

    /**
     * Fetch all raw timeline records for a property.
     */
    public function getRawRecords(?string $fileNumber, ?string $propId): array
    {
        if (!$fileNumber && !$propId) {
            return [];
        }

        $connection = DB::connection('sqlsrv');
        $allRecords = [];

        // 1. file_history_staging
        $fhQuery = $connection->table('file_history_staging');
        $this->applyFilters($fhQuery, $fileNumber, $propId, ['mlsFNo', 'fileno', 'kangisFileNo', 'NewKANGISFileno']);
        $fhRows = $fhQuery->get()->map(fn($r) => $this->normalizeRow($r, 'file_history_staging'))->toArray();
        $allRecords = array_merge($allRecords, $fhRows);

        // 2. CofO_staging
        $cofoQuery = $connection->table('CofO_staging');
        $this->applyFilters($cofoQuery, $fileNumber, $propId, ['mlsFNo', 'fileno', 'kangisFileNo', 'NewKANGISFileno']);
        $cofoRows = $cofoQuery->get()->map(fn($r) => $this->normalizeRow($r, 'CofO_staging'))->toArray();
        $allRecords = array_merge($allRecords, $cofoRows);

        // 3. pra
        $praQuery = $connection->table('pra');
        $this->applyFilters($praQuery, $fileNumber, $propId, ['mlsFNo', 'fileno', 'kangisFileNo', 'NewKANGISFileno']);
        $praRows = $praQuery->get()->map(fn($r) => $this->normalizeRow($r, 'pra'))->toArray();
        $allRecords = array_merge($allRecords, $praRows);

        // 4. deed_registrations
        $deedQuery = $connection->table('deed_registrations');
        $this->applyFilters($deedQuery, $fileNumber, $propId, ['fileno', 'parent_fileno']);
        $deedRows = $deedQuery->get()->map(fn($r) => $this->normalizeRow($r, 'deed_registrations'))->toArray();
        $allRecords = array_merge($allRecords, $deedRows);

        return $allRecords;
    }


    /**
     * The same raw records as getRawRecords(), for many properties in one pass.
     *
     * getRawRecords() runs four queries per property, and each one matches file
     * numbers with UPPER(col) = ? — non-sargable, so every call scans all four
     * tables. A listing that asks per row pays that scan once per row: a 20-row
     * page measured 6.5s. Asking for the whole page at once turns it into four
     * scans total (~0.07s), because the row-to-property matching moves out of SQL
     * and into the maps below.
     *
     * Membership is deliberately identical to applyFilters(): a row belongs to a
     * target when its prop_id equals that target's, or any of the table's file
     * columns equals its file number, case-insensitively. A row matching several
     * targets is handed to each of them, exactly as separate queries would.
     *
     * @param  array<string|int, array{fileNumber?:?string, propId?:?string}>  $targets  keyed by caller's row id
     * @return array<string|int, array<int, array>>  the same keys, each with its raw records
     */
    public function getRawRecordsForMany(array $targets): array
    {
        // Each query binds one parameter per prop_id plus one per file number for
        // every file column (five per target on the four-column tables). SQL Server
        // caps a statement at ~2100 parameters, and the callers that render a whole
        // unpaginated register would sail past it, so the work is sliced.
        if (count($targets) > self::BATCH_CHUNK) {
            $merged = [];
            foreach (array_chunk($targets, self::BATCH_CHUNK, true) as $chunk) {
                $merged += $this->getRawRecordsForMany($chunk);
            }

            return $merged;
        }

        $results = [];
        $propIndex = [];   // prop_id            => [target keys]
        $fileIndex = [];   // UPPERCASE file no  => [target keys]

        foreach ($targets as $key => $target) {
            $results[$key] = [];

            $propId = trim((string) ($target['propId'] ?? ''));
            if ($propId !== '') {
                $propIndex[$propId][] = $key;
            }

            $fileNumber = trim((string) ($target['fileNumber'] ?? ''));
            if ($fileNumber !== '') {
                $fileIndex[strtoupper($fileNumber)][] = $key;
            }
        }

        if (empty($propIndex) && empty($fileIndex)) {
            return $results;
        }

        // Cast back to string: PHP silently turns a numeric-string array key into an
        // int, and binding prop_id as an int makes SQL Server convert every row's
        // nvarchar prop_id to compare - which blows up on the first value too wide
        // for an int ("The conversion of the nvarchar value '6957764400' overflowed
        // an int column"). getRawRecords() never hit this because it binds one
        // string at a time.
        $propIds = array_map('strval', array_keys($propIndex));
        $fileNos = array_map('strval', array_keys($fileIndex));

        $sources = [
            'file_history_staging' => ['mlsFNo', 'fileno', 'kangisFileNo', 'NewKANGISFileno'],
            'CofO_staging'         => ['mlsFNo', 'fileno', 'kangisFileNo', 'NewKANGISFileno'],
            'pra'                  => ['mlsFNo', 'fileno', 'kangisFileNo', 'NewKANGISFileno'],
            'deed_registrations'   => ['fileno', 'parent_fileno'],
        ];

        $connection = DB::connection('sqlsrv');

        foreach ($sources as $table => $fileColumns) {
            $query = $connection->table($table);
            $query->where(function ($q) use ($propIds, $fileNos, $fileColumns) {
                if (!empty($propIds)) {
                    $q->orWhereIn('prop_id', $propIds);
                }
                if (!empty($fileNos)) {
                    // Compared bare, not as UPPER({$col}): every one of these columns
                    // is SQL_Latin1_General_CP1_CI_AS, so the match is already
                    // case-insensitive, and wrapping the column kills the index -
                    // it was the whole cost here (2.5s of scans vs 0.1s of seeks).
                    // The values are upper-cased, which CI matching accepts, and the
                    // PHP-side bucketing below upper-cases both sides regardless.
                    foreach ($fileColumns as $col) {
                        $q->orWhereIn($col, $fileNos);
                    }
                }
            });

            foreach ($query->get() as $row) {
                $rowArr = (array) $row;

                // Every target this row answers to, deduped: a row can match one
                // target on prop_id and another on a file number.
                $keys = [];
                $rowPropId = trim((string) ($rowArr['prop_id'] ?? ''));
                if ($rowPropId !== '' && isset($propIndex[$rowPropId])) {
                    foreach ($propIndex[$rowPropId] as $key) {
                        $keys[$key] = true;
                    }
                }
                foreach ($fileColumns as $col) {
                    $value = strtoupper(trim((string) ($rowArr[$col] ?? '')));
                    if ($value !== '' && isset($fileIndex[$value])) {
                        foreach ($fileIndex[$value] as $key) {
                            $keys[$key] = true;
                        }
                    }
                }

                if (empty($keys)) {
                    continue;
                }

                $normalized = $this->normalizeRow($row, $table);
                foreach (array_keys($keys) as $key) {
                    $results[$key][] = $normalized;
                }
            }
        }

        return $results;
    }

    /**
     * Weighted timeline counts for many properties, in one pass.
     *
     * @param  array<string|int, array{fileNumber?:?string, propId?:?string}>  $targets
     * @return array<string|int, int>
     */
    public function getWeightedCountsForMany(array $targets): array
    {
        $counts = [];
        foreach ($this->getRawRecordsForMany($targets) as $key => $records) {
            $counts[$key] = $this->getWeightedCount($records);
        }

        return $counts;
    }

    /**
     * Calculate the count of weighted (preferred/unique) records.
     */
    public function getWeightedCount(array $records): int
    {
        if (empty($records)) {
            return 0;
        }

        $weightedRecords = $this->getWeightedRecords($records);
        return count($weightedRecords);
    }

    /**
     * Filter records to only include those that are "weighted" (preferred or unique).
     */
    public function getWeightedRecords(array $records): array
    {
        return $this->getWeightingAnalysis($records)['weighted'];
    }

    /**
     * Build a compact, UI-ready ownership chain for a file (or property).
     *
     * Pulls the weighted, deduped records across all sources and orders them the
     * same way as the web Property Timeline (weight first — OP=10, TOT=9, ROFO=8,
     * others=1 — then chronologically, then id). Each node carries the holder,
     * the other party, the transaction type, the date and a friendly source label.
     *
     * Returns an empty array when the file has no transaction history.
     *
     * @return array<int, array{holder:string, to:?string, transaction_type:string, date:?string, source:string}>
     */
    public function holderHistory(?string $fileNumber, ?string $propId = null): array
    {
        $fileNumber = trim((string) $fileNumber);
        if ($fileNumber === '' && ! $propId) {
            return [];
        }

        $records = $this->getRawRecords($fileNumber ?: null, $propId);
        if (empty($records)) {
            return [];
        }

        $weighted = $this->getWeightedRecords($records);
        if (empty($weighted)) {
            return [];
        }

        usort($weighted, function ($a, $b) {
            $wa = (float) ($a['timeline_weight'] ?? 1.0);
            $wb = (float) ($b['timeline_weight'] ?? 1.0);
            if ($wa !== $wb) {
                return $wb <=> $wa;
            }
            $da = $this->holderSortDate($a) ?? '9999-12-31';
            $db = $this->holderSortDate($b) ?? '9999-12-31';
            if ($da !== $db) {
                return $da <=> $db;
            }
            return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
        });

        $sourceLabels = [
            'file_history_staging' => 'File History',
            'CofO_staging'         => 'CofO',
            'pra'                  => 'PRA',
            'deed_registrations'   => 'Deed',
        ];

        $history = [];
        foreach ($weighted as $row) {
            $party1 = trim((string) ($row['party_1'] ?? ''));
            $party2 = trim((string) ($row['party_2'] ?? ''));
            $holder = $party1 !== '' ? $party1 : ($party2 !== '' ? $party2 : 'Unknown');

            $type = trim((string) ($row['transaction_type'] ?? ''));
            $type = $type !== '' ? ucwords(strtolower($type)) : 'Transaction';

            $history[] = [
                'holder'           => $holder,
                'to'               => ($party2 !== '' && strcasecmp($party2, $holder) !== 0) ? $party2 : null,
                'transaction_type' => $type,
                'date'             => $this->holderDisplayDate($row['transaction_date'] ?? null),
                'source'           => $sourceLabels[$row['source_table'] ?? ''] ?? ($row['source_table'] ?? ''),
            ];
        }

        return $history;
    }

    /** Parse a record's transaction date to a sortable Y-m-d string (or null). */
    private function holderSortDate(array $row): ?string
    {
        $raw = $row['transaction_date'] ?? null;
        if (empty($raw)) {
            return null;
        }
        try {
            return Carbon::parse($raw)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Human-friendly display date for a holder node (or null when unparseable). */
    private function holderDisplayDate($raw): ?string
    {
        if (empty($raw)) {
            return null;
        }
        try {
            return Carbon::parse($raw)->format('M j, Y');
        } catch (\Throwable $e) {
            return is_string($raw) ? $raw : null;
        }
    }

    /**
     * Perform full weighting analysis, separating records into weighted and omitted (duplicates).
     */
    public function getWeightingAnalysis(array $records): array
    {
        if (empty($records)) {
            return ['weighted' => [], 'omitted' => []];
        }

        $weightedMap = []; // key -> row
        $omitted = [];

        foreach ($records as $row) {
            $key = $this->getRecordKey($row);

            // Rows that can't be keyed for dedup are always "unique" (weighted)
            if ($key === null) {
                $row['_ptl_weighting_status'] = 'unique';
                $weightedMap[spl_object_hash((object) $row)] = $row;
                continue;
            }

            if (!array_key_exists($key, $weightedMap)) {
                $row['_ptl_weighting_status'] = 'preferred'; // Tentative
                $weightedMap[$key] = $row;
                continue;
            }

            $existing = $weightedMap[$key];

            $rowRichness = $this->calculateRichnessScore($row);
            $existingRichness = $this->calculateRichnessScore($existing);

            $isNewBetter = false;
            if ($rowRichness > $existingRichness) {
                $isNewBetter = true;
            } elseif ($rowRichness === $existingRichness) {
                if ($this->getSourceBaseScore($row) > $this->getSourceBaseScore($existing)) {
                    $isNewBetter = true;
                }
            }

            if ($isNewBetter) {
                // The previous winner is now an omitted record
                $existing['_ptl_weighting_status'] = 'duplicate';
                $omitted[] = $existing;

                $row['_ptl_weighting_status'] = 'preferred';
                $weightedMap[$key] = $row;
            } else {
                $row['_ptl_weighting_status'] = 'duplicate';
                $omitted[] = $row;
            }
        }

        // Final pass to distinguish 'unique' vs 'preferred'
        // If a key in weightedMap has no corresponding records in omitted with the same key, it's unique.
        // Actually, let's keep it simple: if it's in weightedMap, it's a winner.
        // If we want to be exact:
        $finalWeighted = [];
        $omittedKeys = array_map(fn($r) => $this->getRecordKey($r), $omitted);
        foreach ($weightedMap as $key => $row) {
            if ($row['_ptl_weighting_status'] === 'unique') {
                $finalWeighted[] = $row;
                continue;
            }
            if (!in_array($key, $omittedKeys)) {
                $row['_ptl_weighting_status'] = 'unique';
            }
            $finalWeighted[] = $row;
        }

        return [
            'weighted' => $finalWeighted,
            'omitted' => $omitted,
        ];
    }

    protected function applyFilters($query, ?string $fileNumber, ?string $propId, array $fileColumns): void
    {
        $query->where(function ($q) use ($fileNumber, $propId, $fileColumns) {
            if ($fileNumber) {
                $upper = strtoupper($fileNumber);
                foreach ($fileColumns as $col) {
                    $q->orWhereRaw("UPPER({$col}) = ?", [$upper]);
                }
            }
            if ($propId) {
                $q->orWhere('prop_id', $propId);
            }
        });

        // Exclude deleted if column exists
        // (Note: Schema check is omitted for performance here, 
        // but adding it if we want to be safe like the controllers)
    }

    public function normalizeRow($row, string $source): array
    {
        $rowArr = (array) $row;
        $rowArr['source_table'] = $source;

        // Ensure consistent keys for weighting logic
        $rawType = $rowArr['transaction_type'] ?? ($rowArr['instrument_type'] ?? '');
        $transType = $this->getCanonicalInstrumentType($rawType);

        // Date priority logic: For weight 1 (CofO, etc.), prioritize Reg Date. 
        // For weight 10/9/8 (OP/TOT/ROFO), prioritize Transaction Date.
        $isHighPriority = in_array($transType, ['occupancy permit', 'transfer of title', 'right of occupancy']);

        if ($isHighPriority) {
            $rowArr['transaction_date'] = $rowArr['transaction_date'] ?? ($rowArr['deeds_date'] ?? ($rowArr['reg_date'] ?? null));
        } else {
            $rowArr['transaction_date'] = $rowArr['reg_date'] ?? ($rowArr['transaction_date'] ?? ($rowArr['deeds_date'] ?? null));
        }

        // Resolve file number
        $rowArr['file_number'] = $rowArr['file_number'] ?? ($rowArr['mlsFNo'] ?? ($rowArr['fileno'] ?? ($rowArr['kangisFileNo'] ?? ($rowArr['NewKANGISFileno'] ?? ($rowArr['temp_fileno'] ?? '')))));

        // Resolve location
        $rowArr['location'] = $rowArr['location'] ?? ($rowArr['district'] ?? ($rowArr['lga'] ?? ($rowArr['lgsaOrCity'] ?? null)));

        // Party Mapping (Handle both CamelCase and lowercase)
        $rowArr['party_1'] = $this->sanitizePartyName($rowArr['party_1'] ?? ($rowArr['Grantor'] ?? ($rowArr['grantor'] ?? ($rowArr['Assignor'] ?? ($rowArr['assignor'] ?? ($rowArr['Mortgagor'] ?? ($rowArr['mortgagor'] ?? '')))))));
        $rowArr['party_2'] = $this->sanitizePartyName($rowArr['party_2'] ?? ($rowArr['Grantee'] ?? ($rowArr['grantee'] ?? ($rowArr['Assignee'] ?? ($rowArr['assignee'] ?? ($rowArr['Mortgagee'] ?? ($rowArr['mortgagee'] ?? '')))))));

        // Ensure lowercase aliases exist for normalizeTimelineRow compatibility
        $roles = ['Assignor', 'Assignee', 'Mortgagor', 'Mortgagee', 'Grantor', 'Grantee', 'Surrenderor', 'Surrenderee', 'Lessor', 'Lessee'];
        foreach ($roles as $role) {
            $lower = strtolower($role);
            if (!isset($rowArr[$lower]) && isset($rowArr[$role])) {
                $rowArr[$lower] = $rowArr[$role];
            }
        }

        // registration_number is used in deed_registrations
        $regNo = $rowArr['regNo'] ?? ($rowArr['registration_number'] ?? ($rowArr['registration'] ?? ''));

        $s = $rowArr['serialNo'] ?? ($rowArr['serial_no'] ?? '');
        $p = $rowArr['pageNo'] ?? ($rowArr['page_no'] ?? '');
        $v = $rowArr['volumeNo'] ?? ($rowArr['volume_no'] ?? '');

        // If regNo has slashes, prioritize its parts for consistency (often richer/canonical)
        if (str_contains($regNo, '/')) {
            $parts = explode('/', $regNo);
            if (count($parts) >= 1 && trim($parts[0]) !== '' && trim($parts[0]) !== '0')
                $s = trim($parts[0]);
            if (count($parts) >= 2 && trim($parts[1]) !== '' && trim($parts[1]) !== '0')
                $p = trim($parts[1]);
            if (count($parts) >= 3 && trim($parts[2]) !== '' && trim($parts[2]) !== '0')
                $v = trim($parts[2]);
        }

        $rowArr['serial_no'] = $s;
        $rowArr['page_no'] = $p;
        $rowArr['volume_no'] = $v;
        $rowArr['regNo'] = $regNo;
        $rowArr['timeline_weight'] = $this->getSourceBaseScore($rowArr);

        return $rowArr;
    }

    protected function getRecordKey(array $row): ?string
    {
        $source = $row['source_table'];
        $dedupableSources = ['file_history_staging', 'pra', 'CofO_staging', 'deed_registrations'];
        if (!in_array($source, $dedupableSources)) {
            return null;
        }

        $transType = $this->getCanonicalInstrumentType($row['transaction_type'] ?? '');
        if (!$transType) {
            return null;
        }

        $serialNo = $this->cleanNumericValue($row['serial_no'] ?? '') ?: '0';
        $pageNo = $this->cleanNumericValue($row['page_no'] ?? '') ?: '0';
        $volumeNo = $this->cleanNumericValue($row['volume_no'] ?? '') ?: '0';

        $hasRealReg = ($serialNo !== '0' && $serialNo !== '' && $serialNo !== '-') ||
            ($pageNo !== '0' && $pageNo !== '' && $pageNo !== '-') ||
            ($volumeNo !== '0' && $volumeNo !== '' && $volumeNo !== '-');

        if ($hasRealReg) {
            return 'reg|' . $transType . '|' . $serialNo . '/' . $pageNo . '/' . $volumeNo;
        }

        // Fallback key: party + date
        $party1 = $this->normalizeString($row['party_1'] ?? '');
        $party2 = $this->normalizeString($row['party_2'] ?? '');

        // Match JS: ignore date for Right of Occupancy
        $date = ($transType === 'right of occupancy') ? '' : $this->normalizeString($row['transaction_date'] ?? ($row['deeds_date'] ?? ''));

        // Without reg particulars we need a discriminator that keeps genuinely different
        // properties apart (e.g. the source plots of a merger can share the same govt
        // grantor + grantee on a Right of Occupancy and must NOT collapse). prop_id is
        // that discriminator: same property = same prop_id, so copies across PRA/FH/CofO
        // dedupe even when their file-number labels differ (MLS vs KANGIS). Fall back to
        // the file number only when the row has no prop_id.
        $propId = trim((string) ($row['prop_id'] ?? ''));
        $discriminator = $propId !== ''
            ? 'p:' . $propId
            : $this->normalizeString((string) ($row['file_number'] ?? ''));

        if (!$party1 && !$party2 && !$date && !$discriminator) {
            return null;
        }

        return implode('|', [$transType, $party1, $party2, $date, $discriminator]);
    }

    protected function calculateRichnessScore(array $item): float
    {
        $score = 0.0;
        $hasText = fn($v) => !empty($v) && trim((string) $v) !== '-' && trim((string) $v) !== '0' && trim((string) $v) !== 'N/A';

        // Primary parties (critical for identity)
        if ($hasText($item['party_1'] ?? null))
            $score += 2;
        if ($hasText($item['party_2'] ?? null))
            $score += 2;

        // Registration particulars (critical for legal identity)
        $serial = $item['serial_no'] ?? '';
        $page = $item['page_no'] ?? '';
        $volume = $item['volume_no'] ?? '';
        if ($hasText($serial))
            $score += 1.5;
        if ($hasText($page))
            $score += 1.5;
        if ($hasText($volume))
            $score += 1.5;

        // Dates (critical for chronological accuracy)
        if ($hasText($item['transaction_date'] ?? null) || $hasText($item['deeds_date'] ?? null))
            $score += 3;
        if ($hasText($item['reg_date'] ?? null))
            $score += 1;
        if ($hasText($item['reg_time'] ?? null) || $hasText($item['deeds_time'] ?? null))
            $score += 0.5;

        // Contextual richness
        if ($hasText($item['location'] ?? null) || $hasText($item['district'] ?? null))
            $score += 1;
        if ($hasText($item['property_description'] ?? null) || $hasText($item['comments'] ?? null))
            $score += 1;
        if ($hasText($item['land_use'] ?? null))
            $score += 0.5;

        return $score;
    }

    protected function getSourceBaseScore(array $row): float
    {
        $source = $row['source_table'];
        $transType = $this->getCanonicalInstrumentType($row['transaction_type'] ?? '');

        if ($transType === 'occupancy permit')
            return 10.0;
        if ($transType === 'transfer of title')
            return 9.5;
        if ($transType === 'right of occupancy')
            return 9.0;
        if ($source === 'CofO_staging' || $transType === 'certificate of occupancy')
            return 1.0;
        if ($source === 'pra' || $source === 'deed_registrations')
            return 1.0;
        if ($source === 'file_history_staging')
            return 1.0;

        return 1.0;
    }

    /**
     * Public wrapper over the canonical instrument mapping below, so callers that
     * need to reason about an instrument FAMILY (RofO vs CofO vs OP vs
     * assignment) use the same word list this service weights with, instead of
     * re-inventing their own. See TitleHolderResolver.
     */
    public function canonicalInstrumentType(?string $value): string
    {
        return $this->getCanonicalInstrumentType((string) $value);
    }

    protected function getCanonicalInstrumentType(string $value): string
    {
        $raw = $this->normalizeString($value);
        if (!$raw || $raw === '-')
            return '';

        // Right of Occupancy
        if (str_contains($raw, 'right of occupancy') || str_contains($raw, 'right of occupanc'))
            return 'right of occupancy';
        if (preg_match('/^r\s*of\s*o$/i', $raw))
            return 'right of occupancy';
        $compact = preg_replace('/[^a-z0-9]/', '', $raw);
        if (preg_match('/^r[o0]f[o0]$/', $compact) || str_starts_with($compact, 'r0f0occupanc') || str_starts_with($compact, 'rofoccupanc'))
            return 'right of occupancy';
        if ($raw === 'statutory right of occupancy' || $raw === 'customary right of occupancy')
            return 'right of occupancy';

        // CofO
        if (str_contains($raw, 'certificate of occupancy') || str_contains($raw, 'cert of occupancy'))
            return 'certificate of occupancy';
        if (preg_match('/^c\s*of\s*o$/i', $raw) || $compact === 'cofo' || $compact === 'c0f0')
            return 'certificate of occupancy';

        // OP
        if (str_contains($raw, 'occupancy permit'))
            return 'occupancy permit';
        if ($raw === 'op' || $compact === 'op')
            return 'occupancy permit';

        // Transfer
        if (str_contains($raw, 'transfer of title'))
            return 'transfer of title';

        // Mortgage
        if (in_array($raw, ['mortgage', 'deed of mortgage', 'tripartite mortgage', 'legal mortgage', 'equitable mortgage']))
            return 'deed of mortgage';

        // Assignment
        if (str_contains($raw, 'assignment') && !str_contains($raw, 'sub-assignment') && !str_contains($raw, 're-assignment') && !str_contains($raw, 'reassignment'))
            return 'deed of assignment';

        // Surrender
        if (in_array($raw, ['deed of surrender', 'deed of release', 'deed of surrender and release', 'deed of surrender & release']))
            return 'deed of surrender and release';

        // POA
        if (str_contains($raw, 'power of attorney') || $raw === 'poa' || $raw === 'ipoa' || $compact === 'poa' || $compact === 'ipoa')
            return 'power of attorney';

        return $raw;
    }

    protected function normalizeString(string $value): string
    {
        $v = trim(strtolower($value));
        $v = preg_replace('/\s+/', ' ', $v);
        $v = str_replace([',', '.'], '', $v);

        // Strip common titles for better party matching
        $titles = ['alhaii', 'alhaji', 'alh', 'hajiya', 'haj', 'malam', 'mallam', 'mal', 'dr', 'prof', 'mr', 'mrs', 'chief', 'architect', 'arc', 'engr', 'engineer'];
        foreach ($titles as $title) {
            $v = preg_replace('/^' . $title . '\s+/i', '', $v);
            $v = preg_replace('/\s+' . $title . '\s+/i', ' ', $v);
        }

        // Standardize common name variants
        $v = preg_replace('/\b(muhammad|mohammad|muhammed|mohd)\b/i', 'mohammed', $v);

        return trim($v);
    }

    protected function sanitizePartyName(string $value): string
    {
        $v = trim((string) $value);
        if ($v === '') {
            return '';
        }

        // Strip obvious quotes around names or stray quote characters from imported strings.
        $v = str_replace(['"', '“', '”'], '', $v);
        $v = preg_replace('/^[\'"\«\»\‘\’]+|[\'"\«\»\‘\’]+$/u', '', $v);
        return trim($v);
    }

    protected function cleanNumericValue($value): string
    {
        if ($value === null)
            return '';
        $s = trim((string) $value);
        if ($s === '' || $s === 'null' || $s === 'n/a')
            return '';
        return preg_replace('/\.0$/', '', $s);
    }
}
