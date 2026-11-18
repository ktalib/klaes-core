<?php

namespace Tests\Unit;

use App\Models\Survey\SurveyPlanUpload as Plan;
use App\Models\User;
use App\Services\Survey\SurveyPlanStages as Stages;
use Tests\TestCase;

/** The four stages as Kunle set them (2026-11-18): `stage` is the last one done; the uploader never checks or signs. */
class SurveyPlanStepsTest extends TestCase
{
    public function test_the_open_step_follows_the_last_one_done(): void
    {
        $open = fn (int $stage) => [(new Plan(['stage' => $stage]))->step_no, (new Plan(['stage' => $stage]))->stage_label];

        $this->assertSame([2, 'Check'], $open(Plan::STAGE_UPLOADED));
        $this->assertSame([3, 'Final Survey Plan'], $open(Plan::STAGE_CORRECTION));
        $this->assertSame([4, 'Sign (SG/DSG)'], $open(Plan::STAGE_FINAL));
        $this->assertSame([4, 'Signed'], $open(Plan::STAGE_SIGNED));
    }

    public function test_the_uploader_cannot_check_or_sign_their_own_plan(): void
    {
        $u = new User();
        $u->id = 77;
        $plan = new Plan(['source' => 'klaes']);
        $plan->created_by = 77;

        $this->assertTrue(Stages::isOwnPlan($u, $plan));
        $this->assertTrue(Stages::isStaffUploader($u, $plan));
        $this->assertFalse(Stages::isChecker($u, $plan));
        $this->assertFalse(Stages::isSigner($u, $plan));
    }

    public function test_a_portal_plan_has_no_staff_uploader(): void
    {
        $u = new User();
        $u->id = 77;
        $plan = new Plan(['source' => 'portal']);
        $plan->created_by = 77;

        $this->assertFalse(Stages::isOwnPlan($u, $plan));
        $this->assertFalse(Stages::isStaffUploader($u, $plan));
    }
}
