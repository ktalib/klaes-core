<?php

namespace App\Http\Controllers;

use App\Models\LandUse;
use App\Models\Purpose;
use App\Models\SltrRecommendation;
use App\Models\StreetName;
use App\Services\Pra\RofoPraSyncer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SltrRecommendationController extends Controller
{
    public function index(Request $request)
    {
        $query = SltrRecommendation::with('creator');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('sltr_number', 'LIKE', "%{$s}%")
                  ->orWhere('applicant_name', 'LIKE', "%{$s}%")
                  ->orWhere('location', 'LIKE', "%{$s}%")
                  ->orWhere('lga', 'LIKE', "%{$s}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $recommendations = $query->latest()->paginate(20);

        $stats = [
            'total'    => SltrRecommendation::count(),
            'pending'  => SltrRecommendation::where('status', SltrRecommendation::STATUS_PENDING)->count(),
            'approved' => SltrRecommendation::where('status', SltrRecommendation::STATUS_APPROVED)->count(),
            'rofo_generated' => SltrRecommendation::where('rofo_status', SltrRecommendation::ROFO_GENERATED)->count(),
        ];

        $PageTitle   = 'SLTR Recommendations';
        $states      = DB::connection('sqlsrv')->table('States')->orderBy('StateName')->get();
        $lgas        = DB::connection('sqlsrv')->table('StatLGAs')
                         ->join('States', 'StatLGAs.StateID', '=', 'States.StateID')
                         ->where('States.StateName', 'Kano')
                         ->orderBy('LGAName')->get();
        $districts   = DB::connection('sqlsrv')->table('districts')->where('is_active', 1)->orderBy('name')->get();
        $streetNames = StreetName::orderBy('name')->get(['id', 'name'])->toBase();
        $streetOptions = $streetNames->merge([(object)['name' => 'Other']]);
        $landUseOptions = LandUse::orderBy('landuse')->get();
        $purposeOptions = Purpose::orderBy('name')->get();

        return view('sltr_recommendations.index', compact(
            'recommendations', 'stats', 'PageTitle',
            'states', 'lgas', 'districts', 'streetOptions',
            'landUseOptions', 'purposeOptions'
        ));
    }

    public function checkFileNumber(Request $request)
    {
        $fileNumber = $request->input('file_number');
        $excludeId  = $request->input('exclude_id');

        if (!$fileNumber) {
            return response()->json(['exists' => false]);
        }

        $query = SltrRecommendation::where('sltr_number', $fileNumber);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $existing = $query->select('id', 'applicant_name', 'sltr_number', 'status')->first();

        return response()->json([
            'exists' => (bool) $existing,
            'record' => $existing,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'sltr_number'      => ['nullable', 'string', 'max:100', Rule::unique('sltr_recommendations', 'sltr_number')->whereNull('deleted_at')],
            'applicant_name'   => 'required|string|max:300',
            'applicant_address'=> 'nullable|string|max:500',
            'application_date' => 'nullable|date',
            'location'         => 'nullable|string|max:300',
            'lga'              => 'nullable|string|max:200',
            'land_use'         => 'nullable|string|max:200',
            'plot_number'      => 'nullable|string|max:100',
            'page_application' => 'nullable|integer|min:1',
            'page_survey'      => 'nullable|integer|min:1',
            'page_planning'    => 'nullable|integer|min:1',
            'term'             => 'nullable|integer|min:1',
            'ground_rent'      => 'nullable|numeric|min:0',
            'processing_fee'   => 'nullable|numeric|min:0',
            'purpose_of_clause'=> 'nullable|string|max:300',
            'notes'            => 'nullable|string',
        ]);

        $rec = SltrRecommendation::create(array_merge($validated, [
            'status'     => SltrRecommendation::STATUS_PENDING,
            'rofo_status'=> SltrRecommendation::ROFO_PENDING,
            'created_by' => Auth::id(),
        ]));

        return response()->json(['success' => true, 'message' => 'Recommendation created.', 'data' => $rec]);
    }

    public function update(Request $request, $id)
    {
        $rec = SltrRecommendation::findOrFail($id);

        $validated = $request->validate([
            'sltr_number'      => ['nullable', 'string', 'max:100', Rule::unique('sltr_recommendations', 'sltr_number')->ignore($rec->id)->whereNull('deleted_at')],
            'applicant_name'   => 'required|string|max:300',
            'applicant_address'=> 'nullable|string|max:500',
            'application_date' => 'nullable|date',
            'location'         => 'nullable|string|max:300',
            'lga'              => 'nullable|string|max:200',
            'land_use'         => 'nullable|string|max:200',
            'plot_number'      => 'nullable|string|max:100',
            'page_application' => 'nullable|integer|min:1',
            'page_survey'      => 'nullable|integer|min:1',
            'page_planning'    => 'nullable|integer|min:1',
            'term'             => 'nullable|integer|min:1',
            'ground_rent'      => 'nullable|numeric|min:0',
            'processing_fee'   => 'nullable|numeric|min:0',
            'purpose_of_clause'=> 'nullable|string|max:300',
            'notes'            => 'nullable|string',
        ]);

        $rec->update(array_merge($validated, ['updated_by' => Auth::id()]));

        return response()->json(['success' => true, 'message' => 'Recommendation updated.', 'data' => $rec]);
    }

    public function destroy($id)
    {
        $rec = SltrRecommendation::findOrFail($id);
        $rec->delete();

        return response()->json(['success' => true, 'message' => 'Recommendation deleted.']);
    }

    public function approve(Request $request, $id)
    {
        $rec = SltrRecommendation::findOrFail($id);

        if ($rec->status === SltrRecommendation::STATUS_APPROVED) {
            return response()->json(['success' => false, 'message' => 'Already approved.'], 422);
        }

        $rec->update([
            'status'           => SltrRecommendation::STATUS_APPROVED,
            'approved_at'      => now(),
            'approved_by'      => Auth::id(),
            'rofo_status'      => SltrRecommendation::ROFO_GENERATED,
            'rofo_generated_at'=> now(),
            'updated_by'       => Auth::id(),
        ]);

        app(RofoPraSyncer::class)->syncSltr($rec->fresh());

        return response()->json(['success' => true, 'message' => 'Recommendation approved.']);
    }

    public function show($id)
    {
        $rec = SltrRecommendation::with('creator', 'approver')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $rec]);
    }

    public function printRecommendation($id)
    {
        $recommendation = SltrRecommendation::findOrFail($id);
        return view('sltr_recommendations.templates.recommendation_print', compact('recommendation'));
    }
}
