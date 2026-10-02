<?php

namespace App\Services\Prs;

use App\Services\Prs\Support\InstrumentTypeNormalizer;
use App\Services\Prs\Support\LandUseNormalizer;
use App\Services\Prs\Support\SectionShape;
use Illuminate\Support\Facades\DB;

/**
 * The live PRS Annual Report. Replaces PrsSampleData.
 *
 * Emits exactly the section shape the blades already consume, so
 * resources/views/prs/annual_report/** did not change when the fixture was
 * swapped out. PrsSampleData is retained as the 2025 reference transcription;
 * it is no longer wired to the page.
 *
 * Section 01 (Survey layouts) is absent because no survey_layouts table exists —
 * that is D6, a data-entry programme rather than a query, and inventing the
 * section would be worse than omitting it. Sections with no rows in the selected
 * year are omitted rather than rendered as zero, so the section count varies by
 * year and by source.
 *
 * SOURCE, 2026-08-05. Every Deeds Department section — including Certificates of
 * Occupancy, which had a service of its own — now reads `deed_registrations`
 * through DeedRegistrationStats, dated by `deeds_date`. The `pra`,
 * `file_history_staging` and `CofO_staging` back-capture tables are no longer
 * counted: they hold instruments keyed in from paper, not registrations the
 * department performed in the year. See DeedRegistrationStats for the full note.
 *
 * THE STRUCTURAL RULES, all from docs/prs-2025/10-data-quality-audit.md, are
 * enforced here rather than left to whoever writes the next section:
 *
 *   - Months only on the category axis. The annual figure is a hero number, never
 *     a bar and never a stacked series. The source charts did both and read double.
 *   - Gender and land use are separate cuts of ONE row set, so both sum to the
 *     section total. This is what the source could never do.
 *   - Zero months render as 0, not as a gap.
 *   - Every section states its measure and its date basis on its face.
 */
class PrsReportAggregator
{
    use SectionShape;

    /**
     * The departments the report covers.
     *
     * Sectional Titling is one of them, not a corner of Deeds — it is a department
     * in its own right in the `departments` table, alongside Land, Survey and
     * Deeds. Its three sections span what would otherwise be two departments
     * (registration sits with Deeds, commissioning with Lands), which is exactly
     * why it needs its own tab rather than being split across theirs.
     */
    public const DEPARTMENTS = [
        'survey' => ['label' => 'Survey', 'icon' => 'compass'],
        'deeds'  => ['label' => 'Deeds',  'icon' => 'file-signature'],
        'lands'  => ['label' => 'Lands',  'icon' => 'landmark'],
        'st'     => ['label' => 'Sectional Titling', 'icon' => 'layers'],
        // KANGIS and SLTR are registries rather than processing departments, but
        // they earn tabs on the same reasoning ST did: their measure (files
        // indexed) belongs to neither Lands nor Deeds, and filing them under
        // either would put an indexing count beside registration counts it must
        // never be added to.
        'kangis' => ['label' => 'KANGIS', 'icon' => 'archive'],
        'sltr'   => ['label' => 'SLTR',   'icon' => 'map-pinned'],
    ];

    /** Gender series order, fixed. Not Recorded last so it reads as the remainder. */
    private const GENDER_ORDER = ['Male', 'Female', 'Corporate', 'Joint', 'Not Recorded'];

    private ?int $year = null;

    /** Memo for availableYears(); survives forYear()'s clone, which is intended. */
    private ?array $years = null;

    public function __construct(
        private DeedRegistrationStats $deeds,
        private LandFileStats $land,
        private SearchStats $searches,
        private OssStats $oss,
        private StStats $st,
        private RegistryIndexingStats $registries,
    ) {
    }

    public function forYear(int $year): self
    {
        $clone = clone $this;
        $clone->year = $year;

        return $clone;
    }

    public function year(): int
    {
        return $this->year ??= $this->defaultYear();
    }

    /**
     * Years the data can actually support, most active first.
     *
     * Data-driven on purpose. KLAES holds almost no 2025 activity — commissioning,
     * OSS and search all went live in 2026 — so a hardcoded 2025 selector would
     * print a 74-100% shortfall against a report PRS already published.
     * See docs/prs-2025/20-live-data-implementation.md §5.
     */
    public function availableYears(): array
    {
        // Memoised: the controller asks for the selector list, then year() asks
        // again through defaultYear(), and the tally costs five year-scans.
        if ($this->years !== null) {
            return $this->years;
        }

        $tally = [];

        foreach ([
            $this->deeds->availableYears(),
            $this->land->availableYears(),
            $this->searches->availableYears(),
            $this->oss->availableYears(),
            $this->st->availableYears(),
            $this->registries->availableYears(),
        ] as $set) {
            foreach ($set as $y => $n) {
                $tally[$y] = ($tally[$y] ?? 0) + $n;
            }
        }

        krsort($tally);

        return $this->years = $tally;
    }

    private function defaultYear(): int
    {
        $years = $this->availableYears();

        if ($years === []) {
            return (int) date('Y');
        }

        // The most recent year carrying a meaningful volume, not simply the newest.
        $material = array_filter($years, fn ($n) => $n >= 50);

        return (int) array_key_first($material ?: $years);
    }

    // ── Sections ────────────────────────────────────────────────────────────

    public function sections(): array
    {
        $sections = array_filter([
            $this->deedSection('deed_assignment', '02', InstrumentTypeNormalizer::ASSIGNMENT,
                'Deed of Assignment Registration', 'registrations'),
            $this->deedSection('deed_mortgage', '03', InstrumentTypeNormalizer::MORTGAGE,
                'Deed of Mortgage Registration', 'mortgages'),
            $this->bankRankingSection(),
            // CofO now comes from deed_registrations like every other deed section
            // (client decision, 2026-08-05), so it no longer needs a service of its
            // own — CofO_staging was a back-capture table, not the register.
            $this->deedSection('cofo', '05', InstrumentTypeNormalizer::COFO,
                'Certificates of Occupancy Registered', 'certificates'),
            $this->occupancyPermitSection(),
            // OPs are the single largest thing the register takes in — 8,947 of the
            // 9,502 registrations on the 2026 deeds date.
            $this->deedSection('op_captured', '10', InstrumentTypeNormalizer::OP,
                'Occupancy Permits Registered', 'permits'),
            $this->deedSection('roo_captured', '11', InstrumentTypeNormalizer::ROO,
                'Rights of Occupancy Captured', 'titles'),
            $this->deedSection('deed_release', '07', InstrumentTypeNormalizer::RELEASE,
                'Deed of Release', 'releases'),
            $this->deedSection('deed_devolution', '08', InstrumentTypeNormalizer::DEVOLUTION,
                'Deed of Devolution', 'devolutions'),
            $this->searchSection(),
            $this->landStreamSection('land_conversion', '12', LandFileStats::CONVERSION,
                'Applications for Conversion', 'applications'),
            $this->landStreamSection('land_direct_allocation', '13', LandFileStats::DIRECT_ALLOCATION,
                'Applications for Direct Allocation', 'applications'),
            $this->genderMatrixSection(),
            $this->ossSection('oss_size_purpose', '15', 'landuse',
                'OSS Applications by Purpose', 'applications'),
            $this->ossSection('oss_gender', '16', 'gender',
                'OSS Applications by Gender', 'applications'),
            // Sectional Titling, added 2026-08-05. Three sections because ST is
            // three distinct measures — a block fragmenting, its units changing
            // hands, and the file numbers issued for both. Adding them together
            // would double-count the same property.
            $this->stRegisteredSection('st_fragmentation', '20',
                InstrumentTypeNormalizer::ST_FRAGMENTATION,
                'ST Fragmentation Registered', 'fragmentations'),
            $this->stRegisteredSection('st_transfer', '21',
                InstrumentTypeNormalizer::ST_TRANSFER,
                'ST Unit Transfers Registered', 'transfers'),
            $this->stCommissionedSection(),
            // KANGIS and SLTR, added 2026-08-17. One section each: both registries
            // index files and neither commissions any, so there is only one measure
            // to report. See RegistryIndexingStats.
            $this->indexingSection('kangis_indexed', '23', 'kangis',
                RegistryIndexingStats::KANGIS, 'KANGIS Files Indexed', 'gender'),
            $this->indexingSection('sltr_indexed', '24', 'sltr',
                RegistryIndexingStats::SLTR, 'SLTR Files Indexed', 'lga'),
        ]);

        return array_values(array_map([$this, 'withInsights'], $sections));
    }

    /** Headline tiles, derived from the same queries that build the sections. */
    public function highlights(): array
    {
        $year = $this->year();

        $tiles = [
            ['label' => 'Deeds of assignment', 'value' => $this->deeds->monthly(InstrumentTypeNormalizer::ASSIGNMENT, $year)['total'],
             'dept' => 'deeds', 'note' => 'registered', 'icon' => 'file-signature'],
            ['label' => 'Certificates of Occupancy', 'value' => $this->deeds->monthly(InstrumentTypeNormalizer::COFO, $year)['total'],
             'dept' => 'deeds', 'note' => 'registered', 'icon' => 'award'],
            ['label' => 'Mortgages', 'value' => $this->deeds->monthly(InstrumentTypeNormalizer::MORTGAGE, $year)['total'],
             'dept' => 'deeds', 'note' => 'registered', 'icon' => 'banknote'],
            // All registered OPs, not just the ones commissioned as new files. The
            // tile used to read mls_file_no (982 files commissioned for an OP
            // allocation), which sat on the same page as section 10's OP
            // registrations under a nearly identical name — two correct numbers
            // an order of magnitude apart. The tile now counts every OP.
            ['label' => 'Occupancy permits', 'value' => $this->deeds->monthly(InstrumentTypeNormalizer::OP, $year)['total'],
             'dept' => 'deeds', 'note' => 'registered', 'icon' => 'key-round'],
            ['label' => 'Conversion applications', 'value' => $this->land->monthly(LandFileStats::CONVERSION, $year)['total'],
             'dept' => 'lands', 'note' => 'commissioned', 'icon' => 'repeat'],
            ['label' => 'Direct allocation apps', 'value' => $this->land->monthly(LandFileStats::DIRECT_ALLOCATION, $year)['total'],
             'dept' => 'lands', 'note' => 'commissioned', 'icon' => 'inbox'],
            ['label' => 'OSS applications', 'value' => $this->oss->monthly($year)['total'],
             'dept' => 'lands', 'note' => 'received', 'icon' => 'building-2'],
            ['label' => 'Official searches', 'value' => $this->searches->monthly($year)['total'],
             'dept' => 'deeds', 'note' => 'requested', 'icon' => 'search'],
            // The commissioning side, matching the tile's "files" framing. The
            // registration counts live in sections 20 and 21 and are a different
            // measure — see StStats.
            ['label' => 'ST files commissioned', 'value' => $this->st->commissioned($year)['total'],
             'dept' => 'st', 'note' => 'issued', 'icon' => 'layers'],
            ['label' => 'ST registrations', 'value' => $this->st->registered(InstrumentTypeNormalizer::ST_FRAGMENTATION, $year)['total']
                                                       + $this->st->registered(InstrumentTypeNormalizer::ST_TRANSFER, $year)['total'],
             'dept' => 'st', 'note' => 'registered', 'icon' => 'layers-2'],
            // "indexed", never "commissioned": neither registry issues file
            // numbers through KLAES yet, and the dashboard's own KANGIS and SLTR
            // commissioned counts are hardcoded zeros for the same reason.
            ['label' => 'KANGIS files indexed', 'value' => $this->registries->monthly(RegistryIndexingStats::KANGIS, $year)['total'],
             'dept' => 'kangis', 'note' => 'indexed', 'icon' => 'archive'],
            ['label' => 'SLTR files indexed', 'value' => $this->registries->monthly(RegistryIndexingStats::SLTR, $year)['total'],
             'dept' => 'sltr', 'note' => 'indexed', 'icon' => 'map-pinned'],
        ];

        return array_values(array_filter($tiles, fn ($t) => $t['value'] > 0));
    }

    // ── Section builders ────────────────────────────────────────────────────

    private function deedSection(string $key, string $no, string $group, string $title, string $unit): ?array
    {
        $d = $this->deeds->monthly($group, $this->year());

        if ($d['total'] === 0) {
            return null;
        }

        return [
            'key'        => $key,
            'no'         => $no,
            'department' => 'deeds',
            'title'      => $title,
            'subtitle'   => 'Entered in the deeds register, January – December ' . $this->year(),
            'measure'    => 'Transactions registered',
            'date_basis' => 'Deeds date',
            'headline'   => [
                'value'   => $d['total'],
                'unit'    => $unit,
                'caption' => $this->genderCaption($d['coverage'])
                             . ($d['coverage']['undated'] > 0
                                ? ' · ' . number_format($d['coverage']['undated']) . ' rows carry no deeds date'
                                : ''),
            ],
            'layout'     => 'monthly',
            'chart'      => [
                'type'   => 'stacked-column',
                'labels' => self::MONTHS,
                'series' => $this->landUseSeries($d['landuse']),
            ],
            'chart_secondary' => $this->genderPanel($d['gender'], $d['total']),
            'table'      => $this->cutTable($d),
            'table_secondary' => $this->genderTable($d),
        ];
    }

    private function occupancyPermitSection(): ?array
    {
        $d = $this->land->monthly(LandFileStats::OCCUPANCY_PERMIT, $this->year());

        if ($d['total'] === 0) {
            return null;
        }

        return [
            'key'        => 'occupancy_permit',
            'no'         => '06',
            'department' => 'deeds',
            // Named "OP Files Commissioned" rather than "Occupancy Permit" so it
            // cannot be read as the same measure as section 10. This counts new
            // files opened for an OP allocation (982); section 10 counts OP
            // instruments captured into the register (46,422).
            'title'      => 'OP Files Commissioned — Direct Allocation & Resettlement',
            'subtitle'   => 'New files opened for an OP allocation, January – December ' . $this->year(),
            'measure'    => 'Files commissioned for allocation',
            'date_basis' => 'Date captured',
            'headline'   => [
                'value'   => $d['total'],
                'unit'    => 'permits',
                'caption' => $this->streamCaption($d['streams']),
            ],
            'layout'     => 'monthly',
            'chart'      => [
                'type'   => 'stacked-column',
                'labels' => self::MONTHS,
                'series' => $this->namedSeries($d['streams'], 'landuse'),
            ],
            'chart_secondary' => $this->genderPanel($d['gender'], $d['total']),
            'table'      => $this->cutTable($d),
            'table_secondary' => $this->genderTable($d),
        ];
    }

    private function landStreamSection(string $key, string $no, array $sources, string $title, string $unit): ?array
    {
        $d = $this->land->monthly($sources, $this->year());

        if ($d['total'] === 0) {
            return null;
        }

        return [
            'key'        => $key,
            'no'         => $no,
            'department' => 'lands',
            'title'      => $title,
            'subtitle'   => 'Files commissioned, January – December ' . $this->year(),
            'measure'    => 'Applications commissioned',
            'date_basis' => 'Date captured',
            'headline'   => [
                'value'   => $d['total'],
                'unit'    => $unit,
                'caption' => $this->genderCaption($d['coverage']),
            ],
            'layout'     => 'monthly',
            'chart'      => [
                'type'   => 'stacked-column',
                'labels' => self::MONTHS,
                'series' => $this->landUseSeries($d['landuse']),
            ],
            'chart_secondary' => $this->genderPanel($d['gender'], $d['total']),
            'table'      => $this->cutTable($d),
            'table_secondary' => $this->genderTable($d),
        ];
    }

    /**
     * Section 14. The source version mixed application streams with land uses in
     * one column and transposed two rows; cutting one query two ways makes that
     * impossible, and the rows foot to sections 12 and 13 by construction.
     */
    private function genderMatrixSection(): ?array
    {
        $m = $this->land->streamByGender($this->year());

        if ($m['rows'] === []) {
            return null;
        }

        $order   = array_values(array_filter(self::GENDER_ORDER, fn ($g) => in_array($g, $m['genders'], true)));
        $labels  = array_keys($m['rows']);
        $data    = [];
        $total   = 0;

        foreach ($order as $g) {
            $data[$g] = [];
            foreach ($labels as $stream) {
                $v = $m['rows'][$stream][$g] ?? 0;
                $data[$g][] = $v;
                $total += $v;
            }
        }

        $rows = [];
        foreach ($labels as $i => $stream) {
            $row = [$stream];
            foreach ($order as $g) {
                $row[] = $data[$g][$i];
            }
            $row[] = array_sum(array_map(fn ($g) => $data[$g][$i], $order));
            $rows[] = $row;
        }

        $totalRow = ['TOTAL'];
        foreach ($order as $g) {
            $totalRow[] = array_sum($data[$g]);
        }
        $totalRow[] = $total;

        return [
            'key'        => 'land_gender_matrix',
            'no'         => '14',
            'department' => 'lands',
            'title'      => 'Allocation Stream by Gender',
            'subtitle'   => 'Every commissioned file of ' . $this->year() . ', cut by stream and gender',
            'measure'    => 'Files commissioned',
            'date_basis' => 'Date captured',
            'headline'   => [
                'value'   => $total,
                'unit'    => 'files',
                'caption' => 'one query, two cuts — rows foot to sections 12 and 13',
            ],
            'layout'     => 'category',
            'chart'      => [
                'type'   => 'stacked-bar-h',
                'labels' => $labels,
                'series' => $this->series($order, 'gender', $data, count($labels)),
            ],
            'table'      => [
                'columns' => array_merge(
                    [['label' => 'Allocation stream', 'align' => 'left']],
                    array_map(fn ($g) => ['label' => $g, 'suspect' => false], $order),
                    [['label' => 'Total', 'suspect' => false]]
                ),
                'rows'  => $rows,
                'total' => $totalRow,
            ],
        ];
    }

    /**
     * Sections 20 and 21 — ST instruments entered in the deeds register.
     *
     * Shaped like a deed section, but sourced through StStats so land use and
     * gender come from st_file_numbers. Routed through DeedRegistrationStats these
     * would be 100% Uncategorised: an ST file number carries no land-use prefix.
     */
    private function stRegisteredSection(string $key, string $no, string $group, string $title, string $unit): ?array
    {
        $d = $this->st->registered($group, $this->year());

        if ($d['total'] === 0) {
            return null;
        }

        return [
            'key'        => $key,
            'no'         => $no,
            'department' => 'st',
            'title'      => $title,
            'subtitle'   => 'Entered in the deeds register, January – December ' . $this->year(),
            'measure'    => 'Sectional instruments registered',
            'date_basis' => 'Deeds date',
            'headline'   => [
                'value'   => $d['total'],
                'unit'    => $unit,
                'caption' => $this->genderCaption($d['coverage'])
                             . ' · land use and party taken from st_file_numbers, not the file number',
            ],
            'layout'     => 'monthly',
            'chart'      => [
                'type'   => 'stacked-column',
                'labels' => self::MONTHS,
                'series' => $this->landUseSeries($d['landuse']),
            ],
            'chart_secondary' => $this->genderPanel($d['gender'], $d['total']),
            'table'      => $this->cutTable($d),
            'table_secondary' => $this->genderTable($d),
        ];
    }

    /**
     * Section 22 — ST file numbers issued.
     *
     * The commissioning side, and NOT the same measure as 20 and 21: one block is
     * commissioned as a PRIMARY file and then fragmented into unit files, so the
     * file count exceeds the registration count by construction. Charted by file
     * type, because blocks-to-units is the shape of the ST programme.
     */
    private function stCommissionedSection(): ?array
    {
        $d = $this->st->commissioned($this->year());

        if ($d['total'] === 0) {
            return null;
        }

        return [
            'key'        => 'st_commissioned',
            'no'         => '22',
            'department' => 'st',
            'title'      => 'ST Files Commissioned',
            'subtitle'   => 'Sectional title file numbers issued, January – December ' . $this->year(),
            'measure'    => 'ST file numbers issued',
            'date_basis' => 'Date commissioned',
            'headline'   => [
                'value'   => $d['total'],
                'unit'    => 'files',
                'caption' => $this->streamCaption($d['types'])
                             . ' · ' . $this->genderCaption($d['coverage'])
                             . ($d['coverage']['undated'] > 0
                                ? ' · ' . number_format($d['coverage']['undated']) . ' carry no commissioning date'
                                : ''),
            ],
            'layout'     => 'monthly',
            'chart'      => [
                'type'   => 'stacked-column',
                'labels' => self::MONTHS,
                'series' => $this->namedSeries($d['types'], 'landuse'),
            ],
            'chart_secondary' => $this->genderPanel($d['gender'], $d['total']),
            'table'      => $this->cutTable($d),
            'table_secondary' => $this->genderTable($d),
        ];
    }

    /**
     * Sections 23 and 24 — files indexed into the KANGIS and SLTR registries.
     *
     * `$secondary` picks the panel beside the land-use chart, and differs by
     * registry on purpose. KANGIS carries gender on 46% of rows, so it charts
     * gender like every other section. SLTR carries it on 3.3%, so it charts LGA
     * instead — a gender panel there would be a single grey bar, and systematic
     * titling is an LGA-by-LGA programme anyway. Both still emit the gender table,
     * so the coverage gap is stated rather than hidden.
     *
     * @param string[] $registries
     */
    private function indexingSection(
        string $key,
        string $no,
        string $department,
        array $registries,
        string $title,
        string $secondary
    ): ?array {
        $d = $this->registries->monthly($registries, $this->year());

        if ($d['total'] === 0) {
            return null;
        }

        $panel = $secondary === 'lga'
            ? $this->lgaPanel($d)
            : $this->genderPanel($d['gender'], $d['total']);

        return [
            'key'        => $key,
            'no'         => $no,
            'department' => $department,
            'title'      => $title,
            'subtitle'   => 'Files indexed into the registry, January – December ' . $this->year(),
            'measure'    => 'Files indexed',
            // Not "Date captured": file_indexings.date_created is NULL on every
            // row of both registries, so created_at is the only date there is and
            // the label names what it actually marks.
            'date_basis' => 'Date indexed',
            'headline'   => [
                'value'   => $d['total'],
                'unit'    => 'files',
                'caption' => $this->genderCaption($d['coverage'])
                             . ' · ' . number_format($d['lga_count']) . ' LGAs represented',
            ],
            'layout'     => 'monthly',
            'chart'      => [
                'type'   => 'stacked-column',
                'labels' => self::MONTHS,
                'series' => $this->landUseSeries($d['landuse']),
            ],
            'chart_secondary' => $panel,
            'table'      => $this->cutTable($d),
            'table_secondary' => $this->genderTable($d),
        ];
    }

    /** The LGA panel, for a section whose gender coverage is too thin to chart. */
    private function lgaPanel(array $d): ?array
    {
        $series = $this->namedSeries($d['lga'], 'lga');

        if ($series === []) {
            return null;
        }

        // Other LGAs is a remainder, not an LGA, so it takes grey — the same
        // treatment Uncategorised and Not Recorded get in the other two scopes.
        foreach ($series as $i => $s) {
            if ($s['label'] === 'Other LGAs') {
                $series[$i]['color'] = self::HUE['grey'];
            }
        }

        return [
            'type'    => 'stacked-column',
            'labels'  => self::MONTHS,
            'series'  => $series,
            'title'   => 'Local government area',
            'caption' => 'Same rows as the chart above, cut by LGA instead of land use. '
                       . 'The ' . RegistryIndexingStats::LGA_LIMIT . ' busiest of ' . number_format($d['lga_count'])
                       . ' are charted individually; the rest are grouped as Other LGAs '
                       . 'rather than dropped. Gender is charted for this registry in the '
                       . 'table below, where its coverage can be read honestly.',
        ];
    }

    private function bankRankingSection(): ?array
    {
        $result  = $this->deeds->bankRanking($this->year());
        $ranking = $result['institutions'];

        if ($ranking === []) {
            return null;
        }

        $labels = array_keys($ranking);
        $values = array_values($ranking);
        $total  = array_sum($values);

        // Private mortgagees are individuals holding security, not lenders. They
        // are reported as a figure rather than ranked among the banks.
        $privateNote = $result['private'] > 0
            ? ' · ' . number_format($result['private']) . ' further facilities held by '
              . number_format($result['private_payees']) . ' private mortgagees'
            : '';

        return [
            'key'        => 'bank_ranking',
            'no'         => '04',
            'department' => 'deeds',
            'title'      => 'Bank Ranking by Registered Facility',
            'subtitle'   => 'Mortgagees ranked by number of registered mortgages, ' . $this->year(),
            'measure'    => 'Facilities registered',
            'date_basis' => 'Deeds date',
            'headline'   => [
                'value'   => $total,
                'unit'    => 'facilities',
                'caption' => count($labels) . ' lending institutions, names normalised from free text' . $privateNote,
            ],
            'layout'     => 'category',
            'chart'      => [
                // Horizontal and sorted: long names, and ranking is the point.
                'type'   => 'bar-h',
                'labels' => $labels,
                'series' => [[
                    'label' => 'Facilities',
                    'color' => self::HUE['blue'],
                    'data'  => $values,
                ]],
            ],
            'table'      => [
                'columns' => [
                    ['label' => 'Mortgagee', 'align' => 'left'],
                    ['label' => 'Facilities', 'suspect' => false],
                    ['label' => 'Share %', 'suspect' => false],
                ],
                'rows'  => array_map(
                    fn ($b, $n) => [$b, $n, $total > 0 ? round($n / $total * 100, 1) : 0],
                    $labels,
                    $values
                ),
                'total' => ['TOTAL', $total, 100.0],
            ],
        ];
    }

    private function searchSection(): ?array
    {
        $d = $this->searches->monthly($this->year());

        if ($d['total'] === 0) {
            return null;
        }

        return [
            'key'        => 'search',
            'no'         => '09',
            'department' => 'deeds',
            'title'      => 'Official Searches',
            'subtitle'   => 'Search requests logged, January – December ' . $this->year(),
            'measure'    => 'Search requests received',
            'date_basis' => 'Date captured',
            'headline'   => [
                'value'   => $d['total'],
                'unit'    => 'searches',
                'caption' => 'no gender or land use is captured against a search',
            ],
            'layout'     => 'monthly',
            'chart'      => [
                'type'   => 'stacked-column',
                'labels' => self::MONTHS,
                'series' => $this->namedSeries($d['status'], 'landuse'),
            ],
            'table'      => $this->monthlyTable(
                array_merge(array_keys($d['status']), ['Total']),
                array_merge($d['status'], ['Total' => $d['months']]),
                []
            ),
        ];
    }

    private function ossSection(string $key, string $no, string $cut, string $title, string $unit): ?array
    {
        $d = $this->oss->monthly($this->year());

        if ($d['total'] === 0) {
            return null;
        }

        $series = $cut === 'gender'
            ? $this->genderSeries($d['gender'])
            : $this->landUseSeries($d['landuse']);

        return [
            'key'        => $key,
            'no'         => $no,
            'department' => 'lands',
            'title'      => $title,
            'subtitle'   => 'Applications received, January – December ' . $this->year(),
            'measure'    => 'Applications received',
            'date_basis' => 'Date captured',
            'headline'   => [
                'value'   => $d['total'],
                'unit'    => $unit,
                'caption' => $cut === 'gender'
                    ? 'gender captured on the OSS form for '
                      . $this->pct($d['coverage']['gender_resolved'], $d['total']) . ' of applications'
                    : 'land use taken from application_type — oss_applications.land_use is empty',
            ],
            'layout'     => 'monthly',
            'chart'      => [
                'type'   => 'stacked-column',
                'labels' => self::MONTHS,
                'series' => $series,
            ],
            'chart_secondary' => $this->genderPanel($d['gender'], $d['total']),
            'table'      => $this->cutTable($d),
            'table_secondary' => $this->genderTable($d),
        ];
    }

    // ── Shared shaping ──────────────────────────────────────────────────────

    /**
     * The gender panel every section carries: its own chart beside the land-use
     * chart, cut from the same rows.
     *
     * Requested by the client on 2026-08-03. Gender was previously only columns in
     * the table, and only sections 14 and 16 charted it — so the dimension the
     * whole backfill existed to produce was largely invisible on the page.
     *
     * Rendered through chart_secondary, which the section partial already supports.
     */
    private function genderPanel(array $gender, int $total): ?array
    {
        $series = $this->genderSeries($gender);

        if ($series === [] || $total === 0) {
            return null;
        }

        $recorded = $total - array_sum($gender['Not Recorded'] ?? [0]);

        return [
            'type'    => 'stacked-column',
            'labels'  => self::MONTHS,
            'series'  => $series,
            'title'   => 'Gender breakdown',
            'caption' => 'Same rows as the chart above, cut by gender instead of land use. '
                       . 'Gender is on file for ' . $this->pct($recorded, $total)
                       . ' of them; the rest show as Not Recorded rather than being dropped.',
        ];
    }

    /** Land-use series in canonical order, dropping categories with no rows. */
    private function landUseSeries(array $landuse): array
    {
        $order = array_values(array_filter(
            LandUseNormalizer::CANON,
            fn ($c) => isset($landuse[$c]) && array_sum($landuse[$c]) > 0
        ));

        return $this->series($order ?: [LandUseNormalizer::UNCATEGORISED], 'landuse', $landuse);
    }

    private function genderSeries(array $gender): array
    {
        $order = array_values(array_filter(
            self::GENDER_ORDER,
            fn ($g) => isset($gender[$g]) && array_sum($gender[$g]) > 0
        ));

        return $this->series($order ?: ['Not Recorded'], 'gender', $gender);
    }

    /** Series from arbitrary keys (streams, statuses), coloured off the palette. */
    private function namedSeries(array $data, string $scope): array
    {
        $hues  = array_values(self::HUE);
        $out   = [];
        $i     = 0;

        foreach ($data as $label => $months) {
            if (array_sum($months) === 0) {
                continue;
            }

            $out[] = [
                'label' => $label,
                'color' => $hues[$i++ % count($hues)],
                'data'  => array_values($months),
            ];
        }

        return $out;
    }

    /**
     * The land-use table: one column per category, plus Total.
     *
     * Gender moved out to its own table on 2026-08-03 (client request). Putting
     * both cuts in one table made it wide enough to scroll, and hid the fact that
     * they are two independent views of the same rows rather than one flat table
     * of eleven columns — which is precisely the confusion that produced the
     * source report's irreconcilable gender and category totals.
     */
    private function cutTable(array $d): array
    {
        $landCols = array_values(array_filter(
            LandUseNormalizer::CANON,
            fn ($c) => isset($d['landuse'][$c]) && array_sum($d['landuse'][$c]) > 0
        ));

        $columns = array_merge($landCols, ['Total']);
        $data    = array_merge($d['landuse'], ['Total' => $d['months']]);

        return $this->monthlyTable($columns, $data, []);
    }

    /**
     * The gender table, cut from the same rows and footing to the same Total.
     *
     * `Not Recorded` is a column, never a silent omission: 71% of the gender
     * dimension is inferred and the remainder is genuinely unknown, so a table
     * that quietly dropped it would overstate every share on the row.
     */
    private function genderTable(array $d): ?array
    {
        $cols = array_values(array_filter(
            self::GENDER_ORDER,
            fn ($g) => isset($d['gender'][$g]) && array_sum($d['gender'][$g]) > 0
        ));

        if ($cols === []) {
            return null;
        }

        $columns = array_merge($cols, ['Total']);
        $data    = array_merge($d['gender'], ['Total' => $d['months']]);

        return [
            'title' => 'Gender breakdown',
        ] + $this->monthlyTable($columns, $data, []);
    }

    private function genderCaption(array $coverage): string
    {
        $resolved = $coverage['gender_resolved'] ?? 0;
        $missing  = $coverage['gender_missing'] ?? 0;
        $total    = $resolved + $missing;

        if ($total === 0) {
            return 'no rows';
        }

        return 'gender resolved for ' . $this->pct($resolved, $total) . ' of rows';
    }

    private function streamCaption(array $streams): string
    {
        $parts = [];

        foreach ($streams as $name => $months) {
            $parts[] = $name . ' ' . number_format(array_sum($months));
        }

        return implode(' · ', $parts) ?: 'no rows';
    }

    private function pct(int $n, int $of): string
    {
        return $of > 0 ? number_format($n / $of * 100, 1) . '%' : '0%';
    }
}
