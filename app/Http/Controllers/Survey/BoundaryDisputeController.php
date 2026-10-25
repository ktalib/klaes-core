<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyBoundaryDispute as BD;
use App\Models\Survey\SurveyRegisterFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Examination/Verification > Boundary Dispute (docs/KLAES-CORE Survey Module.md 5.2):
 * capture the dispute, investigate, resolve or escalate. One page, as Complaints:
 * the register plus an inline panel that is the create and the edit form. The file
 * comes from the global selector and fills plot, district and LGA; a dispute may
 * point at the Register File line it arose from.
 */
class BoundaryDisputeController extends Controller
{
    public const NATURE_OTHER = '__other__';

    public function index(Request $r)
    {
        return view('survey_module.workflow.boundary_disputes', $this->pageData($r));
    }

    public function edit(Request $r, BD $dispute)
    {
        if ($dispute->isFrozen()) {
            return redirect()->route('survey-module.workflow.boundary-disputes.index')
                ->with('error', "{$dispute->dispute_ref} is resolved and can no longer be edited.");
        }

        return view('survey_module.workflow.boundary_disputes', $this->pageData($r, $dispute));
    }

    public function store(Request $r)
    {
        $data = $this->validated($r);
        $d = BD::create($data + ['dispute_ref' => BD::nextRef('dispute_ref', 'BD'), 'status' => BD::OPEN]);

        return redirect()->route('survey-module.workflow.boundary-disputes.index')
            ->with('success', "Boundary dispute {$d->dispute_ref} logged.");
    }

    public function update(Request $r, BD $dispute)
    {
        if ($dispute->isFrozen()) {
            return back()->with('error', "{$dispute->dispute_ref} is resolved and can no longer be edited.");
        }
        $dispute->update($this->validated($r));

        return redirect()->route('survey-module.workflow.boundary-disputes.index')->with('success', "{$dispute->dispute_ref} updated.");
    }

    public function status(Request $r, BD $dispute)
    {
        $data = $r->validate(['status' => ['required', Rule::in(BD::STATUSES)], 'note' => 'nullable|string|max:2000']);

        if (! in_array($data['status'], BD::NEXT[$dispute->status] ?? [], true)) {
            return back()->with('error', "{$dispute->dispute_ref} is {$dispute->status}; it cannot move to {$data['status']}.");
        }
        $note = trim((string) ($data['note'] ?? ''));
        if (in_array($data['status'], [BD::RESOLVED, BD::ESCALATED], true) && $note === '') {
            throw ValidationException::withMessages(['note' => $data['status'] === BD::RESOLVED
                ? 'Say how the dispute was resolved.' : 'Say why it is escalated, and to whom.']);
        }

        $attrs = ['status' => $data['status']];
        if ($note !== '') {
            $stamp = now()->format('d M Y H:i') . ' · ' . (Auth::user()?->name ?? 'system') . ' · ' . $data['status'];
            $attrs['notes'] = trim(($dispute->notes ? $dispute->notes . "\n" : '') . "[$stamp] $note");
        }
        if ($data['status'] === BD::RESOLVED) {
            $attrs['resolved_at'] = now();
            $attrs['resolved_by'] = Auth::id();
        }
        $dispute->update($attrs);

        return back()->with('success', "{$dispute->dispute_ref} is now {$data['status']}.");
    }

    public function destroy(BD $dispute)
    {
        if ($dispute->status !== BD::OPEN) {
            return back()->with('error', "{$dispute->dispute_ref} is {$dispute->status}; only an Open dispute logged in error can be deleted.");
        }
        $ref = $dispute->dispute_ref;
        $dispute->delete();

        return back()->with('success', "{$ref} deleted.");
    }

    /* ------------------------------------------------------------------ */

    private function pageData(Request $r, ?BD $editing = null): array
    {
        $q = BD::query()->with('registerFile');
        if ($term = trim((string) $r->query('q', ''))) {
            $q->where(fn ($w) => $w->where('dispute_ref', 'like', "%$term%")->orWhere('file_number', 'like', "%$term%")
                ->orWhere('other_file_number', 'like', "%$term%")->orWhere('complainant_name', 'like', "%$term%")
                ->orWhere('respondent_name', 'like', "%$term%")->orWhere('district', 'like', "%$term%"));
        }
        if (in_array($s = $r->query('status'), BD::STATUSES, true)) $q->where('status', $s);
        if ($n = $r->query('nature')) $q->where('nature', $n);

        $stats = [];
        foreach (BD::STATUSES as $s) $stats[$s] = BD::where('status', $s)->count();

        return [
            'disputes' => $q->orderByDesc('id')->paginate(15)->withQueryString(),
            'stats'    => $stats,
            'dispute'  => $editing ?? new BD(['received_date' => now()->toDateString()]),
            'natures'  => $this->natures(),
            'lines'    => SurveyRegisterFile::orderByDesc('id')->limit(300)->get(['id', 'register_ref', 'file_number', 'purpose']),
        ];
    }

    /** The listed natures plus any already used, so an edited record keeps its own. */
    private function natures(): array
    {
        return array_values(array_unique(array_merge(BD::NATURES, BD::distinct()->pluck('nature')->all())));
    }

    private function validated(Request $r): array
    {
        $data = $r->validate([
            'survey_register_file_id' => ['nullable', Rule::exists('sqlsrv.survey_register_files', 'id')->whereNull('deleted_at')],
            'file_number'       => 'nullable|string|max:100',
            'other_file_number' => 'nullable|string|max:100',
            'complainant_name'  => 'required|string|max:255',
            'complainant_phone' => 'nullable|string|max:50',
            'respondent_name'   => 'nullable|string|max:255',
            'nature'            => 'required|string|max:100',
            'nature_other'      => ['nullable', 'string', 'max:100', Rule::requiredIf(fn () => $r->input('nature') === self::NATURE_OTHER)],
            'plot_no'           => 'nullable|string|max:100',
            'district'          => 'nullable|string|max:255',
            'lga'               => 'nullable|string|max:255',
            'description'       => 'nullable|string|max:4000',
            'received_date'     => 'required|date|before_or_equal:today',
        ], [
            'nature_other.required'          => 'Please specify the nature of the dispute.',
            'received_date.before_or_equal'  => 'The date received cannot be in the future.',
        ]);

        // "Other": the typed nature is stored, but a listed one typed in another case wins.
        if ($data['nature'] === self::NATURE_OTHER) {
            $typed = trim(preg_replace('/\s+/', ' ', (string) $data['nature_other']));
            $data['nature'] = collect($this->natures())->first(fn ($n) => mb_strtolower($n) === mb_strtolower($typed)) ?? $typed;
        }
        unset($data['nature_other']);

        // A Register File line supplies its file when none was picked.
        if (! empty($data['survey_register_file_id']) && empty($data['file_number'])) {
            $line = SurveyRegisterFile::find($data['survey_register_file_id']);
            $data['file_number'] = $line?->file_number;
            foreach (['plot_no', 'district', 'lga'] as $k) $data[$k] = $data[$k] ?? $line?->{$k};
        }

        foreach (['complainant_name', 'respondent_name'] as $k) {
            if (isset($data[$k])) $data[$k] = trim(preg_replace('/\s+/', ' ', $data[$k])) ?: null;
        }
        // The location is district + LGA; the plot number never goes in it.
        $data['location'] = implode(', ', array_filter([$data['district'] ?? null, $data['lga'] ?? null])) ?: null;

        return $data;
    }
}
