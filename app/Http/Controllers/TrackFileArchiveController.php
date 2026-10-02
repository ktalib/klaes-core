<?php
namespace App\Http\Controllers;

use App\Models\FileTracker;
use App\Services\FileLocationResolver;
use App\Services\ShelfRackLocator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TrackFileArchiveController extends Controller
{
    /**
     * Full archive detail for one file number — everything the Track File
     * (Archive) card renders, assembled server-side from the same sources
     * Quick Search uses (FileLocationResolver for status/location/registry,
     * ShelfRackLocator for the derived rack/shelf, the file tracker for
     * movement history) plus the print label for the tracking ID / land use.
     *
     * Accepts a file number OR a tracking ID, so a QR scan of either resolves.
     */
    public function details(Request $request, FileLocationResolver $resolver, ShelfRackLocator $locator)
    {
        $query = trim((string) $request->get('file_number', $request->get('query', '')));

        if ($query === '') {
            return response()->json([
                'success' => false,
                'message' => 'File number is required.',
            ], 422);
        }

        try {
            // A scanned tracking ID has to become a file number before the
            // resolver (which only understands file numbers) can place it.
            $fileNumber = $this->fileNumberForIdentifier($query);

            $result   = $resolver->resolve($fileNumber);
            $indexing = $result['indexing'] ?? null;
            $tracker  = $result['tracker'] ?? null;

            $label = $this->findLabelRecord($fileNumber);

            // Recorded shelf wins; fall back to the shelf_rack_ranges map and
            // flag it so the UI can show that it was inferred, not recorded.
            $rackShelf  = $result['rack_shelf'] ?? null;
            $isDerived  = false;

            if (!$rackShelf) {
                $rackShelf = $locator->resolve($fileNumber, $indexing->registry ?? null);
                $isDerived = $rackShelf !== null;
            }

            $rackShelf = $this->normalizeShelfLocation($rackShelf);

            // "A11" => rack "A", shelf "11".
            $rack  = $rackShelf ? (substr($rackShelf, 0, 1) ?: null) : null;
            $shelf = ($rackShelf && strlen($rackShelf) > 1) ? substr($rackShelf, 1) : null;

            $qrPayload = $label ? $this->decodeQrCodeData($label->qr_code_data) : [];

            $trackingId = ($tracker instanceof FileTracker ? $tracker->tracking_id : null)
                ?: ($indexing->tracking_id ?? null)
                ?: ($qrPayload['tracking_id'] ?? $qrPayload['trackingId'] ?? null);

            $landUse = ($indexing->land_use_type ?? null)
                ?: ($qrPayload['land_use_type'] ?? $qrPayload['landUseType'] ?? null)
                ?: ($label->land_use_type ?? null);

            $registryId   = $indexing->registry ?? null;
            $registryName = $result['registry'] ?? null;

            return response()->json([
                'success' => true,
                'data' => [
                    'file_number'      => $result['file_number'] ?? $fileNumber,
                    'searched_for'     => $query,
                    'file_title'       => $indexing->file_title ?? ($tracker instanceof FileTracker ? $tracker->file_title : null),
                    'tracking_id'      => $trackingId,
                    'land_use_type'    => $landUse,
                    'registry'         => $registryName ?: $registryId,
                    'registry_id'      => $registryId,
                    'registry_name'    => $registryName,
                    'status'           => $result['status'] ?? null,
                    'current_location' => $result['current_location'] ?? null,
                    'holder'           => $result['tracker'] instanceof FileTracker
                        ? ($result['tracker']->receiving_officer_name ?: null)
                        : null,
                    'held_since'           => $result['held_since'] ?? null,
                    'duration_with_holder' => $result['duration_with_holder'] ?? null,
                    // "Last updated" on the movement panel reports when the
                    // file's indexing record last changed, not the tracker.
                    'indexing_updated_at' => $indexing->updated_at ?? null,
                    'indexing_updated_by' => $indexing->updated_by ?? null,
                    'rack'             => $rack,
                    'shelf'            => $shelf,
                    'rack_shelf'       => $rackShelf,
                    'shelf_is_derived' => $isDerived,
                    'tracker'          => $tracker instanceof FileTracker ? [
                        'id'                  => $tracker->id,
                        'tracking_id'         => $tracker->tracking_id,
                        'status'              => $tracker->status,
                        'current_office_name' => $tracker->current_office_name,
                        'department'          => $tracker->department,
                        'deadline'            => $tracker->deadline,
                        'is_overdue'          => $tracker->is_overdue,
                        'timeline_status'     => $tracker->timeline_status,
                        'days_until_deadline' => $tracker->days_until_deadline,
                        'request_purpose_name' => $tracker->request_purpose_name,
                        'updated_at'          => $tracker->updated_at,
                    ] : null,
                    'movement_history' => $tracker instanceof FileTracker ? $this->movementLogOf($tracker) : [],
                    'prior_movements'  => $tracker instanceof FileTracker ? $this->priorMovementsFor($tracker) : [],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('TrackFileArchiveController::details failed', [
                'query'   => $query,
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load archive details for this file.',
            ], 500);
        }
    }

    /**
     * Treat the scanned/typed value as a tracking ID first (that is what the
     * printed QR carries) and swap in the tracker's file number when it hits;
     * otherwise the value is already a file number.
     */
    protected function fileNumberForIdentifier(string $identifier): string
    {
        $needle = mb_strtoupper(trim((string) $identifier));
        $tracker = FileTracker::where(function ($q) use ($identifier, $needle) {
            $q->where('tracking_id', $identifier)
              ->orWhere('tracking_id', 'LIKE', $needle . '%');
        })
            ->orderByDesc('id')
            ->first(['file_number']);

        $fileNumber = trim((string) ($tracker->file_number ?? ''));

        return $fileNumber !== '' ? $fileNumber : $identifier;
    }

    /**
     * The most recent print label row for a file number, matched on the same
     * variants labelMetadata() builds. Keyed lookup rather than a table scan.
     */
    protected function findLabelRecord(string $fileNumber)
    {
        $variants = array_map(
            static fn ($v) => strtoupper(trim($v)),
            $this->buildFileNumberVariants($fileNumber)
        );

        return DB::connection('sqlsrv')
            ->table('print_label_batch_items')
            ->whereIn(DB::raw('UPPER(LTRIM(RTRIM(file_number)))'), $variants)
            ->orderByDesc('id')
            ->first(['id', 'file_number', 'land_use_type', 'shelf_location', 'qr_code_data']);
    }

    /**
     * Movement log entries of a tracker as an array.
     */
    protected function movementLogOf(FileTracker $tracker): array
    {
        $log = is_array($tracker->movement_log)
            ? $tracker->movement_log
            : (json_decode((string) $tracker->movement_log, true) ?? []);

        return is_array($log) ? $log : [];
    }

    /**
     * Movement entries from this file's earlier tracking cycles, so the
     * timeline shows the file's whole history and not just the current cycle.
     */
    protected function priorMovementsFor(FileTracker $tracker): array
    {
        $fileNumber = trim((string) $tracker->file_number);

        if ($fileNumber === '') {
            return [];
        }

        $rows = FileTracker::query()
            ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = ?', [mb_strtoupper($fileNumber)])
            ->where('id', '<', $tracker->id)
            ->orderBy('id')
            ->get(['id', 'movement_log']);

        $entries = [];
        foreach ($rows as $row) {
            foreach ($this->movementLogOf($row) as $entry) {
                if (is_array($entry)) {
                    $entries[] = $entry;
                }
            }
        }

        return $entries;
    }

    /**
     * Display the Track File (Archive) page.
     */
    public function index(Request $request)
    {
        $module = $request->get('url', '');
        return view('track_file_archive.index', compact('module'));
    }

    /**
     * Fetch label metadata for a given file number.
     */
    public function labelMetadata(Request $request)
    {
        $fileNumber = trim((string) $request->get('file_number', ''));

        if ($fileNumber === '') {
            return response()->json([
                'success' => false,
                'message' => 'File number is required.',
            ], 422);
        }

        $variants = $this->buildFileNumberVariants($fileNumber);
        $normalized = $this->normalizeFileno($fileNumber);

        if ($normalized !== null) {
            $variants[] = $normalized;
        }

        $variants = array_values(array_unique(array_filter($variants)));

        $normalizedVariants = array_map(static function ($value) {
            return preg_replace('/[^A-Z0-9]/', '', strtoupper($value));
        }, $variants);

        $normalizedVariants = array_values(array_unique(array_filter($normalizedVariants)));

        Log::info('Looking up label metadata', [
            'file_number' => $fileNumber,
            'variants' => $variants,
            'normalized_variants' => $normalizedVariants,
        ]);

        try {
            // Direct check for exact match first
            $directRecord = DB::connection('sqlsrv')
                ->table('print_label_batch_items')
                ->where('file_number', $fileNumber)
                ->first();

            if ($directRecord) {
                Log::info('Found direct match', ['file_number' => $fileNumber]);
                $record = $directRecord;
            } else {
                Log::info('No direct match, trying variants', ['file_number' => $fileNumber]);

                // First try exact matches with a smaller subset
                $records = DB::connection('sqlsrv')
                    ->table('print_label_batch_items')
                    ->select([
                        'id',
                        'file_number',
                        'land_use_type',
                        'shelf_location',
                        'qr_code_data',
                        'created_at',
                    ])
                    ->where(function ($query) use ($variants) {
                        foreach ($variants as $variant) {
                            $query->orWhere('file_number', 'LIKE', '%' . $variant . '%');
                        }
                    })
                    ->orderByDesc('id')
                    ->limit(50)
                    ->get();

                // If no results, try broader search
                if ($records->isEmpty()) {
                    $records = DB::connection('sqlsrv')
                        ->table('print_label_batch_items')
                        ->select([
                            'id',
                            'file_number',
                            'land_use_type',
                            'shelf_location',
                            'qr_code_data',
                            'created_at',
                        ])
                        ->orderByDesc('id')
                        ->limit(5000)
                        ->get();
                }

                Log::info('Retrieved records count', [
                    'count' => $records->count(),
                    'search_file_number' => $fileNumber,
                    'first_few_file_numbers' => $records->take(10)->pluck('file_number')->toArray(),
                ]);

                $record = null;
                
                // First try exact match on file_number
                foreach ($records as $item) {
                    $itemFileNumber = trim((string) $item->file_number);
                    
                    // Check all variants for exact match
                    foreach ($variants as $variant) {
                        if (strcasecmp($itemFileNumber, $variant) === 0) {
                            $record = $item;
                            Log::info('Found match via variant', [
                                'item_file_number' => $itemFileNumber, 
                                'matched_variant' => $variant
                            ]);
                            break 2;
                        }
                    }
                }
            }

            if (! $record) {
                $record = $records->first(function ($item) use ($variants, $normalizedVariants) {
                    $fileNumber = strtoupper(trim((string) $item->file_number));
                    $normalizedFileNumber = preg_replace('/[^A-Z0-9]/', '', $fileNumber);

                    $payload = $this->decodeQrCodeData($item->qr_code_data);
                    $rawPayloadValues = array_filter([
                        $payload['file_number'] ?? null,
                        $payload['fileNumber'] ?? null,
                        $payload['awaiting_fileno'] ?? null,
                        $payload['awaitingFileNo'] ?? null,
                        $payload['awaitingFileNumber'] ?? null,
                    ], static function ($value) {
                        return is_string($value) && trim($value) !== '';
                    });

                    $payloadValues = array_map(static function ($value) {
                        return strtoupper(trim($value));
                    }, $rawPayloadValues);

                    $normalizedPayloadValues = array_map(static function ($value) {
                        return preg_replace('/[^A-Z0-9]/', '', $value);
                    }, $payloadValues);

                $startsWith = static function (string $haystack, string $needle): bool {
                    return strncmp($haystack, $needle, strlen($needle)) === 0;
                };

                foreach ($variants as $variant) {
                    $candidate = strtoupper(trim($variant));
                    if ($candidate === '') {
                        continue;
                    }

                    // Direct match
                    if ($fileNumber === $candidate) {
                        return true;
                    }

                    // Prefix match
                    if ($startsWith($fileNumber, $candidate . '-')) {
                        return true;
                    }

                    // Contains match (for partial file numbers)
                    if (strpos($fileNumber, $candidate) !== false) {
                        return true;
                    }

                    // Check payload
                    if (in_array($candidate, $payloadValues, true)) {
                        return true;
                    }

                    foreach ($payloadValues as $value) {
                        if ($value !== null && ($startsWith($value, $candidate . '-') || strpos($value, $candidate) !== false)) {
                            return true;
                        }
                    }
                }                    foreach ($normalizedVariants as $normalizedVariant) {
                        if ($normalizedVariant === '') {
                            continue;
                        }

                        if ($normalizedFileNumber === $normalizedVariant) {
                            return true;
                        }

                        if (in_array($normalizedVariant, $normalizedPayloadValues, true)) {
                            return true;
                        }
                    }

                    return false;
                });
            }

            if (! $record) {
                Log::info('Label metadata lookup returned no record', [
                    'file_number' => $fileNumber,
                    'variants' => $variants,
                    'normalized_variants' => $normalizedVariants,
                    'total_records_checked' => $records->count(),
                    'sample_file_numbers' => $records->take(10)->pluck('file_number')->toArray(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'No label metadata found for this file number.',
                    'debug' => [
                        'searched_for' => $fileNumber,
                        'variants_tried' => $variants,
                        'total_records' => $records->count(),
                        'sample_records' => $records->take(5)->map(function($r) {
                            return ['id' => $r->id, 'file_number' => $r->file_number];
                        })->toArray()
                    ]
                ], 404);
            }

            Log::info('Found matching record', ['record_id' => $record->id, 'file_number' => $record->file_number]);

            $qrPayload = $this->decodeQrCodeData($record->qr_code_data);

            $trackingId = $qrPayload['tracking_id']
                ?? $qrPayload['trackingId']
                ?? $qrPayload['TrackingId']
                ?? null;

            $landUse = $qrPayload['land_use_type']
                ?? $qrPayload['landUse_type']
                ?? $qrPayload['landUseType']
                ?? $qrPayload['landUse']
                ?? $record->land_use_type
                ?? null;

            $shelfLocationRaw = $qrPayload['shelf_location']
                ?? $qrPayload['shelfLocation']
                ?? $qrPayload['shelf']
                ?? $record->shelf_location
                ?? null;

            $shelfLocation = $this->normalizeShelfLocation($shelfLocationRaw);

            if ($shelfLocation === '') {
                $shelfLocation = null;
            }

            $shelf = null;
            $rack = null;

            if ($shelfLocation !== null) {
                $shelf = substr($shelfLocation, 0, 1) ?: null;
                $rack = strlen($shelfLocation) > 1 ? substr($shelfLocation, 1) : null;

                if ($rack === '') {
                    $rack = null;
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'file_number' => $record->file_number,
                    'tracking_id' => $trackingId,
                    'land_use_type' => $landUse,
                    'land_use_type_label' => $landUse,
                    'shelf' => $shelf,
                    'rack' => $rack,
                    'shelf_location' => $shelfLocation,
                    'shelf_location_label' => $shelfLocationRaw,
                    'qr_code_payload' => $qrPayload,
                    'record_id' => $record->id,
                    'raw_record' => (array) $record,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('TrackFileArchiveController::labelMetadata failed', [
                'file_number' => $fileNumber,
                'variants' => $variants,
                'normalized_variants' => $normalizedVariants,
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve label metadata for the supplied file number.',
            ], 500);
        }
    }

    /**
     * Normalize a file number by removing separators.
     */
    protected function normalizeFileno(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtoupper(trim($value));

        return str_replace(['-', '/', ' ', '\\', '.', ','], '', $normalized);
    }

    /**
     * Build file number variants to improve matching accuracy.
     */
    protected function buildFileNumberVariants(string $fileNumber): array
    {
        $normalized = strtoupper(trim($fileNumber));
        $variants = [$normalized];

        // Remove spaces
        $variants[] = str_replace(' ', '', $normalized);

        // Handle known prefixes (e.g., ST-RES-..., NPFN-RES-...)
        if (preg_match('/^(ST|NPFN|NP|FN|MLS|MLSF|NEWKANGIS|NK)-?(.*)$/', $normalized, $prefixMatches)) {
            $suffix = $prefixMatches[2] ?? '';
            if ($suffix !== '') {
                $variants[] = $suffix;
                $variants[] = str_replace(' ', '', $suffix);
                $variants[] = str_replace('-', '', $suffix);
            }
        }

        // Handle CON-prefixed formats
        if (preg_match('/^(CO?N?-?)([A-Z]{3})-(\d{4})-(\d+)$/', $normalized, $matches)) {
            $prefix = $matches[1];
            $landUse = $matches[2];
            $year = $matches[3];
            $serial = ltrim($matches[4], '0');

            if ($serial !== '') {
                $variants[] = sprintf('%s-%s-%s', $landUse, $year, $serial);
                $variants[] = sprintf('%s-%s-%04d', $landUse, $year, (int) $serial);

                $variants[] = sprintf('%s%s-%s-%s', $prefix, $landUse, $year, $serial);
                $variants[] = sprintf('%s%s-%s-%04d', $prefix, $landUse, $year, (int) $serial);
                $variants[] = sprintf('CON-%s-%s-%s', $landUse, $year, $serial);
                $variants[] = sprintf('CON-%s-%s-%04d', $landUse, $year, (int) $serial);
                $variants[] = sprintf('CON%s-%s-%s', $landUse, $year, $serial);
                $variants[] = sprintf('CON%s-%s-%04d', $landUse, $year, (int) $serial);
                $variants[] = sprintf('CN-%s-%s-%s', $landUse, $year, $serial);
                $variants[] = sprintf('CN-%s-%s-%04d', $landUse, $year, (int) $serial);
                $variants[] = sprintf('C-%s-%s-%s', $landUse, $year, $serial);
                $variants[] = sprintf('C-%s-%s-%04d', $landUse, $year, (int) $serial);
            }
        }

        // Handle standard format
        if (preg_match('/^([A-Z]{3})-(\d{4})-(\d+)$/', $normalized, $matches)) {
            $landUse = $matches[1];
            $year = $matches[2];
            $serial = ltrim($matches[3], '0');

            if ($serial !== '') {
                $variants[] = sprintf('%s-%s-%s', $landUse, $year, $serial);
                $variants[] = sprintf('%s-%s-%04d', $landUse, $year, (int) $serial);
                $variants[] = sprintf('CON-%s-%s-%s', $landUse, $year, $serial);
                $variants[] = sprintf('CON-%s-%s-%04d', $landUse, $year, (int) $serial);
                $variants[] = sprintf('CON%s-%s-%s', $landUse, $year, $serial);
                $variants[] = sprintf('CON%s-%s-%04d', $landUse, $year, (int) $serial);
            }
        }

        // Handle unit suffixes (e.g., ST-RES-2025-1-001)
        $parts = explode('-', $normalized);
        if (count($parts) >= 4) {
            $primaryCandidate = implode('-', array_slice($parts, 0, 3));
            $variants[] = $primaryCandidate;

            if (preg_match('/^([A-Z]{3})-(\d{4})-(\d+)$/', $primaryCandidate, $primaryMatches)) {
                $landUse = $primaryMatches[1];
                $year = $primaryMatches[2];
                $serial = ltrim($primaryMatches[3], '0');

                if ($serial !== '') {
                    $variants[] = sprintf('%s-%s-%s', $landUse, $year, $serial);
                    $variants[] = sprintf('%s-%s-%04d', $landUse, $year, (int) $serial);
                    $variants[] = sprintf('CON-%s-%s-%s', $landUse, $year, $serial);
                    $variants[] = sprintf('CON-%s-%s-%04d', $landUse, $year, (int) $serial);
                }
            }
        }

        // Remove all separators variant
        $variants[] = str_replace('-', '', $normalized);

        if (strpos($normalized, '-') !== false) {
            $variants[] = str_replace('-', '_', $normalized);
            $variants[] = str_replace('-', '/', $normalized);
            $variants[] = str_replace('-', ' ', $normalized);
        }

        return array_values(array_unique(array_filter($variants)));
    }

    /**
     * Decode QR code payload from print label items.
     */
    protected function decodeQrCodeData($value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        $stringValue = trim((string) $value);

        if ($stringValue === '') {
            return [];
        }

        $attempts = [
            $stringValue,
            stripslashes($stringValue),
        ];

        // Handle JSON quoted as a string ("{...}")
        if ($stringValue[0] === '"' && substr($stringValue, -1) === '"') {
            $attempts[] = trim($stringValue, '"');
        }

        foreach ($attempts as $candidate) {
            $decoded = json_decode($candidate, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        Log::warning('Unable to decode qr_code_data payload', [
            'payload_preview' => mb_substr($stringValue, 0, 150),
            'json_error' => json_last_error_msg(),
        ]);

        return [];
    }

    /**
     * Normalize shelf location string (e.g. "A1" or "A-1" -> "A1").
     */
    protected function normalizeShelfLocation($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $stringValue = trim((string) $value);

        if ($stringValue === '') {
            return null;
        }

        // Remove common separators while preserving leading character-order semantics
        $compact = strtoupper(str_replace(['-', ' ', '/', '\\'], '', $stringValue));

        return $compact === '' ? null : $compact;
    }

    /**
     * Escape SQL LIKE special characters.
     */
    protected function escapeLike(string $value): string
    {
        return str_replace(
            ['%', '_', '['],
            ['[%]', '[_]', '[[]'],
            $value
        );
    }
}
