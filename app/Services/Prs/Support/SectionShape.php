<?php

namespace App\Services\Prs\Support;

/**
 * The section DTO the PRS blades consume, and the chart rules that go with it.
 *
 * Lifted from PrsSampleData so the live aggregator emits byte-identical structure:
 * resources/views/prs/annual_report/partials/*.blade.php must not change when the
 * fixture is swapped out. Chart rules per docs/prs-2025/10-data-quality-audit.md —
 * months only on the category axis, totals never a series, colour follows the
 * entity rather than its rank.
 */
trait SectionShape
{
    /** Validated categorical palette — docs/prs-2025/18-reporting-stack.md */
    public const HUE = [
        'blue'    => '#2a78d6',
        'orange'  => '#eb6834',
        'aqua'    => '#1baf7a',
        'yellow'  => '#eda100',
        'magenta' => '#e87ba4',
        'green'   => '#008300',
        'violet'  => '#4a3aa7',
        'grey'    => '#898781',
    ];

    public const LAND_USE_HUE = [
        'Residential'   => 'blue',
        'Commercial'    => 'orange',
        'Agriculture'   => 'aqua',
        'Industrial'    => 'yellow',
        'Institutional' => 'violet',
        'Mixed'         => 'magenta',
        'Uncategorised' => 'grey',
    ];

    /** The client's four values, plus the bucket for rows with no gender yet. */
    public const GENDER_HUE = [
        'Male'         => 'blue',
        'Female'       => 'orange',
        'Corporate'    => 'violet',
        'Joint'        => 'magenta',
        'Not Recorded' => 'grey',
    ];

    public const MONTHS = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December',
    ];

    /** Build chart series in an explicit order, coloured by entity, never by rank. */
    protected function series(array $order, string $scope, array $data, int $len = 12): array
    {
        $map = $scope === 'gender' ? self::GENDER_HUE : self::LAND_USE_HUE;

        return array_map(fn ($name) => [
            'label' => $name,
            'color' => self::HUE[$map[$name] ?? 'grey'],
            'data'  => array_values($data[$name] ?? array_fill(0, $len, 0)),
        ], $order);
    }

    /** Standard 12-month table: Month column, one column per measure, TOTAL row. */
    protected function monthlyTable(array $columns, array $data, array $totals): array
    {
        $rows = [];

        foreach (self::MONTHS as $i => $label) {
            $row = [$label];
            foreach ($columns as $c) {
                $row[] = $data[$c][$i] ?? 0;
            }
            $rows[] = $row;
        }

        $total = ['TOTAL'];
        foreach ($columns as $c) {
            $total[] = $totals[$c] ?? array_sum($data[$c] ?? []);
        }

        return [
            'columns' => array_merge(
                [['label' => 'Month', 'align' => 'left']],
                array_map(fn ($c) => ['label' => $c, 'suspect' => false], $columns)
            ),
            'rows'  => $rows,
            'total' => $total,
        ];
    }

    /**
     * Decorate a section with the derived insights the hero card renders:
     * dominant category, peak and trough month.
     */
    protected function withInsights(array $s): array
    {
        $series  = $s['chart']['series'] ?? [];
        $labels  = $s['chart']['labels'] ?? [];
        $monthly = count($labels) === 12 && ($s['layout'] ?? '') === 'monthly';

        $insights = [];

        if (count($series) > 1) {
            $sums  = array_map(fn ($x) => array_sum($x['data']), $series);
            $grand = array_sum($sums);
            $i     = array_search(max($sums), $sums, true);

            if ($grand > 0 && $i !== false) {
                $insights['top'] = [
                    'label' => $series[$i]['label'],
                    'value' => $sums[$i],
                    'share' => round($sums[$i] / $grand * 100, 1),
                    'color' => $series[$i]['color'],
                ];
            }
        } elseif (count($series) === 1 && !$monthly && $labels) {
            $data  = $series[0]['data'];
            $grand = array_sum($data);
            $i     = array_search(max($data), $data, true);

            if ($grand > 0 && $i !== false) {
                $insights['top'] = [
                    'label' => $labels[$i],
                    'value' => $data[$i],
                    'share' => round($data[$i] / $grand * 100, 1),
                    'color' => $series[0]['color'],
                ];
            }
        }

        if ($monthly && $series) {
            $totals = [];
            foreach ($labels as $i => $label) {
                $totals[$i] = array_sum(array_map(fn ($x) => $x['data'][$i] ?? 0, $series));
            }

            if (array_sum($totals) > 0) {
                $hi = array_search(max($totals), $totals, true);
                $lo = array_search(min($totals), $totals, true);

                $insights['peak']   = ['label' => $labels[$hi], 'value' => $totals[$hi]];
                $insights['trough'] = ['label' => $labels[$lo], 'value' => $totals[$lo]];
            }
        }

        $s['insights'] = $insights;

        return $s;
    }

    /** Empty 12-slot series, so a month with no rows renders as 0 rather than a gap. */
    protected function blankMonths(): array
    {
        return array_fill(0, 12, 0);
    }
}
