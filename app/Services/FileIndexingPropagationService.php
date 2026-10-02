<?php

namespace App\Services;

use App\Services\Concerns\PropagatesFileFacts;
use App\Services\Prs\Support\LandUseNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Pushes edits made on a file-indexing record out to the other systems that hold the
 * same facts: the file-number registers, PRA, and the OSS application the file came from.
 *
 * Before this, editing a file in File Indexing updated file_indexings and a handful of
 * columns on fileNumber (FileName, location, lga, plot_no, tp_no — see
 * FileIndexingController::updateFileNumberTable) and nothing else. Correcting a plot
 * number or an LGA left PRA, mls_file_no and the originating OSS application showing the
 * old value indefinitely.
 *
 * Two rules govern what this writes:
 *
 * 1. ONLY FIELDS THAT ACTUALLY CHANGED. The payload is a diff of the record before and
 *    after the save, not a snapshot. A blanket push would stomp values in OSS and PRA
 *    that were deliberately different from the indexing record — those tables are not
 *    copies of file_indexings, they are separate records that happen to share some facts.
 *
 * 2. NEVER PARTY / APPLICANT IDENTITY. Property attributes (plot, TP, district, LGA,
 *    location, land use, plot size) propagate everywhere. The file TITLE propagates only
 *    to the file-identity mirrors — fileNumber.FileName, mls_file_no.file_name,
 *    customers_staging.customer_name, entities_staging.entity_name — which is exactly the
 *    set InstrumentRegistrationService::syncPartyNames() already maintains in the other
 *    direction — PLUS the holder name on PRA, under a strict condition described below.
 *
 *    The holder-name case matters because the OSS listings show
 *    COALESCE(pra.Grantee, fileNumber.FileName) as the file title
 *    (OpResettlementApplicationController). Updating only FileName left those screens
 *    showing the previous owner forever, which is the bug this was reported for.
 *
 *    The holder name is rewritten WHATEVER it currently says. An earlier rule only
 *    touched rows whose stored value still equalled the old file title, which defeated
 *    the point: the reason someone edits the title is that the recorded name is wrong,
 *    so it rarely matches. What bounds the write instead is WHICH ROW is touched —
 *    only the row those screens actually read: the newest row per instrument type per
 *    property (see currentHolderRows()). Older rows of the same type name PREVIOUS
 *    owners and are historical fact, as is an Occupancy Permit sitting behind a
 *    Transfer of Title — that permit names the original allottee, not today's holder.
 *    Grantor / party_1 is never written on PRA at all — that is the previous owner.
 *
 *    The recommendation letters are the third place the title travels to, as
 *    land_recommendations.applicant_name: that field is readonly on the capture form and
 *    filled from the selected file number, so it is a mirror of the title rather than a
 *    party of its own. See syncLandRecommendations().
 *
 *    Still deliberately NOT written: mother_applications first_name / surname /
 *    corporate_name — the identity of who submitted an application, not a property
 *    attribute of the file.
 */
class FileIndexingPropagationService
{
    use PropagatesFileFacts;

    /**
     * Indexing fields whose changes are worth pushing outward, and how they are named
     * on the indexing record.
     */
    /**
     * Instrument types whose party_2 / Grantee IS the current owner of the file.
     *
     * Mortgages, tripartite mortgages and surrender/release are deliberately absent:
     * their party_2 is a lender or the State, not the owner, and renaming it from a
     * file-title edit would corrupt the instrument.
     */
    private const OWNERSHIP_INSTRUMENTS = [
        'Transfer of Title (OP)',
        'Occupancy Permit (OP)',
        'Right of Occupancy',
        'Certificate of Occupancy',
        'Deed of Assignment',
        'Deed of Gift',
        'Power of Attorney',
    ];

    private const SYNCABLE_FIELDS = [
        'file_title',
        'location',
        'street_name',
        'district',
        'lga',
        'plot_number',
        'tp_no',
        'plot_size',
        'land_use_type',
    ];

    /**
     * Compare the record before and after the save and push whatever changed.
     *
     * @param  object  $before  the file_indexings row as it was
     * @param  object  $after   the file_indexings row as it now is
     * @return array<string,mixed> per-target row counts, for logging and the API response
     */
    public function propagate($before, $after, array $explicitNames = []): array
    {
        [$changes, $previous] = $this->diff($before, $after);

        if (empty($changes)) {
            return ['changed' => [], 'targets' => []];
        }

        $fileNumbers = $this->fileNumberVariants($after);

        if (empty($fileNumbers)) {
            Log::info('FileIndexingPropagation: nothing to key on', [
                'file_indexing_id' => $after->id ?? null,
                'changed' => array_keys($changes),
            ]);

            return ['changed' => array_keys($changes), 'targets' => []];
        }

        $targets = [];

        foreach ([
            'fileNumber' => fn () => $this->syncFileNumber($changes, $fileNumbers),
            'mls_file_no' => fn () => $this->syncMlsFileNo($changes, $fileNumbers),
            'pra' => fn () => $this->syncPra($changes, $fileNumbers),
            'pra_holder_name' => fn () => $this->syncHolderName('pra', ['Grantee', 'party_2'],
                ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'temp_fileno', 'resolved_fileno'],
                $changes, $previous, $fileNumbers),
            'instrument_capture_holder_name' => fn () => $this->syncHolderName('instrument_capture', ['party_2_name'],
                ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'temp_fileno'],
                $changes, $previous, $fileNumbers),
            'pra_current_holder' => fn () => $this->renameCurrentHolder($changes, $fileNumbers),
            'instrument_capture_current_holder' => fn () => $this->renameCaptureHolder($changes, $fileNumbers),
            'instrument_capture' => fn () => $this->syncInstrumentCapture($changes, $fileNumbers),
            'customers_staging' => fn () => $this->syncNameMirror('customers_staging', 'customer_name', 'file_number', $changes, $fileNumbers,
                $explicitNames['customer_name'] ?? null),
            'entities_staging' => fn () => $this->syncNameMirror('entities_staging', 'entity_name', 'file_number', $changes, $fileNumbers,
                $explicitNames['entity_name'] ?? null),
            'oss_applications' => fn () => $this->syncOssApplications($changes, $fileNumbers),
            'oss_applicant_name' => fn () => $this->syncNameMirror('oss_applications', 'applicant_name', 'file_no', $changes, $fileNumbers),
            'land_recommendations' => fn () => $this->syncLandRecommendations($changes, $after, $fileNumbers),
            'land_recommendations_land_use' => fn () => $this->syncRecommendationLandUse($changes, $after, $fileNumbers),
            'mother_applications' => fn () => $this->syncMotherApplication($changes, $after),
            'subapplications' => fn () => $this->syncSubApplication($changes, $after),
        ] as $label => $sync) {
            try {
                $rows = $sync();
                if ($rows > 0) {
                    $targets[$label] = $rows;
                }
            } catch (Throwable $e) {
                // One unreachable target must not fail the save — file_indexings is already
                // committed and is the authoritative record. Report it loudly instead.
                Log::error('FileIndexingPropagation: target failed', [
                    'target' => $label,
                    'file_indexing_id' => $after->id ?? null,
                    'error' => $e->getMessage(),
                ]);
                $targets[$label] = 'failed: ' . $e->getMessage();
            }
        }

        Log::info('FileIndexingPropagation: done', [
            'file_indexing_id' => $after->id ?? null,
            'file_numbers' => $fileNumbers,
            'changed' => array_keys($changes),
            'targets' => $targets,
        ]);

        return ['changed' => array_keys($changes), 'targets' => $targets];
    }

    /**
     * @return array{0:array<string,mixed>,1:array<string,mixed>} [changed => new, changed => old]
     */
    private function diff($before, $after): array
    {
        $changes = [];
        $previous = [];

        foreach (self::SYNCABLE_FIELDS as $field) {
            $old = $this->scalarize($before->$field ?? null);
            $new = $this->scalarize($after->$field ?? null);

            // Only a real, non-blank new value propagates. Clearing a field in indexing
            // does not mean the other systems should lose their copy — those are usually
            // populated from sources indexing does not see.
            if ($this->isBlankFact($new)) {
                continue;
            }

            if ($this->normalize($old) !== $this->normalize($new)) {
                $changes[$field] = $new;
                $previous[$field] = $old;
            }
        }

        return [$changes, $previous];
    }

    /**
     * Rename the current holder on instrument rows that were already mirroring the file
     * title, and only those.
     *
     * The guard is the whole point: `WHERE column = old_title`. A row holding any other
     * name belongs to a genuinely different party on a registered instrument and must
     * survive untouched. Grantor / party_1 is never a target — that is the previous owner.
     *
     * @param  array<int,string>  $nameColumns
     * @param  array<int,string>  $keyColumns
     */
    private function syncHolderName(string $table, array $nameColumns, array $keyColumns, array $changes, array $previous, array $fileNumbers): int
    {
        $newTitle = $changes['file_title'] ?? null;
        $oldTitle = $previous['file_title'] ?? null;

        if ($newTitle === null || $oldTitle === null || trim((string) $oldTitle) === '') {
            return 0;
        }

        if (!Schema::connection('sqlsrv')->hasTable($table)) {
            return 0;
        }

        $nameColumns = array_values(array_filter(
            $nameColumns,
            fn ($column) => Schema::connection('sqlsrv')->hasColumn($table, $column)
        ));

        $keyColumns = array_values(array_filter(
            $keyColumns,
            fn ($column) => Schema::connection('sqlsrv')->hasColumn($table, $column)
        ));

        if (empty($nameColumns) || empty($keyColumns)) {
            return 0;
        }

        $affected = 0;

        // One statement per column: a row may carry the stale title in Grantee but a
        // different, correct value in party_2, and each must be judged on its own.
        foreach ($nameColumns as $column) {
            $affected += DB::connection('sqlsrv')
                ->table($table)
                ->where(function ($query) use ($keyColumns, $fileNumbers) {
                    foreach ($keyColumns as $index => $key) {
                        $index === 0
                            ? $query->whereIn($key, $fileNumbers)
                            : $query->orWhereIn($key, $fileNumbers);
                    }
                })
                ->whereRaw('UPPER(LTRIM(RTRIM(' . $column . '))) = UPPER(?)', [trim((string) $oldTitle)])
                ->update([$column => $newTitle]);
        }

        return $affected;
    }

    /**
     * Every number this physical file is known by. A file's records are split across the
     * main number, the KANGIS aliases and a temporary "(T)" number, and matching only the
     * literal file_number silently updates nothing for temp-registered files.
     *
     * @return array<int,string>
     */
    private function fileNumberVariants($record): array
    {
        return $this->fileNumberVariantsFrom([
            $record->file_number ?? null,
            $record->temp_file_no ?? null,
            $record->mls_file_no ?? null,
            $record->new_kangis_file_no ?? null,
            $record->kangis_fileno_resolved ?? null,
        ]);
    }

    /**
     * updateFileNumberTable() already writes FileName, location, lga, plot_no and tp_no on
     * every save. District and street were never carried, so they are added here; the
     * overlap is harmless because both write the same value.
     */
    private function syncFileNumber(array $changes, array $fileNumbers): int
    {
        return $this->applyTo('fileNumber', [
            'file_title' => 'FileName',
            'location' => 'location',
            'district' => 'district',
            'lga' => 'lga',
            'plot_number' => 'plot_no',
            'tp_no' => 'tp_no',
        ], $changes, $fileNumbers, ['mlsfNo', 'kangisFileNo', 'NewKANGISFileNo', 'temp_fileno']);
    }

    private function syncMlsFileNo(array $changes, array $fileNumbers): int
    {
        return $this->applyTo('mls_file_no', [
            'file_title' => 'file_name',
            'location' => 'location',
            'district' => 'district',
            'lga' => 'lga',
            'plot_number' => 'plot_no',
            'tp_no' => 'tp_no',
            'land_use_type' => 'land_use',
        ], $changes, $fileNumbers, ['full_file_number', 'old_fileno']);
    }

    /**
     * Property attributes only — see the class docblock on why party_1 / party_2 are not
     * touched.
     */
    private function syncPra(array $changes, array $fileNumbers): int
    {
        return $this->applyTo('pra', [
            'location' => 'location',
            'street_name' => 'streetName',
            'district' => 'districtName',
            'lga' => 'lgsaOrCity',
            'plot_number' => 'plot_no',
            'tp_no' => 'tp_no',
            'plot_size' => 'plot_size',
            'land_use_type' => 'land_use',
        ], $changes, $fileNumbers, ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'temp_fileno', 'resolved_fileno']);
    }

    /**
     * The file-identity name mirrors, matching what syncPartyNames() maintains inbound.
     */
    private function syncNameMirror(string $table, string $column, string $keyColumn, array $changes, array $fileNumbers, ?string $explicitName = null): int
    {
        // The Entity & Customer section of the same form writes these columns directly,
        // via updateEntityAndCustomerRecords(), INSIDE the transaction — i.e. before this
        // runs. If the operator deliberately set an entity/customer name different from
        // the file title in the same save, mirroring the title here would silently
        // overwrite what they just typed. Their explicit value wins.
        $explicitName = $explicitName !== null ? trim($explicitName) : '';

        if ($explicitName !== ''
            && isset($changes['file_title'])
            && $this->normalize($explicitName) !== $this->normalize($changes['file_title'])) {
            return 0;
        }

        return $this->applyTo($table, ['file_title' => $column], $changes, $fileNumbers, [$keyColumn]);
    }

    /**
     * Rename the CURRENT owner on the file's latest ownership instrument, whatever name
     * it currently holds.
     *
     * syncHolderName() only touches rows whose stored name still equals the old file
     * title. That covers rows already mirroring the title, but not the common real case:
     * pra.Grantee holds the true owner while file_indexings.file_title had drifted to
     * something else. The OSS listings read COALESCE(pra.Grantee, fileNumber.FileName),
     * so in that state a rename in File Indexing never showed up on those screens.
     *
     * Scope is deliberately one row: the most recent instrument of an OWNERSHIP type
     * (see OWNERSHIP_INSTRUMENTS). Earlier rows name PREVIOUS owners and are historical
     * fact — they are never touched, and neither is Grantor / party_1 on any row.
     *
     * Note this DOES rewrite a party on a registered instrument. That is intended: File
     * Indexing is treated as the master for who currently holds the file.
     */
    private function renameCurrentHolder(array $changes, array $fileNumbers): int
    {
        $newTitle = $changes['file_title'] ?? null;

        if ($newTitle === null || trim((string) $newTitle) === '') {
            return 0;
        }

        if (!Schema::connection('sqlsrv')->hasTable('pra')
            || !Schema::connection('sqlsrv')->hasColumn('pra', 'instrument_type')) {
            return 0;
        }

        $rows = DB::connection('sqlsrv')->table('pra')
            ->where(function ($q) use ($fileNumbers) {
                $q->whereIn('mlsFNo', $fileNumbers)
                    ->orWhereIn('temp_fileno', $fileNumbers)
                    ->orWhereIn('kangisFileNo', $fileNumbers)
                    ->orWhereIn('NewKANGISFileno', $fileNumbers);
            })
            ->whereIn('instrument_type', self::OWNERSHIP_INSTRUMENTS)
            ->orderByDesc('id')
            ->get(['id', 'instrument_type', 'prop_id', 'parent_prop_id', 'Grantee', 'party_2']);

        if ($rows->isEmpty()) {
            return 0;
        }

        $affected = 0;

        foreach ($this->currentHolderRows($rows) as $row) {
            $payload = [];

            foreach (['Grantee', 'party_2'] as $column) {
                if (Schema::connection('sqlsrv')->hasColumn('pra', $column)
                    && $this->normalize($row->$column ?? '') !== $this->normalize($newTitle)) {
                    $payload[$column] = $newTitle;
                }
            }

            if (empty($payload)) {
                continue;
            }

            Log::info('FileIndexingPropagation: renaming current holder', [
                'pra_id' => $row->id,
                'instrument_type' => $row->instrument_type,
                'from' => $row->Grantee ?? null,
                'to' => $newTitle,
            ]);

            $affected += DB::connection('sqlsrv')->table('pra')->where('id', $row->id)->update($payload);
        }

        return $affected;
    }

    /**
     * Of the ownership rows found for a file, the one that states who holds it NOW.
     *
     * Ownership instruments come in two kinds, and only one row is ever "current":
     *
     *   GRANT     (Occupancy Permit, Right of Occupancy, Certificate of Occupancy) —
     *             the State allocating the land. Its grantee is the ORIGINAL ALLOTTEE.
     *   TRANSFER  (Transfer of Title, Deed of Assignment, Deed of Gift, Power of
     *             Attorney) — the holding moving to someone else.
     *
     * So: if the property has any transfer, the newest transfer names the current
     * holder and every grant behind it is allocation history. With no transfer at all,
     * the newest grant IS the current holding. Real data shows why this matters —
     * prop 147221 carries an OP naming "BRUCE HANE" and a ToT naming "SALISU UMAR";
     * renaming the OP from a file-title edit would rewrite who the land was allocated to.
     *
     * Rows are grouped by property (parent_prop_id, else prop_id) and ordered by id, the
     * same way the OSS listings pick the row they display — ids, not transaction_date,
     * because those are user-entered instrument dates that routinely put an OP after the
     * ToT superseding it.
     *
     * @param  \Illuminate\Support\Collection  $rows
     * @return array<int,object>
     */
    private function currentHolderRows($rows): array
    {
        $byGroup = [];

        foreach ($rows as $row) {
            $group = trim((string) ($row->parent_prop_id ?? '')) !== ''
                ? trim((string) $row->parent_prop_id)
                : trim((string) ($row->prop_id ?? ''));

            $kind = $this->isTransferInstrument((string) $row->instrument_type) ? 'transfer' : 'grant';

            $current = $byGroup[$group][$kind] ?? null;
            if ($current === null || (int) $row->id > (int) $current->id) {
                $byGroup[$group][$kind] = $row;
            }
        }

        $selected = [];

        foreach ($byGroup as $kinds) {
            // A transfer always wins: it is the later statement of who holds the land.
            $selected[] = $kinds['transfer'] ?? $kinds['grant'];
        }

        return array_values(array_filter($selected));
    }

    /** Does this instrument move the holding to someone else (rather than grant it)? */
    private function isTransferInstrument(string $instrumentType): bool
    {
        foreach (['Transfer of Title', 'Deed of Assignment', 'Deed of Gift', 'Power of Attorney'] as $needle) {
            if (stripos($instrumentType, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * The same unconditional rename for the capture staging rows.
     *
     * The OSS OP listing unions instrument_capture in for OP records that have no PRA row,
     * and reads `ic.party_1_name as Grantee` there — the permit holder for an OP is
     * party_1, not party_2. Nothing was rewriting that column, so an FC/IC-sourced row
     * kept its old name after a file-title edit no matter what.
     *
     * party_2_name is written too: on the ownership deeds it is the party taking title.
     * Nothing is written on a capture row that PRA has already superseded with a Transfer
     * of Title — behind a transfer the capture row names the original allottee or an
     * earlier owner, which is history, not the current holder.
     */
    private function renameCaptureHolder(array $changes, array $fileNumbers): int
    {
        $newTitle = $changes['file_title'] ?? null;

        if ($newTitle === null || trim((string) $newTitle) === '') {
            return 0;
        }

        if (!Schema::connection('sqlsrv')->hasTable('instrument_capture')
            || !Schema::connection('sqlsrv')->hasColumn('instrument_capture', 'instrument_type')) {
            return 0;
        }

        $keyColumns = array_values(array_filter(
            ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'temp_fileno'],
            fn ($column) => Schema::connection('sqlsrv')->hasColumn('instrument_capture', $column)
        ));

        if (empty($keyColumns)) {
            return 0;
        }

        $rows = DB::connection('sqlsrv')->table('instrument_capture')
            ->where(function ($query) use ($keyColumns, $fileNumbers) {
                foreach ($keyColumns as $index => $key) {
                    $index === 0
                        ? $query->whereIn($key, $fileNumbers)
                        : $query->orWhereIn($key, $fileNumbers);
                }
            })
            ->whereIn('instrument_type', self::OWNERSHIP_INSTRUMENTS)
            ->orderByDesc('id')
            ->get(['id', 'instrument_type', 'prop_id', 'party_1_name', 'party_2_name']);

        if ($rows->isEmpty()) {
            return 0;
        }

        // instrument_capture has no parent_prop_id; currentHolderRows() reads it as absent
        // and groups on prop_id, which is what the listing's IC branch partitions by.
        $candidates = $this->currentHolderRows($rows);
        $affected = 0;

        foreach ($candidates as $row) {
            $instrumentType = (string) $row->instrument_type;

            // PRA holds the authoritative transfer chain. If a Transfer of Title exists for
            // this property, any capture row that is not itself that transfer has been
            // superseded and names an earlier party.
            if (stripos($instrumentType, 'Transfer of Title') === false
                && $this->propertyHasTransferOfTitle($row->prop_id ?? null)) {
                continue;
            }

            $payload = [];

            if (Schema::connection('sqlsrv')->hasColumn('instrument_capture', 'party_2_name')
                && $this->normalize($row->party_2_name ?? '') !== $this->normalize($newTitle)) {
                $payload['party_2_name'] = $newTitle;
            }

            // For an Occupancy Permit the holder is party_1 — and party_1_name is the column
            // the OSS listing reads as the Grantee for IC-sourced rows.
            if (stripos($instrumentType, 'Occupancy Permit') !== false
                && Schema::connection('sqlsrv')->hasColumn('instrument_capture', 'party_1_name')
                && $this->normalize($row->party_1_name ?? '') !== $this->normalize($newTitle)) {
                $payload['party_1_name'] = $newTitle;
            }

            if (empty($payload)) {
                continue;
            }

            Log::info('FileIndexingPropagation: renaming capture holder', [
                'instrument_capture_id' => $row->id,
                'instrument_type' => $row->instrument_type,
                'columns' => array_keys($payload),
                'to' => $newTitle,
            ]);

            $affected += DB::connection('sqlsrv')->table('instrument_capture')
                ->where('id', $row->id)
                ->update($payload);
        }

        return $affected;
    }

    /** Does this property already carry a Transfer of Title in PRA? */
    private function propertyHasTransferOfTitle($propId): bool
    {
        $propId = trim((string) ($propId ?? ''));

        if ($propId === '' || $propId === '0') {
            return false;
        }

        if (!Schema::connection('sqlsrv')->hasTable('pra')
            || !Schema::connection('sqlsrv')->hasColumn('pra', 'instrument_type')) {
            return false;
        }

        return DB::connection('sqlsrv')->table('pra')
            ->where(function ($query) use ($propId) {
                $query->where('prop_id', $propId)->orWhere('parent_prop_id', $propId);
            })
            ->where('instrument_type', 'LIKE', '%Transfer of Title%')
            ->exists();
    }

    /**
     * Property attributes on the capture staging rows. The OSS listings read from
     * instrument_capture for FC-sourced records, so leaving it out left those rows
     * showing pre-edit values.
     */
    private function syncInstrumentCapture(array $changes, array $fileNumbers): int
    {
        return $this->applyTo('instrument_capture', [
            'district' => 'district',
            'lga' => 'lga',
            'tp_no' => 'tp_no',
            'plot_size' => 'plot_size',
            'land_use_type' => 'land_use',
        ], $changes, $fileNumbers, ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'temp_fileno']);
    }

    private function syncOssApplications(array $changes, array $fileNumbers): int
    {
        return $this->applyTo('oss_applications', [
            'location' => 'location',
            'district' => 'district',
            'lga' => 'lga',
            'plot_number' => 'plot_no',
            'land_use_type' => 'land_use',
        ], $changes, $fileNumbers, ['file_no']);
    }

    /**
     * The recommendation / RofO letters already captured against this file.
     *
     * Every column written here is one the capture form fills FROM the file when the
     * number is picked — applicant name, location, street, district, LGA, plot no and
     * TP no (public/js/land_recommendations.js for a single capture,
     * LandRecommendationController::hydrateBatchRows() for a batch). A correction keyed
     * in indexing has to reach them, or the next reprint still carries the LGA or the
     * title that was just corrected.
     *
     * Nothing else on the record is touched: term, premium, ground rent, the purpose
     * clause, the page references and the recommendation text are the grant's own
     * findings, not a copy of the file.
     *
     * Two departures from a plain apply():
     *
     *  - The location loses a leading "PLOT <n>," exactly as hydrateBatchRows() strips
     *    it. file_indexings leads its location with the plot for ~43k rows and the
     *    letter prints the plot from its own column, so a straight copy would print it
     *    twice.
     *
     *  - A BLANK COLUMN IS FILLED FROM THE FILE even when that field is not part of this
     *    edit's diff. Rule 1 of this class exists to protect values another system set
     *    deliberately; an empty column is not one of those, and leaving it empty is not
     *    harmless here. The capture form guesses at a missing LGA by asking which LGA is
     *    most common for the district (InstrumentController::districtLookupLga), so a
     *    blank column does not read as blank on screen — it reads as a plausible wrong
     *    answer that the next save then stores. RES-2026-2600 is the case this was
     *    reported for: LGA null, district BUK, and the edit form showing "Kano Municipal"
     *    (9 BUK files against Ungogo's 2) over the file's own Ungogo.
     */
    private function syncLandRecommendations(array $changes, $record, array $fileNumbers): int
    {
        if (!Schema::connection('sqlsrv')->hasTable('land_recommendations')) {
            return 0;
        }

        $map = [
            // The name on the letter is the file title — the field is readonly on the
            // form and captioned "Taken from the selected file number".
            'file_title'  => 'applicant_name',
            'location'    => 'location',
            'street_name' => 'street_name',
            'district'    => 'district',
            'lga'         => 'lga',
            'plot_number' => 'plot_number',
            'tp_no'       => 'layout_plan_no',
        ];

        $map = array_filter(
            $map,
            fn ($column) => Schema::connection('sqlsrv')->hasColumn('land_recommendations', $column)
        );

        if (empty($map)) {
            return 0;
        }

        $rows = $this->matchedRecommendations(
            $fileNumbers,
            array_merge(['id', 'file_number'], array_values($map))
        );

        if ($rows->isEmpty()) {
            return 0;
        }

        $hasUpdatedAt = Schema::connection('sqlsrv')->hasColumn('land_recommendations', 'updated_at');
        $corrected = [];
        $filled = 0;
        $updated = 0;

        foreach ($rows as $row) {
            $payload = [];

            foreach ($map as $field => $column) {
                if (array_key_exists($field, $changes)) {
                    // A correction. It overwrites whatever is there — that is the point.
                    $payload[$column] = $changes[$field];
                    $corrected[$field] = true;
                    continue;
                }

                if (trim((string) ($row->$column ?? '')) !== '') {
                    continue;
                }

                $value = $this->scalarize($record->$field ?? null);
                if (trim((string) $value) === '') {
                    continue;
                }

                $payload[$column] = $value;
                $filled++;
            }

            if (isset($payload['location'])) {
                $payload['location'] = $this->stripLeadingPlot((string) $payload['location']);
            }

            if (empty($payload)) {
                continue;
            }

            if ($hasUpdatedAt) {
                $payload['updated_at'] = now();
            }

            $updated += DB::connection('sqlsrv')->table('land_recommendations')
                ->where('id', $row->id)
                ->update($payload);
        }

        if ($updated > 0 && !empty($corrected)) {
            // A letter that is already approved, generated or on paper still gets the
            // correction — that is the point of correcting it — but the paper copy in
            // the file now disagrees with the record, so say which ones loudly enough
            // to find. Blank-fills are not reported: nothing that was printed changed.
            $this->warnOnIssuedRecommendations($fileNumbers, array_keys($corrected));
        }

        if ($filled > 0) {
            Log::info('FileIndexingPropagation: blank recommendation columns filled from the file', [
                'columns_filled' => $filled,
                'records' => $rows->pluck('id')->all(),
            ]);
        }

        return $updated;
    }

    /**
     * Land use on a recommendation, kept apart from the other columns because it does
     * not travel alone.
     *
     * The letter prints a purpose clause, and a purpose belongs to exactly one land use
     * (purposes.landuseid). Writing a new land_use_id over a record whose purpose came
     * from the old one leaves the letter printing a purpose that no longer belongs to
     * its land use — worse than the stale value, because it reads as correct. Those rows
     * are left exactly as they are and logged for an officer to re-pick.
     *
     * A land use the register does not recognise is not written either: the id is what
     * the letter prints from, and a name with no id behind it would leave the two
     * columns disagreeing.
     *
     * When the land use is not part of this edit's diff the file's current value is
     * still used, but only to fill a row that has no land use at all — same reasoning as
     * the blank-fill in syncLandRecommendations().
     */
    private function syncRecommendationLandUse(array $changes, $record, array $fileNumbers): int
    {
        if (!Schema::connection('sqlsrv')->hasTable('land_recommendations')
            || !Schema::connection('sqlsrv')->hasColumn('land_recommendations', 'land_use_id')) {
            return 0;
        }

        $isCorrection = array_key_exists('land_use_type', $changes);
        $landUseText  = $isCorrection
            ? (string) $changes['land_use_type']
            : (string) $this->scalarize($record->land_use_type ?? null);

        if (trim($landUseText) === '') {
            return 0;
        }

        // land_uses holds full names while indexing carries free text and prefixes, so
        // the two vocabularies are bridged by the shared normaliser rather than matched
        // as strings — the same route hydrateBatchRows() takes.
        $normalizer = new LandUseNormalizer();
        $canonical  = $normalizer->normalize($landUseText);

        $landUse = DB::connection('sqlsrv')->table('land_uses')->get(['id', 'landuse'])
            ->first(fn ($row) => $normalizer->normalize((string) $row->landuse) === $canonical);

        if (!$landUse) {
            Log::info('FileIndexingPropagation: land use not in the register, recommendations left alone', [
                'land_use' => $landUseText,
                'canonical' => $canonical,
            ]);

            return 0;
        }

        $landUseId = (int) $landUse->id;

        $validPurposes = DB::connection('sqlsrv')->table('purposes')
            ->where('landuseid', $landUseId)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $ids = [];
        $orphaned = [];

        foreach ($this->matchedRecommendations($fileNumbers, ['id', 'file_number', 'land_use_id', 'purpose_id']) as $row) {
            if ((int) ($row->land_use_id ?? 0) === $landUseId) {
                continue;
            }

            // Not a correction, so the file is only allowed to fill a gap — a land use
            // the officer picked on the letter stands until indexing actually changes.
            if (!$isCorrection && (int) ($row->land_use_id ?? 0) > 0) {
                continue;
            }

            $purposeId = trim((string) ($row->purpose_id ?? ''));
            if ($purposeId !== '' && !in_array($purposeId, $validPurposes, true)) {
                $orphaned[] = ['id' => $row->id, 'file_number' => $row->file_number, 'purpose_id' => $purposeId];
                continue;
            }

            $ids[] = $row->id;
        }

        if (!empty($orphaned)) {
            Log::warning('FileIndexingPropagation: land use not applied — purpose clause belongs to the old land use', [
                'land_use' => $landUse->landuse,
                'land_use_id' => $landUseId,
                'records' => $orphaned,
            ]);
        }

        if (empty($ids)) {
            return 0;
        }

        $payload = ['land_use_id' => $landUseId];

        if (Schema::connection('sqlsrv')->hasColumn('land_recommendations', 'land_use')) {
            // The register's own spelling, not the indexing text — that is what the
            // dropdown would have stored had the file been picked today.
            $payload['land_use'] = (string) $landUse->landuse;
        }

        if (Schema::connection('sqlsrv')->hasColumn('land_recommendations', 'updated_at')) {
            $payload['updated_at'] = now();
        }

        return DB::connection('sqlsrv')->table('land_recommendations')
            ->whereIn('id', $ids)
            ->update($payload);
    }

    /**
     * The recommendation rows this file's number reaches, on either the file number the
     * letter was captured against or the old file number a derived application carries.
     *
     * @param  array<int,string>  $columns
     */
    private function matchedRecommendations(array $fileNumbers, array $columns)
    {
        $hasOldFileNumber = Schema::connection('sqlsrv')->hasColumn('land_recommendations', 'old_file_number');

        return DB::connection('sqlsrv')->table('land_recommendations')
            ->where(function ($query) use ($fileNumbers, $hasOldFileNumber) {
                $query->whereIn('file_number', $fileNumbers);
                if ($hasOldFileNumber) {
                    $query->orWhereIn('old_file_number', $fileNumbers);
                }
            })
            ->get($columns);
    }

    /**
     * Record which already-issued letters an indexing edit has just rewritten, so the
     * mismatch between the record and the paper in the file is traceable.
     *
     * @param  array<int,string>  $fields  the indexing fields that were corrected
     */
    private function warnOnIssuedRecommendations(array $fileNumbers, array $fields): void
    {
        $columns = array_values(array_filter(
            ['id', 'file_number', 'status', 'rofo_status', 'print_count', 'rofo_print_count'],
            fn ($column) => Schema::connection('sqlsrv')->hasColumn('land_recommendations', $column)
        ));

        $issued = $this->matchedRecommendations($fileNumbers, $columns)
            ->filter(fn ($row) => ($row->status ?? '') === 'approved'
                || ($row->rofo_status ?? '') === 'generated'
                || (int) ($row->print_count ?? 0) > 0
                || (int) ($row->rofo_print_count ?? 0) > 0)
            ->values();

        if ($issued->isEmpty()) {
            return;
        }

        Log::warning('FileIndexingPropagation: issued recommendation updated from an indexing edit', [
            'changed' => array_values($fields),
            'records' => $issued->map(fn ($row) => [
                'id' => $row->id,
                'file_number' => $row->file_number ?? null,
                'status' => $row->status ?? null,
                'rofo_status' => $row->rofo_status ?? null,
            ])->all(),
        ]);
    }

    /**
     * "PLOT 61, 14, 31" -> "14, 31". Only a leading "PLOT <token>," is taken, and only
     * when something is left after it, so a location that is nothing but a plot survives
     * intact. Mirrors the strip in LandRecommendationController::hydrateBatchRows().
     */
    private function stripLeadingPlot(string $location): string
    {
        $stripped = trim((string) preg_replace('/^\s*PLOT\s+[^,]+,\s*/i', '', $location));

        return $stripped !== '' ? $stripped : $location;
    }

    /**
     * The OSS mother application this file was indexed from. Keyed by the stored link
     * (file_indexings.main_application_id), never by a file-number guess — mother_applications
     * holds several file-number-ish columns and matching loosely could hit another applicant's
     * record.
     */
    private function syncMotherApplication(array $changes, $record): int
    {
        $applicationId = (int) ($record->main_application_id ?? 0);

        if ($applicationId <= 0 || !Schema::connection('sqlsrv')->hasTable('mother_applications')) {
            return 0;
        }

        $map = [
            'district' => 'property_district',
            'lga' => 'property_lga',
            'plot_number' => 'property_plot_no',
            'street_name' => 'property_street_name',
            'land_use_type' => 'land_use',
            'plot_size' => 'plot_size',
            // The owner's name as one string. first_name / surname / corporate_name are
            // still never written: they record who SUBMITTED the application, and
            // splitting a file title back into those parts is guesswork that would
            // corrupt the applicant record.
            'file_title' => 'owner_fullname',
        ];

        $payload = [];
        foreach ($map as $field => $column) {
            if (array_key_exists($field, $changes) && Schema::connection('sqlsrv')->hasColumn('mother_applications', $column)) {
                $payload[$column] = $changes[$field];
            }
        }

        if (empty($payload)) {
            return 0;
        }

        $payload['updated_at'] = now();

        return DB::connection('sqlsrv')
            ->table('mother_applications')
            ->where('id', $applicationId)
            ->update($payload);
    }

    private function syncSubApplication(array $changes, $record): int
    {
        $subApplicationId = (int) ($record->subapplication_id ?? 0);

        if ($subApplicationId <= 0 || !Schema::connection('sqlsrv')->hasTable('subapplications')) {
            return 0;
        }

        $map = [
            'location' => 'property_location',
            'district' => 'unit_district',
            'lga' => 'unit_lga',
            'street_name' => 'address_street_name',
            'land_use_type' => 'land_use',
            'plot_size' => 'plot_size',
        ];

        $payload = [];
        foreach ($map as $field => $column) {
            if (array_key_exists($field, $changes) && Schema::connection('sqlsrv')->hasColumn('subapplications', $column)) {
                $payload[$column] = $changes[$field];
            }
        }

        if (empty($payload)) {
            return 0;
        }

        $payload['updated_at'] = now();

        return DB::connection('sqlsrv')
            ->table('subapplications')
            ->where('id', $subApplicationId)
            ->update($payload);
    }
}
