<?php

namespace Tests\Unit\Laas;

use App\Models\Laas\LaasApplication;
use PHPUnit\Framework\TestCase;

/**
 * The LAAS stage machine, tested without touching the database.
 *
 * These are the properties the workflow hooks rest on. The hooks are dropped
 * into SurveyReportController, LandRecommendationController and
 * LandRofoController — controllers that run for every file in the registry, not
 * just portal ones — so "a repeated or late hook changes nothing" has to hold
 * unconditionally, not just in the happy path.
 */
class LaasStageMachineTest extends TestCase
{
    public function test_rank_follows_the_declared_order(): void
    {
        foreach (LaasApplication::ORDER as $index => $stage) {
            $this->assertSame($index, LaasApplication::rank($stage), "rank({$stage})");
        }
    }

    public function test_rejected_and_unknown_stages_have_no_rank(): void
    {
        $this->assertSame(-1, LaasApplication::rank(LaasApplication::STAGE_REJECTED));
        $this->assertSame(-1, LaasApplication::rank('not-a-stage'));
        $this->assertSame(-1, LaasApplication::rank(null));
    }

    public function test_every_stage_has_a_label(): void
    {
        foreach (array_merge(LaasApplication::ORDER, [LaasApplication::STAGE_REJECTED]) as $stage) {
            $this->assertArrayHasKey($stage, LaasApplication::LABELS, "label for {$stage}");
            $this->assertNotSame('', LaasApplication::LABELS[$stage]);
        }
    }

    public function test_advances_only_forward(): void
    {
        $application = new LaasApplication(['stage' => LaasApplication::STAGE_FILENO_ASSIGNED]);

        $this->assertTrue($application->canAdvanceTo(LaasApplication::STAGE_LAND12_RAISED));
        $this->assertTrue($application->canAdvanceTo(LaasApplication::STAGE_ROFO_SIGNED));

        $this->assertFalse($application->canAdvanceTo(LaasApplication::STAGE_DIRECTOR_APPROVED));
        $this->assertFalse($application->canAdvanceTo(LaasApplication::STAGE_SUBMITTED));
    }

    public function test_repeating_the_current_stage_is_refused(): void
    {
        // A re-saved Land 12 or a second RofO print fires the same hook again;
        // it must record nothing the second time.
        $application = new LaasApplication(['stage' => LaasApplication::STAGE_AT_CADASTRAL]);

        $this->assertFalse($application->canAdvanceTo(LaasApplication::STAGE_AT_CADASTRAL));
    }

    public function test_a_rejected_application_cannot_be_advanced_at_all(): void
    {
        $application = new LaasApplication(['stage' => LaasApplication::STAGE_REJECTED]);

        foreach (LaasApplication::ORDER as $stage) {
            $this->assertFalse($application->canAdvanceTo($stage), "rejected -> {$stage}");
        }
    }

    public function test_has_reached_covers_the_current_stage_and_everything_before_it(): void
    {
        $application = new LaasApplication(['stage' => LaasApplication::STAGE_LAND12_COMPLETED]);

        $this->assertTrue($application->hasReached(LaasApplication::STAGE_SUBMITTED));
        $this->assertTrue($application->hasReached(LaasApplication::STAGE_FILENO_ASSIGNED));
        $this->assertTrue($application->hasReached(LaasApplication::STAGE_LAND12_COMPLETED));

        $this->assertFalse($application->hasReached(LaasApplication::STAGE_RECOMMENDATION_APPROVED));
        $this->assertFalse($application->hasReached(LaasApplication::STAGE_ROFO_SIGNED));
    }

    public function test_has_reached_is_false_for_a_stage_off_the_main_line(): void
    {
        $application = new LaasApplication(['stage' => LaasApplication::STAGE_ROFO_SIGNED]);

        // Even at the end of the line, a rejected application was never reached.
        $this->assertFalse($application->hasReached(LaasApplication::STAGE_REJECTED));
    }

    public function test_draft_is_the_first_stage_and_rofo_signed_the_last(): void
    {
        $order = LaasApplication::ORDER;

        $this->assertSame(LaasApplication::STAGE_DRAFT, $order[0]);
        $this->assertSame(LaasApplication::STAGE_ROFO_SIGNED, end($order));
    }
}
