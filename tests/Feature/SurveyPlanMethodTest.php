<?php

namespace Tests\Feature;

use App\Http\Controllers\Survey\LookupController;
use App\Models\Survey\SurveyPlanUpload as Plan;
use App\Services\Survey\CompensationListReader;
use App\Services\Survey\SurveyPlanUploads;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Survey Plan Management (Recording 5, 2026-11-16): a Manual Upload needs the file and one reference beacon; a Digital
 * Plotting needs the plotted beacons (at least three, X1 first); its plan file is optional (2026-11-16). Validation only:
 * nothing is saved.
 */
class SurveyPlanMethodTest extends TestCase
{
    private function base(): array
    {
        return [
            'unit' => Plan::UNIT_GKN, 'plan_type' => 'Application Plan', 'district' => LookupController::options('districts')[0] ?? 'other',
            'district_other' => 'Test District', 'lga' => CompensationListReader::LGAS[0], 'drawn_by' => 'A Surveyor', 'coord_system' => '26332',
        ];
    }

    private function parcel(): array
    {
        return [['beacon_no' => 'pb 234', 'x' => '441344.783', 'y' => '1338968.261'], ['beacon_no' => '', 'x' => '441329.858', 'y' => '1338969.755'],
            ['beacon_no' => '', 'x' => '441332.037', 'y' => '1338991.647'], ['beacon_no' => '', 'x' => '441346.962', 'y' => '1338990.153']];
    }

    public function test_a_digital_plotting_keeps_every_beacon_in_order_and_its_lines(): void
    {
        $r = Request::create('/', 'POST', $this->base() + ['beacons' => $this->parcel(), 'plot' => json_encode([
            'mode' => 'brg', 'closed' => true, 'closing_error' => 0, 'area' => 329.99,
            'lines' => [['from' => 'pb 234', 'to' => 'x2', 'azimuth' => 275.7163889, 'distance' => '15'], ['from' => 'x2', 'to' => 'x3', 'azimuth' => 360, 'distance' => 22]],
        ])]);
        $d = app(SurveyPlanUploads::class)->validated($r, false, Plan::METHOD_DIGITAL);

        $this->assertSame(Plan::METHOD_DIGITAL, $d['plan_method']);
        $this->assertSame(['PB 234', 'X2', 'X3', 'X4'], array_column($d['beacons'], 'beacon_no'));
        $this->assertSame('brg', $d['plot_data']['mode']);
        $this->assertTrue($d['plot_data']['closed']);
        // A bearing of 360 is not one: that line is dropped; the first is kept, names upper case.
        $this->assertCount(1, $d['plot_data']['lines']);
        $this->assertSame(['from' => 'PB 234', 'to' => 'X2', 'azimuth' => 275.716389, 'distance' => 15.0], $d['plot_data']['lines'][0]);
    }

    public function test_a_digital_plotting_needs_three_beacons_and_its_file_is_optional(): void
    {
        $svc = app(SurveyPlanUploads::class);
        try {
            $svc->validated(Request::create('/', 'POST', $this->base() + ['beacons' => array_slice($this->parcel(), 0, 2)]), false, Plan::METHOD_DIGITAL);
            $this->fail('Two beacons were taken.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('beacons', $e->errors());
        }
        // The plan file is optional on a digital plotting (user, 2026-11-16): taken with or without one.
        $with = $svc->validated(Request::create('/', 'POST', $this->base() + ['beacons' => $this->parcel()], [], ['plan_file' => UploadedFile::fake()->create('plan.pdf', 5, 'application/pdf')]), false, Plan::METHOD_DIGITAL);
        $without = $svc->validated(Request::create('/', 'POST', $this->base() + ['beacons' => $this->parcel()]), false, Plan::METHOD_DIGITAL);
        $this->assertSame(Plan::METHOD_DIGITAL, $with['plan_method']);
        $this->assertSame(Plan::METHOD_DIGITAL, $without['plan_method']);
        try {
            $svc->validated(Request::create('/', 'POST', $this->base() + ['beacons' => $this->parcel()], [], ['plan_file' => UploadedFile::fake()->create('plan.exe', 5, 'application/octet-stream')]), false, Plan::METHOD_DIGITAL);
            $this->fail('A file that is not a plan was taken on a digital plotting.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('plan_file', $e->errors());
        }
    }

    public function test_a_manual_upload_still_needs_its_file_and_one_reference(): void
    {
        try {
            app(SurveyPlanUploads::class)->validated(Request::create('/', 'POST', $this->base() + ['beacons' => array_slice($this->parcel(), 0, 1)]), false);
            $this->fail('A manual upload without a file was taken.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('plan_file', $e->errors());
        }
    }

    public function test_plans_from_before_the_split_count_as_manual(): void
    {
        $this->assertFalse((new Plan(['plan_method' => null]))->isDigital());
        $this->assertSame('Manual Upload', (new Plan(['plan_method' => null]))->method_label);
        $this->assertTrue((new Plan(['plan_method' => 'digital']))->isDigital());
    }
}
