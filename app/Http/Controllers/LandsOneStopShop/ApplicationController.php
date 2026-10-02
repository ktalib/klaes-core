<?php

namespace App\Http\Controllers\LandsOneStopShop;

use App\Rules\NigerianPhone;

use App\Http\Controllers\Controller;
use App\Models\Billing;
use App\Models\LandRecommendation;
use App\Models\LandsOneStopShopApplication;
use App\Models\StreetName;
use App\Services\Pra\PraRecordService;
use App\Services\Pra\RofoPraSyncer;
use App\Support\OssRecommendationLocation;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ApplicationController extends Controller
{
    /**
     * Display the general Lands One Stop Shop applications listing.
     * Now pulls from oss_applications table and the legacy instrument_capture.
     */
    public function index(Request $request): View
    {
        $limit = max(10, min((int) $request->input('limit', 50), 200));
        $search = trim((string) $request->input('search'));
        $typeFilter = $request->input('type');

        // 'change-of-name' and 'no-change-of-name' are page-mode flags, not application_type values
        // Allow a separate app_type param for filtering within the page
        $appTypeFilter = $request->input('app_type');
        if (!$appTypeFilter) {
            $appTypeFilter = in_array($typeFilter, ['change-of-name', 'no-change-of-name'], true)
                ? null
                : $typeFilter;
        }

        // Determine system_source filtering for Change of Name separation
        $isChangeOfNamePage = ($typeFilter === 'change-of-name');
        $isNoChangeOfNamePage = ($typeFilter === 'no-change-of-name');

        if ($isChangeOfNamePage) {
            return $this->indexChangeOfNameApplications($request, $limit, $search, $appTypeFilter);
        }

        // Build schema-safe PRA fallback SQL snippets (avoid hardcoding non-existent columns).
        $praColumns = $this->sqlsrvColumnMap('pra');
        $pickPraColumn = static function (array $candidates) use ($praColumns): ?string {
            foreach ($candidates as $candidate) {
                $key = strtolower((string) $candidate);
                if (isset($praColumns[$key])) {
                    return $praColumns[$key];
                }
            }

            return null;
        };

        $praFileMatchParts = [];
        foreach (['temp_fileno', 'mlsFNo', 'fileno', 'kangisFileNo', 'NewKANGISFileno', 'np_fileno'] as $candidate) {
            $column = $pickPraColumn([$candidate]);
            if ($column !== null) {
                $praFileMatchParts[] = "p.[{$column}] = oa.file_no";
            }
        }
        $praFileMatchSql = implode(' OR ', $praFileMatchParts);

        $buildPraValueExpr = static function (array $columns): string {
            if (empty($columns)) {
                return 'NULL';
            }

            $parts = array_map(static function ($column) {
                return "NULLIF(LTRIM(RTRIM(p.[{$column}])), '')";
            }, $columns);

            if (count($parts) === 1) {
                return $parts[0];
            }

            return 'COALESCE(' . implode(', ', $parts) . ')';
        };

        $plotCols = array_values(array_filter([
            $pickPraColumn(['plot_no']),
            $pickPraColumn(['plot_number']),
        ]));

        $planCols = array_values(array_filter([
            $pickPraColumn(['approved_plan_no']),
            $pickPraColumn(['tp_no']),
            $pickPraColumn(['lpkn_no']),
        ]));

        $opSerialCols = array_values(array_filter([
            $pickPraColumn(['op_serial_number']),
            $pickPraColumn(['serialNo']),
            $pickPraColumn(['pageNo']),
        ]));

        $praPlotExpr = $buildPraValueExpr($plotCols);
        $praPlanExpr = $buildPraValueExpr($planCols);
        $praOpSerialExpr = $buildPraValueExpr($opSerialCols);

        $locationCols = array_values(array_filter([
            $pickPraColumn(['property_description']),
            $pickPraColumn(['location']),
        ]));
        $praLocationExpr = $buildPraValueExpr($locationCols);

        // pra spells it districtName; keep `district` as a defensive alternative
        // so the expression still resolves if the column is ever renamed.
        $districtCols = array_values(array_filter([
            $pickPraColumn(['districtName']),
            $pickPraColumn(['district']),
        ]));
        $praDistrictExpr = $buildPraValueExpr($districtCols);

        $originalHolderCols = array_values(array_filter([
            $pickPraColumn(['Grantor']),
            $pickPraColumn(['party_1']),
            $pickPraColumn(['party_1_name']),
        ]));
        $praOriginalHolderExpr = $buildPraValueExpr($originalHolderCols);

        // ── Build OUTER APPLY clauses to replace 11 correlated subqueries ──
        // PRA: 5 columns in one lateral join instead of 5 separate SELECT TOP 1
        $praApply = '';
        $hasPraTop = ($praFileMatchSql !== '');
        if ($hasPraTop) {
            $praApply = "OUTER APPLY (
                SELECT TOP 1
                    {$praPlotExpr} as plot_no,
                    {$praPlanExpr} as plan_no,
                    {$praOpSerialExpr} as op_serial_number,
                    {$praLocationExpr} as location,
                    {$praDistrictExpr} as district,
                    {$praOriginalHolderExpr} as original_holder
                FROM pra p
                WHERE {$praFileMatchSql}
                ORDER BY p.id DESC
            ) as pra_top";
        }

        // file_indexings: 3 columns in one lateral join
        $fiColumns = $this->sqlsrvColumnMap('file_indexings');
        $pickFiColumn = static function (array $candidates) use ($fiColumns): ?string {
            foreach ($candidates as $c) {
                if (isset($fiColumns[strtolower($c)])) {
                    return $fiColumns[strtolower($c)];
                }
            }
            return null;
        };
        $fiFileNumberCol = $pickFiColumn(['file_number']);
        $fiLocationCol = $pickFiColumn(['location']);
        $fiDistrictCol = $pickFiColumn(['district']);
        $fiPlotCol = $pickFiColumn(['plot_no', 'plot_number']);
        $fiPlanCol = $pickFiColumn(['plan_no', 'tp_no']);

        $fiApply = '';
        $hasFiTop = false;
        if ($fiFileNumberCol) {
            $fiTopCols = [];
            if ($fiLocationCol)
                $fiTopCols[] = "NULLIF(LTRIM(RTRIM(fi.[{$fiLocationCol}])), '') as fi_location";
            if ($fiDistrictCol)
                $fiTopCols[] = "NULLIF(LTRIM(RTRIM(fi.[{$fiDistrictCol}])), '') as fi_district";
            if ($fiPlotCol)
                $fiTopCols[] = "NULLIF(LTRIM(RTRIM(fi.[{$fiPlotCol}])), '') as fi_plot_no";
            if ($fiPlanCol)
                $fiTopCols[] = "NULLIF(LTRIM(RTRIM(fi.[{$fiPlanCol}])), '') as fi_plan_no";
            if (!empty($fiTopCols)) {
                $hasFiTop = true;
                $fiApply = "OUTER APPLY (
                    SELECT TOP 1 " . implode(', ', $fiTopCols) . "
                    FROM file_indexings fi
                    WHERE fi.[{$fiFileNumberCol}] = oa.file_no
                    ORDER BY fi.id DESC
                ) as fi_top";
            }
        }

        // fileNumber: 3 columns in one lateral join
        $hasFnTop = !$isNoChangeOfNamePage;
        $fnApply = $hasFnTop ? "OUTER APPLY (
            SELECT TOP 1
                NULLIF(LTRIM(RTRIM(fn.[location])), '') as fn_location,
                NULLIF(LTRIM(RTRIM(fn.[district])), '') as fn_district,
                NULLIF(LTRIM(RTRIM(fn.[plot_no])), '') as fn_plot_no,
                NULLIF(LTRIM(RTRIM(fn.[tp_no])), '') as fn_plan_no
            FROM fileNumber fn
            WHERE fn.mlsfNo = oa.file_no
            ORDER BY fn.id DESC
        ) as fn_top" : '';

        // MLS commissioning mirrors already carry holder, plot, plan, location and
        // passport directly on oss_applications. Running three TOP-1 OUTER APPLY
        // lookups for every one of the 5,000+ no-change rows made SQL Server repeat
        // expensive fallback work during both the page query and paginator COUNT.
        // The two hidden OP columns are the only consumers of PRA data on this page,
        // so skip all registry fallbacks here; legacy rows still retain their direct
        // OSS/instrument_capture values.
        if ($isNoChangeOfNamePage) {
            $praApply = '';
            $fiApply = '';
            $hasPraTop = false;
            $hasFiTop = false;
        }

        $fromClause = "oss_applications as oa {$praApply} {$fiApply} {$fnApply}";

        // ── Pull from oss_applications table with OUTER APPLY + LEFT JOINs ──
        $records = DB::connection('sqlsrv')
            ->table(DB::raw($fromClause))
            ->leftJoin('instrument_capture as ic', 'ic.id', '=', 'oa.instrument_capture_id')
            ->leftJoin('users as cb', 'cb.id', '=', 'oa.captured_by')
            ->select([
                'oa.*',
                DB::raw("CONCAT(cb.first_name, ' ', cb.last_name) as captured_by_name"),
                'ic.temp_fileno',
                'ic.plot_number as ic_plot_number',
                'ic.survey_plan_no as ic_plan_no',
                'ic.property_description as ic_location',
                'ic.district as ic_district',
                'ic.op_serial_number as ic_op_serial_number',
                DB::raw($hasPraTop ? "pra_top.plot_no as pra_plot_no" : "NULL as pra_plot_no"),
                DB::raw($hasPraTop ? "pra_top.plan_no as pra_plan_no" : "NULL as pra_plan_no"),
                DB::raw($hasPraTop ? "pra_top.op_serial_number as pra_op_serial_number" : "NULL as pra_op_serial_number"),
                DB::raw($hasPraTop ? "pra_top.location as pra_location" : "NULL as pra_location"),
                // Aliased away from a bare `district`, which `oa.*` above already emits.
                DB::raw($hasPraTop ? "pra_top.district as pra_district" : "NULL as pra_district"),
                DB::raw($hasPraTop ? "pra_top.original_holder as pra_original_holder" : "NULL as pra_original_holder"),
                'ic.party_1_name as ic_original_holder',
                DB::raw($hasFiTop && $fiLocationCol ? "fi_top.fi_location" : "NULL as fi_location"),
                DB::raw($hasFiTop && $fiDistrictCol ? "fi_top.fi_district" : "NULL as fi_district"),
                DB::raw($hasFiTop && $fiPlotCol ? "fi_top.fi_plot_no" : "NULL as fi_plot_no"),
                DB::raw($hasFiTop && $fiPlanCol ? "fi_top.fi_plan_no" : "NULL as fi_plan_no"),
                DB::raw($hasFnTop ? "fn_top.fn_location" : "NULL as fn_location"),
                DB::raw($hasFnTop ? "fn_top.fn_district" : "NULL as fn_district"),
                DB::raw($hasFnTop ? "fn_top.fn_plot_no" : "NULL as fn_plot_no"),
                DB::raw($hasFnTop ? "fn_top.fn_plan_no" : "NULL as fn_plan_no"),
            ])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('oa.applicant_name', 'LIKE', "%{$search}%")
                        ->orWhere('oa.file_no', 'LIKE', "%{$search}%")
                        ->orWhere('oa.plot_no', 'LIKE', "%{$search}%")
                        ->orWhere('oa.plan_no', 'LIKE', "%{$search}%")
                        ->orWhere('oa.location', 'LIKE', "%{$search}%")
                        ->orWhere('oa.phone', 'LIKE', "%{$search}%")
                        ->orWhere('ic.temp_fileno', 'LIKE', "%{$search}%")
                        ->orWhere('ic.op_serial_number', 'LIKE', "%{$search}%");
                });
            })
            ->when($appTypeFilter, function ($q) use ($appTypeFilter) {
                $q->where('oa.application_type', $appTypeFilter);
            })
            ->when($isChangeOfNamePage, function ($q) {
                $q->where('oa.system_source', 'OSSOPCHANGEOFNAME');
            })
            ->when($isNoChangeOfNamePage, function ($q) {
                $q->where(function ($sub) {
                    $sub->whereNull('oa.system_source')
                        ->orWhere('oa.system_source', '!=', 'OSSOPCHANGEOFNAME');
                });
            })
            ->where(function ($q) {
                $q->whereNull('oa.is_deleted')
                    ->orWhere('oa.is_deleted', 0);
            })
            ->orderByDesc('oa.created_at')
            ->paginate($limit)
            ->appends($request->query());

        $records->getCollection()->transform(function ($record) {
            $record->passport_photo_url = null;
            if (!empty($record->passport_photo)) {
                $path = $record->passport_photo;
                if (str_starts_with($path, 'public/')) {
                    $path = substr($path, 7);
                }
                $record->passport_photo_url = asset('storage/' . $path);
            }
            return $record;
        });

        // Supporting data for modals / dropdowns
        $states = DB::connection('sqlsrv')->table('States')->orderBy('StateName')->get();
        $lgas = DB::connection('sqlsrv')->table('lgas')->where('is_active', 1)->orderBy('name')->get();
        $districts = DB::connection('sqlsrv')->table('districts')->where('is_active', 1)->orderBy('name')->get();
        $streetNames = StreetName::orderBy('name')->get(['id', 'name'])->toBase();

        // ── Card counts by application type ──
        $cardCountsQuery = LandsOneStopShopApplication::query()
            ->selectRaw("application_type, COUNT(*) as cnt");

        if ($isChangeOfNamePage) {
            $cardCountsQuery->where('system_source', 'OSSOPCHANGEOFNAME');
        } elseif ($isNoChangeOfNamePage) {
            $cardCountsQuery->where(function ($sub) {
                $sub->whereNull('system_source')
                    ->orWhere('system_source', '!=', 'OSSOPCHANGEOFNAME');
            });
        }
        $cardCountsQuery->where(function ($q) {
            $q->whereNull('is_deleted')
                ->orWhere('is_deleted', 0);
        });

        $cardCounts = $cardCountsQuery
            ->groupBy('application_type')
            ->pluck('cnt', 'application_type')
            ->toArray();

        // Total is counted independently so an older/unrecognised land-use type is
        // still represented on the Total Records card even if it has no category card.
        $cardTotalQuery = LandsOneStopShopApplication::query();
        if ($isNoChangeOfNamePage) {
            $cardTotalQuery->where(function ($sub) {
                $sub->whereNull('system_source')
                    ->orWhere('system_source', '!=', 'OSSOPCHANGEOFNAME');
            });
        }
        $cardTotalQuery->where(function ($q) {
            $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
        });
        $cardTotal = $cardTotalQuery->count();

        // ── Daily record count (created today) ──
        $dailyQuery = LandsOneStopShopApplication::query()
            ->whereDate('created_at', now()->toDateString());
        if ($isChangeOfNamePage) {
            $dailyQuery->where('system_source', 'OSSOPCHANGEOFNAME');
        } elseif ($isNoChangeOfNamePage) {
            $dailyQuery->where(function ($sub) {
                $sub->whereNull('system_source')
                    ->orWhere('system_source', '!=', 'OSSOPCHANGEOFNAME');
            });
        }
        $dailyQuery->where(function ($q) {
            $q->whereNull('is_deleted')
                ->orWhere('is_deleted', 0);
        });
        $dailyCount = $dailyQuery->count();

        return view('lands_one_stop_shop.all_applications', [
            'pageTitle' => 'Applications',
            'records' => $records,
            'limit' => $limit,
            'typeFilter' => $typeFilter,
            'states' => $states,
            'lgas' => $lgas,
            'districts' => $districts,
            'streetNames' => $streetNames,
            'tpNumbers' => $this->ossDistinctTpNumbers(),
            'typeOptions' => LandsOneStopShopApplication::typeOptions(),
            'statusOptions' => LandsOneStopShopApplication::statusOptions(),
            'cardCounts' => $cardCounts,
            'cardTotal' => $cardTotal,
            'dailyCount' => $dailyCount,
            'isChangeOfNamePage' => $isChangeOfNamePage,
            'isNoChangeOfNamePage' => $isNoChangeOfNamePage,
        ]);
    }

    private function indexChangeOfNameApplications(Request $request, int $limit, string $search, ?string $appTypeFilter): View
    {
        $ossColumns = $this->sqlsrvColumnMap('oss_applications');
        $ossHasIsDeleted = isset($ossColumns['is_deleted']);
        $ossWhereSql = $ossHasIsDeleted
            ? "WHERE system_source = 'OSSOPCHANGEOFNAME'\n                  AND (is_deleted IS NULL OR is_deleted = 0)"
            : "WHERE system_source = 'OSSOPCHANGEOFNAME'";

        // oa.file_no is the authoritative file number; p columns are supplementary
        // and may be NULL when no Transfer of Title pra row exists for the application.
        $resolvedLandUseSql = "COALESCE(
            mfn.land_use,
            p.land_use,
            oa.land_use,
            CASE
                WHEN oa.file_no LIKE 'RES%' THEN 'RES'
                WHEN oa.file_no LIKE 'COM%' THEN 'COM'
                WHEN oa.file_no LIKE 'CON%' THEN 'COM'
                WHEN oa.file_no LIKE 'IND%' THEN 'IND'
                WHEN oa.file_no LIKE 'AGR%' THEN 'AGR'
                ELSE NULL
            END
        )";
        $applicationTypeSql = "CASE
            WHEN $resolvedLandUseSql LIKE '%RES%' THEN 'residential'
            WHEN $resolvedLandUseSql LIKE '%COM%' THEN 'commercial'
            WHEN $resolvedLandUseSql LIKE '%IND%' THEN 'industrial'
            WHEN $resolvedLandUseSql LIKE '%AGR%' THEN 'agricultural'
            ELSE COALESCE(oa.application_type, 'residential')
        END";

        // District lives on the file's indexing record for 305 of the 352 CoN
        // applications, against 2 on oss_applications itself and 2 on the Transfer
        // of Title pra row joined below — without this apply the District column is
        // almost entirely dashes. Measured at 0.30s over the whole CoN set; the
        // no-change page skips its registry applies for the opposite reason (5,000+
        // rows), so this one deliberately stays on the CoN side only.
        $fiColumns = $this->sqlsrvColumnMap('file_indexings');
        $fiApply = (isset($fiColumns['file_number']) && isset($fiColumns['district']))
            ? "OUTER APPLY (
                SELECT TOP 1 NULLIF(LTRIM(RTRIM(fi.[{$fiColumns['district']}])), '') as fi_district
                FROM file_indexings fi
                WHERE fi.[{$fiColumns['file_number']}] = oa.file_no
                ORDER BY fi.id DESC
            ) as fi_top"
            : '';
        $hasFiTop = $fiApply !== '';

        // Drive from oss_applications so every CoN application record is visible
        // even when its Transfer of Title pra row is absent (e.g. after a failed
        // restore). pra, fileNumber, and mls_file_no are LEFT-joined for enrichment.
        $query = DB::connection('sqlsrv')
            ->table(DB::raw("(
                SELECT *, ROW_NUMBER() OVER (PARTITION BY file_no ORDER BY id DESC) as oa_rn
                FROM oss_applications
                $ossWhereSql
            ) as oa {$fiApply}"))
            ->where('oa.oa_rn', 1)
            ->leftJoin(DB::raw("(
                SELECT id, prop_id, mlsFNo, fileno, temp_fileno, instrument_type,
                    Grantor, Grantee, party_1, party_2, regNo, op_type,
                    op_serial_number, property_description, location, districtName,
                    created_by, created_at, plot_no, tp_no, lgsaOrCity,
                    land_use, system_source,
                    ROW_NUMBER() OVER (PARTITION BY COALESCE(NULLIF(mlsFNo,''), fileno) ORDER BY id DESC) as p_rn
                FROM pra
                WHERE system_source = 'OSSOPCHANGEOFNAME'
                  AND prop_id IS NOT NULL AND prop_id != ''
                  AND (instrument_type LIKE '%Transfer of Title%' OR transaction_type LIKE '%Transfer of Title%')
                  AND (is_deleted IS NULL OR is_deleted = 0)
            ) as p"), function ($join) {
                $join->whereRaw("(p.mlsFNo = oa.file_no OR p.fileno = oa.file_no)")
                    ->where('p.p_rn', 1);
            })
            // The Transfer of Title row (`p`) carries an unreliable Grantor for these
            // batch change-of-name records — it is stamped with the OP issuer
            // "Kano State Government" rather than the previous holder. The real
            // original holder is the Grantee of the original OP allocation row, so
            // pull it from the earliest Occupancy Permit (OP) pra row for the file.
            //
            // Its op_serial_number comes along for the same reason. The OP serial is
            // the OP row's own field, and on a file whose Transfer of Title row was
            // never written — or was written without one — this row is the only
            // place it exists. It used to be read solely off the Transfer of Title
            // row, so files showed an original holder taken from this very row
            // beside a blank OP SERIAL NO, with the serial sitting unread in the
            // same joined record.
            ->leftJoin(DB::raw("(
                SELECT COALESCE(NULLIF(mlsFNo,''), fileno) as op_file,
                    Grantee as op_grantee, party_2 as op_party_2,
                    NULLIF(NULLIF(LTRIM(RTRIM(op_serial_number)), ''), '0') as op_row_serial,
                    ROW_NUMBER() OVER (
                        PARTITION BY COALESCE(NULLIF(mlsFNo,''), fileno)
                        ORDER BY id ASC
                    ) as op_rn
                FROM pra
                WHERE (instrument_type LIKE '%Occupancy Permit%' OR transaction_type LIKE '%Occupancy Permit%')
                  AND (is_deleted IS NULL OR is_deleted = 0)
            ) as op"), function ($join) {
                $join->whereRaw("op.op_file = oa.file_no")
                    ->where('op.op_rn', 1);
            })
            ->leftJoin(DB::raw("(
                SELECT *, ROW_NUMBER() OVER (PARTITION BY mlsfNo ORDER BY id DESC) as fn_rn
                FROM fileNumber
            ) as fn"), function ($join) {
                $join->whereRaw("fn.mlsfNo = oa.file_no")
                    ->where('fn.fn_rn', 1);
            })
            ->leftJoin(DB::raw("(
                SELECT *, ROW_NUMBER() OVER (PARTITION BY tracking_id, full_file_number ORDER BY id DESC) as mfn_rn
                FROM mls_file_no
            ) as mfn"), function ($join) {
                $join->on('fn.tracking_id', '=', 'mfn.tracking_id')
                    ->whereRaw("mfn.full_file_number = oa.file_no")
                    ->where('mfn.mfn_rn', 1);
            })
            ->leftJoin('instrument_capture as source_capture', 'mfn.source_instrument_capture_id', '=', 'source_capture.id')
            // The row the commissioning says this file was raised from, followed by
            // id rather than by file number.
            //
            // Every other join above matches pra on oa.file_no. A file commissioned
            // straight from an Occupancy Permit capture leaves its OP row carrying
            // the TEMP number it was captured under (fileno = 'TEMP-10599',
            // mlsFNo NULL) — nothing back-stamps the allocated file number onto it —
            // so those joins all miss, and both columns came out blank on a file
            // whose serial and allottee were on record the whole time.
            // mls_file_no.source_pra_id is the link that survives that, because the
            // commissioning wrote it.
            ->leftJoin('pra as source_pra', 'mfn.source_pra_id', '=', 'source_pra.id')
            ->leftJoin('users as cb', DB::raw('cb.id'), '=', DB::raw('TRY_CONVERT(int, p.created_by)'))
            ->leftJoin('users as oa_cb', 'oa_cb.id', '=', 'oa.captured_by')
            ->select([
                DB::raw('oa.id as oss_application_id'),
                'oa.instrument_capture_id as oa_instrument_capture_id',
                'oa.application_type as oa_application_type',
                'oa.applicant_name as oa_applicant_name',
                'oa.address as oa_address',
                'oa.location as oa_location',
                'oa.residential_address as oa_residential_address',
                'oa.correspondence_address as oa_correspondence_address',
                'oa.phone as oa_phone',
                'oa.status as oa_status',
                'oa.passport_photo',
                'oa.created_at as oa_created_at',
                // Pull every other oss_applications column so the Edit modal
                // can backfill state_of_origin, lga, occupation, annual_income,
                // prev_allocated, remarks, age, sex, marital_status, husband
                // name/address, nationality, business_address, all res_addr_*/
                // res_corr_*/res_biz_* builder fields, and the equivalent
                // commercial/industrial/agricultural columns. Without this only
                // the ~12 explicitly-aliased columns reach the row's
                // data-record JSON and the rest show as blank/default.
                'oa.*',
                DB::raw("CONCAT(oa_cb.first_name, ' ', oa_cb.last_name) as oa_captured_by_name"),
                'p.id as pra_id',
                'p.prop_id',
                'p.instrument_type as pra_instrument_type',
                'p.Grantor',
                'p.Grantee',
                'p.party_1',
                'p.party_2',
                'op.op_grantee',
                'op.op_party_2',
                // `oa.*` above already emits an op_serial_number column, so selecting
                // p.op_serial_number bare would silently shadow it — the OSS value could
                // never reach the row and files whose Transfer of Title row carries no
                // serial rendered blank. Coalesce instead: the ToT row stays authoritative,
                // the application's own captured serial is the fallback.
                // '0' is a sentinel for "no serial", not a serial — the OP card applies the
                // same guard (op_capture_edit.blade.php). Treat it as absent on both sides so
                // a placeholder never renders as though a real serial were on file.
                //
                // The OP allocation row and the commissioning's source row are the
                // last two links in the chain, and they are what a file carries when
                // no Transfer of Title row was ever written for it.
                DB::raw("COALESCE(NULLIF(NULLIF(LTRIM(RTRIM(p.op_serial_number)), ''), '0'), NULLIF(NULLIF(LTRIM(RTRIM(oa.op_serial_number)), ''), '0'), op.op_row_serial, NULLIF(NULLIF(LTRIM(RTRIM(source_pra.op_serial_number)), ''), '0')) as op_serial_number"),
                DB::raw("NULLIF(NULLIF(LTRIM(RTRIM(p.op_serial_number)), ''), '0') as pra_op_serial_number"),
                // Aliased to avoid clashing with oa.plot_no / oa.property_description
                // brought in by `oa.*` above. Without these aliases the OSS values
                // would shadow the PRA values in $row->plot_no / $row->property_description.
                'p.plot_no as pra_plot_no_raw',
                'p.tp_no',
                'p.location as pra_location',
                // Aliased away from a bare `district`, which `oa.*` above already emits.
                'p.districtName as pra_district',
                'p.property_description as pra_property_description',
                // nvarchar column — convert here so a malformed value surfaces as NULL
                // rather than reaching Carbon::parse() in the view (see orderByRaw below).
                DB::raw('TRY_CONVERT(datetime, p.created_at) as pra_created_at'),
                'p.temp_fileno',
                DB::raw("COALESCE(NULLIF(oa.file_no, ''), NULLIF(p.mlsFNo, ''), p.fileno) as file_no"),
                'fn.FileName as indexed_file_title',
                'fn.plot_no as fn_plot_no',
                'fn.tp_no as fn_plan_no',
                'fn.location as fn_location',
                'fn.district as fn_district',
                DB::raw($hasFiTop ? 'fi_top.fi_district' : 'NULL as fi_district'),
                'fn.temp_fileno as fn_temp_fileno',
                'source_capture.id as source_instrument_capture_id',
                'source_capture.party_1_name as source_party_1_name',
                'source_capture.party_2_name as source_party_2_name',
                'source_capture.party_1_phone as source_party_1_phone',
                'source_capture.party_2_phone as source_party_2_phone',
                'source_capture.party_1_address as source_party_1_address',
                'source_capture.party_2_address as source_party_2_address',
                'source_capture.plot_number as ic_plot_number',
                'source_capture.tp_no as ic_plan_no',
                'source_capture.property_description as ic_location',
                'source_capture.district as ic_district',
                'source_capture.op_serial_number as ic_op_serial_number',
                'source_pra.Grantee as source_pra_grantee',
                'source_pra.party_2 as source_pra_party_2',
                DB::raw("COALESCE(NULLIF(LTRIM(RTRIM(CONCAT(COALESCE(cb.first_name, ''), ' ', COALESCE(cb.last_name, '')))), ''), p.created_by, N'—') as pra_created_by_name"),
                DB::raw("$resolvedLandUseSql as resolved_land_use"),
                DB::raw("$applicationTypeSql as resolved_application_type")
            ])
            ->where(function ($builder) {
                // Allow rows with no fileNumber entry (fn.is_deleted IS NULL covers that)
                $builder->whereNull('fn.is_deleted')
                    ->orWhere('fn.is_deleted', 0);
            })
            ->when($search, function ($builder) use ($search) {
                $builder->where(function ($sub) use ($search) {
                    $sub->whereRaw("oa.file_no LIKE ?", ["%{$search}%"])
                        ->orWhere('p.Grantee', 'LIKE', "%{$search}%")
                        ->orWhere('p.Grantor', 'LIKE', "%{$search}%")
                        ->orWhere('oa.applicant_name', 'LIKE', "%{$search}%")
                        ->orWhere('fn.FileName', 'LIKE', "%{$search}%")
                        ->orWhere('p.plot_no', 'LIKE', "%{$search}%")
                        ->orWhere('p.tp_no', 'LIKE', "%{$search}%")
                        ->orWhere('p.location', 'LIKE', "%{$search}%")
                        ->orWhere('p.property_description', 'LIKE', "%{$search}%")
                        ->orWhere('source_capture.op_serial_number', 'LIKE', "%{$search}%");
                });
            })
            ->when($appTypeFilter, function ($builder) use ($appTypeFilter, $applicationTypeSql) {
                $builder->whereRaw("$applicationTypeSql = ?", [$appTypeFilter]);
            })
            // pra.created_at is nvarchar while oss_applications.created_at is datetime.
            // A bare COALESCE takes the higher-precedence datetime type and implicitly
            // casts every pra string, so one malformed value anywhere in the result set
            // aborts the whole page with a 22007 conversion error. TRY_CONVERT yields
            // NULL for junk instead, and the oa datetime then carries the ordering.
            ->orderByRaw('COALESCE(TRY_CONVERT(datetime, p.created_at), oa.created_at) DESC');

        if ($request->query('format') === 'json') {
            $limit = 5000;
        }

        $records = $query->paginate($limit)->appends($request->query());

        $records->getCollection()->transform(function ($row) {
            $rawLandUse = strtoupper(trim((string) ($row->resolved_land_use ?? '')));
            $applicationType = strtolower(trim((string) ($row->resolved_application_type ?? 'residential')));
            $applicantName = trim((string) ($row->oa_applicant_name ?: ($row->Grantee ?: ($row->source_party_2_name ?: $row->indexed_file_title))));
            // The original holder is the OP allottee — the Grantee of the original
            // Occupancy Permit (OP) allocation row (op_grantee). The Transfer of Title
            // row's Grantor/party_1 is unreliable for these batch change-of-name
            // records: it is stamped with the OP issuer "Kano State Government" rather
            // than the previous holder, so it is only used as a last resort when it
            // actually names a real party (never when it is the government issuer).
            $opAllottee = trim((string) ($row->op_grantee ?: $row->op_party_2));
            $totGrantor = trim((string) ($row->Grantor ?: $row->party_1));
            $originalHolder = $opAllottee;
            if ($originalHolder === '') {
                $originalHolder = trim((string) $row->source_party_2_name);
            }
            // The Grantee of the OP row the commissioning was raised from. Reached by
            // id, so it answers for the files whose OP row still carries its TEMP
            // number and is therefore invisible to every join above — see the
            // source_pra join. The issuer is not a holder, so it is skipped here for
            // the same reason it is skipped on the Transfer of Title row below.
            if ($originalHolder === '') {
                $sourcePraHolder = trim((string) ($row->source_pra_grantee ?: $row->source_pra_party_2));
                if ($sourcePraHolder !== '' && stripos($sourcePraHolder, 'kano state government') === false) {
                    $originalHolder = $sourcePraHolder;
                }
            }
            if ($originalHolder === '' && $totGrantor !== '' && stripos($totGrantor, 'kano state government') === false) {
                $originalHolder = $totGrantor;
            }
            $plotNo = trim((string) ($row->pra_plot_no_raw ?: ($row->ic_plot_number ?: $row->fn_plot_no)));
            $planNo = trim((string) ($row->tp_no ?: ($row->ic_plan_no ?: $row->fn_plan_no)));
            $loc = trim((string) ($row->oa_location ?? ''));
            if ($loc === '' || in_array(strtoupper($loc), ['OTHER', 'OTHERS'])) {
                $loc = trim((string) ($row->pra_location ?? ''));
            }
            if ($loc === '' || in_array(strtoupper($loc), ['OTHER', 'OTHERS'])) {
                $loc = trim((string) ($row->pra_property_description ?? ''));
            }
            if ($loc === '' || in_array(strtoupper($loc), ['OTHER', 'OTHERS'])) {
                $loc = trim((string) ($row->ic_location ?? ''));
            }
            if ($loc === '' || in_array(strtoupper($loc), ['OTHER', 'OTHERS'])) {
                $loc = trim((string) ($row->fn_location ?? ''));
            }
            $location = $loc;
            // Same fallback ladder as location: the application's own captured
            // district first, then the registry rows joined above. 'OTHER' is the
            // location builder's placeholder, not a district, so keep looking.
            $dist = '';
            foreach ([$row->district ?? '', $row->pra_district ?? '', $row->fi_district ?? '', $row->ic_district ?? '', $row->fn_district ?? ''] as $candidate) {
                $candidate = trim((string) $candidate);
                if ($candidate !== '' && !in_array(strtoupper($candidate), ['OTHER', 'OTHERS'], true)) {
                    $dist = $candidate;
                    break;
                }
            }
            $fileNo = trim((string) ($row->file_no ?: ($row->temp_fileno ?: $row->fn_temp_fileno)));
            $createdAt = $row->pra_created_at ?: $row->oa_created_at;
            $workflowRecordId = $row->source_instrument_capture_id ?: $row->oa_instrument_capture_id ?: $row->pra_id;

            $passportPhotoUrl = null;
            if (!empty($row->passport_photo)) {
                $path = (string) $row->passport_photo;
                if (str_starts_with($path, 'public/')) {
                    $path = substr($path, 7);
                }
                $passportPhotoUrl = asset('storage/' . $path);
            }

            return (object) array_merge((array) $row, [
                'id' => $workflowRecordId,
                'workflow_record_id' => $workflowRecordId,
                'oss_application_id' => $row->oss_application_id ?: null,
                'pra_id' => $row->pra_id,
                'application_type' => $applicationType,
                'status' => $row->oa_status ?: LandsOneStopShopApplication::STATUS_PENDING,
                'applicant_name' => $applicantName !== '' ? strtoupper($applicantName) : '—',
                'address' => $row->oa_address ?: $row->source_party_2_address,
                'residential_address' => $row->oa_residential_address ?: $row->source_party_2_address,
                'correspondence_address' => $row->oa_correspondence_address ?: $row->source_party_2_address,
                'phone' => $row->oa_phone ?: $row->source_party_2_phone,
                'file_no' => $fileNo !== '' ? strtoupper($fileNo) : '—',
                'plot_no' => $plotNo !== '' ? strtoupper($plotNo) : '—',
                'plan_no' => $planNo !== '' ? strtoupper($planNo) : '—',
                'location' => $location !== '' ? strtoupper($location) : '—',
                'district' => $dist !== '' ? strtoupper($dist) : '—',
                'created_at' => $createdAt,
                'captured_by_name' => trim((string) ($row->oa_captured_by_name ?: $row->pra_created_by_name)) ?: '—',
                'passport_photo_url' => $passportPhotoUrl,
                'ic_op_serial_number' => $row->ic_op_serial_number,
                'pra_op_serial_number' => $row->op_serial_number,
                'ic_original_holder' => $row->source_party_1_name,
                'pra_original_holder' => $originalHolder !== '' ? strtoupper($originalHolder) : '—',
                'ic_plot_number' => $row->ic_plot_number,
                'pra_plot_no' => $row->pra_plot_no_raw,
                'fn_plot_no' => $row->fn_plot_no,
                'ic_plan_no' => $row->ic_plan_no,
                'pra_plan_no' => $row->tp_no,
                'fn_plan_no' => $row->fn_plan_no,
                'ic_location' => $row->ic_location,
                'pra_location' => $row->pra_location ?: $row->pra_property_description,
                'fn_location' => $row->fn_location,
                'temp_fileno' => $row->temp_fileno ?: $row->fn_temp_fileno,
                'source_instrument_capture_id' => $row->source_instrument_capture_id,
                'op_source' => $row->pra_instrument_type,
                'land_use' => $rawLandUse,
            ]);
        });

        if ($request->query('format') === 'json') {
            $columns = [
                ['key' => 'sn', 'label' => 'S/N'],
                ['key' => 'pra_op_serial_number', 'label' => 'OP SERIAL NO'],
                ['key' => 'file_no', 'label' => 'FILE NO'],
                ['key' => 'pra_original_holder', 'label' => 'ORIGINAL HOLDER'],
                ['key' => 'applicant_name', 'label' => 'APPLICANT NAME'],
                ['key' => 'application_type', 'label' => 'APPLICATION TYPE'],
                ['key' => 'plot_no', 'label' => 'PLOT'],
                ['key' => 'district', 'label' => 'DISTRICT'],
                ['key' => 'location', 'label' => 'LOCATION'],
                ['key' => 'captured_by_name', 'label' => 'COMMISSIONED BY'],
                ['key' => 'created_at', 'label' => 'DATE CREATED'],
            ];

            $rows = $records->getCollection()->values()->map(function($r, $i) {
                $arr = (array) $r;
                $arr['sn'] = $i + 1;
                return $arr;
            })->all();

            return response()->json([
                'success' => true,
                'columns' => $columns,
                'data'    => $rows,
                'count'   => count($rows),
            ]);
        }

        $states = DB::connection('sqlsrv')->table('States')->orderBy('StateName')->get();
        $lgas = DB::connection('sqlsrv')->table('lgas')->where('is_active', 1)->orderBy('name')->get();
        $districts = DB::connection('sqlsrv')->table('districts')->where('is_active', 1)->orderBy('name')->get();
        $streetNames = StreetName::orderBy('name')->get(['id', 'name'])->toBase();

        // Count from oss_applications (the authoritative CoN list) so the cards
        // reflect all applications, not just those with a pra Transfer of Title row.
        $cardCountRows = DB::connection('sqlsrv')
            ->table('oss_applications')
            ->selectRaw("
                COUNT(*) as total_count,
                COUNT(CASE WHEN application_type = 'residential' THEN 1 END) as residential_count,
                COUNT(CASE WHEN application_type = 'commercial'  THEN 1 END) as commercial_count,
                COUNT(CASE WHEN application_type = 'industrial'  THEN 1 END) as industrial_count,
                COUNT(CASE WHEN application_type = 'agricultural' THEN 1 END) as agricultural_count
            ")
            ->where('system_source', 'OSSOPCHANGEOFNAME')
            ->where(function ($q) use ($ossHasIsDeleted) {
                if ($ossHasIsDeleted) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                }
            })
            ->first();

        $cardCounts = [
            'residential' => (int) ($cardCountRows->residential_count ?? 0),
            'commercial' => (int) ($cardCountRows->commercial_count ?? 0),
            'industrial' => (int) ($cardCountRows->industrial_count ?? 0),
            'agricultural' => (int) ($cardCountRows->agricultural_count ?? 0),
        ];
        $cardTotal = (int) ($cardCountRows->total_count ?? 0);

        return view('lands_one_stop_shop.all_applications', [
            'pageTitle' => 'Applications',
            'records' => $records,
            'limit' => $limit,
            'typeFilter' => 'change-of-name',
            'states' => $states,
            'lgas' => $lgas,
            'districts' => $districts,
            'streetNames' => $streetNames,
            'tpNumbers' => $this->ossDistinctTpNumbers(),
            'typeOptions' => LandsOneStopShopApplication::typeOptions(),
            'statusOptions' => LandsOneStopShopApplication::statusOptions(),
            'cardCounts' => $cardCounts,
            'cardTotal' => $cardTotal,
            'isChangeOfNamePage' => true,
            'isNoChangeOfNamePage' => false,
        ]);
    }

    /**
     * Store a new OSS application.
     */
    public function store(Request $request, PraRecordService $praService): JsonResponse
    {
        $this->normalizeOccupancyPermitInput($request);

        $validator = Validator::make($request->all(), $this->validationRules(), $this->passportValidationMessages());

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['captured_by'] = Auth::id();
        $data['status'] = LandsOneStopShopApplication::STATUS_PENDING;

        // Server-side duplicate file number check
        $fileNo = trim((string) ($data['file_no'] ?? ''));
        if ($fileNo) {
            $existing = LandsOneStopShopApplication::where('file_no', $fileNo)->first();
            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'An application with file number ' . $fileNo . ' already exists (Applicant: ' . ($existing->applicant_name ?? '—') . ').',
                ], 422);
            }
        }

        if (($data['system_source'] ?? null) === 'OSSOPCHANGEOFNAME' && $fileNo !== '') {
            if (!$this->fileHasOccupancyPermit($fileNo, $praService)) {
                return response()->json([
                    'success' => false,
                    'message' => 'The selected file number cannot proceed on the Change of Name page because it is not linked to any Occupancy Permit (OP) record in PRA.',
                ], 422);
            }

            // OP Serial Number is mandatory on the Change of Name page.
            if (trim((string) $request->input('op_serial_number', '')) === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'OP Serial Number is required. Enter it in the "Occupancy Permit Details" section.',
                ], 422);
            }
        }

        // Handle passport photo upload into EDMS Lands Registry folder by selected file number.
        if ($request->hasFile('passport_photo')) {
            $selectedFileNo = (string) ($data['file_no'] ?? $request->input('file_no', ''));
            $data['passport_photo'] = $this->storeOssPassportPhoto(
                $request->file('passport_photo'),
                $selectedFileNo
            );
        }

        // Remove passport_photo from data if it's the UploadedFile (already handled)
        if (isset($data['passport_photo']) && $data['passport_photo'] instanceof \Illuminate\Http\UploadedFile) {
            unset($data['passport_photo']);
        }

        // `occupancy_permit` is not a column on oss_applications — it belongs to
        // the PRA OP row and is synced separately below.
        unset($data['occupancy_permit'], $data['has_occupancy_permit'], $data['op_from_record']);

        $record = LandsOneStopShopApplication::create($data);

        $this->syncOccupancyPermitToPra($request, (string) ($record->file_no ?? ''));

        return response()->json([
            'success' => true,
            'message' => 'Application created successfully.',
            'data' => $record,
        ]);
    }

    /**
     * Show a single application.
     */
    public function show(int $id): JsonResponse
    {
        $record = LandsOneStopShopApplication::findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Application retrieved.',
            'data' => $record,
        ]);
    }

    /**
     * Update an existing application.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $record = LandsOneStopShopApplication::findOrFail($id);

        $this->normalizeOccupancyPermitInput($request);

        $rules = $this->validationRules();
        // On update, application_type and applicant_name are optional
        $rules['application_type'] = 'sometimes|required|in:residential,commercial,industrial,agricultural';
        $rules['applicant_name'] = 'sometimes|required|string|max:255';
        $rules['status'] = 'nullable|in:pending,processing,approved,rejected';
        // On update, passport photo is only validated if a new file is uploaded
        $rules['passport_photo'] = 'nullable|image|mimes:jpeg,jpg,png|max:2048|dimensions:min_width=150,min_height=150,max_width=2000,max_height=2000';

        $validator = Validator::make($request->all(), $rules, $this->passportValidationMessages());

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['updated_by'] = Auth::id();

        // Handle passport photo upload into EDMS Lands Registry folder by selected file number.
        if ($request->hasFile('passport_photo')) {
            $selectedFileNo = (string) ($data['file_no'] ?? $record->file_no ?? $request->input('file_no', ''));
            $data['passport_photo'] = $this->storeOssPassportPhoto(
                $request->file('passport_photo'),
                $selectedFileNo
            );
        }

        // Remove passport_photo from data if it's the UploadedFile (already handled)
        if (isset($data['passport_photo']) && $data['passport_photo'] instanceof \Illuminate\Http\UploadedFile) {
            unset($data['passport_photo']);
        }

        unset($data['occupancy_permit'], $data['has_occupancy_permit'], $data['op_from_record']);

        // The form always posts op_serial_number, blank included — when the OP
        // lookup returns nothing the input is empty, and writing that through
        // would erase a serial the record already holds.
        if (array_key_exists('op_serial_number', $data)
            && trim((string) $data['op_serial_number']) === ''
            && trim((string) ($record->op_serial_number ?? '')) !== '') {
            unset($data['op_serial_number']);
        }

        $record->update($data);

        $this->syncOccupancyPermitToPra($request, (string) ($record->file_no ?? ''));

        return response()->json([
            'success' => true,
            'message' => 'Application updated successfully.',
            'data' => $record->fresh(),
        ]);
    }

    /**
     * Delete an application.
     */
    public function destroy(int $id): JsonResponse
    {
        $record = LandsOneStopShopApplication::findOrFail($id);

        if ($record->status === LandsOneStopShopApplication::STATUS_APPROVED) {
            return response()->json([
                'success' => false,
                'message' => 'Approved applications cannot be deleted.',
            ], 403);
        }

        $record->update([
            'is_deleted' => 1,
            'deleted_by' => Auth::id(),
            'deleted_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Application deleted successfully.',
        ]);
    }

    /**
     * Check if a file number already has an application (duplicate check).
     */
    public function checkDuplicate(Request $request): JsonResponse
    {
        $fileNo = trim((string) $request->input('file_no', ''));

        if (!$fileNo) {
            return response()->json(['duplicate' => false]);
        }

        $existing = LandsOneStopShopApplication::where('file_no', $fileNo)->first();

        if ($existing) {
            return response()->json([
                'duplicate' => true,
                'existing_applicant' => $existing->applicant_name,
                'existing_type' => ucfirst($existing->application_type ?? ''),
                'existing_date' => $existing->created_at
                    ? \Carbon\Carbon::parse($existing->created_at)->format('M d, Y')
                    : '—',
            ]);
        }

        return response()->json(['duplicate' => false]);
    }

    /**
     * Store OSS passport photo in EDMS Lands Registry path:
     * storage/app/public/EDMS/SCAN_UPLOAD/Lands_Registry/{FILE_NO}
     *
     * Existing folders/files are preserved; each upload gets a unique file name.
     */
    private function storeOssPassportPhoto(UploadedFile $file, ?string $selectedFileNo): string
    {
        $rawFileNo = strtoupper(trim((string) $selectedFileNo));
        $folderFileNo = $rawFileNo !== '' ? $rawFileNo : 'UNASSIGNED';
        $folderFileNo = preg_replace('/[^A-Z0-9\-_]/', '-', $folderFileNo);
        $folderFileNo = trim((string) $folderFileNo, '-');
        if ($folderFileNo === '') {
            $folderFileNo = 'UNASSIGNED';
        }

        $directory = 'EDMS/SCAN_UPLOAD/Lands_Registry/' . $folderFileNo . '/A4';
        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: 'jpg'));
        $filename = 'passport_' . now()->format('Ymd_His') . '_' . Str::random(8) . '.' . $extension;

        return $file->storeAs($directory, $filename, 'public');
    }

    /**
     * Build a web-accessible URL for a stored passport photo path.
     * Tolerates both legacy paths saved with a leading "public/" prefix and
     * new paths stored relative to the 'public' disk (storage/app/public).
     */
    private function ossPassportUrl(?string $path): string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return '';
        }
        if (str_starts_with($path, 'public/')) {
            $path = substr($path, 7);
        }
        return asset('storage/' . ltrim($path, '/'));
    }

    /**
     * Generate a bill for an OSS application.
     * Saves to the `billing` table with source = OSS_OP_APPLICATION_FEE.
     *
     * Works for both:
     *  - oss_applications records (uses application_type)
     *  - instrument_capture / OP Resettlement records (land_use passed via request)
     */
    public function bill(Request $request, int $id): JsonResponse
    {
        // Check if a bill already exists for this source + id
        $existing = Billing::where('source', 'OSS_OP_APPLICATION_FEE')
            ->where('source_id', $id)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'A bill has already been generated for this application.',
            ], 409);
        }

        // Determine type: prefer request land_use (OP Resettlement), fall back to oss_applications record
        $type = null;
        if ($request->filled('land_use')) {
            $type = strtolower(trim($request->input('land_use')));
        } else {
            $application = LandsOneStopShopApplication::find($id);
            $type = $application ? $application->application_type : 'residential';
        }

        // Bill amounts per application type
        $billRates = [
            'residential' => ['Change_of_Name' => 25000, 'Processing_Fee' => 3000, 'survey_fee' => 10000, 'Application_Form' => 2000],
            'commercial' => ['Change_of_Name' => 25000, 'Processing_Fee' => 20000, 'survey_fee' => 20000, 'Application_Form' => 10000],
            'industrial' => ['Change_of_Name' => 25000, 'Processing_Fee' => 30000, 'survey_fee' => 30000, 'Application_Form' => 20000],
            'agricultural' => ['Change_of_Name' => 25000, 'Processing_Fee' => 3000, 'survey_fee' => 10000, 'Application_Form' => 2000],
        ];

        $rates = $billRates[$type] ?? $billRates['residential'];

        $refId = $this->generateOssBillRefId();

        $bill = Billing::create([
            'Change_of_Name' => $rates['Change_of_Name'],
            'Processing_Fee' => $rates['Processing_Fee'],
            'survey_fee' => $rates['survey_fee'],
            'Application_Form' => $rates['Application_Form'],
            'source' => 'OSS_OP_APPLICATION_FEE',
            'source_id' => $id,
            'ref_id' => $refId,
            'Payment_Status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Bill generated successfully.',
            'data' => $bill,
        ]);
    }

    /**
     * Check whether an OSS bill already exists for an application/source ID.
     */
    public function billStatus(int $id): JsonResponse
    {
        $bill = Billing::where('source', 'OSS_OP_APPLICATION_FEE')
            ->where('source_id', $id)
            ->orderByDesc('ID')
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Bill status fetched successfully.',
            'data' => [
                'exists' => (bool) $bill,
                'bill_id' => $bill->ID ?? null,
                'source_id' => $id,
            ],
        ]);
    }

    private function generateOssBillRefId(): string
    {
        $prefix = 'OSS-1-';

        $latest = Billing::where('ref_id', 'LIKE', "{$prefix}%")
            ->orderByRaw("TRY_CAST(REPLACE(ref_id, '{$prefix}', '') AS INT) DESC")
            ->value('ref_id');

        $next = 1;
        if ($latest) {
            $number = (int) str_replace($prefix, '', $latest);
            $next = max(1, $number + 1);
        }

        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Print the OP Acknowledgement form (opens in new tab).
     * The $id corresponds to instrument_capture.id (the capture_id used by both controllers).
     */
    public function printAcknowledgement(int $id): View
    {
        // 1) Primary path: ID is instrument_capture.id
        $row = DB::connection('sqlsrv')
            ->table('instrument_capture as ic')
            ->leftJoin('deed_registrations as dr', 'dr.instrument_capture_id', '=', 'ic.id')
            ->where('ic.id', $id)
            ->select([
                'ic.id',
                'ic.party_1_name',
                'ic.party_2_name',
                'ic.plot_number',
                'ic.survey_plan_no',
                'ic.tp_no',
                'ic.op_serial_number',
                'ic.property_description',
                'ic.district',
                'ic.lga',
                'ic.created_at',
                'dr.registration_number',
            ])
            ->first();

        // 2) Fallback path: ID is fileNumber.id (OP resettlement/applications listing row)
        if (!$row) {
            $row = DB::connection('sqlsrv')
                ->table('fileNumber as fn')
                ->leftJoin('mls_file_no as mfn', 'fn.tracking_id', '=', 'mfn.tracking_id')
                ->leftJoin('instrument_capture as ic', 'mfn.source_instrument_capture_id', '=', 'ic.id')
                ->leftJoin('deed_registrations as dr', 'dr.instrument_capture_id', '=', 'ic.id')
                ->where('fn.id', $id)
                ->select([
                    DB::raw('COALESCE(ic.id, fn.id) as id'),
                    'ic.party_1_name',
                    'ic.party_2_name',
                    DB::raw('COALESCE(ic.plot_number, fn.plot_no) as plot_number'),
                    DB::raw('COALESCE(ic.survey_plan_no, ic.tp_no, ic.op_serial_number, fn.tp_no, fn.mlsfNo) as survey_plan_no'),
                    DB::raw('COALESCE(ic.property_description, fn.location) as property_description'),
                    DB::raw('COALESCE(ic.created_at, fn.created_at) as created_at'),
                    'dr.registration_number',
                    'fn.FileName as fallback_file_title',
                ])
                ->first();
        }

        abort_unless($row, 404, 'Application not found.');

        $createdAt = $row->created_at ? Carbon::parse($row->created_at) : null;

        // Build a simple object matching the Blade template expectations
        $record = (object) [
            'applicant_name' => strtoupper(
                collect([$row->party_1_name, $row->party_2_name])
                    ->filter()
                    ->implode(' / ')
            ) ?: strtoupper((string) ($row->fallback_file_title ?? '—')),
            'plot_no' => strtoupper($row->plot_number ?? ''),
            'plan_no' => strtoupper($row->survey_plan_no ?? $row->tp_no ?? $row->op_serial_number ?? ''),
            'location' => strtoupper($row->property_description ?? ''),
            'date_captured' => $createdAt ? strtoupper($createdAt->format('M d, Y')) : '',
            'time_captured' => $createdAt ? strtoupper($createdAt->format('g:i A')) : '',
        ];

        return view('lands_one_stop_shop.partials.print_acknowledgement', compact('record'));
    }

    /**
     * Print the Recommendation for Grant of Statutory Right of Occupancy.
     * Data comes from the modal form fields via POST (not yet persisted).
     */
    public function printRecommendation(Request $request)
    {
        $fileRef = strtoupper(trim((string) $request->input('file_ref', '')));
        $recordId = (int) $request->input('record_id', 0);

        $pickFilled = static function (array $values): string {
            foreach ($values as $value) {
                $text = trim((string) $value);
                if ($text !== '') {
                    return $text;
                }
            }

            return '';
        };

        $formatNaira = static function ($value): string {
            $raw = trim((string) $value);
            if ($raw === '') {
                return '';
            }

            if (preg_match('/^to\s*follow$/i', $raw)) {
                return 'TO FOLLOW';
            }

            $normalized = preg_replace('/[^0-9.\-]/', '', $raw);
            if ($normalized === '' || !is_numeric($normalized)) {
                return strtoupper($raw);
            }

            return '₦' . number_format((float) $normalized, 2);
        };

        $plotNo = strtoupper(trim((string) $request->input('plot_no', '')));
        $planNo = strtoupper(trim((string) $request->input('plan_no', '')));
        $location = trim((string) $request->input('location', ''));

        // Safety fallback: if modal missed mapped values, resolve from the source application record.
        if (($plotNo === '' || $planNo === '' || $location === '') && $recordId > 0) {
            $row = DB::connection('sqlsrv')
                ->table('oss_applications as oa')
                ->leftJoin('instrument_capture as ic', 'ic.id', '=', 'oa.instrument_capture_id')
                ->where('oa.id', $recordId)
                ->select([
                    'oa.plot_no',
                    'oa.plan_no',
                    'oa.location',
                    'ic.plot_number as ic_plot_no',
                    'ic.survey_plan_no as ic_plan_no',
                    'ic.tp_no as ic_tp_no',
                    'ic.op_serial_number as ic_op_serial_number',
                    'ic.property_description as ic_location',
                    'ic.district as ic_district',
                ])
                ->first();

            if ($row) {
                if ($plotNo === '') {
                    $plotNo = strtoupper($pickFilled([
                        $row->plot_no ?? '',
                        $row->ic_plot_no ?? '',
                    ]));
                }

                if ($planNo === '') {
                    $planNo = strtoupper($pickFilled([
                        $row->plan_no ?? '',
                        $row->ic_plan_no ?? '',
                        $row->ic_tp_no ?? '',
                        $row->ic_op_serial_number ?? '',
                    ]));
                }

                if ($location === '') {
                    $location = $pickFilled([
                        $row->location ?? '',
                        $row->ic_location ?? '',
                        $row->ic_district ?? '',
                    ]);
                }
            }
        }

        // Last word on the location, and the same word the official print gets.
        //
        // The card's Location box is readonly and filled from the register, so
        // what the modal posts is already the register's answer — but only this
        // route is reached when the modal's mapping misses, and a proof that
        // resolved the location by one ladder while the document resolved it by
        // another is how the two sheets came to name different places. Both climb
        // the shared one now; what the modal posted stands as the fallback.
        $location = OssRecommendationLocation::resolve($fileRef, $location);

        $trackingId = '';
        $rofoSerialNo = '';
        $recommendationId = 0;
        if ($fileRef) {
            $indexing = DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where('file_number', $fileRef)
                ->whereNotNull('tracking_id')
                ->value('tracking_id');
            $trackingId = (string) ($indexing ?? '');

            $recRow = DB::connection('sqlsrv')
                ->table('land_recommendations')
                ->whereRaw('UPPER(file_number) = ?', [$fileRef])
                ->orderByDesc('id')
                ->select(['id', 'land_rofo_serial_no'])
                ->first();
            $recommendationId = $recRow ? (int) $recRow->id : 0;
            $rofoSerialNo = (string) ($recRow->land_rofo_serial_no ?? '');
        }

        $record = (object) [
            'id' => $recommendationId,
            'applicant_name' => strtoupper((string) $request->input('applicant_name', '')),
            'file_ref' => $fileRef,
            'purpose' => strtoupper((string) $request->input('purpose', '')),
            'location' => $location,
            'plot_no' => $plotNo,
            'plan_no' => $planNo,
            'term' => (string) $request->input('term', ''),
            'dev_value' => $formatNaira($request->input('dev_value', '')),
            'completion_time' => (string) $request->input('completion_time', ''),
            'ground_rent' => $formatNaira($request->input('ground_rent', '')),
            'dev_charge' => $formatNaira($request->input('dev_charge', '')),
            'survey_charges' => $formatNaira($request->input('survey_charges', '')),
            'director_reasons' => (string) $request->input('director_reasons', ''),
            'director_sign' => (string) $request->input('director_sign', ''),
            'director_date' => (string) $request->input('director_date', ''),
            'ps_plot' => strtoupper((string) $request->input('ps_plot', '')),
            'ps_location' => strtoupper((string) $request->input('ps_location', '')),
            'ps_sign' => (string) $request->input('ps_sign', ''),
            'ps_date' => (string) $request->input('ps_date', ''),
            'commissioner_name' => (string) $request->input('commissioner_name', ''),
            'commissioner_date' => (string) $request->input('commissioner_date', ''),
            'approval_status' => (string) $request->input('approval_status', ''),
            'tracking_id' => $trackingId,
            'rofo_serial_no' => $rofoSerialNo,
        ];

        // The Recommendation Form prints a WHITE COPY, never the official document.
        //
        // What this route renders is whatever is in the form's fields at the moment
        // the button is pressed — typed, possibly unsaved, and not yet read by
        // anyone. That is a draft by definition, and it is exactly what the proofing
        // stage is for: run it off, read it against the file, correct the form,
        // print it again.
        //
        // The official recommendation stays where every other official print is —
        // behind the Print Manager, which asks whether this proof has been read
        // before it puts a serial on paper. Printing from here therefore mints no
        // serial and logs nothing; see the template's $isWhiteCopy branches.
        view()->share('isWhiteCopy', true);

        // The proofing stage, recorded: the board's Recommendation tile opens on the
        // strength of it and this button closes with it.
        \App\Models\PrintLog::logWhiteCopy('Recommendation', $fileRef, Auth::id());

        // A proof should be a file the officer can keep with the recommendation,
        // not an HTML print dialog that can accidentally be treated as an official
        // print.  This route is used only by the OSS Recommendation Form's White
        // Copy button; the official print remains on its own controlled route.
        $safeFileRef = preg_replace('/[^A-Z0-9._-]+/i', '-', $fileRef) ?: 'OSS-RECOMMENDATION';

        return Pdf::loadView('lands_one_stop_shop.partials.print_recommendation', compact('record'))
            ->setPaper('a4', 'portrait')
            ->download('OSS-Recommendation-White-Copy-' . $safeFileRef . '.pdf');
    }

    /**
     * Fetch existing recommendation by file reference.
     * Used by OSS recommendation modal to decide Save/Print state.
     */
    public function recommendationStatus(Request $request): JsonResponse
    {
        $fileRef = strtoupper(trim((string) $request->query('file_ref', '')));

        if ($fileRef === '') {
            return response()->json([
                'success' => false,
                'message' => 'File reference is required.',
            ], 422);
        }

        $recommendation = LandRecommendation::query()
            ->whereRaw('UPPER(file_number) = ?', [$fileRef])
            ->orderByDesc('id')
            ->first();

        if (!$recommendation) {
            return response()->json([
                'success' => true,
                'message' => 'No recommendation found.',
                'data' => [
                    'exists' => false,
                ],
            ]);
        }

        // land_rofo_serial_no is NOT touched here, and must not be. It is the
        // security paper code, and the only thing entitled to write it is the
        // "Enter Security Paper Code" card on the RofO screen
        // (LandRofoController@assignSecurityPaperCode), which takes a real sheet
        // out of the global_security_paper_codes pool and records who took it.
        //
        // This endpoint used to mint a 5-digit number into it when it found the
        // column empty - from a GET, fired by the recommendation modal merely
        // opening. Files ended up displaying a "security paper code" nobody had
        // entered and no sheet existed for. See the note in saveRecommendation().

        // Whether an official copy has already been run off. Read from the print
        // log rather than from any column: the recommendation is printed under
        // several document types depending on where it was printed from, and all of
        // them mean the same thing to an officer holding the paper.
        //
        // The board uses it to close off the White Copy. A proof is read BEFORE a
        // document is issued; offering one for a recommendation already in the
        // applicant's hand is offering to proofread the past.
        $printed = !empty(\App\Models\PrintLog::printedAnyhowSinceReset(
            'Recommendation For Grant',
            [$recommendation->file_number],
            false
        )) || !empty(\App\Models\PrintLog::printedAnyhowSinceReset(
            'OSS Recommendation For Grant',
            [$recommendation->file_number],
            false
        )) || !empty(\App\Models\PrintLog::printedAnyhowSinceReset(
            'Land Recommendation',
            [$recommendation->file_number],
            false
        ));

        // Whether the proof has been run off. The Recommendation tile opens on the
        // strength of it; the White Copy tile closes with it.
        $whiteCopyDone = !empty(\App\Models\PrintLog::whiteCopyPrinted(
            'Recommendation',
            [$recommendation->file_number]
        ));

        return response()->json([
            'success' => true,
            'message' => 'Recommendation found.',
            'data' => [
                'exists' => true,
                'printed' => $printed,
                'white_copy_done' => $whiteCopyDone,
                'id' => $recommendation->id,
                'file_ref' => (string) ($recommendation->file_number ?? ''),
                'term' => (string) ($recommendation->term ?? ''),
                'dev_value' => (string) ($recommendation->development_value ?? ''),
                'completion_time' => (string) ($recommendation->development_period ?? ''),
                'ground_rent' => (string) ($recommendation->ground_rent ?? ''),
                'dev_charge' => (string) ($recommendation->development_charge ?? ''),
                'survey_charges' => (string) ($recommendation->survey_fees ?? ''),
                'director_reasons' => (string) ($recommendation->recommendation ?? ''),
                'status' => (string) ($recommendation->status ?? ''),
            ],
        ]);
    }

    /**
     * Check whether a verification record exists for a given OP capture record.
     */
    public function verificationStatus(Request $request): JsonResponse
    {
        $recordId = (int) $request->query('record_id', 0);

        if ($recordId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Record ID is required.',
            ], 422);
        }

        $row = DB::connection('sqlsrv')
            ->table('oss_verifications')
            ->where('instrument_capture_id', $recordId)
            ->first();

        if (!$row) {
            return response()->json([
                'success' => true,
                'message' => 'No verification found.',
                'data' => [
                    'exists' => false,
                    'record_id' => $recordId,
                ],
            ]);
        }

        $passportUrl = $this->ossPassportUrl($row->passport_photo ?? '');

        return response()->json([
            'success' => true,
            'message' => 'Verification status fetched successfully.',
            'data' => [
                'exists' => true,
                'record_id' => $recordId,
                'applicant_name' => (string) ($row->applicant_name ?? ''),
                'applicant_address' => (string) ($row->applicant_address ?? ''),
                'applicant_phone' => (string) ($row->applicant_phone ?? ''),
                'applicant_email' => (string) ($row->applicant_email ?? ''),
                'id_type' => (string) ($row->id_type ?? ''),
                'original_allottee' => (string) ($row->original_allottee ?? ''),
                'op_number' => (string) ($row->op_number ?? ''),
                'plot_no' => (string) ($row->plot_no ?? ''),
                'plan_no' => (string) ($row->plan_no ?? ''),
                'location' => (string) ($row->location ?? ''),
                'recommendation' => (string) ($row->recommendation ?? ''),
                'chairman_name' => (string) ($row->chairman_name ?? ''),
                'passport_photo_url' => $passportUrl,
            ],
        ]);
    }

    /**
     * Return the workflow prerequisite status for a Change of Name record.
     * Checks: verification → acknowledgement → land12 → recommendation gates.
     */
    public function workflowStatus(Request $request): JsonResponse
    {
        $recordId = (int) $request->query('record_id', 0);
        $fileNo = trim((string) $request->query('file_no', ''));

        $verificationDone = $recordId > 0
            ? DB::connection('sqlsrv')->table('oss_verifications')
                ->where('instrument_capture_id', $recordId)->exists()
            : false;

        $acknowledgementDone = false;
        if ($recordId > 0) {
            $acknowledgementDone = DB::connection('sqlsrv')->table('oss_acknowledgements')
                ->where('instrument_capture_id', $recordId)->exists();
        }
        if (!$acknowledgementDone && $fileNo) {
            $acknowledgementDone = DB::connection('sqlsrv')->table('oss_acknowledgements')
                ->where('file_no', $fileNo)->exists();
        }

        $land12Exists = false;
        $land12Approved = false;
        if ($fileNo) {
            $land12 = DB::connection('sqlsrv')->table('survey_report_requests')
                ->where('file_number', $fileNo)
                ->orderByDesc('id')
                ->first(['status']);
            if ($land12) {
                $land12Exists = true;
                $land12Approved = ($land12->status === 'Completed');
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'verification_done' => $verificationDone,
                'acknowledgement_done' => $acknowledgementDone,
                'land12_exists' => $land12Exists,
                'land12_approved' => $land12Approved,
            ],
        ]);
    }

    /**
     * Persist an acknowledgement record so the workflow gate can check it.
     */
    public function saveAcknowledgementDb(Request $request): JsonResponse
    {
        $recordId = (int) $request->input('record_id', 0);
        $fileNo = trim((string) $request->input('file_no', ''));
        $sigName = trim((string) $request->input('signature_name', ''));

        if ($recordId <= 0 && !$fileNo) {
            return response()->json(['success' => false, 'message' => 'Record ID or File No is required.'], 422);
        }

        // Upsert — if a record already exists, just return success
        $existing = DB::connection('sqlsrv')->table('oss_acknowledgements')
            ->when($recordId > 0, fn($q) => $q->where('instrument_capture_id', $recordId))
            ->when(!$recordId && $fileNo, fn($q) => $q->where('file_no', $fileNo))
            ->first();

        if (!$existing) {
            DB::connection('sqlsrv')->table('oss_acknowledgements')->insert([
                'instrument_capture_id' => $recordId ?: null,
                'file_no' => $fileNo ?: null,
                'signature_name' => $sigName ?: null,
                'captured_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Acknowledgement saved.']);
    }

    /**
     * Persist recommendation into land_recommendations table.
     */
    public function saveRecommendation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'record_id' => 'nullable|integer',
            'applicant_name' => 'nullable|string|max:255',
            'file_ref' => 'required|string|max:100',
            'purpose' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:500',
            'plot_no' => 'nullable|string|max:100',
            'plan_no' => 'nullable|string|max:100',
            'term' => 'required|string|max:100',
            'dev_value' => 'required|string|max:100',
            'completion_time' => 'required|string|max:100',
            'ground_rent' => 'required|string|max:100',
            'dev_charge' => 'required|string|max:100',
            'survey_charges' => 'required|string|max:100',
            'director_reasons' => 'nullable|string',
            'application_date' => 'nullable|date',
            'applicant_address' => 'nullable|string|max:500',
        ]);

        $fileRef = strtoupper(trim((string) $validated['file_ref']));

        $toNumber = static function ($value) {
            $raw = trim((string) $value);
            if ($raw === '') {
                return null;
            }

            $normalized = preg_replace('/[^0-9.\-]/', '', $raw);
            return $normalized === '' ? null : (float) $normalized;
        };

        // The OSS edit card sends its recommendation id. Use it when present,
        // and always keep this lookup inside the OSS stream: legacy Land and OSS
        // recommendations can share a file number.
        $existing = null;
        if (!empty($validated['record_id'])) {
            // The OSS recommendation register posts land_recommendations.id.
            // The source Applications screen posts its application id instead,
            // so only accept this shortcut when it also belongs to this file.
            $existing = LandRecommendation::query()
                ->where('type', 'OSS')
                ->where('id', $validated['record_id'])
                ->whereRaw('UPPER(file_number) = ?', [$fileRef])
                ->first();
        }

        $existing ??= LandRecommendation::query()
            ->where('type', 'OSS')
            ->whereRaw('UPPER(file_number) = ?', [$fileRef])
            ->orderByDesc('id')
            ->first();

        // No serial is minted here, or anywhere else in OSS.
        //
        // land_rofo_serial_no holds the SECURITY PAPER CODE: a real sheet drawn
        // from the global_security_paper_codes pool by the "Enter Security Paper
        // Code" card on the RofO screen, which marks the sheet used and records
        // who took it. Every code in that pool is 6 digits.
        //
        // OSS used to write its own str_pad(MAX + 1, 5, '0') sequence into the
        // same column - on create, on this existing-record branch, and from
        // recommendationStatus() on a GET. The result was 5-digit values on the
        // RofO listing labelled "Security Paper Code" that no one had entered,
        // that matched no sheet, and that left no assignment trail. The column has
        // one owner now, and OSS is not it: an OSS RofO shows no code until one is
        // entered on that card, which is the same rule Land has always followed.

        $recommendationFields = [
            'applicant_name' => (string) ($validated['applicant_name'] ?? ''),
            'purpose_of_clause' => (string) ($validated['purpose'] ?? ''),
            'location' => (string) ($validated['location'] ?? ''),
            'plot_number' => (string) ($validated['plot_no'] ?? ''),
            'layout_plan_no' => (string) ($validated['plan_no'] ?? ''),
            'term' => (string) ($validated['term'] ?? ''),
            'development_value' => $toNumber($validated['dev_value'] ?? null),
            'development_period' => (string) ($validated['completion_time'] ?? ''),
            'ground_rent' => $toNumber($validated['ground_rent'] ?? null),
            'development_charge' => $toNumber($validated['dev_charge'] ?? null),
            // One canonical mapping: card -> land_recommendations.survey_fees ->
            // OSS white copy / RofO print. Previously the existing-row branch
            // returned success without writing this value at all.
            'survey_fees' => $toNumber($validated['survey_charges'] ?? null),
            'recommendation' => (string) ($validated['director_reasons'] ?? ''),
            'land_use' => (string) ($validated['purpose'] ?? ''),
            'application_date' => $validated['application_date'] ?? null,
            'applicant_address' => $validated['applicant_address'] ?? null,
            'updated_by' => Auth::id(),
        ];

        if ($existing) {
            $recommendation = DB::connection('sqlsrv')->transaction(function () use ($existing, $recommendationFields, $fileRef) {
                $existing->fill($recommendationFields)->save();

                // Keep the originating OSS application in step with its live
                // recommendation, including the Survey & Processing Charges.
                DB::connection('sqlsrv')->table('oss_applications')
                    ->whereRaw('UPPER(file_no) = ?', [$fileRef])
                    ->update([
                        'term_years' => $recommendationFields['term'],
                        'proposed_dev_value' => $recommendationFields['development_value'],
                        'annual_ground_rent' => $recommendationFields['ground_rent'],
                        'development_charge' => $recommendationFields['development_charge'],
                        'survey_processing_charges' => $recommendationFields['survey_fees'],
                        'updated_by' => Auth::id(),
                        'updated_at' => now(),
                    ]);

                return $existing->fresh();
            });

            app(RofoPraSyncer::class)->syncLand($recommendation);

            return response()->json([
                'success' => true,
                'message' => 'OSS recommendation updated successfully.',
                'data' => [
                    'exists' => true,
                    'id' => $recommendation->id,
                    'survey_charges' => (string) ($recommendation->survey_fees ?? ''),
                ],
            ]);
        }

        $recommendation = DB::connection('sqlsrv')->transaction(function () use ($fileRef, $validated, $toNumber, $recommendationFields) {
            return LandRecommendation::create([
                'file_number' => $fileRef,
                ...$recommendationFields,
                'tracking_id' => (string) ($validated['record_id'] ?? ''),
                'status' => LandRecommendation::STATUS_PENDING,
                'rofo_status' => LandRecommendation::ROFO_GENERATED,
                'rofo_generated_at' => now(),
                'type' => 'OSS',
                'created_by' => Auth::id(),
            ]);
        });

        app(RofoPraSyncer::class)->syncLand($recommendation);

        return response()->json([
            'success' => true,
            'message' => 'Recommendation saved successfully.',
            'data' => [
                'exists' => true,
                'id' => $recommendation->id,
            ],
        ]);
    }

    /**
     * Return the saved Change of Ownership record for a given OP capture record,
     * so the modal can backfill on open (mirrors verificationStatus).
     */
    public function changeOfOwnershipStatus(Request $request): JsonResponse
    {
        $recordId = (int) $request->query('record_id', 0);

        if ($recordId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Record ID is required.',
            ], 422);
        }

        $row = DB::connection('sqlsrv')
            ->table('oss_change_of_ownership')
            ->where('instrument_capture_id', $recordId)
            ->first();

        if (!$row) {
            return response()->json([
                'success' => true,
                'message' => 'No change of ownership found.',
                'data' => [
                    'exists' => false,
                    'record_id' => $recordId,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Change of ownership fetched successfully.',
            'data' => [
                'exists' => true,
                'record_id' => $recordId,
                'op_number' => (string) ($row->op_number ?? ''),
                'location' => (string) ($row->location ?? ''),
                'plot_no' => (string) ($row->plot_no ?? ''),
                'plan_no' => (string) ($row->plan_no ?? ''),
                'date_of_issuance' => $row->date_of_issuance ? substr((string) $row->date_of_issuance, 0, 10) : '',
                'original_name' => (string) ($row->original_name ?? ''),
                'original_address' => (string) ($row->original_address ?? ''),
                'original_phone' => (string) ($row->original_phone ?? ''),
                'current_name' => (string) ($row->current_name ?? ''),
                'current_address' => (string) ($row->current_address ?? ''),
                'current_phone' => (string) ($row->current_phone ?? ''),
                'ownership_method' => (string) ($row->ownership_method ?? ''),
            ],
        ]);
    }

    /**
     * Save Change of Ownership form data.
     */
    public function saveChangeOfOwnership(Request $request): JsonResponse
    {
        $request->validate([
            'record_id' => 'required|integer',
            'current_name' => 'required|string|max:255',
        ]);

        $recordId = (int) $request->input('record_id');

        $row = DB::connection('sqlsrv')
            ->table('oss_change_of_ownership')
            ->where('instrument_capture_id', $recordId)
            ->first();

        $data = [
            'instrument_capture_id' => $recordId,
            'op_number' => (string) $request->input('op_number', ''),
            'location' => (string) $request->input('location', ''),
            'plot_no' => (string) $request->input('plot_no', ''),
            'plan_no' => (string) $request->input('plan_no', ''),
            'date_of_issuance' => $request->input('date_of_issuance') ?: null,
            'original_name' => (string) $request->input('original_name', ''),
            'original_address' => (string) $request->input('original_address', ''),
            'original_phone' => (string) $request->input('original_phone', ''),
            'current_name' => (string) $request->input('current_name', ''),
            'current_address' => (string) $request->input('current_address', ''),
            'current_phone' => (string) $request->input('current_phone', ''),
            'ownership_method' => (string) $request->input('ownership_method', ''),
            'updated_at' => now(),
        ];

        if ($row) {
            DB::connection('sqlsrv')
                ->table('oss_change_of_ownership')
                ->where('id', $row->id)
                ->update($data);
        } else {
            $data['captured_by'] = Auth::id();
            $data['created_at'] = now();
            DB::connection('sqlsrv')
                ->table('oss_change_of_ownership')
                ->insert($data);
        }

        return response()->json([
            'success' => true,
            'message' => 'Change of ownership saved successfully.',
        ]);
    }

    /**
     * Save FFR Change of Name flow.
     * Appends PRA timeline row and synchronizes holder/name fields in indexing and customer tables.
     */
    public function saveFfrChangeOfName(Request $request, PraRecordService $praService): JsonResponse
    {
        $validated = $request->validate([
            'source_file_no' => 'required|string|max:120',
            'party_1' => 'nullable|string|max:255',
            'party_2' => 'required|string|max:255',
            'residence_address' => 'nullable|string|max:500',
            'phone' => ['nullable', 'string', 'max:50', new NigerianPhone()],
            'prop_id' => 'nullable',
            'op_serial_number' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:500',
            'land_use' => 'nullable|string|max:150',
        ]);

        $sourceFileNo = trim((string) $validated['source_file_no']);
        $newParty2 = trim((string) $validated['party_2']);
        $resolvedCustomerType = $this->resolveCustomerTypeFromName($newParty2);

        Log::info('FFR save initiated', [
            'source_file_no' => $sourceFileNo,
            'party_1' => (string) ($validated['party_1'] ?? ''),
            'party_2' => $newParty2,
            'resolved_customer_type' => $resolvedCustomerType,
            'user_id' => Auth::id(),
        ]);

        $sourceHistory = $praService->findAllByFileNumber($sourceFileNo);
        if (empty($sourceHistory)) {
            Log::warning('FFR save aborted: no PRA source history found', [
                'source_file_no' => $sourceFileNo,
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No PRA record found for selected source file number.',
            ], 404);
        }

        $opRecord = $this->resolveMotherOpRecord($sourceHistory);

        if (!$opRecord) {
            Log::warning('FFR save aborted: source history has no OP record', [
                'source_file_no' => $sourceFileNo,
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Source file does not contain an OP record in PRA history.',
            ], 422);
        }

        $partyFromOp = trim((string) (
            $this->firstFilledValue(['party_1' => $validated['party_1'] ?? null], ['party_1'])
            ?? $this->firstFilledValue($opRecord, ['parties.party_2', 'party_2', 'Grantee', 'grantee'])
            ?? ''
        ));

        if ($partyFromOp === '') {
            Log::warning('FFR save aborted: party_1 unresolved from OP record', [
                'source_file_no' => $sourceFileNo,
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to resolve Party 1 from OP record. Please provide Party 1.',
            ], 422);
        }

        // Safety net: this is always a Transfer of Title row, so Party 1 must be
        // the previous holder — "Kano State Government" is only ever valid as an
        // OP's Grantor, never a ToT's. Refuse rather than silently corrupt.
        if (strcasecmp($partyFromOp, 'Kano State Government') === 0) {
            Log::error('FFR save blocked: resolved Party 1 is Kano State Government', [
                'source_file_no' => $sourceFileNo,
                'request_party1' => $validated['party_1'] ?? null,
                'op_record_id' => data_get($opRecord, 'id'),
                'op_record_party_2' => data_get($opRecord, 'party_2'),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to resolve the original holder for this Transfer of Title. Please provide Party 1 manually and try again.',
            ], 422);
        }

        $propId = $validated['prop_id']
            ?? data_get($opRecord, 'prop_id')
            ?? data_get($sourceHistory, '0.prop_id');

        $inheritedRegNo = $this->firstFilledValue(
            $opRecord,
            ['regNo', 'reg_no', 'registration_number', 'deeds_serial_no']
        );
        $inheritedRegDate = $this->firstFilledValue(
            $opRecord,
            ['reg_date', 'regDate', 'deeds_date', 'transaction_date']
        );
        $inheritedRegTime = $this->firstFilledValue(
            $opRecord,
            ['reg_time', 'regTime', 'deeds_time', 'transaction_time']
        );

        // If holder/allottee name does not change, this is not a transfer.
        //
        // Shared with MLS/MLPP commissioning, which runs the same test to keep an OP
        // in the right stream. This used to lowercase and collapse whitespace only,
        // so "ALH. MUSA ABDULLAHI" -> "Musa Abdullahi" was written here as a Transfer
        // of Title while MLS called the same pair no change of ownership.
        $hasNameChange = \App\Support\HolderName::changed($partyFromOp, $newParty2);

        $resolvedTransactionType = $hasNameChange
            ? 'Transfer of Title (OP)'
            : 'Occupancy Permit (OP)';

        $resolvedInstrumentType = $hasNameChange
            ? 'Transfer of Title (OP)'
            : 'Occupancy Permit (OP)';

        // When the resolved instrument type is OP (no name change), check if the
        // OP already exists in instrument_capture / deed_registrations.  If it
        // does, link the source record instead of creating a duplicate PRA row.
        $skipPraCreate = false;
        if (!$hasNameChange) {
            $existingOp = $praService->findExistingOpInSource([
                'op_serial_number' => $validated['op_serial_number'] ?? data_get($opRecord, 'op_serial_number'),
                'mlsFNo' => $sourceFileNo,
                'fileno' => $sourceFileNo,
            ]);

            if ($existingOp) {
                $linkPropId = $propId ?: ($existingOp['prop_id'] ?? null);
                if (empty($linkPropId)) {
                    $linkPropId = app(\App\Services\PropertyIdAllocationService::class)
                        ->allocateOrRetrievePropId($sourceFileNo, $sourceFileNo, null, null, ['allow_temp_only' => true]);
                }
                $praService->linkSourceOpRecord($existingOp, [
                    'prop_id' => $linkPropId,
                    'mlsFNo' => $sourceFileNo,
                ]);
                $skipPraCreate = true;

                Log::info('FFR save: OP exists in IC/DR — skipping PRA creation, linked source', [
                    'source_table' => $existingOp['_source_table'],
                    'source_id' => $existingOp['id'],
                    'prop_id' => $linkPropId,
                ]);
            }
        }

        Log::info('FFR save transaction resolution', [
            'source_file_no' => $sourceFileNo,
            'party_from_op' => $partyFromOp,
            'new_party_2' => $newParty2,
            'has_name_change' => $hasNameChange,
            'resolved_transaction_type' => $resolvedTransactionType,
            'resolved_instrument_type' => $resolvedInstrumentType,
            'inherited_reg_no' => $inheritedRegNo,
            'inherited_reg_date' => $inheritedRegDate,
            'inherited_reg_time' => $inheritedRegTime,
            'user_id' => Auth::id(),
        ]);

        $praPayload = [
            'prop_id' => $propId,
            'mlsFNo' => $sourceFileNo,
            'fileno' => $sourceFileNo,
            'transaction_type' => $resolvedTransactionType,
            'instrument_type' => $resolvedInstrumentType,
            'op_serial_number' => $validated['op_serial_number'] ?? data_get($opRecord, 'op_serial_number'),
            'regNo' => $inheritedRegNo,
            'reg_date' => $inheritedRegDate,
            'reg_time' => $inheritedRegTime,
            'land_use' => $validated['land_use'] ?? data_get($opRecord, 'land_use'),
            'location' => $validated['location'] ?? data_get($opRecord, 'location') ?? data_get($opRecord, 'property_description'),
            'property_description' => $validated['location'] ?? data_get($opRecord, 'property_description') ?? data_get($opRecord, 'location'),
            'plot_no' => data_get($opRecord, 'plot_no') ?? data_get($opRecord, 'plot_number'),
            'tp_no' => data_get($opRecord, 'tp_no'),
            'lgsaOrCity' => data_get($opRecord, 'lga') ?? data_get($opRecord, 'lgsaOrCity'),
            'source' => 'FFR Change of Name',
            'system_source' => 'OSSOPCHANGEOFNAME',

            'Grantor' => $partyFromOp,
            'Grantee' => $newParty2,
            'party_1' => $partyFromOp,
            'party_2' => $newParty2,
            'parties' => [
                'grantor' => $partyFromOp,
                'grantee' => $newParty2,
                'party_1' => $partyFromOp,
                'party_2' => $newParty2,
            ],
        ];

        // Transfer of Title rows in FEFR must keep registration particulars at 0/0/0.
        if ($hasNameChange) {
            $praPayload['regNo'] = '0/0/0';
            $praPayload['serialNo'] = '0';
            $praPayload['pageNo'] = '0';
            $praPayload['volumeNo'] = '0';

            // Transfer rows inherit core OP details from the mother OP record.
            $praPayload['op_type'] = data_get($opRecord, 'op_type');
            $praPayload['land_use'] = data_get($opRecord, 'land_use');
            $praPayload['location'] = data_get($opRecord, 'location') ?? data_get($opRecord, 'property_description');
            $praPayload['property_description'] = data_get($opRecord, 'property_description') ?? data_get($opRecord, 'location');
            $praPayload['plot_no'] = data_get($opRecord, 'plot_no') ?? data_get($opRecord, 'plot_number');
            $praPayload['tp_no'] = data_get($opRecord, 'tp_no');
            $praPayload['lgsaOrCity'] = data_get($opRecord, 'lga') ?? data_get($opRecord, 'lgsaOrCity');

            // OP→ToT lineage: ToT must NOT inherit the OP's prop_id. Mint a
            // fresh prop_id and record the originating OP via parent_prop_id +
            // source_op_table/source_op_id (see op-tot-mismatch-rootcause.md).
            $praPayload['prop_id'] = null;
            $praPayload['force_fresh_prop_id'] = true;
            $praPayload['parent_prop_id'] = data_get($opRecord, 'prop_id');
            $praPayload['source_op_table'] = 'pra';
            $opRecordId = data_get($opRecord, 'id');
            if ($opRecordId !== null) {
                $praPayload['source_op_id'] = (int) $opRecordId;
            }
        }

        // Pull geo fields from the mother OP record so the OSS table shows them
        $tpNo = data_get($opRecord, 'tp_no');
        $plotNo = data_get($opRecord, 'plot_no') ?? data_get($opRecord, 'plot_number');
        $lgaValue = data_get($opRecord, 'lga') ?? data_get($opRecord, 'lgsaOrCity');

        try {
            $result = DB::connection('sqlsrv')->transaction(function () use ($praService, $praPayload, $sourceFileNo, $partyFromOp, $newParty2, $validated, $resolvedCustomerType, $tpNo, $plotNo, $lgaValue, $skipPraCreate) {
                $praRecord = $skipPraCreate ? [] : $praService->createRecord($praPayload, Auth::id());
                $now = now();

                $indexingUpdated = $this->safeUpdateByLookup(
                    'file_indexings',
                    [
                        'file_number' => $sourceFileNo,
                        'mlsfNo' => $sourceFileNo,
                        'full_file_number' => $sourceFileNo,
                    ],
                    [
                        'file_title' => $newParty2,
                        'current_holder' => $newParty2,
                        'original_holder' => $partyFromOp,
                        'party_1' => $partyFromOp,
                        'party_2' => $newParty2,
                        'file_type' => $resolvedCustomerType,
                        'registry' => 1,
                        'physical_registry' => 'Lands Registry',
                        'general_registry' => 'Lands Registry',
                        'land_use_type' => $validated['land_use'] ?? null,
                        'residence_address' => $validated['residence_address'] ?? null,
                        'phone' => $validated['phone'] ?? null,
                        'customer_type' => $resolvedCustomerType,
                        'is_updated' => 1,
                        'updated_by' => Auth::id(),
                        'updated_at' => $now,
                    ]
                );

                $customerUpdated = $this->safeUpdateByLookup(
                    'customers_staging',
                    [
                        'file_number' => $sourceFileNo,
                        'mlsfNo' => $sourceFileNo,
                        'full_file_number' => $sourceFileNo,
                    ],
                    [
                        'customer_name' => $newParty2,
                        'customer_type' => $resolvedCustomerType,
                        'updated_by' => Auth::id(),
                        'updated_at' => $now,
                    ]
                );

                if ($customerUpdated === 0) {
                    $inserted = $this->safeInsertRow(
                        'customers_staging',
                        [
                            'customer_type' => $resolvedCustomerType,
                            'file_number' => $sourceFileNo,
                            'customer_name' => $newParty2,
                            'status' => 'Active',
                            'created_by' => Auth::id(),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]
                    );

                    if ($inserted) {
                        $customerUpdated = 1;
                    }
                }

                $mlsFileNoUpdated = $this->safeUpdateByLookup(
                    'mls_file_no',
                    [
                        'full_file_number' => $sourceFileNo,
                        'file_number' => $sourceFileNo,
                        'mlsfNo' => $sourceFileNo,
                    ],
                    [
                        'file_name' => $newParty2,
                        'customer_type' => $resolvedCustomerType,
                        'updated_at' => $now,
                    ]
                );

                // Build fileNumber update — always update name; only update geo fields when the
                // mother OP record has values (avoids overwriting existing data with nulls).
                $fileNumberData = [
                    'FileName' => $newParty2,
                    'customer_type' => $resolvedCustomerType,
                    'updated_at' => $now,
                ];
                if ($tpNo)
                    $fileNumberData['tp_no'] = $tpNo;
                if ($plotNo)
                    $fileNumberData['plot_no'] = $plotNo;
                if ($lgaValue)
                    $fileNumberData['lga'] = $lgaValue;

                $fileNumberUpdated = $this->safeUpdateByLookup(
                    'fileNumber',
                    [
                        'mlsfNo' => $sourceFileNo,
                        'full_file_number' => $sourceFileNo,
                        'file_number' => $sourceFileNo,
                    ],
                    $fileNumberData
                );

                Log::info('FFR sync completed', [
                    'source_file_no' => $sourceFileNo,
                    'indexing_updated' => $indexingUpdated,
                    'customers_updated' => $customerUpdated,
                    'mls_file_no_updated' => $mlsFileNoUpdated,
                    'file_number_updated' => $fileNumberUpdated,
                    'resolved_customer_type' => $resolvedCustomerType,
                    'user_id' => Auth::id(),
                ]);

                return [
                    'pra' => $praRecord,
                    'indexing_updated' => $indexingUpdated,
                    'customers_updated' => $customerUpdated,
                    'mls_file_no_updated' => $mlsFileNoUpdated,
                    'file_number_updated' => $fileNumberUpdated,
                    'resolved_customer_type' => $resolvedCustomerType,
                ];
            });
        } catch (\Throwable $e) {
            $errorContext = [
                'source_file_no' => $sourceFileNo,
                'payload' => $validated,
                'user_id' => Auth::id(),
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ];

            Log::error('FFR save failed', $errorContext);
            $this->writeFfrDiagnostic('error', 'FFR save failed', $errorContext);

            return response()->json([
                'success' => false,
                'message' => 'Unable to complete FFR save operation.',
                'error' => $e->getMessage(),
            ], 500);
        }

        // An FFR capture IS a change-of-ownership application, so it belongs on the
        // Change of Ownership list like any other OP-backed OSS record. That list is
        // driven from oss_applications while this method only ever wrote pra, so
        // without this mirror an FFR record showed on the OP/FEFR page (pra-driven)
        // and was missing from Applications → Change of Ownership entirely.
        //
        // Keyed on the source file number, which is what the pra row above carries in
        // both mlsFNo and fileno. The file is deliberately still uncommissioned — the
        // mls_file_no write above is an update-if-present, never an insert — so it
        // stays on the FEFR side of the fc/fefr split and the listing's LEFT JOIN
        // simply leaves the file-number columns blank until it is commissioned.
        //
        // Best-effort, exactly like the Match OP path: the capture has already
        // committed and a mirror failure must not undo it.
        $ossMirror = null;
        try {
            $ossMirror = app(\App\Services\MlsCommissioningOssApplicationService::class)->sync([
                'full_file_number' => $sourceFileNo,
                'file_name' => $newParty2,
                'plot_no' => $plotNo,
                'tp_no' => $tpNo,
                'location' => $validated['location'] ?? data_get($opRecord, 'location'),
                'district' => data_get($opRecord, 'district'),
                'lga' => $lgaValue,
                'land_use' => $validated['land_use'] ?? data_get($opRecord, 'land_use'),
                // Both halves of the OSS gate in MlsCommissioningOssApplicationService:
                // OSS entry point, and OP-backed via the "OP " sub_source prefix.
                // Without both it would be filed as a plain generator record and never
                // reach the change-of-name page.
                'system_sub_type' => \App\Support\OssOpCommissionFilter::OSS,
                'sub_source' => 'OP Change of Ownership',
                'created_at' => now(),
            ]);

            Log::info('FFR mirrored to the OSS application list', [
                'source_file_no' => $sourceFileNo,
                'action' => $ossMirror['action'] ?? null,
                'oss_application_id' => $ossMirror['id'] ?? null,
                'user_id' => Auth::id(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Could not mirror FFR capture to the OSS application list', [
                'source_file_no' => $sourceFileNo,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'FFR saved successfully. PRA timeline and name fields were synchronized.',
            'data' => array_merge($result, ['oss_application' => $ossMirror]),
        ]);
    }

    /**
     * Look up the temp file number for a given MLS file number.
     */
    public function lookupTempFileno(Request $request): JsonResponse
    {
        $fileNo = trim((string) $request->input('file_no'));
        if (!$fileNo) {
            return response()->json(['success' => false, 'tracking_id' => null]);
        }

        // Simple SELECT from grouping table to get tracking_id by awaiting_fileno
        $row = DB::connection('sqlsrv')
            ->table('grouping')
            ->where('awaiting_fileno', $fileNo)
            ->select('tracking_id')
            ->orderByDesc('id')
            ->first();

        return response()->json([
            'success' => (bool) ($row && $row->tracking_id),
            'tracking_id' => $row->tracking_id ?? null,
        ]);
    }

    /**
     * Capture Existing flow for FFR when file number is not found in PRA.
     * Keeps the same file number (no extension/new serial), syncs local tables,
     * and stores the mapped Occupancy Permit (OP) PRA record for the file number.
     */
    public function captureFfrExisting(Request $request, PraRecordService $praService): JsonResponse
    {
        $validated = $request->validate([
            'source_file_no' => 'required|string|max:120',
            'file_name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'lga' => 'nullable|string|max:100',
            'customer_type' => 'nullable|string|max:50',
            'land_use' => 'nullable|string|max:100',
            'purpose' => 'nullable|string|max:255',
            'purpose_id' => 'nullable|integer',
            'phone_no' => ['nullable', 'string', 'max:50', new NigerianPhone()],
            'address' => 'nullable|string|max:500',
            'plot_no' => 'nullable|string|max:100',
            'tp_no' => 'nullable|string|max:100',
            'op_type' => 'nullable|string|max:100',
            'op_serial_number' => 'nullable|string|max:100',
            'registration_number' => 'nullable|string|max:100',
            'serial_no' => 'nullable|string|max:50',
            'page_no' => 'nullable|string|max:50',
            'volume_no' => 'nullable|string|max:50',
            'transaction_date' => 'nullable|date',
            'party_1' => 'nullable|string|max:255',
            'party_2' => 'nullable|string|max:255',
            'tracking_id' => 'nullable|string|max:100',
            'ffr_first_pra_prop_id' => 'nullable|integer',
            'ffr_temp_fileno' => 'nullable|string|max:100',
            'from_op_flow' => 'nullable|string|max:10',
            'ffr_source_op_id' => 'nullable|integer',
            'ffr_source_op_table' => 'nullable|string|in:instrument_capture,deed_registrations',
        ]);

        $sourceFileNo = trim((string) $validated['source_file_no']);
        $fileName = trim((string) $validated['file_name']);
        $location = trim((string) ($validated['location'] ?? ''));
        $lga = trim((string) ($validated['lga'] ?? 'Unknown'));
        $customerType = trim((string) ($validated['customer_type'] ?? 'Individual'));
        $landUse = trim((string) ($validated['land_use'] ?? ''));
        $purpose = trim((string) ($validated['purpose'] ?? ''));
        $purposeId = $validated['purpose_id'] ?? null;
        $phoneNo = trim((string) ($validated['phone_no'] ?? ''));
        $address = trim((string) ($validated['address'] ?? ''));
        $plotNo = trim((string) ($validated['plot_no'] ?? ''));
        $tpNo = trim((string) ($validated['tp_no'] ?? ''));
        $opType = trim((string) ($validated['op_type'] ?? 'OP Resettlement'));
        $opSerialNumber = trim((string) ($validated['op_serial_number'] ?? ''));
        $registrationNumber = trim((string) ($validated['registration_number'] ?? ''));
        $serialNo = trim((string) ($validated['serial_no'] ?? ''));
        $pageNo = trim((string) ($validated['page_no'] ?? ''));
        $volumeNo = trim((string) ($validated['volume_no'] ?? ''));
        $transactionDate = trim((string) ($validated['transaction_date'] ?? ''));
        $party1 = trim((string) ($validated['party_1'] ?? ''));
        $party2 = trim((string) ($validated['party_2'] ?? $fileName));
        $trackingId = trim((string) ($validated['tracking_id'] ?? ''));
        $ffrFirstPraPropId = $validated['ffr_first_pra_prop_id'] ?? null;
        $ffrTempFileno = trim((string) ($validated['ffr_temp_fileno'] ?? ''));

        $sourceHistory = $praService->findAllByFileNumber($sourceFileNo);
        if (empty($sourceHistory) && $ffrFirstPraPropId) {
            $sourceHistory = $praService->getHistory((string) $ffrFirstPraPropId);
        }
        $motherOpRecord = $this->resolveMotherOpRecord($sourceHistory);

        // Fallback: If history lookup failed to find an OP (common in sequential FEFR capture),
        // try to resolve the mother record directly by the temp file number if provided.
        if (!$motherOpRecord && $ffrTempFileno !== '') {
            $fallbackOp = DB::connection('sqlsrv')->table('pra')
                ->where('temp_fileno', $ffrTempFileno)
                ->where(function ($q) {
                    $q->where('instrument_type', 'like', '%Occupancy Permit%')
                        ->orWhere('transaction_type', 'like', '%Occupancy Permit%');
                })
                ->orderByDesc('id')
                ->first();

            if ($fallbackOp) {
                $motherOpRecord = (array) $fallbackOp;
                Log::info('FFR existing capture: resolved mother OP record via temp_fileno fallback', [
                    'temp_fileno' => $ffrTempFileno,
                    'prop_id' => $motherOpRecord['prop_id'] ?? null,
                ]);
            }
        }

        if (!$motherOpRecord) {
            Log::warning('FFR existing capture: mother OP record not found, using request payload fallbacks', [
                'source_file_no' => $sourceFileNo,
                'user_id' => Auth::id(),
            ]);
        }

        $inheritedRegNo = $this->firstFilledValue(
            $motherOpRecord,
            ['regNo', 'reg_no', 'registration_number', 'deeds_serial_no']
        );
        $inheritedRegDate = $this->firstFilledValue(
            $motherOpRecord,
            ['reg_date', 'regDate', 'deeds_date', 'transaction_date']
        );
        $inheritedRegTime = $this->firstFilledValue(
            $motherOpRecord,
            ['reg_time', 'regTime', 'deeds_time', 'transaction_time']
        );
        $inheritedPlotNo = $this->firstFilledValue($motherOpRecord, ['plot_no', 'plot_number']);
        $inheritedTpNo = $this->firstFilledValue($motherOpRecord, ['tp_no']);
        $inheritedLga = $this->firstFilledValue($motherOpRecord, ['lga', 'lgsaOrCity']);
        $inheritedLocation = $this->firstFilledValue($motherOpRecord, ['location', 'property_description']);
        $inheritedLandUse = $this->firstFilledValue($motherOpRecord, ['land_use']);
        $inheritedOpSerialNumber = $this->firstFilledValue($motherOpRecord, ['op_serial_number']);
        $inheritedOpType = $this->firstFilledValue($motherOpRecord, ['op_type']);

        $resolvedRegistrationNumber = $inheritedRegNo ?: $registrationNumber;
        $resolvedRegDate = $inheritedRegDate;
        $resolvedRegTime = $inheritedRegTime;
        $resolvedPlotNo = $plotNo !== '' ? $plotNo : ($inheritedPlotNo ?? '');
        $resolvedTpNo = $tpNo !== '' ? $tpNo : ($inheritedTpNo ?? '');
        $resolvedLga = $lga !== '' && strtolower($lga) !== 'unknown' ? $lga : ($inheritedLga ?? $lga);
        $resolvedLocation = $location !== '' ? $location : ($inheritedLocation ?? '');
        $resolvedLandUse = $landUse !== '' ? $landUse : ($inheritedLandUse ?? '');
        $resolvedOpSerialNumber = $opSerialNumber !== '' ? $opSerialNumber : ($inheritedOpSerialNumber ?? '');
        $resolvedOpType = $inheritedOpType ?: $opType;
        $resolvedTransactionDate = $transactionDate !== '' ? $transactionDate : null;

        // Step 2 branching:
        // When from_op_flow=1, this is always a Transfer of Title (OP).
        // The OP row was already created by directOpCapture; this row is the transfer.
        // party_1 = original allottee (from OP), party_2 = new holder (file_name entered by user).
        $fromOpFlow = trim((string) ($validated['from_op_flow'] ?? '')) === '1';

        if ($fromOpFlow) {
            // From OP flow: always Transfer of Title.
            // ToT.party_1 must be the previous holder (= mother OP's party_2),
            // NOT request.party_1 (which is the OP's party_1, typically "Kano State Government").
            $motherOpParty2 = trim((string) (
                data_get($motherOpRecord, 'party_2')
                ?? data_get($motherOpRecord, 'parties.party_2')
                ?? data_get($motherOpRecord, 'Grantee')
                ?? data_get($motherOpRecord, 'parties.grantee')
                ?? ''
            ));
            if ($motherOpParty2 !== '') {
                $originalAllottee = $motherOpParty2;
            } elseif ($party1 !== '' && strcasecmp($party1, 'Kano State Government') !== 0) {
                // Only use request.party_1 if it's not the generic OP grantor.
                $originalAllottee = $party1;
            } else {
                $originalAllottee = 'Unknown';
            }
            $enteredAllottee = $fileName;
            $hasNameChange = true;
        } else {
            $originalAllottee = $party2 !== '' ? $party2 : $fileName;
            $enteredAllottee = $fileName !== '' ? $fileName : $party2;
            // Same shared test as saveFfrChangeOfName and MLS commissioning.
            $hasNameChange = \App\Support\HolderName::changed($originalAllottee, $enteredAllottee);
        }

        $resolvedTransactionType = ($hasNameChange || $fromOpFlow)
            ? 'Transfer Of Title (OP)'
            : 'Occupancy Permit (OP)';
        $resolvedInstrumentType = $resolvedTransactionType;
        $resolvedGrantor = ($hasNameChange || $fromOpFlow)
            ? $originalAllottee
            : ($party1 !== '' ? $party1 : 'Kano State Government');
        $resolvedGrantee = $hasNameChange
            ? $enteredAllottee
            : ($originalAllottee !== '' ? $originalAllottee : $enteredAllottee);

        // Safety net: "Kano State Government" is only ever valid as an OP's
        // Grantor (the issuing authority). A Transfer of Title's Party 1 must
        // be the previous holder — if every resolution path above still landed
        // on the government literal, refuse the write instead of silently
        // corrupting the record (this exact corruption has recurred multiple
        // times without a confirmed root cause; log full context for the next one).
        if (($hasNameChange || $fromOpFlow) && strcasecmp(trim($resolvedGrantor), 'Kano State Government') === 0) {
            Log::error('FFR existing capture blocked: resolved ToT Party 1 is Kano State Government', [
                'source_file_no' => $sourceFileNo,
                'from_op_flow' => $fromOpFlow,
                'has_name_change' => $hasNameChange,
                'original_allottee' => $originalAllottee,
                'request_party1' => $party1,
                'request_party2' => $party2,
                'mother_op_record_id' => data_get($motherOpRecord, 'id'),
                'mother_op_party_2' => data_get($motherOpRecord, 'party_2'),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to resolve the original holder for this Transfer of Title. Please provide Party 1 manually and try again.',
            ], 422);
        }

        $isTransferRecord = $hasNameChange || $fromOpFlow;
        $resolvedSerialNo = $isTransferRecord ? '0' : ($serialNo !== '' ? $serialNo : null);
        $resolvedPageNo = $isTransferRecord ? '0' : (($pageNo !== '' ? $pageNo : ($serialNo !== '' ? $serialNo : null)));
        $resolvedVolumeNo = $isTransferRecord ? '0' : ($volumeNo !== '' ? $volumeNo : null);
        if ($isTransferRecord) {
            $resolvedRegistrationNumber = '0/0/0';
            $resolvedPlotNo = $inheritedPlotNo ?: $resolvedPlotNo;
            $resolvedTpNo = $inheritedTpNo ?: $resolvedTpNo;
            $resolvedLga = $inheritedLga ?: $resolvedLga;
            $resolvedLocation = $inheritedLocation ?: $resolvedLocation;
            $resolvedLandUse = $inheritedLandUse ?: $resolvedLandUse;
            $resolvedOpSerialNumber = $inheritedOpSerialNumber ?: $resolvedOpSerialNumber;
            $resolvedOpType = $inheritedOpType ?: $resolvedOpType;
        }

        // When resolved as OP (not a transfer), check IC/DR for existing source.
        $skipPraCreate = false;
        if (!$hasNameChange && !$fromOpFlow) {
            $existingOp = $praService->findExistingOpInSource([
                'op_serial_number' => $resolvedOpSerialNumber,
                'mlsFNo' => $sourceFileNo,
                'fileno' => $sourceFileNo,
                'temp_fileno' => $ffrTempFileno,
            ]);

            if ($existingOp) {
                $linkPropId = $ffrFirstPraPropId ?: ($existingOp['prop_id'] ?? null);
                if (empty($linkPropId)) {
                    $linkPropId = app(\App\Services\PropertyIdAllocationService::class)
                        ->allocateOrRetrievePropId($sourceFileNo, $sourceFileNo, null, null, ['allow_temp_only' => true]);
                }
                $praService->linkSourceOpRecord($existingOp, [
                    'prop_id' => $linkPropId,
                    'mlsFNo' => $sourceFileNo,
                ]);
                $skipPraCreate = true;

                Log::info('FFR existing capture: OP exists in IC/DR — skipping PRA, linked source', [
                    'source_table' => $existingOp['_source_table'],
                    'source_id' => $existingOp['id'],
                    'prop_id' => $linkPropId,
                ]);
            }
        }

        Log::info('FFR existing capture flow resolved', [
            'source_file_no' => $sourceFileNo,
            'has_name_change' => $hasNameChange,
            'original_allottee' => $originalAllottee,
            'entered_allottee' => $enteredAllottee,
            'transaction_type' => $resolvedTransactionType,
            'instrument_type' => $resolvedInstrumentType,
            'inherited_reg_no' => $inheritedRegNo,
            'inherited_reg_date' => $inheritedRegDate,
            'inherited_reg_time' => $inheritedRegTime,
            'user_id' => Auth::id(),
        ]);

        Log::info('FFR existing capture initiated', [
            'source_file_no' => $sourceFileNo,
            'op_type' => $opType,
            'user_id' => Auth::id(),
        ]);

        try {
            $result = DB::connection('sqlsrv')->transaction(function () use ($praService, $trackingId, $ffrFirstPraPropId, $ffrTempFileno, $sourceFileNo, $fileName, $location, $lga, $customerType, $landUse, $purpose, $purposeId, $phoneNo, $address, $resolvedPlotNo, $resolvedTpNo, $resolvedLga, $resolvedLocation, $resolvedLandUse, $opType, $resolvedOpType, $resolvedOpSerialNumber, $resolvedRegistrationNumber, $resolvedRegDate, $resolvedRegTime, $resolvedTransactionDate, $resolvedSerialNo, $resolvedPageNo, $resolvedVolumeNo, $party1, $party2, $resolvedTransactionType, $resolvedInstrumentType, $resolvedGrantor, $resolvedGrantee, $hasNameChange, $originalAllottee, $enteredAllottee, $skipPraCreate, $isTransferRecord, $motherOpRecord) {
                $now = now();
                $userId = Auth::id();

                // Use tracking_id from grouping table; fallback to generated if empty
                if (empty($trackingId)) {
                    $trackingId = 'FFR-' . now()->format('YmdHis') . '-' . random_int(100, 999);
                }

                $fileNumberUpdated = $this->safeUpdateByLookup(
                    'fileNumber',
                    [
                        'mlsfNo' => $sourceFileNo,
                        'full_file_number' => $sourceFileNo,
                        'file_number' => $sourceFileNo,
                    ],
                    [
                        'FileName' => $fileName,
                        'tracking_id' => $trackingId,
                        'location' => $resolvedLocation,
                        'lga' => $resolvedLga,
                        'customer_type' => $customerType,
                        'phone_no' => $phoneNo,
                        'address' => $address,
                        'plot_no' => $resolvedPlotNo,
                        'tp_no' => $resolvedTpNo,
                        'land_use' => $resolvedLandUse,
                        'purpose' => $purpose,
                        'purpose_id' => $purposeId,
                        'type' => 'Captured',
                        'SOURCE' => 'FFR_Existing_Capture',
                        'updated_at' => $now,
                    ]
                );

                if ($fileNumberUpdated === 0) {
                    $inserted = $this->safeInsertRow('fileNumber', [
                        'tracking_id' => $trackingId,
                        'mlsfNo' => $sourceFileNo,
                        'FileName' => $fileName,
                        'location' => $resolvedLocation,
                        'lga' => $resolvedLga,
                        'customer_type' => $customerType,
                        'phone_no' => $phoneNo,
                        'address' => $address,
                        'plot_no' => $resolvedPlotNo,
                        'tp_no' => $resolvedTpNo,
                        'land_use' => $resolvedLandUse,
                        'purpose' => $purpose,
                        'purpose_id' => $purposeId,
                        'type' => 'Captured',
                        'SOURCE' => 'FFR_Existing_Capture',
                        'created_by' => (Auth::user()->name ?? Auth::user()->email ?? 'System'),
                        'is_deleted' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    if ($inserted) {
                        $fileNumberUpdated = 1;
                    }
                }

                $indexingUpdated = $this->safeUpdateByLookup(
                    'file_indexings',
                    [
                        'file_number' => $sourceFileNo,
                        'mlsfNo' => $sourceFileNo,
                        'full_file_number' => $sourceFileNo,
                    ],
                    [
                        'file_title' => $fileName,
                        'tracking_id' => $trackingId,
                        'current_holder' => $resolvedGrantee,
                        'party_1' => $resolvedGrantor,
                        'party_2' => $resolvedGrantee,
                        'file_type' => $customerType,
                        'registry' => 1,
                        'physical_registry' => 'Lands Registry',
                        'general_registry' => 'Lands Registry',
                        'land_use_type' => $resolvedLandUse ?: null,
                        'plot_no' => $resolvedPlotNo,
                        'tp_no' => $resolvedTpNo,
                        'land_use' => $resolvedLandUse,
                        'purpose' => $purpose,
                        'is_updated' => 1,
                        'updated_by' => $userId,
                        'updated_at' => $now,
                    ]
                );

                if ($indexingUpdated === 0) {
                    $inserted = $this->safeInsertRow('file_indexings', [
                        'file_number' => $sourceFileNo,
                        'tracking_id' => $trackingId,
                        'file_title' => $fileName,
                        'current_holder' => $resolvedGrantee,
                        'party_1' => $resolvedGrantor,
                        'party_2' => $resolvedGrantee,
                        'file_type' => $customerType,
                        'registry' => 1,
                        'physical_registry' => 'Lands Registry',
                        'general_registry' => 'Lands Registry',
                        'land_use_type' => $resolvedLandUse ?: null,
                        'lga' => $resolvedLga,
                        'location' => $resolvedLocation,
                        'plot_no' => $resolvedPlotNo,
                        'tp_no' => $resolvedTpNo,
                        'land_use' => $resolvedLandUse,
                        'purpose' => $purpose,
                        'is_updated' => 1,
                        'created_by' => $userId,
                        'updated_by' => $userId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    if ($inserted) {
                        $indexingUpdated = 1;
                    }
                }

                $customerUpdated = $this->safeUpdateByLookup(
                    'customers_staging',
                    [
                        'file_number' => $sourceFileNo,
                        'mlsfNo' => $sourceFileNo,
                        'full_file_number' => $sourceFileNo,
                    ],
                    [
                        'customer_name' => $resolvedGrantee,
                        'customer_type' => $customerType,
                        'status' => 'Active',
                        'updated_by' => $userId,
                        'updated_at' => $now,
                    ]
                );

                if ($customerUpdated === 0) {
                    $inserted = $this->safeInsertRow('customers_staging', [
                        'file_number' => $sourceFileNo,
                        'customer_name' => $resolvedGrantee,
                        'customer_type' => $customerType,
                        'status' => 'Active',
                        'created_by' => $userId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    if ($inserted) {
                        $customerUpdated = 1;
                    }
                }

                $ffrPraPayload = [
                    'prop_id' => $ffrFirstPraPropId ?: null,
                    'mlsFNo' => $sourceFileNo,
                    'fileno' => $sourceFileNo,
                    'temp_fileno' => $ffrTempFileno ?: null,
                    'transaction_type' => $resolvedTransactionType,
                    'instrument_type' => $resolvedInstrumentType,
                    'transaction_date' => $resolvedTransactionDate ?: null,
                    'source' => 'FFR Existing Capture',
                    'system_source' => 'OSSOPCHANGEOFNAME',
                    'op_type' => $resolvedOpType,
                    'op_serial_number' => $resolvedOpSerialNumber ?: null,
                    'regNo' => $resolvedRegistrationNumber ?: null,
                    'reg_date' => $resolvedRegDate ?: null,
                    'reg_time' => $resolvedRegTime ?: null,
                    'serialNo' => $resolvedSerialNo,
                    'pageNo' => $resolvedPageNo,
                    'volumeNo' => $resolvedVolumeNo,
                    'location' => $resolvedLocation,
                    'property_description' => $resolvedLocation,
                    'land_use' => $resolvedLandUse ?: null,
                    'purpose' => $purpose ?: null,
                    'plot_no' => $resolvedPlotNo ?: null,
                    'tp_no' => $resolvedTpNo ?: null,
                    'lgsaOrCity' => $resolvedLga ?: null,
                    'phone_no' => $phoneNo ?: null,
                    'address' => $address ?: null,
                    'Grantor' => $resolvedGrantor,
                    'Grantee' => $resolvedGrantee,
                    'party_1' => $resolvedGrantor,
                    'party_2' => $resolvedGrantee,
                    'parties' => [
                        'grantor' => $resolvedGrantor,
                        'grantee' => $resolvedGrantee,
                        'party_1' => $resolvedGrantor,
                        'party_2' => $resolvedGrantee,
                    ],
                ];

                // OP→ToT lineage: ToT must NOT inherit the OP's prop_id. When
                // this row is the Transfer of Title (name change OR from-OP
                // flow), mint a fresh prop_id and record originating OP via
                // parent_prop_id + source_op_table/source_op_id. Look up the
                // OP row by temp_fileno (from-OP flow created it just before
                // this call) or by mother record id from PRA history.
                if ($isTransferRecord) {
                    $sourceOpId = data_get($motherOpRecord, 'id');
                    if (!$sourceOpId && $ffrTempFileno !== '') {
                        $sourceOpId = DB::connection('sqlsrv')
                            ->table('pra')
                            ->where('temp_fileno', $ffrTempFileno)
                            ->where(function ($q) {
                                $q->where('instrument_type', 'like', '%Occupancy Permit%')
                                    ->orWhere('transaction_type', 'like', '%Occupancy Permit%');
                            })
                            ->orderByDesc('id')
                            ->value('id');
                    }
                    $ffrPraPayload['prop_id'] = null;
                    $ffrPraPayload['parent_prop_id'] = data_get($motherOpRecord, 'prop_id') ?: $ffrFirstPraPropId;
                    $ffrPraPayload['source_op_table'] = 'pra';
                    if ($sourceOpId) {
                        $ffrPraPayload['source_op_id'] = (int) $sourceOpId;
                    }
                    $ffrPraPayload['force_fresh_prop_id'] = true;
                }

                $praOpRecord = $skipPraCreate ? [] : $praService->createRecord($ffrPraPayload, $userId);

                return [
                    'scenario' => $hasNameChange ? 'name-change' : 'no-name-change',
                    'resolved_data' => [
                        'temp_fileno' => $ffrTempFileno ?: null,
                        'mlsFNo' => $sourceFileNo,
                        'Grantor' => $resolvedGrantor,
                        'party_1' => $resolvedGrantor,
                        'Grantee' => $resolvedGrantee,
                        'party_2' => $resolvedGrantee,
                        'instrument_type' => $resolvedInstrumentType,
                        'transaction_type' => $resolvedTransactionType,
                        'prop_id' => $ffrFirstPraPropId ?: null,
                        'inherited_reg_no' => $resolvedRegistrationNumber ?: null,
                        'inherited_reg_date' => $resolvedRegDate ?: null,
                        'inherited_reg_time' => $resolvedRegTime ?: null,
                        'transaction_date' => $resolvedTransactionDate ?: null,
                        'source_allottee' => $originalAllottee,
                        'entered_allottee' => $enteredAllottee,
                    ],
                    'file_number_updated' => $fileNumberUpdated,
                    'indexing_updated' => $indexingUpdated,
                    'customers_updated' => $customerUpdated,
                    'pra_record' => $praOpRecord,
                ];
            });
        } catch (\Throwable $e) {
            $errorContext = [
                'source_file_no' => $sourceFileNo,
                'payload' => $validated,
                'user_id' => Auth::id(),
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ];

            Log::error('FFR existing capture failed', $errorContext);
            $this->writeFfrDiagnostic('error', 'FFR existing capture failed', $errorContext);

            return response()->json([
                'success' => false,
                'message' => 'Unable to complete existing capture synchronization.',
                'error' => $e->getMessage(),
            ], 500);
        }

        // An existing capture IS a change-of-ownership application, exactly like the
        // New-mode save above and the Match OP path, so it belongs on the Change of
        // Ownership list. That list is driven from oss_applications while this method
        // only ever wrote pra, file_indexings and customers -- so a captured file
        // showed in the FEFR modal (pra-driven) and was missing from both the FEFR
        // table and Applications -> Change of Ownership. It bites hardest here:
        // freeing a file for capture means HARD-deleting its oss_applications row,
        // because checkDuplicate ignores is_deleted, so this path routinely runs on
        // files whose row has just been removed and nothing put one back.
        //
        // Keyed on the source file number, which is what the pra rows above carry in
        // both mlsFNo and fileno. Both halves of the gate in
        // MlsCommissioningOssApplicationService are pinned here -- an OSS entry point
        // and the "OP " sub_source prefix -- since with either missing the row is
        // filed as a plain generator record and never reaches the page.
        //
        // Best-effort, like the other two paths: the capture has already committed and
        // a mirror failure must not undo it.
        $ossMirror = null;
        try {
            $ossMirror = app(\App\Services\MlsCommissioningOssApplicationService::class)->sync([
                'full_file_number' => $sourceFileNo,
                'file_name' => $enteredAllottee !== '' ? $enteredAllottee : $fileName,
                'plot_no' => $resolvedPlotNo ?: data_get($motherOpRecord, 'plot_no'),
                'tp_no' => $resolvedTpNo ?: data_get($motherOpRecord, 'tp_no'),
                'location' => $resolvedLocation ?: data_get($motherOpRecord, 'location'),
                'district' => data_get($motherOpRecord, 'district'),
                'lga' => $resolvedLga ?: data_get($motherOpRecord, 'lga'),
                'land_use' => $resolvedLandUse ?: data_get($motherOpRecord, 'land_use'),
                'system_sub_type' => \App\Support\OssOpCommissionFilter::OSS,
                'sub_source' => 'OP Change of Ownership',
                'created_at' => now(),
            ]);

            Log::info('FFR existing capture mirrored to the OSS application list', [
                'source_file_no' => $sourceFileNo,
                'action' => $ossMirror['action'] ?? null,
                'oss_application_id' => $ossMirror['id'] ?? null,
                'user_id' => Auth::id(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Could not mirror FFR existing capture to the OSS application list', [
                'source_file_no' => $sourceFileNo,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
        }

        Log::info('FFR existing capture completed', [
            'source_file_no' => $sourceFileNo,
            'user_id' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Existing capture synchronized: OP data mapped to file number, indexing, customer, and PRA.',
            'data' => array_merge($result, ['oss_application' => $ossMirror]),
        ]);
    }

    /**
     * Direct OP Capture — for indexed files with no OP in PRA.
     *
     * Creates two PRA rows in a single transaction:
     *   Row 1 (OP):                Party 1 = Kano State Government, Party 2 = allottee
     *   Row 2 (Transfer of Title): Party 1 = allottee,              Party 2 = current holder (from fileNumber.FileName)
     *
     * Also syncs file_indexings, customers_staging with the new holder data.
     */
    public function directOpCapture(Request $request, PraRecordService $praService): JsonResponse
    {
        $validated = $request->validate([
            'source_file_no' => 'required|string|max:120',
            'allottee' => 'required|string|max:255',
            'current_holder' => 'nullable|string|max:255',
            'grantor' => 'nullable|string|max:500',
            'op_type' => 'nullable|string|max:100',
            'op_serial_number' => 'nullable|string|max:100',
            'transaction_date' => 'nullable|date',
            'registration_number' => 'nullable|string|max:100',
            'serial_no' => 'nullable|string|max:50',
            'page_no' => 'nullable|string|max:50',
            'volume_no' => 'nullable|string|max:50',
            'location' => 'nullable|string|max:500',
            'plot_no' => 'nullable|string|max:100',
            'tp_no' => 'nullable|string|max:100',
            'lga' => 'nullable|string|max:100',
            'land_use' => 'nullable|string|max:100',
            'temp_fileno' => 'nullable|string|max:120',
            'merger_group_id' => 'nullable|string|max:36',
            'is_merger' => 'nullable|boolean',
        ]);

        $sourceFileNo = trim((string) $validated['source_file_no']);
        $allottee = trim((string) $validated['allottee']);
        $currentHolder = trim((string) $validated['current_holder']);
        $opType = trim((string) ($validated['op_type'] ?? 'OP Resettlement'));
        $opSerialNumber = trim((string) ($validated['op_serial_number'] ?? ''));
        $txDate = trim((string) ($validated['transaction_date'] ?? ''));
        $regNo = trim((string) ($validated['registration_number'] ?? ''));
        $serialNo = trim((string) ($validated['serial_no'] ?? ''));
        $pageNo = trim((string) ($validated['page_no'] ?? ''));
        $volumeNo = trim((string) ($validated['volume_no'] ?? ''));
        $location = trim((string) ($validated['location'] ?? ''));
        $plotNo = trim((string) ($validated['plot_no'] ?? ''));
        $tpNo = trim((string) ($validated['tp_no'] ?? ''));
        $lga = trim((string) ($validated['lga'] ?? ''));
        $landUse = trim((string) ($validated['land_use'] ?? ''));
        $tempFileno = trim((string) ($validated['temp_fileno'] ?? ''));
        $grantor = trim((string) ($validated['grantor'] ?? ''));
        $isMerger = !empty($validated['is_merger']);
        $mergerGroupId = trim((string) ($validated['merger_group_id'] ?? ''));

        // Generate a new UUID for the first OP in a merger set when none is provided
        if ($isMerger && $mergerGroupId === '') {
            $mergerGroupId = (string) \Illuminate\Support\Str::uuid();
        }

        // Default grantor to 'Kano State Government' if not supplied by caller
        if ($grantor === '') {
            $grantor = 'Kano State Government';
        }

        $resolvedCurrentHolder = $this->resolveIndexedFileTitleByFileNo($sourceFileNo, $currentHolder);
        if ($resolvedCurrentHolder === '') {
            // Fallback: use allottee name if current holder cannot be resolved
            $resolvedCurrentHolder = $allottee;
        }

        $customerType = $this->resolveCustomerTypeFromName($resolvedCurrentHolder);

        Log::info('FFR direct OP capture initiated', [
            'source_file_no' => $sourceFileNo,
            'allottee' => $allottee,
            'current_holder' => $resolvedCurrentHolder,
            'op_type' => $opType,
            'user_id' => Auth::id(),
        ]);

        try {
            $result = DB::connection('sqlsrv')->transaction(function () use ($praService, $sourceFileNo, $tempFileno, $allottee, $currentHolder, $resolvedCurrentHolder, $opType, $opSerialNumber, $txDate, $regNo, $serialNo, $pageNo, $volumeNo, $location, $plotNo, $tpNo, $lga, $landUse, $customerType, $grantor, $isMerger, $mergerGroupId) {
                $userId = Auth::id();
                $now = now();

                // ── Row 1: Occupancy Permit ──
                // Check if this OP already exists in instrument_capture or deed_registrations.
                // If it does, link the source record instead of creating a duplicate PRA row.
                $opFileNo = $tempFileno ?: $sourceFileNo;
                $opPra = [];
                // source_op_id/source_op_table pin the exact row the officer
                // confirmed in the "Existing OP Found" dialog. op_serial_number is
                // NOT unique across instrument_capture, so without them the lookup
                // can fall through to a different OP that shares the serial.
                $existingOp = $praService->findExistingOpInSource([
                    'op_serial_number' => $opSerialNumber,
                    'mlsFNo' => $sourceFileNo,
                    'fileno' => $opFileNo,
                    'temp_fileno' => $tempFileno,
                    'source_op_id' => $validated['ffr_source_op_id'] ?? null,
                    'source_op_table' => $validated['ffr_source_op_table'] ?? null,
                ]);

                if ($existingOp) {
                    // OP exists in IC/DR — link it with prop_id instead of duplicating into PRA
                    $propIdService = app(\App\Services\PropertyIdAllocationService::class);
                    $opPropId = $existingOp['prop_id'] ?? null;

                    if (empty($opPropId)) {
                        $opPropId = $propIdService->allocateOrRetrievePropId(
                            $opFileNo,
                            $sourceFileNo,
                            null,
                            null,
                            ['allow_temp_only' => true]
                        );
                    }

                    $praService->linkSourceOpRecord($existingOp, [
                        'prop_id' => $opPropId,
                        'mlsFNo' => $sourceFileNo !== $opFileNo ? $sourceFileNo : null,
                    ]);

                    Log::info('FFR direct OP capture: Row 1 (OP) linked to existing source record', [
                        'source_table' => $existingOp['_source_table'],
                        'source_id' => $existingOp['id'],
                        'op_prop_id' => $opPropId,
                    ]);

                    // Try to resolve an OP PRA row for detail inheritance; fallback to request fields when absent.
                    if (!empty($opPropId)) {
                        $opPra = DB::connection('sqlsrv')->table('pra')
                            ->where('prop_id', $opPropId)
                            ->where(function ($q) {
                                $q->where('instrument_type', 'not like', '%Transfer of Title%')
                                    ->where('transaction_type', 'not like', '%Transfer of Title%');
                            })
                            ->orderByDesc('id')
                            ->first() ?: [];
                    }
                } else {
                    // No existing IC/DR record — create OP PRA row as before
                    $opPra = $praService->createRecord([
                        'mlsFNo' => null,
                        'fileno' => $opFileNo,
                        'temp_fileno' => $tempFileno ?: null,
                        'transaction_type' => 'Occupancy Permit (OP)',
                        'instrument_type' => 'Occupancy Permit (OP)',
                        'op_type' => $opType,
                        'op_serial_number' => $opSerialNumber ?: null,
                        'transaction_date' => $txDate ?: null,
                        'regNo' => $regNo ?: null,
                        'serialNo' => $serialNo ?: null,
                        'pageNo' => ($pageNo ?: $serialNo) ?: null,
                        'volumeNo' => $volumeNo ?: null,
                        'location' => $location ?: null,
                        'property_description' => $location ?: null,
                        'plot_no' => $plotNo ?: null,
                        'tp_no' => $tpNo ?: null,
                        'lgsaOrCity' => $lga ?: null,
                        'land_use' => $landUse ?: null,
                        'source' => 'FFR Direct OP Capture',
                        'system_source' => 'OSSOPCHANGEOFNAME',
                        'Grantor' => $grantor,
                        'Grantee' => $allottee,
                        'party_1' => $grantor,
                        'party_2' => $allottee,
                        'parties' => [
                            'grantor' => $grantor,
                            'grantee' => $allottee,
                            'party_1' => $grantor,
                            'party_2' => $allottee,
                        ],
                        'merger_group_id' => $mergerGroupId ?: null,
                        'is_merger_op' => $isMerger ? 1 : 0,
                    ], $userId);

                    // Persist merger_group_id on the OP row directly (PraRecordService may not
                    // forward unknown keys, so we patch the row after creation if needed)
                    $opPropId = data_get($opPra, 'prop_id');
                    $opRowId = data_get($opPra, 'id');

                    if ($mergerGroupId && !empty($opRowId)) {
                        DB::connection('sqlsrv')->table('pra')
                            ->where('id', $opRowId)
                            ->whereNull('merger_group_id')
                            ->update([
                                'merger_group_id' => $mergerGroupId,
                                'is_merger_op' => $isMerger ? 1 : 0,
                            ]);
                    }

                    // Fallback: if prop_id not in response, query by the row id
                    if (empty($opPropId) && !empty($opRowId)) {
                        $opPropId = DB::connection('sqlsrv')
                            ->table('pra')
                            ->where('id', $opRowId)
                            ->value('prop_id');
                    }

                    // Fallback 2: query latest by file number (temp or source)
                    if (empty($opPropId)) {
                        $opPropId = DB::connection('sqlsrv')
                            ->table('pra')
                            ->where('mlsFNo', $opFileNo)
                            ->orderByDesc('id')
                            ->value('prop_id');
                    }

                    Log::info('FFR direct OP capture: Row 1 (OP) created in PRA (no IC/DR source)', [
                        'op_row_id' => $opRowId,
                        'op_prop_id' => $opPropId,
                    ]);
                }

                if (empty($opPropId)) {
                    throw new \RuntimeException('Failed to resolve prop_id after creating OP PRA row.');
                }

                $opInheritedLocation = data_get($opPra, 'location') ?: $location;
                $opInheritedPropertyDescription = data_get($opPra, 'property_description')
                    ?: data_get($opPra, 'location')
                    ?: $location;
                $opInheritedPlotNo = data_get($opPra, 'plot_no') ?: $plotNo;
                $opInheritedTpNo = data_get($opPra, 'tp_no') ?: $tpNo;
                $opInheritedLga = data_get($opPra, 'lgsaOrCity') ?: $lga;
                $opInheritedLandUse = data_get($opPra, 'land_use') ?: $landUse;
                $opInheritedOpType = data_get($opPra, 'op_type') ?: $opType;
                $opInheritedOpSerial = data_get($opPra, 'op_serial_number') ?: $opSerialNumber;
                $opInheritedTransactionDate = data_get($opPra, 'transaction_date') ?: ($txDate ?: null);
                $opInheritedRegDate = data_get($opPra, 'reg_date') ?: null;
                $opInheritedRegTime = data_get($opPra, 'reg_time') ?: null;
                $opInheritedAllottee = trim((string) (
                    data_get($opPra, 'party_2')
                    ?: data_get($opPra, 'Grantee')
                    ?: data_get($opPra, 'grantee')
                    ?: $allottee
                ));

                if ($opInheritedAllottee === '') {
                    throw new \RuntimeException('Failed to resolve OP Party 2 for the Transfer of Title Party 1.');
                }

                // ── Row 2: Transfer of Title ──
                // For indexed files: allottee becomes party_1, current holder becomes party_2
                //
                // Lineage: ToT must mint a brand-new prop_id (not reuse the OP's)
                // and anchor back to the OP via parent_prop_id + source_op_*. The
                // OP's prop_id stays as the master file\u2192prop mapping; the ToT
                // carries its own identity so OP\u2194ToT pairs are never confused
                // across siblings.

                $opPraRowId = data_get($opPra, 'id');
                $totSourceTable = $opPraRowId ? 'pra' : null;
                $totSourceId = $opPraRowId ? (int) $opPraRowId : null;
                if (!$totSourceId && !empty($existingOp)) {
                    $totSourceTable = (string) ($existingOp['_source_table'] ?? '') ?: null;
                    $totSourceId = !empty($existingOp['id']) ? (int) $existingOp['id'] : null;
                }
                $totPra = $praService->createRecord([
                    'mlsFNo' => $sourceFileNo,
                    'fileno' => $sourceFileNo,
                    'temp_fileno' => $tempFileno ?: null,
                    'force_fresh_prop_id' => true,
                    'parent_prop_id' => $opPropId,
                    'source_op_table' => $totSourceTable,
                    'source_op_id' => $totSourceId,
                    'transaction_type' => 'Transfer of Title (OP)',
                    'instrument_type' => 'Transfer of Title (OP)',
                    'op_type' => $opInheritedOpType,
                    'op_serial_number' => $opInheritedOpSerial ?: null,
                    'transaction_date' => $opInheritedTransactionDate,
                    'regNo' => '0/0/0',
                    'serialNo' => '0',
                    'pageNo' => '0',
                    'volumeNo' => '0',
                    'reg_date' => $opInheritedRegDate,
                    'reg_time' => $opInheritedRegTime,
                    'location' => $opInheritedLocation ?: null,
                    'property_description' => $opInheritedPropertyDescription ?: null,
                    'plot_no' => $opInheritedPlotNo ?: null,
                    'tp_no' => $opInheritedTpNo ?: null,
                    'lgsaOrCity' => $opInheritedLga ?: null,
                    'land_use' => $opInheritedLandUse ?: null,
                    'source' => 'FFR Direct OP Capture',
                    'system_source' => 'OSSOPCHANGEOFNAME',
                    'customer_type' => $customerType,
                    'Grantor' => $opInheritedAllottee,
                    'Grantee' => $resolvedCurrentHolder,
                    'party_1' => $opInheritedAllottee,
                    'party_2' => $resolvedCurrentHolder,
                    'parties' => [
                        'grantor' => $opInheritedAllottee,
                        'grantee' => $resolvedCurrentHolder,
                        'party_1' => $opInheritedAllottee,
                        'party_2' => $resolvedCurrentHolder,
                    ],
                    'merger_group_id' => $mergerGroupId ?: null,
                    'is_merger_op' => 0,
                ], $userId);

                // Patch ToT row with merger_group_id if PraRecordService didn't forward it
                $totRowId = data_get($totPra, 'id');
                if ($mergerGroupId && !empty($totRowId)) {
                    DB::connection('sqlsrv')->table('pra')
                        ->where('id', $totRowId)
                        ->whereNull('merger_group_id')
                        ->update(['merger_group_id' => $mergerGroupId, 'is_merger_op' => 0]);
                }

                Log::info('FFR direct OP capture: Row 2 (Transfer of Title) created', [
                    'tot_row_id' => data_get($totPra, 'id'),
                    'prop_id' => $opPropId,
                ]);

                return [
                    'op_pra' => $opPra,
                    'tot_pra' => $totPra,
                    'prop_id' => $opPropId,
                    'merger_group_id' => $mergerGroupId ?: null,
                    'is_merger' => $isMerger,
                ];
            });
        } catch (\Throwable $e) {
            Log::error('FFR direct OP capture failed', [
                'source_file_no' => $sourceFileNo,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to capture OP record.',
                'error' => $e->getMessage(),
            ], 500);
        }

        // Same reason as the existing-capture and New-mode paths above: this writes a
        // Transfer of Title to pra, and the Change of Ownership / FEFR lists are driven
        // from oss_applications, so without this mirror a directly captured OP is
        // visible in the FEFR modal and on no listing at all. The holder here is the
        // resolved current holder -- the Grantee of the Transfer of Title row this
        // method just wrote.
        //
        // Best-effort, like the other paths: the capture has already committed.
        $ossMirror = null;
        try {
            $ossMirror = app(\App\Services\MlsCommissioningOssApplicationService::class)->sync([
                'full_file_number' => $sourceFileNo,
                'file_name' => $resolvedCurrentHolder !== '' ? $resolvedCurrentHolder : $allottee,
                'plot_no' => $plotNo ?: null,
                'tp_no' => $tpNo ?: null,
                'location' => $location ?: null,
                'district' => null,
                'lga' => $lga ?: null,
                'land_use' => $landUse ?: null,
                'system_sub_type' => \App\Support\OssOpCommissionFilter::OSS,
                'sub_source' => 'OP Change of Ownership',
                'created_at' => now(),
            ]);

            Log::info('FFR direct OP capture mirrored to the OSS application list', [
                'source_file_no' => $sourceFileNo,
                'action' => $ossMirror['action'] ?? null,
                'oss_application_id' => $ossMirror['id'] ?? null,
                'user_id' => Auth::id(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Could not mirror FFR direct OP capture to the OSS application list', [
                'source_file_no' => $sourceFileNo,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
        }

        Log::info('FFR direct OP capture completed', [
            'source_file_no' => $sourceFileNo,
            'prop_id' => $result['prop_id'] ?? null,
            'user_id' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'OP and Transfer of Title captured successfully.',
            'merger_group_id' => $result['merger_group_id'] ?? null,
            'prop_id' => $result['prop_id'] ?? null,
            'data' => array_merge($result, ['oss_application' => $ossMirror]),
        ]);
    }

    private function resolveIndexedFileTitleByFileNo(string $sourceFileNo, string $fallback = ''): string
    {
        $fileNo = strtoupper(trim($sourceFileNo));
        if ($fileNo === '') {
            return strtoupper(trim($fallback));
        }

        $fromFileNumber = strtoupper(trim((string) (DB::connection('sqlsrv')
            ->table('fileNumber')
            ->whereRaw('UPPER(mlsfNo) = ?', [$fileNo])
            ->value('FileName') ?? '')));
        if ($fromFileNumber !== '') {
            return $fromFileNumber;
        }

        $indexingColumns = $this->sqlsrvColumnMap('file_indexings');
        $titleColumn = $indexingColumns['file_title'] ?? ($indexingColumns['file_name'] ?? null);
        if ($titleColumn !== null) {
            $fromIndexing = strtoupper(trim((string) (DB::connection('sqlsrv')
                ->table('file_indexings')
                ->whereRaw('UPPER(file_number) = ?', [$fileNo])
                ->value($titleColumn) ?? '')));
            if ($fromIndexing !== '') {
                return $fromIndexing;
            }
        }

        $mlsColumns = $this->sqlsrvColumnMap('mls_file_no');
        $mlsFileNoColumn = $mlsColumns['full_file_number'] ?? null;
        $mlsFileNameColumn = $mlsColumns['file_name'] ?? null;
        if ($mlsFileNoColumn !== null && $mlsFileNameColumn !== null) {
            $fromMls = strtoupper(trim((string) (DB::connection('sqlsrv')
                ->table('mls_file_no')
                ->whereRaw('UPPER([' . $mlsFileNoColumn . ']) = ?', [$fileNo])
                ->value($mlsFileNameColumn) ?? '')));
            if ($fromMls !== '') {
                return $fromMls;
            }
        }

        return strtoupper(trim($fallback));
    }

    private function safeUpdateByLookup(string $table, array $lookupCandidates, array $payload): int
    {
        $columns = $this->sqlsrvColumnMap($table);
        if (empty($columns)) {
            Log::warning('FFR safe update skipped: table has no discoverable columns', [
                'table' => $table,
            ]);
            return 0;
        }

        $filteredPayload = [];
        foreach ($payload as $column => $value) {
            if (isset($columns[strtolower($column)])) {
                $filteredPayload[$columns[strtolower($column)]] = $value;
            }
        }

        if (empty($filteredPayload)) {
            Log::warning('FFR safe update skipped: payload has no matching columns', [
                'table' => $table,
                'payload_keys' => array_keys($payload),
            ]);
            return 0;
        }

        foreach ($lookupCandidates as $column => $value) {
            $lookup = strtolower($column);
            if (!isset($columns[$lookup])) {
                continue;
            }

            return DB::connection('sqlsrv')
                ->table($table)
                ->where($columns[$lookup], $value)
                ->update($filteredPayload);
        }

        Log::warning('FFR safe update skipped: no matching lookup column found', [
            'table' => $table,
            'lookup_candidates' => array_keys($lookupCandidates),
        ]);

        return 0;
    }

    private function resolveMotherOpRecord(array $history): ?array
    {
        $candidates = collect($history)
            ->filter(fn($row) => is_array($row))
            ->filter(function (array $row): bool {
                $transactionType = strtolower(trim((string) (
                    $row['transaction_type']
                    ?? $row['instrument_type']
                    ?? ''
                )));

                if ($transactionType === '') {
                    return false;
                }

                $looksLikeOp = str_contains($transactionType, 'occupancy permit')
                    || preg_match('/\bop\b/i', $transactionType) === 1;

                if (!$looksLikeOp) {
                    return false;
                }

                // Mother OP should be the base OP transaction, not transfer rows like
                // "Transfer of Title (OP)".
                return !str_contains($transactionType, 'transfer');
            })
            ->values()
            ->all();

        if (empty($candidates)) {
            // Fallback: include Transfer of Title records if they have an op_serial_number
            $candidates = collect($history)
                ->filter(fn($row) => is_array($row))
                ->filter(function (array $row): bool {
                    $transactionType = strtolower(trim((string) (
                        $row['transaction_type']
                        ?? $row['instrument_type']
                        ?? ''
                    )));
                    $looksLikeOp = str_contains($transactionType, 'occupancy permit')
                        || preg_match('/\bop\b/i', $transactionType) === 1;

                    return $looksLikeOp && !empty($row['op_serial_number']);
                })
                ->values()
                ->all();
        }

        if (empty($candidates)) {
            return null;
        }

        usort($candidates, function (array $left, array $right): int {
            return $this->rowTimestampForMotherSelection($left) <=> $this->rowTimestampForMotherSelection($right);
        });

        return $candidates[0] ?? null;
    }

    private function firstFilledValue(?array $source, array $keys): ?string
    {
        if (!$source) {
            return null;
        }

        foreach ($keys as $key) {
            if (!array_key_exists($key, $source)) {
                continue;
            }

            $value = trim((string) $source[$key]);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function rowTimestampForMotherSelection(array $row): int
    {
        $dateValue = $this->firstFilledValue($row, [
            'reg_date',
            'regDate',
            'deeds_date',
            'transaction_date',
            'created_at',
            'updated_at',
        ]);

        if ($dateValue === null) {
            return PHP_INT_MAX;
        }

        $timeValue = $this->firstFilledValue($row, ['reg_time', 'regTime', 'deeds_time', 'transaction_time']);
        $candidate = $dateValue . ($timeValue ? (' ' . $timeValue) : '');
        $timestamp = strtotime($candidate);

        if ($timestamp === false) {
            $timestamp = strtotime($dateValue);
        }

        return $timestamp === false ? PHP_INT_MAX : $timestamp;
    }

    private function safeInsertRow(string $table, array $payload): bool
    {
        $columns = $this->sqlsrvColumnMap($table);
        if (empty($columns)) {
            Log::warning('FFR safe insert skipped: table has no discoverable columns', [
                'table' => $table,
            ]);
            return false;
        }

        $filteredPayload = [];
        foreach ($payload as $column => $value) {
            if (isset($columns[strtolower($column)])) {
                $filteredPayload[$columns[strtolower($column)]] = $value;
            }
        }

        if (empty($filteredPayload)) {
            Log::warning('FFR safe insert skipped: payload has no matching columns', [
                'table' => $table,
                'payload_keys' => array_keys($payload),
            ]);
            return false;
        }

        return (bool) DB::connection('sqlsrv')->table($table)->insert($filteredPayload);
    }

    private function sqlsrvColumnMap(string $table): array
    {
        static $cache = [];

        if (isset($cache[$table])) {
            return $cache[$table];
        }

        try {
            $list = DB::connection('sqlsrv')->getSchemaBuilder()->getColumnListing($table);
        } catch (\Throwable $e) {
            Log::warning('FFR schema inspection failed', [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);
            $list = [];
        }

        $map = [];
        foreach ($list as $column) {
            $map[strtolower((string) $column)] = (string) $column;
        }

        $cache[$table] = $map;
        return $cache[$table];
    }

    private function writeFfrDiagnostic(string $level, string $message, array $context = []): void
    {
        try {
            $logFile = storage_path('logs/ffr-change-of-name.log');
            $line = json_encode([
                'timestamp' => now()->toDateTimeString(),
                'level' => strtoupper($level),
                'message' => $message,
                'context' => $context,
            ], JSON_UNESCAPED_SLASHES);

            if ($line === false) {
                $line = sprintf(
                    '{"timestamp":"%s","level":"%s","message":"%s"}',
                    now()->toDateTimeString(),
                    strtoupper($level),
                    addslashes($message)
                );
            }

            @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND);
        } catch (\Throwable $exception) {
            Log::warning('FFR diagnostic fallback logging failed', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function resolveCustomerTypeFromName(string $name): string
    {
        $raw = trim($name);
        if ($raw === '') {
            return 'Individual';
        }

        $normalized = strtolower(preg_replace('/\s+/', ' ', $raw));

        $corporateTokens = [
            'ltd',
            'limited',
            'plc',
            'inc',
            'llc',
            'company',
            'co.',
            'corp',
            'corporate',
            'enterprise',
            'ventures',
            'bank',
            'foundation',
            'ministry',
            'government',
            'authority',
            'agency',
            'board',
            'association',
            'union',
            'church',
            'mosque',
            'nig.',
            'nigeria',
            'nig '
        ];

        foreach ($corporateTokens as $token) {
            if ($token !== '' && str_contains($normalized, $token)) {
                return 'Corporate';
            }
        }

        $multipleSignals = [
            'multiple',
            'multiple owners',
            'joint',
            'co-owner',
            'co owners',
            'co-owners',
            'partners',
            'partnership'
        ];

        foreach ($multipleSignals as $signal) {
            if ($signal !== '' && str_contains($normalized, $signal)) {
                return 'Multiple';
            }
        }

        if (preg_match('/\s(?:and|&)\s/i', $raw)) {
            return 'Multiple';
        }

        if (preg_match('/[\/,;]+/', $raw)) {
            return 'Multiple';
        }

        return 'Individual';
    }

    /**
     * Save Verification form data.
     */
    public function saveVerification(Request $request): JsonResponse
    {
        $request->validate([
            'record_id' => 'required|integer',
            'applicant_name' => 'required|string|max:255',
            'passport_photo' => 'nullable|image|mimes:jpeg,jpg,png|max:2048',
        ]);

        $recordId = (int) $request->input('record_id');

        $row = DB::connection('sqlsrv')
            ->table('oss_verifications')
            ->where('instrument_capture_id', $recordId)
            ->first();

        // Handle passport photo upload
        $passportPath = $row->passport_photo ?? null;
        if ($request->hasFile('passport_photo')) {
            $file = $request->file('passport_photo');
            $filename = 'ver_' . $recordId . '_' . time() . '.' . $file->getClientOriginalExtension();
            // Store on the 'public' disk (storage/app/public) so the /storage symlink serves it.
            $passportPath = $file->storeAs('oss_verifications/passports', $filename, 'public');
        }

        $data = [
            'instrument_capture_id' => $recordId,
            'applicant_name' => (string) $request->input('applicant_name', ''),
            'applicant_address' => (string) $request->input('address', ''),
            'applicant_phone' => (string) $request->input('phone', ''),
            'applicant_email' => (string) $request->input('email', ''),
            'id_type' => (string) $request->input('id_type', ''),
            'original_allottee' => (string) $request->input('original_allottee', ''),
            'op_number' => (string) $request->input('op_number', ''),
            'plot_no' => (string) $request->input('plot_plan', '') ? explode('/', (string) $request->input('plot_plan', ''))[0] ?? '' : '',
            'plan_no' => (string) $request->input('plot_plan', '') ? (explode('/', (string) $request->input('plot_plan', ''))[1] ?? '') : '',
            'location' => (string) $request->input('location', ''),
            'recommendation' => (string) $request->input('recommendation', ''),
            'chairman_name' => (string) $request->input('chairman_name', ''),
            'passport_photo' => $passportPath,
            'updated_at' => now(),
        ];

        if ($row) {
            DB::connection('sqlsrv')
                ->table('oss_verifications')
                ->where('id', $row->id)
                ->update($data);
        } else {
            $data['captured_by'] = Auth::id();
            $data['created_at'] = now();
            DB::connection('sqlsrv')
                ->table('oss_verifications')
                ->insert($data);
        }

        return response()->json([
            'success' => true,
            'message' => 'Verification saved successfully.',
        ]);
    }

    /**
     * Print the Change of Ownership form.
     * Data comes from the modal form fields via POST (not yet persisted).
     */
    public function printChangeOfOwnership(Request $request): View
    {
        $data = [
            'file_no' => strtoupper((string) $request->input('file_no', '')),
            'op_number' => strtoupper((string) $request->input('op_number', '')),
            'location' => strtoupper((string) $request->input('location', '')),
            'plot_no' => strtoupper((string) $request->input('plot_no', '')),
            'plan_no' => strtoupper((string) $request->input('plan_no', '')),
            'date_of_issuance' => (string) $request->input('date_of_issuance', ''),
            'original_name' => strtoupper((string) $request->input('original_name', '')),
            'original_address' => (string) $request->input('original_address', ''),
            'original_phone' => (string) $request->input('original_phone', ''),
            'current_name' => strtoupper((string) $request->input('current_name', '')),
            'current_address' => (string) $request->input('current_address', ''),
            'current_phone' => (string) $request->input('current_phone', ''),
            'ownership_method' => (string) $request->input('ownership_method', ''),
        ];

        return view('lands_one_stop_shop.print.print_change', compact('data'));
    }

    /**
     * Print the Verification form (FORM LN-03A).
     * Data comes from the modal form fields via POST (not yet persisted).
     */
    public function printVerification(Request $request): View
    {
        $data = [
            'applicant_name' => strtoupper((string) $request->input('applicant_name', '')),
            'applicant_address' => (string) $request->input('applicant_address', ''),
            'applicant_phone' => (string) $request->input('applicant_phone', ''),
            'applicant_email' => (string) $request->input('applicant_email', ''),
            'id_type' => (string) $request->input('id_type', ''),
            'original_allottee' => strtoupper((string) $request->input('original_allottee', '')),
            'op_number' => strtoupper((string) $request->input('op_number', '')),
            'plot_no' => strtoupper((string) $request->input('plot_no', '')),
            'plan_no' => strtoupper((string) $request->input('plan_no', '')),
            'location' => strtoupper((string) $request->input('location', '')),
            'recommendation' => (string) $request->input('recommendation', ''),
            'chairman_name' => (string) $request->input('chairman_name', ''),
            'passport_photo_url' => '',
        ];

        // Prefer the passport shown in the modal (uploaded preview or saved image URL),
        // so printing works even when the record hasn't been (re)saved yet.
        $postedPassport = trim((string) $request->input('passport_photo_url', ''));
        if ($postedPassport !== '' && preg_match('#^(https?://|data:image/)#i', $postedPassport)) {
            $data['passport_photo_url'] = $postedPassport;
        } else {
            // Fall back to the saved passport photo from the DB.
            $recordId = (int) $request->input('record_id', 0);
            if ($recordId) {
                $row = DB::connection('sqlsrv')
                    ->table('oss_verifications')
                    ->where('instrument_capture_id', $recordId)
                    ->first();
                if ($row && !empty($row->passport_photo)) {
                    $data['passport_photo_url'] = $this->ossPassportUrl($row->passport_photo);
                }
            }
        }

        return view('lands_one_stop_shop.print.print_verification', compact('data'));
    }

    /**
     * Print the Verification form directly from a previously saved verification record.
     */
    public function printVerificationByRecord(int $id): View
    {
        $row = DB::connection('sqlsrv')
            ->table('oss_verifications')
            ->where('instrument_capture_id', $id)
            ->first();

        abort_unless($row, 404, 'Verification not found for this record.');

        $data = [
            'applicant_name' => strtoupper((string) ($row->applicant_name ?? '')),
            'applicant_address' => (string) ($row->applicant_address ?? ''),
            'applicant_phone' => (string) ($row->applicant_phone ?? ''),
            'applicant_email' => (string) ($row->applicant_email ?? ''),
            'id_type' => (string) ($row->id_type ?? ''),
            'original_allottee' => strtoupper((string) ($row->original_allottee ?? '')),
            'op_number' => strtoupper((string) ($row->op_number ?? '')),
            'plot_no' => strtoupper((string) ($row->plot_no ?? '')),
            'plan_no' => strtoupper((string) ($row->plan_no ?? '')),
            'location' => strtoupper((string) ($row->location ?? '')),
            'recommendation' => (string) ($row->recommendation ?? ''),
            'chairman_name' => (string) ($row->chairman_name ?? ''),
            'passport_photo_url' => '',
        ];

        $data['passport_photo_url'] = $this->ossPassportUrl($row->passport_photo ?? '');

        return view('lands_one_stop_shop.print.print_verification', compact('data'));
    }

    /**
     * Search instrument_capture records for OP Resettlement
     * (by allottee, registration number, or OP serial number).
     */
    public function searchInstrumentCaptures(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('q'));

        $query = DB::connection('sqlsrv')
            ->table('instrument_capture')
            ->select([
                'id',
                'instrument_type',
                'mlsFNo',
                'temp_fileno',
                'land_use',
                'registration_number',
                'party_2_name',
                'property_description',
                'plot_number',
                'lga',
                'op_serial_number',
                'op_type',
                'tp_no',
            ])
            ->where('op_type', 'OP Resettlement')
            ->where(function ($q) {
                $q->where('instrument_type', 'Occupancy Permit (OP)')
                    ->orWhere('instrument_type', 'Occupancy Permit');
            });

        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('party_2_name', 'LIKE', "%{$term}%")
                    ->orWhere('registration_number', 'LIKE', "%{$term}%")
                    ->orWhere('op_serial_number', 'LIKE', "%{$term}%");
            });
        }

        $results = $query
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(function ($row) {
                return [
                    'id' => (int) $row->id,
                    'instrument_type' => strtoupper((string) ($row->instrument_type ?? '')),
                    'mlsFNo' => strtoupper((string) ($row->mlsFNo ?? '')),
                    'temp_fileno' => strtoupper((string) ($row->temp_fileno ?? '')),
                    'land_use' => strtoupper((string) ($row->land_use ?? '')),
                    'registration_number' => strtoupper((string) ($row->registration_number ?? '')),
                    'party_2_name' => strtoupper((string) ($row->party_2_name ?? '')),
                    'property_description' => strtoupper((string) ($row->property_description ?? '')),
                    'plot_number' => strtoupper((string) ($row->plot_number ?? '')),
                    'lga' => strtoupper((string) ($row->lga ?? '')),
                    'op_serial_number' => strtoupper((string) ($row->op_serial_number ?? '')),
                    'op_type' => strtoupper((string) ($row->op_type ?? '')),
                    'tp_no' => strtoupper((string) ($row->tp_no ?? '')),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $results,
        ]);
    }

    /**
     * Look up a PRA record by file number.
     * Used by the OSS application form to prefill fields from PRA history.
     */
    public function lookupFileIndexing(Request $request, PraRecordService $praService): JsonResponse
    {
        $fileNo = strtoupper(trim((string) $request->input('file_no')));

        if ($fileNo === '') {
            return response()->json([
                'success' => false,
                'message' => 'File number is required.',
            ], 422);
        }

        $indexingColumns = $this->sqlsrvColumnMap('file_indexings');
        $titleColumn = $indexingColumns['file_title'] ?? ($indexingColumns['file_name'] ?? null);
        $customerTypeColumn = $indexingColumns['customer_type'] ?? null;

        $indexingQuery = DB::connection('sqlsrv')
            ->table('file_indexings')
            ->whereRaw('UPPER(file_number) = ?', [$fileNo]);

        if ($titleColumn !== null) {
            $indexingQuery->addSelect(DB::raw("[{$titleColumn}] as applicant_title"));
        }
        if ($customerTypeColumn !== null) {
            $indexingQuery->addSelect(DB::raw("[{$customerTypeColumn}] as applicant_customer_type"));
        }

        $indexingAliases = [
            'file_number' => 'idx_file_number',
            'land_use' => 'idx_land_use',
            'plot_no' => 'idx_plot_no',
            'plan_no' => 'idx_plan_no',
            'location' => 'idx_location',
            'lga' => 'idx_lga',
            'tp_no' => 'idx_tp_no',
            'registration_number' => 'idx_registration_number',
            'phone' => 'idx_phone',
        ];

        foreach ($indexingAliases as $column => $alias) {
            if (isset($indexingColumns[$column])) {
                $actualColumn = $indexingColumns[$column];
                $indexingQuery->addSelect(DB::raw("[{$actualColumn}] as {$alias}"));
            }
        }

        $indexingRow = $indexingQuery->first();

        // Also check mls_file_no metadata table for indexed/commissioned files
        $mlsColumns = $this->sqlsrvColumnMap('mls_file_no');
        $mlsFileNumberColumn = $mlsColumns['full_file_number'] ?? null;
        $mlsFileNameColumn = $mlsColumns['file_name'] ?? null;
        $mlsLandUseColumn = $mlsColumns['land_use'] ?? null;
        $mlsCustomerTypeColumn = $mlsColumns['customer_type'] ?? null;

        $mlsRow = null;
        if ($mlsFileNumberColumn !== null) {
            $mlsQuery = DB::connection('sqlsrv')->table('mls_file_no')
                ->whereRaw('UPPER([' . $mlsFileNumberColumn . ']) = ?', [$fileNo]);

            if ($mlsFileNameColumn !== null) {
                $mlsQuery->addSelect(DB::raw('[' . $mlsFileNameColumn . '] as mls_file_name'));
            }
            if ($mlsLandUseColumn !== null) {
                $mlsQuery->addSelect(DB::raw('[' . $mlsLandUseColumn . '] as mls_land_use'));
            }
            if ($mlsCustomerTypeColumn !== null) {
                $mlsQuery->addSelect(DB::raw('[' . $mlsCustomerTypeColumn . '] as mls_customer_type'));
            }
            $mlsQuery->addSelect(DB::raw('[' . $mlsFileNumberColumn . '] as mls_file_number'));

            $mlsRow = $mlsQuery->first();
        }

        // Also check fileNumber table (files captured via Capture Existing / commissioning)
        $fileNumberRow = DB::connection('sqlsrv')
            ->table('fileNumber')
            ->whereRaw('UPPER(mlsfNo) = ?', [$fileNo])
            ->select('mlsfNo', 'FileName', 'plot_no', 'tp_no', 'lga', 'location', 'tracking_id')
            ->first();

        $records = $praService->findAllByFileNumber($fileNo);
        $totRecords = collect($records)
            ->map(static fn($record) => (array) $record)
            ->filter(function ($r) {
                $instrumentType = strtoupper(trim((string) ($r['instrument_type'] ?? '')));
                $transactionType = strtoupper(trim((string) ($r['transaction_type'] ?? '')));

                return str_contains($instrumentType, 'TRANSFER OF TITLE')
                    || str_contains($transactionType, 'TRANSFER OF TITLE');
            })
            ->sortByDesc(fn(array $record) => $this->rowTimestampForMotherSelection($record));

        $hasTransferOfTitle = $totRecords->isNotEmpty();
        $row = $totRecords->first() ?: [];

        // Occupancy Permit row — a file may carry an OP transaction separate from
        // (and usually older than) the latest transfer. The Occupancy Permit
        // Details section must backfill from THIS row, not the mother row.
        $opRow = collect($records)
            ->map(static fn ($record) => (array) $record)
            ->filter(function (array $r): bool {
                $t = strtoupper(trim((string) ($r['instrument_type'] ?? '') . ' ' . ($r['transaction_type'] ?? '')));
                return str_contains($t, 'OCCUPANCY PERMIT') || str_contains($t, 'OP)');
            })
            ->sortByDesc(fn (array $r) => $this->rowTimestampForMotherSelection($r))
            ->first() ?: [];
        $hasOccupancyPermit = !empty($opRow);

        if (!$hasTransferOfTitle && !$hasOccupancyPermit && !$indexingRow && !$mlsRow && !$fileNumberRow) {
            return response()->json([
                'success' => false,
                'message' => 'File not found in any registry or history.',
            ]);
        }

        $idx = static function ($indexingRow, string $alias): string {
            return strtoupper(trim((string) ($indexingRow->{$alias} ?? '')));
        };

        // Helper: get value from fileNumber row
        $fnr = static function (?object $fileNumberRow, string $col): string {
            return strtoupper(trim((string) ($fileNumberRow->{$col} ?? '')));
        };

        $fileNumber = strtoupper((string) ($this->firstFilledValue($row, [
            'mlsFNo',
            'fileno',
            'kangisFileNo',
            'NewKANGISFileno',
            'temp_fileno',
        ]) ?? ($idx($indexingRow, 'idx_file_number') ?: ($fnr($fileNumberRow, 'mlsfNo') ?: (strtoupper(trim((string) ($mlsRow->mls_file_number ?? ''))) ?: $fileNo)))));

        $applicantNameFromPra = strtoupper((string) ($this->firstFilledValue($row, [
            'Grantee',
            'party_2',
            'Assignee',
            'Mortgagee',
            'Lessee',
            'Surrenderee',
            'Grantor',
            'party_1',
            'Assignor',
            'Mortgagor',
            'Lessor',
        ]) ?? ''));

        $applicantNameFromIndexing = strtoupper(trim((string) ($indexingRow->applicant_title ?? '')));
        $applicantNameFromFileNumber = $fnr($fileNumberRow, 'FileName');
        $applicantNameFromMls = strtoupper(trim((string) ($mlsRow->mls_file_name ?? '')));
        $applicantName = $applicantNameFromIndexing !== '' ? $applicantNameFromIndexing
            : ($applicantNameFromPra !== '' ? $applicantNameFromPra
                : ($applicantNameFromFileNumber !== '' ? $applicantNameFromFileNumber : $applicantNameFromMls));

        $landUse = strtoupper((string) ($this->firstFilledValue($row, ['land_use', 'landUse']) ?? ($idx($indexingRow, 'idx_land_use') ?: ($fnr($fileNumberRow, 'land_use') ?: strtoupper(trim((string) ($mlsRow->mls_land_use ?? '')))))));
        $plotNo = strtoupper((string) ($this->firstFilledValue($row, ['plot_no', 'plotNo', 'plot_number']) ?? ($idx($indexingRow, 'idx_plot_no') ?: $fnr($fileNumberRow, 'plot_no'))));
        $planNo = strtoupper((string) ($this->firstFilledValue($row, ['approved_plan_no', 'tp_no', 'tpNo', 'lpkn_no']) ?? ($idx($indexingRow, 'idx_plan_no') ?: $fnr($fileNumberRow, 'tp_no'))));
        $location = strtoupper((string) ($this->firstFilledValue($row, ['location', 'property_description']) ?? ($idx($indexingRow, 'idx_location') ?: $fnr($fileNumberRow, 'location'))));
        $lga = strtoupper((string) ($this->firstFilledValue($row, ['lga', 'lgsaOrCity', 'lga_name']) ?? ($idx($indexingRow, 'idx_lga') ?: $fnr($fileNumberRow, 'lga'))));
        $state = strtoupper((string) ($this->firstFilledValue($row, ['state', 'state_name']) ?? ''));
        $registrationNo = strtoupper((string) ($this->firstFilledValue($row, ['regNo', 'reg_no', 'registration_number']) ?? $idx($indexingRow, 'idx_registration_number')));

        // ── Occupancy Permit Details (sourced from the OP row) ──
        $toIsoDate = static function ($value): string {
            $value = trim((string) $value);
            if ($value === '') {
                return '';
            }
            $ts = strtotime($value);
            return $ts !== false ? date('Y-m-d', $ts) : '';
        };

        // OP serial only from an OP-specific column (never the deed serial/page).
        $opSerialNo   = strtoupper((string) ($this->firstFilledValue($opRow, ['op_serial_number', 'op_serial', 'opSerialNo']) ?? ''));
        // Primary key of the OP row itself. The form posts it back on save so the
        // edited Occupancy Permit Details update THAT row, and never a sibling
        // sharing the same prop_id (an OP and its Transfer of Title do).
        $opRecordId   = (int) ($opRow['id'] ?? 0);
        $opPropId     = (string) ($opRow['prop_id'] ?? '');
        $opStatus     = trim((string) ($this->firstFilledValue($opRow, ['status']) ?? ''));
        $opPlotNo     = strtoupper((string) ($this->firstFilledValue($opRow, ['plot_no', 'plotNo']) ?? ''));
        $opTpNo       = strtoupper((string) ($this->firstFilledValue($opRow, ['tp_no', 'tpNo', 'approved_plan_no']) ?? ''));
        $opDistrict   = strtoupper((string) ($this->firstFilledValue($opRow, ['districtName', 'district']) ?? ''));
        $opLga        = strtoupper((string) ($this->firstFilledValue($opRow, ['lgsaOrCity', 'lga']) ?? ''));
        // PRA's `location` column is frequently the junk value "Other"; the real
        // text lives in property_description on those rows.
        $opLocation   = strtoupper((string) ($this->firstFilledValue($opRow, ['property_description', 'location']) ?? ''));
        if ($opLocation === 'OTHER') {
            $opLocation = '';
        }
        $opType       = strtoupper((string) ($this->firstFilledValue($opRow, ['op_type']) ?? ''));
        $opDeedSerial = strtoupper((string) ($this->firstFilledValue($opRow, ['serialNo', 'serial_no']) ?? ''));
        $opDeedPage   = strtoupper((string) ($this->firstFilledValue($opRow, ['pageNo', 'page_no']) ?? ''));
        $opDeedVol    = strtoupper((string) ($this->firstFilledValue($opRow, ['volumeNo', 'volNo', 'vol_no']) ?? ''));
        $opGrantor    = strtoupper((string) ($this->firstFilledValue($opRow, ['Grantor', 'grantor', 'party_1']) ?? 'KANO STATE GOVERNMENT'));
        $opGrantee    = strtoupper((string) ($this->firstFilledValue($opRow, ['Grantee', 'grantee', 'party_2']) ?? ''));
        $opLandUse    = strtoupper((string) ($this->firstFilledValue($opRow, ['land_use', 'landUse']) ?? ''));
        $opTxnDate    = $toIsoDate($this->firstFilledValue($opRow, ['transaction_date', 'reg_date', 'regDate']) ?? '');
        $opDeedsDate  = $toIsoDate($this->firstFilledValue($opRow, ['deeds_date', 'reg_date']) ?? '');
        $opDeedsTime  = trim((string) ($this->firstFilledValue($opRow, ['deeds_time', 'reg_time']) ?? ''));
        $phone = (string) ($this->firstFilledValue($row, [
            'phone',
            'phone_no',
            'gsm',
            'telephone',
            'telephone_no',
            'party_1_phone',
            'party_2_phone',
        ]) ?? trim((string) ($indexingRow->idx_phone ?? '')));
        $purpose = strtoupper((string) ($this->firstFilledValue($row, ['purpose', 'transaction_type', 'instrument_type']) ?? ''));
        $tpNo = strtoupper((string) ($this->firstFilledValue($row, ['tp_no', 'tpNo']) ?? ($idx($indexingRow, 'idx_tp_no') ?: $fnr($fileNumberRow, 'tp_no'))));

        return response()->json([
            'success' => true,
            'data' => [
                'id' => (int) ($row['id'] ?? 0),
                'file_number' => $fileNumber,
                'file_name' => $applicantName,
                'file_title' => $applicantName,
                'current_holder' => $applicantName,
                'tracking_id' => (string) ($row['prop_id'] ?? ($fileNumberRow->tracking_id ?? '')),
                'land_use' => $landUse,
                'plot_no' => $plotNo,
                'plan_no' => $planNo,
                'location' => $location,
                'lga' => $lga,
                'state' => $state,
                'tp_no' => $tpNo,
                'registration_number' => $registrationNo,
                'op_serial_number' => $opSerialNo,
                'phone' => $phone,
                'purpose' => $purpose,
                'customer_type' => strtoupper(trim((string) (($indexingRow->applicant_customer_type ?? '') ?: ($mlsRow->mls_customer_type ?? '')))),
                'has_transfer_of_title' => $hasTransferOfTitle,

                // Occupancy Permit Details backfill (from the OP transaction)
                'has_occupancy_permit' => $hasOccupancyPermit,
                'op_record_id'         => $opRecordId,
                'op_prop_id'           => $opPropId,
                'op_status'            => $opStatus,
                'op_plot_no'           => $opPlotNo,
                'op_tp_no'             => $opTpNo,
                'op_district'          => $opDistrict,
                'op_lga'               => $opLga,
                'op_location'          => $opLocation,
                'op_type'              => $opType,
                'op_land_use'          => $opLandUse,
                'grantor'              => $opGrantor,
                'grantee'              => $opGrantee,
                'transaction_date'     => $opTxnDate,
                'deed_serial_no'       => $opDeedSerial,
                'deed_page_no'         => $opDeedPage,
                'deed_vol_no'          => $opDeedVol,
                'deeds_date'           => $opDeedsDate,
                'deeds_time'           => $opDeedsTime,
            ],
        ]);
    }

    private function fileHasOccupancyPermit(string $fileNo, PraRecordService $praService): bool
    {
        $records = $praService->findAllByFileNumber(strtoupper(trim($fileNo)));

        return collect($records)->contains(function ($record) {
            $row = (array) $record;
            // Match the same signal the lookup uses to set has_occupancy_permit.
            $t = strtoupper(trim((string) ($row['instrument_type'] ?? '') . ' ' . ($row['transaction_type'] ?? '')));

            return str_contains($t, 'OCCUPANCY PERMIT') || str_contains($t, 'OP)');
        });
    }

    /**
     * Validation rules covering all 3 form types.
     */
    /**
     * Custom validation messages for passport photo.
     */
    private function passportValidationMessages(): array
    {
        return [
            'passport_photo.required_if' => 'A passport photograph is required for residential applications.',
            'op_serial_number.required_if' => 'OP Serial Number is required for Change of Name applications.',
            'passport_photo.image' => 'The passport photograph must be an image file.',
            'passport_photo.mimes' => 'The passport photograph must be a JPEG or PNG file.',
            'passport_photo.max' => 'The passport photograph may not be larger than 2MB.',
            'passport_photo.dimensions' => 'The passport photograph must be between 150x150 and 2000x2000 pixels.',
        ];
    }

    /**
     * Distinct, non-empty TP numbers from the file registry — used to populate
     * the OP "TP No." dropdown on the application form. Ordered for the picker.
     */
    private function ossDistinctTpNumbers(): \Illuminate\Support\Collection
    {
        try {
            return DB::connection('sqlsrv')->table('fileNumber')
                ->whereNotNull('tp_no')
                ->where('tp_no', '!=', '')
                ->distinct()
                ->orderBy('tp_no')
                ->pluck('tp_no');
        } catch (\Throwable $e) {
            return collect();
        }
    }

    /**
     * Make `occupancy_permit` survive a client that did not send it as a form array.
     *
     * The page builds it as a JS object and flattens it into occupancy_permit[key]
     * entries. A browser running an older applications.js appends the object itself,
     * which FormData stringifies to the literal "[object Object]" — the `array` rule
     * then rejects the whole application with "The occupancy permit must be an array"
     * and nothing can be saved (a 422 leaves no trace in the log, so it is invisible
     * server-side). Rather than block data entry on a stale asset:
     *
     *  - a JSON object string is decoded and used;
     *  - "[object Object]" (or any other scalar) carries no data, so it is dropped and
     *    the request proceeds without the OP block — the application saves, only the
     *    sync back onto the PRA Occupancy Permit row is skipped for that submission.
     */
    private function normalizeOccupancyPermitInput(Request $request): void
    {
        if (!$request->has('occupancy_permit')) {
            return;
        }

        $value = $request->input('occupancy_permit');
        if (is_array($value)) {
            return;
        }

        $decoded = is_string($value) ? json_decode($value, true) : null;
        if (is_array($decoded)) {
            $request->merge(['occupancy_permit' => $decoded]);
            return;
        }

        Log::warning('OSS application posted occupancy_permit as a scalar — dropping it', [
            'file_no' => $request->input('file_no'),
            'received' => is_scalar($value) ? (string) $value : gettype($value),
            'hint' => 'The client is probably running a cached or undeployed public/js/lands-one-stop-shop/applications.js.',
        ]);

        $request->request->remove('occupancy_permit');
        $request->query->remove('occupancy_permit');
    }

    private function validationRules(): array
    {
        return [
            'instrument_capture_id' => 'nullable|integer',
            'file_no' => 'nullable|string|max:120',
            'application_type' => 'required|in:residential,commercial,industrial,agricultural',
            'applicant_name' => 'required|string|max:255',
            'phone' => ['nullable', 'string', 'max:50', new NigerianPhone()],
            'email' => 'nullable|email|max:255',
            'state_of_origin' => 'nullable|string|max:100',
            'lga' => 'nullable|string|max:100',
            'nationality' => 'nullable|string|max:255',
            'correspondence_address' => 'nullable|string|max:2000',
            'purpose' => 'nullable|string|max:500',
            'plot_no' => 'nullable|string|max:100',
            'plan_no' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:500',
            'district' => 'nullable|string|max:255',
            'prev_allocated' => 'nullable|string|max:10',
            'prev_allocation_details' => 'nullable|string',
            'remarks' => 'nullable|string',

            // Residential
            'age' => 'nullable|string|max:10',
            'sex' => 'nullable|string|max:20',
            'marital_status' => 'nullable|string|max:50',
            'husband_name_address' => 'nullable|string|max:500',
            'residential_address' => 'nullable|string|max:2000',
            'business_address' => 'nullable|string|max:2000',
            'occupation' => 'nullable|string|max:255',
            'annual_income' => 'nullable|string|max:100',

            // Commercial
            'home_domicile' => 'nullable|string|max:255',
            'occupation_or_business' => 'nullable|string|max:255',
            'nature_of_commerce' => 'nullable|string|max:500',
            'company_registered_under' => 'nullable|string|max:500',
            'registration_particulars' => 'nullable|string|max:500',
            'business_location' => 'nullable|string|max:500',
            'annual_income_anticipation' => 'nullable|string|max:100',
            'prev_land_purpose' => 'nullable|string|max:500',
            'intended_activities' => 'nullable|string',

            // Industrial
            'nature_of_occupation' => 'nullable|string|max:500',
            'annual_income_turnover' => 'nullable|string|max:100',
            'number_of_employees' => 'nullable|string|max:50',
            'nature_of_industrial' => 'nullable|string|max:500',
            'waste_disposal_requirements' => 'nullable|string',
            'nature_of_agricultural' => 'nullable|string|max:500',

            // Address builder sub-fields (residential)
            'res_addr_house' => 'nullable|string|max:100',
            'res_addr_plot' => 'nullable|string|max:100',
            'res_addr_street' => 'nullable|string|max:255',
            'res_addr_street_other' => 'nullable|string|max:255',
            'res_addr_district' => 'nullable|string|max:255',
            'res_addr_district_other' => 'nullable|string|max:255',
            'res_addr_lga' => 'nullable|string|max:255',
            'res_addr_state' => 'nullable|string|max:255',
            'res_corr_house' => 'nullable|string|max:100',
            'res_corr_plot' => 'nullable|string|max:100',
            'res_corr_street' => 'nullable|string|max:255',
            'res_corr_street_other' => 'nullable|string|max:255',
            'res_corr_district' => 'nullable|string|max:255',
            'res_corr_district_other' => 'nullable|string|max:255',
            'res_corr_lga' => 'nullable|string|max:255',
            'res_corr_state' => 'nullable|string|max:255',
            'res_biz_house' => 'nullable|string|max:100',
            'res_biz_plot' => 'nullable|string|max:100',
            'res_biz_street' => 'nullable|string|max:255',
            'res_biz_street_other' => 'nullable|string|max:255',
            'res_biz_district' => 'nullable|string|max:255',
            'res_biz_district_other' => 'nullable|string|max:255',
            'res_biz_lga' => 'nullable|string|max:255',
            'res_biz_state' => 'nullable|string|max:255',
            // Address builder sub-fields (commercial)
            'com_biz_house' => 'nullable|string|max:100',
            'com_biz_plot' => 'nullable|string|max:100',
            'com_biz_street' => 'nullable|string|max:255',
            'com_biz_street_other' => 'nullable|string|max:255',
            'com_biz_district' => 'nullable|string|max:255',
            'com_biz_district_other' => 'nullable|string|max:255',
            'com_biz_lga' => 'nullable|string|max:255',
            'com_biz_state' => 'nullable|string|max:255',
            'com_corr_house' => 'nullable|string|max:100',
            'com_corr_plot' => 'nullable|string|max:100',
            'com_corr_street' => 'nullable|string|max:255',
            'com_corr_street_other' => 'nullable|string|max:255',
            'com_corr_district' => 'nullable|string|max:255',
            'com_corr_district_other' => 'nullable|string|max:255',
            'com_corr_lga' => 'nullable|string|max:255',
            'com_corr_state' => 'nullable|string|max:255',
            // Address builder sub-fields (industrial)
            'ind_biz_house' => 'nullable|string|max:100',
            'ind_biz_plot' => 'nullable|string|max:100',
            'ind_biz_street' => 'nullable|string|max:255',
            'ind_biz_street_other' => 'nullable|string|max:255',
            'ind_biz_district' => 'nullable|string|max:255',
            'ind_biz_district_other' => 'nullable|string|max:255',
            'ind_biz_lga' => 'nullable|string|max:255',
            'ind_biz_state' => 'nullable|string|max:255',
            'ind_corr_house' => 'nullable|string|max:100',
            'ind_corr_plot' => 'nullable|string|max:100',
            'ind_corr_street' => 'nullable|string|max:255',
            'ind_corr_street_other' => 'nullable|string|max:255',
            'ind_corr_district' => 'nullable|string|max:255',
            'ind_corr_district_other' => 'nullable|string|max:255',
            'ind_corr_lga' => 'nullable|string|max:255',
            'ind_corr_state' => 'nullable|string|max:255',
            // Address builder sub-fields (agricultural)
            'agr_biz_house' => 'nullable|string|max:100',
            'agr_biz_plot' => 'nullable|string|max:100',
            'agr_biz_street' => 'nullable|string|max:255',
            'agr_biz_street_other' => 'nullable|string|max:255',
            'agr_biz_district' => 'nullable|string|max:255',
            'agr_biz_district_other' => 'nullable|string|max:255',
            'agr_biz_lga' => 'nullable|string|max:255',
            'agr_biz_state' => 'nullable|string|max:255',
            'agr_corr_house' => 'nullable|string|max:100',
            'agr_corr_plot' => 'nullable|string|max:100',
            'agr_corr_street' => 'nullable|string|max:255',
            'agr_corr_street_other' => 'nullable|string|max:255',
            'agr_corr_district' => 'nullable|string|max:255',
            'agr_corr_district_other' => 'nullable|string|max:255',
            'agr_corr_lga' => 'nullable|string|max:255',
            'agr_corr_state' => 'nullable|string|max:255',

            // Passport photo (required only for residential applications on create; per-method override on update)
            'passport_photo' => 'required_if:application_type,residential|image|mimes:jpeg,jpg,png|max:2048|dimensions:min_width=150,min_height=150,max_width=2000,max_height=2000',
            // System source (for Change of Name filtering)
            'system_source' => 'nullable|string|max:50',
            // OP Serial Number (for manual capture when not found in record).
            // Mandatory for Change of Name applications so no row is saved without one.
            'op_serial_number' => 'required_if:system_source,OSSOPCHANGEOFNAME|nullable|string|max:100',

            // ── Occupancy Permit Details ──
            // Not columns on oss_applications: this block is synced back onto the
            // PRA Occupancy Permit row identified by occupancy_permit.record_id.
            'has_occupancy_permit' => 'nullable|boolean',
            'op_from_record' => 'nullable|boolean',
            'occupancy_permit' => 'nullable|array',
            'occupancy_permit.record_id' => 'nullable|integer',
            'occupancy_permit.from_record' => 'nullable|boolean',
            'occupancy_permit.status' => 'nullable|string|max:100',
            'occupancy_permit.op_type' => 'nullable|string|max:100',
            'occupancy_permit.op_serial_number' => 'nullable|string|max:100',
            'occupancy_permit.transaction_date' => 'nullable|date',
            'occupancy_permit.file_number' => 'nullable|string|max:120',
            'occupancy_permit.land_use' => 'nullable|string|max:100',
            'occupancy_permit.grantor' => 'nullable|string|max:255',
            'occupancy_permit.grantee' => 'nullable|string|max:255',
            'occupancy_permit.serial_no' => 'nullable|string|max:50',
            'occupancy_permit.page_no' => 'nullable|string|max:50',
            'occupancy_permit.vol_no' => 'nullable|string|max:50',
            'occupancy_permit.deeds_time' => 'nullable|string|max:20',
            'occupancy_permit.deeds_date' => 'nullable|date',
        ];
    }

    /**
     * Push the form's "Occupancy Permit Details" back onto the PRA Occupancy
     * Permit row it was backfilled from.
     *
     * The row is addressed by its primary key (posted as occupancy_permit.record_id,
     * handed out by lookupFileIndexing) rather than by prop_id: an OP and its
     * Transfer of Title share a prop_id, so a prop_id write would hit the wrong
     * sibling. A blank field is treated as "not edited" and left alone — the
     * section is rendered read-only until the user clicks Edit, and half the
     * inputs are disabled, so an empty value is never a deliberate erase.
     *
     * Only existing OP rows are updated. Creating a PRA OP from here would have
     * to mint a prop_id, and the form already hides this section (and blocks
     * Save) when the selected file has no OP, so that path cannot be reached.
     */
    private function syncOccupancyPermitToPra(Request $request, string $fileNo): void
    {
        $op = $request->input('occupancy_permit');
        if (!is_array($op) || empty($op)) {
            return;
        }

        if (!filter_var($request->input('has_occupancy_permit', 1), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $recordId = (int) ($op['record_id'] ?? 0);
        if ($recordId <= 0) {
            Log::info('OSS: occupancy permit not synced — no PRA record id posted', [
                'file_no' => $fileNo,
            ]);
            return;
        }

        $val = static function (array $op, string $key): ?string {
            $v = trim((string) ($op[$key] ?? ''));
            return $v === '' ? null : $v;
        };

        // The dropdown offers "Resettlement" / "Direct Allocation"; PRA stores
        // these prefixed ("OP Resettlement"). Write PRA's form back so a save
        // through this screen does not quietly restyle the value.
        $opType = $val($op, 'op_type');
        if ($opType !== null && stripos($opType, 'OP ') !== 0) {
            $opType = 'OP ' . $opType;
        }

        $updates = array_filter([
            'status' => $val($op, 'status'),
            'op_type' => $opType,
            'op_serial_number' => $val($op, 'op_serial_number'),
            'transaction_date' => $val($op, 'transaction_date'),
            'land_use' => $val($op, 'land_use'),
            'Grantor' => $val($op, 'grantor'),
            'party_1' => $val($op, 'grantor'),
            'Grantee' => $val($op, 'grantee'),
            'party_2' => $val($op, 'grantee'),
            'serialNo' => $val($op, 'serial_no'),
            'pageNo' => $val($op, 'page_no'),
            'volumeNo' => $val($op, 'vol_no'),
            'deeds_date' => $val($op, 'deeds_date'),
            'deeds_time' => $val($op, 'deeds_time'),
        ], static fn ($v) => $v !== null);

        // regNo is the composed serial/page/volume — keep it consistent with the
        // three parts rather than letting it drift.
        $serial = $updates['serialNo'] ?? null;
        $page = $updates['pageNo'] ?? null;
        $vol = $updates['volumeNo'] ?? null;
        if ($serial !== null && $page !== null && $vol !== null) {
            $updates['regNo'] = $serial . '/' . $page . '/' . $vol;
        }

        if (empty($updates)) {
            return;
        }

        $updates['updated_by'] = Auth::id();
        $updates['updated_at'] = now();

        try {
            $affected = DB::connection('sqlsrv')->table('pra')
                ->where('id', $recordId)
                ->update($updates);

            Log::info('OSS: occupancy permit synced to PRA', [
                'pra_id' => $recordId,
                'file_no' => $fileNo,
                'affected' => $affected,
                'fields' => array_keys($updates),
            ]);
        } catch (\Throwable $e) {
            // A failed OP sync must not roll back the application itself.
            Log::error('OSS: occupancy permit sync failed', [
                'pra_id' => $recordId,
                'file_no' => $fileNo,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
