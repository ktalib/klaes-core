<?php

namespace Tests\Feature;

use App\Models\Survey\SurveyChangeReport;
use App\Models\Survey\SurveySurveyorReport;
use Illuminate\Support\MessageBag;
use Tests\TestCase;

class SurveyorReportFormsTest extends TestCase
{
    public function test_gkn_create_shows_the_complete_report_form_before_an_instruction_is_selected(): void
    {
        $report = new SurveySurveyorReport();
        $report->forceFill(['status' => SurveySurveyorReport::DRAFT]);

        $html = view('survey_module.partials.sections._page-gkn-report-form', [
            'report' => $report,
            'choices' => [],
            'key' => '',
            'errors' => new MessageBag(),
        ])->render();

        $this->assertStringContainsString('Instruction to Survey', $html);
        $this->assertStringContainsString('name="grid_origin"', $html);
        $this->assertStringContainsString('name="control_points"', $html);
        $this->assertStringContainsString('name="transport_by"', $html);
        $this->assertStringContainsString('Select an issued Instruction to Survey above.', $html);
    }

    public function test_private_form_uses_the_shared_report_fields_and_only_requires_a_file_number(): void
    {
        $report = new SurveyChangeReport();
        $report->forceFill([
            'status' => SurveyChangeReport::DRAFT,
            'field_jacket_checklist' => ['surveyors_report'],
        ]);

        $html = view('survey_module.partials.sections._page-gkn-report-form', [
            'report' => $report,
            'privateReport' => true,
            'line' => null,
            'fromLine' => false,
            'fromBeacons' => false,
            'beaconLine' => null,
            'choices' => [],
            'key' => '',
            'errors' => new MessageBag(),
        ])->render();

        $this->assertStringContainsString('Select File Number', $html);
        $this->assertStringContainsString('name="grid_origin"', $html);
        $this->assertStringContainsString('name="control_points"', $html);
        $this->assertStringContainsString('name="district"', $html);
        $this->assertStringContainsString('name="lga"', $html);
        $this->assertStringContainsString('value="Other"', $html);
        $this->assertStringContainsString('Other (specify)</option>', $html);
        $this->assertStringContainsString('name="district_other"', $html);
        $this->assertStringContainsString('data-placeholder="Search district…"', $html);
        $this->assertStringContainsString('data-placeholder="Search LGA…"', $html);
        $this->assertStringContainsString("join(', ')", $html);
        $this->assertStringContainsString("'Kano'", $html);
        $this->assertStringContainsString('class="sr-checklist-item is-checked"', $html);
        $this->assertStringContainsString('aria-label="Field jacket records"', $html);
        $this->assertStringContainsString(' Save</button>', $html);
        $this->assertStringNotContainsString('id="srItsChoice"', $html);
        $this->assertStringNotContainsString('name="discrepancies"', $html);
        $this->assertStringNotContainsString('Save Draft', $html);
    }

    public function test_private_print_uses_the_same_official_numbered_report_template(): void
    {
        $report = new SurveyChangeReport();
        $report->forceFill([
            'id' => 21,
            'report_ref' => 'SRC-TEST',
            'file_number' => 'LPKN-1',
            'status' => SurveyChangeReport::DRAFT,
            'field_jacket_checklist' => [],
        ]);

        $html = view('survey_module.gkn.reports.print', ['report' => $report, 'privateReport' => true])->render();

        $this->assertStringContainsString('MINISTRY OF LAND &amp; PHYSICAL PLANNING', $html);
        $this->assertStringContainsString(asset('assets/logo/ministry1.jpg'), $html);
        $this->assertStringContainsString('<ol class="report-lines">', $html);
        $this->assertSame(22, substr_count($html, '<li>'));
        $this->assertStringContainsString('LPKN-1', $html);
        $this->assertStringNotContainsString('I to S', $html);
        $this->assertStringNotContainsString('Discrepancies', $html);
        $this->assertStringNotContainsString('DRAFT', $html);
    }
}
