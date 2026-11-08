<?php

namespace Tests\Feature;

use App\Models\Survey\SurveySurveyorReport;
use Tests\TestCase;

class SurveyorReportPrintTest extends TestCase
{
    public function test_print_includes_the_official_ministry_header_and_logos(): void
    {
        $report = new SurveySurveyorReport();
        $report->forceFill([
            'id' => 20,
            'report_ref' => 'SR-TEST',
            'its_number' => 'SRV/ITS/2026/0001',
            'job_ref' => 'SJ-2026-0001',
            'status' => SurveySurveyorReport::DRAFT,
            'field_jacket_checklist' => [],
        ]);

        $html = view('survey_module.gkn.reports.print', ['report' => $report])->render();

        $this->assertStringContainsString('MINISTRY OF LAND &amp; PHYSICAL PLANNING', $html);
        $this->assertStringContainsString('DEPARTMENT OF SURVEY', $html);
        $this->assertStringContainsString("SURVEYOR'S REPORT", $html);
        $this->assertStringContainsString(asset('assets/logo/ministry1.jpg'), $html);
        $this->assertStringContainsString(asset('assets/logo/ministry2.png'), $html);
        $this->assertStringContainsString('<ol class="report-lines">', $html);
        $this->assertSame(22, substr_count($html, '<li>'));
        $this->assertSame(9, substr_count($html, '<li class='));
        $this->assertStringContainsString('<ol class="field-records">', $html);
        $this->assertStringNotContainsString('DRAFT', $html);
    }
}
