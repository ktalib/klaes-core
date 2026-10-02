<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
 
class CertificationController extends Controller
{
    /**
     * Show certification management page
     */
    public function index()
    {
        return view('recertification.certification');
    }

    /**
     * Get certification data for the certification management page
     */
    public function getCertificationData(Request $request)
    {
        try {
            $query = DB::connection('sqlsrv')->table('recertification_applications');
            $applications = $query->get();

            // Format data for the certification table
            $data = $applications->map(function($app) {
                // Determine applicant name based on type
                $applicantName = '';
                if ($app->applicant_type === 'Corporate') {
                    $applicantName = $app->organisation_name ?? 'N/A';
                } else {
                    $applicantName = trim(($app->surname ?? '') . ' ' . ($app->first_name ?? ''));
                    if (empty($applicantName)) {
                        $applicantName = 'N/A';
                    }
                }

                // Format plot details
                $plotDetails = '';
                if ($app->plot_number) {
                    $plotDetails .= 'Plot: ' . $app->plot_number;
                }
                if ($app->layout_district) {
                    $plotDetails .= ($plotDetails ? ', ' : '') . $app->layout_district;
                }
                if ($app->plot_size) {
                    $plotDetails .= ($plotDetails ? ', ' : '') . 'Size: ' . $app->plot_size;
                }
                if (empty($plotDetails)) {
                    $plotDetails = 'N/A';
                }

                // Check if certificate is generated
                $certificateGenerated = $app->certificate_generated ?? false;

                return [
                    'id' => $app->id,
                    'cofO_serialNo' => $app->cofo_number ?? 'N/A',
                    'NewKANGISFileno' => $app->NewKANGISFileno ?? 'N/A',
                    'kangisFileNo' => $app->kangisFileNo ?? 'N/A',
                    'mlsfNo' => $app->mlsfNo ?? 'N/A',
                    'reg_no' => $app->reg_no ?? 'N/A',
                    'file_number' => $app->file_number ?? 'N/A',
                    'applicant_name' => $applicantName,
                    'applicant_type' => $app->applicant_type ?? 'N/A',
                    'plot_details' => $plotDetails,
                    'land_use' => $app->current_land_use ?? $app->land_use ?? 'N/A',
                    'lga_name' => $app->lga_name ?? 'N/A',
                    'created_at' => $app->created_at ? date('d M Y', strtotime($app->created_at)) : 'N/A',
                    'certificate_generated' => $certificateGenerated,
                    'certificate_generated_date' => $app->certificate_generated_date ? date('d M Y', strtotime($app->certificate_generated_date)) : null,
                    'cofo_number' => $app->cofo_number ?? 'N/A',
                    'cofo_serial_no' => $app->cofo_serial_no ?? 'N/A',
                    'serial_no' => $app->serial_no ?? 'N/A',
                    'reg_page' => $app->reg_page ?? 'N/A',
                    'reg_volume' => $app->reg_volume ?? 'N/A',
                ];
            });

            // Calculate statistics
            $total = $data->count();
            $generated = $data->where('certificate_generated', true)->count();
            $pending = $total - $generated;
            $thisMonth = $data->where('created_at', '>=', now()->startOfMonth()->format('d M Y'))->count();

            $statistics = [
                'total' => $total,
                'generated' => $generated,
                'pending' => $pending,
                'thisMonth' => $thisMonth
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
                'statistics' => $statistics
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching certification data', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'data' => [],
                'statistics' => ['total' => 0, 'generated' => 0, 'pending' => 0, 'thisMonth' => 0],
                'error' => 'Failed to fetch certification data'
            ]);
        }
    }

    /**
     * Show Bills & Payments page
     */
    public function billsPayments()
    {
        return view('recertification.bills_payments');
    }

    /**
     * Get Bills & Payments data
     */
    public function getBillsPaymentsData(Request $request)
    {
        try {
            $query = DB::connection('sqlsrv')->table('recertification_applications');
            $applications = $query->get();

            // Format data for the Bills & Payments table
            $data = $applications->map(function($app) {
                // Parse payload to get payment information
                $payload = json_decode($app->payload ?? '{}', true);
                
                // Determine applicant name based on type
                $applicantName = '';
                if ($app->applicant_type === 'Corporate') {
                    $applicantName = $app->organisation_name ?? 'N/A';
                } else {
                    $applicantName = trim(($app->surname ?? '') . ' ' . ($app->first_name ?? ''));
                    if (empty($applicantName)) {
                        $applicantName = 'N/A';
                    }
                }

                // Get payment information from payload or direct columns
                $paymentMethod = $payload['payment_method'] ?? $app->payment_method ?? 'N/A';
                $receiptNo = $payload['receipt_no'] ?? $app->receipt_no ?? 'N/A';
                $bankName = $payload['bank_name'] ?? $app->bank_name ?? 'N/A';
                $paymentAmount = $payload['payment_amount'] ?? $app->payment_amount ?? 0;
                $paymentDate = $payload['payment_date'] ?? $app->payment_date ?? $app->created_at;

                return [
                    'id' => $app->id,
                    'applicant_name' => $applicantName,
                    'payment_method' => $paymentMethod,
                    'receipt_no' => $receiptNo,
                    'bank_name' => $bankName,
                    'payment_amount' => floatval($paymentAmount),
                    'payment_date' => $paymentDate,
                    'file_number' => $app->file_number ?? 'N/A',
                    'applicant_type' => $app->applicant_type ?? 'N/A',
                    'created_at' => $app->created_at ? date('d M Y', strtotime($app->created_at)) : 'N/A'
                ];
            });

            // Filter out records without payment information
            $data = $data->filter(function($payment) {
                return $payment['payment_amount'] > 0 || 
                       $payment['receipt_no'] !== 'N/A' || 
                       $payment['payment_method'] !== 'N/A';
            });

            // Calculate statistics
            $total = $data->count();
            $totalAmount = $data->sum('payment_amount');
            $avgAmount = $total > 0 ? $totalAmount / $total : 0;
            
            // Count payments this month
            $thisMonth = $data->filter(function($payment) {
                if ($payment['payment_date'] === 'N/A') return false;
                try {
                    $paymentDate = \DateTime::createFromFormat('Y-m-d H:i:s', $payment['payment_date']);
                    if (!$paymentDate) {
                        $paymentDate = \DateTime::createFromFormat('Y-m-d', $payment['payment_date']);
                    }
                    if (!$paymentDate) return false;
                    
                    $startOfMonth = new \DateTime('first day of this month');
                    return $paymentDate >= $startOfMonth;
                } catch (\Exception $e) {
                    return false;
                }
            })->count();

            $statistics = [
                'total' => $total,
                'totalAmount' => $totalAmount,
                'avgAmount' => $avgAmount,
                'thisMonth' => $thisMonth
            ];

            return response()->json([
                'success' => true,
                'data' => $data->values(),
                'statistics' => $statistics
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching Bills & Payments data', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'data' => [],
                'statistics' => ['total' => 0, 'totalAmount' => 0, 'avgAmount' => 0, 'thisMonth' => 0],
                'error' => 'Failed to fetch Bills & Payments data: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Export payments data
     */
    public function exportPayments(Request $request)
    {
        try {
            // Get the payments data
            $response = $this->getBillsPaymentsData($request);
            $responseData = json_decode($response->getContent(), true);
            
            if (!$responseData['success']) {
                return redirect()->back()->with('error', 'Failed to export payments data');
            }
            
            $payments = $responseData['data'];
            
            // Create CSV content
            $csvContent = "SN,Applicant Name,Payment Method,Receipt No,Bank Name,Payment Amount,Payment Date\n";
            
            foreach ($payments as $index => $payment) {
                $csvContent .= sprintf(
                    "%d,%s,%s,%s,%s,%.2f,%s\n",
                    $index + 1,
                    '"' . str_replace('"', '""', $payment['applicant_name']) . '"',
                    '"' . str_replace('"', '""', $payment['payment_method']) . '"',
                    '"' . str_replace('"', '""', $payment['receipt_no']) . '"',
                    '"' . str_replace('"', '""', $payment['bank_name']) . '"',
                    $payment['payment_amount'],
                    $payment['payment_date']
                );
            }
            
            // Return CSV download
            $filename = 'recertification_payments_' . date('Y-m-d_H-i-s') . '.csv';
            
            return response($csvContent)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
                
        } catch (\Exception $e) {
            Log::error('Error exporting payments', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->back()->with('error', 'Failed to export payments data');
        }
    }

    /**
     * Show EDMS page
     */
    public function edms()
    {
        return view('recertification.edms');
    }

    /**
     * Get EDMS data for the EDMS management page
     */
    public function getEDMSData(Request $request)
    {
        try {
            $query = DB::connection('sqlsrv')->table('recertification_applications');
            $applications = $query->get();

            // Format data for the EDMS table
            $data = $applications->map(function($app) {
                // Determine applicant name based on type
                $applicantName = '';
                if ($app->applicant_type === 'Corporate') {
                    $applicantName = $app->organisation_name ?? 'N/A';
                } else {
                    $applicantName = trim(($app->surname ?? '') . ' ' . ($app->first_name ?? ''));
                    if (empty($applicantName)) {
                        $applicantName = 'N/A';
                    }
                }

                // Format plot details
                $plotDetails = '';
                if ($app->plot_number) {
                    $plotDetails .= 'Plot: ' . $app->plot_number;
                }
                if ($app->layout_district) {
                    $plotDetails .= ($plotDetails ? ', ' : '') . $app->layout_district;
                }
                if ($app->plot_size) {
                    $plotDetails .= ($plotDetails ? ', ' : '') . 'Size: ' . $app->plot_size;
                }
                if (empty($plotDetails)) {
                    $plotDetails = 'N/A';
                }

                // Check EDMS status by looking for file indexing records
                $edmsStatus = 'pending';
                $documentCount = 0;
                $lastUpdated = $app->created_at ? date('d M Y', strtotime($app->created_at)) : 'N/A';

                try {
                    // Check if there's a file indexing record for this application
                    $fileIndexing = DB::connection('sqlsrv')->table('file_indexings')
                        ->where('recertification_application_id', $app->id)
                        ->first();

                    if ($fileIndexing) {
                        // Check for scanned documents
                        $scannings = DB::connection('sqlsrv')->table('scannings')
                            ->where('file_indexing_id', $fileIndexing->id)
                            ->get();

                        $documentCount = $scannings->count();

                        if ($documentCount > 0) {
                            // Check if page typing is complete
                            $pageTypings = DB::connection('sqlsrv')->table('pagetypings')
                                ->where('file_indexing_id', $fileIndexing->id)
                                ->get();

                            if ($pageTypings->count() > 0) {
                                $edmsStatus = 'digitized';
                            } else {
                                $edmsStatus = 'scanning';
                            }
                        } else {
                            $edmsStatus = 'indexing';
                        }

                        $lastUpdated = $fileIndexing->updated_at ? date('d M Y', strtotime($fileIndexing->updated_at)) : $lastUpdated;
                    }
                } catch (\Exception $e) {
                    Log::warning('Error checking EDMS status for application ' . $app->id, [
                        'error' => $e->getMessage()
                    ]);
                }

                return [
                    'id' => $app->id,
                    'cofO_serialNo' => $app->cofo_number ?? 'N/A',
                    'NewKANGISFileno' => $app->NewKANGISFileno ?? 'N/A',
                    'kangisFileNo' => $app->kangisFileNo ?? 'N/A',
                    'mlsfNo' => $app->mlsfNo ?? 'N/A',
                    'reg_no' => $app->reg_no ?? 'N/A',
                    'file_number' => $app->file_number ?? 'N/A',
                    'applicant_name' => $applicantName,
                    'applicant_type' => $app->applicant_type ?? 'N/A',
                    'plot_details' => $plotDetails,
                    'current_land_use' => $app->current_land_use ?? $app->land_use ?? 'N/A',
                    'land_use' => $app->current_land_use ?? $app->land_use ?? 'N/A',
                    'lga_name' => $app->lga_name ?? 'N/A',
                    'created_at' => $app->created_at ? date('d M Y', strtotime($app->created_at)) : 'N/A',
                    'cofo_number' => $app->cofo_number ?? 'N/A',
                    'edms_status' => $edmsStatus,
                    'document_count' => $documentCount,
                    'last_updated' => $lastUpdated,
                ];
            });

            // Calculate statistics
            $total = $data->count();
            $digitized = $data->where('edms_status', 'digitized')->count();
            $pending = $data->where('edms_status', 'pending')->count();
            $thisMonth = $data->where('created_at', '>=', now()->startOfMonth()->format('d M Y'))->count();

            $statistics = [
                'total' => $total,
                'digitized' => $digitized,
                'pending' => $pending,
                'thisMonth' => $thisMonth
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
                'statistics' => $statistics
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching EDMS data', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'data' => [],
                'statistics' => ['total' => 0, 'digitized' => 0, 'pending' => 0, 'thisMonth' => 0],
                'error' => 'Failed to fetch EDMS data'
            ]);
        }
    }

    /**
     * View Confirmation of Registration of Instrument (CoR)
     */
    public function viewCoR($id)
    {
        try {
            // Get the application data
            $application = DB::connection('sqlsrv')->table('recertification_applications')
                ->where('id', $id)
                ->first();

            if (!$application) {
                return redirect()->route('recertification.certification')
                    ->with('error', 'Application not found');
            }

            // Return the CORI view with the application data
            return view('recertification.cori', compact('application'));

        } catch (\Exception $e) {
            Log::error('Error viewing CoR for application ' . $id, [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('recertification.certification')
                ->with('error', 'Failed to load Confirmation of Registration');
        }
    }

    /**
     * Generate Certificate of Occupancy Front Page
     */
    public function generateCofoFrontPage($id)
    {
        try {
            // Get the application data
            $application = DB::connection('sqlsrv')->table('recertification_applications')
                ->where('id', $id)
                ->first();

            if (!$application) {
                return response()->json([
                    'success' => false,
                    'message' => 'Application not found'
                ], 404);
            }

            // Check if certificate is already generated
            if ($application->certificate_generated) {
                return response()->json([
                    'success' => false,
                    'message' => 'Certificate has already been generated for this application'
                ]);
            }

            // Generate certificate with current timestamp
            $certificateGeneratedDate = now()->format('Y-m-d H:i:s.v');
            
            // Update the application record
            DB::connection('sqlsrv')->table('recertification_applications')
                ->where('id', $id)
                ->update([
                    'certificate_generated' => true,
                    'certificate_generated_date' => $certificateGeneratedDate,
                    // Set default values if not already set
                    'serial_no' => $application->serial_no ?: $application->cofo_number ?: '1',
                    'reg_page' => $application->reg_page ?: '1',
                    'reg_volume' => $application->reg_volume ?: '1',
                    'updated_at' => now()
                ]);

            Log::info('Certificate generated for recertification application', [
                'application_id' => $id,
                'certificate_generated_date' => $certificateGeneratedDate
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Certificate of Occupancy Front Page generated successfully',
                'certificate_generated_date' => $certificateGeneratedDate
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating CofO Front Page for application ' . $id, [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate Certificate of Occupancy Front Page: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update certificate registration details
     */
    public function updateCertificateDetails(Request $request, $id)
    {
        try {
            $validatedData = $request->validate([
                'serial_no' => 'required|string|max:50',
                'reg_page' => 'required|string|max:50',
                'reg_volume' => 'required|string|max:50',
                'certificate_generated_date' => 'nullable|date'
            ]);

            // Get the application data
            $application = DB::connection('sqlsrv')->table('recertification_applications')
                ->where('id', $id)
                ->first();

            if (!$application) {
                return response()->json([
                    'success' => false,
                    'message' => 'Application not found'
                ], 404);
            }

            // Update the certificate details
            DB::connection('sqlsrv')->table('recertification_applications')
                ->where('id', $id)
                ->update([
                    'serial_no' => $validatedData['serial_no'],
                    'reg_page' => $validatedData['reg_page'],
                    'reg_volume' => $validatedData['reg_volume'],
                    'certificate_generated_date' => $validatedData['certificate_generated_date'] ?: $application->certificate_generated_date,
                    'updated_at' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Certificate details updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating certificate details for application ' . $id, [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update certificate details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show DG's List page
     */
    public function dgList()
    {
        return view('recertification.dg_list');
    }

    /**
     * Get DG data for the DG's List page
     */
    public function getDGData(Request $request)
    {
        try {
            $query = DB::connection('sqlsrv')->table('recertification_applications');
            
            // Filter applications that are ready for DG approval
            // These should have all prerequisites completed
            $applications = $query->get();

            // Format data for the DG's List table and filter by prerequisites
            $data = $applications->map(function($app) {
                // Determine applicant name based on type
                $applicantName = '';
                if ($app->applicant_type === 'Corporate') {
                    $applicantName = $app->organisation_name ?? 'N/A';
                } else {
                    $applicantName = trim(($app->surname ?? '') . ' ' . ($app->first_name ?? ''));
                    if (empty($applicantName)) {
                        $applicantName = 'N/A';
                    }
                }

                // Format plot details
                $plotDetails = '';
                if ($app->plot_number) {
                    $plotDetails .= 'Plot: ' . $app->plot_number;
                }
                if ($app->layout_district) {
                    $plotDetails .= ($plotDetails ? ', ' : '') . $app->layout_district;
                }
                if ($app->plot_size) {
                    $plotDetails .= ($plotDetails ? ', ' : '') . 'Size: ' . $app->plot_size;
                }
                if (empty($plotDetails)) {
                    $plotDetails = 'N/A';
                }

                // Check prerequisites based on available fields
                $acknowledgementGenerated = !empty($app->acknowledgement) && $app->acknowledgement === 'Generated';
                $verificationGenerated = $app->verification === 'Verified';
                $gisCaptured = false; // GIS capture status not tracked in current schema
                $vettingGenerated = $app->verification === 'Verified'; // Use verification as proxy for vetting
                $edmsCaptured = false; // EDMS capture status not tracked in current schema
                $cofoFrontGenerated = $app->certificate_generated ?? false;

                return [
                    'id' => $app->id,
                    'cofO_serialNo' => $app->cofo_number ?? 'N/A',
                    'cofo_serial_no' => $app->cofo_number ?? 'N/A',
                    'NewKANGISFileno' => $app->NewKANGISFileno ?? 'N/A',
                    'kangisFileNo' => $app->kangisFileNo ?? 'N/A',
                    'mlsfileNo' => $app->mlsfNo ?? 'N/A',
                    'mlsfNo' => $app->mlsfNo ?? 'N/A',
                    'plotNo' => $app->plot_number ?? 'N/A',
                    'plot_number' => $app->plot_number ?? 'N/A',
                    'land_use' => $app->land_use ?? 'N/A',
                    'currentAllottee' => $applicantName,
                    'applicant_name' => $applicantName,
                    'layoutName' => $app->layout_district ?? 'N/A',
                    'layout_name' => $app->layout_district ?? 'N/A',
                    'lga_name' => $app->lga_name ?? 'N/A',
                    'file_number' => $app->file_number ?? 'N/A',
                    'applicant_type' => $app->applicant_type ?? 'N/A',
                    'plot_details' => $plotDetails,
                    'created_at' => $app->created_at ? date('d M Y', strtotime($app->created_at)) : 'N/A',
                    'dg_approval' => $app->dg_approval ?? false,
                    'acknowledgement_generated' => $acknowledgementGenerated,
                    'verification_generated' => $verificationGenerated,
                    'gis_captured' => $gisCaptured,
                    'vetting_generated' => $vettingGenerated,
                    'edms_captured' => $edmsCaptured,
                    'cofo_front_generated' => $cofoFrontGenerated,
                    'prerequisites_completed' => $acknowledgementGenerated && 
                                               $verificationGenerated && 
                                               $gisCaptured && 
                                               $vettingGenerated && 
                                               $edmsCaptured && 
                                               $cofoFrontGenerated,
                ];
            })->filter(function($app) {
                // Only show records with all prerequisites completed
                return $app['prerequisites_completed'];
            })->values(); // Reset array keys after filtering

            // Calculate statistics
            $total = $data->count();
            $approved = $data->where('dg_approval', true)->count();
            $pending = $total - $approved;
            $thisMonth = $data->where('created_at', '>=', now()->startOfMonth()->format('d M Y'))->count();

            $statistics = [
                'total' => $total,
                'approved' => $approved,
                'pending' => $pending,
                'thisMonth' => $thisMonth
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
                'statistics' => $statistics
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching DG data', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'data' => [],
                'statistics' => ['total' => 0, 'approved' => 0, 'pending' => 0, 'thisMonth' => 0],
                'error' => 'Failed to fetch DG data'
            ]);
        }
    }

    /**
     * Process batch applications for DG approval
     */
    public function batchProcess(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'application_ids' => 'required|array',
                'application_ids.*' => 'integer|exists:recertification_applications,id',
                'batch_number' => 'required|integer'
            ]);

            $applicationIds = $validatedData['application_ids'];
            $batchNumber = $validatedData['batch_number'];

            // Update applications with DG approval
            $updated = DB::connection('sqlsrv')->table('recertification_applications')
                ->whereIn('id', $applicationIds)
                ->update([
                    'dg_approval' => true,
                    'dg_approval_date' => now(),
                    'dg_batch_number' => $batchNumber,
                    'updated_at' => now()
                ]);

            Log::info('DG batch processing completed', [
                'batch_number' => $batchNumber,
                'processed_count' => $updated,
                'application_ids' => $applicationIds
            ]);

            return response()->json([
                'success' => true,
                'message' => "Successfully processed batch {$batchNumber}",
                'processed_count' => $updated,
                'batch_number' => $batchNumber
            ]);

        } catch (\Exception $e) {
            Log::error('Error processing DG batch', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process batch: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show Governor's List page
     */
    public function governorsList()
    {
        return view('recertification.governors_list');
    }

    /**
     * Get Governors data for the Governor's List page
     */
    public function getGovernorsData(Request $request)
    {
        try {
            $query = DB::connection('sqlsrv')->table('recertification_applications');
            
            // Filter applications that are ready for Governor approval
            // These should have DG approval and all other prerequisites
            $applications = $query->where('dg_approval', true)->get();

            // Format data for the Governor's List table and filter by prerequisites
            $data = $applications->map(function($app) {
                // Determine applicant name based on type
                $applicantName = '';
                if ($app->applicant_type === 'Corporate') {
                    $applicantName = $app->organisation_name ?? 'N/A';
                } else {
                    $applicantName = trim(($app->surname ?? '') . ' ' . ($app->first_name ?? ''));
                    if (empty($applicantName)) {
                        $applicantName = 'N/A';
                    }
                }

                // Check prerequisites (including DG approval) based on available fields
                $acknowledgementGenerated = !empty($app->acknowledgement) && $app->acknowledgement === 'Generated';
                $verificationGenerated = $app->verification === 'Verified';
                $gisCaptured = false; // GIS capture status not tracked in current schema
                $vettingGenerated = $app->verification === 'Verified'; // Use verification as proxy for vetting
                $edmsCaptured = false; // EDMS capture status not tracked in current schema
                $cofoFrontGenerated = $app->certificate_generated ?? false;
                $dgApproval = $app->dg_approval ?? false;

                return [
                    'id' => $app->id,
                    'cofO_serialNo' => $app->cofo_number ?? 'N/A',
                    'cofo_serial_no' => $app->cofo_number ?? 'N/A',
                    'cofo_number' => $app->cofo_number ?? 'N/A',
                    'applicant_name' => $applicantName,
                    'currentAllottee' => $applicantName,
                    'layoutName' => $app->layout_district ?? 'N/A',
                    'layout_name' => $app->layout_district ?? 'N/A',
                    'layout_district' => $app->layout_district ?? 'N/A',
                    'district_name' => $app->layout_district ?? 'N/A',
                    'lga_name' => $app->lga_name ?? 'N/A',
                    'file_number' => $app->file_number ?? 'N/A',
                    'applicant_type' => $app->applicant_type ?? 'N/A',
                    'created_at' => $app->created_at ? date('d M Y', strtotime($app->created_at)) : 'N/A',
                    'governor_approval' => $app->governor_approval ?? false,
                    'dg_approval' => $dgApproval,
                    'acknowledgement_generated' => $acknowledgementGenerated,
                    'verification_generated' => $verificationGenerated,
                    'gis_captured' => $gisCaptured,
                    'vetting_generated' => $vettingGenerated,
                    'edms_captured' => $edmsCaptured,
                    'cofo_front_generated' => $cofoFrontGenerated,
                    'prerequisites_completed' => $acknowledgementGenerated && 
                                               $verificationGenerated && 
                                               $gisCaptured && 
                                               $vettingGenerated && 
                                               $edmsCaptured && 
                                               $cofoFrontGenerated &&
                                               $dgApproval, // DG approval is also a prerequisite for Governor's list
                ];
            })->filter(function($app) {
                // Only show records with all prerequisites completed (including DG approval)
                return $app['prerequisites_completed'];
            })->values(); // Reset array keys after filtering

            // Calculate statistics
            $total = $data->count();
            $approved = $data->where('governor_approval', true)->count();
            $pending = $total - $approved;
            $thisMonth = $data->where('created_at', '>=', now()->startOfMonth()->format('d M Y'))->count();

            $statistics = [
                'total' => $total,
                'approved' => $approved,
                'pending' => $pending,
                'thisMonth' => $thisMonth
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
                'statistics' => $statistics
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching Governors data', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'data' => [],
                'statistics' => ['total' => 0, 'approved' => 0, 'pending' => 0, 'thisMonth' => 0],
                'error' => 'Failed to fetch Governors data'
            ]);
        }
    }

    /**
     * Process batch applications for Governor approval
     */
    public function batchProcessGovernor(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'application_ids' => 'required|array',
                'application_ids.*' => 'integer|exists:recertification_applications,id',
                'batch_number' => 'required|integer'
            ]);

            $applicationIds = $validatedData['application_ids'];
            $batchNumber = $validatedData['batch_number'];

            // Update applications with Governor approval
            $updated = DB::connection('sqlsrv')->table('recertification_applications')
                ->whereIn('id', $applicationIds)
                ->where('dg_approval', true) // Ensure DG approval exists
                ->update([
                    'governor_approval' => true,
                    'governor_approval_date' => now(),
                    'governor_batch_number' => $batchNumber,
                    'updated_at' => now()
                ]);

            Log::info('Governor batch processing completed', [
                'batch_number' => $batchNumber,
                'processed_count' => $updated,
                'application_ids' => $applicationIds
            ]);

            return response()->json([
                'success' => true,
                'message' => "Successfully processed batch {$batchNumber}",
                'processed_count' => $updated,
                'batch_number' => $batchNumber
            ]);

        } catch (\Exception $e) {
            Log::error('Error processing Governor batch', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process batch: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show Vetting Sheet page
     */
    public function vettingSheet()
    {
        return view('recertification.vetting_sheet');
    }

    /**
     * Get Vetting data for the Vetting Sheet page
     */
    public function getVettingData(Request $request)
    {
        try {
            $query = DB::connection('sqlsrv')->table('recertification_applications');
            $applications = $query->get();

            // Format data for the Vetting Sheet table
            $data = $applications->map(function($app) {
                // Determine applicant name based on type
                $applicantName = '';
                if ($app->applicant_type === 'Corporate') {
                    $applicantName = $app->organisation_name ?? 'N/A';
                } else {
                    $applicantName = trim(($app->surname ?? '') . ' ' . ($app->first_name ?? ''));
                    if (empty($applicantName)) {
                        $applicantName = 'N/A';
                    }
                }

                // Format plot details
                $plotDetails = '';
                if ($app->plot_number) {
                    $plotDetails .= 'Plot: ' . $app->plot_number;
                }
                if ($app->layout_district) {
                    $plotDetails .= ($plotDetails ? ', ' : '') . $app->layout_district;
                }
                if ($app->plot_size) {
                    $plotDetails .= ($plotDetails ? ', ' : '') . 'Size: ' . $app->plot_size;
                }
                if (empty($plotDetails)) {
                    $plotDetails = 'N/A';
                }

                // Check vetting status based on available fields
                $vettingStatus = 'pending';
                if ($app->verification === 'Verified') {
                $vettingStatus = 'ready';
                }
                // Note: vetting_generated field doesn't exist in the database schema
                // We'll use verification status as the basis for vetting status

                return [
                    'id' => $app->id,
                    'cofO_serialNo' => $app->cofo_number ?? 'N/A',
                    'NewKANGISFileno' => $app->NewKANGISFileno ?? 'N/A',
                    'kangisFileNo' => $app->kangisFileNo ?? 'N/A',
                    'mlsfNo' => $app->mlsfNo ?? 'N/A',
                    'reg_no' => $app->reg_no ?? 'N/A',
                    'file_number' => $app->file_number ?? 'N/A',
                    'applicant_name' => $applicantName,
                    'applicant_type' => $app->applicant_type ?? 'N/A',
                    'plot_details' => $plotDetails,
                    'land_use' => $app->current_land_use ?? $app->land_use ?? 'N/A',
                    'current_land_use' => $app->current_land_use ?? 'N/A',
                    'lga_name' => $app->lga_name ?? 'N/A',
                    'created_at' => $app->created_at ? date('d M Y', strtotime($app->created_at)) : 'N/A',
                    'vetting_status' => $vettingStatus,
                    'verification' => $app->verification ?? 'N/A',
                    'verification_date' => $app->verification_date ? date('d M Y', strtotime($app->verification_date)) : 'N/A',
                ];
            });

            // Calculate statistics
            $total = $data->count();
            $generated = $data->where('vetting_status', 'generated')->count();
            $ready = $data->where('vetting_status', 'ready')->count();
            $pending = $data->where('vetting_status', 'pending')->count();

            $statistics = [
                'total' => $total,
                'generated' => $generated,
                'ready' => $ready,
                'pending' => $pending
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
                'statistics' => $statistics
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching Vetting data', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'data' => [],
                'statistics' => ['total' => 0, 'generated' => 0, 'ready' => 0, 'pending' => 0],
                'error' => 'Failed to fetch Vetting data'
            ]);
        }
    }

    /**
     * Browse vetting sheet directory
     */
    public function browseVettingSheetDirectory(Request $request)
    {
        try {
            // This would typically browse a file system directory
            // For now, return a placeholder response
            return response()->json([
                'success' => true,
                'directories' => [],
                'files' => []
            ]);
        } catch (\Exception $e) {
            Log::error('Error browsing vetting sheet directory', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to browse directory'
            ]);
        }
    }

    /**
     * Show GIS Data Capture page
     */
    public function gisDataCapture()
    {
        return view('recertification.gis_data_capture');
    }

    /**
     * Get GIS data for the GIS Data Capture page
     */
    public function getGISData(Request $request)
    {
        try {
            $query = DB::connection('sqlsrv')->table('recertification_applications');
            $applications = $query->get();

            // Format data for the GIS Data Capture table
            $data = $applications->map(function($app) {
                // Determine applicant name based on type
                $applicantName = '';
                if ($app->applicant_type === 'Corporate') {
                    $applicantName = $app->organisation_name ?? 'N/A';
                } else {
                    $applicantName = trim(($app->surname ?? '') . ' ' . ($app->first_name ?? ''));
                    if (empty($applicantName)) {
                        $applicantName = 'N/A';
                    }
                }

                // Format plot details
                $plotDetails = '';
                if ($app->plot_number) {
                    $plotDetails .= 'Plot: ' . $app->plot_number;
                }
                if ($app->layout_district) {
                    $plotDetails .= ($plotDetails ? ', ' : '') . $app->layout_district;
                }
                if ($app->plot_size) {
                    $plotDetails .= ($plotDetails ? ', ' : '') . 'Size: ' . $app->plot_size;
                }
                if (empty($plotDetails)) {
                    $plotDetails = 'N/A';
                }

                // Check GIS status - since gis_captured column doesn't exist, default to pending
                $gisStatus = 'pending';

                return [
                    'id' => $app->id,
                    'cofO_serialNo' => $app->cofo_number ?? 'N/A',
                    'NewKANGISFileno' => $app->NewKANGISFileno ?? 'N/A',
                    'kangisFileNo' => $app->kangisFileNo ?? 'N/A',
                    'mlsfNo' => $app->mlsfNo ?? 'N/A',
                    'reg_no' => $app->reg_no ?? 'N/A',
                    'file_number' => $app->file_number ?? 'N/A',
                    'applicant_name' => $applicantName,
                    'applicant_type' => $app->applicant_type ?? 'N/A',
                    'current_land_use' => $app->current_land_use ?? 'N/A',
                    'plot_details' => $plotDetails,
                    'lga_name' => $app->lga_name ?? 'N/A',
                    'created_at' => $app->created_at ? date('d M Y', strtotime($app->created_at)) : 'N/A',
                    'application_date' => $app->created_at ? date('d M Y', strtotime($app->created_at)) : 'N/A',
                    'gis_status' => $gisStatus,
                    'gis_captured' => false,
                ];
            });

            // Calculate statistics
            $total = $data->count();
            $captured = $data->where('gis_captured', true)->count();
            $pending = $total - $captured;
            $thisMonth = $data->where('created_at', '>=', now()->startOfMonth()->format('d M Y'))->count();

            $statistics = [
                'total' => $total,
                'captured' => $captured,
                'pending' => $pending,
                'thisMonth' => $thisMonth
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
                'statistics' => $statistics
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching GIS data', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'data' => [],
                'statistics' => ['total' => 0, 'captured' => 0, 'pending' => 0, 'thisMonth' => 0],
                'error' => 'Failed to fetch GIS data'
            ]);
        }
    }

    /**
     * View Certificate of Occupancy Front Page
     */
    public function viewCofoFrontPage($id)
    {
        try {
            // Get the application data
            $application = DB::connection('sqlsrv')->table('recertification_applications')
                ->where('id', $id)
                ->first();

            if (!$application) {
                return redirect()->route('recertification.certification')
                    ->with('error', 'Application not found');
            }

            // Return the CofO Front Page view with the application data
            return view('recertification.cofo-front-page', compact('application'));

        } catch (\Exception $e) {
            Log::error('Error viewing CofO Front Page for application ' . $id, [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('recertification.certification')
                ->with('error', 'Failed to load Certificate of Occupancy Front Page');
        }
    }

    /**
     * View Title Document Plan (TDP)
     */
    public function viewTDP($id)
    {
        try {
            // Get the application data
            $application = DB::connection('sqlsrv')->table('recertification_applications')
                ->where('id', $id)
                ->first();

            if (!$application) {
                return redirect()->route('recertification.certification')
                    ->with('error', 'Application not found');
            }

            // Return the TDP view with the application data
            return view('recertification.tdp', compact('application'));

        } catch (\Exception $e) {
            Log::error('Error viewing TDP for application ' . $id, [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('recertification.certification')
                ->with('error', 'Failed to load Title Document Plan');
        }
    }

    /**
     * View Certificate of Occupancy
     */
    public function viewCofo($id)
    {
        try {
            // Get the application data
            $application = DB::connection('sqlsrv')->table('recertification_applications')
                ->where('id', $id)
                ->first();

            if (!$application) {
                return redirect()->route('recertification.certification')
                    ->with('error', 'Application not found');
            }

            // Return the CofO view with the application data
            return view('recertification.cofo', compact('application'));

        } catch (\Exception $e) {
            Log::error('Error viewing CofO for application ' . $id, [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('recertification.certification')
                ->with('error', 'Failed to load Certificate of Occupancy');
        }
    }
}