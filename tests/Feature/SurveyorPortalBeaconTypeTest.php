<?php

namespace Tests\Feature;

use App\Models\Survey\SurveyBeaconRequest;
use App\Models\Survey\SurveyorPortalAccount;
use App\Services\Survey\BeaconRequests;
use App\Http\Controllers\Survey\LookupController;
use Mockery;
use Tests\TestCase;

class SurveyorPortalBeaconTypeTest extends TestCase
{
    public function test_beacon_type_is_taken_from_the_account_not_the_form_submission(): void
    {
        $account = new SurveyorPortalAccount();
        $account->forceFill(['id' => 871234, 'surveyor_type' => 'Private', 'name' => 'Test Surveyor']);
        $this->actingAs($account, 'surveyor');

        $request = new SurveyBeaconRequest();
        $request->setAttribute('id', 871234);
        $request->setAttribute('request_no', 'PORTAL-TEST-1');

        $service = Mockery::mock(BeaconRequests::class);
        $service->shouldReceive('fromPortal')
            ->once()
            ->with($account, Mockery::on(fn (array $data) => $data['layout_type'] === 'Private' && $data['job_name'] === null))
            ->andReturn($request);
        $this->app->instance(BeaconRequests::class, $service);

        $this->post(route('surveyor-portal.requests.store'), [
            'layout_type' => 'Government',
            'quantity' => 4,
        ])->assertRedirect(route('surveyor-portal.requests.show', $request));
    }

    public function test_beacon_form_displays_one_read_only_beacon_type(): void
    {
        $account = new SurveyorPortalAccount();
        $account->forceFill(['id' => 871235, 'surveyor_type' => 'Government', 'name' => 'Test Surveyor', 'company_name' => 'Test Surveying Ltd']);
        $this->actingAs($account, 'surveyor');

        $response = $this->get(route('surveyor-portal.requests.create'))
            ->assertOk()
            ->assertSee('Beacon Type')
            ->assertSee('Public')
            ->assertDontSee('Survey Type')
            ->assertDontSee('Name of Survey')
            ->assertDontSee('name="layout_type"', false)
            ->assertSee('aria-label="Portal navigation"', false)
            ->assertSee('Request Beacons')
            ->assertDontSee('>My Requests<', false)
            ->assertDontSee('>Request a Map<', false)
            ->assertSee('Workspace');

        $this->assertLessThan(
            strpos($response->getContent(), 'Test Surveying Ltd'),
            strpos($response->getContent(), 'Test Surveyor')
        );
        $this->assertLessThan(
            strpos($response->getContent(), '<span class="ks-label">Surveyor</span>'),
            strpos($response->getContent(), '<span class="ks-label">Company</span>')
        );

        preg_match('/<input[^>]*name="quantity"[^>]*>/', $response->getContent(), $quantityField);
        $this->assertNotEmpty($quantityField);
        $this->assertDoesNotMatchRegularExpression('/\bvalue="4"/', $quantityField[0]);
    }

    public function test_beacon_form_has_searchable_district_and_lga_based_pin(): void
    {
        $account = new SurveyorPortalAccount();
        $account->forceFill(['id' => 871236, 'surveyor_type' => 'Private', 'name' => 'Test Surveyor']);
        $this->actingAs($account, 'surveyor');

        $this->get(route('surveyor-portal.requests.create'))
            ->assertOk()
            ->assertSee('id="district" name="district"', false)
            ->assertSee('data-url="' . route('surveyor-portal.lookup.districts') . '"', false)
            ->assertSee('district_other')
            ->assertSee('placeAtLga')
            ->assertSee('Search or select district');

        $this->assertContains('Kunchi', \App\Services\Survey\CompensationListReader::LGAS);
        $this->assertNotContains('Ghari', \App\Services\Survey\CompensationListReader::LGAS);
    }

    public function test_other_district_requires_a_specified_name(): void
    {
        $account = new SurveyorPortalAccount();
        $account->forceFill(['id' => 871237, 'surveyor_type' => 'Private', 'name' => 'Test Surveyor']);
        $this->actingAs($account, 'surveyor');

        $service = \Mockery::mock(BeaconRequests::class);
        $service->shouldNotReceive('fromPortal');
        $this->app->instance(BeaconRequests::class, $service);

        $this->post(route('surveyor-portal.requests.store'), [
            'quantity' => 1,
            'district' => 'Other',
        ])->assertSessionHasErrors('district_other');
    }

    public function test_other_district_is_stored_as_the_specified_name(): void
    {
        $account = new SurveyorPortalAccount();
        $account->forceFill(['id' => 871238, 'surveyor_type' => 'Private', 'name' => 'Test Surveyor']);
        $this->actingAs($account, 'surveyor');

        $request = new SurveyBeaconRequest();
        $request->setAttribute('id', 871238);
        $request->setAttribute('request_no', 'PORTAL-TEST-2');

        $service = \Mockery::mock(BeaconRequests::class);
        $service->shouldReceive('fromPortal')
            ->once()
            ->with($account, \Mockery::on(fn (array $data) => $data['district'] === 'New District' && ! array_key_exists('district_other', $data)))
            ->andReturn($request);
        $this->app->instance(BeaconRequests::class, $service);

        $this->post(route('surveyor-portal.requests.store'), [
            'quantity' => 1,
            'district' => 'Other',
            'district_other' => ' New   District ',
        ])->assertRedirect(route('surveyor-portal.requests.show', $request));
    }
}
