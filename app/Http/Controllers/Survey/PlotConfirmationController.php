<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyOpShowing as Showing;
use App\Models\Survey\SurveyPlotCheck as Check;
use Illuminate\Http\Request;

/**
 * Plot Confirmation register (desktop, 2026-10-30): what the Plot Confirmation app
 * records in the field, for the office. Two tabs:
 *
 *   Showings       each time an OP's plot was shown to its allottee (Showing History)
 *   Ground checks  each ground check of a plot against its layout and the Beacon Register
 *
 * Read-only: records are made on the phone. Search, filters and S/N in display order.
 */
class PlotConfirmationController extends Controller
{
    public function index(Request $r)
    {
        $tab = $r->query('tab') === 'checks' ? 'checks' : 'showings';
        $term = trim(preg_replace('/\s+/', ' ', (string) $r->query('q', '')));
        $like = '%' . str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $term) . '%';
        $from = $r->query('from');
        $to = $r->query('to');

        $showings = Showing::query()->whereNotNull('op_source')->withCount('photos')
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('op_serial', 'like', $like)->orWhere('allottee_name', 'like', $like)
                ->orWhere('plot_no', 'like', $like)->orWhere('district', 'like', $like)->orWhere('surveyor_name', 'like', $like)))
            ->when(in_array($r->query('reason'), Showing::REASONS, true), fn ($q) => $q->where('reason', $r->query('reason')))
            ->when($r->query('reason') === 'first', fn ($q) => $q->where('showing_no', 1))
            ->when($from, fn ($q) => $q->whereDate('shown_on', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('shown_on', '<=', $to));

        $checks = Check::query()->withCount('photos')
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('check_ref', 'like', $like)->orWhere('op_serial', 'like', $like)
                ->orWhere('holder_name', 'like', $like)->orWhere('plot_no', 'like', $like)->orWhere('layout_no', 'like', $like)
                ->orWhere('district', 'like', $like)->orWhere('surveyor_name', 'like', $like)->orWhere('beacons_found', 'like', $like)))
            ->when(in_array($r->query('result'), Check::RESULTS, true), fn ($q) => $q->where('result', $r->query('result')))
            ->when($from, fn ($q) => $q->whereDate('checked_on', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('checked_on', '<=', $to));

        return view('survey_module.compensation.plot_confirmations', [
            'tab'      => $tab,
            'totals'   => [
                'showings'      => Showing::whereNotNull('op_source')->count(),
                'ops'           => Showing::whereNotNull('op_source')->distinct()->count('op_source_id'),
                'checks'        => Check::count(),
                'discrepancies' => Check::where('result', Check::DISCREPANCY)->count(),
            ],
            'rows'     => $tab === 'checks'
                ? $checks->with('photos')->orderByDesc('checked_on')->orderByDesc('id')->paginate(20)->withQueryString()
                : $showings->with('photos')->orderByDesc('shown_on')->orderByDesc('id')->paginate(20)->withQueryString(),
        ]);
    }
}
