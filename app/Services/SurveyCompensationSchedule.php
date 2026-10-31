<?php

namespace App\Services;

use App\Models\Survey\SurveyBeneficiary;
use App\Models\Survey\SurveyCompCase;
use App\Models\Survey\SurveyProject;
use Illuminate\Support\Collection;

/**
 * The Compensation Calculator's per-beneficiary schedule for one project
 * (user transcript, 2026-10-25):
 *
 * - Land to Land: a beneficiary appears once however many cases (portions)
 *   name them; their plots are totalled and split 50:50, odd plot to Government.
 * - Monetary Compensation: each beneficiary's trees, count x rate, totalled.
 *
 * A case belongs to the people registered on it. When a case names several
 * people it is a joint holding and is shown as one row for them together; the
 * schedule never divides a case between them on its own.
 *
 * People are matched across cases by NIN, else phone, else name, since each case
 * stores its own beneficiary rows.
 */
class SurveyCompensationSchedule
{
    /** Statuses that do not count towards compensation. */
    public const EXCLUDED_STATUSES = ['Rejected'];

    public function forProject(SurveyProject $project): array
    {
        $cases = SurveyCompCase::where('survey_project_id', $project->id)
            ->whereNotIn('status', self::EXCLUDED_STATUSES)
            ->with(['beneficiaries', 'trees'])
            ->withCount('plots')
            ->orderBy('id')
            ->get();

        $holdings = $this->holdings($cases);

        return $project->isMonetary()
            ? ['scheme' => 'trees', 'cases' => $cases->count()] + $this->cashForTrees($holdings)
            : ['scheme' => 'plots', 'cases' => $cases->count()] + $this->plotForPlot($holdings);
    }

    /** Identity used to recognise the same person on different cases. */
    public static function personKey(SurveyBeneficiary $b): string
    {
        $nin = preg_replace('/\D/', '', (string) $b->nin);
        if (strlen($nin) === 11) return 'nin:' . $nin;

        $phone = preg_replace('/\D/', '', (string) $b->phone);
        if (strlen($phone) >= 10 && ! preg_match('/^0+$/', $phone)) return 'tel:' . substr($phone, -10);

        return 'name:' . mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $b->full_name)));
    }

    /**
     * Cases grouped by the set of people on them.
     *
     * @return Collection<string, array{key: string, people: array, joint: bool, cases: Collection}>
     */
    private function holdings(Collection $cases): Collection
    {
        $out = [];

        foreach ($cases as $case) {
            $people = [];
            foreach ($case->beneficiaries as $b) {
                $people[self::personKey($b)] ??= $b;   // first record wins for display
            }
            ksort($people);
            $key = $people ? implode('+', array_keys($people)) : 'none';

            if (! isset($out[$key])) {
                $out[$key] = ['key' => $key, 'people' => array_values($people), 'joint' => count($people) > 1, 'cases' => collect()];
            }
            $out[$key]['cases']->push($case);
        }

        return collect($out);
    }

    private function plotForPlot(Collection $holdings): array
    {
        $rows = [];
        $totals = ['plots' => 0, 'owner' => 0, 'govt' => 0];

        foreach ($holdings as $h) {
            // Each case's plot count comes from plotSplit(), which owns "plot rows win".
            $plots = (int) $h['cases']->sum(fn ($c) => $c->plotSplit()['total']);
            $split = (new SurveyCompCase(['num_plots' => $plots]))->plotSplit();   // odd plot to Government

            $rows[] = $this->row($h) + [
                'plots' => $plots,
                'owner' => $split['farmer'],
                'govt'  => $split['govt'],
            ];
            $totals['plots'] += $plots;
            $totals['owner'] += $split['farmer'];
            $totals['govt']  += $split['govt'];
        }

        return ['rows' => $this->sorted($rows), 'totals' => $totals];
    }

    private function cashForTrees(Collection $holdings): array
    {
        $rows = [];
        $totals = ['trees' => 0, 'amount' => 0.0];

        foreach ($holdings as $h) {
            $lines = [];
            foreach ($h['cases'] as $case) {
                foreach ($case->trees as $t) {
                    // Same tree at the same rate collapses to one line; a different rate stays separate.
                    $k = mb_strtolower($t->tree_type) . '|' . (float) $t->unit_price;
                    $lines[$k] ??= ['tree' => $t->tree_type, 'rate' => (float) $t->unit_price, 'count' => 0, 'amount' => 0.0];
                    $lines[$k]['count']  += (int) $t->quantity;
                    $lines[$k]['amount'] += (float) $t->line_total;
                }
            }
            $lines = array_values($lines);
            usort($lines, fn ($a, $b) => strcasecmp($a['tree'], $b['tree']));

            $count  = array_sum(array_column($lines, 'count'));
            $amount = array_sum(array_column($lines, 'amount'));

            $rows[] = $this->row($h) + ['lines' => $lines, 'trees' => $count, 'amount' => $amount];
            $totals['trees']  += $count;
            $totals['amount'] += $amount;
        }

        return ['rows' => $this->sorted($rows), 'totals' => $totals];
    }

    /** Columns every row shares. */
    private function row(array $h): array
    {
        $first = $h['people'][0] ?? null;

        return [
            'key'       => $h['key'],
            'name'      => $h['people'] ? implode(' & ', array_map(fn ($p) => $p->full_name, $h['people'])) : null,
            'joint'     => $h['joint'],
            'unassigned'=> ! $h['people'],
            'phone'     => $h['joint'] ? null : $first?->phone,
            'nin'       => $h['joint'] ? null : $first?->nin,
            'bank'      => $h['joint'] ? null : $first?->bank_name,
            'account'   => $h['joint'] ? null : $first?->account_number,
            'cases'     => $h['cases']->map(fn ($c) => ['id' => $c->id, 'ref' => $c->case_ref, 'portion' => $c->portion_no])->all(),
        ];
    }

    /** Named holdings alphabetically; cases with nobody recorded last. */
    private function sorted(array $rows): array
    {
        usort($rows, fn ($a, $b) => [$a['unassigned'], mb_strtolower((string) $a['name'])] <=> [$b['unassigned'], mb_strtolower((string) $b['name'])]);

        return $rows;
    }
}
