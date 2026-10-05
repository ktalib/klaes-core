<?php

namespace App\Http\Controllers\Cadastral;

use App\Http\Controllers\Cadastral\Concerns\LocksFileValues;
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
use App\Services\Cadastral\FileNumberFormat;
use App\Services\Cadastral\LandDescriptionGenerator;
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
 *
 * THE FILE COMES FROM THE SHARED PICKER. Every page picks its file with the
 * global file-number selector (partials/_file_picker): Area & Pillars and the
 * register form take a file registered at intake (scope receipt), Descriptions
 * and the Fee Calculator a file that already has a record (scope plan). On
 * save the file is re-read from its receipt and the index behind it, and what
 * the records supply wins over anything posted (LocksFileValues).
 */
class PlanDescriptionController extends Controller
{
    use LocksFileValues;

    /**
     * The form fields a record takes from its intake receipt (and the file
     * index behind it). Supplied ones are locked on the form and overwritten
     * on the server; blank ones are the officer's. The plot is the builder's
     * prop_plot: the record has no plot column of its own.
     */
    private const FILE_FIELDS = ['file_title', 'prop_house', 'prop_plot', 'prop_street', 'prop_district', 'prop_lga', 'prop_state'];

    /** The officer-entered Fee Calculator fields; nothing else is read from a request. */
    private const FEE_INPUTS = ['delay_days', 'transport_band', 'transport_km', 'field_work_days', 'office_work_days', 'plan_prints'];

    /**
     * File-number land-use codes, as the record's LAND_USES spell them. The
     * pages pass this to the browser, so the land use the picker locks is the
     * one the server forces (see landUseFromLabel).
     */
    public const LAND_USE_CODES = [
        'RES' => 'Residential', 'COM' => 'Commercial', 'IND' => 'Industrial',
        'AG' => 'Agricultural', 'AGR' => 'Agricultural', 'AGRIC' => 'Agricultural',
        'MIX' => 'Mixed', 'MIXED' => 'Mixed', 'INS' => 'Institutional',
    ];

    public function __construct(
        private AreaCalculator $area,
        private CadastralBillCalculator $bills,
        private LandDescriptionGenerator $descriptions,
        private CadastralSettings $settings,
        private CadastralRegistryLookup $lookup,
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

    /**
     * New Record. The file is picked with the shared file picker and must be
     * registered at intake; ?receipt=, ?file_number= or ?chart= (the Charting
     * register's link) preloads it. A file that already has a record opens
     * that record: one working record per file, as on Area & Pillars.
     */
    public function create(Request $r)
    {
        $chart  = $r->query('chart') ? CadastralChart::find($r->query('chart')) : null;
        $picked = $this->pickedReceipt($r, $chart?->file_number);

        if ($open = $this->openExisting($picked, 'edit')) {
            return $open;
        }

        $chart ??= $this->currentChartFor($picked['file']['file_number'] ?? null);

        $record = new CadastralPlanDescription([
            'file_number'        => $picked['file']['file_number'] ?? null,
            'cadastral_chart_id' => $chart?->id,
            'area_sqm'           => $chart?->area_sqm,
            'plot_size_sqm'      => $this->settings->plotSizeSqm(),
        ]);

        return view('cadastral_module.pnd.register', [
            'record'      => $record,
            'picked'      => $picked,
            'fileLandUse' => $this->landUseOf($picked),
            'chart'       => $chart,
            'pillars'     => collect(),
            'bill'        => null,
            'templates'   => $this->descriptions->templates(),
            'areas'       => $this->area->convert($record->area_sqm, $record->plot_size_sqm),
        ]);
    }

    /**
     * Start a record from the register form. File number, owner, plot and
     * location come from the receipt (and the index behind it), whatever was
     * posted; the chart and report are found from the file, not typed.
     */
    public function store(Request $r)
    {
        $receipt = $this->requireRegisteredReceipt($r->input('cadastral_file_receipt_id'), 'have a plan-description record');

        if ($existing = $this->recordFor($receipt->file_number)) {
            return redirect()
                ->route('cadastral-module.plan-description.edit', $existing)
                ->with('error', "{$receipt->file_number} already has {$existing->pd_ref}; it is open below. Nothing new was created.");
        }

        $chart = $this->currentChartFor($receipt->file_number);

        $this->lockFromFile($r, self::FILE_FIELDS, $receipt, null, [
            'file_number'         => $receipt->file_number,
            'cadastral_chart_id'  => $chart?->id,
            'cadastral_report_id' => $this->reportIdFor($receipt),
        ] + $this->lockedLandUse($receipt));

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
        $picked = $this->pickedForRecord($planDescription);

        return view('cadastral_module.pnd.register', [
            'record'      => $planDescription->load('chart'),
            'picked'      => $picked,
            'fileLandUse' => $this->landUseOf($picked),
            'chart'       => $this->currentChartFor($planDescription->file_number) ?? $planDescription->chart,
            'pillars'     => $planDescription->pillars()->get(),
            'bill'        => $planDescription->bills()->where('status', '!=', 'Cancelled')->first(),
            'templates'   => $this->descriptions->templates(),
            'areas'       => $this->area->convert($planDescription->area_sqm, $planDescription->plot_size_sqm),
        ]);
    }

    /**
     * The file is fixed once the record exists: its number is the record's,
     * and what its receipt supplies is re-read from the receipt. A record
     * with no registered receipt behind it (one started before the picker)
     * keeps the officer's values.
     */
    public function update(Request $r, CadastralPlanDescription $planDescription)
    {
        $receipt = $this->latestRegisteredReceipt($planDescription->file_number);
        $chart   = $this->currentChartFor($planDescription->file_number) ?? $planDescription->chart;

        $fixed = [
            'file_number'         => $planDescription->file_number,
            'cadastral_chart_id'  => $chart?->id,
            'cadastral_report_id' => $planDescription->cadastral_report_id ?? ($receipt ? $this->reportIdFor($receipt) : null),
        ];

        if ($receipt) {
            $this->lockFromFile($r, self::FILE_FIELDS, $receipt, null, $fixed + $this->lockedLandUse($receipt));
        } else {
            $r->merge($fixed);
        }

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
     * Area & Pillars, a five-step wizard: file, file details, area, pillars,
     * review. With ?record= it works on that record (the file is fixed);
     * without, the shared picker starts one, and ?receipt= or ?file_number=
     * preloads the file.
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

        if ($record) {
            $picked = $this->pickedForRecord($record);
        } else {
            $picked = $this->pickedReceipt($r);

            // One working record per file: picking a file that has one opens it.
            if ($open = $this->openExisting($picked, 'area')) {
                return $open;
            }
        }

        $plotSize = $record?->plot_size_sqm ?: $this->settings->plotSizeSqm();
        $chart    = $record
            ? ($this->currentChartFor($record->file_number) ?? $record->chart)
            : $this->currentChartFor(($picked['status'] ?? null) === 'ok' ? $picked['file']['file_number'] : null);
        $pillars  = $record ? $record->pillars()->get() : collect();

        return view('cadastral_module.pnd.area', [
            'record'     => $record,
            'picked'     => $picked,
            'fileLandUse'=> $this->landUseOf($picked),
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
     * Start a record from a registered intake file: the whole wizard in one
     * post — file details, area and the pillar schedule.
     *
     * File number, owner, plot and location are re-read from the receipt (and
     * the index behind it) and win over anything posted; only what they leave
     * blank is taken from the form. One working record per file: a second
     * start opens the first and creates nothing.
     */
    public function areaStore(Request $r)
    {
        $receipt = $this->requireRegisteredReceipt($r->input('cadastral_file_receipt_id'), 'be given an area');

        if ($existing = $this->recordFor($receipt->file_number)) {
            return redirect()
                ->route('cadastral-module.plan-description.area', ['record' => $existing->id])
                ->with('error', "{$receipt->file_number} already has {$existing->pd_ref}; it is open below. Nothing new was created.");
        }

        $this->lockFromFile($r, self::FILE_FIELDS, $receipt, null, $this->lockedLandUse($receipt));

        $data  = $this->validatedArea($r);
        $chart = $this->currentChartFor($receipt->file_number);

        $row = [
            'file_number'            => $receipt->file_number,
            'file_title'             => $data['file_title'] ?? null,
            'cadastral_chart_id'     => $chart?->id,
            'cadastral_report_id'    => $this->reportIdFor($receipt),
            'land_use'               => $data['land_use'] ?? null,
            'location_zone'          => $data['location_zone'] ?? null,
            'plot_size_sqm'          => $data['plot_size_sqm'] ?? $this->settings->plotSizeSqm(),
            'description_complexity' => 'standard',
        ] + $this->addressOf($data);

        $this->applyTypedArea($row, $data, $chart);

        $row['pd_ref'] = CadastralPlanDescription::nextRef('pd_ref', 'PND', 4);

        $rows = $this->pillarRows($r);

        $record = DB::connection('sqlsrv')->transaction(function () use ($row, $rows) {
            $record = CadastralPlanDescription::create($row);

            if ($rows !== null) {
                $this->replacePillars($record, $rows);
            }

            return $record;
        });

        $count = $record->pillars()->count();

        return redirect()
            ->route('cadastral-module.plan-description.area', ['record' => $record->id])
            ->with('success', "{$record->pd_ref} started for {$record->file_number} with {$count} pillar(s). Next: the description.");
    }

    /**
     * Re-record an existing record from the wizard: file details the file
     * leaves blank, area, land use, plot size and the pillar schedule. The
     * file itself is fixed.
     */
    public function areaUpdate(Request $r, CadastralPlanDescription $planDescription)
    {
        if ($receipt = $this->latestRegisteredReceipt($planDescription->file_number)) {
            $this->lockFromFile($r, self::FILE_FIELDS, $receipt, null, $this->lockedLandUse($receipt));
        }

        $data  = $this->validatedArea($r);
        $chart = $this->currentChartFor($planDescription->file_number) ?? $planDescription->chart;

        $row = [
            'file_title'         => $data['file_title'] ?? null,
            // A chart re-versioned since the record started is followed to its
            // current version, so "use the chart area" reads today's ring.
            'cadastral_chart_id' => $chart?->id,
            'land_use'           => $data['land_use'] ?? null,
            'location_zone'      => $data['location_zone'] ?? null,
            'plot_size_sqm'      => $data['plot_size_sqm'] ?? ($planDescription->plot_size_sqm ?: $this->settings->plotSizeSqm()),
        ] + $this->addressOf($data);

        $this->applyTypedArea($row, $data, $chart);

        $rows    = $this->pillarRows($r);
        $changed = false;

        DB::connection('sqlsrv')->transaction(function () use ($planDescription, $row, $rows, &$changed) {
            $planDescription->update($row);

            // Only a schedule that differs is replaced: re-saving the area must
            // not leave a soft-deleted copy of an unchanged schedule behind.
            if ($rows !== null && ! $this->sameSchedule($planDescription, $rows)) {
                $this->replacePillars($planDescription, $rows);
                $changed = true;
            }
        });

        return redirect()
            ->route('cadastral-module.plan-description.area', ['record' => $planDescription->id])
            ->with('success', "{$planDescription->pd_ref}: area saved"
                . ($row['area_sqm'] !== null ? ' (' . number_format($row['area_sqm'], 2) . ' m², ' . $row['area_source'] . ')' : ' (cleared)')
                . ($changed ? '; ' . $planDescription->pillars()->count() . ' pillar(s) saved. Regenerate the bill to price them.' : '.'));
    }

    /* ======================== Descriptions (Phase 6) ======================== */

    /**
     * The Descriptions page: the form for one record (?record=) above the
     * table of every record that has a saved description.
     */
    public function descriptionsPage(Request $r)
    {
        if ($open = $this->openPlanFile($r, 'descriptions')) {
            return $open;
        }

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
            'picked'    => $record ? $this->pickedPlan($record) : null,
            'pillars'   => $record ? $record->pillars()->get() : collect(),
            'areas'     => $this->area->convert($record?->area_sqm, $record?->plot_size_sqm ?: $this->settings->plotSizeSqm()),
            'templates' => $this->descriptions->templates(),
            'template'  => $record ? ($record->template_key ?: $this->descriptions->defaultKeyFor($record)) : null,
            'saved'     => $saved,
        ]);
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
        $r->validate(self::pillarRules(), self::PILLAR_MESSAGES);

        $rows = $r->input('pillars', []);

        if (! is_array($rows)) {
            return back()->with('error', 'No pillars were submitted.');
        }

        DB::connection('sqlsrv')->transaction(
            fn () => $this->replacePillars($planDescription, $this->normalisePillars($rows))
        );

        $count = $planDescription->pillars()->count();

        return back()->with('success', "{$count} pillar(s) saved. Regenerate the bill to price them.");
    }

    /** Validation for a posted pillar schedule (savePillars and the Area & Pillars wizard). */
    private static function pillarRules(): array
    {
        return [
            'pillars'                 => 'nullable|array|max:500',
            'pillars.*.pillar_number' => 'nullable|string|max:50',
            'pillars.*.ownership'     => ['nullable', Rule::in([CadastralPillar::OWNERSHIP_GOVERNMENT, CadastralPillar::OWNERSHIP_PRIVATE])],
            'pillars.*.pillar_type'   => 'nullable|string|max:50',
            'pillars.*.northing'      => 'nullable|numeric|between:-999999999999,999999999999',
            'pillars.*.easting'       => 'nullable|numeric|between:-999999999999,999999999999',
            'pillars.*.latitude'      => 'nullable|numeric|between:-90,90',
            'pillars.*.longitude'     => 'nullable|numeric|between:-180,180',
            'pillars.*.condition'     => ['nullable', Rule::in(CadastralPillar::CONDITIONS)],
        ];
    }

    private const PILLAR_MESSAGES = [
        'pillars.*.northing.numeric'  => 'Each northing must be a number.',
        'pillars.*.easting.numeric'   => 'Each easting must be a number.',
        'pillars.*.latitude.between'  => 'A latitude lies between -90 and 90.',
        'pillars.*.longitude.between' => 'A longitude lies between -180 and 180.',
    ];

    /** The pillar columns a schedule row carries, in the order they are compared. */
    private const PILLAR_COLUMNS = ['pillar_number', 'ownership', 'pillar_type', 'northing', 'easting', 'latitude', 'longitude', 'condition'];

    /**
     * Posted pillar rows, cleaned: blank rows dropped, ownership defaulted to
     * government, empty strings as null.
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalisePillars(array $rows): array
    {
        $out = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $number    = trim((string) ($row['pillar_number'] ?? ''));
            $hasCoords = ($row['northing'] ?? '') !== '' || ($row['easting'] ?? '') !== ''
                || ($row['latitude'] ?? '') !== '' || ($row['longitude'] ?? '') !== '';

            if ($number === '' && ! $hasCoords) {
                continue;   // a blank row
            }

            $out[] = [
                'pillar_number' => $number ?: null,
                'ownership'     => ($row['ownership'] ?? '') === 'private' ? 'private' : 'government',
                'pillar_type'   => trim((string) ($row['pillar_type'] ?? '')) ?: null,
                'northing'      => ($row['northing'] ?? '') === '' ? null : $row['northing'],
                'easting'       => ($row['easting'] ?? '') === '' ? null : $row['easting'],
                'latitude'      => ($row['latitude'] ?? '') === '' ? null : $row['latitude'],
                'longitude'     => ($row['longitude'] ?? '') === '' ? null : $row['longitude'],
                'condition'     => trim((string) ($row['condition'] ?? '')) ?: null,
            ];
        }

        return $out;
    }

    /**
     * The wizard's pillar schedule, or null when the form did not carry one
     * (pillars_posted marks it, so removing every row clears the schedule
     * rather than leaving it alone). Validated by validatedArea.
     */
    private function pillarRows(Request $r): ?array
    {
        if (! $r->boolean('pillars_posted')) {
            return null;
        }

        $rows = $r->input('pillars', []);

        return $this->normalisePillars(is_array($rows) ? $rows : []);
    }

    /**
     * Replace a record's pillar schedule with $rows (normalisePillars shape).
     * The caller holds the transaction.
     *
     * FLAGGED, NEVER DELETED. The replaced rows are soft-deleted (deleted_at
     * set), so earlier schedules stay on record; pillars(), like every other
     * read, never sees them again.
     */
    private function replacePillars(CadastralPlanDescription $record, array $rows): void
    {
        $record->pillars()->get()->each->delete();

        foreach (array_values($rows) as $order => $row) {
            CadastralPillar::create(['cadastral_plan_description_id' => $record->id, 'sort_order' => $order] + $row);
        }
    }

    /** True when $rows is the schedule the record already holds, row for row. */
    private function sameSchedule(CadastralPlanDescription $record, array $rows): bool
    {
        $key = fn ($row) => implode('|', array_map(function ($col) use ($row) {
            $v = is_array($row) ? ($row[$col] ?? null) : $row->{$col};
            if ($v === null || $v === '') return '';

            return in_array($col, ['northing', 'easting', 'latitude', 'longitude'], true)
                ? (string) round((float) $v, 7)
                : trim((string) $v);
        }, self::PILLAR_COLUMNS));

        $have = $record->pillars()->get()->map($key)->all();
        $want = array_map($key, array_values($rows));

        return $have === $want;
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
        if ($open = $this->openPlanFile($r, 'fees')) {
            return $open;
        }

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
            'picked'    => $record ? $this->pickedPlan($record) : null,
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

    /**
     * The Area & Pillars wizard: file details (after lockFromFile has put the
     * file's values over the request), area, and the pillar schedule. The
     * address is the Cadastral single-value set (CadastralAddress), tidied.
     */
    private function validatedArea(Request $r): array
    {
        $rules = [
            'file_title'      => 'nullable|string|max:500',
            'area_value'      => 'nullable|numeric|min:0|max:999999999',
            'area_unit'       => ['required', Rule::in(array_keys(AreaCalculator::UNITS))],
            'area_from_chart' => 'nullable|boolean',
            'plot_size_sqm'   => 'nullable|numeric|min:1|max:9999999',
            'land_use'        => ['nullable', Rule::in(CadastralPlanDescription::LAND_USES)],
            'location_zone'   => ['nullable', Rule::in(array_keys(CadastralPlanDescription::ZONES))],
        ] + $this->addressRules($r) + self::pillarRules();

        return CadastralAddress::normalise(
            $r->validate($rules, $this->addressMessages() + self::PILLAR_MESSAGES),
            'prop_'
        );
    }

    /** The prop_* group of validated data, as the record stores it. */
    private function addressOf(array $data): array
    {
        $out = [];
        foreach (\App\Support\AddressBuilder::columns('prop_') as $col) {
            $out[$col] = $data[$col] ?? null;
        }
        $out['prop_state'] = $out['prop_state'] ?: 'Kano';

        return $out;
    }

    /* ------------------------------ file picking ------------------------------ */

    /** The record a file already has (newest), if any. One working record per file. */
    private function recordFor(?string $fileNumber): ?CadastralPlanDescription
    {
        if (trim((string) $fileNumber) === '') {
            return null;
        }

        return CadastralPlanDescription::query()
            ->where('file_number', $fileNumber)
            ->orderByDesc('id')
            ->first();
    }

    /** The newest report opened on the receipt, linked to the record it starts. */
    private function reportIdFor(CadastralFileReceipt $receipt): ?int
    {
        $id = CadastralReport::query()
            ->where('cadastral_file_receipt_id', $receipt->id)->orderByDesc('id')->value('id');

        return $id ? (int) $id : null;
    }

    /**
     * The picker payload a start form preloads: after a failed post the
     * receipt it carried, else ?receipt=, else ?file_number= (or $fileNumber,
     * e.g. a chart's), all at scope receipt. Null when nothing was asked for.
     */
    private function pickedReceipt(Request $r, ?string $fileNumber = null): ?array
    {
        $receiptId = (int) (old('cadastral_file_receipt_id') ?: $r->query('receipt'));
        $number    = trim((string) ($r->query('file_number') ?: $fileNumber));

        return match (true) {
            $receiptId > 0  => $this->lookup->resolveFile(['receipt' => $receiptId, 'scope' => 'receipt']),
            $number !== ''  => $this->lookup->resolveFile(['file_number' => $number, 'scope' => 'receipt']),
            default         => null,
        };
    }

    /**
     * An existing record's file, as its fixed picker shows it: through the
     * file's latest registered receipt. Null for a record no registered
     * receipt stands behind (the picker then shows the number alone).
     */
    private function pickedForRecord(CadastralPlanDescription $record): ?array
    {
        $receipt = $this->latestRegisteredReceipt($record->file_number);

        return $receipt ? $this->lookup->resolveFile(['receipt' => $receipt->id, 'scope' => 'receipt']) : null;
    }

    /** A record's file at scope plan, for the Descriptions and Fee pickers; null unless it resolves cleanly. */
    private function pickedPlan(CadastralPlanDescription $record): ?array
    {
        $receipt = $this->latestRegisteredReceipt($record->file_number);
        $picked  = $receipt ? $this->lookup->resolveFile(['receipt' => $receipt->id, 'scope' => 'plan']) : null;

        return ($picked['status'] ?? null) === 'ok' ? $picked : null;
    }

    /** A picked file that already has a record: open that record instead of starting another. */
    private function openExisting(?array $picked, string $page)
    {
        $id = ($picked['status'] ?? null) === 'ok' ? ($picked['records']['plan_description']['id'] ?? null) : null;

        if (! $id) {
            return null;
        }

        $ref = $picked['records']['plan_description']['ref'];
        $url = $page === 'edit'
            ? route('cadastral-module.plan-description.edit', $id)
            : route('cadastral-module.plan-description.area', ['record' => $id]);

        return redirect($url)->with('success', "{$picked['file']['file_number']} already has {$ref}; it is open below.");
    }

    /**
     * Descriptions and the Fee Calculator take ?file_number= (a link, or the
     * picker with scripts off) and open that file's record, or say why not.
     */
    private function openPlanFile(Request $r, string $page)
    {
        $number = trim((string) $r->query('file_number', ''));

        if ($number === '' || $r->filled('record')) {
            return null;
        }

        $p = $this->lookup->resolveFile(['file_number' => $number, 'scope' => 'plan']);
        $id = $p['status'] === 'ok' ? ($p['hidden']['cadastral_plan_description_id'] ?? null) : null;

        return $id
            ? redirect()->route("cadastral-module.plan-description.{$page}", ['record' => $id])
            : redirect()->route("cadastral-module.plan-description.{$page}")
                ->with('error', $p['message'] ?: "{$number} cannot be opened here.");
    }

    /* -------------------------------- land use -------------------------------- */

    /**
     * The land use a file's own records give, as LAND_USES spells it, or
     * null. The label is the one the picker shows (the index's land-use type,
     * else the file number's code); _pick_hooks maps it the same way in the
     * browser, so what the form locks is what the server forces.
     */
    public static function landUseFromLabel(?string $label): ?string
    {
        $label = trim((string) $label);
        if ($label === '') {
            return null;
        }

        $plain = preg_replace('/\s+use$/i', '', $label);
        foreach (CadastralPlanDescription::LAND_USES as $use) {
            if (strcasecmp($use, $label) === 0 || strcasecmp($use, $plain) === 0) {
                return $use;
            }
        }

        return self::LAND_USE_CODES[strtoupper($label)] ?? null;
    }

    /** The locked land use of a resolveFile payload, or null. */
    private function landUseOf(?array $picked): ?string
    {
        return ($picked['file'] ?? null) ? self::landUseFromLabel($picked['file']['land_use'] ?? null) : null;
    }

    /**
     * ['land_use' => …] when the receipt's file gives one, for merging over
     * the request; [] when it leaves the choice to the officer. The label is
     * worked out as CadastralRegistryLookup::filePayload does.
     */
    private function lockedLandUse(CadastralFileReceipt $receipt): array
    {
        $source = $this->lookup->sourceFile(null, $receipt->file_indexing_id);
        $use    = self::landUseFromLabel($source['land_use'] ?? FileNumberFormat::landUseLabel($receipt->file_number));

        return $use ? ['land_use' => $use] : [];
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
