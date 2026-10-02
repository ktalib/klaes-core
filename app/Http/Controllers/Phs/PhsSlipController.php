<?php

namespace App\Http\Controllers\Phs;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Phs\Concerns\ResolvesDirectorDeedsSignature;
use App\Services\LegalSearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PhsSlipController extends Controller
{
    use ResolvesDirectorDeedsSignature;

    protected LegalSearchService $searchService;

    public function __construct(LegalSearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    /**
     * JSON payload for the search slip (reuses the shared report engine).
     */
    public function data(Request $request)
    {
        $result = $this->searchService->buildPrintReport($request->query());
        return response()->json($result['payload'], $result['status']);
    }

    /**
     * Server-rendered certified search slip (printable).
     */
    public function print(Request $request)
    {
        $member = Auth::guard('phs')->user();
        $institution = $member->institution;

        $result = $this->searchService->buildPrintReport($request->query());

        if (($result['status'] ?? 500) !== 200) {
            abort(404, $result['payload']['message'] ?? 'No records found for this file.');
        }

        return view('phs.print.slip', [
            'data' => $result['payload']['data'],
            'institution' => $institution,
            'member' => $member,
            'reference_no' => trim((string) $request->query('reference_no', '')),
            'authorizedSignatureSrc' => $this->directorDeedsSignature(),
        ]);
    }
}
