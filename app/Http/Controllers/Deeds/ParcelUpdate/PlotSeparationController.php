<?php

namespace App\Http\Controllers\Deeds\ParcelUpdate;

use App\Http\Controllers\Controller;
use App\Services\Edms\ParcelDocumentIngestService;
use App\Models\PlotSeparationApplication;
use App\Models\PlotApplicationSize;
use App\Models\StreetName;
use App\Services\ParcelUpdateNotificationService;
use App\Services\TitleStatusParcelRouter;
use App\Support\MasterJsiGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PlotSeparationController extends Controller
{
    public function __construct(
        protected ParcelUpdateNotificationService $parcelNotifier
    ) {}

    public function index(Request $request)
    {
        $limit  = max(10, min((int) $request->input('limit', 50), 200));
        $search = trim((string) $request->input('search'));

        $records = PlotSeparationApplication::query()
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            // Hide rows routed in from Title Status / File Indexing until processed.
            ->where(function ($q) {
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

        $base  = fn ($q) => $q->where(fn ($x) => $x->whereNull('is_deleted')->orWhere('is_deleted', 0))
                              ->where(fn ($x) => $x->whereNull('status')->orWhere('status', '!=', TitleStatusParcelRouter::HIDDEN_STATUS));
        $stats = [
            'total'    => PlotSeparationApplication::where($base)->count(),
            'daily'    => PlotSeparationApplication::where($base)->whereDate('created_at', today())->count(),
            'pending'  => PlotSeparationApplication::where($base)->where('status', 'pending')->count(),
            'approved' => PlotSeparationApplication::where($base)->where('status', 'approved')->count(),
            'rejected' => PlotSeparationApplication::where($base)->where('status', 'rejected')->count(),
        ];

        return view('deeds.parcel_update.separation', compact(
            'records', 'limit', 'states', 'lgas', 'districts', 'streetNames', 'stats'
        ));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file_no'            => 'required|string|max:100',
            'file_title'         => 'required|string|max:500',
            'applicant_name'     => 'nullable|string|max:255',
            'num_plots'          => 'required|integer|min:1|max:50',
            'plot_no'            => 'nullable|string|max:100',
            'house_no'           => 'nullable|string|max:100',
            'street_name'        => 'nullable|string|max:255',
            'district'           => 'nullable|string|max:255',
            'lga'                => 'nullable|string|max:255',
            'state'              => 'nullable|string|max:100',
            'plot_sizes'         => 'required|array',
            'plot_sizes.*'       => 'required|numeric|min:0',
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
            $application = PlotSeparationApplication::create([
                // Which sidebar raised this. Both open the same register, and until this
                // column existed only the page title knew the difference.
                'source_module' => \App\Support\ParcelUpdateSource::fromRequest($request),
                'file_no'        => $request->file_no,
                'file_title'     => $request->file_title,
                'applicant_name' => $request->applicant_name,
                'num_plots'      => $request->num_plots,
                'plot_no'        => $request->plot_no,
                'house_no'       => $request->house_no,
                'street_name'    => $request->street_name,
                'district'       => $request->district,
                'lga'            => $request->lga,
                'state'          => $request->state,
                'land_use'       => explode('-', $request->file_no)[0],
                'status'         => PlotSeparationApplication::STATUS_PENDING,
                'captured_by'    => Auth::id(),
                'land_value'     => $request->land_value,
                'knupda_fee'     => $request->knupda_fee,
            ]);

            $docUpdates = [];
            foreach (['site_plan', 'ownership_document', 'application_letter', 'means_of_id', 'tax_clearance'] as $field) {
                if ($request->hasFile($field)) {
                    $file     = $request->file($field);
                    $filename = 'separation_' . $application->id . '_' . $field . '_' . time() . '.' . $file->getClientOriginalExtension();
                    // Into the EDMS tree, with a scannings/pagetypings pair, so the
                    // document appears in the EDMS workflow and the Virtual Folder
                    // System rather than only on this screen. Falls back to the old
                    // folder when the file number is not indexed.
                    $docUpdates[$field] = app(ParcelDocumentIngestService::class)->ingest(
                        $file, $request->file_no, $field, 'parcel_documents/separation', $filename, Auth::id()
                    );
                }
            }
            if (!empty($docUpdates)) {
                $application->update($docUpdates);
            }

            foreach ($request->plot_sizes as $index => $size) {
                PlotApplicationSize::create([
                    'application_id'   => $application->id,
                    'application_type' => 'separation',
                    'plot_number'      => 'Plot ' . ($index + 1),
                    'plot_size'        => $size,
                    'type'             => 'separation_fragment',
                ]);
            }

            DB::connection('sqlsrv')->commit();

            $this->parcelNotifier->notifyCreated(
                'separation',
                $application->id,
                $application->file_no,
                $application->file_title,
                $application->applicant_name ?? ''
            );

            return response()->json(['success' => true, 'message' => 'Separation application created successfully.']);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $record = PlotSeparationApplication::with('plotSizes')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $record]);
    }

    public function approve(int $id)
    {
        $record = PlotSeparationApplication::findOrFail($id);
        $record->update([
            'status'     => PlotSeparationApplication::STATUS_APPROVED,
            'updated_by' => Auth::id(),
        ]);

        $approver     = Auth::user();
        $approverName = $approver ? ($approver->name ?? $approver->username ?? '') : '';
        $this->parcelNotifier->notifyApproved(
            'separation',
            $record->id,
            $record->file_no,
            $record->file_title,
            $approverName
        );

        return response()->json(['success' => true, 'message' => 'Application approved.']);
    }

    public function reject(Request $request, int $id)
    {
        $record = PlotSeparationApplication::findOrFail($id);
        $reason = trim((string) $request->input('reason', ''));
        $record->update([
            'status'     => PlotSeparationApplication::STATUS_REJECTED,
            'remarks'    => $reason ? "Rejected: {$reason}" : 'Rejected',
            'updated_by' => Auth::id(),
        ]);
        return response()->json(['success' => true, 'message' => 'Application rejected.']);
    }

    public function generateApplication(int $id): JsonResponse
    {
        $record = PlotSeparationApplication::findOrFail($id);
        $record->update([
            'application_generated_at' => now(),
            'updated_by'               => Auth::id(),
        ]);
        return response()->json(['success' => true, 'message' => 'Application generated.']);
    }

    public function printApplication(int $id)
    {
        $record = PlotSeparationApplication::with('plotSizes')->findOrFail($id);
        return view('deeds.parcel_update.print.separation_application', compact('record'));
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
        $record = PlotSeparationApplication::findOrFail($id);

        if (!MasterJsiGate::clearedFor($record, 'separation')) {
            return response()->json([
                'success' => false,
                'message' => 'An approved Master JSI is required before the recommendation can be generated.',
            ], 422);
        }

        $record->update([
            'recommendation_generated_at' => now(),
            'updated_by'                  => Auth::id(),
        ]);
        return response()->json(['success' => true, 'message' => 'Recommendation generated.']);
    }

    public function printRecommendation(int $id)
    {
        $record = PlotSeparationApplication::with('plotSizes')->findOrFail($id);
        return view('deeds.parcel_update.print.separation_recommendation', compact('record'));
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
        $record = PlotSeparationApplication::findOrFail($id);

        $record->update([
            'land_value'     => $request->input('land_value'),
            'knupda_fee'     => $request->input('knupda_fee'),
            'knupda_status'  => $request->input('knupda_status'),
            'knupda_remarks' => $request->input('knupda_remarks'),
            'updated_by'     => Auth::id(),
        ]);

        return response()->json(['success' => true, 'message' => 'KAMMA status updated.']);
    }

    public function findByFileNo(string $fileNo): JsonResponse
    {
        $record = PlotSeparationApplication::where('file_no', $fileNo)
            ->where('status', PlotSeparationApplication::STATUS_APPROVED)
            ->orderByDesc('created_at')
            ->first();

        if (!$record) {
            return response()->json(['success' => false, 'message' => 'No approved separation application found for this file number.'], 404);
        }

        return response()->json(['success' => true, 'data' => $record]);
    }

    public function destroy(int $id): JsonResponse
    {
        $record = PlotSeparationApplication::findOrFail($id);

        if ($record->status === PlotSeparationApplication::STATUS_APPROVED) {
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
