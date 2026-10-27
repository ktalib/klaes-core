<?php

namespace App\Http\Controllers\CadastralDept;

use App\Http\Controllers\Controller;
use App\Http\Controllers\InstrumentWorkflow\Concerns\RunsWorkflowActions;
use App\Models\Cadastral\CadastralBill;
use App\Models\CadastralDept\CadastralCase;
use App\Services\Cadastral\AreaCalculator;
use App\Services\Cadastral\CadastralBillCalculator;
use App\Services\Cadastral\CadastralSettings;
use App\Services\CadastralDept\CaseWorkflow;
use App\Services\CadastralDept\PndBills;
use App\Services\CadastralDept\WorkflowDefinition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The Fees P&D bill (step 11): area and beacons, description and fees on one page. */
class PndBillController extends Controller
{
    use RunsWorkflowActions;

    /** What the officer enters on the fee lines; everything else comes from the record. */
    private const FEE_INPUTS = ['delay_days', 'transport_band', 'transport_km', 'field_work_days', 'office_work_days', 'plan_prints'];

    public function __construct(private PndBills $bills)
    {
    }

    /** Every P&D bill for a file that has passed through the workflow. */
    public function index(Request $request, WorkflowDefinition $def)
    {
        $term = trim((string) $request->query('q', ''));
        $status = in_array($request->query('status'), ['Issued', 'Cancelled'], true) ? $request->query('status') : null;

        $rows = CadastralBill::query()
            ->whereIn('file_number', CadastralCase::query()->select('file_number'))
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('bill_ref', 'like', "%{$term}%")->orWhere('file_number', 'like', "%{$term}%")))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('id')
            ->paginate(25)->withQueryString();

        // The case behind each bill (newest per file), for the property columns and links.
        $cases = CadastralCase::query()->whereIn('file_number', $rows->pluck('file_number')->unique())
            ->orderBy('id')->get()->keyBy('file_number');

        // Summary cards over the workflow's bills; Issued / Cancelled filter the list.
        $mine = CadastralBill::query()->whereIn('file_number', CadastralCase::query()->select('file_number'));
        $link = fn (?string $s) => route('cadastral-dept.bills.index', array_filter(['status' => $s]));
        $awaiting = CaseWorkflow::installed()
            ? CadastralCase::query()->open()->where('current_step', 'pnd_billing')->get()
                ->filter(fn ($c) => !in_array(PndBills::TASK, $c->doneTaskKeys($c->current_step), true))->count()
            : 0;
        $cdCards = [
            ['label' => 'Bills issued', 'value' => number_format((clone $mine)->where('status', 'Issued')->count()), 'hint' => 'live bills', 'colour' => 'green', 'url' => $link('Issued'), 'active' => $status === 'Issued'],
            ['label' => 'Total issued', 'value' => '₦' . number_format((float) (clone $mine)->where('status', 'Issued')->sum('grand_total'), 2), 'hint' => 'filled in as Survey Fees on Deeds Bill Balance', 'colour' => 'blue'],
            ['label' => 'Cancelled', 'value' => number_format((clone $mine)->where('status', 'Cancelled')->count()), 'hint' => 'replaced or withdrawn', 'colour' => 'gray', 'url' => $link('Cancelled'), 'active' => $status === 'Cancelled'],
            ['label' => 'Waiting for a bill', 'value' => number_format($awaiting), 'hint' => 'files at the billing step', 'colour' => 'yellow',
                'url' => route('cadastral-dept.desks.show', ['desk' => 'ad_pnd', 'queue' => 'bill'])],
        ];

        return view('cadastral_dept.bills.index', compact('def', 'rows', 'cases', 'term', 'status', 'cdCards'));
    }

    public function show(int $case, WorkflowDefinition $def, CadastralSettings $settings, CadastralBillCalculator $calculator)
    {
        $case = CadastralCase::findOrFail($case);
        $pd = $this->bills->record($case);

        return view('cadastral_dept.bills.show', [
            'def' => $def,
            'case' => $case,
            'pd' => $pd,
            'pillars' => $pd ? $pd->pillars()->get() : collect(),
            'backfill' => $pd ? null : $this->bills->backfill($case),
            'due' => $this->bills->dueNow($case),
            'correction' => $this->bills->isCorrection($case),
            'bands' => $calculator->bandOptions(),
            'rates' => $settings->feeRates(),
            'units' => AreaCalculator::UNITS,
            'liveBill' => PndBills::liveBill($case->file_number),
            'history' => $pd ? $pd->bills()->get() : collect(),
            // Plan Description (change request 26-10-2026)
            'linesInstalled' => PndBills::linesInstalled(),
            'header' => $this->bills->headerDefaults($case, $pd),
            'beaconTypes' => PndBills::beaconTypes(),
            'registerBeacons' => PndBills::linesInstalled() ? PndBills::registerBeacons() : [],
            'forms' => PndBills::FORMS,
            'defaultOwnership' => PndBills::defaultOwnership($case),
        ]);
    }

    /** The Plan Description on its form: CAD 20 (bearings and distances) or CAD 13 (SLTR). Standalone page. */
    public function printDescription(Request $request, int $case)
    {
        $case = CadastralCase::findOrFail($case);
        $pd = $this->bills->record($case);
        abort_unless($pd, 404, 'Save the Plan Description first.');
        $header = $this->bills->headerDefaults($case, $pd);
        $form = array_key_exists((string) $request->query('form'), PndBills::FORMS) ? $request->query('form') : $header['description_form'];

        return view('cadastral_dept.bills.description_print', [
            'case' => $case,
            'pd' => $pd,
            'pillars' => $pd->pillars()->get(),
            'header' => $header,
            'form' => $form,
        ]);
    }

    public function saveArea(Request $request, int $case)
    {
        $data = $request->validate([
            'area_value' => 'nullable|string|max:30',
            'area_unit' => 'nullable|string|max:10',
            'pillars' => 'nullable|array|max:500',
            'pillars.*.pillar_number' => 'nullable|string|max:50',
            'pillars.*.ownership' => 'nullable|in:government,private',
            'pillars.*.easting' => 'nullable|string|max:30',
            'pillars.*.northing' => 'nullable|string|max:30',
            'pillars.*.pillar_type' => 'nullable|string|max:60',
            'pillars.*.pillar_type_other' => 'nullable|string|max:50',
            'pillars.*.distance_m' => 'nullable|string|max:30',
            'pillars.*.bearing_deg' => 'nullable|string|max:10',
            'pillars.*.bearing_min' => 'nullable|string|max:10',
            'pillars.*.bearing_sec' => 'nullable|string|max:10',
            'situated_at' => 'nullable|string|max:255',
            'plan_drawing_no' => 'nullable|string|max:100',
            'copied_from_plan_no' => 'nullable|string|max:100',
            'dimensions' => 'nullable|string|max:4000',
            'description_form' => 'nullable|in:' . implode(',', array_keys(PndBills::FORMS)),
        ]);
        $case = CadastralCase::findOrFail($case);

        return $this->attempt(function () use ($case, $data) {
            $pd = $this->bills->saveArea($case, $data, auth()->id());

            return "{$pd->pd_ref}: area " . number_format((float) $pd->area_sqm, 2) . ' m² (' . number_format((float) $pd->area_sqm / 10000, 4) . ' ha) and ' . $pd->pillars()->count() . ' line(s) saved.';
        }, 'Area saved.');
    }

    public function generateDescription(int $case)
    {
        $case = CadastralCase::findOrFail($case);

        return $this->attempt(function () use ($case) {
            $this->bills->generateDescription($case);

            return 'Description written from the template. Check and edit it before it goes out.';
        }, 'Description generated.');
    }

    public function saveDescription(Request $request, int $case)
    {
        $data = $request->validate(['description_body' => 'nullable|string|max:20000']);
        $case = CadastralCase::findOrFail($case);

        return $this->attempt(function () use ($case, $data) {
            $this->bills->saveDescription($case, $data['description_body'] ?? null, auth()->id());

            return 'Description saved.';
        }, 'Description saved.');
    }

    public function preview(Request $request, int $case): JsonResponse
    {
        $case = CadastralCase::findOrFail($case);

        return response()->json($this->bills->preview($case, $request->only(self::FEE_INPUTS)));
    }

    public function store(Request $request, int $case)
    {
        $request->validate([
            'delay_days' => 'nullable|integer|min:0|max:' . CadastralBillCalculator::MAX_QTY,
            'field_work_days' => 'nullable|integer|min:0|max:' . CadastralBillCalculator::MAX_QTY,
            'office_work_days' => 'nullable|integer|min:0|max:' . CadastralBillCalculator::MAX_QTY,
            'plan_prints' => 'nullable|integer|min:0|max:' . CadastralBillCalculator::MAX_QTY,
            'transport_band' => 'nullable|string|max:20',
            'transport_km' => 'nullable|numeric|min:0|max:100000',
        ]);
        $case = CadastralCase::findOrFail($case);

        return $this->attempt(function () use ($case, $request) {
            $bill = $this->bills->issue($case, $request->only(self::FEE_INPUTS), auth()->id());

            return "Bill {$bill->bill_ref} issued: ₦" . number_format((float) $bill->grand_total, 2) . '. Deeds Bill Balance will pick it up as the survey fee.';
        }, 'Bill issued.');
    }

    /** The official sheet, from the bill's own snapshot (copied from the old module's print). */
    public function print(int $bill, CadastralBillCalculator $calculator, CadastralSettings $settings)
    {
        $bill = CadastralBill::with('planDescription')->findOrFail($bill);
        $case = CadastralCase::query()->where('file_number', $bill->file_number)->orderByDesc('id')->first();

        return view('cadastral_dept.bills.print', [
            'record' => $bill->planDescription,
            'bill' => $bill,
            'case' => $case,
            'lines' => $calculator->lines($bill),
            'schedule' => $settings->areaSchedule(),
            'bands' => $settings->transportBands(),
        ]);
    }
}
