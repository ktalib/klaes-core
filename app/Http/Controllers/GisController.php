<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;

class GisController extends Controller
{ 
    public function Index()
    {
        $PageTitle = 'GIS Data Capture';
        $PageDescription = '';
        
        // Get GIS data list for display using SQL Server connection
        $gisData = DB::connection('sqlsrv')->table('gisCapture')->orderBy('created_at', 'desc')->get();
        
        return view('gis_record.index', compact('PageTitle', 'PageDescription', 'gisData'));
    }  
    
    public function create()
    {
        $PageTitle = 'Capture GIS Data';
        $PageDescription = '';

        return view('gis_record.create', compact('PageTitle', 'PageDescription'));
    } 
    
    public function store(Request $request)
    {
        // Validate the request data, including only the actual database column names
        $validated = $request->validate([
            'gis_type' => 'nullable|string',
            'mlsFNo' => 'nullable|string',
            'kangisFileNo' => 'nullable|string',
            'NewKANGISFileno' => 'nullable|string',
            'fileno' => 'nullable|string',
            'plotNo' => 'nullable|string',
            'blockNo' => 'nullable|string',
            'approvedPlanNo' => 'nullable|string',
            'tpPlanNo' => 'nullable|string',
            'surveyedBy' => 'nullable|string',
            'drawnBy' => 'nullable|string',
            'checkedBy' => 'nullable|string',
            'passedBy' => 'nullable|string',
            'beaconControlName' => 'nullable|string',
            'beaconControlX' => 'nullable|string',
            'beaconControlY' => 'nullable|string',
            'metricSheetIndex' => 'nullable|string',
            'metricSheetNo' => 'nullable|string',
            'imperialSheet' => 'nullable|string',
            'imperialSheetNo' => 'nullable|string',
            'layoutName' => 'nullable|string',
            'districtName' => 'nullable|string',
            'lgaName' => 'nullable|string',
            'StateName' => 'nullable|string',
            'oldTitleSerialNo' => 'nullable|string',
            'oldTitlePageNo' => 'nullable|string',
            'oldTitleVolumeNo' => 'nullable|string',
            'deedsDate' => 'nullable|date',
            'deedsTime' => 'nullable',
            'certificateDate' => 'nullable|date',
            'originalAllottee' => 'nullable|string',
            'addressOfOriginalAllottee' => 'nullable|string',
            'titleIssuedYear' => 'nullable|integer',
            'changeOfOwnership' => 'nullable|string',
            'reasonForChange' => 'nullable|string',
            'currentAllottee' => 'nullable|string',
            'addressOfCurrentAllottee' => 'nullable|string',
            'titleOfCurrentAllottee' => 'nullable|string',
            'phoneNo' => 'nullable|string',
            'emailAddress' => 'nullable|email',
            'occupation' => 'nullable|string',
            'nationality' => 'nullable|string',
            'specifically' => 'nullable|string',
            'streetName' => 'nullable|string',
            'houseNo' => 'nullable|string',
            'houseType' => 'nullable|string',
            'tenancy' => 'nullable|string',
            'areaInHectares' => 'nullable|numeric',
            'SurveyorGeneralSignatureDate' => 'nullable|date',
            'CofOSerialNo' => 'nullable|string',
            'CompanyRCNo' => 'nullable|string',
            'transactionDocument' => 'nullable|file',
            'passportPhoto' => 'nullable|file',
            'nationalId' => 'nullable|file',
            'internationalPassport' => 'nullable|file',
            'businessRegCert' => 'nullable|file',
            'formCO7AndCO4' => 'nullable|file',
            'certOfIncorporation' => 'nullable|file',
            'memorandumAndArticle' => 'nullable|file',
            'letterOfAdmin' => 'nullable|file',
            'courtAffidavit' => 'nullable|file',
            'policeReport' => 'nullable|file',
            'newspaperAdvert' => 'nullable|file',
            'picture' => 'nullable|file',
            'SurveyPlan' => 'nullable|file',
            // Track active tab but don't include preview fields in validation
            'activeFileTab' => 'nullable|string',
            // Add new unit form fields
            'PrimaryGISID' => 'nullable|string',
            'STFileNo' => 'nullable|string',
            'app_id' => 'nullable|string',
            'scheme_no' => 'nullable|string',
            'section_no' => 'nullable|string',
            'block_no' => 'nullable|string',
            'unit_no' => 'nullable|string',
            'landuse' => 'nullable|string',
            'height' => 'nullable|string',
            'unit_id' => 'nullable|string',
            'section_attribute' => 'nullable|string',
            'base' => 'nullable|string',
            'floor' => 'nullable|string',
            'tpreport' => 'nullable|string',
            'UnitControlBeaconNo' => 'nullable|string',
            'UnitControlBeaconX' => 'nullable|string',
            'UnitControlBeaconY' => 'nullable|string',
            'UnitSize' => 'nullable|string',
            'UnitDemsion' => 'nullable|string',
            'UnitPosition' => 'nullable|string',
            'main_application_id' => 'nullable|integer',
        ]);
        
        // Start with only the validated database fields
        $data = $validated;
        
        // Make sure we've completely removed preview fields
        unset($data['activeFileTab']);
        
        // Prevent any preview fields from getting into the database
        $previewFields = [
            'mlsPreviewFileNumber', 'kangisPreviewFileNumber', 'newKangisPreviewFileNumber',
            'hiddenFileNumber', 'mlsFileNoPrefix', 'mlsFileNumber',
            'kangisFileNoPrefix', 'kangisFileNumber', 'newKangisFileNoPrefix',
            'newKangisFileNumber', 'file_prefix', 'file_year', 'serial_number',
            'scheme_no_preview', 'section_no_preview', 'block_no_preview', 'unit_no_preview'
        ];
        
        foreach ($previewFields as $field) {
            unset($data[$field]);
        }
        
        // Handle file uploads - each file gets stored in its own column
        $fileFields = [
            'transactionDocument', 'passportPhoto', 'nationalId', 'internationalPassport',
            'businessRegCert', 'formCO7AndCO4', 'certOfIncorporation', 'memorandumAndArticle',
            'letterOfAdmin', 'courtAffidavit', 'policeReport', 'newspaperAdvert', 'picture', 'SurveyPlan'
        ];
        
        foreach ($fileFields as $field) {
            if ($request->hasFile($field) && $request->file($field)->isValid()) {
                $path = $request->file($field)->store('gis_documents', 'public');
                $data[$field] = $path; // Store file path directly in the corresponding column
            } else {
                // Remove the field from data if no file was uploaded
                unset($data[$field]);
            }
        }
        
        try {
            // Add metadata
            $data['created_by'] = Auth::id();
            $data['created_at'] = now();
            $data['updated_at'] = now();
            
            // Log the final data array for debugging (ONLY the keys)
            Log::info('GIS Data keys before insert: ', array_keys($data));
            
            // Store in database using SQL Server connection
            $id = DB::connection('sqlsrv')->table('gisCapture')->insertGetId($data);
            
            return redirect()->route('gis_record.view', $id)->with('success', 'GIS data saved successfully!');
            
        } catch (\Exception $e) {
            Log::error('GIS Data Save Error: ' . $e->getMessage());
            return back()->withInput()->with('error', 'Failed to save GIS data. Error: ' . $e->getMessage());
        }
    }
    public function show($id)
    {
        $PageTitle = 'View Captured GIS Data';
        $PageDescription = '';
        
        // Get the GIS data record using SQL Server connection
        $gisData = DB::connection('sqlsrv')->table('gisCapture')->where('id', $id)->first();
        
        if (!$gisData) {
            return redirect()->route('gis_record.index')->with('error', 'GIS data record not found.');
        }
        
        return view('gis_record.view', compact('PageTitle', 'PageDescription', 'gisData'));
    }

    public function edit($id)
    {
        $PageTitle = 'Edit GIS Data';
        $PageDescription = '';
        
        // Get the GIS data record using SQL Server connection
        $gisData = DB::connection('sqlsrv')->table('gisCapture')->where('id', $id)->first();
        
        if (!$gisData) {
            return redirect()->route('gis_record.index')->with('error', 'GIS data record not found.');
        }
        
        return view('gis_record.edit', compact('PageTitle', 'PageDescription', 'gisData'));
    }
    
    public function update(Request $request, $id)
    {
        // Validate the request data
        $validated = $request->validate([
            'gis_type' => 'nullable|string',
            'mlsfNo' => 'nullable|string',
            'kangisFileNo' => 'nullable|string',
            'NewKANGISFileno' => 'nullable|string',
            'fileno' =>      'nullable|string',
            'plotNo' => 'nullable|string',
            'blockNo' => 'nullable|string',
            'approvedPlanNo' => 'nullable|string',
            'tpPlanNo' => 'nullable|string',
            'surveyedBy' => 'nullable|string',
            'drawnBy' => 'nullable|string',
            'checkedBy' => 'nullable|string',
            'passedBy' => 'nullable|string',
            'beaconControlName' => 'nullable|string',
            'beaconControlX' => 'nullable|string',
            'beaconControlY' => 'nullable|string',
            'metricSheetIndex' => 'nullable|string',
            'metricSheetNo' => 'nullable|string',
            'imperialSheet' => 'nullable|string',
            'imperialSheetNo' => 'nullable|string',
            'layoutName' => 'nullable|string',
            'districtName' => 'nullable|string',
            'lgaName' => 'nullable|string',
            'StateName' => 'nullable|string',
            'oldTitleSerialNo' => 'nullable|string',
            'oldTitlePageNo' => 'nullable|string',
            'oldTitleVolumeNo' => 'nullable|string',
            'deedsDate' => 'nullable|date',
            'deedsTime' => 'nullable',
            'certificateDate' => 'nullable|date',
            'originalAllottee' => 'nullable|string',
            'addressOfOriginalAllottee' => 'nullable|string',
            'titleIssuedYear' => 'nullable|integer',
            'changeOfOwnership' => 'nullable|string',
            'reasonForChange' => 'nullable|string',
            'currentAllottee' => 'nullable|string',
            'addressOfCurrentAllottee' => 'nullable|string',
            'titleOfCurrentAllottee' => 'nullable|string',
            'phoneNo' => 'nullable|string',
            'emailAddress' => 'nullable|email',
            'occupation' => 'nullable|string',
            'nationality' => 'nullable|string',
            'specifically' => 'nullable|string',
            'streetName' => 'nullable|string',
            'houseNo' => 'nullable|string',
            'houseType' => 'nullable|string',
            'tenancy' => 'nullable|string',
            'areaInHectares' => 'nullable|numeric',
            'SurveyorGeneralSignatureDate' => 'nullable|date',
            'CofOSerialNo' => 'nullable|string',
            'CompanyRCNo' => 'nullable|string',
            'transactionDocument' => 'nullable|file',
            'passportPhoto' => 'nullable|file',
            'nationalId' => 'nullable|file',
            'internationalPassport' => 'nullable|file',
            'businessRegCert' => 'nullable|file',
            'formCO7AndCO4' => 'nullable|file',
            'certOfIncorporation' => 'nullable|file',
            'memorandumAndArticle' => 'nullable|file',
            'letterOfAdmin' => 'nullable|file',
            'courtAffidavit' => 'nullable|file',
            'policeReport' => 'nullable|file',
            'newspaperAdvert' => 'nullable|file',
            'picture' => 'nullable|file',
            'SurveyPlan' => 'nullable|file',
            // Add new unit form fields
            'PrimaryGISID' => 'nullable|string',
            'STFileNo' => 'nullable|string',
            'app_id' => 'nullable|string',
            'scheme_no' => 'nullable|string',
            'section_no' => 'nullable|string',
            'block_no' => 'nullable|string',
            'unit_no' => 'nullable|string',
            'landuse' => 'nullable|string',
            'height' => 'nullable|string',
            'unit_id' => 'nullable|string',
            'section_attribute' => 'nullable|string',
            'base' => 'nullable|string',
            'floor' => 'nullable|string',
            'tpreport' => 'nullable|string',
            'UnitControlBeaconNo' => 'nullable|string',
            'UnitControlBeaconX' => 'nullable|string',
            'UnitControlBeaconY' => 'nullable|string',
            'UnitSize' => 'nullable|string',
            'UnitDemsion' => 'nullable|string',
            'UnitPosition' => 'nullable|string',
            'main_application_id' => 'nullable|integer',
        ]);
        
        // Get the current record data
        $currentData = DB::connection('sqlsrv')->table('gisCapture')->where('id', $id)->first();
        
        if (!$currentData) {
            return redirect()->route('gis_record.index')->with('error', 'GIS data record not found.');
        }
        
        // Prepare data array with validated fields
        $data = $validated;
        
        // Handle file uploads - each file gets stored in its own column
        $fileFields = [
            'transactionDocument', 'passportPhoto', 'nationalId', 'internationalPassport',
            'businessRegCert', 'formCO7AndCO4', 'certOfIncorporation', 'memorandumAndArticle',
            'letterOfAdmin', 'courtAffidavit', 'policeReport', 'newspaperAdvert', 'picture', 'SurveyPlan'
        ];
        
        foreach ($fileFields as $field) {
            if ($request->hasFile($field) && $request->file($field)->isValid()) {
                // Delete old file if exists
                if (isset($currentData->$field) && $currentData->$field) {
                    Storage::disk('public')->delete($currentData->$field);
                }
                
                // Store new file
                $path = $request->file($field)->store('gis_documents', 'public');
                $data[$field] = $path;
            } else {
                // Keep the existing file if no new one is uploaded
                unset($data[$field]);
            }
        }
        
        try {
            // Add metadata
            $data['updated_by'] = Auth::id();
            $data['updated_at'] = now();
            
            // Update in database using SQL Server connection
            DB::connection('sqlsrv')->table('gisCapture')->where('id', $id)->update($data);
            
            return redirect()->route('gis_record.view', $id)->with('success', 'GIS data updated successfully!');
            
        } catch (\Exception $e) {
            Log::error('GIS Data Update Error: ' . $e->getMessage());
            return back()->withInput()->with('error', 'Failed to update GIS data. Please try again.');
        }
    }
    
    public function destroy($id)
    {
        try {
            // Get the record to check for files to delete
            $record = DB::connection('sqlsrv')->table('gisCapture')->where('id', $id)->first();
            
            if (!$record) {
                return redirect()->route('gis_record.index')->with('error', 'GIS data record not found.');
            }
            
            // Delete associated files
            $fileFields = [
                'transactionDocument', 'passportPhoto', 'nationalId', 'internationalPassport',
                'businessRegCert', 'formCO7AndCO4', 'certOfIncorporation', 'memorandumAndArticle',
                'letterOfAdmin', 'courtAffidavit', 'policeReport', 'newspaperAdvert', 'picture', 'SurveyPlan'
            ];
            
            foreach ($fileFields as $field) {
                if (isset($record->$field) && $record->$field) {
                    Storage::disk('public')->delete($record->$field);
                }
            }
            
            // Delete the record
            DB::connection('sqlsrv')->table('gisCapture')->where('id', $id)->delete();
            
            return redirect()->route('gis_record.index')->with('success', 'GIS data deleted successfully!');
            
        } catch (\Exception $e) {
            Log::error('GIS Data Delete Error: ' . $e->getMessage());
            return redirect()->route('gis_record.index')->with('error', 'Failed to delete GIS data. Please try again.');
        }
    }

    public function searchFiles(Request $request)
    {
        $search = $request->input('search');
        $initial = $request->input('initial', false);
        
        // For initial load, get the 10 most recent files
        if ($initial) {
            $files = DB::connection('sqlsrv')
                ->table('gisCapture')
                ->select(
                    'id', 'mlsfNo', 'kangisFileNo', 'NewKANGISFileno', 
                    'plotNo', 'blockNo', 'approvedPlanNo', 'tpPlanNo', 'areaInHectares',
                    'landUse', 'specifically', 'layoutName', 'districtName', 'lgaName', 
                    'StateName', 'streetName', 'houseNo', 'houseType', 'tenancy',
                    'oldTitleSerialNo', 'oldTitlePageNo', 'oldTitleVolumeNo', 
                    'deedsDate', 'deedsTime', 'certificateDate', 'titleIssuedYear', 'CofOSerialNo',
                    'originalAllottee', 'addressOfOriginalAllottee', 'changeOfOwnership', 
                    'reasonForChange', 'currentAllottee', 'addressOfCurrentAllottee', 
                    'titleOfCurrentAllottee', 'phoneNo', 'emailAddress', 
                    'occupation', 'nationality', 'CompanyRCNo'
                )
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
                
            return response()->json($files);
        }
        
        // If not initial and no search term, return empty
        if (empty($search)) {
            return response()->json([]);
        }
        
        // Search for files matching the search term
        $files = DB::connection('sqlsrv')
            ->table('gisCapture')
            ->where(function($query) use ($search) {
                $query->where('mlsfNo', 'like', '%' . $search . '%')
                    ->orWhere('kangisFileNo', 'like', '%' . $search . '%')
                    ->orWhere('NewKANGISFileno', 'like', '%' . $search . '%');
            })
            ->select(
                'id', 'mlsfNo', 'kangisFileNo', 'NewKANGISFileno', 
                'plotNo', 'blockNo', 'approvedPlanNo', 'tpPlanNo', 'areaInHectares',
                'landUse', 'specifically', 'layoutName', 'districtName', 'lgaName', 
                'StateName', 'streetName', 'houseNo', 'houseType', 'tenancy',
                'oldTitleSerialNo', 'oldTitlePageNo', 'oldTitleVolumeNo', 
                'deedsDate', 'deedsTime', 'certificateDate', 'titleIssuedYear', 'CofOSerialNo',
                'originalAllottee', 'addressOfOriginalAllottee', 'changeOfOwnership', 
                'reasonForChange', 'currentAllottee', 'addressOfCurrentAllottee', 
                'titleOfCurrentAllottee', 'phoneNo', 'emailAddress', 
                'occupation', 'nationality', 'CompanyRCNo'
            )
            ->limit(10)
            ->get();
        
        return response()->json($files);
    }

    /**
     * Get unit file numbers for a given mother application
     */
    public function getUnits(Request $request)
    {
        $motherId = $request->input('mother_id');
        
        if (!$motherId) {
            return response()->json([]);
        }
        
        // Get the units for this mother application with additional fields
        $units = DB::connection('sqlsrv')
            ->table('subapplications')
            ->where('main_application_id', $motherId)
            ->select(
                'subapplications.id', 
                'subapplications.fileno', 
                'subapplications.scheme_no', 
                'subapplications.floor_number', 
                'subapplications.block_number', 
                'subapplications.unit_number', 
                'subapplications.land_use' // Include land_use
            )
            ->orderBy('subapplications.fileno')
            ->get();
        
        // Fetch the unit_id for each unit from st_unit_measurements
        foreach ($units as $unit) {
            // Get the unit_id from st_unit_measurements based on unit_number
            $unitMeasurement = DB::connection('sqlsrv')
                ->table('st_unit_measurements')
                ->where('unit_no', $unit->unit_number)
                ->select('id')
                ->first();
                
            $unit->unit_id = $unitMeasurement ? $unitMeasurement->id : null;
        }
        
        return response()->json($units);
    }

    /**
     * Get all unit file numbers
     */
    public function getAllUnits()
    {
        // Get all units with additional fields
        $units = DB::connection('sqlsrv')
            ->table('subapplications')
            ->select(
                'subapplications.id', 
                'subapplications.fileno', 
                'subapplications.scheme_no', 
                'subapplications.floor_number', 
                'subapplications.block_number', 
                'subapplications.unit_number', 
                'subapplications.land_use'
            )
            ->orderBy('subapplications.fileno')
            ->get();
        
        // Fetch the unit_id for each unit from st_unit_measurements
        foreach ($units as $unit) {
            // Get the unit_id from st_unit_measurements based on unit_number
            $unitMeasurement = DB::connection('sqlsrv')
                ->table('st_unit_measurements')
                ->where('unit_no', $unit->unit_number)
                ->select('id')
                ->first();
                
            $unit->unit_id = $unitMeasurement ? $unitMeasurement->id : null;
        }
        
        return response()->json($units);
    }
}
