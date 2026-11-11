<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SurveyorPortalCompanyRegistrationTest extends TestCase
{
    public function test_company_registration_form_has_state_dependent_lga_selection(): void
    {
        $response = $this->get('/mlpp-sg/company/register')
            ->assertOk()
            ->assertSee('Register a surveying company')
            ->assertSee('Your company’s next step starts here.')
            ->assertSee('ks-company-mobile-banner', false)
            ->assertDontSee('name="surveyor_name"', false)
            ->assertSee('name="addr_street"', false)
            ->assertSee('name="addr_district"', false)
            ->assertSee('name="addr_state"', false)
            ->assertSee('name="addr_lga"', false)
            ->assertDontSee('<select class="ks-input ab-street"', false)
            ->assertDontSee('<select class="ks-input ab-district"', false)
            ->assertSee('<select class="ks-input ab-lga"', false)
            ->assertSee('data-lgas-url="' . route('surveyor-portal.lookup.lgas') . '"', false)
            ->assertSee('required disabled', false);

        $html = $response->getContent();
        $statePosition = strpos($html, 'for="addr_state"');
        $lgaPosition = strpos($html, 'for="addr_lga"');
        $this->assertNotFalse($statePosition);
        $this->assertNotFalse($lgaPosition);
        $this->assertLessThan($lgaPosition, $statePosition);
    }

    public function test_portal_lga_lookup_is_public_and_rate_limited(): void
    {
        $route = Route::getRoutes()->getByName('surveyor-portal.lookup.lgas');

        $this->assertNotNull($route);
        $this->assertContains('throttle:60,1', $route->gatherMiddleware());
        $this->assertNotContains('auth:surveyor', $route->gatherMiddleware());
    }

    public function test_private_surveyor_form_has_no_free_text_company_option(): void
    {
        $response = $this->get('/mlpp-sg/register?company=yes')
            ->assertOk()
            ->assertSee('Only approved companies are listed.')
            ->assertSee(route('surveyor-portal.company.register'), false)
            ->assertDontSee('Public');

        $html = $response->getContent();
        preg_match('/<select[^>]*id="company_choice"[^>]*>(.*?)<\/select>/s', $html, $matches);

        $this->assertCount(2, $matches);
        $this->assertDoesNotMatchRegularExpression('/<option[^>]*value=["\']other["\']/i', $matches[1]);
        $this->assertDoesNotMatchRegularExpression('/data-surveyor=/i', $matches[1]);
        $this->assertStringNotContainsString('Principal Surveyor', $html);
        $this->assertSame(1, substr_count($html, route('surveyor-portal.company.register')));
    }

    public function test_registration_shows_only_the_company_status_choice(): void
    {
        $this->get('/mlpp-sg/register')
            ->assertOk()
            ->assertSee('Is your surveying company registered?')
            ->assertSee('Yes, register')
            ->assertSee('No, register a company')
            ->assertDontSee('Public')
            ->assertDontSee('Private');
    }

    public function test_company_status_choice_routes_to_the_correct_registration_form(): void
    {
        $this->get('/mlpp-sg/register?company=no')
            ->assertRedirect(route('surveyor-portal.company.register'));

        $this->get('/mlpp-sg/register?company=yes')
            ->assertOk()
            ->assertSee('id="company_choice"', false);

        $this->get('/mlpp-sg/register?type=Government')
            ->assertNotFound();
    }

    public function test_register_entry_points_open_the_company_status_dialog(): void
    {
        $this->get('/mlpp-sg')
            ->assertOk()
            ->assertSee('<dialog id="ksCompanyRegistrationDialog"', false)
            ->assertSee('data-register-dialog-trigger', false)
            ->assertSee('Yes, register')
            ->assertSee('No, register a company');

        $this->get('/mlpp-sg/login')
            ->assertOk()
            ->assertSee('data-register-dialog-trigger', false);
    }

    public function test_company_submission_is_public_and_rate_limited(): void
    {
        $route = Route::getRoutes()->getByName('surveyor-portal.company.submit');

        $this->assertNotNull($route);
        $this->assertContains('throttle:10,1', $route->gatherMiddleware());
        $this->assertNotContains('auth:surveyor', $route->gatherMiddleware());
    }

    public function test_registrations_view_compiles_without_a_company_principal_surveyor(): void
    {
        $source = file_get_contents(resource_path('views/survey_module/beacon_requests/index.blade.php'));

        $this->assertNotFalse($source);
        $this->assertStringNotContainsString('Principal Surveyor', $source);
        $this->assertNotEmpty(app('blade.compiler')->compileString($source));
    }

    public function test_admin_registrations_have_separate_tables_and_alphabetical_company_prefix_order(): void
    {
        $view = file_get_contents(resource_path('views/survey_module/beacon_requests/index.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Survey/BeaconRequestController.php'));

        $this->assertNotFalse($view);
        $this->assertNotFalse($controller);
        $this->assertStringContainsString('Company Registrations', $view);
        $this->assertStringContainsString('Surveyor Registrations', $view);
        $this->assertStringContainsString("\$registrationTab === 'companies'", $view);
        $this->assertStringContainsString("\$registrationTab === 'surveyors'", $view);
        $this->assertStringContainsString("->orderBy('beacon_letters')", $controller);
    }

    /** KSSGOF became MLPP-SG (user, 2026-11-10): links already sent out keep working. */
    public function test_old_kssgof_links_redirect_to_the_same_mlpp_sg_page(): void
    {
        $this->get('/kssgof')->assertStatus(301)->assertRedirect('/mlpp-sg');
        $this->get('/kssgof/register?company=yes')->assertStatus(301)->assertRedirect('/mlpp-sg/register?company=yes');
    }
}
