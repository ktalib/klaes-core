<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyCoFile;
use App\Models\Survey\SurveyProject;
use App\Models\Survey\SurveyProjectSurveyor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Allocation Records (Compensation > Records): the portions each surveyor was
 * allocated on each project, and how many have been surveyed on a case. Read-only;
 * allocations are set on the project form.
 */
class AllocationRecordController extends Controller
{
    /** Surveyor Allocations tab hidden "for now" (user, 2026-10-28); true brings it back. */
    public const SHOW_SURVEYOR_TAB = false;

    public function index(Request $r)
    {
        if (! self::SHOW_SURVEYOR_TAB || $r->query('tab') === 'direct') {
            return $this->directAllocation($r);
        }

        $q = SurveyProjectSurveyor::query()
            ->with(['project.coFile'])
            ->withCount(['cases as surveyed' => fn ($c) => $c->whereNotNull('portion_no')])
            ->whereHas('project');

        if ($term = trim((string) $r->query('q', ''))) {
            $q->where(function ($w) use ($term) {
                $w->where('surveyor_name', 'like', "%$term%")
                  ->orWhereHas('project', fn ($p) => $p->where('name', 'like', "%$term%")
                                                       ->orWhere('project_code', 'like', "%$term%"));
            });
        }
        if ($p = $r->query('project')) $q->where('survey_project_id', $p);
        if ($co = $r->query('co_file')) $q->whereHas('project', fn ($p) => $p->where('survey_co_file_id', $co));

        $rows = $q->get()
            ->each(fn ($a) => $a->surveyed = (int) $a->surveyed)   // sqlsrv returns counts as strings
            ->sortBy(fn ($a) => [$a->project->project_code, mb_strtolower($a->surveyor_name)])->values();

        // Progress is derived per row, so the filter runs after counting.
        if ($prog = $r->query('progress')) {
            $rows = $rows->filter(fn ($a) => match ($prog) {
                'not-started' => $a->surveyed === 0,
                'in-progress' => $a->surveyed > 0 && $a->surveyed < $a->portions,
                'complete'    => $a->surveyed >= $a->portions,
                default       => true,
            })->values();
        }

        $stats = [
            'allocations' => $rows->count(),
            'portions'    => $rows->sum('portions'),
            'surveyed'    => $rows->sum('surveyed'),
            'size'        => $rows->sum(fn ($a) => $a->total_size_ha),
        ];

        $page = max(1, (int) $r->query('page', 1));
        $paged = new \Illuminate\Pagination\LengthAwarePaginator(
            $rows->forPage($page, 15)->values(), $rows->count(), 15, $page,
            ['path' => $r->url(), 'query' => $r->query()]
        );

        return view('survey_module.compensation.allocation_records', [
            'allocations' => $paged,
            'stats'       => $stats,
            'projects'    => SurveyProject::orderByDesc('id')->get(['id', 'project_code', 'name']),
            'coFiles'     => SurveyCoFile::orderByDesc('id')->get(['id', 'co_file_no', 'title']),
        ]);
    }

    /**
     * Direct Allocation files (user, 2026-10-28): every indexed file that is a Direct
     * Allocation, of any year — its Occupancy Permit Type in File Indexing says so
     * (pra.op_type), or it was commissioned as one (mls_file_no.source, 2026 onwards).
     * Joined on the file-number string; "OP " prefix tolerated. Year is a table filter:
     * the OP date's year, else the commissioning year. Read-only; nothing is copied.
     */
    private function directAllocation(Request $r)
    {
        $db = DB::connection('sqlsrv');
        $types = "('Direct Allocation', 'OP Direct Allocation')";
        $rows = $db->table(DB::raw("(
                SELECT fi.id, fi.file_number, fi.file_title, fi.plot_number, fi.district, fi.lga, fi.location,
                       fi.land_use_type, COALESCE(NULLIF(fi.tp_no, ''), m.tp_no) AS tp_no,
                       fi.is_decommissioned, fi.successor_file_no,
                       pr.transaction_date AS op_date, pr.op_serial_number, m.commissioning_date,
                       -- A mistyped OP date (0001-01-01, 22015-01-11) gives no year.
                       COALESCE(CASE WHEN pr.transaction_date LIKE '[12][09][0-9][0-9]-[01][0-9]-[0-3][0-9]'
                                      AND LEFT(pr.transaction_date, 4) BETWEEN '1900' AND CAST(YEAR(GETDATE()) AS varchar(4))
                                     THEN LEFT(pr.transaction_date, 4) END,
                                CAST(m.year AS varchar(4))) AS da_year
                FROM (SELECT p.mlsFNo AS fn FROM pra p
                       WHERE p.instrument_type = 'Occupancy Permit' AND LTRIM(RTRIM(p.op_type)) IN $types
                         AND (p.is_deleted IS NULL OR p.is_deleted = 0)
                      UNION
                      SELECT x.full_file_number FROM mls_file_no x
                       WHERE x.source IN $types AND (x.is_deleted IS NULL OR x.is_deleted = 0)) c
                JOIN file_indexings fi ON fi.file_number = c.fn
                OUTER APPLY (SELECT TOP 1 p.id, p.transaction_date, p.op_serial_number FROM pra p
                             WHERE p.mlsFNo = fi.file_number AND p.instrument_type = 'Occupancy Permit'
                               AND LTRIM(RTRIM(p.op_type)) IN $types AND (p.is_deleted IS NULL OR p.is_deleted = 0)
                             ORDER BY p.id DESC) pr
                OUTER APPLY (SELECT TOP 1 x.id, x.tp_no, x.commissioning_date, x.year FROM mls_file_no x
                             WHERE x.full_file_number = fi.file_number AND x.source IN $types
                               AND (x.is_deleted IS NULL OR x.is_deleted = 0)
                             ORDER BY x.id DESC) m
                WHERE (fi.is_deleted IS NULL OR fi.is_deleted = 0) AND (pr.id IS NOT NULL OR m.id IS NOT NULL)
            ) AS d"));
        $base = fn () => clone $rows;

        $q = $base();
        if ($term = trim((string) $r->query('q', ''))) {
            $q->where(function ($w) use ($term) {
                foreach (['file_number', 'file_title', 'plot_number', 'district', 'location', 'tp_no', 'op_serial_number'] as $col) {
                    $w->orWhere("d.$col", 'like', "%$term%");
                }
            });
        }
        if ($lga = $r->query('lga')) $q->where('d.lga', $lga);
        if ($use = $r->query('land_use')) $q->where('d.land_use_type', $use);
        if ($year = (int) $r->query('year')) $q->where('d.da_year', (string) $year);

        $files = $q->select('d.*')
            ->orderByDesc('d.da_year')->orderByDesc('d.id')
            ->paginate(20)->withQueryString();

        return view('survey_module.compensation.allocation_records', [
            'tab'      => 'direct',
            'files'    => $files,
            'stats'    => ['files' => $base()->count()],
            'lgas'     => $base()->whereNotNull('d.lga')->where('d.lga', '<>', '')->distinct()->orderBy('d.lga')->pluck('d.lga'),
            'landUses' => $base()->whereNotNull('d.land_use_type')->where('d.land_use_type', '<>', '')->distinct()->orderBy('d.land_use_type')->pluck('d.land_use_type'),
            'years'    => $base()->whereNotNull('d.da_year')->distinct()->orderByDesc('d.da_year')->pluck('d.da_year'),
        ]);
    }
}
