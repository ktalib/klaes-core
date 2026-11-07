<?php

namespace Tests\Unit;

use App\Services\Survey\PrivateRegisterLines;
use Tests\TestCase;

/** Surveyor's Report (Private) rows from the Private lines of the Beacon Register (user, 2026-11-06). */
class PrivateRegisterLinesTest extends TestCase
{
    private array $group = ['key' => 'RES-2014-1972', 'file_number' => 'RES-2014-1972', 'numbers' => 'A5445J A5446J', 'beacons' => 3, 'points' => 2];

    public function test_a_file_number_is_the_same_file_however_it_was_typed(): void
    {
        $this->assertSame('RES-2014-1972', PrivateRegisterLines::key(' res-2014-1972 '));
        $this->assertNull(PrivateRegisterLines::key('   '));
        $this->assertNull(PrivateRegisterLines::key(null));
    }

    public function test_every_beacon_of_a_register_line_counts_as_in_the_register(): void
    {
        $rec = PrivateRegisterLines::summary($this->group);

        $this->assertSame(3, $rec['beacons']);
        $this->assertSame(3, $rec['in_register']);
        $this->assertSame(0, $rec['flagged']);
        $this->assertSame(2, $rec['points']);
    }

    public function test_the_register_holds_no_traverse_so_those_stay_empty(): void
    {
        $rec = PrivateRegisterLines::summary($this->group);

        foreach (['area', 'perimeter', 'misclosure', 'sketch'] as $k) {
            $this->assertNull($rec[$k], $k);
        }
        $this->assertSame(0, $rec['readings']);
    }

    public function test_coordinate_register_knows_private_points_and_its_view_compiles(): void
    {
        $this->assertArrayHasKey('private', \App\Http\Controllers\Survey\CoordinateRegisterController::SOURCES);
        $this->assertTrue(view()->exists('survey_module.workflow.coordinate_register'));

        // An apostrophe in the page's description once took this page down (2026-11-06).
        $source = file_get_contents(resource_path('views/survey_module/workflow/coordinate_register.blade.php'));
        $compiled = app('blade.compiler')->compileString($source);
        $this->assertNotFalse(token_get_all($compiled, TOKEN_PARSE));

        $table = file_get_contents(resource_path('views/survey_module/partials/sections/_page-coordinate-register.blade.php'));
        $compiledTable = app('blade.compiler')->compileString($table);
        $this->assertNotFalse(token_get_all($compiledTable, TOKEN_PARSE));
        $this->assertStringNotContainsString('<th>Beacon Group</th>', $table);
        $this->assertStringContainsString('class="cr-coordinate-group-toggle"', $table);
        $this->assertStringContainsString('class="cr-coordinate-group-row" data-group="{{ $groupLetter }}" hidden', $table);
        $this->assertStringContainsString('<strong>{{ $p->point_id }}</strong>', $table);

        $coordinateController = file_get_contents(app_path('Http/Controllers/Survey/CoordinateRegisterController.php'));
        $this->assertSame(4, substr_count($coordinateController, 'AS group_letter'));
        $this->assertStringContainsString('beacon_from), 1', $coordinateController);

        // The Beacon Register's By issue view already groups and collapses issue rows by the leading letter series.
        $beaconRows = file_get_contents(resource_path('views/survey_module/partials/sections/_beacon_issue_rows.blade.php'));
        $this->assertStringContainsString('class="br-group"', $beaconRows);
        $this->assertStringContainsString('data-series="{{ $group->letters }}"', $beaconRows);

        $beaconView = file_get_contents(resource_path('views/survey_module/partials/sections/_page-beacon-register.blade.php'));
        $compiledBeaconView = app('blade.compiler')->compileString($beaconView);
        $this->assertNotFalse(token_get_all($compiledBeaconView, TOKEN_PARSE));
    }

    public function test_coordinate_groups_include_beacons_and_control_points_by_the_leading_letter(): void
    {
        $points = ['A5428B', 'A5433J', 'KAA001', 'KB150', 'KN302', 'K2400', 'K2449'];
        $groups = array_map(fn ($point) => strtoupper(substr($point, 0, 1)), $points);

        $this->assertSame(['A', 'A', 'K', 'K', 'K', 'K', 'K'], $groups);

        $controller = file_get_contents(app_path('Http/Controllers/Survey/CoordinateRegisterController.php'));
        $this->assertStringContainsString('group_letter, COUNT(*) AS points', $controller);
        $this->assertStringContainsString("orderBy('group_letter')", $controller);

        $beaconController = file_get_contents(app_path('Http/Controllers/Survey/BeaconRegisterController.php'));
        $this->assertStringContainsString('LEFT(beacon_series, 1)', $beaconController);
    }

    public function test_farm_capture_choices_use_the_requested_labels(): void
    {
        $menu = file_get_contents(resource_path('views/survey_module/partials/_capture_land_button.blade.php'));
        $this->assertStringContainsString('<strong>Capture New Farm</strong>', $menu);
        $this->assertStringContainsString('<strong>Capture Existing Farm Record</strong>', $menu);
        $this->assertStringNotContainsString('Capture Existing Farmers List', $menu);

        $page = file_get_contents(resource_path('views/survey_module/compensation/capture_list.blade.php'));
        $this->assertStringContainsString("'Capture Existing Farm Record — KLAES'", $page);
    }

    public function test_the_report_form_has_the_tp_dropdown_and_title_beside_the_file(): void
    {
        $form = file_get_contents(resource_path('views/survey_module/workflow/change_reports/_form.blade.php'));

        $this->assertStringContainsString('name="plan_choice"', $form);
        $this->assertStringContainsString('name="plan_no_other"', $form);
        $this->assertStringNotContainsString('name="plan_no"', $form);
        $this->assertStringContainsString('<label>TP No.</label>', $form);
        $this->assertStringNotContainsString('<label>Plan No.</label>', $form);
        $this->assertStringNotContainsString('<div class="form-group full"><label>File Title</label>', $form);
    }

    public function test_a_tp_number_is_the_same_whatever_its_case_or_spaces(): void
    {
        $key = fn ($v) => \App\Http\Controllers\Survey\ChangeReportController::tpKey($v);

        $this->assertSame($key('TP/KNUPDA/338A'), $key(' tp/knupda / 338a '));
        $this->assertNotSame($key('TP/K/338A'), $key('TP/K/338B'));
    }

    public function test_ground_check_and_confirmation_forms_are_buttons_below_record_plot_showing(): void
    {
        $page = file_get_contents(resource_path('views/survey_module/compensation/plot_confirmations.blade.php'));

        // The dropdown holds its two choices, and only those.
        $menu = substr($page, strpos($page, 'role="menu" class="absolute right-0 z-50 w-48'));
        $menu = substr($menu, 0, strpos($menu, '</div>'));
        $this->assertSame(2, substr_count($menu, 'role="menuitem"'));
        $this->assertStringContainsString('> New</button>', $menu);
        $this->assertStringContainsString('> Old Register</button>', $menu);

        // The two buttons come straight after it, in this order.
        $ground = strpos($page, "onclick=\"pcEntry('new', 'check')\"");
        $confirm = strpos($page, 'onclick="pcConfirmOpen()"');
        $this->assertNotFalse($ground);
        $this->assertGreaterThan(strpos($page, 'role="menu" class="absolute right-0 z-50 w-48'), $ground);
        $this->assertGreaterThan($ground, $confirm);
        $this->assertStringNotContainsString('pcFormTabs', $page);
    }

    public function test_the_counts_open_the_registers_on_the_file_or_the_one_beacon(): void
    {
        $links = PrivateRegisterLines::links($this->group);
        $this->assertStringContainsString('beacon-register?q=RES-2014-1972', $links['beacons']);
        $this->assertStringContainsString('coordinate-register?', $links['coordinates']);
        $this->assertStringContainsString('source=beacon', $links['coordinates']);

        $noFile = PrivateRegisterLines::links(['file_number' => null, 'numbers' => 'A5436J']);
        $this->assertStringContainsString('q=A5436J', $noFile['beacons']);
    }
}
