<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use League\Csv\Reader;
use Illuminate\Support\Facades\Validator;
use App\Helpers\SectionalTitleHelper;
use App\Models\FileIndexing;

class PrimaryForm extends Component
{
    use WithFileUploads;

    // Step management
    public $currentStep = 1;
    public $totalSteps = 5;
    
    // Form data
    public $applicantType = 'individual';
    public $applicant_title = '';
    public $first_name = '';
    public $middle_name = '';
    public $surname = '';
    public $corporate_name = '';
    public $rc_number = '';
    
    // Address fields
    public $address_house_no = '';
    public $owner_street_name = '';
    public $owner_district = '';
    public $owner_lga = '';
    public $owner_state = '';
    public $phone_number = '';
    public $owner_email = '';
    
    // Property details
    public $units_count = '';
    public $blocks_count = '';
    public $sections_count = '';
    public $scheme_no = '';
    public $property_street_name = '';
    public $property_lga = '';
    
    // File uploads
    public $application_letter;
    public $building_plan;
    public $ownership_document;
    public $survey_plan;
    public $id_document;
    
    // CSV and Buyers
    public $csvFile;
    public $buyers = [];
    public $csvProcessing = false;
    public $csvError = '';
    
    // Generated properties
    public $npFileNo;
    public $landUse;
    public $currentYear;
    public $serialNo;

    protected $rules = [
        'applicantType' => 'required|in:individual,corporate,multiple',
        'applicant_title' => 'required_if:applicantType,individual|nullable|string|max:20',
        'first_name' => 'required_if:applicantType,individual|nullable|string|max:100',
        'middle_name' => 'nullable|string|max:100',
        'surname' => 'required_if:applicantType,individual|nullable|string|max:100',
        'corporate_name' => 'required_if:applicantType,corporate|nullable|string|max:255',
        'rc_number' => 'required_if:applicantType,corporate|nullable|string|max:100',
        'owner_email' => 'required|email:rfc,dns',
        'phone_number' => 'nullable|regex:/^[0-9+()\\\-\s]{7,20}$/',
        'units_count' => 'required|integer|min:1|max:5000',
        'blocks_count' => 'required|integer|min:1|max:5000',
        'sections_count' => 'required|integer|min:1|max:5000',
        // Scheme number: letters, numbers, hyphens, 3-40 chars
        'scheme_no' => 'required|regex:/^[A-Z0-9-]{3,40}$/',
        'property_street_name' => 'required|string|max:255',
        'property_lga' => 'required|string|max:150',
        // Documents (max 10MB each)
        'application_letter' => 'required|file|max:10240|mimetypes:application/pdf,image/jpeg,image/png,image/jpg',
        'building_plan' => 'required|file|max:10240|mimetypes:application/pdf,image/jpeg,image/png,image/jpg',
        'ownership_document' => 'required|file|max:10240|mimetypes:application/pdf,image/jpeg,image/png,image/jpg',
        'survey_plan' => 'required|file|max:10240|mimetypes:application/pdf,image/jpeg,image/png,image/jpg',
        'id_document' => 'required|file|max:10240|mimetypes:application/pdf,image/jpeg,image/png,image/jpg',
        // Buyer array presence (basic); deeper validation done separately
        'buyers' => 'array|min:1'
    ];

    protected $messages = [
        'applicantType.in' => 'Applicant type must be one of Individual, Corporate Body, or Multiple Owners.',
        'scheme_no.regex' => 'Scheme No format invalid. Use only uppercase letters, numbers, and hyphens (3–40 chars).',
        'units_count.max' => 'Units count seems unusually large (max 5000).',
        'blocks_count.max' => 'Blocks count seems unusually large (max 5000).',
        'sections_count.max' => 'Sections count seems unusually large (max 5000).',
        'buyers.min' => 'Provide at least one buyer / unit entry.',
        'buyers.*.unitNumber.required' => 'Each buyer must have a Unit Number.',
        'buyers.*.buyerEmail.email' => 'One of the buyer emails is not a valid email address.',
    ];

    public function mount()
    {
        // Initialize the form with generated values
        $this->landUse = request()->query('landuse', 'Residential');
        $this->generateNpFileNo();
        $this->initializeBuyers();
    }

    public function generateNpFileNo()
    {
        // Determine the land use code
        $landUseCode = match(strtoupper($this->landUse)) {
            'COMMERCIAL' => 'COM',
            'INDUSTRIAL' => 'IND', 
            'RESIDENTIAL' => 'RES',
            default => 'RES'
        };
        
        // Get the current year
        $this->currentYear = date('Y');
        
        // Get the next serial number based on existing applications
        $lastApplication = DB::connection('sqlsrv')
            ->table('mother_applications')
            ->whereYear('created_at', $this->currentYear)
            ->orderBy('id', 'desc')
            ->first();
        
        $nextSerialNo = 1;
        if ($lastApplication) {
            $nextSerialNo = $lastApplication->id + 1;
        } else {
            $nextSerialNo = DB::connection('sqlsrv')->table('mother_applications')->count() + 1;
        }
        
        $this->serialNo = str_pad($nextSerialNo, 2, '0', STR_PAD_LEFT);
        $this->npFileNo = "ST-{$landUseCode}-{$this->currentYear}-{$this->serialNo}";
    }

    public function initializeBuyers()
    {
        // Initialize with one empty buyer
        $this->buyers = [
            [
                'buyerTitle' => '',
                'firstName' => '',
                'middleName' => '',
                'surname' => '',
                'buyerAddress' => '',
                'buyerEmail' => '',
                'buyerPhone' => '',
                'unitNumber' => '',
                'unitType' => '',
                'unitMeasurement' => ''
            ]
        ];
    }

    public function nextStep()
    {
        $this->validateCurrentStep();
        
        if ($this->currentStep < $this->totalSteps) {
            $this->currentStep++;
        }
    }

    public function previousStep()
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
    }

    public function goToStep($step)
    {
        if ($step >= 1 && $step <= $this->totalSteps) {
            $this->currentStep = $step;
        }
    }

    public function validateCurrentStep()
    {
        switch ($this->currentStep) {
            case 1:
                $rules = [
                    'applicantType' => 'required',
                    'applicant_title' => 'required_if:applicantType,individual',
                    'first_name' => 'required_if:applicantType,individual',
                    'surname' => 'required_if:applicantType,individual',
                    'corporate_name' => 'required_if:applicantType,corporate',
                    'rc_number' => 'required_if:applicantType,corporate',
                    'owner_email' => 'required|email',
                ];
                break;
            case 2:
                $rules = [
                    'units_count' => 'required|integer|min:1|max:5000',
                    'blocks_count' => 'required|integer|min:1|max:5000',
                    'sections_count' => 'required|integer|min:1|max:5000',
                    'scheme_no' => 'required|regex:/^[A-Z0-9-]{3,40}$/',
                    'property_street_name' => 'required|string|max:255',
                    'property_lga' => 'required|string|max:150',
                ];
                break;
            case 3:
                $rules = [
                    'application_letter' => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png',
                    'building_plan' => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png',
                    'ownership_document' => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png',
                    'survey_plan' => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png',
                    'id_document' => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png',
                ];
                break;
            case 4:
                // Buyer validation when leaving Buyers step
                $rules = [
                    'buyers' => 'array|min:1',
                    'buyers.*.buyerTitle' => 'nullable|string|max:20',
                    'buyers.*.firstName' => 'nullable|string|max:100',
                    'buyers.*.middleName' => 'nullable|string|max:100',
                    'buyers.*.surname' => 'nullable|string|max:100',
                    'buyers.*.buyerAddress' => 'nullable|string|max:255',
                    'buyers.*.buyerEmail' => 'nullable|email',
                    'buyers.*.buyerPhone' => ['nullable', 'regex:/^[0-9+()\\\-\s]{7,20}$/'],
                    'buyers.*.unitNumber' => 'required|string|max:50',
                    'buyers.*.unitType' => 'nullable|string|max:100',
                    'buyers.*.unitMeasurement' => 'nullable|string|max:50',
                ];
                break;
            default:
                $rules = [];
        }

        if (!empty($rules)) {
            $this->validate($rules);
        }

        // Extra logical validation for step 4: ensure no duplicate unit numbers
        if ($this->currentStep === 4) {
            $this->assertNoDuplicateUnits();
        }
    }

    public function addBuyer()
    {
        $this->buyers[] = [
            'buyerTitle' => '',
            'firstName' => '',
            'middleName' => '',
            'surname' => '',
            'buyerAddress' => '',
            'buyerEmail' => '',
            'buyerPhone' => '',
            'unitNumber' => '',
            'unitType' => '',
            'unitMeasurement' => ''
        ];
    }

    public function removeBuyer($index)
    {
        if (count($this->buyers) > 1) {
            unset($this->buyers[$index]);
            $this->buyers = array_values($this->buyers);
        }
    }

    public function updatedCsvFile()
    {
        $this->processCsv();
    }

    public function processCsv()
    {
        if (!$this->csvFile) {
            return;
        }

        $this->csvProcessing = true;
        $this->csvError = '';

        try {
            // Get the file path
            $path = $this->csvFile->getRealPath();
            
            // Read CSV
            $csv = Reader::createFromPath($path, 'r');
            $csv->setHeaderOffset(0);
            
            $records = $csv->getRecords();
            $buyers = [];
            
            foreach ($records as $record) {
                $buyers[] = [
                    'buyerTitle' => $record['title'] ?? '',
                    'firstName' => $record['first name'] ?? $record['first_name'] ?? '',
                    'middleName' => $record['middle name'] ?? $record['middle_name'] ?? '',
                    'surname' => $record['surname'] ?? $record['last_name'] ?? '',
                    'buyerAddress' => $record['address'] ?? '',
                    'buyerEmail' => $record['email'] ?? '',
                    'buyerPhone' => $record['phone'] ?? '',
                    'unitNumber' => $record['unit number'] ?? $record['unit_number'] ?? '',
                    'unitType' => $record['unit type'] ?? $record['unit_type'] ?? '',
                    'unitMeasurement' => $record['unit measurement'] ?? $record['unit_measurement'] ?? ''
                ];
            }
            
            if (count($buyers) > 0) {
                $this->buyers = $buyers;
                session()->flash('success', 'CSV imported successfully! ' . count($buyers) . ' buyers loaded.');
            } else {
                $this->csvError = 'No valid records found in CSV file.';
            }
            
        } catch (\Exception $e) {
            Log::error('CSV processing error: ' . $e->getMessage());
            $this->csvError = 'Error processing CSV file: ' . $e->getMessage();
        }

        $this->csvProcessing = false;
    }

    public function downloadTemplate()
    {
        $csvContent = "title,first name,middle name,surname,address,email,phone,unit number,unit type,unit measurement\n";
        $csvContent .= "Mr.,John,Michael,Doe,123 Main St,john.doe@email.com,1234567890,A001,Apartment,50sqm\n";
        $csvContent .= "Mrs.,Jane,,Smith,456 Oak Ave,jane.smith@email.com,0987654321,B002,Flat,75sqm";
        
        return response()->streamDownload(function () use ($csvContent) {
            echo $csvContent;
        }, 'buyers_template.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function submit()
    {
        // Base validation
        $this->validate();

        // Deep buyers validation & logical assertions
        $this->validate([
            'buyers' => 'array|min:1',
            'buyers.*.buyerEmail' => 'nullable|email',
            'buyers.*.buyerPhone' => ['nullable', 'regex:/^[0-9+()\\\-\s]{7,20}$/'],
            'buyers.*.unitNumber' => 'required|string|max:50',
            'buyers.*.unitMeasurement' => 'nullable|string|max:50',
        ]);
        $this->assertNoDuplicateUnits();
        $this->assertAtLeastOneNamedBuyer();
        $this->normalizeDataBeforePersist();

        try {
            DB::connection('sqlsrv')->beginTransaction();

            // Prepare application data (ensure column matches DB schema: np_fileno)
            $applicationData = [
                'np_fileno' => $this->npFileNo,
                'applicant_type' => $this->applicantType,
                'land_use' => strtolower($this->landUse),
                'units_count' => $this->units_count,
                'blocks_count' => $this->blocks_count,
                'sections_count' => $this->sections_count,
                'scheme_no' => strtoupper($this->scheme_no),
                'property_street_name' => strtoupper($this->property_street_name),
                'property_lga' => $this->property_lga,
                'created_at' => now(),
                'updated_at' => now(),
                'user_id' => auth()->id(),
                'status' => 'pending'
            ];

            // Add applicant-specific data
            if ($this->applicantType == 'individual') {
                $applicationData = array_merge($applicationData, [
                    'applicant_title' => $this->applicant_title,
                    'first_name' => strtoupper($this->first_name),
                    'middle_name' => strtoupper($this->middle_name),
                    'surname' => strtoupper($this->surname),
                    'owner_email' => strtolower($this->owner_email),
                    'phone_number' => $this->phone_number,
                ]);
            } else {
                $applicationData = array_merge($applicationData, [
                    'corporate_name' => strtoupper($this->corporate_name),
                    'rc_number' => strtoupper($this->rc_number),
                    'owner_email' => strtolower($this->owner_email),
                    'phone_number' => $this->phone_number,
                ]);
            }

            // Store files
            if ($this->application_letter) {
                $applicationData['application_letter'] = $this->application_letter->store('documents/application_letters', 'public');
            }
            if ($this->building_plan) {
                $applicationData['building_plan'] = $this->building_plan->store('documents/building_plans', 'public');
            }
            if ($this->ownership_document) {
                $applicationData['ownership_document'] = $this->ownership_document->store('documents/ownership_documents', 'public');
            }
            if ($this->survey_plan) {
                $applicationData['survey_plan'] = $this->survey_plan->store('documents/survey_plans', 'public');
            }
            if ($this->id_document) {
                $applicationData['id_document'] = $this->id_document->store('documents/id_documents', 'public');
            }

            // Insert main application
            $applicationId = DB::connection('sqlsrv')->table('mother_applications')->insertGetId($applicationData);

            // Store buyers information into existing dbo.buyer_list table
            foreach ($this->buyers as $buyer) {
                $first = strtoupper(trim((string)($buyer['firstName'] ?? '')));
                $middle = strtoupper(trim((string)($buyer['middleName'] ?? '')));
                $last = strtoupper(trim((string)($buyer['surname'] ?? '')));
                $parts = array_filter([$first, $middle, $last], fn($v) => $v !== '');
                $buyerName = implode(' ', $parts);

                // Only insert if there's at least a name or unit number
                if ($buyerName !== '' || !empty($buyer['unitNumber'])) {
                    DB::connection('sqlsrv')->table('buyer_list')->insert([
                        'application_id' => $applicationId,
                        'buyer_title' => (string)($buyer['buyerTitle'] ?? ''),
                        'buyer_name' => $buyerName,
                        'unit_no' => strtoupper(trim((string)($buyer['unitNumber'] ?? ''))),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::connection('sqlsrv')->commit();
            
            // Log the successful submission
            Log::info('Primary application submitted successfully', [
                'application_id' => $applicationId,
                'np_file_no' => $this->npFileNo,
                'user_id' => auth()->id(),
                'buyers_count' => count($this->buyers)
            ]);
            
            session()->flash('success', 'Application submitted successfully! Your file number is: ' . $this->npFileNo);
            
            // Reset form for new application
            $this->resetForm();
            
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            Log::error('Application submission error: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'file_no' => $this->npFileNo,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            session()->flash('error', 'Error submitting application: ' . $e->getMessage());
        }
    }

    /**
     * Ensure there are no duplicate unit numbers in the buyers list.
     */
    protected function assertNoDuplicateUnits(): void
    {
        $normalized = [];
        foreach ($this->buyers as $idx => $buyer) {
            $unit = strtoupper(trim((string)($buyer['unitNumber'] ?? '')));
            if ($unit === '') {
                // handled by validation rules where required
                continue;
            }
            $normalized[$unit] = ($normalized[$unit] ?? 0) + 1;
        }
        $duplicates = array_keys(array_filter($normalized, fn($count) => $count > 1));
        if (!empty($duplicates)) {
            $message = 'Duplicate unit numbers found: ' . implode(', ', $duplicates);
            $this->addError('buyers_duplicate', $message);
            throw \Illuminate\Validation\ValidationException::withMessages([
                'buyers_duplicate' => $message,
            ]);
        }
    }

    /**
     * Ensure there is at least one buyer with a name or email + unit number.
     */
    protected function assertAtLeastOneNamedBuyer(): void
    {
        $valid = false;
        foreach ($this->buyers as $buyer) {
            $hasName = (trim((string)($buyer['firstName'] ?? '')) !== '') || (trim((string)($buyer['surname'] ?? '')) !== '');
            $hasUnit = trim((string)($buyer['unitNumber'] ?? '')) !== '';
            if ($hasUnit && $hasName) {
                $valid = true;
                break;
            }
        }
        if (!$valid) {
            $message = 'Provide at least one buyer with a name and unit number.';
            $this->addError('buyers_required', $message);
            throw \Illuminate\Validation\ValidationException::withMessages([
                'buyers_required' => $message,
            ]);
        }
    }

    /**
     * Normalize casing and trim fields before persistence.
     */
    protected function normalizeDataBeforePersist(): void
    {
        $this->scheme_no = strtoupper(trim($this->scheme_no));
        $this->property_street_name = strtoupper(trim($this->property_street_name));
        $this->property_lga = strtoupper(trim($this->property_lga));
        $this->first_name = strtoupper(trim($this->first_name));
        $this->middle_name = strtoupper(trim($this->middle_name));
        $this->surname = strtoupper(trim($this->surname));
        $this->corporate_name = strtoupper(trim($this->corporate_name));
        foreach ($this->buyers as &$buyer) {
            $buyer['unitNumber'] = strtoupper(trim((string)($buyer['unitNumber'] ?? '')));
            $buyer['firstName'] = strtoupper(trim((string)($buyer['firstName'] ?? '')));
            $buyer['middleName'] = strtoupper(trim((string)($buyer['middleName'] ?? '')));
            $buyer['surname'] = strtoupper(trim((string)($buyer['surname'] ?? '')));
        }
        unset($buyer);
    }

    public function resetForm()
    {
        // Reset all form fields
        $this->currentStep = 1;
        $this->applicantType = 'individual';
        $this->applicant_title = '';
        $this->first_name = '';
        $this->middle_name = '';
        $this->surname = '';
        $this->corporate_name = '';
        $this->rc_number = '';
        $this->address_house_no = '';
        $this->owner_street_name = '';
        $this->owner_district = '';
        $this->owner_lga = '';
        $this->owner_state = '';
        $this->phone_number = '';
        $this->owner_email = '';
        $this->units_count = '';
        $this->blocks_count = '';
        $this->sections_count = '';
        $this->scheme_no = '';
        $this->property_street_name = '';
        $this->property_lga = '';
        
        // Reset files
        $this->application_letter = null;
        $this->building_plan = null;
        $this->ownership_document = null;
        $this->survey_plan = null;
        $this->id_document = null;
        
        // Reset CSV and buyers
        $this->csvFile = null;
        $this->csvProcessing = false;
        $this->csvError = '';
        
        // Generate new file number
        $this->generateNpFileNo();
        $this->initializeBuyers();
    }

    public function render()
    {
        return view('livewire.primary-form');
    }
}
