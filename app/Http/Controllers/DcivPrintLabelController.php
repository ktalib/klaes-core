<?php

namespace App\Http\Controllers;

use App\Models\FileIndexing;
use App\Models\DcivRackShelfLabel;
use App\Models\DcivPrintLabelBatch;
use App\Models\DcivPrintLabelBatchItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class DcivPrintLabelController extends Controller
{
    const RACK_SHELF_CAPACITY = 100;
    const MAX_BATCH_SELECTION = 500;

    const PREFIXES = ['DCIV', 'LPCC'];

    // -------------------------------------------------------------------------
    // Public endpoints
    // -------------------------------------------------------------------------

    public function index(Request $request)
    {
        $recentBatches = DcivPrintLabelBatch::with(['creator'])
            ->withCount('batchItems')
            ->where('status', '!=', DcivPrintLabelBatch::STATUS_PENDING)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return view('dciv_printlabel.index', compact('recentBatches'));
    }

    /**
     * Return the list of allowed DCIV/LPCC prefixes with a file count each.
     */
    public function getPrefixes()
    {
        try {
            $result = [];
            foreach (self::PREFIXES as $prefix) {
                $count = DB::connection('sqlsrv')
                    ->table('dciv_grouping')
                    ->where('dciv_awaiting_fileno', 'like', $prefix . '-%')
                    ->whereNotNull('dciv_awaiting_fileno')
                    ->count();

                $result[] = [
                    'prefix' => $prefix,
                    'label'  => $prefix,
                    'count'  => $count,
                ];
            }

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            Log::error('dciv-printlabel.getPrefixes', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Suggest the next unprocessed sys_batch_no for a given prefix.
     */
    public function getNextRangeForPrefix(Request $request)
    {
        try {
            $prefix = strtoupper(trim($request->input('prefix', 'DCIV')));
            if (!in_array($prefix, self::PREFIXES)) {
                $prefix = 'DCIV';
            }

            $maxBatched = DB::connection('sqlsrv')
                ->table('dciv_grouping as g')
                ->join('dciv_print_label_batch_items as i', 'i.file_number', '=', 'g.dciv_awaiting_fileno')
                ->where('g.dciv_awaiting_fileno', 'like', $prefix . '-%')
                ->max('g.sys_batch_no');

            $nextBatchNo = $maxBatched ? (int) $maxBatched + 1 : 1;

            $exists = DB::connection('sqlsrv')
                ->table('dciv_grouping')
                ->where('sys_batch_no', $nextBatchNo)
                ->where('dciv_awaiting_fileno', 'like', $prefix . '-%')
                ->exists();

            return response()->json([
                'success' => true,
                'data' => [
                    'prefix'        => $prefix,
                    'next_batch_no' => $nextBatchNo,
                    'exists'        => $exists,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('dciv-printlabel.getNextRangeForPrefix', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Fetch dciv_grouping records for a given prefix + sys_batch_no.
     */
    public function getAvailableFiles(Request $request)
    {
        try {
            $prefix     = strtoupper(trim($request->input('prefix', 'DCIV')));
            $sysBatchNo = (int) $request->input('sys_batch_no', 0);
            $search     = trim((string) $request->input('search', ''));

            if (!in_array($prefix, self::PREFIXES)) {
                return response()->json(['success' => false, 'message' => 'Invalid prefix.'], 422);
            }
            if ($sysBatchNo < 1) {
                return response()->json(['success' => false, 'message' => 'Invalid batch number.'], 422);
            }

            $query = DB::connection('sqlsrv')
                ->table('dciv_grouping')
                ->where('sys_batch_no', $sysBatchNo)
                ->where('dciv_awaiting_fileno', 'like', $prefix . '-%')
                ->whereNotNull('dciv_awaiting_fileno')
                ->whereNull('deleted_at')
                ->select([
                    'id',
                    'dciv_awaiting_fileno',
                    'tracking_id',
                    'landuse',
                    'shelf_rack',
                    'year',
                    'number',
                    'sys_batch_no',
                    'registry_batch_no',
                ]);

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('dciv_awaiting_fileno', 'like', '%' . $search . '%')
                      ->orWhere('tracking_id', 'like', '%' . $search . '%');
                });
            }

            $rows = $query->orderBy('number')->get();

            if ($rows->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'files'        => [],
                        'missing'      => [],
                        'total'        => 0,
                        'prefix'       => $prefix,
                        'sys_batch_no' => $sysBatchNo,
                        'message'      => 'No records found in dciv_grouping for this prefix/batch.',
                    ],
                ]);
            }

            $batchAlreadyUsed = DcivPrintLabelBatch::where('prefix', $prefix)
                ->where('sys_batch_no', $sysBatchNo)
                ->whereIn('status', [
                    DcivPrintLabelBatch::STATUS_GENERATED,
                    DcivPrintLabelBatch::STATUS_PRINTED,
                    DcivPrintLabelBatch::STATUS_COMPLETED,
                ])
                ->exists();

            $alreadyBatched = DB::connection('sqlsrv')
                ->table('dciv_print_label_batch_items')
                ->whereIn('file_number', $rows->pluck('dciv_awaiting_fileno')->all())
                ->pluck('file_number')
                ->flip()
                ->all();

            $mapped = $rows->map(function ($r) use ($alreadyBatched) {
                return [
                    'id'              => $r->id,
                    'file_number'     => $r->dciv_awaiting_fileno,
                    'file_title'      => null,
                    'plot_number'     => null,
                    'district'        => null,
                    'lga'             => null,
                    'land_use_type'   => $r->landuse,
                    'shelf_location'  => $r->shelf_rack,
                    'tracking_id'     => $r->tracking_id,
                    'batch_no'        => $r->sys_batch_no,
                    'already_batched' => isset($alreadyBatched[$r->dciv_awaiting_fileno]),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'files'              => $mapped->values(),
                    'missing'            => [],
                    'total'              => $mapped->count(),
                    'prefix'             => $prefix,
                    'sys_batch_no'       => $sysBatchNo,
                    'grouping_count'     => $rows->count(),
                    'batch_already_used' => $batchAlreadyUsed,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('dciv-printlabel.getAvailableFiles', ['error' => $e->getMessage()]);
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
            Log::error('dciv-printlabel.getRackLabelStatus', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Create a label batch for the selected DCIV/LPCC files on a given shelf.
     */
    public function createBatch(Request $request)
    {
        try {
            $validated = $request->validate([
                'prefix'       => 'required|string|in:' . implode(',', self::PREFIXES),
                'sys_batch_no' => 'required|integer|min:1',
                'file_ids'     => 'required|array|min:1|max:' . self::MAX_BATCH_SELECTION,
                'file_ids.*'   => 'integer|min:1',
                'full_label'   => 'required|string|max:20',
                'rack_primary' => 'required|string|max:5',
                'shelf_number' => 'required|integer|min:1|max:9999',
                'rack_secondary' => 'nullable|string|max:5',
            ]);

            $prefix       = strtoupper(trim($validated['prefix']));
            $sysBatchNo   = (int) $validated['sys_batch_no'];
            $fileIds      = array_unique(array_map('intval', $validated['file_ids']));
            $fullLabel    = strtoupper(trim($validated['full_label']));
            $rackPrimary  = strtoupper(trim($validated['rack_primary']));
            $rackSecondary = isset($validated['rack_secondary']) ? strtoupper(trim($validated['rack_secondary'])) : null;
            $shelfNumber  = (int) $validated['shelf_number'];

            $existing = DcivPrintLabelBatch::where('prefix', $prefix)
                ->where('sys_batch_no', $sysBatchNo)
                ->whereIn('status', [
                    DcivPrintLabelBatch::STATUS_GENERATED,
                    DcivPrintLabelBatch::STATUS_PRINTED,
                    DcivPrintLabelBatch::STATUS_COMPLETED,
                ])
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => "Batch #{$sysBatchNo} for {$prefix} has already been generated (Batch: {$existing->batch_number}). Each sys_batch_no can only be used once per prefix.",
                ], 409);
            }

            $result = DB::connection('sqlsrv')->transaction(function () use (
                $prefix, $sysBatchNo, $fileIds, $fullLabel, $rackPrimary, $rackSecondary, $shelfNumber
            ) {
                $now = Carbon::now();

                $batchNumber = $prefix . '-' . $now->format('YmdHis');

                $batch = DcivPrintLabelBatch::create([
                    'batch_number'  => $batchNumber,
                    'prefix'        => $prefix,
                    'sys_batch_no'  => $sysBatchNo,
                    'status'        => DcivPrintLabelBatch::STATUS_PENDING,
                    'full_label'    => $fullLabel,
                    'rack_primary'  => $rackPrimary,
                    'rack_secondary'=> $rackSecondary,
                    'shelf_number'  => $shelfNumber,
                    'created_by'    => auth()->id(),
                    'updated_by'    => auth()->id(),
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);

                $files = DB::connection('sqlsrv')
                    ->table('dciv_grouping')
                    ->whereIn('id', $fileIds)
                    ->whereNotNull('dciv_awaiting_fileno')
                    ->whereNull('deleted_at')
                    ->get();

                if ($files->isEmpty()) {
                    throw ValidationException::withMessages(['file_ids' => 'No matching files found in dciv_grouping.']);
                }

                $shelfLabelRecord = $this->resolveRackLabelRecord($fullLabel, $rackPrimary, $rackSecondary, $shelfNumber, false, true);

                $items = $files->values()->map(function ($file, $index) use ($batch, $fullLabel, $prefix, $now) {
                    $fileNumber = $file->dciv_awaiting_fileno;
                    $qrPayload = [
                        'file_number'  => $fileNumber,
                        'tracking_id'  => $file->tracking_id ?? $fileNumber,
                        'prefix'       => $prefix,
                        'shelf_label'  => $fullLabel,
                        'generated_at' => $now->toIso8601String(),
                    ];

                    return [
                        'batch_id'        => $batch->id,
                        'file_indexing_id'=> null,
                        'file_number'     => $fileNumber,
                        'prefix'          => $prefix,
                        'file_title'      => null,
                        'plot_number'     => null,
                        'district'        => null,
                        'lga'             => null,
                        'land_use_type'   => $file->landuse,
                        'shelf_location'  => $fullLabel,
                        'label_position'  => $index + 1,
                        'qr_code_data'    => json_encode($qrPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'barcode_data'    => $fileNumber,
                        'is_printed'      => false,
                        'created_at'      => $now,
                        'updated_at'      => $now,
                    ];
                })->all();

                $this->insertBatchItemsInChunks($items);

                $ids = $files->pluck('id')->all();
                foreach (array_chunk($ids, 100) as $chunk) {
                    DB::connection('sqlsrv')
                        ->table('dciv_grouping')
                        ->whereIn('id', $chunk)
                        ->update([
                            'shelf_rack' => $fullLabel,
                            'updated_at' => $now,
                        ]);
                }

                if ($shelfLabelRecord) {
                    $count = count($ids);
                    $shelfLabelRecord->update([
                        'assigned'  => $prefix,
                        'counter'   => DB::raw("CAST(CAST(ISNULL(NULLIF(counter, ''), '0') AS INT) + {$count} AS NVARCHAR(MAX))"),
                        'status'    => 'Occupied',
                        'updated_at'=> $now,
                    ]);
                }

                $batch->update([
                    'status'          => DcivPrintLabelBatch::STATUS_GENERATED,
                    'generated_count' => count($ids),
                    'updated_by'      => auth()->id(),
                ]);

                $labelItems = $files->values()->map(function ($file, $index) use ($fullLabel, $prefix) {
                    $fileNumber = $file->dciv_awaiting_fileno;
                    return [
                        'file_indexing_id' => null,
                        'file_number'      => $fileNumber,
                        'file_title'       => null,
                        'plot_number'      => null,
                        'district'         => null,
                        'lga'              => null,
                        'land_use_type'    => $file->landuse,
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
            });

            return response()->json([
                'success' => true,
                'message' => 'Label batch created successfully.',
                'data' => [
                    'batch_id'     => $result['batch']->id,
                    'batch_number' => $result['batch']->batch_number,
                    'file_count'   => $result['file_count'],
                    'label_items'  => $result['label_items'],
                ],
            ]);

        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('dciv-printlabel.createBatch', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Error creating batch: ' . $e->getMessage()], 500);
        }
    }

    /**
     * List generated batches.
     */
    public function getBatches(Request $request)
    {
        try {
            $query = DcivPrintLabelBatch::with(['creator'])
                ->withCount('batchItems')
                ->where('status', '!=', DcivPrintLabelBatch::STATUS_PENDING);

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

            return response()->json([
                'success' => true,
                'data'    => $batches->items(),
                'pagination' => [
                    'current_page' => $batches->currentPage(),
                    'last_page'    => $batches->lastPage(),
                    'per_page'     => $batches->perPage(),
                    'total'        => $batches->total(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('dciv-printlabel.getBatches', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Return a batch with all label items ready for printing.
     */
    public function getBatchForPrinting($batchId)
    {
        try {
            $batch = DcivPrintLabelBatch::with(['batchItems'])->findOrFail($batchId);

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
            Log::error('dciv-printlabel.getBatchForPrinting', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Mark a batch as printed.
     */
    public function markBatchAsPrinted($batchId)
    {
        try {
            $batch = DcivPrintLabelBatch::findOrFail($batchId);
            $batch->markAsPrinted();
            $batch->batchItems()->update(['is_printed' => true, 'printed_at' => now()]);

            Log::info('dciv-printlabel.markBatchAsPrinted', ['batch_id' => $batchId, 'user_id' => auth()->id()]);

            return response()->json(['success' => true, 'message' => 'Batch marked as printed.']);
        } catch (\Throwable $e) {
            Log::error('dciv-printlabel.markBatchAsPrinted', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete a (non-printed) batch.
     */
    public function deleteBatch($batchId)
    {
        try {
            $batch = DcivPrintLabelBatch::findOrFail($batchId);

            if (in_array($batch->status, [DcivPrintLabelBatch::STATUS_PRINTED, DcivPrintLabelBatch::STATUS_COMPLETED])) {
                return response()->json(['success' => false, 'message' => 'Cannot delete printed or completed batches.'], 400);
            }

            $batch->delete();

            Log::info('dciv-printlabel.deleteBatch', ['batch_id' => $batchId, 'user_id' => auth()->id()]);

            return response()->json(['success' => true, 'message' => 'Batch deleted.']);
        } catch (\Throwable $e) {
            Log::error('dciv-printlabel.deleteBatch', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function resolveRackLabelRecord(
        string $fullLabel,
        ?string $rackPrimary = null,
        ?string $rackSecondary = null,
        ?int $shelfNumber = null,
        bool $lockForUpdate = false,
        bool $createIfMissing = false
    ): ?DcivRackShelfLabel {
        $normalized = strtoupper(trim($fullLabel));

        $query = DcivRackShelfLabel::query();
        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $label = $query->whereRaw('UPPER(LTRIM(RTRIM(full_label))) = ?', [$normalized])->first();
        if ($label) {
            return $label;
        }

        if (($rackPrimary === null || $shelfNumber === null) && preg_match('/^([A-Z]{1,2})(\d{1,4})$/', $normalized, $matches)) {
            $rackGroup   = $matches[1];
            $rackPrimary = substr($rackGroup, 0, 1);
            $rackSecondary = strlen($rackGroup) > 1 ? substr($rackGroup, 1) : null;
            $shelfNumber = (int) $matches[2];
        }

        if ($createIfMissing && $rackPrimary !== null && $shelfNumber !== null) {
            return DcivRackShelfLabel::create([
                'rack'       => $rackPrimary . ($rackSecondary ?? ''),
                'shelf'      => (string) $shelfNumber,
                'full_label' => $normalized,
                'is_used'    => false,
                'counter'    => '0',
                'status'     => 'Available',
            ]);
        }

        return null;
    }

    private function insertBatchItemsInChunks(array $items, int $chunkSize = 50): void
    {
        foreach (array_chunk($items, $chunkSize) as $chunk) {
            DB::connection('sqlsrv')->table('dciv_print_label_batch_items')->insert($chunk);
        }
    }
}
