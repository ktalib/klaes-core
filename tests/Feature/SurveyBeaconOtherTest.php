<?php

namespace Tests\Feature;

use App\Services\Survey\ItsBeacons;
use Tests\TestCase;

/**
 * Numbers typed under Other need not be in the Beacon Register (user, 2026-11-06, "for now"). The rule is off
 * only for the I to S and Computation File forms, which have Other; the Cadastral job form keeps it.
 */
class SurveyBeaconOtherTest extends TestCase
{
    private array $outside = [['no' => 'ZZZ9999', 'series' => 'ZZZ|', 'seq' => 9999]];

    public function test_the_register_rule_stays_on_by_default_for_cadastral(): void
    {
        $this->assertSame(['ZZZ 9999 is not in the Beacon Register.'], app(ItsBeacons::class)->problems($this->outside, null, 'cadastral_survey_job_id', 1));
    }

    public function test_the_forms_with_other_can_turn_it_off(): void
    {
        $this->assertSame([], app(ItsBeacons::class)->problems($this->outside, null, 'survey_its_id', 1, false));
    }

    public function test_the_i_to_s_form_posts_the_searchable_fields(): void
    {
        $form = file_get_contents(resource_path('views/survey_module/partials/sections/_page-gkn-its-form.blade.php'));

        $this->assertStringContainsString('name="beacon_picks[]"', $form);
        $this->assertStringContainsString('name="beacon_other"', $form);
        $this->assertStringNotContainsString('name="beacon_numbers"', $form);
    }

    public function test_kssgof_admin_is_a_sidebar_group_of_its_two_lists(): void
    {
        $menu = file_get_contents(resource_path('views/admin/menu/partials/modules/survey.blade.php'));
        $page = file_get_contents(resource_path('views/survey_module/beacon_requests/index.blade.php'));

        $this->assertSame(1, substr_count($menu, 'data-section="survey-kssgof"'));
        $this->assertSame(1, substr_count($menu, "route('survey-module.beacon-requests.index')"));
        $this->assertSame(1, substr_count($menu, "route('survey-module.beacon-requests.index', ['tab' => 'registrations'])"));
        $this->assertStringContainsString('<span>Registrations</span>', $menu);
        $this->assertStringContainsString('<span>Beacon Requests</span>', $menu);
        // Items on the sidebar, so no tabs on the page and no tab card on the way to it.
        $this->assertStringNotContainsString('data-tab-card="survey-beacon-requests"', $menu);
        $this->assertStringNotContainsString('class="tabs"', $page);
    }
}
