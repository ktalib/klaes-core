<?php

namespace App\Http\Controllers\Phs;

use App\Http\Controllers\Controller;
use App\Models\Phs\PhsSearchLog;
use App\Services\LegalSearchService;
use App\Services\Phs\PhsEditRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PhsDashboardController extends Controller
{
    protected LegalSearchService $searchService;
    protected PhsEditRequestService $editRequests;

    public function __construct(
        LegalSearchService $searchService,
        PhsEditRequestService $editRequests
    ) {
        $this->searchService = $searchService;
        $this->editRequests = $editRequests;
    }

    public function index()
    {
        $member = Auth::guard('phs')->user();
        $institution = $member->institution;

        $searchLogs = PhsSearchLog::where('phs_institution_id', $institution->id);

        $stats = [
            'token_balance' => $member->isSuperAdmin() ? (int) $institution->token_balance : (int) $member->allocated_tokens,
            'total_searches' => (clone $searchLogs)->count(),
            'searches_this_month' => (clone $searchLogs)->where('created_at', '>=', now()->startOfMonth())->count(),
            'member_count' => $institution->members()->count(),
        ];

        $recentSearches = PhsSearchLog::with('member')
            ->where('phs_institution_id', $institution->id)
            ->latest()
            ->limit(5)
            ->get();

        return view('phs.dashboard.index', [
            'member' => $member,
            'institution' => $institution,
            'packages' => PhsTokenController::packages(),
            'stats' => $stats,
            'recentSearches' => $recentSearches,
        ]);
    }

    /**
     * Run a property history search. Consumes 1 token per search.
     */
    public function search(Request $request)
    {
        $member = Auth::guard('phs')->user();
        $institution = $member->institution;

        if (!$member->canSearch()) {
            return response()->json([
                'success' => false,
                'message' => 'Your role does not permit running searches.',
            ], 403);
        }

        $query = trim((string) $request->input('query', ''));
        if ($query === '') {
            return response()->json([
                'success' => false,
                'message' => 'Please enter a file number, KANGIS number, owner or plot number.',
            ], 422);
        }

        // Pre-flight: reject immediately if the member has no tokens at all (avoids
        // a wasted search query when the balance is already zero).
        $currentBalance = $member->isSuperAdmin()
            ? (int) $institution->token_balance
            : (int) $member->allocated_tokens;

        // A correction returned by the PHS-P Admin entitles this member to ONE
        // re-run of THAT file at no charge. Resolved before the balance check so
        // an entitled member with an empty wallet can still collect the re-run
        // they are owed - refusing them here would be charging them for our own
        // bad result.
        $freeRerun = $this->editRequests->findAuthorisation($member, $query);

        if (!$freeRerun && $currentBalance < 1) {
            return response()->json([
                'success' => false,
                'insufficient_tokens' => true,
                'message' => $member->isSuperAdmin()
                    ? 'Insufficient organization tokens. Please purchase more to continue.'
                    : 'You have no tokens left. Please contact your organization administrator for more.',
                'token_balance' => $currentBalance,
            ], 422);
        }

        // Run the search BEFORE deducting — tokens are only spent when results exist.
        $results = $this->searchService->search(['query' => $query]);
        $transactions = $results['transactions'] ?? [];

        // No real transactions found. A commissioned/indexed file still has a
        // File Commissioning event (and a Temporary File row for a "(T)"), so
        // mirror the main Legal Search: show the default file info + synthetic
        // commissioning rows instead of "No Results Found". buildPrintReport()
        // renders those rows whenever the file exists, and returns 404 only when
        // the file is genuinely unknown. No token is charged here — there is no
        // real transaction history to pay for.
        if (empty($transactions)) {
            $resolvedFileNo = $results['file_index_number'] ?? $query;
            $reportData = [];
            try {
                $report = $this->searchService->buildPrintReport(['file_number' => $resolvedFileNo]);
                if (($report['status'] ?? null) === 200 && !empty($report['payload']['data']['rows'])) {
                    $reportData = $report['payload']['data'];
                }
            } catch (\Throwable $e) {
                report($e);
            }

            $defaultRows = $reportData['rows'] ?? [];

            PhsSearchLog::create([
                'phs_institution_id' => $institution->id,
                'phs_member_id' => $member->id,
                'query' => Str::limit($query, 250),
                'file_number' => $resolvedFileNo,
                'result_count' => count($defaultRows),
                'reference_no' => null,
                'tokens_used' => 0,
            ]);

            return response()->json([
                'success' => true,
                'reference_no' => null,
                'token_balance' => $currentBalance,
                'transactions' => $defaultRows,
                'file_title' => $results['file_title'] ?? ($reportData['file_title'] ?? null),
                'file_district' => $results['file_district'] ?? null,
                'file_lga' => $results['file_lga'] ?? null,
                'file_land_use' => $results['file_land_use'] ?? ($reportData['land_use'] ?? null),
                'file_plot_number' => $results['file_plot_number'] ?? ($reportData['plot_no'] ?? null),
                'file_tp_no' => $results['file_tp_no'] ?? ($reportData['tpno'] ?? null),
                'file_size' => $results['file_size'] ?? ($reportData['size'] ?? null),
                'file_index_number' => $resolvedFileNo,
                'total_count' => count($defaultRows),
                'no_charge' => true,
                // Report notices (parity with the main LS remarks).
                'caveat_note' => $reportData['caveat_note'] ?? null,
                'is_caveated' => $reportData['is_caveated'] ?? false,
                'under_investigation' => $reportData['under_investigation'] ?? false,
                'ground_rent' => $reportData['ground_rent'] ?? null,
                'no_cofo_comment' => $reportData['no_cofo_comment'] ?? null,
                'encumbrance_comment' => $reportData['encumbrance_comment'] ?? null,
                'litigation_comment' => $reportData['litigation_comment'] ?? null,
                'wrc_comment' => $reportData['wrc_comment'] ?? null,
                'cofo_comment' => $reportData['cofo_comment'] ?? null,
            ]);
        }

        // Results found — now deduct 1 token.
        $reference = 'PHS/' . now()->format('Y') . '/' . strtoupper(Str::random(6));

        // Authorised free re-run: charge nothing, and record why. The
        // authorisation is consumed further down, once the search log exists to
        // point at - consuming it here would spend it even if the search failed.
        if ($freeRerun) {
            $debited = false;
        } elseif ($member->isSuperAdmin()) {
            $debit = $institution->deductTokens(1, $member->id, [
                'reference_no' => $reference,
                'notes' => 'Search: ' . Str::limit($query, 100),
            ]);
            $debited = $debit !== null;
        } else {
            $debited = DB::connection('sqlsrv')->table('phs_members')
                ->where('id', $member->id)
                ->where('allocated_tokens', '>=', 1)
                ->update([
                    'allocated_tokens' => DB::raw('allocated_tokens - 1'),
                    'tokens_used' => DB::raw('tokens_used + 1'),
                ]) > 0;
        }

        // Edge case: balance was consumed by a concurrent request between the
        // pre-flight check and the actual deduction. Return results anyway — the
        // search already ran and the user should see what they asked for.
        if ($debited && $member->isSuperAdmin()) {
            $member->increment('tokens_used');
        }
        $member->refresh();
        $institution->refresh();

        // Build the certified-slip report so the on-screen Property Timeline shows
        // the exact same LS-weighed set the printed slip will (deduplicated,
        // scored, and ordered). Falls back to the raw transactions if the report
        // engine can't produce rows for any reason.
        $resolvedFileNo = $results['file_index_number'] ?? $query;
        $timeline = $transactions;
        $reportData = [];
        try {
            $report = $this->searchService->buildPrintReport(['file_number' => $resolvedFileNo]);
            if (($report['status'] ?? null) === 200 && !empty($report['payload']['data'])) {
                $reportData = $report['payload']['data'];
                if (!empty($reportData['rows'])) {
                    $timeline = $reportData['rows'];
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $searchLog = PhsSearchLog::create([
            'phs_institution_id' => $institution->id,
            'phs_member_id' => $member->id,
            'query' => Str::limit($query, 250),
            'file_number' => $resolvedFileNo,
            'result_count' => count($timeline),
            'reference_no' => $reference,
            'tokens_used' => $debited ? 1 : 0,
        ]);

        // Spend the free-re-run authorisation, pointing it at the log row that
        // proves the re-run happened. consume() is conditional on the row still
        // being unspent, so two concurrent re-runs cannot both come out free -
        // if this one loses the race it is charged like any other search.
        $rerunApplied = false;
        if ($freeRerun) {
            $rerunApplied = $this->editRequests->consume($freeRerun, $searchLog->id, $member);

            if (!$rerunApplied) {
                $searchLog->forceFill(['tokens_used' => 1])->save();
                $debited = $member->isSuperAdmin()
                    ? $institution->deductTokens(1, $member->id, [
                        'reference_no' => $reference,
                        'notes' => 'Search: ' . Str::limit($query, 100),
                    ]) !== null
                    : DB::connection('sqlsrv')->table('phs_members')
                        ->where('id', $member->id)
                        ->where('allocated_tokens', '>=', 1)
                        ->update([
                            'allocated_tokens' => DB::raw('allocated_tokens - 1'),
                            'tokens_used' => DB::raw('tokens_used + 1'),
                        ]) > 0;
                $member->refresh();
                $institution->refresh();
            }
        }

        return response()->json([
            'success' => true,
            'reference_no' => $reference,
            'token_balance' => $member->isSuperAdmin() ? (int) $institution->token_balance : (int) $member->allocated_tokens,
            // Set when this search was the free re-run of a corrected result, so
            // the portal can say so instead of leaving the member to wonder
            // whether they were charged.
            'free_rerun' => $rerunApplied,
            'free_rerun_reference' => $rerunApplied ? ($freeRerun->reference_no ?? null) : null,
            'transactions' => $timeline,
            'file_title' => $results['file_title'] ?? null,
            'file_district' => $results['file_district'] ?? null,
            'file_lga' => $results['file_lga'] ?? null,
            'file_land_use' => $results['file_land_use'] ?? null,
            'file_plot_number' => $results['file_plot_number'] ?? null,
            'file_tp_no' => $results['file_tp_no'] ?? null,
            'file_size' => $results['file_size'] ?? null,
            'file_index_number' => $results['file_index_number'] ?? null,
            'total_count' => count($timeline),
            // Report notices — mirror the main Legal Search remarks so the portal
            // (and the certified slip) surface the same caveat / W-R-C / CoFO /
            // ground-rent / litigation / encumbrance information.
            'caveat_note' => $reportData['caveat_note'] ?? null,
            'is_caveated' => $reportData['is_caveated'] ?? false,
            'under_investigation' => $reportData['under_investigation'] ?? false,
            'ground_rent' => $reportData['ground_rent'] ?? null,
            'no_cofo_comment' => $reportData['no_cofo_comment'] ?? null,
            'encumbrance_comment' => $reportData['encumbrance_comment'] ?? null,
            'litigation_comment' => $reportData['litigation_comment'] ?? null,
            'wrc_comment' => $reportData['wrc_comment'] ?? null,
            'cofo_comment' => $reportData['cofo_comment'] ?? null,
        ]);
    }
}
