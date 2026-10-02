<?php

namespace App\Http\Controllers\Cadastral;

use App\Http\Controllers\Controller;
use App\Models\Cadastral\CadastralChart;
use App\Models\Cadastral\CadastralChartCoordinate;
use App\Models\Cadastral\CadastralFileReceipt;
use App\Services\Cadastral\AreaCalculator;
use App\Services\Cadastral\CadastralAddress;
use App\Services\Cadastral\ChartConflictScanner;
use App\Services\Cadastral\FileNumberFormat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The charting register (concept note 4.3a).
 *
 * TWO THINGS THAT SHAPE THIS CONTROLLER.
 *
 *  1. CONVERSION FILES ARE NOT CHARTED. CON-* files are recorded with
 *     charting_required = false and sent straight to index-card commissioning,
 *     which is Ministry policy, not an optimisation. The classification comes
 *     from the file number, not from a checkbox someone can get wrong.
 *
 *  2. CHARTS ARE VERSIONED, NEVER OVERWRITTEN. newVersion() clones the row and
 *     marks the old one Superseded, so what was charted when survives. Editing
 *     in place would quietly rewrite history that a dispute may later turn on.
 *
 * THE CHARTING QUEUE (Phase 5) is the registered intake files that still need a
 * chart: direct files (RES, COM, IND …) with no current chart. Conversion files
 * never enter it — they are counted as "Charting not required" instead — and
 * the decision is read from the file number (FileNumberFormat::classify, stored
 * on the receipt as file_class), not from anything an officer ticks.
 *
 * GIS is linked out to, not embedded (plan Q6): a chart that mirrors a
 * gisCapture or surveyCadastral row by origin/origin_id opens that record in its
 * own screen (CadastralChart::gisLink).
 */
class ChartingController extends Controller
{
    public function __construct(
        private ChartConflictScanner $conflicts,
        private AreaCalculator $area,
    ) {}

    public function index(Request $r)
    {
        $q = CadastralChart::query()->withCount('coordinates');

        if ($term = trim((string) $r->query('q', ''))) {
            $q->where(function ($w) use ($term) {
                $w->where('file_number', 'like', "%$term%")
                  ->orWhere('chart_ref', 'like', "%$term%")
                  ->orWhere('plot_no', 'like', "%$term%")
                  ->orWhere('layout_name', 'like', "%$term%")
                  ->orWhere('approved_plan_no', 'like', "%$term%");
            });
        }
        if ($c = $r->query('chart_category')) $q->where('chart_category', $c);
        if ($s = $r->query('status'))         $q->where('status', $s);
        if ($r->query('current') !== '0')     $q->current();

        $charts = $q->orderByDesc('id')->paginate(15)->withQueryString();

        $queue = $this->queue();

        $stats = [
            'total'        => CadastralChart::current()->count(),
            'charted'      => CadastralChart::current()->whereIn('status', ['Charted', 'Checked', 'Approved'])->count(),
            'pending'      => CadastralChart::current()->where('status', 'Draft')->where('charting_required', true)->count(),
            'conflicts'    => CadastralChart::current()->whereIn('conflict_status', ['suspected', 'confirmed'])->count(),
            'queue'        => $queue->total(),
            'not_required' => $this->openReceipts()->where('file_class', 'conversion')->count(),
            'clusters'     => $this->conflicts->identityClusters()->count(),
        ];

        // Identity conflicts for the charts on this page only — two indexed
        // reads per row. The geometric test stays on the chart's own page.
        $conflictCounts = $charts->getCollection()
            ->filter(fn (CadastralChart $c) => $c->is_current)
            ->mapWithKeys(fn (CadastralChart $c) => [$c->id => count($this->conflicts->identityConflicts($c))])
            ->all();

        return view('cadastral_module.information.charting', compact('charts', 'stats', 'queue', 'conflictCounts'));
    }

    /**
     * Registered intake files awaiting a chart: open, direct, and with no
     * current chart for the same file number. Conversion files are excluded
     * here and nowhere else, so the rule is stated once.
     */
    private function queue()
    {
        return $this->openReceipts()
            ->where(fn ($w) => $w->whereNull('file_class')->orWhere('file_class', '!=', 'conversion'))
            ->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('cadastral_charts as c')
                    ->whereColumn('c.file_number', 'cadastral_file_receipts.file_number')
                    ->where('c.is_current', 1)
                    ->whereNull('c.deleted_at');
            })
            ->orderBy('registered_at')
            ->paginate(10, ['*'], 'queue_page')
            ->withQueryString();
    }

    /** Receipts registered and still held by Cadastral. */
    private function openReceipts()
    {
        return CadastralFileReceipt::query()
            ->whereNotNull('registered_at')
            ->whereNotIn('status', ['Archived', 'Returned', 'Rejected']);
    }

    public function create(Request $r)
    {
        $chart = new CadastralChart([
            'file_number' => $r->query('file_number'),
            'status'      => 'Draft',
            'prop_state'  => 'Kano',
        ]);

        return view('cadastral_module.information.chart_register', [
            'chart'       => $chart,
            'coordinates' => collect(),
            'conflicts'   => [],
            'versions'    => collect(),
        ]);
    }

    public function store(Request $r)
    {
        $data = $this->validated($r);

        $this->applyClassification($data);

        $data['chart_ref'] = CadastralChart::nextRef('chart_ref', 'CHT', 4);
        $data['origin']  ??= 'cadastral_module';
        $data['version']   = 1;
        $data['is_current'] = true;

        $chart = CadastralChart::create($data);

        if (! $chart->charting_required) {
            return redirect()
                ->route('cadastral-module.charting.index')
                ->with('success', "{$chart->chart_ref} recorded. {$chart->file_number} is a conversion file, so no charting is required — commission its index card next.");
        }

        return redirect()
            ->route('cadastral-module.charting.edit', $chart)
            ->with('success', "{$chart->chart_ref} created. Add the beacon coordinates to compute its area.");
    }

    public function edit(CadastralChart $chart)
    {
        $coordinates = $chart->coordinates()->get();

        return view('cadastral_module.information.chart_register', [
            'chart'        => $chart,
            'coordinates'  => $coordinates,
            'conflicts'    => $this->conflicts->scan($chart),
            'computedArea' => $this->area->areaSqm($coordinates),
            'perimeter'    => $this->area->perimeterM($coordinates),
            // Every version charted for this file, oldest first, superseded
            // ones included — the history a dispute turns on.
            'versions'     => CadastralChart::query()
                ->where('file_number', $chart->file_number)
                ->orderBy('version')->orderBy('id')
                ->get(['id', 'chart_ref', 'version', 'status', 'is_current', 'supersedes_chart_id',
                       'area_sqm', 'charted_on', 'checked_by', 'checked_on', 'created_at']),
        ]);
    }

    public function update(Request $r, CadastralChart $chart)
    {
        if (! $chart->is_current) {
            return back()->with('error', "{$chart->chart_ref} is superseded and cannot be edited. Edit the current version instead.");
        }

        $data = $this->validated($r, $chart);

        $this->applyClassification($data);

        $chart->update($data);

        return redirect()
            ->route('cadastral-module.charting.edit', $chart)
            ->with('success', "{$chart->chart_ref} updated.");
    }

    /**
     * Supersede this chart with a copy the officer can then edit.
     *
     * The old row is kept and marked Superseded rather than deleted: a chart is
     * the Ministry's record of what it believed the ground looked like on a date.
     */
    public function newVersion(CadastralChart $chart)
    {
        if (! $chart->is_current) {
            return back()->with('error', 'That chart is already superseded.');
        }

        $next = DB::connection('sqlsrv')->transaction(function () use ($chart) {
            $clone = $chart->replicate([
                'chart_ref', 'version', 'is_current', 'supersedes_chart_id',
                'checked_by', 'checked_on', 'created_at', 'updated_at', 'deleted_at',
            ]);

            $clone->chart_ref           = CadastralChart::nextRef('chart_ref', 'CHT', 4);
            $clone->version             = ((int) $chart->version) + 1;
            $clone->is_current          = true;
            $clone->supersedes_chart_id = $chart->id;
            $clone->status              = 'Draft';
            $clone->checked_by          = null;
            $clone->checked_on          = null;
            $clone->save();

            // The beacon ring carries forward — a new version is usually a
            // correction to part of it, not a blank sheet.
            foreach ($chart->coordinates()->get() as $coordinate) {
                $copy = $coordinate->replicate(['cadastral_chart_id', 'created_at', 'updated_at', 'deleted_at']);
                $copy->cadastral_chart_id = $clone->id;
                $copy->save();
            }

            $chart->update(['is_current' => false, 'status' => 'Superseded']);

            return $clone;
        });

        return redirect()
            ->route('cadastral-module.charting.edit', $next)
            ->with('success', "Version {$next->version} created as {$next->chart_ref}. {$chart->chart_ref} is now superseded.");
    }

    public function markChecked(Request $r, CadastralChart $chart)
    {
        $chart->update([
            'status'     => 'Checked',
            'checked_by' => trim((string) $r->input('checked_by')) ?: (auth()->user()->name ?? null),
            'checked_on' => now()->toDateString(),
        ]);

        return back()->with('success', "{$chart->chart_ref} marked as checked.");
    }

    /**
     * Replace the whole beacon ring in one transaction.
     *
     * Whole-list replace rather than per-row edits: the ring is only meaningful
     * in order and as a set, and a half-applied update would produce an area
     * that looks plausible and is wrong.
     */
    public function saveCoordinates(Request $r, CadastralChart $chart)
    {
        $rows = $r->input('coordinates', []);

        if (! is_array($rows)) {
            return back()->with('error', 'No coordinates were submitted.');
        }

        DB::connection('sqlsrv')->transaction(function () use ($chart, $rows) {
            $chart->coordinates()->forceDelete();

            $order = 0;

            foreach ($rows as $row) {
                $beacon   = trim((string) ($row['beacon_id'] ?? ''));
                $northing = $row['northing'] ?? null;
                $easting  = $row['easting'] ?? null;

                // A wholly blank row is the empty template line, not data.
                if ($beacon === '' && $northing === null && $easting === null) {
                    continue;
                }

                CadastralChartCoordinate::create([
                    'cadastral_chart_id' => $chart->id,
                    'sort_order'         => $order++,
                    'beacon_id'          => $beacon ?: null,
                    'northing'           => $northing === '' ? null : $northing,
                    'easting'            => $easting === '' ? null : $easting,
                    'elevation'          => ($row['elevation'] ?? '') === '' ? null : $row['elevation'],
                    'bearing'            => trim((string) ($row['bearing'] ?? '')) ?: null,
                    'distance'           => ($row['distance'] ?? '') === '' ? null : $row['distance'],
                    'remarks'            => trim((string) ($row['remarks'] ?? '')) ?: null,
                ]);
            }

            // The area is recomputed from what was actually stored, never taken
            // from the form.
            $computed = $this->area->areaSqm($chart->coordinates()->get());

            if ($computed !== null) {
                $chart->update(['area_sqm' => $computed, 'area_source' => 'coordinates']);
            }
        });

        $computed = $chart->fresh()->area_sqm;

        return back()->with('success', $computed
            ? 'Coordinates saved. Computed area: ' . number_format((float) $computed, 2) . ' sqm.'
            : 'Coordinates saved. At least three points with both a northing and an easting are needed to compute an area.');
    }

    /** Charts that appear to describe the same ground as another. */
    public function conflicts(Request $r)
    {
        $clusters = $this->conflicts->identityClusters();

        $flagged = CadastralChart::query()
            ->current()
            ->whereIn('conflict_status', ['suspected', 'confirmed'])
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('cadastral_module.information.conflicts', compact('clusters', 'flagged'));
    }

    public function destroy(CadastralChart $chart)
    {
        if ($chart->indexCards()->exists()) {
            return back()->with('error', "{$chart->chart_ref} is referenced by an index card and cannot be deleted.");
        }

        if ($chart->version > 1 || CadastralChart::where('supersedes_chart_id', $chart->id)->exists()) {
            return back()->with('error', "{$chart->chart_ref} is part of a version chain and cannot be deleted.");
        }

        $ref = $chart->chart_ref;
        $chart->delete();

        return back()->with('success', "{$ref} deleted.");
    }

    /** origin value => [table, key column] of the GIS records a chart may mirror. */
    private const GIS_ORIGINS = [
        'gisCapture'      => ['gisCapture', 'id'],
        'surveyCadastral' => ['surveyCadastral', 'ID'],
    ];

    /**
     * Turn the form's GIS link into origin/origin_id, checking the row exists.
     * Read only: the GIS tables are never written from here. Leaving the link
     * blank keeps whatever origin the chart already had.
     */
    private function applyGisLink(array &$data, ?CadastralChart $existing): void
    {
        $origin = $data['gis_origin'] ?? null;
        $id     = $data['gis_origin_id'] ?? null;
        unset($data['gis_origin'], $data['gis_origin_id']);

        if (! $origin) {
            return;
        }

        [$table, $key] = self::GIS_ORIGINS[$origin];

        if (! DB::connection('sqlsrv')->table($table)->where($key, $id)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'gis_origin_id' => "There is no {$origin} record #{$id}.",
            ]);
        }

        $data['origin']    = $origin;
        $data['origin_id'] = (int) $id;
    }

    /**
     * Category and the charting-required flag come from the file number, so a
     * conversion file cannot be mis-marked as chartable on the form.
     */
    private function applyClassification(array &$data): void
    {
        $class = FileNumberFormat::classify($data['file_number']) ?: 'direct';

        $data['chart_category']    = $class;
        $data['charting_required'] = $class !== 'conversion';
    }

    private function validated(Request $r, ?CadastralChart $existing = null): array
    {
        $rules = [
            'file_number'        => 'required|string|max:100',
            'file_title'         => 'nullable|string|max:500',
            'plot_no'            => 'nullable|string|max:50',
            'block_no'           => 'nullable|string|max:50',
            'approved_plan_no'   => 'nullable|string|max:100',
            'tp_plan_no'         => 'nullable|string|max:100',
            'scheme_plan_no'     => 'nullable|string|max:100',
            'layout_name'        => 'nullable|string|max:255',
            'sheet_metric_index' => 'nullable|string|max:100',
            'sheet_metric_no'    => 'nullable|string|max:100',
            'sheet_imperial'     => 'nullable|string|max:100',
            'sheet_imperial_no'  => 'nullable|string|max:100',
            'area_sqm'           => 'nullable|numeric|min:0',
            'chart_officer_name' => 'nullable|string|max:255',
            'charted_on'         => 'nullable|date',
            'manual_chart_path'  => 'nullable|string|max:500',
            'digital_chart_path' => 'nullable|string|max:500',
            'conflict_status'    => ['nullable', Rule::in(['none', 'suspected', 'confirmed', 'cleared'])],
            'conflict_note'      => 'nullable|string|max:1000',
            'status'             => ['required', Rule::in(CadastralChart::STATUSES)],
            // Link to the GIS capture this chart mirrors (linked out, not embedded).
            'gis_origin'         => ['nullable', Rule::in(array_keys(self::GIS_ORIGINS))],
            'gis_origin_id'      => 'nullable|required_with:gis_origin|integer|min:1',
        ] + CadastralAddress::rules('prop_');

        // plot_no is the chart's plot field; prop_plot follows it.
        $data = CadastralAddress::normalise(
            $r->validate($rules, CadastralAddress::messages('prop_')),
            'prop_',
            'plot_no'
        );

        $this->applyGisLink($data, $existing);

        // An area typed by hand is kept, but recorded as hand-typed so nobody
        // later mistakes it for one the coordinates produced.
        if (! empty($data['area_sqm']) && (! $existing || (float) $existing->area_sqm !== (float) $data['area_sqm'])) {
            $data['area_source'] = 'manual';
        }

        return $data;
    }
}
