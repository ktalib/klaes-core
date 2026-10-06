<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * After-the-fact audit of a commissioning: did every table the commissioning engine
 * is meant to write actually get its row?
 *
 * Read-only. Each step of MlsFileNoController's commissioning is wrapped in its own
 * try/catch (staging, OSS mirror, tracking...), so a step can fail quietly while the
 * file itself is issued — e.g. a duplex batch that never showed the tracking card
 * came out with no file_tracker row. This lists, per file, which of those writes
 * are present, missing, or not expected for that kind of commissioning.
 *
 * Statuses: ok | missing | warn (present but in an odd state) | na (not expected).
 */
class CommissioningChecklistService
{
    public const OK = 'ok';
    public const MISSING = 'missing';
    public const WARN = 'warn';
    public const NA = 'na';

    /** file_option values that retire a parent file. */
    private const PARCEL_OPTIONS = ['subdivision', 'merger', 'regrant', 'extension', 'separation', 'duplex'];

    /** mls_file_no.source values that retire a parent file (CoP is commissioned as "normal"). */
    private const PARCEL_SOURCES = ['SUBDIVISION', 'CHANGE OF PURPOSE', 'RE-GRANT', 'EXTENSION FILE', 'MERGER', 'SEPARATION', 'DUPLEX'];

    /** Above this many files the per-file successor scan is skipped (parents still resolve). */
    private const SUCCESSOR_SCAN_LIMIT = 100;

    public const ITEMS = [
        'mls_file_no'       => 'MLS File No',
        'file_number'       => 'fileNumber',
        'entity'            => 'Entity Table',
        'customer'          => 'Customers',
        'file_indexing'     => 'File Indexings',
        'old_file_number'   => 'Old File Numbers',
        'oss_application'   => 'OSS Applications',
        'file_tracking'     => 'File Tracking',
        'decommissioned'    => 'Decommissioned (parcel update)',
    ];

    private string $connection = 'sqlsrv';

    /**
     * Every file in a commissioning batch (live rows only).
     *
     * @return array<int,string>
     */
    public function batchFileNumbers(string $batchNo, int $limit = 3000): array
    {
        return DB::connection($this->connection)->table('mls_file_no')
            ->where('batch_no', $batchNo)
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->orderBy('serial_number')
            ->limit($limit)
            ->pluck('full_file_number')
            ->filter()
            ->map(fn ($n) => trim((string) $n))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param array<int,string> $fileNumbers
     * @return array{files:array<int,array>,summary:array<int,array>,total:int,complete:int}
     */
    public function check(array $fileNumbers): array
    {
        $numbers = [];
        foreach ($fileNumbers as $n) {
            $n = trim((string) $n);
            if ($n !== '') {
                $numbers[$this->key($n)] = $n;
            }
        }
        $numbers = array_values($numbers);

        if (!$numbers) {
            return ['files' => [], 'summary' => [], 'total' => 0, 'complete' => 0];
        }

        $mls = $this->keyed('mls_file_no', 'full_file_number', $numbers,
            ['id', 'full_file_number', 'file_option', 'source', 'sub_source', 'system_sub_type', 'old_fileno',
             'batch_no', 'tracking_id', 'source_pra_id', 'source_instrument_capture_id', 'is_deleted']);
        $fileNumberRows = $this->fileNumberRows($numbers);
        $entities = $this->keyed('entities_staging', 'file_number', $numbers, ['id', 'file_number', 'is_decommissioned']);
        $customers = $this->keyed('customers_staging', 'file_number', $numbers, ['id', 'file_number', 'deleted_at', 'is_decommissioned']);
        $indexings = $this->keyed('file_indexings', 'file_number', $numbers, ['id', 'file_number', 'is_deleted', 'related_fileno']);
        $oldNumbers = $this->keyed('old_file_numbers', 'file_number', $numbers, ['id', 'file_number', 'old_file_number']);
        $oss = $this->keyed('oss_applications', 'file_no', $numbers, ['id', 'file_no', 'system_source', 'is_deleted', 'deleted_at']);
        $trackers = $this->keyed('file_tracker', 'file_number', $numbers, ['id', 'file_number', 'tracking_id', 'current_office_name', 'receiving_officer_name']);
        $decommission = $this->parentDecommissioning($numbers, $mls, $indexings, $fileNumberRows);

        $files = [];
        foreach ($numbers as $number) {
            $k = $this->key($number);
            $m = $this->firstActive($mls[$k] ?? [], 'is_deleted');
            $isTemporary = $m && strtolower(trim((string) $m->file_option)) === 'temporary';

            $items = [
                'mls_file_no'     => $this->mlsItem($mls[$k] ?? []),
                'file_number'     => $isTemporary
                    ? $this->na('Temporary files are not added to fileNumber')
                    : $this->fileNumberItem($fileNumberRows[$k] ?? []),
                'entity'          => $isTemporary
                    ? $this->na('Temporary files have no entity row')
                    : $this->presence($entities[$k] ?? [], fn ($r) => 'Entity #' . $r->id, 'No entity row'),
                'customer'        => $isTemporary
                    ? $this->na('Temporary files have no customer row')
                    : $this->customerItem($customers[$k] ?? []),
                'file_indexing'   => $this->indexingItem($indexings[$k] ?? []),
                'old_file_number' => $this->oldNumberItem($m, $oldNumbers[$k] ?? []),
                'oss_application' => $this->ossItem($m, $oss[$k] ?? []),
                'file_tracking'   => empty($trackers[$k]) && $this->isRetired($fileNumberRows[$k] ?? [])
                    // An intermediate file retired by a later parcel update (a CoP output
                    // subdivided at once) is never handed on; its successors carry tracking.
                    ? $this->na('Since decommissioned — tracking follows the successor files')
                    : $this->presence($trackers[$k] ?? [], function ($r) {
                        $where = trim((string) ($r->current_office_name ?? ''));
                        $who = trim((string) ($r->receiving_officer_name ?? ''));
                        return trim(($r->tracking_id ?? ('Tracker #' . $r->id)) . ($where !== '' ? ' → ' . $where : '') . ($who !== '' ? ' (' . $who . ')' : ''));
                    }, 'No tracking opened — the file was never dispatched to an office'),
                'decommissioned'  => $decommission[$k] ?? $this->na('Not a parcel update'),
            ];

            $missing = count(array_filter($items, fn ($i) => $i['status'] === self::MISSING));
            $files[] = [
                'file_number' => $number,
                'batch_no'    => $m->batch_no ?? null,
                'source'      => $m ? trim(($m->source ?? '') . ($m->sub_source ? ' / ' . $m->sub_source : '')) : null,
                'items'       => $items,
                'missing'     => $missing,
                'complete'    => $missing === 0,
            ];
        }

        return [
            'files'    => $files,
            'summary'  => $this->summarise($files),
            'total'    => count($files),
            'complete' => count(array_filter($files, fn ($f) => $f['complete'])),
        ];
    }

    // ---- items --------------------------------------------------------------

    private function mlsItem(array $rows): array
    {
        if (!$rows) {
            return $this->missing('Not in the commissioning register (mls_file_no)');
        }
        $active = $this->firstActive($rows, 'is_deleted');
        if (!$active) {
            return $this->warn('Only a deleted register row exists (#' . $rows[0]->id . ')');
        }
        $detail = '#' . $active->id;
        if (!empty($active->batch_no)) {
            $detail .= ' · batch ' . $active->batch_no;
        }
        if (count($rows) > 1) {
            return $this->warn($detail . ' · ' . count($rows) . ' register rows for this number');
        }
        return $this->ok($detail);
    }

    private function fileNumberItem(array $rows): array
    {
        if (!$rows) {
            return $this->missing('No fileNumber row');
        }
        $active = $this->firstActive($rows, 'is_deleted');
        if (!$active) {
            return $this->warn('Only deleted fileNumber rows (#' . $rows[0]->id . ')');
        }
        // Retired by a LATER parcel update (e.g. a CoP file that was then subdivided).
        // That is the file's history, not a gap in its commissioning.
        if ((int) ($active->is_decommissioned ?? 0) === 1) {
            return $this->ok('#' . $active->id . ' · since decommissioned'
                . (!empty($active->successor_file_no) ? ' → ' . $this->shortList($active->successor_file_no) : ''));
        }
        return $this->ok('#' . $active->id . (count($rows) > 1 ? ' (+' . (count($rows) - 1) . ' more)' : ''));
    }

    private function customerItem(array $rows): array
    {
        if (!$rows) {
            return $this->missing('No customer row');
        }
        $live = array_values(array_filter($rows, fn ($r) => empty($r->deleted_at)));
        if (!$live) {
            return $this->warn('Only deleted customer rows (#' . $rows[0]->id . ')');
        }
        return $this->ok('Customer #' . $live[0]->id);
    }

    private function indexingItem(array $rows): array
    {
        if (!$rows) {
            return $this->missing('Not indexed — no file_indexings row');
        }
        $active = $this->firstActive($rows, 'is_deleted');
        if (!$active) {
            return $this->warn('Only a deleted indexing row (#' . $rows[0]->id . ')');
        }
        return $this->ok('Indexing #' . $active->id);
    }

    private function oldNumberItem(?object $mls, array $rows): array
    {
        $expected = $mls ? trim((string) ($mls->old_fileno ?? '')) : '';
        if ($rows) {
            $olds = array_unique(array_filter(array_map(fn ($r) => trim((string) $r->old_file_number), $rows)));
            return $this->ok(implode(', ', $olds));
        }
        if ($expected !== '') {
            return $this->missing('Re-issuance of ' . $expected . ' but no old_file_numbers row');
        }
        return $this->na('No old file number (not a re-issuance)');
    }

    private function ossItem(?object $mls, array $rows): array
    {
        $live = array_values(array_filter($rows, fn ($r) => empty($r->deleted_at)
            && ($r->is_deleted === null || (int) $r->is_deleted === 0)));
        if ($live) {
            $r = $live[0];
            return $this->ok('#' . $r->id . (!empty($r->system_source) ? ' · ' . $r->system_source : ''));
        }

        // Mirrors MlsCommissioningOssApplicationService::sync(): an OSS commissioning
        // that is not OP-backed raises no application of its own.
        if ($mls && strtoupper(trim((string) ($mls->system_sub_type ?? ''))) === 'OSS') {
            $opBacked = str_starts_with(strtoupper(trim((string) ($mls->sub_source ?? ''))), 'OP ')
                || trim((string) ($mls->source_pra_id ?? '')) !== ''
                || trim((string) ($mls->source_instrument_capture_id ?? '')) !== '';
            if (!$opBacked) {
                return $this->na('OSS commissioning without an OP raises no application');
            }
        }

        return $rows
            ? $this->warn('Only deleted OSS applications (#' . $rows[0]->id . ')')
            : $this->missing('No OSS application mirror');
    }

    // ---- parcel-update decommissioning ---------------------------------------

    /**
     * For each parcel-update file: was the file it came from retired into
     * decommissioned_files naming it as a successor?
     *
     * @return array<string,array> keyed by file key; only parcel-update files appear
     */
    private function parentDecommissioning(array $numbers, array $mls, array $indexings, array $fileNumberRows): array
    {
        $parcel = [];
        foreach ($numbers as $number) {
            $k = $this->key($number);
            $m = $this->firstActive($mls[$k] ?? [], 'is_deleted');
            if ($m && $this->isParcelUpdate($m)) {
                $parcel[$k] = $number;
            }
        }
        if (!$parcel) {
            return [];
        }

        // Parents as recorded at commissioning (related_fileno is a JSON list).
        $parentsOf = [];
        foreach ($parcel as $k => $number) {
            $parents = [];
            foreach (array_merge($indexings[$k] ?? [], $fileNumberRows[$k] ?? []) as $row) {
                foreach ($this->decodeList($row->related_fileno ?? null) as $p) {
                    if ($this->key($p) !== $k) {
                        $parents[$this->key($p)] = $p;
                    }
                }
            }
            $parentsOf[$k] = $parents;
        }

        $db = DB::connection($this->connection);
        $rows = [];
        $allParents = [];
        foreach ($parentsOf as $parents) {
            $allParents += $parents;
        }
        $allParents = array_values($allParents);
        foreach (array_chunk($allParents, 1000) as $chunk) {
            foreach ($db->table('decommissioned_files')->whereIn('file_no', $chunk)
                ->get(['id', 'file_no', 'successor_file_no', 'decommissioning_reason', 'false_decommissioning']) as $r) {
                $rows[$r->id] = $r;
            }
        }

        // Files whose parent is unknown (or not among the rows above) — look them up
        // by successor directly, as long as the list is small enough to do one by one.
        $resolvedBySuccessor = $this->indexBySuccessor($rows);
        $unresolved = array_filter($parcel, fn ($n, $k) => empty($resolvedBySuccessor[$k]), ARRAY_FILTER_USE_BOTH);
        if ($unresolved && count($unresolved) <= self::SUCCESSOR_SCAN_LIMIT) {
            foreach ($unresolved as $number) {
                foreach ($db->table('decommissioned_files')
                    ->whereRaw('CHARINDEX(?, successor_file_no) > 0', [$number])
                    ->get(['id', 'file_no', 'successor_file_no', 'decommissioning_reason', 'false_decommissioning']) as $r) {
                    $rows[$r->id] = $r;
                }
            }
            $resolvedBySuccessor = $this->indexBySuccessor($rows);
        }

        $out = [];
        foreach ($parcel as $k => $number) {
            $found = $resolvedBySuccessor[$k] ?? [];
            $retiredParents = [];
            foreach ($found as $r) {
                $retiredParents[$this->key($r->file_no)] = trim((string) $r->file_no);
            }
            // A parent retired without a successor list still counts if it is a recorded parent.
            foreach ($rows as $r) {
                $pk = $this->key($r->file_no);
                if (isset($parentsOf[$k][$pk]) && trim((string) $r->successor_file_no) === '' && !$this->isFalse($r)) {
                    $retiredParents[$pk] = trim((string) $r->file_no);
                }
            }

            $notRetired = array_diff_key($parentsOf[$k], $retiredParents);

            if ($retiredParents && !$notRetired) {
                $out[$k] = $this->ok('Retired: ' . implode(', ', $retiredParents));
            } elseif ($retiredParents) {
                $out[$k] = $this->missing('Retired: ' . implode(', ', $retiredParents)
                    . ' · not retired: ' . implode(', ', $notRetired));
            } elseif ($parentsOf[$k]) {
                $out[$k] = $this->missing('Parent ' . implode(', ', $parentsOf[$k]) . ' was not decommissioned');
            } elseif (count($parcel) > self::SUCCESSOR_SCAN_LIMIT) {
                $out[$k] = $this->warn('No parent recorded on the file; not searched (large batch)');
            } else {
                $out[$k] = $this->missing('No decommissioned parent file names this file as its successor');
            }
        }

        return $out;
    }

    private function isParcelUpdate(object $mls): bool
    {
        $option = strtolower(trim((string) ($mls->file_option ?? '')));
        $source = strtoupper(trim((string) ($mls->source ?? '')));

        return in_array($option, self::PARCEL_OPTIONS, true) || in_array($source, self::PARCEL_SOURCES, true);
    }

    /** @return array<string,array<int,object>> successor key => live decommissioned rows */
    private function indexBySuccessor(array $rows): array
    {
        $map = [];
        foreach ($rows as $r) {
            if ($this->isFalse($r)) {
                continue;
            }
            foreach (explode(',', (string) $r->successor_file_no) as $s) {
                $s = trim($s);
                if ($s !== '') {
                    $map[$this->key($s)][] = $r;
                }
            }
        }
        return $map;
    }

    private function isRetired(array $fileNumberRows): bool
    {
        $active = $this->firstActive($fileNumberRows, 'is_deleted');
        return $active && (int) ($active->is_decommissioned ?? 0) === 1;
    }

    private function isFalse(object $row): bool
    {
        return (int) ($row->false_decommissioning ?? 0) === 1;
    }

    // ---- data access ----------------------------------------------------------

    /** @return array<string,array<int,object>> */
    private function keyed(string $table, string $column, array $numbers, array $columns): array
    {
        $out = [];
        $db = DB::connection($this->connection);
        // SQL Server caps a statement at 2,100 parameters.
        foreach (array_chunk($numbers, 1000) as $chunk) {
            foreach ($db->table($table)->whereIn($column, $chunk)->orderBy('id')->get($columns) as $row) {
                $out[$this->key($row->{$column})][] = $row;
            }
        }
        return $out;
    }

    /**
     * fileNumber carries the same number in different columns (see CLAUDE.md), so
     * match all three and key the row under whichever wanted number it holds.
     *
     * @return array<string,array<int,object>>
     */
    private function fileNumberRows(array $numbers): array
    {
        $wanted = array_flip(array_map(fn ($n) => $this->key($n), $numbers));
        $out = [];
        $db = DB::connection($this->connection);
        foreach (array_chunk($numbers, 600) as $chunk) {
            $rows = $db->table('fileNumber')
                ->where(function ($q) use ($chunk) {
                    $q->whereIn('mlsfNo', $chunk)
                        ->orWhereIn('kangisFileNo', $chunk)
                        ->orWhereIn('NewKANGISFileNo', $chunk);
                })
                ->orderBy('id')
                ->get(['id', 'mlsfNo', 'kangisFileNo', 'NewKANGISFileNo', 'is_deleted', 'is_decommissioned', 'successor_file_no', 'related_fileno']);

            foreach ($rows as $row) {
                $seen = [];
                foreach (['mlsfNo', 'kangisFileNo', 'NewKANGISFileNo'] as $col) {
                    $k = $this->key($row->{$col} ?? '');
                    if ($k !== '' && isset($wanted[$k]) && !isset($seen[$k])) {
                        $out[$k][] = $row;
                        $seen[$k] = true;
                    }
                }
            }
        }
        return $out;
    }

    // ---- helpers -----------------------------------------------------------------

    private function summarise(array $files): array
    {
        $summary = [];
        foreach (self::ITEMS as $key => $label) {
            $counts = [self::OK => 0, self::MISSING => 0, self::WARN => 0, self::NA => 0];
            $missingFiles = [];
            foreach ($files as $f) {
                $status = $f['items'][$key]['status'];
                $counts[$status]++;
                if ($status === self::MISSING || $status === self::WARN) {
                    $missingFiles[] = $f['file_number'];
                }
            }
            $summary[] = [
                'key'      => $key,
                'label'    => $label,
                'counts'   => $counts,
                'status'   => $counts[self::MISSING] ? self::MISSING : ($counts[self::WARN] ? self::WARN : ($counts[self::OK] ? self::OK : self::NA)),
                // Enough to act on without flooding the card for a 1,500-file subdivision.
                'files'    => array_slice($missingFiles, 0, 50),
                'more'     => max(0, count($missingFiles) - 50),
            ];
        }
        return $summary;
    }

    private function firstActive(array $rows, string $flag): ?object
    {
        foreach ($rows as $r) {
            if (!isset($r->{$flag}) || $r->{$flag} === null || (int) $r->{$flag} === 0) {
                return $r;
            }
        }
        return null;
    }

    private function presence(array $rows, callable $describe, string $missing = 'No row'): array
    {
        return $rows ? $this->ok($describe($rows[0])) : $this->missing($missing);
    }

    /** @return array<int,string> */
    private function decodeList($value): array
    {
        $value = trim((string) $value);
        if ($value === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        $list = is_array($decoded) ? $decoded : explode(',', $value);
        $out = [];
        array_walk_recursive($list, function ($v) use (&$out) {
            $v = trim((string) $v);
            if ($v !== '') {
                $out[] = $v;
            }
        });
        return $out;
    }

    private function shortList($csv, int $show = 3): string
    {
        $items = array_values(array_filter(array_map('trim', explode(',', (string) $csv))));
        return count($items) > $show
            ? implode(', ', array_slice($items, 0, $show)) . ' … +' . (count($items) - $show) . ' more'
            : implode(', ', $items);
    }

    private function key($number): string
    {
        return strtoupper(trim((string) $number));
    }

    private function ok(string $detail = ''): array { return ['status' => self::OK, 'detail' => $detail]; }
    private function missing(string $detail): array { return ['status' => self::MISSING, 'detail' => $detail]; }
    private function warn(string $detail): array { return ['status' => self::WARN, 'detail' => $detail]; }
    private function na(string $detail): array { return ['status' => self::NA, 'detail' => $detail]; }
}
