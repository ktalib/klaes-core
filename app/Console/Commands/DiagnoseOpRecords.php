<?php

namespace App\Console\Commands;

use App\Http\Controllers\LandsOneStopShop\OpResettlementApplicationController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * READ-ONLY diagnostic dump for the "Occupancy Permit (OP) Details" card.
 *
 * Nothing is written to the database. The command collects, for each requested
 * OP record, both sides of the picture:
 *
 *  - what the UI actually received (the exact payload of the
 *    /lands-one-stop-shop/applications/op-resettlement/pra-transactions endpoint,
 *    produced by calling the real controller method), and
 *  - the raw underlying rows (SELECT *) from pra, instrument_capture,
 *    mls_file_no and PropID_Master, including soft-deleted rows,
 *
 * plus a list of automatically detected anomalies (blank Party 1, placeholder
 * registration numbers, duplicated Transfer of Title rows, temp file numbers that
 * disagree between the OP and its ToT, unlinked ToT rows, and so on).
 *
 * The result is written as one JSON file that can be handed back for analysis
 * before any repair is designed.
 *
 * Usage on production:
 *   php artisan oss:diagnose-op-records TEMP-121469 TEMP-121605 TEMP-121617
 *   php artisan oss:diagnose-op-records RES-2026-2274 --scan
 *   php artisan oss:diagnose-op-records --input=targets.txt --out=/tmp/op.json
 *
 * A target may be a temp file no, an MLS file no, a plain fileno, or a prop_id.
 */
class DiagnoseOpRecords extends Command
{
    protected $signature = 'oss:diagnose-op-records
        {targets?* : Temp file no, MLS file no, fileno, or prop_id (repeatable)}
        {--input= : Path to a text/CSV file with one target per line}
        {--scan : Also run a database-wide anomaly scan over the OP/ToT universe}
        {--scan-limit=100 : Max sample rows reported per anomaly bucket in --scan}
        {--out= : Output JSON path (default storage/app/diagnostics/op-diagnostics-<timestamp>.json)}';

    protected $description = 'READ-ONLY: dump OP/Transfer-of-Title records (endpoint payload + raw rows + anomalies) to JSON for analysis';

    /** @var \Illuminate\Database\Connection */
    private $db;

    /** @var array<string, array<int, string>> */
    private array $columnCache = [];

    public function handle(): int
    {
        $this->db = DB::connection('sqlsrv');

        $targets = $this->collectTargets();
        $runScan = (bool) $this->option('scan');

        if (empty($targets) && ! $runScan) {
            $this->error('Nothing to do — pass at least one target, --input=<file>, or --scan.');
            return self::FAILURE;
        }

        $report = [
            'generated_at' => now()->toIso8601String(),
            'command' => 'oss:diagnose-op-records',
            'read_only' => true,
            'app_env' => config('app.env'),
            'connection' => $this->db->getName(),
            'database' => $this->db->getDatabaseName(),
            'schema' => [
                'pra' => $this->columns('pra'),
                'instrument_capture' => $this->columns('instrument_capture'),
            ],
            'targets' => $targets,
            'cases' => [],
        ];

        foreach ($targets as $target) {
            $this->line("Collecting {$target} ...");
            $report['cases'][] = $this->buildCase($target);
        }

        if ($runScan) {
            $this->line('Running database-wide anomaly scan ...');
            $report['scan'] = $this->buildScan((int) $this->option('scan-limit'));
        }

        $path = $this->resolveOutPath();
        @mkdir(dirname($path), 0775, true);
        file_put_contents(
            $path,
            json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)
        );

        $this->newLine();
        $this->info('Diagnostics written to: ' . $path);
        $this->line('Size: ' . number_format(filesize($path) / 1024, 1) . ' KB');
        $this->line('No database rows were modified.');

        foreach ($report['cases'] as $case) {
            $flags = $case['anomalies'] ?? [];
            $label = $case['input'] . ' (prop_id ' . ($case['resolved_prop_id'] ?? '—') . ')';
            if (empty($flags)) {
                $this->line("  OK   {$label}");
            } else {
                $this->warn('  FLAG ' . $label . ' — ' . implode(', ', array_column($flags, 'code')));
            }
        }

        return self::SUCCESS;
    }

    // ---------------------------------------------------------------- targets

    /** @return array<int, string> */
    private function collectTargets(): array
    {
        $targets = array_map('trim', (array) $this->argument('targets'));

        if ($input = $this->option('input')) {
            if (! is_file($input)) {
                $this->error("Input file not found: {$input}");
                return [];
            }
            foreach (preg_split('/[\r\n,;]+/', (string) file_get_contents($input)) as $line) {
                $targets[] = trim($line);
            }
        }

        return array_values(array_unique(array_filter($targets, fn ($t) => $t !== '')));
    }

    // ------------------------------------------------------------------- case

    private function buildCase(string $target): array
    {
        $isPropId = ctype_digit($target);

        $case = [
            'input' => $target,
            'input_looks_like' => $isPropId ? 'prop_id' : 'file_number',
            'resolution' => [],
            'resolved_prop_id' => null,
        ];

        // ---- resolve prop_id / file numbers from every angle we know of
        $propId = $isPropId ? $target : null;
        $matches = $this->db->table('pra')
            ->where(function ($q) use ($target, $isPropId) {
                $q->where('temp_fileno', $target)
                    ->orWhere('mlsFNo', $target)
                    ->orWhere('fileno', $target)
                    ->orWhere('resolved_fileno', $target);
                if ($isPropId) {
                    $q->orWhere('prop_id', $target);
                }
            })
            ->orderBy('id')
            ->get();

        $case['resolution']['pra_direct_match_ids'] = $matches->pluck('id')->all();
        $case['resolution']['pra_direct_match_prop_ids'] = $matches->pluck('prop_id')->unique()->values()->all();

        if (! $propId) {
            $propId = $matches->pluck('prop_id')->filter()->first();
        }
        if (! $propId) {
            $icMatch = $this->db->table('instrument_capture')
                ->where(function ($q) use ($target) {
                    $q->where('temp_fileno', $target)
                        ->orWhere('mlsFNo', $target)
                        ->orWhere('kangisFileNo', $target)
                        ->orWhere('NewKANGISFileno', $target);
                })
                ->orderBy('id')
                ->first();
            $propId = $icMatch->prop_id ?? null;
            $case['resolution']['instrument_capture_fallback_id'] = $icMatch->id ?? null;
        }

        $case['resolved_prop_id'] = $propId;

        $parentPropId = $propId
            ? $this->db->table('pra')->where('prop_id', $propId)->whereNotNull('parent_prop_id')->value('parent_prop_id')
            : null;
        $case['resolution']['parent_prop_id'] = $parentPropId;

        // File numbers touching this case, from every matched row.
        $fileNos = collect($matches)
            ->flatMap(fn ($r) => [$r->temp_fileno ?? null, $r->mlsFNo ?? null, $r->fileno ?? null])
            ->push($target)
            ->map(fn ($v) => trim((string) $v))
            ->filter()
            ->unique()
            ->values();
        $case['resolution']['file_numbers_in_play'] = $fileNos->all();

        // ---- exactly what the UI endpoint returned for this record
        $case['endpoint_payload'] = $this->endpointPayload($propId, $fileNos, $target, $isPropId);

        // ---- raw rows (everything, including soft-deleted)
        $case['raw'] = [
            'pra' => $this->rawPraRows($propId, $parentPropId, $fileNos),
            'instrument_capture' => $this->rawIcRows($propId, $parentPropId, $fileNos),
            'deed_registrations' => $this->rawSimpleRows('deed_registrations', $propId, $fileNos, ['MLSFileNo', 'fileno', 'mlsFNo', 'temp_fileno']),
            'oss_applications' => $this->rawSimpleRows('oss_applications', null, $fileNos, ['file_no', 'temp_fileno']),
            'mls_file_no' => $this->rawMlsFileNoRows($fileNos),
            'PropID_Master' => $this->rawPropIdMasterRows($propId, $parentPropId),
        ];

        // Root Cause #1 of docs/reports/op_tot_mismatch_analysis.md: one temp_fileno
        // pointing at two properties makes the OP lookup pick whichever row was
        // inserted last. Report every other property sharing these temp file numbers.
        $case['temp_fileno_collisions'] = $this->tempFilenoCollisions($fileNos);

        // Batch cohort: matchTotBatchToOps() pairs a batch's OPs to its ToTs strictly
        // by ordinal position, so a wrong pairing is only visible next to its siblings.
        $case['op_batch_cohort'] = $this->opBatchCohort($case['raw']['pra']);

        $case['anomalies'] = $this->detectAnomalies($case);

        return $case;
    }

    /**
     * Call the real controller action so the payload is byte-for-byte what the
     * browser received when the OP Details card was opened.
     */
    private function endpointPayload(?string $propId, $fileNos, string $target, bool $isPropId): array
    {
        $params = [];
        if ($propId) {
            $params['prop_id'] = $propId;
        }
        if (! $isPropId) {
            // Mirror the front-end: it sends both labels off the table row.
            $temp = $fileNos->first(fn ($f) => stripos($f, 'TEMP') === 0) ?: $target;
            $mls = $fileNos->first(fn ($f) => stripos($f, 'TEMP') !== 0);
            $params['temp_fileno'] = $temp;
            if ($mls) {
                $params['mls_fileno'] = $mls;
            }
        }

        try {
            $controller = new OpResettlementApplicationController();
            $response = $controller->praTransactions(new Request($params));
            $decoded = json_decode($response->getContent(), true);

            return [
                'request_params' => $params,
                'status' => $response->getStatusCode(),
                'response' => $decoded,
                'transaction_count' => is_array($decoded['data'] ?? null) ? count($decoded['data']) : 0,
            ];
        } catch (\Throwable $e) {
            return [
                'request_params' => $params,
                'error' => $e->getMessage(),
                'error_at' => basename($e->getFile()) . ':' . $e->getLine(),
            ];
        }
    }

    // -------------------------------------------------------------- raw dumps

    private function rawPraRows(?string $propId, ?string $parentPropId, $fileNos): array
    {
        $rows = $this->db->table('pra')
            ->where(function ($q) use ($propId, $parentPropId, $fileNos) {
                if ($propId) {
                    $q->orWhere('prop_id', $propId)->orWhere('parent_prop_id', $propId);
                }
                if ($parentPropId) {
                    $q->orWhere('prop_id', $parentPropId)->orWhere('parent_prop_id', $parentPropId);
                }
                foreach ($fileNos as $f) {
                    $q->orWhere('temp_fileno', $f)->orWhere('mlsFNo', $f)->orWhere('fileno', $f);
                }
            })
            ->orderBy('id')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->values()
            ->all();

        // Follow OP<->ToT links in both directions so a row whose prop_id drifted
        // is still in the dump.
        $ids = array_column($rows, 'id');
        $linkedIds = [];
        if ($ids) {
            $linkedIds = array_merge(
                $this->db->table('pra')->whereIn('id', $ids)->whereNotNull('source_op_id')
                    ->where('source_op_table', 'pra')->pluck('source_op_id')->all(),
                $this->db->table('pra')->where('source_op_table', 'pra')
                    ->whereIn('source_op_id', $ids)->pluck('id')->all()
            );
            $linkedIds = array_values(array_diff(array_unique($linkedIds), $ids));
        }
        if ($linkedIds) {
            $extra = $this->db->table('pra')->whereIn('id', $linkedIds)->orderBy('id')->get()
                ->map(function ($r) {
                    $a = (array) $r;
                    $a['_pulled_in_via'] = 'source_op_id link';
                    return $a;
                })->all();
            $rows = array_merge($rows, $extra);
        }

        return $rows;
    }

    private function rawIcRows(?string $propId, ?string $parentPropId, $fileNos): array
    {
        return $this->db->table('instrument_capture')
            ->where(function ($q) use ($propId, $parentPropId, $fileNos) {
                if ($propId) {
                    $q->orWhere('prop_id', $propId);
                }
                if ($parentPropId) {
                    $q->orWhere('prop_id', $parentPropId);
                }
                foreach ($fileNos as $f) {
                    $q->orWhere('temp_fileno', $f)
                        ->orWhere('mlsFNo', $f)
                        ->orWhere('kangisFileNo', $f)
                        ->orWhere('NewKANGISFileno', $f);
                }
            })
            ->orderBy('id')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->values()
            ->all();
    }

    private function rawMlsFileNoRows($fileNos): array
    {
        if (! Schema::connection('sqlsrv')->hasTable('mls_file_no') || $fileNos->isEmpty()) {
            return [];
        }

        $cols = $this->columns('mls_file_no');

        return $this->db->table('mls_file_no')
            ->where(function ($q) use ($fileNos, $cols) {
                foreach ($fileNos as $f) {
                    foreach (['full_file_number', 'file_number', 'temp_fileno', 'mlsFNo'] as $col) {
                        if (in_array($col, $cols, true)) {
                            $q->orWhere($col, $f);
                        }
                    }
                }
            })
            ->get()
            ->map(fn ($r) => (array) $r)
            ->values()
            ->all();
    }

    /**
     * Generic dump for a side table, matched on whichever of the candidate columns exist.
     */
    private function rawSimpleRows(string $table, ?string $propId, $fileNos, array $fileColumns): array
    {
        if (! Schema::connection('sqlsrv')->hasTable($table)) {
            return [];
        }

        $cols = $this->columns($table);
        $usable = array_values(array_intersect($fileColumns, $cols));
        $hasPropId = in_array('prop_id', $cols, true);

        if (! $usable && ! ($hasPropId && $propId)) {
            return [];
        }

        return $this->db->table($table)
            ->where(function ($q) use ($propId, $fileNos, $usable, $hasPropId) {
                if ($hasPropId && $propId) {
                    $q->orWhere('prop_id', $propId);
                }
                foreach ($fileNos as $f) {
                    foreach ($usable as $col) {
                        $q->orWhere($col, $f);
                    }
                }
            })
            ->orderBy('id')
            ->limit(50)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->values()
            ->all();
    }

    /**
     * Other properties sharing this record's temp file numbers — the documented
     * primary root cause of OP/ToT cross-linking.
     */
    private function tempFilenoCollisions($fileNos): array
    {
        $temps = $fileNos->filter(fn ($f) => stripos($f, 'TEMP') === 0)->values();
        if ($temps->isEmpty()) {
            return [];
        }

        $out = [];
        foreach ($temps as $temp) {
            $pra = $this->db->table('pra')->where('temp_fileno', $temp)
                ->orderBy('id')
                ->get(['id', 'prop_id', 'mlsFNo', 'fileno', 'instrument_type', 'op_type', 'op_serial_number', 'plot_no', 'Grantee', 'party_2', 'is_deleted'])
                ->map(fn ($r) => (array) $r)->all();
            $ic = $this->db->table('instrument_capture')->where('temp_fileno', $temp)
                ->orderBy('id')
                ->get(['id', 'prop_id', 'mlsFNo', 'instrument_type', 'op_type', 'op_serial_number', 'plot_number', 'party_1_name', 'party_2_name'])
                ->map(fn ($r) => (array) $r)->all();

            $propIds = array_values(array_unique(array_filter(array_merge(
                array_column($pra, 'prop_id'),
                array_column($ic, 'prop_id')
            ))));

            $out[$temp] = [
                'distinct_prop_ids' => $propIds,
                'is_collision' => count($propIds) > 1,
                'pra_rows' => $pra,
                'instrument_capture_rows' => $ic,
            ];
        }

        return $out;
    }

    /**
     * Every row of the same op_batch, slimmed to the fields the ordinal pairing in
     * matchTotBatchToOps() relies on, so a shifted pairing is visible at a glance.
     */
    private function opBatchCohort(array $praRows): array
    {
        $batches = array_values(array_unique(array_filter(array_column($praRows, 'op_batch'))));
        if (! $batches) {
            return [];
        }

        $out = [];
        foreach ($batches as $batch) {
            $out[$batch] = $this->db->table('pra')
                ->where('op_batch', $batch)
                ->orderBy('id')
                ->get([
                    'id', 'prop_id', 'parent_prop_id', 'temp_fileno', 'mlsFNo', 'fileno',
                    'instrument_type', 'op_type', 'op_serial_number', 'transaction_date',
                    'Grantor', 'Grantee', 'party_1', 'party_2', 'plot_no', 'tp_no',
                    'location', 'source_op_id', 'source_op_table', 'is_deleted',
                ])
                ->map(fn ($r) => (array) $r)
                ->values()
                ->all();
        }

        return $out;
    }

    private function rawPropIdMasterRows(?string $propId, ?string $parentPropId): array
    {
        if (! Schema::connection('sqlsrv')->hasTable('PropID_Master') || (! $propId && ! $parentPropId)) {
            return [];
        }

        return $this->db->table('PropID_Master')
            ->where(function ($q) use ($propId, $parentPropId) {
                if ($propId) {
                    $q->orWhere('prop_id', $propId);
                }
                if ($parentPropId) {
                    $q->orWhere('prop_id', $parentPropId);
                }
            })
            ->get()
            ->map(fn ($r) => (array) $r)
            ->values()
            ->all();
    }

    // ------------------------------------------------------------- anomalies

    private function detectAnomalies(array $case): array
    {
        $flags = [];
        $add = function (string $code, string $detail, $context = null) use (&$flags) {
            $flags[] = array_filter([
                'code' => $code,
                'detail' => $detail,
                'context' => $context,
            ], fn ($v) => $v !== null);
        };

        $live = array_values(array_filter(
            $case['raw']['pra'] ?? [],
            fn ($r) => empty($r['is_deleted'])
        ));
        $isOp = fn ($r) => stripos((string) ($r['instrument_type'] ?? ''), 'Occupancy Permit') !== false;
        $isTot = fn ($r) => stripos((string) ($r['instrument_type'] ?? ''), 'Transfer of Title') !== false;

        $ops = array_values(array_filter($live, $isOp));
        $tots = array_values(array_filter($live, $isTot));
        $icOps = $case['raw']['instrument_capture'] ?? [];

        if (! $ops && ! $icOps) {
            $add('no_op_row', 'No Occupancy Permit row exists in pra or instrument_capture for this property.');
        }
        if (count($tots) > 1) {
            $add('duplicate_tot', 'More than one live Transfer of Title row for this property.', [
                'ids' => array_column($tots, 'id'),
                'temp_filenos' => array_values(array_unique(array_column($tots, 'temp_fileno'))),
                'party_2' => array_values(array_unique(array_map(
                    fn ($r) => $this->party($r, 2),
                    $tots
                ))),
            ]);
        }

        foreach ($live as $row) {
            $label = ($row['instrument_type'] ?? 'row') . ' #' . ($row['id'] ?? '?');

            if ($this->party($row, 1) === '') {
                $add('blank_party_1', "Party 1 is empty on {$label}.", ['id' => $row['id'] ?? null]);
            }
            if ($this->party($row, 2) === '') {
                $add('blank_party_2', "Party 2 is empty on {$label}.", ['id' => $row['id'] ?? null]);
            }
            if ($this->isPlaceholderRegNo($row)) {
                $add('placeholder_registration_no', "Registration number is a placeholder on {$label}.", [
                    'id' => $row['id'] ?? null,
                    'regNo' => $row['regNo'] ?? null,
                    'serial_page_volume' => [$row['serialNo'] ?? null, $row['pageNo'] ?? null, $row['volumeNo'] ?? null],
                ]);
            }
            if ($isTot($row) && empty($row['source_op_id'])) {
                $add('tot_not_linked_to_op', "Transfer of Title {$label} has no source_op_id link.", ['id' => $row['id'] ?? null]);
            }
            if (stripos($this->party($row, 1), 'kano state government') !== false && $isTot($row)) {
                $add('tot_grantor_is_government', "Transfer of Title {$label} still carries the government as Party 1.", ['id' => $row['id'] ?? null]);
            }
            foreach ([1, 2] as $which) {
                if (strcasecmp($this->party($row, $which), 'unknown') === 0) {
                    $add('party_is_unknown', "Party {$which} is the literal 'Unknown' on {$label}.", ['id' => $row['id'] ?? null]);
                }
            }
        }

        foreach ($case['temp_fileno_collisions'] ?? [] as $temp => $info) {
            if (! empty($info['is_collision'])) {
                $add('temp_fileno_collision', "temp_fileno {$temp} is shared by more than one property.", [
                    'temp_fileno' => $temp,
                    'prop_ids' => $info['distinct_prop_ids'],
                ]);
            }
        }

        // Cross-row consistency between the OP and its ToT(s).
        $opRow = $ops[0] ?? null;
        foreach ($tots as $tot) {
            if ($opRow) {
                if (trim((string) ($tot['temp_fileno'] ?? '')) !== trim((string) ($opRow['temp_fileno'] ?? ''))) {
                    $add('tot_temp_fileno_mismatch', 'Transfer of Title temp_fileno differs from the OP temp_fileno.', [
                        'tot_id' => $tot['id'] ?? null,
                        'tot_temp_fileno' => $tot['temp_fileno'] ?? null,
                        'op_id' => $opRow['id'] ?? null,
                        'op_temp_fileno' => $opRow['temp_fileno'] ?? null,
                    ]);
                }
                if ($this->party($tot, 1) !== '' && $this->party($opRow, 2) !== ''
                    && $this->norm($this->party($tot, 1)) !== $this->norm($this->party($opRow, 2))) {
                    $add('tot_party_1_not_op_allottee', 'Transfer of Title Party 1 is not the OP allottee.', [
                        'tot_party_1' => $this->party($tot, 1),
                        'op_party_2' => $this->party($opRow, 2),
                    ]);
                }
                if (($tot['transaction_date'] ?? null) && ($opRow['transaction_date'] ?? null)
                    && (string) $tot['transaction_date'] === (string) $opRow['transaction_date']) {
                    $add('tot_date_equals_op_date', 'Transfer of Title carries the same transaction_date as the OP (batch-stamped).', [
                        'date' => (string) $tot['transaction_date'],
                    ]);
                }
                // Property-identity fields that must agree — a disagreement means the
                // ToT is hanging off the wrong OP (the layout/plot recycling problem).
                foreach (['plot_no', 'tp_no', 'land_use', 'lgsaOrCity', 'location', 'op_serial_number'] as $field) {
                    $opValue = trim((string) ($opRow[$field] ?? ''));
                    $totValue = trim((string) ($tot[$field] ?? ''));
                    if ($opValue !== '' && $totValue !== '' && $this->norm($opValue) !== $this->norm($totValue)) {
                        $add($field . '_mismatch', "{$field} differs between the OP and its Transfer of Title.", [
                            'op_id' => $opRow['id'] ?? null,
                            'tot_id' => $tot['id'] ?? null,
                            'op_value' => $opValue,
                            'tot_value' => $totValue,
                        ]);
                    }
                }
            }
        }

        // prop_id drift across rows the card groups together.
        $propIds = array_values(array_unique(array_filter(array_column($live, 'prop_id'))));
        if (count($propIds) > 1) {
            $add('prop_id_divergence', 'Rows grouped in this card carry different prop_id values.', ['prop_ids' => $propIds]);
        }

        // Batch cohort health — ordinal pairing only works when the two sides balance.
        foreach ($case['op_batch_cohort'] ?? [] as $batch => $cohort) {
            $liveCohort = array_values(array_filter($cohort, fn ($r) => empty($r['is_deleted'])));
            $batchOps = count(array_filter($liveCohort, $isOp));
            $batchTots = count(array_filter($liveCohort, $isTot));
            if ($batchOps !== $batchTots) {
                $add('op_batch_count_imbalance', "Batch {$batch} has {$batchOps} OP row(s) against {$batchTots} Transfer of Title row(s); the 1:1 ordinal pairing cannot be trusted.", [
                    'op_batch' => $batch,
                    'op_rows' => $batchOps,
                    'tot_rows' => $batchTots,
                ]);
            }

            $buyers = array_map(fn ($r) => $this->norm($this->party($r, 2)), array_filter($liveCohort, $isTot));
            $buyers = array_filter($buyers);
            $repeated = array_values(array_unique(array_diff_assoc($buyers, array_unique($buyers))));
            if ($repeated) {
                $add('tot_buyer_repeated_in_batch', "The same Party 2 appears on more than one Transfer of Title in batch {$batch}.", [
                    'op_batch' => $batch,
                    'repeated_party_2' => $repeated,
                ]);
            }
        }

        $deleted = array_values(array_filter($case['raw']['pra'] ?? [], fn ($r) => ! empty($r['is_deleted'])));
        if ($deleted) {
            $add('soft_deleted_rows_present', 'Soft-deleted pra rows exist for this property (hidden from the card).', [
                'ids' => array_column($deleted, 'id'),
            ]);
        }

        // Payload vs raw: did the card show a row the raw dump does not explain?
        $payloadCount = $case['endpoint_payload']['transaction_count'] ?? null;
        if ($payloadCount !== null && $payloadCount !== count($live) + count($icOps)) {
            $add('payload_row_count_mismatch', 'Endpoint returned a different number of rows than the live raw rows.', [
                'endpoint_rows' => $payloadCount,
                'live_pra_rows' => count($live),
                'instrument_capture_rows' => count($icOps),
            ]);
        }

        return $flags;
    }

    /** Mirror of the controller's party resolution, so flags match what the card shows. */
    private function party(array $row, int $which): string
    {
        $candidates = $which === 1
            ? ['Grantor', 'Assignor', 'Mortgagor', 'party_1', 'party_1_name']
            : ['Grantee', 'Assignee', 'Mortgagee', 'party_2', 'party_2_name'];

        foreach ($candidates as $col) {
            $value = trim((string) ($row[$col] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function norm(string $value): string
    {
        return strtolower(preg_replace('/\s+/', ' ', trim($value)));
    }

    private function isPlaceholderRegNo(array $row): bool
    {
        $reg = trim((string) ($row['regNo'] ?? ''));
        if ($reg === '') {
            $parts = array_filter([
                trim((string) ($row['serialNo'] ?? '')),
                trim((string) ($row['pageNo'] ?? '')),
                trim((string) ($row['volumeNo'] ?? '')),
            ], fn ($v) => $v !== '');
            $reg = $parts ? implode('/', $parts) : '';
        }

        return in_array(preg_replace('/\s+/', '', $reg), ['0/0/0', '0\0\0', '0', '//', '\\\\'], true);
    }

    // ------------------------------------------------------------------ scan

    /**
     * Database-wide anomaly buckets, so the size of each defect is known before a
     * repair is written. Counts are exact; sample rows are capped.
     */
    private function buildScan(int $limit): array
    {
        $limit = max(1, min($limit, 1000));
        $scan = ['sample_limit' => $limit];

        $opUniverse = "(instrument_type LIKE '%Occupancy Permit%' OR instrument_type LIKE '%Transfer of Title%')
                       AND (is_deleted IS NULL OR is_deleted = 0)";

        $buckets = [
            'tot_blank_party_1' => "
                SELECT id, prop_id, temp_fileno, mlsFNo, fileno, instrument_type, op_type, op_batch,
                       transaction_date, Grantor, party_1, Grantee, party_2, source_op_id, source_op_table
                FROM pra
                WHERE instrument_type LIKE '%Transfer of Title%'
                  AND (is_deleted IS NULL OR is_deleted = 0)
                  AND (Grantor IS NULL OR LTRIM(RTRIM(Grantor)) = '')
                  AND (Assignor IS NULL OR LTRIM(RTRIM(Assignor)) = '')
                  AND (party_1 IS NULL OR LTRIM(RTRIM(party_1)) = '')",

            'tot_grantor_is_government' => "
                SELECT id, prop_id, temp_fileno, mlsFNo, instrument_type, op_batch, Grantor, party_1, party_2
                FROM pra
                WHERE instrument_type LIKE '%Transfer of Title%'
                  AND (is_deleted IS NULL OR is_deleted = 0)
                  AND UPPER(LTRIM(RTRIM(ISNULL(Grantor, party_1)))) = 'KANO STATE GOVERNMENT'",

            'placeholder_registration_no' => "
                SELECT id, prop_id, temp_fileno, mlsFNo, instrument_type, op_batch, regNo, serialNo, pageNo, volumeNo
                FROM pra
                WHERE {$opUniverse}
                  AND (
                        REPLACE(ISNULL(regNo,''), ' ', '') IN ('0/0/0','0\\0\\0','0')
                     OR (ISNULL(regNo,'') = '' AND ISNULL(serialNo,'0') = '0' AND ISNULL(pageNo,'0') = '0' AND ISNULL(volumeNo,'0') = '0')
                  )",

            'tot_not_linked_to_op' => "
                SELECT id, prop_id, temp_fileno, mlsFNo, instrument_type, op_batch, transaction_date
                FROM pra
                WHERE instrument_type LIKE '%Transfer of Title%'
                  AND (is_deleted IS NULL OR is_deleted = 0)
                  AND source_op_id IS NULL",

            // Duplicate ToTs hide from the prop_id bucket when the copies were written
            // under different prop_ids, which is exactly how they present in the card.
            'duplicate_tot_for_same_file_number' => "
                SELECT COALESCE(NULLIF(mlsFNo,''), fileno) AS file_no,
                       COUNT(*) AS tot_count,
                       COUNT(DISTINCT prop_id) AS distinct_prop_ids,
                       MIN(id) AS first_id, MAX(id) AS last_id
                FROM pra
                WHERE instrument_type LIKE '%Transfer of Title%'
                  AND (is_deleted IS NULL OR is_deleted = 0)
                  AND COALESCE(NULLIF(mlsFNo,''), fileno) IS NOT NULL
                GROUP BY COALESCE(NULLIF(mlsFNo,''), fileno)
                HAVING COUNT(*) > 1",

            // A ToT that names its OP through source_op_id but sits on a different
            // prop_id: the card groups by prop_id, so the pair splits across cards.
            'linked_op_and_tot_have_different_prop_ids' => "
                SELECT t.id AS tot_id, t.prop_id AS tot_prop_id, t.mlsFNo, t.temp_fileno AS tot_temp_fileno,
                       o.id AS op_id, o.prop_id AS op_prop_id, o.temp_fileno AS op_temp_fileno
                FROM pra t
                JOIN pra o ON o.id = t.source_op_id
                WHERE t.source_op_table = 'pra'
                  AND t.instrument_type LIKE '%Transfer of Title%'
                  AND (t.is_deleted IS NULL OR t.is_deleted = 0)
                  AND ISNULL(CAST(t.prop_id AS NVARCHAR(50)),'') <> ISNULL(CAST(o.prop_id AS NVARCHAR(50)),'')",

            'prop_id_with_multiple_tots' => "
                SELECT prop_id, COUNT(*) AS tot_count, MIN(id) AS first_id, MAX(id) AS last_id
                FROM pra
                WHERE instrument_type LIKE '%Transfer of Title%'
                  AND (is_deleted IS NULL OR is_deleted = 0)
                  AND prop_id IS NOT NULL
                GROUP BY prop_id
                HAVING COUNT(*) > 1",

            'tot_temp_fileno_differs_from_op' => "
                SELECT t.id AS tot_id, t.prop_id, t.temp_fileno AS tot_temp_fileno, t.mlsFNo,
                       o.id AS op_id, o.temp_fileno AS op_temp_fileno, t.op_batch
                FROM pra t
                JOIN pra o ON o.prop_id = t.prop_id
                          AND o.instrument_type LIKE '%Occupancy Permit%'
                          AND (o.is_deleted IS NULL OR o.is_deleted = 0)
                WHERE t.instrument_type LIKE '%Transfer of Title%'
                  AND (t.is_deleted IS NULL OR t.is_deleted = 0)
                  AND ISNULL(LTRIM(RTRIM(t.temp_fileno)),'') <> ISNULL(LTRIM(RTRIM(o.temp_fileno)),'')",

            'tot_party_2_repeated_within_batch' => "
                SELECT op_batch, LTRIM(RTRIM(ISNULL(Grantee, party_2))) AS buyer, COUNT(*) AS row_count,
                       MIN(id) AS first_id, MAX(id) AS last_id
                FROM pra
                WHERE instrument_type LIKE '%Transfer of Title%'
                  AND (is_deleted IS NULL OR is_deleted = 0)
                  AND op_batch IS NOT NULL
                  AND LTRIM(RTRIM(ISNULL(Grantee, party_2))) <> ''
                GROUP BY op_batch, LTRIM(RTRIM(ISNULL(Grantee, party_2)))
                HAVING COUNT(*) > 1",

            'op_row_blank_allottee' => "
                SELECT id, prop_id, temp_fileno, mlsFNo, op_type, op_serial_number, op_batch, Grantee, party_2
                FROM pra
                WHERE instrument_type LIKE '%Occupancy Permit%'
                  AND (is_deleted IS NULL OR is_deleted = 0)
                  AND (Grantee IS NULL OR LTRIM(RTRIM(Grantee)) = '')
                  AND (Assignee IS NULL OR LTRIM(RTRIM(Assignee)) = '')
                  AND (party_2 IS NULL OR LTRIM(RTRIM(party_2)) = '')",

            'temp_fileno_shared_by_multiple_prop_ids' => "
                SELECT temp_fileno, COUNT(DISTINCT prop_id) AS prop_id_count, COUNT(*) AS row_count
                FROM (
                    SELECT temp_fileno, prop_id FROM pra
                    WHERE temp_fileno IS NOT NULL AND LTRIM(RTRIM(temp_fileno)) <> ''
                      AND prop_id IS NOT NULL AND (is_deleted IS NULL OR is_deleted = 0)
                    UNION ALL
                    SELECT temp_fileno, prop_id FROM instrument_capture
                    WHERE temp_fileno IS NOT NULL AND LTRIM(RTRIM(temp_fileno)) <> ''
                      AND prop_id IS NOT NULL AND (is_deleted IS NULL OR is_deleted = 0)
                ) u
                GROUP BY temp_fileno
                HAVING COUNT(DISTINCT prop_id) > 1",

            'party_literal_unknown' => "
                SELECT id, prop_id, temp_fileno, mlsFNo, instrument_type, op_batch, party_1, party_2, Grantor, Grantee
                FROM pra
                WHERE {$opUniverse}
                  AND (
                        UPPER(LTRIM(RTRIM(ISNULL(party_1,'')))) = 'UNKNOWN'
                     OR UPPER(LTRIM(RTRIM(ISNULL(party_2,'')))) = 'UNKNOWN'
                     OR UPPER(LTRIM(RTRIM(ISNULL(Grantor,'')))) = 'UNKNOWN'
                     OR UPPER(LTRIM(RTRIM(ISNULL(Grantee,'')))) = 'UNKNOWN'
                  )",

            'op_batch_op_vs_tot_imbalance' => "
                SELECT op_batch,
                       SUM(CASE WHEN instrument_type LIKE '%Occupancy Permit%' THEN 1 ELSE 0 END) AS op_rows,
                       SUM(CASE WHEN instrument_type LIKE '%Transfer of Title%' THEN 1 ELSE 0 END) AS tot_rows
                FROM pra
                WHERE op_batch IS NOT NULL AND {$opUniverse}
                GROUP BY op_batch
                HAVING SUM(CASE WHEN instrument_type LIKE '%Occupancy Permit%' THEN 1 ELSE 0 END)
                     <> SUM(CASE WHEN instrument_type LIKE '%Transfer of Title%' THEN 1 ELSE 0 END)",

            'tot_without_any_op_for_prop_id' => "
                SELECT t.id, t.prop_id, t.temp_fileno, t.mlsFNo, t.op_batch, t.transaction_date
                FROM pra t
                WHERE t.instrument_type LIKE '%Transfer of Title%'
                  AND (t.is_deleted IS NULL OR t.is_deleted = 0)
                  AND t.prop_id IS NOT NULL
                  AND NOT EXISTS (
                        SELECT 1 FROM pra o
                        WHERE o.prop_id = t.prop_id
                          AND o.instrument_type LIKE '%Occupancy Permit%'
                          AND (o.is_deleted IS NULL OR o.is_deleted = 0)
                  )
                  AND NOT EXISTS (
                        SELECT 1 FROM instrument_capture ic
                        WHERE ic.prop_id = t.prop_id
                          AND ic.instrument_type LIKE '%Occupancy Permit%'
                  )",
        ];

        foreach ($buckets as $name => $sql) {
            try {
                $count = $this->db->selectOne('SELECT COUNT(*) AS c FROM (' . $sql . ') x')->c ?? 0;
                $samples = $this->db->select('SELECT TOP ' . $limit . ' * FROM (' . $sql . ') x');
                $scan[$name] = [
                    'count' => (int) $count,
                    'samples' => array_map(fn ($r) => (array) $r, $samples),
                ];
                $this->line(sprintf('  %-38s %s', $name, $count));
            } catch (\Throwable $e) {
                $scan[$name] = ['error' => $e->getMessage()];
                $this->warn("  {$name}: query failed — " . $e->getMessage());
            }
        }

        return $scan;
    }

    // ----------------------------------------------------------------- helpers

    /** @return array<int, string> */
    private function columns(string $table): array
    {
        if (! isset($this->columnCache[$table])) {
            $this->columnCache[$table] = Schema::connection('sqlsrv')->hasTable($table)
                ? Schema::connection('sqlsrv')->getColumnListing($table)
                : [];
        }

        return $this->columnCache[$table];
    }

    private function resolveOutPath(): string
    {
        $out = $this->option('out');
        if ($out) {
            return $out;
        }

        return storage_path('app/diagnostics/op-diagnostics-' . now()->format('Ymd-His') . '.json');
    }
}
