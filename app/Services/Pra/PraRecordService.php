<?php

namespace App\Services\Pra;

use App\Exceptions\PropIdAllocationException;
use App\Services\Pra\Repositories\PraHistoryRepository;
use App\Services\Pra\Repositories\PraRecordRepository;
use App\Services\PropertyIdAllocationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PraRecordService
{
    private PraRecordRepository $records;
    private PraHistoryRepository $history;
    private PropertyIdAllocationService $propertyIdAllocation;

    public function __construct(
        PraRecordRepository $records,
        PraHistoryRepository $history,
        PropertyIdAllocationService $propertyIdAllocation
    ) {
        $this->records = $records;
        $this->history = $history;
        $this->propertyIdAllocation = $propertyIdAllocation;
    }

    /**
     * Search PRA records using multi-criteria filters.
     */
    public function search(array $filters, int $perPage = 25, int $page = 1): LengthAwarePaginator
    {
        $perPage = max(1, min($perPage, 100));
        $page = max(1, $page);

        return $this->records->search($filters, $perPage, $page);
    }

    /**
     * Retrieve a canonical PRA record by prop_id.
     */
    public function getByPropId(string $propId): ?array
    {
        if ($propId === '') {
            return null;
        }

        $record = $this->records->findByPropId($propId);

        if ($record === null) {
            return null;
        }

        return $this->records->appendMasterIdentifiers($record);
    }

    /**
     * Lookup a PRA record by any known file number.
     */
    public function getByFileNumber(string $fileNumber): ?array
    {
        if ($fileNumber === '') {
            return null;
        }

        $cacheKey = sprintf('pra:lookup:%s', strtoupper(trim($fileNumber)));

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($fileNumber) {
            $record = $this->records->findByFileNumber($fileNumber);

            if ($record === null) {
                return null;
            }

            return $this->records->appendMasterIdentifiers($record);
        });
    }

    /**
     * Retrieve all PRA records by any known file number.
     */
    public function findAllByFileNumber(string $fileNumber): array
    {
        if ($fileNumber === '') {
            return [];
        }

        $records = $this->records->findAllByFileNumber($fileNumber);

        return $records->map(function ($record) {
            return $this->records->appendMasterIdentifiers($record);
        })->toArray();
    }

    /**
     * Create a new PRA record and synchronize PropID metadata.
     */
    public function createRecord(array $input, ?int $userId = null): array
    {
        $identifierSet = $this->gatherIdentifierSet($input);
        $propId = $this->allocatePropId($identifierSet);

        if ($propId !== null) {
            $identifierSet['prop_id'] = is_numeric($propId) ? (int) $propId : $propId;
        }

        $payload = $this->prepareRecordPayload($input, $identifierSet, $userId, true);

        $record = $this->records->create($payload) ?? [];

        if (empty($record) && isset($identifierSet['prop_id'])) {
            $record = $this->records->findByPropId((string) $identifierSet['prop_id']) ?? $record;
        }

        if ($propId !== null) {
            $this->syncPropIdTimeline($identifierSet, $propId);
        }

        $this->forgetLookupCache($this->extractLookupCandidates($identifierSet));
        $this->markTempFilenoAsUsed($identifierSet['temp_fileno'] ?? ($payload['temp_fileno'] ?? null));

        if (empty($record)) {
            return $payload;
        }

        return $this->records->appendMasterIdentifiers($record);
    }

    /**
     * Update an existing PRA record by prop_id.
     */
    public function updateRecord(string $propId, array $input, ?int $userId = null): array
    {
        if ($propId === '') {
            throw ValidationException::withMessages([
                'prop_id' => ['prop_id is required'],
            ]);
        }

        $existing = $this->records->findByPropId($propId);

        if ($existing === null) {
            throw ValidationException::withMessages([
                'prop_id' => ['Record not found'],
            ]);
        }

        $identifierSet = $this->gatherIdentifierSet($input, $existing);
        $identifierSet['prop_id'] = $existing['prop_id'] ?? $propId;

        $allocatedPropId = $this->allocatePropId($identifierSet, $existing);
        if ($allocatedPropId !== null) {
            $identifierSet['prop_id'] = $allocatedPropId;
        }

        if (isset($identifierSet['prop_id']) && $identifierSet['prop_id'] !== null && is_numeric($identifierSet['prop_id'])) {
            $identifierSet['prop_id'] = (int) $identifierSet['prop_id'];
        }

        $payload = $this->prepareRecordPayload($input, $identifierSet, $userId, false);

        // A row can BECOME a Transfer of Title through the update itself, so the type
        // must be judged on the merged post-update state. Testing $existing alone let a
        // conversion escape both the 0/0/0 zeroing and the Party 1 guard below, which is
        // how ToT rows with a NULL regNo and a NULL Grantor reached production.
        $merged = array_merge($existing, $payload);

        if ($this->isOssTransferOfTitleOp($merged)) {
            $payload['regNo'] = '0/0/0';
            $payload['serialNo'] = '0';
            $payload['pageNo'] = '0';
            $payload['volumeNo'] = '0';

            // Enforced when this update is what turns the row into a Transfer of Title,
            // or when it writes the party columns at all. A partial update to an already
            // broken legacy row is left editable on purpose — those are repaired by
            // oss:fix-op-transfer-grantor, and blocking them here would only strand them.
            $becomesTot = ! $this->isOssTransferOfTitleOp($existing);
            $touchesParties = array_key_exists('party_1', $payload) || array_key_exists('Grantor', $payload);

            if ($becomesTot || $touchesParties) {
                $this->assertTransferOfTitleHasPartyOne($merged);
            }
        }

        $record = $this->records->updateByPropId((string) ($existing['prop_id'] ?? $propId), $payload) ?? $existing;

        // When update_siblings is requested, update shared property-detail
        // fields on ALL rows with the same prop_id (e.g. OP + Transfer of Title).
        $updateSiblings = filter_var($input['update_siblings'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($updateSiblings) {
            $resolvedPropId = (string) ($identifierSet['prop_id'] ?? $existing['prop_id'] ?? $propId);
            $this->records->updatePropertyDetailsByPropId($resolvedPropId, $payload);
        }

        $this->syncPropIdTimeline($identifierSet, $identifierSet['prop_id'] ?? null);

        $this->forgetLookupCache(array_merge(
            $this->extractLookupCandidates($existing),
            $this->extractLookupCandidates($identifierSet)
        ));

        $this->markTempFilenoAsUsed($identifierSet['temp_fileno'] ?? ($payload['temp_fileno'] ?? null));

        return $this->records->appendMasterIdentifiers($record);
    }

    private function markTempFilenoAsUsed($tempFileno): void
    {
        $value = $this->normalizeNullableString($tempFileno);
        if ($value === null) {
            return;
        }

        $upper = strtoupper($value);
        if (strpos($upper, 'TEMP-') !== 0) {
            return;
        }

        $sequenceId = (int) str_replace('TEMP-', '', $upper);
        if ($sequenceId <= 0) {
            return;
        }

        try {
            DB::connection('sqlsrv')->table('temp_fileno_sequence')
                ->where('id', $sequenceId)
                ->update([
                    'is_used' => 1,
                    'updated_at' => now(),
                ]);
        } catch (\Throwable $exception) {
            Log::warning('PRA temp fileno sequence update failed', [
                'temp_fileno' => $value,
                'sequence_id' => $sequenceId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Get chronological transaction history for a prop_id.
     */
    public function getHistory(string $propId): array
    {
        if ($propId === '') {
            return [];
        }

        return $this->history->timelineForPropId($propId);
    }

    /**
     * Identify potential duplicates for a prop_id.
     */
    public function getDuplicates(string $propId): array
    {
        if ($propId === '') {
            return [];
        }

        try {
            return $this->records->findDuplicates($propId);
        } catch (\Throwable $exception) {
            Log::warning('PraRecordService duplicate lookup failed', [
                'prop_id' => $propId,
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    private function gatherIdentifierSet(array $input, ?array $existing = null): array
    {
        $keys = [
            'mlsFNo',
            'kangisFileNo',
            'NewKANGISFileno',
            'temp_fileno',
            'fileno',
            'np_fileno',
        ];

        $identifiers = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $input)) {
                $identifiers[$key] = $this->normalizeNullableString($input[$key]);
            } elseif ($existing !== null && array_key_exists($key, $existing)) {
                $identifiers[$key] = $this->normalizeNullableString($existing[$key]);
            } else {
                $identifiers[$key] = null;
            }
        }

        if (array_key_exists('prop_id', $input)) {
            $identifiers['prop_id'] = $input['prop_id'];
        }

        if ($existing !== null && isset($existing['prop_id'])) {
            $identifiers['prop_id'] = $existing['prop_id'];
        }

        // Caller opt-in: Transfer of Title rows pass force_fresh_prop_id=true
        // so a brand-new prop_id is minted instead of reusing the OP's.
        if (array_key_exists('force_fresh_prop_id', $input)) {
            $identifiers['force_fresh_prop_id'] = filter_var(
                $input['force_fresh_prop_id'],
                FILTER_VALIDATE_BOOLEAN
            );
        }

        $identifiers['primary'] = $this->resolvePrimaryIdentifier(
            $identifiers['fileno'] ?? null,
            $identifiers['np_fileno'] ?? null,
            $identifiers['mlsFNo'] ?? null,
            $identifiers['kangisFileNo'] ?? null,
            $identifiers['NewKANGISFileno'] ?? null,
            $identifiers['temp_fileno'] ?? null
        );

        return $identifiers;
    }

    private function allocatePropId(array $identifierSet, ?array $existing = null): ?int
    {
        // If caller already supplied a valid prop_id (for example from source OP
        // linkage), preserve it to keep cross-table inserts consistent.
        $explicitPropId = $identifierSet['prop_id'] ?? ($existing['prop_id'] ?? null);
        if ($explicitPropId !== null && $explicitPropId !== '' && is_numeric($explicitPropId)) {
            $explicitPropId = (int) $explicitPropId;
            if ($explicitPropId > 0) {
                return $explicitPropId;
            }
        }

        // When the caller explicitly asks for a fresh prop_id (Transfer of Title
        // rows that must NOT inherit the OP's prop_id), bypass the lookup-by-
        // fileno path so we always mint a brand-new identifier.
        $forceFresh = (bool) ($identifierSet['force_fresh_prop_id'] ?? false);

        $officials = array_filter([
            $identifierSet['mlsFNo'] ?? null,
            $identifierSet['kangisFileNo'] ?? null,
            $identifierSet['NewKANGISFileno'] ?? null,
            $identifierSet['fileno'] ?? null,
            $identifierSet['np_fileno'] ?? null,
            $identifierSet['temp_fileno'] ?? null,
        ]);

        if (empty($officials)) {
            return $identifierSet['prop_id'] ?? ($existing['prop_id'] ?? null);
        }

        $primary = $this->resolvePrimaryIdentifier(
            $identifierSet['primary'] ?? null,
            $identifierSet['mlsFNo'] ?? null,
            $identifierSet['kangisFileNo'] ?? null,
            $identifierSet['NewKANGISFileno'] ?? null,
            $identifierSet['fileno'] ?? null,
            $identifierSet['np_fileno'] ?? null,
            $identifierSet['temp_fileno'] ?? null
        );

        try {
            return $this->propertyIdAllocation->allocateOrRetrievePropId(
                $primary,
                $identifierSet['mlsFNo'] ?? null,
                $identifierSet['kangisFileNo'] ?? null,
                $identifierSet['NewKANGISFileno'] ?? null,
                [
                    'temp_fileno' => $identifierSet['temp_fileno'] ?? null,
                    // Capture Existing OP may start from temp-file-only details
                    // before a commissioned file number exists.
                    'allow_temp_only' => true,
                    'skip_lookup' => $forceFresh,
                ]
            );
        } catch (PropIdAllocationException $exception) {
            throw ValidationException::withMessages([
                'mlsFNo' => [$exception->getMessage()],
            ]);
        }
    }

    private function prepareRecordPayload(array $input, array $identifierSet, ?int $userId, bool $isCreate): array
    {
        $payload = [];

        if (isset($identifierSet['prop_id']) && $identifierSet['prop_id'] !== null) {
            $payload['prop_id'] = is_numeric($identifierSet['prop_id'])
                ? (int) $identifierSet['prop_id']
                : $identifierSet['prop_id'];
        }

        foreach (['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'temp_fileno', 'fileno', 'np_fileno'] as $key) {
            if ($isCreate) {
                $payload[$key] = $identifierSet[$key] ?? null;
                continue;
            }

            if (array_key_exists($key, $input)) {
                $payload[$key] = $identifierSet[$key] ?? null;
            }
        }

        $fields = [
            'transaction_type',
            'related_file_number',
            'transaction_date',
            'reg_date',
            'reg_time',
            'regNo',
            'serialNo',
            'pageNo',
            'volumeNo',
            'op_serial_number',
            'consideration_amount',
            'receipt_no',
            'op_type',
            'purpose',
            'land_use',
            'plot_no',
            'tp_no',
            'lpkn_no',
            'plot_size',
            'house_no',
            'lgsaOrCity',
            'location',
            'property_description',
            'source',
            'instrument_type',
            'system_source',
            'comments',
            'remarks',
            'deeds_date',
            'deeds_time',
            'rofo_number',
        ];

        foreach ($fields as $field) {
            if (array_key_exists($field, $input)) {
                $payload[$field] = $this->normalizeNullableString($input[$field]);
            }
        }

        if ($userId !== null) {
            if ($isCreate) {
                $payload['created_by'] = $userId;
            }

            $payload['updated_by'] = $userId;
        }

        $payload = array_merge($payload, $this->buildPartyAssignments($input));

        if (array_key_exists('merger_group_id', $input)) {
            $payload['merger_group_id'] = $this->normalizeNullableString($input['merger_group_id']);
        }
        if (array_key_exists('is_merger_op', $input)) {
            $payload['is_merger_op'] = isset($input['is_merger_op']) ? (int) filter_var($input['is_merger_op'], FILTER_VALIDATE_BOOLEAN) : null;
        }
        if (array_key_exists('is_subdivided', $input)) {
            $payload['is_subdivided'] = isset($input['is_subdivided']) ? (int) filter_var($input['is_subdivided'], FILTER_VALIDATE_BOOLEAN) : null;
        }
        if (array_key_exists('op_count', $input)) {
            $payload['op_count'] = isset($input['op_count']) ? (int) $input['op_count'] : null;
        }

        // OP→ToT lineage. These columns let writers record the exact source OP
        // row by primary key (not by reused prop_id), so duplicate prop_id
        // siblings cannot cause OP↔ToT mismatches downstream.
        if (array_key_exists('source_op_table', $input)) {
            $payload['source_op_table'] = $this->normalizeNullableString($input['source_op_table']);
        }
        if (array_key_exists('source_op_id', $input)) {
            $sourceOpId = $input['source_op_id'];
            $payload['source_op_id'] = ($sourceOpId !== null && $sourceOpId !== '' && is_numeric($sourceOpId))
                ? (int) $sourceOpId
                : null;
        }
        if (array_key_exists('parent_prop_id', $input)) {
            $payload['parent_prop_id'] = $this->normalizeNullableString($input['parent_prop_id']);
        }

        // OSS Change-of-Name transfer rows must never inherit registrar details
        // from the source OP; they are always represented as 0/0/0 in PRA.
        if ($isCreate && $this->isOssTransferOfTitleOp($payload)) {
            $payload['regNo'] = '0/0/0';
            $payload['serialNo'] = '0';
            $payload['pageNo'] = '0';
            $payload['volumeNo'] = '0';

            $this->assertTransferOfTitleHasPartyOne($payload);
        }

        return $payload;
    }

    /**
     * A Transfer of Title (OP) records a change of holder, so its Party 1 is the
     * previous holder — the one fact the row exists to capture. Several callers have
     * historically written it blank (an empty-string Grantee slipping through a ??
     * fallback, a missing mother-OP lookup), producing rows that render "—" as the
     * original holder and read as an ownerless dealing in Legal Search.
     *
     * Guarding here rather than in each caller because every creation path — FFR
     * capture, Match OP, batch commissioning — funnels through createRecord(), and
     * new callers get the check for free.
     *
     * Fails loudly instead of defaulting: there is no safe value to invent for a
     * prior owner, and a wrong name is worse than a rejected write.
     */
    private function assertTransferOfTitleHasPartyOne(array $payload): void
    {
        $partyOne = trim((string) ($payload['party_1'] ?? $payload['Grantor'] ?? ''));

        if ($partyOne !== '') {
            return;
        }

        Log::error('PRA create blocked: Transfer of Title (OP) has no Party 1 / Grantor', [
            'mlsFNo' => $payload['mlsFNo'] ?? null,
            'fileno' => $payload['fileno'] ?? null,
            'temp_fileno' => $payload['temp_fileno'] ?? null,
            'prop_id' => $payload['prop_id'] ?? null,
            'parent_prop_id' => $payload['parent_prop_id'] ?? null,
            'source' => $payload['source'] ?? null,
            'party_2' => $payload['party_2'] ?? $payload['Grantee'] ?? null,
        ]);

        throw ValidationException::withMessages([
            'party_1' => [
                'A Transfer of Title (OP) requires Party 1 (the previous holder). '
                . 'It could not be resolved from the source Occupancy Permit — set it explicitly and retry.',
            ],
        ]);
    }

    private function isOssTransferOfTitleOp(array $payload): bool
    {
        $systemSource = strtoupper((string) ($payload['system_source'] ?? ''));
        if ($systemSource !== 'OSSOPCHANGEOFNAME') {
            return false;
        }

        $transactionType = strtolower((string) ($payload['transaction_type'] ?? ''));
        $instrumentType = strtolower((string) ($payload['instrument_type'] ?? ''));
        $typeText = trim($transactionType . ' ' . $instrumentType);

        return str_contains($typeText, 'transfer of title') && str_contains($typeText, '(op)');
    }

    private function buildPartyAssignments(array $input): array
    {
        $parties = $input['parties'] ?? [];
        if (!is_array($parties)) {
            $parties = [];
        }

        $result = [];
        $map = [
            'assignor' => 'Assignor',
            'assignee' => 'Assignee',
            'mortgagor' => 'Mortgagor',
            'mortgagee' => 'Mortgagee',
            'grantor' => 'Grantor',
            'grantee' => 'Grantee',
            'lessor' => 'Lessor',
            'lessee' => 'Lessee',
            'surrenderor' => 'Surrenderor',
            'surrenderee' => 'Surrenderee',
        ];

        foreach ($map as $key => $column) {
            $value = null;

            if (array_key_exists($column, $input)) {
                $value = $input[$column];
            } elseif (array_key_exists($key, $parties)) {
                $value = $parties[$key];
            } elseif (array_key_exists($column, $parties)) {
                $value = $parties[$column];
            }

            if (array_key_exists($column, $input) || array_key_exists($key, $parties) || array_key_exists($column, $parties) || $value !== null) {
                $result[$column] = $this->normalizeNullableString($value);
            }
        }

        // Keep generic party columns aligned for table views that read party_1/party_2.
        $resolvedGrantor = $result['Grantor'] ?? $this->normalizeNullableString($parties['grantor'] ?? null);
        $resolvedGrantee = $result['Grantee'] ?? $this->normalizeNullableString($parties['grantee'] ?? null);

        if ($resolvedGrantor !== null) {
            $result['party_1'] = $resolvedGrantor;
        }

        if ($resolvedGrantee !== null) {
            $result['party_2'] = $resolvedGrantee;
        }

        return $result;
    }

    private function syncPropIdTimeline(array $identifierSet, $propId): void
    {
        if ($propId === null) {
            return;
        }

        $propIdInt = (int) $propId;
        if ($propIdInt <= 0) {
            return;
        }

        $primary = $this->resolvePrimaryIdentifier(
            $identifierSet['primary'] ?? null,
            $identifierSet['mlsFNo'] ?? null,
            $identifierSet['kangisFileNo'] ?? null,
            $identifierSet['NewKANGISFileno'] ?? null,
            $identifierSet['fileno'] ?? null
        );

        if ($primary === null) {
            return;
        }

        $this->propertyIdAllocation->syncPropIdToFileHistory($primary, $propIdInt);
    }

    private function extractLookupCandidates(array $source): array
    {
        return [
            $source['mlsFNo'] ?? null,
            $source['kangisFileNo'] ?? null,
            $source['NewKANGISFileno'] ?? null,
            $source['fileno'] ?? null,
            $source['np_fileno'] ?? null,
            $source['temp_fileno'] ?? null,
        ];
    }

    private function forgetLookupCache(array $values): void
    {
        collect($values)
            ->map(fn($value) => $this->normalizeNullableString($value))
            ->filter()
            ->map(fn($value) => strtoupper($value))
            ->unique()
            ->each(function ($token) {
                Cache::forget(sprintf('pra:lookup:%s', $token));
            });
    }

    private function resolvePrimaryIdentifier(?string ...$candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if ($candidate !== null && $candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    private function normalizeNullableString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
        } else {
            $value = trim((string) $value);
        }

        return $value === '' ? null : $value;
    }

    /**
     * Find OP records that EXACTLY match all supplied identifying fields across
     * PRA, instrument_capture, and deed_registrations.
     *
     * Used by the disambiguation prompt in the OP capture modal: the user
     * fills in OP details (op_serial_number + serial_no + page_no + volume_no +
     * party_2_name) and the frontend asks "is this the same OP?" before
     * committing to a fresh capture. Returns rows where every supplied key
     * field matches exactly (case-insensitive, whitespace-trimmed). DR has no
     * op_serial_number column — DR rows match only if the caller did not
     * supply op_serial_number.
     *
     * Column names differ per table:
     *   IC : party_2_name, plot_number, op_serial_number, serial_no, page_no, volume_no, registration_number
     *   DR : grantee,      plot_number,        (no OPSN), serial_no, page_no, volume_no, registration_number
     *   PRA: party_2,      plot_no,     op_serial_number,  serialNo,  pageNo,  volumeNo, regNo
     *
     * Returns at most $limit rows. Expected callers gate the trigger on all
     * five key fields being supplied, so 0 or 1 result is typical.
     */
    public function findCandidateOpsByFields(array $fields, int $limit = 5): array
    {
        $norm = fn ($v) => strtoupper(trim((string) ($v ?? '')));

        // Canonical "logical" keys the caller passes in.
        $supplied = array_filter([
            'op_serial_number'    => $norm($fields['op_serial_number'] ?? null),
            'serial_no'           => $norm($fields['serial_no']        ?? null),
            'page_no'             => $norm($fields['page_no']          ?? null),
            'volume_no'           => $norm($fields['volume_no']        ?? null),
            'plot_no'             => $norm($fields['plot_no']          ?? null),
            'tp_no'               => $norm($fields['tp_no']            ?? null),
            'party_2_name'        => $norm($fields['party_2_name']     ?? $fields['party_2'] ?? null),
            'registration_number' => $norm($fields['registration_number'] ?? $fields['regNo'] ?? null),
        ], fn ($v) => $v !== '');

        if (empty($supplied)) {
            return [];
        }

        $conn = DB::connection('sqlsrv');
        $candidates = [];

        // Map logical key → actual column per table. null entry means the
        // table has no equivalent column, so any caller-supplied value for
        // that logical key makes the table unmatchable (we skip that table).
        $columnMaps = [
            'instrument_capture' => [
                'op_serial_number'    => 'op_serial_number',
                'serial_no'           => 'serial_no',
                'page_no'             => 'page_no',
                'volume_no'           => 'volume_no',
                'plot_no'             => 'plot_number',
                'tp_no'               => null,
                'party_2_name'        => 'party_2_name',
                'registration_number' => 'registration_number',
            ],
            'deed_registrations' => [
                'op_serial_number'    => null,
                'serial_no'           => 'serial_no',
                'page_no'             => 'page_no',
                'volume_no'           => 'volume_no',
                'plot_no'             => 'plot_number',
                'tp_no'               => null,
                'party_2_name'        => 'grantee',
                'registration_number' => 'registration_number',
            ],
            'pra' => [
                'op_serial_number'    => 'op_serial_number',
                'serial_no'           => 'serialNo',
                'page_no'             => 'pageNo',
                'volume_no'           => 'volumeNo',
                'plot_no'             => 'plot_no',
                'tp_no'               => 'tp_no',
                'party_2_name'        => 'party_2',
                'registration_number' => 'regNo',
            ],
        ];

        $instrumentTypeFilters = [
            'instrument_capture'  => "instrument_type = 'Occupancy Permit (OP)'",
            'deed_registrations'  => "instrument_type = 'Occupancy Permit (OP)'",
            'pra'                 => "(UPPER(ISNULL(instrument_type,'')) LIKE '%OCCUPANCY PERMIT%' OR UPPER(ISNULL(transaction_type,'')) LIKE '%OCCUPANCY PERMIT%')",
        ];

        $softDeleteFilters = [
            'instrument_capture' => '(is_deleted IS NULL OR is_deleted = 0)',
            'deed_registrations' => null,
            'pra'                => null,
        ];

        foreach ($columnMaps as $table => $cols) {
            // Skip table if any supplied key has no column equivalent here.
            // (e.g. op_serial_number supplied but DR has no such column → skip DR.)
            $skip = false;
            foreach ($supplied as $key => $_) {
                if (!array_key_exists($key, $cols) || $cols[$key] === null) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) continue;

            $query = $conn->table($table)->whereRaw($instrumentTypeFilters[$table]);
            if ($softDeleteFilters[$table]) {
                $query->whereRaw($softDeleteFilters[$table]);
            }

            // EXACT MATCH on every supplied field — AND, not OR.
            foreach ($supplied as $key => $val) {
                $col = $cols[$key];
                $query->whereRaw(
                    "UPPER(LTRIM(RTRIM(ISNULL(CAST($col AS NVARCHAR(200)), '')))) = ?",
                    [$val]
                );
            }

            try {
                $rows = $query->limit($limit)->get();
            } catch (\Throwable $e) {
                Log::warning('findCandidateOpsByFields: query failed', [
                    'table' => $table,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            foreach ($rows as $row) {
                $arr = (array) $row;
                $party2 = $arr['party_2_name'] ?? $arr['party_2'] ?? $arr['grantee'] ?? null;
                $plot   = $arr['plot_number']  ?? $arr['plot_no'] ?? null;
                $serial = $arr['serial_no']    ?? $arr['serialNo'] ?? null;
                $page   = $arr['page_no']      ?? $arr['pageNo']   ?? null;
                $vol    = $arr['volume_no']    ?? $arr['volumeNo'] ?? null;
                $reg    = $arr['registration_number'] ?? $arr['regNo'] ?? null;

                $candidates[] = [
                    'source_table'        => $table,
                    'id'                  => $arr['id'] ?? null,
                    'prop_id'             => $arr['prop_id'] ?? null,
                    'op_serial_number'    => $arr['op_serial_number'] ?? null,
                    'op_type'             => $arr['op_type'] ?? null,
                    'serial_no'           => $serial,
                    'page_no'             => $page,
                    'volume_no'           => $vol,
                    'registration_number' => $reg,
                    'plot_no'             => $plot,
                    'plot_number'         => $plot,
                    'tp_no'               => $arr['tp_no'] ?? null,
                    'party_1'             => $arr['party_1'] ?? $arr['party_1_name'] ?? $arr['grantor'] ?? null,
                    'party_2'             => $party2,
                    'party_2_name'        => $party2,
                    'land_use'            => $arr['land_use'] ?? null,
                    'property_description' => $arr['property_description'] ?? $arr['location'] ?? null,
                    'property_location'   => $arr['location'] ?? $arr['property_location'] ?? null,
                    'temp_fileno'         => $arr['temp_fileno'] ?? null,
                    'fileno'              => $arr['fileno'] ?? null,
                    'mlsFNo'              => $arr['mlsFNo'] ?? null,
                    'transaction_date'    => $arr['transaction_date'] ?? null,
                    'reg_date'            => $arr['reg_date'] ?? $arr['deeds_date'] ?? null,
                    'reg_time'            => $arr['reg_time'] ?? $arr['deeds_time'] ?? null,
                    'created_at'          => $arr['created_at'] ?? null,
                ];
            }
        }

        // Prefer rows with a prop_id (linkable), then most recent.
        usort($candidates, function ($a, $b) {
            $aHasProp = !empty($a['prop_id']) ? 1 : 0;
            $bHasProp = !empty($b['prop_id']) ? 1 : 0;
            if ($aHasProp !== $bHasProp) {
                return $bHasProp - $aHasProp;
            }
            return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
        });

        return array_slice($candidates, 0, $limit);
    }

    /**
     * Check if an Occupancy Permit already exists in instrument_capture or
     * deed_registrations.  Returns the source record (with a `_source_table`
     * key) when found, or null otherwise.
     *
     * Lookup priority:
     *   1. instrument_capture by op_serial_number
     *   2. instrument_capture by file number columns
     *   3. deed_registrations by file number
     */
    public function findExistingOpInSource(array $identifiers): ?array
    {
        $conn = DB::connection('sqlsrv');

        $opSerial = $this->normalizeNullableString($identifiers['op_serial_number'] ?? null);
        $mlsFNo   = $this->normalizeNullableString($identifiers['mlsFNo'] ?? null);
        $fileno   = $this->normalizeNullableString($identifiers['fileno'] ?? null);
        $kangis   = $this->normalizeNullableString($identifiers['kangisFileNo'] ?? null);
        $newKangis = $this->normalizeNullableString($identifiers['NewKANGISFileno'] ?? null);
        $tempFileno = $this->normalizeNullableString($identifiers['temp_fileno'] ?? null);

        // 0. EXACT match by caller-supplied source row id.
        // When the frontend has the user picking a specific OP from a lookup
        // list (e.g. multiple OPs sharing the same op_serial_number), it must
        // pass the chosen row's id and table so we link THAT row, not the
        // latest IC row that happens to share the serial.
        $sourceId    = $identifiers['source_op_id']
            ?? $identifiers['source_id']
            ?? $identifiers['id']
            ?? null;
        $sourceTable = $this->normalizeNullableString(
            $identifiers['source_op_table']
                ?? $identifiers['source_table']
                ?? null
        );
        if ($sourceId !== null && $sourceId !== '' && (int) $sourceId > 0) {
            $tableCandidates = ['instrument_capture', 'deed_registrations'];
            if ($sourceTable !== null && in_array($sourceTable, $tableCandidates, true)) {
                $tableCandidates = [$sourceTable];
            }
            foreach ($tableCandidates as $table) {
                $row = $conn->table($table)
                    ->where('id', (int) $sourceId)
                    ->when($table === 'instrument_capture', function ($q) {
                        $q->where(function ($qq) {
                            $qq->whereNull('is_deleted')->orWhere('is_deleted', 0);
                        });
                    })
                    ->first();
                if ($row) {
                    $arr = (array) $row;
                    $arr['_source_table'] = $table;
                    return $arr;
                }
            }
        }

        // 1. By temp_fileno in instrument_capture (unique per IC row post-dedup,
        //    so this is the most precise legacy fallback when no source_op_id
        //    was supplied).
        if ($tempFileno !== null) {
            $ic = $conn->table('instrument_capture')
                ->where('temp_fileno', $tempFileno)
                ->where('instrument_type', 'Occupancy Permit (OP)')
                ->where(function ($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->orderByDesc('id')
                ->first();

            if ($ic) {
                $row = (array) $ic;
                $row['_source_table'] = 'instrument_capture';
                return $row;
            }
        }

        // 2. By OP serial number in instrument_capture (last-resort fallback —
        //    serial numbers are NOT unique across IC rows).
        if ($opSerial !== null) {
            $ic = $conn->table('instrument_capture')
                ->where('op_serial_number', $opSerial)
                ->where('instrument_type', 'Occupancy Permit (OP)')
                ->where(function ($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->orderByDesc('id')
                ->first();

            if ($ic) {
                $row = (array) $ic;
                $row['_source_table'] = 'instrument_capture';
                return $row;
            }
        }

        // 2. By file number columns in instrument_capture
        $fileNos = array_filter([$mlsFNo, $fileno, $kangis, $newKangis, $tempFileno]);
        if (!empty($fileNos)) {
            $ic = $conn->table('instrument_capture')
                ->where('instrument_type', 'Occupancy Permit (OP)')
                ->where(function ($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->where(function ($q) use ($mlsFNo, $kangis, $newKangis, $tempFileno) {
                    if ($mlsFNo)    $q->orWhere('mlsFNo', $mlsFNo);
                    if ($kangis)    $q->orWhere('kangisFileNo', $kangis);
                    if ($newKangis) $q->orWhere('NewKANGISFileno', $newKangis);
                    if ($tempFileno) $q->orWhere('temp_fileno', $tempFileno);
                })
                ->orderByDesc('id')
                ->first();

            if ($ic) {
                $row = (array) $ic;
                $row['_source_table'] = 'instrument_capture';
                return $row;
            }
        }

        // 3. By file number in deed_registrations
        if (!empty($fileNos)) {
            $dr = $conn->table('deed_registrations')
                ->where('instrument_type', 'Occupancy Permit (OP)')
                ->where(function ($q) use ($mlsFNo, $fileno) {
                    if ($mlsFNo) $q->orWhere('fileno', $mlsFNo);
                    if ($fileno) $q->orWhere('fileno', $fileno);
                })
                ->orderByDesc('id')
                ->first();

            if ($dr) {
                $row = (array) $dr;
                $row['_source_table'] = 'deed_registrations';
                return $row;
            }
        }

        return null;
    }

    /**
     * Link/update an existing OP source record (in instrument_capture or
     * deed_registrations) with prop_id and file number data instead of
     * creating a duplicate OP row in PRA.
     *
     * Returns the prop_id of the linked record.
     */
    public function linkSourceOpRecord(array $sourceRecord, array $updates): ?int
    {
        $conn  = DB::connection('sqlsrv');
        $table = $sourceRecord['_source_table'] ?? 'instrument_capture';
        $id    = $sourceRecord['id'] ?? null;

        if (!$id) {
            Log::warning('linkSourceOpRecord: no id in source record', ['source' => $sourceRecord]);
            return null;
        }

        $allowedFields = $table === 'instrument_capture'
            ? ['prop_id', 'mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'temp_fileno']
            : ['prop_id', 'fileno'];

        $payload = [];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $updates) && $updates[$field] !== null && $updates[$field] !== '') {
                $payload[$field] = $updates[$field];
            }
        }

        if (empty($payload)) {
            Log::info('linkSourceOpRecord: no fields to update', ['table' => $table, 'id' => $id]);
            return $sourceRecord['prop_id'] ?? null;
        }

        $payload['updated_at'] = now();

        try {
            $conn->table($table)->where('id', $id)->update($payload);

            Log::info('linkSourceOpRecord: source OP record linked', [
                'table'   => $table,
                'id'      => $id,
                'updates' => $payload,
            ]);
        } catch (\Throwable $e) {
            Log::error('linkSourceOpRecord: update failed', [
                'table' => $table,
                'id'    => $id,
                'error' => $e->getMessage(),
            ]);
        }

        return $payload['prop_id'] ?? ($sourceRecord['prop_id'] ?? null);
    }
}
