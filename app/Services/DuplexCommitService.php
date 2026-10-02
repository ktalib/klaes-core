<?php

namespace App\Services;

use App\Http\Controllers\MlsFileNoController;
use App\Models\ChangeOfPurposeApplication;
use App\Models\DuplexParcelUpdate;
use App\Models\DuplexParcelUpdateFile;
use App\Models\DuplexParcelUpdateStage;
use App\Models\PlotApplicationSize;
use App\Models\PlotExtensionApplication;
use App\Models\PlotMergerApplication;
use App\Models\PlotSeparationApplication;
use App\Models\PlotSubdivisionApplication;
use App\Support\FileNumberLandUse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Turns a duplex's holding numbers into real file numbers.
 *
 * The one rule this service exists to honour: there is only ONE commissioning
 * engine. Every file number is still minted by MlsFileNoController
 * (generateBatch / generateMlsFileNumber), because that is where lineage
 * (prop_id, parent_prop_id, related_fileno), decommissioning, PRA rows and the six
 * fixes from the 2026-07-10 parcel-update audit live. Re-implementing any of it
 * here would re-earn every one of those defects.
 *
 * How the chain resolves
 * ----------------------
 * Stages run in RANK order. Stage 1 consumes the duplex's real source files.
 * Every later stage consumes the real file numbers the previous stage just
 * produced — so by the time a stage runs, its input is always a real registry
 * file, which is exactly what the commissioning engine expects.
 *
 * Holding numbers are therefore a planning device only: they let the officer lay
 * out the whole duplex before anything is committed, and at commit each holding
 * row is stamped with the real number it became.
 *
 * Decommissioning falls out of this for free. Each stage decommissions its own
 * input through the existing path, so the original sources are retired by stage 1
 * and every intermediate file is retired by the stage that consumes it.
 */
class DuplexCommitService
{
    public function __construct(
        protected DuplexHoldingNumberService $holding
    ) {}

    /**
     * Commit a duplex. Returns a per-stage summary of what was created and retired.
     *
     * @param array $meta commissioned_by / commission_date / commission_time / customer_type
     */
    public function commit(DuplexParcelUpdate $duplex, array $meta = []): array
    {
        // Approved is enough. The separate "Send to Land" hand-off was removed from the
        // register — Land picks the duplex up from the MLS commissioning modal, which
        // lists approved duplexes — so requiring in_land here would refuse every one of
        // them. in_land is still accepted for duplexes handed over before the change.
        if (!in_array($duplex->status, [
            DuplexParcelUpdate::STATUS_APPROVED,
            DuplexParcelUpdate::STATUS_IN_LAND,
        ], true)) {
            throw new \RuntimeException(
                'A duplex must be approved before it can be commissioned. Current status: ' . $duplex->status
            );
        }

        $stages = $duplex->stageRows()->get(); // the relation already orders by rank

        if ($stages->isEmpty()) {
            throw new \RuntimeException('This duplex has no stages to commission.');
        }

        // Guard the namespace before anything is written: a holding number that has
        // leaked into the registry means a coding slip, and it must surface as a
        // refusal rather than as a poisoned index.
        // Only a real holding number is checked. A file the stage CARRIED through
        // unchanged keeps its own registry number in this column — that is the point of
        // a carried row — so testing every value refused the commissioning of any
        // duplex whose Change of Purpose left a source file alone.
        foreach ($duplex->files as $file) {
            if ($this->holding->isHoldingNumber($file->holding_no)) {
                $this->holding->assertNotInRegistry($file->holding_no);
            }
        }

        $summary = [];

        // Per-file overrides typed on the commissioning modal are indexed across the
        // WHOLE run (file 1..N), while stages consume them a slice at a time — so a
        // cursor walks them forward rather than each stage starting at zero.
        $meta['_entries'] = array_values((array) ($meta['location_entries'] ?? []));
        $meta['_cursor']  = 0;

        // Stage 1's input is the real source file(s) the officer picked.
        $carry = array_values(array_filter((array) ($duplex->source_file_nos ?? [])));

        foreach ($stages as $stage) {
            if (empty($carry)) {
                throw new \RuntimeException(
                    "Stage {$stage->rank} ({$stage->label()}) has no input file to work from."
                );
            }

            $result = $this->commitStage($duplex, $stage, $meta, $carry);

            $summary[] = [
                'rank'           => $stage->rank,
                'type'           => $stage->type,
                'label'          => $stage->label(),
                'inputs'         => $carry,
                'files'          => $result['files'],
                'decommissioned' => $carry,
            ];

            // What this stage produced becomes the next stage's input.
            $carry = $result['files'];
        }

        $duplex->update([
            'status'       => DuplexParcelUpdate::STATUS_COMMITTED,
            'committed_at' => now(),
            'committed_by' => Auth::id(),
            'updated_by'   => Auth::id(),
        ]);

        Log::info('Duplex committed', [
            'duplex_id' => $duplex->duplex_id,
            'stages'    => count($summary),
        ]);

        return $summary;
    }

    /**
     * Run one stage: materialise the application row the commissioning engine reads
     * its lineage from, commission, then stamp the results onto the holding rows.
     */
    protected function commitStage(
        DuplexParcelUpdate $duplex,
        DuplexParcelUpdateStage $stage,
        array &$meta,
        array $inputs
    ): array {
        $appId = $this->materialiseApplication($duplex, $stage, $inputs);

        // The relation already orders by sequence; adding it again would duplicate
        // the ORDER BY column, which SQL Server rejects outright.
        $holdings = $stage->files()
            ->where('role', DuplexParcelUpdateFile::ROLE_HOLDING)
            ->get();


        $files = match ($stage->type) {
            'change_of_purpose' => $this->commissionChangeOfPurpose($duplex, $stage, $inputs, $appId, $meta),
            default             => $this->commissionStandard($duplex, $stage, $inputs, $appId, $holdings->count(), $meta),
        };

        // Stamp each row with the real number it became. Results come back in the same
        // order as the stage's rows, so a carried file is stamped with the number it
        // kept and a changed one with its new number.
        $all = $stage->files()->get()->values();

        foreach ($all as $i => $row) {
            if (!isset($files[$i])) {
                continue;
            }

            $row->update([
                'final_file_no' => $files[$i],
                // A carried file keeps its role: it was never a holding number of this
                // stage's own making, and the Land screen shows it as unchanged.
                'role' => $row->role === DuplexParcelUpdateFile::ROLE_CARRIED
                    ? DuplexParcelUpdateFile::ROLE_CARRIED
                    : DuplexParcelUpdateFile::ROLE_RESULT,
            ]);
        }

        $stage->update([
            'status'       => DuplexParcelUpdateStage::STATUS_DONE,
            'completed_at' => now(),
            'updated_by'   => Auth::id(),
        ]);

        // Move the override cursor past the files this stage minted, so the next stage
        // reads the entries that belong to it rather than starting over at the first.
        $meta['_cursor'] = ($meta['_cursor'] ?? 0) + $holdings->count();

        return ['files' => $files];
    }

    /**
     * Create the row in the existing parcel-update table that the commissioning
     * engine resolves lineage from (it looks the mother/sources up by app id).
     *
     * These rows are real applications — the duplex genuinely performed them — so
     * they are written as approved and tagged with the duplex reference, which
     * keeps the single-workflow registers a truthful record of what happened.
     */
    protected function materialiseApplication(
        DuplexParcelUpdate $duplex,
        DuplexParcelUpdateStage $stage,
        array $inputs
    ): ?int {
        $primary = $inputs[0];
        $tag     = "[Duplex {$duplex->duplex_id} · stage {$stage->rank}]";
        $payload = (array) ($stage->payload ?? []);

        $common = [
            'file_no'        => $primary,
            'file_title'     => $duplex->file_title ?: $duplex->applicant_name,
            'applicant_name' => $duplex->applicant_name,
            'plot_no'        => $duplex->plot_no,
            'house_no'       => $duplex->house_no,
            'street_name'    => $duplex->street_name,
            'district'       => $duplex->district,
            'lga'            => $duplex->lga,
            'state'          => $duplex->state,
            'land_use'       => FileNumberLandUse::codeFor((string) $primary) ?: (string) $duplex->land_use,
            'remarks'        => $tag,
            'knupda_status'  => $duplex->knupda_status,
            'knupda_fee'     => $duplex->knupda_fee,
            'land_value'     => $duplex->land_value,
            'captured_by'    => $duplex->captured_by,
            'updated_by'     => Auth::id(),
        ];

        $plots = (array) ($payload['plots'] ?? []);

        switch ($stage->type) {
            case 'subdivision':
                $app = PlotSubdivisionApplication::create($common + [
                    'num_plots' => $stage->outputCount(),
                    'status'    => PlotSubdivisionApplication::STATUS_APPROVED,
                ]);
                $this->writeSizes($app->id, 'subdivision', 'subdivision_fragment', $plots);
                return $app->id;

            case 'separation':
                $app = PlotSeparationApplication::create($common + [
                    'num_plots' => $stage->outputCount(),
                    'status'    => PlotSeparationApplication::STATUS_APPROVED,
                ]);
                $this->writeSizes($app->id, 'separation', 'separation_fragment', $plots);
                return $app->id;

            case 'merger':
                $app = PlotMergerApplication::create($common + [
                    'temp_file_no' => $stage->input_holding_no ?: $duplex->duplex_id,
                    'num_plots'    => max(2, count($inputs)),
                    'status'       => PlotMergerApplication::STATUS_APPROVED,
                ]);

                // Merger lineage is read back off these rows: the engine collects
                // every source_file_no to build parent_prop_id and related_fileno.
                foreach (array_values($inputs) as $i => $sourceFileNo) {
                    PlotApplicationSize::create([
                        'application_id'    => $app->id,
                        'application_type'  => 'merger',
                        'plot_number'       => $plots[$i]['plot_no'] ?? ('Plot ' . ($i + 1)),
                        'source_file_no'    => $sourceFileNo,
                        'source_file_title' => $plots[$i]['file_title'] ?? '—',
                        'plot_size'         => $plots[$i]['size'] ?? 0,
                        'type'              => 'merger_source',
                    ]);
                }
                return $app->id;

            case 'extension':
                $app = PlotExtensionApplication::create([
                    'file_no'        => $primary,
                    'applicant_name' => $duplex->applicant_name,
                    'land_use'       => FileNumberLandUse::codeFor((string) $primary) ?: (string) $duplex->land_use,
                    'plot_no'        => $duplex->plot_no,
                    'district'       => $duplex->district,
                    'lga'            => $duplex->lga,
                    'status'         => PlotExtensionApplication::STATUS_APPROVED,
                    'captured_by'    => $duplex->captured_by,
                    'updated_by'     => Auth::id(),
                    'remarks'        => $tag,
                ]);
                return $app->id;

            case 'change_of_purpose':
                $app = ChangeOfPurposeApplication::create([
                    'applicant_name' => $duplex->applicant_name,
                    'file_no'        => $primary,
                    'purpose'        => strtoupper((string) ($payload['new_land_use'] ?? '')),
                    'land_use'       => FileNumberLandUse::codeFor((string) $primary) ?: (string) $duplex->land_use,
                    'district'       => $duplex->district,
                    'lga'            => $duplex->lga,
                    'plot_no'        => $duplex->plot_no,
                    'status'         => ChangeOfPurposeApplication::STATUS_APPROVED,
                    'knupda_status'  => $duplex->knupda_status,
                    'knupda_fee'     => $duplex->knupda_fee,
                    'remarks'        => $tag,
                    'captured_by'    => $duplex->captured_by,
                    'updated_by'     => Auth::id(),
                ]);
                return $app->id;
        }

        return null;
    }

    protected function writeSizes(int $appId, string $appType, string $rowType, array $plots): void
    {
        foreach (array_values($plots) as $i => $plot) {
            PlotApplicationSize::create([
                'application_id'   => $appId,
                'application_type' => $appType,
                'plot_number'      => $plot['plot_no'] ?? ('Plot ' . ($i + 1)),
                'plot_size'        => $plot['size'] ?? 0,
                'type'             => $rowType,
            ]);
        }
    }

    /**
     * Subdivision / Separation / Merger / Extension.
     *
     * generateBatch refuses a quantity below 2, so a single-output stage (a merger
     * result, an extension, a one-plot subdivision) goes through the single-file
     * endpoint instead. Both write the same lineage.
     */
    protected function commissionStandard(
        DuplexParcelUpdate $duplex,
        DuplexParcelUpdateStage $stage,
        array $inputs,
        ?int $appId,
        int $quantity,
        array $meta
    ): array {
        $quantity = max(1, $quantity);
        $landUse  = $this->landUseFor($duplex, $stage, $inputs);

        // An extension adjusts ONE parcel's boundary and issues one number, whatever
        // the stage's rows happen to say. Letting a mis-saved second holding row push
        // it down the batch path would mint a fresh serial instead of extending the
        // incoming file.
        $isExtension = $stage->type === 'extension';

        if ($isExtension) {
            $quantity = 1;
        }

        $payload  = (array) ($stage->payload ?? []);
        $plots    = (array) ($payload['plots'] ?? []);

        $appIdField = match ($stage->type) {
            'subdivision' => 'subdivision_app_id',
            'separation'  => 'separation_app_id',
            'merger'      => 'merger_app_id',
            default       => null,
        };

        $base = [
            'application_type'        => $stage->type,
            'file_option'             => $stage->type,
            'land_use'                => $landUse,
            'customer_type'           => $meta['customer_type'] ?? 'Individual',
            'commissioned_by'         => $meta['commissioned_by'] ?? null,
            'commission_date'         => $meta['commission_date'] ?? now()->toDateString(),
            'commission_time'         => $meta['commission_time'] ?? now()->format('H:i'),
            'allocated_by_filter'     => '',
            'default_allocation_type' => null,
        ];

        if ($appIdField && $appId) {
            $base[$appIdField] = $appId;
        }

        if ($isExtension) {
            // The commissioning engine builds an extension's number from the file it
            // extends - "<incoming> AND EXTENSION" - and consumes no serial. It reads
            // that file from existing_file_no, and lineage (parent_prop_id,
            // related_fileno, the PRA row) hangs off the same key, so passing
            // original_file_no alone left the stage unable to commission at all.
            $base['existing_file_no'] = $inputs[0];

            // The " AND EXTENSION" suffix is opt-out on the manual modal only. A duplex
            // extension always takes it: its incoming file is either a source the
            // officer picked or a number a previous stage just commissioned, and both
            // already exist in mls_file_no, where full_file_number is unique.
            $base['suppress_extension_suffix'] = 0;
        }

        if ($quantity < 2) {
            $plot  = $plots[0] ?? [];
            $entry = $this->entryFor($meta, 0);

            $plotNo = $this->pick($entry, 'plotNo', $plot['plot_no'] ?? $duplex->plot_no);

            return $this->callSingle($base + [
                'tracking_id'      => $this->pick($entry, 'tracking_id', $this->trackingIdFor($stage)),
                'file_name'        => $this->pick($entry, 'file_name', $plot['holder'] ?? ($duplex->file_title ?: $duplex->applicant_name)),
                'plot_no'          => $isExtension ? $this->extensionPlotNo($plotNo) : $plotNo,
                'tp_no'            => $this->pick($entry, 'tpNo', null),
                'location'         => $this->pick($entry, 'location', $this->locationFor($duplex)),
                'lga'              => $this->pick($entry, 'lga', $duplex->lga),
                'district'         => $this->pick($entry, 'district', $duplex->district),
                'phone_no'         => $this->pick($entry, 'phone_no', $duplex->phone),
                'address'          => $this->pick($entry, 'address', $duplex->address),
                'gender'           => $meta['gender'] ?? 'Male',
                'original_file_no' => $inputs[0],
            ]);
        }

        $entries = [];
        for ($i = 0; $i < $quantity; $i++) {
            $plot  = $plots[$i] ?? [];
            // Anything typed for this file on the commissioning modal wins; anything
            // left blank falls back to what the duplex captured for it.
            $entry = $this->entryFor($meta, $i);

            $entries[] = [
                'plotNo'    => $this->pick($entry, 'plotNo', $plot['plot_no'] ?? $duplex->plot_no),
                'tpNo'      => $this->pick($entry, 'tpNo', null),
                'location'  => $this->pick($entry, 'location', $this->locationFor($duplex)),
                'lga'       => $this->pick($entry, 'lga', $duplex->lga),
                'district'  => $this->pick($entry, 'district', $duplex->district),
                // The holder may differ per plot — subdivided plots usually go to
                // different people, so the stage captured a name per child.
                'file_name' => $this->pick($entry, 'file_name', $plot['holder'] ?? ($duplex->file_title ?: $duplex->applicant_name)),
                'phone_no'  => $this->pick($entry, 'phone_no', $duplex->phone),
                'address'   => $this->pick($entry, 'address', $duplex->address),
                // Left empty on purpose: the batch path mints a fresh unique tracking id
                // for every entry, and reuses the grouping record's where one exists.
                'tracking_id' => $this->pick($entry, 'tracking_id', null),
            ];
        }

        return $this->callBatch($base + [
            'batch_mode'       => true,
            'batch_quantity'   => $quantity,
            'year'             => (int) date('Y'),
            'serial_start'     => $this->nextSerial($landUse, (int) date('Y')),
            'file_name'        => $duplex->file_title ?: $duplex->applicant_name,
            'location_entries' => $entries,
        ]);
    }

    /**
     * Change of Purpose is a rename in place, one file at a time, so each selected
     * child is its own call. The engine keeps the prop_id (same parcel) and writes
     * the "old -> new" PRA row that anchors the timeline.
     */
    protected function commissionChangeOfPurpose(
        DuplexParcelUpdate $duplex,
        DuplexParcelUpdateStage $stage,
        array $inputs,
        ?int $appId,
        array $meta
    ): array {
        $payload = (array) ($stage->payload ?? []);

        // Each file changes to its OWN new purpose. A duplex may bring several files
        // of different land uses to a common one before a merger, so a single value
        // for the whole stage cannot express what the officer captured.
        //
        // $targets is fileNo => new land use, in input order.
        $targets = $this->changeOfPurposeTargets($stage, $payload, $inputs);

        if (empty($targets)) {
            throw new \RuntimeException("Stage {$stage->rank} (Change of Purpose) has no new land use.");
        }

        // Results must come back in INPUT order, not changed-then-untouched: the stage's
        // file rows are stored in input order, and a later stage lines its inputs up
        // against them positionally.
        $renamed = [];
        $idx     = 0;

        foreach ($targets as $fileNo => $newLandUse) {
            $entry = $this->entryFor($meta, $idx++);

            // A container prefix belongs to the parcel, not to the purpose: a Change
            // of Purpose on CON-AG-1995-15 produces CON-COM-..., never COM-.... It is
            // read per file, because one stage can carry files of different shapes.
            $prefix        = FileNumberLandUse::prefixFor((string) $fileNo);
            $targetLandUse = $prefix !== '' ? $prefix . '-' . $newLandUse : $newLandUse;

            $renamed[$fileNo] = $this->callSingle([
                'application_type'          => 'change_of_purpose',
                'file_option'               => 'normal',
                'land_use'                  => $targetLandUse,
                'original_file_no'          => $fileNo,
                'change_of_purpose_app_id'  => $appId,
                'tracking_id'               => $this->trackingIdFor($stage),
                'file_name'                 => $this->pick($entry, 'file_name', $duplex->file_title ?: $duplex->applicant_name),
                'plot_no'                   => $this->pick($entry, 'plotNo', $duplex->plot_no),
                'tp_no'                     => $this->pick($entry, 'tpNo', null),
                'location'                  => $this->pick($entry, 'location', $this->locationFor($duplex)),
                'lga'                       => $this->pick($entry, 'lga', $duplex->lga),
                'district'                  => $this->pick($entry, 'district', $duplex->district),
                'phone_no'                  => $this->pick($entry, 'phone_no', $duplex->phone),
                'address'                   => $this->pick($entry, 'address', $duplex->address),
                'customer_type'             => $meta['customer_type'] ?? 'Individual',
                'gender'                    => $meta['gender'] ?? 'Male',
                'commissioned_by'           => $meta['commissioned_by'] ?? null,
                'commission_date'           => $meta['commission_date'] ?? now()->toDateString(),
                'commission_time'           => $meta['commission_time'] ?? now()->format('H:i'),
            ])[0] ?? $fileNo;
        }

        // Files this stage did not touch still belong to the duplex and must carry
        // forward, or a later stage would silently lose them — each one keeping the
        // number it arrived with.
        return array_map(fn ($fileNo) => $renamed[$fileNo] ?? $fileNo, $inputs);
    }

    /**
     * Which incoming files a Change of Purpose renames, and what each one becomes.
     *
     * Returns fileNo => new land use, in input order. A file the officer left out is
     * absent from the map: it keeps its number and travels on.
     *
     * The officer captured this against holding numbers (or, for a first-leg CoP,
     * against the real source files), but by commit time the inputs are the REAL
     * numbers the previous stage produced — so each row is matched by the file it
     * named OR by the holding number sitting at the same position.
     */
    protected function changeOfPurposeTargets(
        DuplexParcelUpdateStage $stage,
        array $payload,
        array $inputs
    ): array {
        $rows = array_values((array) ($payload['cop_rows'] ?? []));

        // Captured before per-file purposes existed: one land use for every file the
        // stage applied to. Still commissioned exactly as it was captured.
        if (empty($rows)) {
            $legacy = strtoupper((string) ($payload['new_land_use'] ?? ''));

            if ($legacy === '') {
                return [];
            }

            $selected = (array) ($payload['applies_to'] ?? []);
            $targets  = [];

            foreach ($inputs as $i => $fileNo) {
                $holding = $payload['input_holdings'][$i] ?? null;

                if (empty($selected)
                    || in_array($fileNo, $selected, true)
                    || ($holding && in_array($holding, $selected, true))) {
                    $targets[$fileNo] = $legacy;
                }
            }

            return $targets ?: array_fill_keys($inputs, $legacy);
        }

        // Index the captured rows by every name they might be known under, so a row
        // matches whether it was written against a source file or a holding number.
        $byName = [];

        foreach ($rows as $row) {
            $landUse = strtoupper(trim((string) ($row['new_land_use'] ?? '')));
            $name    = trim((string) ($row['file_no'] ?? ''));

            if ($landUse === '' || $name === '') {
                continue;
            }

            $byName[$name] = $landUse;
        }

        $targets = [];

        foreach ($inputs as $i => $fileNo) {
            $holding = $payload['input_holdings'][$i] ?? null;
            $source  = $payload['sources'][$i] ?? null;

            foreach ([$fileNo, $holding, $source] as $candidate) {
                if ($candidate !== null && isset($byName[$candidate])) {
                    $targets[$fileNo] = $byName[$candidate];
                    break;
                }
            }
        }

        return $targets;
    }

    protected function callBatch(array $payload): array
    {
        $response = app(MlsFileNoController::class)->generateBatch($this->request($payload));
        $data = json_decode($response->getContent(), true) ?: [];

        if (empty($data['success'])) {
            throw new \RuntimeException($data['message'] ?? 'Batch commissioning failed.');
        }

        return array_values((array) ($data['files'] ?? []));
    }

    protected function callSingle(array $payload): array
    {
        $response = app(MlsFileNoController::class)->generateMlsFileNumber($this->request($payload));
        $data = json_decode($response->getContent(), true) ?: [];

        if (empty($data['success'])) {
            throw new \RuntimeException($data['message'] ?? 'Commissioning failed.');
        }

        $fileNo = $data['data']['file_number'] ?? null;

        return $fileNo ? [$fileNo] : [];
    }

    /** An internal request carrying the caller's session, so Auth::id() still resolves. */
    protected function request(array $payload): Request
    {
        $request = Request::create('/internal/duplex-commission', 'POST', $payload);
        $request->setUserResolver(fn () => Auth::user());

        return $request;
    }

    /**
     * A tracking id for a single-file stage.
     *
     * The batch path mints one per file when the grouping record has none; the
     * single-file path refuses to commission without one and will not invent it. That
     * asymmetry is the engine's, not the officer's - so the duplex resolves it here
     * rather than making every merger, extension and Change of Purpose stage ask for
     * a number the system can work out for itself.
     *
     * Order: whatever the stage already carries, then the grouping record, then a
     * fresh unique TRK- id in the same format the batch path uses.
     */
    protected function trackingIdFor(DuplexParcelUpdateStage $stage): string
    {
        if (!empty($stage->tracking_id)) {
            return $stage->tracking_id;
        }

        $inputFile = (string) ($stage->input_holding_no ?? '');

        if ($inputFile !== '') {
            try {
                $grouping = app(\App\Services\GroupingFileNumberService::class)->findGroupingRecord(
                    DB::connection('sqlsrv'),
                    $inputFile,
                    strtoupper(preg_replace('/[^A-Z0-9]/i', '', $inputFile))
                );

                if (!empty($grouping['record']->tracking_id)) {
                    return $grouping['record']->tracking_id;
                }
            } catch (\Throwable $e) {
                Log::warning('Duplex: grouping tracking-id lookup failed', [
                    'file'  => $inputFile,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->mintTrackingId();
    }

    /** TRK-YYMMDDHHMMSS-RAND, checked for collisions - the batch path's format. */
    protected function mintTrackingId(): string
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $random = '';
            for ($i = 0; $i < 6; $i++) {
                $random .= $characters[random_int(0, strlen($characters) - 1)];
            }

            $candidate = 'TRK-' . now()->format('ymdHis') . '-' . $random;

            $taken = DB::connection('sqlsrv')->table('fileNumber')
                ->where('tracking_id', $candidate)->exists();

            if (!$taken) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Could not allocate a unique tracking ID for this stage.');
    }

    /**
     * One file's overrides from the commissioning modal, if the officer typed any.
     *
     * `$offset` is that file's position within the current stage; the run-wide cursor
     * is added to it, so stage 2's first file reads entry 6 when stage 1 produced five.
     */
    protected function entryFor(array $meta, int $offset): array
    {
        $i = ($meta['_cursor'] ?? 0) + $offset;

        return (array) ($meta['_entries'][$i] ?? []);
    }

    /** Blank overrides must not beat a real captured value. */
    protected function pick(array $entry, string $key, $fallback)
    {
        $v = $entry[$key] ?? null;

        return (is_string($v) ? trim($v) : $v) ?: $fallback;
    }

    /**
     * The location written onto every file the duplex commissions.
     *
     * Prefers the value composed at capture (district, LGA, state) and otherwise
     * assembles one from the parts, so a duplex captured before that field existed
     * still lands a usable location rather than a blank.
     *
     * The plot number is deliberately excluded: each file carries its own plot_no, and
     * a subdivision gives every child a different one.
     */
    protected function locationFor(DuplexParcelUpdate $duplex): ?string
    {
        if (!empty($duplex->address)) {
            return $duplex->address;
        }

        $parts = array_filter([
            $duplex->street_name,
            $duplex->district,
            $duplex->lga,
            $duplex->state,
        ]);

        return $parts ? strtoupper(implode(', ', $parts)) : null;
    }

    /**
     * A stage's land use is the new purpose for a Change of Purpose, and otherwise
     * whatever the incoming file already carries.
     */
    /**
     * The plot number an extension writes: "463 & EXTENSION".
     *
     * The marker sits on the PLOT number for every extension, whether or not the file
     * number carries " AND EXTENSION" - that is what identifies the record as an
     * extension - and it mirrors withExtensionPlotSuffix() on the commissioning modal.
     * Idempotent, so a plot number the officer already typed the marker onto does not
     * come out with it twice.
     */
    protected function extensionPlotNo($plotNo): ?string
    {
        $base = trim(preg_replace('/\s*&\s*EXTENSION\s*$/i', '', (string) $plotNo));

        return $base === '' ? null : $base . ' & EXTENSION';
    }

    protected function landUseFor(DuplexParcelUpdate $duplex, DuplexParcelUpdateStage $stage, array $inputs): string
    {
        $payload = (array) ($stage->payload ?? []);

        if (!empty($payload['new_land_use'])) {
            return strtoupper((string) $payload['new_land_use']);
        }

        // Parsed through the shared rule rather than by splitting on "-" here. Taking
        // the first two segments blindly read RES-RC-2004-6 as land use "RES-RC" and
        // would have allocated a merger into a series that does not exist — RC is a
        // middle segment, not a prefix, and 4,498 files carry it.
        $first   = (string) ($inputs[0] ?? '');
        $code    = FileNumberLandUse::codeFor($first);
        $prefix  = FileNumberLandUse::prefixFor($first);

        if ($code === '') {
            // A KANGIS number, or anything else carrying no land use of its own.
            return (string) $duplex->land_use;
        }

        // CON-RES-1984-248 keeps its two-part prefix; RES-1994-762 keeps one.
        return $prefix !== '' ? $prefix . '-' . $code : $code;
    }

    /**
     * Starting serial for a batch. generateBatch skips any serial already taken, so
     * this only has to be a sane floor rather than a guaranteed-free number.
     */
    protected function nextSerial(string $landUse, int $year): int
    {
        $max = DB::connection('sqlsrv')->table('mls_file_no')
            ->where('land_use', $landUse)
            ->where('year', $year)
            ->max('serial_number');

        return ((int) $max) + 1;
    }
}
