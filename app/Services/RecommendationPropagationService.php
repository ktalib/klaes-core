<?php

namespace App\Services;

use App\Models\LandRecommendation;
use App\Services\Concerns\PropagatesFileFacts;
use Illuminate\Support\Facades\DB;

/**
 * Pushes an edit made on a recommendation out to the registers that hold the same
 * facts about the file — the mirror image of FileIndexingPropagationService, which
 * runs when the File Indexing record is the one being edited.
 *
 * Before this, only a BATCH edit propagated anything (backfillBatchChildDetails), it
 * wrote a snapshot rather than a diff, and it carried neither district nor LGA. A
 * single-record edit propagated nothing at all: correcting a plot number, a district
 * or an LGA on the letter left file_indexings, fileNumber and the name mirrors showing
 * the old value indefinitely, and the capture form — which hydrates from exactly those
 * rows — would hand the stale value straight back on the next file that touched them.
 *
 * WHICH DIRECTION WINS. File Indexing remains the authority on property facts: its
 * propagation overwrites a recommendation outright, and that is deliberate. This is
 * the weaker, opposite push — it exists so a correction keyed on the letter is not
 * simply lost, not to make the letter outrank the register.
 *
 * WHAT IS NOT WRITTEN HERE:
 *
 *  - PRA. RofoPraSyncer inserts a PRA row when the RofO is generated and never updates
 *    one (praRowExists() bails), so a recommendation edited after its RofO was
 *    generated still leaves PRA stale. Rewriting a registered instrument is a
 *    different decision from correcting a register, and is not taken here.
 *
 *  - Any party identity beyond the file title. applicant_name on a recommendation is
 *    the file title — readonly on the single capture form and filled from the selected
 *    file number — so it is a mirror of the title rather than a party of its own. It
 *    reaches the file-identity mirrors (FileName, customer_name, entity_name) and
 *    nothing else. Grantor / party_1 / previous holders are never touched.
 */
class RecommendationPropagationService
{
    use PropagatesFileFacts;

    /**
     * Recommendation column => the name that fact goes by in the shared vocabulary
     * the target maps below are keyed on. Anything not listed here never propagates,
     * which is why the grant conditions, fees, page numbers and print state — all of
     * which belong to the letter rather than to the file — stay put.
     */
    private const FACTS = [
        'applicant_name'    => 'file_title',
        'applicant_address' => 'residence_address',
        'plot_number'       => 'plot_number',
        'location'          => 'location',
        'street_name'       => 'street_name',
        'district'          => 'district',
        'lga'               => 'lga',
        'land_use'          => 'land_use_type',
        'layout_plan_no'    => 'tp_no',
        'tracking_id'       => 'tracking_id',
    ];

    /**
     * Call immediately after the record is saved: the diff is taken from Eloquent's
     * own record of what that save changed, so a field the officer opened and left
     * alone writes nothing anywhere.
     *
     * Throws. A caller that is mid-transaction (updateBatch) wants a failure to roll
     * the batch back; a caller whose record is already committed (update) catches and
     * logs instead. Both are deliberate — see the call sites.
     *
     * @return array<string,int> target table => rows touched
     */
    public function propagate(LandRecommendation $recommendation): array
    {
        $changes = $this->changedFacts($recommendation);

        if (empty($changes)) {
            return [];
        }

        $fileNumbers = $this->fileNumberVariantsFrom([$recommendation->file_number]);

        if (empty($fileNumbers)) {
            return [];
        }

        $targets = [];

        foreach ([
            // The indexing record itself. current_holder moves with the title for the
            // same reason FileName does — on this file it names the same person.
            'file_indexings' => fn () => $this->applyTo('file_indexings', [
                'file_title'        => ['file_title', 'current_holder'],
                'residence_address' => 'residence_address',
                'plot_number'       => 'plot_number',
                'location'          => 'location',
                'street_name'       => 'street_name',
                'district'          => 'district',
                'lga'               => 'lga',
                'land_use_type'     => 'land_use_type',
                'tracking_id'       => 'tracking_id',
            ], $changes, $fileNumbers, ['file_number', 'mls_file_no', 'new_kangis_file_no', 'temp_file_no']),

            // The file-number register. Keyed on every column it records a file under,
            // matching how fillBlankLocationFromFile() READS it — a write that resolved
            // fewer rows than the read would report a sync that never reached the row
            // the form goes on to hydrate from.
            'fileNumber' => fn () => $this->applyTo('fileNumber', [
                'file_title'        => 'FileName',
                'residence_address' => 'address',
                'plot_number'       => 'plot_no',
                'location'          => 'location',
                'district'          => 'district',
                'lga'               => 'lga',
                'tp_no'             => 'tp_no',
                'tracking_id'       => 'tracking_id',
            ], $changes, $fileNumbers, ['mlsfNo', 'kangisFileNo', 'NewKANGISFileNo', 'temp_fileno']),

            // The commissioning record. A file with a row here was commissioned in
            // KLAES, and hydrateBatchRows() reads it as a first-class source, so a
            // correction that skipped it would be handed straight back the next time
            // a child was loaded off this file.
            //
            // Keyed on full_file_number ALONE, unlike the File Indexing push, which
            // also matches old_fileno. A row whose old_fileno is this file is a
            // SUCCESSOR of it — a subdivision child with its own plot and its own
            // holder — so matching it here would stamp the mother's plot number
            // across every child, which is the exact failure the batch table's
            // Apply-to-all warning exists to prevent.
            //
            // is_deleted is honoured because every read that hydrates the form
            // honours it; a write reaching rows the reads cannot see would report a
            // sync into a row nothing will ever read back.
            'mls_file_no' => fn () => $this->applyTo('mls_file_no', [
                'file_title'        => 'file_name',
                'residence_address' => 'address',
                'plot_number'       => 'plot_no',
                'location'          => 'location',
                'district'          => 'district',
                'lga'               => 'lga',
                'land_use_type'     => 'land_use',
                'tp_no'             => 'tp_no',
                'tracking_id'       => 'tracking_id',
            ], $changes, $fileNumbers, ['full_file_number'], function ($query) {
                if ($this->tableHasColumn('mls_file_no', 'is_deleted')) {
                    $query->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0));
                }
            }),

            // The file-identity name mirrors, the same pair
            // FileIndexingPropagationService::syncNameMirror() maintains from the other
            // direction. Title only — these rows carry no property facts.
            'customers_staging' => fn () => $this->applyTo('customers_staging', [
                'file_title' => 'customer_name',
            ], $changes, $fileNumbers, ['file_number']),

            'entities_staging' => fn () => $this->applyTo('entities_staging', [
                'file_title' => 'entity_name',
            ], $changes, $fileNumbers, ['file_number']),

            // Subdivision children are also described by the manual linkage table, so
            // it must not keep the pre-edit plot number. It carries an extra condition
            // of its own, which is why it is not an applyTo() target.
            'manual_file_linkages' => fn () => $this->syncManualLinkage($changes, $fileNumbers),
        ] as $table => $sync) {
            $rows = $sync();
            if ($rows > 0) {
                $targets[$table] = $rows;
            }
        }

        return $targets;
    }

    /**
     * What this save actually changed, in the shared vocabulary, with rule 2 applied.
     *
     * getChanges() is rule 1 straight from Eloquent — after an update it holds exactly
     * the attributes that moved.
     *
     * It is EMPTY after an insert, though: Model::performInsert() never calls
     * syncChanges(), only performUpdate() does. A child newly added to a batch would
     * therefore propagate nothing at all, which is a silent regression on the snapshot
     * backfill this replaced — that one always wrote for a new record. So a record that
     * was just created falls back to its full attributes, which is also what it means:
     * every fact on a new record is new. Rule 2 still filters the blanks out below.
     */
    private function changedFacts(LandRecommendation $recommendation): array
    {
        $changed = $recommendation->wasRecentlyCreated
            ? $recommendation->getAttributes()
            : $recommendation->getChanges();

        $facts = [];

        foreach (self::FACTS as $column => $fact) {
            if (!array_key_exists($column, $changed)) {
                continue;
            }

            $value = $this->scalarize($changed[$column]);

            if ($this->isBlankFact($value)) {
                continue;
            }

            $facts[$fact] = $value;
        }

        return $facts;
    }

    private function syncManualLinkage(array $changes, array $fileNumbers): int
    {
        $table = 'manual_file_linkages';

        if (!$this->tableExists($table)) {
            return 0;
        }

        $payload = [];

        foreach ([
            'file_title'  => 'applicant_name',
            'plot_number' => 'child_plot_number',
        ] as $fact => $column) {
            if (array_key_exists($fact, $changes) && $this->tableHasColumn($table, $column)) {
                $payload[$column] = $changes[$fact];
            }
        }

        if (empty($payload)) {
            return 0;
        }

        $payload['updated_at'] = now();

        return DB::connection('sqlsrv')->table($table)
            ->where('workflow_type', 'Subdivision')
            ->whereIn('new_file_number', $fileNumbers)
            ->update($payload);
    }
}
