<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class SectionalCofORegistrationController extends Controller
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
 
    public function SectionalCofORegistration()
    {
        $PageTitle = 'Sectional Title Certificate Registration';
        $PageDescription = 'Register Certificates of Occupancy for sectional properties';

        // Fetch all unit applications with st_cofo data and SectionalCofOReg data
        $approvedUnitApplications = DB::connection('sqlsrv')->table('subapplications')
            ->join('mother_applications', 'subapplications.main_application_id', '=', 'mother_applications.id')
            ->join('st_cofo', function($join) { // Changed from leftJoin to join (inner join)
                $join->on('subapplications.id', '=', 'st_cofo.sub_application_id')
                     ->where('st_cofo.is_active', 1);
            })
            ->leftJoin('SectionalCofOReg', 'subapplications.id', '=', 'SectionalCofOReg.sub_application_id')
            ->leftJoin('users', 'SectionalCofOReg.created_by', '=', 'users.id')
            ->where('subapplications.planning_recommendation_status', 'Approved')
            ->where('subapplications.application_status', 'Approved')
            ->select(
                'subapplications.id as sub_id',
                'subapplications.fileno',
                'subapplications.applicant_title',
                'subapplications.first_name',
                'subapplications.surname',
                'subapplications.corporate_name',
                'subapplications.multiple_owners_names',
                'subapplications.block_number',
                'subapplications.floor_number',
                'subapplications.unit_number',
                'subapplications.application_status',
                'subapplications.scheme_no',
                'subapplications.planning_recommendation_status',
                'subapplications.commercial_type',
                'subapplications.industrial_type',
                'subapplications.residence_type',
                'mother_applications.id as mother_id',
                'mother_applications.property_lga',
                'mother_applications.land_use',
                'st_cofo.id as cofo_id',
                'st_cofo.file_no',
                'st_cofo.plot_no',
                'st_cofo.block_no',
                'st_cofo.floor_no',
                'st_cofo.flat_no',
                'st_cofo.holder_name',
                'st_cofo.holder_address',
                'st_cofo.certificate_number',
                'st_cofo.land_use as cofo_land_use',
                'st_cofo.start_date',
                'st_cofo.total_term',
                'st_cofo.remaining_term',
                'st_cofo.property_house_no',
                'st_cofo.property_street_name',
                'st_cofo.property_district',
                'st_cofo.property_lga as cofo_property_lga',
                'st_cofo.property_state',
                'st_cofo.page_no',
                'st_cofo.issued_date',
                'st_cofo.signed_by',
                'st_cofo.signed_title',
                'st_cofo.created_at',
                'st_cofo.is_active',
                'SectionalCofOReg.Sectional_Title_File_No',
                'SectionalCofOReg.STM_Ref', // Add this line to select the STM_Ref field
                'SectionalCofOReg.Applicant_Name',
                'SectionalCofOReg.Tenure_Period',
                'SectionalCofOReg.Deeds_Transfer',
                'SectionalCofOReg.Deeds_Serial_No',
                
                'SectionalCofOReg.Current_Owner',
                'SectionalCofOReg.Occupation',
                'SectionalCofOReg.serial_no',
                'SectionalCofOReg.page_no as reg_page_no',
                'SectionalCofOReg.volume_no',
                'SectionalCofOReg.deeds_time',
                'SectionalCofOReg.deeds_date',
                'SectionalCofOReg.created_by as reg_created_by',
                'SectionalCofOReg.status as reg_status',
                DB::raw("CONCAT(users.first_name, ' ', users.last_name) as reg_creator_name")
            )
            ->get();

        // Count statistics for the statistics cards
        $pendingCount = 0;
        $registeredCount = 0;
        $rejectedCount = 0;
        $totalCount = $approvedUnitApplications->count();

        // Build lookup of file numbers that have a registered ST Assignment
        $fileNos = $approvedUnitApplications->pluck('fileno')->filter()->unique()->values()->toArray();
        $registeredAssignmentFilenos = [];
        if (!empty($fileNos)) {
            $registeredAssignmentFilenos = DB::connection('sqlsrv')
                ->table('deed_registrations')
                ->whereIn('fileno', $fileNos)
                ->where('instrument_type', 'ST Assignment (Transfer of Title)')
                ->where('status', 'registered')
                ->pluck('fileno')
                ->flip()
                ->toArray();
        }

        // Process owner names and determine status
        foreach ($approvedUnitApplications as $application) {
            if (!empty($application->multiple_owners_names)) {
                $ownerArray = json_decode($application->multiple_owners_names, true);
                $application->owner_name = $ownerArray ? implode(', ', $ownerArray) : null;
            } elseif (!empty($application->corporate_name)) {
                $application->owner_name = $application->corporate_name;
            } else {
                $application->owner_name = trim($application->applicant_title . ' ' . $application->first_name . ' ' . $application->surname);
            }
            
            
            // If it exists in st_cofo but not in SectionalCofOReg, it's still "pending"
            if (!empty($application->reg_status)) {
                // If it has a SectionalCofOReg entry
                if (strtolower($application->reg_status) === 'rejected') {
                    $application->status = 'rejected';
                    $rejectedCount++;
                } else {
                    $application->status = 'registered';
                    $registeredCount++;
                }
            } else {
                // No SectionalCofOReg entry - so it's pending regardless of st_cofo status
                $application->status = 'pending';
                $pendingCount++;
            }

            $application->assignment_registered = isset($registeredAssignmentFilenos[$application->fileno]);

            // Format property description
            $application->property_description = 'Unit ' . $application->unit_number . 
                (!empty($application->block_number) ? ', Block ' . $application->block_number : '') .
                (!empty($application->property_street_name) ? ', ' . $application->property_street_name : '') .
                (!empty($application->property_district) ? ', ' . $application->property_district : '');
                
            // Set registration date (prefer st_cofo.issued_date over SectionalCofOReg.Registration_Date)

            

            // Determine unit type based on which type column is not null
            if (!empty($application->commercial_type)) {
                $application->unit_type = $application->commercial_type; // Store the actual value, not the column name
            } elseif (!empty($application->industrial_type)) {
                $application->unit_type = $application->industrial_type; // Store the actual value, not the column name
            } elseif (!empty($application->residence_type)) {
                $application->unit_type = $application->residence_type; // Store the actual value, not the column name
            } else {
                // Default if no type is set
                $application->unit_type = 'Unknown';
            }
        }

        return view('st_registration.index', compact(
            'approvedUnitApplications',
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
            ->leftJoin('st_cofo', function($join) {
                $join->on('subapplications.id', '=', 'st_cofo.sub_application_id')
                    ->where('st_cofo.is_active', 1);
            })
            ->where('subapplications.id', $id)
            ->first();

        if (!$application) {
            return redirect()->back()->with('error', 'Application not found');
        }

        return view('st_registration.generate', compact('application'));
    }

    public function save(Request $request)
    {
        $request->validate([
            'sub_application_id' => 'required',
            'certificate_number' => 'required',
       
            'signed_by' => 'required',
            'signed_title' => 'required'
        ]);

        // Check if a record already exists
        $existingCofo = DB::connection('sqlsrv')->table('st_cofo')
            ->where('sub_application_id', $request->sub_application_id)
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
                ->where('id', $request->sub_application_id)
                ->first();

            if (!$subApplication) {
                return redirect()->back()->with('error', 'Sub-application not found');
            }

            DB::connection('sqlsrv')->table('st_cofo')->insert([
                'sub_application_id' => $request->sub_application_id,
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

        return redirect()->route('st_registration.index')->with('success', 'Certificate of Occupancy has been registered successfully');
    }

    public function view($id)
    {
        $application = DB::connection('sqlsrv')->table('subapplications')
            ->join('mother_applications', 'subapplications.main_application_id', '=', 'mother_applications.id')
            ->leftJoin('st_cofo', function($join) {
                $join->on('subapplications.id', '=', 'st_cofo.sub_application_id')
                    ->where('st_cofo.is_active', 1);
            })
            ->leftJoin('SectionalCofOReg', 'subapplications.id', '=', 'SectionalCofOReg.sub_application_id')
            ->where('subapplications.id', $id)
            ->first();

        if (!$application) {
            return redirect()->back()->with('error', 'Application not found');
        }

        return view('st_registration.view', compact('application'));
    }

    public function getNextSerialNumber()
    {
        // Get the latest volume and page numbers
        $latestRecord = DB::connection('sqlsrv')->table('SectionalCofOReg')
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
        
        return response()->json([
            'serial_no' => $serialNo,
            'page_no' => $pageNo,
            'volume_no' => $volumeNo,
            'deeds_serial_no' => "$serialNo/$pageNo/$volumeNo"
        ]);
    }

    public function validateDeedsTime($time)
    {
        // Validate time format (must contain AM or PM)
        return preg_match('/^(0?[1-9]|1[0-2]):[0-5][0-9]\s?(AM|PM|am|pm)$/', $time);
    }

    public function registerSingle(Request $request)
    {
        $request->validate([
            'sub_application_id' => 'required|integer',
            'sectional_title_file_no' => 'required|string|unique:sqlsrv.SectionalCofOReg,Sectional_Title_File_No',
            'applicant_name' => 'required|string',
            'tenure_period' => 'required',
            'current_owner' => 'required|string',
            'occupation' => 'required|string',
            'deeds_time' => 'required|string',
            'deeds_date' => 'required|date',
          
        ]);
        
        // Verify the sub application exists
        $subApplication = DB::connection('sqlsrv')->table('subapplications')
            ->where('id', $request->sub_application_id)
            ->first();
            
        if (!$subApplication) {
            return response()->json(['error' => 'Application not found. The sub-application ID is invalid.'], 404);
        }
        
        if (!$this->validateDeedsTime($request->deeds_time)) {
            return response()->json(['error' => 'Deeds time must be in format HH:MM AM/PM'], 422);
        }
        
        // Get next serial number
        $serialData = $this->getNextSerialNumber()->getData(true);
        
        try {
            DB::connection('sqlsrv')->beginTransaction();
            
            // Generate STM reference number in format STM-YYYY-XXX
            $year = date('Y');
            $lastRef = DB::connection('sqlsrv')->table('SectionalCofOReg')
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
         
            $userId = Auth::id();
            
            // Create SectionalCofOReg record
            DB::connection('sqlsrv')->table('SectionalCofOReg')->insert([
                'sub_application_id' => $request->sub_application_id,
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
            
            return response()->json([
                'success' => true,
                'message' => 'Certificate of Occupancy registered successfully',
                'serial_data' => $serialData,
                'stm_ref' => $stmRef
            ]);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json(['error' => 'Failed to register CofO: ' . $e->getMessage()], 500);
        }
    }

    public function registerBatch(Request $request)
    {
        $request->validate([
            'batch_entries' => 'required|array',
            'batch_entries.*.sub_application_id' => 'required|integer',
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
                $existing = DB::connection('sqlsrv')->table('SectionalCofOReg')
                    ->where('Sectional_Title_File_No', $entry['sectional_title_file_no'])
                    ->first();
                    
                if ($existing) {
                    throw new \Exception("Sectional Title File No already exists: " . $entry['sectional_title_file_no']);
                }
            }
            
            // Generate base STM reference
            $year = date('Y');
            $lastRef = DB::connection('sqlsrv')->table('SectionalCofOReg')
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
                DB::connection('sqlsrv')->table('SectionalCofOReg')->insert([
                    'sub_application_id' => $entry['sub_application_id'],
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
            'sub_application_id' => 'required|integer',
        ]);
        
        try {
            DB::connection('sqlsrv')->beginTransaction();
            
            // Update or insert a record in SectionalCofOReg with status 'rejected'
            DB::connection('sqlsrv')->table('SectionalCofOReg')->updateOrInsert(
                ['sub_application_id' => $request->sub_application_id],
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
            return response()->json(['error' => 'Failed to decline registration: ' . $e->getMessage()], 500);
        }
    }
}
