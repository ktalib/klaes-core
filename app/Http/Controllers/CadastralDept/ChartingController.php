<?php

namespace App\Http\Controllers\CadastralDept;

use App\Http\Controllers\Controller;
use App\Models\CadastralDept\CadastralCase;
use App\Models\CadastralDept\CadastralCharting;
use App\Services\CadastralDept\ChartingCards;
use App\Services\CadastralDept\WorkflowDefinition;
use App\Services\InstrumentWorkflow\WorkflowGuardException;
use Illuminate\Http\Request;

/** Charting cards (step 8): fill in at the charting step, view, print, and the charting tables. */
class ChartingController extends Controller
{
    /** The tables, by where they sit: 1st charting under Information, the rest under Reports. */
    public const TABLES = [
        'information' => ['Cadastral Information · 1st charting', ['first'], null],
        'reports' => ['Cadastral Reports · charting', ['single', 'second'], null],
        'checks' => ['Verification checks', ['check'], null],
    ];

    public function __construct(private ChartingCards $cards)
    {
    }

    public function index(Request $request, WorkflowDefinition $def)
    {
        $installed = ChartingCards::installed();
        $table = isset(self::TABLES[$request->query('table')]) ? $request->query('table') : 'reports';
        $term = trim((string) $request->query('q', ''));
        $rows = null;

        if ($installed) {
            $rows = CadastralCharting::query()->with(['case', 'officer'])
                ->where('is_current', true)
                ->whereIn('stage', self::TABLES[$table][1])
                ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('charting_no', 'like', "%{$term}%")
                    ->orWhereHas('case', fn ($c) => $c->where('file_number', 'like', "%{$term}%"))))
                ->orderByDesc('id')
                ->paginate(25)->withQueryString();
        }

        return view('cadastral_dept.charting.index', compact('def', 'installed', 'table', 'term', 'rows'));
    }

    public function create(int $case, WorkflowDefinition $def)
    {
        $case = CadastralCase::findOrFail($case);
        abort_unless(ChartingCards::installed(), 404);
        $due = $this->cards->due($case);
        if (!$due) {
            return redirect()->route('cadastral-dept.cases.show', $case->id)->with('error', "{$case->case_ref} is not at a charting step.");
        }

        return view('cadastral_dept.charting.form', [
            'def' => $def,
            'case' => $case,
            'due' => $due,
            'existing' => $this->cards->current($case, $due['stage'], $case->current_step),
            'first' => $due['stage'] === 'second' ? $this->cards->current($case, 'first') : null,
            'suggestions' => $due['stage'] === 'check' ? [] : $this->cards->suggestions($case),
            'reasons' => ChartingCards::NOT_CHARTED_REASONS,
            'fmc' => \App\Services\CadastralDept\FileMovementCards::installed() ? $case->fmc : null,
        ]);
    }

    public function store(Request $request, int $case)
    {
        $case = CadastralCase::findOrFail($case);
        $data = $request->validate([
            'charted' => 'nullable|in:0,1',
            'not_charted_reason' => 'nullable|string|max:255',
            'not_charted_other' => 'nullable|string|max:255',
            'falls_on_existing' => 'nullable|in:0,1',
            'existing_files' => 'nullable|array|max:20',
            'existing_files.*' => 'nullable|string|max:100',
            'overlap_extent' => 'nullable|in:fully,partly',
            'agrees_with_first' => 'nullable|in:0,1',
            'agree_note' => 'nullable|string|max:1000',
            'matches_record' => 'nullable|in:0,1',
            'match_note' => 'nullable|string|max:1000',
            'description' => 'nullable|string|max:4000',
            'sheet_type' => 'nullable|in:metric,imperial',
            'sheet_no' => 'nullable|string|max:50',
            'layout' => 'nullable|string|max:150',
            'plan_no' => 'nullable|string|max:100',
            'remarks' => 'nullable|string|max:4000',
            'beacons' => 'nullable|array|max:60',
            'beacons.*.beacon' => 'nullable|string|max:30',
            'beacons.*.easting' => 'nullable|string|max:30',
            'beacons.*.northing' => 'nullable|string|max:30',
            'beacons.*.remarks' => 'nullable|string|max:255',
        ]);

        try {
            $card = $this->cards->save($case, $data, auth()->id());
        } catch (WorkflowGuardException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('cadastral-dept.cases.show', $case->id)
            ->with('success', "{$card->charting_no}" . ($card->version > 1 ? " (version {$card->version})" : '') . ' saved; the charting task is ticked.');
    }

    public function show(int $charting, WorkflowDefinition $def)
    {
        $card = CadastralCharting::with(['beacons', 'case', 'officer'])->findOrFail($charting);

        return view('cadastral_dept.charting.show', [
            'def' => $def,
            'card' => $card,
            'case' => $card->case,
            'versions' => CadastralCharting::query()->where('cadastral_case_id', $card->cadastral_case_id)
                ->where('charting_no', $card->charting_no)->where('step', $card->step)->orderByDesc('version')->get(),
        ]);
    }

    public function print(int $charting, WorkflowDefinition $def)
    {
        $card = CadastralCharting::with(['beacons', 'case', 'officer'])->findOrFail($charting);
        $card->increment('print_count');

        return view('cadastral_dept.charting.print', ['def' => $def, 'card' => $card->fresh(['beacons', 'case', 'officer']), 'case' => $card->case]);
    }
}
