<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class STTransferOfTitleController extends Controller
{
    private function getApplication($id)
    {
        $application = DB::connection('sqlsrv')->table('mother_applications')
            ->where('id', $id)
            ->first();

        if (!$application) {
            return response()->json(['error' => 'Application not found'], 404);
        }

        return $application;
    }
 
    public function StTransfer()
    {
        $PageTitle = 'Assignments ';
        $PageDescription = '';

        // Fetch all approved applications with Sectional_title_transfer data
        $approvedApplications = DB::connection('sqlsrv')->table('mother_applications')
            ->leftJoin('Sectional_title_transfer', 'mother_applications.id', '=', 'Sectional_title_transfer.application_id')
            ->leftJoin('users', 'Sectional_title_transfer.created_by', '=', 'users.id')
            ->where('mother_applications.planning_recommendation_status', 'Approved')
            ->where('mother_applications.application_status', 'Approved')
            ->select(
                'mother_applications.id as mother_id',
                'mother_applications.fileno',
                'mother_applications.applicant_title',
                'mother_applications.first_name',
                'mother_applications.surname',
                'mother_applications.corporate_name',
                'mother_applications.multiple_owners_names',
                'mother_applications.NoOfBlocks',
                'mother_applications.NoOfUnits',
                'mother_applications.NoOfSections',
                'mother_applications.application_status',
                'mother_applications.scheme_no',
                'mother_applications.planning_recommendation_status',
                'mother_applications.commercial_type',
                'mother_applications.industrial_type',
                'mother_applications.residential_type',
                'mother_applications.property_lga',
                'mother_applications.land_use',
                'mother_applications.property_street_name',
                'mother_applications.property_district',
                'mother_applications.property_state',
                'Sectional_title_transfer.Sectional_Title_File_No',
                'Sectional_title_transfer.STM_Ref', 
                'Sectional_title_transfer.Applicant_Name',
                'Sectional_title_transfer.Tenure_Period',
                'Sectional_title_transfer.Deeds_Transfer',
                'Sectional_title_transfer.Deeds_Serial_No',
                'Sectional_title_transfer.Current_Owner',
                'Sectional_title_transfer.Occupation',
                'Sectional_title_transfer.serial_no',
                'Sectional_title_transfer.page_no as reg_page_no',
                'Sectional_title_transfer.volume_no',
                'Sectional_title_transfer.deeds_time',
                'Sectional_title_transfer.deeds_date',
                'Sectional_title_transfer.created_by as reg_created_by',
                'Sectional_title_transfer.status as reg_status',
                DB::raw("CONCAT(users.first_name, ' ', users.last_name) as reg_creator_name")
            )
            ->get();

        // Count statistics for the statistics cards
        $pendingCount = 0;
        $registeredCount = 0;
        $rejectedCount = 0;
        $totalCount = $approvedApplications->count();

        // Process owner names and determine status
        foreach ($approvedApplications as $application) {
            if (!empty($application->multiple_owners_names)) {
                $ownerArray = json_decode($application->multiple_owners_names, true);
                $application->owner_name = $ownerArray ? implode(', ', $ownerArray) : null;
            } elseif (!empty($application->corporate_name)) {
                $application->owner_name = $application->corporate_name;
            } else {
                $application->owner_name = trim($application->applicant_title . ' ' . $application->first_name . ' ' . $application->surname);
            }
            
            // Determine status from Sectional_title_transfer
            if (!empty($application->reg_status)) {
                // If it has a Sectional_title_transfer entry
                if (strtolower($application->reg_status) === 'rejected') {
                    $application->status = 'rejected';
                    $rejectedCount++;
                } else {
                    $application->status = 'registered';
                    $registeredCount++;
                }
            } else {
                // No Sectional_title_transfer entry - so it's pending
                $application->status = 'pending';
                $pendingCount++;
            }
            
            // Format property description
            $application->property_description = 
                (!empty($application->property_house_no) ? 'House No: ' . $application->property_house_no . ', ' : '') .
                (!empty($application->property_plot_no) ? 'Plot No: ' . $application->property_plot_no . ', ' : '') .
                (!empty($application->property_street_name) ? $application->property_street_name . ', ' : '') .
                (!empty($application->property_district) ? $application->property_district . ', ' : '') .
                (!empty($application->property_state) ? $application->property_state : '');
                
            // Determine unit type based on which type column is not null
            if (!empty($application->commercial_type)) {
                $application->unit_type = $application->commercial_type;
            } elseif (!empty($application->industrial_type)) {
                $application->unit_type = $application->industrial_type;
            } elseif (!empty($application->residential_type)) {
                $application->unit_type = $application->residential_type;
            } else {
                // Default if no type is set
                $application->unit_type = 'Unknown';
            }
        }

        return view('st_transfer.index', compact(
            'approvedApplications',
            'PageTitle',
            'PageDescription',
            'pendingCount',
            'registeredCount',
            'rejectedCount',
            'totalCount'
        ));
    }

    // Add generate and save methods for CofO registration
    public function generate($id)
    {
        // Fetch the specific subapplication
        $application = DB::connection('sqlsrv')->table('subapplications')
            ->join('mother_applications', 'subapplications.main_application_id', '=', 'mother_applications.id')
            ->where('subapplications.id', $id)
            ->first();

        if (!$application) {
            return redirect()->back()->with('error', 'Application not found');
        }

        return view('st_transfer.generate', compact('application'));
    }

    public function save(Request $request)
    {
        $request->validate([
            'application_id' => 'required',
            'certificate_number' => 'required',
       
            'signed_by' => 'required',
            'signed_title' => 'required'
        ]);

        // Check if a record already exists
        $existingCofo = DB::connection('sqlsrv')->table('st_cofo')
            ->where('application_id', $request->application_id)
            ->where('is_active', 1)
            ->first();

        if ($existingCofo) {
            // Update existing record
            DB::connection('sqlsrv')->table('st_cofo')
                ->where('id', $existingCofo->id)
                ->update([
                    'certificate_number' => $request->certificate_number,

                    'signed_by' => $request->signed_by,
                    'signed_title' => $request->signed_title,
                    'updated_by' => auth()->id(),
                    'updated_at' => now()
                ]);
        } else {
            // Insert new record
            $subApplication = DB::connection('sqlsrv')->table('subapplications')
                ->where('id', $request->application_id)
                ->first();

            if (!$subApplication) {
                return redirect()->back()->with('error', 'Sub-application not found');
            }

            DB::connection('sqlsrv')->table('st_cofo')->insert([
                'application_id' => $request->application_id,
                'mother_application_id' => $subApplication->main_application_id,
                'file_no' => $subApplication->fileno,
                'certificate_number' => $request->certificate_number,
                'holder_name' => $subApplication->first_name . ' ' . $subApplication->surname,
               
                'signed_by' => $request->signed_by,
                'signed_title' => $request->signed_title,
                'is_active' => 1,
                'created_by' => auth()->id(),
                'created_at' => now()
            ]);
        }

        return redirect()->route('st_transfer.index')->with('success', 'Certificate of Occupancy has been registered successfully');
    }

    public function view($id)
    {
        $PageTitle = 'View ST Transfer of Title';
        $PageDescription = '';
        try {
            // Use application_id instead of sub_application_id
            $application = DB::connection('sqlsrv')->table('mother_applications')
                ->leftJoin('Sectional_title_transfer', 'mother_applications.id', '=', 'Sectional_title_transfer.application_id')
                ->leftJoin('users', 'Sectional_title_transfer.created_by', '=', 'users.id')
                ->select(
                    'mother_applications.*',
                    'Sectional_title_transfer.*',
                    'mother_applications.id as mother_id',
                    'Sectional_title_transfer.page_no as reg_page_no',
                    'Sectional_title_transfer.created_by as reg_created_by',
                    'Sectional_title_transfer.status as reg_status',
                    DB::raw("CONCAT(users.first_name, ' ', users.last_name) as reg_creator_name")
                )
                ->where('mother_applications.id', $id)
                ->first();

            if (!$application) {
                \Log::error('Application not found', ['id' => $id]);
                return redirect()->route('st_transfer.index')->with('error', 'Application not found');
            }

            // Process owner names if not already set
            if (empty($application->owner_name)) {
                if (!empty($application->multiple_owners_names)) {
                    $ownerArray = json_decode($application->multiple_owners_names, true);
                    $application->owner_name = $ownerArray ? implode(', ', $ownerArray) : null;
                } elseif (!empty($application->corporate_name)) {
                    $application->owner_name = $application->corporate_name;
                } else {
                    $application->owner_name = trim($application->applicant_title . ' ' . $application->first_name . ' ' . $application->surname);
                }
            }

            return view('st_transfer.view', compact('application', 'PageTitle', 'PageDescription'));
        } catch (\Exception $e) {
            \Log::error('Error in view method', [
                'id' => $id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->route('st_transfer.index')->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }

    public function getNextSerialNumber()
    {
        try {
            // Get the latest volume and page numbers
            $latestRecord = DB::connection('sqlsrv')->table('Sectional_title_transfer')
                ->select('volume_no', 'page_no', 'serial_no')
                ->orderBy('volume_no', 'desc')
                ->orderBy('page_no', 'desc')
                ->first();
            
            if (!$latestRecord) {
                // First record ever
                return response()->json([
                    'serial_no' => 1,
                    'page_no' => 1,
                    'volume_no' => 1,
                    'deeds_serial_no' => '1/1/1'
                ]);
            }
            
            $volumeNo = $latestRecord->volume_no;
            $pageNo = $latestRecord->page_no;
            $serialNo = $latestRecord->serial_no;
            
            // Check if we need to start a new volume
            if ($pageNo >= 100) {
                $volumeNo++;
                $pageNo = 1;
                $serialNo = 1;
            } else {
                $pageNo++;
                $serialNo++;
            }
            
            $result = [
                'serial_no' => $serialNo,
                'page_no' => $pageNo,
                'volume_no' => $volumeNo,
                'deeds_serial_no' => "$serialNo/$pageNo/$volumeNo"
            ];
            
            \Log::info('Generated next serial number', $result);
            
            return response()->json($result);
        } catch (\Exception $e) {
            \Log::error('Error generating next serial number', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Failed to generate serial number: ' . $e->getMessage()], 500);
        }
    }

    public function validateDeedsTime($time)
    {
        // Validate time format (must contain AM or PM)
        return preg_match('/^(0?[1-9]|1[0-2]):[0-5][0-9]\s?(AM|PM|am|pm)$/', $time);
    }

    public function registerSingle(Request $request)
    {
        try {
            // Log incoming request for debugging
            \Log::info('Register Single Request', [
                'data' => $request->all()
            ]);
            
            $request->validate([
                'mother_application_id' => 'required|integer',
                'sectional_title_file_no' => 'required|string|unique:sqlsrv.Sectional_title_transfer,Sectional_Title_File_No',
                'applicant_name' => 'required|string',
                'tenure_period' => 'required',
                'current_owner' => 'required|string',
                'occupation' => 'required|string',
                'deeds_time' => 'required|string',
                'deeds_date' => 'required|date',
            ]);
            
            // Verify the application exists
            $application = DB::connection('sqlsrv')->table('mother_applications')
                ->where('id', $request->mother_application_id)
                ->first();
                
            if (!$application) {
                \Log::error('Application not found', ['id' => $request->mother_application_id]);
                return response()->json(['error' => 'Application not found. The application ID is invalid.'], 404);
            }
            
            if (!$this->validateDeedsTime($request->deeds_time)) {
                \Log::error('Invalid deeds time format', ['time' => $request->deeds_time]);
                return response()->json(['error' => 'Deeds time must be in format HH:MM AM/PM'], 422);
            }
            
            // Get next serial number
            $serialData = $this->getNextSerialNumber()->getData(true);
            
            try {
                DB::connection('sqlsrv')->beginTransaction();
                
                // Generate STM reference number in format STM-YYYY-XXX
                $year = date('Y');
                $lastRef = DB::connection('sqlsrv')->table('Sectional_title_transfer')
                    ->where('STM_Ref', 'like', "STM-$year-%")
                    ->orderBy('id', 'desc')
                    ->value('STM_Ref');
                    
                if ($lastRef) {
                    $lastNumber = (int)substr($lastRef, -3);
                    $newNumber = $lastNumber + 1;
                } else {
                    $newNumber = 1;
                }
                
                $stmRef = "STM-$year-" . str_pad($newNumber, 3, '0', STR_PAD_LEFT);
                
                // Ensure we have a valid user ID
                $userId = Auth::id() ?: 1; // Default to ID 1 if not authenticated
                
                // Create Sectional_title_transfer record
                DB::connection('sqlsrv')->table('Sectional_title_transfer')->insert([
                    'application_id' => $request->mother_application_id,
                    'Sectional_Title_File_No' => $request->sectional_title_file_no,
                    'STM_Ref' => $stmRef,
                    'Applicant_Name' => $request->applicant_name,
                    'Tenure_Period' => $request->tenure_period,
                    'Deeds_Transfer' => $request->deeds_transfer ?? null,
                    'Deeds_Serial_No' => $serialData['deeds_serial_no'],
                   
                    'Current_Owner' => $request->current_owner,
                    'Occupation' => $request->occupation,
                    'serial_no' => $serialData['serial_no'],
                    'page_no' => $serialData['page_no'],
                    'volume_no' => $serialData['volume_no'],
                    'deeds_time' => $request->deeds_time,
                    'deeds_date' => $request->deeds_date,
                    'created_by' => $userId,
                    'created_at' => now(),
                    'status' => 'registered'
                ]);
                
                DB::connection('sqlsrv')->commit();
                
                \Log::info('CofO registered successfully', [
                    'application_id' => $request->mother_application_id,
                    'serial_data' => $serialData,
                    'stm_ref' => $stmRef
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Certificate of Occupancy registered successfully',
                    'serial_data' => $serialData,
                    'stm_ref' => $stmRef
                ]);
            } catch (\Exception $e) {
                DB::connection('sqlsrv')->rollBack();
                \Log::error('Database error while registering CofO', [
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                return response()->json(['error' => 'Failed to register CofO: ' . $e->getMessage()], 500);
            }
        } catch (\Exception $e) {
            \Log::error('Exception in registerSingle', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    public function registerBatch(Request $request)
    {
        $request->validate([
            'batch_entries' => 'required|array',
            'batch_entries.*.application_id' => 'required|integer',
            'batch_entries.*.sectional_title_file_no' => 'required|string',
            'batch_entries.*.applicant_name' => 'required|string',
            'batch_entries.*.tenure_period' => 'required',
            'batch_entries.*.current_owner' => 'required|string',
            'batch_entries.*.occupation' => 'required|string',
            'batch_entries.*.serial_no' => 'required|integer',
            'batch_entries.*.page_no' => 'required|integer',
            'batch_entries.*.volume_no' => 'required|integer',
            'deeds_time' => 'required|string',
            'deeds_date' => 'required|date',
           
        ]);
        
        if (!$this->validateDeedsTime($request->deeds_time)) {
            return response()->json(['error' => 'Deeds time must be in format HH:MM AM/PM'], 422);
        }
        
        try {
            DB::connection('sqlsrv')->beginTransaction();
            
            $results = [];
            $fileNos = [];
            
            // Check for duplicates in the batch
            foreach ($request->batch_entries as $entry) {
                if (in_array($entry['sectional_title_file_no'], $fileNos)) {
                    throw new \Exception("Duplicate Sectional Title File No: " . $entry['sectional_title_file_no']);
                }
                
                $fileNos[] = $entry['sectional_title_file_no'];
                
                // Check for existing records
                $existing = DB::connection('sqlsrv')->table('Sectional_title_transfer')
                    ->where('Sectional_Title_File_No', $entry['sectional_title_file_no'])
                    ->first();
                    
                if ($existing) {
                    throw new \Exception("Sectional Title File No already exists: " . $entry['sectional_title_file_no']);
                }
            }
            
            // Generate base STM reference
            $year = date('Y');
            $lastRef = DB::connection('sqlsrv')->table('Sectional_title_transfer')
                ->where('STM_Ref', 'like', "STM-$year-%")
                ->orderBy('id', 'desc')
                ->value('STM_Ref');
                
            if ($lastRef) {
                $lastNumber = (int)substr($lastRef, -3);
                $newNumber = $lastNumber + 1;
            } else {
                $newNumber = 1;
            }
           
            // Ensure we have a valid user ID
            $userId = Auth::id();
          
            
            foreach ($request->batch_entries as $index => $entry) {
                // Generate unique STM reference for each entry in the batch
                $stmRef = "STM-$year-" . str_pad($newNumber + $index, 3, '0', STR_PAD_LEFT);
                
                // Use the provided serial numbers
                $serialData = [
                    'serial_no' => $entry['serial_no'],
                    'page_no' => $entry['page_no'],
                    'volume_no' => $entry['volume_no'],
                    'deeds_serial_no' => "{$entry['serial_no']}/{$entry['page_no']}/{$entry['volume_no']}"
                ];
                
                // Insert record
                DB::connection('sqlsrv')->table('Sectional_title_transfer')->insert([
                    'application_id' => $entry['application_id'],
                    'Sectional_Title_File_No' => $entry['sectional_title_file_no'],
                    'STM_Ref' => $stmRef,
                    'Applicant_Name' => $entry['applicant_name'],
                    'Tenure_Period' => $entry['tenure_period'],
                    'Deeds_Transfer' => $entry['deeds_transfer'] ?? null,
                    'Deeds_Serial_No' => $serialData['deeds_serial_no'],
                   
                    'Current_Owner' => $entry['current_owner'],
                    'Occupation' => $entry['occupation'],
                    'serial_no' => $serialData['serial_no'],
                    'page_no' => $serialData['page_no'],
                    'volume_no' => $serialData['volume_no'],
                    'deeds_time' => $request->deeds_time,
                    'deeds_date' => $request->deeds_date,
                    'created_by' => $userId,
                    'created_at' => now(),
                    'status' => 'registered'
                ]);
                
                $results[] = [
                    'sectional_title_file_no' => $entry['sectional_title_file_no'],
                    'stm_ref' => $stmRef,
                    'serial_data' => $serialData
                ];
            }
            
            DB::connection('sqlsrv')->commit();
            
            return response()->json([
                'success' => true,
                'message' => count($results) . ' Certificates of Occupancy registered successfully',
                'results' => $results
            ]);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json(['error' => 'Failed to register CofOs: ' . $e->getMessage()], 500);
        }
    }

    public function declineRegistration(Request $request)
    {
        $request->validate([
            'application_id' => 'required|integer',
        ]);
        
        try {
            DB::connection('sqlsrv')->beginTransaction();
            
            // Update or insert a record in Sectional_title_transfer with status 'rejected'
            DB::connection('sqlsrv')->table('Sectional_title_transfer')->updateOrInsert(
                ['application_id' => $request->application_id],
                [
                    'status' => 'rejected',
                    'created_by' => Auth::id(),
                    'created_at' => now(),
                ]
            );
            
            DB::connection('sqlsrv')->commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Registration has been declined successfully',
            ]);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            
            // Log the error
            \Log::error('Failed to decline registration: ' . $e->getMessage(), [
                'application_id' => $request->application_id,
                'exception' => $e
            ]);
            
            return response()->json(['error' => 'Failed to decline registration: ' . $e->getMessage()], 500);
        }
    }

    public function debug()
    {
        try {
            return response()->json([
                'status' => 'ok',
                'message' => 'API is working',
                'timestamp' => now()->toDateTimeString(),
                'environment' => app()->environment(),
                'debug' => config('app.debug')
            ]);
        } catch (\Exception $e) {
            \Log::error('Debug route failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
