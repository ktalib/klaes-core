<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RelatedFileNumberController extends Controller
{
    private const PER_PAGE_DEFAULT = 50;
    private const PER_PAGE_MAX = 200;

    /**
     * Noise patterns in related_fileno that indicate the value is not a clean
     * single file number — annotations like "NOW WITHDRAW", ampersand-joined
     * lists ("X & Y"), or free-form commentary. These rows stay in the staging
     * table but are hidden from the UI listing.
     */
    private const NOISE_PATTERNS = [
        '%WITHDRAW%',
        '% & %',
        '% NOW %',
        '% AND %',
        '% NEW %',
        '%REGISTERED%',
        '%REGISERED%',
        '% TO %',
        '%PLOT%',
        '%18TH JUNE 2007%',
        '%REVOKED%',
        '%REA-%',
        '158%',
        '%RES-19-%',
        '%RES-RC-86-21-KNML03649%',
    ];

    public function index(): View
    {
        $totals = DB::connection('sqlsrv')->selectOne(
            $this->buildTotalsCountSql()
        );

        return view('related_file_number.index', [
            'pageTitle' => 'Related File Numbers',
            'totals' => $totals,
        ]);
    }

    /**
     * Related and Temporary file numbers for the files on one table page, for the
     * expandable rows (Change Request 22102026, items 4-6). Keyed by file number;
     * files with neither are left out, so their rows get no chevron.
     */
    public function expand(Request $request, \App\Services\RelatedTempFileLookup $lookup): JsonResponse
    {
        // details[]: the row details for an expanded row's own table.
        if ($request->has('details')) {
            $numbers = array_slice(array_filter((array) $request->input('details', []), 'is_string'), 0, \App\Services\RelatedTempFileLookup::MAX_FILES);

            return response()->json(['success' => true, 'data' => (object) $lookup->details($numbers)]);
        }

        $files = array_slice(array_filter((array) $request->input('files', []), 'is_string'), 0, \App\Services\RelatedTempFileLookup::MAX_FILES);

        return response()->json(['success' => true, 'data' => (object) $lookup->forFiles($files)]);
    }

    public function apiList(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->input('per_page', self::PER_PAGE_DEFAULT), self::PER_PAGE_MAX));
        $page = max(1, (int) $request->input('page', 1));

        $search = trim((string) $request->input('search', ''));
        $commentType = trim((string) $request->input('comment_type', '')); // kangis | mlpp | none | any

        $q = DB::connection('sqlsrv')->table('related_file_number')
            ->where('related_fileno', 'not like', 'TEMP-%')
            ->where('related_fileno', 'not like', 'TEMP%')
            ->whereNotIn('source_table', ['pic', 'dciv_file_no'])
            // Parent file_number must exist (was the "if the rfn doesn't have
            // a pfn it should not display" requirement).
            ->whereNotNull('file_number')
            ->whereRaw("LTRIM(RTRIM(file_number)) <> ''")
            // Drop junk related_filenos:
            //   * shorter than 3 chars (e.g. "Q")
            //   * purely numeric (e.g. "652", "1234")
            //   * TP-* prefix (Town Planning, not a registry file)
            ->whereRaw("LEN(LTRIM(RTRIM(related_fileno))) >= 3")
            ->whereRaw("related_fileno LIKE '%[^0-9]%'")  // contains at least one non-digit
            ->where('related_fileno', 'not like', 'TP-%')
            ->where('related_fileno', 'not like', 'TP %');

        // Hide rows where related_fileno carries noise / annotations
        foreach (self::NOISE_PATTERNS as $pattern) {
            $q->where('related_fileno', 'not like', $pattern);
        }

        if ($search !== '') {
            $q->where(function ($w) use ($search) {
                $w->where('related_fileno', 'like', '%' . $search . '%')
                  ->orWhere('file_number', 'like', '%' . $search . '%')
                  ->orWhere('file_title', 'like', '%' . $search . '%')
                  ->orWhere('party_2', 'like', '%' . $search . '%')
                  ->orWhere('comment', 'like', '%' . $search . '%');
            });
        }

        switch ($commentType) {
            case 'kangis':
                $q->where('transaction_type', 'KANGIS Recertification');
                break;
            case 'mlpp':
                // Two spellings exist in the wild: the register rebuild writes
                // "Ministry of Land & Physical Planning Recertification" (852 rows) while
                // MlsFileNoController writes the short "Land & Physical Planning
                // Recertification". Match the common suffix so both are covered.
                $q->where('transaction_type', 'like', '%Physical Planning Recertification');
                break;
            case 'merger':
                $q->where('transaction_type', 'Merger');
                break;
            case 'subdivision':
                $q->where('transaction_type', 'Subdivision');
                break;
            case 'change_of_purpose':
                $q->where('transaction_type', 'Change of Purpose');
                break;
            case 'none':
                $q->whereNull('transaction_type');
                break;
            // 'any' or '' = no filter
        }

        // One row per parent file (Change Request 22102026, items 4-6): the newest link
        // is shown, and the row expands to list the parent's other related files and
        // any temporary file. Paginated over parents, so S/N counts files, not links.
        $total = (clone $q)->distinct()->count('file_number');

        $groups = (clone $q)
            ->selectRaw('file_number, MAX(id) as mid, COUNT(*) as n')
            ->groupBy('file_number')
            ->orderByDesc('mid')
            ->forPage($page, $perPage)
            ->get();
        $groupCounts = $groups->pluck('n', 'mid');

        $rows = DB::connection('sqlsrv')->table('related_file_number')
            ->whereIn('id', $groups->pluck('mid')->all() ?: [0])
            ->orderByDesc('id')
            ->get([
                'id', 'related_fileno', 'prop_id', 'source_table', 'source_id',
                'file_number', 'file_title', 'party_2', 'location', 'comment',
                'transaction_type', 'created_at',
            ]);
        foreach ($rows as $row) {
            $row->group_count = (int) ($groupCounts[$row->id] ?? 1);
        }

        // Look up the title for each related_fileno (by matching it against
        // file_indexings.file_number, falling back to fileNumber tables).
        // One batched query for the entire page.
        $relatedFilenos = $rows->pluck('related_fileno')
            ->map(fn ($v) => trim((string) $v))
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $relatedTitleMap = [];
        if (!empty($relatedFilenos)) {
            $conn = DB::connection('sqlsrv');

            // file_indexings: file_number -> file_title
            $fi = $conn->table('file_indexings')
                ->whereIn('file_number', $relatedFilenos)
                ->whereNull('deleted_at')
                ->whereNotNull('file_title')
                ->select('file_number', 'file_title')
                ->get();
            foreach ($fi as $r) {
                $relatedTitleMap[trim((string) $r->file_number)] = $r->file_title;
            }

            // Fallback: fileNumber -> FileName (only for rfns not yet matched)
            $unmatched = array_values(array_diff($relatedFilenos, array_keys($relatedTitleMap)));
            if (!empty($unmatched)) {
                $fn = $conn->table('fileNumber')
                    ->where(function ($q) use ($unmatched) {
                        $q->whereIn('mlsfNo', $unmatched)
                          ->orWhereIn('kangisFileNo', $unmatched)
                          ->orWhereIn('NewKANGISFileNo', $unmatched);
                    })
                    ->whereNotNull('FileName')
                    ->select('mlsfNo', 'kangisFileNo', 'NewKANGISFileNo', 'FileName')
                    ->get();
                foreach ($fn as $r) {
                    foreach (['mlsfNo', 'kangisFileNo', 'NewKANGISFileNo'] as $col) {
                        $val = trim((string) ($r->{$col} ?? ''));
                        if ($val !== '' && in_array($val, $unmatched, true) && ! isset($relatedTitleMap[$val])) {
                            $relatedTitleMap[$val] = $r->FileName;
                        }
                    }
                }
            }
        }

        // Attach related_file_title to each row
        foreach ($rows as $row) {
            $key = trim((string) ($row->related_fileno ?? ''));
            $row->related_file_title = $relatedTitleMap[$key] ?? null;
        }

        return response()->json([
            'data' => $rows,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ]);
    }

    /**
     * Build the SQL for the index header's Total Rows tile — must match the
     * same exclusion set the API uses, so the count and the list agree.
     */
    private function buildTotalsCountSql(): string
    {
        $where = "related_fileno NOT LIKE 'TEMP-%'"
              . " AND related_fileno NOT LIKE 'TEMP%'"
              . " AND source_table NOT IN ('pic','dciv_file_no')"
              . " AND file_number IS NOT NULL AND LTRIM(RTRIM(file_number)) <> ''"
              . " AND LEN(LTRIM(RTRIM(related_fileno))) >= 3"
              . " AND related_fileno LIKE '%[^0-9]%'"
              . " AND related_fileno NOT LIKE 'TP-%'"
              . " AND related_fileno NOT LIKE 'TP %'";
        foreach (self::NOISE_PATTERNS as $pattern) {
            $where .= " AND related_fileno NOT LIKE " . "'" . str_replace("'", "''", $pattern) . "'";
        }
        return "SELECT COUNT(*) AS total_rows FROM related_file_number WHERE {$where}";
    }
}
