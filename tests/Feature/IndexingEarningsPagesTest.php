<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\IndexingPay\EarningsService;
use App\Services\IndexingPay\IndexingPaySettings;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Indexing piece-rate earnings against real data: the figures every screen shows agree
 * with each other. READ-ONLY against sqlsrv (the test connection refuses writes).
 *
 * Pages are not requested over HTTP here: klaes_testing has no `settings` / `permissions`
 * tables, which every page's middleware and layout read. The services the pages call are
 * exercised directly instead. The programme start is moved back in-process so last week's
 * records show up.
 */
class IndexingEarningsPagesTest extends TestCase
{
    private EarningsService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config()->set('indexing_pay.defaults.start_date', '2026-10-20');
        app(IndexingPaySettings::class)->forget();
        $this->svc = app(EarningsService::class);
    }

    private function busyIndexer(): User
    {
        $ids = $this->svc->eligibleUserIds();
        $items = $this->svc->collect($ids, '2026-10-26', '2026-10-30');
        $counts = array_count_values(array_column($items, 'user_id'));
        arsort($counts);
        $user = $counts ? User::find(array_key_first($counts)) : null;
        if (! $user) {
            $this->markTestSkipped('Nobody earned anything in the window.');
        }

        return $user;
    }

    public function test_summary_adds_up(): void
    {
        $s = $this->svc->summary($this->busyIndexer(), '2026-10', true);

        $this->assertTrue($s['eligible']);
        $this->assertCount(31, $s['calendar']);
        $this->assertEqualsWithDelta($s['month_totals']['amount'], array_sum(array_column($s['calendar'], 'amount')), 0.001);
        $parts = array_sum(array_column($s['month_totals']['by_type'], 'amount')) + array_sum(array_column($s['month_totals']['by_record'], 'amount'));
        $this->assertEqualsWithDelta($s['month_totals']['amount'], $parts, 0.001);
        $this->assertEqualsWithDelta($s['month_totals']['amount'], $s['month_totals']['base_amount'] + $s['month_totals']['records_amount'], 0.001);
        // October is over, so "at your pace" is simply what was earned.
        $this->assertEqualsWithDelta($s['month_totals']['amount'], $s['projected'], 0.001);
        // Nothing before the programme start counts.
        foreach ($s['calendar'] as $day) {
            if ($day['date'] < '2026-10-20') {
                $this->assertSame(0.0, (float) $day['amount'], $day['date']);
            }
        }
    }

    public function test_each_calendar_day_matches_that_days_items(): void
    {
        $user = $this->busyIndexer();
        $s = $this->svc->summary($user, '2026-10', true);
        foreach ($s['calendar'] as $day) {
            if ($day['amount'] <= 0) {
                continue;
            }
            $items = $this->svc->collect([$user->id], $day['date'], $day['date']);
            $this->assertEqualsWithDelta($day['amount'], EarningsService::totals($items)['amount'], 0.001, $day['date']);
            $this->assertSame($day['files'] + $day['records'], count($items), $day['date']);
        }
    }

    public function test_the_sheet_agrees_with_each_persons_own_figures(): void
    {
        $sheet = $this->svc->sheet('2026-10-26', '2026-10-30');
        $this->assertNotEmpty($sheet['rows']);
        $sum = 0.0;
        foreach ($sheet['rows'] as $row) {
            $sum += $row['totals']['amount'];
        }
        $this->assertEqualsWithDelta($sheet['totals']['amount'], $sum, 0.001);

        $top = $sheet['rows'][0];
        $own = EarningsService::totals($this->svc->collect([$top['user_id']], '2026-10-26', '2026-10-30'));
        $this->assertEqualsWithDelta($top['totals']['amount'], $own['amount'], 0.001);
    }

    public function test_every_item_is_priced_from_the_card(): void
    {
        $items = $this->svc->collect($this->svc->eligibleUserIds(), '2026-10-26', '2026-10-30');
        $this->assertNotEmpty($items);
        $card = IndexingPaySettings::normalise([]);
        $seen = [];
        foreach ($items as $i) {
            $expected = EarningsService::amountFor($card, $i['kind'], $i['type']);
            $this->assertSame($expected, $i['amount'], $i['source'] . '#' . $i['source_id']);
            $key = $i['source'] . '#' . $i['source_id'];
            $this->assertArrayNotHasKey($key, $seen, 'A record was paid twice: ' . $key);
            $seen[$key] = true;
        }
    }

    public function test_only_users_with_the_module_are_eligible(): void
    {
        $ids = $this->svc->eligibleUserIds();
        $this->assertNotEmpty($ids);
        foreach (User::whereIn('id', array_slice($ids, 0, 20))->get() as $u) {
            $this->assertTrue($this->svc->isEligible($u));
        }
        $outsider = User::query()->whereNotIn('id', $ids)->orderBy('id')->first();
        if ($outsider) {
            $this->assertFalse($this->svc->isEligible($outsider));
        }
    }
}
