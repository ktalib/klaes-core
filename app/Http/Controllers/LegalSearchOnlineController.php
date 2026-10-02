<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Models\LegalSearchOnlinePayment;
use App\Services\LegalSearchService;

class LegalSearchOnlineController extends Controller
{
    protected LegalSearchService $searchService;

    public function __construct(LegalSearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    public function index()
    {
        $districtOptions = DB::connection('sqlsrv')->table('districts')->pluck('name')->toArray();

        $pending = session('legal_search_pending');
        // Clear it once read so it doesn't re-trigger on refresh
        if ($pending) {
            session()->forget('legal_search_pending');
        }

        $config = [
            'paystackPublicKey' => config('services.paystack.public'),
            'paymentAmount'     => 100000, // kobo (₦1,000)
            'searchUrl'         => route('legalsearch.online.search'),
            'verifyUrl'         => route('legal_search.online.payment.verify'),
            'authCheckUrl'      => route('legal_search.online.auth.check'),
            'pendUrl'           => route('legal_search.online.auth.pend'),
            'isAuthenticated'   => auth()->check(),
            // The Online Legal Search portal is now fully public (no accounts);
            // point would-be sign-in/register actions at the public portal.
            'loginUrl'          => route('ols.landing'),
            'registerUrl'       => route('ols.landing'),
            'pendingFile'       => $pending['file_number'] ?? null,
            'pendingQuery'      => $pending['query'] ?? null,
        ];

        return view('legal_search_online.index', compact('districtOptions', 'config'));
    }

    /**
     * Check if the current user is authenticated (for JS-side gating).
     */
    public function authCheck(Request $request)
    {
        return response()->json([
            'authenticated' => auth()->check(),
            'user' => auth()->check() ? [
                'id'    => auth()->id(),
                'name'  => auth()->user()->name ?? auth()->user()->full_name ?? '',
                'email' => auth()->user()->email ?? '',
            ] : null,
        ]);
    }

    /**
     * Store the pending search context in session before redirecting to login.
     */
    public function pendSearch(Request $request)
    {
        $request->validate(['file_number' => 'required|string|max:100']);

        $backUrl = route('legal_search.online');

        // Store the file number the user was trying to view so we can
        // re-open it after they log in / register and pay.
        session([
            'legal_search_pending' => [
                'file_number' => trim($request->input('file_number')),
                'query'       => $request->input('query', ''),
                'redirect_to' => $backUrl,
            ],
            // Tell Laravel's redirect()->intended() where to go after login
            'url.intended' => $backUrl,
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Public search — no auth logging, just returns results.
     */
    public function publicSearch(Request $request)
    {
        $results = $this->searchService->search($request->all());
        return response()->json($results);
    }

    /**
     * Verify a Paystack payment reference and record the transaction.
     */
    public function verifyPayment(Request $request)
    {
        $request->validate(['reference' => 'required|string|max:100']);

        $reference     = trim($request->input('reference'));
        $email         = $request->input('email', '');
        $fileNumber    = $request->input('file_number', '');

        // Idempotent check
        $existing = LegalSearchOnlinePayment::where('reference', $reference)->first();
        if ($existing && $existing->isPaid()) {
            return response()->json(['success' => true, 'already_paid' => true]);
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

        LegalSearchOnlinePayment::updateOrCreate(
            ['reference' => $reference],
            [
                'user_id'     => auth()->id() ?? null,
                'email'       => $customerEmail,
                'file_number' => $fileNumber,
                'amount'      => $amountPaid,
                'status'      => 'paid',
                'paid_at'     => now(),
            ]
        );

        return response()->json(['success' => true, 'reference' => $reference]);
    }
}
