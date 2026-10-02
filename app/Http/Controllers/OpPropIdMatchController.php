<?php

namespace App\Http\Controllers;

use App\Services\OpPropIdMatchService;
use App\Services\OpUnlinkService;
use App\Services\PropertyIdAllocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * OP → File Property ID Matching.
 *
 * A two-panel page. On the left the officer finds the CONFIRMED file and loads its
 * Property ID; that is the control record and the target. On the right they search
 * the Occupancy Permits — by serial number first, because that is what is printed on
 * the permit in front of them — tick the ones that belong to the file, and press
 * Batch Match. Every ticked permit is moved onto the file's Property ID.
 *
 * WHY SERIAL, NOT NAME
 * OP serials are heavily reused (roughly three permits per serial, some carry twenty),
 * so a serial search returns a shortlist rather than an answer — which is exactly what
 * is wanted here, because the officer is choosing. Names are far worse: they are
 * transliterated inconsistently and a name search on 36k permits is a fishing trip.
 * Name is offered as a NARROWING filter, never as the primary one.
 *
 * TEMPORARY FILE NUMBERS
 * A permit captured before its file existed carries a TEMP-xxxxx in `fileno` and
 * `temp_fileno` while the confirmed number, if it was ever written back, sits in
 * `mlsFNo`. All three are searched, so a permit is findable by whichever number the
 * officer happens to be holding.
 *
 * @see \App\Services\OpPropIdMatchService  the rule and the write
 */
class OpPropIdMatchController extends Controller
{
    private const CONNECTION = 'sqlsrv';

    /** How many OP candidates a search hands back. Enough to choose from, not to browse. */
    private const OP_LIMIT = 200;

    /**
     * Every file_indexings column that can hold the number the file picker returns.
     * The picker's tabs hand back an MLS number, a KANGIS number or a New KANGIS
     * number depending on which one the officer used, and only the first of those is
     * what `file_number` holds.
     */
    private const INDEXING_ALIAS_COLUMNS = [
        'file_number',
        'mls_file_no',
        'kangis_file_no',
        'new_kangis_file_no',
        'temp_file_no',
    ];

    /** The PropID_Master columns that can carry a file's number, plus their _norm twins. */
    private const MASTER_ALIAS_COLUMNS = [
        'primary_file_number',
        'mlsFNo',
        'kangisFileNo',
        'NewKANGISFileno',
        'temp_fileno',
    ];

    public function __construct(private OpPropIdMatchService $matcher)
    {
    }

    public function index()
    {
        return view('op_propid_match.index', [
            'PageTitle' => 'Match OP (FC)',
            // The one kind of match this page performs, named from the service so the
            // badge on the page and the value in the audit trail cannot drift apart.
            'matchModeLabel' => OpPropIdMatchService::MATCH_MODE_LABEL,
        ]);
    }


    /**
     * Load one file as the target: its Property ID, and what is already sitting on it.
     *
     * PropID_Master is the authority. file_indexings.prop_id is NOT consulted — a bulk
     * import wrote row ordinals into that column on thousands of files, and reading it
     * here would aim a batch of permits at a number that identifies nothing.
     *
     * A FILE WITH NO REGISTERED PROPERTY ID GETS A PREVIEW, NOT A WRITE.
     *
     * An unregistered file is common and unremarkable here — the picker offers KANGIS and
     * New KANGIS tabs, and a land file, its Old KANGIS file and its New KANGIS file each
     * hold their OWN prop_id by design (three physical files over one parcel, linked
     * upward by parent_prop_id). So rather than refuse, this returns the id the file WOULD
     * be given, flagged `prop_id_proposed`. Nothing is written: merely looking at a file
     * must never consume a parcel id, or an afternoon of browsing burns a hundred of them.
     *
     * The preview is settled at match time by resolveTargetPropId(), which registers it —
     * or a different one, if it was claimed in the meantime.
     *
     * The related numbers are still listed either way. They matter: a KANGIS number with
     * no id of its own often sits beside a land file that already has one, and matching to
     * the wrong one of the two splits the file instead of consolidating it.
     */
    public function fileTarget(Request $request): JsonResponse
    {
        $fileNo = trim((string) $request->query('file_no', ''));

        if ($fileNo === '') {
            return response()->json(['success' => false, 'message' => 'A file number is required.'], 422);
        }

        $conn = DB::connection(self::CONNECTION);

        $mls = $conn->table('mls_file_no')
            ->whereRaw('LTRIM(RTRIM(full_file_number)) = ?', [$fileNo])
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->orderByDesc('id')
            ->first();

        // Every column of file_indexings that can hold the number the selector handed
        // back. The picker's KANGIS and New KANGIS tabs return a KANGIS number, which is
        // NOT what file_number holds — matching that column alone would 404 a file that
        // is plainly indexed, on the tab the officer was invited to use.
        $indexing = $conn->table('file_indexings')
            ->where(function ($q) use ($fileNo) {
                foreach (self::INDEXING_ALIAS_COLUMNS as $column) {
                    $q->orWhereRaw("LTRIM(RTRIM($column)) = ?", [$fileNo]);
                }
            })
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->orderByDesc('id')
            ->first();

        if (! $mls && ! $indexing) {
            return response()->json([
                'success' => false,
                'message' => 'No confirmed file with that number. A file must be in MLPP File Numbers '
                    . 'or File Indexing before permits can be matched to it.',
            ], 404);
        }

        // Every number this physical file is known by, the picked one first. Each is
        // looked up separately: they are DIFFERENT parcels in PropID_Master by design,
        // so this is a list to show the officer, never a set to collapse into one answer.
        $related = $this->relatedFileNumbers($fileNo, $mls, $indexing);
        $propIds = $this->propIdsForFileNumbers($related);
        $propId = $propIds[mb_strtoupper($fileNo)] ?? null;

        // NOT registered yet: show the number this file WOULD be given, rather than an
        // empty target and a warning. Nothing is written here — the id is only reserved
        // when the officer actually matches, so opening a file to look at it never
        // consumes a parcel id.
        //
        // The preview can go stale (another officer registers it first), which is exactly
        // why batchMatch() re-resolves before it writes and mints a fresh id if this one
        // has been taken since.
        $proposed = $propId === null;
        if ($proposed) {
            $propId = $this->nextAvailablePropId();
        }

        // What the file is called in its own indexing row. When the officer picked a
        // KANGIS number, this is the land file number behind it — worth showing, because
        // the permits they are about to move will be carrying THAT one.
        $canonical = trim((string) ($indexing->file_number ?? ''));

        return response()->json([
            'success' => true,
            'data' => [
                'file_number' => $fileNo,
                'also_known_as' => ($canonical !== '' && mb_strtoupper($canonical) !== mb_strtoupper($fileNo)) ? $canonical : null,
                // True means this id is a PREVIEW, not yet registered against the file.
                // The page shows it as the target; the server confirms or replaces it at
                // match time.
                'prop_id_proposed' => $proposed,
                'related' => collect($related)
                    ->reject(fn ($number) => mb_strtoupper($number) === mb_strtoupper($fileNo))
                    ->map(fn ($number) => [
                        'file_number' => $number,
                        'prop_id' => $propIds[mb_strtoupper($number)] ?? null,
                    ])
                    ->values()
                    ->all(),
                'file_title' => trim((string) ($mls->file_name ?? $indexing->file_title ?? '')),
                'land_use' => trim((string) ($mls->land_use ?? $indexing->land_use_type ?? '')),
                'lga' => trim((string) ($mls->lga ?? $indexing->lga ?? '')),
                'location' => trim((string) ($mls->location ?? $indexing->location ?? '')),
                'plot_no' => trim((string) ($mls->plot_no ?? $indexing->plot_number ?? '')),
                'district' => trim((string) ($mls->district ?? $indexing->district ?? '')),
                'commissioned' => (bool) ($mls->commissioning_date ?? false),
                'in_mls' => (bool) $mls,
                'indexed' => (bool) $indexing,
                'prop_id' => $propId,
                // A proposed id has nothing on it by definition; skip the lookup.
                'existing' => ($propId && ! $proposed) ? $this->recordsOnPropId($propId) : [],
            ],
        ]);
    }

    /**
     * Advanced OP search.
     *
     * Serial is the primary key into this and can be a LIST — a permit book hands the
     * officer a run of serials, and typing them one at a time is how a batch gets half
     * done. Split on comma, semicolon and newline ONLY: KANGIS-era numbers contain
     * spaces, so splitting on whitespace would tear them in half.
     */
    public function searchOps(Request $request): JsonResponse
    {
        $serialInput = trim((string) $request->query('serial', ''));
        $fileNo = trim((string) $request->query('file_no', ''));
        $party = trim((string) $request->query('party', ''));
        $propId = trim((string) $request->query('prop_id', ''));
        $plotNo = trim((string) $request->query('plot_no', ''));
        $mode = in_array($request->query('serial_mode'), ['exact', 'starts', 'contains'], true)
            ? $request->query('serial_mode')
            : 'exact';
        $excludePropId = trim((string) $request->query('exclude_prop_id', ''));

        // "Everything linked to this file", asked when a file is selected rather than
        // typed as a search. A permit belongs to a file in TWO independent ways and
        // neither implies the other:
        //
        //   it sits on the file's parcel id      — it has been matched
        //   it carries the file's NUMBER         — it was captured against the file but
        //                                          never matched, so it still holds its
        //                                          own parcel id
        //
        // The second is the whole reason this page exists, so the two are OR'd. Asking
        // for them with the ordinary AND filters would return only permits that are both
        // — which is the already-correct ones, and none of the ones needing work.
        // (IND-2026-434: one permit carries the file number on parcel 147255 while the
        // file itself is 99000004. An AND search finds nothing.)
        $associated = $request->boolean('associated');

        // Matched permits are LISTED, not hidden. This page does both halves of the job
        // now: an already-matched permit is what an unmatch acts on, so hiding it would
        // put the fix out of reach. Each row says which it is (is_matched) and the batch
        // offers the action that fits.

        $serials = $this->splitList($serialInput);

        if (! $associated && empty($serials) && $fileNo === '' && $party === '' && $propId === '' && $plotNo === '') {
            return response()->json([
                'success' => false,
                'message' => 'Give at least one of: OP serial number, Temp FileNo, holder name, plot number, or Property ID.',
            ], 422);
        }

        // A name on its own would sweep 36k permits. It narrows a search; it does not make one.
        if (! $associated && empty($serials) && $fileNo === '' && $propId === '' && $plotNo === '' && mb_strlen($party) < 3) {
            return response()->json([
                'success' => false,
                'message' => 'A holder name on its own must be at least 3 characters — or add a serial, file number or Property ID.',
            ], 422);
        }

        $conn = DB::connection(self::CONNECTION);

        try {
            $praRows = $this->searchPra($conn, $serials, $mode, $fileNo, $party, $propId, $plotNo, $excludePropId, $associated);
            $icRows = $this->searchInstrumentCapture($conn, $serials, $mode, $fileNo, $party, $propId, $plotNo, $excludePropId, $associated);
        } catch (\Throwable $e) {
            Log::channel('op_batch')->error('OP prop_id match: OP search failed', [
                'serials' => $serials,
                'file_no' => $fileNo,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'The OP search could not be run: ' . $e->getMessage(),
            ], 500);
        }

        $rows = $praRows->concat($icRows)
            ->sortBy([['op_serial_number', 'asc'], ['op_id', 'desc']])
            ->take(self::OP_LIMIT)
            ->values();

        $rows = $this->attachCompanionCounts($conn, $rows);

        Log::channel('op_batch')->info('OP prop_id match: OP search', [
            'serials' => count($serials),
            'file_no' => $fileNo,
            'party' => $party !== '' ? 'yes' : 'no',
            'hits' => $rows->count(),
        ]);

        // Either sub-query coming back FULL means the database had more to give — the
        // sum overflowing the cap is not the only way this search was cut short, and a
        // page that under-reports truncation is a page that hides permits the officer
        // believes they have seen all of.
        $truncated = $praRows->count() >= self::OP_LIMIT
            || $icRows->count() >= self::OP_LIMIT
            || $praRows->count() + $icRows->count() > self::OP_LIMIT;

        return response()->json([
            'success' => true,
            'count' => $rows->count(),
            'truncated' => $truncated,
            'data' => $rows,
        ]);
    }

    /** Move the selected OPs onto the target file's Property ID. */
    public function batchMatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file_no' => 'nullable|string|max:100',
            'prop_id' => 'required|integer|min:1',
            'ops' => 'required|array|min:1',
            'ops.*.source_table' => 'required|string|in:pra,instrument_capture',
            'ops.*.op_id' => 'required|integer|min:1',
            'move_companions' => 'nullable|boolean',
            // Says the prop_id above is a PREVIEW the page showed for a file that had
            // none. It is a hint, not an instruction — the id is settled below.
            'prop_id_proposed' => 'nullable|boolean',
            // No match_mode is accepted from the browser. There is one kind of match and
            // the service sets it, so there is nothing here for a caller to get wrong.
        ], [
            'ops.required' => 'Tick at least one OP before matching.',
            'prop_id.required' => 'Select a confirmed file on the left first.',
        ]);

        $fileNo = isset($validated['file_no']) ? trim((string) $validated['file_no']) : null;

        try {
            $resolved = $this->resolveTargetPropId(
                $fileNo,
                (int) $validated['prop_id'],
                $request->boolean('prop_id_proposed')
            );
        } catch (\Throwable $e) {
            Log::channel('op_batch')->error('OP prop_id match: could not settle the target Property ID', [
                'file_no' => $fileNo,
                'requested' => $validated['prop_id'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'The file could not be given a Property ID, so nothing was matched: ' . $e->getMessage(),
            ], 422);
        }

        $propId = $resolved['prop_id'];

        if ($propId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'No usable Property ID for this file, so nothing was matched.',
            ], 422);
        }

        $result = $this->matcher->batchMatch(
            $propId,
            $fileNo,
            $validated['ops'],
            $request->boolean('move_companions', true)
        );

        if (! $result['ok']) {
            return response()->json(['success' => false, 'message' => $result['message']], 422);
        }

        return response()->json([
            'success' => true,
            // A substituted id is said plainly and first: the officer matched against a
            // number on screen, and if it is not the number that was written they must be
            // told, not left to notice.
            'message' => trim(($resolved['message'] ? $resolved['message'] . ' ' : '') . $result['message']),
            'batch_ref' => $result['batch_ref'],
            'moved' => $result['moved'],
            'companions' => $result['companions'],
            'skipped' => $result['skipped'],
            'errors' => $result['errors'],
            // The id actually used, so the page can correct its target card.
            'prop_id' => $propId,
            'prop_id_changed' => $resolved['changed'],
            'existing' => $this->recordsOnPropId($propId),
        ]);
    }

    /**
     * Unmatch: take the selected permits back off whatever parcel they are on.
     *
     * The other half of this page. A permit put on the wrong file is corrected here
     * rather than on a screen of its own — it is the same officer, the same permits and
     * the same parcel ids, and the batch they are looking at is the one to act on.
     *
     * Two ways to separate, and the choice is the officer's:
     *   individual  each permit becomes its own parcel, with its own Property ID
     *   group       all of them share ONE new Property ID — a Group OP
     *
     * Group is meaningless for a single permit and is refused there, so the page never
     * offers it. Reassigning afterwards is optional: an unmatched permit simply sits in
     * the batch, and the officer may match it to a file or stop.
     */
    public function batchUnmatch(Request $request): JsonResponse
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
        ]);

        $result = app(OpUnlinkService::class)->unlink(
            $validated['ops'],
            $validated['mode'],
            $request->boolean('move_companions', true)
        );

        if (! $result['ok']) {
            return response()->json(['success' => false, 'message' => $result['message']], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'batch_ref' => $result['batch_ref'],
            'unlinked' => $result['unlinked'],
            'companions' => $result['companions'],
            'group_prop_id' => $result['group_prop_id'],
            'errors' => $result['errors'],
            // Where each permit landed, so the batch on screen can redraw without a
            // second round trip and without guessing.
            'assignments' => $result['assignments'],
        ]);
    }

    /** The last few batches this page performed, newest first, for the Undo strip. */
    public function recentBatches(): JsonResponse
    {
        if (! Schema::connection(self::CONNECTION)->hasTable('op_propid_matches')) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $batches = DB::connection(self::CONNECTION)->table('op_propid_matches')
            // Matches only. The Unlink page writes OPU- batches to this same ledger and
            // lists them on its own strip; showing them here would offer "undo" on a
            // record this page never touched.
            ->where('batch_ref', 'LIKE', 'OPM-%')
            ->selectRaw('batch_ref, MAX(target_file_number) AS target_file_number, MAX(target_prop_id) AS target_prop_id,'
                . ' COUNT(*) AS records, SUM(CASE WHEN reverted_at IS NULL THEN 0 ELSE 1 END) AS reverted,'
                . ' MAX(created_at) AS created_at')
            ->groupBy('batch_ref')
            ->orderByDesc(DB::raw('MAX(created_at)'))
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'batch_ref' => $r->batch_ref,
                'file_number' => $r->target_file_number,
                'prop_id' => (int) $r->target_prop_id,
                'records' => (int) $r->records,
                'undone' => (int) $r->reverted === (int) $r->records,
                'created_at' => $r->created_at ? \Illuminate\Support\Carbon::parse($r->created_at)->format('d M Y g:i A') : null,
            ]);

        return response()->json(['success' => true, 'data' => $batches]);
    }

    /** Put one batch back. */
    public function undoBatch(Request $request): JsonResponse
    {
        $validated = $request->validate(['batch_ref' => 'required|string|max:40']);

        $result = $this->matcher->undo(trim($validated['batch_ref']));

        return response()->json(
            ['success' => $result['ok'], 'message' => $result['message']],
            $result['ok'] ? 200 : 422
        );
    }

    // ---------------------------------------------------------------- search internals

    private function searchPra($conn, array $serials, string $mode, string $fileNo, string $party, string $propId, string $plotNo, string $excludePropId, bool $associated = false)
    {
        $query = $conn->table('pra as o')
            ->where('o.instrument_type', 'LIKE', OpPropIdMatchService::PRA_OP_TYPE_LIKE)
            ->where(fn ($q) => $q->whereNull('o.is_deleted')->orWhere('o.is_deleted', 0));

        if ($associated) {
            $this->applyAssociatedFilter($query, ['o.mlsFNo', 'o.fileno', 'o.temp_fileno'], $fileNo, $propId);

            return $this->mapPraRows($query);
        }

        $this->applySerialFilter($query, 'o.op_serial_number', $serials, $mode);
        $this->applyFileNumberFilter($query, ['o.mlsFNo', 'o.fileno', 'o.temp_fileno'], $fileNo);
        $this->applyPartyFilter($query, ['o.party_1', 'o.party_2', 'o.Grantee', 'o.Assignee'], $party);

        if ($propId !== '') {
            $query->where('o.prop_id', $propId);
        }

        // Plot numbers are entered inconsistently ("3E", "3 E", "PLOT 3E"), so this
        // matches on containment rather than equality. `pra` spells the column
        // plot_no; instrument_capture spells it plot_number - hence the two branches.
        if ($plotNo !== '') {
            $query->where('o.plot_no', 'LIKE', '%' . $plotNo . '%');
        }

        if ($excludePropId !== '') {
            $query->where(function ($q) use ($excludePropId) {
                $q->whereNull('o.prop_id')->orWhere('o.prop_id', '<>', $excludePropId);
            });
        }

        return $this->mapPraRows($query);
    }

    private function searchInstrumentCapture($conn, array $serials, string $mode, string $fileNo, string $party, string $propId, string $plotNo, string $excludePropId, bool $associated = false)
    {
        $query = $conn->table('instrument_capture as o')
            ->where('o.instrument_type', OpPropIdMatchService::IC_OP_TYPE)
            ->where(fn ($q) => $q->whereNull('o.is_deleted')->orWhere('o.is_deleted', 0));

        if ($associated) {
            // prop_id is bigint here; a non-numeric parcel id can only be the file-number
            // arm's business, so it is dropped rather than handed to the driver.
            $this->applyAssociatedFilter(
                $query,
                ['o.mlsFNo', 'o.temp_fileno'],
                $fileNo,
                ctype_digit($propId) ? $propId : ''
            );

            return $this->mapIcRows($query);
        }

        $this->applySerialFilter($query, 'o.op_serial_number', $serials, $mode);
        $this->applyFileNumberFilter($query, ['o.mlsFNo', 'o.temp_fileno'], $fileNo);
        $this->applyPartyFilter($query, ['o.party_1_name', 'o.party_2_name'], $party);

        // prop_id is bigint here, so a non-numeric filter cannot match anything and must
        // not be handed to the driver as a comparison.
        if ($propId !== '') {
            if (! ctype_digit($propId)) {
                return collect();
            }
            $query->where('o.prop_id', (int) $propId);
        }

        // instrument_capture spells this column plot_number, not plot_no.
        if ($plotNo !== '') {
            $query->where('o.plot_number', 'LIKE', '%' . $plotNo . '%');
        }

        if ($excludePropId !== '' && ctype_digit($excludePropId)) {
            $query->where(function ($q) use ($excludePropId) {
                $q->whereNull('o.prop_id')->orWhere('o.prop_id', '<>', (int) $excludePropId);
            });
        }

        return $this->mapIcRows($query);
    }

    /** Shape `pra` OP rows for the page. Shared by the search and the associated lookup. */
    private function mapPraRows($query)
    {
        return $query->select([
                'o.id', 'o.prop_id', 'o.op_type', 'o.op_serial_number', 'o.mlsFNo', 'o.fileno', 'o.temp_fileno',
                'o.party_1', 'o.party_2', 'o.transaction_date', 'o.regNo', 'o.serialNo', 'o.pageNo', 'o.volumeNo',
                'o.plot_no', 'o.tp_no', 'o.lgsaOrCity as lga', 'o.location', 'o.property_description',
                'o.land_use', 'o.op_batch', 'o.created_at',
            ])
            ->orderByDesc('o.id')
            ->limit(self::OP_LIMIT)
            ->get()
            ->map(fn ($r) => [
                'source_table' => 'pra',
                'op_id' => (int) $r->id,
                'prop_id' => trim((string) ($r->prop_id ?? '')),
                'op_type' => trim((string) ($r->op_type ?? '')),
                'op_serial_number' => trim((string) ($r->op_serial_number ?? '')),
                'file_no' => $this->firstNonEmpty([$r->mlsFNo, $r->fileno, $r->temp_fileno]),
                // The temporary number only. `fileno` is NOT a fallback here: on most rows
                // it holds the confirmed number, and showing that in the TEMP badge would
                // label a settled file as provisional.
                'temp_file_no' => trim((string) ($r->temp_fileno ?? '')),
                'grantor' => trim((string) ($r->party_1 ?? '')),
                'holder' => trim((string) ($r->party_2 ?? '')),
                'transaction_date' => $r->transaction_date ?: null,
                'reg_particulars' => $this->regParticulars($r->serialNo ?? null, $r->pageNo ?? null, $r->volumeNo ?? null, $r->regNo ?? null),
                'plot_no' => trim((string) ($r->plot_no ?? '')),
                'tp_no' => trim((string) ($r->tp_no ?? '')),
                'lga' => trim((string) ($r->lga ?? '')),
                'location' => $this->firstNonEmpty([$r->location, $r->property_description]),
                'land_use' => trim((string) ($r->land_use ?? '')),
                'op_batch' => trim((string) ($r->op_batch ?? '')),
                'created_at' => $r->created_at,
            ]);
    }

    /** Shape `instrument_capture` OP rows for the page. */
    private function mapIcRows($query)
    {
        return $query->select([
                'o.id', 'o.prop_id', 'o.op_type', 'o.op_serial_number', 'o.mlsFNo', 'o.temp_fileno',
                'o.party_1_name', 'o.party_2_name', 'o.instrument_date', 'o.registration_number',
                'o.serial_no', 'o.page_no', 'o.volume_no', 'o.plot_number', 'o.tp_no',
                'o.lga', 'o.property_location', 'o.property_description', 'o.land_use', 'o.created_at',
            ])
            ->orderByDesc('o.id')
            ->limit(self::OP_LIMIT)
            ->get()
            ->map(fn ($r) => [
                'source_table' => 'instrument_capture',
                'op_id' => (int) $r->id,
                'prop_id' => trim((string) ($r->prop_id ?? '')),
                'op_type' => trim((string) ($r->op_type ?? '')),
                'op_serial_number' => trim((string) ($r->op_serial_number ?? '')),
                'file_no' => $this->firstNonEmpty([$r->mlsFNo, $r->temp_fileno]),
                'temp_file_no' => trim((string) ($r->temp_fileno ?? '')),
                'grantor' => trim((string) ($r->party_1_name ?? '')),
                'holder' => trim((string) ($r->party_2_name ?? '')),
                'transaction_date' => $r->instrument_date ?: null,
                'reg_particulars' => $this->regParticulars($r->serial_no ?? null, $r->page_no ?? null, $r->volume_no ?? null, $r->registration_number ?? null),
                'plot_no' => trim((string) ($r->plot_number ?? '')),
                'tp_no' => trim((string) ($r->tp_no ?? '')),
                'lga' => trim((string) ($r->lga ?? '')),
                'location' => $this->firstNonEmpty([$r->property_location, $r->property_description]),
                'land_use' => trim((string) ($r->land_use ?? '')),
                'op_batch' => '',
                'created_at' => $r->created_at,
            ]);
    }

    /**
     * Everything linked to one file: on its parcel, OR carrying its number.
     *
     * The two are OR'd because they are independent facts and the interesting permits
     * satisfy only ONE of them:
     *
     *   on the parcel, carrying the number   already matched — correct, shown for context
     *   carrying the number, other parcel    captured against the file, never matched —
     *                                        the work this page exists to do
     *   on the parcel, some other number     matched from a temp number that was never
     *                                        written back
     *
     * The file number is matched EXACTLY, not by LIKE. A prefix match would pull
     * RES-2023-31890 in on a search for RES-2023-3189 and offer an unrelated parcel for
     * unmatching. Several numbers may be given (the file's own plus its KANGIS aliases),
     * comma-separated.
     *
     * Serial is deliberately not part of this: the officer picked a FILE, and a permit
     * belongs to it by parcel or by number — never by a serial, which is reused across
     * thousands of unrelated permits.
     */
    private function applyAssociatedFilter($query, array $fileColumns, string $fileNumbers, string $propId): void
    {
        $numbers = $this->splitList($fileNumbers);

        // Nothing to key on: refuse rather than return the whole table.
        if (empty($numbers) && $propId === '') {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function ($outer) use ($fileColumns, $numbers, $propId) {
            if ($propId !== '') {
                $outer->orWhere('o.prop_id', $propId);
            }

            foreach ($fileColumns as $column) {
                foreach ($numbers as $number) {
                    $outer->orWhereRaw("LTRIM(RTRIM($column)) = ?", [$number]);
                }
            }
        });
    }

    /**
     * Serials, matched the way the officer asked.
     *
     * Exact is the default and is what a permit in hand calls for; the two LIKE forms
     * are for a serial that was written down with a prefix or a suffix nobody kept.
     */
    private function applySerialFilter($query, string $column, array $serials, string $mode): void
    {
        if (empty($serials)) {
            return;
        }

        $query->where(function ($q) use ($column, $serials, $mode) {
            foreach ($serials as $serial) {
                if ($mode === 'exact') {
                    $q->orWhereRaw("LTRIM(RTRIM($column)) = ?", [$serial]);
                } else {
                    $pattern = $mode === 'starts'
                        ? $this->escapeLike($serial) . '%'
                        : '%' . $this->escapeLike($serial) . '%';
                    $q->orWhere(DB::raw("LTRIM(RTRIM($column))"), 'LIKE', $pattern);
                }
            }
        });
    }

    /**
     * A file number, matched against every column that can hold one.
     *
     * Temporary numbers matter here: a permit captured before its file existed carries
     * TEMP-xxxxx and nothing else, and it is the permit most in need of this page.
     */
    private function applyFileNumberFilter($query, array $columns, string $fileNo): void
    {
        if ($fileNo === '') {
            return;
        }

        $pattern = '%' . $this->escapeLike($fileNo) . '%';

        $query->where(function ($q) use ($columns, $fileNo, $pattern) {
            foreach ($columns as $column) {
                $q->orWhereRaw("LTRIM(RTRIM($column)) = ?", [$fileNo])
                    ->orWhere($column, 'LIKE', $pattern);
            }
        });
    }

    private function applyPartyFilter($query, array $columns, string $party): void
    {
        if ($party === '') {
            return;
        }

        $pattern = '%' . $this->escapeLike($party) . '%';

        $query->where(function ($q) use ($columns, $pattern) {
            foreach ($columns as $column) {
                $q->orWhere($column, 'LIKE', $pattern);
            }
        });
    }

    /**
     * How many rows would travel with each permit if Batch Match is pressed.
     *
     * Shown per row so the officer sees the true size of the change before making it —
     * a batch of eight permits that quietly moves twelve rows is a surprise, and this
     * page's whole job is to stop parcel ids being changed by surprise.
     */
    private function attachCompanionCounts($conn, $rows)
    {
        if ($rows->isEmpty()) {
            return $rows;
        }

        // ONE query for the whole page, not one per row: this list runs to 200 permits
        // and a per-row count would be 200 round trips for a decoration.
        $opIds = $rows->pluck('op_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $propIds = $rows->pluck('prop_id')->filter(fn ($v) => trim((string) $v) !== '')->unique()->values()->all();

        try {
            $candidates = $conn->table('pra')
                ->select('id', 'source_op_table', 'source_op_id', 'prop_id', 'instrument_type')
                ->where(function ($q) use ($opIds, $propIds) {
                    $q->where(function ($pointer) use ($opIds) {
                        $pointer->whereIn('source_op_id', $opIds)
                            ->whereIn('source_op_table', OpPropIdMatchService::OP_TABLES);
                    });

                    if (! empty($propIds)) {
                        $q->orWhere(function ($shared) use ($propIds) {
                            $shared->whereIn('prop_id', $propIds)
                                ->where('instrument_type', 'LIKE', '%Transfer of Title%');
                        });
                    }
                })
                ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
                ->get();
        } catch (\Throwable $e) {
            // A count is decoration. Failing it must not lose the officer their search.
            Log::channel('op_batch')->warning('OP prop_id match: companion count failed', [
                'error' => $e->getMessage(),
            ]);

            // Unknown, not false: claiming a matched permit is unmatched would offer the
            // wrong action on it. The page treats null as "cannot tell" and asks.
            return $rows->map(function ($row) {
                $row['companions'] = null;
                $row['is_matched'] = null;

                return $row;
            });
        }

        // Distinct rows per permit, matching OpPropIdMatchService::findCompanions exactly:
        // a ToT that BOTH points at the permit and shares its prop_id is one companion,
        // not two, and a permit is never its own companion.
        return $rows->map(function ($row) use ($candidates) {
            $propId = trim((string) $row['prop_id']);

            $ids = $candidates->filter(function ($candidate) use ($row, $propId) {
                if ($row['source_table'] === 'pra' && (int) $candidate->id === (int) $row['op_id']) {
                    return false;
                }

                $byPointer = (string) $candidate->source_op_table === $row['source_table']
                    && (int) $candidate->source_op_id === (int) $row['op_id'];

                // The shared-prop_id arm counts Transfers of Title only. The query above
                // pulled non-ToT rows in too — they were fetched by the pointer arm, for
                // some OTHER permit — so the type has to be re-checked here or a pointer
                // row would be counted against every permit that shares its parcel id.
                $byPropId = $propId !== ''
                    && trim((string) $candidate->prop_id) === $propId
                    && stripos((string) $candidate->instrument_type, 'Transfer of Title') !== false;

                return $byPointer || $byPropId;
            })->pluck('id')->unique();

            $row['companions'] = $ids->count();

            // MATCHED means the permit has been given its file, and there are two
            // independent proofs of that. Either is enough:
            //
            //   1. it carries a REAL file number — not a TEMP-xxxxx. A temporary number
            //      is what a permit holds until its file is confirmed, so a permit
            //      showing a confirmed number has already been placed. This is the
            //      common case by far: 4,569 permits, against 492 with a transfer.
            //   2. a Transfer of Title has been written against it, by pointer or by
            //      shared parcel id — the companion set just counted.
            //
            // Rule 1 alone matters most here: the KN81 permits carry a real file number
            // and no transfer, and calling them unmatched offered to match them to a file
            // they were already on.
            $row['is_matched'] = $this->hasRealFileNumber($row['file_no']) || $ids->count() > 0;

            return $row;
        });
    }

    // ---------------------------------------------------------------- shared helpers

    /**
     * prop_id for a set of file numbers, read from PropID_Master only.
     *
     * @param  array<int,string>  $fileNumbers
     * @return array<string,int>  keyed by UPPERCASED file number
     */
    private function propIdsForFileNumbers(array $fileNumbers): array
    {
        $wanted = [];
        foreach ($fileNumbers as $fileNumber) {
            $value = mb_strtoupper(trim((string) $fileNumber));
            if ($value !== '') {
                $wanted[$value] = true;
            }
        }

        if (empty($wanted) || ! $this->masterExists()) {
            return [];
        }

        $wanted = array_keys($wanted);
        $columns = array_merge(
            self::MASTER_ALIAS_COLUMNS,
            array_map(fn ($c) => $c . '_norm', self::MASTER_ALIAS_COLUMNS)
        );

        $out = [];

        try {
            DB::connection(self::CONNECTION)->table('PropID_Master')
                ->select(array_merge(['prop_id'], self::MASTER_ALIAS_COLUMNS))
                ->where(function ($q) use ($columns, $wanted) {
                    foreach ($columns as $column) {
                        $q->orWhereIn($column, $wanted);
                    }
                })
                ->orderBy('updated_at')
                ->get()
                ->each(function ($row) use (&$out) {
                    foreach (self::MASTER_ALIAS_COLUMNS as $column) {
                        $alias = mb_strtoupper(trim((string) ($row->{$column} ?? '')));
                        if ($alias !== '') {
                            $out[$alias] = (int) $row->prop_id;
                        }
                    }
                });
        } catch (\Throwable $e) {
            Log::channel('op_batch')->warning('OP prop_id match: PropID_Master lookup failed', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        // Only the numbers actually asked for; the loop above also indexed every other
        // alias on the same master rows.
        return array_intersect_key($out, array_flip($wanted));
    }

    /** What already sits on the target Property ID, so the officer sees the file's current shape. */
    private function recordsOnPropId(int $propId): array
    {
        $conn = DB::connection(self::CONNECTION);
        $value = (string) $propId;

        try {
            $rows = $conn->table('pra')
                ->where('prop_id', $value)
                ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
                ->select('id', 'instrument_type', 'op_serial_number', 'mlsFNo', 'fileno', 'temp_fileno', 'party_1', 'party_2', 'transaction_date')
                ->orderBy('id')
                ->limit(50)
                ->get();

            $ic = $conn->table('instrument_capture')
                ->where('prop_id', $propId)
                ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
                ->select('id', 'instrument_type', 'op_serial_number', 'mlsFNo', 'temp_fileno', 'party_1_name', 'party_2_name', 'instrument_date')
                ->orderBy('id')
                ->limit(50)
                ->get();
        } catch (\Throwable $e) {
            return [];
        }

        $out = $rows->map(fn ($r) => [
            'source_table' => 'pra',
            'id' => (int) $r->id,
            'instrument_type' => trim((string) ($r->instrument_type ?? '')),
            'op_serial_number' => trim((string) ($r->op_serial_number ?? '')),
            'file_no' => $this->firstNonEmpty([$r->mlsFNo, $r->fileno, $r->temp_fileno]),
            'party_1' => trim((string) ($r->party_1 ?? '')),
            'party_2' => trim((string) ($r->party_2 ?? '')),
            'date' => $r->transaction_date ?: null,
        ])->concat($ic->map(fn ($r) => [
            'source_table' => 'instrument_capture',
            'id' => (int) $r->id,
            'instrument_type' => trim((string) ($r->instrument_type ?? '')),
            'op_serial_number' => trim((string) ($r->op_serial_number ?? '')),
            'file_no' => $this->firstNonEmpty([$r->mlsFNo, $r->temp_fileno]),
            'party_1' => trim((string) ($r->party_1_name ?? '')),
            'party_2' => trim((string) ($r->party_2_name ?? '')),
            'date' => $r->instrument_date ?: null,
        ]));

        return $out->values()->all();
    }

    /**
     * Every number this one physical file is known by, the picked one first.
     *
     * Used to SHOW the officer the alternatives when the number they picked has no
     * Property ID of its own — never to pick one on their behalf. A land file, its Old
     * KANGIS file and its New KANGIS file are three files over one parcel and hold three
     * distinct prop_ids deliberately, so collapsing them here would silently retarget the
     * batch at a file the officer did not choose.
     *
     * @return array<int,string>
     */
    private function relatedFileNumbers(string $fileNo, $mls, $indexing): array
    {
        $numbers = [$fileNo];

        foreach (self::INDEXING_ALIAS_COLUMNS as $column) {
            $numbers[] = trim((string) ($indexing->{$column} ?? ''));
        }

        $numbers[] = trim((string) ($mls->full_file_number ?? ''));
        $numbers[] = trim((string) ($mls->old_fileno ?? ''));

        // The KANGIS link, which is NOT one of the flat columns above. A land file's
        // KANGIS number lives in related_fileno as a JSON array, and for many files it is
        // the ONLY thing tying it to its permits: RES-2016-2818 has every alias column
        // empty and related_fileno ["KN81"], while all 27 of its Occupancy Permits carry
        // KN81. Reading only the flat columns showed the officer an empty list on a file
        // with thirty permits on it.
        foreach ($this->parseRelatedFileno($indexing->related_fileno ?? null) as $related) {
            $numbers[] = $related;
        }

        $seen = [];
        $out = [];

        foreach ($numbers as $number) {
            $upper = mb_strtoupper($number);
            if ($number === '' || isset($seen[$upper])) {
                continue;
            }
            $seen[$upper] = true;
            $out[] = $number;
        }

        return $out;
    }

    /**
     * The prop_id the allocator would hand out next.
     *
     * Deliberately the SAME rule PropertyIdAllocationService uses — MAX(prop_id) + 1 over
     * PropID_Master — so the number previewed on the page is the number actually minted.
     * A prettier preview computed a different way would simply be a lie the officer
     * discovers after pressing Batch Match.
     *
     * Nothing is reserved: this reads, it does not write. Two officers previewing at once
     * see the same number, and the first to match gets it — which is what the re-resolve
     * in resolveTargetPropId() exists to handle.
     *
     * NOTE: the running maximum currently sits in the 99,000,00x band, seeded by a
     * ZZTEST row, so this returns a number from that band. That is a pre-existing defect
     * in the allocator affecting every capture screen, not something this page invents —
     * and it is not quietly worked around here, because a page that skipped the band
     * would disagree with the id the allocator then writes.
     */
    private function nextAvailablePropId(): int
    {
        if (! $this->masterExists()) {
            return 1;
        }

        try {
            $max = (int) DB::connection(self::CONNECTION)->table('PropID_Master')
                ->where('prop_id', '<', 2147483647)  // stay inside the column's 32-bit int
                ->max('prop_id');

            return $max > 0 ? $max + 1 : 1;
        } catch (\Throwable $e) {
            Log::channel('op_batch')->warning('OP prop_id match: could not read the next Property ID', [
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Settle the target Property ID at the moment of writing.
     *
     * A registered id is trusted as given. A PROPOSED one — previewed while the file had
     * none — is re-checked here, because minutes may have passed:
     *
     *   1. the file may have been registered since, by this page or any other capture
     *      screen. That id wins; the preview is discarded.
     *   2. the previewed number may have been taken by a DIFFERENT file. Then it is not
     *      ours to use and a fresh one is minted.
     *   3. otherwise the allocator registers the file and returns its id — which is what
     *      makes the number real, so the next officer to preview gets a different one.
     *
     * @return array{prop_id:int,changed:bool,message:?string}
     */
    private function resolveTargetPropId(?string $fileNo, int $requested, bool $proposed): array
    {
        if (! $proposed || $fileNo === null || $fileNo === '') {
            return ['prop_id' => $requested, 'changed' => false, 'message' => null];
        }

        // (1) Registered while the officer was working?
        $registered = $this->propIdsForFileNumbers([$fileNo])[mb_strtoupper($fileNo)] ?? null;

        if ($registered !== null) {
            return [
                'prop_id' => (int) $registered,
                'changed' => (int) $registered !== $requested,
                'message' => (int) $registered !== $requested
                    ? 'This file was given Property ID ' . $registered . ' while you were working, so that one was used instead of ' . $requested . '.'
                    : null,
            ];
        }

        // (2)+(3) Claim it properly. allocateOrRetrievePropId is idempotent per file
        // number and takes MAX+1 under a lock, so it cannot return an id another file
        // already holds — which covers the "taken since you looked" case without a
        // separate check that could itself go stale between reading and writing.
        $allocated = (int) app(PropertyIdAllocationService::class)->allocateOrRetrievePropId($fileNo, $fileNo);

        Log::channel('op_batch')->info('OP prop_id match: registered a Property ID at match time', [
            'file_no' => $fileNo,
            'previewed' => $requested,
            'allocated' => $allocated,
            'user' => \Illuminate\Support\Facades\Auth::id(),
        ]);

        return [
            'prop_id' => $allocated,
            'changed' => $allocated !== $requested,
            'message' => $allocated !== $requested
                ? 'Property ID ' . $requested . ' had been taken since you opened the file, so this file was given ' . $allocated . ' instead.'
                : null,
        ];
    }

    /**
     * related_fileno holds a JSON array on newer rows and a comma list on older ones.
     * Same rule as KangisLandPairService::parseRelatedFileno, which is where this shape
     * is documented.
     *
     * Split on comma only — never whitespace. KANGIS numbers contain spaces ("KNML 1"),
     * and splitting on those tears one number into two that match nothing.
     *
     * @return array<int,string>
     */
    private function parseRelatedFileno($raw): array
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return [];
        }

        if ($raw[0] === '[') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return array_values(array_filter(
                    array_map(fn ($v) => trim((string) $v), $decoded),
                    fn ($v) => $v !== '' && $v !== '-'
                ));
            }
        }

        return array_values(array_filter(
            array_map('trim', explode(',', $raw)),
            fn ($v) => $v !== '' && $v !== '-'
        ));
    }

    /**
     * Is this a confirmed file number, rather than a placeholder?
     *
     * A permit captured before its file existed carries TEMP-xxxxx. Anything else — an
     * MLS number, a KANGIS number, an old KN number — is a real file, and a permit
     * holding one has been placed on it.
     *
     * The prefix is matched loosely (TEMP-, TEMP_, "TEMP ") because the capture screens
     * that write it are not consistent, and a placeholder read as a real number is the
     * expensive direction of the mistake: it would mark an unmatched permit as matched
     * and offer to unlink a file it was never on.
     */
    private function hasRealFileNumber($fileNo): bool
    {
        $value = trim((string) $fileNo);

        if ($value === '' || $value === '-') {
            return false;
        }

        return ! preg_match('/^TEMP[-_ ]?/i', $value);
    }

    private function masterExists(): bool
    {
        try {
            return Schema::connection(self::CONNECTION)->hasTable('PropID_Master');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Split a typed list on comma, semicolon and newline — never on whitespace.
     * KANGIS-era numbers contain spaces ("KNML 1"), and splitting those tears them apart.
     *
     * @return array<int,string>
     */
    private function splitList(string $input): array
    {
        if (trim($input) === '') {
            return [];
        }

        $parts = preg_split('/[,;\r\n]+/', $input) ?: [];

        return array_values(array_unique(array_filter(array_map('trim', $parts), fn ($v) => $v !== '')));
    }

    /** LIKE metacharacters are literal when an officer types them into a search box. */
    private function escapeLike(string $value): string
    {
        return str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $value);
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
