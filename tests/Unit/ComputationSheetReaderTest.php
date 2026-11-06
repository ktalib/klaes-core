<?php

namespace Tests\Unit;

use App\Services\Survey\ComputationSheetReader as R;
use PHPUnit\Framework\TestCase;

/** Computation sheet.xlsx (user, 2026-11-06): each sheet has its own columns. */
class ComputationSheetReaderTest extends TestCase
{
    private array $head = ['F ID', 'F Name', 'M Name', 'L Name', 'Sheet', 'AREA (M²)', 'AREA (Ha)', 'CREATABLE', 'GOVT 50%', 'FARM 50%', 'PLOTS', null];

    public function test_what_reads_as_plots(): void
    {
        foreach (['388', '418, 419', '267B', '13 & 14', '707-712', '3149B (1/2)', '36, 37 38A', '1192, 1193, 1195 1196, 1191B'] as $v) {
            $this->assertTrue(R::looksLikePlots($v), $v);
        }
        foreach (['allocated', '2plots', '=', 'SHARE ABOVE', '8164131468', '', null] as $v) {
            $this->assertFalse(R::looksLikePlots($v), (string) $v);
        }
    }

    public function test_a_line_reads_by_its_headers_with_words_beside_it_kept_as_remarks(): void
    {
        $out = R::read([$this->head,
            ['1181', 'Adamu', null, 'Musa', 'P14', 1897.6, 0.19, 4.18, 2.09, 2.11, '418, 419', 'see file'],
            ['1182', 'Alh. Shehu', 'Mohd.', 'Gaida', 'P14', 1185.9, 0.12, 2.6, 1.3, 1.3, 'allocated', null],
        ]);

        $f = $out['rows'][0]['fields'];
        $this->assertSame('farmer', $out['rows'][0]['kind']);
        $this->assertSame('1181', $f['fn']);
        $this->assertSame('Adamu Musa', $f['name']);
        $this->assertSame(0.19, $f['area']);
        $this->assertSame(2.09, $f['govt50']);
        $this->assertSame(2.11, $f['farmer50']);
        $this->assertSame('418, 419', $f['plot']);
        $this->assertSame('see file', $f['remarks']);

        // Words in the plots column are not a plot number, and are not lost.
        $this->assertNull($out['rows'][1]['fields']['plot']);
        $this->assertSame('allocated', $out['rows'][1]['fields']['remarks']);
        $this->assertSame('Alh. Shehu Mohd. Gaida', $out['rows'][1]['fields']['name']);
    }

    public function test_farm_before_govt_goes_by_the_header_not_the_position(): void
    {
        $out = R::read([['Property ID', 'First Name', 'Middle Name', 'Last Name', 'Sheet No', 'AREA (M²)', 'AREA (Ha)', 'CREATABLE', 'FARM 50%', 'GOVT 50%'],
            ['9', 'Musa', null, 'Ali', 'S', 100, 0.01, 1.0, 0.25, 0.75]]);

        $this->assertSame(0.25, $out['rows'][0]['fields']['farmer50']);
        $this->assertSame(0.75, $out['rows'][0]['fields']['govt50']);
    }

    public function test_the_plots_column_is_found_by_what_it_holds_even_unlabelled(): void
    {
        $head = ['Property ID', 'First Name', 'Middle Name', 'Last Name', 'Sheet No', 'AREA (M²)', 'AREA (Ha)', 'CREATABLE', 'GOVT 50%', 'FARM 50%', null, null];
        $out = R::read([$head,
            ['1', 'A', null, 'B', 'P', 1, 0.1, 1, 0.5, 0.5, 'note', '12'],
            ['2', 'C', null, 'D', 'P', 1, 0.1, 1, 0.5, 0.5, null, '13, 14'],
        ]);

        $this->assertSame(11, $out['plots_col']);
        $this->assertSame('12', $out['rows'][0]['fields']['plot']);
        $this->assertSame('note', $out['rows'][0]['fields']['remarks']);
    }

    public function test_totals_and_stray_notes_are_kept_but_are_not_farmers(): void
    {
        $out = R::read([$this->head,
            ['1', 'A', null, 'B', 'P', 1, 0.1, 1, 0.5, 0.5, '12', null],
            ['TOTAL', null, null, null, null, 1, 0.1, 1, 0.5, 0.5, null, null],
            [null, null, null, null, null, 5, 0.5, null, null, null, null, null],
            [null, null, null, null, null, null, null, null, null, null, null, 'TP/KNUPDA/328A'],
            [null, null, null, null, null, null, null, null, null, null, null, null],
        ]);

        $this->assertSame(['farmer', 'total', 'total', 'other'], array_column($out['rows'], 'kind'));
        $this->assertSame([2, 3, 4, 5], array_column($out['rows'], 'row'));
    }

    public function test_a_dirty_line_is_still_a_line(): void
    {
        $out = R::read([$this->head,
            ['10331a', 'Mustapha', null, 'Nasir', 'Q15', null, 0, 0, 0, 0, null, null],
            [null, 'No Id', null, 'Farmer', 'Q15', null, null, null, null, null, null, null],
            ['777', null, null, null, 'Q15', null, null, null, null, null, null, null],
        ]);

        $this->assertSame(['farmer', 'farmer', 'farmer'], array_column($out['rows'], 'kind'));
    }

    public function test_written_keeps_each_cell_as_the_sheet_shows_it(): void
    {
        $head = ['F ID', 'F Name', 'M Name', 'L Name', 'Sheet', 'AREA (M²)', 'AREA (Ha)', 'CREATABLE', 'GOVT 50%', 'FARM 50%', 'PLOTS', null];
        $values = [$head,
            ['1', 'A', null, 'B', 'q14', 881.006, 0.088, 1.941, 0.97, 0.97, '12', null],
            ['2', 'C', null, 'D', 'Q16/Q17', 100, 0.01, 1, 0.5, 0.5, 'allocated', 'see file'],
            ['TOTAL', null, null, null, null, 981, 0.098, 2.9, 1.47, 1.47, null, null],
        ];
        $shown = $values;
        $shown[1][7] = '1.94';   // Excel's own format for the cell

        $rows = R::written($values, $shown);

        $this->assertSame(['line', 'line', 'total'], array_column($rows, 'kind'));
        $this->assertSame('q14', $rows[0]['sheet_no']);
        $this->assertSame('1.94', $rows[0]['creatable']);
        $this->assertSame('12', $rows[0]['plots']);
        // Unlike the plot reading, words in the plots column stay in it here.
        $this->assertSame('allocated', $rows[1]['plots']);
        $this->assertSame('see file', $rows[1]['remarks']);
        $this->assertSame('Q16/Q17', $rows[1]['sheet_no']);
        $this->assertSame(4, $rows[2]['source_row']);
    }

    public function test_a_whole_number_excel_keeps_as_a_float_reads_as_a_number(): void
    {
        $this->assertSame('388', R::text(388.0));
        $this->assertSame('1.5', R::text(1.5));
        $this->assertNull(R::text('   '));
    }
}
