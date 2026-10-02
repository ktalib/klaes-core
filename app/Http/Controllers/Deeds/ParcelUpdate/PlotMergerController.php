<?php

namespace App\Http\Controllers\Deeds\ParcelUpdate;

use App\Http\Controllers\Controller;
use App\Services\Edms\ParcelDocumentIngestService;
use App\Models\PlotMergerApplication;
use App\Models\PlotApplicationSize;
use App\Models\StreetName;
use App\Services\ParcelUpdateNotificationService;
use App\Services\TitleStatusParcelRouter;
use App\Support\MasterJsiGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class PlotMergerController extends Controller
{
    public function __construct(
        protected ParcelUpdateNotificationService $parcelNotifier
    ) {}

    public function index(Request $request)
    {
        $limit = max(10, min((int) $request->input('limit', 50), 200));
        $search = trim((string) $request->input('search'));

        $records = PlotMergerApplication::query()
            ->with(['plotSizes' => function($q) {
                $q->whereIn('type', ['source', 'merger_source']);
            }])
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
                        ->orWhere('temp_file_no', 'LIKE', "%{$search}%");
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
            'total'        => PlotMergerApplication::where($visible)->count(),
            'daily'        => PlotMergerApplication::where($visible)->whereDate('created_at', today())->count(),
            'pending'      => PlotMergerApplication::where(function($q){ $q->whereNull('is_deleted')->orWhere('is_deleted', 0); })->where('status', 'pending')->count(),
            'approved'     => PlotMergerApplication::where(function($q){ $q->whereNull('is_deleted')->orWhere('is_deleted', 0); })->where('status', 'approved')->count(),
            'rejected'     => PlotMergerApplication::where(function($q){ $q->whereNull('is_deleted')->orWhere('is_deleted', 0); })->where('status', 'rejected')->count(),
        ];

        return view('deeds.parcel_update.merger', compact(
            'records', 'limit', 'states', 'lgas', 'districts', 'streetNames', 'stats'
        ));
    }

    /** The per-source-plot location columns, added by the 2026_09_29_110000 migration. */
    private const SOURCE_LOCATION_COLUMNS = ['house_no', 'street_name', 'district', 'lga', 'state'];

    /** Resolved once per request — hasColumn() is a round trip, and this is asked per plot. */
    private static ?bool $sourceLocationReady = null;

    /**
     * Has the schema caught up with the code?
     *
     * The columns arrive in a migration, and code and schema do not land in the same
     * instant. Writing to a column that is not there yet would not degrade the merger
     * capture, it would break it outright — so the location is written only once there
     * is somewhere to put it, and until then this behaves exactly as it did before.
     */
    private static function sourceLocationReady(): bool
    {
        if (self::$sourceLocationReady === null) {
            self::$sourceLocationReady = Schema::connection('sqlsrv')
                ->hasColumns('plot_application_sizes', self::SOURCE_LOCATION_COLUMNS);
        }

        return self::$sourceLocationReady;
    }

    /**
     * One source plot of a merger, as a child row.
     *
     * The plots being merged are separate parcels with separate file numbers, so each
     * carries its OWN location — which the capture form has always asked for, one card
     * per plot, and which until now went into location_details_json and no further.
     * Re-opening an application to add a plot is what made that visible: the cards for
     * the plots already captured came back blank.
     *
     * The em-dash placeholders are kept for the file number, title and plot no because
     * the printed memo has always shown them and a blank cell there reads as a missing
     * column rather than a missing answer. The location columns get null instead: they
     * are new, nothing prints them yet, and null is the honest record of "not stated".
     *
     * @param  array  $details  one entry of location_details_json
     * @param  int    $idx      the plot's 1-based position, used only for the fallback
     */
    private static function sourceRow(array $details, int $idx, $size): array
    {
        $value = fn (string $key) => (isset($details[$key]) && trim((string) $details[$key]) !== '')
            ? trim((string) $details[$key])
            : null;

        $row = [
            'plot_number'       => $value('plot_no') ?? '—',
            'source_file_no'    => $value('source_file_no') ?? ('Plot ' . $idx),
            'source_file_title' => $value('source_file_title') ?? '—',
            'plot_size'         => $size,
        ];

        if (!self::sourceLocationReady()) {
            return $row;
        }

        foreach (self::SOURCE_LOCATION_COLUMNS as $column) {
            $row[$column] = $value($column);
        }

        return $row;
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'temp_file_no' => 'required|string|max:100',
            'num_plots' => 'required|integer|min:2|max:50',
            'file_no' => 'required|string|max:100',
            'file_title' => 'required|string|max:500',
            'applicant_name' => 'nullable|string|max:255',
            'plot_no' => 'nullable|string|max:100',
            'house_no' => 'nullable|string|max:100',
            'street_name' => 'nullable|string|max:255',
            'district' => 'nullable|string|max:255',
            'lga' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:100',
            'plot_sizes' => 'required|array',
            'plot_sizes.*' => 'required|numeric|min:0',
            'site_plan'          => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:5120',
            'ownership_document' => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:5120',
            'application_letter' => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:5120',
            'means_of_id'        => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:5120',
            'tax_clearance'      => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::connection('sqlsrv')->beginTransaction();
        try {
            $application = PlotMergerApplication::create([
                // Which sidebar raised this. Both open the same register, and until this
                // column existed only the page title knew the difference.
                'source_module' => \App\Support\ParcelUpdateSource::fromRequest($request),
                'temp_file_no' => $request->temp_file_no,
                'num_plots' => $request->num_plots,
                'file_no' => $request->file_no,
                'file_title' => $request->file_title,
                'applicant_name' => $request->applicant_name,
                'plot_no' => $request->plot_no,
                'house_no' => $request->house_no,
                'street_name' => $request->street_name,
                'district' => $request->district,
                'lga' => $request->lga,
                'state' => $request->state,
                'land_use' => explode('-', $request->file_no)[0],
                'status' => PlotMergerApplication::STATUS_PENDING,
                'captured_by' => Auth::id(),
                'land_value' => $request->land_value,
                'knupda_fee' => $request->knupda_fee,
            ]);

            $docUpdates = [];
            foreach (['site_plan', 'ownership_document', 'application_letter', 'means_of_id', 'tax_clearance'] as $field) {
                if ($request->hasFile($field)) {
                    $file = $request->file($field);
                    $filename = 'merger_' . $application->id . '_' . $field . '_' . time() . '.' . $file->getClientOriginalExtension();
                    // Into the EDMS tree, with a scannings/pagetypings pair, so the
                    // document appears in the EDMS workflow and the Virtual Folder
                    // System rather than only on this screen. Falls back to the old
                    // folder when the file number is not indexed.
                    $docUpdates[$field] = app(ParcelDocumentIngestService::class)->ingest(
                        $file, $request->file_no, $field, 'parcel_documents/merger', $filename, Auth::id()
                    );
                }
            }
            if (!empty($docUpdates)) {
                $application->update($docUpdates);
            }

            $locationDetails = [];
            if ($request->has('location_details_json')) {
                $locationDetails = json_decode($request->location_details_json, true);
            }

            foreach ($request->plot_sizes as $index => $size) {
                $idx = $index + 1;

                PlotApplicationSize::create(self::sourceRow($locationDetails[$idx] ?? [], $idx, $size) + [
                    'application_id' => $application->id,
                    'application_type' => 'merger',
                    'type' => 'merger_source',
                ]);
            }

            DB::connection('sqlsrv')->commit();

            $this->parcelNotifier->notifyCreated(
                'merger',
                $application->id,
                $application->file_no,
                $application->file_title,
                $application->applicant_name ?? ''
            );

            return response()->json(['success' => true, 'message' => 'Merger application created successfully.']);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Re-open a captured merger, chiefly to ADD plots to it.
     *
     * An applicant who set out to merge two plots turns up with a third, and until now
     * that meant deleting the application and typing it all back in — which an approved
     * one will not even allow.
     *
     * The source plots are held in plot_application_sizes in the order they were
     * captured, and that order is what the application memo and the recommendation both
     * print, so position is identity here: the nth card edits the nth row rather than
     * the whole schedule being torn down and rewritten with fresh ids.
     *
     * A merger that has been COMMISSIONED is closed — the merged file exists and the
     * sources are decommissioned behind it, so a further plot is a new instruction, not
     * a wider old one. A REJECTED one is closed for the same reason it cannot be
     * deleted-and-recaptured: the decision is on the file.
     *
     * Plots are only ever removed from an application still in draft. Once it is past
     * 'pending' the schedule is what the memo was written on, so it can be added to but
     * not cut down.
     */
    public function update(Request $request, int $id)
    {
        $record = PlotMergerApplication::findOrFail($id);

        if ((int) ($record->is_deleted ?? 0) === 1) {
            return response()->json(['success' => false, 'message' => 'This application has been deleted.'], 422);
        }

        if ($record->status === PlotMergerApplication::STATUS_REJECTED) {
            return response()->json(['success' => false, 'message' => 'A rejected application cannot be edited.'], 422);
        }

        if ($record->status === PlotMergerApplication::STATUS_COMMISSIONED) {
            return response()->json([
                'success' => false,
                'message' => 'This merger has been commissioned, so it is closed. Capture a new merger for any further plots.',
            ], 422);
        }

        // The source schedule in capture order, which is the order it is printed in.
        $existing = DB::connection('sqlsrv')->table('plot_application_sizes')
            ->where('application_id', $record->id)
            ->where('application_type', 'merger')
            ->orderBy('id')
            ->get(['id'])
            ->values();
        $existingCount = $existing->count();

        $canShrink = $record->status === PlotMergerApplication::STATUS_PENDING;

        $validator = Validator::make($request->all(), [
            'temp_file_no' => 'required|string|max:100',
            'num_plots' => 'required|integer|min:2|max:50',
            'file_no' => 'required|string|max:100',
            'file_title' => 'required|string|max:500',
            'applicant_name' => 'nullable|string|max:255',
            'plot_no' => 'nullable|string|max:100',
            'house_no' => 'nullable|string|max:100',
            'street_name' => 'nullable|string|max:255',
            'district' => 'nullable|string|max:255',
            'lga' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:100',
            'plot_sizes' => 'required|array|min:2|max:50',
            'plot_sizes.*' => 'required|numeric|min:0',
            'site_plan'          => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:5120',
            'ownership_document' => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:5120',
            'application_letter' => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:5120',
            'means_of_id'        => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:5120',
            'tax_clearance'      => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:5120',
        ]);

        // The plot count and the schedule that arrived must agree, and an application
        // out of draft may be added to but not cut down.
        $validator->after(function ($validator) use ($request, $canShrink, $existingCount, $record) {
            $declared = (int) $request->input('num_plots');
            $received = count((array) $request->input('plot_sizes', []));

            if ($declared > 0 && $received !== $declared) {
                $validator->errors()->add('plot_sizes', sprintf(
                    'This merger says %d plots but %d arrived, so nothing was saved. Reload the page and try again.',
                    $declared,
                    $received
                ));
            }

            if (!$canShrink && $declared < $existingCount) {
                $validator->errors()->add('num_plots', sprintf(
                    'This application is %s, so its %d plots can be added to but not removed.',
                    $record->status,
                    $existingCount
                ));
            }
        });

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $numPlots = (int) $request->input('num_plots');
        $wasPlots = (int) $record->num_plots;
        $sizes    = array_values((array) $request->plot_sizes);

        $locationDetails = [];
        if ($request->filled('location_details_json')) {
            $locationDetails = json_decode($request->location_details_json, true) ?: [];
        }

        DB::connection('sqlsrv')->beginTransaction();
        try {
            $payload = [
                'temp_file_no' => $request->temp_file_no,
                'num_plots' => $numPlots,
                'file_no' => $request->file_no,
                'file_title' => $request->file_title,
                'applicant_name' => $request->applicant_name,
                'land_use' => explode('-', $request->file_no)[0],
                'updated_by' => Auth::id(),
            ];

            /*
             | The mother location, only where the form actually sent it.
             |
             | The capture form posts its location per SOURCE PLOT, as
             | location_details_json, and never fills these six columns -- so on most
             | records they are null. A record routed in from Title Status does carry
             | them, though, and copying absent fields across would wipe exactly those.
             */
            foreach (['plot_no', 'house_no', 'street_name', 'district', 'lga', 'state'] as $field) {
                if ($request->has($field)) {
                    $payload[$field] = $request->input($field);
                }
            }

            /*
             | The fee is deliberately NOT touched here.
             |
             | The merger form has no land value box and carries the application fee as
             | a fixed hidden 20,000, which is already what the capture wrote. The only
             | thing that ever moves those two columns afterwards is the
             | KAMMA/Physical Planning modal, so copying the form's constant across on
             | every save would quietly undo it.
             */

            // Scope changes go on the record, not only into a log: the file should say
            // who widened the merger and when.
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
                    $filename = 'merger_' . $record->id . '_' . $field . '_' . time() . '.' . $file->getClientOriginalExtension();
                    // Into the EDMS tree, with a scannings/pagetypings pair, so the
                    // document appears in the EDMS workflow and the Virtual Folder
                    // System rather than only on this screen. Falls back to the old
                    // folder when the file number is not indexed.
                    $payload[$field] = app(ParcelDocumentIngestService::class)->ingest(
                        $file, $request->file_no ?: $record->file_no, $field, 'parcel_documents/merger', $filename, Auth::id()
                    );
                }
            }

            $record->update($payload);

            $added = 0;
            foreach ($sizes as $index => $size) {
                $idx = $index + 1;
                $row = self::sourceRow($locationDetails[$idx] ?? [], $idx, $size);

                if ($index < $existingCount) {
                    DB::connection('sqlsrv')->table('plot_application_sizes')
                        ->where('id', $existing[$index]->id)
                        ->update($row + ['updated_at' => now()]);
                    continue;
                }

                PlotApplicationSize::create($row + [
                    'application_id'   => $record->id,
                    'application_type' => 'merger',
                    'type'             => 'merger_source',
                ]);
                $added++;
            }

            // Only ever the tail, and only from a draft -- the check above is what makes
            // this reachable at all.
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

            $message = $added > 0
                ? sprintf('Merger updated. %d plot%s added, %d now in total.', $added, $added === 1 ? '' : 's', $numPlots)
                : 'Merger updated.';

            return response()->json(['success' => true, 'message' => $message, 'removed' => $removed]);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $record = PlotMergerApplication::with('plotSizes')
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $record]);
    }


    public function approve(int $id)
    {
        $record = PlotMergerApplication::findOrFail($id);
        $record->update([
            'status' => PlotMergerApplication::STATUS_APPROVED,
            'updated_by' => Auth::id(),
        ]);

        $approver = Auth::user();
        $approverName = $approver ? ($approver->name ?? $approver->username ?? '') : '';
        $this->parcelNotifier->notifyApproved(
            'merger',
            $record->id,
            $record->file_no,
            $record->file_title,
            $approverName
        );

        return response()->json(['success' => true, 'message' => 'Application approved.']);
    }

    public function reject(Request $request, int $id)
    {
        $record = PlotMergerApplication::findOrFail($id);
        $reason = trim((string) $request->input('reason', ''));
        $record->update([
            'status' => PlotMergerApplication::STATUS_REJECTED,
            'remarks' => $reason ? "Rejected: {$reason}" : 'Rejected',
            'updated_by' => Auth::id(),
        ]);
        return response()->json(['success' => true, 'message' => 'Application rejected.']);
    }

    public function generateApplication(int $id): JsonResponse
    {
        $record = PlotMergerApplication::findOrFail($id);
        $record->update([
            'application_generated_at' => now(),
            'updated_by' => Auth::id(),
        ]);
        return response()->json(['success' => true, 'message' => 'Application generated.']);
    }

    public function printApplication(int $id)
    {
        $record = PlotMergerApplication::with('plotSizes')->findOrFail($id);
        return view('deeds.parcel_update.print.merger_application', compact('record'));
    }

    /**
     * The recommendation memo, which the approval is then given on the strength of.
     *
     * It used to require status = approved — which only passed because the KAMMA
     * handshake set both statuses at once, approving the application before the memo
     * recommending it existed. The precondition is now the planning clearance, and
     * the approval follows the memo as it does on the duplex register.
     */
    public function generateRecommendation(int $id): JsonResponse
    {
        $record = PlotMergerApplication::findOrFail($id);

        if (!MasterJsiGate::clearedFor($record, 'merger')) {
            return response()->json([
                'success' => false,
                'message' => 'An approved Master JSI is required before the recommendation can be generated.',
            ], 422);
        }

        $record->update([
            'recommendation_generated_at' => now(),
            'updated_by' => Auth::id(),
        ]);
        return response()->json(['success' => true, 'message' => 'Recommendation generated.']);
    }

    public function printRecommendation(int $id)
    {
        $record = PlotMergerApplication::with('plotSizes')->findOrFail($id);
        return view('deeds.parcel_update.print.merger_recommendation', compact('record'));
    }

    /**
     * The KAMMA/Physical Planning handshake, kept only for the records that used it.
     *
     * The planning clearance is now the Master JSI (App\Support\MasterJsiGate) and
     * the handshake is off the action menu; knupda_status stays as history, which
     * the gate's legacy arm still reads so pre-cutover records keep moving.
     *
     * It no longer APPROVES the application: setting status from a planning note
     * let a decision be taken that nobody made and no memo recorded. Approval is
     * the explicit Approve action, given on the strength of the recommendation.
     */
    public function updateKnupda(Request $request, int $id): JsonResponse
    {
        $record = PlotMergerApplication::findOrFail($id);

        $record->update([
            'land_value' => $request->input('land_value'),
            'knupda_fee' => $request->input('knupda_fee'),
            'knupda_status' => $request->input('knupda_status'),
            'knupda_remarks' => $request->input('knupda_remarks'),
            'updated_by' => Auth::id(),
        ]);

        return response()->json(['success' => true, 'message' => 'KAMMA status updated.']);
    }

    public function approvedList(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search'));
        $records = PlotMergerApplication::where('status', PlotMergerApplication::STATUS_APPROVED)
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('temp_file_no', 'LIKE', "%{$search}%")
                        ->orWhere('file_no', 'LIKE', "%{$search}%")
                        ->orWhere('applicant_name', 'LIKE', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->limit(50)
            ->get([
                'id', 
                'temp_file_no', 
                'file_no', 
                'applicant_name', 
                'file_title',
                'plot_no',
                'house_no',
                'street_name',
                'district',
                'lga',
                'state'
            ]);

        // Add source file numbers to each record
        $records->transform(function($record) {
            $record->source_file_nos = DB::connection('sqlsrv')->table('plot_application_sizes')
                ->where('application_id', $record->id)
                ->where('application_type', 'merger')
                ->pluck('source_file_no')
                ->toArray();
            return $record;
        });

        return response()->json(['success' => true, 'data' => $records]);
    }

    public function findByFileNo(string $fileNo): JsonResponse
    {
        $record = PlotMergerApplication::where(function($q) use ($fileNo) {
                $q->where('file_no', $fileNo)
                  ->orWhere('temp_file_no', $fileNo);
            })
            ->where('status', PlotMergerApplication::STATUS_APPROVED)
            ->orderByDesc('created_at')
            ->first();

        if (!$record) {
            return response()->json(['success' => false, 'message' => 'No approved merger application found for this identifier.'], 404);
        }

        $record->source_file_nos = DB::connection('sqlsrv')->table('plot_application_sizes')
            ->where('application_id', $record->id)
            ->where('application_type', 'merger')
            ->pluck('source_file_no')
            ->toArray();

        return response()->json(['success' => true, 'data' => $record]);
    }

    public function destroy(int $id): JsonResponse
    {
        $record = PlotMergerApplication::findOrFail($id);
        
        if ($record->status === PlotMergerApplication::STATUS_APPROVED) {
            return response()->json(['success' => false, 'message' => 'Approved applications cannot be deleted.'], 403);
        }

        $record->update([
            'is_deleted' => 1,
            'deleted_by' => Auth::id(),
            'deleted_at' => now(),
        ]);
        return response()->json(['success' => true, 'message' => 'Application deleted successfully.']);
    }
}
