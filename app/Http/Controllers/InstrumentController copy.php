<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Services\InstrumentCaptureService;

class InstrumentController extends Controller
{
    public function __construct(
        protected InstrumentCaptureService $captureService
    ) {
    }

    public function index()
    {
        $PageTitle = 'Instrument Capture';
        $PageDescription = '';

        // Base statistics query (independent)
        $statsBase = DB::connection('sqlsrv')->table('instrument_capture')->where('is_deleted', 0);
        $totalCount = (clone $statsBase)->count();
        $pendingCount = (clone $statsBase)->whereNull('registration_number')->count();
        $verifiedCount = (clone $statsBase)->whereNotNull('registration_number')->count();

        // Main paginated results query
        $instruments = DB::connection('sqlsrv')->table('instrument_capture as ic')
            ->leftJoin('deed_registrations as dr', function($join) {
                $join->on('ic.registration_number', '=', 'dr.registration_number')
                     ->on('ic.instrument_type', '=', 'dr.instrument_type');
            })
            ->where('ic.is_deleted', 0)
            ->select('ic.*', DB::raw("CONCAT('deed_reg_', dr.id) as registered_instrument_id"))
            ->orderBy('ic.created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        // Prepare data for JS (only for the current page)
        $fullDataForJs = collect($instruments->items())->map(function($item) {
            return (array)$item;
        });

        return view('instruments.index', compact('PageTitle', 'PageDescription', 'instruments', 'totalCount', 'pendingCount', 'verifiedCount', 'fullDataForJs'));
    }

    public function create()
    {
        $PageTitle = 'Instrument Capture';
        $PageDescription = 'Capture a new instrument ';

        // Fetch states, lgas, and districts for dropdowns
        $states = DB::connection('sqlsrv')->table('States')->orderBy('StateName')->get();
        $lgas = DB::connection('sqlsrv')->table('lgas')->where('is_active', 1)->orderBy('name')->get();
        $districts = DB::connection('sqlsrv')->table('districts')->where('is_active', 1)->orderBy('name')->get();

        return view('instruments.create', compact('PageTitle', 'PageDescription', 'states', 'lgas', 'districts'));
    }

    public function store(Request $request)
    {
        Log::info('Instrument Store Request', $request->all());
        try {
            // Basic Validation
            $instrumentType = $request->input('instrument_type');
            $rules = [
                'instrument_type' => 'required|string',
            ];

            if (stripos($instrumentType, 'Occupancy Permit') !== false) {
                $rules['op_serial_number'] = 'required';
            }

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation failed',
                        'errors' => $validator->errors()
                    ], 422);
                }
                return redirect()->back()
                    ->withErrors($validator)
                    ->withInput();
            }

            // Delegate to Service
            $result = $this->captureService->capture($request->all());

            if ($result['success']) {
                $message = 'Instrument registered successfully. Ref: ' . ($result['reg_number'] ?? '');
                if ($request->expectsJson()) {
                    return response()->json(['success' => true, 'message' => $message]);
                }
                return redirect()->route('instruments.index')->with('success', $message);
            }

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Failed to register instrument.'], 500);
            }
            return redirect()->back()->with('error', 'Failed to register instrument')->withInput();

        } catch (\Exception $e) {
            Log::error('Instrument Capture Error: ' . $e->getMessage());
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()], 500);
            }
            return redirect()->back()->with('error', 'An error occurred: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * API: Check for duplicate instrument
     */
    public function checkDuplicate(Request $request)
    {
        $fileno = $request->query('fileno');
        $type = $request->query('instrument_type');
        $date = $request->query('date'); // instrumentDate

        Log::info('checkDuplicate API Called', [
            'fileno' => $fileno,
            'type' => $type,
            'date' => $date
        ]);

        if (!$fileno) {
            return response()->json(['exists' => false]);
        }

        // 1. Check for existing Instrument Record
        $query = DB::connection('sqlsrv')->table('instrument_capture')
            ->where(function ($q) use ($fileno) {
                $q->where('mlsFNo', $fileno)
                    ->orWhere('kangisFileNo', $fileno)
                    ->orWhere('NewKANGISFileno', $fileno)
                    ->orWhere('temp_fileno', $fileno);
            });

        if ($type) {
            $query->where('instrument_type', $type);
        }

        // Use date if provided to be distinct? Or just warn about ANY record for this file/type?
        // User implied "duplicate check" usually means "has this been registered already?"
        // We'll return the latest match.
        $instrument = $query->orderBy('created_at', 'desc')->first();

        // 2. Retrieve PRA (Property Record) History for Context
        // We always search for PRA history if a file number is provided, 
        // regardless of whether a duplicate instrument was found.
        $praHistoryQuery = DB::connection('sqlsrv')->table('pra');

        $praHistoryQuery->where(function ($q) use ($fileno, $instrument) {
            $q->where('mlsFNo', $fileno)
                ->orWhere('kangisFileNo', $fileno)
                ->orWhere('NewKANGISFileno', $fileno)
                ->orWhere('fileno', $fileno);

            // If we found an instrument with a prop_id, include that in the search
            if ($instrument && !empty($instrument->prop_id)) {
                $q->orWhere('prop_id', $instrument->prop_id);
            }
        });

        $praHistory = $praHistoryQuery->orderBy('created_at', 'desc')->get();

        // 3. Retrieve Deed Registrations (Instrument Registration) History
        $deedHistoryQuery = DB::connection('sqlsrv')->table('deed_registrations');
        $deedHistoryQuery->where('fileno', $fileno);

        $deedHistory = $deedHistoryQuery->orderBy('created_at', 'desc')->get()->map(function ($item) {
            $item->is_deed_reg = true; // Mark as deed registration record

            // Map common fields to match PRA history structure for unified JS display
            $item->transaction_type = $item->instrument_type ?? 'Instrument';

            // Map Parties
            $item->party_1 = $item->grantor ?? '';
            $item->party_2 = $item->grantee ?? '';

            // Map Particulars (Frontend expects serialNo, pageNo, volumeNo)
            $item->serialNo = $item->serial_no ?? '0';
            $item->pageNo = $item->page_no ?? '0';
            $item->volumeNo = $item->volume_no ?? '0';

            return $item;
        });

        // Merge histories for the modal
        $combinedHistory = $praHistory->concat($deedHistory)->sortByDesc('created_at')->values();

        // 4. Retrieve Latest Consent Application for Auto-fill
        $consentApp = DB::connection('sqlsrv')->table('consent_applications')
            ->where('file_number', $fileno)
            ->orderBy('created_at', 'desc')
            ->first();

        return response()->json([
            'exists' => $instrument ? true : false,
            'instrument' => $instrument,
            'pra_history' => $combinedHistory,
            'pra' => $praHistory->first(),
            'consent_app' => $consentApp
        ]);
    }

    /**
     * API: Get Capture Record
     */
    public function getCaptureRecord($id)
    {
        $record = DB::connection('sqlsrv')->table('instrument_capture')->find($id);

        if (!$record) {
            return response()->json(['success' => false, 'message' => 'Record not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $record]);
    }

    public function generateParticulars()
    {
        try {
            // Get the last record from the NEW table
            $lastRecord = DB::connection('sqlsrv')
                ->table('instrument_capture')
                ->whereNotNull('root_reg_number')
                ->orderBy('id', 'desc')
                ->first();

            if (!$lastRecord || !$lastRecord->root_reg_number) {
                $serial_no = 1;
                $page_no = 1;
                $volume_no = 1;
            } else {
                $regParts = explode('/', $lastRecord->root_reg_number);
                $serial_no = (int) ($regParts[0] ?? 0) + 1;
                $page_no = (int) ($regParts[1] ?? 0) + 1;
                $volume_no = (int) ($regParts[2] ?? 1);

                if ($serial_no > 300) {
                    $serial_no = 1;
                    $page_no = 1;
                    $volume_no++;
                }
            }

            $formatted = "{$serial_no}/{$page_no}/{$volume_no}";

            return response()->json([
                'success' => true,
                'rootRegistrationNumber' => $formatted,
                'serial_no' => $serial_no,
                'page_no' => $page_no,
                'volume_no' => $volume_no
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate particulars: ' . $e->getMessage()
            ], 500);
        }
    }

    public function Coroi()
    {
        $title = 'Confirmation Of Instrument Registration';
        return view('coroi.index', compact('title'));
    }

    public function show($id)
    {
        $PageTitle = 'View Instrument';
        $PageDescription = 'View details of an instrument record';
        $states = DB::connection('sqlsrv')->table('States')->orderBy('StateName')->get();
        $lgas = DB::connection('sqlsrv')->table('lgas')->where('is_active', 1)->orderBy('name')->get();
        $districts = DB::connection('sqlsrv')->table('districts')->where('is_active', 1)->orderBy('name')->get();
        $record = DB::connection('sqlsrv')->table('instrument_capture')->find($id);

        if (!$record) {
            return redirect()->route('instruments.index')->with('error', 'Record not found');
        }

        return view('instruments.show', compact('PageTitle', 'PageDescription', 'record', 'states', 'lgas', 'districts'));
    }

    public function edit($id)
    {
        $PageTitle = 'Edit Instrument';
        $PageDescription = 'Edit an existing instrument record';
        $states = DB::connection('sqlsrv')->table('States')->orderBy('StateName')->get();
        $lgas = DB::connection('sqlsrv')->table('lgas')->where('is_active', 1)->orderBy('name')->get();
        $districts = DB::connection('sqlsrv')->table('districts')->where('is_active', 1)->orderBy('name')->get();
        $record = DB::connection('sqlsrv')->table('instrument_capture')->find($id);

        if (!$record) {
            return redirect()->route('instruments.index')->with('error', 'Record not found');
        }

        return view('instruments.edit', compact('PageTitle', 'PageDescription', 'states', 'record', 'lgas', 'districts'));
    }

    public function update(Request $request, $id)
    {
        try {
            // Validate if necessary?
            // Service handles most logic, but basic presence checks might be good?
            // We'll pass everything to service.

            $result = $this->captureService->update($id, $request->all());

            if ($result['success']) {
                $message = 'Instrument updated successfully. Ref: ' . ($result['reg_number'] ?? '');

                if ($request->expectsJson()) {
                    return response()->json(['success' => true, 'message' => $message]);
                }

                return redirect()->route('instruments.index')->with('success', $message);
            }

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Failed to update instrument.'], 500);
            }

            return redirect()->back()->with('error', 'Failed to update instrument')->withInput();

        } catch (\Exception $e) {
            Log::error('Instrument Update Error: ' . $e->getMessage());

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()], 500);
            }

            return redirect()->back()->with('error', 'An error occurred: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            DB::connection('sqlsrv')->table('instrument_capture')
                ->where('id', $id)
                ->update(['is_deleted' => 1]);

            return redirect()->route('instruments.index')->with('success', 'Instrument deleted successfully');
        } catch (\Exception $e) {
            Log::error('Instrument Delete Error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to delete instrument: ' . $e->getMessage());
        }
    }

    public function getLgas($state)
    {
        $query = DB::connection('sqlsrv')->table('StatLGAs');

        if (is_numeric($state)) {
            $query->where('StateID', $state);
        } else {
            $stateRecord = DB::connection('sqlsrv')->table('States')
                ->where('StateName', $state)
                ->first();

            if ($stateRecord) {
                $query->where('StateID', $stateRecord->StateID);
            } else {
                return response()->json([]);
            }
        }

        $lgas = $query->orderBy('LGAName')->get();
        return response()->json($lgas);
    }

    public function getNextTempFileNo()
    {
        $prefix = 'TEMP-';

        try {
            // Use the sequence table to generate a unique ID atomically
            $sequenceId = DB::connection('sqlsrv')->table('temp_fileno_sequence')->insertGetId([
                'created_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            $tempFileNo = $prefix . str_pad((string) $sequenceId, 5, '0', STR_PAD_LEFT);

            return response()->json([
                'status' => 'success',
                'temp_fileno' => $tempFileNo,
            ]);

        } catch (\Throwable $e) {
            \Log::error('Failed to generate temp file number from sequence table in InstrumentController: ' . $e->getMessage());

            // Fallback to old method if sequence table fails
            return $this->getNextTempFileNoLegacy();
        }
    }

    /**
     * Fallback method for generating temp file numbers (Legacy Logic)
     * Used only if sequence table insertion fails
     */
    private function getNextTempFileNoLegacy()
    {
        try {
            $prefix = 'TEMP-';
            $maxNumber = 0;
            $tablesToCheck = ['pra', 'pic', 'instrument_capture'];

            foreach ($tablesToCheck as $table) {
                if (!Schema::connection('sqlsrv')->hasTable($table)) {
                    continue;
                }

                // Check both temp_fileno AND mlsFNo columns since some records miss temp_fileno
                $colsToCheck = ['temp_fileno', 'mlsFNo'];
                foreach ($colsToCheck as $col) {
                    if (!Schema::connection('sqlsrv')->hasColumn($table, $col)) {
                        continue;
                    }

                    $latestValue = DB::connection('sqlsrv')
                        ->table($table)
                        ->whereNotNull($col)
                        ->where($col, 'like', $prefix . '%')
                        ->orderByDesc(DB::raw("TRY_CONVERT(INT, REPLACE(CAST(" . $col . " AS VARCHAR), '{$prefix}', ''))"))
                        ->orderByDesc($col)
                        ->value($col);

                    if ($latestValue && preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/i', $latestValue, $matches)) {
                        $maxNumber = max($maxNumber, (int) $matches[1]);
                    }
                }
            }

            $nextNumber = $maxNumber + 1;
            $tempFileNo = $prefix . str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);

            return response()->json([
                'status' => 'success',
                'temp_fileno' => $tempFileNo,
            ]);
        } catch (\Throwable $e) {
            \Log::error('Failed to generate temporary file number in InstrumentController (Legacy): ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Unable to generate a temporary file number',
            ], 500);
        }
    }

    public function previewRegistrationNumber(Request $request)
    {
        $type = $request->query('instrument_type');
        
        if (!$type) {
             return response()->json(['success' => false, 'message' => 'Instrument type required'], 400);
        }

        try {
             $service = app(\App\Services\InstrumentRegistrationService::class);
             $preview = $service->previewNextNumber($type);

             return response()->json([
                 'success' => true,
                 'preview' => $preview,
                 'deeds_date' => $preview['deeds_date'] ?? null,
                 'deeds_time' => $preview['deeds_time'] ?? null
             ]);
        } catch (\Exception $e) {
             return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}