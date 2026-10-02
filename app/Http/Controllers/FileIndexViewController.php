<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\TimelineWeightingService;

class FileIndexViewController extends Controller
{
    protected TimelineWeightingService $timelineService;

    public function __construct(TimelineWeightingService $timelineService)
    {
        $this->timelineService = $timelineService;
    }

    public function index()
    {
        return view('file_index_view.index', [
            'PageTitle' => 'File History View',
        ]);
    }

    public function data(Request $request)
    {
        $columns = [
            'prop_id',          // 0
            'fileno',           // 1
            'party_1',          // 2
            'party_2',          // 3
            'party_3',          // 4
            'transaction_type', // 5 (Instrument Type)
            'land_use',         // 6
            'regNo',            // 7 (Registration Particulars)
            'transaction_date', // 8
            'deeds_date',       // 9
            'deeds_time',       // 10
            'created_at',       // 11 (Date Created)
            'created_by',       // 12
            'id',               // 13
        ];

        $draw = (int) $request->input('draw', 1);
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 25);
        $searchValue = $request->input('search.value', '');
        $orderColumnIndex = (int) $request->input('order.0.column', 0);
        $orderDir = $request->input('order.0.dir', 'asc');
        $orderColumn = $columns[$orderColumnIndex] ?? 'created_at';

        $connection = DB::connection('sqlsrv');

        $urlContext = strtolower(trim((string) $request->input('url', '')));
        if (empty($urlContext) && $request->has('sltr')) {
            $urlContext = 'sltr';
        }

        $baseQuery = $this->buildFileHistoryBaseQuery($connection, $urlContext);

        $recordsTotal = (clone $baseQuery)->count();

        $filteredQuery = clone $baseQuery;

        if (!empty($searchValue)) {
            $filteredQuery->where(function ($query) use ($searchValue) {
                $query->orWhere('fileno', 'like', "%{$searchValue}%")
                    ->orWhere('mlsFNo', 'like', "%{$searchValue}%")
                    ->orWhere('kangisFileNo', 'like', "%{$searchValue}%")
                    ->orWhere('NewKANGISFileno', 'like', "%{$searchValue}%")
                    ->orWhere('party_1', 'like', "%{$searchValue}%")
                    ->orWhere('party_2', 'like', "%{$searchValue}%")
                    ->orWhere('party_3', 'like', "%{$searchValue}%")
                    ->orWhere('transaction_type', 'like', "%{$searchValue}%")
                    ->orWhere('regNo', 'like', "%{$searchValue}%")
                    ->orWhere('land_use', 'like', "%{$searchValue}%")
                    ->orWhere('instrument_type', 'like', "%{$searchValue}%")
                    ->orWhere('location', 'like', "%{$searchValue}%")
                    ->orWhere('created_by', 'like', "%{$searchValue}%");
            });
        }

        $recordsFiltered = (clone $filteredQuery)->count();

        $records = $filteredQuery
            ->orderBy($orderColumn, $orderDir)
            ->offset($start)
            ->limit($length)
            ->get([
                'prop_id',
                'id',
                'fileno',
                'mlsFNo',
                'kangisFileNo',
                'NewKANGISFileno',
                'party_1',
                'party_2',
                'party_3',
                'transaction_type',
                'location',
                'districtName',
                'plot_no',
                'lgsaOrCity',
                'tp_no',
                'lpkn_no',
                'regNo',
                'land_use',
                'transaction_date',
                'deeds_date',
                'deeds_time',
                'reg_date',
                'reg_time',
                'created_at',
                'created_by',
                'prop_total',
            ]);

            $timelineCounts = $this->resolveTimelineCountsForRecords($records);

        $self = $this;
        $userIds = $records->pluck('created_by')->filter(fn($v) => is_numeric($v))->unique()->toArray();
        $userMap = !empty($userIds)
            ? DB::connection('sqlsrv')->table('users')->whereIn('id', $userIds)->get(['id', 'first_name', 'last_name'])->keyBy('id')
            : collect();

        // Pre-compute which resolved file numbers are flagged as duplicates
        // (present in duplicate_fileno), so the UI can enable the Call-up action.
        $pageFileNumbers = $records
            ->map(fn($record) => trim((string) ($self->resolveFileNumber($record) ?? '')))
            ->filter()
            ->unique()
            ->values();
        $duplicateSet = $pageFileNumbers->isEmpty()
            ? collect()
            : DB::connection('sqlsrv')
                ->table('duplicate_fileno')
                ->whereIn('file_number', $pageFileNumbers)
                ->distinct()
                ->pluck('file_number')
                ->map(fn($v) => trim((string) $v))
                ->flip();

        $data = $records->map(function ($record) use ($self, $userMap, $timelineCounts, $duplicateSet) {
            $fileNumber = $self->resolveFileNumber($record) ?? 'N/A';
            $regParts = explode('/', $record->regNo ?? '');
            $serialNo = $regParts[0] ?? null;
            $pageNo = $regParts[1] ?? null;
            $volumeNo = $regParts[2] ?? null;

            $propIdKey = trim((string) ($record->prop_id ?? ''));
            $fallbackKey = '__id_' . (string) ($record->id ?? '0');
            $resolvedTimeline = (int) ($timelineCounts[$propIdKey !== '' ? $propIdKey : $fallbackKey] ?? 1);

            $transactionDateInfo = $self->formatDateValue($record->transaction_date);
            // Function to get the first non-empty value
            $deedsDateValue = $record->deeds_date ?: $record->reg_date;
            $deedsDateInfo = $self->formatDateValue($deedsDateValue);

            $deedsTimeValue = $record->deeds_time ?: $record->reg_time;

            $capturedDateInfo = $self->formatDateValue($record->created_at);
            $registrationParticulars = $self->formatRegistrationParticulars(
                $serialNo,
                $pageNo,
                $volumeNo
            );

            $createdBy = $record->created_by;
            if (is_numeric($createdBy) && isset($userMap[$createdBy])) {
                $u = $userMap[$createdBy];
                $createdBy = trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? ''));
            }

            return [

                'id' => $record->id,
                'prop_id' => $record->prop_id,
                'file_number' => $fileNumber,
                'party_1' => $record->party_1 ?? 'N/A',
                'party_2' => $record->party_2 ?? 'N/A',
                'party_3' => $record->party_3 ?? 'N/A',
                'instrument_type' => $record->transaction_type ?? 'N/A',
                'registration' => $record->regNo ?? 'N/A',
                'registration_particulars' => $registrationParticulars,
                'land_use' => $self->deriveLandUse($fileNumber, $record->land_use),
                'timeline_count' => max(1, $resolvedTimeline),
                'transaction_date' => $transactionDateInfo['display'] ?? 'N/A',
                'deeds_date' => $deedsDateInfo['display'] ?? 'N/A',
                'deeds_time' => $self->formatTimeValue($deedsTimeValue) ?? 'N/A',
                'date_created' => $capturedDateInfo['display'] ?? 'N/A',
                'created_by' => $createdBy ?: 'N/A',
                'lga' => $record->lgsaOrCity ?? 'N/A',
                'district' => $record->districtName ?? 'N/A',
                'plot_no' => $record->plot_no ?? 'N/A',
                'tp_no' => $record->tp_no ?? 'N/A',
                'lpkn_no' => $record->lpkn_no ?? 'N/A',
                'has_duplicate' => $duplicateSet->has(trim((string) $fileNumber)),
            ];
        });

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function timeline($recordId)
    {
        $connection = DB::connection('sqlsrv');

        $record = $connection->table('file_history_staging')->where('id', $recordId)->first();

        abort_unless((bool) $record, 404, 'File history record not found.');

        $matchColumns = collect([
            'fileno' => $record->fileno,
            'related_file_number' => $record->related_file_number,
            'kangisFileNo' => $record->kangisFileNo,
            'NewKANGISFileno' => $record->NewKANGISFileno,
        ])->filter(fn($value) => filled($value));

        $transactionsQuery = $connection->table('file_history_staging');

        if ($matchColumns->isEmpty()) {
            $transactionsQuery->where('id', $record->id);
        } else {
            $transactionsQuery->where(function ($query) use ($matchColumns, $record) {
                foreach ($matchColumns as $column => $value) {
                    $query->orWhere($column, $value);
                }
                $query->orWhere('id', $record->id);
            });
        }

        // The SQL sort is only a STABLE arrival order — the displayed order is the
        // Legal Search one, applied below. Date-ascending alone put a 1982 C of O
        // above the file's own commissioning line and buried the grant (OP/RofO)
        // among ordinary dealings, so the same file read differently here and in
        // Legal Search.
        $transactions = $transactionsQuery
            ->orderByRaw("TRY_CONVERT(DATE, transaction_date, 105) ASC")
            ->orderByRaw("TRY_CONVERT(DATE, reg_date, 105) ASC")
            ->orderBy('id')
            ->get();

        // Timeline Weighting Method (spec §3) — the same ordering Legal Search
        // uses, via the shared LegalSearchTimelineWeights map. See
        // App\Services\TimelineChronologyOrderer.
        $orderer = app(\App\Services\TimelineChronologyOrderer::class);
        $orderedRows = $orderer->sort(
            $transactions->map(fn($row) => (array) $row)->all()
        );
        $transactions = collect($orderedRows)->map(fn(array $row) => (object) $row);

        $self = $this;

        $transactionsData = $transactions->map(function ($row) use ($self) {
            // Party 2 is typically the "To" party (Grantee, Assignee, etc.)
            $currentHolder = $row->party_2 ?? $this->pickParty($row, [
                'current_holder',
            ]) ?? 'Unknown holder';

            // Party 1 is typically the "From" party (Grantor, Assignor, etc.)
            $originalHolder = $row->party_1 ?? $this->pickParty($row, [
                'original_holder',
            ]) ?? 'Unknown origin';

            $dateInfo = $this->formatDateValue($row->transaction_date ?? $row->reg_date ?? $row->created_at);
            $rowFileNumber = $self->resolveFileNumber($row);

            $regParts = explode('/', $row->regNo ?? '');
            $serialNo = $regParts[0] ?? 'N/A';
            $pageNo = $regParts[1] ?? 'N/A';
            $volNo = $regParts[2] ?? 'N/A';

            return [
                'id' => $row->id,
                'transactionType' => $row->transaction_type ?? 'Transaction',
                'owner' => $currentHolder,
                'originalHolder' => $originalHolder,
                'date' => $dateInfo['raw'],
                'date_display' => $dateInfo['display'],
                'date_year' => $dateInfo['year'],
                'serialNo' => $serialNo,
                'pageNo' => $pageNo,
                'volNo' => $volNo,
                'regNo' => $row->regNo ?? 'N/A',
                'description' => $row->comments ?? null,
                'propertyDetails' => [
                    'area' => $self->deriveLandUse($rowFileNumber, $row->land_use),
                    'location' => $row->location ?? 'N/A',
                ],
            ];
        })->values();

        $fileNumber = $this->resolveFileNumber($record) ?? 'Unknown File';

        $transactionsArray = $transactionsData->toArray();

        // Row 0 is no longer the earliest event and the last row is no longer the
        // latest — under the Legal Search ordering the top of the list is the
        // file's OPENING event (the grant / commissioning line), whatever its
        // date. So the date span and the holder fallbacks are taken from the
        // rows' timestamps rather than from their positions.
        $datedRows = [];
        foreach ($orderedRows as $position => $row) {
            $ts = $orderer->timestamp($row);
            if ($ts !== null) {
                $datedRows[$position] = $ts;
            }
        }
        asort($datedRows);
        $earliestPosition = array_key_first($datedRows);
        $latestPosition = array_key_last($datedRows);

        $firstTransaction = $earliestPosition !== null
            ? ($transactionsArray[$earliestPosition] ?? null)
            : ($transactionsArray[0] ?? null);
        $lastTransaction = $latestPosition !== null
            ? ($transactionsArray[$latestPosition] ?? null)
            : (!empty($transactionsArray) ? $transactionsArray[array_key_last($transactionsArray)] : null);

        // Root of Title / Original Holder / Current Holder — three distinct
        // concepts (client spec 2026-08-20 §12). The old rule here ("party_1 of
        // the first row is the Original Holder, party_2 of the last row is the
        // Current Holder") is wrong on every file where a dealing predates the
        // Ministry grant, and it could not express Root of Title at all.
        $holders = app(\App\Services\TitleHolderResolver::class)
            ->resolveForDisplay($fileNumber, $record->prop_id ?? null);

        $historyPayload = [
            'recordId' => $record->id,
            'fileNumber' => $fileNumber,
            'landuse' => $this->deriveLandUse($fileNumber, $record->land_use),
            'location' => $record->location ?? 'Not specified',
            'totalTransactions' => $transactionsData->count(),
            'applicationType' => $holders['application_type'],
            // The spec table's render list — which holder lines this Application
            // Type prints, and in what order.
            'titleLines' => $holders['lines'],
            'rootOfTitle' => $holders['root_of_title'],
            'originalOwner' => $holders['original_holder'] ?? ($firstTransaction['originalHolder'] ?? null),
            'currentOwner' => $holders['current_holder'] ?? ($lastTransaction['owner'] ?? null),
            // Chronological extremes, which the row ORDER no longer expresses.
            'dateSpan' => [
                'earliest' => $firstTransaction['date'] ?? null,
                'earliest_display' => $firstTransaction['date_display'] ?? null,
                'latest' => $lastTransaction['date'] ?? null,
                'latest_display' => $lastTransaction['date_display'] ?? null,
            ],
            'transactions' => $transactionsArray,
        ];

        return view('file_index_view.timeline', [
            'PageTitle' => 'File History & Transaction Timeline',
            'historyPayload' => $historyPayload,
            'record' => $record,
        ]);
    }

    /**
     * Resolve timeline counts for list rows using prop_id OR file number across all 4 timeline tables.
     */
    protected function resolveTimelineCountsForRecords($records)
    {
        $counts = [];

        foreach ($records as $record) {
            $propId = trim((string) ($record->prop_id ?? ''));
            $key = $propId !== '' ? $propId : ('__id_' . (string) ($record->id ?? '0'));

            $primaryFileNumber = $record->mlsFNo ?: ($record->fileno ?: ($record->kangisFileNo ?: ($record->NewKANGISFileno ?: null)));

            if ($propId === '' && !$primaryFileNumber) {
                $counts[$key] = 1;
                continue;
            }

            $rawRecords = $this->timelineService->getRawRecords($primaryFileNumber, $propId);
            $weightedCount = $this->timelineService->getWeightedCount($rawRecords);

            $counts[$key] = max(1, $weightedCount);
        }

        return collect($counts);
    }

    /**
     * Count timeline matches in a table by prop_id OR matching file number columns.
     */
    protected function countTimelineMatches(string $table, string $propId, array $fileNumbers, array $fileColumns, bool $excludeDeleted = false): int
    {
        try {
            $query = DB::connection('sqlsrv')->table($table);
            $validFileColumns = array_values(array_filter($fileColumns, function ($column) use ($table) {
                return Schema::connection('sqlsrv')->hasColumn($table, $column);
            }));

            $query->where(function ($q) use ($propId, $fileNumbers, $validFileColumns, $table) {
                if ($propId !== '' && Schema::connection('sqlsrv')->hasColumn($table, 'prop_id')) {
                    $q->orWhereRaw('CAST(prop_id AS NVARCHAR(50)) = ?', [$propId]);
                }

                foreach ($fileNumbers as $fileNo) {
                    foreach ($validFileColumns as $column) {
                        $q->orWhereRaw("UPPER({$column}) = ?", [$fileNo]);
                    }
                }
            });

            if ($excludeDeleted && Schema::connection('sqlsrv')->hasColumn($table, 'is_deleted')) {
                $query->where(function ($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                });
            }

            return (int) $query->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    protected function pickParty(object $record, array $fields): ?string
    {
        foreach ($fields as $field) {
            if (!empty($record->{$field})) {
                return $record->{$field};
            }
        }

        return null;
    }

    protected function formatDateValue(?string $value): array
    {
        if (empty($value)) {
            return [
                'raw' => null,
                'display' => null,
                'year' => null,
            ];
        }

        try {
            $carbon = Carbon::parse($value);
            return [
                'raw' => $carbon->toDateString(),
                'display' => $carbon->format('F j, Y'),
                'year' => $carbon->year,
            ];
        } catch (\Throwable $th) {
            return [
                'raw' => $value,
                'display' => $value,
                'year' => null,
            ];
        }
    }

    protected function formatTimeValue(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            $carbon = Carbon::parse($value);
            return $carbon->format('h:i A');
        } catch (\Throwable $th) {
            $trimmed = trim((string) $value);
            return $trimmed !== '' ? $trimmed : null;
        }
    }

    protected function formatRegistrationParticulars(?string $serialNo, ?string $pageNo, ?string $volumeNo): string
    {
        $serial = $serialNo ? trim((string) $serialNo) : null;
        $page = $pageNo ? trim((string) $pageNo) : null;
        $volume = $volumeNo ? trim((string) $volumeNo) : null;

        if (!$serial && !$page && !$volume) {
            return 'N/A';
        }

        return implode('/', [
            $serial ?: 'N/A',
            $page ?: 'N/A',
            $volume ?: 'N/A',
        ]);
    }

    protected function resolveFileNumber(object $record): ?string
    {
        foreach (['fileno', 'kangisFileNo', 'NewKANGISFileno', 'mlsFNo'] as $field) {
            if (!empty($record->{$field})) {
                $value = trim((string) $record->{$field});
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    protected function deriveLandUse(?string $fileNumber, ?string $existing = null): string
    {
        if ($fileNumber) {
            $normalized = strtoupper(str_replace(['_', '/', '\\'], '-', $fileNumber));
            $tokens = array_filter(preg_split('/[^A-Z]/', $normalized));
            $map = [
                'RES' => 'RESIDENTIAL',
                'COM' => 'COMMERCIAL',
                'IND' => 'INDUSTRIAL',
                'AG' => 'AGRICULTURAL',
            ];
            foreach ($tokens as $token) {
                if (isset($map[$token])) {
                    return $map[$token];
                }
            }
        }

        if (!empty($existing)) {
            return strtoupper($existing);
        }

        return 'N/A';
    }

    protected function buildFileHistoryBaseQuery($connection, ?string $urlContext = null)
    {
        $partitionExpression = "CASE WHEN prop_id IS NULL OR LTRIM(RTRIM(prop_id)) = '' THEN CAST(id AS NVARCHAR(50)) ELSE CAST(prop_id AS NVARCHAR(50)) END";

        // KANGIS context: only include rows whose mlsFNo / kangisFileNo / NewKANGISFileno
        // start with one of the KANGIS prefixes (KNML, MLKN, KNGP).
        $kangisPrefixes = ['KNML', 'MLKN', 'KNGP'];
        $applyKangisFilter = $urlContext === 'kangis';
        $applySltrFilter = $urlContext === 'sltr';
        $applyLandFilter = $urlContext === 'land';

        // Land files are identified by the land-use code in the file number.
        // Keep the complete mapping here (longest prefixes first) so conversion
        // and recertification files are included while KANGIS, SLTR, ST, TEMP,
        // and unrelated registry numbers stay out of the Land History view.
        $landFilePatterns = [
            'CON-RES-RC-[0-9][0-9][0-9][0-9]-%',
            'CON-COM-RC-[0-9][0-9][0-9][0-9]-%',
            'CON-IND-RC-[0-9][0-9][0-9][0-9]-%',
            'CON-AG-RC-[0-9][0-9][0-9][0-9]-%',
            'CON-RES-[0-9][0-9][0-9][0-9]-%',
            'CON-COM-[0-9][0-9][0-9][0-9]-%',
            'CON-IND-[0-9][0-9][0-9][0-9]-%',
            'CON-AG-[0-9][0-9][0-9][0-9]-%',
            'RES-RC-[0-9][0-9][0-9][0-9]-%',
            'COM-RC-[0-9][0-9][0-9][0-9]-%',
            'IND-RC-[0-9][0-9][0-9][0-9]-%',
            'AG-RC-[0-9][0-9][0-9][0-9]-%',
            'RES-[0-9][0-9][0-9][0-9]-%',
            'COM-[0-9][0-9][0-9][0-9]-%',
            'IND-[0-9][0-9][0-9][0-9]-%',
            'AG-[0-9][0-9][0-9][0-9]-%',
        ];

        return $connection->query()
            ->fromSub(function ($sub) use ($partitionExpression, $applyKangisFilter, $kangisPrefixes, $applySltrFilter, $applyLandFilter, $landFilePatterns) {
                $sub->from('file_history_staging')
                    ->select('*')
                    ->selectRaw("ROW_NUMBER() OVER (PARTITION BY {$partitionExpression} ORDER BY id DESC) as prop_rank")
                    ->selectRaw("COUNT(*) OVER (PARTITION BY {$partitionExpression}) as prop_total");

                if ($applyKangisFilter) {
                    $sub->where(function ($q) use ($kangisPrefixes) {
                        foreach (['mlsFNo', 'kangisFileNo', 'NewKANGISFileno'] as $col) {
                            foreach ($kangisPrefixes as $prefix) {
                                $q->orWhere($col, 'like', $prefix . '%');
                            }
                        }
                    });
                } elseif ($applySltrFilter) {
                    $sub->where('mlsFNo', 'like', 'sltr%');
                } elseif ($applyLandFilter) {
                    $sub->where(function ($q) use ($landFilePatterns) {
                        foreach (['mlsFNo', 'fileno', 'kangisFileNo', 'NewKANGISFileno'] as $column) {
                            foreach ($landFilePatterns as $pattern) {
                                $q->orWhereRaw("UPPER(LTRIM(RTRIM({$column}))) LIKE ?", [$pattern]);
                            }
                        }
                    });
                }
            }, 'ranked_files')
            ->where('prop_rank', 1);
    }
}
