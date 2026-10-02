<?php

namespace App\Services\Prs;

/**
 * STATIC SAMPLE DATA — UI PROTOTYPE ONLY.
 *
 * Every figure here is transcribed verbatim from the 2025 PRS progress reports
 * (Survey / Deed / Land Departments). See docs/prs-2025/ for the full
 * transcription, the arithmetic audit and the implementation plan.
 *
 * THIS CLASS IS A FIXTURE. It exists so the UI can be built and reviewed before
 * the aggregation layer exists. Most of this data is NOT yet derivable from the
 * KLAES database — that is the whole reason the backend is deferred.
 *
 * When the real aggregators land (App\Services\Prs\*Stats), they must return
 * this same section shape and this file is deleted. Nothing else should change.
 *
 * The source data contains known errors. They are reproduced faithfully rather
 * than silently corrected. The per-section caveats are NOT rendered in the UI —
 * they are recorded in docs/prs-2025/19-ui-caveat-log.md, with the underlying
 * analysis in docs/prs-2025/10-data-quality-audit.md.
 *
 * That means the screen shows several figures that are known to be wrong without
 * saying so. Read the caveat log before quoting anything off this page.
 */
class PrsSampleData
{
    /** Validated categorical palette — see docs/prs-2025/18-reporting-stack.md */
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

    /**
     * Colour follows the entity, never its rank. Two scopes, because land use and
     * gender never appear in the same chart.
     *
     * Series ORDER matters: the validator (dataviz skill) shows orange may only sit
     * beside blue / aqua / violet. Every series order below respects that.
     */
    public const LAND_USE = [
        'Residential'  => 'blue',
        'Commercial'   => 'orange',
        'Agriculture'  => 'aqua',
        'Facilities'   => 'aqua',   // never co-occurs with Agriculture
        'Industrial'   => 'yellow',
        'Industry'     => 'yellow',
        'Organisation' => 'violet',
        'Joint'        => 'magenta',
        'SIT'          => 'magenta',
        'High Density' => 'blue',
        'Low Density'  => 'violet',
        'Small Scale'  => 'magenta',
    ];

    public const GENDER = [
        'Male'                 => 'blue',
        'Female'               => 'orange',
        'Joint'                => 'violet',
        'Organisation'         => 'magenta',
        'Private Organisation' => 'magenta',
        'Governmental'         => 'aqua',
        'Not Recorded'         => 'grey',
    ];

    public const MONTHS = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December',
    ];

    public const DEPARTMENTS = [
        'survey' => ['label' => 'Survey',  'icon' => 'compass'],
        'deeds'  => ['label' => 'Deeds',   'icon' => 'file-signature'],
        'lands'  => ['label' => 'Lands',   'icon' => 'landmark'],
    ];

    public function year(): int
    {
        return 2025;
    }

    /** All sections, in report order, each decorated with derived insights. */
    public function sections(): array
    {
        return array_map([$this, 'withInsights'], $this->rawSections());
    }

    private function rawSections(): array
    {
        return [
            $this->surveyLayouts(),
            $this->deedAssignment(),
            $this->deedMortgage(),
            $this->bankRanking(),
            $this->certificateOfOccupancy(),
            $this->resettlement(),
            $this->deedRelease(),
            $this->deedDevolution(),
            $this->search(),
            $this->landConversion(),
            $this->landDirectAllocation(),
            $this->landGenderMatrix(),
            $this->ossSizePurpose(),
            $this->ossGender(),
        ];
    }

    public function section(string $key): ?array
    {
        foreach ($this->sections() as $s) {
            if ($s['key'] === $key) {
                return $s;
            }
        }

        return null;
    }

    /** Headline tiles for the top of the report. */
    public function highlights(): array
    {
        return [
            ['label' => 'Survey plots laid out',   'value' => 12933, 'dept' => 'survey', 'note' => '5 layouts',            'icon' => 'map'],
            ['label' => 'Occupancy permits',       'value' => 6047,  'dept' => 'deeds',  'note' => 'allocated',            'icon' => 'key-round'],
            ['label' => 'Direct allocation apps',  'value' => 6798,  'dept' => 'lands',  'note' => 'received',             'icon' => 'inbox'],
            ['label' => 'Conversion applications', 'value' => 6595,  'dept' => 'lands',  'note' => 'received',             'icon' => 'repeat'],
            ['label' => 'OSS applications',        'value' => 2315,  'dept' => 'lands',  'note' => 'received',             'icon' => 'building-2'],
            ['label' => 'Deeds of assignment',     'value' => 1248,  'dept' => 'deeds',  'note' => 'registered',           'icon' => 'file-signature'],
            ['label' => 'Certificates of Occupancy', 'value' => 907, 'dept' => 'deeds',  'note' => 'registered',           'icon' => 'award'],
            ['label' => 'Mortgages',               'value' => 61,    'dept' => 'deeds',  'note' => 'registered',           'icon' => 'banknote'],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Survey Department
    // ─────────────────────────────────────────────────────────────────────────

    private function surveyLayouts(): array
    {
        $rows = [
            ['Bagadawa Village',  1142, 0,    0,   1142, 6],
            ['Buk Rimi Zakara',   4425, 4230, 195, 0,    3],
            ['Dawanan',           741,  718,  23,  0,    0],
            ['Dawanan Extension', 3543, 3543, 0,   0,    5],
            ['Runkusawa',         3082, 3082, 0,   0,    4],
        ];

        return [
            'key'        => 'survey_layouts',
            'no'         => '01',
            'department' => 'survey',
            'title'      => 'Layouts Implemented',
            'subtitle'   => 'Five layouts facilitated by the Survey Department in 2025',
            'measure'    => 'Plots laid out',
            'date_basis' => 'Year of implementation',
            'headline'   => ['value' => 12933, 'unit' => 'plots', 'caption' => 'across 5 layouts'],
            'layout'     => 'category',
            'chart'      => [
                'type'   => 'stacked-bar-h',
                'labels' => array_column($rows, 0),
                'series' => $this->series(['Residential', 'Commercial', 'Facilities', 'Industry'], 'landuse', [
                    'Residential' => array_column($rows, 2),
                    'Commercial'  => array_column($rows, 3),
                    'Facilities'  => array_column($rows, 5),
                    'Industry'    => array_column($rows, 4),
                ]),
            ],
            'table'      => [
                'columns' => [
                    ['label' => 'Name of layout', 'align' => 'left'],
                    ['label' => 'Number of plots'],
                    ['label' => 'Residential'],
                    ['label' => 'Commercial'],
                    ['label' => 'Industry'],
                    ['label' => 'Facilities'],
                ],
                'rows'    => $rows,
                'total'   => ['TOTAL', 12933, 11573, 218, 1142, 18],
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Deed Department
    // ─────────────────────────────────────────────────────────────────────────

    private function deedAssignment(): array
    {
        $m = [
            'Male'         => [100, 100, 54, 70, 110, 51, 70, 70, 102, 88, 100, 101],
            'Female'       => [7, 13, 0, 2, 10, 0, 18, 16, 27, 20, 10, 10],
            'Commercial'   => [8, 4, 0, 16, 30, 11, 25, 30, 29, 16, 6, 23],
            'Residential'  => [100, 109, 54, 60, 74, 40, 82, 56, 100, 93, 98, 160],
            'Organisation' => [1, 0, 0, 4, 0, 0, 19, 0, 0, 0, 6, 2],
            'Joint'        => array_fill(0, 12, 0),
            'Total'        => [108, 113, 54, 76, 124, 51, 107, 86, 129, 109, 116, 175],
        ];

        return [
            'key'        => 'deed_assignment',
            'no'         => '02',
            'department' => 'deeds',
            'title'      => 'Deed of Assignment Registration',
            'subtitle'   => 'Monthly registrations, January – December 2025',
            'measure'    => 'Transactions registered',
            'date_basis' => 'Date of registration',
            'headline'   => ['value' => 1248, 'unit' => 'registrations', 'caption' => 'largest deed category of the year'],
            'layout'     => 'monthly',
            'chart'      => [
                'type'   => 'stacked-column',
                'labels' => self::MONTHS,
                'series' => $this->series(['Residential', 'Commercial', 'Organisation', 'Joint'], 'landuse', $m),
            ],
            'table'      => $this->monthlyTable(
                ['Male', 'Female', 'Commercial', 'Residential', 'Organisation', 'Joint', 'Total'],
                $m,
                ['Male' => 1087, 'Female' => 133, 'Commercial' => 198, 'Residential' => 1026, 'Organisation' => 32, 'Joint' => 0, 'Total' => 1248]
            ),
        ];
    }

    private function deedMortgage(): array
    {
        $m = [
            'Male'         => [1, 3, 7, 3, 4, 5, 2, 1, 1, 8, 1, 5],
            'Female'       => [0, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0],
            'Residential'  => [2, 2, 4, 1, 2, 3, 1, 2, 2, 5, 3, 2],
            'Commercial'   => [0, 0, 4, 2, 2, 0, 1, 1, 1, 4, 0, 3],
            'Organisation' => [1, 3, 2, 0, 1, 2, 0, 0, 1, 0, 2, 2],
            'Joint'        => array_fill(0, 12, 0),
            'Total'        => [3, 5, 10, 3, 5, 5, 2, 3, 4, 9, 5, 7],
        ];

        return [
            'key'        => 'deed_mortgage',
            'no'         => '03',
            'department' => 'deeds',
            'title'      => 'Deed of Mortgage Registration',
            'subtitle'   => 'Monthly registrations, January – December 2025',
            'measure'    => 'Transactions registered',
            'date_basis' => 'Date of registration',
            'headline'   => ['value' => 61, 'unit' => 'mortgages', 'caption' => 'just 5% of assignment volume'],
            'layout'     => 'monthly',
            'chart'      => [
                'type'   => 'stacked-column',
                'labels' => self::MONTHS,
                'series' => $this->series(['Residential', 'Commercial', 'Organisation', 'Joint'], 'landuse', $m),
            ],
            'table'      => $this->monthlyTable(
                ['Male', 'Female', 'Residential', 'Commercial', 'Organisation', 'Joint', 'Total'],
                $m,
                ['Male' => 43, 'Female' => 0, 'Residential' => 26, 'Commercial' => 12, 'Organisation' => 14, 'Joint' => 0, 'Total' => 61]
            ),
        ];
    }

    private function bankRanking(): array
    {
        $rows = [
            ['Jaiz', 31], ['Fidelity', 18], ['Union', 3], ['FCMB', 3],
            ['Access Bank', 2], ['Unity Bank', 2], ['UBA', 1], ['Stanbic IBTC', 1],
            ['Federal Mortgage Bank', 0],
        ];

        return [
            'key'        => 'bank_ranking',
            'no'         => '04',
            'department' => 'deeds',
            'title'      => 'Bank Ranking by Facility',
            'subtitle'   => 'Mortgagee banks, 2025',
            'measure'    => 'Mortgage facilities issued',
            'date_basis' => 'Date of registration',
            'headline'   => ['value' => 61, 'unit' => 'facilities', 'caption' => 'Jaiz + Fidelity hold 80%'],
            'layout'     => 'ranking',
            'chart'      => [
                'type'   => 'bar-h',
                'labels' => array_column($rows, 0),
                'series' => [[
                    'label' => 'Facilities',
                    'color' => self::HUE['blue'],
                    'data'  => array_column($rows, 1),
                ]],
            ],
            'table'      => [
                'columns' => [
                    ['label' => 'Bank', 'align' => 'left'],
                    ['label' => 'Facilities'],
                    ['label' => 'Share'],
                ],
                'rows'    => array_map(fn ($r) => [$r[0], $r[1], $r[1] ? round($r[1] / 61 * 100, 1) . '%' : '—'], $rows),
                'total'   => ['TOTAL', 61, '100%'],
            ],
        ];
    }

    private function certificateOfOccupancy(): array
    {
        $m = [
            'Male'        => [103, 101, 49, 40, 45, 105, 25, 88, 71, 88, 124, 33],
            'Female'      => [7, 5, 5, 2, 2, 3, 1, 3, 1, 2, 4, 0],
            'Commercial'  => [6, 5, 9, 3, 5, 9, 2, 9, 10, 11, 11, 3],
            'Residential' => [100, 95, 40, 35, 40, 88, 20, 75, 75, 75, 75, 75],
            'Agriculture' => [2, 3, 1, 1, 0, 6, 1, 1, 1, 2, 3, 1],
            'Industry'    => [2, 3, 4, 1, 0, 2, 2, 1, 1, 0, 0, 1],
            'Total'       => [110, 106, 54, 42, 47, 108, 28, 89, 72, 90, 128, 33],
        ];

        return [
            'key'        => 'cofo',
            'no'         => '05',
            'department' => 'deeds',
            'title'      => 'Certificates of Occupancy Registered',
            'subtitle'   => 'Monthly registrations, January – December 2025',
            'measure'    => 'Certificates registered',
            'date_basis' => 'Date of registration',
            'headline'   => ['value' => 907, 'unit' => 'certificates', 'caption' => 'peak November (128)'],
            'layout'     => 'monthly',
            'chart'      => [
                'type'   => 'stacked-column',
                'labels' => self::MONTHS,
                'series' => $this->series(['Residential', 'Commercial', 'Agriculture', 'Industry'], 'landuse', $m),
            ],
            'table'      => $this->monthlyTable(
                ['Male', 'Female', 'Commercial', 'Residential', 'Industry', 'Agriculture', 'Total'],
                $m,
                ['Male' => 872, 'Female' => 32, 'Commercial' => 83, 'Residential' => 793, 'Industry' => 17, 'Agriculture' => 2, 'Total' => 907]
            ),
        ];
    }

    private function resettlement(): array
    {
        $m = [
            'Male'   => [445, 390, 39, 600, 364, 472, 755, 443, 558, 485, 740, 478],
            'Female' => [39, 21, 19, 48, 13, 12, 14, 5, 20, 30, 40, 33],
            'Joint'  => array_fill(0, 12, 0),
            'Total'  => [484, 411, 42, 648, 377, 484, 769, 448, 578, 515, 780, 511],
        ];

        return [
            'key'        => 'resettlement',
            'no'         => '06',
            'department' => 'deeds',
            'title'      => 'Occupancy Permit — Direct Allocation & Resettlement',
            'subtitle'   => 'Monthly allocations, January – December 2025',
            'measure'    => 'Allocations made',
            'date_basis' => 'Date of allocation',
            'headline'   => ['value' => 6047, 'unit' => 'permits', 'caption' => 'highest-volume activity in the report'],
            'layout'     => 'monthly',
            'chart'      => [
                'type'   => 'stacked-column',
                'labels' => self::MONTHS,
                'series' => $this->series(['Male', 'Female'], 'gender', $m),
            ],
            'table'      => $this->monthlyTable(
                ['Male', 'Female', 'Joint', 'Total'],
                $m,
                ['Male' => 5769, 'Female' => 294, 'Joint' => 0, 'Total' => 6047]
            ),
        ];
    }

    private function deedRelease(): array
    {
        $m = [
            'Male'        => [4, 5, 6, 3, 1, 1, 7, 4, 8, 2, 4, 3],
            'Female'      => [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1],
            'Commercial'  => [6, 5, 9, 3, 5, 9, 2, 9, 10, 11, 11, 3],
            'Residential' => [100, 95, 40, 35, 40, 88, 20, 75, 60, 74, 110, 28],
            'Agriculture' => [2, 3, 1, 1, 0, 6, 1, 1, 1, 2, 3, 1],
            'Industry'    => [2, 3, 4, 1, 0, 2, 2, 1, 1, 0, 0, 1],
            'Total'       => [6, 11, 11, 18, 0, 2, 10, 11, 9, 5, 8, 6],
        ];

        return [
            'key'        => 'deed_release',
            'no'         => '07',
            'department' => 'deeds',
            'title'      => 'Deed of Release',
            'subtitle'   => 'Monthly registrations, January – December 2025',
            'measure'    => 'Transactions registered',
            'date_basis' => 'Date of registration',
            'headline'   => ['value' => 97, 'unit' => 'releases', 'caption' => 'from the total column only'],
            'layout'     => 'monthly',
            'chart'      => [
                'type'   => 'column',
                'labels' => self::MONTHS,
                'series' => [[
                    'label' => 'Releases registered',
                    'color' => self::HUE['blue'],
                    'data'  => $m['Total'],
                ]],
            ],
            'table'      => $this->monthlyTable(
                ['Male', 'Female', 'Commercial', 'Residential', 'Industry', 'Agriculture', 'Total'],
                $m,
                ['Male' => 48, 'Female' => 1, 'Commercial' => 83, 'Residential' => 765, 'Industry' => 34, 'Agriculture' => 24, 'Total' => 97],
                ['Commercial', 'Residential', 'Industry', 'Agriculture']
            ),
        ];
    }

    private function deedDevolution(): array
    {
        $m = [
            'Male'        => [5, 1, 0, 0, 0, 1, 0, 0, 0, 0, 1, 0],
            'Female'      => [0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 0, 1],
            'Commercial'  => [5, 1, 0, 0, 1, 0, 0, 0, 0, 0, 0, 0],
            'Residential' => [0, 0, 0, 4, 1, 0, 0, 0, 0, 1, 0, 2],
            'Agriculture' => [10, 2, 20, 10, 2, 7, 37, 5, 10, 10, 17, 33],
            'Industry'    => [0, 0, 4, 1, 0, 0, 0, 0, 0, 2, 0, 1],
            'Total'       => [20, 4, 24, 15, 4, 9, 37, 5, 10, 13, 18, 37],
        ];

        return [
            'key'        => 'deed_devolution',
            'no'         => '08',
            'department' => 'deeds',
            'title'      => 'Deed of Devolution',
            'subtitle'   => 'Monthly registrations, January – December 2025',
            'measure'    => 'Transactions registered',
            'date_basis' => 'Date of registration',
            'headline'   => ['value' => 196, 'unit' => 'devolutions', 'caption' => '83% agricultural'],
            'layout'     => 'monthly',
            'chart'      => [
                'type'   => 'stacked-column',
                'labels' => self::MONTHS,
                'series' => $this->series(['Residential', 'Commercial', 'Agriculture', 'Industry'], 'landuse', $m),
            ],
            'table'      => $this->monthlyTable(
                ['Male', 'Female', 'Commercial', 'Residential', 'Industry', 'Agriculture', 'Total'],
                $m,
                ['Male' => 7, 'Female' => 3, 'Commercial' => 7, 'Residential' => 8, 'Industry' => 8, 'Agriculture' => 163, 'Total' => 196]
            ),
        ];
    }

    private function search(): array
    {
        $m = [
            'Male'        => [9, 2, 9, 8, 2, 4, 25, 5, 10, 10, 6, 33],
            'Female'      => [0, 0, 0, 0, 0, 0, 1, 0, 0, 0, 1, 0],
            'Commercial'  => [0, 0, 2, 3, 1, 3, 10, 0, 1, 3, 4, 45],
            'Residential' => [9, 2, 6, 4, 0, 4, 27, 5, 9, 6, 13, 24],
        ];

        return [
            'key'        => 'search',
            'no'         => '09',
            'department' => 'deeds',
            'title'      => 'Official Searches',
            'subtitle'   => 'Monthly searches, January – December 2025',
            'measure'    => 'Searches conducted',
            'date_basis' => 'Date of search request',
            'headline'   => ['value' => 181, 'unit' => 'searches', 'caption' => 'on the land-use basis'],
            'layout'     => 'monthly',
            'chart'      => [
                'type'   => 'stacked-column',
                'labels' => self::MONTHS,
                'series' => $this->series(['Residential', 'Commercial'], 'landuse', $m),
            ],
            'table'      => $this->monthlyTable(
                ['Male', 'Female', 'Commercial', 'Residential'],
                $m,
                ['Male' => 123, 'Female' => 2, 'Commercial' => 72, 'Residential' => 109]
            ),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Land Department
    // ─────────────────────────────────────────────────────────────────────────

    private function landConversion(): array
    {
        $cats = ['Residential' => 5982, 'Commercial' => 234, 'Agriculture' => 311, 'Industrial' => 68];

        return [
            'key'        => 'land_conversion',
            'no'         => '12',
            'department' => 'lands',
            'title'      => 'Applications for Conversion',
            'subtitle'   => 'Annual totals, 2025',
            'measure'    => 'Applications received',
            'date_basis' => 'Date application received',
            'headline'   => ['value' => 6595, 'unit' => 'applications', 'caption' => '90.7% residential'],
            'layout'     => 'category',
            'chart'      => [
                'type'   => 'bar-h',
                'labels' => array_keys($cats),
                'series' => [[
                    'label'  => 'Applications',
                    'color'  => self::HUE['blue'],
                    'data'   => array_values($cats),
                ]],
            ],
            'chart_secondary' => [
                'type'    => 'bar-h',
                'labels'  => ['Commercial', 'Agriculture', 'Industrial'],
                'series'  => [[
                    'label' => 'Applications',
                    'color' => self::HUE['blue'],
                    'data'  => [234, 311, 68],
                ]],
                'caption' => 'Minor categories at their own scale — Residential (90.7%) excluded.',
            ],
            'table'      => [
                'columns' => [['label' => 'Land use', 'align' => 'left'], ['label' => 'Applications'], ['label' => 'Share']],
                'rows'    => array_map(fn ($k) => [$k, $cats[$k], round($cats[$k] / 6595 * 100, 1) . '%'], array_keys($cats)),
                'total'   => ['TOTAL', 6595, '100%'],
            ],
        ];
    }

    private function landDirectAllocation(): array
    {
        $cats = ['Residential' => 6021, 'Commercial' => 701, 'Agriculture' => 58, 'Industrial' => 18, 'SIT' => 0];

        return [
            'key'        => 'land_direct_allocation',
            'no'         => '13',
            'department' => 'lands',
            'title'      => 'Applications for Direct Allocation',
            'subtitle'   => 'Annual totals, 2025',
            'measure'    => 'Applications received',
            'date_basis' => 'Date application received',
            'headline'   => ['value' => 6798, 'unit' => 'applications', 'caption' => '3× the commercial share of conversion'],
            'layout'     => 'category',
            'chart'      => [
                'type'   => 'bar-h',
                'labels' => array_keys($cats),
                'series' => [[
                    'label' => 'Applications',
                    'color' => self::HUE['blue'],
                    'data'  => array_values($cats),
                ]],
            ],
            'chart_secondary' => [
                'type'    => 'bar-h',
                'labels'  => ['Commercial', 'Agriculture', 'Industrial', 'SIT'],
                'series'  => [[
                    'label' => 'Applications',
                    'color' => self::HUE['blue'],
                    'data'  => [701, 58, 18, 0],
                ]],
                'caption' => 'Minor categories at their own scale — Residential (88.6%) excluded.',
            ],
            'table'      => [
                'columns' => [['label' => 'Land use', 'align' => 'left'], ['label' => 'Applications'], ['label' => 'Share']],
                'rows'    => array_map(fn ($k) => [$k, $cats[$k], $cats[$k] ? round($cats[$k] / 6798 * 100, 1) . '%' : '—'], array_keys($cats)),
                'total'   => ['TOTAL', 6798, '100%'],
            ],
        ];
    }

    private function landGenderMatrix(): array
    {
        $rows = [
            ['Direct Allocation', 5570, 448, 8, 0, 0],
            ['Direct Conversion', 701, 55, 3, 0, 0],
            ['Direct Industrial', 58, 0, 0, 0, 0],
            ['Direct Agriculture', 15, 1, 2, 0, 0],
        ];

        return [
            'key'        => 'land_gender_matrix',
            'no'         => '14',
            'department' => 'lands',
            'title'      => 'Direct Government Allocation by Gender',
            'subtitle'   => 'By stream, 2025',
            'measure'    => 'Applications received',
            'date_basis' => 'Date application received',
            'headline'   => ['value' => 6861, 'unit' => 'applications', 'caption' => 'sum of all four streams'],
            'layout'     => 'matrix',
            'chart'      => [
                'type'   => 'stacked-bar-h',
                'labels' => array_column($rows, 0),
                'series' => $this->series(['Male', 'Female', 'Joint'], 'gender', [
                    'Male'   => array_column($rows, 1),
                    'Female' => array_column($rows, 2),
                    'Joint'  => array_column($rows, 3),
                ]),
            ],
            'table'      => [
                'columns' => [
                    ['label' => 'Base', 'align' => 'left'],
                    ['label' => 'Male'], ['label' => 'Female'], ['label' => 'Joint'],
                    ['label' => 'Private Orga'], ['label' => 'Governmental'], ['label' => 'Row total'],
                ],
                'rows'    => array_map(fn ($r) => array_merge($r, [$r[1] + $r[2] + $r[3] + $r[4] + $r[5]]), $rows),
                'total'   => ['TOTAL', 6344, 504, 13, 0, 0, 6861],
            ],
        ];
    }

    private function ossSizePurpose(): array
    {
        $m = [
            'High Density' => [97, 200, 206, 175, 145, 228, 167, 239, 207, 170, 206, 188],
            'Low Density'  => [5, 2, 0, 2, 1, 0, 2, 2, 0, 5, 0, 2],
            'Commercial'   => [4, 3, 3, 4, 3, 0, 2, 4, 2, 5, 2, 4],
            'Industrial'   => [1, 0, 0, 0, 4, 0, 2, 0, 1, 3, 0, 2],
            'Small Scale'  => [3, 1, 2, 0, 1, 0, 1, 2, 0, 4, 0, 3],
            'Total'        => [110, 206, 211, 181, 154, 228, 174, 247, 210, 187, 208, 199],
        ];

        return [
            'key'        => 'oss_size_purpose',
            'no'         => '15',
            'department' => 'lands',
            'title'      => 'OSS Applications by Size and Purpose',
            'subtitle'   => 'Monthly applications, January – December 2025',
            'measure'    => 'Applications received',
            'date_basis' => 'Date application received',
            'headline'   => ['value' => 2315, 'unit' => 'applications', 'caption' => '96.2% high density'],
            'layout'     => 'monthly',
            'chart'      => [
                'type'   => 'stacked-column',
                'labels' => self::MONTHS,
                'series' => $this->series(['High Density', 'Commercial', 'Low Density', 'Small Scale', 'Industrial'], 'landuse', $m),
            ],
            // High density is 96.2% of the total, so the other four categories are
            // invisible in the stack above. Same data, minor categories only, own scale.
            'chart_secondary' => [
                'type'    => 'stacked-column',
                'labels'  => self::MONTHS,
                'series'  => $this->series(['Commercial', 'Low Density', 'Small Scale', 'Industrial'], 'landuse', $m),
                'caption' => 'Minor categories at their own scale — High Density (96.2%) excluded, otherwise these four are invisible.',
            ],
            'table'      => $this->monthlyTable(
                ['High Density', 'Low Density', 'Commercial', 'Industrial', 'Small Scale', 'Total'],
                $m,
                ['High Density' => 2228, 'Low Density' => 21, 'Commercial' => 36, 'Industrial' => 13, 'Small Scale' => 17, 'Total' => 2315]
            ),
        ];
    }

    private function ossGender(): array
    {
        $m = [
            'Male'   => [102, 202, 204, 180, 149, 225, 168, 238, 203, 180, 201, 194],
            'Female' => [8, 4, 7, 1, 5, 3, 6, 9, 7, 7, 7, 5],
            'Total'  => [110, 206, 211, 181, 154, 228, 174, 247, 210, 187, 208, 199],
        ];

        return [
            'key'        => 'oss_gender',
            'no'         => '16',
            'department' => 'lands',
            'title'      => 'OSS Applications by Gender',
            'subtitle'   => 'Monthly applications, January – December 2025',
            'measure'    => 'Applications received',
            'date_basis' => 'Date application received',
            'headline'   => ['value' => 2315, 'unit' => 'applications', 'caption' => 'female share just 3.0%'],
            'layout'     => 'monthly',
            'chart'      => [
                'type'   => 'stacked-column',
                'labels' => self::MONTHS,
                'series' => $this->series(['Male', 'Female'], 'gender', $m),
            ],
            'table'      => $this->monthlyTable(
                ['Male', 'Female', 'Total'],
                $m,
                ['Male' => 2246, 'Female' => 69, 'Total' => 2315]
            ),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Derive the small facts shown beside the headline figure: the dominant
     * category and its share, and (for monthly sections) the peak period.
     *
     * Computed from the chart series rather than hand-written, so they cannot
     * drift from what the chart actually plots.
     */
    private function withInsights(array $s): array
    {
        $series  = $s['chart']['series'] ?? [];
        $labels  = $s['chart']['labels'] ?? [];
        $monthly = count($labels) === 12 && ($s['layout'] ?? '') === 'monthly';

        $insights = [];

        // Dominant category. Multi-series: the series with the largest sum.
        // Single-series ranking/category chart: the largest label.
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
        } elseif (count($series) === 1 && ! $monthly && $labels) {
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

        // Peak and trough period, summed across all plotted series.
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

    /**
     * Build chart series in an explicit order, coloured by entity.
     * Order is not cosmetic — see the adjacency rule in the class docblock.
     */
    private function series(array $order, string $scope, array $data): array
    {
        $map = $scope === 'gender' ? self::GENDER : self::LAND_USE;

        return array_map(fn ($name) => [
            'label' => $name,
            'color' => self::HUE[$map[$name] ?? 'grey'],
            'data'  => array_values($data[$name] ?? array_fill(0, 12, 0)),
        ], $order);
    }

    /**
     * Standard 12-month table: a Month column, one column per measure, TOTAL row.
     *
     * @param array $suspect column labels to render as struck-through/greyed
     */
    private function monthlyTable(array $columns, array $data, array $totals, array $suspect = []): array
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
            $total[] = $totals[$c] ?? null;
        }

        return [
            'columns' => array_merge(
                [['label' => 'Month', 'align' => 'left']],
                array_map(fn ($c) => [
                    'label'   => $c,
                    'suspect' => in_array($c, $suspect, true),
                ], $columns)
            ),
            'rows'    => $rows,
            'total'   => $total,
        ];
    }
}
