<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Arr;
use Carbon\Carbon;
use App\Models\FileIndexing;
use App\Models\FileIndexingLink;
use App\Models\FileSnapshot;
use App\Models\MasterDcivLink;
use App\Models\Grouping;
use App\Models\Entity;
use App\Models\Customer;
use App\Models\FileTracker;
use App\Models\FileSearchRequest;
use App\Services\CommissioningMirrorService;
use App\Services\KangisFileNoPlaceholderService;
use App\Services\KangisParentLinkService;
use App\Services\FileLocationResolver;
use Illuminate\Validation\Rule;
use App\Services\PropertyIdAllocationService;
use App\Services\FileIndexingBillService;
use App\Services\IndexingStorageSummaryService;
use App\Services\FileRangeTrackingService;
use App\Services\EdmsScanUploadFolderService;
use App\Services\FileSnapshotService;

class FileIndexingController extends Controller
{
    private const COFO_TABLE = 'CofO_staging';

    /** PropID_Master's pre-normalised (UPPER + trimmed) identifier columns. */
    private const MASTER_ALIAS_COLUMNS = [
        'primary_file_number_norm',
        'mlsFNo_norm',
        'kangisFileNo_norm',
        'NewKANGISFileno_norm',
        'temp_fileno_norm',
    ];

    private PropertyIdAllocationService $propertyIdAllocationService;
    private CommissioningMirrorService $commissioningMirrorService;
    private FileIndexingBillService $fileIndexingBillService;
    private KangisParentLinkService $kangisParentLinkService;
    private IndexingStorageSummaryService $indexingStorageSummaryService;
    private FileRangeTrackingService $fileRangeTrackingService;
    private EdmsScanUploadFolderService $edmsScanUploadFolderService;
    private FileSnapshotService $fileSnapshotService;

    public function __construct(
        PropertyIdAllocationService $propertyIdAllocationService,
        CommissioningMirrorService $commissioningMirrorService,
        FileIndexingBillService $fileIndexingBillService,
        KangisParentLinkService $kangisParentLinkService,
        IndexingStorageSummaryService $indexingStorageSummaryService,
        FileRangeTrackingService $fileRangeTrackingService,
        EdmsScanUploadFolderService $edmsScanUploadFolderService,
        FileSnapshotService $fileSnapshotService
    ) {
        $this->propertyIdAllocationService = $propertyIdAllocationService;
        $this->commissioningMirrorService = $commissioningMirrorService;
        $this->fileIndexingBillService = $fileIndexingBillService;
        $this->kangisParentLinkService = $kangisParentLinkService;
        $this->indexingStorageSummaryService = $indexingStorageSummaryService;
        $this->fileRangeTrackingService = $fileRangeTrackingService;
        $this->edmsScanUploadFolderService = $edmsScanUploadFolderService;
        $this->fileSnapshotService = $fileSnapshotService;
    }

    /**
     * Coerce empty-string bill values into null before they reach the
     * file_indexings numeric/date columns (SQL Server rejects '' for those
     * types). The repeater may submit '' for the primary row when blank.
     */
    protected function normalizeBillColumns(array $data): array
    {
        $nullable = [
            'bill_total_amount', 'bill_from_year', 'bill_to_year', 'bill_receipt_date',
            'ground_rent_amount', 'ground_rent_from_year', 'ground_rent_to_year', 'ground_rent_receipt_date',
        ];

        foreach ($nullable as $key) {
            if (array_key_exists($key, $data) && ($data[$key] === '' || $data[$key] === [])) {
                $data[$key] = null;
            }
        }

        return $data;
    }

    protected function resolveCurrentUserName(): string
    {
        $user = Auth::user();

        if (!$user) {
            return 'System';
        }

        $first = trim((string) ($user->first_name ?? ''));
        $last = trim((string) ($user->last_name ?? ''));
        $full = trim($first . ' ' . $last);

        if ($full !== '') {
            return $full;
        }

        return $user->name ?? $user->email ?? 'Unknown User';
    }
    protected function normalizeFileno(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtoupper(trim($value));
        return str_replace(['-', '/', ' ', '\\', '.', ','], '', $normalized);
    }

    protected function isSurveyRegistry(?string $registry): bool
    {
        if ($registry === null) {
            return false;
        }

        return str_contains(strtoupper($registry), 'SURVEY');
    }

    protected function normalizeSurveyAwaitingCore(?string $normalizedFileno): ?string
    {
        if (!$normalizedFileno) {
            return null;
        }

        // Survey grouping can carry equivalent awaiting prefixes for the same sequence.
        // Match by numeric/core segment across all supported prefix aliases.
        foreach (['MISCSKN', 'MISC KN', 'LPKN', 'GKN', 'MISC'] as $prefix) {
            if (str_starts_with($normalizedFileno, $prefix)) {
                $core = substr($normalizedFileno, strlen($prefix));
                return $core !== '' ? $core : $normalizedFileno;
            }
        }

        return $normalizedFileno;
    }

    protected function matchesGroupingAwaitingFileNumber(?string $normalizedFileNumber, ?string $normalizedGroupingAwaiting, ?string $registry): bool
    {
        if (!$normalizedFileNumber || !$normalizedGroupingAwaiting) {
            return false;
        }

        if ($normalizedFileNumber === $normalizedGroupingAwaiting) {
            return true;
        }

        if (!$this->isSurveyRegistry($registry)) {
            return false;
        }

        return $this->normalizeSurveyAwaitingCore($normalizedFileNumber) === $this->normalizeSurveyAwaitingCore($normalizedGroupingAwaiting);
    }

    protected function normalizeChoiceValue($value)
    {
        if (is_array($value)) {
            return array_map([$this, 'normalizeChoiceValue'], $value);
        }

        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        $placeholderValues = [
            'select district',
            'select district name',
            'select lga',
            'select lga name',
            'loading districts...',
            'loading lgas...',
            'loading district...',
            'loading lga...',
        ];

        return in_array(strtolower($trimmed), $placeholderValues, true) ? null : $trimmed;
    }

    protected function resolveDistrictChoice($district, $customDistrict)
    {
        if (is_array($district)) {
            $resolved = [];
            foreach ($district as $index => $value) {
                $customValue = is_array($customDistrict) ? ($customDistrict[$index] ?? null) : $customDistrict;
                $resolved[] = $this->resolveDistrictChoice($value, $customValue);
            }
            return $resolved;
        }

        if ($district === null) {
            return null;
        }

        $districtText = trim((string) $district);
        if ($districtText === '') {
            return null;
        }

        if (strtolower($districtText) !== 'other') {
            return $districtText;
        }

        $customText = trim((string) ($customDistrict ?? ''));
        return $customText !== '' ? $customText : $districtText;
    }



    protected function buildFileNumberVariants(string $fileNumber): array
    {
        $normalized = strtoupper(trim($fileNumber));
        $variants = [$normalized];

        // Temporary file number support: e.g. "RES-2026-1(T)" should also match
        // the base "RES-2026-1" when looking up indexing / registry records.
        $stripped = preg_replace('/\s*\(\s*T\s*\)\s*$/i', '', $normalized);
        if ($stripped !== null && $stripped !== '' && $stripped !== $normalized) {
            $variants[] = $stripped;
        }
        $baseForExpansion = $stripped !== null && $stripped !== '' ? $stripped : $normalized;

        // Remove spaces
        $variants[] = str_replace(' ', '', $normalized);
        $variants[] = str_replace(' ', '', $baseForExpansion);

        // Handle structured formats like RES-YYYY-#### or CON-RES-YYYY-####
        if (preg_match('/^(?:(CO?N?|CN|C)-?)?([A-Z]{3})-(\d{4})-(\d+)$/', $baseForExpansion, $matches)) {
            $prefix = strtoupper((string) ($matches[1] ?? ''));
            $landUse = $matches[2];
            $year = $matches[3];
            $serialRaw = $matches[4];

            $serialTrimmed = ltrim($serialRaw, '0');
            if ($serialTrimmed === '') {
                $serialTrimmed = '0';
            }

            $serialInt = (int) $serialRaw;
            $serialVariants = array_values(array_unique(array_filter([
                $serialRaw,
                $serialTrimmed,
                (string) $serialInt,
                str_pad((string) $serialInt, max(strlen($serialRaw), 4), '0', STR_PAD_LEFT),
            ], static function ($value) {
                return $value !== null && $value !== '';
            })));

            $normalizedPrefix = rtrim($prefix, '-');
            $baseWithDash = $normalizedPrefix !== ''
                ? sprintf('%s-%s-%s', $normalizedPrefix, $landUse, $year)
                : sprintf('%s-%s', $landUse, $year);
            $baseTight = $normalizedPrefix !== ''
                ? sprintf('%s%s-%s', $normalizedPrefix, $landUse, $year)
                : null;

            foreach ($serialVariants as $serialVariant) {
                $variants[] = sprintf('%s-%s', $baseWithDash, $serialVariant);

                if ($baseTight) {
                    $variants[] = sprintf('%s-%s', $baseTight, $serialVariant);
                }
            }
        }

        // Remove all separators variant
        $variants[] = str_replace('-', '', $normalized);
        $variants[] = str_replace('-', '', $baseForExpansion);

        // Add separator variants
        if (strpos($normalized, '-') !== false) {
            $variants[] = str_replace('-', '_', $normalized);
            $variants[] = str_replace('-', '/', $normalized);
            $variants[] = str_replace('-', ' ', $normalized);
        }
        if (strpos($baseForExpansion, '-') !== false) {
            $variants[] = str_replace('-', '_', $baseForExpansion);
            $variants[] = str_replace('-', '/', $baseForExpansion);
            $variants[] = str_replace('-', ' ', $baseForExpansion);
        }

        return array_values(array_unique(array_filter($variants)));
    }

    protected function lookupTrackerFallback(array $variants, array $normalizedVariants): ?array
    {
        $upperVariants = array_values(array_unique(array_filter(array_map(static function ($value) {
            return strtoupper(trim((string) $value));
        }, $variants))));

        $normalizedSet = array_values(array_unique(array_filter($normalizedVariants)));

        if (empty($upperVariants) && empty($normalizedSet)) {
            return null;
        }

        $trackerQuery = FileTracker::query()
            ->where(function ($query) use ($upperVariants, $normalizedSet) {
                $added = false;

                foreach ($upperVariants as $variant) {
                    if ($variant === '') {
                        continue;
                    }

                    if (!$added) {
                        $query->whereRaw('UPPER(file_number) = ?', [$variant]);
                        $added = true;
                    } else {
                        $query->orWhereRaw('UPPER(file_number) = ?', [$variant]);
                    }
                }

                foreach ($normalizedSet as $normalized) {
                    if ($normalized === '') {
                        continue;
                    }

                    $expression = "REPLACE(REPLACE(REPLACE(UPPER(file_number), '-', ''), '/', ''), ' ', '')";

                    if (!$added) {
                        $query->whereRaw("{$expression} = ?", [$normalized]);
                        $added = true;
                    } else {
                        $query->orWhereRaw("{$expression} = ?", [$normalized]);
                    }
                }
            })
            ->orderByDesc('updated_at');

        $tracker = $trackerQuery->first();

        if (!$tracker || empty($tracker->tracking_id)) {
            return null;
        }

        return [
            'id' => $tracker->id,
            'file_number' => $tracker->file_number,
            'file_name' => $tracker->file_title ?? $tracker->file_name ?? null,
            'tracking_id' => $tracker->tracking_id,
        ];
    }

    public function getCofORecord(string $fileNo)
    {
        $fileNumber = trim($fileNo);

        if ($fileNumber === '') {
            return response()->json([
                'success' => false,
                'message' => 'File number is required.'
            ], 422);
        }

        $variants = $this->buildFileNumberVariants($fileNumber);

        $columns = $this->getAvailableCofOColumns();
        if (empty($columns)) {
            Log::warning('CofO lookup aborted: no searchable columns defined.');

            return response()->json([
                'success' => false,
                'message' => 'CofO lookup is unavailable because the target table has no searchable columns configured.',
            ], 500);
        }

        $schema = Schema::connection('sqlsrv');

        $selectColumns = [
            'serialNo',
            'pageNo',
            'volumeNo',
            'regNo',
            'cofo_no',
            'transaction_date',
            'transaction_time',
            'cofo_date',
            'land_use',
            'cofo_type',
            'property_description',
        ];

        $resolveColumn = static function ($columnCandidates) use ($schema) {
            foreach ($columnCandidates as $candidate) {
                try {
                    if ($schema->hasColumn(self::COFO_TABLE, $candidate)) {
                        return $candidate;
                    }
                } catch (\Throwable $e) {
                    Log::debug('CofO optional column lookup failed', [
                        'column' => $candidate,
                        'message' => $e->getMessage(),
                    ]);
                }
            }

            return null;
        };

        $grantorColumn = $resolveColumn(['Grantor', 'grantor']);
        $granteeColumn = $resolveColumn(['Grantee', 'grantee']);

        if ($grantorColumn) {
            $selectColumns[] = DB::raw('[' . $grantorColumn . '] as grantor');
        }

        if ($granteeColumn) {
            $selectColumns[] = DB::raw('[' . $granteeColumn . '] as grantee');
        }

        $certificateColumn = $resolveColumn(['certificate_date', 'certificateDate']);
        if ($certificateColumn && !in_array($certificateColumn, $selectColumns, true)) {
            $selectColumns[] = $certificateColumn;
        }

        $record = DB::connection('sqlsrv')
            ->table(self::COFO_TABLE)
            ->select($selectColumns)
            ->where(function ($query) use ($variants, $columns) {
                foreach ($columns as $column) {
                    $query->orWhereIn($column, $variants);
                }
            })
            ->orderByDesc(DB::raw('ISNULL(transaction_date, transaction_date)'))
            ->first();

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => 'No CofO record found for this file number.'
            ]);
        }

        $serial = $record->serialNo ?? null;
        $page = $record->pageNo ?? $record->reg_page ?? $serial;
        $volume = $record->volumeNo ?? $record->reg_volume ?? null;

        $transaction_dateRaw = $record->transaction_date ?? $record->transaction_date ?? null;
        $transaction_date = null;
        $transactionTime = null;

        if ($transaction_dateRaw) {
            try {
                $parsed = Carbon::parse($transaction_dateRaw);
                $transaction_date = $parsed->format('Y-m-d');
                $transactionTime = $parsed->format('H:i');
            } catch (\Throwable $e) {
                $transaction_date = $transaction_dateRaw;
            }
        }

        if (!$transactionTime && !empty($record->transaction_time)) {
            $transactionTime = substr($record->transaction_time, 0, 5);
        }

        $certificateDateRaw = $record->certificate_date ?? $record->certificateDate ?? $record->cofo_date ?? null;
        $certificateDate = null;

        if ($certificateDateRaw) {
            try {
                $certificateDate = Carbon::parse($certificateDateRaw)->format('Y-m-d');
            } catch (\Throwable $e) {
                $certificateDate = $certificateDateRaw;
            }
        }

        $normalizeParty = static function ($value) {
            if ($value === null) {
                return null;
            }

            $stringValue = is_string($value) ? trim($value) : trim((string) $value);

            return $stringValue === '' ? null : $stringValue;
        };

        $grantor = $normalizeParty($record->grantor ?? $record->Grantor ?? null);
        $grantee = $normalizeParty($record->grantee ?? $record->Grantee ?? null);

        return response()->json([
            'success' => true,
            'data' => [
                'serial_no' => $serial,
                'page_no' => $page,
                'volume_no' => $volume,
                'transaction_date' => $transaction_date,
                'transaction_time' => $transactionTime,
                'cofo_no' => $record->cofo_no,
                'certificate_date' => $certificateDate,
                'reg_no' => $record->regNo ?? ($serial && $page && $volume ? sprintf('%s/%s/%s', $serial, $page, $volume) : null),
                'land_use' => $record->land_use,
                'cofo_type' => $record->cofo_type,
                'property_description' => $record->property_description,
                'grantor' => $grantor,
                'grantee' => $grantee,
            ],
        ]);
    }

    protected function getAvailableCofOColumns(): array
    {
        static $availableColumns = null;

        if ($availableColumns !== null) {
            return $availableColumns;
        }

        $candidates = [
            'mlsFNo',
            'mlsfNo',
            'kangisFileNo',
            'NewKANGISFileNo',
            'NewKANGISFileno',
            'FileNo',
            'NewFileNo',
            'np_fileno',
            'cofo_no',
            'temp_fileno',
        ];

        $schema = Schema::connection('sqlsrv');

        $availableColumns = array_values(array_filter($candidates, static function ($column) use ($schema) {
            try {
                return $schema->hasColumn(self::COFO_TABLE, $column);
            } catch (\Throwable $e) {
                Log::warning('Failed checking CofO column availability', [
                    'column' => $column,
                    'message' => $e->getMessage(),
                ]);
                return false;
            }
        }));

        return $availableColumns;
    }


    /**
     * Get leaderboard data for the modal
     */
    public function leaderboardData(Request $request)
    {
        try {
            // Reuse the activity log's optimized, correct report builder so the
            // leaderboard figures match the page exactly and we avoid the slow
            // OR-join full table scan that previously timed out.
            $groups = app(FileIndexingActivityLogController::class)->report($request);

            // $groups is already sorted by total weight (most productive first).
            $leaderboard = collect($groups)
                ->take(10)
                ->map(function ($group) {
                    return [
                        'name' => $group['user'],
                        'files' => (int) $group['files'],
                        'indexed' => (int) $group['indexed'],
                        'transactions' => (int) $group['transactions'],
                        'weight' => rtrim(rtrim(number_format((float) $group['weight'], 1), '0'), '.'),
                    ];
                })
                ->values();

            return response()->json($leaderboard);

        } catch (\Throwable $e) {
            Log::error('Failed to fetch leaderboard data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to load leaderboard data'
            ], 500);
        }
    }

    /**
     * Display the specified file indexing record.
     */
    public function show($id)
    {
        try {
            $record = DB::connection('sqlsrv')->table('file_indexings')->where('id', $id)->first();
        } catch (\Throwable $e) {
            $record = null;
        }

        if (!$record) {
            return redirect()->route('fileindex.index')->with('error', 'File indexing record not found.');
        }

        // If a dedicated view exists, render it; otherwise, return to index with context
        if (view()->exists('fileindexing.show')) {
            return view('fileindexing.show', compact('record'));
        }

        return redirect()->route('fileindex.index')
            ->with('success', 'Opened file indexing record.')
            ->with('file_indexing_id', $id);
    }

    /**
     * Show the form for editing the specified file indexing record.
     */
    public function edit($id)
    {
        try {
            $record = DB::connection('sqlsrv')->table('file_indexings')->where('id', $id)->first();

            if (!$record) {
                // Try to handle ID mismatch: user might be passing a fileNumber ID
                $fileNumberRecord = DB::connection('sqlsrv')->table('fileNumber')->where('id', $id)->first();

                if ($fileNumberRecord) {
                    // Check if there is an existing indexing record for this file number
                    $existingIndex = DB::connection('sqlsrv')->table('file_indexings')
                        ->where('file_number', $fileNumberRecord->mlsfNo)
                        ->first();

                    if ($existingIndex) {
                        return redirect()->route('fileindexing.edit', $existingIndex->id)
                            ->with('info', 'Redirected to the correct indexing record for this file number.');
                    }

                    // If no indexing record exists, allow creating one
                    return redirect()->route('fileindexing.create', ['fileno' => $fileNumberRecord->mlsfNo])
                        ->with('info', 'File not indexed yet. You can create the index now.');
                }

                return redirect()->route('fileindex.index')->with('error', 'File indexing record not found.');
            }

            // Older indexing rows may miss tracking_id even when grouping has it.
            // Resolve and backfill here so users can see tracking_id immediately on edit.
            if (empty($record->tracking_id) && !empty($record->file_number)) {
                $groupingTable = $this->resolveGroupingTableForRename(
                    $record->general_registry ?? null,
                    (string) $record->file_number,
                    (string) $record->file_number
                );

                if ($groupingTable) {
                    $resolvedTrackingId = $this->lookupTrackingIdFromGrouping($groupingTable, (string) $record->file_number);

                    if (!empty($resolvedTrackingId)) {
                        $record->tracking_id = $resolvedTrackingId;

                        DB::connection('sqlsrv')->table('file_indexings')
                            ->where('id', $record->id)
                            ->update(['tracking_id' => $resolvedTrackingId]);

                        if (
                            Schema::connection('sqlsrv')->hasTable('fileNumber') &&
                            Schema::connection('sqlsrv')->hasColumn('fileNumber', 'tra')
                        ) {
                            DB::connection('sqlsrv')->table('fileNumber')
                                ->where(function ($query) use ($record) {
                                    $query->whereRaw('UPPER(LTRIM(RTRIM(mlsfNo))) = UPPER(?)', [(string) $record->file_number])
                                        ->orWhereRaw('UPPER(LTRIM(RTRIM(kangisFileNo))) = UPPER(?)', [(string) $record->file_number])
                                        ->orWhereRaw('UPPER(LTRIM(RTRIM(NewKANGISFileNo))) = UPPER(?)', [(string) $record->file_number]);
                                })
                                ->where(function ($query) {
                                    $query->whereNull('tra')->orWhere('tra', '');
                                })
                                ->update(['tra' => $resolvedTrackingId]);
                        }
                    }
                }
            }

            // Fetch related links
            $relatedLinks = DB::connection('sqlsrv')->table('file_indexing_links')
                ->where('file_indexing_id', $id)
                ->get();

            // Base related details from the link table, keyed by file number so the
            // DCIV / indexed backfills below can extend or enrich them without dupes.
            $relatedDetails = $relatedLinks->map(function ($link) {
                return [
                    'file_number' => $link->file_number,
                    'related_fileno' => $link->file_number,
                    'file_title' => $link->file_title,
                    'plot_number' => $link->plot_number,
                    'tp_no' => $link->tp_no,
                    'lpkn_no' => $link->lpkn_no,
                    'location' => $link->location,
                    'entity_type' => $link->entity_type ?? null,
                    'entity_name' => $link->entity_name ?? null,
                    'customer_name' => $link->customer_name ?? null,
                    'land_use_type' => $link->land_use_type ?? null,
                    'district' => $link->district ?? null,
                    'lga' => $link->lga ?? null,
                    'mfile' => $link->mfile ?? null
                ];
            })->keyBy(function ($d) {
                return strtoupper(trim((string) $d['file_number']));
            })->all();

            // DCIV backfill: a DCIV's related files are also recorded in master_dciv_links,
            // keyed by the DCIV number. For temp DCIVs the number lives in temp_file_no
            // (e.g. "DCIV-2025-545(T)"), so fall back to it when file_number is null.
            $dcivKey = trim((string) ($record->file_number ?? '')) !== ''
                ? trim((string) $record->file_number)
                : trim((string) ($record->temp_file_no ?? ''));

            if ($dcivKey !== '') {
                $masterLinks = DB::connection('sqlsrv')->table('master_dciv_links')
                    ->whereRaw('UPPER(LTRIM(RTRIM(dciv_file_number))) = ?', [strtoupper($dcivKey)])
                    ->whereNotNull('related_file_number')
                    ->get();

                foreach ($masterLinks as $ml) {
                    $relNo = trim((string) $ml->related_file_number);
                    if ($relNo === '') {
                        continue;
                    }
                    $key = strtoupper($relNo);

                    if (!isset($relatedDetails[$key])) {
                        $relatedDetails[$key] = [
                            'file_number' => $relNo,
                            'related_fileno' => $relNo,
                            'file_title' => $ml->related_file_title,
                            'plot_number' => null,
                            'tp_no' => null,
                            'lpkn_no' => null,
                            'location' => null,
                            'entity_type' => null,
                            'entity_name' => null,
                            'customer_name' => null,
                            'land_use_type' => null,
                            'district' => null,
                            'lga' => null,
                            'mfile' => null,
                        ];
                    } elseif (empty($relatedDetails[$key]['file_title']) && !empty($ml->related_file_title)) {
                        $relatedDetails[$key]['file_title'] = $ml->related_file_title;
                    }
                }
            }

            // For every related file that is itself indexed, backfill the detail fields
            // the link row left blank from its own file_indexings record.
            foreach ($relatedDetails as $key => $detail) {
                $indexed = $this->indexedRelatedDetails((string) $detail['file_number']);
                if (!$indexed) {
                    continue;
                }
                foreach (['file_title', 'location', 'plot_number', 'tp_no', 'lpkn_no'] as $field) {
                    if (trim((string) ($detail[$field] ?? '')) === '' && !empty($indexed[$field])) {
                        $relatedDetails[$key][$field] = $indexed[$field];
                    }
                }
            }

            // Attach related details as an array for the JS
            $record->related_details = array_values($relatedDetails);

            // Prepare CofO Details
            $cofoDetails = $this->prepareCofODetailsForEdit($record);

            // Enrich record with RoFO details from PRA table
            if ($record->has_rofo ?? false) {
                $rofoDetails = $this->prepareRofoDetailsForEdit($record);
                foreach ($rofoDetails as $key => $value) {
                    $record->$key = $value;
                }
            }

            // Enrich record with Occupancy Permit details from PRA table
            if ($record->has_occupancy_permit ?? false) {
                $occupancyPermitDetails = $this->prepareOccupancyPermitDetailsForEdit($record);
                foreach ($occupancyPermitDetails as $key => $value) {
                    $record->$key = $value;
                }
            }

            // Fetch dynamic registries
            $registries = \App\Models\Registry::orderBy('name')->get();
            $lgas = \App\Models\Lga::orderBy('name')->pluck('name');
            $districts = \App\Models\District::orderBy('name')->pluck('name');
            $physicalRegistries = \App\Models\PhysicalRegistry::orderBy('name')->get();
            $landUseTypes = \App\Models\LandUseType::orderBy('name')->get()->pluck('name', 'name');

            // Bill Balance & Ground Rent — authoritative multi-row store (file_indexing_bills).
            // Split by type so the billing section can prefill each repeater. When no multi-row
            // records exist the section falls back to the primary single columns on the record.
            $allIndexingBills = \App\Models\FileIndexingBill::on('sqlsrv')
                ->where('file_indexing_id', $record->id)
                ->orderBy('id')
                ->get();
            // Pass null (not an empty Collection) when a type has no multi-row records, so the
            // billing section's `!empty(...)` check correctly falls back to the record's single
            // bill_* / ground_rent_* columns. (empty() on a Collection object is always false.)
            $billBalances = $allIndexingBills
                ->where('bill_type', \App\Models\FileIndexingBill::TYPE_BILL_BALANCE)
                ->values();
            $billBalances = $billBalances->isNotEmpty() ? $billBalances : null;
            $grantRents = $allIndexingBills
                ->where('bill_type', \App\Models\FileIndexingBill::TYPE_GRANT_RENT)
                ->values();
            $grantRents = $grantRents->isNotEmpty() ? $grantRents : null;

            // Check if property records exist (CofO, PRA, or History).
            // For temp-only records (file_number = null) use temp_file_no as the lookup key.
            $lookupFileNo = trim((string) ($record->file_number ?? ''));
            $lookupTempNo = trim((string) ($record->temp_file_no ?? ''));
            $effectiveLookupNo = $lookupFileNo !== '' ? $lookupFileNo : $lookupTempNo;

            $hasPropertyRecords = !empty($cofoDetails) || ($effectiveLookupNo !== '' && (
                DB::connection('sqlsrv')->table('pra')->where('mlsFNo', $effectiveLookupNo)->exists() ||
                DB::connection('sqlsrv')->table('file_history_staging')
                    ->where('mlsFNo', $effectiveLookupNo)
                    ->orWhere('fileno', $effectiveLookupNo)
                    ->exists()
            ));

            // "Has Transaction" (New KANGIS): rows already saved for the KN-series file,
            // shown read-only under the checkbox so they are never re-keyed.
            $newKangisTransactions = $this->fetchNewKangisTransactionsForEdit(
                (string) ($record->new_kangis_file_no ?? '')
            );

            // Title Status / Title Status Update ticks already recorded for this file.
            // They are not columns on file_indexings — they live in
            // title_status_applications and the Parcel Update tables — so without this
            // the update screen opened with every box clear, and since the form makes
            // the selection mandatory, each edit forced a re-pick that wrote a duplicate
            // application row.
            $titleStatusSelection = app(\App\Services\FileIndexingTitleStatusLookup::class)
                ->forFileNumber($lookupFileNo, $lookupTempNo);

            // Return the original Edit View as requested
            $PageTitle = 'Edit File Indexing';
            $PageDescription = '';

            return view('fileindexing.edit', compact(
                'record',
                'PageTitle',
                'PageDescription',
                'lgas',
                'registries',
                'districts',
                'physicalRegistries',
                'landUseTypes',
                'cofoDetails',
                'hasPropertyRecords',
                'billBalances',
                'grantRents',
                'newKangisTransactions',
                'titleStatusSelection'
            ))->with('fileIndexing', $record);
        } catch (\Throwable $e) {
            return redirect()->route('fileindex.index')->with('error', 'Error loading record: ' . $e->getMessage());
        }
    }

    /**
     * Prepare CofO Details for Edit
     */
    protected function prepareCofODetailsForEdit($fileIndexing): array
    {
        $fileNumber = trim((string) ($fileIndexing->file_number ?? ''));
        $tempFileNo = trim((string) ($fileIndexing->temp_file_no ?? ''));

        if ($fileNumber === '' && $tempFileNo === '') {
            return [];
        }

        $hasTempFileno = Schema::connection('sqlsrv')->hasColumn(self::COFO_TABLE, 'temp_fileno');

        // Try CofO_staging first (the definitive source for processed files)
        $record = DB::connection('sqlsrv')->table(self::COFO_TABLE)
            ->where(function ($query) use ($fileNumber, $tempFileNo, $hasTempFileno) {
                $added = false;
                if ($fileNumber !== '') {
                    $query->where('mlsFNo', $fileNumber)
                        ->orWhere('fileno', $fileNumber)
                        ->orWhere('kangisFileNo', $fileNumber)
                        ->orWhere('NewKANGISFileno', $fileNumber);
                    $added = true;
                }
                // For temp-only records look up by temp_fileno column when available
                if ($tempFileNo !== '' && $hasTempFileno) {
                    if ($added) {
                        $query->orWhere('temp_fileno', $tempFileNo);
                    } else {
                        $query->where('temp_fileno', $tempFileNo);
                    }
                }
            })
            ->first();

        // Fallback to legacy deed_registrations if not in staging
        if (!$record && $fileNumber !== '') {
            $record = DB::connection('sqlsrv')->table('deed_registrations')
                ->where('fileno', $fileNumber)
                ->first();
        }

        if (!$record) {
            return [];
        }

        return [
            'cofo_no' => $record->cofo_no ?? null,
            'cofo_instrument_type' => $record->instrument_type ?? $record->cofo_type ?? $record->transaction_type ?? null,
            'cofo_date' => $this->formatDateForForm($record->cofo_date ?? $record->transaction_date ?? null),
            'cofo_serial_no' => $record->serialNo ?? $record->serial_no ?? null,
            'cofo_page_no' => $record->pageNo ?? $record->page_no ?? ($record->serialNo ?? null),
            'cofo_vol_no' => $record->volumeNo ?? $record->volume_no ?? null,
            'cofo_deeds_time' => $this->formatTimeForForm($record->transaction_time ?? $record->deeds_time ?? $record->reg_time ?? null),
            'cofo_deeds_date' => $this->formatDateForForm($record->deeds_date ?? $record->transaction_date ?? $record->reg_date ?? null),
            'transaction_date' => $this->formatDateForForm($record->transaction_date ?? null),
            'cofo_first_party' => $record->Grantor ?? $record->grantor ?? $record->party_1 ?? null,
            'cofo_second_party' => $record->Grantee ?? $record->grantee ?? $record->party_2 ?? null,
            'cofo_land_use' => $record->land_use ?? null,
            'cofo_period' => $record->period ?? null,
            'cofo_period_unit' => $record->period_unit ?? null,
            'cofo_status' => $record->status ?? 'Active',
        ];
    }

    /**
     * Format Date for Form
     */
    protected function formatDateForForm($date)
    {
        if (empty($date))
            return null;
        try {
            return \Carbon\Carbon::parse($date)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Format Time for Form
     */
    protected function formatTimeForForm($time)
    {
        if (empty($time))
            return null;
        try {
            return \Carbon\Carbon::parse($time)->format('H:i');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Prepare RoFO Details for Edit from PRA table
     */
    protected function prepareRofoDetailsForEdit($fileIndexing): array
    {
        $fileNumber = $fileIndexing->file_number ?? null;
        if (empty($fileNumber)) {
            return [];
        }

        $record = DB::connection('sqlsrv')->table('pra')
            ->where('mlsFNo', $fileNumber)
            ->whereIn('instrument_type', ['Right of Occupancy', 'Statutory Right of Occupancy', 'Customary Right of Occupancy'])
            ->first();

        if (!$record) {
            return [];
        }

        return [
            'rofo_instrument_type' => $record->instrument_type ?? null,
            'rofo_date' => $this->formatDateForForm($record->transaction_date ?? null),
            'rofo_number' => $record->rofo_number ?? null,
            'rofo_file_number' => $record->mlsFNo ?? $fileNumber,
            'rofo_land_use' => $record->land_use ?? null,
            'rofo_grantor' => $record->party_1 ?? null,
            'rofo_grantee' => $record->party_2 ?? null,
            'rofo_status' => $record->status ?? 'Active',
        ];
    }

    /**
     * Prepare Occupancy Permit Details for Edit.
     *
     * Occupancy Permit rows live in the `pra` instruments table, distinguished by
     * instrument_type. Read the row back so the indexing form repopulates on edit.
     */
    protected function prepareOccupancyPermitDetailsForEdit($fileIndexing): array
    {
        $fileNumber = $fileIndexing->file_number ?? null;
        if (empty($fileNumber)) {
            return [];
        }

        $record = DB::connection('sqlsrv')->table('pra')
            ->where('mlsFNo', $fileNumber)
            ->where('instrument_type', 'Occupancy Permit')
            ->first();

        if (!$record) {
            return [];
        }

        return [
            'occupancy_permit_instrument_type' => $record->instrument_type ?? 'Occupancy Permit',
            'occupancy_permit_op_type' => $record->op_type ?? null,
            'occupancy_permit_op_category' => $record->instrument_category ?? null,
            'occupancy_permit_op_serial_number' => $record->op_serial_number ?? null,
            'occupancy_permit_date' => $this->formatDateForForm($record->transaction_date ?? null),
            'occupancy_permit_file_number' => $record->mlsFNo ?? $fileNumber,
            'occupancy_permit_land_use' => $record->land_use ?? null,
            'occupancy_permit_grantor' => $record->party_1 ?? null,
            'occupancy_permit_grantee' => $record->party_2 ?? null,
            'occupancy_permit_status' => $record->status ?? 'Normal',
            'occupancy_permit_serial_no' => $record->serialNo ?? null,
            'occupancy_permit_page_no' => $record->pageNo ?? null,
            'occupancy_permit_vol_no' => $record->volumeNo ?? null,
            'occupancy_permit_deeds_time' => $this->formatTimeForForm($record->deeds_time ?? null),
            'occupancy_permit_deeds_date' => $this->formatDateForForm($record->deeds_date ?? null),
        ];
    }

    /**
     * Update the specified file indexing record.
     */
    public function update(Request $request, $id)
    {
        try {
            $request->merge([
                'district' => $this->resolveDistrictChoice(
                    $request->input('district'),
                    $request->input('custom_district')
                ),
                'street_name' => $this->resolveDistrictChoice(
                    $request->input('street_name'),
                    $request->input('custom_street_name')
                ),
            ]);

            // Find the existing record
            $existingRecord = DB::connection('sqlsrv')->table('file_indexings')->where('id', $id)->first();

            if (!$existingRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'File indexing record not found.'
                ], 404);
            }

            // Handle bulk entries by returning error for this context
            if ($request->has('bulk_entries')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bulk entry updates are not supported through this endpoint. Please use individual updates.'
                ], 422);
            }

            $validated = $request->validate([
                'file_number' => 'required|string|max:255',
                'file_title' => 'required', // Allow array or string
                // Land Use Type is required (allow array for Block indexing or string).
                'land_use_type' => ['required'],
                'plot_number' => 'nullable',
                'tp_no' => 'nullable',
                'lpkn_no' => 'nullable',
                'location' => 'nullable',
                'latitude' => 'nullable',
                'latitude.*' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable',
                'longitude.*' => 'nullable|numeric|between:-180,180',
                'district' => 'nullable',
                'registry' => 'nullable|string|max:255',
                'physical_registry' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::notIn(['', 'Select Registry']),
                ],
                'general_registry' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::notIn(['', 'Select Registry']),
                ],
                'registry_batch_no' => 'nullable|string|max:255',
                'lga' => 'nullable',
                'related_fileno' => 'nullable',
                'has_cofo' => 'nullable|boolean',
                'has_transaction' => 'nullable|boolean',
                'is_problematic' => 'nullable|boolean',
                'is_co_owned_plot' => 'nullable|boolean',
                'indexing_type' => 'nullable|string|in:Regular,Block',
                'file_classification' => 'nullable|string|max:50',
                'file_classification_remarks' => 'nullable|string',
                'shelf_label_id' => 'nullable|integer',
                'is_merged' => 'nullable|boolean',
                'serial_no' => 'nullable|string|max:255',
                'batch_no' => 'nullable|string',
                'shelf_location' => 'nullable|string|max:255',
                'tracking_id' => 'nullable|string|max:255',
                'main_application_id' => 'nullable|integer',
                'subapplication_id' => 'nullable|integer',
                'source_file_id' => 'nullable|integer',
                'awaiting_file_no' => 'nullable|string|max:255',
                'group_no' => 'nullable|string|max:255',
                'sys_batch_no' => 'nullable|string|max:255',
                'shelf_rack_no' => 'nullable|string|max:255',
                'grouping_match_id' => 'nullable|integer',
                'test_control' => 'nullable|string|max:25',
                'street_name' => 'nullable',
                'file_type' => 'nullable|string|max:255',
                // Required for human data-entry; the automated scanning importer
                // (source=scanning_upload) has no gender input, so it is exempted.
                'gender' => 'required_unless:source,scanning_upload|nullable|string|in:Male,Female,Corporate,Joint',
                'dob' => 'nullable',
                'nin' => 'nullable',
                'tin' => 'nullable',
                'rc_no' => 'nullable',
                'country_code' => 'nullable',
                'phone' => 'nullable',
                'residence_address' => 'nullable',
                'dciv_reason' => 'nullable|string|max:500',
                'complainant' => 'nullable|string|max:255',
                // Root of Title is a data-entry requirement of the File Indexing form
                // (create, edit and KANGIS update all post here), but only for the two
                // registry combinations that have a root to state — see
                // rootOfTitleIsRequired(). The non-form write paths have no such field,
                // so they are exempted the same way gender is.
                'root_of_title' => [
                    Rule::requiredIf(fn () => $this->rootOfTitleIsRequired($request)),
                    'nullable', 'string', 'max:255',
                ],
                'current_holder' => 'nullable',
                'original_holder' => 'nullable',
                // Entity & Customer fields
                'entity_id' => 'nullable|integer',
                'entity_name' => 'nullable|string|max:255',
                'entity_type' => 'nullable|string|in:Individual,Corporate,Multiple Owners',
                'entity_physical_address' => 'nullable|string',
                'customer_id' => 'nullable|integer',
                'customer_name' => 'nullable|string|max:255',
                'customer_type' => 'nullable|string|in:Individual,Corporate,Multiple Owners',
                'customer_account_no' => 'nullable|string|max:255',
                'customer_code' => 'nullable|string|max:255',
                'customer_status' => 'nullable|string|max:50',
                'sub_prefix' => 'nullable|string|max:50',
                'suffix' => 'nullable|string|max:50',
                'property_address' => 'nullable|string',
                'plot_size' => 'required',
                'plot_size.*' => 'required',
                'email' => 'nullable|string|max:255',
                // RoFO fields
                'has_rofo' => 'nullable|boolean',
                'rofo_instrument_type' => 'nullable|string|max:255',
                'rofo_date' => 'nullable|date',
                'rofo_number' => 'nullable|string|max:255',
                'rofo_file_number' => 'nullable|string|max:255',
                'rofo_land_use' => 'nullable|string|max:255',
                'rofo_grantor' => 'nullable|string|max:255',
                'rofo_grantee' => 'nullable|string|max:255',
                'rofo_status' => 'nullable|string|max:50',
                // Occupancy Permit fields
                'has_occupancy_permit' => 'nullable|boolean',
                'occupancy_permit_instrument_type' => 'nullable|string|max:255',
                'occupancy_permit_op_type' => 'nullable|string|max:100',
                'occupancy_permit_op_category' => 'nullable|string|max:100',
                'occupancy_permit_op_serial_number' => 'nullable|string|max:100',
                'occupancy_permit_date' => 'nullable|date',
                'occupancy_permit_file_number' => 'nullable|string|max:255',
                'occupancy_permit_land_use' => 'nullable|string|max:255',
                'occupancy_permit_grantor' => 'nullable|string|max:255',
                'occupancy_permit_grantee' => 'nullable|string|max:255',
                'occupancy_permit_status' => 'nullable|string|max:50',
                'occupancy_permit_serial_no' => 'nullable|string|max:100',
                'occupancy_permit_page_no' => 'nullable|string|max:100',
                'occupancy_permit_vol_no' => 'nullable|string|max:100',
                'occupancy_permit_deeds_time' => 'nullable|string|max:10',
                'occupancy_permit_deeds_date' => 'nullable|date',
                'cofo_status' => 'nullable|string|max:50',
                'cofo_type' => 'nullable|string|max:100',
                'has_temp_file' => 'nullable|boolean',
                'temp_file_no' => 'nullable|string|max:255',
                // KANGIS placeholder is required only when KANGIS registry is selected.
                'kangis_fileno_placeholder' => [
                    Rule::requiredIf(function () use ($request) {
                        return str_contains(strtoupper((string) $request->input('general_registry', '')), 'KANGIS');
                    }),
                    'nullable',
                    'string',
                    'max:100',
                ],
                // New KANGIS FileNo (KN-series) — distinct from the legacy KANGIS placeholder.
                // Required only when the "Has New KANGIS FileNo" checkbox is ticked.
                'has_new_kangis_fileno' => 'nullable|boolean',
                'new_kangis_file_no' => [
                    Rule::requiredIf(function () use ($request) {
                        return $request->boolean('has_new_kangis_fileno');
                    }),
                    'nullable',
                    'string',
                    'max:255',
                ],
                // "Has Transaction" rows for the New KANGIS file — same shape as the create
                // form; every field is nullable so partial rows never block the save.
                'has_new_kangis_transaction' => 'nullable|boolean',
                'transactions' => 'nullable|array',
                'transactions.*.instrument_type'  => 'nullable|string|max:255',
                'transactions.*.transaction_date' => 'nullable|date',
                'transactions.*.serial_no'        => 'nullable|string|max:100',
                'transactions.*.page_no'          => 'nullable|string|max:100',
                'transactions.*.vol_no'           => 'nullable|string|max:100',
                'transactions.*.reg_date'         => 'nullable|date',
                'transactions.*.reg_time'         => 'nullable|string|max:20',
                'transactions.*.grantor'          => 'nullable|string|max:500',
                'transactions.*.grantee'          => 'nullable|string|max:500',
                // Bill Balance & Grant Rent (repeatable: arrays). Kept permissive so
                // partial/empty repeater rows never block submission; the
                // FileIndexingBillService sanitizes each value.
                'bill_total_amount'        => 'nullable|array',
                'bill_from_year'           => 'nullable|array',
                'bill_to_year'             => 'nullable|array',
                'bill_receipt_no'          => 'nullable|array',
                'bill_receipt_date'        => 'nullable|array',
                'ground_rent_amount'       => 'nullable|array',
                'ground_rent_from_year'    => 'nullable|array',
                'ground_rent_to_year'      => 'nullable|array',
                'ground_rent_receipt_no'   => 'nullable|array',
                'ground_rent_receipt_date' => 'nullable|array',
            ], [
                'land_use_type.required' => 'Please select a Land Use Type.',
                'root_of_title.required' => 'Please enter the Root of Title.',
                'kangis_fileno_placeholder.required' => 'KANGIS FileNo Placeholder is required when KANGIS Registry is selected.',
                'new_kangis_file_no.required' => 'New KANGIS File No is required when "Has New KANGIS FileNo" is checked.',
            ]);

            // Handle DCIV Registry specific logic for file_title the  and complainant
            if (strcasecmp($validated['general_registry'] ?? '', 'DCIV Registry') === 0) {
                $validated['complainant'] = $validated['file_title'];
            }

            // Transform current_holder if it's an array (Block indexing)
            if ($request->has('current_holder') && is_array($request->input('current_holder'))) {
                $validated['current_holder'] = array_values(array_filter($request->input('current_holder')));
            } else if ($request->has('current_holder')) {
                $validated['current_holder'] = trim((string) $request->input('current_holder', ''));
            }

            // Transform original_holder if it's an array (Block indexing)
            if ($request->has('original_holder') && is_array($request->input('original_holder'))) {
                $validated['original_holder'] = array_values(array_filter($request->input('original_holder')));
            } else if ($request->has('original_holder')) {
                $validated['original_holder'] = trim((string) $request->input('original_holder', ''));
            }

            $validated['district'] = $this->normalizeChoiceValue($validated['district'] ?? null);
            $validated['street_name'] = $this->normalizeChoiceValue($validated['street_name'] ?? null);
            $validated['lga'] = $this->normalizeChoiceValue($validated['lga'] ?? null);
            $validated['registry_batch_no'] = $this->normalizeChoiceValue($validated['registry_batch_no'] ?? null);
            $validated['related_fileno'] = $this->normalizeChoiceValue($validated['related_fileno'] ?? null);

            // Filter out nulls from related_fileno array
            if (is_array($validated['related_fileno'])) {
                $validated['related_fileno'] = array_values(array_filter($validated['related_fileno']));
            }

            // Detect and normalise temporary file number suffix e.g. RES-2026-1(T) or RES-2026-1 (T)
            $tempSuffixPattern = '/\(\s*T\s*\)\s*$/i';
            $rawRequestFileNumber = trim((string) $request->input('file_number', ''));
            $rawRequestTempFileNo = trim((string) $request->input('temp_file_no', ''));
            $rawRequestAwaitingFileNo = trim((string) $request->input('awaiting_file_no', ''));
            $rawRequestArchiveFileNo = trim((string) $request->input('archive_file_no', ''));
            $rawFileNumber = trim((string) ($validated['file_number'] ?? ''));

            $tempCandidate = null;
            foreach ([$rawRequestTempFileNo, $rawRequestFileNumber, $rawRequestAwaitingFileNo, $rawRequestArchiveFileNo, $rawFileNumber] as $candidate) {
                if ($candidate !== '' && preg_match($tempSuffixPattern, $candidate)) {
                    $tempCandidate = $candidate;
                    break;
                }
            }

            if ($tempCandidate !== null) {
                $baseFileNumber = trim((string) preg_replace($tempSuffixPattern, '', $tempCandidate));
                $existingMainFileNumber = trim((string) ($existingRecord->file_number ?? ''));

                // Only promote temp to main file_number when the record already had a real file_number
                // (rename scenario). If existing file_number is null the user did not select a main
                // file number — preserve null so we never accidentally assign the temp as the main.
                if ($existingMainFileNumber !== '') {
                    $validated['file_number'] = $baseFileNumber;
                } else {
                    $validated['file_number'] = null;
                }
                $validated['has_temp_file'] = true;
                $validated['temp_file_no'] = $baseFileNumber . '(T)';
            } else {
                $validated['has_temp_file'] = false;
                $validated['temp_file_no'] = null;
            }

            $awaitingFileNoInput = Arr::pull($validated, 'awaiting_file_no');

            // Set updated metadata
            $validated['updated_by'] = $this->resolveCurrentUserName();
            $validated['updated_at'] = now();

            // Process CofO data
            $requestHasCofo = filter_var($request->input('has_cofo', false), FILTER_VALIDATE_BOOLEAN)
                || (stripos($validated['file_number'] ?? '', 'cofo') !== false);
            $resolvedTestControl = $validated['test_control'] ?? $existingRecord->test_control ?? null;

            $normalizedPropId = $this->resolveTrustedPropId(
                $request->input('prop_id'),
                $validated['file_number'] ?? ($existingRecord->file_number ?? null),
                $existingRecord->file_number ?? null,
                $validated['related_fileno'] ?? null,
                $existingRecord->related_fileno ?? null
            );

            Log::info('FileIndexing::update - updating record', [
                'id' => $id,
                'file_number' => $validated['file_number'],
                'user_id' => Auth::id()
            ]);

            $skipEntityCustomerUpdates = $request->boolean('skip_entity_customer_updates', false);
            $isKangisRegistry = str_contains(
                strtoupper(trim((string) ($validated['general_registry'] ?? $existingRecord->general_registry ?? ''))),
                'KANGIS'
            );

            // Flatten any array values in $validated that are NOT intended to be JSON/arrays in the DB.
            // This prevents "Array to string conversion" errors in subsequent query builder updates.
            foreach ($validated as $key => $value) {
                if (is_array($value) && !in_array($key, ['current_holder', 'original_holder', 'related_fileno'])) {
                    $validated[$key] = $value[0] ?? null;
                }
            }

            // Check if the file number exists in the corresponding_fileno table
            $correspondingMatch = DB::connection('sqlsrv')
                ->table('corresponding_fileno')
                ->whereRaw('UPPER(LTRIM(RTRIM(fileno))) = UPPER(?)', [trim((string) ($validated['file_number'] ?? ''))])
                ->value('fileno');
            if ($correspondingMatch !== null) {
                $validated['is_corresponding_file'] = 1;
                $validated['corresponding_fileno'] = $correspondingMatch;
            } else {
                $validated['is_corresponding_file'] = 0;
                $validated['corresponding_fileno'] = null;
            }

            DB::connection('sqlsrv')->transaction(function () use (&$validated, $id, $existingRecord, $request, $resolvedTestControl, $requestHasCofo, $normalizedPropId, $skipEntityCustomerUpdates, $isKangisRegistry) {
                $updatePayload = Arr::only($validated, FileIndexing::columnWhitelist());

                // Flatten any array values in updatePayload (SQL Server columns expect strings)
                // Pick the first value for most fields, except those we want as JSON.
                foreach ($updatePayload as $key => $value) {
                    if (is_array($value) && !in_array($key, ['current_holder', 'original_holder', 'related_fileno'])) {
                        $updatePayload[$key] = $value[0] ?? null;
                    }
                }
                $updatePayload = $this->normalizeBillColumns($updatePayload);

                // Coordinates are decimal(nullable) columns — coerce empty/non-numeric strings
                // to null so SQL Server does not reject '' when the map was never pinned.
                foreach (['latitude', 'longitude'] as $coordKey) {
                    if (array_key_exists($coordKey, $updatePayload)
                        && !is_numeric($updatePayload[$coordKey])) {
                        $updatePayload[$coordKey] = null;
                    }
                }

                // Explicitly serialize array fields since we're using Query Builder's update() call
                // which bypasses Eloquent's $casts.
                if (isset($updatePayload['current_holder']) && is_array($updatePayload['current_holder'])) {
                    $updatePayload['current_holder'] = json_encode($updatePayload['current_holder']);
                }

                if (isset($updatePayload['original_holder']) && is_array($updatePayload['original_holder'])) {
                    $updatePayload['original_holder'] = json_encode($updatePayload['original_holder']);
                }

                if (isset($updatePayload['related_fileno']) && is_array($updatePayload['related_fileno'])) {
                    $updatePayload['related_fileno'] = json_encode($updatePayload['related_fileno']);
                }

                // Update the main file indexing record
                DB::connection('sqlsrv')->table('file_indexings')
                    ->where('id', $id)
                    ->update($updatePayload);

                // Capture old/new file numbers once and reuse for all downstream syncs.
                $oldFileNumber = trim((string) $existingRecord->file_number);
                $newFileNumber = trim((string) ($validated['file_number'] ?? ''));

                // When a Kangis placeholder is present, also persist kangis_fileno_resolved
                // (it is not sent by the form as a separate field, so we derive it here).
                $kangisPlaceholderForUpdate = trim((string) ($validated['kangis_fileno_placeholder'] ?? ''));
                if ($kangisPlaceholderForUpdate !== '' && $newFileNumber !== '') {
                    DB::connection('sqlsrv')->table('file_indexings')
                        ->where('id', $id)
                        ->update(['kangis_fileno_resolved' => $newFileNumber]);
                    $validated['kangis_fileno_resolved'] = $newFileNumber;
                }

                // Cascade file_number rename across all related tables before updating them
                if ($oldFileNumber !== '' && $newFileNumber !== '' && $oldFileNumber !== $newFileNumber) {
                    $this->cascadeFileNumberRename(
                        $oldFileNumber,
                        $newFileNumber,
                        $resolvedTestControl,
                        $isKangisRegistry,
                        $existingRecord,
                        $validated['general_registry'] ?? $existingRecord->general_registry ?? null
                    );
                }

                // Update related tables (no inserts — edit only updates existing rows)
                $this->updateRelatedTables(
                    $existingRecord,
                    $validated,
                    $request,
                    $resolvedTestControl,
                    $requestHasCofo,
                    $normalizedPropId,
                    !$skipEntityCustomerUpdates,
                    false  // $allowInserts = false during edit
                );

                // Handle related file links for update
                $rawRelatedFiles = $validated['related_fileno'] ?? null;
                $relatedDetails = $request->input('related_details', []);

                $indexingType = $validated['indexing_type'] ?? $existingRecord->indexing_type ?? 'Regular';
                $relatedFileNos = [];

                if ($indexingType === 'Block' && !empty($relatedDetails)) {
                    // For block indexing, we want a link for each holder detail
                    // We'll try to map existing related file numbers from the main form to these holders
                    $mainFormFiles = array_values(array_filter(is_array($rawRelatedFiles) ? $rawRelatedFiles : [$rawRelatedFiles]));

                    foreach ($relatedDetails as $index => $detail) {
                        // Use the file number from main form if it exists at this index, else use null for fallback
                        $relatedFileNos[] = $detail['file_number'] ?? ($mainFormFiles[$index] ?? null);
                    }
                } else if (is_array($rawRelatedFiles)) {
                    $relatedFileNos = array_values(array_filter($rawRelatedFiles, fn($v) => !empty($v)));
                } elseif (is_string($rawRelatedFiles) && !empty($rawRelatedFiles)) {
                    // Try decoding if it looks like JSON
                    if (str_starts_with($rawRelatedFiles, '[')) {
                        $decoded = json_decode($rawRelatedFiles, true);
                        if (is_array($decoded)) {
                            $relatedFileNos = $decoded;
                        } else {
                            $relatedFileNos = [$rawRelatedFiles];
                        }
                    } else {
                        $relatedFileNos = [$rawRelatedFiles];
                    }
                }

                $relatedDetails = $request->input('related_details', []);

                // "Has New KANGIS FileNo": index the KN-series file on the fly. Create the
                // standalone KANGIS Registry record when it doesn't exist yet, otherwise
                // update the existing one in place (never a duplicate). Also link it to this
                // file and store the pointer on the main row so the KANGIS tracking sheet shows.
                $hasNewKangisFileno = $request->boolean('has_new_kangis_fileno');
                $newKangisFileNoRaw = trim((string) ($validated['new_kangis_file_no'] ?? ''));
                if ($hasNewKangisFileno && $newKangisFileNoRaw !== '') {
                    if (!in_array($newKangisFileNoRaw, $relatedFileNos, true)) {
                        $relatedFileNos[] = $newKangisFileNoRaw;
                    }
                    $hasKnDetail = collect($relatedDetails)
                        ->contains(fn($d) => is_array($d) && ($d['file_number'] ?? null) === $newKangisFileNoRaw);
                    if (!$hasKnDetail) {
                        $relatedDetails[] = ['file_number' => $newKangisFileNoRaw];
                    }

                    DB::connection('sqlsrv')->table('file_indexings')
                        ->where('id', $id)
                        ->update(['new_kangis_file_no' => $newKangisFileNoRaw]);

                    $this->upsertStandaloneNewKangisRecord($newKangisFileNoRaw, $validated);
                }

                $fileIndexingModel = FileIndexing::on('sqlsrv')->find($id);
                if ($fileIndexingModel) {
                    // Read the raw request, not $validated: related_fileno is re-seeded to null
                    // above for every update, so $validated can no longer tell "submitted empty"
                    // apart from "never sent".
                    $explicitRelatedSubmission = $request->has('related_fileno');

                    $this->syncRelatedFileLinks(
                        $fileIndexingModel,
                        $relatedFileNos,
                        $relatedDetails,
                        $explicitRelatedSubmission
                    );
                    $this->syncMasterDcivLinks($fileIndexingModel, $relatedFileNos, $relatedDetails);

                    // Keep the related_file_number register in step with the edit. Pruning is
                    // allowed only on an explicit submission (same rule as the links above),
                    // and only ever removes the untyped rows this form owns.
                    app(\App\Services\RelatedFileNumberRegistrar::class)->sync(
                        $fileIndexingModel->id,
                        $fileIndexingModel->file_number,
                        $fileIndexingModel->file_title,
                        $fileIndexingModel->prop_id ?? null,
                        $relatedFileNos,
                        [
                            'prune'    => $explicitRelatedSubmission,
                            'location' => $fileIndexingModel->location ?? null,
                        ]
                    );
                }
            });

            // Fetch updated record
            $updatedRecord = DB::connection('sqlsrv')->table('file_indexings')->where('id', $id)->first();
            $fileNumber = $updatedRecord->file_number ?? ($validated['file_number'] ?? null);

            // Push whatever the operator actually changed out to the systems that hold the
            // same facts — PRA, mls_file_no, the file-number registers and the originating
            // OSS application. Runs after the commit and swallows its own failures: this is
            // downstream propagation, and file_indexings is already the authoritative row.
            $propagation = app(\App\Services\FileIndexingPropagationService::class)
                ->propagate($existingRecord, $updatedRecord, [
                    // Names the operator typed into the Entity & Customer section on this
                    // same save. They take precedence over the file-title mirror.
                    'entity_name' => $request->input('entity_details.entity_name', $request->input('entity_name')),
                    'customer_name' => $request->input('customer_details.customer_name', $request->input('customer_name')),
                ]);

            // Persist Bill Balance + Grant Rent (billing + file_indexing_bills tables).
            $billFileIndexing = FileIndexing::on('sqlsrv')->find($id);
            if ($billFileIndexing) {
                $this->fileIndexingBillService->syncForFile($billFileIndexing, $request);
            }

            // "Has Transaction" (New KANGIS): persist the rows typed on the edit form. As on
            // the create path they belong to the KN-series record when one is attached —
            // its own prop_id, parent = this file's — otherwise to the file being edited.
            // Rows already stored are skipped, so re-saving never duplicates them.
            if ($billFileIndexing) {
                $mainPropId = isset($updatedRecord->prop_id) && $updatedRecord->prop_id !== null
                    ? (int) $updatedRecord->prop_id
                    : null;
                $txnTarget = $billFileIndexing;
                $txnPropId = $mainPropId;
                $txnParentPropId = isset($updatedRecord->parent_prop_id) && $updatedRecord->parent_prop_id !== null
                    ? (int) $updatedRecord->parent_prop_id
                    : null;

                $knForTxn = trim((string) ($request->input('new_kangis_file_no')
                    ?: ($updatedRecord->new_kangis_file_no ?? '')));

                if ($knForTxn !== '') {
                    $knRecord = FileIndexing::on('sqlsrv')
                        ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = UPPER(?)', [$knForTxn])
                        ->first();

                    if ($knRecord) {
                        $txnTarget = $knRecord;
                        $txnPropId = $knRecord->prop_id !== null ? (int) $knRecord->prop_id : $mainPropId;
                        $txnParentPropId = $mainPropId;
                    }
                }

                $this->syncFileIndexingTransactions($request, $txnTarget, $txnPropId, $txnParentPropId);
            }

            $fileTransactions = $fileNumber
                ? $this->fetchFileTransactionsForFileNumber($fileNumber)
                : [];

            Log::info('FileIndexing::update - record updated successfully', [
                'id' => $id,
                'file_number' => $updatedRecord->file_number ?? 'unknown'
            ]);

            // Same "where did this record go?" card as the create path — an update
            // fans out across the same tables, so the operator sees the current state.
            $storageSummary = $billFileIndexing
                ? $this->indexingStorageSummaryService->summarize(
                    $billFileIndexing->fresh() ?? $billFileIndexing,
                    ['is_update' => true]
                )
                : null;

            // A new version of the audit trail rather than an overwrite of the last
            // one — that is the whole point of the table. 'auto' lets the service
            // work out from the diff whether this was an edit or a re-linking; a
            // save that changed nothing writes no version at all.
            $snapshotRecord = $billFileIndexing
                ? ($billFileIndexing->fresh() ?? $billFileIndexing)
                : null;

            $snapshot = $snapshotRecord
                ? $this->fileSnapshotService->capture($snapshotRecord, 'auto', [
                    'source' => 'file_indexing.update',
                    'storage_summary' => $storageSummary,
                ])
                : null;

            return response()->json([
                'success' => true,
                'message' => 'File indexing record updated successfully!',
                'data' => $updatedRecord,
                'file_transactions' => $fileTransactions,
                'storage_summary' => $storageSummary,
                'propagation' => $propagation,
                'snapshot' => $snapshot ? $snapshot->toCardPayload() : null,
            ]);
        } catch (\Exception $e) {
            Log::error('FileIndexing::update - failed', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function transactions(FileIndexing $fileIndexing)
    {
        $transactions = $this->fetchFileTransactionsForFileNumber($fileIndexing->file_number);

        return response()->json([
            'success' => true,
            'file_number' => $fileIndexing->file_number,
            'data' => $transactions,
        ]);
    }

    /**
     * The File Snapshot card for a file, for re-opening it away from a save. The
     * save responses already embed the snapshot they just wrote, so this is the
     * re-open path, not the usual one.
     *
     * Two answers, and the difference is stated in the response rather than hidden:
     *
     *   STORED  the newest row in file_snapshots — a real version, with the list of
     *           what changed since the version before it.
     *
     *   LIVE    the file's current state, composed on the fly and persisted nowhere.
     *
     * The live branch is not a nicety. file_snapshots only began on 2026-08-26, so
     * 9 of the 133,808 indexed files have any history at all; a plain 404 for the
     * rest would make the File Indexing "View Details" action — which is what sends
     * most of the traffic here — dead for practically every row. buildPayload()
     * reads the same sections the stored snapshot holds, so both answers render
     * through one card.
     */
    public function snapshot(FileIndexing $fileIndexing)
    {
        $snapshot = $this->fileSnapshotService->latestFor((int) $fileIndexing->id);

        if ($snapshot) {
            return response()->json([
                'success' => true,
                'live' => false,
                'file_number' => $fileIndexing->file_number,
                'snapshot' => $snapshot->toCardPayload(),
            ]);
        }

        // Composed, never written: reading a file must not create a version, or the
        // history would fill with entries recording that somebody looked.
        try {
            $payload = $this->fileSnapshotService->buildPayload($fileIndexing);
        } catch (\Throwable $e) {
            Log::warning('Live file snapshot could not be composed', [
                'file_indexing_id' => $fileIndexing->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'file_number' => $fileIndexing->file_number,
                'snapshot' => null,
            ], 500);
        }

        return response()->json([
            'success' => true,
            'live' => true,
            'file_number' => $fileIndexing->file_number,
            'snapshot' => [
                'id' => null,
                'file_indexing_id' => (int) $fileIndexing->id,
                'file_number' => $fileIndexing->file_number,
                'temp_file_no' => $fileIndexing->temp_file_no,
                'tracking_id' => $fileIndexing->tracking_id,
                // No version and no author: nothing recorded this state, so claiming
                // "v1, captured by X" would be inventing a history that never ran.
                // The card reads is_live and badges it LIVE instead.
                'version' => null,
                'is_live' => true,
                'event_type' => 'live',
                'event_label' => 'Current state — no snapshot history for this file',
                'changed_field_count' => 0,
                'performed_by_name' => null,
                'performed_at' => optional($fileIndexing->updated_at)->toDateTimeString(),
                'payload' => $payload,
                'changes' => [],
            ],
        ]);
    }

    /**
     * Sample data for the Create File Index form — TEST AID, disabled by default.
     *
     * Returns 404 rather than 403 when switched off: on production this endpoint
     * should be indistinguishable from one that was never deployed.
     *
     * See config/fileindexing.php ('demo_fill') for the two locks.
     */
    public function demoSample(Request $request, \App\Services\DemoIndexingDataService $demo)
    {
        if (!$demo->enabled()) {
            abort(404);
        }

        // File numbers this session has already been handed, so a second click
        // offers a different file instead of the same one.
        $exclude = array_filter(array_map('trim', (array) $request->input('exclude', [])));

        $sample = $demo->sample($exclude);

        if (!$sample) {
            return response()->json([
                'success' => false,
                'message' => 'No unindexed ' . config('fileindexing.demo_fill.land_use')
                    . ' file is left in the grouping table to demo with.',
            ], 404);
        }

        return response()->json(['success' => true] + $sample);
    }

    /**
     * Cascade a file_number rename across all related tables.
     * Call this BEFORE updateRelatedTables() so that subsequent lookups use the new number.
     */
    protected function cascadeFileNumberRename(
        string $oldFileNumber,
        string $newFileNumber,
        ?string $testControl = null,
        bool $isKangisRegistry = false,
        $existingRecord = null,
        ?string $registry = null
    ): void {
        if ($oldFileNumber === $newFileNumber || $oldFileNumber === '' || $newFileNumber === '') {
            return;
        }

        // Resolve tracking_id: prefer the one already on the file_indexings row,
        // otherwise try to backfill it from the grouping table that owns this file number.
        $trackingId = null;
        if ($existingRecord && !empty($existingRecord->tracking_id)) {
            $trackingId = trim((string) $existingRecord->tracking_id);
        }

        $groupingTable = $this->resolveGroupingTableForRename($registry, $oldFileNumber, $newFileNumber);

        if ($trackingId === null && $groupingTable) {
            $trackingId = $this->lookupTrackingIdFromGrouping($groupingTable, $oldFileNumber);
            if ($trackingId !== null && $existingRecord && !empty($existingRecord->id)) {
                // Backfill tracking_id on file_indexings so future renames have it cheaply.
                DB::connection('sqlsrv')->table('file_indexings')
                    ->where('id', $existingRecord->id)
                    ->update(['tracking_id' => $trackingId]);
            }
        }

        Log::info('FileIndexing::cascadeFileNumberRename - starting', [
            'old' => $oldFileNumber,
            'new' => $newFileNumber,
            'tracking_id' => $trackingId,
            'grouping_table' => $groupingTable['table'] ?? null,
            'registry' => $registry,
        ]);

        // 1. Rename the fileNumber table row so updateFileNumberTable() can find it by the new number
        $fileNumberUpdateData = [
            'kangisFileNo' => $newFileNumber,
            'NewKANGISFileNo' => $newFileNumber,
            'kangis_fileno_resolved' => $newFileNumber,
            'updated_at' => now(),
        ];

        if ($isKangisRegistry) {
            $fileNumberUpdateData['mlsfNo'] = null;
        } else {
            $fileNumberUpdateData['mlsfNo'] = $newFileNumber;
        }

        $hasFileNumberTraColumn = Schema::connection('sqlsrv')->hasColumn('fileNumber', 'tra');

        if ($trackingId !== null && $trackingId !== '' && $hasFileNumberTraColumn) {
            $fileNumberUpdateData['tra'] = $trackingId;
        }

        DB::connection('sqlsrv')->table('fileNumber')
            ->where(function ($q) use ($oldFileNumber, $trackingId, $hasFileNumberTraColumn) {
                $q->whereRaw('UPPER(LTRIM(RTRIM(mlsfNo))) = UPPER(?)', [$oldFileNumber])
                    ->orWhereRaw('UPPER(LTRIM(RTRIM(kangisFileNo))) = UPPER(?)', [$oldFileNumber])
                    ->orWhereRaw('UPPER(LTRIM(RTRIM(NewKANGISFileNo))) = UPPER(?)', [$oldFileNumber]);
                if ($trackingId !== null && $trackingId !== '' && $hasFileNumberTraColumn) {
                    $q->orWhere('tra', $trackingId);
                }
            })
            ->update($fileNumberUpdateData);

        // 2. Rename file_history_staging rows
        if (!$isKangisRegistry) {
            DB::connection('sqlsrv')->table('file_history_staging')
                ->whereRaw('UPPER(LTRIM(RTRIM(mlsFNo))) = UPPER(?)', [$oldFileNumber])
                ->update(['mlsFNo' => $newFileNumber]);
        }

        DB::connection('sqlsrv')->table('file_history_staging')
            ->whereRaw('UPPER(LTRIM(RTRIM(fileno))) = UPPER(?)', [$oldFileNumber])
            ->update(['fileno' => $newFileNumber]);

        DB::connection('sqlsrv')->table('file_history_staging')
            ->whereRaw('UPPER(LTRIM(RTRIM(kangisFileNo))) = UPPER(?)', [$oldFileNumber])
            ->update(['kangisFileNo' => $newFileNumber]);

        // 3. Rename pra rows
        if (!$isKangisRegistry) {
            DB::connection('sqlsrv')->table('pra')
                ->whereRaw('UPPER(LTRIM(RTRIM(mlsFNo))) = UPPER(?)', [$oldFileNumber])
                ->update(['mlsFNo' => $newFileNumber]);
        }

        DB::connection('sqlsrv')->table('pra')
            ->whereRaw('UPPER(LTRIM(RTRIM(fileno))) = UPPER(?)', [$oldFileNumber])
            ->update(['fileno' => $newFileNumber]);

        DB::connection('sqlsrv')->table('pra')
            ->whereRaw('UPPER(LTRIM(RTRIM(kangisFileNo))) = UPPER(?)', [$oldFileNumber])
            ->update(['kangisFileNo' => $newFileNumber]);

        // 4. Rename the correct registry-specific grouping table row.
        //    Match primarily by tracking_id (set at indexing time), with final-column fallback.
        if ($groupingTable) {
            $this->renameGroupingRow($groupingTable, $oldFileNumber, $newFileNumber, $trackingId);
        }

        // 5. Rename entities_staging rows
        DB::connection('sqlsrv')->table('entities_staging')
            ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = UPPER(?)', [$oldFileNumber])
            ->update(['file_number' => $newFileNumber]);

        // 6. Rename customers_staging rows
        DB::connection('sqlsrv')->table('customers_staging')
            ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = UPPER(?)', [$oldFileNumber])
            ->update(['file_number' => $newFileNumber]);

        // 7. Rename additional business tables that carry the file number as a string.
        //    Each table is guarded by Schema::hasTable / hasColumn so schema drift is safe.
        $this->renameFileNumberInBusinessTables($oldFileNumber, $newFileNumber, $isKangisRegistry);

        Log::info('FileIndexing::cascadeFileNumberRename - completed', [
            'old' => $oldFileNumber,
            'new' => $newFileNumber,
            'tracking_id' => $trackingId,
        ]);
    }

    /**
     * Resolve the registry-specific grouping table (and its columns) for a rename.
     *
     * Returns an array:
     *   [ 'table' => <table>, 'awaiting' => <col>, 'final' => <col>, 'extra_final' => <col|null> ]
     * or null if no table could be resolved.
     *
     * Note: LPKN and MISC-KN file numbers live under Survey registry in `gkn_grouping`,
     * so they are normalised to that table here even though their prefix differs.
     */
    protected function resolveGroupingTableForRename(?string $registry, string $oldFileNumber, string $newFileNumber): ?array
    {
        $registryUpper = strtoupper(trim((string) $registry));
        $oldUpper = strtoupper($oldFileNumber);
        $newUpper = strtoupper($newFileNumber);

        $detect = function (string $registry, string $fileNumber): ?string {
            $r = strtoupper($registry);
            $f = strtoupper($fileNumber);

            // File-number prefix takes priority so renamed files still route correctly.
            if ($f !== '') {
                if (str_contains($f, 'GKN') || str_contains($f, 'LPKN') || str_contains($f, 'MISC')) {
                    return 'gkn_grouping';
                }
                if (str_contains($f, 'SLTR'))
                    return 'sltr_grouping';
                if (str_contains($f, 'SIT'))
                    return 'sit_grouping';
                if (str_contains($f, 'DCIV'))
                    return 'dciv_grouping';
                if (str_contains($f, 'KNML') || str_contains($f, 'MLKN') || str_contains($f, 'MNKL') || str_contains($f, 'KNGP')) {
                    return 'kangis_grouping';
                }
                if (preg_match('/^KN\d+$/i', $f))
                    return 'kn_grouping';
            }

            if ($r !== '') {
                if (str_contains($r, 'SURVEY'))
                    return 'gkn_grouping';
                if (str_contains($r, 'SLTR'))
                    return 'sltr_grouping';
                if (str_contains($r, 'SIT'))
                    return 'sit_grouping';
                if (str_contains($r, 'DCIV'))
                    return 'dciv_grouping';
                if (str_contains($r, 'KANGIS'))
                    return 'kangis_grouping';
                if (str_contains($r, 'LANDS'))
                    return 'grouping';
            }

            return null;
        };

        $table = $detect($registryUpper, $oldUpper) ?? $detect($registryUpper, $newUpper) ?? 'grouping';

        $columnMap = [
            'grouping' => ['awaiting' => 'awaiting_fileno', 'final' => 'mls_fileno', 'extra_final' => null],
            'gkn_grouping' => ['awaiting' => 'gkn_awaiting_fileno', 'final' => 'gkn_fileno', 'extra_final' => null],
            'sltr_grouping' => ['awaiting' => 'sltr_awaiting_fileno', 'final' => 'sltr_fileno', 'extra_final' => null],
            'sit_grouping' => ['awaiting' => 'sit_awaiting_fileno', 'final' => 'sit_fileno', 'extra_final' => null],
            'dciv_grouping' => ['awaiting' => 'dciv_awaiting_fileno', 'final' => 'dciv_fileno', 'extra_final' => null],
            'kangis_grouping' => ['awaiting' => 'kangis_awaiting_fileno', 'final' => 'kangis_fileno', 'extra_final' => 'indexing_kangis_fileno'],
            'kn_grouping' => ['awaiting' => 'kn_awaiting_fileno', 'final' => 'kn_fileno', 'extra_final' => null],
        ];

        if (!isset($columnMap[$table])) {
            return null;
        }

        return array_merge(['table' => $table], $columnMap[$table]);
    }

    /**
     * Best-effort lookup of a tracking_id from the registry grouping table
     * when the file_indexings row is missing it.
     */
    protected function lookupTrackingIdFromGrouping(array $groupingTable, string $oldFileNumber): ?string
    {
        $table = $groupingTable['table'];

        try {
            if (
                !Schema::connection('sqlsrv')->hasTable($table) ||
                !Schema::connection('sqlsrv')->hasColumn($table, 'tracking_id')
            ) {
                return null;
            }

            $finalCol = $groupingTable['final'];
            $awaitingCol = $groupingTable['awaiting'];
            $extraFinalCol = $groupingTable['extra_final'];

            $row = DB::connection('sqlsrv')->table($table)
                ->select('tracking_id')
                ->where(function ($q) use ($finalCol, $awaitingCol, $extraFinalCol, $oldFileNumber) {
                    $q->whereRaw("UPPER(LTRIM(RTRIM({$finalCol}))) = UPPER(?)", [$oldFileNumber])
                        ->orWhereRaw("UPPER(LTRIM(RTRIM({$awaitingCol}))) = UPPER(?)", [$oldFileNumber]);
                    if ($extraFinalCol) {
                        $q->orWhereRaw("UPPER(LTRIM(RTRIM({$extraFinalCol}))) = UPPER(?)", [$oldFileNumber]);
                    }
                })
                ->whereNotNull('tracking_id')
                ->orderByDesc('updated_at')
                ->first();

            $value = $row->tracking_id ?? null;
            return $value !== null ? trim((string) $value) : null;
        } catch (\Throwable $e) {
            Log::warning('FileIndexing::lookupTrackingIdFromGrouping - failed', [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Rename the final/MLS column on the registry grouping row.
     * Matches by tracking_id first, then final column, then awaiting column.
     * The awaiting column is deliberately left unchanged (it records the original placeholder).
     */
    protected function renameGroupingRow(array $groupingTable, string $oldFileNumber, string $newFileNumber, ?string $trackingId): void
    {
        $table = $groupingTable['table'];

        try {
            if (!Schema::connection('sqlsrv')->hasTable($table)) {
                return;
            }

            $finalCol = $groupingTable['final'];
            $awaitingCol = $groupingTable['awaiting'];
            $extraFinalCol = $groupingTable['extra_final'];

            $updateData = [$finalCol => $newFileNumber];
            if ($extraFinalCol && Schema::connection('sqlsrv')->hasColumn($table, $extraFinalCol)) {
                $updateData[$extraFinalCol] = $newFileNumber;
            }
            if (Schema::connection('sqlsrv')->hasColumn($table, 'updated_at')) {
                $updateData['updated_at'] = now();
            }

            $affected = DB::connection('sqlsrv')->table($table)
                ->where(function ($q) use ($finalCol, $awaitingCol, $extraFinalCol, $oldFileNumber, $trackingId, $table) {
                    if ($trackingId !== null && $trackingId !== '' && Schema::connection('sqlsrv')->hasColumn($table, 'tracking_id')) {
                        $q->where('tracking_id', $trackingId);
                    }
                    $q->orWhereRaw("UPPER(LTRIM(RTRIM({$finalCol}))) = UPPER(?)", [$oldFileNumber])
                        ->orWhereRaw("UPPER(LTRIM(RTRIM({$awaitingCol}))) = UPPER(?)", [$oldFileNumber]);
                    if ($extraFinalCol) {
                        $q->orWhereRaw("UPPER(LTRIM(RTRIM({$extraFinalCol}))) = UPPER(?)", [$oldFileNumber]);
                    }
                })
                ->update($updateData);

            Log::info('FileIndexing::renameGroupingRow', [
                'table' => $table,
                'affected' => $affected,
                'old' => $oldFileNumber,
                'new' => $newFileNumber,
                'tracking_id' => $trackingId,
            ]);
        } catch (\Throwable $e) {
            Log::warning('FileIndexing::renameGroupingRow - failed', [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Cascade the file-number string rename into downstream business tables.
     * Every table/column pair is guarded by Schema checks so this is safe
     * even when the schema differs between environments.
     */
    protected function renameFileNumberInBusinessTables(string $oldFileNumber, string $newFileNumber, bool $isKangisRegistry): void
    {
        // table => [columns that may hold the file number string]
        $targets = [
            'CofO' => ['mlsFNo', 'kangisFileNo', 'NewKANGISFileNo', 'fileno', 'file_number'],
            'CofO_staging' => ['mlsFNo', 'kangisFileNo', 'NewKANGISFileNo', 'fileno', 'file_number'],
            'mother_applications' => ['file_number'],
            'subapplications' => ['file_number'],
            'instrument_captures' => ['file_number', 'fileno'],
            'caveats' => ['file_number_kangis', 'file_number_mlsf', 'file_number_new_kangis'],
            'bills' => ['file_number', 'fileno'],
            'pagetypings' => ['file_number', 'fileno', 'kangisFileNo', 'mlsFNo'],
            'scanning_batches' => ['file_number', 'fileno'],
            'scans' => ['file_number', 'fileno'],
            'file_archives' => ['file_number', 'fileno', 'mlsFNo', 'kangisFileNo'],
            'file_trackings' => ['file_number', 'fileno'],
            'rack_allocations' => ['file_number', 'fileno'],
            'shelf_allocations' => ['file_number', 'fileno'],
            'property_cards' => ['file_number', 'fileno', 'mlsFNo', 'kangisFileNo'],
            'rds' => ['file_number', 'fileno'],
            'cors' => ['file_number', 'fileno'],
        ];

        foreach ($targets as $table => $columns) {
            try {
                if (!Schema::connection('sqlsrv')->hasTable($table)) {
                    continue;
                }

                foreach ($columns as $col) {
                    if (!Schema::connection('sqlsrv')->hasColumn($table, $col)) {
                        continue;
                    }

                    // For KANGIS registry, don't clobber MLSF columns (they should remain NULL / untouched).
                    if ($isKangisRegistry && in_array($col, ['mlsFNo', 'mlsfNo'], true)) {
                        continue;
                    }

                    $affected = DB::connection('sqlsrv')->table($table)
                        ->whereRaw("UPPER(LTRIM(RTRIM({$col}))) = UPPER(?)", [$oldFileNumber])
                        ->update([$col => $newFileNumber]);

                    if ($affected > 0) {
                        Log::info('FileIndexing::renameFileNumberInBusinessTables', [
                            'table' => $table,
                            'column' => $col,
                            'affected' => $affected,
                            'old' => $oldFileNumber,
                            'new' => $newFileNumber,
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('FileIndexing::renameFileNumberInBusinessTables - failed', [
                    'table' => $table,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Update related tables (fileNumber, CofO, Entity, Customer) when updating file indexing
     */
    protected function updateRelatedTables($existingRecord, $validated, Request $request, ?string $testControl = null, bool $hasCofo = false, ?int $propId = null, bool $shouldUpdateEntityCustomer = true, bool $allowInserts = true)
    {
        $fileNumber = trim((string) ($validated['file_number'] ?? ''));
        $trackingId = $validated['tracking_id'] ?? $existingRecord->tracking_id;
        $currentUserName = $this->resolveCurrentUserName();

        if ($propId === null) {
            $propId = $this->determinePropIdForIndexing($existingRecord, $validated);
        }

        // Update fileNumber table
        $isKangisRegistry = str_contains(
            strtoupper(trim((string) ($validated['general_registry'] ?? $existingRecord->general_registry ?? ''))),
            'KANGIS'
        );

        // For temp-only records (file_number is null) the fileNumber table is looked up by
        // temp_file_no/tracking_id instead. Skip the main file-number update to avoid
        // accidentally writing the temp value into mlsfNo.
        $fileNumberTableKey = $fileNumber !== ''
            ? $fileNumber
            : trim((string) ($validated['temp_file_no'] ?? $existingRecord->temp_file_no ?? ''));

        if ($fileNumberTableKey !== '') {
            $this->updateFileNumberTable($fileNumberTableKey, $trackingId, $testControl, [
                'file_title' => $validated['file_title'] ?? $existingRecord->file_title ?? null,
                'location' => $validated['location'] ?? $existingRecord->location ?? null,
                'lga' => $validated['lga'] ?? $existingRecord->lga ?? null,
                'plot_no' => $validated['plot_number'] ?? $existingRecord->plot_number ?? null,
                'tp_no' => $validated['tp_no'] ?? $existingRecord->tp_no ?? null,
                'has_temp_file' => $validated['has_temp_file'] ?? $existingRecord->has_temp_file ?? false,
                'temp_file_no' => $validated['temp_file_no'] ?? $existingRecord->temp_file_no ?? null,
                'is_kangis' => $isKangisRegistry,
                // When looking up by temp_file_no, do NOT overwrite mlsfNo — pass null so the
                // updateFileNumberTable skips the mlsfNo column for non-Kangis records.
                'skip_mlsf_update' => $fileNumber === '',
                'kangis_fileno_placeholder' => $validated['kangis_fileno_placeholder'] ?? $existingRecord->kangis_fileno_placeholder ?? null,
                'kangis_fileno_resolved' => $validated['kangis_fileno_resolved'] ?? ($fileNumber !== '' ? $fileNumber : null) ?? $existingRecord->kangis_fileno_resolved ?? null,
                'created_by' => $currentUserName,
                'updated_by' => $currentUserName,
                'source' => 'indexing',
                'type' => 'indexing',
            ], $allowInserts);
        }

        // Update CofO record (temp-only records use temp_fileno as the lookup key inside)
        $this->updateCofORecord($existingRecord, $validated, $request, $testControl, $hasCofo, $propId);

        // Update RoFO record
        $hasRofo = filter_var($request->input('has_rofo', false), FILTER_VALIDATE_BOOLEAN);
        if ($hasRofo) {
            $fileIndexingModel = FileIndexing::on('sqlsrv')->find($existingRecord->id);
            if ($fileIndexingModel) {
                $this->syncRofoRecord($fileIndexingModel, $request, $testControl, $propId);
            }
        }

        // Update Entity and Customer records
        if ($shouldUpdateEntityCustomer) {
            $this->updateEntityAndCustomerRecords($existingRecord, $request, $testControl, $allowInserts);
        }

        if ($fileNumber !== '') {
            $this->updateFileHistoryPropId($fileNumber, $propId, $testControl);
        }
    }

    protected function fetchFileTransactionsForFileNumber(string $fileNumber, int $limit = 25): array
    {
        try {
            $historyTransactions = DB::connection('sqlsrv')
                ->table('file_history_staging')
                ->select([
                    'id',
                    'mlsFNo',
                    'fileno',
                    'transaction_type',
                    'transaction_date',
                    'regNo',
                    'reg_date',
                    'reg_time',
                    'period',
                    'period_unit',
                    'land_use',
                    'party_1',
                    'party_2',
                    'party_3',
                    'party_4',
                    'party_5',
                    'location',
                    'plot_no',
                    'lgsaOrCity',
                    'status',
                    DB::raw("'file_history_staging' as source_table"),
                ])
                ->where(function ($builder) use ($fileNumber) {
                    $builder->where('mlsFNo', $fileNumber)
                        ->orWhere('fileno', $fileNumber);
                })
                ->get();

            $praTransactions = DB::connection('sqlsrv')
                ->table('pra')
                ->select([
                    'id',
                    'mlsFNo',
                    'fileno',
                    'transaction_type',
                    'transaction_date',
                    DB::raw('NULL as regNo'),
                    DB::raw('NULL as reg_date'),
                    DB::raw('NULL as reg_time'),
                    'period',
                    'period_unit',
                    'land_use',
                    'party_1',
                    'party_2',
                    DB::raw('NULL as party_3'),
                    DB::raw('NULL as party_4'),
                    DB::raw('NULL as party_5'),
                    'location',
                    'plot_no',
                    'lgsaOrCity',
                    'status',
                    DB::raw("'pra' as source_table"),
                ])
                ->where(function ($builder) use ($fileNumber) {
                    $builder->where('mlsFNo', $fileNumber)
                        ->orWhere('fileno', $fileNumber);
                })
                ->get();

            $allTransactions = $historyTransactions->merge($praTransactions)
                ->sortByDesc('transaction_date')
                ->take($limit)
                ->map(static function ($row) {
                    $regParts = explode('/', $row->regNo ?? '');
                    return [
                        'id' => $row->id,
                        'mlsFNo' => $row->mlsFNo,
                        'fileno' => $row->fileno,
                        'transaction_type' => $row->transaction_type,
                        'transaction_date' => $row->transaction_date,
                        'serialNo' => $regParts[0] ?? '',
                        'pageNo' => $regParts[1] ?? '',
                        'volumeNo' => $regParts[2] ?? '',
                        'regDate' => $row->reg_date,
                        'regTime' => $row->reg_time,
                        'period' => $row->period,
                        'period_unit' => $row->period_unit,
                        'land_use' => $row->land_use,
                        'party_1' => $row->party_1,
                        'party_2' => $row->party_2,
                        'party_3' => $row->party_3 ?? null,
                        'party_4' => $row->party_4 ?? null,
                        'party_5' => $row->party_5 ?? null,
                        'location' => $row->location,
                        'plot_no' => $row->plot_no,
                        'lgsaOrCity' => $row->lgsaOrCity,
                        'status' => $row->status ?? 'Normal',
                        'source_table' => $row->source_table ?? 'unknown',
                    ];
                })
                ->values()
                ->toArray();

            return $allTransactions;
        } catch (\Throwable $exception) {
            Log::warning('FileIndexing::fetchFileTransactionsForFileNumber - failed', [
                'file_number' => $fileNumber,
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Update fileNumber table records
     */
    protected function updateFileNumberTable(string $fileNumber, ?string $trackingId, ?string $testControl = null, array $extraAttributes = [], bool $allowInsert = true)
    {
        try {
            $isKangis = !empty($extraAttributes['is_kangis']);
            $skipMlsfUpdate = !empty($extraAttributes['skip_mlsf_update']);
            $kangisPlaceholder = isset($extraAttributes['kangis_fileno_placeholder'])
                ? trim((string) $extraAttributes['kangis_fileno_placeholder'])
                : '';
            $kangisResolved = isset($extraAttributes['kangis_fileno_resolved'])
                ? trim((string) $extraAttributes['kangis_fileno_resolved'])
                : '';

            $updateData = [
                'updated_at' => Carbon::now(),
            ];

            if ($isKangis) {
                $resolvedForKangis = $kangisResolved !== '' ? $kangisResolved : $fileNumber;
                $updateData['mlsfNo'] = null;
                $updateData['kangisFileNo'] = $resolvedForKangis;
                $updateData['NewKANGISFileNo'] = $resolvedForKangis;
                $updateData['kangis_fileno_resolved'] = $resolvedForKangis;
                if ($kangisPlaceholder !== '') {
                    $updateData['kangis_fileno_placeholder'] = $kangisPlaceholder;
                }
            } elseif (!$skipMlsfUpdate) {
                // Only write mlsfNo when we have a confirmed main file number.
                // Temp-only records (looked up by temp_file_no) must not overwrite mlsfNo.
                $updateData['mlsfNo'] = $fileNumber;
            }

            $fileName = $extraAttributes['file_title'] ?? null;
            if ($fileName !== null && $fileName !== '') {
                $updateData['FileName'] = $fileName;
            } else {
                $updateData['FileName'] = $fileNumber;
            }

            if (array_key_exists('location', $extraAttributes) && $extraAttributes['location'] !== null) {
                $updateData['location'] = $extraAttributes['location'];
            }

            if (array_key_exists('lga', $extraAttributes) && $extraAttributes['lga'] !== null) {
                $updateData['lga'] = $extraAttributes['lga'];
            }

            if (array_key_exists('plot_no', $extraAttributes) && $extraAttributes['plot_no'] !== null) {
                $updateData['plot_no'] = $extraAttributes['plot_no'];
            }

            if (array_key_exists('tp_no', $extraAttributes) && $extraAttributes['tp_no'] !== null) {
                $updateData['tp_no'] = $extraAttributes['tp_no'];
            }

            if (array_key_exists('has_temp_file', $extraAttributes)) {
                $updateData['has_temp_file'] = !empty($extraAttributes['has_temp_file']) ? 1 : 0;
            }

            if (array_key_exists('temp_file_no', $extraAttributes)) {
                $tempFileNo = is_string($extraAttributes['temp_file_no']) ? trim($extraAttributes['temp_file_no']) : $extraAttributes['temp_file_no'];
                $updateData['temp_file_no'] = $tempFileNo !== '' ? $tempFileNo : null;
            }

            if ($trackingId !== null && $trackingId !== '') {
                $updateData['tracking_id'] = $trackingId;
            }

            if ($testControl !== null) {
                $updateData['test_control'] = $testControl;
            }

            $variants = array_values(array_unique($this->buildFileNumberVariants($fileNumber)));
            $hasFilenoColumn = Schema::connection('sqlsrv')->hasColumn('fileNumber', 'fileno');

            if (empty($variants)) {
                Log::warning('FileIndexing::updateFileNumberTable - no variants generated', [
                    'file_number' => $fileNumber,
                ]);
                return;
            }

            $query = DB::connection('sqlsrv')->table('fileNumber')
                ->where(function ($builder) use ($variants, $hasFilenoColumn) {
                    $builder
                        ->whereIn('st_file_no', $variants)
                        ->orWhereIn('mlsfNo', $variants)
                        ->orWhereIn('kangisFileNo', $variants)
                        ->orWhereIn('NewKANGISFileNo', $variants);

                    if ($hasFilenoColumn) {
                        $builder->orWhereIn('fileno', $variants);
                    }
                });

            $affected = $query->update($updateData);

            // Guard against duplicate rows: if variants miss but tracking_id exists, update by tracking_id.
            if ($affected === 0 && !empty($trackingId)) {
                $affected = DB::connection('sqlsrv')->table('fileNumber')
                    ->whereRaw('UPPER(LTRIM(RTRIM(tracking_id))) = UPPER(?)', [trim((string) $trackingId)])
                    ->update($updateData);
            }

            if ($affected === 0) {
                if (!$allowInsert) {
                    Log::info('FileIndexing::updateFileNumberTable - no matching row found, insert skipped (edit mode)', [
                        'file_number' => $fileNumber,
                        'tracking_id' => $trackingId,
                    ]);
                } else {
                    $defaultName = $this->resolveCurrentUserName();
                    $insertData = [
                        'mlsfNo' => $isKangis ? null : $fileNumber,
                        'kangisFileNo' => $isKangis ? ($kangisResolved !== '' ? $kangisResolved : $fileNumber) : null,
                        'NewKANGISFileNo' => $isKangis ? ($kangisResolved !== '' ? $kangisResolved : $fileNumber) : null,
                        'kangis_fileno_placeholder' => ($isKangis && $kangisPlaceholder !== '') ? $kangisPlaceholder : null,
                        'kangis_fileno_resolved' => $isKangis ? ($kangisResolved !== '' ? $kangisResolved : $fileNumber) : null,
                        'FileName' => $extraAttributes['file_title'] ?? $fileNumber,
                        'location' => $extraAttributes['location'] ?? null,
                        'lga' => $extraAttributes['lga'] ?? null,
                        'plot_no' => $extraAttributes['plot_no'] ?? null,
                        'tp_no' => $extraAttributes['tp_no'] ?? null,
                        'has_temp_file' => !empty($extraAttributes['has_temp_file']) ? 1 : 0,
                        'temp_file_no' => isset($extraAttributes['temp_file_no']) && trim((string) $extraAttributes['temp_file_no']) !== ''
                            ? trim((string) $extraAttributes['temp_file_no'])
                            : null,
                        'tracking_id' => $trackingId,
                        'test_control' => $testControl,
                        'type' => $extraAttributes['type'] ?? 'indexing',
                        'SOURCE' => $extraAttributes['source'] ?? 'indexing',
                        'created_by' => $extraAttributes['created_by'] ?? $defaultName,
                        'updated_by' => $extraAttributes['updated_by'] ?? $defaultName,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    DB::connection('sqlsrv')->table('fileNumber')->insert($insertData);

                    Log::info('FileIndexing::updateFileNumberTable - inserted new fileNumber row', [
                        'file_number' => $fileNumber,
                        'tracking_id' => $trackingId,
                        'insert_data' => $insertData,
                    ]);
                }
            } else {
                Log::info('FileIndexing::updateFileNumberTable - completed', [
                    'file_number' => $fileNumber,
                    'tracking_id' => $trackingId,
                    'rows_updated' => $affected,
                ]);
            }
        } catch (\Throwable $exception) {
            Log::warning('FileIndexing::updateFileNumberTable - error', [
                'file_number' => $fileNumber,
                'tracking_id' => $trackingId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Update CofO staging table
     */
    protected function updateCofORecord($existingRecord, $validated, Request $request, ?string $testControl = null, bool $hasCofo = false, ?int $propId = null)
    {
        if (!$hasCofo) {
            return;
        }

        $cofoPayload = $this->extractCofOInput($request);

        $payloadFields = array_filter($cofoPayload, static function ($value) {
            return !is_null($value) && $value !== '';
        });

        if (empty($payloadFields)) {
            return;
        }

        $fileNumber = trim((string) ($validated['file_number'] ?? ''));
        $tempFileNo = trim((string) ($validated['temp_file_no'] ?? $existingRecord->temp_file_no ?? ''));

        $hasTempFileno = Schema::connection('sqlsrv')->hasColumn(self::COFO_TABLE, 'temp_fileno');

        // Determine the best match key for looking up / inserting the CofO row.
        // Priority: explicit cofo_no > main file_number > temp_fileno (for temp-only records).
        $matchColumns = [];
        if (!empty($cofoPayload['cofo_no'])) {
            $matchColumns['cofo_no'] = $cofoPayload['cofo_no'];
        } elseif ($fileNumber !== '') {
            $matchColumns['mlsFNo'] = $fileNumber;
        } elseif ($tempFileNo !== '' && $hasTempFileno) {
            $matchColumns['temp_fileno'] = $tempFileNo;
        }

        if (empty($matchColumns)) {
            return;
        }

        $registrationNumber = $this->formatRegistrationNumber(
            $cofoPayload['serial_no'] ?? null,
            $cofoPayload['page_no'] ?? null,
            $cofoPayload['volume_no'] ?? null
        );

        $transactionDate = $cofoPayload['transaction_date'] ?? $cofoPayload['cofo_date'] ?? $cofoPayload['deeds_date'] ?? null;

        $recordPayload = [
            'cofo_no' => $cofoPayload['cofo_no'] ?? null,
            'transaction_type' => $cofoPayload['instrument_type'] ?? null,
            'instrument_type' => $cofoPayload['instrument_type'] ?? null,
            'cofo_type' => $cofoPayload['cofo_type'] ?? null,
            'transaction_date' => $transactionDate,
            'transaction_time' => $cofoPayload['deeds_time'] ?? null,
            'cofo_date' => $cofoPayload['cofo_date'] ?? $transactionDate,
            'serialNo' => $cofoPayload['serial_no'] ?? null,
            'pageNo' => $cofoPayload['page_no'] ?? ($cofoPayload['serial_no'] ?? null),
            'volumeNo' => $cofoPayload['volume_no'] ?? null,
            'regNo' => $registrationNumber,
            'period' => $cofoPayload['period'] ?? null,
            'period_unit' => $cofoPayload['period_unit'] ?? null,
            'status' => $cofoPayload['status'] ?? 'Active',
            'Grantor' => $cofoPayload['first_party'] ?? null,
            'Grantee' => $cofoPayload['second_party'] ?? null,
            'land_use' => $cofoPayload['land_use'] ?? $validated['land_use_type'],
            'property_description' => $validated['location'] ?? $validated['district'],
            'location' => $validated['location'] ?? $validated['district'],
            'plot_no' => $validated['plot_number'],
            'lgsaOrCity' => $validated['lga'],
            'updated_by' => Auth::id(),
            'updated_at' => now(),
        ];

        // Only set mlsFNo when a confirmed main file number exists.
        if ($fileNumber !== '') {
            $recordPayload['mlsFNo'] = $fileNumber;
        }

        // Always persist the temp file number so the row can be found by it later.
        if ($tempFileNo !== '' && $hasTempFileno) {
            $recordPayload['temp_fileno'] = $tempFileNo;
        }

        if ($testControl) {
            $recordPayload['test_control'] = $testControl;
        }
        if ($propId !== null) {
            $recordPayload['prop_id'] = $propId;
        }

        $table = DB::connection('sqlsrv')->table(self::COFO_TABLE);
        $existing = $table->where($matchColumns)->first();

        if ($existing) {
            $table->where('id', $existing->id)->update($recordPayload);
        } else {
            $recordPayload['created_at'] = now();
            $recordPayload['created_by'] = Auth::id();
            $table->insert(array_merge($matchColumns, $recordPayload));
        }
    }

    /**
     * Update Entity and Customer records
     */
    protected function updateEntityAndCustomerRecords($existingRecord, Request $request, ?string $testControl = null, bool $allowCreate = true)
    {
        // Use the request's file_number (already validated and potentially renamed) so that
        // any file_number rename cascaded by cascadeFileNumberRename() is reflected here.
        $fileNumber = $request->input('file_number', $existingRecord->file_number);

        // Update Entity
        $entityPayload = $request->input('entity_details');
        if (!is_array($entityPayload)) {
            $entityPayload = [
                'entity_id' => $request->input('entity_id'),
                'entity_name' => $request->input('entity_name'),
                'entity_type' => $request->input('entity_type'),
            ];
        }

        $entityName = $this->normalizeValue($entityPayload['entity_name'] ?? null);

        if ($entityName !== null) {
            $entity = null;
            if (!empty($entityPayload['entity_id'])) {
                $entity = Entity::on('sqlsrv')->find($entityPayload['entity_id']);
            }

            if (!$entity) {
                if ($allowCreate) {
                    $entity = Entity::on('sqlsrv')->firstOrNew(['file_number' => $fileNumber]);
                } else {
                    $entity = Entity::on('sqlsrv')->where('file_number', $fileNumber)->first();
                }
            }

            if ($entity) {
                $entity->entity_name = $entityName;
                $entity->entity_type = $entityPayload['entity_type'] ?? 'Individual';
                $entity->file_number = $fileNumber;

                if ($testControl) {
                    $entity->test_control = $testControl;
                }

                $entity->save();
            }
        }

        // Update Customer
        $customerPayload = $request->input('customer_details');
        if (!is_array($customerPayload)) {
            $customerPayload = [
                'customer_id' => $request->input('customer_id'),
                'customer_type' => $request->input('customer_type'),
                'customer_name' => $request->input('customer_name'),
                'status' => $request->input('customer_status'),
                'account_no' => $request->input('customer_account_no'),
                'customer_code' => $request->input('customer_code'),
                'email' => $request->input('customer_email'),
                'phone' => $request->input('customer_phone'),
                'property_address' => $request->input('customer_property_address'),
                'reason_retired' => $request->input('customer_reason_retired'),
                'retired_by' => $request->input('customer_retired_by'),
            ];
        }

        $customerName = $this->normalizeValue($customerPayload['customer_name'] ?? null);
        $customerIndicators = [
            $customerName,
            $this->normalizeValue($customerPayload['phone'] ?? null),
            $this->normalizeValue($customerPayload['email'] ?? null),
            $this->normalizeValue($customerPayload['account_no'] ?? null),
            $this->normalizeValue($customerPayload['property_address'] ?? null),
            $this->normalizeValue($customerPayload['property_address'] ?? null),
            $this->normalizeValue($customerPayload['reason_retired'] ?? null),
            $this->normalizeValue($customerPayload['retired_by'] ?? null),
        ];

        $hasCustomerData = array_filter($customerIndicators, static function ($value) {
            return $value !== null;
        });

        if (!empty($hasCustomerData)) {
            if ($customerName === null) {
                $customerName = $entityName ?? 'Unknown Customer';
            }

            $customer = null;
            if (!empty($customerPayload['customer_id'])) {
                $customer = Customer::on('sqlsrv')->find($customerPayload['customer_id']);
            }

            if (!$customer) {
                if ($allowCreate) {
                    $customer = Customer::on('sqlsrv')->firstOrNew([
                        'file_number' => $fileNumber,
                        'customer_name' => $customerName,
                    ]);
                } else {
                    $customer = Customer::on('sqlsrv')
                        ->where('file_number', $fileNumber)
                        ->where('customer_name', $customerName)
                        ->first();
                }
            }

            if ($customer) {
                $customer->customer_type = $customerPayload['customer_type'] ?? 'Individual';
                $customer->status = $customerPayload['status'] ?? 'Active';
                $customer->customer_name = $customerName;
                $customer->file_number = $fileNumber;
                $customer->account_no = $this->normalizeValue($customerPayload['account_no'] ?? null);
                $customer->customer_code = $this->normalizeValue($customerPayload['customer_code'] ?? null);
                $customer->email = $this->normalizeValue($customerPayload['email'] ?? null);
                $customer->phone = $this->normalizeValue($customerPayload['phone'] ?? null);
                $customer->property_address = $this->normalizeValue($customerPayload['property_address'] ?? null);
                $customer->reason_retired = $this->normalizeValue($customerPayload['reason_retired'] ?? null);
                $customer->retired_by = $this->normalizeValue($customerPayload['retired_by'] ?? null);

                if ($testControl) {
                    $customer->test_control = $testControl;
                }

                if (!$customer->exists) {
                    $customer->created_by = Auth::id();
                }
                $customer->updated_by = Auth::id();

                $customer->save();
            }
        }
    }

    /**
     * Check if a file number has already been indexed
     */
    public function checkIndexed(Request $request)
    {
        $fileno = trim((string) $request->query('fileno', ''));

        if ($fileno === '') {
            return response()->json(['exists' => false]);
        }

        $variants = $this->buildFileNumberVariants($fileno);
        $normalizedFileno = $this->normalizeFileno($fileno);
        if ($normalizedFileno) {
            $variants[] = $normalizedFileno;
        }
        $variants = array_values(array_unique(array_filter($variants)));

        $normalizedVariants = array_map(static function ($value) {
            return preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $value));
        }, $variants);
        $normalizedVariants = array_values(array_unique(array_filter($normalizedVariants)));

        try {
            $query = DB::connection('sqlsrv')->table('file_indexings');

            $query->where(function ($where) use ($variants, $normalizedVariants) {
                $applied = false;

                if (!empty($variants)) {
                    $where->whereIn('file_number', $variants);
                    $applied = true;
                }

                if (!empty($normalizedVariants)) {
                    foreach ($normalizedVariants as $variant) {
                        $clause = "REPLACE(REPLACE(REPLACE(UPPER(file_number), '-', ''), '/', ''), ' ', '') = ?";
                        if ($applied) {
                            $where->orWhereRaw($clause, [$variant]);
                        } else {
                            $where->whereRaw($clause, [$variant]);
                            $applied = true;
                        }
                    }
                }

                if (!$applied) {
                    $where->whereRaw('1 = 0');
                }
            });

            $record = $query
                ->orderBy('id', 'desc')
                ->first();

            if (!$record) {
                $blindReadyRequest = FileSearchRequest::query()
                    ->where(function ($where) use ($variants, $normalizedVariants) {
                        $applied = false;

                        if (!empty($variants)) {
                            $where->whereIn('file_number', $variants);
                            $applied = true;
                        }

                        if (!empty($normalizedVariants)) {
                            foreach ($normalizedVariants as $variant) {
                                $clause = "REPLACE(REPLACE(REPLACE(UPPER(file_number), '-', ''), '/', ''), ' ', '') = ?";
                                if ($applied) {
                                    $where->orWhereRaw($clause, [$variant]);
                                } else {
                                    $where->whereRaw($clause, [$variant]);
                                    $applied = true;
                                }
                            }
                        }

                        if (!$applied) {
                            $where->whereRaw('1 = 0');
                        }
                    })
                    ->where('status', FileSearchRequest::STATUS_FOUND)
                    ->where('resolved_status', FileLocationResolver::STATUS_BLIND_FOUND_FOR_INDEXING)
                    ->orderByDesc('responded_at')
                    ->orderByDesc('id')
                    ->first(['id', 'request_no', 'file_number', 'responded_at']);

                $blindNotice = null;
                if ($blindReadyRequest) {
                    $blindNotice = [
                        'request_id' => $blindReadyRequest->id,
                        'request_no' => $blindReadyRequest->request_no,
                        'file_number' => $blindReadyRequest->file_number,
                        'responded_at' => optional($blindReadyRequest->responded_at)->format('Y-m-d g:i A'),
                        'message' => sprintf(
                            'A blind request was sent for %s. Once indexing is completed, this file will be ready for logout.',
                            strtoupper(trim((string) $blindReadyRequest->file_number))
                        ),
                    ];
                }

                // If not found in index, check grouping for autofill data
                $registryContext = trim((string) $request->query('registry', 'Lands Registry'));

                /** @var \App\Services\GroupingFileNumberService $groupingService */
                $groupingService = app(\App\Services\GroupingFileNumberService::class);

                // Strip temporary (T) suffix so RES-2026-1(T) matches grouping.awaiting_fileno = RES-2026-1
                $groupingLookupFileno = trim((string) preg_replace('/\(T\)$/i', '', $fileno));
                $groupingLookupNormalized = $normalizedFileno
                    ? trim((string) preg_replace('/\(T\)$/i', '', $normalizedFileno))
                    : $groupingLookupFileno;

                $groupingResult = $groupingService->findGroupingRecord(
                    DB::connection('sqlsrv'),
                    $groupingLookupFileno,
                    $groupingLookupNormalized,
                    $registryContext
                );

                if ($groupingResult['record']) {
                    return response()->json([
                        'exists' => false,
                        'grouping_found' => true,
                        'grouping_record' => (array) $groupingResult['record'],
                        'blind_request_ready_for_indexing' => (bool) $blindNotice,
                        'blind_request_notice' => $blindNotice,
                    ]);
                }

                return response()->json([
                    'exists' => false,
                    'blind_request_ready_for_indexing' => (bool) $blindNotice,
                    'blind_request_notice' => $blindNotice,
                ]);
            }

            $responseRecord = [
                'id' => $record->id,
                'file_number' => $record->file_number,
                'st_fillno' => $record->st_fillno,
                'file_title' => $record->file_title,
                'land_use_type' => $record->land_use_type,
                'plot_number' => $record->plot_number,
                'district' => $record->district,
                'lga' => $record->lga,
                'tracking_id' => $record->tracking_id,
                'tp_no' => $record->tp_no,
                'lpkn_no' => $record->lpkn_no,
                'location' => $record->location,
                'registry' => $record->registry,
                'serial_no' => $record->serial_no,
                'batch_no' => $record->batch_no,
                'registry_batch_no' => $record->registry_batch_no ?? $record->group_no,
                'sys_batch_no' => $record->sys_batch_no,
                'group_no' => $record->group_no,
                'batch_id' => $record->batch_id ?? null,
                'physical_registry' => $record->physical_registry ?? null,
                'general_registry' => $record->general_registry,
                'shelf_location' => $record->shelf_location,
                'shelf_label_id' => $record->shelf_label_id,
                'indexed_by' => $record->indexed_by ?? null,
                'indexed_date' => $record->indexed_date ?? null,
                'has_cofo' => (int) ($record->has_cofo ?? 0),
                'is_merged' => (int) ($record->is_merged ?? 0),
                'has_transaction' => (int) ($record->has_transaction ?? 0),
                'is_problematic' => (int) ($record->is_problematic ?? 0),
                'is_co_owned_plot' => (int) ($record->is_co_owned_plot ?? 0),
                'file_classification' => $record->file_classification ?? null,
                'file_classification_remarks' => $record->file_classification_remarks ?? null,
                'file_type' => $record->file_type ?? null,
                'dob' => $record->dob ?? null,
                'nin' => $record->nin ?? null,
                'tin' => $record->tin ?? null,
                'rc_no' => $record->rc_no ?? null,
                'country_code' => $record->country_code ?? null,
                'phone' => $record->phone ?? null,
                'residence_address' => $record->residence_address ?? null,
                'dciv_reason' => $record->dciv_reason ?? null,
                'created_at' => $record->created_at,
                'updated_at' => $record->updated_at,
                'root_of_title' => $record->root_of_title ?? null,
                'current_holder' => $record->current_holder ?? $record->file_title,
                'original_holder' => $record->original_holder ?? $record->file_title,
                'related_fileno' => $record->related_fileno ?? null,
                'plot_size' => $record->plot_size ?? null,
                // Entity details
                'entity_type' => $record->entity_type ?? null,
                'entity_name' => $record->entity_name ?? null,
                'entity_physical_address' => $record->entity_physical_address ?? null,
                'entity_id' => $record->entity_id ?? null,
                // Customer details
                'customer_type' => $record->customer_type ?? null,
                'customer_name' => $record->customer_name ?? null,
                'customer_account_no' => $record->customer_account_no ?? $record->account_no ?? null,
                'customer_code' => $record->customer_code ?? null,
                'customer_property_address' => $record->customer_property_address ?? $record->property_address ?? null,
                'customer_id' => $record->customer_id ?? null,
                'has_rofo' => (int) ($record->has_rofo ?? 0),
            ];

            // Enrich with RoFO details from PRA table
            if ($record->has_rofo ?? false) {
                $rofoDetails = $this->prepareRofoDetailsForEdit($record);
                $responseRecord = array_merge($responseRecord, $rofoDetails);
            }

            // Existing property transactions for this file number, so the
            // "Add Property Transaction Details" modal can backfill them.
            $fileTransactions = $this->fetchFileTransactionsForFileNumber($record->file_number);

            return response()->json([
                'exists' => true,
                'record' => $responseRecord,
                'file_transactions' => $fileTransactions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'exists' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Return the property detail fields of an indexed file (its latest file_indexings
     * row), matched on file_number OR temp_file_no. Null when the file isn't indexed.
     * Used to backfill related-file details on the edit form.
     *
     * @return array{file_title: ?string, location: ?string, plot_number: ?string, tp_no: ?string, lpkn_no: ?string}|null
     */
    private function indexedRelatedDetails(string $fileNumber): ?array
    {
        $fileNumber = trim($fileNumber);
        if ($fileNumber === '') {
            return null;
        }

        $row = DB::connection('sqlsrv')->table('file_indexings')
            ->where(function ($query) use ($fileNumber) {
                $query->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = ?', [strtoupper($fileNumber)])
                    ->orWhereRaw('UPPER(LTRIM(RTRIM(temp_file_no))) = ?', [strtoupper($fileNumber)]);
            })
            ->orderByDesc('id')
            ->first(['file_title', 'location', 'plot_number', 'tp_no', 'lpkn_no']);

        if (!$row) {
            return null;
        }

        return [
            'file_title' => $row->file_title,
            'location' => $row->location,
            'plot_number' => $row->plot_number,
            'tp_no' => $row->tp_no,
            'lpkn_no' => $row->lpkn_no,
        ];
    }

    /**
     * AJAX endpoint: fetch an indexed file's detail fields so the Related File Number
     * details modal can prefill when a related file number is selected in the picker.
     */
    public function relatedIndexedDetails(Request $request)
    {
        $fileNumber = trim((string) $request->get('file_number', ''));

        if ($fileNumber === '') {
            return response()->json([
                'success' => false,
                'message' => 'File number is required.'
            ], 422);
        }

        $details = $this->indexedRelatedDetails($fileNumber);

        return response()->json([
            'success' => (bool) $details,
            'indexed' => (bool) $details,
            'data' => $details,
        ]);
    }

    public function lookupByFileNumber(Request $request)
    {
        $fileNumber = trim((string) $request->get('file_number', ''));

        if ($fileNumber === '') {
            return response()->json([
                'success' => false,
                'message' => 'File number is required.'
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

        try {
            $columns = ['fn.st_file_no', 'fn.mlsfNo', 'fn.kangisFileNo', 'fn.NewKANGISFileNo'];

            $builder = DB::connection('sqlsrv')
                ->table('dbo.fileNumber as fn')
                ->leftJoinSub(
                    DB::connection('sqlsrv')->table('file_indexings')
                        ->select(['file_number', DB::raw('MAX(id) as max_id')])
                        ->groupBy('file_number'),
                    'fi_max',
                    fn($join) => $join->on('fi_max.file_number', '=', 'fn.mlsfNo')
                        ->orOn('fi_max.file_number', '=', 'fn.st_file_no')
                        ->orOn('fi_max.file_number', '=', 'fn.kangisFileNo')
                        ->orOn('fi_max.file_number', '=', 'fn.NewKANGISFileNo')
                )
                ->leftJoin('file_indexings as fi', 'fi.id', '=', 'fi_max.max_id')
                ->select([
                    'fn.id',
                    'fn.st_file_no',
                    'fn.mlsfNo',
                    'fn.kangisFileNo',
                    'fn.NewKANGISFileNo',
                    'fn.FileName',
                    'fn.tracking_id',
                    'fi.id as file_indexing_id',
                    'fi.lga',
                    'fi.location',
                    'fi.district',
                    'fi.land_use_type',
                    'fi.residence_address',
                ])
                ->whereNotNull('fn.tracking_id')
                ->whereRaw("LTRIM(RTRIM(fn.tracking_id)) != ''")
                ->where(function ($query) {
                    $query->whereNull('fn.is_deleted')
                        ->orWhere('fn.is_deleted', 0);
                })
                ->where(function ($query) use ($columns, $variants, $normalizedVariants) {
                    $addedClause = false;

                    if (!empty($variants)) {
                        foreach ($columns as $column) {
                            foreach ($variants as $variant) {
                                if ($variant === '') {
                                    continue;
                                }

                                if (!$addedClause) {
                                    $query->where($column, $variant);
                                    $addedClause = true;
                                } else {
                                    $query->orWhere($column, $variant);
                                }
                            }
                        }
                    }

                    if (!empty($normalizedVariants)) {
                        foreach ($normalizedVariants as $normalizedVariant) {
                            foreach ($columns as $column) {
                                $expression = "REPLACE(REPLACE(REPLACE(UPPER($column), '-', ''), '/', ''), ' ', '')";

                                if (!$addedClause) {
                                    $query->whereRaw("$expression = ?", [$normalizedVariant]);
                                    $addedClause = true;
                                } else {
                                    $query->orWhereRaw("$expression = ?", [$normalizedVariant]);
                                }
                            }
                        }
                    }
                })
                ->orderByDesc('fn.id')
                ->limit(1);

            $record = $builder->first();

            if (!$record || empty($record->tracking_id)) {
                $trackerFallback = $this->lookupTrackerFallback($variants, $normalizedVariants);

                if (!$trackerFallback) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No file metadata with a tracking ID was found for this file number.'
                    ], 404);
                }

                return response()->json([
                    'success' => true,
                    'data' => $trackerFallback,
                    'message' => 'File metadata loaded from tracker history.'
                ]);
            }

            $preferredOrder = [
                'st_file_no' => $record->st_file_no,
                'mlsfNo' => $record->mlsfNo,
                'kangisFileNo' => $record->kangisFileNo,
                'NewKANGISFileNo' => $record->NewKANGISFileNo,
            ];

            $upperVariants = array_map(static function ($value) {
                return strtoupper(trim($value));
            }, $variants);

            $resolvedFileNumber = null;
            foreach ($preferredOrder as $value) {
                $trimmed = strtoupper(trim((string) $value));
                if ($trimmed !== '' && in_array($trimmed, $upperVariants, true)) {
                    $resolvedFileNumber = $value;
                    break;
                }
            }

            if ($resolvedFileNumber === null) {
                foreach ($preferredOrder as $value) {
                    if (!empty($value)) {
                        $resolvedFileNumber = $value;
                        break;
                    }
                }
            }

            if ($resolvedFileNumber === null) {
                $resolvedFileNumber = $fileNumber;
            }

            // Check if file exists in Digital Archive (has completed scanning + page typing)
            $inDigitalArchive = false;
            $numPages = null;
            try {
                // Use the file_indexing_id from the joined fi record if available,
                // otherwise fall back to querying by file_number variants.
                $indexingId = $record->file_indexing_id ?? null;

                if ($indexingId) {
                    // Check if this specific file_indexing record has completed scanning + page typing
                    $archiveRecord = DB::connection('sqlsrv')
                        ->table('file_indexings')
                        ->where('id', $indexingId)
                        ->whereHas('pagetypings')
                        ->whereHas('scannings')
                        ->whereColumn(
                            DB::raw('(SELECT COUNT(*) FROM pagetypings WHERE pagetypings.file_indexing_id = file_indexings.id)'),
                            '>=',
                            DB::raw('(SELECT COUNT(*) FROM scannings WHERE scannings.file_indexing_id = file_indexings.id)')
                        )
                        ->first();

                    if ($archiveRecord) {
                        $inDigitalArchive = true;
                        $numPages = (int) DB::connection('sqlsrv')
                            ->table('pagetypings')
                            ->where('file_indexing_id', $archiveRecord->id)
                            ->count();
                    }
                }

                // Fallback: if no fi match or fi.id not found, try by file_number variants
                if (!$inDigitalArchive) {
                    $archiveQuery = DB::connection('sqlsrv')
                        ->table('file_indexings')
                        ->where(function ($q) use ($variants, $normalizedVariants) {
                            $added = false;
                            $fileNumberColumns = ['file_number', 'mls_file_no'];
                            foreach ($fileNumberColumns as $column) {
                                foreach ($variants as $variant) {
                                    if ($variant === '') continue;
                                    if (!$added) {
                                        $q->where($column, $variant);
                                        $added = true;
                                    } else {
                                        $q->orWhere($column, $variant);
                                    }
                                }
                            }
                            foreach ($normalizedVariants as $nv) {
                                $expr = "REPLACE(REPLACE(REPLACE(UPPER(file_number), '-', ''), '/', ''), ' ', '')";
                                if (!$added) {
                                    $q->whereRaw("$expr = ?", [$nv]);
                                    $added = true;
                                } else {
                                    $q->orWhereRaw("$expr = ?", [$nv]);
                                }
                            }
                            if (!$added) {
                                $q->whereRaw('1 = 0');
                            }
                        })
                        ->whereHas('pagetypings')
                        ->whereHas('scannings')
                        ->whereColumn(
                            DB::raw('(SELECT COUNT(*) FROM pagetypings WHERE pagetypings.file_indexing_id = file_indexings.id)'),
                            '>=',
                            DB::raw('(SELECT COUNT(*) FROM scannings WHERE scannings.file_indexing_id = file_indexings.id)')
                        );
                    $indexingRecord = $archiveQuery->first();

                    if ($indexingRecord) {
                        $inDigitalArchive = true;
                        $numPages = (int) DB::connection('sqlsrv')
                            ->table('pagetypings')
                            ->where('file_indexing_id', $indexingRecord->id)
                            ->count();
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('lookupByFileNumber: digital archive check failed', [
                    'file_number' => $fileNumber,
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $record->id,
                    'file_indexing_id' => $record->id,
                    'is_indexed' => true,
                    'file_number' => $resolvedFileNumber,
                    'st_file_number' => $record->st_file_no,
                    'mls_file_number' => $record->mlsfNo,
                    'kangis_file_number' => $record->kangisFileNo,
                    'new_kangis_file_number' => $record->NewKANGISFileNo,
                    'tracking_id' => $record->tracking_id,
                    'file_name' => $record->FileName,
                    'lga' => $record->lga ?? null,
                    'location' => $record->location ?? null,
                    'district' => $record->district ?? null,
                    'land_use_type' => $record->land_use_type ?? null,
                    'residence_address' => $record->residence_address ?? null,
                    'in_digital_archive' => $inDigitalArchive,
                    'num_pages' => $numPages,
                    'resolved_at' => now()->toIso8601String(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('lookupByFileNumber failed', [
                'file_number' => $fileNumber,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve file metadata for the supplied file number.'
            ], 500);
        }
    }
    /**
     * Return the total number of scanned pages for a file number.
     *
     * Uses the file_indexings → scannings relationship:
     *   SELECT COUNT(s.id) FROM file_indexings fi
     *   LEFT JOIN scannings s ON s.file_indexing_id = fi.id
     *   WHERE fi.file_number = :file_number
     *
     * Drives the auto-populated, read-only "Number of Pages" field on the
     * Create File Tracker form.
     */
    public function scanCountByFileNumber(Request $request)
    {
        $fileNumber = trim((string) $request->get('file_number', ''));

        if ($fileNumber === '') {
            return response()->json([
                'success' => false,
                'message' => 'File number is required.',
                'total_scans' => 0,
            ], 422);
        }

        try {
            $variants = $this->buildFileNumberVariants($fileNumber);
            $normalized = $this->normalizeFileno($fileNumber);
            if ($normalized !== null) {
                $variants[] = $normalized;
            }
            $variants = array_values(array_unique(array_filter($variants)));

            if (empty($variants)) {
                $variants = [$fileNumber];
            }

            $totalScans = (int) DB::connection('sqlsrv')
                ->table('file_indexings as fi')
                ->leftJoin('scannings as s', 's.file_indexing_id', '=', 'fi.id')
                ->where(function ($query) use ($variants) {
                    foreach ($variants as $i => $variant) {
                        $i === 0
                            ? $query->where('fi.file_number', $variant)
                            : $query->orWhere('fi.file_number', $variant);
                    }
                })
                ->count('s.id');

            return response()->json([
                'success' => true,
                'file_number' => $fileNumber,
                'total_scans' => $totalScans,
            ]);
        } catch (\Throwable $e) {
            Log::warning('scanCountByFileNumber failed', [
                'file_number' => $fileNumber,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve scan count for the supplied file number.',
                'total_scans' => 0,
            ], 500);
        }
    }

    public function getSltrGroupingDetails(Request $request)
    {
        $fileNumber = $request->input('file_number');

        if (!$fileNumber) {
            return response()->json([
                'success' => false,
                'message' => 'File number is required'
            ], 400);
        }

        $sltrGrouping = DB::connection('sqlsrv')->table('sltr_grouping')
            ->where('sltr_awaiting_fileno', $fileNumber)
            ->first();

        if ($sltrGrouping) {
            return response()->json([
                'success' => true,
                'data' => [
                    'sub_prefix' => $sltrGrouping->sub_prefix,
                    'suffix' => $sltrGrouping->suffix
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'SLTR grouping record not found'
        ], 404);
    }

    protected function findAssociatedTempRecord($fileNumber)
    {
        $fileNumber = trim((string) $fileNumber);
        if ($fileNumber === '') {
            return null;
        }
        $baseFileNumber = preg_replace('/\(\s*T\s*\)\s*$/i', '', $fileNumber);
        $normalizedBase = preg_replace('/[^A-Z0-9]/', '', strtoupper($baseFileNumber));

        return \App\Models\FileIndexing::on('sqlsrv')
            ->where(function ($q) {
                $q->whereNull('file_number')->orWhere('file_number', '');
            })
            ->whereNotNull('temp_file_no')
            ->get()
            ->first(function ($row) use ($normalizedBase) {
                $tempNo = preg_replace('/\(\s*T\s*\)\s*$/i', '', trim($row->temp_file_no));
                $normalizedTemp = preg_replace('/[^A-Z0-9]/', '', strtoupper($tempNo));
                return $normalizedTemp === $normalizedBase;
            });
    }

    public function checkTempAssociation(Request $request)
    {
        $fileNumber = trim((string) $request->input('file_number', ''));
        if ($fileNumber === '') {
            return response()->json(['has_associated_temp' => false]);
        }

        $existing = $this->findAssociatedTempRecord($fileNumber);

        if ($existing) {
            return response()->json([
                'has_associated_temp' => true,
                'message' => 'Warning: An existing temporary file (' . $existing->temp_file_no . ') is already associated with this main file number. Saving will merge this main file number into the existing temporary indexing record.',
                'temp_record' => $existing
            ]);
        }

        return response()->json([
            'has_associated_temp' => false
        ]);
    }

    /**
     * Does this submission need a Root of Title?
     *
     * Only two registry combinations do (client rule 2026-08-27):
     *   - SLTR              — General Registry OR Physical Registry says SLTR
     *   - Land / Registry 3 — General Registry "Land Registry" AND Physical
     *                         Registry "Registry 3 - Land"
     * Everything else (KANGIS, DCIV, the other Land physical registries) neither
     * shows the field nor validates it. rootOfTitleApplies() in
     * create-indexing-dialog.js is the same test on the form side; the two must
     * agree, or the officer is asked for a field that is not on screen.
     *
     * The automated scanning importer is exempt outright: it has no registry
     * question and no root to give, the same exemption gender carries.
     */
    private function rootOfTitleIsRequired(Request $request): bool
    {
        if ($request->input('source') === 'scanning_upload') {
            return false;
        }

        $norm = fn ($v) => preg_replace('/\s+/', ' ', strtoupper(trim((string) $v)));

        $general  = $norm($request->input('general_registry'));
        $physical = $norm($request->input('physical_registry'));

        $isSltr = str_contains($general, 'SLTR') || str_contains($physical, 'SLTR');

        $isLandRegistry3 = str_contains($general, 'LAND REGISTRY')
            && str_contains(preg_replace('/[^A-Z0-9]/', '', $physical), 'REGISTRY3');

        return $isSltr || $isLandRegistry3;
    }

    public function store(Request $request)
    {
        try {
            $resolvedDistrictInput = $this->resolveDistrictChoice(
                $request->input('district'),
                $request->input('custom_district')
            );
            $resolvedStreetName = $this->resolveDistrictChoice(
                $request->input('street_name'),
                $request->input('custom_street_name')
            );
            $request->merge([
                'district' => $resolvedDistrictInput,
                'street_name' => $resolvedStreetName,
            ]);

            // Handle bulk entries by returning success message
            if ($request->has('bulk_entries')) {
                return response()->json([
                    'success' => true,
                    'message' => 'Bulk entries are processed separately. Please check the file index listing for results.'
                ]);
            }

            // Check for duplicate file number before processing
            // Skip duplicate check when a KANGIS FileNo Placeholder is supplied —
            // the user is intentionally creating a new physical-variant record for
            // a file number that already exists (e.g. MLKN 001 alongside MLKN 01).
            $isKangisVariant = trim((string) $request->input('kangis_fileno_placeholder', '')) !== '';

            $rawRequestFileNumber = trim((string) $request->input('file_number', ''));
            $tempSuffixPattern = '/\(\s*T\s*\)\s*$/i';
            $isTempCandidate = (bool) preg_match($tempSuffixPattern, $rawRequestFileNumber);
            $baseFileNumber = trim((string) preg_replace($tempSuffixPattern, '', $rawRequestFileNumber));

            $existingTempRecord = null;
            if (!$isTempCandidate) {
                $existingTempRecord = $this->findAssociatedTempRecord($rawRequestFileNumber);
            }

            if (!$isKangisVariant && $baseFileNumber !== '') {
                if ($isTempCandidate) {
                    // Temporary file duplicate check
                    $targetTempFileNo = $baseFileNumber . '(T)';
                    $existingTemp = DB::connection('sqlsrv')->table('file_indexings')
                        ->where('temp_file_no', $targetTempFileNo)
                        ->first();

                    if ($existingTemp) {
                        return response()->json([
                            'success' => false,
                            'message' => 'This temporary file number has already been indexed. Do you want to update?',
                            'error_type' => 'duplicate',
                            'duplicate_action' => 'prompt_update',
                            'existing_record' => (array) $existingTemp,
                        ], 422);
                    }
                } else {
                    // Main file: only check for duplicates if there is NO associated temporary record
                    if (!$existingTempRecord) {
                        $variants = $this->buildFileNumberVariants($baseFileNumber);
                        $normalizedFileno = $this->normalizeFileno($baseFileNumber);
                        if ($normalizedFileno) {
                            $variants[] = $normalizedFileno;
                        }
                        $variants = array_values(array_unique(array_filter($variants)));

                        $normalizedVariants = array_map(static function ($value) {
                            return preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $value));
                        }, $variants);
                        $normalizedVariants = array_values(array_unique(array_filter($normalizedVariants)));

                        $existingQuery = DB::connection('sqlsrv')->table('file_indexings');

                        $existingQuery->where(function ($where) use ($variants, $normalizedVariants) {
                            $applied = false;

                            if (!empty($variants)) {
                                $where->whereIn('file_number', $variants);
                                $applied = true;
                            }

                            if (!empty($normalizedVariants)) {
                                foreach ($normalizedVariants as $variant) {
                                    $clause = "REPLACE(REPLACE(REPLACE(UPPER(file_number), '-', ''), '/', ''), ' ', '') = ?";
                                    if ($applied) {
                                        $where->orWhereRaw($clause, [$variant]);
                                    } else {
                                        $where->whereRaw($clause, [$variant]);
                                        $applied = true;
                                    }
                                }
                            }

                            if (!$applied) {
                                $where->whereRaw('1 = 0');
                            }
                        });

                        $existingRecord = $existingQuery->first();

                        if ($existingRecord) {
                            $existingRecordPayload = (array) $existingRecord;

                            return response()->json([
                                'success' => false,
                                'message' => 'This file number has already been indexed. Do you want to update?',
                                'error_type' => 'duplicate',
                                'duplicate_action' => 'prompt_update',
                                'existing_record' => $existingRecordPayload,
                            ], 422);
                        }
                    }
                }
            }

            // "Has New KANGIS FileNo": the selected KN-series number becomes its own
            // standalone file_indexings record (see createStandaloneNewKangisRecord()),
            // with this file only linked to it as a Related File. If that KN number has
            // already been indexed by someone else, reject rather than silently attach
            // "Has Transaction" rows to an unrelated existing record.
            $hasNewKangisFileno = $request->boolean('has_new_kangis_fileno');
            $newKangisFileNoRaw = trim((string) $request->input('new_kangis_file_no', ''));
            if ($hasNewKangisFileno && $newKangisFileNoRaw !== '') {
                $existingKangisIndex = DB::connection('sqlsrv')->table('file_indexings')
                    ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = UPPER(?)', [$newKangisFileNoRaw])
                    ->first();

                if ($existingKangisIndex) {
                    return response()->json([
                        'success' => false,
                        'message' => "New KANGIS File No \"{$newKangisFileNoRaw}\" has already been indexed. Please edit that record directly instead of attaching transactions here.",
                        'error_type' => 'kangis_duplicate',
                        'existing_record' => (array) $existingKangisIndex,
                    ], 422);
                }
            }

            $validated = $request->validate([
                'file_number' => 'required|string|max:255',
                'file_title' => 'required', // Allow array or string
                // Land Use Type is required (allow array for Block indexing or string).
                'land_use_type' => ['required'],
                'plot_number' => 'nullable',
                'tp_no' => 'nullable',
                'lpkn_no' => 'nullable',
                'location' => 'nullable',
                'latitude' => 'nullable',
                'latitude.*' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable',
                'longitude.*' => 'nullable|numeric|between:-180,180',
                'district' => 'nullable',
                'registry' => 'nullable|string|max:255',
                'physical_registry' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::notIn(['', 'Select Registry']),
                ],
                'general_registry' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::notIn(['', 'Select Registry']),
                ],
                'registry_batch_no' => 'nullable|string|max:255',
                'lga' => [
                    Rule::when($request->input('indexing_type') !== 'Block', ['required', Rule::notIn(['Select LGA', 'Select LGA Name', 'Loading LGAs...'])]),
                    Rule::when($request->input('indexing_type') === 'Block', ['nullable']),
                ],
                'plot_size' => 'required',
                'plot_size.*' => 'required',
                'related_fileno' => 'nullable|array',
                'related_fileno.*' => 'string|max:255',
                'has_cofo' => 'nullable|boolean',
                'has_transaction' => 'nullable|boolean',
                'is_problematic' => 'nullable|boolean',
                'is_co_owned_plot' => 'nullable|boolean',
                'shelf_label_id' => 'nullable|integer',
                'is_merged' => 'nullable|boolean',
                'serial_no' => 'nullable|string|max:255',
                'batch_no' => 'nullable|string',
                'shelf_location' => 'nullable|string|max:255',
                'tracking_id' => 'nullable|string|max:255',
                'main_application_id' => 'nullable|integer',
                'subapplication_id' => 'nullable|integer',
                'source_file_id' => 'nullable|integer',
                'awaiting_file_no' => 'required|string|max:255',
                'group_no' => 'nullable|string|max:255',
                'sys_batch_no' => 'nullable|string|max:255',
                'shelf_rack_no' => 'nullable|string|max:255',
                'grouping_match_id' => 'nullable|integer',
                'test_control' => 'nullable|string|max:25',
                'street_name' => 'nullable',
                'file_type' => 'nullable|string|max:255',
                // Required for human data-entry; the automated scanning importer
                // (source=scanning_upload) has no gender input, so it is exempted.
                'gender' => 'required_unless:source,scanning_upload|nullable|string|in:Male,Female,Corporate,Joint',
                'dob' => 'nullable',
                'nin' => 'nullable',
                'tin' => 'nullable',
                'rc_no' => 'nullable',
                'country_code' => 'nullable',
                'phone' => 'nullable',
                'residence_address' => 'nullable',
                'dciv_reason' => 'nullable|string|max:500',
                'complainant' => 'nullable|string|max:255',
                // Root of Title is a data-entry requirement of the File Indexing form
                // (create, edit and KANGIS update all post here), but only for the two
                // registry combinations that have a root to state — see
                // rootOfTitleIsRequired(). The non-form write paths have no such field,
                // so they are exempted the same way gender is.
                'root_of_title' => [
                    Rule::requiredIf(fn () => $this->rootOfTitleIsRequired($request)),
                    'nullable', 'string', 'max:255',
                ],
                'current_holder' => 'nullable',
                'original_holder' => 'nullable',
                // Entity & Customer fields
                'entity_id' => 'nullable|integer',
                'entity_name' => 'nullable|string|max:255',
                'entity_type' => 'nullable|string|in:Individual,Corporate,Multiple Owners',
                'entity_physical_address' => 'nullable|string',
                'customer_id' => 'nullable|integer',
                'customer_name' => 'nullable|string|max:255',
                'customer_type' => 'nullable|string|in:Individual,Corporate,Multiple Owners',
                'sub_prefix' => 'nullable|string|max:50',
                'suffix' => 'nullable|string|max:50',
                'customer_account_no' => 'nullable|string|max:255',
                'customer_code' => 'nullable|string|max:255',
                'customer_status' => 'nullable|string|max:50',
                'property_address' => 'nullable|string',
                'indexing_type' => 'nullable|string|in:Regular,Block',
                'file_classification' => 'nullable|string|max:50',
                'file_classification_remarks' => 'nullable|string',
                // RoFO fields
                'has_rofo' => 'nullable|boolean',
                'rofo_instrument_type' => 'nullable|string|max:255',
                'rofo_date' => 'nullable|date',
                'rofo_number' => 'nullable|string|max:255',
                'rofo_file_number' => 'nullable|string|max:255',
                'rofo_land_use' => 'nullable|string|max:255',
                'rofo_grantor' => 'nullable|string|max:255',
                'rofo_grantee' => 'nullable|string|max:255',
                'rofo_status' => 'nullable|string|max:50',
                // Occupancy Permit fields
                'has_occupancy_permit' => 'nullable|boolean',
                'occupancy_permit_instrument_type' => 'nullable|string|max:255',
                'occupancy_permit_op_type' => 'nullable|string|max:100',
                'occupancy_permit_op_category' => 'nullable|string|max:100',
                'occupancy_permit_op_serial_number' => 'nullable|string|max:100',
                'occupancy_permit_date' => 'nullable|date',
                'occupancy_permit_file_number' => 'nullable|string|max:255',
                'occupancy_permit_land_use' => 'nullable|string|max:255',
                'occupancy_permit_grantor' => 'nullable|string|max:255',
                'occupancy_permit_grantee' => 'nullable|string|max:255',
                'occupancy_permit_status' => 'nullable|string|max:50',
                'occupancy_permit_serial_no' => 'nullable|string|max:100',
                'occupancy_permit_page_no' => 'nullable|string|max:100',
                'occupancy_permit_vol_no' => 'nullable|string|max:100',
                'occupancy_permit_deeds_time' => 'nullable|string|max:10',
                'occupancy_permit_deeds_date' => 'nullable|date',
                'cofo_status' => 'nullable|string|max:50',
                'cofo_type' => 'nullable|string|max:100',
                'has_temp_file' => 'nullable|boolean',
                'temp_file_no' => 'nullable|string|max:255',
                // KANGIS placeholder is required only when KANGIS registry is selected.
                'kangis_fileno_placeholder' => [
                    Rule::requiredIf(function () use ($request) {
                        return str_contains(strtoupper((string) $request->input('general_registry', '')), 'KANGIS');
                    }),
                    'nullable',
                    'string',
                    'max:100',
                ],
                // New KANGIS FileNo (KN-series) — distinct from the legacy KANGIS placeholder.
                // Required only when the "Has New KANGIS FileNo" checkbox is ticked.
                'has_new_kangis_fileno' => 'nullable|boolean',
                'new_kangis_file_no' => [
                    Rule::requiredIf(function () use ($request) {
                        return $request->boolean('has_new_kangis_fileno');
                    }),
                    'nullable',
                    'string',
                    'max:255',
                ],
                // New KANGIS transactions — each row is saved to the pra table on submit.
                'has_new_kangis_transaction' => 'nullable|boolean',
                'transactions' => 'nullable|array',
                'transactions.*.instrument_type'  => 'nullable|string|max:255',
                'transactions.*.transaction_date' => 'nullable|date',
                'transactions.*.serial_no'        => 'nullable|string|max:100',
                'transactions.*.page_no'          => 'nullable|string|max:100',
                'transactions.*.vol_no'           => 'nullable|string|max:100',
                'transactions.*.reg_date'         => 'nullable|date',
                'transactions.*.reg_time'         => 'nullable|string|max:20',
                'transactions.*.grantor'          => 'nullable|string|max:500',
                'transactions.*.grantee'          => 'nullable|string|max:500',
                // Bill Balance & Grant Rent (repeatable: arrays). Kept permissive so
                // partial/empty repeater rows never block submission; the
                // FileIndexingBillService sanitizes each value.
                'bill_total_amount'        => 'nullable|array',
                'bill_from_year'           => 'nullable|array',
                'bill_to_year'             => 'nullable|array',
                'bill_receipt_no'          => 'nullable|array',
                'bill_receipt_date'        => 'nullable|array',
                'ground_rent_amount'       => 'nullable|array',
                'ground_rent_from_year'    => 'nullable|array',
                'ground_rent_to_year'      => 'nullable|array',
                'ground_rent_receipt_no'   => 'nullable|array',
                'ground_rent_receipt_date' => 'nullable|array',
            ], [
                'land_use_type.required' => 'Please select a Land Use Type.',
                'root_of_title.required' => 'Please enter the Root of Title.',
                'kangis_fileno_placeholder.required' => 'KANGIS FileNo Placeholder is required when KANGIS Registry is selected.',
                'new_kangis_file_no.required' => 'New KANGIS File No is required when "Has New KANGIS FileNo" is checked.',
            ]);

            // Transform current_holder if it's an array (Block indexing)
            if ($request->has('current_holder') && is_array($request->input('current_holder'))) {
                $validated['current_holder'] = array_values(array_filter($request->input('current_holder')));
            } else {
                $validated['current_holder'] = trim((string) $request->input('current_holder', ''));
            }

            // Transform original_holder if it's an array (Block indexing)
            if ($request->has('original_holder') && is_array($request->input('original_holder'))) {
                $validated['original_holder'] = array_values(array_filter($request->input('original_holder')));
            } else if ($request->has('original_holder')) {
                $validated['original_holder'] = trim((string) $request->input('original_holder', ''));
            }

            // Handle DCIV Registry specific logic for file_title and complainant
            if (strcasecmp($validated['general_registry'] ?? '', 'DCIV Registry') === 0) {
                $validated['complainant'] = $validated['file_title'];
            }

            $validated['district'] = $this->normalizeChoiceValue($validated['district'] ?? null);
            $validated['street_name'] = $this->normalizeChoiceValue($validated['street_name'] ?? null);
            $validated['lga'] = $this->normalizeChoiceValue($validated['lga'] ?? null);
            $validated['registry_batch_no'] = $this->normalizeChoiceValue($validated['registry_batch_no'] ?? null);

            // If these are arrays (Block Indexing), pick the first one for the main summary record
            if (is_array($validated['district'])) {
                $validated['district'] = $validated['district'][0] ?? null;
            }
            if (is_array($validated['street_name'])) {
                $validated['street_name'] = $validated['street_name'][0] ?? null;
            }
            if (is_array($validated['lga'])) {
                $validated['lga'] = $validated['lga'][0] ?? null;
            }
            if (is_array($validated['registry_batch_no'])) {
                $validated['registry_batch_no'] = $validated['registry_batch_no'][0] ?? null;
            }

            if ($request->input('indexing_type') !== 'Block' && ($validated['lga'] ?? null) === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'LGA selection is required. Please choose a valid option before submitting.'
                ], 422);
            }

            $awaitingFileNo = Arr::pull($validated, 'awaiting_file_no');
            $groupingId = Arr::pull($validated, 'grouping_match_id');
            $inputGroupNo = Arr::pull($validated, 'group_no');
            $inputSysBatchNo = Arr::pull($validated, 'sys_batch_no');
            $inputShelfRackNo = Arr::pull($validated, 'shelf_rack_no');

            // Detect and normalise temporary file number suffix e.g. RES-2026-1(T) or RES-2026-1 (T)
            $tempSuffixPattern = '/\(\s*T\s*\)\s*$/i';
            $rawRequestFileNumber = trim((string) $request->input('file_number', ''));
            $rawRequestTempFileNo = trim((string) $request->input('temp_file_no', ''));
            $rawRequestArchiveFileNo = trim((string) $request->input('archive_file_no', ''));
            $rawFileNumber = trim((string) ($validated['file_number'] ?? ''));
            $rawAwaitingFileNo = trim((string) $awaitingFileNo);

            $tempCandidate = null;
            foreach ([$rawRequestTempFileNo, $rawRequestFileNumber, $rawAwaitingFileNo, $rawRequestArchiveFileNo, $rawFileNumber] as $candidate) {
                if ($candidate !== '' && preg_match($tempSuffixPattern, $candidate)) {
                    $tempCandidate = $candidate;
                    break;
                }
            }

            if ($tempCandidate !== null) {
                $baseFileNumber = trim((string) preg_replace($tempSuffixPattern, '', $tempCandidate));
                $validated['file_number'] = $baseFileNumber;
                $validated['has_temp_file'] = true;
                $validated['temp_file_no'] = $baseFileNumber . '(T)';
            } else {
                if ($existingTempRecord) {
                    $validated['has_temp_file'] = true;
                    $validated['temp_file_no'] = $existingTempRecord->temp_file_no;
                } else {
                    $validated['has_temp_file'] = false;
                    $validated['temp_file_no'] = null;
                }
            }

            $inputMdcBatchNo = null;
            if ($request->filled('mdc_batch_no')) {
                $inputMdcBatchNo = trim((string) $request->input('mdc_batch_no'));
                if ($inputMdcBatchNo === '') {
                    $inputMdcBatchNo = null;
                }
            }

            $requestHasCofo = filter_var($request->input('has_cofo', false), FILTER_VALIDATE_BOOLEAN)
                || (stripos($validated['file_number'] ?? '', 'cofo') !== false);

            $submittedSysBatch = $inputSysBatchNo !== null ? trim((string) $inputSysBatchNo) : null;
            $submittedShelfRack = $inputShelfRackNo !== null ? trim((string) $inputShelfRackNo) : null;
            $submittedRegistry = isset($validated['registry']) ? trim((string) $validated['registry']) : null;

            $submittedSysBatch = $submittedSysBatch === '' ? null : $submittedSysBatch;
            $submittedShelfRack = $submittedShelfRack === '' ? null : $submittedShelfRack;
            $submittedRegistry = $submittedRegistry === '' ? null : $submittedRegistry;

            $grouping = null;

            $grouping = null;
            $groupingService = app(\App\Services\GroupingFileNumberService::class);

            // Determine registry context for lookup
            $registryContext = $submittedRegistry ?? 'Lands Registry'; // Fallback to default if not provided

            if ($groupingId) {
                // Pass awaiting file number so prefix auto-detection picks the right table
                $grouping = $groupingService->findById($registryContext, $groupingId, $awaitingFileNo ?: null);
            }

            $normalizedAwaiting = $this->normalizeFileno($awaitingFileNo);

            if (!$grouping && $normalizedAwaiting) {
                // Use the service to find the record dynamically based on registry
                $result = $groupingService->findGroupingRecord(
                    DB::connection('sqlsrv'),
                    $awaitingFileNo,
                    $normalizedAwaiting,
                    $registryContext
                );

                $grouping = $result['record'];
            }

            if (!$grouping) {
                return response()->json([
                    'success' => false,
                    'message' => 'Awaiting file number must match an existing grouping record.'
                ], 422);
            }

            $normalizedGroupingAwaiting = $this->normalizeFileno($grouping->awaiting_fileno);
            // Strip an "AND EXTENSION" suffix for the awaiting-file match only. Grouping holds the
            // base number (e.g. RES-2024-10), while the submitted file number may be the extension
            // variant (RES-2024-10 AND EXTENSION). The full value is still saved via
            // $validated['file_number'] — only the comparison key is stripped.
            $fileNumberForMatch = preg_replace('/\s*AND\s+EXTENSION\s*$/i', '', (string) $validated['file_number']);
            $normalizedFileNumber = $this->normalizeFileno($fileNumberForMatch);
            $matchesGroupingAwaiting = $this->matchesGroupingAwaitingFileNumber(
                $normalizedFileNumber,
                $normalizedGroupingAwaiting,
                $submittedRegistry
            );
            $rawRelatedFiles = $validated['related_fileno'] ?? [];
            $relatedFileNos = [];
            if (is_array($rawRelatedFiles)) {
                $relatedFileNos = array_values(array_filter($rawRelatedFiles, fn($v) => !empty($v)));
            } elseif (is_string($rawRelatedFiles) && !empty($rawRelatedFiles)) {
                $relatedFileNos = [$rawRelatedFiles];
            }

            $hasRelatedSelection = !empty($relatedFileNos);

            // Debug Logging
            Log::info('Debug File Mismatch:', [
                'file_number' => $validated['file_number'],
                'normalized_file_number' => $normalizedFileNumber,
                'grouping_awaiting' => $grouping->awaiting_fileno,
                'normalized_grouping_awaiting' => $normalizedGroupingAwaiting,
                'submitted_registry' => $submittedRegistry,
                'prefix_aware_match' => $matchesGroupingAwaiting,
                'has_related_selection' => $hasRelatedSelection,
                'related_files' => $relatedFileNos
            ]);

            $normalizedRelatedFileNumber = $hasRelatedSelection ? $this->normalizeFileno($relatedFileNos[0]) : null;
            $shouldEnforcePrimaryMatch = !$hasRelatedSelection;

            if ($shouldEnforcePrimaryMatch) {
                if (!$matchesGroupingAwaiting) {
                    Log::warning('File Match Failed', [
                        'enforced' => true,
                        'match_result' => $matchesGroupingAwaiting
                    ]);
                    $debugMsg = sprintf(
                        " | Debug: File=%s, G_Await=%s, RelCount=%d, RawRel=%s",
                        $normalizedFileNumber,
                        $normalizedGroupingAwaiting ?? 'NULL',
                        count($relatedFileNos),
                        json_encode($rawRelatedFiles ?? [])
                    );

                    return response()->json([
                        'success' => false,
                        'message' => 'The selected file number does not match the awaiting file number from grouping. Please refresh the archive details.' . $debugMsg
                    ], 422);
                }
            }

            $awaitingMatchesGrouping = $this->matchesGroupingAwaitingFileNumber(
                $normalizedAwaiting,
                $normalizedGroupingAwaiting,
                $submittedRegistry
            );

            if (!$awaitingMatchesGrouping) {
                return response()->json([
                    'success' => false,
                    'message' => 'Awaiting file number does not match the grouping record.'
                ], 422);
            }

            if ($grouping->sys_batch_no !== null) {
                if ($submittedSysBatch === null || strcasecmp($submittedSysBatch, $grouping->sys_batch_no) !== 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'SYS batch number must come from the grouping record.'
                    ], 422);
                }
            }

            if ($submittedSysBatch !== null && $grouping->sys_batch_no === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'SYS batch number is not defined for the grouping record.'
                ], 422);
            }

            if ($grouping->shelf_rack !== null) {
                if ($submittedShelfRack === null || strcasecmp($submittedShelfRack, $grouping->shelf_rack) !== 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Shelf/Rack number must come from the grouping record.'
                    ], 422);
                }
            }

            // NOTE: registry-vs-grouping validation disabled — deriveRegistryFromGrouping()
            // still guesses off land-use ("COMMERCIAL" => "Registry 1 - Land", etc.), which
            // disagrees with the file-number-derived value (RegistryDetector) the client now
            // submits, so it rejected every legitimate submission. $submittedRegistry (already
            // validated client-side against the file number) is kept as-is.

            $trackingId = $grouping->tracking_id ?? ($validated['tracking_id'] ?? null);

            // KANGIS variant: only clone the grouping row when the base file number
            // already exists in file_indexings (collision). When it is the first time
            // this file number is indexed the record keeps its bare file_number and
            // the original grouping entry is updated in-place inside the transaction.
            $kangisGroupingCloned = false;
            $groupingTable = null;
            $kangisResolvedFileNo = null;
            $kangisBaseFileNo = null;
            if ($isKangisVariant) {
                $groupingService = app(\App\Services\GroupingFileNumberService::class);
                $groupingTable = $groupingService->getTableName($registryContext);
                $baseFileNo = $grouping->mls_fileno ?? $grouping->awaiting_fileno ?? null;

                // Settle the variant's own file number BEFORE the insert, and retro-rename
                // the incumbent to _1 when this is the first collision. file_indexings.
                // file_number is uniquely indexed, so inserting the bare number and
                // renaming afterwards (what resolveAndSave does) fails on the INSERT.
                $kangisBaseFileNo = trim((string) ($baseFileNo ?? ''));
                if ($kangisBaseFileNo !== '') {
                    $kangisPreResolve = app(KangisFileNoPlaceholderService::class)
                        ->resolveForNewRecord($kangisBaseFileNo);
                    $kangisResolvedFileNo = $kangisPreResolve['resolved'];
                    $validated['file_number'] = $kangisResolvedFileNo;

                    Log::info('FileIndexing::store - KANGIS variant: file number resolved pre-insert', [
                        'base_file_no' => $kangisBaseFileNo,
                        'resolved' => $kangisResolvedFileNo,
                        'renamed_siblings' => $kangisPreResolve['siblings'],
                    ]);
                }

                // Clone the grouping whenever the variant ended up with a suffix — a bare
                // resolution means this is the first record for the number and the original
                // grouping row is updated in place inside the transaction instead.
                $anyCollision = $kangisResolvedFileNo !== null && $kangisResolvedFileNo !== $kangisBaseFileNo;

                if ($anyCollision) {
                    $suffixedFileNo = $kangisResolvedFileNo;

                    $newTrackingId = 'TRK-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 8))
                        . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));

                    if ($groupingTable === 'kangis_grouping') {
                        // kangis_grouping has different column names
                        $groupingCloneData = [
                            'kangis_awaiting_fileno' => $suffixedFileNo,
                            'kangis_fileno' => $suffixedFileNo,
                            'mapping' => $grouping->mapping ?? null,
                            'group' => $grouping->group ?? null,
                            'number' => null,
                            'mdc_batch_no' => $grouping->mdc_batch_no ?? null,
                            'sys_batch_no' => $grouping->sys_batch_no ?? null,
                            'shelf_rack' => $grouping->shelf_rack ?? null,
                            'registry' => $grouping->registry ?? null,
                            'registry_batch_no' => $grouping->registry_batch_no ?? null,
                            'landuse' => $grouping->landuse ?? null,
                            'year' => $grouping->year ?? null,
                            'tracking_id' => $newTrackingId,
                            'test_control' => $grouping->test_control ?? null,
                            'created_by' => Auth::user()->name ?? Auth::id(),
                            'updated_by' => Auth::user()->name ?? Auth::id(),
                            'indexing_mapping' => 1,
                            'indexing_kangis_fileno' => $suffixedFileNo,
                            'kangis_fileno_resolved' => $suffixedFileNo,
                            'kangis_fileno_placeholder' => $request->input('kangis_fileno_placeholder'),
                            'is_indexed' => 1,
                        ];
                    } else {
                        // Default grouping table columns
                        $groupingCloneData = [
                            'awaiting_fileno' => $grouping->awaiting_fileno,
                            'mls_fileno' => $grouping->mls_fileno ?? null,
                            'mapping' => $grouping->mapping ?? null,
                            'group' => $grouping->group ?? null,
                            'batch_no' => $grouping->batch_no ?? null,
                            'mdc_batch_no' => $grouping->mdc_batch_no ?? null,
                            'sys_batch_no' => $grouping->sys_batch_no ?? null,
                            'shelf_rack' => $grouping->shelf_rack ?? null,
                            'registry' => $grouping->registry ?? null,
                            'landuse' => $grouping->landuse ?? null,
                            'year' => $grouping->year ?? null,
                            'tracking_id' => $newTrackingId,
                            'test_control' => $grouping->test_control ?? null,
                            'created_by' => Auth::user()->name ?? Auth::id(),
                            'updated_by' => Auth::user()->name ?? Auth::id(),
                            'indexing_mapping' => 1,
                            'indexing_mls_fileno' => $grouping->indexing_mls_fileno ?? $grouping->awaiting_fileno,
                        ];
                    }

                    // The clone lands outside the save transaction, so an attempt that
                    // fails later leaves the row behind. kangis_awaiting_fileno is
                    // uniquely indexed (UX_KANGIS_FileNo), which turned that leftover
                    // into a hard duplicate-key error on the retry — reuse it instead.
                    $existingClone = $groupingTable === 'kangis_grouping'
                        ? DB::connection('sqlsrv')
                            ->table('kangis_grouping')
                            ->whereRaw('UPPER(LTRIM(RTRIM(kangis_awaiting_fileno))) = UPPER(?)', [$suffixedFileNo])
                            ->first(['id', 'tracking_id'])
                        : null;

                    if ($existingClone) {
                        $clonedGroupingId = $existingClone->id;
                        $newTrackingId = $existingClone->tracking_id ?: $newTrackingId;

                        DB::connection('sqlsrv')
                            ->table('kangis_grouping')
                            ->where('id', $clonedGroupingId)
                            ->update(array_merge($groupingCloneData, ['tracking_id' => $newTrackingId]));

                        Log::info('FileIndexing::store - KANGIS variant: reused orphaned grouping clone', [
                            'grouping_id' => $clonedGroupingId,
                            'file_no' => $suffixedFileNo,
                        ]);
                    } else {
                        $clonedGroupingId = DB::connection('sqlsrv')
                            ->table($groupingTable)
                            ->insertGetId($groupingCloneData);
                    }

                    // Reload using the service so aliases are applied consistently
                    $grouping = $groupingService->findById($registryContext, $clonedGroupingId);

                    $trackingId = $newTrackingId;
                    $kangisGroupingCloned = true;

                    Log::info('FileIndexing::store - KANGIS variant: cloned grouping (collision)', [
                        'table' => $groupingTable,
                        'original_grouping_id' => $groupingIdValue ?? null,
                        'cloned_grouping_id' => $clonedGroupingId,
                        'new_tracking_id' => $newTrackingId,
                        'kangis_placeholder' => $request->input('kangis_fileno_placeholder'),
                        'resolved_file_no' => $kangisResolvedFileNo,
                    ]);
                } else {
                    // No collision: first time this file number is indexed with a placeholder.
                    // Original grouping will be updated in-place inside the transaction.
                    Log::info('FileIndexing::store - KANGIS variant: no collision, using original grouping', [
                        'grouping_id' => $groupingIdValue ?? null,
                        'kangis_placeholder' => $request->input('kangis_fileno_placeholder'),
                        'base_file_no' => $baseFileNo,
                    ]);
                }
            }

            if ($trackingId === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Grouping record is missing a tracking ID; please update the grouping entry before indexing.',
                ], 422);
            }

            $validated['tracking_id'] = $trackingId;

            $resolvedMdcBatch = $grouping->mdc_batch_no ?? $inputMdcBatchNo;
            $resolvedGroupNo = $grouping->group ?? $inputGroupNo ?? null;
            $resolvedSysBatch = $grouping->sys_batch_no ?? $submittedSysBatch;
            $resolvedShelfRack = $grouping->shelf_rack ?? $submittedShelfRack ?? ($validated['shelf_location'] ?? null);
            $resolvedTestControl = $validated['test_control'] ?? $grouping->test_control ?? null;

            $validated['group_no'] = $resolvedGroupNo;
            $validated['sys_batch_no'] = $resolvedSysBatch;
            if ($resolvedShelfRack !== null) {
                $validated['shelf_location'] = $resolvedShelfRack;
            }
            if ($resolvedMdcBatch !== null) {
                $validated['batch_no'] = $resolvedMdcBatch;
            }
            $validated['test_control'] = $resolvedTestControl;

            $groupingAwaiting = $grouping->awaiting_fileno ?? $awaitingFileNo;
            $groupingIndexingMls = $grouping->indexing_mls_fileno ?? null;
            $propIdForStore = $this->resolveTrustedPropId(
                $request->input('prop_id'),
                $validated['file_number'] ?? null,
                $validated['related_fileno'] ?? null,
                $groupingAwaiting,
                $groupingIndexingMls
            );

            $groupingSyncPayload = [
                'tracking_id' => $trackingId,
                'mdc_batch_no' => $resolvedMdcBatch,
                'test_control' => $resolvedTestControl,
                'indexing_mapping' => 1,
                'indexing_mls_fileno' => $validated['file_number'],
                'file_number' => $validated['file_number'],
            ];
            $currentUserName = $this->resolveCurrentUserName();
            $validated['created_by'] = $currentUserName;
            $validated['updated_by'] = $currentUserName;
            if (!isset($validated['workflow_status']) || empty($validated['workflow_status'])) {
                $validated['workflow_status'] = 'indexed';
            }

            $sourceFileId = Arr::pull($validated, 'source_file_id', null);
            if ($sourceFileId !== null && $sourceFileId !== '') {
                $sourceFileId = (int) $sourceFileId;
            } else {
                $sourceFileId = null;
            }
            $groupingIdValue = $grouping->id;
            $fileNumberForUpdate = $validated['file_number'];

            Log::info('FileIndexing::store - Post-Validation Debug', [
                'keys' => array_keys($validated),
                'related_fileno_exists' => array_key_exists('related_fileno', $validated),
                'related_fileno_value' => $validated['related_fileno'] ?? 'NOT SET',
                'related_fileno_type' => gettype($validated['related_fileno'] ?? null),
            ]);

            Log::info('FileIndexing::store - validated payload', [
                'registry' => $validated['registry'] ?? null,
                'tp_no' => $validated['tp_no'] ?? null,
                'location' => $validated['location'] ?? null,
                'raw_request_file_number' => $request->input('file_number'),
                'raw_request_temp_file_no' => $request->input('temp_file_no'),
                'resolved_has_temp_file' => $validated['has_temp_file'] ?? null,
                'resolved_temp_file_no' => $validated['temp_file_no'] ?? null,
                'payload' => $validated,
                'user_id' => Auth::id()
            ]);

            // Prepare file titles for Block Indexing
            $rawFileTitles = $validated['file_title'];
            if (is_string($rawFileTitles)) {
                $rawFileTitles = [$rawFileTitles];
            }

            // Prepare related file data
            $rawRelatedFiles = $validated['related_fileno'] ?? [];
            $relatedDetails = $request->input('related_details', []); // Capture details
            $indexingType = $validated['indexing_type'] ?? 'Regular';

            $relatedFileNos = [];
            // Collect from main form inputs
            if (is_array($rawRelatedFiles)) {
                $relatedFileNos = array_values(array_filter($rawRelatedFiles, fn($v) => !empty($v)));
            }

            // In block mode, also add numbers from details modal if any
            if ($indexingType === 'Block' && !empty($relatedDetails)) {
                foreach ($relatedDetails as $detail) {
                    $num = $detail['file_number'] ?? null;
                    if ($num && !in_array($num, $relatedFileNos)) {
                        $relatedFileNos[] = $num;
                    }
                }
            }

            // "Has New KANGIS FileNo": link the main file to the new KN-series record as
            // a Related File (the KN number gets its OWN file_indexings row — see
            // createStandaloneNewKangisRecord() below — this file is just related to it).
            if ($hasNewKangisFileno && $newKangisFileNoRaw !== '') {
                if (!in_array($newKangisFileNoRaw, $relatedFileNos, true)) {
                    $relatedFileNos[] = $newKangisFileNoRaw;
                }
                if ($indexingType === 'Block') {
                    $alreadyInDetails = false;
                    foreach ($relatedDetails as $detail) {
                        if (($detail['file_number'] ?? null) === $newKangisFileNoRaw) {
                            $alreadyInDetails = true;
                            break;
                        }
                    }
                    if (!$alreadyInDetails) {
                        $relatedDetails[] = ['file_number' => $newKangisFileNoRaw];
                    }
                }
            }

            // Reuse relatedFileNos calculated earlier for JSON persistence in group record if needed
            if (!empty($relatedFileNos)) {
                $validated['related_fileno'] = json_encode($relatedFileNos);
            } else {
                $validated['related_fileno'] = null;
            }

            // Logic for Group Title in Block Indexing
            if ($indexingType === 'Block') {
                $validated['file_title'] = 'Group Title';
                // Ensure current_holder is stored as JSON
                if (isset($validated['current_holder']) && !is_array($validated['current_holder'])) {
                    // It might be already a string due to validation sanitization earlier, but we want the raw array from request if available for JSON storage?
                    // The validation earlier:
                    // if (is_array) $validated['current_holder'] = array_values...
                    // else $validated['current_holder'] = trim string
                    // So it is already in good shape. Model cast 'array' will handle array -> json.
                }
            } else {
                // Regular: use first title if array
                $validated['file_title'] = $rawFileTitles[0] ?? $validated['file_title'];
            }

            $fileIndexing = null;
            $persistableData = Arr::only($validated, FileIndexing::columnWhitelist());
            if ($tempCandidate !== null) {
                $persistableData['file_number'] = null;
            }

            // Check if the file number exists in the corresponding_fileno table
            $correspondingMatch = DB::connection('sqlsrv')
                ->table('corresponding_fileno')
                ->whereRaw('UPPER(LTRIM(RTRIM(fileno))) = UPPER(?)', [trim((string) ($validated['file_number'] ?? ''))])
                ->value('fileno');
            if ($correspondingMatch !== null) {
                $persistableData['is_corresponding_file'] = 1;
                $persistableData['corresponding_fileno'] = $correspondingMatch;
            } else {
                $persistableData['is_corresponding_file'] = 0;
                $persistableData['corresponding_fileno'] = null;
            }

            // Initialize before transaction
            $kangisPlaceholderResult = ['resolved' => null, 'siblings' => []];

            // Flatten any array values in persistableData (for main record)
            // SQL Server columns expect strings, so we pick the first value for the summary record.
            foreach ($persistableData as $key => $value) {
                if (is_array($value) && !in_array($key, ['current_holder', 'original_holder'])) {
                    $persistableData[$key] = $value[0] ?? null;
                }
            }
            $persistableData = $this->normalizeBillColumns($persistableData);

            // Also flatten the same fields in the main $validated array because it's used 
            // in subsequent operations like syncFileNumberTracking which use query builder.
            foreach ($validated as $key => $value) {
                if (is_array($value) && !in_array($key, ['current_holder', 'original_holder', 'related_fileno'])) {
                    $validated[$key] = $value[0] ?? null;
                }
            }

            DB::connection('sqlsrv')->transaction(function () use (&$fileIndexing, &$grouping, &$kangisPlaceholderResult, $isKangisVariant, $kangisGroupingCloned, $groupingTable, $kangisResolvedFileNo, $kangisBaseFileNo, $persistableData, $groupingIdValue, $groupingSyncPayload, $sourceFileId, $fileNumberForUpdate, $trackingId, $resolvedTestControl, $validated, $indexingType, $rawFileTitles, $relatedFileNos, $relatedDetails, $request, $resolvedDistrictInput, $existingTempRecord) {
                // Create Main Record or Update Existing Temporary Record
                if ($existingTempRecord) {
                    $fileIndexing = $existingTempRecord;
                    $persistableData['file_number'] = $validated['file_number'];
                    $persistableData['has_temp_file'] = 1;
                    $persistableData['temp_file_no'] = $existingTempRecord->temp_file_no;
                    $fileIndexing->update($persistableData);
                } else {
                    $fileIndexing = FileIndexing::create($persistableData);
                }

                // Resolve Kangis FileNo Placeholder (collision/disambiguation)
                $kangisPlaceholderResult = ['resolved' => null, 'siblings' => []];
                $rawKangisPlaceholder = trim((string) ($validated['kangis_fileno_placeholder'] ?? ''));
                if ($rawKangisPlaceholder !== '') {
                    try {
                        $kangisService = app(KangisFileNoPlaceholderService::class);

                        if ($kangisResolvedFileNo !== null) {
                            // Already settled before the insert (and any incumbent already
                            // renamed to _1) — resolveAndSave would re-derive a suffix from
                            // the row's own suffixed number and get it wrong.
                            $kangisService->finalizeResolved(
                                $rawKangisPlaceholder,
                                $fileIndexing->id,
                                $kangisBaseFileNo,
                                $kangisResolvedFileNo
                            );
                            $kangisPlaceholderResult = ['resolved' => $kangisResolvedFileNo, 'siblings' => []];
                        } else {
                            $kangisPlaceholderResult = $kangisService->resolveAndSave($rawKangisPlaceholder, $fileIndexing->id);
                        }
                    } catch (\Throwable $e) {
                        Log::warning('KangisFileNoPlaceholderService failed', [
                            'file_id' => $fileIndexing->id,
                            'input' => $rawKangisPlaceholder,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // If resolveAndSave assigned a suffixed file number (e.g. KNML 12_2),
                // update the in-memory record so all downstream code uses the correct value.
                // The service already updated file_indexings.file_number in the DB and
                // renamed related rows for the bare sibling; here we just sync in-memory.
                $resolvedKangisFileNumber = $kangisPlaceholderResult['resolved'] ?? null;
                if ($resolvedKangisFileNumber && $resolvedKangisFileNumber !== $fileIndexing->file_number) {
                    $fileIndexing->file_number = $resolvedKangisFileNumber;
                }

                // Flag the fileNumber table record when a temp suffix was used
                if (!empty($validated['has_temp_file']) && !empty($validated['temp_file_no']) && !empty($fileIndexing->file_number)) {
                    try {
                        DB::connection('sqlsrv')->table('fileNumber')
                            ->where(function ($query) use ($fileIndexing) {
                                $query->where('mlsfNo', $fileIndexing->file_number)
                                    ->orWhere('st_file_no', $fileIndexing->file_number)
                                    ->orWhere('kangisFileNo', $fileIndexing->file_number)
                                    ->orWhere('NewKANGISFileNo', $fileIndexing->file_number);
                            })
                            ->update([
                                'has_temp_file' => 1,
                                'temp_file_no' => $validated['temp_file_no'],
                            ]);
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('Could not flag fileNumber with temp_file_no', [
                            'file_number' => $fileIndexing->file_number,
                            'temp_file_no' => $validated['temp_file_no'],
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // For KANGIS variants where the grouping was cloned, the clone already
                // has indexing_mapping = 1 — no further sync needed.
                // For KANGIS variants without a clone (first-time index, no collision),
                // update the original kangis_grouping row directly inside this transaction.
                // For non-KANGIS records, run the normal grouping sync.
                if ($isKangisVariant && !$kangisGroupingCloned) {
                    if ($groupingTable === 'kangis_grouping') {
                        DB::connection('sqlsrv')
                            ->table('kangis_grouping')
                            ->where('id', $groupingIdValue)
                            ->update([
                                'indexing_mapping' => 1,
                                'indexing_kangis_fileno' => $fileIndexing->file_number,
                                'kangis_fileno_resolved' => $fileIndexing->file_number,
                                'kangis_fileno_placeholder' => $request->input('kangis_fileno_placeholder'),
                                'is_indexed' => 1,
                                'tracking_id' => $trackingId,
                            ]);
                    }
                } elseif (!$isKangisVariant) {
                    $lockedGrouping = Grouping::on('sqlsrv')
                        ->where('id', $groupingIdValue)
                        ->lockForUpdate()
                        ->first();

                    if ($lockedGrouping) {
                        $this->applyGroupingIndexingSync($lockedGrouping, $groupingSyncPayload);
                        $grouping = $lockedGrouping;
                    }
                }

                if (!empty($fileIndexing->file_number)) {
                    $this->syncFileNumberTracking(
                        $sourceFileId,
                        $fileIndexing->file_number,
                        $trackingId,
                        $resolvedTestControl,
                        [
                            'file_title' => $validated['file_title'] ?? null,
                            'location' => $validated['location'] ?? null,
                            'lga' => $validated['lga'] ?? null,
                            'plot_no' => $validated['plot_number'] ?? null,
                            'tp_no' => $validated['tp_no'] ?? null,
                            'has_temp_file' => $validated['has_temp_file'] ?? false,
                            'temp_file_no' => $validated['temp_file_no'] ?? null,
                            'is_kangis' => $isKangisVariant,
                            'kangis_fileno_placeholder' => $validated['kangis_fileno_placeholder'] ?? null,
                            'kangis_fileno_resolved' => $fileIndexing->file_number,
                            'created_by' => Auth::id(),
                            'updated_by' => Auth::id(),
                            'source' => 'indexing',
                            'type' => 'indexing',
                        ]
                    );
                }

                // Block Indexing Specifics: Create Links for Main Files and Related Files
                if ($indexingType === 'Block') {
                    // 1. Create Links for Main Files (mfile = 1)
                    foreach ($rawFileTitles as $index => $title) {
                        if (empty($title))
                            continue;

                        $linkData = [
                            'file_indexing_id' => $fileIndexing->id,
                            'file_number' => $validated['file_number'],
                            'file_title' => $title,
                            'mfile' => 1,
                            'indexing_type' => 'Block',
                            'created_by' => Auth::id(),
                            'updated_by' => Auth::id(),

                            'location' => is_array($request->input('location')) ? ($request->input('location')[$index] ?? null) : $request->input('location'),

                            // User requested to remove Customer and Entity from Block Logic
                            // 'entity_type' => is_array($request->input('entity_type')) ? ($request->input('entity_type')[$index] ?? null) : $request->input('entity_type'),
                            // 'entity_name' => is_array($request->input('entity_name')) ? ($request->input('entity_name')[$index] ?? null) : $request->input('entity_name'),
                            // 'entity_physical_address' => is_array($request->input('entity_physical_address')) ? ($request->input('entity_physical_address')[$index] ?? null) : $request->input('entity_physical_address'),
                            // 'customer_type' => is_array($request->input('customer_type')) ? ($request->input('customer_type')[$index] ?? null) : $request->input('customer_type'),
                            // 'customer_name' => is_array($request->input('customer_name')) ? ($request->input('customer_name')[$index] ?? null) : $request->input('customer_name'),
                            // 'customer_account_no' => is_array($request->input('customer_account_no')) ? ($request->input('customer_account_no')[$index] ?? null) : $request->input('customer_account_no'),
                            // 'customer_code' => is_array($request->input('customer_code')) ? ($request->input('customer_code')[$index] ?? null) : $request->input('customer_code'),
                            // 'property_address' => is_array($request->input('property_address')) ? ($request->input('property_address')[$index] ?? null) : $request->input('property_address'),
                            // 'customer_status' => is_array($request->input('customer_status')) ? ($request->input('customer_status')[$index] ?? 'Active') : ($request->input('customer_status') ?? 'Active'),

                            'land_use_type' => is_array($request->input('land_use_type')) ? ($request->input('land_use_type')[$index] ?? null) : $request->input('land_use_type'),
                            'district' => is_array($resolvedDistrictInput) ? ($resolvedDistrictInput[$index] ?? null) : $resolvedDistrictInput,
                            'lga' => is_array($request->input('lga')) ? ($request->input('lga')[$index] ?? null) : $request->input('lga'),
                            'plot_size' => is_array($request->input('plot_size')) ? ($request->input('plot_size')[$index] ?? null) : $request->input('plot_size'),
                            'plot_number' => is_array($request->input('plot_number')) ? ($request->input('plot_number')[$index] ?? null) : $request->input('plot_number'),
                            'tp_no' => is_array($request->input('tp_no')) ? ($request->input('tp_no')[$index] ?? null) : $request->input('tp_no'),
                            'lpkn_no' => is_array($request->input('lpkn_no')) ? ($request->input('lpkn_no')[$index] ?? null) : $request->input('lpkn_no'),


                            'dob' => is_array($request->input('dob')) ? ($request->input('dob')[$index] ?? null) : $request->input('dob'),
                            'nin' => is_array($request->input('nin')) ? ($request->input('nin')[$index] ?? null) : $request->input('nin'),
                            'tin' => is_array($request->input('tin')) ? ($request->input('tin')[$index] ?? null) : $request->input('tin'),
                            'rc_no' => is_array($request->input('rc_no')) ? ($request->input('rc_no')[$index] ?? null) : $request->input('rc_no'),
                            'phone' => is_array($request->input('phone')) ? ($request->input('phone')[$index] ?? null) : $request->input('phone'),
                            'email' => is_array($request->input('email')) ? ($request->input('email')[$index] ?? null) : $request->input('email'),
                            'residence_address' => is_array($request->input('residence_address')) ? ($request->input('residence_address')[$index] ?? null) : $request->input('residence_address'),
                            'country_code' => is_array($request->input('country_code')) ? ($request->input('country_code')[$index] ?? null) : $request->input('country_code'),
                        ];
                        FileIndexingLink::create($linkData);
                    }

                    // 2. Create Links for Related Files (mfile = 0)
                    foreach ($relatedDetails as $detail) {
                        $rFileNo = $detail['file_number'] ?? null;
                        if (empty($rFileNo) && !empty($detail['holder'])) {
                            // Use holder as file number for block indexing link if file number wasn't provided directly
                            $rFileNo = $detail['holder'];
                        }
                        if (empty($rFileNo))
                            continue;

                        $linkData = [
                            'file_indexing_id' => $fileIndexing->id,
                            'file_number' => $rFileNo,
                            'mfile' => 0,
                            'indexing_type' => 'Block',
                            'created_by' => Auth::id(),
                            'updated_by' => Auth::id(),

                            'file_title' => $detail['file_title'] ?? null,
                            'location' => $detail['location'] ?? null,
                            'plot_number' => $detail['plot_number'] ?? null,
                            'plot_size' => $detail['plot_size'] ?? null, // Added plot_size
                            'tp_no' => $detail['tp_no'] ?? null,
                            'lpkn_no' => $detail['lpkn_no'] ?? null,

                            'district' => $detail['district'] ?? ($validated['district'] ?? null),
                            'lga' => $detail['lga'] ?? ($validated['lga'] ?? null),
                            'land_use_type' => $detail['land_use_type'] ?? ($validated['land_use_type'] ?? null),

                            'dob' => $detail['dob'] ?? ($validated['dob'] ?? null),
                            'nin' => $detail['nin'] ?? ($validated['nin'] ?? null),
                            'tin' => $detail['tin'] ?? ($validated['tin'] ?? null),
                            'rc_no' => $detail['rc_no'] ?? ($validated['rc_no'] ?? null),
                            'phone' => $detail['phone'] ?? ($validated['phone'] ?? null),
                            'email' => $detail['email'] ?? ($validated['email'] ?? null),
                            'residence_address' => $detail['residence_address'] ?? ($validated['residence_address'] ?? null),
                            'country_code' => $detail['country_code'] ?? ($validated['country_code'] ?? null),
                        ];
                        FileIndexingLink::create($linkData);
                    }

                } else {
                    // Regular Logic (maintain backward compatibility but using new mfile field? or just related links?)
                    // Existing logic for Regular handles related files in syncRelatedFileLinks outside transaction or we can do it here?
                    // The original code called `syncRelatedFileLinks` AFTER transaction.
                    // But for Block we did it INSIDE.
                    // We should probably rely on `syncRelatedFileLinks` for Regular to ensure consistency if it does more than just creating links.
                    // Let's check `syncRelatedFileLinks`.
                }

            });

            if (!$fileIndexing) {
                throw new \RuntimeException('Failed to persist file indexing record.');
            }

            if ($validated['general_registry'] !== 'DCIV Registry') {
                $this->syncCofORecord($fileIndexing, $request, $resolvedTestControl, $requestHasCofo ?? false, $propIdForStore);
            }

            // Sync RoFO record if has_rofo is checked
            $requestHasRofo = filter_var($request->input('has_rofo', false), FILTER_VALIDATE_BOOLEAN);
            if ($requestHasRofo) {
                $this->syncRofoRecord($fileIndexing, $request, $resolvedTestControl, $propIdForStore);
            }

            // Sync Occupancy Permit record if has_occupancy_permit is checked
            $requestHasOccupancyPermit = filter_var($request->input('has_occupancy_permit', false), FILTER_VALIDATE_BOOLEAN);
            if ($requestHasOccupancyPermit) {
                $this->syncOccupancyPermitRecord($fileIndexing, $request, $resolvedTestControl, $propIdForStore);
            }

            // Exclude from entity/customer sync if Block Indexing OR DCIV Registry
            if ($indexingType !== 'Block' && $validated['general_registry'] !== 'DCIV Registry') {
                $this->syncEntityAndCustomer($fileIndexing, $request, $resolvedTestControl);
            }

            // Only sync related file links if NOT Block Indexing
            // Block Indexing handles link creation (mfile=1, mfile=0) inside the transaction
            // Running syncRelatedFileLinks here would delete those links!
            if ($indexingType !== 'Block') {
                $this->syncRelatedFileLinks($fileIndexing, $relatedFileNos, $relatedDetails);
            }

            // Surface DCIV relationships in the DCIV master link table — whether the
            // indexed file is itself a DCIV (its related files become links) or an
            // ordinary file whose related number points at a DCIV.
            $this->syncMasterDcivLinks($fileIndexing, $relatedFileNos, $relatedDetails);

            // Register the same numbers in related_file_number. file_indexings.related_fileno
            // is the row's own JSON list; the register is what Legal Search and the Related
            // File Numbers page read, and indexing never wrote to it before.
            app(\App\Services\RelatedFileNumberRegistrar::class)->sync(
                $fileIndexing->id ?? null,
                $fileIndexing->file_number ?? null,
                $fileIndexing->file_title ?? null,
                $fileIndexing->prop_id ?? null,
                $relatedFileNos,
                ['location' => $fileIndexing->location ?? null]
            );

            // KANGIS three-file linkage (guide-faithful "Option A"): give this file
            // its own prop_id and, when it is a child (e.g. a Land file quoting an
            // already-indexed Old KANGIS number), point it up at the Old KANGIS parent
            // via parent_prop_id. When THIS file is the Old KANGIS parent, its
            // already-indexed related Land file(s) are re-parented to it. Best-effort —
            // gaps are filled by `php artisan kangis:link-parent-propids`.
            $mainParentPropId = $this->kangisParentLinkService->linkOnIndex(
                (int) $fileIndexing->id,
                (string) $fileIndexing->file_number,
                $propIdForStore,
                $relatedFileNos
            );

            // "Has New KANGIS FileNo": create a standalone file_indexings row for the
            // KN-series number itself, copying descriptive fields from this form. The
            // file being indexed here is only linked to it via the Related File Number
            // link synced just above — it is NOT where the transactions get saved.
            // The New KANGIS record gets its OWN distinct prop_id and hangs off the
            // Old KANGIS parent (this file's prop_id, $propIdForStore).
            $kangisFileIndexing = null;
            if ($hasNewKangisFileno && $newKangisFileNoRaw !== '') {
                $kangisFileIndexing = $this->createStandaloneNewKangisRecord(
                    $newKangisFileNoRaw,
                    $persistableData,
                    $propIdForStore
                );
            }

            // Persist "Has Transaction" rows — against the standalone New KANGIS record
            // when one was created above, otherwise the main file being indexed. Each
            // transaction carries its file's OWN prop_id plus the Old KANGIS parent's
            // prop_id (parent_prop_id) so Legal Search's ancestor expansion resolves it.
            if ($kangisFileIndexing !== null) {
                // Transactions belong to the New KANGIS file: its own prop_id, parent = Old KANGIS.
                $txnPropId = $kangisFileIndexing->prop_id !== null ? (int) $kangisFileIndexing->prop_id : $propIdForStore;
                $txnParentPropId = $propIdForStore;
            } else {
                // Transactions belong to the main file: its own prop_id, parent = its resolved ancestor.
                $txnPropId = $propIdForStore;
                $txnParentPropId = $mainParentPropId;
            }
            $this->syncFileIndexingTransactions($request, $kangisFileIndexing ?? $fileIndexing, $txnPropId, $txnParentPropId);

            if ($propIdForStore !== null) {
                $fileNoForHistory = $fileIndexing->file_number;
                if (empty($fileNoForHistory) && !empty($fileIndexing->temp_file_no)) {
                    $fileNoForHistory = trim((string) preg_replace('/\(\s*T\s*\)\s*$/i', '', $fileIndexing->temp_file_no));
                }
                $this->propertyIdAllocationService->syncPropIdToFileHistory(
                    $fileNoForHistory ?: '',
                    $propIdForStore,
                    $resolvedTestControl
                );
            }

            $this->commissioningMirrorService->mirror($fileIndexing, $validated, $relatedFileNos);

            // Persist Bill Balance + Grant Rent (billing + file_indexing_bills tables).
            $this->fileIndexingBillService->syncForFile($fileIndexing, $request);

            Log::info('FileIndexing::store - saved record', [
                'id' => $fileIndexing->id,
                'tp_no_saved' => $fileIndexing->tp_no,
                'location_saved' => $fileIndexing->location,
                'plot_number_saved' => $fileIndexing->plot_number,
                'tracking_id' => $fileIndexing->tracking_id,
            ]);

            try {
                $fileIndexing->refresh();
            } catch (\Throwable $e) {
                Log::warning('FileIndexing::store - refresh failed: ' . $e->getMessage());
            }

            // Handle batch and shelf assignment with new system
            if (isset($validated['shelf_label_id']) && !empty($validated['shelf_label_id'])) {
                try {
                    $batchId = $request->get('batch_id'); // From the new batch system

                    // Check if using new batch management system
                    if ($batchId) {
                        $batchRecord = \App\Models\FileindexingBatch::find($batchId);
                        if ($batchRecord) {
                            // Update file indexing with batch reference
                            $fileIndexing->update(['batch_id' => $batchId]);

                            // Mark shelf as used and update batch statistics
                            $batchRecord->markShelfUsed($validated['shelf_label_id']);

                            Log::info('FileIndexing::store - used new batch system', [
                                'batch_id' => $batchId,
                                'shelf_id' => $validated['shelf_label_id'],
                                'batch_used_shelves' => $batchRecord->fresh()->used_shelves
                            ]);
                        }
                    } else {
                        // Fallback to legacy system
                        $tableExists = DB::connection('sqlsrv')->select("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'Rack_Shelf_Labels'");
                        if (!empty($tableExists)) {
                            DB::connection('sqlsrv')->table('Rack_Shelf_Labels')
                                ->where('id', $validated['shelf_label_id'])
                                ->update(['is_used' => 1]);
                        }

                        Log::info('FileIndexing::store - used legacy shelf system', [
                            'shelf_id' => $validated['shelf_label_id']
                        ]);
                    }
                } catch (\Exception $e) {
                    // Log the error but don't fail the file indexing creation
                    Log::error('Failed to mark shelf/batch as used: ' . $e->getMessage(), [
                        'shelf_label_id' => $validated['shelf_label_id'],
                        'batch_id' => $request->get('batch_id'),
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Opening tracking line: config/file_ranges.php says which registry and
            // zone (Digital Archive / Pool Office) the file number belongs to, so the
            // file gets a real file_tracker row recording where it now physically is
            // instead of only being placed at search time. See FileRangeTrackingService.
            $rangeTracking = $this->fileRangeTrackingService->openForIndexing($fileIndexing);

            // Scan folder: EDMS/SCAN_UPLOAD/{Registry}/{FILE NUMBER}. Created now so
            // it is waiting before anyone scans, rather than appearing only when the
            // first page is uploaded. Best-effort — never fails the save.
            $scanFolder = $this->edmsScanUploadFolderService->ensureForIndexing($fileIndexing);

            // "Where did this record go?" — the fan-out across registry, parties,
            // transaction and commissioning tables is invisible from the form, so
            // the response carries a count of what now exists for this file and the
            // client shows it as a confirmation card. Best-effort: never fails the save.
            $storageSummary = $this->indexingStorageSummaryService->summarize($fileIndexing, [
                'is_update' => false,
                'parent_prop_id' => $mainParentPropId,
                'kangis_record' => $kangisFileIndexing,
                'range_tracking' => $rangeTracking,
                'scan_folder' => $scanFolder,
            ]);

            // Version 1 of the file's audit trail. Append-only and best-effort —
            // the record is already committed, so a failed snapshot returns null
            // and the response simply carries no 'snapshot' key.
            $snapshot = $this->fileSnapshotService->capture($fileIndexing, FileSnapshot::EVENT_INDEXED, [
                'source' => 'file_indexing.store',
                'scan_folder' => $scanFolder,
                'storage_summary' => $storageSummary,
                'parent_prop_id' => $mainParentPropId,
            ]);

            // The standalone New KANGIS record is a file in its own right — it gets
            // its own v1 rather than being folded into the main file's snapshot.
            if ($kangisFileIndexing !== null) {
                $this->fileSnapshotService->capture($kangisFileIndexing, FileSnapshot::EVENT_INDEXED, [
                    'source' => 'file_indexing.store',
                    'event_label' => 'New KANGIS file created and indexed',
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'File indexing created successfully!',
                'data' => $fileIndexing,
                'siblings_updated' => $kangisPlaceholderResult['siblings'] ?? [],
                'kangis_resolved' => $kangisPlaceholderResult['resolved'] ?? null,
                'storage_summary' => $storageSummary,
                'snapshot' => $snapshot ? $snapshot->toCardPayload() : null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    protected function applyGroupingIndexingSync(Grouping $grouping, array $payload): void
    {
        $updates = [];

        if (array_key_exists('tracking_id', $payload) && $payload['tracking_id'] !== null) {
            $updates['tracking_id'] = $payload['tracking_id'];
        }

        if ($this->groupingColumnExists('indexing_mapping')) {
            $updates['indexing_mapping'] = $payload['indexing_mapping'] ?? 1;
        }

        if ($this->groupingColumnExists('indexing_mls_fileno')) {
            $mlsValue = $payload['indexing_mls_fileno'] ?? ($payload['file_number'] ?? null);
            if ($mlsValue !== null) {
                $updates['indexing_mls_fileno'] = $mlsValue;
            }
        }

        if ($this->groupingColumnExists('mdc_batch_no') && array_key_exists('mdc_batch_no', $payload)) {
            $updates['mdc_batch_no'] = $payload['mdc_batch_no'];
        }

        if ($this->groupingColumnExists('test_control') && array_key_exists('test_control', $payload)) {
            $updates['test_control'] = $payload['test_control'];
        }

        if (empty($updates)) {
            return;
        }

        $grouping->fill($updates);
        $grouping->save();

        Log::info('FileIndexing::store - grouping sync complete', [
            'grouping_id' => $grouping->id,
            'updates' => $updates,
        ]);
    }

    protected function groupingColumnExists(string $column): bool
    {
        static $cache = [];

        if (array_key_exists($column, $cache)) {
            return $cache[$column];
        }

        return $cache[$column] = Schema::connection('sqlsrv')->hasColumn('grouping', $column);
    }

    protected function syncFileNumberTracking(?int $sourceFileId, ?string $fileNumber, string $trackingId, ?string $testControl = null, array $extraAttributes = []): void
    {
        $fileNumber = (string) $fileNumber;
        if (empty($trackingId)) {
            return;
        }

        try {
            $isKangis = !empty($extraAttributes['is_kangis']);
            $kangisPlaceholder = isset($extraAttributes['kangis_fileno_placeholder'])
                ? trim((string) $extraAttributes['kangis_fileno_placeholder'])
                : '';
            $kangisResolved = isset($extraAttributes['kangis_fileno_resolved'])
                ? trim((string) $extraAttributes['kangis_fileno_resolved'])
                : '';

            $fileTitle = $extraAttributes['file_title'] ?? null;
            if (is_string($fileTitle)) {
                $fileTitle = trim($fileTitle);
                if ($fileTitle === '') {
                    $fileTitle = null;
                }
            } else {
                $fileTitle = null;
            }

            $resolvedFileTitle = $fileTitle ?? $fileNumber;

            $updateData = [
                'tracking_id' => $trackingId,
                'FileName' => $resolvedFileTitle,
                'updated_at' => Carbon::now(),
            ];

            if ($isKangis) {
                $resolvedForKangis = $kangisResolved !== '' ? $kangisResolved : $fileNumber;
                $updateData['mlsfNo'] = null;
                $updateData['kangisFileNo'] = $resolvedForKangis;
                $updateData['NewKANGISFileNo'] = $resolvedForKangis;
                $updateData['kangis_fileno_resolved'] = $resolvedForKangis;
                if ($kangisPlaceholder !== '') {
                    $updateData['kangis_fileno_placeholder'] = $kangisPlaceholder;
                }
            } else {
                $updateData['mlsfNo'] = $fileNumber;
            }

            if (array_key_exists('location', $extraAttributes) && $extraAttributes['location'] !== null) {
                $updateData['location'] = $extraAttributes['location'];
            }

            if (array_key_exists('lga', $extraAttributes) && $extraAttributes['lga'] !== null) {
                $updateData['lga'] = $extraAttributes['lga'];
            }

            if (array_key_exists('plot_no', $extraAttributes) && $extraAttributes['plot_no'] !== null) {
                $updateData['plot_no'] = $extraAttributes['plot_no'];
            }

            if (array_key_exists('tp_no', $extraAttributes) && $extraAttributes['tp_no'] !== null) {
                $updateData['tp_no'] = $extraAttributes['tp_no'];
            }

            if (array_key_exists('has_temp_file', $extraAttributes)) {
                $updateData['has_temp_file'] = !empty($extraAttributes['has_temp_file']) ? 1 : 0;
            }

            if (array_key_exists('temp_file_no', $extraAttributes)) {
                $tempFileNo = is_string($extraAttributes['temp_file_no']) ? trim($extraAttributes['temp_file_no']) : $extraAttributes['temp_file_no'];
                $updateData['temp_file_no'] = $tempFileNo !== '' ? $tempFileNo : null;
            }

            if ($testControl !== null) {
                $updateData['test_control'] = $testControl;
            }

            Log::info('FileIndexing::syncFileNumberTracking - debug', [
                'file_number' => $fileNumber,
                'source_file_id' => $sourceFileId,
                'has_temp_file_in_extra' => $extraAttributes['has_temp_file'] ?? 'NOT SET',
                'temp_file_no_in_extra' => $extraAttributes['temp_file_no'] ?? 'NOT SET',
                'has_temp_in_updateData' => $updateData['has_temp_file'] ?? 'NOT IN UPDATE',
                'temp_no_in_updateData' => $updateData['temp_file_no'] ?? 'NOT IN UPDATE',
            ]);

            $variants = array_values(array_unique($this->buildFileNumberVariants($fileNumber)));
            $tempFileNoAttr = $extraAttributes['temp_file_no'] ?? null;
            $tempVariants = [];
            if ($tempFileNoAttr) {
                $tempVariants[] = $tempFileNoAttr;
                $tempVariants[] = str_replace(' ', '', $tempFileNoAttr);
                $tempVariants = array_values(array_unique(array_filter($tempVariants)));
            }

            $hasFilenoColumn = Schema::connection('sqlsrv')->hasColumn('fileNumber', 'fileno');

            $query = DB::connection('sqlsrv')->table('fileNumber');

            if ($sourceFileId) {
                $query->where('id', $sourceFileId);
            } else {
                if (empty($variants) && empty($tempVariants)) {
                    Log::warning('FileIndexing::store - no variants generated for fileNumber sync', [
                        'file_number' => $fileNumber,
                    ]);
                    return;
                }

                Log::info('FileIndexing::syncFileNumberTracking - variants', ['variants' => $variants, 'temp_variants' => $tempVariants]);

                $query->where(function ($builder) use ($variants, $tempVariants, $hasFilenoColumn) {
                    $applied = false;
                    if (!empty($variants)) {
                        $builder->whereIn('st_file_no', $variants)
                            ->orWhereIn('mlsfNo', $variants)
                            ->orWhereIn('kangisFileNo', $variants)
                            ->orWhereIn('NewKANGISFileNo', $variants);
                        $applied = true;
                    }

                    if (!empty($tempVariants)) {
                        if ($applied) {
                            $builder->orWhereIn('temp_file_no', $tempVariants);
                        } else {
                            $builder->whereIn('temp_file_no', $tempVariants);
                            $applied = true;
                        }
                    }

                    if ($hasFilenoColumn && !empty($variants)) {
                        $builder->orWhereIn('fileno', $variants);
                    }
                });
            }

            $affected = $query->update($updateData);

            // If source_file_id was stale/non-fileNumber id, retry by file number variants.
            if ($affected === 0 && $sourceFileId && (!empty($variants) || !empty($tempVariants))) {
                $affected = DB::connection('sqlsrv')->table('fileNumber')
                    ->where(function ($builder) use ($variants, $tempVariants, $hasFilenoColumn) {
                        $applied = false;
                        if (!empty($variants)) {
                            $builder->whereIn('st_file_no', $variants)
                                ->orWhereIn('mlsfNo', $variants)
                                ->orWhereIn('kangisFileNo', $variants)
                                ->orWhereIn('NewKANGISFileNo', $variants);
                            $applied = true;
                        }



                        if (!empty($tempVariants)) {
                            if ($applied) {
                                $builder->orWhereIn('temp_file_no', $tempVariants);
                            } else {
                                $builder->whereIn('temp_file_no', $tempVariants);
                                $applied = true;
                            }
                        }

                        if ($hasFilenoColumn && !empty($variants)) {
                            $builder->orWhereIn('fileno', $variants);
                        }
                    })
                    ->update($updateData);
            }

            // Guard against duplicate rows: if variants miss but tracking_id exists, update by tracking_id.
            if ($affected === 0 && !empty($trackingId)) {
                $affected = DB::connection('sqlsrv')->table('fileNumber')
                    ->whereRaw('UPPER(LTRIM(RTRIM(tracking_id))) = UPPER(?)', [trim((string) $trackingId)])
                    ->update($updateData);
            }

            if ($affected === 0) {
                $defaultName = $this->resolveCurrentUserName();
                $insertData = [
                    'mlsfNo' => $isKangis ? null : $fileNumber,
                    'kangisFileNo' => $isKangis ? ($kangisResolved !== '' ? $kangisResolved : $fileNumber) : null,
                    'NewKANGISFileNo' => $isKangis ? ($kangisResolved !== '' ? $kangisResolved : $fileNumber) : null,
                    'kangis_fileno_placeholder' => ($isKangis && $kangisPlaceholder !== '') ? $kangisPlaceholder : null,
                    'kangis_fileno_resolved' => $isKangis ? ($kangisResolved !== '' ? $kangisResolved : $fileNumber) : null,
                    'FileName' => $resolvedFileTitle,
                    'location' => $extraAttributes['location'] ?? null,
                    'lga' => $extraAttributes['lga'] ?? null,
                    'plot_no' => $extraAttributes['plot_no'] ?? null,
                    'tp_no' => $extraAttributes['tp_no'] ?? null,
                    'has_temp_file' => !empty($extraAttributes['has_temp_file']) ? 1 : 0,
                    'temp_file_no' => isset($extraAttributes['temp_file_no']) && trim((string) $extraAttributes['temp_file_no']) !== ''
                        ? trim((string) $extraAttributes['temp_file_no'])
                        : null,
                    'tracking_id' => $trackingId,
                    'test_control' => $testControl,
                    'type' => $extraAttributes['type'] ?? 'indexing',
                    'SOURCE' => $extraAttributes['source'] ?? 'indexing',
                    'created_by' => $extraAttributes['created_by'] ?? $defaultName,
                    'updated_by' => $extraAttributes['updated_by'] ?? $defaultName,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                DB::connection('sqlsrv')->table('fileNumber')->insert($insertData);

                Log::info('FileIndexing::store - fileNumber row inserted', [
                    'file_number' => $fileNumber,
                    'tracking_id' => $trackingId,
                    'insert_data' => $insertData,
                ]);
            } else {
                Log::info('FileIndexing::store - fileNumber tracking sync complete', [
                    'source_file_id' => $sourceFileId,
                    'file_number' => $fileNumber,
                    'tracking_id' => $trackingId,
                    'rows_updated' => $affected,
                ]);
            }
        } catch (\Throwable $exception) {
            Log::warning('FileIndexing::store - error syncing fileNumber tracking', [
                'file_number' => $fileNumber,
                'tracking_id' => $trackingId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function syncCofORecord(FileIndexing $fileIndexing, Request $request, ?string $testControl = null, bool $hasCofo = false, ?int $propId = null): void
    {
        if (!$hasCofo) {
            return;
        }

        $cofoPayload = $this->extractCofOInput($request);

        $payloadFields = array_filter($cofoPayload, static function ($value) {
            return !is_null($value) && $value !== '';
        });

        if (empty($payloadFields)) {
            return;
        }

        $matchColumns = [];
        if (!empty($cofoPayload['cofo_no'])) {
            $matchColumns['cofo_no'] = $cofoPayload['cofo_no'];
        } else {
            $matchColumns['mlsFNo'] = $fileIndexing->file_number;
        }

        if (empty($matchColumns)) {
            return;
        }

        $registrationNumber = $this->formatRegistrationNumber(
            $cofoPayload['serial_no'] ?? null,
            $cofoPayload['page_no'] ?? null,
            $cofoPayload['volume_no'] ?? null
        );

        $transactionDate = $cofoPayload['transaction_date'] ?? $cofoPayload['cofo_date'] ?? $cofoPayload['deeds_date'] ?? null;

        $recordPayload = [
            'mlsFNo' => $fileIndexing->file_number,
            'cofo_no' => $cofoPayload['cofo_no'] ?? null,
            'transaction_type' => $cofoPayload['instrument_type'] ?? null,
            'instrument_type' => $cofoPayload['instrument_type'] ?? null,
            'cofo_type' => $cofoPayload['cofo_type'] ?? null,
            'transaction_date' => $transactionDate,
            'transaction_time' => $cofoPayload['deeds_time'] ?? null,
            'cofo_date' => $cofoPayload['cofo_date'] ?? $transactionDate,
            'serialNo' => $cofoPayload['serial_no'] ?? null,
            'pageNo' => $cofoPayload['page_no'] ?? ($cofoPayload['serial_no'] ?? null),
            'volumeNo' => $cofoPayload['volume_no'] ?? null,
            'regNo' => $registrationNumber,
            'period' => $cofoPayload['period'] ?? null,
            'period_unit' => $cofoPayload['period_unit'] ?? null,
            'status' => $cofoPayload['status'] ?? 'Active',
            'Grantor' => $cofoPayload['first_party'] ?? null,
            'Grantee' => $cofoPayload['second_party'] ?? null,
            'land_use' => $cofoPayload['land_use'] ?? $fileIndexing->land_use_type,
            'property_description' => $fileIndexing->location ?? $fileIndexing->district,
            'location' => $fileIndexing->location ?? $fileIndexing->district,
            'plot_no' => $fileIndexing->plot_number,
            'lgsaOrCity' => $fileIndexing->lga,
            'updated_by' => Auth::id(),
            'updated_at' => now(),
        ];

        if ($testControl) {
            $recordPayload['test_control'] = $testControl;
        }
        if ($propId !== null) {
            $recordPayload['prop_id'] = $propId;
        }

        $table = DB::connection('sqlsrv')->table(self::COFO_TABLE);
        $existing = $table->where($matchColumns)->first();

        if ($existing) {
            $table->where('id', $existing->id)->update($recordPayload);
            return;
        }

        $recordPayload['created_at'] = now();
        $recordPayload['created_by'] = Auth::id();

        $table->insert(array_merge($matchColumns, $recordPayload));
    }

    protected function extractCofOInput(Request $request): array
    {
        return [
            'cofo_no' => $this->normalizeValue($request->input('cofo_no')),
            'instrument_type' => $this->normalizeValue($request->input('cofo_instrument_type')),
            'cofo_date' => $this->normalizeValue($request->input('cofo_date')),
            'transaction_date' => $this->normalizeValue($request->input('transaction_date')),
            'serial_no' => $this->normalizeValue($request->input('cofo_serial_no')),
            'page_no' => $this->normalizeValue($request->input('cofo_page_no')),
            'volume_no' => $this->normalizeValue($request->input('cofo_vol_no')),
            'deeds_time' => $this->normalizeValue($request->input('cofo_deeds_time')),
            'deeds_date' => $this->normalizeValue($request->input('cofo_deeds_date')),
            'first_party' => $this->normalizeValue($request->input('cofo_first_party')),
            'second_party' => $this->normalizeValue($request->input('cofo_second_party')),
            'land_use' => $this->normalizeValue($request->input('cofo_land_use')),
            'period' => $this->normalizeValue($request->input('cofo_period')),
            'period_unit' => $this->normalizeValue($request->input('cofo_period_unit')),
            'status' => $this->normalizeValue($request->input('cofo_status')),
            'cofo_type' => $this->normalizeValue($request->input('cofo_type')),
        ];
    }

    protected function formatRegistrationNumber(?string ...$parts): ?string
    {
        $filtered = array_values(array_filter(array_map(function ($value) {
            return $this->normalizeValue($value);
        }, $parts)));

        if (empty($filtered)) {
            return null;
        }

        return implode('/', $filtered);
    }

    protected function syncRofoRecord(FileIndexing $fileIndexing, Request $request, ?string $testControl = null, ?int $propId = null): void
    {
        $rofoPayload = [
            'instrument_type' => $this->normalizeValue($request->input('rofo_instrument_type')),
            'transaction_date' => $this->normalizeValue($request->input('rofo_date')),
            'rofo_number' => $this->normalizeValue($request->input('rofo_number')),
            'land_use' => $this->normalizeValue($request->input('rofo_land_use')),
            'party_1' => $this->normalizeValue($request->input('rofo_grantor')),
            'party_2' => $this->normalizeValue($request->input('rofo_grantee')),
            'status' => $this->normalizeValue($request->input('rofo_status')),
        ];

        $payloadFields = array_filter($rofoPayload, static function ($value) {
            return !is_null($value) && $value !== '';
        });

        if (empty($payloadFields)) {
            return;
        }

        $matchColumns = [];
        if (!empty($rofoPayload['rofo_number'])) {
            $matchColumns['rofo_number'] = $rofoPayload['rofo_number'];
        } else {
            $matchColumns['mlsFNo'] = $fileIndexing->file_number;
        }

        if (empty($matchColumns)) {
            return;
        }

        $recordPayload = [
            'mlsFNo' => $fileIndexing->file_number,
            'rofo_number' => $rofoPayload['rofo_number'] ?? null,
            'instrument_type' => $rofoPayload['instrument_type'] ?? null,
            'transaction_type' => $rofoPayload['instrument_type'] ?? null,
            'transaction_date' => $rofoPayload['transaction_date'] ?? null,
            'party_1' => $rofoPayload['party_1'] ?? null,
            'party_2' => $rofoPayload['party_2'] ?? null,
            'status' => $rofoPayload['status'] ?? 'Active',
            'land_use' => $rofoPayload['land_use'] ?? $fileIndexing->land_use_type,
            'property_description' => $fileIndexing->location ?? $fileIndexing->district,
            'location' => $fileIndexing->location ?? $fileIndexing->district,
            'plot_no' => $fileIndexing->plot_number,
            'lgsaOrCity' => $fileIndexing->lga,
            'updated_by' => Auth::id(),
            'created_by' => Auth::id(),
            'source' => 'indexing',
            'updated_at' => now(),
        ];

        if ($testControl) {
            $recordPayload['test_control'] = $testControl;
        }
        if ($propId !== null) {
            $recordPayload['prop_id'] = $propId;
        }

        $table = DB::connection('sqlsrv')->table('pra');
        $existing = $table->where($matchColumns)->first();

        if ($existing) {
            $table->where('id', $existing->id)->update($recordPayload);
            return;
        }

        $recordPayload['created_at'] = now();
        $recordPayload['created_by'] = Auth::id();

        $table->insert(array_merge($matchColumns, $recordPayload));

        // Update file indexing has_rofo flag
        $fileIndexing->update(['has_rofo' => true]);
    }

    protected function syncOccupancyPermitRecord(FileIndexing $fileIndexing, Request $request, ?string $testControl = null, ?int $propId = null): void
    {
        $permitPayload = [
            'instrument_type' => $this->normalizeValue($request->input('occupancy_permit_instrument_type')),
            'op_type' => $this->normalizeValue($request->input('occupancy_permit_op_type')),
            // Old / New. Asked for every Occupancy Permit; blank means the question was
            // never put, and the permit keeps the rules a New one has.
            // Stored in instrument_category, the one Category column every capture table
            // carries — see App\Services\InstrumentTypeCatalog.
            'instrument_category' => $this->normalizeValue($request->input('occupancy_permit_op_category')),
            'op_serial_number' => $this->normalizeValue($request->input('occupancy_permit_op_serial_number')),
            'transaction_date' => $this->normalizeValue($request->input('occupancy_permit_date')),
            'land_use' => $this->normalizeValue($request->input('occupancy_permit_land_use')),
            'party_1' => $this->normalizeValue($request->input('occupancy_permit_grantor')),
            'party_2' => $this->normalizeValue($request->input('occupancy_permit_grantee')),
            'status' => $this->normalizeValue($request->input('occupancy_permit_status')),
            'serialNo' => $this->normalizeValue($request->input('occupancy_permit_serial_no')),
            'pageNo' => $this->normalizeValue($request->input('occupancy_permit_page_no')),
            'volumeNo' => $this->normalizeValue($request->input('occupancy_permit_vol_no')),
            'deeds_time' => $this->normalizeValue($request->input('occupancy_permit_deeds_time')),
            'deeds_date' => $this->normalizeValue($request->input('occupancy_permit_deeds_date')),
        ];

        $payloadFields = array_filter($permitPayload, static function ($value) {
            return !is_null($value) && $value !== '';
        });

        if (empty($payloadFields)) {
            return;
        }

        // Occupancy Permit rows live in the same `pra` instruments table as RoFO,
        // distinguished by instrument_type. Match on the file number + instrument type
        // so a re-index updates the existing permit row rather than duplicating it.
        $instrumentType = $permitPayload['instrument_type'] ?? 'Occupancy Permit';
        $matchColumns = [
            'mlsFNo' => $fileIndexing->file_number,
            'instrument_type' => $instrumentType,
        ];

        $recordPayload = [
            'mlsFNo' => $fileIndexing->file_number,
            // Leave rofo_number null: populating it would let the RoFO matcher, which
            // looks up pra rows by rofo_number, collide with this Occupancy Permit row.
            'rofo_number' => null,
            'instrument_type' => $instrumentType,
            'transaction_type' => $instrumentType,
            'op_type' => $permitPayload['op_type'] ?? null,
            'instrument_category' => $permitPayload['instrument_category'] ?? null,
            'op_serial_number' => $permitPayload['op_serial_number'] ?? null,
            'transaction_date' => $permitPayload['transaction_date'] ?? null,
            'party_1' => $permitPayload['party_1'] ?? null,
            'party_2' => $permitPayload['party_2'] ?? null,
            'status' => $permitPayload['status'] ?? 'Active',
            'serialNo' => $permitPayload['serialNo'] ?? null,
            'pageNo' => $permitPayload['pageNo'] ?? null,
            'volumeNo' => $permitPayload['volumeNo'] ?? null,
            'deeds_time' => $permitPayload['deeds_time'] ?? null,
            'deeds_date' => $permitPayload['deeds_date'] ?? null,
            'cofo_type' => $permitPayload['cofo_type'] ?? null,
            'land_use' => $permitPayload['land_use'] ?? $fileIndexing->land_use_type,
            'property_description' => $fileIndexing->location ?? $fileIndexing->district,
            'location' => $fileIndexing->location ?? $fileIndexing->district,
            'plot_no' => $fileIndexing->plot_number,
            'lgsaOrCity' => $fileIndexing->lga,
            'updated_by' => Auth::id(),
            'source' => 'indexing',
            'updated_at' => now(),
        ];

        if ($testControl) {
            $recordPayload['test_control'] = $testControl;
        }
        if ($propId !== null) {
            $recordPayload['prop_id'] = $propId;
        }

        $table = DB::connection('sqlsrv')->table('pra');
        $existing = $table->where($matchColumns)->first();

        if ($existing) {
            $table->where('id', $existing->id)->update($recordPayload);
            $fileIndexing->update(['has_occupancy_permit' => true]);
            return;
        }

        $recordPayload['created_at'] = now();
        $recordPayload['created_by'] = Auth::id();

        $table->insert(array_merge($matchColumns, $recordPayload));

        // Update file indexing has_occupancy_permit flag
        $fileIndexing->update(['has_occupancy_permit' => true]);
    }

    /**
     * "Has New KANGIS FileNo": create a standalone file_indexings row for the selected
     * KN-series number. Descriptive fields (title, land use, holder, location, district/lga)
     * are copied from the form that referenced it; tracking/batch/grouping identifiers are
     * NOT copied since this KN file hasn't gone through its own grouping/tracking workflow —
     * it can be picked up and completed later via its own edit page. The caller (store())
     * has already confirmed this file number isn't indexed yet.
     */
    /**
     * Create the standalone New KANGIS (KN-series) record.
     *
     * Under the guide-faithful three-file model the New KANGIS file is an
     * INDEPENDENT property with its OWN prop_id, pointing up at the Old KANGIS
     * parent via parent_prop_id. $parentPropId is the Old KANGIS file's own
     * prop_id (i.e. the prop_id allocated for the file currently being indexed,
     * which is the Old KANGIS in the documented workflow).
     */
    protected function createStandaloneNewKangisRecord(string $newKangisFileNo, array $mainPersistableData, ?int $parentPropId = null): FileIndexing
    {
        $currentUserName = $this->resolveCurrentUserName();

        $copyKeys = [
            'file_title', 'land_use_type', 'plot_number', 'plot_size',
            'latitude', 'longitude', 'district', 'lga', 'street_name',
            'current_holder', 'original_holder', 'physical_registry',
            'tp_no', 'lpkn_no', 'location',
        ];

        $payload = Arr::only($mainPersistableData, $copyKeys);
        $payload['file_number'] = $newKangisFileNo;
        $payload['general_registry'] = 'KANGIS Registry';
        $payload['indexing_type'] = 'Regular';
        $payload['workflow_status'] = 'indexed';
        $payload['created_by'] = $currentUserName;
        $payload['updated_by'] = $currentUserName;

        // Allocate a DISTINCT prop_id for the New KANGIS file (skip_lookup so it
        // never collapses onto the parent's/sibling's prop_id via cross-identifier
        // matching), then hang it off the Old KANGIS parent.
        $ownPropId = null;
        try {
            $ownPropId = $this->propertyIdAllocationService->allocateOrRetrievePropId(
                $newKangisFileNo,
                null,
                null,
                $newKangisFileNo,
                ['skip_lookup' => true]
            );
        } catch (\Throwable $e) {
            Log::warning('createStandaloneNewKangisRecord: distinct prop_id allocation failed', [
                'file_number' => $newKangisFileNo,
                'error' => $e->getMessage(),
            ]);
        }

        if ($ownPropId !== null) {
            $payload['prop_id'] = $ownPropId;
        }
        if ($parentPropId !== null) {
            $payload['parent_prop_id'] = (string) $parentPropId;
        }

        $kangisFileIndexing = FileIndexing::on('sqlsrv')->create($payload);

        // Mirror the parent link onto the fileNumber row too.
        if ($parentPropId !== null) {
            $this->kangisParentLinkService->assignFileLevelParent($newKangisFileNo, $parentPropId);
        }

        if ($ownPropId !== null) {
            try {
                $this->propertyIdAllocationService->syncPropIdToFileHistory($newKangisFileNo, $ownPropId, null);
            } catch (\Throwable $e) {
                Log::warning('createStandaloneNewKangisRecord: prop_id sync to file_history failed', [
                    'file_number' => $newKangisFileNo,
                    'prop_id' => $ownPropId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $kangisFileIndexing;
    }

    /**
     * Edit-page counterpart to createStandaloneNewKangisRecord(): index the KN-series file
     * on the fly during update(). If a file_indexings row already exists for this KN number
     * it is UPDATED in place (descriptive fields refreshed from the source form, but empty
     * source values never overwrite existing data); otherwise a new standalone KANGIS
     * Registry record is created. Idempotent — re-saving the same edit never adds a duplicate.
     */
    protected function upsertStandaloneNewKangisRecord(string $newKangisFileNo, array $sourceData): void
    {
        $newKangisFileNo = trim($newKangisFileNo);
        if ($newKangisFileNo === '') {
            return;
        }

        $currentUserName = $this->resolveCurrentUserName();

        $copyKeys = [
            'file_title', 'land_use_type', 'plot_number', 'plot_size',
            'latitude', 'longitude', 'district', 'lga', 'street_name',
            'current_holder', 'original_holder', 'physical_registry',
            'tp_no', 'lpkn_no', 'location',
        ];

        $payload = Arr::only($sourceData, $copyKeys);
        foreach (['current_holder', 'original_holder'] as $holderKey) {
            if (isset($payload[$holderKey]) && is_array($payload[$holderKey])) {
                $payload[$holderKey] = json_encode(array_values(array_filter($payload[$holderKey])));
            }
        }

        $existing = DB::connection('sqlsrv')->table('file_indexings')
            ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = UPPER(?)', [$newKangisFileNo])
            ->first();

        if ($existing) {
            // Update in place — do not create a second row. Only overwrite with
            // non-empty source values so existing good data on the KN record is kept.
            $update = array_filter($payload, fn($v) => $v !== null && $v !== '' && $v !== []);
            if (empty($existing->general_registry)) {
                $update['general_registry'] = 'KANGIS Registry';
            }
            $update['updated_by'] = $currentUserName;
            $update['updated_at'] = now();

            DB::connection('sqlsrv')->table('file_indexings')
                ->where('id', $existing->id)
                ->update($update);

            return;
        }

        // No existing row — create the standalone KANGIS Registry record.
        $payload['file_number'] = $newKangisFileNo;
        $payload['general_registry'] = 'KANGIS Registry';
        $payload['indexing_type'] = 'Regular';
        $payload['workflow_status'] = 'indexed';
        $payload['created_by'] = $currentUserName;
        $payload['updated_by'] = $currentUserName;

        FileIndexing::on('sqlsrv')->create($payload);
    }

    /**
     * Edit page: the "Has Transaction" rows already saved against a New KANGIS (KN-series)
     * file, from both destinations syncFileIndexingTransactions() writes to — `pra` and
     * `CofO_staging`. Read-only display only; returns [] when the KN number is blank or the
     * lookup fails (the edit page must still render).
     */
    protected function fetchNewKangisTransactionsForEdit(string $newKangisFileNo): array
    {
        $newKangisFileNo = trim($newKangisFileNo);
        if ($newKangisFileNo === '') {
            return [];
        }

        $match = function ($query) use ($newKangisFileNo) {
            $query->whereRaw('UPPER(LTRIM(RTRIM(mlsFNo))) = UPPER(?)', [$newKangisFileNo])
                ->orWhereRaw('UPPER(LTRIM(RTRIM(NewKANGISFileno))) = UPPER(?)', [$newKangisFileNo]);
        };

        $rows = [];

        foreach (['pra', self::COFO_TABLE] as $table) {
            try {
                $records = DB::connection('sqlsrv')->table($table)
                    ->where($match)
                    ->orderBy('id')
                    ->get();
            } catch (\Throwable $e) {
                Log::warning('fetchNewKangisTransactionsForEdit: lookup failed', [
                    'table' => $table,
                    'file_number' => $newKangisFileNo,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            foreach ($records as $record) {
                $regNo = $this->normalizeValue($record->regNo ?? null)
                    ?? $this->formatRegistrationNumber(
                        $this->normalizeValue($record->serialNo ?? null),
                        $this->normalizeValue($record->pageNo ?? null),
                        $this->normalizeValue($record->volumeNo ?? null)
                    );

                $txnDate = $this->normalizeValue($record->transaction_date ?? null)
                    ?? $this->normalizeValue($record->cofo_date ?? null);

                $rows[] = [
                    'instrument_type'  => $this->normalizeValue($record->instrument_type ?? null)
                        ?? $this->normalizeValue($record->transaction_type ?? null),
                    'transaction_date' => $txnDate ? substr((string) $txnDate, 0, 10) : null,
                    'reg_no'           => $regNo,
                    'grantor'          => $this->normalizeValue($record->Grantor ?? null)
                        ?? $this->normalizeValue($record->party_1 ?? null),
                    'grantee'          => $this->normalizeValue($record->Grantee ?? null)
                        ?? $this->normalizeValue($record->party_2 ?? null),
                    'source_table'     => $table,
                ];
            }
        }

        return $rows;
    }

    /**
     * Persist "Has Transaction" rows captured during New KANGIS file indexing into the pra
     * table. Each row becomes one pra row; the target file's file number is stored as mlsFNo,
     * and every row shares $propId, so they all resolve to one property.
     */
    protected function syncFileIndexingTransactions(Request $request, FileIndexing $fileIndexing, ?int $propId = null, ?int $parentPropId = null): void
    {
        if (!$request->boolean('has_new_kangis_transaction')) {
            return;
        }

        $transactions = $request->input('transactions');
        if (!is_array($transactions) || empty($transactions)) {
            return;
        }

        $newKangis = $this->normalizeValue($request->input('new_kangis_file_no'))
            ?? $this->normalizeValue($fileIndexing->new_kangis_file_no ?? null);
        $kangis = $this->normalizeValue($request->input('kangis_fileno_placeholder'));

        $praTable  = DB::connection('sqlsrv')->table('pra');
        $cofoTable = DB::connection('sqlsrv')->table(self::COFO_TABLE);
        $saved = 0;

        foreach ($transactions as $row) {
            if (!is_array($row)) {
                continue;
            }

            $instrument = $this->normalizeValue($row['instrument_type'] ?? null);
            $txnDate    = $this->normalizeValue($row['transaction_date'] ?? null);
            $serial     = $this->normalizeValue($row['serial_no'] ?? null);
            $page       = $this->normalizeValue($row['page_no'] ?? null);
            $vol        = $this->normalizeValue($row['vol_no'] ?? null);
            $regDate    = $this->normalizeValue($row['reg_date'] ?? null);
            $regTime    = $this->normalizeValue($row['reg_time'] ?? null);
            $grantor    = $this->normalizeValue($row['grantor'] ?? null);
            $grantee    = $this->normalizeValue($row['grantee'] ?? null);
            $cofoType   = $this->normalizeValue($row['cofo_type'] ?? null);

            // Skip fully-empty rows.
            if (!$instrument && !$txnDate && !$serial && !$page && !$vol && !$regDate && !$regTime && !$grantor && !$grantee) {
                continue;
            }

            // Certificate of Occupancy transactions belong in CofO_staging, not pra.
            $isCofo = strcasecmp((string) $instrument, 'Certificate of Occupancy') === 0;

            $payload = [
                'mlsFNo'            => $fileIndexing->file_number,
                'NewKANGISFileno'   => $newKangis,
                'kangisFileNo'      => $kangis,
                'transaction_type'  => $instrument,
                'instrument_type'   => $instrument,
                'transaction_date'  => $txnDate,
                // registration date/time live in the deeds_date/deeds_time columns
                'deeds_date'        => $regDate,
                'deeds_time'        => $regTime,
                'serialNo'          => $serial,
                'pageNo'            => $page,
                'volumeNo'          => $vol,
                'Grantor'           => $grantor,
                'Grantee'           => $grantee,
                'party_1'           => $grantor,
                'party_2'           => $grantee,
                'land_use'          => $fileIndexing->land_use_type,
                'plot_no'           => $fileIndexing->plot_number,
                'location'          => $fileIndexing->location ?? $fileIndexing->district,
                'property_description' => $fileIndexing->location ?? $fileIndexing->district,
                'lgsaOrCity'        => $fileIndexing->lga,
                'source'            => 'File Indexing',
                'created_by'        => Auth::id(),
                'updated_by'        => Auth::id(),
                'created_at'        => now(),
                'updated_at'        => now(),
            ];

            if ($propId !== null) {
                $payload['prop_id'] = $propId;
            }
            // Old KANGIS parent link so Legal Search's ancestor expansion resolves
            // this transaction when the property is searched from any of its files.
            if ($parentPropId !== null) {
                $payload['parent_prop_id'] = (string) $parentPropId;
            }

            if ($isCofo) {
                // CofO-specific columns
                $payload['cofo_type'] = $cofoType;
                $payload['cofo_date'] = $txnDate;
                $payload['transaction_time'] = $regTime;
                $payload['regNo'] = $this->formatRegistrationNumber($serial, $page, $vol);
            }

            // Re-saving the edit form resubmits rows still sitting in the DOM, so skip a row
            // that is already stored against this file with the same identifying values.
            if ($this->newKangisTransactionExists($isCofo ? self::COFO_TABLE : 'pra', $fileIndexing->file_number, $instrument, $txnDate, $serial, $page, $vol)) {
                continue;
            }

            try {
                ($isCofo ? $cofoTable : $praTable)->insert($payload);
                $saved++;
            } catch (\Throwable $e) {
                Log::warning('FileIndexing: transaction insert failed', [
                    'file_number' => $fileIndexing->file_number,
                    'table' => $isCofo ? self::COFO_TABLE : 'pra',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($saved > 0) {
            try {
                $fileIndexing->update(['has_transaction' => 1]);
            } catch (\Throwable $e) {
                // non-fatal
            }
        }
    }

    /**
     * True when an identical "Has Transaction" row is already stored for this file — the
     * guard that keeps a repeated save of the edit form from duplicating rows. Matches on
     * the identifying values a user types; a genuinely different row differs in at least one.
     */
    protected function newKangisTransactionExists(
        string $table,
        ?string $fileNumber,
        ?string $instrument,
        ?string $txnDate,
        ?string $serial,
        ?string $page,
        ?string $vol
    ): bool {
        $fileNumber = trim((string) $fileNumber);
        if ($fileNumber === '') {
            return false;
        }

        try {
            $query = DB::connection('sqlsrv')->table($table)
                ->whereRaw('UPPER(LTRIM(RTRIM(mlsFNo))) = UPPER(?)', [$fileNumber]);

            foreach ([
                'instrument_type'  => $instrument,
                'transaction_date' => $txnDate,
                'serialNo'         => $serial,
                'pageNo'           => $page,
                'volumeNo'         => $vol,
            ] as $column => $value) {
                if ($value === null || $value === '') {
                    $query->where(function ($q) use ($column) {
                        $q->whereNull($column)->orWhere($column, '');
                    });
                } else {
                    $query->where($column, $value);
                }
            }

            return $query->exists();
        } catch (\Throwable $e) {
            // Never let the guard block a legitimate save.
            Log::warning('newKangisTransactionExists: duplicate check failed', [
                'table' => $table,
                'file_number' => $fileNumber,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    protected function syncEntityAndCustomer(FileIndexing $fileIndexing, Request $request, ?string $testControl = null): void
    {
        $entityPayload = $request->input('entity_details');
        if (!is_array($entityPayload)) {
            $entityPayload = [
                'entity_id' => $request->input('entity_id'),
                'entity_name' => $request->input('entity_name'),
                'entity_type' => $request->input('entity_type'),
            ];
        }

        $entityName = $this->normalizeValue($entityPayload['entity_name'] ?? null);

        // Fallback to file title if entity name is missing
        if ($entityName === null) {
            $entityName = $fileIndexing->file_title;
        }

        $entityId = null;

        if ($entityName !== null) {
            $entity = null;
            if (!empty($entityPayload['entity_id'])) {
                $entity = Entity::on('sqlsrv')->find($entityPayload['entity_id']);
            }

            if (!$entity) {
                $entity = Entity::on('sqlsrv')->firstOrNew([
                    'file_number' => $fileIndexing->file_number,
                ]);
            }

            $entity->entity_name = $entityName;
            $entity->entity_type = $entityPayload['entity_type'] ?? 'Individual';
            $entity->file_number = $fileIndexing->file_number;

            if ($testControl) {
                $entity->test_control = $testControl;
            }

            $entity->save();
            $entityId = $entity->id;
        }

        $customerPayload = $request->input('customer_details');
        if (!is_array($customerPayload)) {
            $customerPayload = [
                'customer_id' => $request->input('customer_id'),
                'customer_type' => $request->input('customer_type'),
                'customer_name' => $request->input('customer_name'),
                'status' => $request->input('customer_status'),
                'account_no' => $request->input('customer_account_no'),
                'customer_code' => $request->input('customer_code'),
                'email' => $request->input('customer_email'),
                'phone' => $request->input('customer_phone'),
                'property_address' => $request->input('property_address'),
                'reason_retired' => $request->input('reason_retired'),
                'retired_by' => $request->input('retired_by'),
            ];
        }

        $customerName = $this->normalizeValue($customerPayload['customer_name'] ?? null);

        // Fallback for customer name
        if ($customerName === null) {
            $customerName = $entityName ?? $fileIndexing->file_title ?? 'Unknown Customer';
        }

        $customerIndicators = [
            $customerName,
            $this->normalizeValue($customerPayload['phone'] ?? null),
            $this->normalizeValue($customerPayload['email'] ?? null),
            $this->normalizeValue($customerPayload['account_no'] ?? null),
            $this->normalizeValue($customerPayload['property_address'] ?? null),
            $this->normalizeValue($customerPayload['reason_retired'] ?? null),
            $this->normalizeValue($customerPayload['retired_by'] ?? null),
        ];

        $hasCustomerData = array_filter($customerIndicators, static function ($value) {
            return $value !== null;
        });

        if (empty($hasCustomerData)) {
            return;
        }

        $customer = null;
        if (!empty($customerPayload['customer_id'])) {
            $customer = Customer::on('sqlsrv')->find($customerPayload['customer_id']);
        }

        if (!$customer) {
            $customer = Customer::on('sqlsrv')->firstOrNew([
                'file_number' => $fileIndexing->file_number,
                'customer_name' => $customerName,
            ]);
        }

        $customer->customer_type = $customerPayload['customer_type'] ?? 'Individual';
        $customer->status = $customerPayload['status'] ?? 'Active';
        $customer->customer_name = $customerName;
        $customer->file_number = $fileIndexing->file_number;
        $customer->account_no = $this->normalizeValue($customerPayload['account_no'] ?? null);
        $customer->customer_code = $this->normalizeValue($customerPayload['customer_code'] ?? null);
        $customer->email = $this->normalizeValue($customerPayload['email'] ?? null);
        $customer->phone = $this->normalizeValue($customerPayload['phone'] ?? null);
        $customer->property_address = $this->normalizeValue($customerPayload['property_address'] ?? null);
        $customer->reason_retired = $this->normalizeValue($customerPayload['reason_retired'] ?? null);
        $customer->retired_by = $this->normalizeValue($customerPayload['retired_by'] ?? null);
        $customer->entity_id = $entityId;
        if ($testControl) {
            $customer->test_control = $testControl;
        }

        if (!$customer->exists) {
            $customer->created_by = Auth::id();
        }
        $customer->updated_by = Auth::id();

        $customer->save();
    }

    protected function updateFileHistoryPropId(string $fileNumber, ?int $propId, ?string $testControl = null): void
    {
        if ($propId === null) {
            return;
        }

        $this->propertyIdAllocationService->syncPropIdToFileHistory($fileNumber, $propId, $testControl);
    }

    protected function normalizePropIdInput($rawValue): ?int
    {
        if ($rawValue === null) {
            return null;
        }

        if (is_array($rawValue)) {
            $rawValue = $rawValue[0] ?? null;
            if ($rawValue === null) {
                return null;
            }
        }

        $stringValue = trim((string) $rawValue);
        if ($stringValue === '' || !ctype_digit($stringValue)) {
            return null;
        }

        $intValue = (int) $stringValue;

        return $intValue > 0 ? $intValue : null;
    }

    /**
     * Resolve the prop_id to persist, treating PropID_Master — not the request — as the
     * authority.
     *
     * The client posts a prop_id it scraped from the file-history panel, and this used to
     * be written verbatim after nothing more than a ctype_digit check. That let a stale or
     * mis-keyed value claim a prop_id belonging to an entirely different file, which then
     * cross-contaminates Legal Search timelines (the LS guard treats a PropID_Master hit as
     * proof of a legitimate alias, so a stray id launders itself into "same parcel").
     *
     * Precedence: the registry's answer for this file > a client value the registry cannot
     * contradict > a freshly allocated id. A rejected value is logged, never silently kept.
     */
    protected function resolveTrustedPropId($rawInput, ?string $primary, ...$alternates): ?int
    {
        $client = $this->normalizePropIdInput($rawInput);
        $canonical = $this->lookupCanonicalPropId($primary, $alternates);

        if ($client === null) {
            return $canonical ?? $this->allocatePropIdForFileIndexing($primary, $alternates);
        }

        // The registry already knows this file's prop_id — it wins over the request.
        if ($canonical !== null && $canonical !== $client) {
            Log::warning('FileIndexing::resolveTrustedPropId - rejected client prop_id (registry disagrees)', [
                'file_number' => $primary,
                'client_prop_id' => $client,
                'canonical_prop_id' => $canonical,
            ]);

            return $canonical;
        }

        // The registry has no entry for this file, so it cannot confirm the client value.
        // Reject it anyway if it demonstrably belongs to a DIFFERENT file.
        if ($canonical === null) {
            $owner = $this->propIdRegisteredOwner($client);
            if ($owner !== null && !$this->identifiersMatch($owner, $primary, $alternates)) {
                Log::warning('FileIndexing::resolveTrustedPropId - rejected client prop_id (owned by another file)', [
                    'file_number' => $primary,
                    'client_prop_id' => $client,
                    'registered_to' => $owner,
                ]);

                return $this->allocatePropIdForFileIndexing($primary, $alternates);
            }
        }

        return $client;
    }

    /** This file's prop_id according to PropID_Master, by any of its registered identifiers. */
    protected function lookupCanonicalPropId(?string $primary, array $alternates = []): ?int
    {
        $candidates = [];
        foreach (array_merge([$primary], $alternates) as $value) {
            if (is_array($value)) {
                foreach ($value as $sub) {
                    $sub = $this->normalizeValue($sub);
                    if ($sub !== null) {
                        $candidates[] = strtoupper($sub);
                    }
                }
                continue;
            }
            $value = $this->normalizeValue($value);
            if ($value !== null) {
                $candidates[] = strtoupper($value);
            }
        }

        $candidates = array_values(array_unique($candidates));
        if (empty($candidates)) {
            return null;
        }

        try {
            $propId = DB::connection('sqlsrv')->table('PropID_Master')
                ->where(function ($q) use ($candidates) {
                    foreach (self::MASTER_ALIAS_COLUMNS as $col) {
                        $q->orWhereIn($col, $candidates);
                    }
                })
                ->value('prop_id');

            return ($propId !== null && (int) $propId > 0) ? (int) $propId : null;
        } catch (\Throwable $exception) {
            Log::warning('FileIndexing::lookupCanonicalPropId - failed', [
                'file_number' => $primary,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /** The primary file number PropID_Master has registered against a prop_id, if any. */
    protected function propIdRegisteredOwner(int $propId): ?string
    {
        try {
            $owner = DB::connection('sqlsrv')->table('PropID_Master')
                ->where('prop_id', $propId)
                ->value('primary_file_number');

            return $this->normalizeValue($owner);
        } catch (\Throwable $exception) {
            return null;
        }
    }

    /** Is $owner one of the identifiers this record is being saved under? */
    protected function identifiersMatch(string $owner, ?string $primary, array $alternates = []): bool
    {
        $owner = strtoupper($owner);
        foreach (array_merge([$primary], $alternates) as $value) {
            $values = is_array($value) ? $value : [$value];
            foreach ($values as $sub) {
                $sub = $this->normalizeValue($sub);
                if ($sub !== null && strtoupper($sub) === $owner) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function allocatePropIdForFileIndexing(?string $primary, ...$alternates): ?int
    {
        $primaryValue = $this->normalizeValue($primary);

        if ($primaryValue === null) {
            return null;
        }

        // Flatten alternates to handle mixed array/string inputs
        $flatAlternates = [];
        foreach ($alternates as $alt) {
            if (is_array($alt)) {
                foreach ($alt as $subAlt) {
                    $flatAlternates[] = $subAlt;
                }
            } else {
                $flatAlternates[] = $alt;
            }
        }

        $normalizedAlternates = [];
        foreach ($flatAlternates as $alternate) {
            $normalizedAlternates[] = $this->normalizeValue($alternate);
        }
        $normalizedAlternates = array_pad($normalizedAlternates, 3, null);

        try {
            return $this->propertyIdAllocationService->allocateOrRetrievePropId(
                $primaryValue,
                $normalizedAlternates[0],
                $normalizedAlternates[1],
                $normalizedAlternates[2]
            );
        } catch (\Throwable $exception) {
            Log::warning('FileIndexing::allocatePropIdForFileIndexing - failed', [
                'file_number' => $primaryValue,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    protected function determinePropIdForIndexing($existingRecord, array $validated): ?int
    {
        $primaryCandidate = $validated['file_number'] ?? ($existingRecord->file_number ?? null);

        return $this->allocatePropIdForFileIndexing(
            $primaryCandidate,
            $existingRecord->file_number ?? null,
            $validated['related_fileno'] ?? null,
            $existingRecord->related_fileno ?? null
        );
    }

    protected function normalizeValue($value): ?string
    {
        if ($value === null) {
            return null;
        }

        // Handle array values by picking the first non-null element
        if (is_array($value)) {
            $value = $value[0] ?? null;
            if ($value === null) {
                return null;
            }
        }

        $stringValue = is_string($value) ? trim($value) : trim((string) $value);

        return $stringValue === '' ? null : $stringValue;
    }


    public function getShelfForBatch($batch)
    {
        try {
            if (empty($batch)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Batch number is required'
                ], 400);
            }

            $batchNumber = (int) $batch;
            if ($batchNumber <= 0) {
                return response()->json(['success' => false, 'message' => 'Invalid batch number'], 400);
            }

            // Check if fileindexing_batch table exists
            $batchTableExists = DB::connection('sqlsrv')->select("
                SELECT TABLE_NAME 
                FROM INFORMATION_SCHEMA.TABLES 
                WHERE TABLE_NAME = 'fileindexing_batch'
            ");

            if (!empty($batchTableExists)) {
                // Use the new batch management system
                $batchRecord = \App\Models\FileindexingBatch::where('batch_number', $batchNumber)
                    ->where('is_active', true)
                    ->first();

                if (!$batchRecord) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Batch not found or not available',
                        'label' => ''
                    ]);
                }

                if ($batchRecord->is_full) {
                    return response()->json([
                        'success' => false,
                        'message' => 'All shelves in this batch are already used',
                        'label' => ''
                    ]);
                }

                // Get the next available shelf
                $shelf = $batchRecord->getNextAvailableShelf();

                if ($shelf) {
                    $label = $shelf->full_label ?? "Rack-Shelf-{$shelf->id}";
                    return response()->json([
                        'success' => true,
                        'label' => $label,
                        'shelf_label_id' => $shelf->id,
                        'batch_id' => $batchRecord->id,
                        'message' => 'Shelf location found using batch management system',
                        'batch_info' => [
                            'used_shelves' => $batchRecord->used_shelves,
                            'total_shelves' => $batchRecord->shelf_count,
                            'available_shelves' => $batchRecord->available_shelves
                        ]
                    ]);
                } else {
                    // Mark batch as full if no shelves available
                    $batchRecord->update(['is_full' => true]);

                    return response()->json([
                        'success' => false,
                        'message' => 'No available shelves in this batch (batch marked as full)',
                        'label' => ''
                    ]);
                }
            }

            // Fallback to legacy system
            return $this->getLegacyShelfForBatch($batchNumber);

        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'label' => "Default-S{$batch}",
                'message' => 'Database error occurred, using default shelf location',
                'error' => $e->getMessage()
            ]);
        }
    }

    private function getLegacyShelfForBatch($batchNumber)
    {
        // Legacy system fallback
        $tableExists = DB::connection('sqlsrv')->select("
            SELECT TABLE_NAME 
            FROM INFORMATION_SCHEMA.TABLES 
            WHERE TABLE_NAME = 'Rack_Shelf_Labels'
        ");

        if (empty($tableExists)) {
            return response()->json([
                'success' => true,
                'label' => "A1-S{$batchNumber}",
                'message' => 'Using legacy default shelf location'
            ]);
        }

        $startId = ($batchNumber - 1) * 100 + 1;
        $endId = $batchNumber * 100;

        $shelf = DB::connection('sqlsrv')->table('Rack_Shelf_Labels')
            ->whereBetween('id', [$startId, $endId])
            ->where(function ($query) {
                $query->where('is_used', 0)->orWhereNull('is_used');
            })
            ->orderBy('id')
            ->first();

        if ($shelf) {
            $label = $shelf->full_label ?? "Rack-Shelf-{$shelf->id}";
            return response()->json([
                'success' => true,
                'label' => $label,
                'shelf_label_id' => $shelf->id,
                'message' => 'Legacy shelf location found'
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'No available shelf in this batch (legacy)',
                'label' => ''
            ]);
        }
    }

    /**
     * Get specific shelf information by shelf_label_id
     */
    public function getShelfById($shelfId)
    {
        try {
            if (empty($shelfId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Shelf ID is required'
                ], 400);
            }

            $shelf = DB::connection('sqlsrv')->table('Rack_Shelf_Labels')
                ->where('id', $shelfId)
                ->first();

            if (!$shelf) {
                return response()->json([
                    'success' => false,
                    'message' => 'Shelf not found',
                    'label' => ''
                ]);
            }

            // Check if shelf is already used
            $isUsedInRack = $shelf->is_used == 1;
            $isUsedInFileIndexing = DB::connection('sqlsrv')->table('file_indexings')
                ->where('shelf_label_id', $shelfId)
                ->exists();

            if ($isUsedInRack || $isUsedInFileIndexing) {
                return response()->json([
                    'success' => false,
                    'message' => 'This shelf is already in use',
                    'label' => $shelf->full_label
                ]);
            }

            return response()->json([
                'success' => true,
                'label' => $shelf->full_label,
                'shelf_label_id' => $shelf->id,
                'rack' => $shelf->rack,
                'shelf' => $shelf->shelf,
                'message' => 'Shelf is available for use'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error checking shelf availability: ' . $e->getMessage(),
                'label' => ''
            ]);
        }
    }

    /**
     * Get available shelf labels for direct selection
     */

    public function getAvailableShelfLabels(Request $request)
    {
        try {
            $page = $request->get('page', 1);
            $perPage = 20; // Show more shelf labels per page
            $searchTerm = $request->get('q', '');
            $offset = ($page - 1) * $perPage;

            // Build query for available shelf labels
            $query = DB::connection('sqlsrv')->table('Rack_Shelf_Labels')
                ->where(function ($q) {
                    $q->where('is_used', 0)->orWhereNull('is_used');
                })
                ->whereNotIn('id', function ($subquery) {
                    $subquery->select('shelf_label_id')
                        ->from('file_indexings')
                        ->whereNotNull('shelf_label_id');
                });

            // Apply search filter
            if (!empty($searchTerm)) {
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('full_label', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('id', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('rack', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('shelf', 'LIKE', "%{$searchTerm}%");
                });
            }

            $total = $query->count();

            $shelves = $query->orderBy('id')
                ->offset($offset)
                ->limit($perPage)
                ->get();

            $results = $shelves->map(function ($shelf) {
                return [
                    'id' => $shelf->id,
                    'text' => $shelf->full_label . " (ID: {$shelf->id})",
                    'shelf_label_id' => $shelf->id,
                    'full_label' => $shelf->full_label,
                    'rack' => $shelf->rack,
                    'shelf' => $shelf->shelf
                ];
            });

            return response()->json([
                'success' => true,
                'shelves' => $results,
                'pagination' => [
                    'more' => ($offset + $perPage) < $total
                ],
                'total' => $total,
                'message' => "Found {$total} available shelf labels"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'shelves' => [],
                'pagination' => ['more' => false],
                'total' => 0,
                'message' => 'Error fetching shelf labels: ' . $e->getMessage()
            ]);
        }
    }

    public function getAvailableBatches(Request $request)
    {
        try {
            $page = $request->get('page', 1);
            $perPage = 5;
            $searchTerm = $request->get('q', '');

            // Check if fileindexing_batch table exists
            $batchTableExists = DB::connection('sqlsrv')->select("
                SELECT TABLE_NAME 
                FROM INFORMATION_SCHEMA.TABLES 
                WHERE TABLE_NAME = 'fileindexing_batch'
            ");

            if (!empty($batchTableExists)) {
                // Use the new batch system
                $result = \App\Models\FileindexingBatch::getAvailableBatches($searchTerm, $page, $perPage);

                return response()->json([
                    'success' => true,
                    'batches' => $result['batches'],
                    'pagination' => $result['pagination'],
                    'message' => "Found {$result['total']} available batches using batch management system"
                ]);
            }

            // Fallback to legacy system if batch table doesn't exist
            return $this->getLegacyAvailableBatches($request);

        } catch (\Exception $e) {
            // Emergency fallback
            $batches = [];
            for ($i = 1; $i <= 5; $i++) {
                if (empty($searchTerm) || strpos((string) $i, $searchTerm) !== false) {
                    $batches[] = [
                        'id' => $i,
                        'text' => $i
                    ];
                }
            }
            return response()->json([
                'success' => true,
                'batches' => $batches,
                'pagination' => ['more' => false],
                'message' => 'Using emergency fallback batch numbers',
                'error' => $e->getMessage()
            ]);
        }
    }

    private function getLegacyAvailableBatches(Request $request)
    {
        $page = $request->get('page', 1);
        $perPage = 5;
        $offset = ($page - 1) * $perPage;
        $searchTerm = $request->get('q', '');

        // Check if the Rack_Shelf_Labels table exists
        $tableExists = DB::connection('sqlsrv')->select("
            SELECT TABLE_NAME 
            FROM INFORMATION_SCHEMA.TABLES 
            WHERE TABLE_NAME = 'Rack_Shelf_Labels'
        ");

        if (empty($tableExists)) {
            // Table doesn't exist, return default batches
            $batches = [];
            $startBatch = ($page - 1) * $perPage + 1;
            $endBatch = $page * $perPage;

            for ($i = $startBatch; $i <= $endBatch; $i++) {
                if (empty($searchTerm) || strpos((string) $i, $searchTerm) !== false) {
                    $batches[] = [
                        'id' => $i,
                        'text' => $i
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'batches' => $batches,
                'pagination' => [
                    'more' => $page < 20
                ],
                'message' => 'Using legacy default batch numbers'
            ]);
        }

        // Find batch numbers that have available unused shelves (legacy method)
        $availableBatchNumbers = [];
        $maxShelfId = DB::connection('sqlsrv')->table('Rack_Shelf_Labels')->max('id') ?? 1000;
        $maxBatchNumber = (int) ceil($maxShelfId / 100);

        for ($batchNumber = 1; $batchNumber <= $maxBatchNumber; $batchNumber++) {
            if (!empty($searchTerm) && strpos((string) $batchNumber, $searchTerm) === false) {
                continue;
            }

            $startId = ($batchNumber - 1) * 100 + 1;
            $endId = $batchNumber * 100;

            $hasUnusedShelf = DB::connection('sqlsrv')->table('Rack_Shelf_Labels')
                ->whereBetween('id', [$startId, $endId])
                ->where(function ($query) {
                    $query->where('is_used', 0)->orWhereNull('is_used');
                })
                ->exists();

            if ($hasUnusedShelf) {
                $availableBatchNumbers[] = $batchNumber;
            }
        }

        $totalAvailable = count($availableBatchNumbers);
        $paginatedBatches = array_slice($availableBatchNumbers, $offset, $perPage);

        $batches = array_map(function ($batchNumber) {
            return [
                'id' => $batchNumber,
                'text' => $batchNumber
            ];
        }, $paginatedBatches);

        return response()->json([
            'success' => true,
            'batches' => $batches,
            'pagination' => [
                'more' => ($offset + $perPage) < $totalAvailable
            ],
            'message' => "Found {$totalAvailable} legacy batches with available shelves"
        ]);
    }

    // Return distinct batch numbers present in file_indexings (limited to batches with records)
    public function distinctBatches()
    {
        try {
            $batches = DB::connection('sqlsrv')->table('file_indexings')
                ->whereNotNull('batch_no')
                ->select(DB::raw('DISTINCT batch_no'))
                ->orderBy('batch_no')
                ->pluck('batch_no')
                ->toArray();

            // Debug: Log what we found
            \Log::info('DistinctBatches Debug - Found batches: ', $batches);
            \Log::info('DistinctBatches Debug - Total count: ' . count($batches));

            // Also check what's actually in the table
            $sampleRecords = DB::connection('sqlsrv')->table('file_indexings')
                ->whereNotNull('batch_no')
                ->select('batch_no', 'file_number', 'registry')
                ->orderBy('batch_no', 'desc')
                ->limit(10)
                ->get();
            \Log::info('DistinctBatches Debug - Sample records: ', $sampleRecords->toArray());

            return response()->json(['success' => true, 'batches' => $batches]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // Debug method to check file_indexings table content
    public function debugBatches()
    {
        try {
            // Get all distinct batch_no values with count
            $batchCounts = DB::connection('sqlsrv')->table('file_indexings')
                ->select(DB::raw('batch_no, COUNT(*) as record_count'))
                ->whereNotNull('batch_no')
                ->groupBy('batch_no')
                ->orderBy('batch_no')
                ->get();

            // Get table row count
            $totalRows = DB::connection('sqlsrv')->table('file_indexings')->count();

            // Get sample of latest records
            $latestRecords = DB::connection('sqlsrv')->table('file_indexings')
                ->select('id', 'batch_no', 'file_number', 'registry', 'created_at')
                ->orderBy('id', 'desc')
                ->limit(20)
                ->get();

            return response()->json([
                'success' => true,
                'total_rows' => $totalRows,
                'batch_counts' => $batchCounts,
                'latest_records' => $latestRecords
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // Show standalone Sign In & Out page
    public function signin()
    {
        return view('fileindexing.signin');
    }

    // Generate Sign In & Out report for a given batch_no (JSON)
    public function signinReport(Request $request)
    {
        $batch = trim((string) $request->get('batch_no', ''));
        if ($batch === '') {
            return response()->json(['success' => false, 'message' => 'Invalid batch number'], 400);
        }

        try {
            // Limit to 100 records per batch
            $rows = DB::connection('sqlsrv')->table('file_indexings as f')
                ->leftJoin('users as u', function ($join) {
                    // file_indexings.created_by holds a NAME for indexing-created rows,
                    // so a direct id join makes SQL Server fail the whole query with
                    // "Error converting data type nvarchar to bigint".
                    $join->on('u.id', '=', DB::raw('TRY_CONVERT(INT, f.created_by)'));
                })
                ->whereRaw('LTRIM(RTRIM(CONVERT(NVARCHAR(50), f.batch_no))) = ?', [$batch])
                ->orderBy('f.id')
                ->limit(100)
                ->get([
                    'f.file_number',
                    DB::raw("COALESCE(f.registry, '') as registry"),
                    DB::raw("COALESCE(f.batch_no, '') as batch_no"),
                    DB::raw("COALESCE(f.file_title, '') as name"),
                    DB::raw("COALESCE(f.plot_number, '') as plot_number"),
                    DB::raw("COALESCE(f.lga, '') as lga"),
                    DB::raw("COALESCE(f.workflow_status, '') as status"),
                    DB::raw("COALESCE(f.shelf_location, '') as location"),
                    DB::raw("COALESCE(f.land_use_type, '') as land_use"),
                    DB::raw("COALESCE(f.district, '') as district"),
                    DB::raw("CONVERT(varchar(19), f.created_at, 120) as indexed_date"),
                    DB::raw("COALESCE(u.first_name + ' ' + u.last_name, f.created_by, '') as indexed_by")
                ]);

            // Debug: Log first few records to see actual data
            \Log::info('SigninReport Debug - Batch: ' . $batch);
            \Log::info('SigninReport Debug - Total rows: ' . count($rows));
            if (count($rows) > 0) {
                \Log::info('SigninReport Debug - First row: ', [
                    'registry' => $rows[0]->registry ?? 'NULL',
                    'batch_no' => $rows[0]->batch_no ?? 'NULL',
                    'file_number' => $rows[0]->file_number ?? 'NULL'
                ]);
            }

            return response()->json(['success' => true, 'rows' => $rows]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // Export Sign In & Out report (format = pdf | excel)
    public function exportSigninReport($format, Request $request)
    {
        $batch = trim((string) $request->get('batch_no', ''));
        if ($batch === '') {
            return response('Invalid batch number', 400);
        }

        // Reuse signinReport query
        $rows = DB::connection('sqlsrv')->table('file_indexings as f')
            ->leftJoin('users as u', function ($join) {
                    // file_indexings.created_by holds a NAME for indexing-created rows,
                    // so a direct id join makes SQL Server fail the whole query with
                    // "Error converting data type nvarchar to bigint".
                    $join->on('u.id', '=', DB::raw('TRY_CONVERT(INT, f.created_by)'));
                })
            ->whereRaw('LTRIM(RTRIM(CONVERT(NVARCHAR(50), f.batch_no))) = ?', [$batch])
            ->orderBy('f.id')
            ->limit(100)
            ->get([
                'f.file_number',
                DB::raw("COALESCE(f.registry, '') as registry"),
                DB::raw("COALESCE(f.batch_no, '') as batch_no"),
                DB::raw("COALESCE(f.file_title, '') as name"),
                DB::raw("COALESCE(f.plot_number, '') as plot_number"),
                DB::raw("COALESCE(f.lga, '') as lga"),
                DB::raw("COALESCE(f.workflow_status, '') as status"),
                DB::raw("COALESCE(f.shelf_location, '') as location"),
                DB::raw("COALESCE(f.land_use_type, '') as land_use"),
                DB::raw("COALESCE(f.district, '') as district"),
                DB::raw("CONVERT(varchar(19), f.created_at, 120) as indexed_date"),
                DB::raw("COALESCE(u.first_name + ' ' + u.last_name, f.created_by, '') as indexed_by")
            ]);

        if (strtolower($format) === 'excel') {
            // Return CSV as Excel-friendly download
            $filename = "signin_batch_{$batch}.csv";
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ];

            $callback = function () use ($rows, $batch) {
                $out = fopen('php://output', 'w');

                // Get registry/batch from first row for header
                $registryBatch = '';
                if (count($rows) > 0) {
                    $firstRow = $rows[0];
                    $registryBatch = ($firstRow->registry ?? '') . (($firstRow->registry && $firstRow->batch_no) ? '/' : '') . ($firstRow->batch_no ?? '');
                }

                // Add title row
                fputcsv($out, ['Sign In & Out Sheet - Registry/Batch: ' . $registryBatch]);
                fputcsv($out, []); // Empty row

                // Header row without Registry/Batch column
                fputcsv($out, ['N/S', 'File Number', 'Plot Number', 'LGA', 'Name', 'Location', 'Land Use', 'District', 'Indexed Date', 'Indexed By']);
                $i = 1;
                foreach ($rows as $r) {
                    fputcsv($out, [$i++, $r->file_number, $r->plot_number, $r->lga ?? '', $r->name, $r->location, $r->land_use, $r->district, $r->indexed_date, $r->indexed_by]);
                }
                // signature lines
                fputcsv($out, []);
                fputcsv($out, ['Sign In (KLAES MDC):', 'Signed:', 'Date Received:', 'Date Submitted:']);
                fputcsv($out, ['Sign Out (Ministry):', 'Signed:', 'Date Submitted:', 'Date Received:']);
                fclose($out);
            };

            return response()->stream($callback, 200, $headers);
        }

        // Default: generate simple HTML for printing; prefer producing a real PDF when possible

        // Get registry/batch from first row for title
        $registryBatch = '';
        if (count($rows) > 0) {
            $firstRow = $rows[0];
            $registryBatch = ($firstRow->registry ?? '') . (($firstRow->registry && $firstRow->batch_no) ? '/' : '') . ($firstRow->batch_no ?? '');
        }

        $html = '<html><head><meta charset="utf-8"><title>Sign In & Out - Registry/Batch ' . htmlspecialchars($registryBatch) . '</title><style>body{font-family: Arial, sans-serif;}table{width:100%;border-collapse:collapse;}th,td{border:1px solid #ddd;padding:8px;}th{background:#f7f7f7;text-align:left;}</style></head><body>';
        $html .= '<h2>Sign In & Out Report - Registry/Batch: ' . htmlspecialchars($registryBatch) . '</h2>';
        $html .= '<table><thead><tr><th>N/S</th><th>File Number</th><th>Plot Number</th><th>LGA</th><th>Name</th><th>Location</th><th>Land Use</th><th>District</th><th>Indexed Date</th><th>Indexed By</th></tr></thead><tbody>';
        $i = 1;
        foreach ($rows as $r) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($i++) . '</td>';
            $html .= '<td>' . htmlspecialchars($r->file_number) . '</td>';
            $html .= '<td>' . htmlspecialchars($r->plot_number) . '</td>';
            $html .= '<td>' . htmlspecialchars($r->lga ?? '') . '</td>';
            $html .= '<td>' . htmlspecialchars($r->name) . '</td>';
            $html .= '<td>' . htmlspecialchars($r->location) . '</td>';
            $html .= '<td>' . htmlspecialchars($r->land_use) . '</td>';
            $html .= '<td>' . htmlspecialchars($r->district) . '</td>';
            $html .= '<td>' . htmlspecialchars($r->indexed_date) . '</td>';
            $html .= '<td>' . htmlspecialchars($r->indexed_by) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        $html .= '<div style="margin-top:40px;display:flex;justify-content:flex-end"><div style="width:360px"><div class="text-sm font-semibold">Sign In (KLAES MDC)</div><div style="margin-top:12px">Signed: ____________________________</div><div style="margin-top:6px">Date Received: _______________________</div><div style="margin-top:6px">Date Submitted: ______________________</div><div style="margin-top:24px" class="text-sm font-semibold">Sign Out (Ministry)</div><div style="margin-top:12px">Signed: ____________________________</div><div style="margin-top:6px">Date Submitted: ______________________</div><div style="margin-top:6px">Date Received: _______________________</div></div></div>';
        $html .= '</body></html>';

        // If the request asked for PDF, attempt to render using dompdf (preferred). If not available, fall back to streaming HTML with PDF headers.
        if (strtolower($format) === 'pdf') {
            // Use Barryvdh arryvdh/laravel-dompdf if available
            if (class_exists('\Barryvdh\DomPDF\Facade\Pdf')) {
                try {
                    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4', 'landscape');
                    return $pdf->download("signin_batch_{$batch}.pdf");
                } catch (\Exception $e) {
                    // fallthrough to fallback
                    \Log::error('PDF generation (barryvdh) failed: ' . $e->getMessage());
                }
            }

            // Try using dompdf/dompdf directly if available
            if (class_exists('\Dompdf\Dompdf')) {
                try {
                    $dompdf = new \Dompdf\Dompdf();
                    $dompdf->loadHtml($html);
                    $dompdf->setPaper('A4', 'landscape');
                    $dompdf->render();
                    $output = $dompdf->output();
                    $headers = [
                        'Content-Type' => 'application/pdf',
                        'Content-Disposition' => "attachment; filename=\"signin_batch_{$batch}.pdf\"",
                    ];
                    return response($output, 200, $headers);
                } catch (\Exception $e) {
                    \Log::error('PDF generation (dompdf) failed: ' . $e->getMessage());
                }
            }

            // Fallback: PDF library not available or generation failed.
            // Returning raw HTML with application/pdf headers breaks PDF viewers (causes "Failed to load PDF document").
            // Instead, return an HTML download and log the condition so admins can install a PDF library.
            \Log::warning("PDF generation unavailable for signin batch {$batch} - dompdf/barryvdh missing or failed");

            $notice = '<div style="margin-top:16px;padding:12px;border:1px solid #f1c40f;background:#fff8e1;color:#664d03;font-size:13px;">
                <strong>Note:</strong> PDF generation is not available on this server. To enable PDF export, install <code>barryvdh/laravel-dompdf</code> or <code>dompdf/dompdf</code> and restart the app.
            </div>';

            $fullHtml = $html . $notice;
            $headers = [
                'Content-Type' => 'text/html; charset=utf-8',
                'Content-Disposition' => "attachment; filename=\"signin_batch_{$batch}.html\"",
            ];

            return response($fullHtml, 200, $headers);
        }

        // If not requesting PDF, return HTML (used previously for default)
        $headers = [
            'Content-Type' => 'text/html',
            'Content-Disposition' => "attachment; filename=\"signin_batch_{$batch}.html\"",
        ];

        return response($html, 200, $headers);
    }

    /**
     * Generate new batches
     */
    public function generateBatches(Request $request)
    {
        try {
            $batchesToGenerate = $request->get('count', 100);

            if ($batchesToGenerate < 1 || $batchesToGenerate > 1000) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid batch count. Must be between 1 and 1000.'
                ], 400);
            }

            $generated = \App\Models\FileindexingBatch::generateBatches($batchesToGenerate);

            return response()->json([
                'success' => true,
                'message' => "Successfully generated {$generated} new batches",
                'generated_count' => $generated
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate batches: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get batch management dashboard data
     */
    public function getBatchManagementData()
    {
        try {
            // Check if batch table exists
            $batchTableExists = DB::connection('sqlsrv')->select("
                SELECT TABLE_NAME 
                FROM INFORMATION_SCHEMA.TABLES 
                WHERE TABLE_NAME = 'fileindexing_batch'
            ");

            if (empty($batchTableExists)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Batch management table not found. Please run migrations.'
                ]);
            }

            $stats = [
                'total_batches' => \App\Models\FileindexingBatch::count(),
                'active_batches' => \App\Models\FileindexingBatch::where('is_active', true)->count(),
                'full_batches' => \App\Models\FileindexingBatch::where('is_full', true)->count(),
                'available_batches' => \App\Models\FileindexingBatch::where('is_active', true)
                    ->where('is_full', false)->count(),
                'total_shelves' => \App\Models\FileindexingBatch::sum('shelf_count'),
                'used_shelves' => \App\Models\FileindexingBatch::sum('used_shelves'),
            ];

            $stats['available_shelves'] = $stats['total_shelves'] - $stats['used_shelves'];
            $stats['usage_percentage'] = $stats['total_shelves'] > 0
                ? round(($stats['used_shelves'] / $stats['total_shelves']) * 100, 2)
                : 0;

            // Get recent batches
            $recentBatches = \App\Models\FileindexingBatch::orderBy('created_at', 'desc')
                ->limit(10)
                ->get(['batch_number', 'shelf_count', 'used_shelves', 'is_full', 'is_active', 'created_at']);

            return response()->json([
                'success' => true,
                'stats' => $stats,
                'recent_batches' => $recentBatches
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load batch management data: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Auto-assign shelves to files using gap-filling logic
     */
    public function autoAssignShelves(Request $request)
    {
        try {
            $batchSize = 100; // Files per shelf label
            $processedFiles = 0;

            DB::connection('sqlsrv')->transaction(function () use (&$processedFiles, $batchSize) {
                // Get unassigned files
                $unassignedFiles = DB::connection('sqlsrv')->table('file_indexings')
                    ->whereNull('shelf_label_id')
                    ->orderBy('id')
                    ->get(['id']);

                if ($unassignedFiles->isEmpty()) {
                    return;
                }

                // Get unused shelf labels with proper ordering
                $unusedLabels = DB::connection('sqlsrv')->table('Rack_Shelf_Labels as rsl')
                    ->leftJoin('file_indexings as fi', 'fi.shelf_label_id', '=', 'rsl.id')
                    ->whereNull('fi.shelf_label_id')
                    ->where(function ($query) {
                        $query->where('rsl.is_used', 0)->orWhereNull('rsl.is_used');
                    })
                    ->orderBy('rsl.id')
                    ->get(['rsl.id', 'rsl.full_label']);

                if ($unusedLabels->isEmpty()) {
                    throw new \Exception('No unused shelf labels available');
                }

                // Assign shelf labels using gap-filling logic
                foreach ($unassignedFiles as $index => $file) {
                    // Calculate which shelf label to use (1 shelf per batch of files)
                    $shelfIndex = (int) floor($index / $batchSize);

                    if ($shelfIndex >= $unusedLabels->count()) {
                        break; // No more shelf labels available
                    }

                    $shelfLabel = $unusedLabels[$shelfIndex];

                    // Update the file indexing record
                    DB::connection('sqlsrv')->table('file_indexings')
                        ->where('id', $file->id)
                        ->update([
                            'shelf_label_id' => $shelfLabel->id,
                            'shelf_location' => $shelfLabel->full_label,
                            'updated_at' => now()
                        ]);

                    // Mark the shelf as used
                    DB::connection('sqlsrv')->table('Rack_Shelf_Labels')
                        ->where('id', $shelfLabel->id)
                        ->update(['is_used' => 1]);

                    $processedFiles++;
                }
            });

            return response()->json([
                'success' => true,
                'message' => "Successfully assigned shelves to {$processedFiles} files using gap-filling logic",
                'processed_files' => $processedFiles
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to auto-assign shelves: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show batch management page
     */
    public function batchManagement()
    {
        return view('fileindexing.batch-management');
    }

    /**
     * Run shelf location cleanup and batch update
     */
    public function runShelfCleanup(Request $request)
    {
        try {
            $dryRun = $request->get('dry_run', false);

            // Execute the cleanup command programmatically
            $exitCode = \Artisan::call('fileindexing:cleanup-shelf-batch', [
                '--dry-run' => $dryRun
            ]);

            $output = \Artisan::output();

            if ($exitCode === 0) {
                return response()->json([
                    'success' => true,
                    'message' => $dryRun ? 'Dry-run completed successfully' : 'Cleanup completed successfully',
                    'output' => $output,
                    'dry_run' => $dryRun
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Cleanup failed',
                    'output' => $output
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error running cleanup: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Sync related file links
     */
    /**
     * @param bool $allowClear Caller confirmed the submission explicitly carried a related-file
     *                         list. Only then does an empty list mean "remove them all"; without
     *                         it an empty list is treated as "not submitted" and links are kept.
     */
    protected function syncRelatedFileLinks($mainRecord, array $relatedFileNos, array $relatedDetails = [], bool $allowClear = false)
    {
        // If no related file data is provided, skip the sync to prevent accidental deletion
        // of existing links (especially since the form section might have been removed).
        // An edit that explicitly submitted an empty list is the one case where the operator
        // really did remove the last related file, and the links must go with it — otherwise
        // file_indexings.related_fileno is nulled while file_indexing_links keeps the row,
        // and the related file reappears from the links side.
        if (empty($relatedFileNos)) {
            if ($allowClear) {
                FileIndexingLink::on('sqlsrv')->where('file_indexing_id', $mainRecord->id)->delete();
            }

            return;
        }

        // Remove existing links to avoid duplicates
        FileIndexingLink::on('sqlsrv')->where('file_indexing_id', $mainRecord->id)->delete();

        foreach ($relatedFileNos as $index => $relatedFileNo) {
            // Find details for this file number if available
            $details = [];
            if (is_array($relatedDetails)) {
                // Try index-based match first for block indexing consistency
                if (isset($relatedDetails[$index])) {
                    $details = $relatedDetails[$index];
                } else {
                    foreach ($relatedDetails as $detail) {
                        if (isset($detail['file_number']) && $detail['file_number'] === $relatedFileNo) {
                            $details = $detail;
                            break;
                        }
                    }
                }
            }

            FileIndexingLink::on('sqlsrv')->create([
                'file_indexing_id' => $mainRecord->id,
                'file_number' => $relatedFileNo ?: $mainRecord->file_number,
                'indexing_type' => $mainRecord->indexing_type ?? 'Regular',
                'file_title' => $details['file_title'] ?? $details['holder'] ?? null,
                'plot_number' => $details['plot_number'] ?? null,
                'tp_no' => $details['tp_no'] ?? null,
                'lpkn_no' => $details['lpkn_no'] ?? null,
                'location' => $details['location'] ?? null,
                'entity_type' => $details['entity_type'] ?? null,
                'entity_name' => $details['entity_name'] ?? $details['holder'] ?? null,
                'entity_physical_address' => $details['entity_physical_address'] ?? null,
                'customer_name' => $details['customer_name'] ?? $details['holder'] ?? null,
                'land_use_type' => $details['land_use_type'] ?? null,
                'district' => $details['district'] ?? null,
                'lga' => $details['lga'] ?? null,
                'mfile' => (isset($details['mfile']) && $details['mfile'] !== '') ? (int) $details['mfile'] : null,

                'dob' => $details['dob'] ?? ($mainRecord->dob ?? null),
                'nin' => $details['nin'] ?? ($mainRecord->nin ?? null),
                'tin' => $details['tin'] ?? ($mainRecord->tin ?? null),
                'rc_no' => $details['rc_no'] ?? ($mainRecord->rc_no ?? null),
                'phone' => $details['phone'] ?? ($mainRecord->phone ?? null),
                'email' => $details['email'] ?? ($mainRecord->email ?? null),
                'residence_address' => $details['residence_address'] ?? ($mainRecord->residence_address ?? null),
                'country_code' => $details['country_code'] ?? ($mainRecord->country_code ?? null),

                'tracking_id' => $mainRecord->tracking_id,
                'created_by' => auth()->user()->userName ?? auth()->user()->name ?? 'System',
                'updated_by' => auth()->user()->userName ?? auth()->user()->name ?? 'System',
            ]);
        }

        Log::info('FileIndexing::syncRelatedFileLinks - synced', [
            'count' => count($relatedFileNos),
            'main_id' => $mainRecord->id
        ]);
    }

    /**
     * Is the given file number a DCIV/LPCC investigation file?
     */
    protected function isDcivFileNumber(?string $fileNo): bool
    {
        $f = strtoupper(trim((string) $fileNo));

        return $f !== '' && (str_starts_with($f, 'DCIV') || str_starts_with($f, 'LPCC'));
    }

    /**
     * Mirror DCIV relationships discovered at indexing time into master_dciv_links.
     *
     * master_dciv_links is directional (dciv_file_number -> related_file_number), so
     * we cover both ways a relationship can surface during file indexing:
     *   1. The indexed file is itself a DCIV/LPCC file — each related file becomes a link.
     *   2. The indexed file is an ordinary file whose related number points at a DCIV —
     *      the DCIV becomes the parent and the indexed file the related link.
     *
     * Idempotent (per dciv/related pair) and best-effort: failures are logged, not thrown,
     * so they never block the core indexing save.
     */
    protected function syncMasterDcivLinks($mainRecord, array $relatedFileNos, array $relatedDetails = [])
    {
        try {
            if (! Schema::connection('sqlsrv')->hasTable('master_dciv_links')) {
                return;
            }

            $mainNo = trim((string) ($mainRecord->file_number ?? ''));
            if ($mainNo === '') {
                return;
            }

            $userId = is_numeric(Auth::id()) ? (int) Auth::id() : null;
            $mainIsDciv = $this->isDcivFileNumber($mainNo);
            $mainReason = $mainRecord->dciv_reason ?? null;
            $mainTitle = $mainRecord->file_title ?? null;

            // Normalise related numbers: drop blanks and self-references, de-dupe.
            $validRelated = [];
            foreach ($relatedFileNos as $rNo) {
                $rNo = trim((string) $rNo);
                if ($rNo !== '' && strcasecmp($rNo, $mainNo) !== 0) {
                    $validRelated[$rNo] = $rNo;
                }
            }

            // A DCIV file belongs in the master table even with no related file —
            // record a standalone row (null related_file_number) so it isn't lost.
            if ($mainIsDciv && empty($validRelated)) {
                $this->upsertMasterDcivLink($mainNo, null, null, $mainReason, $userId);
                return;
            }

            if (empty($validRelated)) {
                return;
            }

            // Map related file number -> supplied title (from the details repeater).
            $titles = [];
            foreach ($relatedFileNos as $index => $rNo) {
                $rNo = trim((string) $rNo);
                if ($rNo === '') {
                    continue;
                }
                $detail = $relatedDetails[$index] ?? null;
                if (! is_array($detail)) {
                    foreach ($relatedDetails as $d) {
                        if (is_array($d) && trim((string) ($d['file_number'] ?? '')) === $rNo) {
                            $detail = $d;
                            break;
                        }
                    }
                }
                $titles[$rNo] = $detail['file_title'] ?? $detail['holder'] ?? null;
            }

            foreach ($validRelated as $relatedNo) {
                // Direction 1: the indexed file is the DCIV; related is its linked file.
                if ($mainIsDciv) {
                    $this->upsertMasterDcivLink($mainNo, $relatedNo, $titles[$relatedNo] ?? null, $mainReason, $userId);
                }

                // Direction 2: the related number is a DCIV; the indexed file is its link.
                if ($this->isDcivFileNumber($relatedNo)) {
                    $this->upsertMasterDcivLink($relatedNo, $mainNo, $mainTitle, $mainReason, $userId);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('FileIndexing::syncMasterDcivLinks failed', [
                'main_id' => $mainRecord->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Insert (or refresh) a single master_dciv_links row for a dciv/related pair.
     * Resolves the related-file title and the dciv_file_no metadata when available.
     */
    protected function upsertMasterDcivLink(string $dcivNo, ?string $relatedNo, ?string $relatedTitle, ?string $dcivReason, ?int $userId): void
    {
        $dcivNo = trim($dcivNo);
        $relatedNo = $relatedNo === null ? null : trim($relatedNo);
        if ($dcivNo === '') {
            return;
        }

        // A standalone DCIV (no related file) is stored once with a null related number.
        $isStandalone = ($relatedNo === null || $relatedNo === '');

        $conn = DB::connection('sqlsrv');

        // Resolve a related-file title from file_indexings when none was supplied.
        if (! $isStandalone && empty($relatedTitle)) {
            $relatedTitle = $conn->table('file_indexings')
                ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = ?', [strtoupper($relatedNo)])
                ->orderByDesc('id')
                ->value('file_title');
        }

        // Pull dciv_file_no metadata (id / reason / created_by) when a record exists.
        $dcivFileNo = $conn->table('dciv_file_no')
            ->whereRaw('UPPER(LTRIM(RTRIM(full_file_number))) = ?', [strtoupper($dcivNo)])
            ->where(fn ($q) => $q->where('is_deleted', 0)->orWhereNull('is_deleted'))
            ->orderByDesc('id')
            ->first();

        if ($isStandalone) {
            // Only create the placeholder when the DCIV has no rows at all — a real
            // link (now or later) supersedes it.
            $hasAny = $conn->table('master_dciv_links')
                ->whereRaw('UPPER(LTRIM(RTRIM(dciv_file_number))) = ?', [strtoupper($dcivNo)])
                ->exists();
            if (! $hasAny) {
                MasterDcivLink::create([
                    'dciv_file_no_id'     => $dcivFileNo->id ?? null,
                    'dciv_file_number'    => $dcivNo,
                    'dciv_reason'         => $dcivFileNo->dciv_reason ?? $dcivReason,
                    'related_file_number' => null,
                    'related_file_title'  => null,
                    'related_file_type'   => null,
                    'land_file_number'    => null,
                    'sltr_file_number'    => null,
                    'st_file_number'      => null,
                    'created_by'          => $dcivFileNo->created_by ?? $userId,
                ]);
            }
            return;
        }

        $existing = $conn->table('master_dciv_links')
            ->whereRaw('UPPER(LTRIM(RTRIM(dciv_file_number))) = ?', [strtoupper($dcivNo)])
            ->whereRaw('UPPER(LTRIM(RTRIM(related_file_number))) = ?', [strtoupper($relatedNo)])
            ->first();

        if ($existing) {
            // Keep the title fresh if we now have one and the row didn't.
            if (! empty($relatedTitle) && empty($existing->related_file_title)) {
                $conn->table('master_dciv_links')
                    ->where('id', $existing->id)
                    ->update([
                        'related_file_title' => $relatedTitle,
                        'updated_at' => now(),
                    ]);
            }
        } else {
            MasterDcivLink::create(array_merge([
                'dciv_file_no_id'     => $dcivFileNo->id ?? null,
                'dciv_file_number'    => $dcivNo,
                'dciv_reason'         => $dcivFileNo->dciv_reason ?? $dcivReason,
                'related_file_number' => $relatedNo,
                'related_file_title'  => $relatedTitle,
                'created_by'          => $dcivFileNo->created_by ?? $userId,
            ], MasterDcivLink::typeColumns($relatedNo)));
        }

        // A real link supersedes any leftover standalone placeholder for this DCIV.
        $conn->table('master_dciv_links')
            ->whereRaw('UPPER(LTRIM(RTRIM(dciv_file_number))) = ?', [strtoupper($dcivNo)])
            ->whereNull('related_file_number')
            ->delete();
    }

}
