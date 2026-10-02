<?php

namespace App\Http\Controllers;

use App\Services\OpUnlinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * OP Unlink / Reassignment.
 *
 * Five sections, top to bottom:
 *
 *   1  Select Existing File Number   the file the permits are currently on
 *   2  Associated OPs                everything sitting on that file's Property ID
 *   3  Unmatch / Conversion          detach them, individually or as a Group OP
 *   4  Unmatched OPs                 what was just detached, plus anything added by hand
 *   5  Select New File Number        optional — reassign, or stop after unmatching
 *
 * REASSIGNMENT IS OPTIONAL, and that is the rule the whole page is built around.
 * Unlinking a permit is a complete act: the permit had been put on the wrong parcel and
 * now it is not. Where it belongs instead may not be known today. So section 5 never
 * gates section 3, and an officer may stop the moment the unlink is done.
 *
 * WHAT THIS PAGE DOES NOT OWN
 * The file picker, the OP search and the final Match all belong to the Match OP page
 * and are reached at ITS endpoints — /op-propid-match/file, /ops and /batch. Only the
 * two things unique to unlinking live here: listing what is currently associated with a
 * file, and detaching it. A second copy of the matching logic would drift from the
 * first, and both write the same prop_id.
 *
 * @see \App\Services\OpUnlinkService          the detach, and the two modes
 * @see \App\Http\Controllers\OpPropIdMatchController  the file lookup, search and match
 */
class OpUnlinkController extends Controller
{
    private const CONNECTION = 'sqlsrv';

    /** Enough permits to work through; a file with more than this is not a UI problem. */
    private const ASSOCIATED_LIMIT = 300;

    public function __construct(private OpUnlinkService $unlinker)
    {
    }

    public function index()
    {
        return view('op_unlink.index', [
            'PageTitle' => 'OP Unlink / Reassignment',
            'unlinkModes' => OpUnlinkService::MODES,
        ]);
    }

    /**
     * Everything currently associated with a file's Property ID.
     *
     * "Associated", not "matched": a permit can be sitting on this parcel because it was
     * matched here, because it was captured here, or because it has always been here.
     * The page is not asserting how it arrived, only that it is on this parcel now — and
     * the officer is deciding whether it should be.
     *
     * Transfers are listed too, marked but NOT selectable. They are shown because
     * unlinking a permit may take one with it and the officer should see what is on the
     * parcel; they are not selectable because a transfer is not a permit and detaching
     * one on its own would strand a grant with no transfer.
     */
    public function associated(Request $request): JsonResponse
    {
        $propId = trim((string) $request->query('prop_id', ''));

        if ($propId === '' || ! ctype_digit($propId)) {
            return response()->json(['success' => false, 'message' => 'A Property ID is required.'], 422);
        }

        $conn = DB::connection(self::CONNECTION);

        try {
            $praRows = $conn->table('pra as o')
                ->where('o.prop_id', $propId)
                ->where(fn ($q) => $q->whereNull('o.is_deleted')->orWhere('o.is_deleted', 0))
                ->select([
                    'o.id', 'o.prop_id', 'o.instrument_type', 'o.op_type', 'o.op_serial_number',
                    'o.mlsFNo', 'o.fileno', 'o.temp_fileno', 'o.party_1', 'o.party_2',
                    'o.transaction_date', 'o.regNo', 'o.serialNo', 'o.pageNo', 'o.volumeNo',
                    'o.plot_no', 'o.tp_no', 'o.lgsaOrCity as lga', 'o.location',
                    'o.property_description', 'o.land_use', 'o.created_at',
                ])
                ->orderBy('o.id')
                ->limit(self::ASSOCIATED_LIMIT)
                ->get()
                ->map(fn ($r) => $this->shapePra($r));

            $icRows = $conn->table('instrument_capture as o')
                ->where('o.prop_id', (int) $propId)
                ->where('o.instrument_type', \App\Services\OpPropIdMatchService::IC_OP_TYPE)
                ->where(fn ($q) => $q->whereNull('o.is_deleted')->orWhere('o.is_deleted', 0))
                ->select([
                    'o.id', 'o.prop_id', 'o.instrument_type', 'o.op_type', 'o.op_serial_number',
                    'o.mlsFNo', 'o.temp_fileno', 'o.party_1_name', 'o.party_2_name',
                    'o.instrument_date', 'o.registration_number', 'o.serial_no', 'o.page_no',
                    'o.volume_no', 'o.plot_number', 'o.tp_no', 'o.lga', 'o.property_location',
                    'o.property_description', 'o.land_use', 'o.created_at',
                ])
                ->orderBy('o.id')
                ->limit(self::ASSOCIATED_LIMIT)
                ->get()
                ->map(fn ($r) => $this->shapeInstrumentCapture($r));
        } catch (\Throwable $e) {
            Log::channel('op_batch')->error('OP unlink: associated lookup failed', [
                'prop_id' => $propId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'The associated records could not be read: ' . $e->getMessage(),
            ], 500);
        }

        $rows = $praRows->concat($icRows)->values();

        return response()->json([
            'success' => true,
            'prop_id' => (int) $propId,
            'count' => $rows->count(),
            'permits' => $rows->where('is_permit', true)->values()->all(),
            'others' => $rows->where('is_permit', false)->values()->all(),
        ]);
    }

    /** Detach the selected permits from the parcel they are on. */
    public function unlink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mode' => 'required|string|in:' . implode(',', array_keys(OpUnlinkService::MODES)),
            'ops' => 'required|array|min:1',
            'ops.*.source_table' => 'required|string|in:pra,instrument_capture',
            'ops.*.op_id' => 'required|integer|min:1',
            'move_companions' => 'nullable|boolean',
        ], [
            'ops.required' => 'Select at least one OP to unmatch.',
            'mode.required' => 'Choose how the permits should be separated.',
            'mode.in' => 'That is not one of the two ways to separate permits.',
        ]);

        $result = $this->unlinker->unlink(
            $validated['ops'],
            $validated['mode'],
            $request->boolean('move_companions', true)
        );

        if (! $result['ok']) {
            return response()->json(['success' => false, 'message' => $result['message']], 422);
        }

        // The detached permits, shaped exactly like a search result, so the page can drop
        // them straight into section 4 without a second round trip.
        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'batch_ref' => $result['batch_ref'],
            'unlinked' => $result['unlinked'],
            'companions' => $result['companions'],
            'group_prop_id' => $result['group_prop_id'],
            'errors' => $result['errors'],
            'released' => $this->rowsFor($result['assignments']),
        ]);
    }

    /** Recent unlink batches, for the Undo strip. Match batches are a separate list. */
    public function recentBatches(): JsonResponse
    {
        if (! Schema::connection(self::CONNECTION)->hasTable('op_propid_matches')) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $batches = DB::connection(self::CONNECTION)->table('op_propid_matches')
            ->where('batch_ref', 'LIKE', 'OPU-%')
            ->selectRaw('batch_ref, MAX(target_prop_id) AS target_prop_id, COUNT(*) AS records,'
                . ' SUM(CASE WHEN reverted_at IS NULL THEN 0 ELSE 1 END) AS reverted,'
                . ' MAX(created_at) AS created_at, MIN(record_kind) AS record_kind')
            ->groupBy('batch_ref')
            ->orderByDesc(DB::raw('MAX(created_at)'))
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'batch_ref' => $r->batch_ref,
                'prop_id' => (int) $r->target_prop_id,
                'records' => (int) $r->records,
                'grouped' => str_contains((string) $r->record_kind, 'group'),
                'undone' => (int) $r->reverted === (int) $r->records,
                'created_at' => $r->created_at ? \Illuminate\Support\Carbon::parse($r->created_at)->format('d M Y g:i A') : null,
            ]);

        return response()->json(['success' => true, 'data' => $batches]);
    }

    /**
     * Re-read the permits an unlink just moved, so section 4 shows live rows.
     *
     * @param  array<int,array{source_table:string,op_id:int}>  $assignments
     */
    private function rowsFor(array $assignments): array
    {
        if (empty($assignments)) {
            return [];
        }

        $conn = DB::connection(self::CONNECTION);
        $out = [];

        $byTable = [];
        foreach ($assignments as $assignment) {
            $byTable[$assignment['source_table']][] = (int) $assignment['op_id'];
        }

        try {
            if (! empty($byTable['pra'])) {
                $out = array_merge($out, $conn->table('pra as o')
                    ->whereIn('o.id', $byTable['pra'])
                    ->select([
                        'o.id', 'o.prop_id', 'o.instrument_type', 'o.op_type', 'o.op_serial_number',
                        'o.mlsFNo', 'o.fileno', 'o.temp_fileno', 'o.party_1', 'o.party_2',
                        'o.transaction_date', 'o.regNo', 'o.serialNo', 'o.pageNo', 'o.volumeNo',
                        'o.plot_no', 'o.tp_no', 'o.lgsaOrCity as lga', 'o.location',
                        'o.property_description', 'o.land_use', 'o.created_at',
                    ])
                    ->get()
                    ->map(fn ($r) => $this->shapePra($r))
                    ->all());
            }

            if (! empty($byTable['instrument_capture'])) {
                $out = array_merge($out, $conn->table('instrument_capture as o')
                    ->whereIn('o.id', $byTable['instrument_capture'])
                    ->select([
                        'o.id', 'o.prop_id', 'o.instrument_type', 'o.op_type', 'o.op_serial_number',
                        'o.mlsFNo', 'o.temp_fileno', 'o.party_1_name', 'o.party_2_name',
                        'o.instrument_date', 'o.registration_number', 'o.serial_no', 'o.page_no',
                        'o.volume_no', 'o.plot_number', 'o.tp_no', 'o.lga', 'o.property_location',
                        'o.property_description', 'o.land_use', 'o.created_at',
                    ])
                    ->get()
                    ->map(fn ($r) => $this->shapeInstrumentCapture($r))
                    ->all());
            }
        } catch (\Throwable $e) {
            Log::channel('op_batch')->warning('OP unlink: could not re-read released permits', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        // Permits only. A transfer that travelled with one is not something the officer
        // reassigns on its own; it follows its permit again on the next match.
        return array_values(array_filter($out, fn ($row) => $row['is_permit']));
    }

    /**
     * The row shape the page speaks, identical to the Match page's OP search rows so the
     * same renderer draws both.
     */
    private function shapePra($r): array
    {
        $type = (string) ($r->instrument_type ?? '');

        return [
            'source_table' => 'pra',
            'op_id' => (int) $r->id,
            'prop_id' => trim((string) ($r->prop_id ?? '')),
            'instrument_type' => $type,
            'is_permit' => stripos($type, 'Occupancy Permit') !== false,
            'op_type' => trim((string) ($r->op_type ?? '')),
            'op_serial_number' => trim((string) ($r->op_serial_number ?? '')),
            'file_no' => $this->firstNonEmpty([$r->mlsFNo ?? null, $r->fileno ?? null, $r->temp_fileno ?? null]),
            'temp_file_no' => $this->firstNonEmpty([$r->temp_fileno ?? null, $r->fileno ?? null]),
            'grantor' => trim((string) ($r->party_1 ?? '')),
            'holder' => trim((string) ($r->party_2 ?? '')),
            'transaction_date' => $r->transaction_date ?: null,
            'reg_particulars' => $this->regParticulars($r->serialNo ?? null, $r->pageNo ?? null, $r->volumeNo ?? null, $r->regNo ?? null),
            'plot_no' => trim((string) ($r->plot_no ?? '')),
            'tp_no' => trim((string) ($r->tp_no ?? '')),
            'lga' => trim((string) ($r->lga ?? '')),
            'location' => $this->firstNonEmpty([$r->location ?? null, $r->property_description ?? null]),
            'land_use' => trim((string) ($r->land_use ?? '')),
            'companions' => null,
            'created_at' => $r->created_at,
        ];
    }

    private function shapeInstrumentCapture($r): array
    {
        $type = (string) ($r->instrument_type ?? '');

        return [
            'source_table' => 'instrument_capture',
            'op_id' => (int) $r->id,
            'prop_id' => trim((string) ($r->prop_id ?? '')),
            'instrument_type' => $type,
            'is_permit' => stripos($type, 'Occupancy Permit') !== false,
            'op_type' => trim((string) ($r->op_type ?? '')),
            'op_serial_number' => trim((string) ($r->op_serial_number ?? '')),
            'file_no' => $this->firstNonEmpty([$r->mlsFNo ?? null, $r->temp_fileno ?? null]),
            'temp_file_no' => trim((string) ($r->temp_fileno ?? '')),
            'grantor' => trim((string) ($r->party_1_name ?? '')),
            'holder' => trim((string) ($r->party_2_name ?? '')),
            'transaction_date' => $r->instrument_date ?: null,
            'reg_particulars' => $this->regParticulars($r->serial_no ?? null, $r->page_no ?? null, $r->volume_no ?? null, $r->registration_number ?? null),
            'plot_no' => trim((string) ($r->plot_number ?? '')),
            'tp_no' => trim((string) ($r->tp_no ?? '')),
            'lga' => trim((string) ($r->lga ?? '')),
            'location' => $this->firstNonEmpty([$r->property_location ?? null, $r->property_description ?? null]),
            'land_use' => trim((string) ($r->land_use ?? '')),
            'companions' => null,
            'created_at' => $r->created_at,
        ];
    }

    private function firstNonEmpty(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            $value = trim((string) $candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function regParticulars($serial, $page, $volume, $regNo): string
    {
        $serial = trim((string) $serial);
        $page = trim((string) $page);
        $volume = trim((string) $volume);

        if ($serial !== '' || $page !== '' || $volume !== '') {
            return trim(($serial !== '' ? $serial : '—') . '/' . ($page !== '' ? $page : '—') . '/' . ($volume !== '' ? $volume : '—'));
        }

        return trim((string) $regNo);
    }
}
