<?php

namespace App\Http\Controllers;

use App\Models\BillBalance;
use App\Models\StreetName;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class BillBalanceController extends Controller
{
    private const BILLING_SOURCE = 'DEEDS_BILL_BALANCE';
    private const BILLING_PRIMARY_KEY = 'ID';

    /**
     * Keys that belong to the global billing table.
     */
    private array $billingFieldKeys = [
        'bill_balance_reciept',
        'bill_balance_date',
        'Site_Plan_Fee',
        'survey_fee',
        'Processing_Fee',
        'Betterment_Charges',
        'Land_Use_Charge',
        'Penalty_Fees',
    ];

    public function index(Request $request): View
    {
        $table = (new BillBalance())->getTable();
        $query = BillBalance::query()
            ->leftJoin('billing as billing_records', 'billing_records.id', '=', "$table.billing_id")
            ->select(
                "$table.*",
                'billing_records.bill_balance_reciept as billing_receipt',
                'billing_records.bill_balance_date as billing_date',
                'billing_records.Payment_Status as billing_payment_status',
                'billing_records.Site_Plan_Fee as billing_site_plan_fee',
                'billing_records.survey_fee as billing_survey_fee',
                'billing_records.Processing_Fee as billing_processing_fee',
                'billing_records.Betterment_Charges as billing_betterment_charges',
                'billing_records.Land_Use_Charge as billing_land_use_charge',
                'billing_records.Penalty_Fees as billing_penalty_fees'
            );

        // Search functionality
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search, $table) {
                $q->where("$table.reference", 'LIKE', "%$search%")
                  ->orWhere("$table.file_number", 'LIKE', "%$search%")
                  ->orWhere("$table.applicant_name", 'LIKE', "%$search%")
                  ->orWhere("$table.location_station", 'LIKE', "%$search%")
                  ->orWhere("$table.district", 'LIKE', "%$search%")
                  ->orWhere('billing_records.bill_balance_reciept', 'LIKE', "%$search%");
            });
        }

        // Date range filtering
        if ($dateFrom = $request->get('date_from')) {
            $query->where("$table.created_at", '>=', Carbon::parse($dateFrom)->startOfDay());
        }
        if ($dateTo = $request->get('date_to')) {
            $query->where("$table.created_at", '<=', Carbon::parse($dateTo)->endOfDay());
        }

        // Payment status filtering
        if ($status = $request->get('status')) {
            if ($status === 'paid') {
                $query->where('billing_records.Payment_Status', 'Paid');
            } elseif ($status === 'pending') {
                $query->where(function ($q) {
                    $q->where('billing_records.Payment_Status', '!=', 'Paid')
                      ->orWhereNull('billing_records.Payment_Status');
                });
            }
        }

        // Sorting
        $sortBy = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');
        
        $allowedSorts = ['created_at', 'reference', 'file_number', 'applicant_name', 'amount', 'updated_at'];
        if (in_array($sortBy, $allowedSorts)) {
            if ($sortBy === 'created_at' || $sortBy === 'updated_at') {
                $query->orderBy("$table.$sortBy", $sortDirection);
            } else {
                $query->orderBy("$table.$sortBy", $sortDirection);
            }
        } else {
            $query->latest("$table.created_at");
        }

        $perPage = $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100]) ? $perPage : 25;
        
        $records = $query->paginate($perPage)->withQueryString();

        // Statistics
        $stats = [
            'total' => BillBalance::count(),
            'total_amount' => BillBalance::sum(DB::raw("CAST(amount AS DECIMAL(18,2))")),
            'paid_count' => BillBalance::query()
                ->join('billing', 'billing.id', '=', 'deeds_bill_balances_metadata.billing_id')
                ->where('billing.Payment_Status', 'Paid')
                ->count(),
            'pending_count' => BillBalance::query()
                ->leftJoin('billing', 'billing.id', '=', 'deeds_bill_balances_metadata.billing_id')
                ->where(function ($q) {
                    $q->where('billing.Payment_Status', '!=', 'Paid')
                      ->orWhereNull('billing.Payment_Status');
                })
                ->count(),
            'generated_today' => BillBalance::whereDate('created_at', Carbon::today())->count(),
            'total_revenue' => BillBalance::query()
                ->join('billing', 'billing.id', '=', 'deeds_bill_balances_metadata.billing_id')
                ->sum(DB::raw("CAST(billing.Penalty_Fees AS DECIMAL(18,2))")),
        ];

        return view('bill_balance.index', [
            'records' => $records,
            'billBalance' => new BillBalance(),
            'billingValues' => $this->defaultBillingValues(),
            'stats' => $stats,
            'filters' => $request->only(['search', 'date_from', 'date_to', 'status', 'sort', 'direction', 'per_page']),
        ] + $this->addressLookups());
    }

    public function create(): View
    {
        $billBalance = new BillBalance();
        $billBalance->reference = $this->generateReference();

        return view('bill_balance.create', [
            'billBalance' => $billBalance,
            'billingValues' => $this->defaultBillingValues(),
        ] + $this->addressLookups());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        if (empty($data['reference'])) {
            $data['reference'] = $this->generateReference();
        }

        if (empty($data['prepared_at'])) {
            $data['prepared_at'] = Carbon::now()->format('Y-m-d H:i:s');
        }

        $metadata = Arr::except($data, $this->billingFieldKeys);
        $billBalance = BillBalance::create($metadata);
        $billingId = $this->syncBillingRecord($billBalance, $data);

        if ($billingId && !$billBalance->billing_id) {
            $billBalance->forceFill(['billing_id' => $billingId])->save();
        }

        return redirect()->route('bill-balance.index')->with('status', 'Bill balance created.');
    }

    public function show(BillBalance $billBalance): View
    {
        return view('bill_balance.show', [
            'billBalance' => $billBalance,
            'billingRecord' => $this->fetchBillingRecord($billBalance->billing_id),
        ]);
    }

    public function print(BillBalance $billBalance): View
    {
        $billing = $this->fetchBillingRecord($billBalance->billing_id);
        
        return view('bill_balance.print', [
            'billBalance' => $billBalance,
            'billing' => $billing ?: (object) $this->baseBillingPayload(),
        ]);
    }

    public function edit(BillBalance $billBalance): View
    {
        return view('bill_balance.edit', [
            'billBalance' => $billBalance,
            'billingValues' => $this->billingFormValues($billBalance),
        ] + $this->addressLookups());
    }

    public function update(Request $request, BillBalance $billBalance): RedirectResponse
    {
        $data = $this->validatedData($request);
        $metadata = Arr::except($data, $this->billingFieldKeys);
        $billBalance->update($metadata);
        $billingId = $this->syncBillingRecord($billBalance, $data);

        if ($billingId && $billBalance->billing_id !== $billingId) {
            $billBalance->forceFill(['billing_id' => $billingId])->save();
        }

        return redirect()->route('bill-balance.index')->with('status', 'Bill balance updated.');
    }

    public function destroy(BillBalance $billBalance): RedirectResponse
    {
        $billBalance->delete();

        return redirect()->route('bill-balance.index')->with('status', 'Bill balance removed.');
    }

    protected function validatedData(Request $request): array
    {
        return $request->validate([
            'reference' => ['nullable', 'string', 'max:50'],
            'file_number' => ['nullable', 'string', 'max:50'],
            'applicant_name' => ['required', 'string', 'max:120'],
            'applicant_address' => ['nullable', 'string', 'max:255'],
            'location_station' => ['nullable', 'string', 'max:120'],
            'district' => ['nullable', 'string', 'max:120'],
            'area' => ['nullable', 'numeric', 'min:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'amount' => ['required', 'numeric', 'min:0'],
            'prepared_at' => ['nullable', 'date'],
            'date_of_expiry' => ['nullable', 'date'],
            'rent_from_year' => ['nullable', 'integer', 'min:1900'],
            'rent_to_year' => ['nullable', 'integer', 'min:1900'],
            'app_house_number' => ['nullable', 'string', 'max:50'],
            'app_plot_number' => ['nullable', 'string', 'max:50'],
            'app_street_name' => ['nullable', 'string', 'max:120'],
            'app_district' => ['nullable', 'string', 'max:120'],
            'app_lga' => ['nullable', 'string', 'max:120'],
            'app_state' => ['nullable', 'string', 'max:120'],
            'loc_house_number' => ['nullable', 'string', 'max:50'],
            'loc_plot_number' => ['nullable', 'string', 'max:50'],
            'loc_street_name' => ['nullable', 'string', 'max:120'],
            'loc_district' => ['nullable', 'string', 'max:120'],
            'loc_lga' => ['nullable', 'string', 'max:120'],
            'loc_state' => ['nullable', 'string', 'max:120'],
            'bill_balance_reciept' => ['nullable', 'string', 'max:150'],
            'bill_balance_date' => ['nullable', 'date'],
            'Site_Plan_Fee' => ['nullable', 'numeric', 'min:0'],
            'survey_fee' => ['nullable', 'numeric', 'min:0'],
            'Processing_Fee' => ['nullable', 'numeric', 'min:0'],
            'Betterment_Charges' => ['nullable', 'numeric', 'min:0'],
            'Land_Use_Charge' => ['nullable', 'numeric', 'min:0'],
            'Penalty_Fees' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    public function nextReference(Request $request)
    {
        $year = (int) ($request->get('year') ?: now()->year);

        return response()->json([
            'success' => true,
            'data' => [
                'reference' => $this->generateReference($year),
            ],
        ]);
    }

    private function addressLookups(): array
    {
        $states = DB::connection('sqlsrv')
            ->table('States')
            ->orderBy('StateName')
            ->get();

        $lgas = DB::connection('sqlsrv')
            ->table('StatLGAs')
            ->join('States', 'StatLGAs.StateID', '=', 'States.StateID')
            ->where('States.StateName', 'Kano')
            ->orderBy('LGAName')
            ->select('StatLGAs.LGAName')
            ->get();

        $districts = DB::connection('sqlsrv')
            ->table('districts')
            ->where('is_active', 1)
            ->orderBy('name')
            ->select('name')
            ->get();

        $streetNames = StreetName::orderBy('name')->get(['id', 'name'])->toBase();

        return compact('states', 'lgas', 'districts', 'streetNames');
    }

    private function defaultBillingValues(): array
    {
        return [
            'bill_balance_reciept' => null,
            'bill_balance_date' => null,
            'Site_Plan_Fee' => 0,
            'survey_fee' => 0,
            'Processing_Fee' => 0,
            'Betterment_Charges' => 0,
            'Land_Use_Charge' => 0,
            'Penalty_Fees' => 0,
        ];
    }

    private function billingFormValues(?BillBalance $billBalance): array
    {
        if (!$billBalance || !$billBalance->billing_id) {
            return $this->defaultBillingValues();
        }

        $record = $this->fetchBillingRecord($billBalance->billing_id);

        if (!$record) {
            return $this->defaultBillingValues();
        }

        return [
            'bill_balance_reciept' => $record->bill_balance_reciept ?? null,
            'bill_balance_date' => $record->bill_balance_date
                ? Carbon::parse($record->bill_balance_date)->format('Y-m-d\TH:i')
                : null,
            'Site_Plan_Fee' => $record->Site_Plan_Fee ?? 0,
            'survey_fee' => $record->survey_fee ?? 0,
            'Processing_Fee' => $record->Processing_Fee ?? 0,
            'Betterment_Charges' => $record->Betterment_Charges ?? 0,
            'Land_Use_Charge' => $record->Land_Use_Charge ?? 0,
            'Penalty_Fees' => $record->Penalty_Fees ?? 0,
        ];
    }

    private function fetchBillingRecord(?int $billingId): ?object
    {
        if (!$billingId) {
            return null;
        }

        return DB::connection('sqlsrv')->table('billing')->where(self::BILLING_PRIMARY_KEY, $billingId)->first();
    }

    private function extractBillingRecordId(?object $record): ?int
    {
        if (!$record) {
            return null;
        }

        $value = $record->{self::BILLING_PRIMARY_KEY} ?? $record->id ?? null;

        return $value !== null ? (int) $value : null;
    }

    private function syncBillingRecord(BillBalance $billBalance, array $data): ?int
    {
        $connection = DB::connection('sqlsrv');

        $billBalanceDate = $this->normalizeDate($data['bill_balance_date'] ?? optional($billBalance->prepared_at)->format('Y-m-d H:i:s'));
        $amount = $data['amount'] ?? 0;
        $receipt = $data['bill_balance_reciept'] ?? $data['reference'];
        $refId = $billBalance->file_number ?? $data['file_number'] ?? $data['reference'];

        // Resolve THIS record's own billing row, deterministically.
        //
        // The lookup must never stray outside source = DEEDS_BILL_BALANCE: an
        // earlier version matched on ref_id/receipt alone, which let a Deeds
        // save adopt and overwrite a File Indexing billing row for the same
        // file number (billing #4694 was hijacked that way) and let two Deeds
        // records collapse onto one row. Keying on source + source_id gives
        // each record exactly one row — updated on re-save, never duplicated.
        $existing = null;
        if ($billBalance->billing_id) {
            $existing = $connection->table('billing')->where(self::BILLING_PRIMARY_KEY, $billBalance->billing_id)->first();
        }

        if (!$existing && $billBalance->id) {
            $existing = $connection->table('billing')
                ->where('source', self::BILLING_SOURCE)
                ->where('source_id', $billBalance->id)
                ->orderByDesc(self::BILLING_PRIMARY_KEY)
                ->first();
        }

        if (!$existing) {
            // Legacy rows written before source_id was populated: adopt one only
            // if it is already a Deeds row and unclaimed by another record.
            $existing = $connection->table('billing')
                ->where('source', self::BILLING_SOURCE)
                ->whereNull('source_id')
                ->where(function ($query) use ($refId, $receipt) {
                    $query->where('ref_id', $refId)
                        ->orWhere('bill_balance_reciept', $receipt);
                })
                ->orderByDesc(self::BILLING_PRIMARY_KEY)
                ->first();
        }

        $payload = [
            'ref_id' => $refId,
            'source' => self::BILLING_SOURCE,
            'source_id' => $billBalance->id,
            'Site_Plan_Fee' => $data['Site_Plan_Fee'] ?? 0,
            'survey_fee' => $data['survey_fee'] ?? 0,
            'Processing_Fee' => $data['Processing_Fee'] ?? 0,
            'Betterment_Charges' => $data['Betterment_Charges'] ?? 0,
            'Land_Use_Charge' => $data['Land_Use_Charge'] ?? 0,
            'Penalty_Fees' => $data['Penalty_Fees'] ?? 0,
            'Payment_Status' => $existing->Payment_Status ?? 'Pending',
            'bill_balance_reciept' => $receipt,
            'bill_balance_date' => $billBalanceDate,
            'updated_at' => Carbon::now(),
        ];

        if ($existing) {
            $existingId = $this->extractBillingRecordId($existing);

            $connection->table('billing')
                ->where(self::BILLING_PRIMARY_KEY, $existingId)
                ->update($payload);

            return $existingId;
        }

        $insertPayload = array_merge($this->baseBillingPayload(), $payload, [
            'created_at' => Carbon::now(),
        ]);

        return (int) $connection->table('billing')->insertGetId($insertPayload, self::BILLING_PRIMARY_KEY);
    }

    private function normalizeDate(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        return Carbon::parse($value)->format('Y-m-d H:i:s');
    }

    private function baseBillingPayload(): array
    {
        return [
            'Site_Plan_Fee' => 0,
            'survey_fee' => 0,
            'Processing_Fee' => 0,
            'Betterment_Charges' => 0,
            'Unit_Application_Fees' => 0,
            'Land_Use_Charge' => 0,
            'property_value' => 0,
            'Penalty_Fees' => 0,
            'betterment_rate' => 0,
            'Betterment_receipt' => null,
            'receipt_date' => null,
        ];
    }

    private function generateReference(?int $year = null): string
    {
        $year = $year ?: (int) now()->year;
        $prefix = sprintf('DBBL-%d-', $year);
        $table = (new BillBalance())->getTable();

        $lastReference = DB::connection('sqlsrv')
            ->table($table)
            ->where('reference', 'like', $prefix . '%')
            ->orderByDesc('reference')
            ->value('reference');

        $nextSerial = 1;
        if ($lastReference && preg_match('/^DBBL-\d{4}-(\d{5})$/', $lastReference, $matches)) {
            $nextSerial = ((int) $matches[1]) + 1;
        }

        return sprintf('DBBL-%d-%05d', $year, $nextSerial);
    }

    /**
     * Identify an ST file and return its fees mapped onto this form's
     * Fees & Charges fields, so a Sectional Titling file does not have to be
     * re-costed by hand. Read-only.
     */
    public function stFees(Request $request, \App\Services\StBillBalanceResolver $resolver)
    {
        $fileNumber = trim((string) $request->get('file_number', ''));

        if ($fileNumber === '') {
            return response()->json([
                'success' => false,
                'message' => 'File number is required',
            ], 422);
        }

        try {
            return response()->json([
                'success' => true,
                'data'    => $resolver->stFeeProfile($fileNumber),
            ]);
        } catch (\Throwable $e) {
            Log::warning('stFees lookup failed', [
                'file_number' => $fileNumber,
                'error'       => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Could not check the ST fee record.',
            ], 500);
        }
    }

    /**
     * Get residence address for a file number from file_indexings table
     */
    /**
     * Backfill blank address details for an ST file from the ST register.
     *
     * st_file_numbers carries the unit's own property columns; where those are
     * empty the scheme's mother application (matched on the primary np_fileno)
     * holds the location every unit of that scheme shares. Only blanks are
     * filled, so an indexing row always wins where it has a value.
     */
    private function fillFromStScheme(string $fileNumber, array $data): array
    {
        if (!preg_match('/^ST-/i', $fileNumber)) {
            return $data;
        }

        try {
            $unit = DB::connection('sqlsrv')
                ->table('st_file_numbers')
                ->where('fileno', $fileNumber)
                ->orderByDesc('id')
                ->first();

            if (!$unit) {
                return $data;
            }

            $scheme = null;
            if (!empty($unit->np_fileno)) {
                $scheme = DB::connection('sqlsrv')
                    ->table('mother_applications')
                    ->where('np_fileno', $unit->np_fileno)
                    ->orderByDesc('id')
                    ->first();
            }

            $pick = function (...$values) {
                foreach ($values as $value) {
                    if (trim((string) $value) !== '') {
                        return (string) $value;
                    }
                }

                return null;
            };

            // The buyer list is the name the unit was sold under; the register's own
            // parts are only a fallback, as its middle_name has been seen copied
            // between units of the same scheme.
            $buyerName = null;
            if (!empty($unit->buyer_list_id)) {
                $buyerName = DB::connection('sqlsrv')
                    ->table('buyer_list')
                    ->where('id', $unit->buyer_list_id)
                    ->value('buyer_name');
            }

            $sources = [
                'file_title'  => [
                    $buyerName,
                    $unit->corporate_name ?? null,
                    trim(trim((string) ($unit->first_name ?? '')) . ' ' . trim((string) ($unit->surname ?? ''))),
                ],
                'plot_number' => [$unit->property_plot_no ?? null, $scheme->property_plot_no ?? null],
                'street_name' => [$unit->property_street_name ?? null, $scheme->property_street_name ?? null],
                'district'    => [$unit->property_district ?? null, $scheme->property_district ?? null],
                'lga'         => [$unit->property_lga ?? null, $scheme->property_lga ?? null],
                'location'    => [$unit->property_address ?? null, $unit->property_district ?? null, $scheme->property_district ?? null],
            ];

            foreach ($sources as $key => $candidates) {
                if (trim((string) ($data[$key] ?? '')) === '') {
                    $data[$key] = $pick(...$candidates);
                }
            }

            return $data;
        } catch (\Throwable $e) {
            Log::warning('fillFromStScheme failed', [
                'file_number' => $fileNumber,
                'error' => $e->getMessage(),
            ]);

            return $data;
        }
    }

    public function getResidenceAddress(Request $request)
    {
        $fileNumber = trim((string) $request->get('file_number', ''));

        if (empty($fileNumber)) {
            return response()->json([
                'success' => false,
                'message' => 'File number is required'
            ], 422);
        }

        try {
            // Query file_indexings table for address and location details
            $record = DB::connection('sqlsrv')
                ->table('file_indexings')
                ->select('residence_address', 'file_title', 'plot_number', 'street_name', 'district', 'lga', 'location')
                ->where('file_number', $fileNumber)
                ->first();

            $data = [
                'residence_address' => $record->residence_address ?? null,
                'file_title'        => $record->file_title ?? null,
                'plot_number'       => $record->plot_number ?? null,
                'street_name'       => $record->street_name ?? null,
                'district'          => $record->district ?? null,
                'lga'               => $record->lga ?? null,
                'location'          => $record->location ?? null,
            ];

            // ST units are indexed late or not at all -- 35 of the 137 ST file
            // numbers have no file_indexings row, and 18 more have one with no LGA --
            // so fall back to the ST register and its scheme, which carry the
            // property location from the day the unit is commissioned.
            $data = $this->fillFromStScheme($fileNumber, $data);

            if (!$record && !array_filter($data, fn($value) => trim((string) $value) !== '')) {
                return response()->json([
                    'success' => false,
                    'message' => 'No file indexing record found for this file number'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            Log::error('getResidenceAddress failed', [
                'file_number' => $fileNumber,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching residence address'
            ], 500);
        }
    }
}
