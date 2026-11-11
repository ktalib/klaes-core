<?php

namespace Tests\Feature;

use App\Http\Controllers\SurveyorPortal\MapRequestController;
use App\Services\Survey\PortalMapRequests;
use App\Models\Sdi\SdiMapRequest;
use App\Models\Sdi\SdiMapType;
use App\Models\Sdi\SdiMda;
use Illuminate\Support\Facades\Route;
use Mockery;
use Tests\TestCase;

/** Map requests on the KSSGOF portal (user, 2026-11-06). Reads only; the saving path was run in a rollback harness. */
class SurveyorPortalMapRequestTest extends TestCase
{
    private array $square = ['type' => 'Polygon', 'coordinates' => [[[8.5, 12.0], [8.6, 12.0], [8.6, 12.1], [8.5, 12.1], [8.5, 12.0]]]];

    public function test_only_the_drawn_outline_is_kept(): void
    {
        $sent = json_encode(['type' => 'Feature', 'evil' => 1, 'properties' => ['x' => '</script><script>alert(1)</script>'], 'geometry' => $this->square]);

        $clean = PortalMapRequests::cleanGeometry($sent);

        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringNotContainsString('evil', $clean);
        // Equals: JSON writes 12.0 as 12.
        $this->assertEquals($this->square, json_decode($clean, true)['geometry']);
    }

    public function test_what_is_not_an_area_is_refused(): void
    {
        foreach (['', 'hello', '{}', '{"type":"Point","coordinates":[8,12]}', '{"type":"Polygon","coordinates":[[[8,12],[9,12]]]}', '{"type":"Polygon","coordinates":[[[999,12],[8,12],[9,13],[999,12]]]}'] as $sent) {
            $this->assertNull(PortalMapRequests::cleanGeometry($sent), $sent);
        }
    }

    public function test_only_map_request_submission_is_public(): void
    {
        $routes = collect(Route::getRoutes()->getRoutesByName())
            ->filter(fn ($route, $name) => str_starts_with($name, 'surveyor-portal.map-requests.'));

        $this->assertCount(6, $routes);
        foreach ($routes as $name => $route) {
            $requiresSurveyor = in_array($name, [
                'surveyor-portal.map-requests.index',
                'surveyor-portal.map-requests.show',
                'surveyor-portal.map-requests.request-file',
                'surveyor-portal.map-requests.map',
            ], true);

            if ($requiresSurveyor) {
                $this->assertContains('auth:surveyor', $route->gatherMiddleware(), "$name must require a surveyor");
            } else {
                $this->assertNotContains('auth:surveyor', $route->gatherMiddleware(), "$name should be available without an account");
            }
        }
    }

    public function test_a_guest_can_open_the_map_request_form_without_creating_an_account(): void
    {
        $this->get('/mlpp-sg/map-requests/new')
            ->assertOk()
            ->assertSee('Your Name')
            ->assertSee('Phone')
            ->assertSee('Email (optional)');
        $this->get('/mlpp-sg/map-requests/1/map')->assertRedirect('/mlpp-sg/login');
    }

    public function test_a_guest_can_submit_a_map_request_with_contact_details(): void
    {
        $mapTypeId = SdiMapType::where('is_active', true)->value('id');
        $this->assertNotNull($mapTypeId, 'The map request form needs an active map type.');
        $mdaId = (int) SdiMda::where('is_active', true)->value('id');
        $this->assertNotSame(0, $mdaId, 'The map request form needs an active MDA.');

        $request = new SdiMapRequest();
        $request->setAttribute('id', 771234);
        $request->setAttribute('request_ref', 'MR-PORTAL-TEST');

        $service = Mockery::mock(PortalMapRequests::class);
        $service->shouldReceive('submit')
            ->once()
            ->with(null, Mockery::on(fn (array $data) =>
                $data['contact_name'] === 'Guest Requester'
                && $data['contact_phone'] === '08030000000'
                && $data['contact_email'] === null
                && $data['mda_id'] === $mdaId
                && ! isset($data['requester_other'])
                && ! isset($data['request_file'])
            ), null)
            ->andReturn($request);
        $this->app->instance(PortalMapRequests::class, $service);

        $this->post(route('surveyor-portal.map-requests.store'), [
            'contact_name' => 'Guest Requester',
            'contact_phone' => '08030000000',
            'contact_email' => '',
            'mda_id' => (string) $mdaId,
            'map_type_id' => $mapTypeId,
            'map_name' => 'Guest map request',
            'output_formats' => ['pdf'],
            'geometry_geojson' => json_encode(['type' => 'Feature', 'geometry' => $this->square]),
        ])->assertRedirect(route('surveyor-portal.map-requests.create'))
            ->assertSessionHas('status', 'Map request MR-PORTAL-TEST has gone to the Production Unit.');
    }

    public function test_the_portal_form_does_not_offer_staff_fields_or_land_data(): void
    {
        $form = file_get_contents(resource_path('views/surveyor_portal/map_requests/form.blade.php'));

        // File Indexing land use and the staff lookups need a staff login.
        $this->assertStringNotContainsString('land-use-points', $form);
        $this->assertStringNotContainsString('sdi-landuse', $form);
        $this->assertStringNotContainsString('name="priority"', $form);
    }

    /** What a request needs apart from its MDA. */
    private function mapRequestFields(array $mda): array
    {
        return $mda + [
            'contact_name' => 'Guest Requester',
            'contact_phone' => '08030000000',
            'map_type_id' => SdiMapType::where('is_active', true)->value('id'),
            'map_name' => 'Guest map request',
            'output_formats' => ['pdf'],
            'geometry_geojson' => json_encode(['type' => 'Feature', 'geometry' => $this->square]),
        ];
    }

    private function refusesWithoutSaving(array $mda, string $field): void
    {
        $service = Mockery::mock(PortalMapRequests::class);
        $service->shouldNotReceive('submit');
        $this->app->instance(PortalMapRequests::class, $service);

        $this->post(route('surveyor-portal.map-requests.store'), $this->mapRequestFields($mda))->assertSessionHasErrors($field);
    }

    public function test_the_form_lists_active_mdas_and_one_other(): void
    {
        $page = $this->get('/mlpp-sg/map-requests/new')->assertOk()->assertSee('name="mda_id"', false)->getContent();

        $this->assertSame(1, substr_count($page, '>Other (specify)</option>'));
        $this->assertStringContainsString(e(SdiMda::where('is_active', true)->value('name')), $page);
    }

    public function test_an_mda_is_required(): void
    {
        $this->refusesWithoutSaving([], 'mda_id');
    }

    public function test_an_mda_must_be_a_listed_one(): void
    {
        $this->refusesWithoutSaving(['mda_id' => 'not-a-number'], 'mda_id');
        $this->refusesWithoutSaving(['mda_id' => '99999999'], 'mda_id');
    }

    public function test_other_needs_the_body_named(): void
    {
        $this->refusesWithoutSaving(['mda_id' => MapRequestController::MDA_OTHER, 'requester_other' => '   '], 'requester_other');
    }

    public function test_other_keeps_the_typed_body_and_a_listed_mda_typed_again_becomes_that_mda(): void
    {
        $mda = SdiMda::where('is_active', true)->first();
        $request = new SdiMapRequest();
        $request->setAttribute('id', 771234);
        $request->setAttribute('request_ref', 'MR-PORTAL-TEST');

        $service = Mockery::mock(PortalMapRequests::class);
        $service->shouldReceive('submit')->once()
            ->with(null, Mockery::on(fn (array $d) => $d['mda_id'] === null && $d['requester_other'] === 'A Body Not On The List'), null)->andReturn($request);
        $service->shouldReceive('submit')->once()
            ->with(null, Mockery::on(fn (array $d) => $d['mda_id'] === (int) $mda->id && $d['requester_other'] === null), null)->andReturn($request);
        $this->app->instance(PortalMapRequests::class, $service);

        $other = ['mda_id' => MapRequestController::MDA_OTHER];
        $this->post(route('surveyor-portal.map-requests.store'), $this->mapRequestFields($other + ['requester_other' => ' A Body  Not On The List ']))->assertSessionHasNoErrors();
        $this->post(route('surveyor-portal.map-requests.store'), $this->mapRequestFields($other + ['requester_other' => mb_strtoupper($mda->name)]))->assertSessionHasNoErrors();
    }

    public function test_kssgof_admin_has_map_requests_as_its_third_item(): void
    {
        $menu = file_get_contents(resource_path('views/admin/menu/partials/modules/survey.blade.php'));

        $this->assertSame(1, substr_count($menu, "route('survey-module.beacon-requests.index', ['tab' => 'maps'])"));
        $this->assertLessThan(strpos($menu, '<span>Map Requests</span>'), strpos($menu, '<span>Beacon Requests</span>'));
        // Lighting one item must not light Beacon Requests too.
        $this->assertStringContainsString('$ksOn && ! $ksReg && ! $ksMaps', $menu);
    }
}
