<?php

namespace App\Http\Controllers\Deeds\ParcelUpdate;

use App\Http\Controllers\Controller;
use App\Services\Edms\ParcelDocumentIngestService;
use App\Models\DuplexParcelUpdate;
use App\Models\DuplexParcelUpdateFile;
use App\Models\DuplexParcelUpdateStage;
use App\Models\StreetName;
use App\Support\FileNumberLandUse;
use App\Support\MasterJsiGate;
use App\Support\PersonName;
use App\Services\DuplexCommitService;
use App\Services\DuplexSummaryService;
use App\Services\DuplexHoldingNumberService;
use App\Services\ParcelUpdateNotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * Duplex Parcel Update — several parcel updates carried as one instruction, on one
 * page, from capture through to commissioning.
 *
 * The page is self-contained by design: the five single-workflow pages are left
 * untouched, and the Land confirm/reject step lives here rather than as another tab
 * on the commissioning screen. A duplex must carry TWO or more updates: with one
 * there is nothing to combine, and Plot Subdivision / Merger / Extension / Separation
 * each have their own page for that. Duplexes captured before this rule may hold a
 * single stage, and every read path still handles them.
 *
 * Nothing in this controller writes to the registry. Real file numbers appear only
 * when commit() hands the duplex to DuplexCommitService, which delegates to the one
 * existing commissioning engine.
 */
class DuplexParcelUpdateController extends Controller
{
    public function __construct(
        protected ParcelUpdateNotificationService $parcelNotifier,
        protected DuplexHoldingNumberService $holding,
        protected DuplexCommitService $committer
    ) {}

    public function index(Request $request): View
    {
        $limit  = max(10, min((int) $request->input('limit', 50), 200));
        $search = trim((string) $request->input('search'));

        $records = DuplexParcelUpdate::query()
            ->visible()
            ->with('stageRows')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('duplex_id', 'LIKE', "%{$search}%")
                        ->orWhere('applicant_name', 'LIKE', "%{$search}%")
                        ->orWhere('file_title', 'LIKE', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate($limit);

        $states      = DB::connection('sqlsrv')->table('States')->orderBy('StateName')->get();
        $lgas        = DB::connection('sqlsrv')->table('lgas')->where('is_active', 1)->orderBy('name')->get();
        $districts   = DB::connection('sqlsrv')->table('districts')->where('is_active', 1)->orderBy('name')->get();
        $streetNames = StreetName::orderBy('name')->get(['id', 'name'])->toBase();

        $stats = [
            'total'     => DuplexParcelUpdate::visible()->count(),
            'daily'     => DuplexParcelUpdate::visible()->whereDate('created_at', today())->count(),
            'draft'     => DuplexParcelUpdate::visible()->where('status', DuplexParcelUpdate::STATUS_DRAFT)->count(),
            'pending'   => DuplexParcelUpdate::visible()->whereIn('status', [
                DuplexParcelUpdate::STATUS_CAPTURED,
                DuplexParcelUpdate::STATUS_PENDING,
            ])->count(),
            'approved'  => DuplexParcelUpdate::visible()->whereIn('status', [
                DuplexParcelUpdate::STATUS_APPROVED,
                DuplexParcelUpdate::STATUS_IN_LAND,
            ])->count(),
            'committed' => DuplexParcelUpdate::visible()->where('status', DuplexParcelUpdate::STATUS_COMMITTED)->count(),
        ];

        return view('deeds.parcel_update.duplex.index', compact(
            'records', 'limit', 'search', 'states', 'lgas', 'districts', 'streetNames', 'stats'
        ) + ['types' => DuplexParcelUpdate::TYPES]);
    }

    /**
     * Step 1 + 2 — the ticked types with their ranks, and how many of each.
     *
     * Rank comes from the ORDER THE OFFICER TICKED and is stored as given. Nothing
     * downstream may re-derive it from a type list: the officer's order is the
     * execution order, and it is what the memo prints.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'applicant_name'   => 'required|string|max:255',
            // One value per file, or a single value for a one-file duplex.
            'file_title'       => 'nullable',
            'file_title.*'     => 'nullable|string|max:500',
            'source_entries'               => 'nullable|array|max:50',
            'source_entries.*.file_no'     => 'required_with:source_entries|string|max:100',
            'source_entries.*.file_title'  => 'nullable|string|max:500',
            'source_entries.*.plot_no'     => 'nullable|string|max:100',
            'source_entries.*.district'    => 'nullable|string|max:255',
            'source_entries.*.lga'         => 'nullable|string|max:255',
            'source_file_nos'  => 'required|array|min:1',
            'source_file_nos.*' => 'required|string|max:100',
            // TWO or more. A duplex is a combination of parcel updates; with one there
            // is nothing to combine, and the single-workflow page for that update is
            // where it belongs. The wizard disables Start Process on the same rule.
            'stages'           => 'required|array|min:2',
            'stages.*.type'    => 'required|string|in:' . implode(',', array_keys(DuplexParcelUpdate::TYPES)),
            'stages.*.rank'    => 'required|integer|min:1',
            'stages.*.count'   => 'nullable|integer|min:1|max:200',
            // A first-leg Change of Purpose is answered on step 1, while the officer
            // is still looking at the source files, because its holding numbers are
            // minted from the answer.
            'stages.*.cop_rows'                    => 'nullable|array|max:200',
            'stages.*.cop_rows.*.file_no'          => 'required_with:stages.*.cop_rows|string|max:100',
            'stages.*.cop_rows.*.current_land_use' => 'nullable|string|max:50',
            'stages.*.cop_rows.*.new_land_use'     => 'required_with:stages.*.cop_rows|string|max:50',
            // A LATER Change of Purpose cannot name its files at step 1 — they are the
            // previous stage's plots — so it carries a count and the purpose they take.
            'stages.*.new_land_use'                => 'nullable|string|max:50',
            'stages.*.current_land_use'            => 'nullable|string|max:50',
            'plot_no'          => 'nullable',
            'plot_no.*'        => 'nullable|string|max:100',
            'house_no'         => 'nullable|string|max:100',
            'street_name'      => 'nullable|string|max:255',
            'district'         => 'nullable',
            'district.*'       => 'nullable|string|max:255',
            'lga'              => 'nullable',
            'lga.*'            => 'nullable|string|max:255',
            'state'            => 'nullable|string|max:100',
            'phone'            => 'nullable|string|max:50',
            'address'          => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->has('stages')
                    ? 'A duplex carries two or more updates. For a single update, use its own page under Parcel Update — New.'
                    : 'Check the entries and try again.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $data   = $validator->validated();
        $stages = collect($data['stages'])->sortBy('rank')->values();

        // Ranks must be a clean 1..N sequence, or the runner cannot tell which stage
        // feeds which.
        $expected = range(1, $stages->count());
        if ($stages->pluck('rank')->map(fn ($r) => (int) $r)->all() !== $expected) {
            return response()->json([
                'success' => false,
                'message' => 'Stage ranks must run 1 to ' . $stages->count() . ' without gaps.',
            ], 422);
        }

        DB::connection('sqlsrv')->beginTransaction();
        try {
            $duplexId = $this->holding->allocateDuplexId();
            // A KANGIS number carries no land use, so the first source file cannot
            // always answer for the duplex — this application's first two files are
            // KANGIS-numbered. Take the first source that actually has one.
            $landUse = '';
            foreach ($data['source_file_nos'] as $candidate) {
                $landUse = FileNumberLandUse::codeFor($candidate);
                if ($landUse !== '') {
                    break;
                }
            }

            $duplex = DuplexParcelUpdate::create([
                // Which sidebar raised this. Both open the same register, and until this
                // column existed only the page title knew the difference.
                'source_module' => \App\Support\ParcelUpdateSource::fromRequest($request),
                'duplex_id'       => $duplexId,
                // ONE applicant for the whole duplex — a single instruction brought by
                // a single person. The file TITLES differ per file, so those are a list.
                'applicant_name'  => $data['applicant_name'],
                'file_title'      => DuplexParcelUpdate::encodeList($data['file_title'] ?? null)
                    ?: $data['applicant_name'],
                'source_file_nos' => $data['source_file_nos'],
                'stages'          => $stages->all(),
                'status'          => DuplexParcelUpdate::STATUS_DRAFT,
                'land_use'        => $landUse,
                'plot_no'         => DuplexParcelUpdate::encodeList($data['plot_no'] ?? null),
                'house_no'        => $data['house_no'] ?? null,
                'street_name'     => $data['street_name'] ?? null,
                'district'        => DuplexParcelUpdate::encodeList($data['district'] ?? null),
                'lga'             => DuplexParcelUpdate::encodeList($data['lga'] ?? null),
                'state'           => $data['state'] ?? null,
                'phone'           => $data['phone'] ?? null,
                'address'         => $data['address'] ?? null,
                'captured_by'     => Auth::id(),
            ]);

            foreach ($stages as $stage) {
                $copRows = array_values($stage['cop_rows'] ?? []);

                DuplexParcelUpdateStage::create([
                    'duplex_parcel_update_id' => $duplex->id,
                    'duplex_id'   => $duplexId,
                    'type'        => $stage['type'],
                    'rank'        => (int) $stage['rank'],
                    'status'      => DuplexParcelUpdateStage::STATUS_PENDING,
                    // A Change of Purpose answered on step 1 already knows how many
                    // files it changes; that IS its count.
                    'plot_count'  => $copRows
                        ? count($copRows)
                        : (isset($stage['count']) ? (int) $stage['count'] : null),
                    // Seeded, not saved: the stage is still PENDING and step 3 will
                    // write the real payload. This is here so step 3 opens with the
                    // officer's own answer rather than an empty form — the per-file rows
                    // for a first leg, the chosen purpose for a later one.
                    'payload'     => $this->seedStagePayload($stage, $copRows),
                    'captured_by' => Auth::id(),
                ]);
            }

            // The real files this duplex consumes. They are retired at commit by the
            // stage that reads them, not here.
            // Each source file's own title and applicant, kept beside the file rather
            // than only in the duplex's lists — this is what a later screen reads when
            // it needs to say who a PARTICULAR file belongs to.
            $entries = collect($data['source_entries'] ?? [])
                ->keyBy(fn ($e) => trim((string) ($e['file_no'] ?? '')));

            foreach (array_values($data['source_file_nos']) as $i => $fileNo) {
                $entry = (array) ($entries[$fileNo] ?? []);

                DuplexParcelUpdateFile::create([
                    'duplex_parcel_update_id' => $duplex->id,
                    'duplex_id'         => $duplexId,
                    'role'              => DuplexParcelUpdateFile::ROLE_SOURCE,
                    'source_file_no'    => $fileNo,
                    'file_title'        => trim((string) ($entry['file_title'] ?? '')) ?: null,
                    'holder_name'       => $data['applicant_name'],
                    'will_decommission' => 1,
                    'sequence'          => $i,
                ]);
            }

            DB::connection('sqlsrv')->commit();

            Log::info('Duplex created', [
                'duplex_id' => $duplexId,
                'stages'    => $stages->pluck('type')->all(),
                'sources'   => $data['source_file_nos'],
            ]);

            return response()->json([
                'success'   => true,
                'message'   => 'Duplex ' . $duplexId . ' created. Continue with the stages.',
                'duplex_id' => $duplexId,
                'id'        => $duplex->id,
            ]);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            Log::error('Duplex creation failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        $duplex = DuplexParcelUpdate::with(['stageRows.files', 'files'])->findOrFail($id);

        // The RAW applicant_name is left exactly as stored: the wizard reads this
        // response when resuming a duplex, and title-casing the value it fills the form
        // with would rewrite the officer's own entry on the next save. The tidied
        // version rides alongside it for the read-only view modal.
        return response()->json([
            'success' => true,
            'data'    => $duplex->toArray() + [
                'applicant_display' => PersonName::display($duplex->applicant_name),
            ],
        ]);
    }

    /**
     * What a stage opens with on step 3, before it has been captured.
     *
     * A first-leg Change of Purpose brings its per-file rows from step 1. A later one
     * cannot name files there — they do not exist yet — so it brings the purpose those
     * plots take, and step 3 asks only which of them.
     */
    protected function seedStagePayload(array $stage, array $copRows): ?array
    {
        $payload = [];

        if ($copRows) {
            $payload['cop_rows'] = $copRows;
        }

        $newLandUse = strtoupper(trim((string) ($stage['new_land_use'] ?? '')));
        if ($newLandUse !== '') {
            $payload['new_land_use'] = $newLandUse;
        }

        $current = strtoupper(trim((string) ($stage['current_land_use'] ?? '')));
        if ($current !== '') {
            $payload['current_land_use'] = $current;
        }

        return $payload ?: null;
    }

    /**
     * The holding numbers a stage WOULD receive, without issuing them.
     *
     * Lets the wizard show them while the stage is being filled in — the Change of
     * Purpose in particular, whose holding numbers the rest of the plan hangs off.
     * Read-only: it writes nothing, and the real numbers are still minted by saveStage.
     */
    public function holdingPreview(Request $request, int $id, int $stageId): JsonResponse
    {
        $duplex = DuplexParcelUpdate::findOrFail($id);
        $stage  = DuplexParcelUpdateStage::where('duplex_parcel_update_id', $duplex->id)
            ->findOrFail($stageId);

        $count = (int) $request->query('count', 1);
        $count = max(0, min($count, 200));

        // The stage's own rows are excluded because saveStage clears them
        // before allocating: a stage being re-filled reclaims its numbers.
        $numbers = $count === 0
            ? []
            : $this->holding->previewHoldingNumbers($duplex, $count, $stage->id);

        // An extension re-numbers the file it receives, so its holding number is shown
        // the way the file will actually read — suffixed exactly as saveStage mints it,
        // or the preview and the saved plan would disagree.
        if ($stage->type === 'extension') {
            $numbers = $this->holding->withExtensionSuffixes($numbers);
        }

        return response()->json([
            'success' => true,
            'numbers' => $numbers,
        ]);
    }

    /**
     * Re-pick which parcels a Change of Purpose renames.
     *
     * The choice is captured in Deeds, on the stage panel, but Land may correct it at
     * the counter — the officer holding the file and the survey plan is often the one
     * who knows which plots the layout actually makes commercial.
     *
     * WHICH parcels, never HOW MANY. Keeping the count fixed is what makes this safe
     * after approval: the file count, both serial ranges and the approved memo — which
     * states counts and names no plot — all stay true, and no holding number is minted
     * or released. Changing the count would falsify all four, and belongs back in
     * Deeds with a fresh memo.
     *
     * Deliberately narrow: it rewrites the selection and the holders that follow it,
     * and nothing else. Sizes, purposes and the plan itself are not reachable here.
     */
    public function updateSelection(Request $request, int $id, int $stageId): JsonResponse
    {
        $duplex = DuplexParcelUpdate::findOrFail($id);
        $stage  = DuplexParcelUpdateStage::where('duplex_parcel_update_id', $duplex->id)
            ->findOrFail($stageId);

        if (!in_array($duplex->status, [
            DuplexParcelUpdate::STATUS_APPROVED,
            DuplexParcelUpdate::STATUS_IN_LAND,
        ], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Only an approved duplex can have its selection changed. Current status: ' . $duplex->status,
            ], 422);
        }

        if ($stage->type !== 'change_of_purpose') {
            return response()->json([
                'success' => false,
                'message' => 'Only a Change of Purpose chooses which parcels it applies to.',
            ], 422);
        }

        $data = $request->validate([
            'applies_to'   => 'required|array|min:1',
            'applies_to.*' => 'required|string|max:120',
        ]);

        $payload  = (array) ($stage->payload ?? []);
        $incoming = array_values(array_filter((array) ($payload['input_holdings'] ?? [])));

        if (empty($incoming)) {
            $incoming = array_values(array_filter((array) ($payload['sources'] ?? [])))
                ?: array_values(array_filter((array) ($duplex->source_file_nos ?? [])));
        }

        $wanted  = array_values(array_unique($data['applies_to']));
        $current = array_values(array_filter((array) ($payload['applies_to'] ?? [])));

        $unknown = array_values(array_diff($wanted, $incoming));

        if (!empty($unknown)) {
            return response()->json([
                'success' => false,
                'message' => 'These are not parcels this stage receives: ' . implode(', ', $unknown),
            ], 422);
        }

        if (count($wanted) !== count($current)) {
            return response()->json([
                'success' => false,
                'message' => sprintf(
                    'This stage changes the purpose of %d parcel(s), and %d were picked. '
                    . 'Land may change WHICH parcels, not how many — the approved memo and '
                    . 'the file numbers both rest on the count. Send it back to Deeds to change that.',
                    count($current),
                    count($wanted)
                ),
            ], 422);
        }

        // Keep the selection in the order the parcels arrive, because every list that
        // reads it — the rows, the holders, the commit's own walk — is positional.
        $selected = array_values(array_filter($incoming, fn ($f) => in_array($f, $wanted, true)));

        // The holder captured against each parcel travels with it. A parcel that was
        // already changing keeps the name typed for it; a newly picked one starts from
        // the applicant, exactly as the wizard would have offered.
        $holders = [];
        foreach ($current as $i => $fileNo) {
            $holder = data_get($payload, "plots.{$i}.holder");

            if ($holder) {
                $holders[$fileNo] = $holder;
            }
        }

        $plots = array_map(fn ($fileNo) => [
            'size'       => null,
            'dimensions' => [],
            'length'     => null,
            'width'      => null,
            'holder'     => $holders[$fileNo] ?? ($duplex->applicant_name ?: $duplex->file_title),
        ], $selected);

        // The holding numbers this stage already minted, in row order. The count has
        // not changed, so they are re-used as they are — nothing is allocated and
        // nothing is orphaned.
        // The relation already orders by sequence; adding it again duplicates the
        // ORDER BY column, which SQL Server rejects outright.
        $numbers = $stage->files()
            ->where('role', DuplexParcelUpdateFile::ROLE_HOLDING)
            ->pluck('holding_no')
            ->values()
            ->all();

        if (count($numbers) !== count($selected)) {
            return response()->json([
                'success' => false,
                'message' => 'This stage has not been captured yet, so there is no selection to change.',
            ], 422);
        }

        DB::connection('sqlsrv')->transaction(function () use (
            $duplex, $stage, $payload, $incoming, $selected, $plots, $numbers
        ) {
            $payload['applies_to'] = $selected;
            $payload['plots']      = $plots;

            // Who changed it, and from where. Without this the register cannot tell a
            // selection Land corrected from the one Deeds captured and the Commissioner
            // approved.
            $payload['selection_changed_at']    = now()->toDateTimeString();
            $payload['selection_changed_by']    = Auth::id();
            $payload['selection_changed_stage'] = 'land';

            $stage->update(['payload' => $payload, 'updated_by' => Auth::id()]);

            // Rebuild the rows exactly as saveStage() builds them: one row per incoming
            // parcel, in arrival order, holding or carried according to the selection.
            $stage->files()->delete();

            $minted = 0;

            foreach ($incoming as $i => $parcel) {
                $changing = in_array($parcel, $selected, true);
                $at       = array_search($parcel, $selected, true);
                $holder   = $at === false ? null : ($plots[$at]['holder'] ?? null);

                DuplexParcelUpdateFile::create([
                    'duplex_parcel_update_id'       => $duplex->id,
                    'duplex_parcel_update_stage_id' => $stage->id,
                    'duplex_id'   => $duplex->duplex_id,
                    'role'        => $changing
                        ? DuplexParcelUpdateFile::ROLE_HOLDING
                        : DuplexParcelUpdateFile::ROLE_CARRIED,
                    'holding_no'  => $changing ? $numbers[$minted++] : $parcel,
                    'file_title'  => $holder ?? $duplex->file_title,
                    'holder_name' => $holder ?? $duplex->applicant_name,
                    'plot_size'   => null,
                    'will_decommission' => $changing ? 1 : 0,
                    'sequence'    => $i,
                ]);
            }
        });

        Log::info('Duplex Change of Purpose selection changed at the Land step', [
            'duplex_id' => $duplex->duplex_id,
            'stage'     => $stage->rank,
            'from'      => $current,
            'to'        => $selected,
            'user'      => Auth::id(),
        ]);

        return response()->json([
            'success'    => true,
            'message'    => 'Selection updated. ' . count($selected) . ' parcel(s) will change purpose.',
            'applies_to' => $selected,
        ]);
    }

    /**
     * Save one stage and mint its holding numbers.
     *
     * Registry-free by construction: the only writes are to duplex_* tables.
     */
    public function saveStage(Request $request, int $id, int $stageId): JsonResponse
    {
        $duplex = DuplexParcelUpdate::findOrFail($id);
        $stage  = DuplexParcelUpdateStage::where('duplex_parcel_update_id', $duplex->id)->findOrFail($stageId);

        if ($duplex->status === DuplexParcelUpdate::STATUS_COMMITTED) {
            return response()->json(['success' => false, 'message' => 'This duplex has already been commissioned.'], 422);
        }

        $validator = Validator::make($request->all(), [
            'plot_count'        => 'nullable|integer|min:1|max:200',
            'new_land_use'      => 'nullable|string|max:50',
            // The purpose the stage's parcels are changing FROM. A later Change of
            // Purpose has no file rows to read it off, and it is not the duplex's own
            // land use either — the parcels have already been through earlier stages.
            'current_land_use'  => 'nullable|string|max:50',
            'applies_to'        => 'nullable|array',
            'applies_to.*'      => 'nullable|string|max:100',
            // One row per file being changed: each file names its OWN new purpose,
            // because a duplex may bring several land uses to a common one.
            'cop_rows'                  => 'nullable|array|max:200',
            'cop_rows.*.file_no'        => 'required_with:cop_rows|string|max:100',
            'cop_rows.*.current_land_use' => 'nullable|string|max:50',
            'cop_rows.*.new_land_use'   => 'required_with:cop_rows|string|max:50',
            'plots'             => 'nullable|array',
            'plots.*.size'      => 'nullable|numeric|min:0',
            // The parcel's sides, in survey order: "60.00 x 21.00 x 46.00 x 21.00 x 42.71".
            // A polygon, not a rectangle - which is how the Ministry's own memo states
            // them - so this is a LIST, of any length. `size` remains the authority
            // everything downstream reads; where the sides cannot give it (any count but
            // two) the officer enters the measured area from the plan.
            'plots.*.dimensions'   => 'nullable|array|max:24',
            'plots.*.dimensions.*' => 'nullable|numeric|min:0',
            // Still written for a two-sided parcel, so anything reading them keeps working.
            'plots.*.length'    => 'nullable|numeric|min:0',
            'plots.*.width'     => 'nullable|numeric|min:0',
            'plots.*.plot_no'   => 'nullable|string|max:100',
            'plots.*.holder'    => 'nullable|string|max:255',
            'plots.*.file_title' => 'nullable|string|max:500',
            'tracking_id'       => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        // Either shape is acceptable: a row per file (the current capture), or one
        // land use for the whole stage (how duplexes were captured before per-file
        // purposes existed). What is not acceptable is neither.
        if ($stage->type === 'change_of_purpose'
            && empty($data['cop_rows'])
            && empty($data['new_land_use'])) {
            return response()->json([
                'success' => false,
                'message' => 'Say which files are changing purpose, and what each one is changing to.',
            ], 422);
        }

        DB::connection('sqlsrv')->beginTransaction();
        try {
            // reorder() first: the stages relation already sorts by rank ascending, and
            // SQL Server rejects a query naming the same column twice in ORDER BY.
            $previous = $duplex->stageRows()
                ->where('rank', '<', $stage->rank)
                ->reorder('rank', 'desc')
                ->first();

            // What this stage consumes: the previous stage's holding numbers, or the
            // duplex's real source files when this is the first stage.
            $inputHoldings = $previous
                ? $previous->files()->whereNotNull('holding_no')->pluck('holding_no')->all()
                : [];

            $sources = $previous ? [] : array_values((array) ($duplex->source_file_nos ?? []));
            $copRows = array_values($data['cop_rows'] ?? []);

            // A row per file is the authority on which files change; `applies_to` is
            // kept in step with it so everything reading the older field still agrees.
            $appliesTo = $copRows
                ? array_values(array_filter(array_map(
                    fn ($r) => trim((string) ($r['file_no'] ?? '')),
                    $copRows
                )))
                : array_values($data['applies_to'] ?? []);

            // Seeded by store() from the plan, so an unchanged re-save must not drop
            // it: the memo and the conveyance read this to say what the parcels were.
            $currentLandUse = strtoupper(trim((string) ($data['current_land_use'] ?? '')))
                ?: (string) data_get($stage->payload, 'current_land_use', '');

            $payload = [
                'plots'            => array_values($data['plots'] ?? []),
                'new_land_use'     => $data['new_land_use'] ?? null,
                'current_land_use' => $currentLandUse ?: null,
                'cop_rows'       => $copRows,
                'applies_to'     => $appliesTo,
                'input_holdings' => $inputHoldings,
                'sources'        => $sources,
            ];

            $stage->update([
                'plot_count'       => $data['plot_count'] ?? $stage->plot_count,
                'payload'          => $payload,
                'input_holding_no' => $inputHoldings[0] ?? null,
                'tracking_id'      => $data['tracking_id'] ?? $stage->tracking_id,
                'status'           => DuplexParcelUpdateStage::STATUS_DONE,
                'completed_at'     => now(),
                'updated_by'       => Auth::id(),
            ]);

            // Re-running a stage replaces its holding numbers rather than adding to
            // them, so a corrected stage does not leave orphans behind.
            $stage->files()->delete();

            if ($stage->type === 'change_of_purpose') {
                // A Change of Purpose renames ONLY the files it was applied to. Each of
                // those gets a new number and its old one is decommissioned; the rest
                // keep the number the previous stage gave them and simply travel on.
                //
                // Minting a number for every incoming file was wrong: a 5-plot
                // subdivision followed by a 2-file CoP uses 7 numbers, not 10.
                //
                // A FIRST-leg Change of Purpose consumes the real source files rather
                // than holding numbers from a previous stage. It used to fall through
                // to the generic branch, which mints a number for every file and
                // retires all of them — so the files the officer deliberately left
                // alone were counted as changed.
                $incomingFiles = $previous ? $inputHoldings : $sources;

                $selected = array_values($payload['applies_to'] ?? []);
                $numbers  = $this->holding->allocateHoldingNumbers($duplex, max(1, count($selected)));
                $minted   = 0;

                foreach ($incomingFiles as $i => $incoming) {
                    $changing = in_array($incoming, $selected, true);

                    // The Holder typed on the stage's card for THIS file. The cards are
                    // rendered over the files being changed, in the same order as
                    // applies_to (see renderCopPlots), so the plot is found by the
                    // file's position in that list — not by $i, which walks every
                    // incoming file including the ones passing through untouched.
                    $plotIndex = $changing ? array_search($incoming, $selected, true) : false;
                    $plot = $plotIndex === false ? [] : ($payload['plots'][$plotIndex] ?? []);
                    $holder = trim((string) ($plot['holder'] ?? '')) ?: null;

                    DuplexParcelUpdateFile::create([
                        'duplex_parcel_update_id'       => $duplex->id,
                        'duplex_parcel_update_stage_id' => $stage->id,
                        'duplex_id'   => $duplex->duplex_id,
                        'role'        => $changing
                            ? DuplexParcelUpdateFile::ROLE_HOLDING
                            : DuplexParcelUpdateFile::ROLE_CARRIED,
                        'holding_no'  => $changing ? $numbers[$minted++] : $incoming,
                        'file_title'  => $holder ?? $duplex->file_title,
                        'holder_name' => $holder ?? $duplex->applicant_name,
                        'plot_size'   => $plot['size'] ?? null,
                        // Only a file being renamed has an old number to retire.
                        'will_decommission' => $changing ? 1 : 0,
                        'sequence'    => $i,
                    ]);
                }
            } else {
                $outputs = $stage->fresh()->outputCount();
                $numbers = $this->holding->allocateHoldingNumbers($duplex, max(1, $outputs));

                // An Extension stage's holding number carries " AND EXTENSION", because
                // that is what the file it produces will read: commissioning turns the
                // incoming file into "<incoming> AND EXTENSION" rather than minting a
                // number from the series. The serial underneath is untouched.
                if ($stage->type === 'extension') {
                    $numbers = $this->holding->withExtensionSuffixes($numbers);
                }

                foreach ($numbers as $i => $holdingNo) {
                    $plot = $payload['plots'][$i] ?? [];
                    DuplexParcelUpdateFile::create([
                        'duplex_parcel_update_id'       => $duplex->id,
                        'duplex_parcel_update_stage_id' => $stage->id,
                        'duplex_id'         => $duplex->duplex_id,
                        'role'              => DuplexParcelUpdateFile::ROLE_HOLDING,
                        'holding_no'        => $holdingNo,
                        'file_title'        => $plot['holder'] ?? $duplex->file_title,
                        'plot_size'         => $plot['size'] ?? null,
                        'holder_name'       => $plot['holder'] ?? $duplex->applicant_name,
                        'will_decommission' => 1,
                        'sequence'          => $i,
                    ]);
                }
            }

            // All stages done -> the duplex is captured and ready for KAMMA/approval.
            $allDone = $duplex->stageRows()->where('status', '!=', DuplexParcelUpdateStage::STATUS_DONE)->count() === 0;
            if ($allDone && $duplex->status === DuplexParcelUpdate::STATUS_DRAFT) {
                $duplex->update([
                    'status'     => DuplexParcelUpdate::STATUS_CAPTURED,
                    'updated_by' => Auth::id(),
                ]);

                $this->parcelNotifier->notifyCreated(
                    'duplex',
                    $duplex->id,
                    $duplex->duplex_id,
                    (string) $duplex->file_title,
                    (string) $duplex->applicant_name
                );
            }

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success'          => true,
                'message'          => $stage->label() . ' stage saved.',
                // What leaves this stage, in order — new numbers for the files that
                // changed, existing ones for the files that did not.
                'holding_numbers'  => $stage->files()->pluck('holding_no')->all(),
                'all_stages_done'  => $allDone,
            ]);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            Log::error('Duplex stage save failed', [
                'duplex_id' => $duplex->duplex_id,
                'stage'     => $stage->rank,
                'error'     => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Reject a single stage. The rest of the duplex holds its position — only this
     * stage reopens for a re-run.
     */
    public function rejectStage(Request $request, int $id, int $stageId): JsonResponse
    {
        $duplex = DuplexParcelUpdate::findOrFail($id);
        $stage  = DuplexParcelUpdateStage::where('duplex_parcel_update_id', $duplex->id)->findOrFail($stageId);

        $stage->update([
            'status'        => DuplexParcelUpdateStage::STATUS_REJECTED,
            'reject_reason' => trim((string) $request->input('reason', '')) ?: null,
            'updated_by'    => Auth::id(),
        ]);

        // The duplex drops back to draft so the wizard reopens at this stage; the
        // other stages keep their saved payloads and holding numbers.
        $duplex->update([
            'status'     => DuplexParcelUpdate::STATUS_DRAFT,
            'updated_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => $stage->label() . ' stage sent back for re-run. The other stages are untouched.',
        ]);
    }

    public function updateKnupda(Request $request, int $id): JsonResponse
    {
        $duplex = DuplexParcelUpdate::findOrFail($id);

        // The register greys this step out on a draft for the same reason: the stages
        // can still change under it, so there is nothing settled for Physical Planning
        // to clear. The greyed button is the reminder, and this is the rule.
        if ($duplex->status === DuplexParcelUpdate::STATUS_DRAFT) {
            return response()->json([
                'success' => false,
                'message' => 'Finish capturing the stages before recording the KAMMA / Physical Planning clearance.',
            ], 422);
        }

        $duplex->update([
            'land_value'     => $request->input('land_value'),
            'knupda_fee'     => $request->input('knupda_fee'),
            'knupda_status'  => $request->input('knupda_status'),
            'knupda_remarks' => $request->input('knupda_remarks'),
            'updated_by'     => Auth::id(),
        ]);

        return response()->json(['success' => true, 'message' => 'KAMMA status updated.']);
    }

    /** One approval for the whole duplex, by the authority the single workflows use. */
    public function approve(int $id): JsonResponse
    {
        $duplex = DuplexParcelUpdate::findOrFail($id);

        if ($duplex->stageRows()->where('status', '!=', DuplexParcelUpdateStage::STATUS_DONE)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Every stage must be completed before the duplex can be approved.',
            ], 422);
        }

        // Already decided and handed on. Approving again would move approved_by and the
        // timestamps under a duplex Land is already working from.
        if (in_array($duplex->status, [
            DuplexParcelUpdate::STATUS_IN_LAND,
            DuplexParcelUpdate::STATUS_COMMITTED,
        ], true)) {
            return response()->json([
                'success' => false,
                'message' => 'This duplex has already been approved and sent to Land.',
            ], 422);
        }

        // Each of these is a document the approver is meant to have read, and the
        // register's menu greys the action out for the same reasons - but a greyed
        // button is a reminder, and this is the rule. Reported one at a time, in the
        // order the work is done, so the answer is always the next step rather than
        // a list.
        //
        // The conveyance is NOT among them any more: it reports the decision, so it
        // is drawn after the approval, and generateConveyance() holds it there.
        $missing = null;

        if (!MasterJsiGate::clearedFor($duplex, 'duplex')) {
            $missing = 'Capture and approve the Master JSI before approving — it is the planning clearance.';
        } elseif (!$duplex->recommendation_generated_at) {
            $missing = 'Generate the recommendation before approving — the approval is given on the strength of it.';
        } elseif (!$duplex->recommendation_printed_at) {
            // Drawn and read are different things. A memo generated and never printed
            // has been in front of nobody, and the approval is given on the paper.
            $missing = 'Print the recommendation before approving — the approval is given on the printed memo.';
        } elseif (trim((string) $duplex->site_plan) === '') {
            // Also covers a plan removed after capture, which the wizard cannot see.
            $missing = 'Attach the site plan before approving — it is the drawing the recommendation is read against.';
        }

        if ($missing !== null) {
            return response()->json(['success' => false, 'message' => $missing], 422);
        }

        $duplex->update([
            'status'      => DuplexParcelUpdate::STATUS_APPROVED,
            'approved_by' => Auth::id(),
            'updated_by'  => Auth::id(),
        ]);

        $approver = Auth::user();
        $this->parcelNotifier->notifyApproved(
            'duplex',
            $duplex->id,
            $duplex->duplex_id,
            (string) $duplex->file_title,
            $approver ? ($approver->name ?? $approver->username ?? '') : ''
        );

        return response()->json(['success' => true, 'message' => 'Duplex approved.']);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $duplex = DuplexParcelUpdate::findOrFail($id);
        $reason = trim((string) $request->input('reason', ''));

        // Rejecting is a decision on the instruction, so there has to be one. A draft
        // is still being captured — reject the stage from the wizard instead, or delete
        // the duplex. approve() refuses a draft through the same all-stages-done test.
        if ($duplex->status === DuplexParcelUpdate::STATUS_DRAFT) {
            return response()->json([
                'success' => false,
                'message' => 'Finish capturing the stages before approving or rejecting the duplex.',
            ], 422);
        }

        // The decision closes at the hand-off. Once Land holds the duplex it is working
        // from the approval, and once it is committed the file numbers are in the
        // registry — flipping either to `rejected` would leave the register saying the
        // instruction was refused while its files exist and its successors are live.
        if (in_array($duplex->status, [
            DuplexParcelUpdate::STATUS_APPROVED,
            DuplexParcelUpdate::STATUS_IN_LAND,
            DuplexParcelUpdate::STATUS_COMMITTED,
        ], true)) {
            return response()->json([
                'success' => false,
                'message' => match ($duplex->status) {
                    DuplexParcelUpdate::STATUS_COMMITTED => 'This duplex has been commissioned — its file numbers are already in the registry. Roll it back instead of rejecting it.',
                    DuplexParcelUpdate::STATUS_IN_LAND   => 'This duplex has been sent to Land. The decision is behind it and cannot be changed here.',
                    default                              => 'This duplex has already been approved. Send a stage back from the wizard if it has to be reworked.',
                },
            ], 422);
        }

        $duplex->update([
            'status'     => DuplexParcelUpdate::STATUS_REJECTED,
            'remarks'    => $reason ? "Rejected: {$reason}" : 'Rejected',
            'updated_by' => Auth::id(),
        ]);

        return response()->json(['success' => true, 'message' => 'Duplex rejected.']);
    }

    public function generateApplication(int $id): JsonResponse
    {
        $duplex = DuplexParcelUpdate::findOrFail($id);
        $duplex->update(['application_generated_at' => now(), 'updated_by' => Auth::id()]);

        return response()->json(['success' => true, 'message' => 'Application generated.']);
    }

    public function printApplication(int $id): View
    {
        $duplex = DuplexParcelUpdate::with(['stageRows.files'])->findOrFail($id);

        return view('deeds.parcel_update.duplex.print.application', compact('duplex'));
    }

    /**
     * The memo lists the component updates in the officer's ticked order — that
     * order is the instruction, and printing it in any other order misstates it.
     */
    public function generateRecommendation(int $id): JsonResponse
    {
        $duplex = DuplexParcelUpdate::findOrFail($id);

        if (!MasterJsiGate::clearedFor($duplex, 'duplex')) {
            return response()->json([
                'success' => false,
                'message' => 'An approved Master JSI is required before the recommendation can be generated.',
            ], 422);
        }

        $duplex->update(['recommendation_generated_at' => now(), 'updated_by' => Auth::id()]);

        return response()->json(['success' => true, 'message' => 'Recommendation generated.']);
    }

    public function printRecommendation(int $id): View
    {
        $duplex = DuplexParcelUpdate::with(['stageRows.files'])->findOrFail($id);

        // Approval rests on this memo, so the register records that it actually reached
        // paper rather than only that it was drawn. Stamped once: re-printing answers the
        // same question the first print already answered, and moving the timestamp would
        // make a re-print look like the event the approval waited for.
        if (!$duplex->recommendation_printed_at) {
            $duplex->forceFill([
                'recommendation_printed_at' => now(),
                'updated_by' => Auth::id(),
            ])->save();
        }

        return view('deeds.parcel_update.duplex.print.recommendation', compact('duplex'));
    }

    /**
     * The conveyance carries the Commissioner's answer to the applicant, so there has
     * to be an answer first. It used to be drawn before the approval - which asked the
     * officer to write out a decision nobody had taken - and approve() required it in
     * return, holding the two in the wrong order. The register's menu now lists it
     * below Approve, and this is the rule behind that.
     */
    public function generateConveyance(int $id): JsonResponse
    {
        $duplex = DuplexParcelUpdate::findOrFail($id);

        $approved = in_array($duplex->status, [
            DuplexParcelUpdate::STATUS_APPROVED,
            DuplexParcelUpdate::STATUS_IN_LAND,
            DuplexParcelUpdate::STATUS_COMMITTED,
        ], true);

        if (!$approved) {
            return response()->json([
                'success' => false,
                'message' => 'Approve the duplex before generating the conveyance — the conveyance reports the approval.',
            ], 422);
        }

        $duplex->update(['conveyance_generated_at' => now(), 'updated_by' => Auth::id()]);

        return response()->json(['success' => true, 'message' => 'Conveyance generated.']);
    }

    public function printConveyance(int $id): View
    {
        $duplex = DuplexParcelUpdate::with(['stageRows.files'])->findOrFail($id);

        return view('deeds.parcel_update.duplex.print.conveyance', compact('duplex'));
    }

    /** Hand the approved duplex to Land. Still no registry writes. */
    public function sendToLand(int $id): JsonResponse
    {
        $duplex = DuplexParcelUpdate::findOrFail($id);

        if ($duplex->status !== DuplexParcelUpdate::STATUS_APPROVED) {
            return response()->json([
                'success' => false,
                'message' => 'Only an approved duplex can be sent to Land.',
            ], 422);
        }

        if (!$duplex->conveyance_generated_at || !$duplex->recommendation_generated_at) {
            return response()->json([
                'success' => false,
                'message' => 'Generate the recommendation and the conveyance before sending to Land.',
            ], 422);
        }

        $duplex->update([
            'status'        => DuplexParcelUpdate::STATUS_IN_LAND,
            'sent_to_land_at' => now(),
            'updated_by'    => Auth::id(),
        ]);

        return response()->json(['success' => true, 'message' => 'Duplex sent to Land for commissioning.']);
    }

    /**
     * The Land step, in this page — holding numbers on the left, the file numbers
     * they will become on the right, and everything that will be retired listed in
     * execution order. Read-only: Land confirms or rejects the whole duplex.
     */
    public function commissionView(int $id): View
    {
        $duplex = DuplexParcelUpdate::with(['stageRows.files', 'files'])->findOrFail($id);

        return view('deeds.parcel_update.duplex.commission', compact('duplex'));
    }

    /**
     * Approved duplexes, for the Duplex picker on the MLS commissioning modal.
     *
     * A duplex is commissioned from that modal like every other parcel update, so it
     * needs the same "pick an approved application" list the Subdivision, Merger and
     * Separation selectors use.
     */
    public function approvedList(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search'));

        $records = DuplexParcelUpdate::query()
            ->visible()
            ->with('stageRows')
            ->whereIn('status', [
                DuplexParcelUpdate::STATUS_APPROVED,
                DuplexParcelUpdate::STATUS_IN_LAND,
            ])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('duplex_id', 'LIKE', "%{$search}%")
                        ->orWhere('applicant_name', 'LIKE', "%{$search}%")
                        ->orWhere('file_title', 'LIKE', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $records->map(fn ($d) => [
                'id'          => $d->id,
                'duplex_id'   => $d->duplex_id,
                'applicant'   => $d->applicant_name,
                'file_title'  => $d->file_title,
                'sources'     => implode(', ', (array) ($d->source_file_nos ?? [])),
                'status'      => $d->status,
                'stages'      => $d->stageSummary(),
                // NEW file numbers only. Counting every file row double-counts the files
                // a stage carried through unchanged — they already have a number, and
                // commissioning does not mint another for them.
                'file_count'  => $d->files()
                    ->whereNotNull('holding_no')
                    ->where('role', '!=', DuplexParcelUpdateFile::ROLE_CARRIED)
                    ->count(),
            ])->values(),
        ]);
    }

    /**
     * The whole account of a duplex: source parcels, every stage in execution order,
     * the numbers it issued, and everything it retired.
     *
     * Available at any status — before commissioning it reports holding numbers and
     * what is planned, afterwards the real file numbers — because an officer asks
     * "what is this duplex going to do" as often as "what did it do".
     */
    public function summary(int $id): JsonResponse
    {
        $duplex  = DuplexParcelUpdate::with(['stageRows.files', 'files'])->findOrFail($id);
        $service = app(DuplexSummaryService::class);

        return response()->json([
            'success' => true,
            'data'    => $service->build($duplex) + [
                'storage_summary' => $service->storageSummary($duplex),
            ],
        ]);
    }

    /**
     * Attach the recommended site plan to the duplex.
     *
     * ONE drawing for the whole instruction, not one per stage: the stages are legs
     * of a single instruction over the same parcels, and the officer recommending it
     * reads a single application plan showing every portion — plus the extension land
     * where there is an extension. Asking per stage would collect the same sheet
     * several times and leave nothing able to say which copy is the plan of record.
     *
     * Optional at capture, as it is on Merger / Subdivision / Separation: a duplex is
     * often opened before the drawing comes back from Survey, and blocking capture on
     * it would only push officers into holding the whole instruction outside the
     * system until the plan arrives.
     *
     * Uploaded the moment it is chosen rather than at Submit, so a plan attached to a
     * duplex the officer then abandons is still on the row when they resume it.
     */
    public function uploadSitePlan(Request $request, int $id): JsonResponse
    {
        $duplex = DuplexParcelUpdate::findOrFail($id);

        if ($duplex->status === DuplexParcelUpdate::STATUS_COMMITTED) {
            return response()->json([
                'success' => false,
                'message' => 'This duplex has already been commissioned; its site plan can no longer be changed.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            // Same shapes and ceiling as the single workflows, so a plan that uploads
            // on a Merger cannot be refused here.
            'site_plan' => 'required|file|mimes:pdf,png,jpg,jpeg|max:5120',
        ], [
            'site_plan.mimes' => 'The site plan must be a PDF or an image (PNG or JPG).',
            'site_plan.max'   => 'The site plan must be 5 MB or smaller.',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $file     = $request->file('site_plan');
            $filename = 'duplex_' . $duplex->id . '_site_plan_' . time() . '.' . $file->getClientOriginalExtension();
            // Into the EDMS tree, with a scannings/pagetypings pair, so the plan
            // appears in the EDMS workflow and the Virtual Folder System. The duplex
            // row carries no file number of its own — the documents describe the
            // SOURCE file, so that is what the upload is filed under. Falls back to
            // parcel_documents/duplex when that file is not indexed.
            $sourceFileNo = $duplex->files()
                ->whereNotNull('source_file_no')
                ->where('source_file_no', '<>', '')
                ->orderBy('sequence')
                ->value('source_file_no');

            $path = app(ParcelDocumentIngestService::class)->ingest(
                $file, $sourceFileNo, 'site_plan', 'parcel_documents/duplex', $filename, Auth::id()
            );

            // Replacing the plan removes the sheet it replaces: these are large scans,
            // and an orphaned file nothing links to is only ever storage nobody can
            // account for. Done after the new one is safely written.
            $previous = $duplex->site_plan;

            $duplex->update([
                'site_plan'  => $path,
                'updated_by' => Auth::id(),
            ]);

            // NEVER delete a file inside the EDMS tree. Since parcel uploads began
            // landing there, the previous plan may be the same file a scannings /
            // pagetypings row points at, and deleting it would leave a broken page in
            // the archive and the Virtual Folder System. Superseding the plan is this
            // module's business; removing a registered EDMS page is not.
            $previousIsEdms = $previous && str_starts_with($previous, 'EDMS/');

            if ($previous && $previous !== $path && !$previousIsEdms
                && Storage::disk('public')->exists($previous)) {
                Storage::disk('public')->delete($previous);
            }

            Log::info('Duplex site plan uploaded', [
                'duplex_id' => $duplex->duplex_id,
                'path'      => $path,
                'replaced'  => $previous,
            ]);

            return response()->json([
                'success'   => true,
                'message'   => 'Site plan attached to ' . $duplex->duplex_id . '.',
                'site_plan' => $path,
                'url'       => Storage::url($path),
                'name'      => $file->getClientOriginalName(),
            ]);
        } catch (\Exception $e) {
            Log::error('Duplex site plan upload failed', [
                'duplex_id' => $duplex->duplex_id,
                'error'     => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /** Detach the site plan, and remove the file with it. */
    public function deleteSitePlan(int $id): JsonResponse
    {
        $duplex = DuplexParcelUpdate::findOrFail($id);

        if ($duplex->status === DuplexParcelUpdate::STATUS_COMMITTED) {
            return response()->json([
                'success' => false,
                'message' => 'This duplex has already been commissioned; its site plan can no longer be changed.',
            ], 422);
        }

        $path = $duplex->site_plan;

        $duplex->update(['site_plan' => null, 'updated_by' => Auth::id()]);

        // Detaching the plan from the duplex must not destroy the EDMS page. Once
        // parcel uploads land in the EDMS tree, this path can be the same file a
        // scannings / pagetypings row points at, and deleting it would leave a
        // broken page in the archive and the Virtual Folder System. Removing the
        // EDMS page is the archive's decision, made through the archive.
        if ($path && !str_starts_with($path, 'EDMS/') && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        return response()->json(['success' => true, 'message' => 'Site plan removed.']);
    }

    /** One click, one pass: every file number for the whole duplex. */
    public function commit(Request $request, int $id): JsonResponse
    {
        $duplex = DuplexParcelUpdate::with(['stageRows.files', 'files'])->findOrFail($id);

        try {
            $summary = $this->committer->commit($duplex, [
                'commissioned_by' => $request->input('commissioned_by'),
                'commission_date' => $request->input('commission_date'),
                'commission_time' => $request->input('commission_time'),
                'customer_type'   => $request->input('customer_type', 'Individual'),
                'gender'          => $request->input('gender', 'Male'),
                // Per-file overrides typed on the commissioning modal, in generation
                // order. Anything left blank falls back to the duplex's own capture.
                'location_entries' => (array) $request->input('location_entries', []),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Duplex ' . $duplex->duplex_id . ' commissioned.',
                'summary' => $summary,
            ]);
        } catch (\Exception $e) {
            Log::error('Duplex commit failed', [
                'duplex_id' => $duplex->duplex_id,
                'error'     => $e->getMessage(),
                'file'      => $e->getFile() . ':' . $e->getLine(),
            ]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        $duplex = DuplexParcelUpdate::findOrFail($id);

        if ($duplex->status === DuplexParcelUpdate::STATUS_COMMITTED) {
            return response()->json([
                'success' => false,
                'message' => 'A commissioned duplex cannot be deleted.',
            ], 403);
        }

        $duplex->update([
            'is_deleted' => 1,
            'deleted_by' => Auth::id(),
            'deleted_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Duplex deleted.']);
    }
}
