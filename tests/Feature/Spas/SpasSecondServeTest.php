<?php

namespace Tests\Feature\Spas;

use App\Models\SpaNotice;
use App\Services\BulkSmsNgService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * The second serve escalates from DELIVERY of the first, not from data entry.
 *
 * This is the rule that matters legally. The second-serve text tells the owner
 * "there was no response from you after the first serve, hence you will pay the
 * contravention charges plus penalty" — so sending it to someone who was never
 * told anything would levy a penalty off a notice that never existed to them.
 *
 * The gateway refuses some messages on wording and still answers HTTP 200, so a
 * failed first serve is a routine state, not a rare one.
 */
class SpasSecondServeTest extends TestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = ['sqlsrv'];

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** Stop the command reaching a real SMS gateway. */
    private function fakeSms(bool $sends = true): void
    {
        $mock = Mockery::mock(BulkSmsNgService::class);
        $mock->shouldReceive('send')->andReturn($sends);
        $this->app->instance(BulkSmsNgService::class, $mock);
    }

    private function firstServe(array $overrides = []): SpaNotice
    {
        return SpaNotice::create(array_merge([
            'spa_application_id' => null,
            'file_number'        => 'ZZ-NOTICE-'.Str::random(8),
            'notice_type'        => 'first',
            'recipient_name'     => 'ZZ TEST Owner',
            'phone'              => '08000000000',
            'served_date'        => now()->subDays(20)->toDateString(),
            'sms_sent'           => true,
            'sms_sent_at'        => now()->subDays(20),
            'status'             => 'served',
            'created_by'         => 'zz-test',
        ], $overrides));
    }

    private function secondServesFor(SpaNotice $first): int
    {
        return SpaNotice::where('notice_type', 'second')
            ->where('file_number', $first->file_number)
            ->count();
    }

    // -----------------------------------------------------------------------

    public function test_a_delivered_first_serve_escalates_after_fourteen_days(): void
    {
        $this->fakeSms();
        $first = $this->firstServe();

        $this->artisan('spa:trigger-second-service')->assertExitCode(0);

        $this->assertSame(1, $this->secondServesFor($first));
    }

    /**
     * The defect this work fixes.
     *
     * The old rule keyed on served_date alone, so a first serve whose SMS never
     * sent still escalated — demanding a penalty from someone who was never
     * notified.
     */
    public function test_a_first_serve_whose_sms_failed_is_never_escalated(): void
    {
        $this->fakeSms();

        $first = $this->firstServe([
            'sms_sent'    => false,
            'sms_sent_at' => null,
            'served_date' => now()->subDays(60)->toDateString(),   // long overdue
        ]);

        $this->artisan('spa:trigger-second-service')
            ->expectsOutputToContain('NOT escalated')
            ->assertExitCode(0);

        $this->assertSame(
            0,
            $this->secondServesFor($first),
            'A penalty notice must never follow a first serve the owner never received.'
        );
    }

    /**
     * A first serve sent late must get its full two weeks from the send, not
     * from when somebody typed the row.
     */
    public function test_the_clock_runs_from_the_sms_not_the_served_date(): void
    {
        $this->fakeSms();

        $first = $this->firstServe([
            'served_date' => now()->subDays(30)->toDateString(),  // entered a month ago
            'sms_sent_at' => now()->subDays(3),                   // but only sent 3 days ago
        ]);

        $this->artisan('spa:trigger-second-service')->assertExitCode(0);

        $this->assertSame(
            0,
            $this->secondServesFor($first),
            'Only 3 days have passed since the owner was told; escalating now would cut the statutory 14 days short.'
        );
    }

    public function test_a_first_serve_delivered_less_than_fourteen_days_ago_waits(): void
    {
        $this->fakeSms();
        $first = $this->firstServe([
            'served_date' => now()->subDays(5)->toDateString(),
            'sms_sent_at' => now()->subDays(5),
        ]);

        $this->artisan('spa:trigger-second-service')->assertExitCode(0);

        $this->assertSame(0, $this->secondServesFor($first));
    }

    /**
     * Legacy rows have sms_sent = 1 but no timestamp, because sms_sent_at was
     * added later. served_date is the best evidence available for them.
     */
    public function test_a_legacy_row_with_no_timestamp_falls_back_to_served_date(): void
    {
        $this->fakeSms();

        $first = $this->firstServe([
            'sms_sent'    => true,
            'sms_sent_at' => null,
            'served_date' => now()->subDays(40)->toDateString(),
        ]);

        $this->artisan('spa:trigger-second-service')->assertExitCode(0);

        $this->assertSame(1, $this->secondServesFor($first));
    }

    /** The daily run and a manual run can overlap; a duplicate is a duplicate demand. */
    public function test_running_twice_does_not_serve_twice(): void
    {
        $this->fakeSms();
        $first = $this->firstServe();

        $this->artisan('spa:trigger-second-service')->assertExitCode(0);
        $this->artisan('spa:trigger-second-service')->assertExitCode(0);

        $this->assertSame(1, $this->secondServesFor($first));
    }

    public function test_dry_run_creates_nothing(): void
    {
        $this->fakeSms();
        $first = $this->firstServe();

        $this->artisan('spa:trigger-second-service', ['--dry-run' => true])
            ->expectsOutputToContain('Dry run')
            ->assertExitCode(0);

        $this->assertSame(0, $this->secondServesFor($first));
    }

    /**
     * The second serve is still recorded when its own SMS fails, so the office
     * can see it was raised and chase it — unlike the first serve, the
     * escalation decision has already been justified.
     */
    public function test_a_second_serve_is_recorded_even_if_its_sms_fails(): void
    {
        $this->fakeSms(false);
        $first = $this->firstServe();

        $this->artisan('spa:trigger-second-service')->assertExitCode(0);

        $second = SpaNotice::where('notice_type', 'second')
            ->where('file_number', $first->file_number)
            ->first();

        $this->assertNotNull($second);
        $this->assertFalse((bool) $second->sms_sent);
        $this->assertNull($second->sms_sent_at);
    }
}
