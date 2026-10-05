<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class DashboardController extends Controller
{
    protected $connection = 'sqlsrv';

    public function applicationAnalytics(Request $request)
    {
        $data = $request->validate([
            'period' => 'nullable|in:week,month,quarter,year',
            'status' => 'nullable|in:all,approved,pending,rejected',
        ]);
        return response()->json(['success' => true, 'data' => app(\App\Services\DashboardApplicationAnalytics::class)
            ->get($data['period'] ?? 'week', $data['status'] ?? 'all')]);
    }

    /* ─────────────────────────────────────────────────────────────────────
     | COMBINED – single request loads all top-level stats
     | GET /api/dashboard/all-stats
     ───────────────────────────────────────────────────────────────────── */
    public function getAllStats()
    {
        $ttl = 120; // 2-minute cache

        try {
            $stats = Cache::remember('dashboard.all_stats', $ttl, function () {
                $db = DB::connection($this->connection);
                $now = Carbon::now();
                $prev30 = $now->copy()->subDays(30);
                $prev60 = $now->copy()->subDays(60);

                /* --- helpers --- */
                $trend = function ($current, $previous) {
                    if ($previous <= 0) return 0;
                    return round((($current - $previous) / $previous) * 100, 1);
                };

                $countWithTrend = function ($table, $where = []) use ($db, $now, $prev30, $prev60, $trend) {
                    $q = $db->table($table);
                    foreach ($where as $col => $val) {
                        is_array($val) ? $q->whereIn($col, $val) : $q->where($col, $val);
                    }
                    $total   = (clone $q)->count();
                    $curr30  = (clone $q)->where('created_at', '>=', $prev30)->count();
                    $prev30c = (clone $q)->whereBetween('created_at', [$prev60, $prev30])->count();
                    return [
                        'count'    => number_format($total),
                        'raw'      => $total,
                        'trend'    => $trend($curr30, $prev30c),
                    ];
                };

                /* ── Indexed files ────────────────────────────────────── */
                $indexedFiles = $countWithTrend('file_indexings');

                /* ── Blind scans ─────────────────────────────────────── */
                $blindScans = $countWithTrend('blind_scannings');

                /* ── Scan uploads ────────────────────────────────────── */
                $scanUploads = $countWithTrend('scannings');

                /* ── Total applications (mother + sub) ────────────────── */
                $motherTotal  = $db->table('mother_applications')->count();
                $subTotal     = $db->table('subapplications')->count();
                $appTotal     = $motherTotal + $subTotal;

                $motherCurr   = $db->table('mother_applications')->where('created_at', '>=', $prev30)->count();
                $subCurr      = $db->table('subapplications')->where('created_at', '>=', $prev30)->count();
                $motherPrev   = $db->table('mother_applications')->whereBetween('created_at', [$prev60, $prev30])->count();
                $subPrev      = $db->table('subapplications')->whereBetween('created_at', [$prev60, $prev30])->count();
                $appTrend     = $trend($motherCurr + $subCurr, $motherPrev + $subPrev);

                $totalApplications = [
                    'count' => number_format($appTotal),
                    'raw'   => $appTotal,
                    'trend' => $appTrend,
                ];

                /* ── Pending approvals ────────────────────────────────── */
                $pendingMother = $db->table('mother_applications')
                    ->where(function ($q) {
                        $q->whereNull('application_status')
                          ->orWhereIn('application_status', ['pending', 'Pending', 'PENDING']);
                    })->count();
                $pendingSub = $db->table('subapplications')
                    ->where(function ($q) {
                        $q->whereNull('application_status')
                          ->orWhereIn('application_status', ['pending', 'Pending', 'PENDING']);
                    })->count();

                $pendingApprovals = [
                    'count' => number_format($pendingMother + $pendingSub),
                    'raw'   => $pendingMother + $pendingSub,
                    'trend' => 0,
                ];

                /* ── PRA records ──────────────────────────────────────── */
                $praStats = $countWithTrend('pra');

                /* ── IC (instrument_capture) records ─────────────────── */
                $icTotal   = $db->table('instrument_capture')
                    ->where(function ($q) {
                        $q->where('is_deleted', 0)->orWhereNull('is_deleted');
                    })->count();
                $icCurr    = $db->table('instrument_capture')
                    ->where(function ($q) { $q->where('is_deleted', 0)->orWhereNull('is_deleted'); })
                    ->where('created_at', '>=', $prev30)->count();
                $icPrev    = $db->table('instrument_capture')
                    ->where(function ($q) { $q->where('is_deleted', 0)->orWhereNull('is_deleted'); })
                    ->whereBetween('created_at', [$prev60, $prev30])->count();

                $icStats = [
                    'count' => number_format($icTotal),
                    'raw'   => $icTotal,
                    'trend' => $trend($icCurr, $icPrev),
                ];

                /* ── FH (file_history_staging) records ───────────────── */
                $fhTotal = $db->table('file_history_staging')->count();
                $fhCurr  = $db->table('file_history_staging')->where('created_at', '>=', $prev30)->count();
                $fhPrev  = $db->table('file_history_staging')->whereBetween('created_at', [$prev60, $prev30])->count();

                $fhStats = [
                    'count' => number_format($fhTotal),
                    'raw'   => $fhTotal,
                    'trend' => $trend($fhCurr, $fhPrev),
                ];

                /* ── MLS Files Commissioned ───────────────────────────── */
                $mlsTotal  = $db->table('mls_file_no')->whereNotNull('commissioning_date')->count();
                $mlsCurr   = $db->table('mls_file_no')->whereNotNull('commissioning_date')
                    ->where('created_at', '>=', $prev30)->count();
                $mlsPrev   = $db->table('mls_file_no')->whereNotNull('commissioning_date')
                    ->whereBetween('created_at', [$prev60, $prev30])->count();

                $mlsCommissioned = [
                    'count' => number_format($mlsTotal),
                    'raw'   => $mlsTotal,
                    'trend' => $trend($mlsCurr, $mlsPrev),
                ];

                /* ── ST Primary Applications (Sectional_Title_File_Numbers) ── */
                $stPrimaryTotal = $db->table('Sectional_Title_File_Numbers')->count();
                $stPrimaryCurr  = $db->table('Sectional_Title_File_Numbers')
                    ->where('created_at', '>=', $prev30)->count();
                $stPrimaryPrev  = $db->table('Sectional_Title_File_Numbers')
                    ->whereBetween('created_at', [$prev60, $prev30])->count();

                $stPrimaryApps = [
                    'count' => number_format($stPrimaryTotal),
                    'raw'   => $stPrimaryTotal,
                    'trend' => $trend($stPrimaryCurr, $stPrimaryPrev),
                ];

                /* ── ST PuAs (Sectional Titling sub-unit applications) ────── */
                $stPuasTotal = $db->table('subapplications')->count();
                $stPuasCurr  = $db->table('subapplications')
                    ->where('created_at', '>=', $prev30)->count();
                $stPuasPrev  = $db->table('subapplications')
                    ->whereBetween('created_at', [$prev60, $prev30])->count();

                $stPuas = [
                    'count' => number_format($stPuasTotal),
                    'raw'   => $stPuasTotal,
                    'trend' => $trend($stPuasCurr, $stPuasPrev),
                ];

                /* ── File Stats – redesigned ─────────────────────────────── */
                $fiBase = fn($registry) => $db->table('file_indexings')
                    ->where('registry', $registry)
                    ->where(function ($q) { $q->where('is_deleted', 0)->orWhereNull('is_deleted'); });

                // 1. Raw file registry file count.
                //    $fileNumberCount is the whole registry (land + KANGIS) and
                //    feeds the headline total only. The "Land Files" card uses
                //    $landFileCount: rows carrying an MLS file number. The 6,400
                //    rows with no mlsfNo are KANGIS-only files and are excluded;
                //    the 151 rows holding both an MLS and a KANGIS number are land
                //    files that also have a KANGIS counterpart, so they stay in.
                $fileNumberCount = $db->table('fileNumber')->count();
                $landFileCount   = $db->table('fileNumber')
                    ->whereRaw("LTRIM(RTRIM(ISNULL(mlsfNo, ''))) <> ''")
                    ->count();

                // 2 + 3. Indexed and Commissioned as a PARTITION of the land files
                //    in card 2, so the two cards sum to it exactly.
                //
                //    The old version counted raw file_indexings rows and dropped six
                //    registries by name. That broke the sum three ways: 13,075 land
                //    files were thrown out purely because SLTR / DCIV / KANGIS had
                //    indexed them (they hold MLS numbers, so card 2 counts them);
                //    8,066 files were counted by BOTH cards, since a commissioned
                //    file is normally also indexed; and 137 indexing rows belonged to
                //    no land file at all, most having no file number.
                //
                //    Both figures are now counted from the fileNumber side over the
                //    same population as card 2 - a land file is a land file whichever
                //    registry indexed it - and "indexed" excludes the commissioned so
                //    the two buckets stay disjoint.
                $landBase = fn() => $db->table('fileNumber')
                    ->whereRaw("LTRIM(RTRIM(ISNULL(mlsfNo, ''))) <> ''");

                $hasIndexing = "EXISTS (SELECT 1 FROM file_indexings i
                                 WHERE ISNULL(i.is_deleted, 0) = 0
                                   AND i.file_number = fileNumber.mlsfNo)";
                $isCommissioned = "EXISTS (SELECT 1 FROM mls_file_no m
                                    WHERE m.commissioning_date IS NOT NULL
                                      AND m.full_file_number = fileNumber.mlsfNo)";

                $landIndexedTotal = $landBase()
                    ->whereRaw($hasIndexing)->whereRaw("NOT $isCommissioned")->count();

                $landCommissionedTotal = $landBase()->whereRaw($isCommissioned)->count();

                // 3. Land Files Commissioned (mls_file_no with commissioning date)
                // Already computed as $mlsCommissioned

                // 4. KANGIS Indexed + Commissioned
                $cadastralTotal = (clone $fiBase('KANGIS'))->count(); // kept for backward compat

                $kangisIndexed      = (clone $fiBase('KANGIS'))
                    ->orWhere('registry', 'KANGIS Registry')->count();
                $kangisCommissioned = 0; // KANGIS commissioning not yet started

                // 4b. Cadastral Indexed + Commissioned
                $cadastralIndexed      = $db->table('file_indexings')
                    ->where('is_corresponding_file', 1)
                    ->where(function ($q) { $q->where('is_deleted', 0)->orWhereNull('is_deleted'); })
                    ->count();
                $cadastralCommissioned = $db->table('fileNumber')
                    ->where('cad_lands_matching', 1)
                    ->count();

                // 5. ST Files

                $stIndexed      = $db->table('file_indexings')
                    ->where('registry', 'like', 'st%')
                    ->where(function ($q) { $q->where('is_deleted', 0)->orWhereNull('is_deleted'); })
                    ->count();
                $stCommissioned = $db->table('st_file_numbers')->count();

                // 6. DCIV Files
                $dcivIndexed      = (clone $fiBase('DCIV'))->count();
                $dcivCommissioned = $db->table('dciv_file_no')
                    ->where('year', '2026')
                    ->count();

                // 7. SLTR Files
                $sltrIndexed      = (clone $fiBase('SLTR'))->count();
                $sltrCommissioned = 0; // SLTR commissioning not yet started

                // 8. GKN Files (indexed = file_indexings where registry = 'Survey')
                $gknIndexed      = $db->table('file_indexings')
                    ->where('registry', 'Survey')
                    ->where(function ($q) { $q->where('is_deleted', 0)->orWhereNull('is_deleted'); })
                    ->count();
                $gknCommissioned = 0;

                // 8b. Physical Planning matched files (Shadow File Commissioning).
                //     Matched = indexed files matched (file_indexings.pp_lands_matching = 1)
                //     + fileNumber records matched directly (not sourced from indexing)
                //     + any ST / SLTR shadow-file matches on fileNumber.
                $phyMatched = $db->table('file_indexings')
                        ->where('pp_lands_matching', 1)->count()
                    + $db->table('fileNumber')
                        ->where('pp_lands_matching', 1)
                        ->whereNotIn('mlsfNo', function ($q) {
                            $q->select('file_number')->from('file_indexings')
                              ->where('pp_lands_matching', 1)->whereNotNull('file_number');
                        })->count()
                    + $db->table('fileNumber')->where('pp_st_matching', 1)->count()
                    + $db->table('fileNumber')->where('pp_sltr_matching', 1)->count();

                // 9. Headline "Total Number of Files" = DISTINCT files, not a sum
                //    of registry buckets. file_indexings is very largely a mirror of
                //    fileNumber (167,695 of 171,474 live rows match an existing
                //    fileNumber on mlsfNo / kangisFileNo / NewKANGISFileNo), so the
                //    old "fileNumber + indexed + commissioned" formula counted those
                //    files twice. Count fileNumber in full, then add only the
                //    indexing rows that have no fileNumber counterpart.
                //    Commissioned rows add nothing: cad_lands_matching sits on
                //    fileNumber itself, and every st_file_numbers / dciv_file_no row
                //    already appears in fileNumber or file_indexings (verified 0
                //    orphans in both), so adding them was pure double-counting.
                //    Indexing rows with no file number at all are excluded - they
                //    cannot be reconciled to a distinct file.
                $phyCommissioned = 0;

                $orphanIndexed = (int) $db->selectOne("
                    SELECT COUNT(*) AS c
                    FROM file_indexings fi
                    LEFT JOIN (
                        SELECT mlsfNo AS k FROM fileNumber
                         WHERE LTRIM(RTRIM(ISNULL(mlsfNo, ''))) <> ''
                        UNION
                        SELECT kangisFileNo FROM fileNumber
                         WHERE LTRIM(RTRIM(ISNULL(kangisFileNo, ''))) <> ''
                        UNION
                        SELECT NewKANGISFileNo FROM fileNumber
                         WHERE LTRIM(RTRIM(ISNULL(NewKANGISFileNo, ''))) <> ''
                    ) f ON f.k = fi.file_number
                    WHERE ISNULL(fi.is_deleted, 0) = 0
                      AND f.k IS NULL
                      AND LTRIM(RTRIM(ISNULL(fi.file_number, ''))) <> ''
                ")->c;

                $headlineFileNumberTotal = $fileNumberCount + $orphanIndexed;

                $fileStats = [
                    'total_file_numbers'   => ['count' => number_format($headlineFileNumberTotal),  'raw' => $headlineFileNumberTotal],
                    'total_files'          => ['count' => number_format($landFileCount),          'raw' => $landFileCount],
                    'land_indexed'         => ['count' => number_format($landIndexedTotal),  'raw' => $landIndexedTotal],
                    'land_commissioned'    => ['count' => number_format($landCommissionedTotal),
                                              'raw'   => $landCommissionedTotal,
                                              'trend' => $mlsCommissioned['trend']],
                    'cadastral_total'      => ['count' => number_format($cadastralTotal),    'raw' => $cadastralTotal],
                    'kangis' => ['indexed' => number_format($kangisIndexed), 'commissioned' => number_format($kangisCommissioned)],
                    'cadastral' => ['indexed' => number_format($cadastralIndexed), 'commissioned' => number_format($cadastralCommissioned)],
                        'st'  => ['indexed' => number_format($stIndexed),   'commissioned' => number_format($stCommissioned)],
                    'dciv'=> ['indexed' => number_format($dcivIndexed), 'commissioned' => number_format($dcivCommissioned)],
                    'phy_planning' => ['indexed' => number_format($phyMatched), 'commissioned' => number_format($phyCommissioned)],
                    'sltr'=> ['indexed' => number_format($sltrIndexed), 'commissioned' => number_format($sltrCommissioned)],
                    'gkn' => ['indexed' => number_format($gknIndexed),  'commissioned' => number_format($gknCommissioned)],
                ];

                // Keep $fileRegistry as alias for backward compat with old JS references
                $fileRegistry = $fileStats;

                /* ── Information Products ────────────────────────────────── */
                // CofO – from CofO_staging (all historical + new records)
                $cofOTotal = $db->table('CofO_staging')
                    ->where(function ($q) { $q->whereNull('is_deleted')->orWhere('is_deleted', 0); })
                    ->count();

                // Right of Occupancy – land_recommendations + sltr_recommendations
                $rofoTotal = $db->table('land_recommendations')->count()
                           + $db->table('sltr_recommendations')->count();

                // Letter of Administration – no dedicated table yet
                $loaTotal = 0;

                // Occupancy Permit – instrument_capture (OP type, not deleted)
                $opTotal = $db->table('instrument_capture')
                    ->where('instrument_type', 'Occupancy Permit (OP)')
                    ->where(function ($q) { $q->where('is_deleted', 0)->orWhereNull('is_deleted'); })
                    ->count();

                // Site Plan / Parcel Plan – recommended_site_plans
                $sitePlanTotal = $db->table('recommended_site_plans')->count();

                // Sectional Title Deeds – eRegistry (all commissioned)
                $stDeedsTotal = $db->table('eRegistry')->count();

                $infoProducts = [
                    'cofo'            => ['count' => number_format($cofOTotal),      'raw' => $cofOTotal],
                    'rofo'            => ['count' => number_format($rofoTotal),       'raw' => $rofoTotal],
                    'loa'             => ['count' => number_format($loaTotal),        'raw' => $loaTotal],
                    'occupancy_permit' => ['count' => number_format($opTotal),        'raw' => $opTotal],
                    'site_plan'       => ['count' => number_format($sitePlanTotal),   'raw' => $sitePlanTotal],
                    'st_deeds'        => ['count' => number_format($stDeedsTotal),    'raw' => $stDeedsTotal],
                ];

                /* ── Approved / Rejected applications ────────────────── */
                $approvedMother = $db->table('mother_applications')
                    ->whereIn('application_status', ['approved', 'Approved', 'APPROVED'])->count();
                $approvedSub = $db->table('subapplications')
                    ->whereIn('application_status', ['approved', 'Approved', 'APPROVED'])->count();
                $approvedTotal = $approvedMother + $approvedSub;

                $rejectedMother = $db->table('mother_applications')
                    ->whereIn('application_status', ['rejected', 'Rejected', 'REJECTED'])->count();
                $rejectedSub = $db->table('subapplications')
                    ->whereIn('application_status', ['rejected', 'Rejected', 'REJECTED'])->count();
                $rejectedTotal = $rejectedMother + $rejectedSub;

                /* ── Application pipeline: status split per stream ───────────
                 | Same three streams and same live-row filter as the chart above
                 | it (DashboardApplicationAnalytics), so the numbers reconcile.
                 | A NULL status counts as pending, as it does everywhere else. */
                $pipeline = [];
                foreach ([
                    'st_primary' => ['mother_applications', 'application_status'],
                    'st_sub'     => ['subapplications',     'application_status'],
                    'oss'        => ['oss_applications',    'status'],
                ] as $key => [$table, $col]) {
                    $state = "LOWER(LTRIM(RTRIM(ISNULL($col, 'pending'))))";
                    $live  = fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0);

                    $counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'other' => 0];
                    foreach ($db->table($table)->where($live)
                        ->selectRaw("$state AS s, COUNT(*) AS n")->groupByRaw($state)->get() as $r) {
                        $bucket = array_key_exists($r->s, $counts) ? $r->s : 'other';
                        $counts[$bucket] += (int) $r->n;
                    }

                    $oldestPending = $db->table($table)->where($live)
                        ->whereRaw("$state = 'pending'")->min('created_at');

                    $pipeline[$key] = $counts + [
                        'total'               => array_sum($counts),
                        'oldest_pending_days' => $oldestPending
                            ? (int) Carbon::parse($oldestPending)->diffInDays($now) : null,
                    ];
                }

                /* ── Recent applications: OSS (5) + ST Mother (2) + PuA (2) + SUA (2) ── */

                // Helper: resolve applicant name from first_name/surname/corporate_name
                $resolveName = function ($first, $surname, $corporate) {
                    $full = trim(($first ?? '') . ' ' . ($surname ?? ''));
                    if (empty($full) && !empty($corporate)) {
                        return $corporate;
                    }
                    return $full ?: '—';
                };

                // 5 latest OSS applications
                $ossRows = $db->table('oss_applications')
                    ->where(function ($q) { $q->whereNull('is_deleted')->orWhere('is_deleted', 0); })
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get(['id', 'applicant_name', 'application_type', 'status', 'file_no', 'created_at'])
                    ->map(fn($r) => [
                        'id'          => $r->id,
                        'app_id'      => 'OSS-' . $r->id,
                        'applicant'   => $r->applicant_name ?: '—',
                        'type'        => 'OSS Application',
                        'status'      => $r->status ?? 'pending',
                        'file_number' => $r->file_no ?? '—',
                        'date'        => $r->created_at ? Carbon::parse($r->created_at)->format('Y-m-d') : '—',
                        'source'      => 'oss',
                    ]);

                // 2 latest ST Mother applications (those with a Sectional_Title_File_Numbers entry)
                $stMotherRows = $db->table('mother_applications')
                    ->join('Sectional_Title_File_Numbers', 'Sectional_Title_File_Numbers.application_id', '=', 'mother_applications.id')
                    ->select([
                        'mother_applications.id', 'mother_applications.applicationID',
                        'mother_applications.first_name', 'mother_applications.surname',
                        'mother_applications.corporate_name', 'mother_applications.application_status',
                        'mother_applications.created_at', 'mother_applications.applied_file_number',
                        'mother_applications.fileno',
                        'Sectional_Title_File_Numbers.file_number as st_file_number',
                    ])
                    ->orderBy('mother_applications.created_at', 'desc')
                    ->limit(2)
                    ->get()
                    ->map(fn($r) => [
                        'id'          => $r->id,
                        'app_id'      => $r->applicationID ?: 'ST-' . $r->id,
                        'applicant'   => $resolveName($r->first_name, $r->surname, $r->corporate_name),
                        'type'        => 'ST Primary',
                        'status'      => $r->application_status ?? 'pending',
                        'file_number' => $r->st_file_number ?? $r->applied_file_number ?? $r->fileno ?? '—',
                        'date'        => $r->created_at ? Carbon::parse($r->created_at)->format('Y-m-d') : '—',
                        'source'      => 'st_mother',
                    ]);

                // 2 latest ST PuAs (subapplications where is_sua_unit = 0)
                $puaRows = $db->table('subapplications')
                    ->where('is_sua_unit', 0)
                    ->where(function ($q) { $q->where('is_deleted', 0)->orWhereNull('is_deleted'); })
                    ->orderBy('created_at', 'desc')
                    ->limit(2)
                    ->get(['id', 'fileno', 'first_name', 'surname', 'corporate_name', 'application_status', 'created_at'])
                    ->map(fn($r) => [
                        'id'          => $r->id,
                        'app_id'      => 'PuA-' . $r->id,
                        'applicant'   => $resolveName($r->first_name, $r->surname, $r->corporate_name),
                        'type'        => 'ST PuA',
                        'status'      => $r->application_status ?? 'pending',
                        'file_number' => $r->fileno ?? '—',
                        'date'        => $r->created_at ? Carbon::parse($r->created_at)->format('Y-m-d') : '—',
                        'source'      => 'st_pua',
                    ]);

                // 2 latest ST SUAs (subapplications where is_sua_unit = 1)
                $suaRows = $db->table('subapplications')
                    ->where('is_sua_unit', 1)
                    ->where(function ($q) { $q->where('is_deleted', 0)->orWhereNull('is_deleted'); })
                    ->orderBy('created_at', 'desc')
                    ->limit(2)
                    ->get(['id', 'fileno', 'first_name', 'surname', 'corporate_name', 'application_status', 'created_at'])
                    ->map(fn($r) => [
                        'id'          => $r->id,
                        'app_id'      => 'SUA-' . $r->id,
                        'applicant'   => $resolveName($r->first_name, $r->surname, $r->corporate_name),
                        'type'        => 'ST SUA',
                        'status'      => $r->application_status ?? 'pending',
                        'file_number' => $r->fileno ?? '—',
                        'date'        => $r->created_at ? Carbon::parse($r->created_at)->format('Y-m-d') : '—',
                        'source'      => 'st_sua',
                    ]);

                // Merge all sources: OSS first, then ST Mother, PuA, SUA
                $recentApps = $ossRows
                    ->concat($stMotherRows)
                    ->concat($puaRows)
                    ->concat($suaRows)
                    ->values();

                /* ── Weekly application trend (last 7 days) ──────────── */
                $weeklyData = app(\App\Services\DashboardApplicationAnalytics::class)->get();

                /* ── Application type breakdown ──────────────────────── */
                $appTypes = $db->table('mother_applications')
                    ->select('application_type', DB::raw('COUNT(*) as cnt'))
                    ->whereNotNull('application_type')
                    ->groupBy('application_type')
                    ->orderByDesc('cnt')
                    ->limit(5)
                    ->get()
                    ->map(fn($r) => ['type' => $r->application_type, 'count' => (int) $r->cnt]);

                return [
                    'indexed_files'      => $indexedFiles,
                    'blind_scans'        => $blindScans,
                    'scan_uploads'       => $scanUploads,
                    'total_applications' => $totalApplications,
                    'pending_approvals'  => $pendingApprovals,
                    'pra_records'        => $praStats,
                    'ic_records'         => $icStats,
                    'fh_records'         => $fhStats,
                    'mls_commissioned'   => $mlsCommissioned,
                    'st_primary_apps'    => $stPrimaryApps,
                    'st_puas'            => $stPuas,
                    'file_registry'      => $fileRegistry,
                    'file_stats'         => $fileStats,
                    'info_products'      => $infoProducts,
                    'approved_apps'      => $approvedTotal,
                    'rejected_apps'      => $rejectedTotal,
                    'pipeline'           => $pipeline,
                    'recent_apps'        => $recentApps,
                    'weekly_trend'       => $weeklyData,
                    'app_type_breakdown' => $appTypes,
                    'generated_at'       => $now->toDateTimeString(),
                ];
            });

            return response()->json(['success' => true, 'data' => $stats]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data'    => null,
            ], 500);
        }
    }

    /* ─────────────────────────────────────────────────────────────────────
     | INDIVIDUAL endpoints (kept for backward compatibility)
     ───────────────────────────────────────────────────────────────────── */

    public function getTotalApplications()
    {
        try {
            $motherCount = DB::connection($this->connection)->table('mother_applications')->count();
            $subCount    = DB::connection($this->connection)->table('subapplications')->count();
            $total       = $motherCount + $subCount;

            $now     = Carbon::now();
            $curr    = DB::connection($this->connection)->table('mother_applications')->where('created_at', '>=', $now->copy()->subDays(30))->count()
                     + DB::connection($this->connection)->table('subapplications')->where('created_at', '>=', $now->copy()->subDays(30))->count();
            $prev    = DB::connection($this->connection)->table('mother_applications')->whereBetween('created_at', [$now->copy()->subDays(60), $now->copy()->subDays(30)])->count()
                     + DB::connection($this->connection)->table('subapplications')->whereBetween('created_at', [$now->copy()->subDays(60), $now->copy()->subDays(30)])->count();

            $trend = $prev > 0 ? round((($curr - $prev) / $prev) * 100, 1) : 0;

            return response()->json(['count' => number_format($total), 'trend' => $trend, 'raw_count' => $total]);
        } catch (\Exception $e) {
            return response()->json(['count' => 'Error', 'trend' => '0', 'error' => $e->getMessage()], 500);
        }
    }

    public function getPendingApprovals()
    {
        try {
            $pending = DB::connection($this->connection)->table('mother_applications')
                ->where(function ($q) { $q->whereNull('application_status')->orWhereIn('application_status', ['pending', 'Pending', 'PENDING']); })->count()
                + DB::connection($this->connection)->table('subapplications')
                ->where(function ($q) { $q->whereNull('application_status')->orWhereIn('application_status', ['pending', 'Pending', 'PENDING']); })->count();

            return response()->json(['count' => number_format($pending), 'trend' => '0', 'raw_count' => $pending]);
        } catch (\Exception $e) {
            return response()->json(['count' => 'Error', 'trend' => '0', 'error' => $e->getMessage()], 500);
        }
    }

    public function getIndexedFiles()
    {
        try {
            $count = DB::connection($this->connection)->table('file_indexings')->count();
            $now   = Carbon::now();
            $curr  = DB::connection($this->connection)->table('file_indexings')->where('created_at', '>=', $now->copy()->subDays(30))->count();
            $prev  = DB::connection($this->connection)->table('file_indexings')->whereBetween('created_at', [$now->copy()->subDays(60), $now->copy()->subDays(30)])->count();
            $trend = $prev > 0 ? round((($curr - $prev) / $prev) * 100, 1) : 0;

            return response()->json(['count' => number_format($count), 'trend' => $trend, 'raw_count' => $count]);
        } catch (\Exception $e) {
            return response()->json(['count' => 'Error', 'trend' => '0', 'error' => $e->getMessage()], 500);
        }
    }

    public function getBlindScans()
    {
        try {
            $count = DB::connection($this->connection)->table('blind_scannings')->count();
            $now   = Carbon::now();
            $curr  = DB::connection($this->connection)->table('blind_scannings')->where('created_at', '>=', $now->copy()->subDays(30))->count();
            $prev  = DB::connection($this->connection)->table('blind_scannings')->whereBetween('created_at', [$now->copy()->subDays(60), $now->copy()->subDays(30)])->count();
            $trend = $prev > 0 ? round((($curr - $prev) / $prev) * 100, 1) : 0;

            return response()->json(['count' => number_format($count), 'trend' => $trend, 'raw_count' => $count]);
        } catch (\Exception $e) {
            return response()->json(['count' => 'Error', 'trend' => '0', 'error' => $e->getMessage()], 500);
        }
    }

    public function getScanUploads()
    {
        try {
            $count = DB::connection($this->connection)->table('scannings')->count();
            $now   = Carbon::now();
            $curr  = DB::connection($this->connection)->table('scannings')->where('created_at', '>=', $now->copy()->subDays(30))->count();
            $prev  = DB::connection($this->connection)->table('scannings')->whereBetween('created_at', [$now->copy()->subDays(60), $now->copy()->subDays(30)])->count();
            $trend = $prev > 0 ? round((($curr - $prev) / $prev) * 100, 1) : 0;

            return response()->json(['count' => number_format($count), 'trend' => $trend, 'raw_count' => $count]);
        } catch (\Exception $e) {
            return response()->json(['count' => 'Error', 'trend' => '0', 'error' => $e->getMessage()], 500);
        }
    }

    /* ── New individual endpoints ──────────────────────────────────────── */

    public function getPraRecords()
    {
        try {
            $count = DB::connection($this->connection)->table('pra')->count();
            return response()->json(['count' => number_format($count), 'trend' => 0, 'raw_count' => $count]);
        } catch (Throwable $e) {
            return response()->json(['count' => 'Error', 'trend' => 0, 'error' => $e->getMessage()], 500);
        }
    }

    public function getIcRecords()
    {
        try {
            $count = DB::connection($this->connection)->table('instrument_capture')
                ->where(function ($q) { $q->where('is_deleted', 0)->orWhereNull('is_deleted'); })
                ->count();
            return response()->json(['count' => number_format($count), 'trend' => 0, 'raw_count' => $count]);
        } catch (Throwable $e) {
            return response()->json(['count' => 'Error', 'trend' => 0, 'error' => $e->getMessage()], 500);
        }
    }

    public function getFhRecords()
    {
        try {
            $count = DB::connection($this->connection)->table('file_history_staging')->count();
            return response()->json(['count' => number_format($count), 'trend' => 0, 'raw_count' => $count]);
        } catch (Throwable $e) {
            return response()->json(['count' => 'Error', 'trend' => 0, 'error' => $e->getMessage()], 500);
        }
    }

    public function getMlsCommissioned()
    {
        try {
            $count = DB::connection($this->connection)->table('mls_file_no')
                ->whereNotNull('commissioning_date')
                ->count();
            return response()->json(['count' => number_format($count), 'trend' => 0, 'raw_count' => $count]);
        } catch (Throwable $e) {
            return response()->json(['count' => 'Error', 'trend' => 0, 'error' => $e->getMessage()], 500);
        }
    }
}
