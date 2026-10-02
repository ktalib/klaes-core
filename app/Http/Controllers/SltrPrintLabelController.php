<?php

namespace App\Http\Controllers;

use App\Models\FileIndexing;
use App\Models\SltrRackShelfLabel;
use App\Models\SltrPrintLabelBatch;
use App\Models\SltrPrintLabelBatchItem;
use App\Support\SltrDigitRank;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SltrPrintLabelController extends Controller
{
    const RACK_SHELF_CAPACITY = 999999;

    const PREFIX = 'SLTR';

    // -------------------------------------------------------------------------
    // Public endpoints
    // -------------------------------------------------------------------------

    public function index(Request $request)
    {
        $recentBatches = SltrPrintLabelBatch::with(['creator'])
            ->withCount('batchItems')
            ->where('status', '!=', SltrPrintLabelBatch::STATUS_PENDING)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return view('sltr_printlabel.index', compact('recentBatches'));
    }

    /**
     * Return the single SLTR prefix with a file count.
     */
    public function getPrefixes()
    {
        try {
            $count = DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where('registry', 'like', '%SLTR%')
                ->count();

            $result = [
                [
                    'prefix' => self::PREFIX,
                    'label'  => self::PREFIX,
                    'count'  => $count,
                ],
            ];

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            Log::error('sltr-printlabel.getPrefixes', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Suggest the next unprocessed sys_batch_no for SLTR.
     */
    /**
     * Return distinct sub_prefix values from sltr_fileno_subprefix.
     */
    public function getSubPrefixes()
    {
        try {
            $prefixes = DB::connection('sqlsrv')
                ->table('sltr_fileno_subprefix')
                ->whereNotNull('sub_prefix')
                ->distinct()
                ->orderBy('sub_prefix')
                ->pluck('sub_prefix');

            return response()->json(['success' => true, 'data' => $prefixes]);
        } catch (\Throwable $e) {
            Log::error('sltr-printlabel.getSubPrefixes', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Suggest the next unprocessed sub_prefix for SLTR.
     * (Deprecated: user now selects from dropdown)
     */
    public function getNextRangeForPrefix(Request $request)
    {
        try {
            $maxBatched = DB::connection('sqlsrv')
                ->table('sltr_grouping as g')
                ->join('sltr_print_label_batch_items as i', 'i.file_number', '=', 'g.sltr_awaiting_fileno')
                ->max('g.sys_batch_no');

            $nextBatchNo = $maxBatched ? (int)$maxBatched + 1 : 1;

            $exists = DB::connection('sqlsrv')
                ->table('sltr_grouping')
                ->where('sys_batch_no', $nextBatchNo)
                ->exists();

            return response()->json([
                'success' => true,
                'data' => [
                    'prefix'        => self::PREFIX,
                    'next_batch_no' => $nextBatchNo,
                    'exists'        => $exists,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('sltr-printlabel.getNextRangeForPrefix', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Fetch sltr_grouping records for a given sys_batch_no.
     */
    public function getAvailableFiles(Request $request)
    {
        try {
            // Sub Prefix accepts a list ("A" or "A,B,C") so several sub prefixes can be
            // loaded together and then assigned a shelf each in the Sub Prefix panel.
            $subPrefixes = $this->parseSubPrefixes($request->input('sub_prefix', ''));
            $subPrefix   = implode(',', $subPrefixes);
            $search      = trim((string) $request->input('search', ''));
            $digitRank   = trim((string) $request->input('digit_rank', ''));

            if (empty($subPrefixes)) {
                return response()->json(['success' => false, 'message' => 'Please select at least one sub prefix.'], 422);
            }

            if ($digitRank !== '' && !in_array($digitRank, ['5', '6'], true)) {
                return response()->json(['success' => false, 'message' => 'Invalid digit rank selected.'], 422);
            }

            // Look up from file_indexings instead of sltr_grouping
            $query = FileIndexing::on('sqlsrv')
                ->where('registry', 'like', '%SLTR%')
                ->where(function ($q) use ($subPrefixes) {
                    foreach ($subPrefixes as $p) {
                        $q->orWhereRaw("file_number LIKE ? ESCAPE '\\'", [$this->likePrefixPattern('SLTR-' . $p)]);
                    }
                })
                ->select([
                    'id',
                    'file_number',
                    'tracking_id',
                    'land_use_type',
                    'shelf_location',
                    'sub_prefix',
                    'suffix',
                    'digit_rank',
                ]);

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('file_number', 'like', '%' . $search . '%')
                      ->orWhere('tracking_id', 'like', '%' . $search . '%');
                });
            }

            if ($digitRank !== '') {
                $query->where('digit_rank', (int) $digitRank);
            }

            // Exclude files that are already in a batch (already generated/printed)
            $query->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('sltr_print_label_batch_items')
                    ->whereColumn('sltr_print_label_batch_items.file_number', 'file_indexings.file_number');
            });

            // Handle exclude_assigned parameter (Skip Assigned Shelves)
            if ($request->boolean('exclude_assigned')) {
                $query->where(function ($q) {
                    $q->whereNull('shelf_location')
                      ->orWhere('shelf_location', '')
                      ->orWhere('shelf_location', 'like', '%N/A%');
                });
            }

            $rows = $query
                ->orderByRaw('CASE WHEN digit_rank IS NULL THEN 999 ELSE digit_rank END')
                ->orderBy('file_number')
                ->get();

            if ($rows->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'files'      => [],
                        'missing'    => [],
                        'total'      => 0,
                        'prefix'       => self::PREFIX,
                        'sub_prefix'   => $subPrefix,
                        'sub_prefixes' => $subPrefixes,
                        'digit_rank'   => $digitRank,
                        'message'      => 'No indexed records found for this sub prefix.',
                    ],
                ]);
            }

            // Check if this sub_prefix already has a generated batch (optional, logic might need adjustment)
            $batchAlreadyUsed = false; // We can relax this for sub_prefix or implement similar logic if needed

            $this->syncFileIndexingDigitRanks($rows);

            // Which of the requested sub prefixes each file belongs to. Files stay
            // grouped by sub prefix (in the order the user picked them) so each
            // group is a contiguous, serially ordered slice.
            $order = array_flip($subPrefixes);

            $mapped = $rows
                ->each(function ($r) use ($subPrefixes) {
                    $r->sub_prefix_group = $this->resolveSubPrefixGroup($r->file_number, $subPrefixes);
                })
                ->sort(function ($a, $b) use ($order) {
                    $groupCompare = ($order[$a->sub_prefix_group] ?? PHP_INT_MAX) <=> ($order[$b->sub_prefix_group] ?? PHP_INT_MAX);
                    if ($groupCompare !== 0) {
                        return $groupCompare;
                    }

                    $rankCompare = ($a->digit_rank ?? 999) <=> ($b->digit_rank ?? 999);

                    return $rankCompare !== 0
                        ? $rankCompare
                        : strnatcasecmp((string) $a->file_number, (string) $b->file_number);
                })
                ->map(function ($r) {
                return [
                    'id'               => $r->id,
                    'file_number'      => $r->file_number,
                    'digit_rank'       => $r->digit_rank,
                    'file_title'       => null,
                    'plot_number'      => null,
                    'district'         => null,
                    'lga'              => null,
                    'land_use_type'    => $r->land_use_type,
                    'shelf_location'   => $r->shelf_location,
                    'tracking_id'      => $r->tracking_id,
                    'sub_prefix'       => $r->sub_prefix,
                    'sub_prefix_group' => $r->sub_prefix_group,
                    'suffix'           => $r->suffix,
                    'already_batched'  => false,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'files'              => $mapped->values(),
                    'missing'            => [],
                    'total'              => $mapped->count(),
                    'prefix'             => self::PREFIX,
                    'sub_prefix'         => $subPrefix,
                    'sub_prefixes'       => $subPrefixes,
                    'digit_rank'         => $digitRank,
                    'indexed_count'      => $rows->count(),
                    'batch_already_used' => $batchAlreadyUsed,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('sltr-printlabel.getAvailableFiles', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get rack/shelf label capacity status.
     */
    public function getRackLabelStatus(Request $request)
    {
        try {
            $fullLabel = strtoupper(trim((string) $request->input('full_label', '')));
            if ($fullLabel === '') {
                return response()->json(['success' => false, 'message' => 'full_label is required.'], 422);
            }

            $label = $this->resolveRackLabelRecord($fullLabel);

            if (!$label) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'full_label' => $fullLabel,
                        'counter'    => 0,
                        'capacity'   => self::RACK_SHELF_CAPACITY,
                        'remaining'  => self::RACK_SHELF_CAPACITY,
                        'is_full'    => false,
                        'exists'     => false,
                    ],
                ]);
            }

            $counter   = max(0, (int) ($label->counter ?? 0));
            $remaining = max(0, self::RACK_SHELF_CAPACITY - $counter);

            return response()->json([
                'success' => true,
                'data' => [
                    'label_id'   => $label->id,
                    'full_label' => $label->full_label ?? $fullLabel,
                    'rack'       => $label->rack,
                    'shelf'      => $label->shelf,
                    'counter'    => $counter,
                    'capacity'   => self::RACK_SHELF_CAPACITY,
                    'remaining'  => $remaining,
                    'is_full'    => $counter >= self::RACK_SHELF_CAPACITY,
                    'assigned'   => $label->assigned,
                    'exists'     => true,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('sltr-printlabel.getRackLabelStatus', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Create a label batch for the selected SLTR files on a given shelf.
     *
     * When `sub_groups` is supplied the selection is split into serial sub groups,
     * each with its own rack/shelf label, and one batch is created per sub group.
     */
    public function createBatch(Request $request)
    {
        try {
            $validated = $request->validate([
                'prefix'       => 'required|string|in:' . self::PREFIX,
                'sub_prefix'   => 'nullable|string',
                'file_ids'     => 'required|array|min:1',
                'file_ids.*'   => 'integer|min:1',
                'full_label'   => 'required|string|max:20',
                'rack_primary' => 'required|string|max:5',
                'shelf_number' => 'required|integer|min:1|max:9999',
                'rack_secondary' => 'nullable|string|max:5',

                // One entry per shelf assignment: a serial slice (Sub Group mode) or
                // a whole sub prefix (Sub Prefix mode, which allows a single group).
                'sub_groups'                    => 'nullable|array|min:1',
                'sub_groups.*.file_ids'         => 'required|array|min:1',
                'sub_groups.*.file_ids.*'       => 'integer|min:1',
                'sub_groups.*.sub_prefix'       => 'nullable|string|max:50',
                'sub_groups.*.full_label'       => 'required|string|max:20',
                'sub_groups.*.rack_primary'     => 'required|string|max:5',
                'sub_groups.*.rack_secondary'   => 'nullable|string|max:5',
                'sub_groups.*.shelf_number'     => 'required|integer|min:1|max:9999',
            ]);

            $prefix    = self::PREFIX;
            $subPrefix = $validated['sub_prefix'] ?? null;

            $groups = $this->normalizeBatchGroups($validated);

            $result = DB::connection('sqlsrv')->transaction(function () use ($prefix, $subPrefix, $groups) {
                $now        = Carbon::now();
                $multiGroup = count($groups) > 1;
                $baseNumber = 'SLTR-' . $now->format('YmdHis');

                $batches     = [];
                $labelItems  = [];
                $fileCount   = 0;

                foreach ($groups as $index => $group) {
                    $batchNumber = $multiGroup ? $baseNumber . '-G' . ($index + 1) : $baseNumber;

                    $created = $this->createBatchForGroup(
                        $batchNumber,
                        $prefix,
                        // In Sub Prefix mode each group carries its own sub prefix, so
                        // the batch row records the one it actually holds.
                        $group['sub_prefix'] ?? $subPrefix,
                        $group['file_ids'],
                        $group['full_label'],
                        $group['rack_primary'],
                        $group['rack_secondary'],
                        $group['shelf_number'],
                        $now
                    );

                    $subGroupIndex = $multiGroup ? $index + 1 : null;
                    $groupSubPrefix = $group['sub_prefix'] ?? $subPrefix;
                    foreach ($created['label_items'] as $item) {
                        $item['sub_group']       = $subGroupIndex;
                        $item['sub_group_total'] = $multiGroup ? count($groups) : null;
                        $labelItems[] = $item;
                    }

                    $fileCount += $created['file_count'];
                    $batches[] = [
                        'batch_id'     => $created['batch']->id,
                        'batch_number' => $created['batch']->batch_number,
                        'full_label'   => $group['full_label'],
                        'file_count'   => $created['file_count'],
                        'sub_group'    => $subGroupIndex,
                        'sub_prefix'   => $groupSubPrefix,
                    ];
                }

                return [
                    'batches'     => $batches,
                    'label_items' => $labelItems,
                    'file_count'  => $fileCount,
                ];
            });

            $first = $result['batches'][0];

            return response()->json([
                'success' => true,
                'message' => count($result['batches']) > 1
                    ? count($result['batches']) . ' sub group label batches created successfully.'
                    : 'Label batch created successfully.',
                'data' => [
                    'batch_id'     => $first['batch_id'],
                    'batch_number' => $first['batch_number'],
                    'batch_ids'    => array_column($result['batches'], 'batch_id'),
                    'batches'      => $result['batches'],
                    'file_count'   => $result['file_count'],
                    'label_items'  => $result['label_items'],
                ],
            ]);

        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('sltr-printlabel.createBatch', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Error creating batch: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Turn the validated request into a list of {file_ids, label, rack, shelf} specs.
     * Without sub_groups this is a single spec built from the top-level fields.
     */
    private function normalizeBatchGroups(array $validated): array
    {
        $raw = $validated['sub_groups'] ?? null;

        if (empty($raw)) {
            return [[
                'file_ids'       => array_values(array_unique(array_map('intval', $validated['file_ids']))),
                'sub_prefix'     => $validated['sub_prefix'] ?? null,
                'full_label'     => strtoupper(trim($validated['full_label'])),
                'rack_primary'   => strtoupper(trim($validated['rack_primary'])),
                'rack_secondary' => isset($validated['rack_secondary']) ? strtoupper(trim($validated['rack_secondary'])) : null,
                'shelf_number'   => (int) $validated['shelf_number'],
            ]];
        }

        $groups = [];
        $seen   = [];

        foreach ($raw as $group) {
            $ids = array_values(array_unique(array_map('intval', $group['file_ids'])));

            foreach ($ids as $id) {
                if (isset($seen[$id])) {
                    throw ValidationException::withMessages([
                        'sub_groups' => "File #{$id} appears in more than one sub group.",
                    ]);
                }
                $seen[$id] = true;
            }

            $subPrefix = isset($group['sub_prefix']) ? (trim((string) $group['sub_prefix']) ?: null) : null;

            // Groups are allowed to share the same shelf/rack label.
            $label = strtoupper(trim($group['full_label']));

            $groups[] = [
                'file_ids'       => $ids,
                'sub_prefix'     => $subPrefix,
                'full_label'     => $label,
                'rack_primary'   => strtoupper(trim($group['rack_primary'])),
                'rack_secondary' => isset($group['rack_secondary']) ? (strtoupper(trim((string) $group['rack_secondary'])) ?: null) : null,
                'shelf_number'   => (int) $group['shelf_number'],
            ];
        }

        return $groups;
    }

    /**
     * Create one batch (and its items) for a single rack/shelf label.
     */
    private function createBatchForGroup(
        string $batchNumber,
        string $prefix,
        ?string $subPrefix,
        array $fileIds,
        string $fullLabel,
        string $rackPrimary,
        ?string $rackSecondary,
        int $shelfNumber,
        Carbon $now
    ): array {
        $batch = SltrPrintLabelBatch::create([
            'batch_number'  => $batchNumber,
            'prefix'        => $prefix,
            'sub_prefix'    => $subPrefix, // "Group" — distinct sub_prefix from grouping
            'sys_batch_no'  => 0, // No longer using sys_batch_no as primary grouping
            'status'        => SltrPrintLabelBatch::STATUS_PENDING,
            'full_label'    => $fullLabel,
            'rack_primary'  => $rackPrimary,
            'rack_secondary'=> $rackSecondary,
            'shelf_number'  => $shelfNumber,
            'created_by'    => auth()->id(),
            'updated_by'    => auth()->id(),
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        // Fetch files from file_indexings instead of sltr_grouping
        // Chunked: SQL Server caps a statement at 2100 bind parameters, and the
        // selection size is no longer capped. Final ordering is applied below.
        $files = collect();
        foreach (array_chunk(array_values($fileIds), 1000) as $idChunk) {
            $files = $files->concat(
                FileIndexing::on('sqlsrv')
                    ->whereIn('id', $idChunk)
                    ->orderByRaw('CASE WHEN digit_rank IS NULL THEN 999 ELSE digit_rank END')
                    ->orderBy('file_number')
                    ->get()
            );
        }

        if ($files->isEmpty()) {
            throw ValidationException::withMessages(['file_ids' => 'No matching files found in indexed files.']);
        }

        $this->syncFileIndexingDigitRanks($files);
        $files = $files
            ->sort(function ($a, $b) {
                $rankCompare = ($a->digit_rank ?? 999) <=> ($b->digit_rank ?? 999);

                return $rankCompare !== 0
                    ? $rankCompare
                    : strnatcasecmp((string) $a->file_number, (string) $b->file_number);
            })
            ->values();

        // Resolve / create rack label record
        $shelfLabelRecord = $this->resolveRackLabelRecord($fullLabel, $rackPrimary, $rackSecondary, $shelfNumber, false, true);

        $items = $files->values()->map(function ($file, $index) use ($batch, $fullLabel, $prefix, $now) {
            $fileNumber = $file->file_number;
            $qrPayload = [
                'file_number'  => $fileNumber,
                'tracking_id'  => $file->tracking_id ?? $fileNumber,
                'prefix'       => $prefix,
                'digit_rank'   => $file->digit_rank,
                'shelf_label'  => $fullLabel,
                'generated_at' => $now->toIso8601String(),
            ];

            return [
                'batch_id'        => $batch->id,
                'file_indexing_id'=> $file->id,
                'file_number'     => $fileNumber,
                'prefix'          => $prefix,
                'file_title'      => null,
                'plot_number'     => null,
                'district'        => null,
                'lga'             => null,
                'land_use_type'   => $file->land_use_type,
                'shelf_location'  => $fullLabel,
                'label_position'  => $index + 1,
                'qr_code_data'    => json_encode($qrPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'barcode_data'    => $fileNumber,
                'is_printed'      => false,
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
        })->all();

        // Insert batch items in chunks
        $this->insertBatchItemsInChunks($items);

        // Update file_indexings.shelf_location with the assigned label
        $ids = $files->pluck('id')->all();
        foreach (array_chunk($ids, 100) as $chunk) {
            FileIndexing::on('sqlsrv')
                ->whereIn('id', $chunk)
                ->update([
                    'shelf_location' => $fullLabel,
                    'updated_at' => $now,
                ]);
        }

        // Bump the rack label counter
        if ($shelfLabelRecord) {
            $count = count($ids);
            $shelfLabelRecord->update([
                'assigned'  => 'SLTR',
                'counter'   => DB::raw("CAST(CAST(ISNULL(NULLIF(counter, ''), '0') AS INT) + {$count} AS NVARCHAR(MAX))"),
                'status'    => 'Occupied',
                'updated_at'=> $now,
            ]);
        }

        // Finalize batch
        $batch->update([
            'status'          => SltrPrintLabelBatch::STATUS_GENERATED,
            'generated_count' => count($ids),
            'updated_by'      => auth()->id(),
        ]);

        // Return label items for immediate print preview
        $labelItems = $files->values()->map(function ($file, $index) use ($fullLabel, $prefix) {
            $fileNumber = $file->file_number;
            return [
                'file_indexing_id' => $file->id,
                'file_number'      => $fileNumber,
                'digit_rank'       => $file->digit_rank,
                'file_title'       => $file->file_title,
                'plot_number'      => $file->plot_number,
                'district'         => $file->location,
                'lga'              => null,
                'land_use_type'    => $file->land_use_type,
                'shelf_location'   => $fullLabel,
                'shelf_value'      => $fullLabel,
                'shelf_label'      => $fullLabel,
                'tracking_id'      => $file->tracking_id ?? $fileNumber,
                'qr_value'         => $file->tracking_id ?? $fileNumber,
                'label_position'   => $index + 1,
                'prefix'           => $prefix,
            ];
        })->values()->toArray();

        return [
            'batch'       => $batch->fresh(),
            'label_items' => $labelItems,
            'file_count'  => count($ids),
        ];
    }

    /**
     * List generated batches (SLTR only).
     */
    public function getBatches(Request $request)
    {
        try {
            $query = SltrPrintLabelBatch::with(['creator'])
                ->withCount('batchItems')
                ->where('status', '!=', SltrPrintLabelBatch::STATUS_PENDING);

            if ($request->filled('search')) {
                $query->where('batch_number', 'like', '%' . $request->search . '%');
            }

            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            $perPage = min(100, max(1, (int) $request->get('per_page', 20)));
            $batches = $query->orderBy('created_at', 'desc')->paginate($perPage);

            $batchCollection = $batches->getCollection();
            $batchIds = $batchCollection->pluck('id')->filter()->values()->toArray();

            $firstFileNumbers = [];
            if (!empty($batchIds)) {
                $firstFileNumbers = SltrPrintLabelBatchItem::whereIn('batch_id', $batchIds)
                    ->select('batch_id', 'file_number')
                    ->orderBy('label_position')
                    ->get()
                    ->groupBy('batch_id')
                    ->map(fn($items) => $items->first()->file_number ?? null)
                    ->toArray();
            }

            $data = $batchCollection->map(function ($batch) use ($firstFileNumbers) {
                $arr = $batch->toArray();
                $fileNumber = $firstFileNumbers[$batch->id] ?? null;
                $arr['digit_rank'] = $fileNumber ? SltrDigitRank::fromFileNumber($fileNumber) : null;
                return $arr;
            });

            return response()->json([
                'success' => true,
                'data'    => $data->values(),
                'pagination' => [
                    'current_page' => $batches->currentPage(),
                    'last_page'    => $batches->lastPage(),
                    'per_page'     => $batches->perPage(),
                    'total'        => $batches->total(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('sltr-printlabel.getBatches', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Return a batch with all label items ready for printing.
     */
    public function getBatchForPrinting($batchId)
    {
        try {
            $batch = SltrPrintLabelBatch::with(['batchItems'])->findOrFail($batchId);

            $fileIndexingIds = $batch->batchItems->pluck('file_indexing_id')->filter()->unique()->values();

            $indexingDetails = collect();
            if ($fileIndexingIds->isNotEmpty()) {
                $indexingDetails = FileIndexing::on('sqlsrv')
                    ->whereIn('id', $fileIndexingIds)
                    ->get()
                    ->keyBy('id');
            }

            $files = $batch->batchItems->map(function ($item) use ($indexingDetails) {
                $details = $indexingDetails->get($item->file_indexing_id);

                $qrData = [];
                if (!empty($item->qr_code_data)) {
                    $decoded = json_decode($item->qr_code_data, true);
                    if (is_array($decoded)) {
                        $qrData = $decoded;
                    }
                }

                $trackingId = $qrData['tracking_id'] ?? optional($details)->tracking_id ?? $item->file_number;
                $shelfValue = $item->shelf_location ?? optional($details)->shelf_location ?? null;

                return [
                    'id'               => $item->file_indexing_id,
                    'batch_item_id'    => $item->id,
                    'file_number'      => $item->file_number,
                    'file_title'       => $item->file_title,
                    'plot_number'      => $item->plot_number,
                    'district'         => $item->district,
                    'lga'              => $item->lga,
                    'land_use_type'    => $item->land_use_type,
                    'digit_rank'       => SltrDigitRank::fromFileNumber($item->file_number),
                    'shelf_location'   => $shelfValue,
                    'shelf_value'      => $shelfValue,
                    'shelf_label'      => $shelfValue,
                    'tracking_id'      => $trackingId,
                    'qr_code_data'     => $qrData,
                    'qr_value'         => $trackingId,
                    'label_position'   => $item->label_position,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'batch' => $batch,
                    'files' => $files->values(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('sltr-printlabel.getBatchForPrinting', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Mark a batch as printed.
     */
    public function markBatchAsPrinted($batchId)
    {
        try {
            $batch = SltrPrintLabelBatch::findOrFail($batchId);
            $batch->markAsPrinted();
            $batch->batchItems()->update(['is_printed' => true, 'printed_at' => now()]);

            Log::info('sltr-printlabel.markBatchAsPrinted', ['batch_id' => $batchId, 'user_id' => auth()->id()]);

            return response()->json(['success' => true, 'message' => 'Batch marked as printed.']);
        } catch (\Throwable $e) {
            Log::error('sltr-printlabel.markBatchAsPrinted', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete a (non-printed) batch.
     */
    public function deleteBatch($batchId)
    {
        try {
            $batch = SltrPrintLabelBatch::findOrFail($batchId);

            if (in_array($batch->status, [SltrPrintLabelBatch::STATUS_PRINTED, SltrPrintLabelBatch::STATUS_COMPLETED])) {
                return response()->json(['success' => false, 'message' => 'Cannot delete printed or completed batches.'], 400);
            }

            $batch->delete();

            Log::info('sltr-printlabel.deleteBatch', ['batch_id' => $batchId, 'user_id' => auth()->id()]);

            return response()->json(['success' => true, 'message' => 'Batch deleted.']);
        } catch (\Throwable $e) {
            Log::error('sltr-printlabel.deleteBatch', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Split the Sub Prefix input into a clean list. Sub prefixes never contain
     * whitespace-delimited parts, so only split on commas / semicolons / newlines.
     */
    private function parseSubPrefixes($raw): array
    {
        $parts = preg_split('/[,;\r\n]+/', (string) $raw);
        $parts = array_filter(array_map('trim', $parts), fn($p) => $p !== '');

        return array_values(array_unique($parts));
    }

    /**
     * Build a "starts with" LIKE pattern with the SQL Server wildcards escaped,
     * for use with `LIKE ? ESCAPE '\'`.
     */
    private function likePrefixPattern(string $value): string
    {
        return str_replace(['\\', '%', '_', '['], ['\\\\', '\%', '\_', '\['], $value) . '%';
    }

    /**
     * Which of the requested sub prefixes a file number belongs to. Longest match
     * wins so "SLTR-AB12" resolves to "AB" rather than "A" when both were loaded.
     */
    private function resolveSubPrefixGroup(?string $fileNumber, array $subPrefixes): ?string
    {
        $number = strtoupper(trim((string) $fileNumber));
        if ($number === '') {
            return null;
        }

        $candidates = $subPrefixes;
        usort($candidates, fn($a, $b) => strlen($b) <=> strlen($a));

        foreach ($candidates as $p) {
            if (str_starts_with($number, strtoupper('SLTR-' . $p))) {
                return $p;
            }
        }

        return null;
    }

    private function resolveRackLabelRecord(
        string $fullLabel,
        ?string $rackPrimary = null,
        ?string $rackSecondary = null,
        ?int $shelfNumber = null,
        bool $lockForUpdate = false,
        bool $createIfMissing = false
    ): ?SltrRackShelfLabel {
        $normalized = strtoupper(trim($fullLabel));

        $query = SltrRackShelfLabel::query();
        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $label = $query->whereRaw('UPPER(LTRIM(RTRIM(full_label))) = ?', [$normalized])->first();
        if ($label) {
            return $label;
        }

        // Try to parse the label if parts not provided
        if (($rackPrimary === null || $shelfNumber === null) && preg_match('/^([A-Z]{1,2})(\d{1,4})$/', $normalized, $matches)) {
            $rackGroup   = $matches[1];
            $rackPrimary = substr($rackGroup, 0, 1);
            $rackSecondary = strlen($rackGroup) > 1 ? substr($rackGroup, 1) : null;
            $shelfNumber = (int) $matches[2];
        }

        if ($createIfMissing && $rackPrimary !== null && $shelfNumber !== null) {
            return SltrRackShelfLabel::create([
                'rack'       => strtoupper(trim($rackPrimary . ($rackSecondary ?? ''))),
                'shelf'      => (string) $shelfNumber,
                'full_label' => $normalized,
                'is_used'    => false,
                'counter'    => '0',
                'status'     => 'Available',
                'created_at' => now(),
            ]);
        }

        return null;
    }

    private function insertBatchItemsInChunks(array $items): void
    {
        if (empty($items)) {
            return;
        }

        $columnCount  = count($items[0]);
        $maxPerInsert = max(1, (int) floor(2000 / $columnCount));

        foreach (array_chunk($items, $maxPerInsert) as $chunk) {
            SltrPrintLabelBatchItem::insert($chunk);
        }
    }

    private function syncFileIndexingDigitRanks($rows): void
    {
        foreach ($rows as $row) {
            $rank = SltrDigitRank::fromFileNumber($row->file_number ?? null);
            $row->digit_rank = $rank;

            if ((int) ($row->getOriginal('digit_rank') ?? 0) === (int) ($rank ?? 0)) {
                continue;
            }

            FileIndexing::on('sqlsrv')
                ->where('id', $row->id)
                ->update(['digit_rank' => $rank]);
        }
    }
}
