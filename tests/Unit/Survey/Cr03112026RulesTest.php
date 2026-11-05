<?php

namespace Tests\Unit\Survey;

use App\Http\Middleware\SurveyLandRecommendation;
use App\Models\Survey\SurveyBeaconRegister;
use App\Models\Survey\SurveyOpShowing;
use App\Services\Survey\OpTpGroups;
use PHPUnit\Framework\TestCase;

/** CR 03112026: the small rules several screens rest on. No database. */
class Cr03112026RulesTest extends TestCase
{
    public function test_plots_run_numbers_first_then_the_series(): void
    {
        $plots = ['SS-1', 'C-14', '14', 'IND 79', '2', 'PIECE OF LAND', 'C-2', '', '31B', 'IND 3'];
        $ops = array_map(fn ($p) => ['plot_no' => $p], $plots);

        $sorted = array_column(OpTpGroups::sortByPlot($ops), 'plot_no');

        $this->assertSame(['2', '14', '31B', 'C-2', 'C-14', 'IND 3', 'IND 79', 'SS-1', 'PIECE OF LAND', ''], $sorted);
    }

    public function test_a_spelling_is_stored_trimmed_and_in_capitals(): void
    {
        $this->assertSame('TP/KN/215M', OpTpGroups::raw('  tp/kn/215m '));
        $this->assertNull(OpTpGroups::raw('   '));
        $this->assertNull(OpTpGroups::raw(null));
    }

    /** @dataProvider addresses */
    public function test_a_lands_address_stays_in_survey(string $from, string $to): void
    {
        $this->assertSame($to, SurveyLandRecommendation::onSurvey($from));
    }

    public static function addresses(): array
    {
        $s = 'http://app.klaes.ng/survey-module/letter-of-grant';

        return [
            'the list'        => ['http://app.klaes.ng/land-recommendations?type=ROFO', "$s/land-recommendations?type=ROFO"],
            'the RofO list'   => ['http://app.klaes.ng/land-rofos', "$s/land-rofos"],
            'a record'        => ['http://app.klaes.ng/land-rofos/5/print', "$s/land-rofos/5/print"],
            'a path only'     => ['/land-recommendations/create', '/survey-module/letter-of-grant/land-recommendations/create'],
            'already there'   => ["$s/land-rofos", "$s/land-rofos"],
            'somewhere else'  => ['http://app.klaes.ng/home', 'http://app.klaes.ng/home'],
            'a similar name'  => ['http://app.klaes.ng/land-recommendations-old', 'http://app.klaes.ng/land-recommendations-old'],
        ];
    }

    public function test_a_showing_takes_its_type_from_the_op(): void
    {
        $this->assertSame('Resettlement', SurveyOpShowing::typeOf('OP Resettlement'));
        $this->assertSame('Resettlement', SurveyOpShowing::typeOf('Resettlement'));
        $this->assertSame('Direct Allocation', SurveyOpShowing::typeOf('OP Direct Allocation'));
        $this->assertSame('Direct Allocation', SurveyOpShowing::typeOf('Other'), 'Other counts as Direct Allocation');
        $this->assertSame('Direct Allocation', SurveyOpShowing::typeOf(null), 'no type counts as Direct Allocation');
    }

    public function test_pasted_beacon_numbers_and_ranges(): void
    {
        $list = SurveyBeaconRegister::parseList("KB1088, A1404B\nKB1100 to KB1120;KDJ 0001 - KDJ 0004\nA0001B–A0002B\nrubbish!");

        $this->assertSame(['rubbish!'], $list['bad']);
        $this->assertCount(5, $list['ranges']);
        $this->assertSame('KB|', $list['ranges'][0]['series']);
        $this->assertSame('A|B', $list['ranges'][1]['series']);
        $this->assertSame(21, $list['ranges'][2]['count']);
        $this->assertSame('KDJ|', $list['ranges'][3]['series']);
        $this->assertSame(4, $list['ranges'][3]['count']);
        $this->assertSame(2, $list['ranges'][4]['count']);
    }

    public function test_a_range_over_the_limit_is_not_read(): void
    {
        $list = SurveyBeaconRegister::parseList('A0001B to A2000B');

        $this->assertSame([], $list['ranges']);
        $this->assertCount(1, $list['bad']);
    }
}
