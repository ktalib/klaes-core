<?php

namespace Tests\Feature;

use App\Services\Survey\PortalMapRequests;
use Illuminate\Support\Facades\Route;
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

    public function test_every_map_request_page_needs_a_surveyor_signed_in(): void
    {
        $routes = collect(Route::getRoutes()->getRoutesByName())
            ->filter(fn ($route, $name) => str_starts_with($name, 'surveyor-portal.map-requests.'));

        $this->assertCount(6, $routes);
        foreach ($routes as $name => $route) {
            $this->assertContains('auth:surveyor', $route->gatherMiddleware(), "$name is open to guests");
        }
    }

    public function test_a_guest_goes_to_the_portal_sign_in(): void
    {
        $this->get('/kssgof/map-requests')->assertRedirect('/kssgof/login');
        $this->get('/kssgof/map-requests/1/map')->assertRedirect('/kssgof/login');
    }

    public function test_the_portal_form_does_not_offer_staff_fields_or_land_data(): void
    {
        $form = file_get_contents(resource_path('views/surveyor_portal/map_requests/form.blade.php'));

        // File Indexing land use and the staff lookups need a staff login.
        $this->assertStringNotContainsString('land-use-points', $form);
        $this->assertStringNotContainsString('sdi-landuse', $form);
        $this->assertStringNotContainsString('name="priority"', $form);
        $this->assertStringNotContainsString('name="mda_id"', $form);
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
