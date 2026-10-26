<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyCoFile;
use App\Models\Survey\SurveyOpRecord as OP;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Compensation > Records > OP Verification (docs/KLAES-CORE Survey Module.md 2.1; the
 * annotated sheet routes it on to "Deeds OP Verification"). Survey checks each OP that
 * OP Allocation generated: Verified or Returned, then Sent to Deeds once verified.
 *
 * Survey-side only (user, 2026-10-25): "Sent to Deeds" is a stamp on the Survey record
 * for tracking; nothing is written into the Deeds module. A sent OP is locked.
 */
class OpVerificationController extends Controller
{
    public function index(Request $r)
    {
        $q = OP::query()->with(['case.project.coFile', 'beneficiary']);

        if ($term = trim((string) $r->query('q', ''))) {
            $q->where(fn ($w) => $w->where('op_number', 'like', "%$term%")->orWhere('plot_or_cash_ref', 'like', "%$term%")
                ->orWhereHas('case', fn ($c) => $c->where('case_ref', 'like', "%$term%"))
                ->orWhereHas('beneficiary', fn ($b) => $b->where('full_name', 'like', "%$term%")));
        }
        if (in_array($v = $r->query('verification'), OP::V_STATUSES, true)) {
            $v === OP::V_PENDING
                ? $q->where(fn ($w) => $w->whereNull('verification_status')->orWhere('verification_status', OP::V_PENDING))
                : $q->where('verification_status', $v);
        }
        if ($co = $r->query('co_file')) {
            $q->whereHas('case.project', fn ($p) => $p->where('survey_co_file_id', $co));
        }

        $stats = [];
        foreach (OP::V_STATUSES as $s) {
            $stats[$s] = $s === OP::V_PENDING
                ? OP::where(fn ($w) => $w->whereNull('verification_status')->orWhere('verification_status', OP::V_PENDING))->count()
                : OP::where('verification_status', $s)->count();
        }

        return view('survey_module.compensation.op_verification', [
            'ops'     => $q->orderByDesc('id')->paginate(20)->withQueryString(),
            'stats'   => $stats,
            'coFiles' => SurveyCoFile::orderByDesc('id')->get(['id', 'co_file_no', 'title']),
            'coFile'  => $co ? SurveyCoFile::find($co) : null,
        ]);
    }

    public function act(Request $r, OP $op)
    {
        $data = $r->validate([
            'action' => ['required', Rule::in(['verify', 'return', 'send'])],
            'note'   => 'nullable|string|max:2000',
        ]);
        $now = $op->verificationStatus();
        $note = trim((string) ($data['note'] ?? ''));

        if ($now === OP::V_SENT) {
            return back()->with('error', ($op->op_number ?: "OP #{$op->id}") . ' has been sent to Deeds and is locked.');
        }

        $attrs = [];
        switch ($data['action']) {
            case 'verify':
                if (! in_array($now, [OP::V_PENDING, OP::V_RETURNED], true)) return back()->with('error', 'Only a pending or returned OP can be verified.');
                $attrs = ['verification_status' => OP::V_VERIFIED, 'verified_at' => now(), 'verified_by' => Auth::id()];
                break;
            case 'return':
                if ($note === '') throw ValidationException::withMessages(['note' => 'Say what must be corrected.']);
                $attrs = ['verification_status' => OP::V_RETURNED];
                break;
            case 'send':
                if ($now !== OP::V_VERIFIED) return back()->with('error', 'Verify the OP before sending it to Deeds.');
                $attrs = ['verification_status' => OP::V_SENT, 'sent_to_deeds_at' => now(), 'sent_to_deeds_by' => Auth::id()];
                break;
        }

        $label = ['verify' => 'Verified', 'return' => 'Returned', 'send' => 'Sent to Deeds'][$data['action']];
        $stamp = now()->format('d M Y H:i') . ' · ' . (Auth::user()?->name ?? 'system') . ' · ' . $label;
        $attrs['verification_notes'] = trim(($op->verification_notes ? $op->verification_notes . "\n" : '') . "[$stamp]" . ($note !== '' ? " $note" : ''));

        $op->update($attrs);

        return back()->with('success', ($op->op_number ?: "OP #{$op->id}") . " is now {$label}.");
    }
}
