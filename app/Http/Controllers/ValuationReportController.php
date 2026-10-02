<?php

namespace App\Http\Controllers;

use App\Models\StreetName;
use App\Models\ValuationReport;
use App\Models\ValuationOfficer;
use App\Models\PrintLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ValuationReportController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $query = ValuationReport::query();
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('file_number', 'LIKE', "%{$search}%")
                    ->orWhere('full_name', 'LIKE', "%{$search}%");
            });
        }

        // Paginated so the page renders a fixed number of rows instead of the
        // whole table at once, which was making this page slow to load.
        $reports = $query->orderBy('created_at', 'desc')->paginate(25)->withQueryString();

        // Stat cards always describe the whole register, not just the current
        // search/page, so they are counted separately from the listing query.
        $todayCount = ValuationReport::whereDate('created_at', \Carbon\Carbon::today())->count();
        $totalCount = ValuationReport::count();
        $printedCount = ValuationReport::where('print_count', '>', 0)->count();
        $unprintedCount = ValuationReport::where('print_count', 0)->count();
        $states = DB::connection('sqlsrv')->table('States')->orderBy('StateName')->get();
        $lgas = DB::connection('sqlsrv')->table('StatLGAs')
            ->join('States', 'StatLGAs.StateID', '=', 'States.StateID')
            ->where('States.StateName', 'Kano')
            ->orderBy('LGAName')
            ->get();
        $districts = DB::connection('sqlsrv')->table('districts')->where('is_active', 1)->orderBy('name')->get();
        $streetNames = StreetName::orderBy('name')->get();
        $landUses = \App\Models\LandUse::with('purposes')->get();
        $valuationOfficers = ValuationOfficer::orderBy('name')->get(['name', 'rank']);

        return view('valuation_reports.index', compact('reports', 'todayCount', 'totalCount', 'printedCount', 'unprintedCount', 'search', 'states', 'lgas', 'districts', 'streetNames', 'landUses', 'valuationOfficers'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Contracts\View\View
     */
    /**
     * Fold the shapes officers actually type (+234 803..., 0803 123 4567) into
     * the canonical 0XXXXXXXXXX the SMS gateways and the rest of the pipeline
     * expect, before the number is validated.
     *
     * Done here rather than inside the rule because the normalized value is what
     * must be STORED: the consent wizard and Instrument Capture both read this
     * column back, and the same number written three different ways is three
     * different numbers as far as any later matching is concerned.
     */
    private function normalizePhone(Request $request): void
    {
        if ($request->filled('phone')) {
            $request->merge(['phone' => \App\Rules\NigerianPhone::normalize($request->input('phone'))]);
        }
    }

    public function create()
    {
        $states = DB::connection('sqlsrv')->table('States')->orderBy('StateName')->get();
        $lgas = DB::connection('sqlsrv')->table('StatLGAs')
            ->join('States', 'StatLGAs.StateID', '=', 'States.StateID')
            ->where('States.StateName', 'Kano')
            ->orderBy('LGAName')
            ->get();
        $districts = DB::connection('sqlsrv')->table('districts')->where('is_active', 1)->orderBy('name')->get();
        $streetNames = StreetName::orderBy('name')->get();
        $landUses = \App\Models\LandUse::with('purposes')->get();
        $valuationOfficers = ValuationOfficer::orderBy('name')->get(['name', 'rank']);

        return view('valuation_reports.form', compact('states', 'lgas', 'districts', 'streetNames', 'landUses', 'valuationOfficers'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $this->normalizePhone($request);

        $data = $request->validate([
            'file_number' => 'required|string',
            'use' => 'nullable|string',
            'property_desc' => 'nullable|string',
            'inspection_date' => 'required|date',
            'land_use_purpose' => 'nullable|string',
            'valuation_purpose' => 'required|string',
            'valuation_type' => 'nullable|string',
            'full_name' => 'nullable|string',
            'address' => 'required|string',
            'phone' => ['required', 'string', 'max:30', new \App\Rules\NigerianPhone],
            'property_no' => 'nullable|string',
            'plot_no' => 'nullable|string',
            'street_name' => 'nullable|string',
            'estate_quarters' => 'nullable|string',
            'neighborhood_characteristics' => 'nullable|string',
            'town_city' => 'nullable|string',
            'lga' => 'nullable|string',
            'property_type' => 'nullable|string',
            'num_blocks' => 'nullable|string',
            'units_per_block' => 'nullable|string',
            'storey_height' => 'nullable|string',
            'bedroom_units' => 'nullable|string',
            'ancillary_bldgs' => 'nullable|string',
            'current_use' => 'nullable|string',
            'foundation_type' => 'nullable|string',
            'foundation_stage' => 'nullable|string',
            'floor_type' => 'nullable|string',
            'floor_finish' => 'nullable|string',
            'walls_type' => 'nullable|string',
            'walls_stage' => 'nullable|string',
            'walls_finish' => 'nullable|string',
            'boundary_wall_type' => 'nullable|string',
            'boundary_wall_height' => 'nullable|string',
            'boundary_wall_finish' => 'nullable|string',
            'roof_type' => 'nullable|string',
            'roof_material' => 'nullable|string',
            'ceiling_detail' => 'nullable|string',
            'ext_doors' => 'nullable|string',
            'window_type' => 'nullable|string',
            'gate_type' => 'nullable|string',
            'int_doors' => 'nullable|string',
            'door_security_proof' => 'nullable|string',
            'burglary_proof' => 'nullable|string',
            'toilet_bath' => 'nullable|string',
            'water_supply' => 'nullable|string',
            'electricity' => 'nullable|string',
            'drainage' => 'nullable|string',
            'fittings_sitting' => 'nullable|string',
            'fittings_dining' => 'nullable|string',
            'fittings_bedroom' => 'nullable|string',
            'fittings_toilet' => 'nullable|string',
            'fittings_bath' => 'nullable|string',
            'fittings_kitchen' => 'nullable|string',
            'fittings_store' => 'nullable|string',
            'fittings_bq' => 'nullable|string',
            'fittings_summary' => 'nullable|string',
            'other_info' => 'nullable|string',
            'remarks' => 'nullable|string',
            'valuation_basis' => 'required|string',
            'value_words' => 'required|string',
            'value_figures' => 'required|string',
            'surveyor_name' => 'required|string',
            'surveyor_rank' => 'nullable|string',
            'chief_valuer' => 'nullable|string',
        ]);

        $data['user_id'] = Auth::id();
        $data['status'] = 'Generated';

        $report = ValuationReport::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Valuation report generated successfully.',
            'id' => $report->id
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse
     */
    public function edit($id)
    {
        $report = ValuationReport::findOrFail($id);

        if ($report->print_count > 0) {
            return redirect()->route('valuation-reports.index')
                ->with('error', 'Cannot edit a report that has already been printed.');
        }

        $states = DB::connection('sqlsrv')->table('States')->orderBy('StateName')->get();
        $lgas = DB::connection('sqlsrv')->table('StatLGAs')
            ->join('States', 'StatLGAs.StateID', '=', 'States.StateID')
            ->where('States.StateName', 'Kano')
            ->orderBy('LGAName')
            ->get();
        $districts = DB::connection('sqlsrv')->table('districts')->where('is_active', 1)->orderBy('name')->get();
        $streetNames = StreetName::orderBy('name')->get();
        $landUses = \App\Models\LandUse::with('purposes')->get();
        $valuationOfficers = ValuationOfficer::orderBy('name')->get(['name', 'rank']);

        return view('valuation_reports.form', compact('report', 'states', 'lgas', 'districts', 'streetNames', 'landUses', 'valuationOfficers'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $report = ValuationReport::findOrFail($id);

        if ($report->print_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot edit a report that has already been printed.'
            ], 403);
        }

        $this->normalizePhone($request);

        $data = $request->validate([
            'file_number' => 'required|string',
            'inspection_date' => 'required|date',
            'land_use_purpose' => 'nullable|string',
            'valuation_purpose' => 'required|string',
            'valuation_type' => 'nullable|string',
            'full_name' => 'nullable|string',
            'address' => 'required|string',
            'phone' => ['required', 'string', 'max:30', new \App\Rules\NigerianPhone],
            'property_no' => 'nullable|string',
            'plot_no' => 'nullable|string',
            'street_name' => 'nullable|string',
            'estate_quarters' => 'nullable|string',
            'neighborhood_characteristics' => 'nullable|string',
            'town_city' => 'nullable|string',
            'lga' => 'nullable|string',
            'property_type' => 'nullable|string',
            'num_blocks' => 'nullable|string',
            'units_per_block' => 'nullable|string',
            'storey_height' => 'nullable|string',
            'bedroom_units' => 'nullable|string',
            'ancillary_bldgs' => 'nullable|string',
            'current_use' => 'nullable|string',
            'foundation_type' => 'nullable|string',
            'foundation_stage' => 'nullable|string',
            'floor_type' => 'nullable|string',
            'floor_finish' => 'nullable|string',
            'walls_type' => 'nullable|string',
            'walls_stage' => 'nullable|string',
            'walls_finish' => 'nullable|string',
            'boundary_wall_type' => 'nullable|string',
            'boundary_wall_height' => 'nullable|string',
            'boundary_wall_finish' => 'nullable|string',
            'roof_type' => 'nullable|string',
            'roof_material' => 'nullable|string',
            'ceiling_detail' => 'nullable|string',
            'ext_doors' => 'nullable|string',
            'window_type' => 'nullable|string',
            'gate_type' => 'nullable|string',
            'int_doors' => 'nullable|string',
            'door_security_proof' => 'nullable|string',
            'burglary_proof' => 'nullable|string',
            'toilet_bath' => 'nullable|string',
            'water_supply' => 'nullable|string',
            'electricity' => 'nullable|string',
            'drainage' => 'nullable|string',
            'fittings_sitting' => 'nullable|string',
            'fittings_dining' => 'nullable|string',
            'fittings_bedroom' => 'nullable|string',
            'fittings_toilet' => 'nullable|string',
            'fittings_bath' => 'nullable|string',
            'fittings_kitchen' => 'nullable|string',
            'fittings_store' => 'nullable|string',
            'fittings_bq' => 'nullable|string',
            'fittings_summary' => 'nullable|string',
            'other_info' => 'nullable|string',
            'remarks' => 'nullable|string',
            'valuation_basis' => 'required|string',
            'value_words' => 'required|string',
            'value_figures' => 'required|string',
            'surveyor_name' => 'required|string',
            'surveyor_rank' => 'nullable|string',
            'chief_valuer' => 'nullable|string',
        ]);

        $report->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Valuation report updated successfully.',
            'id' => $report->id
        ]);
    }

    /**
     * Re-evaluate a report.
     *
     * Stores re-evaluation values (re_value_words / re_value_figures) on the
     * existing row without touching the original value_words / value_figures.
     * When present, these values are printed in place of the originals.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function reEvaluate(Request $request, $id)
    {
        $report = ValuationReport::findOrFail($id);

        $data = $request->validate([
            're_value_figures' => 'required|string',
            're_value_words' => 'required|string',
        ]);

        // Re-evaluating supersedes the printed version, so reset the print count
        // to allow the report to be printed again with the new values.
        $data['print_count'] = 0;

        $report->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Re-evaluation values saved. The report can now be printed again with the new values.',
            'id' => $report->id
        ]);
    }

    /**
     * Master reset of the print count.
     *
     * Sets print_count back to zero so the report becomes editable and
     * printable again. Kept separate from re-evaluation because it does not
     * touch any value, it only clears the printed state.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetPrints(Request $request, $id)
    {
        $report = ValuationReport::findOrFail($id);

        $previous = (int) $report->print_count;
        $report->update(['print_count' => 0]);

        PrintLog::create([
            'reference_number' => $report->file_number,
            'document_type' => 'Valuation Report',
            'print_type' => 'Master Reset',
            'status' => 'Reset',
            'user_id' => Auth::id()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Print count reset to zero. The report can now be edited and printed again.',
            'previous_print_count' => $previous,
            'print_count' => 0
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Contracts\View\View
     */
    public function show($id)
    {
        $report = ValuationReport::findOrFail($id);
        return view('valuation_reports.templates.main', compact('report'));
    }

    /**
     * Update the print log.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function logPrint(Request $request, $id)
    {
        $report = ValuationReport::findOrFail($id);

        DB::beginTransaction();
        try {
            $isFirstPrint = $report->print_count == 0;
            $report->increment('print_count');

            PrintLog::create([
                'reference_number' => $report->file_number,
                'document_type' => 'Valuation Report',
                'print_type' => 'Individual',
                'status' => $isFirstPrint ? 'Original' : 'Duplicate',
                'user_id' => Auth::id()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'print_count' => $report->print_count
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error logging print: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export Valuation Reports data
     */
    public function exportReports(Request $request)
    {
        try {
            $query = ValuationReport::with('user');

            // Add filters if needed, e.g., by property type
            if ($request->has('property_type') && $request->property_type != '') {
                $query->where('property_type', $request->property_type);
            }

            $results = $query->orderBy('created_at', 'desc')->get();

            $data = $results->map(function ($item, $index) {
                return [
                    'SN' => $index + 1,
                    'file_number' => $item->file_number,
                    'owner' => $item->full_name,
                    'property_type' => $item->property_type,
                    'generated_by' => $item->user ? $item->user->first_name . ' ' . $item->user->last_name : 'System',
                    'inspection_date' => $item->inspection_date ? \Carbon\Carbon::parse($item->inspection_date)->format('d/m/Y') : 'N/A',
                    'time_generated' => $item->created_at ? $item->created_at->format('g:i A') : 'N/A',
                    'date_generated' => $item->created_at ? $item->created_at->format('d/m/Y') : 'N/A',
                    'value_figures' => $item->value_figures,
                    'address' => $item->address
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Export failed: ' . $e->getMessage()
            ], 500);
        }
    }
}

