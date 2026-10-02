<?php

namespace Tests\Unit\Services;

use App\Services\RegrantTermService;
use Tests\TestCase;

/**
 * Re-grant tenure arithmetic, exercised without a database.
 *
 * These rules decide whether a live title is declared expired and put forward for re-grant,
 * so they have to be provable on their own. The date parsing in particular reads a column
 * that holds four different formats, and getting it wrong in one direction retires a title
 * that has decades left to run.
 */
class RegrantTermServiceTest extends TestCase
{
    /**
     * Every shape the nvarchar date columns actually hold.
     *
     * @dataProvider storedDateShapes
     */
    public function test_it_parses_every_stored_date_shape(string $stored, ?array $expected): void
    {
        $this->assertSame($expected, RegrantTermService::parseDateParts($stored));
    }

    public static function storedDateShapes(): array
    {
        return [
            'ISO'                 => ['2016-03-16', [16, 3, 2016]],
            'ISO with time'       => ['1919-01-04 00:00:00', [4, 1, 1919]],
            'day-first dashes'    => ['20-6-1994', [20, 6, 1994]],
            'day-first slashes'   => ['8/2/1995', [8, 2, 1995]],
            'SQL Server default'  => ['May 11 2025 12:00AM', [11, 5, 2025]],
            // .NET's DateTime.MinValue parses as a real calendar date here — year 1 is valid.
            // It is screened out earlier, in SQL: yearExpression() looks for a 4-digit run
            // matching [12][0-9][0-9][0-9], which "0001" cannot satisfy, so a row carrying
            // only this sentinel never reaches the date parser with a term to clock.
            'MinValue sentinel'   => ['0001-01-01', [1, 1, 1]],
            'impossible calendar' => ['2016-02-30', null],
            'junk'                => ['not a date', null],
            'empty'               => ['', null],
        ];
    }

    /**
     * parseDateParts() was split out of formatDate(); the rendering must not have shifted.
     */
    public function test_format_date_is_unchanged_by_the_split(): void
    {
        $this->assertSame('March 16, 2016', RegrantTermService::formatDate('2016-03-16'));
        $this->assertSame('June 20, 1994', RegrantTermService::formatDate('20-6-1994'));
        $this->assertSame('May 11, 2025', RegrantTermService::formatDate('May 11 2025 12:00AM'));
        $this->assertNull(RegrantTermService::formatDate(''));
    }

    /**
     * A value that is not a real date is handed back untouched rather than guessed at, so a
     * bad record stays visible on screen instead of becoming a plausible-looking wrong date.
     */
    public function test_an_unparseable_date_is_returned_unchanged(): void
    {
        $this->assertSame('not a date', RegrantTermService::formatDate('not a date'));
        $this->assertSame('2016-02-30', RegrantTermService::formatDate('2016-02-30'));
    }

    /**
     * The .NET MinValue sentinel is a real calendar date as far as the parser is concerned, so
     * it is rejected in SQL instead: yearExpression() hunts for a 4-digit run matching
     * [12][0-9][0-9][0-9], and "0001" cannot match it. Pinned here because the guard lives in
     * a different layer from the parser it protects, and is easy to remove by accident.
     */
    public function test_the_minvalue_sentinel_is_screened_out_in_sql_not_in_the_parser(): void
    {
        $this->assertSame([1, 1, 1], RegrantTermService::parseDateParts('0001-01-01'));

        $this->assertStringContainsString(
            "PATINDEX('%[12][0-9][0-9][0-9]%'",
            RegrantTermService::yearExpression('transaction_date')
        );
    }

    /**
     * Pre-1970 grants must survive: the register holds titles back to 1914, and anything
     * built on Unix timestamps would not reach them.
     */
    public function test_it_handles_dates_before_the_unix_epoch(): void
    {
        $this->assertSame([4, 1, 1919], RegrantTermService::parseDateParts('1919-01-04'));
        $this->assertSame('January 4, 1919', RegrantTermService::formatDate('1919-01-04'));
    }

    /**
     * The land-use wording may only be attached to a term that was actually derived from the
     * land use. Saying "99 yrs (Residential)" about a term read off the certificate would
     * assert a reason that was never used to reach it.
     */
    public function test_term_label_only_claims_a_land_use_reason_for_a_standard_term(): void
    {
        $this->assertSame('99 yrs (Residential / Agricultural)', RegrantTermService::termLabel(99, 'standard'));
        $this->assertSame('40 yrs (Commercial / Industrial)', RegrantTermService::termLabel(40, 'standard'));

        $this->assertSame('99 yrs', RegrantTermService::termLabel(99, 'instrument'));
        $this->assertSame('30 yrs', RegrantTermService::termLabel(30, 'instrument'));
        $this->assertSame('10 yrs', RegrantTermService::termLabel(10, 'file'));

        $this->assertSame('Term undetermined', RegrantTermService::termLabel(null));
    }

    public function test_term_source_labels_distinguish_recorded_from_assumed(): void
    {
        $this->assertSame('Actual (file)', RegrantTermService::termSourceLabel('file'));
        $this->assertSame('Actual (CofO)', RegrantTermService::termSourceLabel('instrument'));
        $this->assertSame('Standard', RegrantTermService::termSourceLabel('standard'));
        $this->assertSame('Undetermined', RegrantTermService::termSourceLabel(null));
    }

    /**
     * The term is read from the file-number prefix before the free-text land use, because the
     * two disagree on 1,275 RofO rows — 996 files numbered RES- are tagged "COMMERCIAL",
     * which would shorten their term from 99 to 40 and flag live titles as expired.
     */
    public function test_the_standard_term_reads_the_prefix_before_the_land_use_column(): void
    {
        $sql = RegrantTermService::termExpression('fi.file_number', 'fi.land_use_type');

        $resBranch = strpos($sql, "LIKE 'RES-%'");
        $columnBranch = strpos($sql, 'LIKE \'RES%\'');

        $this->assertNotFalse($resBranch, 'the prefix branch must exist');
        $this->assertNotFalse($columnBranch, 'the land-use fallback must exist');
        $this->assertLessThan($columnBranch, $resBranch, 'the prefix must be tested before the column');
    }

    /**
     * Single-digit terms are not trusted. The register holds 16 of them against 17,670 recorded
     * as 99 — including four residential files recorded as "9" — and because a short term is
     * exactly what puts a file on the due list, those few rows landed on it out of all
     * proportion. Ten years is a real approved term, so the floor sits there and no credible
     * short grant is lost.
     */
    public function test_the_instrument_term_rejects_implausibly_short_values(): void
    {
        $sql = RegrantTermService::actualTermExpression('period', 'period_unit');

        $this->assertStringContainsString('BETWEEN 10 AND 999', $sql);
        // Months and Days are not tenures a title is granted for; only years count.
        $this->assertStringContainsString("IN ('', 'YEARS')", $sql);
    }

    /**
     * file_indexings.term is free text holding a rendered term ("99 Years"). Both this service
     * and Legal Search take the leading digits, so one stored string cannot be read two ways.
     */
    public function test_the_recorded_term_reads_leading_digits_from_free_text(): void
    {
        $sql = RegrantTermService::recordedTermExpression('fi.term');

        $this->assertStringContainsString("PATINDEX('%[^0-9]%'", $sql);
        // The sentinel keeps an all-digit value from yielding a zero-length take.
        $this->assertStringContainsString("+ 'X'", $sql);
        $this->assertStringContainsString('BETWEEN 1 AND 999', $sql);
    }
}
