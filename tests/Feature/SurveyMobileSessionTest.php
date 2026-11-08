<?php

namespace Tests\Feature;

use App\Exceptions\Handler;
use App\Support\SurveyMobileApps;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Tests\TestCase;

class SurveyMobileSessionTest extends TestCase
{
    public function test_login_page_remembers_the_selected_field_app(): void
    {
        $this->get('/survey-module/mobile/login?app=plot')
            ->assertOk()
            ->assertSee('Plot Showing')
            ->assertCookie(SurveyMobileApps::COOKIE);
    }

    public function test_login_pages_use_the_selected_app_name(): void
    {
        foreach (['scf' => 'Farm Assessment', 'cfo' => 'Field Observations', 'plot' => 'Plot Showing'] as $key => $name) {
            $this->get('/survey-module/mobile/login?app=' . $key)
                ->assertOk()
                ->assertSee($name);
        }
    }

    public function test_expired_authentication_returns_to_the_same_field_app(): void
    {
        $this->withSession([SurveyMobileApps::SESSION => 'cfo'])
            ->get('/survey-module/mobile/observations')
            ->assertRedirect(route('survey-module.mobile.login', ['app' => 'cfo']));
    }

    public function test_logout_returns_to_the_selected_app_sign_in(): void
    {
        $this->withSession([SurveyMobileApps::SESSION => 'plot'])
            ->post('/survey-module/mobile/logout')
            ->assertRedirect(route('survey-module.mobile.login', ['app' => 'plot']))
            ->assertCookie(SurveyMobileApps::COOKIE);
    }

    public function test_app_context_cookie_identifies_the_app_after_session_expiry(): void
    {
        $request = Request::create('/survey-module/mobile/observations');
        $request->cookies->set(SurveyMobileApps::COOKIE, 'cfo');

        $this->assertSame('cfo', SurveyMobileApps::fromRequest($request));
    }

    public function test_mobile_csrf_expiry_uses_the_app_specific_page(): void
    {
        $request = Request::create(
            '/survey-module/mobile/cases',
            'POST',
            [],
            [SurveyMobileApps::COOKIE => 'cfo'],
            [],
            ['HTTP_ACCEPT' => 'text/html']
        );

        $response = app(Handler::class)->render($request, new TokenMismatchException());

        $this->assertSame(419, $response->getStatusCode());
        $this->assertStringContainsString('Field Observations', $response->getContent());
        $this->assertStringNotContainsString('Page Expired', $response->getContent());
    }

    public function test_mobile_json_csrf_expiry_returns_a_native_redirect_payload(): void
    {
        $request = Request::create(
            '/survey-module/mobile/cases',
            'POST',
            [],
            [SurveyMobileApps::COOKIE => 'plot'],
            [],
            ['HTTP_ACCEPT' => 'application/json']
        );

        $response = app(Handler::class)->render($request, new TokenMismatchException());
        $payload = json_decode($response->getContent(), true);

        $this->assertSame(419, $response->getStatusCode());
        $this->assertTrue($payload['session_expired']);
        $this->assertSame(route('survey-module.mobile.login', ['app' => 'plot']), $payload['redirect']);
    }
}
