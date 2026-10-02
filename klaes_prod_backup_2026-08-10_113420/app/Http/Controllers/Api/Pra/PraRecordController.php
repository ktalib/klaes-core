<?php

namespace App\Http\Controllers\Api\Pra;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pra\PraSearchRequest;
use App\Http\Requests\Pra\PraStoreRequest;
use App\Http\Requests\Pra\PraUpdateRequest;
use App\Http\Resources\Pra\PraHistoryResource;
use App\Http\Resources\Pra\PraRecordResource;
use App\Services\Pra\PraRecordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PraRecordController extends Controller
{
    private PraRecordService $service;

    public function __construct(PraRecordService $service)
    {
        $this->service = $service;
    }

    public function search(PraSearchRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 25);
        $page = (int) ($filters['page'] ?? 1);

        $paginator = $this->service->search($filters, $perPage, $page);

        $resource = PraRecordResource::collection(collect($paginator->items()));

        return response()->json([
            'success' => true,
            'data' => $resource->toArray($request),
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function show(string $propId): JsonResponse
    {
        $record = $this->service->getByPropId($propId);

        if ($record === null) {
            return response()->json([
                'success' => false,
                'message' => 'Record not found',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'data' => (new PraRecordResource($record))->toArray(request()),
        ]);
    }

    public function showByFile(string $fileNumber): JsonResponse
    {
        $record = $this->service->getByFileNumber($fileNumber);

        if ($record === null) {
            return response()->json([
                'success' => false,
                'message' => 'Record not found',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'data' => (new PraRecordResource($record))->toArray(request()),
        ]);
    }

    public function lookupAllByFile(Request $request, string $fileNumber): JsonResponse
    {
        // **PIC/PRA ROUTING FIX**: Check record_mode to determine if we should look in PIC table
        $recordMode = $request->input('record_mode', 'property');

        if ($recordMode === 'index') {
            $normalized = strtoupper(trim($fileNumber));
            // Columns to search in PIC table (matching repository logic)
            $columns = ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'fileno', 'np_fileno', 'temp_fileno'];

            $records = \Illuminate\Support\Facades\DB::connection('sqlsrv')->table('pic')
                ->where(function ($query) use ($columns, $normalized) {
                    foreach ($columns as $column) {
                        // Using raw to ensure case insensitivity and trim if needed
                        $query->orWhere(\Illuminate\Support\Facades\DB::raw("UPPER($column)"), $normalized);
                    }
                })
                ->orderByDesc('id')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $records->toArray(),
            ]);
        }

        // Default behavior for PRA records
        $records = $this->service->findAllByFileNumber($fileNumber);

        return response()->json([
            'success' => true,
            'data' => PraRecordResource::collection(collect($records))->toArray($request),
        ]);
    }

    /**
     * Return shared property details for a file from the file_indexings table.
     *
     * Used by the "Capture Existing Mortgage Now" backfill: the property
     * attributes (plot, TP, LPKN, district, LGA, land use, etc.) for a file
     * live in file_indexings, not in the PRA register, so once a file number
     * is selected we source them here. Matches across every file-number column
     * a file can be indexed under and returns snake_case keys the PRA form
     * controller already maps.
     */
    public function propertyByFile(Request $request, string $fileNumber): JsonResponse
    {
        $value = trim($fileNumber);
        if ($value === '') {
            return response()->json(['success' => false, 'data' => null]);
        }

        $columns = ['file_number', 'temp_file_no', 'mls_file_no', 'kangis_file_no', 'new_kangis_file_no'];

        $row = \Illuminate\Support\Facades\DB::connection('sqlsrv')
            ->table('file_indexings')
            ->where(function ($q) use ($columns, $value) {
                foreach ($columns as $column) {
                    $q->orWhereRaw("$column = CAST(? AS VARCHAR(255))", [$value]);
                }
            })
            ->orderByDesc('id')
            ->first();

        if (!$row) {
            return response()->json(['success' => true, 'data' => null]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'plot_no'              => $row->plot_number ?? null,
                'tp_no'                => $row->tp_no ?? null,
                'lpkn_no'              => $row->lpkn_no ?? null,
                'street_name'          => $row->street_name ?? null,
                'district'             => $row->district ?? null,
                'lga'                  => $row->lga ?? null,
                'location'             => $row->location ?? null,
                'land_use'             => $row->land_use_type ?? null,
                'property_description' => $row->property_description ?? null,
                // Owner/holder for grant-type instruments (CofO/OP) where the
                // file owner is the grantee. Holder columns store either a plain
                // name or a JSON-encoded array (block indexing); fall back to
                // the file title.
                'owner_name'           => $this->normalizeHolderName($row->current_holder ?? null)
                    ?: $this->normalizeHolderName($row->original_holder ?? null)
                    ?: (trim((string) ($row->file_title ?? '')) ?: null),
            ],
        ]);
    }

    /**
     * Normalize a raw holder value from file_indexings for display.
     *
     * The current_holder / original_holder columns store either a plain name
     * string or a JSON-encoded array of names (block indexing). Returns a
     * comma-joined name string, or null when empty.
     */
    private function normalizeHolderName($raw): ?string
    {
        if ($raw === null || trim((string) $raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            if (is_array($decoded)) {
                $names = array_values(array_filter(array_map('trim', $decoded), fn ($n) => $n !== ''));
                return $names ? implode(', ', $names) : null;
            }
            if (is_string($decoded)) {
                return trim($decoded) ?: null;
            }
        }

        return trim((string) $raw) ?: null;
    }

    public function history(Request $request, string $propId): JsonResponse
    {
        $history = $this->service->getHistory($propId);

        return response()->json([
            'success' => true,
            'data' => PraHistoryResource::collection(collect($history))->toArray($request),
        ]);
    }

    public function duplicates(Request $request, string $propId): JsonResponse
    {
        $duplicates = $this->service->getDuplicates($propId);

        $transformed = collect($duplicates)
            ->map(function (array $duplicate) use ($request) {
                if (isset($duplicate['record'])) {
                    $duplicate['record'] = (new PraRecordResource($duplicate['record']))->toArray($request);
                }

                return $duplicate;
            })
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => $transformed,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), (new PraStoreRequest())->rules());
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()->toArray(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validated = $validator->validated();

        // ── OP dedup guard (opt-in) ──────────────────────────────────────
        // Disabled by default since 2026-05-30: the guard's last-resort match
        // by op_serial_number alone (PraRecordService::findExistingOpInSource
        // step 2) silently hijacked fresh OP captures and linked them to an
        // unrelated existing IC row sharing the same serial — causing the
        // captured OP data to be lost and the TOT to inherit the wrong
        // parent prop_id. The OP serial lookup UI was removed for the same
        // reason; this guard was the backend twin of that behavior.
        //
        // Callers that genuinely want the link-existing-or-create-new
        // behavior must now opt in by passing `link_existing_op: true`
        // in the payload. Direct callers of the service method (e.g.
        // MlsFileNoController, ApplicationController) are unaffected.
        $incomingSource = trim($validated['source'] ?? '');
        $incomingType   = trim($validated['instrument_type'] ?? '');
        $isTransfer     = stripos($incomingType, 'Transfer of Title') !== false;
        $isOpRecord     = !$isTransfer
                       && (stripos($incomingSource, 'Occupancy Permit') !== false
                           || stripos($incomingType, 'Occupancy Permit') !== false);
        $linkExistingOp = filter_var(
            $request->input('link_existing_op', false),
            FILTER_VALIDATE_BOOLEAN
        );

        if ($isOpRecord && $linkExistingOp) {
            $identifiers = [
                'op_serial_number' => $validated['op_serial_number'] ?? null,
                'mlsFNo'           => $validated['mlsFNo'] ?? null,
                'fileno'           => $validated['fileno'] ?? null,
                'kangisFileNo'     => $validated['kangisFileNo'] ?? null,
                'NewKANGISFileno'  => $validated['NewKANGISFileno'] ?? null,
                'temp_fileno'      => $validated['temp_fileno'] ?? null,
                // Caller-supplied exact source row id/table — used when the user
                // picked a specific OP from a lookup list with duplicate serials.
                'source_op_id'     => $validated['source_op_id'] ?? null,
                'source_op_table'  => $validated['source_op_table'] ?? null,
            ];

            $existingOp = $this->service->findExistingOpInSource($identifiers);

            if ($existingOp) {
                // Link source record with any new data (prop_id, file numbers).
                $updates = array_filter([
                    'prop_id'    => $validated['prop_id'] ?? null,
                    'mlsFNo'     => $validated['mlsFNo'] ?? null,
                    'temp_fileno'=> $validated['temp_fileno'] ?? null,
                ], fn ($v) => $v !== null && $v !== '');

                $linkedPropId = $this->service->linkSourceOpRecord($existingOp, $updates);

                Log::info('PRA store: OP already in source – skipped duplicate', [
                    'source_table' => $existingOp['_source_table'],
                    'source_id'    => $existingOp['id'] ?? null,
                    'prop_id'      => $linkedPropId,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'OP already exists in ' . $existingOp['_source_table'] . '; linked instead of duplicating.',
                    'data'    => [
                        'prop_id'      => $linkedPropId ?? ($existingOp['prop_id'] ?? null),
                        'id'           => $existingOp['id'] ?? null,
                        'source_table' => $existingOp['_source_table'],
                        'skipped_duplicate' => true,
                    ],
                ], Response::HTTP_OK);
            }
        }
        // ── end OP dedup guard ───────────────────────────────────────────

        try {
            $record = $this->service->createRecord($validated, $request->user()?->id);
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $exception->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $exception) {
            Log::error('PRA record creation failed', [
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to create record',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'success' => true,
            'message' => 'Record created successfully',
            'data' => (new PraRecordResource($record))->toArray($request),
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, string $propId): JsonResponse
    {
        $validator = Validator::make($request->all(), (new PraUpdateRequest())->rules());
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()->toArray(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $record = $this->service->updateRecord($propId, $validator->validated(), $request->user()?->id);
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            $status = Response::HTTP_UNPROCESSABLE_ENTITY;

            if (isset($errors['prop_id']) && in_array('Record not found', $errors['prop_id'], true)) {
                $status = Response::HTTP_NOT_FOUND;
            }

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $errors,
            ], $status);
        } catch (Throwable $exception) {
            Log::error('PRA record update failed', [
                'prop_id' => $propId,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to update record',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'success' => true,
            'message' => 'Record updated successfully',
            'data' => (new PraRecordResource($record))->toArray($request),
        ]);


       
    }
}
