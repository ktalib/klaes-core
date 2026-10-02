<?php

namespace App\Http\Controllers\OnlineLegalSearch;

use App\Http\Controllers\Controller;
use App\Models\LegalSearchOnlinePayment;
use App\Services\LegalSearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class OnlineLsDashboardController extends Controller
{
    protected LegalSearchService $searchService;

    public function __construct(LegalSearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    /**
     * Flat fee charged per full Legal Search result, in kobo (₦10,000).
     */
    public const PAYMENT_AMOUNT_KOBO = 1000000;

    /**
     * Public landing page. No accounts — anyone can search, pay and view a report.
     */
    public function landing()
    {
        // Lookup options for the advanced-search District / LGA dropdowns.
        $districtOptions = DB::connection('sqlsrv')->table('districts')
            ->whereNotNull('name')
            ->where('name', '<>', '')
            ->orderBy('name')
            ->pluck('name')
            ->unique()
            ->values();

        $lgaOptions = DB::connection('sqlsrv')->table('lgas')
            ->where('is_active', 1)
            ->whereNotNull('name')
            ->where('name', '<>', '')
            ->orderBy('name')
            ->pluck('name')
            ->unique()
            ->values();

        return view('online_legal_search.landing', compact('districtOptions', 'lgaOptions'));
    }

    /**
     * Normalize the incoming request into the parameter keys that
     * LegalSearchService::search() understands.
     */
    protected function mapSearchParams(Request $request): array
    {
        // Accept both the template's `query`/`file_number` plus the advanced fields.
        $query = trim((string) ($request->input('query') ?: $request->input('file_number') ?: ''));

        return array_filter([
            'query'         => $query,
            'guarantorName' => trim((string) $request->input('guarantorName', '')),
            'guaranteeName' => trim((string) $request->input('guaranteeName', '')),
            'lga'           => trim((string) $request->input('lga', '')),
            'district'      => trim((string) $request->input('district', '')),
            'location'      => trim((string) $request->input('location', '')),
            'plotNumber'    => trim((string) $request->input('plotNumber', '')),
            'planNumber'    => trim((string) $request->input('planNumber', '')),
            'size'          => trim((string) $request->input('size', '')),
            'caveat'        => trim((string) $request->input('caveat', '')),
        ], fn ($v) => $v !== '');
    }

    /**
     * Public legal search — no login required. Returns a preview/summary only.
     * The full report (transactions) is gated behind payment via result().
     */
    public function search(Request $request)
    {
        $params  = $this->mapSearchParams($request);
        $results = $this->searchService->search($params);

        $totalCount   = (int) ($results['total_count'] ?? 0);
        $transactions = $results['transactions'] ?? [];

        // Determine caveat status server-side without exposing the transaction rows.
        $isCaveated = false;
        foreach ($transactions as $tx) {
            if (strtolower(trim((string) ($tx['caveat'] ?? ''))) === 'yes') {
                $isCaveated = true;
                break;
            }
        }

        $fileNumber = $results['file_index_number'] ?? ($params['query'] ?? '');

        // Log the search (guest — no user id).
        try {
            DB::connection('sqlsrv')->table('online_ls_search_logs')->insert([
                'online_ls_user_id' => null,
                'file_number'       => $fileNumber ?: null,
                'search_params'     => json_encode($params),
                'results_count'     => $totalCount,
                'ip_address'        => $request->ip(),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        } catch (\Throwable $e) {
            // Logging is best-effort; never block the search on a log failure.
        }

        if ($totalCount < 1) {
            // The file may be indexed yet carry no transactions. In that case still
            // surface its File Information (mirrors the main Legal Search) instead of
            // a bare "No Results Found". The shared search service only resolves the
            // file_indexings record when transactions exist, so look it up directly
            // by the searched file number here.
            $fileInfo = $this->resolveFileInfo((string) ($params['query'] ?? ''));

            return response()->json([
                'total_count' => 0,
                'has_results' => false,
                'file_info'   => $fileInfo ?: null,
            ]);
        }

        return response()->json([
            'total_count' => $totalCount,
            'has_results' => true,
            'summary'     => [
                'file_number'       => $fileNumber,
                'file_title'        => $results['file_title'] ?? null,
                'district'          => $results['file_district'] ?? null,
                'lga'               => $results['file_lga'] ?? null,
                'land_use'          => $results['file_land_use'] ?? null,
                'plot_number'       => $results['file_plot_number'] ?? null,
                'size'              => $results['file_size'] ?? null,
                'is_caveated'       => $isCaveated,
                'transaction_count' => $totalCount,
            ],
        ]);
    }

    /**
     * Resolve a file's indexed details directly from file_indexings, used to show
     * a "File Information" card when a searched file is indexed but has no
     * transactions. Returns null when nothing matches the file number.
     */
    protected function resolveFileInfo(string $fileNumber): ?array
    {
        $fileNumber = trim($fileNumber);
        if ($fileNumber === '') {
            return null;
        }

        $row = DB::connection('sqlsrv')->table('file_indexings')
            ->whereNull('deleted_at')
            ->where(function ($q) use ($fileNumber) {
                $q->where('file_number', $fileNumber)
                    ->orWhere('related_fileno', 'like', '%' . $fileNumber . '%');
            })
            ->select('file_number', 'file_title', 'district', 'lga', 'land_use_type', 'plot_number', 'tp_no', 'related_fileno', 'location')
            ->orderByRaw('CASE WHEN file_number = ? THEN 0 ELSE 1 END', [$fileNumber])
            ->first();

        if (!$row) {
            return null;
        }

        $fileInfo = array_filter([
            'file_number'    => $row->file_number ?: $fileNumber,
            'file_title'     => $row->file_title ?? null,
            'district'       => $row->district ?? null,
            'lga'            => $row->lga ?? null,
            'land_use'       => $row->land_use_type ?? null,
            'plot_number'    => $row->plot_number ?? null,
            'tp_no'          => $row->tp_no ?? null,
            'location'       => $row->location ?? null,
            'related_fileno' => $row->related_fileno ?? null,
        ], fn ($v) => $v !== null && trim((string) $v) !== '' && trim((string) $v) !== '-');

        // Only meaningful if we have more than just the bare file number.
        return count(array_diff_key($fileInfo, ['file_number' => true])) > 0 ? $fileInfo : null;
    }

    /**
     * Select2 autocomplete for file numbers, sourced from file_indexings.
     * Matches the term against file_number and returns distinct file numbers.
     */
    public function fileNumbers(Request $request)
    {
        $term = trim((string) $request->query('term', $request->query('q', '')));

        if (mb_strlen($term) < 2) {
            return response()->json(['results' => []]);
        }

        $like = '%' . $term . '%';

        $fileNumbers = DB::connection('sqlsrv')->table('file_indexings')
            ->whereNull('deleted_at')
            ->where('file_number', 'like', $like)
            ->whereNotNull('file_number')
            ->where('file_number', '<>', '')
            ->orderByDesc('id')
            ->limit(20)
            ->pluck('file_number');

        // Distinct file numbers, preserving the most-recent-first order.
        $seen = [];
        $results = [];
        foreach ($fileNumbers as $fileNumber) {
            $fileNumber = trim((string) $fileNumber);
            if ($fileNumber === '' || isset($seen[$fileNumber])) {
                continue;
            }
            $seen[$fileNumber] = true;
            $results[] = ['id' => $fileNumber, 'text' => $fileNumber];
        }

        return response()->json(['results' => $results]);
    }

    /**
     * Public full result. No login required.
     *
     * Access to the report is gated by a verified Paystack payment for the file
     * number. After paying, the front-end returns here with the payment
     * `reference`; if a matching paid record exists the full report is shown,
     * otherwise the Paystack checkout (payment mode) is rendered.
     */
    public function result(Request $request)
    {
        $fileNumber = trim((string) ($request->input('query') ?: $request->input('file_number') ?: ''));
        $reference  = trim((string) $request->input('ref', ''));

        if ($fileNumber === '') {
            return redirect()->route('ols.landing');
        }

        // A report is unlocked only by presenting the reference of a paid
        // transaction for this exact file number.
        $payment = null;
        if ($reference !== '') {
            $payment = LegalSearchOnlinePayment::where('reference', $reference)
                ->where('file_number', $fileNumber)
                ->where('status', 'paid')
                ->first();
        }

        if (!$payment) {
            // Payment mode: show the Paystack checkout for this search.
            return view('online_legal_search.result', [
                'mode'              => 'payment',
                'fileNumber'        => $fileNumber,
                'searchParams'      => $request->only(['query', 'guarantorName', 'guaranteeName', 'lga', 'district', 'location', 'plotNumber', 'planNumber', 'size', 'caveat']),
                'amount'            => self::PAYMENT_AMOUNT_KOBO,
                'paystackPublicKey' => config('services.paystack.public'),
                'report'            => null,
            ]);
        }

        // Report mode: build the full report using the canonical print engine.
        $built = $this->searchService->buildPrintReport(['file_number' => $fileNumber]);
        $report = $built['payload']['data'] ?? null;

        return view('online_legal_search.result', [
            'mode'       => 'report',
            'payment'    => $payment,
            'fileNumber' => $fileNumber,
            'report'     => $report,
        ]);
    }

    /**
     * Verify a Paystack payment reference and record the guest transaction.
     * On success a human-friendly tracking id (USER-0001) is assigned so the
     * transaction can be traced back-office without an account.
     */
    public function verifyPayment(Request $request)
    {
        $request->validate([
            'reference' => 'required|string|max:100',
            'email'     => 'nullable|email|max:255',
        ]);

        $reference  = trim($request->input('reference'));
        $email      = $request->input('email', '');
        $fileNumber = $request->input('file_number', '');

        // Idempotent check
        $existing = LegalSearchOnlinePayment::where('reference', $reference)->first();
        if ($existing && $existing->isPaid()) {
            return response()->json(['success' => true, 'already_paid' => true, 'reference' => $reference]);
        }

        // Verify with Paystack
        $verify = Http::withToken(config('services.paystack.secret'))
            ->get(config('services.paystack.base_url') . '/transaction/verify/' . $reference);

        if (!$verify->successful() || $verify->json('data.status') !== 'success') {
            if ($existing) {
                $existing->update(['status' => 'failed']);
            }
            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed. Please contact support if you were charged.',
            ], 422);
        }

        $amountPaid    = (int) $verify->json('data.amount');
        $customerEmail = $verify->json('data.customer.email') ?? $email;

        $payment = LegalSearchOnlinePayment::updateOrCreate(
            ['reference' => $reference],
            [
                'user_id'           => null,
                'online_ls_user_id' => null,
                'email'             => $customerEmail,
                'file_number'       => $fileNumber,
                'search_params'     => $request->input('search_params'),
                'amount'            => $amountPaid,
                'status'            => 'paid',
                'paid_at'           => now(),
            ]
        );

        // Assign a back-office tracking id (USER-0001) once, derived from the
        // payment's own id so it is unique and sequential.
        if (empty($payment->tracking_id)) {
            $payment->tracking_id = 'USER-' . str_pad((string) $payment->id, 4, '0', STR_PAD_LEFT);
            $payment->save();
        }

        return response()->json(['success' => true, 'reference' => $reference, 'tracking_id' => $payment->tracking_id]);
    }
}
