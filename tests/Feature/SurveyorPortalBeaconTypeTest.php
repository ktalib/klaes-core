<?php

namespace Tests\Feature;

use App\Models\Survey\SurveyBeaconRequest;
use App\Models\Survey\SurveyorPortalAccount;
use App\Services\Survey\BeaconRequests;
use App\Http\Controllers\Survey\LookupController;
use Illuminate\Http\UploadedFile;
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
            ->with($account, Mockery::on(fn (array $data) => $data['layout_type'] === 'Private' && $data['request_type'] === 'bulk' && $data['job_name'] === null && $data['amount_paid'] === '12500.50' && $data['payment_date'] === '2026-11-07'), Mockery::type(UploadedFile::class))
            ->andReturn($request);
        $this->app->instance(BeaconRequests::class, $service);

        $this->post(route('surveyor-portal.requests.store'), [
            'layout_type' => 'Government',
            'request_type' => 'bulk',
            'quantity' => 4,
            'amount_paid' => '12500.50',
            'payment_date' => '2026-11-07',
            'payment_evidence' => UploadedFile::fake()->create('payment.pdf', 100, 'application/pdf'),
        ])->assertRedirect(route('surveyor-portal.requests.show', $request));
    }

    public function test_beacon_form_displays_one_read_only_beacon_type(): void
    {
        $account = new SurveyorPortalAccount();
        $account->forceFill(['id' => 871235, 'surveyor_type' => 'Government', 'name' => 'Test Surveyor', 'company_name' => 'Test Surveying Ltd']);
        $this->actingAs($account, 'surveyor');

        $response = $this->get(route('surveyor-portal.requests.create', ['tab' => 'request']))
            ->assertOk()
            ->assertSee('Beacon Type')
            ->assertSee('Public')
            ->assertDontSee('Survey Type')
            ->assertDontSee('Name of Survey')
            ->assertDontSee('name="layout_type"', false)
            ->assertSee('aria-label="Portal navigation"', false)
            ->assertSee('Requested Beacons')
            ->assertSee('KNSSGOF')
            ->assertSee('assets/logo/knssgof-logo.png')
            ->assertSee('Beacon Request')
            ->assertSee('SURCON Payment Evidence')
            ->assertSee('name="payment_evidence"', false)
            ->assertSee('name="amount_paid"', false)
            ->assertSee('name="payment_date"', false)
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('made to the State’s surveyors body (SURCON)')
            ->assertSee('Beacons')
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
        $this->assertLessThan(
            strpos($response->getContent(), 'Request Type'),
            strpos($response->getContent(), 'Number of Beacons')
        );

        preg_match('/<input[^>]*name="quantity"[^>]*>/', $response->getContent(), $quantityField);
        $this->assertNotEmpty($quantityField);
        $this->assertDoesNotMatchRegularExpression('/\bvalue="4"/', $quantityField[0]);
    }

    public function test_registration_page_uses_the_corrected_knssgof_banner(): void
    {
        $this->get(route('surveyor-portal.register'))
            ->assertOk()
            ->assertSee('assets/logo/knssgof-register-banner.png');

        $this->get(route('surveyor-portal.register', ['company' => 'yes']))
            ->assertOk()
            ->assertSee('assets/logo/knssgof-register-banner.png')
            ->assertSee("KNSSGOF — Kano State Surveyor General's Office Portal", false);
    }

    public function test_beacon_form_has_searchable_district_and_lga_based_pin(): void
    {
        $account = new SurveyorPortalAccount();
        $account->forceFill(['id' => 871236, 'surveyor_type' => 'Private', 'name' => 'Test Surveyor']);
        $this->actingAs($account, 'surveyor');

        $this->get(route('surveyor-portal.requests.create', ['tab' => 'request']))
            ->assertOk()
            ->assertSee('id="district" name="district"', false)
            ->assertSee('data-url="' . route('surveyor-portal.lookup.districts') . '"', false)
            ->assertSee('district_other')
            ->assertSee('placeAtLga')
            ->assertSee('Pin on Map')
            ->assertSee('pinLga.addEventListener')
            ->assertSee('Search or select district');
    }

    public function test_beacons_tab_shows_only_issued_beacon_numbers_for_the_signed_in_surveyor(): void
    {
        $account = new SurveyorPortalAccount();
        $account->forceFill(['id' => 871239, 'surveyor_type' => 'Private', 'name' => 'Test Surveyor']);
        $this->actingAs($account, 'surveyor');

        $this->get(route('surveyor-portal.requests.create'))
            ->assertOk()
            ->assertSee('Requested Beacons')
            ->assertSee('Beacon Request')
            ->assertSee('Beacons')
            ->assertSee('aria-selected="true"', false)
            ->assertDontSee('id="requestTypeDialog"', false)
            ->assertSee('No issued beacons yet.');

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
            'request_type' => 'bulk',
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
            ->with($account, \Mockery::on(fn (array $data) => $data['request_type'] === 'bulk' && $data['district'] === 'New District' && ! array_key_exists('district_other', $data)), Mockery::type(UploadedFile::class))
            ->andReturn($request);
        $this->app->instance(BeaconRequests::class, $service);

        $this->post(route('surveyor-portal.requests.store'), [
            'request_type' => 'bulk',
            'quantity' => 1,
            'district' => 'Other',
            'district_other' => ' New   District ',
            'amount_paid' => '2500',
            'payment_date' => '2026-11-07',
            'payment_evidence' => UploadedFile::fake()->image('receipt.png'),
        ])->assertRedirect(route('surveyor-portal.requests.show', $request));
    }

    public function test_a_portal_beacon_request_requires_payment_evidence(): void
    {
        $account = new SurveyorPortalAccount();
        $account->forceFill(['id' => 871240, 'surveyor_type' => 'Private', 'name' => 'Test Surveyor']);
        $this->actingAs($account, 'surveyor');

        $service = Mockery::mock(BeaconRequests::class);
        $service->shouldNotReceive('fromPortal');
        $this->app->instance(BeaconRequests::class, $service);

        $this->post(route('surveyor-portal.requests.store'), ['request_type' => 'bulk', 'quantity' => 1])
            ->assertSessionHasErrors('payment_evidence');
    }

    public function test_a_single_beacon_request_requires_a_district_and_lga(): void
    {
        $account = new SurveyorPortalAccount();
        $account->forceFill(['id' => 871241, 'surveyor_type' => 'Private', 'name' => 'Test Surveyor']);
        $this->actingAs($account, 'surveyor');

        $service = Mockery::mock(BeaconRequests::class);
        $service->shouldNotReceive('fromPortal');
        $this->app->instance(BeaconRequests::class, $service);

        $this->post(route('surveyor-portal.requests.store'), [
            'request_type' => 'single',
            'quantity' => 1,
            'amount_paid' => '1000',
            'payment_date' => '2026-11-07',
            'payment_evidence' => UploadedFile::fake()->create('payment.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors(['district', 'lga']);
    }

    public function test_a_bulk_beacon_request_can_omit_location(): void
    {
        $account = new SurveyorPortalAccount();
        $account->forceFill(['id' => 871242, 'surveyor_type' => 'Private', 'name' => 'Test Surveyor']);
        $this->actingAs($account, 'surveyor');

        $request = new SurveyBeaconRequest();
        $request->setAttribute('id', 871242);
        $request->setAttribute('request_no', 'PORTAL-TEST-3');

        $service = Mockery::mock(BeaconRequests::class);
        $service->shouldReceive('fromPortal')
            ->once()
            ->with($account, Mockery::on(fn (array $data) => $data['request_type'] === 'bulk' && ($data['district'] ?? null) === null && ($data['lga'] ?? null) === null && $data['amount_paid'] === '1000' && $data['payment_date'] === '2026-11-07'), Mockery::type(UploadedFile::class))
            ->andReturn($request);
        $this->app->instance(BeaconRequests::class, $service);

        $this->post(route('surveyor-portal.requests.store'), [
            'request_type' => 'bulk',
            'quantity' => 1,
            'amount_paid' => '1000',
            'payment_date' => '2026-11-07',
            'payment_evidence' => UploadedFile::fake()->create('payment.pdf', 100, 'application/pdf'),
        ])->assertRedirect(route('surveyor-portal.requests.show', $request));
    }

    public function test_a_portal_beacon_request_requires_payment_amount_and_date(): void
    {
        $account = new SurveyorPortalAccount();
        $account->forceFill(['id' => 871243, 'surveyor_type' => 'Private', 'name' => 'Test Surveyor']);
        $this->actingAs($account, 'surveyor');

        $service = Mockery::mock(BeaconRequests::class);
        $service->shouldNotReceive('fromPortal');
        $this->app->instance(BeaconRequests::class, $service);

        $this->post(route('surveyor-portal.requests.store'), [
            'request_type' => 'bulk',
            'quantity' => 1,
            'payment_evidence' => UploadedFile::fake()->create('payment.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors(['amount_paid', 'payment_date']);
    }
}
