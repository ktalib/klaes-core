<?php

namespace Tests\Unit\Support;

use App\Support\BatchDivergence;
use Tests\TestCase;

/**
 * BatchDivergence — the gate on writing one edit across every file in a batch.
 *
 * A batch of N file numbers is drawn as ONE row on the MLS File Commission list, so
 * "apply this to the whole batch" is a reasonable thing to offer. For most batches it is
 * also harmless: of 141 multi-file batches, 111 hold a single plot number across all
 * members and 125 a single applicant name — they were commissioned as one allocation.
 *
 * Thirty were not. Those hold genuinely different plot numbers per file, and a batch-wide
 * write would flatten them into whatever the clicked row said. The loss is silent and
 * irreversible, which is why the batch save stops and shows the competing values first.
 *
 * Two failure modes matter equally: missing a real divergence (data destroyed without a
 * word) and inventing one (a confirmation dialog on every ordinary batch edit, which
 * trains people to click through the real ones). Both are asserted.
 */
class BatchDivergenceTest extends TestCase
{
    /** Column names as they appear on a `fileNumber` row. */
    private const MAP = [
        'file_name' => 'FileName',
        'plot_no'   => 'plot_no',
        'tp_no'     => 'tp_no',
        'district'  => 'district',
        'lga'       => 'lga',
        'location'  => 'location',
    ];

    private function members(array $rows): array
    {
        return array_map(fn ($r) => (object) $r, $rows);
    }

    /** @test */
    public function a_batch_that_agrees_raises_nothing(): void
    {
        // The common case: seven files, one allocation, one plot description.
        $members = $this->members([
            ['FileName' => 'ALH. BALA', 'plot_no' => 'PIECE OF LAND'],
            ['FileName' => 'ALH. BALA', 'plot_no' => 'PIECE OF LAND'],
            ['FileName' => 'ALH. BALA', 'plot_no' => 'PIECE OF LAND'],
        ]);

        $divergent = BatchDivergence::detect(
            $members,
            ['file_name' => 'ALH. BALA AHMADU', 'plot_no' => 'PLOT 12'],
            self::MAP
        );

        $this->assertSame([], $divergent);
    }

    /** @test */
    public function it_reports_a_field_the_members_disagree_on(): void
    {
        $members = $this->members([
            ['plot_no' => 'PLOT 1'],
            ['plot_no' => 'PLOT 2'],
            ['plot_no' => 'PLOT 3'],
        ]);

        $divergent = BatchDivergence::detect($members, ['plot_no' => 'PLOT 9'], self::MAP);

        $this->assertCount(1, $divergent);
        $this->assertSame('plot_no', $divergent[0]['field']);
        $this->assertSame('Plot Number', $divergent[0]['label']);
        $this->assertSame(['PLOT 1', 'PLOT 2', 'PLOT 3'], $divergent[0]['values']);
    }

    /** @test */
    public function only_fields_the_edit_actually_writes_can_raise_a_prompt(): void
    {
        // The members differ on plot_no, but this edit does not touch plot_no — nothing
        // is at risk, so interrupting would be noise.
        $members = $this->members([
            ['plot_no' => 'PLOT 1', 'lga' => 'NASSARAWA'],
            ['plot_no' => 'PLOT 2', 'lga' => 'NASSARAWA'],
        ]);

        $divergent = BatchDivergence::detect($members, ['lga' => 'FAGGE'], self::MAP);

        $this->assertSame([], $divergent);
    }

    /** @test */
    public function it_reports_every_divergent_field_at_once(): void
    {
        $members = $this->members([
            ['FileName' => 'A', 'plot_no' => 'PLOT 1', 'lga' => 'NASSARAWA'],
            ['FileName' => 'B', 'plot_no' => 'PLOT 2', 'lga' => 'NASSARAWA'],
        ]);

        $divergent = BatchDivergence::detect(
            $members,
            ['file_name' => 'C', 'plot_no' => 'PLOT 9', 'lga' => 'FAGGE'],
            self::MAP
        );

        // file_name and plot_no differ; lga is uniform and must not be listed.
        $this->assertSame(['file_name', 'plot_no'], array_column($divergent, 'field'));
    }

    /** @test */
    public function a_single_file_never_diverges(): void
    {
        // Guards the ordinary non-batch save from ever hitting the confirmation path.
        $divergent = BatchDivergence::detect(
            $this->members([['plot_no' => 'PLOT 1']]),
            ['plot_no' => 'PLOT 2'],
            self::MAP
        );

        $this->assertSame([], $divergent);
        $this->assertSame([], BatchDivergence::detect([], ['plot_no' => 'X'], self::MAP));
    }

    /** @test */
    public function casing_and_padding_are_not_treated_as_a_disagreement(): void
    {
        // The commissioning form upper-cases some fields and not others; "Plot 1" and
        // "PLOT 1 " are the same plot, and prompting on them would be a false alarm.
        $members = $this->members([
            ['plot_no' => 'PLOT 1'],
            ['plot_no' => ' plot 1 '],
        ]);

        $this->assertSame([], BatchDivergence::detect($members, ['plot_no' => 'PLOT 2'], self::MAP));
    }

    /** @test */
    public function a_blank_and_a_populated_value_do_diverge_and_blank_is_named(): void
    {
        // One file carrying a TP number and one not IS a real difference — writing over
        // it destroys the one that had a value.
        $members = $this->members([
            ['tp_no' => 'TP/2020/1'],
            ['tp_no' => null],
        ]);

        $divergent = BatchDivergence::detect($members, ['tp_no' => 'TP/2021/9'], self::MAP);

        $this->assertCount(1, $divergent);
        $this->assertSame(['(blank)', 'TP/2020/1'], $divergent[0]['values']);
    }

    /** @test */
    public function null_and_empty_string_are_the_same_absence(): void
    {
        $members = $this->members([
            ['tp_no' => null],
            ['tp_no' => ''],
            ['tp_no' => '   '],
        ]);

        $this->assertSame([], BatchDivergence::detect($members, ['tp_no' => 'X'], self::MAP));
    }

    /** @test */
    public function unwatched_fields_are_ignored_even_when_they_differ(): void
    {
        $members = $this->members([
            ['phone_no' => '0801', 'plot_no' => 'PLOT 1'],
            ['phone_no' => '0802', 'plot_no' => 'PLOT 1'],
        ]);

        $divergent = BatchDivergence::detect(
            $members,
            ['phone_no' => '0900', 'plot_no' => 'PLOT 1'],
            self::MAP
        );

        $this->assertSame([], $divergent);
    }

    /** @test */
    public function it_reads_array_rows_as_well_as_objects(): void
    {
        $divergent = BatchDivergence::detect(
            [['plot_no' => 'PLOT 1'], ['plot_no' => 'PLOT 2']],
            ['plot_no' => 'PLOT 9'],
            self::MAP
        );

        $this->assertCount(1, $divergent);
    }

    /** @test */
    public function the_summary_names_the_fields_and_the_file_count(): void
    {
        $divergent = BatchDivergence::detect(
            $this->members([['plot_no' => 'PLOT 1'], ['plot_no' => 'PLOT 2']]),
            ['plot_no' => 'PLOT 9'],
            self::MAP
        );

        $summary = BatchDivergence::summarise($divergent, 7);

        $this->assertStringContainsString('7 files', $summary);
        $this->assertStringContainsString('Plot Number', $summary);
        $this->assertStringContainsString('PLOT 1', $summary);
        $this->assertStringContainsString('PLOT 2', $summary);
    }

    /** @test */
    public function the_summary_is_empty_when_nothing_diverges(): void
    {
        $this->assertSame('', BatchDivergence::summarise([], 7));
    }

    /** @test */
    public function a_long_value_list_is_truncated_rather_than_flooding_the_dialog(): void
    {
        $members = $this->members(array_map(
            fn ($i) => ['plot_no' => "PLOT {$i}"],
            range(1, 12)
        ));

        $divergent = BatchDivergence::detect($members, ['plot_no' => 'PLOT 99'], self::MAP);
        $summary = BatchDivergence::summarise($divergent, 12);

        $this->assertCount(12, $divergent[0]['values']);
        $this->assertStringContainsString('more', $summary);
    }
}
