<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MasterDcivLinkController extends Controller
{
    private const PER_PAGE_DEFAULT = 50;
    private const PER_PAGE_MAX = 200;

    public function index(): View
    {
        $total = DB::connection('sqlsrv')
            ->table('master_dciv_links')
            ->distinct()
            ->count('dciv_file_number');

        return view('master_dciv_links.index', [
            'pageTitle' => 'DCIV Master Link Table',
            'total' => $total,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->input('per_page', self::PER_PAGE_DEFAULT), self::PER_PAGE_MAX));
        $page    = max(1, (int) $request->input('page', 1));
        $search  = trim((string) $request->input('search', ''));
        $type    = trim((string) $request->input('type', ''));

        $applyFilters = function ($q) use ($search, $type) {
            if ($search !== '') {
                $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
                $q->where(function ($w) use ($like) {
                    $w->where('dciv_file_number', 'like', $like)
                        ->orWhere('related_file_number', 'like', $like)
                        ->orWhere('related_file_title', 'like', $like)
                        ->orWhere('dciv_reason', 'like', $like);
                });
            }
            if (in_array($type, ['Land', 'SLTR', 'ST', 'Other'], true)) {
                $q->where('related_file_type', $type);
            }
        };

        // Count unique DCIVs matching the filter
        $countQ = DB::connection('sqlsrv')->table('master_dciv_links');
        $applyFilters($countQ);
        $total = $countQ->distinct()->count('dciv_file_number');

        // Get the DCIV file numbers for this page (newest by Date Created first).
        // Order by the latest created_at — backfilled rows keep their original
        // indexing date, so id-order would not reflect Date Created.
        $pageQ = DB::connection('sqlsrv')->table('master_dciv_links')
            ->select('dciv_file_number', DB::raw('MAX(created_at) AS max_created'), DB::raw('MAX(id) AS max_id'))
            ->groupBy('dciv_file_number');
        $applyFilters($pageQ);
        $dcivNos = $pageQ->orderByDesc('max_created')
            ->orderByDesc('max_id')
            ->forPage($page, $perPage)
            ->pluck('dciv_file_number');

        if ($dcivNos->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [],
                'meta' => [
                    'current_page' => $page,
                    'per_page'     => $perPage,
                    'total'        => $total,
                    'last_page'    => (int) ceil(max($total, 1) / $perPage),
                ],
            ]);
        }

        // Fetch all link rows for the current-page DCIVs, joining for the DCIV's own title
        $rows = DB::connection('sqlsrv')
            ->table('master_dciv_links')
            ->leftJoin(
                DB::raw('(SELECT file_number, MAX(file_title) AS dciv_file_title FROM file_indexings GROUP BY file_number) AS dfi'),
                DB::raw('UPPER(LTRIM(RTRIM(master_dciv_links.dciv_file_number)))'),
                '=',
                DB::raw('UPPER(LTRIM(RTRIM(dfi.file_number)))')
            )
            ->whereIn('master_dciv_links.dciv_file_number', $dcivNos)
            ->get([
                'master_dciv_links.dciv_file_number',
                'master_dciv_links.dciv_reason',
                'master_dciv_links.related_file_number',
                'master_dciv_links.related_file_type',
                'master_dciv_links.related_file_title',
                'master_dciv_links.created_at',
                'dfi.dciv_file_title',
            ]);

        $grouped = $rows->groupBy('dciv_file_number');

        // Build output preserving page order
        $data = $dcivNos->map(function ($dcivNo) use ($grouped) {
            $links = $grouped->get($dcivNo, collect());
            $first = $links->first();

            return [
                'dciv_file_number' => $dcivNo,
                'dciv_file_title'  => $first->dciv_file_title ?? null,
                'dciv_reason'      => $first->dciv_reason ?? null,
                'created_at'       => $first->created_at ?? null,
                // Standalone DCIVs are stored with a null related_file_number — skip
                // those placeholder rows so the related-files cell renders empty ("-").
                'related_files'    => $links
                    ->filter(fn ($r) => trim((string) $r->related_file_number) !== '')
                    ->map(fn ($r) => [
                        'file_number' => $r->related_file_number,
                        'type'        => $r->related_file_type,
                        'title'       => $r->related_file_title,
                    ])->values()->all(),
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $total,
                'last_page'    => (int) ceil(max($total, 1) / $perPage),
            ],
        ]);
    }
}
