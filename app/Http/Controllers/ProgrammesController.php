<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\LandUseType;
use App\Models\District;
use App\Models\Lga;
use App\Models\StreetName;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Concerns\ExecutesMasterDelete;
use App\Services\RofoRecommendationPurgeService;

class ProgrammesController extends Controller
{
    use ExecutesMasterDelete;

    /**
     * Derive a displayable owner name from an application row.
     * Rules:
     * - If applicant_type is corporate-like, use corporate_name.
     * - Else if multiple_owners_names present (JSON array or comma/semicolon separated), join them.
     * - Else use title + first_name + surname.
     */
    private function computeOwnerName(object $row): ?string
    {
        $type = strtolower(trim($row->applicant_type ?? ''));

        // Corporate precedence if type indicates corporate body
        if (in_array($type, ['corporate', 'corporate body', 'company', 'cooperative', 'organization', 'organisation'])) {
            $corp = trim((string) ($row->corporate_name ?? ''));
            if ($corp !== '') {
                return $corp;
            }
        }

        // Multiple owners: support JSON array or delimited string
        $multiRaw = $row->multiple_owners_names ?? null;
        if (!empty($multiRaw)) {
            $names = [];
            if (is_string($multiRaw)) {
                $decoded = json_decode($multiRaw, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $names = $decoded;
                } else {
                    // Fallback: split by comma/semicolon
                    $names = preg_split('/[;,]+/', $multiRaw);
                }
            } elseif (is_array($multiRaw)) {
                $names = $multiRaw;
            }
            // Normalize and join
            $names = array_values(array_filter(array_map(function ($n) {
                return trim((string) $n);
            }, $names), function ($n) {
                return $n !== '';
            }));
            if (!empty($names)) {
                return implode(', ', $names);
            }
        }

        // Corporate fallback even if applicant_type not set
        $corp = trim((string) ($row->corporate_name ?? ''));
        if ($corp !== '') {
            return $corp;
        }

        // Individual fallback
        $title = trim((string) ($row->applicant_title ?? ''));
        $first = trim((string) ($row->first_name ?? ''));
        $last = trim((string) ($row->surname ?? ''));
        $full = trim(implode(' ', array_filter([$title, $first, $last], function ($p) {
            return $p !== '';
        })));
        return $full !== '' ? $full : null;
    }

    /**
     * Extract address components from a row.
     * $type can be:
     * - 'property'  => use property_* columns
     * - 'applicant' => use address_* (owner/contact) columns
     */
    private function extractAddressComponents(object $row, ?object $fallback = null, string $type = 'property'): array
    {
        $useApplicant = strtolower($type) === 'applicant';

        $plot = $useApplicant
            ? ($row->address_house_no
                ?? $row->address_plot_no
                ?? $fallback?->address_house_no
                ?? $fallback?->address_plot_no
                ?? '')
            : ($row->property_house_no
                ?? $row->property_plot_no
                ?? $fallback?->property_house_no
                ?? $fallback?->property_plot_no
                ?? '');

        $street = $useApplicant
            ? ($row->address_street_name
                ?? $row->owner_street_name
                ?? $fallback?->address_street_name
                ?? $fallback?->owner_street_name
                ?? '')
            : ($row->property_street_name
                ?? $row->property_street
                ?? $fallback?->property_street_name
                ?? $fallback?->property_street
                ?? '');

        $district = $useApplicant
            ? ($row->address_district ?? ($fallback?->address_district ?? ''))
            : ($row->property_district ?? ($fallback?->property_district ?? ''));

        $lga = $useApplicant
            ? ($row->address_lga ?? ($fallback?->address_lga ?? ''))
            : ($row->property_lga ?? ($fallback?->property_lga ?? ''));

        $state = $useApplicant
            ? ($row->address_state ?? ($fallback?->address_state ?? ''))
            : ($row->property_state ?? ($fallback?->property_state ?? ''));

        return [
            'plot_number' => $plot,
            'street' => $street,
            'street_other' => '',
            'district' => $district,
            'district_other' => '',
            'lga' => $lga,
            'state' => $state ?: 'Kano',
        ];
    }

    private function compileAddressString(array $components): string
    {
        $parts = array_filter([
            $components['plot_number'] ?? '',
            $components['street'] ?? '',
            $components['district'] ?? '',
            !empty($components['lga']) ? ($components['lga'] . ' LGA') : '',
            !empty($components['state']) ? ($components['state'] . ' State') : '',
        ], function ($part) {
            return trim((string) $part) !== '';
        });

        return implode(', ', $parts);
    }

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

    public function FieldData()
    {
        $PageTitle = 'Field Data Collection';
        $PageDescription = 'Import, collect, and manage field data from Survey123';

        return view('programmes.field_data', compact('PageTitle', 'PageDescription'));
    }

    public function bills(Request $request)
    {
        $PageTitle = 'Bills Management';
        $PageDescription = 'Manage Initial Bills, Betterment Bills, and Bill Balance';

        // ── Conversion Applications (source=pp_conversion) ──────────────
        $sourceParam = $request->get('source');
        if ($sourceParam === 'pp_conversion') {
            return $this->conversionBills($request);
        }

        // ── Special Assignment Bills (?url=spa) ──────────────────────────
        $urlParam = $request->get('url');
        if ($urlParam === 'spa') {
            return $this->spaBills($request);
        }

        // Check if URL parameter is 'physical_planning' to filter only primary files
        $showOnlyPrimary = ($urlParam === 'physical_planning');

        // Get all file numbers for Select2 dropdown
        $primaryFiles = DB::connection('sqlsrv')->table('mother_applications')
            ->select('id', 'fileno', 'applicationID', 'first_name', 'surname', 'corporate_name')
            ->whereNotNull('fileno')
            ->get()
            ->map(function ($item) {
                $ownerName = !empty($item->corporate_name)
                    ? $item->corporate_name
                    : trim($item->first_name . ' ' . $item->surname);
                return [
                    'id' => $item->id,
                    'fileno' => $item->fileno,
                    'applicationID' => $item->applicationID,
                    'owner_name' => $ownerName,
                    'type' => 'primary',
                    'unit_type' => null,
                ];
            });

        // Only include unit files if not filtering for physical plan
        if ($showOnlyPrimary) {
            // For physical planning view include primary files and SUA units (subapplications flagged as SUA)
            $suaFiles = DB::connection('sqlsrv')->table('subapplications')
                ->select('id', 'fileno', 'main_application_id', 'first_name', 'surname', 'corporate_name', 'unit_type', 'is_sua_unit')
                ->whereNotNull('fileno')
                ->where(function ($q) {
                    $q->whereRaw("UPPER(unit_type) = 'SUA'")
                        ->orWhere('is_sua_unit', 1);
                })
                ->get()
                ->map(function ($item) {
                    $ownerName = !empty($item->corporate_name)
                        ? $item->corporate_name
                        : trim($item->first_name . ' ' . $item->surname);
                    return [
                        'id' => $item->id,
                        'fileno' => $item->fileno,
                        'applicationID' => $item->main_application_id,
                        'owner_name' => $ownerName,
                        'type' => 'sua',
                        'unit_type' => strtoupper((string) ($item->unit_type ?? '')) ?: null,
                    ];
                });

            $allFiles = $primaryFiles->merge($suaFiles)->sortBy('fileno');
        } else {
            $unitFiles = DB::connection('sqlsrv')->table('subapplications')
                ->select('id', 'fileno', 'main_application_id', 'first_name', 'surname', 'corporate_name', 'unit_type', 'is_sua_unit')
                ->whereNotNull('fileno')
                ->get()
                ->map(function ($item) {
                    $ownerName = !empty($item->corporate_name)
                        ? $item->corporate_name
                        : trim($item->first_name . ' ' . $item->surname);
                    return [
                        'id' => $item->id,
                        'fileno' => $item->fileno,
                        'applicationID' => $item->main_application_id,
                        'owner_name' => $ownerName,
                        'type' => ($item->is_sua_unit ?? 0) ? 'sua' : 'unit',
                        'unit_type' => strtoupper((string) ($item->unit_type ?? '')) ?: null,
                    ];
                });

            $allFiles = $primaryFiles->merge($unitFiles)->sortBy('fileno');
        }

        // Get initial bills data directly from mother_applications table for primary applications
        $primaryInitialBills = DB::connection('sqlsrv')->table('mother_applications')
            ->select(
                'id',
                'id as application_id',
                DB::raw('NULL as sub_application_id'),
                'applicationID',
                'application_fee as primary_application_fee',
                'processing_fee as primary_processing_fee',
                'site_plan_fee as primary_site_plan_fee',
                'payment_date as primary_payment_date',
                'receipt_number as primary_receipt_number',
                'application_fee_payment_date',
                'application_fee_receipt_number',
                'processing_fee_payment_date',
                'processing_fee_receipt_number',
                'site_plan_fee_payment_date',
                'site_plan_fee_receipt_number',
                'fileno as primary_fileno',
                'first_name as primary_first_name',
                'surname as primary_surname',
                'corporate_name as primary_corporate_name',
                'created_at',
                'Payment_Status'
            )
            ->where(function ($query) {
                $query->whereNotNull('application_fee')
                    ->orWhereNotNull('processing_fee')
                    ->orWhereNotNull('site_plan_fee');
            })
            ->get();

        // Get initial bills data directly from subapplications table for unit applications
        $unitInitialBills = DB::connection('sqlsrv')->table('subapplications')
            ->select(
                'id',
                'id as sub_application_id',
                'main_application_id as application_id',
                'application_fee as unit_application_fee',
                'processing_fee as unit_processing_fee',
                'site_plan_fee as unit_site_plan_fee',
                'payment_date as unit_payment_date',
                'receipt_number as unit_receipt_number',
                'application_fee_payment_date',
                'application_fee_receipt_no',
                'processing_fee_payment_date',
                'processing_fee_receipt_no',
                'survey_fee_payment_date',
                'survey_fee_receipt_no',
                'fileno as unit_fileno',
                'first_name as unit_first_name',
                'surname as unit_surname',
                'corporate_name as unit_corporate_name',
                'unit_type',
                'created_at',
                'Payment_Status'
            )
            ->where(function ($query) {
                $query->whereNotNull('application_fee')
                    ->orWhereNotNull('processing_fee')
                    ->orWhereNotNull('site_plan_fee');
            })
            ->get();

        // Combine all initial bills from application tables only (no billing table)
        $initialBills = $primaryInitialBills->merge($unitInitialBills);

        // Get betterment bills data
        $bettermentBills = DB::connection('sqlsrv')->table('billing')
            ->leftJoin('mother_applications', 'billing.application_id', '=', 'mother_applications.id')
            ->leftJoin('subapplications', 'billing.sub_application_id', '=', 'subapplications.id')
            ->select(
                'billing.*',
                'mother_applications.applicationID as primary_applicationID',
                'mother_applications.fileno as primary_fileno',
                'mother_applications.first_name as primary_first_name',
                'mother_applications.surname as primary_surname',
                'mother_applications.corporate_name as primary_corporate_name',
                'subapplications.fileno as unit_fileno',
                'subapplications.first_name as unit_first_name',
                'subapplications.surname as unit_surname',
                'subapplications.corporate_name as unit_corporate_name',
                'subapplications.unit_type as unit_type'
            )
            ->whereNotNull('billing.Betterment_Charges')
            ->get();

        // Get final bills data
        $finalBills = DB::connection('sqlsrv')->table('final_bills')
            ->leftJoin('mother_applications', 'final_bills.application_id', '=', 'mother_applications.id')
            ->leftJoin('subapplications', 'final_bills.sub_application_id', '=', 'subapplications.id')
            ->select(
                'final_bills.*',
                'mother_applications.fileno as primary_fileno',
                'mother_applications.first_name as primary_first_name',
                'mother_applications.surname as primary_surname',
                'mother_applications.corporate_name as primary_corporate_name',
                'subapplications.fileno as unit_fileno',
                'subapplications.first_name as unit_first_name',
                'subapplications.surname as unit_surname',
                'subapplications.corporate_name as unit_corporate_name',
                'subapplications.unit_type as unit_type'
            )
            ->get();

        // Process owner names for all bill types
        foreach ([$initialBills, $bettermentBills, $finalBills] as $billCollection) {
            foreach ($billCollection as $bill) {
                if (!empty($bill->primary_fileno)) {
                    $bill->fileno = $bill->primary_fileno;
                    $bill->applicationID = $bill->applicationID ?? $bill->primary_applicationID ?? null;
                    $bill->owner_name = !empty($bill->primary_corporate_name)
                        ? $bill->primary_corporate_name
                        : trim(($bill->primary_first_name ?? '') . ' ' . ($bill->primary_surname ?? ''));
                } else {
                    $bill->fileno = $bill->unit_fileno ?? 'N/A';
                    $bill->applicationID = $bill->application_id ?? null;
                    $bill->owner_name = !empty($bill->unit_corporate_name)
                        ? $bill->unit_corporate_name
                        : trim(($bill->unit_first_name ?? '') . ' ' . ($bill->unit_surname ?? ''));
                }

                if (isset($bill->unit_type)) {
                    $bill->unit_type = strtoupper((string) $bill->unit_type);
                }
            }
        }

        // Convert bill collections to arrays for blade filtering
        $initialBills = $initialBills->toArray();
        $bettermentBills = $bettermentBills->toArray();
        $finalBills = $finalBills->toArray();

        return view('programmes.bills', compact(
            'PageTitle',
            'PageDescription',
            'allFiles',
            'initialBills',
            'bettermentBills',
            'finalBills'
        ));
    }

    /**
     * Bills view for Conversion Applications (source=pp_conversion).
     * Loads file numbers from JSI reports and betterment bills from billing table.
     */
    private function conversionBills(Request $request)
    {
        $PageTitle = 'Conversion Applications - Betterment Bills';
        $PageDescription = 'Manage betterment bills for conversion applications';

        // Load JSI conversion files for the dropdown
        $jsiFiles = DB::connection('sqlsrv')
            ->table('joint_site_inspection_reports')
            ->whereNull('sub_application_id')
            ->where(function ($q) {
                $q->where('source', 'Conversion Applications')
                  ->orWhere('file_number', 'LIKE', 'CON-%');
            })
            ->select(
                'id',
                'file_number',
                'applicant_name',
                'applied_land_use',
                'application_id',
                'existing_site_area',
                'existing_site_length',
                'existing_site_width',
                'existing_site_measurement_entries'
            )
            ->orderBy('file_number')
            ->get();

        $parseAreaFromText = function ($value): ?float {
            $text = trim((string) $value);
            if ($text === '') {
                return null;
            }

            if (is_numeric($text)) {
                $numeric = (float) $text;
                return $numeric > 0 ? $numeric : null;
            }

            $parts = collect(preg_split('/[xX×*]/', $text))
                ->map(function ($part) {
                    $trimmed = trim((string) $part);
                    return is_numeric($trimmed) ? (float) $trimmed : null;
                })
                ->filter(function ($n) {
                    return $n !== null && $n > 0;
                })
                ->values();

            if ($parts->count() >= 2) {
                return $parts->take(2)->reduce(function ($carry, $value) {
                    return $carry * $value;
                }, 1.0);
            }

            return null;
        };

        $allFiles = $jsiFiles->map(function ($item) use ($parseAreaFromText) {
            $existingStructureTotal = 0.0;
            $resolvedExistingSiteArea = is_numeric($item->existing_site_area ?? null)
                ? (float) $item->existing_site_area
                : null;

            if ((!$resolvedExistingSiteArea || $resolvedExistingSiteArea <= 0)
                && is_numeric($item->existing_site_length ?? null)
                && is_numeric($item->existing_site_width ?? null)
            ) {
                $resolvedExistingSiteArea = (float) $item->existing_site_length * (float) $item->existing_site_width;
            }

            $entries = $item->existing_site_measurement_entries;

            if (is_string($entries) && trim($entries) !== '') {
                $decoded = json_decode($entries, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $entries = $decoded;
                }
            }

            if (is_array($entries)) {
                if (!$resolvedExistingSiteArea || $resolvedExistingSiteArea <= 0) {
                    $measurementEntry = collect($entries)->first(function ($entry) {
                        if (!is_array($entry)) {
                            return false;
                        }

                        $description = strtolower(trim((string) ($entry['description'] ?? '')));
                        return $description !== '' && strpos($description, '__') !== 0;
                    });

                    if (is_array($measurementEntry)) {
                        $candidate = $measurementEntry['dimension'] ?? ($measurementEntry['measurement_dimension'] ?? '');
                        $derivedArea = $parseAreaFromText($candidate);
                        if ($derivedArea && $derivedArea > 0) {
                            $resolvedExistingSiteArea = $derivedArea;
                        }
                    }
                }

                $metaEntry = collect($entries)->first(function ($entry) {
                    return is_array($entry)
                        && strtolower(trim((string) ($entry['description'] ?? ''))) === '__existing_structure__';
                });

                if (is_array($metaEntry) && ((int) ($metaEntry['count'] ?? 0)) === 1) {
                    $dimensions = trim((string) ($metaEntry['measurement_dimension'] ?? ''));
                    if ($dimensions !== '') {
                        $parts = collect(preg_split('/[xX×]/', $dimensions))
                            ->map(function ($part) {
                                $value = trim((string) $part);
                                return is_numeric($value) ? (float) $value : null;
                            })
                            ->filter(function ($value) {
                                return $value !== null && $value >= 0;
                            })
                            ->values();

                        if ($parts->count() >= 3) {
                            $volume = $parts->take(3)->reduce(function ($carry, $value) {
                                return $carry * $value;
                            }, 1.0);

                            if (is_finite($volume) && $volume > 0) {
                                $existingStructureTotal = $volume * 7.5;
                            }
                        }
                    }
                }
            }

            return [
                'id'         => $item->id,
                'fileno'     => $item->file_number ?? ('JSI-' . $item->id),
                'owner_name' => $item->applicant_name ?? 'N/A',
                'type'       => 'conversion',
                'unit_type'  => null,
                'existing_site_area' => ($resolvedExistingSiteArea && $resolvedExistingSiteArea > 0) ? round($resolvedExistingSiteArea, 2) : null,
                'existing_structure_total' => $existingStructureTotal,
            ];
        })->sortBy('fileno');

        // Load betterment bills for conversion (source = PP_BETTERMENT_BILL)
        $bettermentBills = DB::connection('sqlsrv')
            ->table('billing')
            ->leftJoin('joint_site_inspection_reports', function ($join) {
                $join->on('billing.source_id', '=', DB::raw('CAST(joint_site_inspection_reports.id AS NVARCHAR(50))'));
            })
            ->where('billing.source', 'PP_BETTERMENT_BILL')
            ->select(
                'billing.*',
                'joint_site_inspection_reports.file_number as jsi_file_number',
                'joint_site_inspection_reports.applicant_name as jsi_applicant_name',
                'joint_site_inspection_reports.applied_land_use as jsi_land_use',
                'joint_site_inspection_reports.id as jsi_id'
            )
            ->get();

        // Format owner names and file numbers for betterment bills
        foreach ($bettermentBills as $bill) {
            $bill->fileno = $bill->jsi_file_number ?? $bill->Sectional_Title_File_No ?? 'N/A';
            $bill->owner_name = $bill->jsi_applicant_name ?? 'N/A';
            // Map source_id to application_id so filterBillsByFile works
            $bill->application_id = $bill->source_id;
        }

        $bettermentBills = $bettermentBills->toArray();

        // Get betterment rates for the generate form
        $rates = DB::connection('sqlsrv')
            ->table('pp_betterment_rates')
            ->where('is_active', true)
            ->orderBy('category')
            ->get();

        // No initial or final bills for conversion
        $initialBills = [];
        $finalBills = [];

        return view('programmes.bills', compact(
            'PageTitle',
            'PageDescription',
            'allFiles',
            'initialBills',
            'bettermentBills',
            'finalBills',
            'rates'
        ));
    }

    /**
     * View bill details
     */
    public function viewBill($type, $id)
    {
        try {
            $bill = $this->getBillData($type, $id);

            if (!$bill) {
                return response()->json(['error' => 'Bill not found'], 404);
            }

            return response()->json([
                'success' => true,
                'bill' => $bill,
                'html' => view('programmes.partials.bill-details', compact('bill', 'type'))->render()
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to load bill details: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Print bill
     */
    public function printBill($type, $id)
    {
        try {
            $bill = $this->getBillData($type, $id);

            if (!$bill) {
                abort(404, 'Bill not found');
            }

            return view('programmes.partials.bill-print', compact('bill', 'type'));
        } catch (\Exception $e) {
            abort(500, 'Failed to load bill for printing: ' . $e->getMessage());
        }
    }

    /**
     * Download bill as PDF
     */
    public function downloadBill($type, $id)
    {
        try {
            $bill = $this->getBillData($type, $id);

            if (!$bill) {
                abort(404, 'Bill not found');
            }

            // Generate PDF using DomPDF or similar
            $pdf = app('dompdf.wrapper');
            $pdf->loadView('programmes.partials.bill-pdf', compact('bill', 'type'));

            $filename = $this->generateBillFilename($bill, $type);

            return $pdf->download($filename);
        } catch (\Exception $e) {
            abort(500, 'Failed to generate PDF: ' . $e->getMessage());
        }
    }

    /**
     * Get bill data based on type and ID
     */
    private function getBillData($type, $id)
    {
        switch ($type) {
            case 'initial':
                // For initial bills, get data from application tables instead of billing table
                $primaryBill = DB::connection('sqlsrv')->table('mother_applications')
                    ->select(
                        'id',
                        'id as application_id',
                        'fileno',
                        'first_name',
                        'surname',
                        'corporate_name',
                        'property_street_name',
                        'property_lga',
                        'application_fee',
                        'processing_fee',
                        'site_plan_fee',
                        'payment_date',
                        'receipt_number',
                        'application_fee_payment_date',
                        'application_fee_receipt_number',
                        'processing_fee_payment_date',
                        'processing_fee_receipt_number',
                        'site_plan_fee_payment_date',
                        'site_plan_fee_receipt_number',
                        'Payment_Status',
                        'created_at'
                    )
                    ->where('id', $id)
                    ->first();

                if ($primaryBill) {
                    // Map to expected structure
                    $primaryBill->primary_fileno = $primaryBill->fileno;
                    $primaryBill->primary_first_name = $primaryBill->first_name;
                    $primaryBill->primary_surname = $primaryBill->surname;
                    $primaryBill->primary_corporate_name = $primaryBill->corporate_name;
                    $primaryBill->primary_property_street = $primaryBill->property_street_name;
                    $primaryBill->primary_property_lga = $primaryBill->property_lga;
                    $primaryBill->Scheme_Application_Fee = $primaryBill->application_fee;
                    $primaryBill->Processing_Fee = $primaryBill->processing_fee;
                    $primaryBill->Site_Plan_Fee = $primaryBill->site_plan_fee;
                    return $primaryBill;
                }

                // Check subapplications
                $unitBill = DB::connection('sqlsrv')->table('subapplications')
                    ->select(
                        'id',
                        'id as sub_application_id',
                        'fileno',
                        'first_name',
                        'surname',
                        'corporate_name',
                        'property_location',
                        'unit_type',
                        'application_fee',
                        'processing_fee',
                        'site_plan_fee',
                        'payment_date',
                        'receipt_number',
                        'application_fee_payment_date',
                        'application_fee_receipt_no',
                        'processing_fee_payment_date',
                        'processing_fee_receipt_no',
                        'survey_fee_payment_date',
                        'survey_fee_receipt_no',
                        'Payment_Status',
                        'created_at'
                    )
                    ->where('id', $id)
                    ->first();

                if ($unitBill) {
                    // Map to expected structure
                    $unitBill->unit_fileno = $unitBill->fileno;
                    $unitBill->unit_first_name = $unitBill->first_name;
                    $unitBill->unit_surname = $unitBill->surname;
                    $unitBill->unit_corporate_name = $unitBill->corporate_name;
                    $unitBill->unit_property_location = $unitBill->property_location;
                    $unitBill->unit_type = strtoupper((string) ($unitBill->unit_type ?? ''));
                    $unitBill->Unit_Application_Fees = $unitBill->application_fee;
                    $unitBill->Processing_Fee = $unitBill->processing_fee;
                    $unitBill->Site_Plan_Fee = $unitBill->site_plan_fee;
                    return $unitBill;
                }

                return null;

            case 'betterment':
                $bill = DB::connection('sqlsrv')->table('billing')
                    ->leftJoin('mother_applications', 'billing.application_id', '=', 'mother_applications.id')
                    ->leftJoin('subapplications', 'billing.sub_application_id', '=', 'subapplications.id')
                    ->select(
                        'billing.*',
                        'mother_applications.fileno as primary_fileno',
                        'mother_applications.first_name as primary_first_name',
                        'mother_applications.surname as primary_surname',
                        'mother_applications.corporate_name as primary_corporate_name',
                        'mother_applications.property_street_name as primary_property_street',
                        'mother_applications.property_lga as primary_property_lga',
                        'subapplications.fileno as unit_fileno',
                        'subapplications.first_name as unit_first_name',
                        'subapplications.surname as unit_surname',
                        'subapplications.corporate_name as unit_corporate_name',
                        'subapplications.property_location as unit_property_location',
                        'subapplications.unit_type as unit_type'
                    )
                    ->where(function ($query) use ($id) {
                        $query->where('billing.application_id', $id)
                            ->orWhere('billing.sub_application_id', $id);
                    })
                    ->whereNotNull('billing.Betterment_Charges')
                    ->first();

                // Add a fallback ID if the bill doesn't have one
                if ($bill && !isset($bill->id)) {
                    $bill->id = $bill->application_id ?? $bill->sub_application_id ?? $id;
                }
                return $bill;

            case 'balance':
                $bill = DB::connection('sqlsrv')->table('final_bills')
                    ->leftJoin('mother_applications', 'final_bills.application_id', '=', 'mother_applications.id')
                    ->leftJoin('subapplications', 'final_bills.sub_application_id', '=', 'subapplications.id')
                    ->select(
                        'final_bills.*',
                        'mother_applications.fileno as primary_fileno',
                        'mother_applications.first_name as primary_first_name',
                        'mother_applications.surname as primary_surname',
                        'mother_applications.corporate_name as primary_corporate_name',
                        'mother_applications.property_street_name as primary_property_street',
                        'mother_applications.property_lga as primary_property_lga',
                        'subapplications.fileno as unit_fileno',
                        'subapplications.first_name as unit_first_name',
                        'subapplications.surname as unit_surname',
                        'subapplications.corporate_name as unit_corporate_name',
                        'subapplications.property_location as unit_property_location',
                        'subapplications.unit_type as unit_type'
                    )
                    ->where('final_bills.id', $id)
                    ->first();

                // Add a fallback ID if the bill doesn't have one
                if ($bill && !isset($bill->id)) {
                    $bill->id = $bill->application_id ?? $bill->sub_application_id ?? $id;
                }
                return $bill;

            default:
                return null;
        }
    }

    /**
     * Generate filename for bill download
     */
    private function generateBillFilename($bill, $type)
    {
        $fileno = $bill->primary_fileno ?? $bill->unit_fileno ?? 'Unknown';
        $date = date('Y-m-d');
        $typeLabel = ucfirst($type);

        return "{$typeLabel}_Bill_{$fileno}_{$date}.pdf";
    }

    public function Payments(Request $request)
    {
        // ── Special Assignment Payments (?url=spa) ───────────────────────
        if ($request->get('url') === 'spa') {
            return $this->spaPayments($request);
        }

        // ── Conversion Applications (source=pp_conversion) ──────────────
        $sourceParam = $request->get('source');
        $isConversion = ($sourceParam === 'pp_conversion');
        $isReport = ($request->get('url') === 'report');

        if ($isConversion) {
            $PageTitle = $isReport
                ? 'Conversion Applications - Payment Reports'
                : 'Conversion Applications - Payments';
            $PageDescription = $isReport
                ? 'Summary of conversion betterment payment records'
                : 'View payment records for conversion betterment bills';

            $paymentData = $this->getConversionPaymentData($request);

            return view('programmes.payments', array_merge(
                $paymentData,
                [
                    'PageTitle' => $PageTitle,
                    'PageDescription' => $PageDescription,
                ]
            ));
        }

        $PageTitle = 'Payments';
        $PageDescription = '';

        // Get payment data
        $paymentData = $this->getPaymentData();

        return view('programmes.payments', array_merge(
            $paymentData,
            [
                'PageTitle' => $PageTitle,
                'PageDescription' => $PageDescription
            ]
        ));
    }

    /**
     * Filter payment data based on request parameters
     */
    public function filterPayments(Request $request)
    {
        // Validate request
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'payment_type' => 'nullable|string|in:all,initial,betterment,final',
            'payment_status' => 'nullable|string'
        ]);

        // Handle conversion source
        if ($request->get('source') === 'pp_conversion') {
            return response()->json($this->getConversionPaymentData($request));
        }

        // Get filtered payment data
        $paymentData = $this->getPaymentData($request);

        return response()->json($paymentData);
    }

    /**
     * Get payment data with optional filters - NEW STRUCTURE
     */
    private function getPaymentData(Request $request = null)
    {
        // Query 1: Primary Application Payments (from mother_applications + billing for betterment charges)
        $primaryQuery = DB::connection('sqlsrv')->table('mother_applications')
            ->leftJoin('billing', 'mother_applications.id', '=', 'billing.application_id');

        // Apply date filters for primary applications
        if ($request && $request->filled('start_date')) {
            $primaryQuery->whereDate('mother_applications.created_at', '>=', $request->start_date);
        }
        if ($request && $request->filled('end_date')) {
            $primaryQuery->whereDate('mother_applications.created_at', '<=', $request->end_date);
        }

        // Apply payment status filter for primary applications
        if ($request && $request->filled('payment_status') && $request->payment_status !== 'all') {
            $primaryQuery->where('mother_applications.Payment_Status', $request->payment_status);
        }

        $primaryPayments = $primaryQuery
            ->select(
                'mother_applications.id',
                'mother_applications.fileno as Sectional_Title_File_No',
                'mother_applications.first_name',
                'mother_applications.surname',
                'mother_applications.corporate_name',
                'mother_applications.application_fee',
                'mother_applications.processing_fee',
                'mother_applications.site_plan_fee',
                'mother_applications.payment_date',
                'mother_applications.receipt_number',
                'mother_applications.application_fee_payment_date',
                'mother_applications.application_fee_receipt_number',
                'mother_applications.processing_fee_payment_date',
                'mother_applications.processing_fee_receipt_number',
                'mother_applications.site_plan_fee_payment_date',
                'mother_applications.site_plan_fee_receipt_number',
                'mother_applications.Payment_Status',
                'mother_applications.created_at',
                'mother_applications.sys_date',
                'billing.Betterment_Charges',
                'billing.Betterment_receipt',
                'billing.Penalty_Fees'
            )
            ->where(function ($query) {
                $query->whereNotNull('mother_applications.application_fee')
                    ->orWhereNotNull('mother_applications.processing_fee')
                    ->orWhereNotNull('mother_applications.site_plan_fee')
                    ->orWhereNotNull('billing.Betterment_Charges');
            })
            ->get();

        // Query 2: Unit Application Payments (from subapplications + final_bills for additional fees)
        $unitQuery = DB::connection('sqlsrv')->table('subapplications')
            ->leftJoin('final_bills', 'subapplications.id', '=', 'final_bills.sub_application_id')
            ->leftJoin('billing', 'subapplications.id', '=', 'billing.sub_application_id'); // <-- add this join

        // Apply date filters for unit applications
        if ($request && $request->filled('start_date')) {
            $unitQuery->whereDate('subapplications.created_at', '>=', $request->start_date);
        }
        if ($request && $request->filled('end_date')) {
            $unitQuery->whereDate('subapplications.created_at', '<=', $request->end_date);
        }

        // Apply payment status filter for unit applications
        if ($request && $request->filled('payment_status') && $request->payment_status !== 'all') {
            $unitQuery->where('subapplications.Payment_Status', $request->payment_status);
        }

        $unitPayments = $unitQuery
            ->select(
                'subapplications.id',
                'subapplications.fileno as Sectional_Title_File_No',
                'subapplications.first_name',
                'subapplications.surname',
                'subapplications.corporate_name',
                'subapplications.application_fee',
                'subapplications.processing_fee',
                'subapplications.site_plan_fee',
                'subapplications.payment_date',
                'subapplications.receipt_number',
                'subapplications.processing_fee_payment_date',
                'subapplications.processing_fee_receipt_no as processing_fee_receipt_number',
                'subapplications.application_fee_payment_date',
                'subapplications.application_fee_receipt_no as application_fee_receipt_number',
                'subapplications.survey_fee_payment_date',
                'subapplications.survey_fee_receipt_no as survey_fee_receipt_number',
                'subapplications.Payment_Status',
                'subapplications.created_at',
                'subapplications.sys_date',
                'final_bills.recertification_fee',
                'final_bills.assignment_fee',
                'final_bills.dev_charges',
                'final_bills.bill_balance', // <-- add bill_balance
                'final_bills.Bill_Balance_receipt',
                'billing.Penalty_Fees',     // <-- add Penalty_Fees
                'billing.Betterment_receipt',
                'subapplications.unit_type',
                'subapplications.is_sua_unit',
                'billing.Betterment_Charges as billing_betterment_charges'
            )
            ->where(function ($query) {
                $query->whereNotNull('subapplications.application_fee')
                    ->orWhereNotNull('subapplications.processing_fee')
                    ->orWhereNotNull('subapplications.site_plan_fee')
                    ->orWhereNotNull('final_bills.recertification_fee')
                    ->orWhereNotNull('final_bills.assignment_fee')
                    ->orWhereNotNull('final_bills.dev_charges')
                    ->orWhereNotNull('final_bills.bill_balance')
                    ->orWhereNotNull('billing.Penalty_Fees');
            })
            ->get();

        // Format owner names for primary payments
        foreach ($primaryPayments as $payment) {
            $payment->owner_name = !empty($payment->corporate_name)
                ? $payment->corporate_name
                : trim($payment->first_name . ' ' . $payment->surname);
        }

        // Format owner names for unit payments
        foreach ($unitPayments as $payment) {
            $payment->owner_name = !empty($payment->corporate_name)
                ? $payment->corporate_name
                : trim($payment->first_name . ' ' . $payment->surname);
        }

        // Combined payments for the main summary section
        $payments = collect($primaryPayments)->merge($unitPayments);

        // Calculate general payment statistics
        $totalPayments = $payments->count();
        $pendingPayments = $payments->where('Payment_Status', 'Incomplete')->count();
        $paidPayments = $payments->where('Payment_Status', 'Complete')->count();

        // Calculate sums for each payment type with safe numeric conversion using TRY_CAST
        // For primary applications
        $schemeApplicationFeeSum = collect($primaryPayments)
            ->sum(function ($payment) {
                return floatval($payment->application_fee ?? 0);
            });
        $sitePlanFeeSum = collect($primaryPayments)
            ->sum(function ($payment) {
                return floatval($payment->site_plan_fee ?? 0);
            }) +
            collect($unitPayments)
                ->sum(function ($payment) {
                    return floatval($payment->site_plan_fee ?? 0);
                });
        $processingFeeSum = collect($primaryPayments)
            ->sum(function ($payment) {
                return floatval($payment->processing_fee ?? 0);
            }) +
            collect($unitPayments)
                ->sum(function ($payment) {
                    return floatval($payment->processing_fee ?? 0);
                });
        $bettermentChargesSum = collect($primaryPayments)
            ->sum(function ($payment) {
                return floatval($payment->Betterment_Charges ?? 0);
            });
        $unitApplicationFeesSum = collect($unitPayments)
            ->sum(function ($payment) {
                return floatval($payment->application_fee ?? 0);
            });
        $recertificationFeeSum = collect($unitPayments)
            ->sum(function ($payment) {
                return floatval($payment->recertification_fee ?? 0);
            });
        $assignmentFeeSum = collect($unitPayments)
            ->sum(function ($payment) {
                return floatval($payment->assignment_fee ?? 0);
            });
        $devChargesSum = collect($unitPayments)
            ->sum(function ($payment) {
                return floatval($payment->dev_charges ?? 0);
            });
        $penaltyFeesSum = collect($primaryPayments)
            ->sum(function ($payment) {
                return floatval($payment->Penalty_Fees ?? 0);
            });

        // Add total sum of all payment fields
        $totalPaymentSum = $schemeApplicationFeeSum + $sitePlanFeeSum + $processingFeeSum +
            $bettermentChargesSum + $unitApplicationFeesSum + $recertificationFeeSum +
            $assignmentFeeSum + $devChargesSum + $penaltyFeesSum;

        // Primary vs Unit statistics for charts
        $primaryTotalSum = collect($primaryPayments)->sum(function ($payment) {
            return
                floatval($payment->application_fee ?? 0) +
                floatval($payment->site_plan_fee ?? 0) +
                floatval($payment->processing_fee ?? 0) +
                floatval($payment->Betterment_Charges ?? 0) +
                floatval($payment->Penalty_Fees ?? 0);
        });

        $unitTotalSum = collect($unitPayments)->sum(function ($payment) {
            return
                floatval($payment->application_fee ?? 0) +
                floatval($payment->processing_fee ?? 0) +
                floatval($payment->site_plan_fee ?? 0) +
                floatval($payment->recertification_fee ?? 0) +
                floatval($payment->assignment_fee ?? 0) +
                floatval($payment->dev_charges ?? 0);
        });

        // Payment trends by month (for line chart)
        $paymentsByMonth = $payments->groupBy(function ($payment) {
            if (is_object($payment) && isset($payment->created_at) && $payment->created_at) {
                return \Carbon\Carbon::parse($payment->created_at)->format('Y-m');
            }
            return 'Unknown';
        })->map(function ($group) {
            return $group->count();
        });

        return [
            'payments' => $payments,
            'primaryPayments' => $primaryPayments,
            'unitPayments' => $unitPayments,
            'totalPayments' => $totalPayments,
            'pendingPayments' => $pendingPayments,
            'paidPayments' => $paidPayments,
            'totalPaymentSum' => $totalPaymentSum,
            'schemeApplicationFeeSum' => $schemeApplicationFeeSum,
            'sitePlanFeeSum' => $sitePlanFeeSum,
            'processingFeeSum' => $processingFeeSum,
            'bettermentChargesSum' => $bettermentChargesSum,
            'unitApplicationFeesSum' => $unitApplicationFeesSum,
            'recertificationFeeSum' => $recertificationFeeSum,
            'assignmentFeeSum' => $assignmentFeeSum,
            'devChargesSum' => $devChargesSum,
            'penaltyFeesSum' => $penaltyFeesSum,
            'primaryTotalSum' => $primaryTotalSum,
            'unitTotalSum' => $unitTotalSum,
            'paymentsByMonth' => $paymentsByMonth
        ];
    }

    /**
     * Get payment data specifically for Conversion Applications (source=PP_BETTERMENT_BILL).
     * Returns the same shape as getPaymentData() so the existing payments blade works.
     */
    private function getConversionPaymentData(Request $request = null)
    {
        $query = DB::connection('sqlsrv')
            ->table('billing')
            ->leftJoin('joint_site_inspection_reports as jsi', function ($join) {
                $join->on('billing.source_id', '=', DB::raw('CAST(jsi.id AS NVARCHAR(50))'));
            })
            ->where('billing.source', 'PP_BETTERMENT_BILL');

        // Date filters
        if ($request && $request->filled('start_date')) {
            $query->whereDate('billing.created_at', '>=', $request->start_date);
        }
        if ($request && $request->filled('end_date')) {
            $query->whereDate('billing.created_at', '<=', $request->end_date);
        }

        // Status filter
        if ($request && $request->filled('payment_status') && $request->payment_status !== 'all') {
            $query->where('billing.Payment_Status', $request->payment_status);
        }

        $bills = $query->select(
            'billing.ID as id',
            'billing.Sectional_Title_File_No as Sectional_Title_File_No',
            'billing.ref_id',
            'billing.Betterment_Charges',
            'billing.Betterment_receipt',
            'billing.receipt_date',
            'billing.Payment_Status',
            'billing.property_value',
            'billing.created_at',
            'billing.source_id',
            'jsi.applicant_name',
            'jsi.applied_land_use',
            'jsi.file_number as jsi_file_number'
        )->get();

        // Format for the payments view
        foreach ($bills as $bill) {
            $bill->first_name = '';
            $bill->surname = '';
            $bill->corporate_name = '';
            $bill->owner_name = $bill->applicant_name ?? 'N/A';
            $bill->application_fee = null;
            $bill->processing_fee = null;
            $bill->site_plan_fee = null;
            $bill->payment_date = $bill->receipt_date;
            $bill->receipt_number = $bill->Betterment_receipt;
            $bill->sys_date = null;
        }

        $payments = $bills;
        $totalPayments = $payments->count();
        $paidPayments = $payments->where('Payment_Status', 'Paid')->count()
                      + $payments->where('Payment_Status', 'Complete')->count();
        $pendingPayments = $totalPayments - $paidPayments;

        $bettermentChargesSum = $payments->sum(fn ($p) => floatval($p->Betterment_Charges ?? 0));
        $totalPaymentSum = $bettermentChargesSum;

        // Payments by month for chart
        $paymentsByMonth = $payments->groupBy(function ($p) {
            return $p->created_at ? \Carbon\Carbon::parse($p->created_at)->format('Y-m') : 'Unknown';
        })->map(fn ($group) => $group->count());

        return [
            'payments' => $payments,
            'primaryPayments' => $payments,   // used by the view
            'unitPayments' => collect([]),
            'totalPayments' => $totalPayments,
            'pendingPayments' => $pendingPayments,
            'paidPayments' => $paidPayments,
            'totalPaymentSum' => $totalPaymentSum,
            'schemeApplicationFeeSum' => 0,
            'sitePlanFeeSum' => 0,
            'processingFeeSum' => 0,
            'bettermentChargesSum' => $bettermentChargesSum,
            'unitApplicationFeesSum' => 0,
            'recertificationFeeSum' => 0,
            'assignmentFeeSum' => 0,
            'devChargesSum' => 0,
            'penaltyFeesSum' => 0,
            'primaryTotalSum' => $totalPaymentSum,
            'unitTotalSum' => 0,
            'paymentsByMonth' => $paymentsByMonth,
        ];
    }

    // ... rest of the methods remain the same ...
    public function Others()
    {
        $PageTitle = 'OTHER DEPARTMENT APPROVALS';
        $PageDescription = '';

        // Fetch surveys with mother application owner information
        $surveys = DB::connection('sqlsrv')->table('surveyCadastralRecord')
            ->join('mother_applications', 'surveyCadastralRecord.application_id', '=', 'mother_applications.id')
            ->whereNull('surveyCadastralRecord.sub_application_id')
            ->select(
                'surveyCadastralRecord.fileno',
                'surveyCadastralRecord.application_id',
                'surveyCadastralRecord.survey_by',
                'surveyCadastralRecord.survey_by_date',
                'surveyCadastralRecord.drawn_by',
                'surveyCadastralRecord.drawn_by_date',
                'surveyCadastralRecord.checked_by',
                'surveyCadastralRecord.checked_by_date',
                'surveyCadastralRecord.approved_by',
                'surveyCadastralRecord.approved_by_date',
                'mother_applications.applicant_title',
                'mother_applications.first_name',
                'mother_applications.surname',
                'mother_applications.corporate_name',
                'mother_applications.multiple_owners_names'
            )->get();

        // Fetch unit surveys with subapplication owner information
        $Unitsurveys = DB::connection('sqlsrv')->table('surveyCadastralRecord')
            ->join('subapplications', 'surveyCadastralRecord.sub_application_id', '=', 'subapplications.id')
            ->whereNotNull('surveyCadastralRecord.sub_application_id')
            ->select(
                'surveyCadastralRecord.fileno',
                'surveyCadastralRecord.application_id',
                'surveyCadastralRecord.sub_application_id',
                'surveyCadastralRecord.survey_by',
                'surveyCadastralRecord.survey_by_date',
                'surveyCadastralRecord.drawn_by',
                'surveyCadastralRecord.drawn_by_date',
                'surveyCadastralRecord.checked_by',
                'surveyCadastralRecord.checked_by_date',
                'surveyCadastralRecord.approved_by',
                'surveyCadastralRecord.approved_by_date',
                'subapplications.applicant_title',
                'subapplications.first_name',
                'subapplications.surname',
                'subapplications.corporate_name',
                'subapplications.multiple_owners_names'
            )->get();

        // Process owner names for main applications
        foreach ($surveys as $survey) {
            if (!empty($survey->multiple_owners_names)) {
                $ownerArray = json_decode($survey->multiple_owners_names, true);
                $survey->owner_name = $ownerArray ? implode(', ', $ownerArray) : null;
            } elseif (!empty($survey->corporate_name)) {
                $survey->owner_name = $survey->corporate_name;
            } else {
                $survey->owner_name = trim($survey->applicant_title . ' ' . $survey->first_name . ' ' . $survey->surname);
            }
        }

        // Process owner names for unit applications
        foreach ($Unitsurveys as $survey) {
            if (!empty($survey->multiple_owners_names)) {
                $ownerArray = json_decode($survey->multiple_owners_names, true);
                $survey->owner_name = $ownerArray ? implode(', ', $ownerArray) : null;
            } elseif (!empty($survey->corporate_name)) {
                $survey->owner_name = $survey->corporate_name;
            } else {
                $survey->owner_name = trim($survey->applicant_title . ' ' . $survey->first_name . ' ' . $survey->surname);
            }
        }

        return view('programmes.approvals.other_departments', compact('surveys', 'Unitsurveys', 'PageTitle', 'PageDescription'));
    }

    public function Deeds()
    {
        $PageTitle = 'DEEDS';
        $PageDescription = '';
        $deeds = DB::connection('sqlsrv')->table('landAdministration')
            ->join('mother_applications', 'landAdministration.application_id', '=', 'mother_applications.id')
            ->select(
                'landAdministration.*',
                'mother_applications.applicant_title',
                'mother_applications.first_name',
                'mother_applications.surname',
                'mother_applications.corporate_name',
                'mother_applications.multiple_owners_names'
            )
            ->get();

        // Process owner names for deeds
        foreach ($deeds as $deed) {
            if (!empty($deed->multiple_owners_names)) {
                $ownerArray = json_decode($deed->multiple_owners_names, true);
                $deed->owner_name = $ownerArray ? implode(', ', $ownerArray) : null;
            } elseif (!empty($deed->corporate_name)) {
                $deed->owner_name = $deed->corporate_name;
            } else {
                $deed->owner_name = trim($deed->applicant_title . ' ' . $deed->first_name . ' ' . $deed->surname);
            }
        }

        // Fetch deeds with subapplication owner information
        $unitDeeds = DB::connection('sqlsrv')->table('landAdministration')
            ->join('subapplications', 'landAdministration.sub_application_id', '=', 'subapplications.id')
            ->select(
                'landAdministration.*',
                'subapplications.applicant_title',
                'subapplications.first_name',
                'subapplications.surname',
                'subapplications.corporate_name',
                'subapplications.multiple_owners_names'
            )
            ->get();
        foreach ($unitDeeds as $deed) {
            if (!empty($deed->multiple_owners_names)) {
                $ownerArray = json_decode($deed->multiple_owners_names, true);
                $deed->owner_name = $ownerArray ? implode(', ', $ownerArray) : null;
            } elseif (!empty($deed->corporate_name)) {
                $deed->owner_name = $deed->corporate_name;
            } else {
                $deed->owner_name = trim($deed->applicant_title . ' ' . $deed->first_name . ' ' . $deed->surname);
            }
        }

        return view('programmes.approvals.deeds', compact('deeds', 'unitDeeds', 'PageTitle', 'PageDescription'));
    }

    public function Lands()
    {
        $PageTitle = 'LANDS';
        $PageDescription = '';

        // Fetch lands data from the database
        $lands = DB::connection('sqlsrv')->table('eRegistry')
            ->select([
                'MLSFileNo',
                'KANGISFileNo',
                'NEWKangisFileNo',
                'Sectional_Title_File_No',
                'Commissioning_Date',
                'Decommissioning_Date',
                'Site_Plan_Approval',
                'Survey_Plan_Approval',
                'Expected_Return_Date',
                'Current_Office',
                'created_at'
            ])
            ->get();

        return view('programmes.approvals.lands', compact('lands', 'PageTitle', 'PageDescription'));
    }
    public function eRegistryNew()
    {
        $PageTitle = 'eRegistry';
        $PageDescription = 'Manages the workflow and status of sectional title applications';

        // Fetch Primary Applications data with eRegistry information and EDMS status
        $primaryApplications = DB::connection('sqlsrv')->table('mother_applications')
            ->leftJoin('eRegistry', function ($join) {
                $join->on('mother_applications.id', '=', 'eRegistry.application_id')
                    ->orOn('mother_applications.fileno', '=', 'eRegistry.Sectional_Title_File_No')
                    ->orOn('mother_applications.fileno', '=', 'eRegistry.MLSFileNo');
            })
            ->leftJoin('file_indexings', 'mother_applications.id', '=', 'file_indexings.main_application_id')
            ->select(
                'mother_applications.id as application_id',
                'mother_applications.applicant_type',
                'mother_applications.applicant_title',
                'mother_applications.first_name',
                'mother_applications.surname',
                'mother_applications.corporate_name',
                'mother_applications.rc_number',
                'mother_applications.multiple_owners_names',
                'mother_applications.fileno',
                'mother_applications.np_fileno',
                'mother_applications.land_use',
                'mother_applications.property_house_no',
                'mother_applications.property_plot_no',
                'mother_applications.property_street_name',
                'mother_applications.property_district',
                'mother_applications.property_lga',
                'mother_applications.property_state',
                'eRegistry.Commissioning_Date',
                'eRegistry.Decommissioning_Date',
                'eRegistry.Expected_Return_Date',
                'eRegistry.Current_Office',
                'file_indexings.id as file_indexing_id'
            )
            ->get();

        // Determine status and file count for each primary application based on EDMS tables
        foreach ($primaryApplications as $application) {
            $fileCount = 0;
            $scanningCount = 0;
            $pageTypingCount = 0;

            if ($application->file_indexing_id) {
                // Count files in scannings table
                $scanningCount = DB::connection('sqlsrv')->table('scannings')
                    ->where('file_indexing_id', $application->file_indexing_id)
                    ->count();

                // Count typed pages in pagetypings table
                $pageTypingCount = DB::connection('sqlsrv')->table('pagetypings')
                    ->where('file_indexing_id', $application->file_indexing_id)
                    ->count();

                $fileCount = $scanningCount;
            }

            // Determine processing status based on EDMS data
            if ($application->file_indexing_id && $scanningCount > 0 && $pageTypingCount > 0) {
                // Has file indexing, scanned files, and typed pages = Completed
                $application->processing_status = 'Completed';
            } elseif ($application->file_indexing_id && $scanningCount > 0) {
                // Has file indexing and scanned files but no typing = In Progress
                $application->processing_status = 'In Progress';
            } elseif ($application->file_indexing_id) {
                // Has file indexing but no files = In Progress
                $application->processing_status = 'In Progress';
            } else {
                // No file indexing = Not Started
                $application->processing_status = 'Not Started';
            }

            $application->file_count = $fileCount;
            $application->scanning_count = $scanningCount;
            $application->page_typing_count = $pageTypingCount;
        }

        // Fetch Unit Applications data with eRegistry information and EDMS status
        $unitApplications = DB::connection('sqlsrv')->table('subapplications')
            ->leftJoin('mother_applications', 'subapplications.main_application_id', '=', 'mother_applications.id')
            ->leftJoin('eRegistry', function ($join) {
                $join->on('subapplications.id', '=', 'eRegistry.sub_application_id')
                    ->orOn('subapplications.fileno', '=', 'eRegistry.Sectional_Title_File_No');
            })
            ->leftJoin('file_indexings', 'subapplications.id', '=', 'file_indexings.subapplication_id')
            ->select(
                'subapplications.id as sub_application_id',
                'subapplications.fileno',
                'subapplications.applicant_type',
                'subapplications.applicant_title',
                'subapplications.first_name',
                'subapplications.surname',
                'subapplications.corporate_name',
                'subapplications.rc_number',
                'subapplications.multiple_owners_names',
                'subapplications.main_application_id',
                'subapplications.unit_number',
                'subapplications.block_number',
                'subapplications.floor_number',
                'mother_applications.fileno as parent_fileno',
                'eRegistry.Commissioning_Date',
                'eRegistry.Decommissioning_Date',
                'eRegistry.Expected_Return_Date',
                'eRegistry.Current_Office',
                'file_indexings.id as file_indexing_id'
            )
            ->get();

        // Determine status and file count for each unit application based on EDMS tables
        foreach ($unitApplications as $application) {
            $fileCount = 0;
            $scanningCount = 0;
            $pageTypingCount = 0;

            if ($application->file_indexing_id) {
                // Count files in scannings table
                $scanningCount = DB::connection('sqlsrv')->table('scannings')
                    ->where('file_indexing_id', $application->file_indexing_id)
                    ->count();

                // Count typed pages in pagetypings table
                $pageTypingCount = DB::connection('sqlsrv')->table('pagetypings')
                    ->where('file_indexing_id', $application->file_indexing_id)
                    ->count();

                $fileCount = $scanningCount;
            }

            // Determine processing status based on EDMS data
            if ($application->file_indexing_id && $scanningCount > 0 && $pageTypingCount > 0) {
                // Has file indexing, scanned files, and typed pages = Completed
                $application->processing_status = 'Completed';
            } elseif ($application->file_indexing_id && $scanningCount > 0) {
                // Has file indexing and scanned files but no typing = In Progress
                $application->processing_status = 'In Progress';
            } elseif ($application->file_indexing_id) {
                // Has file indexing but no files = In Progress
                $application->processing_status = 'In Progress';
            } else {
                // No file indexing = Not Started
                $application->processing_status = 'Not Started';
            }

            $application->file_count = $fileCount;
            $application->scanning_count = $scanningCount;
            $application->page_typing_count = $pageTypingCount;
        }

        return view('programmes.eRegistry', compact('primaryApplications', 'unitApplications', 'PageTitle', 'PageDescription'));
    }

    // Add other methods from the original controller here as needed
    public function getApplicationDetails($fileId, $fileType)
    {
        try {
            $application = null;
            $bettermentBill = null;

            if ($fileType === 'primary') {
                // Get primary application details
                $application = DB::connection('sqlsrv')->table('mother_applications')
                    ->select('id', 'plot_size', 'NoOfUnits', 'property_value')
                    ->where('id', $fileId)
                    ->first();

                // Get existing betterment bill if any
                $bettermentBill = DB::connection('sqlsrv')->table('billing')
                    ->where('application_id', $fileId)
                    ->whereNotNull('Betterment_Charges')
                    ->first();
            } else {
                // Get unit application details
                $application = DB::connection('sqlsrv')->table('subapplications')
                    ->select('id', 'plot_size', 'NoOfUnits', 'property_value')
                    ->where('id', $fileId)
                    ->first();

                // Get existing betterment bill if any
                $bettermentBill = DB::connection('sqlsrv')->table('billing')
                    ->where('sub_application_id', $fileId)
                    ->whereNotNull('Betterment_Charges')
                    ->first();
            }

            if (!$application) {
                return response()->json([
                    'success' => false,
                    'message' => 'Application not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'application' => $application,
                'betterment_bill' => $bettermentBill
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching application details: ' . $e->getMessage()
            ], 500);
        }
    }



    /**
     * Put a `created_by_name` on each row from its created_by id.
     *
     * One query for the page, not one per row. A row whose created_by is null or
     * names a deleted user gets null and the listing prints a dash — the audit
     * columns came later than these tables, so plenty of historic rows have no
     * author to show.
     */
    private function attachCreatorNames($rows): void
    {
        $ids = collect($rows)->pluck('created_by')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
        $names = empty($ids) ? [] : \App\Models\User::whereIn('id', $ids)->pluck('name', 'id')->all();

        foreach ($rows as $row) {
            $row->created_by_name = $row->created_by ? ($names[(int) $row->created_by] ?? null) : null;
        }
    }

    public function PlanningRecomm(Request $request)
    {
        $PageTitle = 'Planning Recommendation';
        $PageDescription = '';

        $urlParam = strtolower(trim((string) $request->query('url', '')));
        if ($urlParam === 'approval') {
            $PageTitle = 'Planning Recommendation Approval';
        }

        // Filters for Primary Applications
        $primaryStatus = $request->query('primary_status');
        $primarySearch = $request->query('primary_search');

        // Filters for Unit Applications
        $unitStatus = $request->query('unit_status');
        $unitSearch = $request->query('unit_search');

        // Calculate statistics for primary applications
        $totalPrimaryApplications = DB::connection('sqlsrv')->table('mother_applications')->count();
        $approvedPrimaryApplications = DB::connection('sqlsrv')->table('mother_applications')->where('planning_recommendation_status', 'Approved')->count();
        $rejectedPrimaryApplications = DB::connection('sqlsrv')->table('mother_applications')->where('planning_recommendation_status', 'Declined')->count();
        $pendingPrimaryApplications = $totalPrimaryApplications - $approvedPrimaryApplications - $rejectedPrimaryApplications;

        // Calculate statistics for unit applications
        $totalUnitApplications = DB::connection('sqlsrv')->table('subapplications')->count();
        $approvedUnitApplications = DB::connection('sqlsrv')->table('subapplications')->where('planning_recommendation_status', 'Approved')->count();
        $rejectedUnitApplications = DB::connection('sqlsrv')->table('subapplications')->where('planning_recommendation_status', 'Declined')->count();
        $pendingUnitApplications = $totalUnitApplications - $approvedUnitApplications - $rejectedUnitApplications;

        // Get mother applications sorted by sys_date (most recent first)
        $primaryQuery = DB::connection('sqlsrv')->table('mother_applications');

        if ($primaryStatus && $primaryStatus !== 'All...') {
            $primaryQuery->where('planning_recommendation_status', $primaryStatus);
        }

        if ($primarySearch) {
            $primaryQuery->where(function ($q) use ($primarySearch) {
                $q->where('np_fileno', 'like', '%' . $primarySearch . '%')
                    ->orWhere('fileno', 'like', '%' . $primarySearch . '%')
                    ->orWhere('first_name', 'like', '%' . $primarySearch . '%')
                    ->orWhere('surname', 'like', '%' . $primarySearch . '%')
                    ->orWhere('corporate_name', 'like', '%' . $primarySearch . '%');
            });
        }

        $applications = $primaryQuery->orderBy('sys_date', 'desc')
            ->paginate(15, ['*'], 'primary_page');

        // Process owner names for primary applications
        foreach ($applications as $application) {
            $application->owner_name = $this->computeOwnerName($application) ?? 'N/A';
        }

        $this->attachCreatorNames($applications);

        // Get unit applications sorted by sys_date (most recent first)
        $unitQuery = DB::connection('sqlsrv')->table('subapplications')
            ->leftJoin('mother_applications', 'subapplications.main_application_id', '=', 'mother_applications.id')
            ->select(
                'subapplications.*',
                'mother_applications.np_fileno as mother_np_fileno',
                'mother_applications.applicant_type as parent_applicant_type',
                'mother_applications.applicant_title as parent_applicant_title',
                'mother_applications.first_name as parent_first_name',
                'mother_applications.surname as parent_surname',
                'mother_applications.corporate_name as parent_corporate_name',
                'mother_applications.multiple_owners_names as parent_multiple_owners_names',
                'mother_applications.planning_recommendation_status as mother_planning_status'
            );

        if ($unitStatus && $unitStatus !== 'All...') {
            $unitQuery->where('subapplications.planning_recommendation_status', $unitStatus);
        }

        if ($unitSearch) {
            $unitQuery->where(function ($q) use ($unitSearch) {
                $q->where('subapplications.np_fileno', 'like', '%' . $unitSearch . '%')
                    ->orWhere('mother_applications.np_fileno', 'like', '%' . $unitSearch . '%')
                    ->orWhere('subapplications.fileno', 'like', '%' . $unitSearch . '%')
                    ->orWhere('subapplications.mls_fileno', 'like', '%' . $unitSearch . '%')
                    ->orWhere('subapplications.unit_type', 'like', '%' . $unitSearch . '%');
            });
        }

        $unitApplications = $unitQuery->orderBy('subapplications.sys_date', 'desc')
            ->paginate(15, ['*'], 'unit_page');

        $this->attachCreatorNames($unitApplications);

        // Process owner names for unit applications
        foreach ($unitApplications as $unitApplication) {
            $ownerName = $this->computeOwnerName($unitApplication);

            if ($ownerName === null || trim($ownerName) === '') {
                $parentRow = (object) [
                    'applicant_type' => $unitApplication->parent_applicant_type ?? null,
                    'multiple_owners_names' => $unitApplication->parent_multiple_owners_names ?? null,
                    'corporate_name' => $unitApplication->parent_corporate_name ?? null,
                    'applicant_title' => $unitApplication->parent_applicant_title ?? null,
                    'first_name' => $unitApplication->parent_first_name ?? null,
                    'surname' => $unitApplication->parent_surname ?? null,
                ];

                $ownerName = $this->computeOwnerName($parentRow);
            }

            $unitApplication->owner_name = $ownerName && trim($ownerName) !== '' ? $ownerName : 'N/A';
        }

        // Fetch data for Joint Site Inspection (JSI) modal
        $districts = District::where('is_active', 1)->orderBy('name')->get();
        $lgas = Lga::where('is_active', 1)->orderBy('name')->get();
        $states = DB::connection('sqlsrv')->table('States')->orderBy('StateName')->get();
        $streetNames = StreetName::orderBy('name')->get(['id', 'name'])->toBase();
        
        $landUseTypes = Cache::remember('land_use_types_all', 3600, function () {
            return LandUseType::orderBy('name')->get(['id', 'name']);
        });

        $inspectorColumns = ['id', 'name', 'rank'];
        if (Schema::connection('sqlsrv')->hasColumn('pp_inspectors', 'in_sectional_titling')) {
            $inspectorColumns[] = 'in_physical_planning';
            $inspectorColumns[] = 'in_sectional_titling';
        }
        $inspectors = Cache::remember('pp_inspectors_all', 1800, function () use ($inspectorColumns) {
            return DB::connection('sqlsrv')
                ->table('pp_inspectors')
                ->select($inspectorColumns)
                ->orderBy('name')
                ->get();
        });

        $detailedLandUseHasLandUseId = Schema::connection('sqlsrv')->hasColumn('detailed_land_use', 'land_use_id');
        $jsiDetailedLandUsesQuery = DB::connection('sqlsrv')
            ->table('detailed_land_use')
            ->where('is_active', 1)
            ->orderByRaw("CASE name WHEN 'Residential' THEN 1 WHEN 'Commercial' THEN 2 WHEN 'Agricultural' THEN 3 WHEN 'Industrial' THEN 4 ELSE 99 END")
            ->orderBy('name');

        if ($detailedLandUseHasLandUseId) {
            $jsiDetailedLandUsesQuery->select('id', 'land_use_id', 'name');
        } else {
            $jsiDetailedLandUsesQuery->select('id', 'name');
        }
        $jsiDetailedLandUses = $jsiDetailedLandUsesQuery->get();

        $jsiPurposesByLandUse = DB::connection('sqlsrv')
            ->table('jsi_lut_purposes')
            ->select('id', 'land_use_id', 'name')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get()
            ->groupBy('land_use_id');

        $jsiRoadCategories = DB::connection('sqlsrv')
            ->table('jsi_lut_road_categories')
            ->select('id', 'name')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        $jsiReservations = DB::connection('sqlsrv')
            ->table('jsi_lut_reservations')
            ->select('id', 'name')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        return view('programmes.approvals.planning_recomm', compact(
            'applications',
            'unitApplications',
            'PageTitle',
            'PageDescription',
            'totalPrimaryApplications',
            'approvedPrimaryApplications',
            'rejectedPrimaryApplications',
            'pendingPrimaryApplications',
            'totalUnitApplications',
            'approvedUnitApplications',
            'rejectedUnitApplications',
            'pendingUnitApplications',
            'districts',
            'lgas',
            'states',
            'streetNames',
            'landUseTypes',
            'inspectors',
            'jsiDetailedLandUses',
            'jsiPurposesByLandUse',
            'jsiRoadCategories',
            'jsiReservations'
        ));
    }

    /**
     * Get primary application survey data for modal prefill (AJAX)
     */
    public function getPrimarySurveyData($id)
    {
        try {
            $app = DB::connection('sqlsrv')->table('mother_applications')->where('id', $id)->first();
            if (!$app) {
                return response()->json(['success' => false, 'message' => 'Application not found'], 404);
            }

            $survey = DB::connection('sqlsrv')->table('surveyCadastralRecord')->where('application_id', $id)->first();

            // Compute owner name
            $ownerName = $this->computeOwnerName($app) ?? 'N/A';

            // Construct location with fallback logic
            $propertyLocation = '';

            // Primary number logic: use property_house_no if available, otherwise property_plot_no
            $primaryNumber = '';
            if (!empty($app->property_house_no)) {
                $primaryNumber = $app->property_house_no;
            } elseif (!empty($app->property_plot_no)) {
                $primaryNumber = $app->property_plot_no;
            }

            // Build location string with available components
            $locationParts = array_filter([
                $primaryNumber,
                $app->property_district ?? null,
                $app->property_lga ?? null,
                $app->property_state ?? null
            ]);

            $propertyLocation = implode(', ', $locationParts);

            // Fallback to existing location fields if constructed location is empty
            if (empty($propertyLocation)) {
                $propertyLocation = $app->property_location ?? $app->location ?? '';
            }

            // Get shared utilities from shared_utilities table
            $sharedUtilities = DB::connection('sqlsrv')
                ->table('shared_utilities')
                ->where('application_id', $id)
                ->whereNull('sub_application_id') // Primary application utilities
                ->orderBy('order')
                ->pluck('utility_type')
                ->map(function ($utility) {
                    return strtolower(trim($utility));
                })
                ->toArray();



            // Get site measurements 
            $siteMeasurements = DB::connection('sqlsrv')
                ->table('site_plan_dimensions')
                ->where('application_id', $id)
                ->orderBy('order')
                ->get()
                ->map(function ($item) {
                    return [
                        'utility_type' => $item->description ?? '',
                        'measurement' => $item->dimension ?? ''
                    ];
                })
                ->toArray();

            $locationComponents = $this->extractAddressComponents($app, null, 'property');
            $applicantAddressComponents = $this->extractAddressComponents($app, null, 'applicant');
            $compiledLocation = $this->compileAddressString($locationComponents);
            $compiledApplicantAddress = $this->compileAddressString($applicantAddressComponents);

            if (empty($propertyLocation) && !empty($compiledLocation)) {
                $propertyLocation = $compiledLocation;
            }

            $fileNumber = $app->fileno ?? $app->np_fileno ?? '';

            return response()->json([
                'success' => true,
                'data' => [
                    'file_number' => $fileNumber,
                    'applicant_name' => $ownerName,
                    // Historical UI uses tp_plan_no for both LPKN and TP fields
                    'lkn_number' => $survey->tp_plan_no ?? '',
                    'tp_plan_number' => $survey->tp_plan_no ?? '',
                    'approved_plan_number' => $survey->approved_plan_no ?? '',
                    'scheme_plan_number' => $app->scheme_no ?? '',
                    'scheme_no' => $app->scheme_no ?? '',
                    'owner_name' => $ownerName,
                    'plot_no' => $app->property_plot_no ?? '',
                    'property_plot_no' => $app->property_plot_no ?? '',
                    'property_house_no' => $app->property_house_no ?? '',
                    'location' => $propertyLocation,
                    'property_location' => $propertyLocation,
                    'location_components' => $locationComponents,
                    'applicant_address_components' => $applicantAddressComponents,
                    'applicant_address' => $compiledApplicantAddress,
                    'shared_utilities' => $sharedUtilities,
                    'site_measurements' => $siteMeasurements,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get unit (sub-application) survey data for modal prefill (AJAX)
     */
    public function getUnitSurveyData($id)
    {
        try {
            $sub = DB::connection('sqlsrv')->table('subapplications')->where('id', $id)->first();
            if (!$sub) {
                return response()->json(['success' => false, 'message' => 'Sub-application not found'], 404);
            }

            // Survey numbers are generally captured against the mother application
            $survey = null;
            if (!empty($sub->main_application_id)) {
                $survey = DB::connection('sqlsrv')->table('surveyCadastralRecord')->where('application_id', $sub->main_application_id)->first();
            }

            // Compute owner name
            $ownerName = $this->computeOwnerName($sub) ?? 'N/A';

            // Get parent application for location fallback
            $parentApp = null;
            if (!empty($sub->main_application_id)) {
                $parentApp = DB::connection('sqlsrv')->table('mother_applications')->where('id', $sub->main_application_id)->first();
            }

            // Construct location with fallback logic
            $propertyLocation = '';

            // Primary number logic: use property_house_no if available, otherwise property_plot_no
            $primaryNumber = '';
            $houseNo = $sub->property_house_no ?? ($parentApp->property_house_no ?? null);
            $plotNo = $sub->property_plot_no ?? ($parentApp->property_plot_no ?? null);

            if (!empty($houseNo)) {
                $primaryNumber = $houseNo;
            } elseif (!empty($plotNo)) {
                $primaryNumber = $plotNo;
            }

            // Build location string with available components (sub first, then parent fallback)
            $locationParts = array_filter([
                $primaryNumber,
                $sub->property_district ?? ($parentApp->property_district ?? null),
                $sub->property_lga ?? ($parentApp->property_lga ?? null),
                $sub->property_state ?? ($parentApp->property_state ?? null)
            ]);

            $propertyLocation = implode(', ', $locationParts);

            // Fallback to existing location fields if constructed location is empty
            if (empty($propertyLocation)) {
                $propertyLocation = $sub->property_location ?? $sub->location ?? ($parentApp->property_location ?? ($parentApp->location ?? ''));
            }

            // Get shared utilities from shared_utilities table
            // First try sub-application specific utilities
            $sharedUtilities = DB::connection('sqlsrv')
                ->table('shared_utilities')
                ->where('sub_application_id', $id)
                ->orderBy('order')
                ->pluck('utility_type')
                ->map(function ($utility) {
                    return strtolower(trim($utility));
                })
                ->toArray();

            // If no sub-application specific utilities, get parent application utilities
            if (empty($sharedUtilities) && $parentApp) {
                $sharedUtilities = DB::connection('sqlsrv')
                    ->table('shared_utilities')
                    ->where('application_id', $parentApp->id)
                    ->whereNull('sub_application_id') // Parent application utilities
                    ->orderBy('order')
                    ->pluck('utility_type')
                    ->map(function ($utility) {
                        return strtolower(trim($utility));
                    })
                    ->toArray();
            }

            // Get site measurements - try sub-application first, then parent application
            $siteMeasurements = DB::connection('sqlsrv')
                ->table('site_plan_dimensions')
                ->where('sub_application_id', $id)
                ->orderBy('order')
                ->get()
                ->map(function ($item) {
                    return [
                        'utility_type' => $item->description ?? '',
                        'measurement' => $item->dimension ?? ''
                    ];
                })
                ->toArray();

            if (empty($siteMeasurements) && !empty($sub->main_application_id)) {
                $siteMeasurements = DB::connection('sqlsrv')
                    ->table('site_plan_dimensions')
                    ->where('application_id', $sub->main_application_id)
                    ->orderBy('order')
                    ->get()
                    ->map(function ($item) {
                        return [
                            'utility_type' => $item->description ?? '',
                            'measurement' => $item->dimension ?? ''
                        ];
                    })
                    ->toArray();
            }

            $locationComponents = $this->extractAddressComponents($sub, $parentApp, 'property');
            $applicantAddressComponents = $this->extractAddressComponents($sub, $parentApp, 'applicant');
            $compiledLocation = $this->compileAddressString($locationComponents);
            $compiledApplicantAddress = $this->compileAddressString($applicantAddressComponents);

            if (empty($propertyLocation) && !empty($compiledLocation)) {
                $propertyLocation = $compiledLocation;
            }

            $fileNumber = $sub->fileno ?? $sub->np_fileno ?? ($parentApp?->fileno ?? ($parentApp?->np_fileno ?? ''));

            return response()->json([
                'success' => true,
                'data' => [
                    'file_number' => $fileNumber,
                    'applicant_name' => $ownerName,
                    'primary_id' => $sub->main_application_id,
                    'unit_type' => $sub->unit_type ?? null,
                    'lkn_number' => $survey->tp_plan_no ?? '',
                    'tp_plan_number' => $survey->tp_plan_no ?? '',
                    'approved_plan_number' => $survey->approved_plan_no ?? '',
                    'scheme_plan_number' => $sub->scheme_no ?? '',
                    'scheme_no' => $sub->scheme_no ?? '',
                    'owner_name' => $ownerName,
                    'plot_no' => $sub->property_plot_no ?? ($parentApp->property_plot_no ?? ''),
                    'property_plot_no' => $sub->property_plot_no ?? ($parentApp->property_plot_no ?? ''),
                    'property_house_no' => $sub->property_house_no ?? ($parentApp->property_house_no ?? ''),
                    'location' => $propertyLocation,
                    'property_location' => $propertyLocation,
                    'location_components' => $locationComponents,
                    'applicant_address_components' => $applicantAddressComponents,
                    'applicant_address' => $compiledApplicantAddress,
                    'shared_utilities' => $sharedUtilities,
                    'site_measurements' => $siteMeasurements,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function showPrimarySurveyForm($id)
    {
        $application = DB::connection('sqlsrv')->table('mother_applications')->where('id', $id)->first();

        if (!$application) {
            return redirect()->route('programmes.approvals.planning_recomm')
                ->with('error', __('Primary application not found.'));
        }

        $survey = DB::connection('sqlsrv')->table('surveyCadastralRecord')->where('application_id', $id)->first();

        $formData = [
            'lkn_number' => $survey->tp_plan_no ?? '',
            'tp_plan_number' => $survey->tp_plan_no ?? '',
            'approved_plan_number' => $survey->approved_plan_no ?? '',
            'scheme_plan_number' => $application->scheme_no ?? '',
        ];

        $ownerName = $this->computeOwnerName($application) ?? 'N/A';

        $returnUrl = url()->previous();
        if (!$returnUrl || Str::contains($returnUrl, '/complete-survey')) {
            $returnUrl = route('programmes.approvals.planning_recomm');
        }

        $PageTitle = 'Complete Survey Details (Primary)';
        $PageDescription = 'Capture and update survey references for this primary application.';

        return view('programmes.approvals.primary_survey_form', compact(
            'PageTitle',
            'PageDescription',
            'application',
            'ownerName',
            'formData',
            'returnUrl'
        ));
    }

    public function showUnitSurveyForm($id)
    {
        $unitApplication = DB::connection('sqlsrv')->table('subapplications')->where('id', $id)->first();

        if (!$unitApplication) {
            return redirect()->route('programmes.approvals.planning_recomm')
                ->with('error', __('Unit application not found.'));
        }

        $primaryApplication = null;
        $primarySurvey = null;

        if (!empty($unitApplication->main_application_id)) {
            $primaryApplication = DB::connection('sqlsrv')->table('mother_applications')
                ->where('id', $unitApplication->main_application_id)
                ->first();

            if ($primaryApplication) {
                $primarySurvey = DB::connection('sqlsrv')->table('surveyCadastralRecord')
                    ->where('application_id', $primaryApplication->id)
                    ->first();
            }
        }

        $formData = [
            'lkn_number' => $primarySurvey->tp_plan_no ?? '',
            'tp_plan_number' => $primarySurvey->tp_plan_no ?? '',
            'approved_plan_number' => $primarySurvey->approved_plan_no ?? '',
            'scheme_plan_number' => $unitApplication->scheme_no ?? '',
        ];

        $ownerName = $this->computeOwnerName($unitApplication) ?? 'N/A';
        $primaryOwnerName = $primaryApplication ? $this->computeOwnerName($primaryApplication) ?? 'N/A' : null;
        $schemeRequired = strtoupper($unitApplication->unit_type ?? '') !== 'SUA';

        $returnUrl = url()->previous();
        if (!$returnUrl || Str::contains($returnUrl, '/complete-survey')) {
            $returnUrl = route('programmes.approvals.planning_recomm');
        }

        $PageTitle = 'Complete Survey Details (Unit)';
        $PageDescription = 'Capture and update survey references for this unit application.';

        return view('programmes.approvals.unit_survey_form', compact(
            'PageTitle',
            'PageDescription',
            'unitApplication',
            'primaryApplication',
            'ownerName',
            'primaryOwnerName',
            'formData',
            'returnUrl',
            'schemeRequired'
        ));
    }

    public function Director_approval(Request $request)
    {
        $PageTitle = 'Director\'s Approval';
        $PageDescription = '';

        // Filters for Primary Applications
        $primaryStatus = $request->query('primary_status');
        $primarySearch = $request->query('primary_search');

        // Filters for Unit Applications
        $unitStatus = $request->query('unit_status');
        $unitSearch = $request->query('unit_search');

        // Calculate statistics for primary applications
        $totalPrimaryApplications = DB::connection('sqlsrv')->table('mother_applications')->count();
        $approvedPrimaryApplications = DB::connection('sqlsrv')->table('mother_applications')->where('application_status', 'Approved')->count();
        $rejectedPrimaryApplications = DB::connection('sqlsrv')->table('mother_applications')->where('application_status', 'Declined')->count();
        $pendingPrimaryApplications = $totalPrimaryApplications - $approvedPrimaryApplications - $rejectedPrimaryApplications;

        // Calculate statistics for unit applications
        $totalUnitApplications = DB::connection('sqlsrv')->table('subapplications')->count();
        $approvedUnitApplications = DB::connection('sqlsrv')->table('subapplications')->where('application_status', 'Approved')->count();
        $rejectedUnitApplications = DB::connection('sqlsrv')->table('subapplications')->where('application_status', 'Declined')->count();
        $pendingUnitApplications = $totalUnitApplications - $approvedUnitApplications - $rejectedUnitApplications;

        // Get mother applications sorted by sys_date (most recent first)
        $primaryQuery = DB::connection('sqlsrv')->table('mother_applications');

        if ($primaryStatus && $primaryStatus !== 'All...') {
            if ($primaryStatus === 'Awaiting Planning Rec') {
                $primaryQuery->where('planning_recommendation_status', '!=', 'Approved');
            } elseif ($primaryStatus === 'Planning Approved') {
                $primaryQuery->where('planning_recommendation_status', 'Approved');
            } else {
                $primaryQuery->where('application_status', $primaryStatus);
            }
        }

        if ($primarySearch) {
            $primaryQuery->where(function ($q) use ($primarySearch) {
                $q->where('np_fileno', 'like', '%' . $primarySearch . '%')
                    ->orWhere('fileno', 'like', '%' . $primarySearch . '%')
                    ->orWhere('first_name', 'like', '%' . $primarySearch . '%')
                    ->orWhere('surname', 'like', '%' . $primarySearch . '%')
                    ->orWhere('corporate_name', 'like', '%' . $primarySearch . '%');
            });
        }

        $applications = $primaryQuery->orderBy('sys_date', 'desc')
            ->paginate(15, ['*'], 'primary_page');

        // Process owner names for primary applications
        foreach ($applications as $application) {
            $application->owner_name = $this->computeOwnerName($application) ?? 'N/A';
        }

        // Get unit applications sorted by sys_date (most recent first)
        $unitQuery = DB::connection('sqlsrv')->table('subapplications')
            ->leftJoin('mother_applications', 'subapplications.main_application_id', '=', 'mother_applications.id')
            ->select(
                'subapplications.*',
                'mother_applications.np_fileno as mother_np_fileno',
                'mother_applications.planning_recommendation_status as mother_planning_status',
                'mother_applications.application_status as mother_application_status',
                'mother_applications.applicant_type as parent_applicant_type',
                'mother_applications.applicant_title as parent_applicant_title',
                'mother_applications.first_name as parent_first_name',
                'mother_applications.surname as parent_surname',
                'mother_applications.corporate_name as parent_corporate_name',
                'mother_applications.multiple_owners_names as parent_multiple_owners_names'
            );

        if ($unitStatus && $unitStatus !== 'All...') {
            if ($unitStatus === 'Awaiting Planning Rec') {
                $unitQuery->where('subapplications.planning_recommendation_status', '!=', 'Approved');
            } elseif ($unitStatus === 'Planning Approved') {
                $unitQuery->where('subapplications.planning_recommendation_status', 'Approved');
            } else {
                $unitQuery->where('subapplications.application_status', $unitStatus);
            }
        }

        if ($unitSearch) {
            $unitQuery->where(function ($q) use ($unitSearch) {
                $q->where('subapplications.np_fileno', 'like', '%' . $unitSearch . '%')
                    ->orWhere('mother_applications.np_fileno', 'like', '%' . $unitSearch . '%')
                    ->orWhere('subapplications.fileno', 'like', '%' . $unitSearch . '%')
                    ->orWhere('subapplications.mls_fileno', 'like', '%' . $unitSearch . '%');
            });
        }

        $unitApplications = $unitQuery->orderBy('subapplications.sys_date', 'desc')
            ->paginate(15, ['*'], 'unit_page');

        // Process owner names for unit applications
        foreach ($unitApplications as $unitApplication) {
            $ownerName = $this->computeOwnerName($unitApplication);
            if ($ownerName === null || trim($ownerName) === '') {
                $parentRow = (object) [
                    'applicant_type' => $unitApplication->parent_applicant_type ?? null,
                    'multiple_owners_names' => $unitApplication->parent_multiple_owners_names ?? null,
                    'corporate_name' => $unitApplication->parent_corporate_name ?? null,
                    'applicant_title' => $unitApplication->parent_applicant_title ?? null,
                    'first_name' => $unitApplication->parent_first_name ?? null,
                    'surname' => $unitApplication->parent_surname ?? null,
                ];
                $ownerName = $this->computeOwnerName($parentRow);
            }
            $unitApplication->owner_name = $ownerName && trim($ownerName) !== '' ? $ownerName : 'N/A';
        }

        return view('programmes.approvals.director', compact(
            'applications',
            'unitApplications',
            'PageTitle',
            'PageDescription',
            'totalPrimaryApplications',
            'approvedPrimaryApplications',
            'rejectedPrimaryApplications',
            'pendingPrimaryApplications',
            'totalUnitApplications',
            'approvedUnitApplications',
            'rejectedUnitApplications',
            'pendingUnitApplications'
        ));
    }


    public function ST_Report()
    {
        $PageTitle = 'Sectional Titling Report';
        $PageDescription = '';

        // Fetch primary applications with related billing data
        $primaryApplications = DB::connection('sqlsrv')->table('mother_applications')
            ->leftJoin('billing', function ($join) {
                $join->on('mother_applications.id', '=', 'billing.application_id')
                    ->whereNull('billing.sub_application_id');
            })
            ->select(
                'mother_applications.id',
                'mother_applications.fileno',
                'mother_applications.applicant_type',
                'mother_applications.applicant_title',
                'mother_applications.first_name',
                'mother_applications.surname',
                'mother_applications.corporate_name',
                'mother_applications.rc_number',
                'mother_applications.multiple_owners_names',
                'mother_applications.property_lga',
                'mother_applications.NoOfUnits',
                'mother_applications.NoOfSections',
                'mother_applications.NoOfBlocks',
                'mother_applications.land_use',
                'mother_applications.residential_type',
                'mother_applications.commercial_type',
                'mother_applications.industrial_type',
                'mother_applications.mixed_type',
                'mother_applications.ownership_type',
                'mother_applications.application_status',
                'mother_applications.approval_date',
                'mother_applications.planning_recommendation_status',
                'mother_applications.planning_approval_date',
                'mother_applications.created_at',
                'mother_applications.updated_at',
                'billing.Payment_Status'
            )
            ->get();

        // Process owner names
        foreach ($primaryApplications as $application) {
            if (!empty($application->multiple_owners_names)) {
                $ownerArray = json_decode($application->multiple_owners_names, true);
                $application->owner_name = $ownerArray ? implode(', ', $ownerArray) : null;
            } elseif (!empty($application->corporate_name)) {
                $application->owner_name = $application->corporate_name;
            } else {
                $application->owner_name = trim($application->applicant_title . ' ' . $application->first_name . ' ' . $application->surname);
            }
        }

        // Calculate statistics
        $totalApplications = count($primaryApplications);
        $approvedApplications = collect($primaryApplications)->where('application_status', 'Approved')->count();
        $pendingApplications = collect($primaryApplications)->where('application_status', '!=', 'Approved')
            ->where('application_status', '!=', 'Declined')
            ->count();
        $rejectedApplications = collect($primaryApplications)->where('application_status', 'Declined')->count();

        // Planning recommendation stats
        $approvedPlanningRecommendations = collect($primaryApplications)->where('planning_recommendation_status', 'Approved')->count();
        $pendingPlanningRecommendations = collect($primaryApplications)->where('planning_recommendation_status', '!=', 'Approved')
            ->where('planning_recommendation_status', '!=', 'Declined')
            ->count();
        $rejectedPlanningRecommendations = collect($primaryApplications)->where('planning_recommendation_status', 'Declined')->count();

        // Group applications by LGA for geo chart
        $applicationsByLGA = collect($primaryApplications)
            ->groupBy('property_lga')
            ->map(function ($group) {
                return $group->count();
            });

        // Get monthly application trend data (last 12 months)
        $monthlyTrend = collect($primaryApplications)
            ->filter(function ($app) {
                return !empty($app->created_at);
            })
            ->groupBy(function ($app) {
                return \Carbon\Carbon::parse($app->created_at)->format('Y-m');
            })
            ->map(function ($group) {
                return $group->count();
            })
            ->sortKeys();

        // Prepare month labels for chart
        $monthLabels = [];
        $applicationCountByMonth = [];
        foreach ($monthlyTrend as $month => $count) {
            $monthLabels[] = $month;
            $applicationCountByMonth[] = $count;
        }

        // Fetch unit applications with related billing data and mother application context
        $unitApplications = DB::connection('sqlsrv')->table('subapplications')
            ->leftJoin('mother_applications', 'subapplications.main_application_id', '=', 'mother_applications.id')
            ->leftJoin('billing', function ($join) {
                $join->on('subapplications.id', '=', 'billing.sub_application_id');
            })
            ->select(
                'subapplications.id',
                'subapplications.main_application_id',
                'subapplications.fileno',
                'subapplications.applicant_type',
                'subapplications.applicant_title',
                'subapplications.first_name',
                'subapplications.surname',
                'subapplications.corporate_name',
                'subapplications.rc_number',
                'subapplications.multiple_owners_names',
                'subapplications.block_number',
                'subapplications.floor_number',
                'subapplications.unit_number',
                'subapplications.property_location',
                'subapplications.ownership',

                'subapplications.plot_size',
                'subapplications.commercial_type',
                'subapplications.industrial_type',
                'subapplications.ownership_type',
                'subapplications.residence_type',
                'subapplications.application_status',
                'subapplications.approval_date',
                'subapplications.planning_recommendation_status',
                'subapplications.planning_approval_date',
                'subapplications.created_at',
                'subapplications.updated_at',
                'mother_applications.land_use',
                'mother_applications.property_lga',
                'billing.Payment_Status'
            )
            ->get();

        // Process owner names for unit applications
        foreach ($unitApplications as $application) {
            if (!empty($application->multiple_owners_names)) {
                $ownerArray = json_decode($application->multiple_owners_names, true);
                $application->owner_name = $ownerArray ? implode(', ', $ownerArray) : null;
            } elseif (!empty($application->corporate_name)) {
                $application->owner_name = $application->corporate_name;
            } else {
                $application->owner_name = trim($application->applicant_title . ' ' . $application->first_name . ' ' . $application->surname);
            }
        }

        // Calculate unit application statistics
        $totalUnitApplications = count($unitApplications);
        $approvedUnitApplications = collect($unitApplications)->where('application_status', 'Approved')->count();
        $pendingUnitApplications = collect($unitApplications)->where('application_status', '!=', 'Approved')
            ->where('application_status', '!=', 'Declined')
            ->count();
        $rejectedUnitApplications = collect($unitApplications)->where('application_status', 'Declined')->count();

        // Planning recommendation stats for unit applications
        $approvedUnitPlanningRecommendations = collect($unitApplications)->where('planning_recommendation_status', 'Approved')->count();
        $pendingUnitPlanningRecommendations = collect($unitApplications)->where('planning_recommendation_status', '!=', 'Approved')
            ->where('planning_recommendation_status', '!=', 'Declined')
            ->count();
        $rejectedUnitPlanningRecommendations = collect($unitApplications)->where('planning_recommendation_status', 'Declined')->count();

        // Group unit applications by LGA for geo chart
        $unitApplicationsByLGA = collect($unitApplications)
            ->groupBy('property_lga')
            ->map(function ($group) {
                return $group->count();
            });

        // Get monthly unit application trend data
        $unitMonthlyTrend = collect($unitApplications)
            ->filter(function ($app) {
                return !empty($app->created_at);
            })
            ->groupBy(function ($app) {
                return \Carbon\Carbon::parse($app->created_at)->format('Y-m');
            })
            ->map(function ($group) {
                return $group->count();
            })
            ->sortKeys();

        // Prepare month labels for unit application chart
        $unitMonthLabels = [];
        $unitApplicationCountByMonth = [];
        foreach ($unitMonthlyTrend as $month => $count) {
            $unitMonthLabels[] = $month;
            $unitApplicationCountByMonth[] = $count;
        }

        return view('programmes.report', compact(
            'PageTitle',
            'PageDescription',
            'primaryApplications',
            'totalApplications',
            'approvedApplications',
            'pendingApplications',
            'rejectedApplications',
            'approvedPlanningRecommendations',
            'pendingPlanningRecommendations',
            'rejectedPlanningRecommendations',
            'applicationsByLGA',
            'monthLabels',
            'applicationCountByMonth',
            // Unit application data
            'unitApplications',
            'totalUnitApplications',
            'approvedUnitApplications',
            'pendingUnitApplications',
            'rejectedUnitApplications',
            'approvedUnitPlanningRecommendations',
            'pendingUnitPlanningRecommendations',
            'rejectedUnitPlanningRecommendations',
            'unitApplicationsByLGA',
            'unitMonthLabels',
            'unitApplicationCountByMonth'
        ));
    }

    public function Entity($applicationId = null)
    {
        $PageTitle = 'Entities';
        $PageDescription = 'Application Entities by Type';

        // Fetch primary applications grouped by applicant type
        $individualApplications = DB::connection('sqlsrv')->table('mother_applications')
            ->where('applicant_type', 'individual')
            ->select(
                'id',
                'fileno',
                'applicant_type',
                'first_name',
                'surname',
                'corporate_name',
                'multiple_owners_names',
                'land_use',
                'NoOfUnits',
                'receipt_date',
                'planning_recommendation_status',
                'application_status',
                'property_street_name',
                'property_lga',
                'created_at'
            )
            ->get();

        $corporateApplications = DB::connection('sqlsrv')->table('mother_applications')
            ->where('applicant_type', 'corporate')
            ->select(
                'id',
                'fileno',
                'applicant_type',
                'first_name',
                'surname',
                'corporate_name',
                'multiple_owners_names',
                'land_use',
                'NoOfUnits',
                'receipt_date',
                'planning_recommendation_status',
                'application_status',
                'property_street_name',
                'property_lga',
                'created_at'
            )
            ->get();

        $multipleApplications = DB::connection('sqlsrv')->table('mother_applications')
            ->where('applicant_type', 'multiple')
            ->select(
                'id',
                'fileno',
                'applicant_type',
                'first_name',
                'surname',
                'corporate_name',
                'multiple_owners_names',
                'land_use',
                'NoOfUnits',
                'receipt_date',
                'planning_recommendation_status',
                'application_status',
                'property_street_name',
                'property_lga',
                'created_at'
            )
            ->get();

        // Fetch unit applications grouped by applicant type
        $unitIndividualApplications = DB::connection('sqlsrv')->table('subapplications')
            ->where('applicant_type', 'individual')
            ->select(
                'id',
                'fileno',
                'applicant_type',
                'first_name',
                'surname',
                'corporate_name',
                'multiple_owners_names',
                'land_use',
                'property_location',
                'created_at',
                'planning_recommendation_status',
                'application_status'
            )
            ->get();

        $unitCorporateApplications = DB::connection('sqlsrv')->table('subapplications')
            ->where('applicant_type', 'corporate')
            ->select(
                'id',
                'fileno',
                'applicant_type',
                'first_name',
                'surname',
                'corporate_name',
                'multiple_owners_names',
                'land_use',
                'property_location',
                'created_at',
                'planning_recommendation_status',
                'application_status'
            )
            ->get();

        $unitMultipleApplications = DB::connection('sqlsrv')->table('subapplications')
            ->where('applicant_type', 'multiple')
            ->select(
                'id',
                'fileno',
                'applicant_type',
                'first_name',
                'surname',
                'corporate_name',
                'multiple_owners_names',
                'land_use',
                'property_location',
                'created_at',
                'planning_recommendation_status',
                'application_status'
            )
            ->get();

        // Process owner names for primary applications
        foreach ($individualApplications as $app) {
            $app->owner_name = trim($app->first_name . ' ' . $app->surname);
        }

        foreach ($corporateApplications as $app) {
            $app->owner_name = $app->corporate_name;
        }

        foreach ($multipleApplications as $app) {
            if (!empty($app->multiple_owners_names)) {
                $ownerArray = json_decode($app->multiple_owners_names, true);
                $app->owner_name = $ownerArray ? implode(', ', $ownerArray) : null;
            } else {
                $app->owner_name = 'Multiple Owners';
            }
        }

        // Process owner names for unit applications
        foreach ($unitIndividualApplications as $app) {
            $app->owner_name = trim($app->first_name . ' ' . $app->surname);
        }

        foreach ($unitCorporateApplications as $app) {
            $app->owner_name = $app->corporate_name;
        }

        foreach ($unitMultipleApplications as $app) {
            if (!empty($app->multiple_owners_names)) {
                $ownerArray = json_decode($app->multiple_owners_names, true);
                $app->owner_name = $ownerArray ? implode(', ', $ownerArray) : null;
            } else {
                $app->owner_name = 'Multiple Owners';
            }
        }

        // Prepare statistics
        $primaryStats = [
            'individual' => count($individualApplications),
            'corporate' => count($corporateApplications),
            'multiple' => count($multipleApplications),
            'total' => count($individualApplications) + count($corporateApplications) + count($multipleApplications)
        ];

        $unitStats = [
            'individual' => count($unitIndividualApplications),
            'corporate' => count($unitCorporateApplications),
            'multiple' => count($unitMultipleApplications),
            'total' => count($unitIndividualApplications) + count($unitCorporateApplications) + count($unitMultipleApplications)
        ];

        return view('programmes.entity.index', compact(
            'PageTitle',
            'PageDescription',
            'individualApplications',
            'corporateApplications',
            'multipleApplications',
            'unitIndividualApplications',
            'unitCorporateApplications',
            'unitMultipleApplications',
            'primaryStats',
            'unitStats'
        ));
    }

    /**
     * Get initial bill receipt data from mother_applications table
     */
    public function getInitialBillReceipt($fileNo)
    {
        try {
            // First try to find in mother_applications
            $application = DB::connection('sqlsrv')->table('mother_applications')
                ->where('fileno', $fileNo)
                ->select(
                    'id',
                    'fileno',
                    'receipt_number',
                    'application_fee',
                    'processing_fee',
                    'site_plan_fee',
                    'payment_date',
                    'Payment_Status',
                    'created_at'
                )
                ->first();

            if ($application) {
                $totalAmount = floatval($application->application_fee ?? 0) +
                    floatval($application->processing_fee ?? 0) +
                    floatval($application->site_plan_fee ?? 0);

                return response()->json([
                    'success' => true,
                    'receipt_number' => $application->receipt_number ?? 'N/A',
                    'amount' => $totalAmount,
                    'payment_date' => $application->payment_date ?
                        \Carbon\Carbon::parse($application->payment_date)->format('Y-m-d') : 'N/A',
                    'status' => $application->Payment_Status ?? 'N/A',
                    'notes' => 'Initial bill payment for primary application'
                ]);
            }

            // If not found in mother_applications, try subapplications
            $subApplication = DB::connection('sqlsrv')->table('subapplications')
                ->where('fileno', $fileNo)
                ->select(
                    'id',
                    'fileno',
                    'receipt_number',
                    'application_fee',
                    'processing_fee',
                    'site_plan_fee',
                    'payment_date',
                    'Payment_Status',
                    'created_at'
                )
                ->first();

            if ($subApplication) {
                $totalAmount = floatval($subApplication->application_fee ?? 0) +
                    floatval($subApplication->processing_fee ?? 0) +
                    floatval($subApplication->site_plan_fee ?? 0);

                return response()->json([
                    'success' => true,
                    'receipt_number' => $subApplication->receipt_number ?? 'N/A',
                    'amount' => $totalAmount,
                    'payment_date' => $subApplication->payment_date ?
                        \Carbon\Carbon::parse($subApplication->payment_date)->format('Y-m-d') : 'N/A',
                    'status' => $subApplication->Payment_Status ?? 'N/A',
                    'notes' => 'Initial bill payment for unit application'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No initial bill receipt found for file number: ' . $fileNo
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving initial bill receipt: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get better bill reference ID
     */
    public function getBetterBillReference($fileNo)
    {
        try {
            // Check in billing table for betterment charges - only for primary applications
            $bettermentBill = DB::connection('sqlsrv')->table('billing')
                ->leftJoin('mother_applications', 'billing.application_id', '=', 'mother_applications.id')
                ->where('mother_applications.fileno', $fileNo)
                ->whereNotNull('billing.Betterment_Charges')
                ->select(
                    'billing.id',
                    'billing.Betterment_Charges',
                    'billing.created_at',
                    'billing.Payment_Status'
                )
                ->first();

            if ($bettermentBill) {
                // Generate reference ID based on file number and billing ID
                $referenceId = 'BBR-' . strtoupper($fileNo) . '-' . date('Ymd') . '-' . str_pad($bettermentBill->id, 4, '0', STR_PAD_LEFT);

                return response()->json([
                    'success' => true,
                    'reference_id' => $referenceId,
                    'amount' => floatval($bettermentBill->Betterment_Charges ?? 0),
                    'generated_date' => $bettermentBill->created_at ?
                        \Carbon\Carbon::parse($bettermentBill->created_at)->format('Y-m-d') : 'N/A',
                    'status' => $bettermentBill->Payment_Status ?? 'Pending'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No better bill reference found for file number: ' . $fileNo
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving better bill reference: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save better bill receipt
     */
    public function saveBetterBillReceipt(Request $request)
    {
        try {
            // Validate the request - removed amount field as requested
            $request->validate([
                'file_no' => 'required|string',
                'receipt_number' => 'required|string',
                'receipt_date' => 'required|date',
                'notes' => 'nullable|string'
            ]);

            $fileNo = $request->input('file_no');

            // Find the billing record for this file number - only check mother_applications
            $billingRecord = DB::connection('sqlsrv')->table('billing')
                ->leftJoin('mother_applications', 'billing.application_id', '=', 'mother_applications.id')
                ->where('mother_applications.fileno', $fileNo)
                ->whereNotNull('billing.Betterment_Charges')
                ->select('billing.id')
                ->first();

            if (!$billingRecord) {
                // Attempt to create a billing record for subapplication (SUA) when missing
                $subApp = DB::connection('sqlsrv')->table('subapplications')->where('fileno', $fileNo)->first();
                $motherApp = DB::connection('sqlsrv')->table('mother_applications')->where('fileno', $fileNo)->first();

                if ($subApp) {
                    // Default Betterment charge for SUA is 40000 (as per UI requirement)
                    $insertId = DB::connection('sqlsrv')->table('billing')->insertGetId([
                        'sub_application_id' => $subApp->id,
                        'Betterment_Charges' => 40000.00,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    $billingRecord = (object) ['id' => $insertId];
                } elseif ($motherApp) {
                    $insertId = DB::connection('sqlsrv')->table('billing')->insertGetId([
                        'application_id' => $motherApp->id,
                        'Betterment_Charges' => 0.00,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    $billingRecord = (object) ['id' => $insertId];
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'No betterment bill found for file number and unable to create one automatically: ' . $fileNo
                    ]);
                }
            }

            // Update the billing record with receipt information - removed amount field
            $updateData = [
                'Betterment_receipt' => $request->input('receipt_number'),
                'Betterment_receipt_date' => $request->input('receipt_date'),
                'Betterment_receipt_notes' => $request->input('notes'),
                'updated_at' => now()
            ];

            DB::connection('sqlsrv')->table('billing')
                ->where('id', $billingRecord->id)
                ->update($updateData);

            // If Betterment_Charges is empty and this is an SUA, ensure charge is set
            try {
                $b = DB::connection('sqlsrv')->table('billing')->where('id', $billingRecord->id)->first();
                if (empty($b->Betterment_Charges)) {
                    DB::connection('sqlsrv')->table('billing')->where('id', $billingRecord->id)->update(['Betterment_Charges' => 40000.00]);
                }
            } catch (\Exception $e) {
                // ignore secondary update errors
            }

            return response()->json([
                'success' => true,
                'message' => 'Better bill receipt saved successfully'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error saving better bill receipt: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Backfill source for the ST Bill Balance form.
     *
     * Returns the Bill Balance already recorded against this unit's file
     * (or, failing that, its parent scheme's file) so the Bills screen can
     * prefill instead of asking staff to re-key it.
     *
     * Read-only: resolving never touches the billing table.
     */
    public function stBalanceSource($subApplicationId, \App\Services\StBillBalanceResolver $resolver)
    {
        try {
            $result = $resolver->forSubApplication((int) $subApplicationId);

            return response()->json([
                'success' => true,
                'data'    => $result,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('stBalanceSource failed', [
                'sub_application_id' => $subApplicationId,
                'error'              => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Could not resolve an existing bill balance.',
            ], 500);
        }
    }

    /**
     * Get bill balance reference ID for unit applications
     */
    public function getBillBalanceReference($fileNo)
    {
        try {
            // Check in final_bills table for bill balance - only for unit applications
            $billBalanceRecord = DB::connection('sqlsrv')->table('final_bills')
                ->leftJoin('subapplications', 'final_bills.sub_application_id', '=', 'subapplications.id')
                ->where('subapplications.fileno', $fileNo)
                ->whereNotNull('final_bills.bill_balance')
                ->select(
                    'final_bills.id',
                    'final_bills.bill_balance',
                    'final_bills.created_at',
                    'final_bills.bill_status'
                )
                ->first();

            if ($billBalanceRecord) {
                // Generate reference ID based on file number and bill ID
                $referenceId = 'BBL-' . strtoupper($fileNo) . '-' . date('Ymd') . '-' . str_pad($billBalanceRecord->id, 4, '0', STR_PAD_LEFT);

                return response()->json([
                    'success' => true,
                    'reference_id' => $referenceId,
                    'amount' => floatval($billBalanceRecord->bill_balance ?? 0),
                    'generated_date' => $billBalanceRecord->created_at ?
                        \Carbon\Carbon::parse($billBalanceRecord->created_at)->format('Y-m-d') : 'N/A',
                    'status' => $billBalanceRecord->bill_status ?? 'Pending'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No bill balance reference found for file number: ' . $fileNo
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving bill balance reference: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save bill balance receipt for unit applications
     */
    public function saveBillBalanceReceipt(Request $request)
    {
        try {
            // Validate the request
            $request->validate([
                'file_no' => 'required|string',
                'receipt_number' => 'required|string',
                'receipt_date' => 'required|date',
                'notes' => 'nullable|string'
            ]);

            $fileNo = $request->input('file_no');

            // Find the final_bills record for this file number - only check subapplications
            $billRecord = DB::connection('sqlsrv')->table('final_bills')
                ->leftJoin('subapplications', 'final_bills.sub_application_id', '=', 'subapplications.id')
                ->where('subapplications.fileno', $fileNo)
                ->whereNotNull('final_bills.bill_balance')
                ->select('final_bills.id')
                ->first();

            if (!$billRecord) {
                // Attempt to create a final_bills record for the subapplication when missing
                $subApp = DB::connection('sqlsrv')->table('subapplications')->where('fileno', $fileNo)->first();
                if ($subApp) {
                    $insertId = DB::connection('sqlsrv')->table('final_bills')->insertGetId([
                        'sub_application_id' => $subApp->id,
                        'bill_balance' => 0.00,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    $billRecord = (object) ['id' => $insertId];
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'No bill balance found for file number: ' . $fileNo
                    ]);
                }
            }

            // Update the final_bills record with receipt information
            $updateData = [
                'Bill_Balance_receipt' => $request->input('receipt_number'),
                'Bill_Balance_receipt_date' => $request->input('receipt_date'),
                'Bill_Balance_receipt_notes' => $request->input('notes'),
                'updated_at' => now()
            ];

            DB::connection('sqlsrv')->table('final_bills')
                ->where('id', $billRecord->id)
                ->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Bill balance receipt saved successfully'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error saving bill balance receipt: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get betterment bill reference ID (updated from getBetterBillReference)
     */
    public function getBettermentBillReference($fileNo)
    {
        try {
            // Check in billing table for betterment charges for primary OR subapplication (SUA)
            $bettermentBill = DB::connection('sqlsrv')->table('billing')
                ->leftJoin('mother_applications', 'billing.application_id', '=', 'mother_applications.id')
                ->leftJoin('subapplications', 'billing.sub_application_id', '=', 'subapplications.id')
                ->where(function ($q) use ($fileNo) {
                    $q->where('mother_applications.fileno', $fileNo)
                        ->orWhere('subapplications.fileno', $fileNo);
                })
                ->whereNotNull('billing.Betterment_Charges')
                ->select(
                    'billing.id',
                    'billing.Betterment_Charges',
                    'billing.created_at',
                    'billing.Payment_Status'
                )
                ->first();

            if ($bettermentBill) {
                // Generate reference ID based on file number and billing ID
                $referenceId = 'BTR-' . strtoupper($fileNo) . '-' . date('Ymd') . '-' . str_pad($bettermentBill->id, 4, '0', STR_PAD_LEFT);

                return response()->json([
                    'success' => true,
                    'reference_id' => $referenceId,
                    'amount' => floatval($bettermentBill->Betterment_Charges ?? 0),
                    'generated_date' => $bettermentBill->created_at ?
                        \Carbon\Carbon::parse($bettermentBill->created_at)->format('Y-m-d') : 'N/A',
                    'status' => $bettermentBill->Payment_Status ?? 'Pending'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No betterment bill reference found for file number: ' . $fileNo
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving betterment bill reference: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save betterment bill receipt (updated from saveBetterBillReceipt)
     */
    public function saveBettermentBillReceipt(Request $request)
    {
        try {
            // Validate the request
            $request->validate([
                'file_no' => 'required|string',
                'receipt_number' => 'required|string',
                'receipt_date' => 'required|date',
                'notes' => 'nullable|string'
            ]);

            $fileNo = $request->input('file_no');

            // Find the billing record for this file number - check both mother_applications and subapplications
            $billingRecord = DB::connection('sqlsrv')->table('billing')
                ->leftJoin('mother_applications', 'billing.application_id', '=', 'mother_applications.id')
                ->leftJoin('subapplications', 'billing.sub_application_id', '=', 'subapplications.id')
                ->where(function ($q) use ($fileNo) {
                    $q->where('mother_applications.fileno', $fileNo)
                        ->orWhere('subapplications.fileno', $fileNo);
                })
                ->whereNotNull('billing.Betterment_Charges')
                ->select('billing.id')
                ->first();

            if (!$billingRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'No betterment bill found for file number: ' . $fileNo
                ]);
            }

            // Update the billing record with receipt information
            $updateData = [
                'Betterment_receipt' => $request->input('receipt_number'),
                'Betterment_receipt_date' => $request->input('receipt_date'),
                'Betterment_receipt_notes' => $request->input('notes'),
                'updated_at' => now()
            ];

            DB::connection('sqlsrv')->table('billing')
                ->where('id', $billingRecord->id)
                ->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Betterment bill receipt saved successfully'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error saving betterment bill receipt: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save specific fee receipt (Application, Processing, or Site Plan/Survey Fee)
     */
    public function saveSpecificReceipt(Request $request)
    {
        try {
            $request->validate([
                'file_no' => 'required|string',
                'fee_type' => 'required|string|in:application_fee,processing_fee,site_plan_fee',
                'receipt_number' => 'required|string',
                'receipt_date' => 'required|date',
            ]);

            $fileNo = $request->input('file_no');
            $feeType = $request->input('fee_type');
            $receiptNo = $request->input('receipt_number');
            $receiptDate = $request->input('receipt_date');

            // Try primary first
            $primary = DB::connection('sqlsrv')->table('mother_applications')->where('fileno', $fileNo)->first();
            if ($primary) {
                $columnPrefix = $feeType;
                $updateData = [
                    $columnPrefix . '_receipt_number' => $receiptNo,
                    $columnPrefix . '_payment_date' => $receiptDate,
                    'updated_at' => now()
                ];
                
                // If it's the first receipt, also update the main receipt_number if empty
                if (empty($primary->receipt_number)) {
                    $updateData['receipt_number'] = $receiptNo;
                    $updateData['payment_date'] = $receiptDate;
                }

                DB::connection('sqlsrv')->table('mother_applications')
                    ->where('id', $primary->id)
                    ->update($updateData);

                return response()->json(['success' => true, 'message' => 'Receipt saved successfully']);
            }

            // Try unit next
            $unit = DB::connection('sqlsrv')->table('subapplications')->where('fileno', $fileNo)->first();
            if ($unit) {
                $columnReceipt = ($feeType === 'site_plan_fee') ? 'survey_fee_receipt_no' : ($feeType . '_receipt_no');
                $columnDate = ($feeType === 'site_plan_fee') ? 'survey_fee_payment_date' : ($feeType . '_payment_date');

                $updateData = [
                    $columnReceipt => $receiptNo,
                    $columnDate => $receiptDate,
                    'updated_at' => now()
                ];

                if (empty($unit->receipt_number)) {
                    $updateData['receipt_number'] = $receiptNo;
                    $updateData['payment_date'] = $receiptDate;
                }

                DB::connection('sqlsrv')->table('subapplications')
                    ->where('id', $unit->id)
                    ->update($updateData);

                return response()->json(['success' => true, 'message' => 'Receipt saved successfully']);
            }

            return response()->json(['success' => false, 'message' => 'File not found'], 404);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─── Special Assignment Bills & Payments ──────────────────────────────────

    /**
     * Row buttons for a SPAS bill.
     *
     * "Pay" disappears once nothing is owed — a paid bill with a live Pay button
     * invites a second collection against it. The receipt of the most recent
     * payment stays reachable for reprinting, which is the copy an owner asks
     * for after losing theirs.
     */
    private function spaBillActions(\App\Models\SpaBill $bill): string
    {
        $buttons = [];

        if ($bill->status !== 'paid') {
            $buttons[] = '<button data-id="'.$bill->id.'" class="btn-pay text-xs px-3 py-1 rounded bg-[rgb(186,191,12)] text-white">Pay</button>';
        }

        $latest = $bill->payments->sortByDesc('id')->first();

        if ($latest) {
            $buttons[] = '<a href="'.route('special-assignment.bills.receipt', $latest->id).'" target="_blank" '
                .'class="text-xs px-3 py-1 rounded border border-gray-200 text-gray-600 hover:bg-gray-50">Receipt</a>';
        }

        return $buttons ? '<div class="flex items-center gap-2">'.implode('', $buttons).'</div>' : '—';
    }

    /**
     * The bill-item columns the bills table shows, in tariff order.
     *
     * Taken from what bills were actually COMPOSED OF, not from the current
     * tariff: an item removed from the tariff still has to have a column, or an
     * old bill's figures would vanish from the table while its total kept
     * counting them. Tariff order is applied where it is known so the columns
     * read the way the settings screen does, with anything retired sorted last.
     *
     * @return array<int,string>
     */
    private function spaBillItemColumns(): array
    {
        $used = \App\Models\SpaBillLine::select('name')->distinct()->pluck('name');

        if ($used->isEmpty()) {
            return [];
        }

        $order = \App\Models\SpaBillItem::orderBy('sort_order')->pluck('sort_order', 'name');

        return $used->sortBy(fn ($name) => $order[$name] ?? PHP_INT_MAX)->values()->all();
    }

    private function spaBills(Request $request)
    {
        $csrf = csrf_token();

        // One column per bill item, so the composition of a bill is readable at a
        // glance instead of being buried in the description sentence.
        $itemColumns = $this->spaBillItemColumns();

        if ($request->ajax()) {
            // Payments and lines come along so a row can offer a receipt reprint
            // and show its per-item figures without a query per row.
            $query = \App\Models\SpaBill::with(['application', 'payments', 'lines'])->orderByDesc('created_at');
            $total = $query->count();
            $data  = $query->skip($request->input('start', 0))
                           ->take($request->input('length', 10))
                           ->get()
                           ->map(function ($b, $i) use ($request, $itemColumns) {
                               $paid    = (float) $b->payments->sum('amount_paid');
                               $byName  = $b->lines->keyBy('name');

                               $row = [
                                   'DT_RowIndex'  => $request->input('start', 0) + $i + 1,
                                   'reference_id' => $b->reference_id ?? '—',
                                   'file_number'  => optional($b->application)->file_number ?? '—',
                                   'owner_name'   => optional($b->application)->owner_name  ?? '—',
                                   'bill_type'    => $b->bill_type ?? '—',
                                   'description'  => $b->description ?? '—',
                                   'amount'       => number_format($b->amount, 2),
                                   'paid'         => number_format($paid, 2),
                                   'balance'      => number_format(max(0, (float) $b->amount - $paid), 2),
                                   'due_date'     => $b->due_date?->format('d/m/Y') ?? '—',
                                   'status'       => $b->status,
                                   'action'       => $this->spaBillActions($b),
                               ];

                               // Keyed by position rather than by name: item names
                               // contain spaces and are edited by officers, and the
                               // column list is built from the same source, so the
                               // two cannot drift apart.
                               foreach ($itemColumns as $index => $name) {
                                   $row['item_'.$index] = isset($byName[$name])
                                       ? number_format($byName[$name]->amount, 2)
                                       : '—';
                               }

                               return $row;
                           });

            return response()->json([
                'draw'            => intval($request->input('draw')),
                'recordsTotal'    => $total,
                'recordsFiltered' => $total,
                'data'            => $data,
            ]);
        }

        // Load applications for the "Add Bill" modal select
        $applications = \App\Models\SpaApplication::select('id', 'file_number', 'owner_name')->orderBy('file_number')->get();

        $stats = [
            'total'   => \App\Models\SpaBill::count(),
            'unpaid'  => \App\Models\SpaBill::where('status', 'unpaid')->count(),
            'paid'    => \App\Models\SpaBill::where('status', 'paid')->count(),
            'partial' => \App\Models\SpaBill::where('status', 'partial')->count(),
        ];

        $PageTitle       = 'Special Assignment – Bills';
        $PageDescription = 'Manage billing for Special Assignment cases.';

        return view('programmes.spa_bills', compact('PageTitle', 'PageDescription', 'stats', 'applications', 'itemColumns'));
    }

    private function spaPayments(Request $request)
    {
        if ($request->ajax()) {
            $dotIcon = '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="12" cy="19" r="1.8"/></svg>';

            $query = \App\Models\SpaBill::with(['application', 'payments'])->orderByDesc('created_at');
            $total = $query->count();
            $data  = $query->skip($request->input('start', 0))
                           ->take($request->input('length', 10))
                           ->get()
                           ->map(function ($b, $i) use ($request, $dotIcon) {
                               $totalPaid = $b->payments->sum('amount_paid');
                               $balance   = max(0, (float) $b->amount - $totalPaid);

                               $actionItems = '';
                               if ($b->status !== 'paid') {
                                   $actionItems .= '<button class="btn-record-payment w-full text-left px-3 py-2 text-xs text-gray-700 hover:bg-gray-50"'
                                       . ' data-id="'.$b->id.'"'
                                       . ' data-file="'.e(optional($b->application)->file_number ?? '').'"'
                                       . ' data-type="'.e($b->bill_type ?? '').'"'
                                       . ' data-amount="'.e(number_format($b->amount, 2)).'"'
                                       . ' data-balance="'.e(number_format($balance, 2)).'"'
                                       . '>Record Payment</button>';
                               }

                               $action = '<div class="relative inline-block">'
                                   . '<button class="btn-action-toggle p-1.5 rounded-lg text-gray-500 hover:bg-gray-100 border border-transparent hover:border-gray-200 transition-colors">'
                                   . $dotIcon . '</button>'
                                   . '<div class="action-dropdown hidden absolute right-0 top-full z-50 mt-1 w-40 bg-white border border-gray-200 rounded-lg shadow-lg overflow-hidden">'
                                   . $actionItems
                                   . '<button class="btn-view-bill w-full text-left px-3 py-2 text-xs text-gray-700 hover:bg-gray-50" data-id="'.$b->id.'">View Payments</button>'
                                   . '</div></div>';

                               return [
                                   'DT_RowIndex' => $request->input('start', 0) + $i + 1,
                                   'file_number' => optional($b->application)->file_number ?? '—',
                                   'owner_name'  => optional($b->application)->owner_name  ?? '—',
                                   'bill_type'   => $b->bill_type ?? '—',
                                   'bill_amount' => number_format((float) $b->amount, 2),
                                   'amount_paid' => number_format($totalPaid, 2),
                                   'balance'     => number_format($balance, 2),
                                   'status'      => $b->status,
                                   'action'      => $action,
                               ];
                           });

            return response()->json([
                'draw'            => intval($request->input('draw')),
                'recordsTotal'    => $total,
                'recordsFiltered' => $total,
                'data'            => $data,
            ]);
        }

        $stats = [
            'total_payments' => \App\Models\SpaPayment::count(),
            'total_amount'   => number_format(\App\Models\SpaPayment::sum('amount_paid'), 2),
            'bills_paid'     => \App\Models\SpaBill::where('status', 'paid')->count(),
            'bills_partial'  => \App\Models\SpaBill::where('status', 'partial')->count(),
        ];

        $isReport = $request->get('sub') === 'report';

        $PageTitle       = $isReport ? 'Special Assignment – Payment Reports' : 'Special Assignment – Payments';
        $PageDescription = $isReport ? 'Summary of SPAS payment records.' : 'Record and manage payments for Special Assignment bills.';

        return view('programmes.spa_payments', compact('PageTitle', 'PageDescription', 'stats', 'isReport'));
    }

    /**
     * MASTER DELETE — unmake an ST planning recommendation.
     *
     * ST keeps no recommendation record: the decision lives as three columns on
     * the application itself (planning_recommendation_status, its approval date and
     * its comment). There is therefore nothing to delete, only to unmake — this
     * puts the file back to Pending so the recommendation can be given again.
     *
     * Named Master Delete, and gated like one, because from the operator's side it
     * is the same act on the same kind of row as the Land and SLTR deletes: an
     * approval put on a file in error, taken off it.
     *
     * The application, its units, its memo, its RofO and its file number all
     * survive. Erasing an ST FILE is CommissionNewSTController::masterDestroy.
     */
    public function masterDestroyPlanningRecommendation(Request $request)
    {
        if ($deny = $this->denyUnlessMasterDeleter()) {
            return $deny;
        }

        $validated = $request->validate([
            'scope' => 'required|string|in:primary,unit',
            'id'    => 'required|integer',
        ]);

        $isPrimary = $validated['scope'] === 'primary';
        $table     = $isPrimary ? 'mother_applications' : 'subapplications';

        $row = DB::connection('sqlsrv')->table($table)
            ->where('id', $validated['id'])
            ->first();

        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Application not found.'], 404);
        }

        // The typed confirmation is the only safeguard between a row menu and an
        // irreversible change, so a file number is required to have one at all.
        $reference = trim((string) ($row->fileno ?? ''));
        if ($reference === '') {
            return response()->json([
                'success' => false,
                'message' => 'This application has no file number, so a master delete cannot be confirmed against it.',
            ], 422);
        }

        if ($deny = $this->denyUnlessConfirmationMatches($request, $reference)) {
            return $deny;
        }

        $snapshot = [
            'planning_recommendation_status' => $row->planning_recommendation_status ?? null,
            'planning_approval_date'         => $row->planning_approval_date ?? null,
            'comments'                       => $isPrimary
                ? ($row->recomm_comments ?? null)
                : ($row->planning_recomm_comments ?? null),
        ];

        DB::connection('sqlsrv')->beginTransaction();
        try {
            $counts = app(RofoRecommendationPurgeService::class)
                ->resetStRecommendation($validated['scope'], (int) $validated['id']);

            DB::connection('sqlsrv')->commit();

            $this->logMasterDelete(
                'st_planning_recommendation',
                $validated['id'],
                $snapshot,
                $counts,
                'ST Planning Recommendation ' . $reference
            );

            return response()->json([
                'success' => true,
                'message' => 'Planning recommendation for ' . $reference . ' cleared. The application is back to Pending.',
                'details' => $counts,
            ]);
        } catch (\Throwable $e) {
            DB::connection('sqlsrv')->rollBack();
            Log::error('ST planning recommendation master delete failed', [
                'scope' => $validated['scope'],
                'id'    => $validated['id'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error clearing planning recommendation: ' . $e->getMessage(),
            ], 500);
        }
    }
}
