<?php

namespace App\Http\Controllers\Deeds\ParcelUpdate;

use App\Http\Controllers\Controller;
use App\Services\Edms\ParcelDocumentIngestService;
use App\Models\PlotSubdivisionApplication;
use App\Models\PlotApplicationSize;
use App\Models\StreetName;
use App\Services\ParcelUpdateNotificationService;
use App\Services\TitleStatusParcelRouter;
// Plot Subdivision logging goes to its own file (storage/logs/plot_subdivision.log),
// not laravel.log — see config/logging.php channel "plot_subdivision".
use App\Support\ParcelSizeSummary;
use App\Support\PlotSubdivisionLog as Log;
use App\Support\MasterJsiGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PlotSubdivisionController extends Controller
{
    /**
     * Files the MLS number generator will mint in one batch run
     * (MlsFileNoController::generateBatch validates batch_quantity max:200).
     * A subdivision bigger than this is commissioned in several runs.
     */
    public const BATCH_CAP = 200;

    /**
     * Child plots one subdivision application may hold.
     *
     * A hard maximum, not a performance hint: the capture form, the validator and the
     * bulk insert are all sized against this number, and it is the figure the end-to-end
     * test was run at. Raising it means re-timing the save, not just editing this line.
     *
     * Unrelated to BATCH_CAP above -- that is how many FILE NUMBERS are minted per
     * commissioning run, so a full 1000-plot application is commissioned in 5 runs.
     */
    public const MAX_PLOTS = 1000;

    /**
     * Rows per INSERT when writing child plots.
     *
     * SQL Server caps a single statement at 2100 parameters. Each child row binds 8
     * columns, so 200 rows = 1600 parameters, comfortably inside the limit with room for
     * a column to be added later. Same reasoning as the commissioning inserts in
     * MlsFileNoController::generateBatch (array_chunk 90/140/90 for 21/14/20 columns).
     */
    private const INSERT_CHUNK = 200;

    public function __construct(
        protected ParcelUpdateNotificationService $parcelNotifier
    ) {}

    public function index(Request $request)
    {
        $limit = max(10, min((int) $request->input('limit', 50), 200));
        $search = trim((string) $request->input('search'));

        $records = PlotSubdivisionApplication::query()
            ->where(function($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            // Hide rows routed in from Title Status / File Indexing until processed.
            ->where(function($q) {
                $q->whereNull('status')->orWhere('status', '!=', TitleStatusParcelRouter::HIDDEN_STATUS);
            })
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('applicant_name', 'LIKE', "%{$search}%")
                        ->orWhere('file_no', 'LIKE', "%{$search}%")
                        ->orWhere('file_title', 'LIKE', "%{$search}%")
                        ->orWhere('plot_no', 'LIKE', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate($limit);

        $states      = DB::connection('sqlsrv')->table('States')->orderBy('StateName')->get();
        $lgas        = DB::connection('sqlsrv')->table('lgas')->where('is_active', 1)->orderBy('name')->get();
        $districts   = DB::connection('sqlsrv')->table('districts')->where('is_active', 1)->orderBy('name')->get();
        $streetNames = StreetName::orderBy('name')->get(['id', 'name'])->toBase();

        $visible = fn($q) => $q->where(fn($x) => $x->whereNull('is_deleted')->orWhere('is_deleted', 0))
                               ->where(fn($x) => $x->whereNull('status')->orWhere('status', '!=', TitleStatusParcelRouter::HIDDEN_STATUS));
        $stats = [
            'total'        => PlotSubdivisionApplication::where($visible)->count(),
            'daily'        => PlotSubdivisionApplication::where($visible)->whereDate('created_at', today())->count(),
            'pending'      => PlotSubdivisionApplication::where(function($q){ $q->whereNull('is_deleted')->orWhere('is_deleted', 0); })->where('status', 'pending')->count(),
            'approved'     => PlotSubdivisionApplication::where(function($q){ $q->whereNull('is_deleted')->orWhere('is_deleted', 0); })->where('status', 'approved')->count(),
            'rejected'     => PlotSubdivisionApplication::where(function($q){ $q->whereNull('is_deleted')->orWhere('is_deleted', 0); })->where('status', 'rejected')->count(),
        ];

        return view('deeds.parcel_update.subdivision', compact(
            'records', 'limit', 'states', 'lgas', 'districts', 'streetNames', 'stats'
        ));
    }

    public function store(Request $request)
    {
        /*
         | Raise the execution limit only if it is currently LOWER. set_time_limit() sets
         | the ceiling outright rather than lifting a floor, so a bare set_time_limit(600)
         | -- the shape used by CreateFileTrackerController and FileNumberController --
         | would quietly CUT this request's budget anywhere the configured limit is higher
         | (php.ini carries 9000; public/.htaccess narrows web requests to 600). Belt and
         | braces either way: with the child rows now written in chunks the whole save is a
         | handful of statements.
         */
        $configuredLimit = (int) ini_get('max_execution_time');
        if ($configuredLimit > 0 && $configuredLimit < 600) {
            @set_time_limit(600);
        }

        $validator = Validator::make($request->all(), [
            'file_no' => 'required|string|max:100',
            'file_title' => 'required|string|max:500',
            'applicant_name' => 'nullable|string|max:255',
            // 1000 is a deliberate hard ceiling, not a guess at what the form can carry.
            // Raised from 600 (2026-09-28) so a large industrial layout can be captured in
            // one application; the largest ever captured before this was 530.
            'num_plots' => 'required|integer|min:1|max:' . self::MAX_PLOTS,
            'plot_no' => 'nullable|string|max:100',
            'house_no' => 'nullable|string|max:100',
            'street_name' => 'nullable|string|max:255',
            'district' => 'nullable|string|max:255',
            'lga' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:100',
            // Bounded by the same ceiling as num_plots. Without this the array was
            // uncapped while num_plots was not, so the two could never be trusted to
            // agree -- see the reconciliation check below.
            'plot_sizes' => 'nullable|array|max:' . self::MAX_PLOTS,
            'plot_sizes.*' => 'nullable|numeric|min:0',
            // The sides of each plot, in survey order: "60 x 21 x 46 x 21 x 42.71".
            // A parcel is a polygon, not always a rectangle - which is how the
            // Ministry's own memo states it - so this is a LIST, of any length, one
            // per plot. `plot_sizes` stays the authority everything downstream reads;
            // where two sides give the area the form derives it, and where they cannot
            // (any other count) the officer types the measured area from the plan.
            'plot_dimensions' => 'nullable|array',
            'plot_dimensions.*' => 'nullable|array|max:24',
            'plot_dimensions.*.*' => 'nullable|numeric|min:0',
            'site_plan'          => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:5120',
            'ownership_document' => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:5120',
            'application_letter' => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:5120',
            'means_of_id'        => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:5120',
            'tax_clearance'      => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:5120',
        ], [
            'num_plots.max' => 'A subdivision can hold at most ' . self::MAX_PLOTS . ' plots. Split a larger layout across more than one application.',
            'plot_sizes.max' => 'A subdivision can hold at most ' . self::MAX_PLOTS . ' plots.',
        ]);

        /*
         | The plot count and the plots themselves must agree.
         |
         | num_plots was validated above and plot_sizes is what actually gets written, but
         | until now nothing compared them. Anything that dropped fields in transit -- a
         | proxy, a lowered max_input_vars, a half-sent request -- wrote fewer children
         | than the application claimed and still answered `success: true`, leaving a
         | record whose own plot count disagreed with its plot list and nobody any the
         | wiser. Refuse the whole request instead; a rejected capture is recoverable,
         | a silently short one is not.
         */
        $validator->after(function ($validator) use ($request) {
            $declared = (int) $request->input('num_plots');
            $received = count((array) $request->input('plot_sizes', []));

            if ($declared > 0 && $received !== $declared) {
                $validator->errors()->add('plot_sizes', sprintf(
                    'This application says %d plots but %d arrived, so nothing was saved. Reload the page and capture it again; if it keeps happening, report it with the file number.',
                    $declared,
                    $received
                ));
            }
        });

        if ($validator->fails()) {
            Log::warning('Subdivision capture rejected by validation', [
                'file_no' => $request->input('file_no'),
                'errors' => $validator->errors()->toArray(),
            ]);
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::connection('sqlsrv')->beginTransaction();
        try {
            $application = PlotSubdivisionApplication::create([
                // Which sidebar raised this. Both open the same register, and until this
                // column existed only the page title knew the difference.
                'source_module' => \App\Support\ParcelUpdateSource::fromRequest($request),
                'file_no' => $request->file_no,
                'file_title' => $request->file_title,
                'applicant_name' => $request->applicant_name,
                'num_plots' => $request->num_plots,
                'plot_no' => $request->plot_no,
                'house_no' => $request->house_no,
                'street_name' => $request->street_name,
                'district' => $request->district,
                'lga' => $request->lga,
                'state' => $request->state,
                'land_use' => explode('-', $request->file_no)[0],
                'status' => PlotSubdivisionApplication::STATUS_PENDING,
                'captured_by' => Auth::id(),
                'land_value' => $request->land_value,
                'knupda_fee' => $request->knupda_fee,
            ]);

            $docUpdates = [];
            foreach (['site_plan', 'ownership_document', 'application_letter', 'means_of_id', 'tax_clearance'] as $field) {
                if ($request->hasFile($field)) {
                    $file = $request->file($field);
                    $filename = 'subdivision_' . $application->id . '_' . $field . '_' . time() . '.' . $file->getClientOriginalExtension();
                    // Into the EDMS tree, with a scannings/pagetypings pair, so the
                    // document appears in the EDMS workflow and the Virtual Folder
                    // System rather than only on this screen. Falls back to the old
                    // folder when the file number is not indexed.
                    $docUpdates[$field] = app(ParcelDocumentIngestService::class)->ingest(
                        $file, $request->file_no, $field, 'parcel_documents/subdivision', $filename, Auth::id()
                    );
                }
            }
            if (!empty($docUpdates)) {
                $application->update($docUpdates);
            }

            $dimensions = array_values($request->input('plot_dimensions') ?? []);

            /*
             | Child plots, written in chunks rather than one model at a time.
             |
             | This was PlotApplicationSize::create() inside the loop: one INSERT and one
             | model boot per plot, so a 1000-plot layout meant 1000 round-trips to SQL
             | Server with the transaction held open throughout. That is the wait the
             | officer was staring at, and it got worse in a straight line with the plot
             | count -- which is exactly the wrong shape for a change that raises the
             | ceiling to 1000.
             |
             | Chunked at INSERT_CHUNK rows (8 columns, so 1600 of SQL Server's 2100
             | parameters), the same technique the commissioning path already uses at
             | MlsFileNoController::generateBatch. 1000 plots is 5 statements.
             |
             | The query builder bypasses Eloquent, so timestamps are set by hand -- the
             | model does keep them (usesTimestamps() is true) and rows written the old way
             | have them, so leaving them null would make new rows the odd ones out.
             */
            $now = now();
            $rows = [];

            foreach (array_values((array) ($request->plot_sizes ?? [])) as $index => $size) {
                $rows[] = [
                    'application_id' => $application->id,
                    'application_type' => 'subdivision',
                    'plot_number' => 'Plot ' . ($index + 1),
                    'plot_size' => ($size === null || $size === '') ? 0 : $size,
                    // Kept beside the area, not instead of it: the memo states a
                    // parcel by its sides, everything else works in m².
                    'dimensions' => ParcelSizeSummary::dimensionsText($dimensions[$index] ?? null),
                    'type' => 'subdivision_fragment',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($rows, self::INSERT_CHUNK) as $chunk) {
                DB::connection('sqlsrv')->table('plot_application_sizes')->insert($chunk);
            }

            DB::connection('sqlsrv')->commit();

            Log::info('Subdivision application captured', [
                'application_id' => $application->id,
                'file_no' => $application->file_no,
                'file_title' => $application->file_title,
                'num_plots' => (int) $application->num_plots,
                'plot_sizes' => array_values($request->plot_sizes ?? []),
                'documents' => array_keys($docUpdates),
            ]);

            $this->parcelNotifier->notifyCreated(
                'subdivision',
                $application->id,
                $application->file_no,
                $application->file_title,
                $application->applicant_name ?? ''
            );

            return response()->json(['success' => true, 'message' => 'Subdivision application created successfully.']);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            Log::error('Subdivision capture failed, transaction rolled back', [
                'file_no' => $request->input('file_no'),
                'error' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Re-open a captured application, chiefly to ADD plots.
     *
     * A layout is not always final when it is first captured — a plot is split again,
     * or one the surveyor had not returned turns up — and until now the only way to
     * record that was to delete the application and type the whole thing back in,
     * which an approved application will not even allow.
     *
     * What this will NOT do:
     *
     *   - touch a plot that has already been commissioned. Those rows have file
     *     numbers minted against them; their sizes and their order are what the
     *     minted files were cut from, so they are read past, never rewritten.
     *   - shrink an application that is past 'pending' or has any plot commissioned.
     *     A capture still in draft can be corrected downward, and that is the only
     *     case where a child row is removed.
     *   - re-open a finished subdivision. Once the last plot is minted the register
     *     entry is closed; more plots there are a new application, not a bigger old
     *     one.
     *
     * Raising num_plots on an APPROVED application is deliberate and is the point of
     * the whole thing: remainingPlots() grows, so the next commissioning run simply
     * has more to take. The change is written into remarks so the file says who
     * widened the scope and when, the same way each commissioning batch does.
     */
    public function update(Request $request, int $id)
    {
        $configuredLimit = (int) ini_get('max_execution_time');
        if ($configuredLimit > 0 && $configuredLimit < 600) {
            @set_time_limit(600);
        }

        $record = PlotSubdivisionApplication::findOrFail($id);

        if ((int) ($record->is_deleted ?? 0) === 1) {
            return response()->json(['success' => false, 'message' => 'This application has been deleted.'], 422);
        }

        if ($record->status === PlotSubdivisionApplication::STATUS_REJECTED) {
            return response()->json(['success' => false, 'message' => 'A rejected application cannot be edited.'], 422);
        }

        $commissioned = $record->commissionedCount();

        if ($record->status === PlotSubdivisionApplication::STATUS_COMMISSIONED || ($commissioned > 0 && $record->isCommissioningComplete())) {
            return response()->json([
                'success' => false,
                'message' => "All {$record->num_plots} plots have been commissioned, so this application is closed. Capture a new subdivision for any further plots.",
            ], 422);
        }

        // Rows in the order they were written, which is the order they are commissioned in.
        $existing = DB::connection('sqlsrv')->table('plot_application_sizes')
            ->where('application_id', $record->id)
            ->where('application_type', 'subdivision')
            ->orderBy('id')
            ->get(['id'])
            ->values();
        $existingCount = $existing->count();

        // Below this, nothing may be removed: either the plot is already minted, or the
        // application has left draft and its schedule is what the memo was written on.
        $floor = ($commissioned > 0 || $record->status !== PlotSubdivisionApplication::STATUS_PENDING)
            ? max($commissioned, $existingCount)
            : $commissioned;

        $validator = Validator::make($request->all(), [
            'file_no' => 'required|string|max:100',
            'file_title' => 'required|string|max:500',
            'applicant_name' => 'nullable|string|max:255',
            'num_plots' => 'required|integer|min:1|max:' . self::MAX_PLOTS,
            'plot_no' => 'nullable|string|max:100',
            'house_no' => 'nullable|string|max:100',
            'street_name' => 'nullable|string|max:255',
            'district' => 'nullable|string|max:255',
            'lga' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:100',
            'plot_sizes' => 'nullable|array|max:' . self::MAX_PLOTS,
            'plot_sizes.*' => 'nullable|numeric|min:0',
            'plot_dimensions' => 'nullable|array',
            'plot_dimensions.*' => 'nullable|array|max:24',
            'plot_dimensions.*.*' => 'nullable|numeric|min:0',
            'site_plan'          => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:5120',
            'ownership_document' => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:5120',
            'application_letter' => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:5120',
            'means_of_id'        => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:5120',
            'tax_clearance'      => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:5120',
        ], [
            'num_plots.max' => 'A subdivision can hold at most ' . self::MAX_PLOTS . ' plots. Split a larger layout across more than one application.',
            'plot_sizes.max' => 'A subdivision can hold at most ' . self::MAX_PLOTS . ' plots.',
        ]);

        // Same reconciliation the capture does -- the count and the schedule must agree,
        // or nothing is written -- plus the floor this application may not drop below.
        $validator->after(function ($validator) use ($request, $floor, $commissioned, $existingCount, $record) {
            $declared = (int) $request->input('num_plots');
            $received = count((array) $request->input('plot_sizes', []));

            if ($declared > 0 && $received !== $declared) {
                $validator->errors()->add('plot_sizes', sprintf(
                    'This application says %d plots but %d arrived, so nothing was saved. Reload the page and try again; if it keeps happening, report it with the file number.',
                    $declared,
                    $received
                ));
            }

            if ($declared < $floor) {
                $validator->errors()->add('num_plots', $commissioned > 0
                    ? sprintf('%d of these plots have already been commissioned, so the plot count cannot go below %d.', $commissioned, $floor)
                    : sprintf('This application is %s, so its %d plots can be added to but not removed.', $record->status, $existingCount));
            }
        });

        if ($validator->fails()) {
            Log::warning('Subdivision update rejected by validation', [
                'application_id' => $record->id,
                'file_no' => $record->file_no,
                'errors' => $validator->errors()->toArray(),
            ]);
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $numPlots   = (int) $request->input('num_plots');
        $wasPlots   = (int) $record->num_plots;
        $sizes      = array_values((array) ($request->plot_sizes ?? []));
        $dimensions = array_values($request->input('plot_dimensions') ?? []);

        DB::connection('sqlsrv')->beginTransaction();
        try {
            $payload = [
                'file_no' => $request->file_no,
                'file_title' => $request->file_title,
                'applicant_name' => $request->applicant_name,
                'num_plots' => $numPlots,
                'plot_no' => $request->plot_no,
                'house_no' => $request->house_no,
                'street_name' => $request->street_name,
                'district' => $request->district,
                'lga' => $request->lga,
                'state' => $request->state,
                'land_use' => explode('-', $request->file_no)[0],
                'updated_by' => Auth::id(),
            ];

            /*
             | Money, and only when the form actually carried a land value.
             |
             | The fee box is derived (0.25% of the land value) and is posted on every
             | submit, so on an application with no land value yet it arrives as a
             | perfectly sincere 0 -- which, written through, would erase a fee the
             | KAMMA/Physical Planning modal had recorded. The land value is what the
             | officer types, so it is what decides whether either figure is rewritten.
             */
            if ($request->filled('land_value')) {
                $payload['land_value'] = $request->input('land_value');

                if ($request->filled('knupda_fee')) {
                    $payload['knupda_fee'] = $request->input('knupda_fee');
                }
            }

            // Scope changes go on the record itself, beside the commissioning batches,
            // rather than only into the log: the file should say who widened it.
            if ($numPlots !== $wasPlots) {
                $who  = Auth::user();
                $name = $who ? ($who->name ?? $who->username ?? ('user ' . Auth::id())) : 'unknown user';
                $line = sprintf(
                    'Plot count %s from %d to %d by %s on %s',
                    $numPlots > $wasPlots ? 'raised' : 'reduced',
                    $wasPlots,
                    $numPlots,
                    $name,
                    now()->toDateTimeString()
                );
                $payload['remarks'] = trim(($record->remarks ? $record->remarks . PHP_EOL : '') . $line);
            }

            // A document is replaced only when a new one is uploaded; an update that
            // leaves the file inputs empty keeps whatever is already attached.
            foreach (['site_plan', 'ownership_document', 'application_letter', 'means_of_id', 'tax_clearance'] as $field) {
                if ($request->hasFile($field)) {
                    $file = $request->file($field);
                    $filename = 'subdivision_' . $record->id . '_' . $field . '_' . time() . '.' . $file->getClientOriginalExtension();
                    // Into the EDMS tree, with a scannings/pagetypings pair, so the
                    // document appears in the EDMS workflow and the Virtual Folder
                    // System rather than only on this screen. Falls back to the old
                    // folder when the file number is not indexed.
                    $payload[$field] = app(ParcelDocumentIngestService::class)->ingest(
                        $file, $request->file_no ?: $record->file_no, $field, 'parcel_documents/subdivision', $filename, Auth::id()
                    );
                }
            }

            $record->update($payload);

            $now = now();

            /*
             | The schedule, row by row against what is already there.
             |
             | Position is identity here: the rows are commissioned in id order, so the
             | nth row is "Plot n" and stays the nth row for the life of the application.
             | Rows below the commissioned mark are therefore skipped outright rather
             | than rewritten with the same values -- a minted plot's size is not the
             | capture form's to change.
             */
            $updated  = 0;
            $inserted = [];

            foreach ($sizes as $index => $size) {
                $plotSize = ($size === null || $size === '') ? 0 : $size;
                $dims     = ParcelSizeSummary::dimensionsText($dimensions[$index] ?? null);

                if ($index < $existingCount) {
                    if ($index < $commissioned) {
                        continue;   // already minted -- read past it
                    }

                    DB::connection('sqlsrv')->table('plot_application_sizes')
                        ->where('id', $existing[$index]->id)
                        ->update([
                            'plot_size'  => $plotSize,
                            'dimensions' => $dims,
                            'updated_at' => $now,
                        ]);
                    $updated++;
                    continue;
                }

                $inserted[] = [
                    'application_id' => $record->id,
                    'application_type' => 'subdivision',
                    'plot_number' => 'Plot ' . ($index + 1),
                    'plot_size' => $plotSize,
                    'dimensions' => $dims,
                    'type' => 'subdivision_fragment',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($inserted, self::INSERT_CHUNK) as $chunk) {
                DB::connection('sqlsrv')->table('plot_application_sizes')->insert($chunk);
            }

            // Only ever the tail, and only from a draft that has nothing minted --
            // the floor check above is what makes this reachable at all.
            $removed = 0;
            if ($numPlots < $existingCount) {
                $doomed = $existing->slice($numPlots)->pluck('id')->all();
                if (!empty($doomed)) {
                    $removed = DB::connection('sqlsrv')->table('plot_application_sizes')
                        ->whereIn('id', $doomed)
                        ->delete();
                }
            }

            DB::connection('sqlsrv')->commit();

            Log::info('Subdivision application updated', [
                'application_id' => $record->id,
                'file_no' => $record->file_no,
                'status' => $record->status,
                'num_plots_was' => $wasPlots,
                'num_plots_now' => $numPlots,
                'commissioned' => $commissioned,
                'rows_kept_locked' => min($commissioned, $existingCount),
                'rows_updated' => $updated,
                'rows_added' => count($inserted),
                'rows_removed' => $removed,
            ]);

            $added   = count($inserted);
            $message = $added > 0
                ? sprintf('Application updated. %d plot%s added, %d now in total.', $added, $added === 1 ? '' : 's', $numPlots)
                : 'Application updated.';

            return response()->json(['success' => true, 'message' => $message]);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            Log::error('Subdivision update failed, transaction rolled back', [
                'application_id' => $record->id,
                'file_no' => $record->file_no,
                'error' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $record = PlotSubdivisionApplication::with('plotSizes')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => array_merge($record->toArray(), [
                'commissioned_count' => $record->commissionedCount(),
                'remaining_plots'    => $record->remainingPlots(),
                'batches_done'       => count($record->commissionedBatches()),
                'batch_cap'          => self::BATCH_CAP,
            ]),
        ]);
    }


    public function approve(int $id)
    {
        $record = PlotSubdivisionApplication::findOrFail($id);
        $previousStatus = $record->status;

        // Never walk a commissioned application back to 'approved' — its fragments
        // already exist and the mother file is decommissioned. A partially commissioned
        // application legitimately sits at 'approved' (see recordCommissionedBatch), so
        // it is only the finished ones and any already-minted work that are protected.
        if ($previousStatus === PlotSubdivisionApplication::STATUS_COMMISSIONED || $record->commissionedCount() > 0) {
            return response()->json([
                'success' => false,
                'message' => "This application already has {$record->commissionedCount()} of {$record->num_plots} plots commissioned.",
            ], 422);
        }
        $record->update([
            'status' => PlotSubdivisionApplication::STATUS_APPROVED,
            'updated_by' => Auth::id(),
        ]);

        Log::info('Subdivision application approved', [
            'application_id' => $record->id,
            'file_no' => $record->file_no,
            'num_plots' => (int) $record->num_plots,
            'previous_status' => $previousStatus,
        ]);

        $approver = Auth::user();
        $approverName = $approver ? ($approver->name ?? $approver->username ?? '') : '';
        $this->parcelNotifier->notifyApproved(
            'subdivision',
            $record->id,
            $record->file_no,
            $record->file_title,
            $approverName
        );

        return response()->json(['success' => true, 'message' => 'Application approved.']);
    }

    public function reject(Request $request, int $id)
    {
        $record = PlotSubdivisionApplication::findOrFail($id);
        $reason = trim((string) $request->input('reason', ''));
        $previousStatus = $record->status;
        $record->update([
            'status' => PlotSubdivisionApplication::STATUS_REJECTED,
            'remarks' => $reason ? "Rejected: {$reason}" : 'Rejected',
            'updated_by' => Auth::id(),
        ]);

        Log::info('Subdivision application rejected', [
            'application_id' => $record->id,
            'file_no' => $record->file_no,
            'previous_status' => $previousStatus,
            'reason' => $reason ?: null,
        ]);

        return response()->json(['success' => true, 'message' => 'Application rejected.']);
    }

    public function generateApplication(int $id): JsonResponse
    {
        $record = PlotSubdivisionApplication::findOrFail($id);
        $record->update([
            'application_generated_at' => now(),
            'updated_by' => Auth::id(),
        ]);

        Log::info('Subdivision application document generated', [
            'application_id' => $record->id,
            'file_no' => $record->file_no,
        ]);

        return response()->json(['success' => true, 'message' => 'Application generated.']);
    }

    public function printApplication(int $id)
    {
        $record = PlotSubdivisionApplication::with('plotSizes')->findOrFail($id);
        return view('deeds.parcel_update.print.subdivision_application', compact('record'));
    }

    /**
     * The recommendation memo, which the approval is then given on the strength of.
     *
     * It used to require status = approved. That only ever passed because the KAMMA
     * handshake set BOTH knupda_status and status in one go, so the application was
     * approved before the memo recommending it existed — the two the wrong way
     * round. The handshake no longer approves anything, so the precondition is the
     * planning clearance, and the approval follows the memo the way the duplex
     * register already runs it.
     */
    public function generateRecommendation(int $id): JsonResponse
    {
        $record = PlotSubdivisionApplication::findOrFail($id);

        if (!MasterJsiGate::clearedFor($record, 'subdivision')) {
            return response()->json([
                'success' => false,
                'message' => 'An approved Master JSI is required before the recommendation can be generated.',
            ], 422);
        }

        $record->update([
            'recommendation_generated_at' => now(),
            'updated_by' => Auth::id(),
        ]);

        Log::info('Subdivision recommendation generated', [
            'application_id' => $record->id,
            'file_no' => $record->file_no,
        ]);

        return response()->json(['success' => true, 'message' => 'Recommendation generated.']);
    }

    public function printRecommendation(int $id)
    {
        $record = PlotSubdivisionApplication::with('plotSizes')->findOrFail($id);
        return view('deeds.parcel_update.print.subdivision_recommendation', compact('record'));
    }

    /**
     * The KAMMA/Physical Planning handshake, kept only for the records that used it.
     *
     * The planning clearance is now the Master JSI (App\Support\MasterJsiGate), and
     * the handshake is off the action menu. This endpoint stays because the route
     * and any cached page still point at it, and because knupda_status is history
     * that DuplexCommitService and the legacy arm of the gate both still read.
     *
     * What it no longer does is APPROVE the application. It used to set
     * status = approved on a KAMMA 'Approved' and rejected on a 'Declined', so a
     * planning note silently decided the whole application — a decision nobody had
     * taken and no memo recorded. The approval is now the explicit Approve action
     * on the listing, given on the strength of the recommendation, as it is
     * everywhere else in this module.
     */
    public function updateKnupda(Request $request, int $id): JsonResponse
    {
        $record = PlotSubdivisionApplication::findOrFail($id);
        $knupdaStatus = $request->input('knupda_status');

        $record->update([
            'land_value' => $request->input('land_value'),
            'knupda_fee' => $request->input('knupda_fee'),
            'knupda_status' => $knupdaStatus,
            'knupda_remarks' => $request->input('knupda_remarks'),
            'updated_by' => Auth::id(),
        ]);

        Log::info('Subdivision KAMMA status updated', [
            'application_id' => $record->id,
            'file_no' => $record->file_no,
            'knupda_status' => $knupdaStatus,
            'land_value' => $request->input('land_value'),
            'knupda_fee' => $request->input('knupda_fee'),
        ]);

        return response()->json(['success' => true, 'message' => 'KAMMA status updated.']);
    }

    public function findByFileNo(string $fileNo): JsonResponse
    {
        $record = PlotSubdivisionApplication::where('file_no', $fileNo)
            ->where('status', PlotSubdivisionApplication::STATUS_APPROVED)
            ->orderByDesc('created_at')
            ->first();

        if (!$record) {
            return response()->json(['success' => false, 'message' => 'No approved subdivision application found for this file number.'], 404);
        }

        // The generator's batch mode tops out at self::BATCH_CAP files per run, so a
        // subdivision larger than that is commissioned across several runs. Tell the
        // caller what is left and how big the next chunk may be; the application stays
        // 'approved' (and so keeps being found here) until the last plot is minted.
        $remaining = $record->remainingPlots();

        return response()->json([
            'success' => true,
            'data'    => array_merge($record->toArray(), [
                'planned_plots'      => (int) $record->num_plots,
                'commissioned_count' => $record->commissionedCount(),
                'remaining_plots'    => $remaining,
                'batch_cap'          => self::BATCH_CAP,
                'next_batch_size'    => min($remaining ?: (int) $record->num_plots, self::BATCH_CAP),
                'batches_done'       => count($record->commissionedBatches()),
            ]),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $record = PlotSubdivisionApplication::findOrFail($id);
        
        if ($record->status === PlotSubdivisionApplication::STATUS_APPROVED) {
            Log::warning('Blocked delete of approved subdivision application', [
                'application_id' => $record->id,
                'file_no' => $record->file_no,
            ]);
            return response()->json(['success' => false, 'message' => 'Approved applications cannot be deleted.'], 403);
        }

        $record->update([
            'is_deleted' => 1,
            'deleted_by' => Auth::id(),
            'deleted_at' => now(),
        ]);

        Log::info('Subdivision application soft-deleted', [
            'application_id' => $record->id,
            'file_no' => $record->file_no,
            'status_at_delete' => $record->status,
        ]);

        return response()->json(['success' => true, 'message' => 'Application deleted successfully.']);
    }
}
