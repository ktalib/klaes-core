<?php

namespace Tests\Feature;

use App\Models\Survey\SurveyorPortalAccount;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * MLPP-SG sign-in (user, 2026-11-11): companies and surveyors sign in at their own URLs, every Sign in link asks
 * "company or surveyor?" first, and each page lets in its own kind of login only.
 */
class SurveyorPortalLoginKindTest extends TestCase
{
    public function test_login_asks_company_or_surveyor_and_each_has_its_own_page(): void
    {
        $this->get('/mlpp-sg/login')
            ->assertOk()
            ->assertSee('Sign in as')
            ->assertSee(route('surveyor-portal.login.as', 'surveyor'), false)
            ->assertSee(route('surveyor-portal.login.as', 'company'), false);

        $this->get('/mlpp-sg/login/company')
            ->assertOk()
            ->assertSee('Sign in as a company')
            ->assertSee('name="as" value="company"', false);

        $this->get('/mlpp-sg/login/surveyor')
            ->assertOk()
            ->assertSee('Sign in as a surveyor')
            ->assertSee('name="as" value="surveyor"', false);

        $this->get('/mlpp-sg/login/anything')->assertNotFound();
    }

    public function test_every_public_sign_in_link_opens_the_choice_pop_up(): void
    {
        $this->get('/mlpp-sg')
            ->assertOk()
            ->assertSee('<dialog id="ksSignInDialog"', false)
            ->assertSee('data-signin-dialog-trigger', false);
    }

    public function test_a_login_is_refused_on_the_other_kinds_page(): void
    {
        $company = SurveyorPortalAccount::query()->where('surveyor_type', SurveyorPortalAccount::COMPANY)->first();
        $surveyor = SurveyorPortalAccount::query()->surveyors()->whereNotNull('username')->first();
        if (! $company || ! $surveyor) {
            $this->markTestSkipped('Needs a company login and a surveyor login on record.');
        }
        // The password is taken as right, so only the kind check can refuse; refused before any code is sent.
        Hash::shouldReceive('check')->andReturn(true);

        $this->from('/mlpp-sg/login/surveyor')
            ->post('/mlpp-sg/login', ['username' => $company->username, 'password' => 'x', 'as' => 'surveyor'])
            ->assertRedirect('/mlpp-sg/login/surveyor')
            ->assertSessionHasErrors(['username' => 'This is a company\'s login. Use "Sign in as a company".']);

        $this->from('/mlpp-sg/login/company')
            ->post('/mlpp-sg/login', ['username' => $surveyor->username, 'password' => 'x', 'as' => 'company'])
            ->assertRedirect('/mlpp-sg/login/company')
            ->assertSessionHasErrors(['username' => 'This is a surveyor\'s login. Use "Sign in as a surveyor".']);

        $this->post('/mlpp-sg/login', ['username' => $company->username, 'password' => 'x'])
            ->assertSessionHasErrors('as');
    }
}
