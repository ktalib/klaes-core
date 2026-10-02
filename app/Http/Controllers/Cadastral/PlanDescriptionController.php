<?php

namespace App\Http\Controllers\Cadastral;

use App\Http\Controllers\Controller;
use App\Models\Cadastral\CadastralBill;
use App\Models\Cadastral\CadastralChart;
use App\Models\Cadastral\CadastralFileReceipt;
use App\Models\Cadastral\CadastralPillar;
use App\Models\Cadastral\CadastralPlanDescription;
use App\Models\Cadastral\CadastralReport;
use App\Services\Cadastral\AreaCalculator;
use App\Services\Cadastral\CadastralAddress;
use App\Services\Cadastral\CadastralBillCalculator;
use App\Services\Cadastral\CadastralRegistryLookup;
use App\Services\Cadastral\CadastralSettings;
use App\Services\Cadastral\LandDescriptionGenerator;
use App\Support\FileNumberLandUse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The Plan and Description Unit (concept note 4.4): area, pillars, fee and the
 * land description.
 *
 * THE TOTAL IS NEVER TAKEN FROM THE BROWSER. Every amount is recomputed
 * server-side from the stored area and the stored pillar rows before a bill is
 * issued. It is the one field on this screen an applicant would most like to
 * edit, so the posted value is not read at all.
 *
 * AREA COMES FROM THE TRAVERSE, NOT FROM A PICTURE. The note asks for automatic
 * area calculation "from digital charts"; nothing in KLAES can read an area out
 * of a scanned chart or a CAD file. What is computable is the area enclosed by
 * the beacon coordinates, and a hand-entered figure is recorded as hand-entered.
 *
 * THREE PAGES OVER ONE RECORD. Area & Pillars and Descriptions (Phase 6) and
 * the Fee Calculator (Phase 7) are their own pages; the combined edit screen
 * remains for the record's other fields. All read and write the same
 * cadastral_plan_descriptions row and its cadastral_pillars rows.
 */
class PlanDescriptionController extends Controller
{
    /** Receipts in these states are not offered on the Area & Pillars picker. */
    private const DEAD_RECEIPTS = ['Rejected', 'Returned'];

    /** The officer-entered Fee Calculator fields; nothing else is read from a request. */
    private const FEE_INPUTS = ['delay_days', 'transport_band', 'transport_km', 'field_work_days', 'office_work_days', 'plan_prints'];

    /** File-number land-use codes, as the record's LAND_USES spell them. */
    private const LAND_USE_CODES = [
        'RES' => 'Residential', 'COM' => 'Commercial', 'IND' => 'Industrial',
        'AG' => 'Agricultural', 'AGR' => 'Agricultural', 'AGRIC' => 'Agricultural',
        'MIX' => 'Mixed', 'MIXED' => 'Mixed', 'INS' => 'Institutional',
    ];

    public function __construct(
        private AreaCalculator $area,
        private CadastralBillCalculator $bills,
        private LandDescriptionGenerator $descriptions,
        private CadastralSettings $settings,
    ) {}

    public function index(Request $r)
    {
        // The sidebar used to open this register with ?view= to stand in for the
        // two pages Phase 6 split out. Old links and bookmarks land on them now.
        if ($r->query('view') === 'area') {
            return redirect()->route('cadastral-module.plan-description.area');
        }
        if ($r->query('view') === 'descriptions') {
            return redirect()->route('cadastral-module.plan-description.descriptions');
        }
        if ($r->query('view') === 'fees') {
            return redirect()->route('cadastral-module.plan-description.fees');
        }

        $q = CadastralPlanDescription::query()->withCount('pillars');

        if ($term = trim((string) $r->query('q', ''))) {
            $q->where(function ($w) use ($term) {
                $w->where('pd_ref', 'like', "%$term%")
                  ->orWhere('file_number', 'like', "%$term%")
                  ->orWhere('file_title', 'like', "%$term%");
            });
        }
        if ($u = $r->query('land_use'))      $q->where('land_use', $u);
        if ($z = $r->query('location_zone')) $q->where('location_zone', $z);

        $records = $q->orderByDesc('id')->paginate(15)->withQueryString();

        $stats = [
            'total'      => CadastralPlanDescription::count(),
            'with_area'  => CadastralPlanDescription::whereNotNull('area_sqm')->count(),
            'billed'     => CadastralBill::where('status', 'Issued')->count(),
            'billed_sum' => (float) CadastralBill::where('status', 'Issued')->sum('grand_total'),
        ];

        return view('cadastral_module.pnd.index', compact('records', 'stats'));
    }

    public function create(Request $r)
    {
        $chart = $r->query('chart') ? CadastralChart::find($r->query('chart')) : null;

        // The whole location group comes across from the chart, including an
        // "Other" district's free text and the chart's own plot number.
        $record = new CadastralPlanDescription([
            'file_number'        => $chart?->file_number ?? $r->query('file_number'),
            'file_title'         => $chart?->file_title,
            'cadastral_chart_id' => $chart?->id,
            'area_sqm'           => $chart?->area_sqm,
            'plot_size_sqm'      => app(\App\Services\Cadastral\CadastralSettings::class)->plotSizeSqm(),
        ] + CadastralAddress::inherit($chart, 'prop_', 'plot_no'));

        return view('cadastral_module.pnd.register', [
            'record'    => $record,
            'pillars'   => collect(),
            'bill'      => null,
            'templates' => $this->descriptions->templates(),
            'areas'     => $this->area->convert($record->area_sqm, $record->plot_size_sqm),
        ]);
    }

    public function store(Request $r)
    {
        $data = $this->validated($r);

        $data['pd_ref'] = CadastralPlanDescription::nextRef('pd_ref', 'PND', 4);

        $this->applyArea($data);

        $record = CadastralPlanDescription::create($data);

        return redirect()
            ->route('cadastral-module.plan-description.edit', $record)
            ->with('success', "{$record->pd_ref} created. Add the pillars, then generate the bill.");
    }

    public function edit(CadastralPlanDescription $planDescription)
    {
        return view('cadastral_module.pnd.register', [
            'record'    => $planDescription->load('chart'),
            'pillars'   => $planDescription->pillars()->get(),
            'bill'      => $planDescription->bills()->where('status', '!=', 'Cancelled')->first(),
            'templates' => $this->descriptions->templates(),
            'areas'     => $this->area->convert($planDescription->area_sqm, $planDescription->plot_size_sqm),
        ]);
    }

    public function update(Request $r, CadastralPlanDescription $planDescription)
    {
        $data = $this->validated($r, $planDescription);

        $this->applyArea($data);

        $planDescription->update($data);

        return back()->with('success', "{$planDescription->pd_ref} updated.");
    }

    public function destroy(CadastralPlanDescription $planDescription)
    {
        if ($planDescription->bills()->where('status', 'Issued')->exists()) {
            return back()->with('error',
                "{$planDescription->pd_ref} has an issued bill and cannot be deleted. Cancel the bill first.");
        }

        $ref = $planDescription->pd_ref;
        $planDescription->delete();

        return back()->with('success', "{$ref} deleted.");
    }

    /* ======================= Area & Pillars (Phase 6) ======================= */

    /**
     * Area & Pillars. With ?record= it works on that record; without, it offers
     * the registered-intake picker that starts one.
     *
     * WHY INTAKE RECEIPTS, NOT CHARTS. Every file the department works on comes
     * through intake, and only some are charted (conversion files never are).
     * Picking from charts would leave the uncharted files with no way in, and
     * a free-text file number would let a record exist for a file the registry
     * never received. The chart is found from the picked file instead, and
     * offered as the area source when it has a beacon ring.
     */
    public function area(Request $r)
    {
        $record = $r->filled('record')
            ? CadastralPlanDescription::with('chart')->find((int) $r->query('record'))
            : null;

        if ($r->filled('record') && ! $record) {
            return redirect()->route('cadastral-module.plan-description.area')
                ->with('error', 'That plan and description record does not exist.');
        }

        $plotSize = $record?->plot_size_sqm ?: $this->settings->plotSizeSqm();
        $chart    = $record ? ($this->currentChartFor($record->file_number) ?? $record->chart) : null;
        $pillars  = $record ? $record->pillars()->get() : collect();

        // After a failed start, put the picked receipt back in the picker.
        $receiptId = (int) old('cadastral_file_receipt_id', $r->query('receipt'));
        $picked    = (! $record && $receiptId)
            ? ($this->formatReceipts(CadastralFileReceipt::whereKey($receiptId)->get())[0] ?? null)
            : null;

        return view('cadastral_module.pnd.area', [
            'record'     => $record,
            'picked'     => $picked,
            'chart'      => $chart,
            'chartArea'  => $chart ? $this->area->areaSqmForChart($chart) : null,
            'chartPoints'=> $chart ? $this->area->plottableCount($chart->coordinates()->get()) : 0,
            'pillars'    => $pillars,
            'plotSize'   => $plotSize,
            'areas'      => $this->area->convert($record?->area_sqm, $plotSize),
            'check'      => $record ? $this->boundaryCheck($record, $pillars) : null,
            'factors'    => $this->area->factors(),
            'units'      => AreaCalculator::UNITS,
            'recent'     => CadastralPlanDescription::query()->withCount('pillars')->orderByDesc('id')->limit(10)->get(),
        ]);
    }

    /**
     * Select2 source for the Area & Pillars picker: registered intake receipts
     * whose file number (or receipt ref) starts with the term — the Phase 5
     * index-card picker's rule. GET and verb-free, so it infers `view`.
     */
    public function intakeFiles(Request $r): JsonResponse
    {
        $term = trim((string) $r->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['results' => [], 'pagination' => ['more' => false]]);
        }

        $search = function (bool $contains) use ($term) {
            $like = ($contains ? '%' : '') . $this->escapeLike($term) . '%';

            return CadastralFileReceipt::query()
                ->whereNotNull('registered_at')
                ->whereNotIn('status', self::DEAD_RECEIPTS)
                ->where(fn ($w) => $w->where('file_number', 'like', $like)->orWhere('receipt_ref', 'like', $like))
                ->orderBy('file_number')
                ->limit(25)
                ->get();
        };

        $rows = $search(false);
        if ($rows->isEmpty() && mb_strlen($term) >= 3) {
            $rows = $search(true);
        }

        return response()->json(['results' => $this->formatReceipts($rows), 'pagination' => ['more' => false]]);
    }

    /**
     * Start a record from a registered intake file.
     *
     * File number, owner and location are copied from the receipt on the
     * server; nothing typed in the browser can set them. One working record
     * per file: a second start opens the first.
     */
    public function areaStore(Request $r)
    {
        $data    = $this->validatedArea($r, true);
        $receipt = $this->requireReceipt((int) $data['cadastral_file_receipt_id']);

        $existing = CadastralPlanDescription::query()
            ->where('file_number', $receipt->file_number)
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            return redirect()
                ->route('cadastral-module.plan-description.area', ['record' => $existing->id])
                ->with('error', "{$receipt->file_number} already has {$existing->pd_ref}; it is open below. Nothing new was created.");
        }

        $chart = $this->currentChartFor($receipt->file_number);

        $row = [
            'file_number'            => $receipt->file_number,
            'file_title'             => $receipt->file_title,
            'cadastral_chart_id'     => $chart?->id,
            'cadastral_report_id'    => CadastralReport::query()
                ->where('cadastral_file_receipt_id', $receipt->id)->orderByDesc('id')->value('id'),
            'land_use'               => $data['land_use'] ?? $this->landUseFor($receipt->file_number),
            'location_zone'          => $data['location_zone'] ?? null,
            'plot_size_sqm'          => $data['plot_size_sqm'] ?? $this->settings->plotSizeSqm(),
            'description_complexity' => 'standard',
        ] + CadastralAddress::inherit($receipt, 'prop_');

        $this->applyTypedArea($row, $data, $chart);

        $row['pd_ref'] = CadastralPlanDescription::nextRef('pd_ref', 'PND', 4);

        $record = CadastralPlanDescription::create($row);

        return redirect()
            ->route('cadastral-module.plan-description.area', ['record' => $record->id])
            ->with('success', "{$record->pd_ref} started for {$record->file_number}. Add the pillars below.");
    }

    /** Re-record the area, land use and plot size of an existing record. */
    public function areaUpdate(Request $r, CadastralPlanDescription $planDescription)
    {
        $data  = $this->validatedArea($r, false);
        $chart = $this->currentChartFor($planDescription->file_number) ?? $planDescription->chart;

        $row = [
            // A chart re-versioned since the record started is followed to its
            // current version, so "use the chart area" reads today's ring.
            'cadastral_chart_id' => $chart?->id,
            'land_use'           => $data['land_use'] ?? null,
            'location_zone'      => $data['location_zone'] ?? null,
            'plot_size_sqm'      => $data['plot_size_sqm'] ?? ($planDescription->plot_size_sqm ?: $this->settings->plotSizeSqm()),
        ];

        $this->applyTypedArea($row, $data, $chart);

        $planDescription->update($row);

        return redirect()
            ->route('cadastral-module.plan-description.area', ['record' => $planDescription->id])
            ->with('success', "{$planDescription->pd_ref}: area saved"
                . ($row['area_sqm'] !== null ? ' (' . number_format($row['area_sqm'], 2) . ' m², ' . $row['area_source'] . ').' : ' (cleared).'));
    }

    /* ======================== Descriptions (Phase 6) ======================== */

    /**
     * The Descriptions page: the form for one record (?record=) above the
     * table of every record that has a saved description.
     */
    public function descriptionsPage(Request $r)
    {
        $record = $r->filled('record')
            ? CadastralPlanDescription::with('chart')->find((int) $r->query('record'))
            : null;

        if ($r->filled('record') && ! $record) {
            return redirect()->route('cadastral-module.plan-description.descriptions')
                ->with('error', 'That plan and description record does not exist.');
        }

        $saved = CadastralPlanDescription::query()
            ->whereNotNull('description_body')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('cadastral_module.pnd.descriptions', [
            'record'    => $record,
            'picked'    => $record ? $this->formatRecords(collect([$record]))[0] : null,
            'pillars'   => $record ? $record->pillars()->get() : collect(),
            'areas'     => $this->area->convert($record?->area_sqm, $record?->plot_size_sqm ?: $this->settings->plotSizeSqm()),
            'templates' => $this->descriptions->templates(),
            'template'  => $record ? ($record->template_key ?: $this->descriptions->defaultKeyFor($record)) : null,
            'saved'     => $saved,
        ]);
    }

    /**
     * Select2 source for the Descriptions page's File box: plan and
     * description records by file number, reference or owner. GET and
     * verb-free, so it infers `view`.
     */
    public function descriptionFiles(Request $r): JsonResponse
    {
        $term = trim((string) $r->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['results' => [], 'pagination' => ['more' => false]]);
        }

        $like = '%' . $this->escapeLike($term) . '%';

        $rows = CadastralPlanDescription::query()
            ->where(fn ($w) => $w->where('file_number', 'like', $like)
                ->orWhere('pd_ref', 'like', $like)
                ->orWhere('file_title', 'like', $like))
            ->orderBy('file_number')
            ->limit(25)
            ->get();

        return response()->json(['results' => $this->formatRecords($rows), 'pagination' => ['more' => false]]);
    }

    /**
     * The saved description as a clean A4 sheet.
     *
     * There is no official template for this document (docs/templates/cadastral
     * holds the fee sheet and the report on application only), so the layout
     * follows the module's other prints.
     */
    public function printDescription(CadastralPlanDescription $planDescription)
    {
        if (trim((string) $planDescription->description_body) === '') {
            return redirect()
                ->route('cadastral-module.plan-description.descriptions', ['record' => $planDescription->id])
                ->with('error', 'There is no saved description to print yet. Generate one, then save it.');
        }

        return view('cadastral_module.pnd.description_print', [
            'record'   => $planDescription->load('chart'),
            'pillars'  => $planDescription->pillars()->get(),
            'areas'    => $this->area->convert($planDescription->area_sqm, $planDescription->plot_size_sqm ?: $this->settings->plotSizeSqm()),
            'template' => $this->descriptions->templates()[$planDescription->template_key] ?? null,
        ]);
    }

    /**
     * Area in every unit, for the live panel. GET so it reads as `view`.
     */
    public function previewArea(Request $r): JsonResponse
    {
        $chart = $r->query('chart') ? CadastralChart::with('coordinates')->find($r->query('chart')) : null;

        $sqm = $r->filled('area_sqm')
            ? (float) $r->query('area_sqm')
            : ($chart ? $this->area->areaSqmForChart($chart) : null);

        $plot = $r->filled('plot_size_sqm')
            ? (float) $r->query('plot_size_sqm')
            : null;

        return response()->json([
            'ok'        => $sqm !== null,
            'areas'     => $this->area->convert($sqm, $plot),
            'source'    => $r->filled('area_sqm') ? 'manual' : ($chart ? 'coordinates' : null),
            'perimeter' => $chart ? $this->area->perimeterM($chart->coordinates) : null,
        ]);
    }

    /**
     * Replace the pillar list in one transaction, for the same reason the beacon
     * ring is replaced whole: the count drives the bill, and a half-applied
     * update would bill the wrong number.
     *
     * FLAGGED, NEVER DELETED. The list is a working schedule rewritten on every
     * save; the replaced rows are soft-deleted, so earlier schedules stay on
     * record. The bill keeps its own pillar counts and unit price.
     */
    public function savePillars(Request $r, CadastralPlanDescription $planDescription)
    {
        // A non-numeric ordinate would otherwise reach a decimal column and fail
        // as a SQL error halfway through the replacement.
        $r->validate([
            'pillars'                 => 'nullable|array|max:500',
            'pillars.*.pillar_number' => 'nullable|string|max:50',
            'pillars.*.ownership'     => ['nullable', Rule::in([CadastralPillar::OWNERSHIP_GOVERNMENT, CadastralPillar::OWNERSHIP_PRIVATE])],
            'pillars.*.pillar_type'   => 'nullable|string|max:50',
            'pillars.*.northing'      => 'nullable|numeric|between:-999999999999,999999999999',
            'pillars.*.easting'       => 'nullable|numeric|between:-999999999999,999999999999',
            'pillars.*.latitude'      => 'nullable|numeric|between:-90,90',
            'pillars.*.longitude'     => 'nullable|numeric|between:-180,180',
            'pillars.*.condition'     => ['nullable', Rule::in(CadastralPillar::CONDITIONS)],
        ], [
            'pillars.*.northing.numeric'  => 'Each northing must be a number.',
            'pillars.*.easting.numeric'   => 'Each easting must be a number.',
            'pillars.*.latitude.between'  => 'A latitude lies between -90 and 90.',
            'pillars.*.longitude.between' => 'A longitude lies between -180 and 180.',
        ]);

        $rows = $r->input('pillars', []);

        if (! is_array($rows)) {
            return back()->with('error', 'No pillars were submitted.');
        }

        DB::connection('sqlsrv')->transaction(function () use ($planDescription, $rows) {
            // Soft delete: the replaced schedule stays on record (deleted_at set),
            // and pillars() — like every other read — never sees it again.
            $planDescription->pillars()->get()->each->delete();

            $order = 0;

            foreach ($rows as $row) {
                $number    = trim((string) ($row['pillar_number'] ?? ''));
                $ownership = ($row['ownership'] ?? '') === 'private' ? 'private' : 'government';

                $hasCoords = ($row['northing'] ?? '') !== '' || ($row['easting'] ?? '') !== ''
                    || ($row['latitude'] ?? '') !== '' || ($row['longitude'] ?? '') !== '';

                if ($number === '' && ! $hasCoords) {
                    continue;   // the blank template row
                }

                CadastralPillar::create([
                    'cadastral_plan_description_id' => $planDescription->id,
                    'sort_order'    => $order++,
                    'pillar_number' => $number ?: null,
                    'ownership'     => $ownership,
                    'pillar_type'   => trim((string) ($row['pillar_type'] ?? '')) ?: null,
                    'northing'      => ($row['northing'] ?? '') === '' ? null : $row['northing'],
                    'easting'       => ($row['easting'] ?? '') === '' ? null : $row['easting'],
                    'latitude'      => ($row['latitude'] ?? '') === '' ? null : $row['latitude'],
                    'longitude'     => ($row['longitude'] ?? '') === '' ? null : $row['longitude'],
                    'condition'     => trim((string) ($row['condition'] ?? '')) ?: null,
                ]);
            }
        });

        $count = $planDescription->pillars()->count();

        return back()->with('success', "{$count} pillar(s) saved. Regenerate the bill to price them.");
    }

    /* ======================= Fee Calculator (Phase 7) ======================= */

    /**
     * The Fee Calculator: the official fee sheet's lines for one record
     * (?record=), previewed live from the server, above the table of bills.
     *
     * The officer enters only what the sheet leaves to the officer (delay,
     * transport, field work, office work, plan prints). Area and beacons come
     * from the record.
     */
    public function fees(Request $r)
    {
        $record = $r->filled('record')
            ? CadastralPlanDescription::find((int) $r->query('record'))
            : null;

        if ($r->filled('record') && ! $record) {
            return redirect()->route('cadastral-module.plan-description.fees')
                ->with('error', 'That plan and description record does not exist.');
        }

        $input = [];
        foreach (self::FEE_INPUTS as $key) {
            $input[$key] = old($key, $r->query($key));
        }

        $bills = CadastralBill::query()
            ->with('planDescription:id,pd_ref,file_title')
            ->when($record && $r->query('bills') !== 'all', fn ($q) => $q->where('cadastral_plan_description_id', $record->id))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('cadastral_module.pnd.fees', [
            'record'    => $record,
            'picked'    => $record ? $this->formatFeeRecords(collect([$record]))[0] : null,
            'preview'   => $record ? $this->bills->compute($record, $input) : null,
            'input'     => $this->bills->inputs($input),
            'rates'     => $this->settings->feeRates(),
            'bands'     => $this->bills->bandOptions(),
            'installed' => CadastralBill::feeSheetInstalled(),
            'confirmed' => [
                'Q1' => (bool) $this->settings->get('fee_sheet.between_rows_confirmed', false),
                'Q3' => (bool) $this->settings->get('fee_sheet.transport_bands_confirmed', false),
            ],
            'aboveMax'  => $this->settings->aboveMaxArea(),
            'maxHa'     => $this->settings->maxScheduleHectares(),
            'bills'     => $bills,
        ]);
    }

    /**
     * Select2 source for the Fee Calculator's File box: plan and description
     * records by file number, reference or owner. GET and verb-free (view).
     */
    public function feeFiles(Request $r): JsonResponse
    {
        $term = trim((string) $r->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['results' => [], 'pagination' => ['more' => false]]);
        }

        $like = '%' . $this->escapeLike($term) . '%';

        $rows = CadastralPlanDescription::query()
            ->where(fn ($w) => $w->where('file_number', 'like', $like)
                ->orWhere('pd_ref', 'like', $like)
                ->orWhere('file_title', 'like', $like))
            ->orderBy('file_number')
            ->limit(25)
            ->get();

        return response()->json(['results' => $this->formatFeeRecords($rows), 'pagination' => ['more' => false]]);
    }

    /**
     * The live preview: every line and the total, computed on the server from
     * the record and the officer's inputs. GET, name ends ".preview" (view).
     */
    public function previewFees(Request $r): JsonResponse
    {
        $record = CadastralPlanDescription::find((int) $r->query('record'));

        if (! $record) {
            return response()->json(['ok' => false, 'refusal' => 'Pick a file first.'], 404);
        }

        $c = $this->bills->compute($record, $r->only(self::FEE_INPUTS));

        return response()->json([
            'ok'        => $c['ok'],
            'refusal'   => $c['refusal'],
            'refusals'  => $c['refusals'],
            'flags'     => $c['flags'],
            'lines'     => array_values($c['lines']),
            'total'     => $c['total'],
            'area'      => $c['area'],
            'pillars'   => $c['pillars'],
            'transport' => $c['transport'],
            'installed' => $c['installed'],
        ]);
    }

    /**
     * Save (issue) the bill from the Fee Calculator.
     *
     * Only the officer's quantities and transport choice are read from the
     * request. Any amount or total posted alongside is ignored: issue()
     * recomputes every line from the record and CadastralSettings.
     */
    public function generateBill(Request $r, CadastralPlanDescription $planDescription)
    {
        $r->validate([
            'delay_days'       => 'nullable|integer|min:0|max:' . CadastralBillCalculator::MAX_QTY,
            'field_work_days'  => 'nullable|integer|min:0|max:' . CadastralBillCalculator::MAX_QTY,
            'office_work_days' => 'nullable|integer|min:0|max:' . CadastralBillCalculator::MAX_QTY,
            'plan_prints'      => 'nullable|integer|min:0|max:' . CadastralBillCalculator::MAX_QTY,
            'transport_band'   => 'nullable|string|max:20',
            'transport_km'     => 'nullable|numeric|min:0|max:100000',
        ]);

        $back = redirect()->route('cadastral-module.plan-description.fees',
            ['record' => $planDescription->id] + array_filter($r->only(self::FEE_INPUTS), fn ($v) => $v !== null && $v !== ''));

        try {
            $bill = DB::connection('sqlsrv')->transaction(
                fn () => $this->bills->issue($planDescription, $r->only(self::FEE_INPUTS))
            );
        } catch (ValidationException $e) {
            return $back->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()
            ->route('cadastral-module.plan-description.fees', ['record' => $planDescription->id])
            ->with('success', "Bill {$bill->bill_ref} issued. Total ₦" . number_format($bill->grand_total, 2) . '.');
    }

    /**
     * Cancel a Draft bill. Flagged, never deleted. ("mark-*" -> edit.)
     */
    public function cancelBill(Request $r, CadastralPlanDescription $planDescription, CadastralBill $bill)
    {
        if ($bill->cadastral_plan_description_id !== $planDescription->id) {
            return back()->with('error', 'That bill belongs to another record.');
        }

        if ($bill->status !== 'Draft') {
            return back()->with('error', "{$bill->bill_ref} is {$bill->status}; only a draft can be cancelled here. Issuing a new bill supersedes an unpaid issued one.");
        }

        $bill->status = 'Cancelled';
        if (CadastralBill::feeSheetInstalled()) {
            $bill->forceFill([
                'cancelled_at'  => now(),
                'cancelled_by'  => auth()->id(),
                'cancel_reason' => 'Draft cancelled on the Fee Calculator.',
            ]);
        }
        $bill->save();

        return back()->with('success', "{$bill->bill_ref} cancelled. It stays on record.");
    }

    /**
     * The official sheet, "Right of Occupancy - Cadastral Fees and Area".
     *
     * Every line prints from the bill's own snapshot; only the two schedules
     * at the foot are read from CadastralSettings, as the sheet prints them
     * today, with the row the bill was charged at highlighted.
     */
    public function printBill(CadastralPlanDescription $planDescription, CadastralBill $bill)
    {
        if ($bill->cadastral_plan_description_id !== $planDescription->id) {
            return back()->with('error', 'That bill belongs to another record.');
        }

        return view('cadastral_module.pnd.bill_print', [
            'record'   => $planDescription,
            'bill'     => $bill,
            'lines'    => $this->bills->lines($bill),
            'schedule' => $this->settings->areaSchedule(),
            'bands'    => $this->settings->transportBands(),
        ]);
    }

    /** Picker rows for the Fee Calculator's File box. */
    private function formatFeeRecords($records): array
    {
        return $records->map(fn (CadastralPlanDescription $pd) => [
            'id'          => $pd->id,
            'text'        => $pd->file_number . ($pd->file_title ? ' — ' . $pd->file_title : '') . " ({$pd->pd_ref})",
            'file_number' => $pd->file_number,
            'location'    => $pd->property_location,
            'area_ha'     => $pd->area_ha,
            'url'         => route('cadastral-module.plan-description.fees', ['record' => $pd->id]),
        ])->values()->all();
    }

    /**
     * Render a description from a template. The result stays editable.
     *
     * The Descriptions page posts its boundary notes with the button, and they
     * are saved first so the text is built from what is on screen. The combined
     * screen posts no boundaries, and absent keys are left alone.
     *
     * mode=regenerate re-renders the record's own template against its current
     * area, pillars and boundaries; otherwise the posted template is used.
     */
    public function generateDescription(Request $r, CadastralPlanDescription $planDescription)
    {
        $data = $r->validate([
            'template_key'   => 'nullable|string|max:50',
            'mode'           => 'nullable|in:template,regenerate',
            'boundary_north' => 'nullable|string|max:255',
            'boundary_south' => 'nullable|string|max:255',
            'boundary_east'  => 'nullable|string|max:255',
            'boundary_west'  => 'nullable|string|max:255',
        ]);

        $bounds = [];
        foreach (['north', 'south', 'east', 'west'] as $side) {
            if ($r->has("boundary_{$side}")) {
                $bounds["boundary_{$side}"] = $data["boundary_{$side}"] ?? null;
            }
        }
        if ($bounds !== []) {
            $planDescription->update($bounds);
        }

        if (($data['mode'] ?? null) === 'regenerate') {
            $key = $planDescription->template_key ?: $this->descriptions->defaultKeyFor($planDescription);
        } else {
            $key = ($data['template_key'] ?? null) ?: $this->descriptions->defaultKeyFor($planDescription);
        }

        if (! $this->descriptions->hasTemplate($key)) {
            return back()->with('error', "There is no description template called \"{$key}\".");
        }

        $body = $this->descriptions->generate($planDescription, $key);

        $planDescription->update([
            'template_key'             => $key,
            'description_body'         => $body,
            'description_generated_at' => now(),
        ]);

        return back()->with('success', 'Description generated. Edit it before it goes out — it is a form of words, not a fact.');
    }

    public function saveDescription(Request $r, CadastralPlanDescription $planDescription)
    {
        $data = $r->validate([
            'description_body' => 'required|string|max:16000',
            'boundary_north'   => 'nullable|string|max:255',
            'boundary_south'   => 'nullable|string|max:255',
            'boundary_east'    => 'nullable|string|max:255',
            'boundary_west'    => 'nullable|string|max:255',
            // The Descriptions page posts its Template box with Save, so a
            // changed choice is kept even when the text was written by hand.
            'template_key'     => 'nullable|string|max:50',
        ]);

        if (! $this->descriptions->hasTemplate($data['template_key'] ?? null)) {
            unset($data['template_key']);
        }

        $planDescription->update($data + ['description_edited_by' => auth()->id()]);

        $check = $this->descriptions->validate($planDescription->fresh('chart'));

        $planDescription->update([
            'validation_status' => $check['status'],
            'validation_notes'  => $check['notes'] === [] ? null : implode(' ', $check['notes']),
        ]);

        $message = 'Description saved.';

        if ($check['notes'] !== []) {
            return back()->with('error', $message . ' Checks against the chart: ' . implode(' ', $check['notes']));
        }

        return back()->with('success', $message . ' It agrees with the linked chart.');
    }

    /* ----------------------------- Phase 6 helpers ----------------------------- */

    /**
     * The area the Area & Pillars form asked for, written into $row as m²
     * with its source. Always sets both keys: a blank box clears the area.
     */
    private function applyTypedArea(array &$row, array $data, ?CadastralChart $chart): void
    {
        $row['area_sqm']    = null;
        $row['area_source'] = null;

        if (! empty($data['area_from_chart'])) {
            if (! $chart) {
                throw ValidationException::withMessages([
                    'area_from_chart' => 'This file has no current chart, so there is no chart area to use. Type the area instead.',
                ]);
            }

            $computed = $this->area->areaSqmForChart($chart);

            if ($computed !== null) {
                $row['area_sqm']    = $computed;
                $row['area_source'] = 'coordinates';
            } elseif ($chart->area_sqm !== null) {
                $row['area_sqm']    = $chart->area_sqm;
                $row['area_source'] = $chart->area_source ?: 'survey_plan';
            } else {
                throw ValidationException::withMessages([
                    'area_from_chart' => "Chart {$chart->chart_ref} has fewer than three beacons with coordinates and no area of its own. Type the area instead.",
                ]);
            }

            return;
        }

        if (isset($data['area_value']) && $data['area_value'] !== '') {
            $row['area_sqm']    = $this->area->toSqm((float) $data['area_value'], $data['area_unit'] ?? 'sqm');
            $row['area_source'] = 'manual';
        }
    }

    private function validatedArea(Request $r, bool $creating): array
    {
        $rules = [
            'area_value'      => 'nullable|numeric|min:0|max:999999999',
            'area_unit'       => ['required', Rule::in(array_keys(AreaCalculator::UNITS))],
            'area_from_chart' => 'nullable|boolean',
            'plot_size_sqm'   => 'nullable|numeric|min:1|max:9999999',
            'land_use'        => ['nullable', Rule::in(CadastralPlanDescription::LAND_USES)],
            'location_zone'   => ['nullable', Rule::in(array_keys(CadastralPlanDescription::ZONES))],
        ];

        if ($creating) {
            $rules['cadastral_file_receipt_id'] = 'required|integer';
        }

        return $r->validate($rules, [
            'cadastral_file_receipt_id.required' => 'Pick the file from the registered intake files.',
        ]);
    }

    /** A registered, live intake receipt, or a validation error. */
    private function requireReceipt(int $id): CadastralFileReceipt
    {
        $receipt = CadastralFileReceipt::find($id);

        if (! $receipt || ! $receipt->registered_at || in_array($receipt->status, self::DEAD_RECEIPTS, true)) {
            throw ValidationException::withMessages([
                'cadastral_file_receipt_id' => 'Only a file registered at intake can be given an area. Pick it again from the list.',
            ]);
        }

        return $receipt;
    }

    /** The file's current chart, newest version first. */
    private function currentChartFor(?string $fileNumber): ?CadastralChart
    {
        if (trim((string) $fileNumber) === '') {
            return null;
        }

        return CadastralChart::current()
            ->where('file_number', $fileNumber)
            ->orderByDesc('version')
            ->orderByDesc('id')
            ->first();
    }

    /** The land use a file number carries, as LAND_USES spells it, or null. */
    private function landUseFor(?string $fileNumber): ?string
    {
        return self::LAND_USE_CODES[FileNumberLandUse::codeFor($fileNumber)] ?? null;
    }

    /**
     * What the pillars say about the boundary, for the results card. Advice,
     * never a block: a pillar schedule is often keyed before its coordinates.
     *
     * @return array{government: int, private: int, with_coordinates: int, ring_sqm: ?float, perimeter_m: ?float, notes: array<int, string>}
     */
    private function boundaryCheck(CadastralPlanDescription $record, $pillars): array
    {
        $notes  = [];
        $coords = $pillars->filter(fn ($p) => $p->easting !== null && $p->northing !== null);
        $ring   = $this->area->areaSqm($coords);

        if ($pillars->isNotEmpty() && $coords->count() < 3) {
            $notes[] = 'Fewer than three pillars carry eastings and northings, so they do not enclose an area to check against.';
        }

        if ($ring !== null && $record->area_sqm !== null) {
            $delta = abs($ring - (float) $record->area_sqm);

            if ($delta > max(1.0, $ring * 0.01)) {
                $notes[] = sprintf(
                    'The pillars enclose %s m², which differs from the recorded %s m² by %s m². Check the pillar order and the area.',
                    number_format($ring, 2), number_format((float) $record->area_sqm, 2), number_format($delta, 2)
                );
            }
        }

        $numbers = $pillars->pluck('pillar_number')->filter()->map(fn ($n) => strtoupper(trim($n)));
        if ($numbers->count() !== $numbers->unique()->count()) {
            $notes[] = 'A pillar number appears twice.';
        }

        $points = $coords->map(fn ($p) => round($p->easting, 3) . ',' . round($p->northing, 3));
        if ($points->count() !== $points->unique()->count()) {
            $notes[] = 'Two pillars share the same coordinates.';
        }

        return [
            'government'       => $pillars->where('ownership', CadastralPillar::OWNERSHIP_GOVERNMENT)->count(),
            'private'          => $pillars->where('ownership', CadastralPillar::OWNERSHIP_PRIVATE)->count(),
            'with_coordinates' => $coords->count(),
            'ring_sqm'         => $ring,
            'perimeter_m'      => $this->area->perimeterM($coords),
            'notes'            => $notes,
        ];
    }

    /** Picker rows for registered intake receipts, with the file's record and chart. */
    private function formatReceipts($receipts): array
    {
        $numbers = $receipts->pluck('file_number')->unique()->values()->all();

        $records = $numbers === [] ? collect() : CadastralPlanDescription::query()
            ->whereIn('file_number', $numbers)->orderByDesc('id')->get()
            ->unique('file_number')->keyBy('file_number');

        $charts = $numbers === [] ? collect() : CadastralChart::current()
            ->whereIn('file_number', $numbers)->withCount('coordinates')
            ->orderByDesc('version')->orderByDesc('id')->get()
            ->unique('file_number')->keyBy('file_number');

        return $receipts->map(function (CadastralFileReceipt $rc) use ($records, $charts) {
            $pd    = $records[$rc->file_number] ?? null;
            $chart = $charts[$rc->file_number] ?? null;
            $text  = $rc->file_number . ($rc->file_title ? ' — ' . $rc->file_title : '') . " ({$rc->receipt_ref})";

            if ($pd) $text .= " — already on {$pd->pd_ref}";

            return [
                'id'          => $rc->id,
                'text'        => $text,
                'file_number' => $rc->file_number,
                'owner'       => (string) $rc->file_title,
                'plot'        => (string) $rc->prop_plot,
                'location'    => CadastralAddress::propertyLocation($rc),
                'type'        => CadastralRegistryLookup::typeLabel($rc->file_number, $rc->source_registry, $rc->file_class),
                'land_use'    => $this->landUseFor($rc->file_number),
                'record'      => $pd ? [
                    'id'  => $pd->id,
                    'ref' => $pd->pd_ref,
                    'url' => route('cadastral-module.plan-description.area', ['record' => $pd->id]),
                ] : null,
                'chart'       => $chart ? [
                    'id'          => $chart->id,
                    'ref'         => $chart->chart_ref,
                    'coordinates' => (int) $chart->coordinates_count,
                ] : null,
            ];
        })->values()->all();
    }

    /** Picker rows for plan and description records (the Descriptions File box). */
    private function formatRecords($records): array
    {
        return $records->map(fn (CadastralPlanDescription $pd) => [
            'id'          => $pd->id,
            'text'        => $pd->file_number . ($pd->file_title ? ' — ' . $pd->file_title : '') . " ({$pd->pd_ref})",
            'file_number' => $pd->file_number,
            'owner'       => (string) $pd->file_title,
            'location'    => $pd->property_location,
            'has_text'    => trim((string) $pd->description_body) !== '',
            'url'         => route('cadastral-module.plan-description.descriptions', ['record' => $pd->id]),
        ])->values()->all();
    }

    /** A LIKE term with T-SQL's wildcards ([ % _) taken literally. */
    private function escapeLike(string $term): string
    {
        return str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $term);
    }

    /**
     * Area from the chart's beacon ring where the officer has not overridden it;
     * either way the source is recorded, so nobody later mistakes a typed figure
     * for a computed one.
     */
    private function applyArea(array &$data): void
    {
        $data['plot_size_sqm'] = $data['plot_size_sqm'] ?? app(\App\Services\Cadastral\CadastralSettings::class)->plotSizeSqm();

        if (! empty($data['area_sqm'])) {
            $data['area_source'] = 'manual';

            return;
        }

        if (empty($data['cadastral_chart_id'])) {
            return;
        }

        $chart = CadastralChart::with('coordinates')->find($data['cadastral_chart_id']);

        if (! $chart) {
            return;
        }

        $computed = $this->area->areaSqmForChart($chart);

        if ($computed !== null) {
            $data['area_sqm']    = $computed;
            $data['area_source'] = 'coordinates';
        } elseif ($chart->area_sqm !== null) {
            $data['area_sqm']    = $chart->area_sqm;
            $data['area_source'] = $chart->area_source ?: 'survey_plan';
        }
    }

    private function validated(Request $r, ?CadastralPlanDescription $existing = null): array
    {
        $rules = [
            'file_number'            => 'required|string|max:100',
            'file_title'             => 'nullable|string|max:500',
            'cadastral_chart_id'     => 'nullable|integer',
            'cadastral_report_id'    => 'nullable|integer',
            'land_use'               => ['nullable', Rule::in(CadastralPlanDescription::LAND_USES)],
            'location_zone'          => ['nullable', Rule::in(array_keys(CadastralPlanDescription::ZONES))],
            'area_sqm'               => 'nullable|numeric|min:0',
            'area_precision'         => 'nullable|integer|min:0|max:6',
            'plot_size_sqm'          => 'nullable|numeric|min:1',
            'boundary_north'         => 'nullable|string|max:255',
            'boundary_south'         => 'nullable|string|max:255',
            'boundary_east'          => 'nullable|string|max:255',
            'boundary_west'          => 'nullable|string|max:255',
            'description_complexity' => ['required', Rule::in(array_keys(CadastralPlanDescription::COMPLEXITIES))],
            'template_key'           => 'nullable|string|max:50',
        ] + CadastralAddress::rules('prop_');

        // No plot column of its own; the builder's prop_plot is it.
        return CadastralAddress::normalise(
            $r->validate($rules, CadastralAddress::messages('prop_')),
            'prop_'
        );
    }
}
