<?php
namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;

class  SurveyCadastralAttributionController extends Controller
{
    public function Attributions()
    {
        $PageTitle = 'Cadastral Record';
        $PageDescription = '';
        $surveys = DB::connection('sqlsrv')->table('surveyCadastral')
            ->where('survey_type', 'Primary Survey')
            ->get();
 
        return view('survey_cadastral.index', compact(
           'PageTitle',
            'PageDescription',
            'surveys' // Pass surveys to the view
        ));
    }

    public function create()
    {
        $PageTitle = 'Create Cadastral Record';
        $PageDescription = '';

        return view('survey_cadastral.create', compact(
            'PageTitle',
            'PageDescription'
        ));
    }


    public function editSurvey($id)
    {
        $PageTitle = 'Update Survey';
        $PageDescription = 'Edit survey details';
        $survey = DB::connection('sqlsrv')->table('surveyCadastral')->where('ID', $id)->first(); // Fetch survey record by ID

        if (!$survey) {
            abort(404, 'Survey record not found');
        }

        return view('survey_cadastral.update_survey', compact(
            'PageTitle',
            'PageDescription',
            'survey' // Pass survey data to the view
        ));
    }
      public function store(Request $request)
    {
        $validatedData = $request->validate([
            'application_id' => 'nullable',
            'sub_application_id' => 'nullable',
            'fileno' => 'nullable|string|max:255',
            // Primary Survey ID fields for unit surveys
            'PrimarysurveyId' => 'nullable|string|max:255',
            'STFileNo' => 'nullable|string|max:255',
            // Unit Information
            'scheme_no' => 'nullable|string|max:255',
            'section_no' => 'nullable|string|max:255',
            'unit_number' => 'nullable|string|max:255',
            'unit_id' => 'nullable|string|max:255',
            'height' => 'nullable|string|max:255',
            'app_id' => 'nullable|string|max:255',
            'landuse' => 'nullable|string|max:255',
            'section_attribute' => 'nullable|string|max:255',
            'base' => 'nullable|string|max:255',
            'floor' => 'nullable|string|max:255',
            // Unit Control Beacon Information
            'UnitControlBeaconNo' => 'nullable|string|max:255',
            'UnitControlBeaconX' => 'nullable|string|max:255',
            'UnitControlBeaconY' => 'nullable|string|max:255',
            // Unit Dimensions and Position
            'UnitSize' => 'nullable|string|max:255',
            'UnitDemsion' => 'nullable|string|max:255',
            'UnitPosition' => 'nullable|string|max:255',
            // Additional Information
            'tpreport' => 'nullable|string',
            // Survey Plan Upload
            'survey_plan_path' => 'nullable|file|mimes:pdf,jpg,jpeg,png,dwg,dxf|max:10240', // 10MB max
            // Existing fields
            'plot_no' => 'required|string|max:255',
            'block_no' => 'required|string|max:255',
            'approved_plan_no' => 'required|string|max:255',
            'tp_plan_no' => 'required|string|max:255',
            'beacon_control_name' => 'required|string|max:255',
            'Control_Beacon_Coordinate_X' => 'required|string|max:255',
            'Control_Beacon_Coordinate_Y' => 'required|string|max:255',
            'Metric_Sheet_Index' => 'required|string|max:255',
            'Metric_Sheet_No' => 'required|string|max:255',
            'Imperial_Sheet' => 'required|string|max:255',
            'Imperial_Sheet_No' => 'required|string|max:255',
            'layout_name' => 'required|string|max:255',
            'district_name' => 'required|string|max:255',
            'lga_name' => 'required|string|max:255',
            'survey_by' => 'required|string|max:255',
            'survey_by_date' => 'nullable|date',
            'drawn_by' => 'nullable|string|max:255',
            'drawn_by_date' => 'nullable|date',
            'checked_by' => 'nullable|string|max:255',
            'checked_by_date' => 'nullable|date',
            'approved_by' => 'nullable|string|max:255',
            'approved_by_date' => 'nullable|date',
        ]);

        // Handle survey plan file upload
        if ($request->hasFile('survey_plan_path')) {
            $file = $request->file('survey_plan_path');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('survey_plans', $fileName, 'public');
            $validatedData['survey_plan_path'] = $filePath;
        }

        // Determine the survey type based on URL parameter 'is'
        if ($request->query('is') == 'secondary') {
            $validatedData['survey_type'] = 'Unit Survey';
        } else {
            $validatedData['survey_type'] = 'Primary Survey';
        }

        // Use STFileNo as fileno for unit surveys if provided
        if (!empty($validatedData['STFileNo'])) {
            $validatedData['fileno'] = $validatedData['STFileNo'];
        }

        DB::connection('sqlsrv')->table('surveyCadastral')->insert($validatedData);

        return redirect()->route('survey_cadastral.index')->with('success', __('Survey created successfully.'));
    }

    /**
     * Update the survey record.
  
     */
    public function updateSurvey(Request $request)
    {
        $validatedData = $request->validate([
            'id' => 'required|integer',
            'fileno' => 'nullable|string|max:255',
            // Primary Survey ID fields for unit surveys
            'PrimarysurveyId' => 'nullable|string|max:255',
            'STFileNo' => 'nullable|string|max:255',
            // Unit Information
            'scheme_no' => 'nullable|string|max:255',
            'section_no' => 'nullable|string|max:255',
            'unit_number' => 'nullable|string|max:255',
            'unit_id' => 'nullable|string|max:255',
            'height' => 'nullable|string|max:255',
            'app_id' => 'nullable|string|max:255',
            'landuse' => 'nullable|string|max:255',
            'section_attribute' => 'nullable|string|max:255',
            'base' => 'nullable|string|max:255',
            'floor' => 'nullable|string|max:255',
            // Unit Control Beacon Information
            'UnitControlBeaconNo' => 'nullable|string|max:255',
            'UnitControlBeaconX' => 'nullable|string|max:255',
            'UnitControlBeaconY' => 'nullable|string|max:255',
            // Unit Dimensions and Position
            'UnitSize' => 'nullable|string|max:255',
            'UnitDemsion' => 'nullable|string|max:255',
            'UnitPosition' => 'nullable|string|max:255',
            // Additional Information
            'tpreport' => 'nullable|string',
            // Survey Plan Upload (optional for updates)
            'survey_plan_path' => 'nullable|file|mimes:pdf,jpg,jpeg,png,dwg,dxf|max:10240', // 10MB max
            // Existing fields
            'plot_no' => 'nullable|string|max:255',
            'block_no' => 'nullable|string|max:255',
            'approved_plan_no' => 'nullable|string|max:255',
            'tp_plan_no' => 'nullable|string|max:255',
            'beacon_control_name' => 'nullable|string|max:255',
            'Control_Beacon_Coordinate_X' => 'nullable|string|max:255',
            'Control_Beacon_Coordinate_Y' => 'nullable|string|max:255',
            'Metric_Sheet_Index' => 'nullable|string|max:255',
            'Metric_Sheet_No' => 'nullable|string|max:255',
            'Imperial_Sheet' => 'nullable|string|max:255',
            'Imperial_Sheet_No' => 'nullable|string|max:255',
            'layout_name' => 'nullable|string|max:255',
            'district_name' => 'nullable|string|max:255',
            'lga_name' => 'nullable|string|max:255',
            'survey_by' => 'nullable|string|max:255',
            'survey_by_date' => 'nullable|date',
            'drawn_by' => 'nullable|string|max:255',
            'drawn_by_date' => 'nullable|date',
            'checked_by' => 'nullable|string|max:255',
            'checked_by_date' => 'nullable|date',
            'approved_by' => 'nullable|string|max:255',
            'approved_by_date' => 'nullable|date',
        ]);

        // Handle survey plan file upload for updates
        if ($request->hasFile('survey_plan_path')) {
            $file = $request->file('survey_plan_path');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('survey_plans', $fileName, 'public');
            $validatedData['survey_plan_path'] = $filePath;
        }

        // Use STFileNo as fileno for unit surveys if provided
        if (!empty($validatedData['STFileNo'])) {
            $validatedData['fileno'] = $validatedData['STFileNo'];
        }

        $updated = DB::connection('sqlsrv')
            ->table('surveyCadastral')
            ->where('ID', $validatedData['id'])
            ->update(Arr::except($validatedData, ['id']));

        if ($updated) {
            return redirect()->route('survey_cadastral.index')->with('success', 'Survey updated successfully.');
        } else {
            return redirect()->back()->with('error', 'Failed to update survey. Please try again.');
        }
    }
    
    /**
     * Search for application by file number
     */
    public function searchFileNo(Request $request)
    {
        $fileNo = $request->input('fileno');
        $type = $request->input('type', 'primary'); // default to primary
        $isInitial = $request->input('initial', false); // Flag for initial load
        $page = $request->input('page', 1); // For pagination
        $limit = 20; // Number of records per page
        $offset = ($page - 1) * $limit;
        
        if ($type === 'primary') {
            // Search in mother_applications
            $query = DB::connection('sqlsrv')->table('mother_applications')
            ->select('id', 'applicant_title', 'first_name', 'surname', 'fileno', 
            'corporate_name', 'multiple_owners_names', 'land_use', 'applicant_type');
            
            // Apply search filter or get initial records
            if (!empty($fileNo)) {
                $query->where('fileno', 'LIKE', '%' . $fileNo . '%');
            } else if ($isInitial) {
                // For initial load, order by most recent
                $query->orderBy('id', 'desc');
            }
            
            // Get total count for pagination
            $total = $query->count();
            
            // Apply pagination
            $applications = $query->skip($offset)->take($limit)->get();
            
            return response()->json([
                'success' => true,
                'applications' => $applications,
                'pagination' => [
                    'more' => ($offset + $limit) < $total
                ],
                'message' => count($applications) > 0 ? 'Applications found' : 'No applications found'
            ]);
        } else {
            // Search in subapplications with more detailed information for units
            $query = DB::connection('sqlsrv')->table('subapplications')
            ->select('subapplications.id', 'subapplications.applicant_title', 'subapplications.first_name', 
            'subapplications.surname', 'subapplications.fileno', 'subapplications.corporate_name', 
            'subapplications.multiple_owners_names', 'subapplications.land_use', 
            'subapplications.main_application_id', 'subapplications.applicant_type',
            'mother_applications.fileno as primary_fileno', 
            'subapplications.scheme_no', 'subapplications.floor_number', 'subapplications.block_number', 
            'subapplications.unit_number')
            ->leftJoin('mother_applications', 'subapplications.main_application_id', '=', 'mother_applications.id');
            
            // Apply search filter or get initial records
            if (!empty($fileNo)) {
                $query->where('subapplications.fileno', 'LIKE', '%' . $fileNo . '%');
            } else if ($isInitial) {
                // For initial load, order by most recent
                $query->orderBy('subapplications.id', 'desc');
            }
            
            // Get total count for pagination
            $total = $query->count();
            
            // Apply pagination
            $applications = $query->skip($offset)->take($limit)->get();
            
            if (count($applications) > 0) {
                // For each unit application, fetch the unit_id from st_unit_measurements
                foreach ($applications as $app) {
                    // Find the unit_id from st_unit_measurements if unit_number exists
                    if (!empty($app->unit_number)) {
                        $unitMeasurement = DB::connection('sqlsrv')->table('st_unit_measurements')
                        ->select('id')
                        ->where('unit_no', $app->unit_number)
                        ->first();
                            
                        $app->unit_id = $unitMeasurement ? $unitMeasurement->id : null;
                    } else {
                        $app->unit_id = null;
                    }
                    
                    // Set app_id as the subapplication id for clarity
                    $app->app_id = $app->id;
                }
            }
            
            return response()->json([
                'success' => true,
                'applications' => $applications,
                'pagination' => [
                    'more' => ($offset + $limit) < $total
                ],
                'message' => count($applications) > 0 ? 'Applications found' : 'No applications found'
            ]);
        }
        
        return response()->json([
            'success' => false,
            'message' => $isInitial ? 'No file numbers available' : 'No application found with the given file number'
        ]);
    }

    /**
     * Fetch Primary Surveys for dropdown
     */
    public function fetchPrimarySurveys(Request $request)
    {
        $searchTerm = $request->input('search', '');
        $page = $request->input('page', 1);
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        $query = DB::table('surveyCadastral')
            ->select('ID', 'fileno', 'plot_no', 'block_no', 'Scheme_Plan_No', 'layout_name', 
                    'district_name', 'lga_name', 'approved_plan_no', 'survey_type',
                    // Add Personnel Information fields
                    'survey_by', 'survey_by_date', 'drawn_by', 'drawn_by_date', 
                    'checked_by', 'checked_by_date', 'approved_by', 'approved_by_date',
                    // Add additional control and sheet information
                    'beacon_control_name', 'Control_Beacon_Coordinate_X', 'Control_Beacon_Coordinate_Y',
                    'Metric_Sheet_Index', 'Metric_Sheet_No', 'Imperial_Sheet', 'Imperial_Sheet_No');
        
        // Filter by survey_type = "Primary Survey" based on the database structure
        $query->where('survey_type', 'Primary Survey');
            
        if (!empty($searchTerm)) {
            $query->where(function($q) use ($searchTerm) {
                $q->where('fileno', 'LIKE', '%' . $searchTerm . '%')
                  ->orWhere('plot_no', 'LIKE', '%' . $searchTerm . '%')
                  ->orWhere('block_no', 'LIKE', '%' . $searchTerm . '%')
                  ->orWhere('layout_name', 'LIKE', '%' . $searchTerm . '%');
            });
        }
        
        // Order by most recent first
        $query->orderBy('ID', 'desc');
        
        // For debugging: log the query
        \Log::info('Primary Survey Query: ' . $query->toSql());
        
        $total = $query->count();
        $surveys = $query->skip($offset)->take($limit)->get();
        
        // Log the result count for debugging
        \Log::info('Primary Survey Count: ' . count($surveys));
        
        return response()->json([
            'success' => true,
            'surveys' => $surveys,
            'pagination' => [
                'more' => ($offset + $limit) < $total
            ]
        ]);
    }

    /**
     * Get Primary Survey details by ID
     */
    public function getPrimarySurveyDetails($id)
    {
        $survey = DB::table('surveyCadastral')
            ->where('ID', $id)
            ->first();
            
        if ($survey) {
            return response()->json([
                'success' => true,
                'survey' => $survey
            ]);
        }
        
        return response()->json([
            'success' => false,
            'message' => 'Survey not found'
        ]);
    }

    /**
     * Show the form for editing the specified cadastral survey.
     */
    public function edit($id)
    {
        $PageTitle = 'Edit Cadastral Record';
        $PageDescription = 'Edit cadastral survey record details';
        $survey = DB::table('surveyCadastral')->where('ID', $id)->first();

        if (!$survey) {
            abort(404, 'Cadastral record not found');
        }

        return view('survey_cadastral.edit', compact(
            'PageTitle',
            'PageDescription',
            'survey'
        ));
    }

    /**
     * View the cadastral plan for the specified survey.
     */
    public function viewPlan($id)
    {
        $PageTitle = 'View Cadastral Plan';
        $PageDescription = 'View cadastral plan document';
        $survey = DB::table('surveyCadastral')->where('ID', $id)->first();

        if (!$survey) {
            abort(404, 'Cadastral record not found');
        }

        return view('survey_cadastral.view_plan', compact(
            'PageTitle',
            'PageDescription',
            'survey'
        ));
    }

    /**
     * Remove the specified cadastral survey from storage.
     */
    public function destroy($id)
    {
        try {
            $survey = DB::table('surveyCadastral')->where('ID', $id)->first();
            
            if (!$survey) {
                return redirect()->route('survey_cadastral.index')->with('error', 'Cadastral record not found.');
            }

            // Delete the survey plan file if it exists
            if (!empty($survey->survey_plan_path)) {
                Storage::disk('public')->delete($survey->survey_plan_path);
            }

            // Delete the cadastral survey record
            $deleted = DB::table('surveyCadastral')->where('ID', $id)->delete();

            if ($deleted) {
                return redirect()->route('survey_cadastral.index')->with('success', 'Cadastral record deleted successfully.');
            } else {
                return redirect()->route('survey_cadastral.index')->with('error', 'Failed to delete cadastral record.');
            }
        } catch (\Exception $e) {
            return redirect()->route('survey_cadastral.index')->with('error', 'An error occurred while deleting the cadastral record.');
        }
    }
}