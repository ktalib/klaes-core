<?php

namespace App\Http\Controllers\SystemAdmin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\InstrumentWorkflow\Concerns\RunsWorkflowActions;
use App\Services\IndexingPay\IndexingPaySettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Configurable Entries > Indexing Pay: saves a version of the indexing rate card.
 * The tab itself is drawn by ConfigurableEntriesController.
 */
class IndexingPaySettingsController extends Controller
{
    use RunsWorkflowActions;

    public function save(Request $r, IndexingPaySettings $settings)
    {
        $user = Auth::user();
        $roles = array_map(fn ($x) => strtolower(trim((string) $x)), $user?->assignedRoleNames() ?? []);
        abort_unless($user && ($user->isSuperAdmin() || in_array('system settings', $roles, true)), 403, 'Configurable Entries needs the System Settings role.');

        $money = 'required|numeric|min:0|max:100000';
        $data = $r->validate([
            'effective_from'        => 'required|date_format:Y-m-d',
            'base.regular'          => $money,
            'base.sltr'             => $money,
            'base.kangis_land'      => $money,
            'base.kangis'           => $money,
            'rates.trans'           => $money,
            'rates.gr'              => $money,
            'rates.bb'              => $money,
            'sltr_pays_gr_bb'       => 'nullable|boolean',
            'start_date'            => 'required|date_format:Y-m-d',
            'end_date'              => 'required|date_format:Y-m-d|after_or_equal:start_date',
            'daily_file_target'     => 'required|integer|min:0|max:100000',
            'monthly_target'        => 'required|numeric|min:0|max:100000000',
            'eligible_modules'      => 'required|string|max:500',
            'eligible_staff_types'  => 'nullable|string|max:200',
            'eligible_work_stations' => 'nullable|string|max:500',
            'show.header'           => 'nullable|boolean',
            'show.profile'          => 'nullable|boolean',
            'show.create_page'      => 'nullable|boolean',
            'note'                  => 'nullable|string|max:500',
        ], [], [
            'base.regular' => 'Regular Land File fee', 'base.sltr' => 'SLTR File fee',
            'base.kangis_land' => 'KANGIS Land File fee', 'base.kangis' => 'KANGIS File fee',
            'rates.trans' => 'Transaction rate', 'rates.gr' => 'Ground Rent rate', 'rates.bb' => 'Bill Balance rate',
        ]);

        // ITF stays dormant and is not on the form: carry its stored fee forward unchanged.
        $current = $settings->forDate($data['effective_from']);
        $values = [
            'base' => [
                'regular' => $data['base']['regular'], 'sltr' => $data['base']['sltr'],
                'kangis_land' => $data['base']['kangis_land'], 'kangis' => $data['base']['kangis'],
                'itf' => $current['base']['itf'],
            ],
            'rates' => $data['rates'],
            'sltr_pays_gr_bb' => $r->boolean('sltr_pays_gr_bb'),
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'daily_file_target' => (int) $data['daily_file_target'],
            'monthly_target' => $data['monthly_target'],
            'eligible_modules' => array_map('trim', explode(',', $data['eligible_modules'])),
            'eligible_staff_types' => array_map('trim', explode(',', (string) ($data['eligible_staff_types'] ?? ''))),
            'eligible_work_stations' => array_map('trim', explode(',', (string) ($data['eligible_work_stations'] ?? ''))),
            'show' => [
                'header' => $r->boolean('show.header'),
                'profile' => $r->boolean('show.profile'),
                'create_page' => $r->boolean('show.create_page'),
            ],
        ];

        return $this->attempt(function () use ($settings, $values, $data) {
            $before = $settings->forDate($data['effective_from']);
            $settings->save($values, $data['effective_from'], Auth::id(), $data['note'] ?? null);
            Log::info('Configurable Entries: Indexing Pay saved', [
                'effective_from' => $data['effective_from'], 'before' => $before, 'after' => $values, 'by' => Auth::id(),
            ]);

            return 'Indexing Pay saved. It applies from ' . \Carbon\Carbon::parse($data['effective_from'])->format('j M Y') . ' onward.';
        }, 'Saved.', route('configurable-entries.index', ['tab' => 'indexing-pay']));
    }
}
