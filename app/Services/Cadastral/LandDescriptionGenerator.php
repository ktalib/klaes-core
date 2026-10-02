<?php

namespace App\Services\Cadastral;

use App\Models\Cadastral\CadastralPlanDescription;

/**
 * The land description, built from a template and the record's own data.
 *
 * Generation is a starting point, not an authority: the output is written into
 * description_body and stays editable, because a description is a legal form of
 * words and an officer will want the last say over it.
 *
 * The location token is the address builder's property format — "District, LGA,
 * State" — and never carries the plot number, which appears in its own sentence.
 */
class LandDescriptionGenerator
{
    public function __construct(private AreaCalculator $area = new AreaCalculator()) {}

    /** @return array<string, string> template key => label */
    public function templates(): array
    {
        return collect(config('cadastral_module.description_templates', []))
            ->map(fn ($t) => $t['label'] ?? '')
            ->all();
    }

    /** True when $key names a configured template, so a posted key can be trusted. */
    public function hasTemplate(?string $key): bool
    {
        return $key !== null && array_key_exists($key, $this->templates());
    }

    /** Render a template against a plan/description. */
    public function generate(CadastralPlanDescription $pd, ?string $templateKey = null): string
    {
        $key       = $templateKey ?: ($pd->template_key ?: $this->defaultKeyFor($pd));
        $templates = config('cadastral_module.description_templates', []);
        $body      = $templates[$key]['body'] ?? ($templates['standard']['body'] ?? '');

        return strtr($body, $this->tokens($pd));
    }

    /**
     * A template guess from what the record says. Agricultural land reads
     * differently from a plot in a layout, and getting the shape right saves the
     * officer more editing than any wording choice.
     */
    public function defaultKeyFor(CadastralPlanDescription $pd): string
    {
        if ($pd->land_use === 'Agricultural') {
            return 'agricultural';
        }

        if (trim((string) $pd->chart?->layout_name) !== '') {
            return 'layout';
        }

        return 'standard';
    }

    /** @return array<string, string> */
    public function tokens(CadastralPlanDescription $pd): array
    {
        $chart   = $pd->chart;
        $pillars = $pd->pillars()->get();
        $areas   = $this->area->convert($pd->area_sqm, $pd->plot_size_sqm);

        $plotNo  = $chart?->plot_no ?: $pd->prop_plot;
        $blockNo = $chart?->block_no;

        return [
            '{file_number}'     => (string) $pd->file_number,
            // Phase 6 tokens. The shipped templates do not use them yet; they are
            // here so a template edited in config can name the holder and the use.
            '{owner}'           => (string) ($pd->file_title ?: '—'),
            '{land_use}'        => (string) ($pd->land_use ?: '—'),
            '{area_acres_2dp}'  => $areas['acres'] !== null ? number_format($areas['acres'], 2) : '—',
            '{pillar_count_government}' => (string) $pillars->where('ownership', 'government')->count(),
            '{pillar_count_private}'    => (string) $pillars->where('ownership', 'private')->count(),
            '{plot_no}'         => $plotNo ?: '—',
            // Only appears when there is a block, so "Plot 12, Block C" does not
            // become "Plot 12, Block " on the many records with no block.
            '{block_clause}'    => $blockNo ? ", Block {$blockNo}" : '',
            '{block_no}'        => (string) ($blockNo ?: ''),
            '{layout_name}'     => (string) ($chart?->layout_name ?: '—'),
            // "District, LGA, State" — the address builder's property format.
            '{location}'        => $pd->property_location ?: '—',
            '{area_sqm}'        => $areas['sqm'] !== null ? number_format($areas['sqm'], 2) : '—',
            '{area_ha}'         => $areas['hectares'] !== null ? number_format($areas['hectares'], 4) : '—',
            '{area_acres}'      => $areas['acres'] !== null ? number_format($areas['acres'], 4) : '—',
            '{area_plots}'      => $areas['plots'] !== null ? number_format($areas['plots'], 2) : '—',
            '{plan_no}'         => (string) ($chart?->approved_plan_no ?: $chart?->tp_plan_no ?: '—'),
            '{boundary_north}'  => (string) ($pd->boundary_north ?: '—'),
            '{boundary_south}'  => (string) ($pd->boundary_south ?: '—'),
            '{boundary_east}'   => (string) ($pd->boundary_east ?: '—'),
            '{boundary_west}'   => (string) ($pd->boundary_west ?: '—'),
            '{pillar_count}'    => (string) $pillars->count(),
            '{pillar_list}'     => $this->pillarList($pillars),
        ];
    }

    /**
     * Validate the description against the chart it claims to describe.
     *
     * This is a consistency check between two records the Ministry already holds,
     * not a check against the ground. It cannot tell you the survey is right; it
     * can tell you the description and the chart disagree.
     *
     * @return array{status: string, notes: array<int, string>}
     */
    public function validate(CadastralPlanDescription $pd): array
    {
        $notes = [];
        $chart = $pd->chart;

        if (! $chart) {
            $notes[] = 'No chart is linked, so the description cannot be checked against one.';

            return ['status' => 'unvalidated', 'notes' => $notes];
        }

        if ($chart->file_number !== $pd->file_number) {
            $notes[] = "The linked chart is for {$chart->file_number}, not {$pd->file_number}.";
        }

        $computed = $this->area->areaSqmForChart($chart);

        if ($computed !== null && $pd->area_sqm !== null) {
            $delta = abs($computed - (float) $pd->area_sqm);
            // 1% or 1 sqm, whichever is larger — below that it is rounding, not
            // disagreement.
            $tolerance = max(1.0, $computed * 0.01);

            if ($delta > $tolerance) {
                $notes[] = sprintf(
                    'Area differs from the beacon ring by %s sqm (%s recorded, %s computed).',
                    number_format($delta, 2),
                    number_format((float) $pd->area_sqm, 2),
                    number_format($computed, 2)
                );
            }
        } elseif ($computed === null) {
            $notes[] = 'The chart has no usable beacon ring, so the area could not be recomputed.';
        }

        foreach (['north', 'south', 'east', 'west'] as $side) {
            if (trim((string) $pd->{"boundary_{$side}"}) === '') {
                $notes[] = 'The ' . $side . ' boundary is blank.';
            }
        }

        if ($pd->pillars()->count() === 0) {
            $notes[] = 'No pillars have been recorded.';
        }

        // Blank boundaries and missing pillars are incompleteness, not
        // contradiction; only a real disagreement should fail.
        $failed = collect($notes)->contains(fn ($n) => str_contains($n, 'differs') || str_contains($n, 'not ' . $pd->file_number));

        return [
            'status' => $failed ? 'failed' : ($notes === [] ? 'passed' : 'passed'),
            'notes'  => $notes,
        ];
    }

    private function pillarList($pillars): string
    {
        if ($pillars->isEmpty()) {
            return 'none recorded';
        }

        return $pillars
            ->map(function ($p) {
                $label = $p->pillar_number ?: ('#' . ($p->sort_order + 1));

                if ($p->easting !== null && $p->northing !== null) {
                    $label .= sprintf(' (E %s, N %s)', number_format($p->easting, 3), number_format($p->northing, 3));
                }

                return $label;
            })
            ->implode('; ');
    }
}
