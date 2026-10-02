<?php
//!Deeds Department Controller
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeedsDepartmentController extends Controller
{
    private function getApplication($id)
    {
        // Modified to join subapplications with mother_applications to get primary application details
        $application = DB::connection('sqlsrv')->table('subapplications')
            ->select(
                'subapplications.*', 
                'subapplications.id as applicationID', // Add alias for applicationID
                'subapplications.main_application_id as main_application_id', // Add main_application_id if it exists
                'mother_applications.fileno as primary_fileno',
                'mother_applications.fileno as primary_id',
                'mother_applications.first_name as primary_first_name',
                'mother_applications.surname as primary_surname',
                'mother_applications.applicant_title as primary_applicant_title',
                'mother_applications.application_status as primary_application_status',
                'mother_applications.land_use as primary_land_use',
                'mother_applications.id as main_application_id',

                // Property fields with proper aliases
                'mother_applications.property_house_no as property_house_no',
                'mother_applications.property_plot_no as property_plot_no',
                'mother_applications.property_street_name as property_street_name',
                'mother_applications.property_lga as property_lga',
                 
                'mother_applications.applicationID as primary_applicationID' // Get primary app's applicationID
            )
            ->leftJoin('mother_applications', 'subapplications.main_application_id', '=', 'mother_applications.id')
            ->where('subapplications.id', $id)
            ->first();

        if (!$application) {
            return response()->json(['error' => 'Sub application not found'], 404);
        }

        return $application;
    }

    private function getSecondaryApplication($id)
    {
        $application = DB::connection('sqlsrv')->table('dbo.subapplications')
            ->leftJoin('dbo.mother_applications', 'dbo.subapplications.main_application_id', '=', 'dbo.mother_applications.id')
            ->select(
                'dbo.subapplications.*',
                'dbo.subapplications.id as id',
                'dbo.subapplications.id as applicationID', // Add alias for applicationID
                'dbo.mother_applications.fileno as primary_fileno', // Changed alias to primary_fileno
                'dbo.mother_applications.id as primary_id', // Changed alias to primary_id
                'dbo.mother_applications.passport as mother_passport',
                'dbo.mother_applications.multiple_owners_passport as mother_multiple_owners_passport',
                'dbo.mother_applications.applicant_title as primary_applicant_title', // Changed alias to primary_applicant_title
                'dbo.mother_applications.first_name as primary_first_name', // Changed alias to primary_first_name
                'dbo.mother_applications.surname as primary_surname', // Changed alias to primary_surname
                'dbo.mother_applications.corporate_name as mother_corporate_name',
                'dbo.mother_applications.multiple_owners_names as mother_multiple_owners_names',
                'dbo.mother_applications.land_use',
                'dbo.mother_applications.property_house_no',
                'dbo.mother_applications.property_plot_no',
                'dbo.mother_applications.property_street_name',
                'dbo.mother_applications.property_district',
                'dbo.mother_applications.property_lga',
                'dbo.mother_applications.id as main_application_id' // Add main_application_id for reference
            )
            ->where('dbo.subapplications.id', $id)
            ->first();

        if (!$application) {
            return response()->json(['error' => 'Application not found'], 404);
        }

        return $application;
    }

    private function getPrimaryApplication($id)
    {
        $application = DB::connection('sqlsrv')->table('mother_applications')
            ->select(
                'mother_applications.*',
                'mother_applications.id as main_application_id',
                'mother_applications.id as applicationID'
            )
            ->where('mother_applications.id', $id)
            ->first();

        if (!$application) {
            return response()->json(['error' => 'Primary application not found'], 404);
        }

        return $application;
    }

    public function Deeds_Primary(Request $request)
    {
        $PageTitle = 'ST DEEDS DEPARTMENT';
        $PageDescription = 'processing of sectional title deeds  for primary applications';
        
        if ($request->has('id')) {
            $application = $this->getApplication($request->get('id'));
            if ($application instanceof \Illuminate\Http\JsonResponse) {
                return $application;
            }
            
            return view('other_departments.primary_deeds', compact('application', 'PageTitle', 'PageDescription'));
        }
        
        $PrimaryApplications = DB::connection('sqlsrv')->table('dbo.mother_applications')->get();
        return view('other_departments.primary_deeds', compact('PrimaryApplications', 'PageTitle', 'PageDescription'));
    }

    public function Survey_Secondary(Request $request)
    {
        $PageTitle = 'SECTIONAL TITLING  -DEEDS DEPARTMENT';
        $PageDescription = 'processing of sectional title survey  for secondary applications';
        if ($request->has('id')) {
            $application = $this->getSecondaryApplication($request->get('id'));
            if ($application instanceof \Illuminate\Http\JsonResponse) {
                return $application;
            }
            
            return view('other_departments.secondary_survey_view', compact('application', 'PageTitle', 'PageDescription'));
        }

        $SecondaryApplications = DB::connection('sqlsrv')->table('dbo.subapplications')
            ->leftJoin('dbo.mother_applications', 'dbo.subapplications.main_application_id', '=', 'dbo.mother_applications.id')
            ->select(
            'dbo.subapplications.fileno', 
            'dbo.subapplications.applicant_type',
            'dbo.subapplications.scheme_no',
            'dbo.subapplications.id',
            'dbo.subapplications.main_application_id',
            'dbo.subapplications.applicant_title',
            'dbo.subapplications.first_name',
            'dbo.subapplications.surname',
            'dbo.subapplications.corporate_name',
            'dbo.subapplications.multiple_owners_names',
            'dbo.subapplications.phone_number',
            'dbo.subapplications.planning_recommendation_status',
            'dbo.subapplications.application_status',
            'dbo.subapplications.created_at',
            'dbo.subapplications.unit_number',
            'dbo.subapplications.main_id',

            'dbo.subapplications.passport',
            'dbo.subapplications.multiple_owners_passport',
            'dbo.mother_applications.fileno as mother_fileno',
            'dbo.mother_applications.id as mother_application_id',
           'dbo.mother_applications.passport as mother_passport',
            'dbo.mother_applications.multiple_owners_passport as mother_multiple_owners_passport',
            'dbo.mother_applications.applicant_title as mother_applicant_title',
            'dbo.mother_applications.first_name as mother_first_name',
            'dbo.mother_applications.surname as mother_surname',
            'dbo.mother_applications.corporate_name as mother_corporate_name',
            'dbo.mother_applications.multiple_owners_names as mother_multiple_owners_names',
            'dbo.mother_applications.land_use',
            'dbo.mother_applications.property_house_no',
            'dbo.mother_applications.property_plot_no',
            'dbo.mother_applications.property_street_name',
            'dbo.mother_applications.property_district',
            'dbo.mother_applications.property_lga'
            )
            ->get();
         
        return view('other_departments.secondary_survey', compact('SecondaryApplications', 'PageTitle', 'PageDescription')); 
    }

    public function DeedsView($d)
    {
        $PageTitle = 'DEEDS';
        $PageDescription = '';
        
        // Check if this is a secondary application
        $isSecondary = request()->has('is') && request()->get('is') === 'secondary';
        
        if ($isSecondary) {
            // For secondary applications, get the subapplication
            $application = $this->getSecondaryApplication($d);
            if ($application instanceof \Illuminate\Http\JsonResponse) {
                return $application;
            }
            
            // Get deeds data from SectionalCofOReg for secondary applications
            // Check if the table and column names match exactly what's in the database
            $deeds = DB::connection('sqlsrv')->table('SectionalCofOReg')
                ->where('sub_application_id', $d)
                ->first();
            
            // If no data is found, log this for debugging
            if (!$deeds) {
                \Log::info('No deeds found for secondary application: ' . $d);
                
                // Try with different column name to check if that's the issue
                $deedsAlt = DB::connection('sqlsrv')->table('SectionalCofOReg')
                    ->where('sub_application_id', $d)
                    ->first();
                
                if ($deedsAlt) {
                    \Log::info('Found deeds with subapplication_id instead of sub_application_id');
                    $deeds = $deedsAlt;
                }
                
                // Create an empty object to avoid errors in the view
                if (!$deeds) {
                    $deeds = (object)[
                        'serial_no' => '',
                        'page_no' => '',
                        'volume_no' => '',
                        'deeds_time' => '',
                        'deeds_date' => ''
                    ];
                }
            }
            
            // For secondary applications, we don't need CofO data
            $cofoData = null;
        } else {
            // For primary applications, get the primary application
            $application = $this->getPrimaryApplication($d);
            if ($application instanceof \Illuminate\Http\JsonResponse) {
                return $application;
            }
            
            // Get deeds data from landAdministration for primary applications
            $deeds = DB::connection('sqlsrv')->table('landAdministration')
                ->where('application_id', $application->id)
                ->first();
            
            // Get CofO Registration Particulars from Cofo table for primary applications
            $cofoData = $this->getCofORegistrationParticulars($application);
        }

        // Fetch all primary applications to satisfy the template's requirement
        $PrimaryApplications = DB::connection('sqlsrv')->table('dbo.mother_applications')->get();

        return view('other_departments.deeds', compact('application', 'PrimaryApplications', 'PageTitle', 'PageDescription', 'deeds', 'isSecondary', 'cofoData'));
    }

    /**
     * Get CofO Registration Particulars from Cofo table for primary applications
     */
    private function getCofORegistrationParticulars($application)
    {
        try {
            // Try to find CofO data using different file number fields
            $fileNumber = null;
            $cofoData = null;
            
            // Determine the correct file number to use
            if (isset($application->fileno)) {
                $fileNumber = $application->fileno;
            } elseif (isset($application->primary_fileno)) {
                $fileNumber = $application->primary_fileno;
            } elseif (isset($application->mother_fileno)) {
                $fileNumber = $application->mother_fileno;
            }
            
            if ($fileNumber) {
                // Search in Cofo table using various file number fields
                $cofoData = DB::connection('sqlsrv')->table('Cofo')
                    ->select('oldTitleSerialNo', 'oldTitlePageNo', 'oldTitleVolumeNo', 'fileNo')
                    ->where(function($query) use ($fileNumber) {
                        $query->where('fileNo', $fileNumber)
                              ->orWhere('mlsfNo', $fileNumber)
                              ->orWhere('kangisFileNo', $fileNumber)
                              ->orWhere('NewKANGISFileno', $fileNumber);
                    })
                    ->whereNotNull('oldTitleSerialNo')
                    ->whereNotNull('oldTitlePageNo')
                    ->whereNotNull('oldTitleVolumeNo')
                    ->first();
            }
            
            return [
                'data' => $cofoData,
                'fileNumber' => $fileNumber,
                'found' => $cofoData !== null
            ];
            
        } catch (\Exception $e) {
            \Log::error('Error fetching CofO Registration Particulars: ' . $e->getMessage());
            return [
                'data' => null,
                'fileNumber' => null,
                'found' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Store CofO Registration Particulars for primary applications
     */
    public function storeCofOParticulars(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'application_id' => 'required|integer',
                'fileno' => 'required|string',
                'oldTitleSerialNo' => 'required|string|max:255',
                'oldTitlePageNo' => 'required|string|max:255',
                'oldTitleVolumeNo' => 'required|string|max:255',
                'deeds_time' => 'nullable|string',
                'deeds_date' => 'nullable|date',
            ]);

            // Get the primary application to ensure it exists
            $application = DB::connection('sqlsrv')->table('mother_applications')
                ->where('id', $validatedData['application_id'])
                ->first();

            if (!$application) {
                return response()->json([
                    'success' => false,
                    'message' => 'Primary application not found!'
                ], 404);
            }

            // Check if CofO record already exists for this file number
            $existingCofo = DB::connection('sqlsrv')->table('Cofo')
                ->where('fileNo', $validatedData['fileno'])
                ->first();

            if ($existingCofo) {
                // Update existing record
                DB::connection('sqlsrv')->table('Cofo')
                    ->where('fileNo', $validatedData['fileno'])
                    ->update([
                        'oldTitleSerialNo' => $validatedData['oldTitleSerialNo'],
                        'oldTitlePageNo' => $validatedData['oldTitlePageNo'],
                        'oldTitleVolumeNo' => $validatedData['oldTitleVolumeNo'],
                        'deedsTime' => $validatedData['deeds_time'],
                        'deedsDate' => $validatedData['deeds_date'],
                        'updated_at' => now()
                    ]);
            } else {
                // Insert new record
                DB::connection('sqlsrv')->table('Cofo')->insert([
                    'fileNo' => $validatedData['fileno'],
                    'oldTitleSerialNo' => $validatedData['oldTitleSerialNo'],
                    'oldTitlePageNo' => $validatedData['oldTitlePageNo'],
                    'oldTitleVolumeNo' => $validatedData['oldTitleVolumeNo'],
                    'deedsTime' => $validatedData['deeds_time'],
                    'deedsDate' => $validatedData['deeds_date'],
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'CofO Registration Particulars saved successfully!'
            ]);

        } catch (\Exception $e) {
            \Log::error('Error storing CofO Registration Particulars: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 422);
        }
    }
   

 }