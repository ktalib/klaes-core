<?php

namespace Tests\Unit\MasterJsi;

use App\Http\Controllers\PhysicalPlanning\MasterJsiController;
use App\Models\MasterJsiExtensionPortion;
use App\Models\MasterJsiMergerProperty;
use App\Models\MasterJsiParticipant;
use App\Models\MasterJsiPurpose;
use App\Models\MasterJsiReport;
use App\Models\MasterJsiSubdivisionPlot;
use ReflectionMethod;
use Tests\TestCase;

/**
 * The Site Inspection Template Update, as pure rules.
 *
 * None of these tests touch a database: the report's measurement helpers only read
 * the loaded child relations, and the controller's site-unit conversion is a pure
 * array transform before anything is persisted.
 */
class MasterJsiInspectionTemplateTest extends TestCase
{
    private function report(array $attributes = []): MasterJsiReport
    {
        $report = new MasterJsiReport();
        $report->forceFill($attributes);

        $report->setRelation('purposes', collect());
        $report->setRelation('participants', collect());
        $report->setRelation('mergerProperties', collect());
        $report->setRelation('extensionPortions', collect());
        $report->setRelation('subdivisionPlots', collect());
        $report->setRelation('purposeChanges', collect());

        return $report;
    }

    private function purposes(string ...$slugs)
    {
        return collect(array_map(fn ($s, $i) => new MasterJsiPurpose([
            'purpose'  => $s,
            'sequence' => $i + 1,
        ]), $slugs, array_keys($slugs)));
    }

    // ------------------------------------------------------------------ purposes

    public function test_separation_counts_as_the_subdivision_section(): void
    {
        $report = $this->report();
        $report->setRelation('purposes', $this->purposes('separation'));

        $this->assertTrue($report->hasPurpose('separation'));
        $this->assertTrue($report->hasPurpose('subdivision'));
        $this->assertTrue($report->wantsSubdivisionSection());
        $this->assertSame(['separation'], $report->purposeSlugs());
    }

    public function test_combined_purposes_stay_individual(): void
    {
        $report = $this->report();
        $report->setRelation('purposes', $this->purposes('merger', 'extension', 'change_of_purpose'));

        $this->assertSame(['merger', 'extension', 'change_of_purpose'], $report->purposeSlugs());
        $this->assertTrue($report->hasPurpose('merger'));
        $this->assertFalse($report->hasPurpose('subdivision'));
        $this->assertFalse($report->wantsSubdivisionSection());
    }

    public function test_purpose_labels_cover_every_purpose(): void
    {
        foreach (['merger', 'extension', 'subdivision', 'separation', 'change_of_purpose'] as $slug) {
            $this->assertArrayHasKey($slug, MasterJsiPurpose::PURPOSES);
        }
    }

    public function test_legacy_sheet_derives_its_purpose_from_parcel_update_type(): void
    {
        $report = $this->report(['parcel_update_type' => 'subdivision']);
        $this->assertSame(['subdivision'], $report->purposeSlugs());
        $this->assertTrue($report->wantsSubdivisionSection());

        $separation = $this->report(['parcel_update_type' => 'separation']);
        $this->assertSame(['subdivision'], $separation->purposeSlugs());
        $this->assertTrue($separation->hasPurpose('subdivision'));
    }

    // ------------------------------------------------------------------ legacy

    public function test_record_with_no_new_child_rows_is_legacy(): void
    {
        $this->assertTrue($this->report()->isLegacyLayout());
    }

    public function test_purpose_children_mark_the_new_layout(): void
    {
        $report = $this->report();
        $report->setRelation('purposes', $this->purposes('merger'));

        $this->assertFalse($report->isLegacyLayout());
    }

    public function test_an_explicitly_negative_finding_marks_the_new_layout(): void
    {
        $report = $this->report(['finding_site_suitable' => false]);

        $this->assertFalse($report->isLegacyLayout());
    }

    public function test_child_rows_mark_the_new_layout(): void
    {
        $report = $this->report();
        $report->setRelation('mergerProperties', collect([new MasterJsiMergerProperty([
            'property_file_number' => 'F1001',
        ])]));

        $this->assertFalse($report->isLegacyLayout());
    }

    // ------------------------------------------------------------------ areas

    public function test_merger_total_is_the_sum_of_properties(): void
    {
        $report = $this->report();
        $report->setRelation('mergerProperties', collect([
            new MasterJsiMergerProperty(['area_sqm' => 1000]),
            new MasterJsiMergerProperty(['area_sqm' => 2500.5]),
        ]));

        $this->assertSame(3500.5, $report->totalMergerAreaSqm());
    }

    public function test_extension_total_is_the_sum_of_portions(): void
    {
        $report = $this->report();
        $report->setRelation('extensionPortions', collect([
            new MasterJsiExtensionPortion(['area_sqm' => 500]),
            new MasterJsiExtensionPortion(['area_sqm' => null]),
        ]));

        $this->assertSame(500.0, $report->totalExtensionAreaSqm());
    }

    public function test_subdivision_totals_and_remaining(): void
    {
        $report = $this->report(['site_recommended_area_sqm' => 5000]);
        $report->setRelation('subdivisionPlots', collect([
            new MasterJsiSubdivisionPlot(['area_sqm' => 1000]),
            new MasterJsiSubdivisionPlot(['area_sqm' => 2000]),
        ]));

        $this->assertSame(3000.0, $report->totalSubdivisionAreaSqm());
        $this->assertSame(2000.0, $report->subdivisionRemainingAreaSqm());
    }

    public function test_subdivision_remaining_never_goes_negative(): void
    {
        $report = $this->report(['site_recommended_area_sqm' => 1000]);
        $report->setRelation('subdivisionPlots', collect([
            new MasterJsiSubdivisionPlot(['area_sqm' => 2000]),
        ]));

        $this->assertSame(0.0, $report->subdivisionRemainingAreaSqm());
    }

    public function test_subdivision_remaining_uses_existing_plus_additions_when_not_typed(): void
    {
        $report = $this->report(['site_existing_area_sqm' => 4000]);
        $report->setRelation('extensionPortions', collect([
            new MasterJsiExtensionPortion(['area_sqm' => 1000]),
        ]));
        $report->setRelation('subdivisionPlots', collect([
            new MasterJsiSubdivisionPlot(['area_sqm' => 3000]),
        ]));

        $this->assertSame(2000.0, $report->subdivisionRemainingAreaSqm());
    }

    // ------------------------------------------------- recommended total rule

    public function test_override_wins_over_calculation(): void
    {
        $report = $this->report([
            'site_existing_area_sqm'         => 1000,
            'site_recommended_area_sqm'      => 2000,
            'recommended_total_override_sqm' => 9999,
        ]);
        $report->setRelation('extensionPortions', collect([
            new MasterJsiExtensionPortion(['area_sqm' => 500]),
        ]));

        $this->assertSame(9999.0, $report->recommendedTotalSiteAreaSqm());
    }

    public function test_recommended_total_is_existing_plus_extensions(): void
    {
        $report = $this->report([
            'site_existing_area_sqm'    => 1000,
            'site_recommended_area_sqm' => 7777,
        ]);
        $report->setRelation('extensionPortions', collect([
            new MasterJsiExtensionPortion(['area_sqm' => 500]),
        ]));

        $this->assertSame(1500.0, $report->recommendedTotalSiteAreaSqm());
    }

    public function test_recommended_total_falls_back_to_recommended_then_existing(): void
    {
        $withoutAdditions = $this->report([
            'site_existing_area_sqm'    => 1000,
            'site_recommended_area_sqm' => 2000,
        ]);
        $this->assertSame(2000.0, $withoutAdditions->recommendedTotalSiteAreaSqm());

        $bare = $this->report(['site_existing_area_sqm' => 1000]);
        $this->assertSame(1000.0, $bare->recommendedTotalSiteAreaSqm());
    }

    // -------------------------------------------- site unit normalisation

    public function test_site_figures_entered_in_ha_are_stored_as_sqm(): void
    {
        $attributes = $this->reportAttributes([
            'site_unit'                      => 'ha',
            'site_existing_area_sqm'         => 1.5,
            'site_recommended_area_sqm'      => 2,
            'recommended_total_override_sqm' => null,
            'override_reason'                => null,
        ]);

        $this->assertSame(15000.0, $attributes['site_existing_area_sqm']);
        $this->assertSame(20000.0, $attributes['site_recommended_area_sqm']);
        $this->assertNull($attributes['recommended_total_override_sqm']);
    }

    public function test_site_figures_entered_in_sqm_pass_through(): void
    {
        $attributes = $this->reportAttributes([
            'site_unit'                  => 'sqm',
            'site_existing_area_sqm'     => 1200.5,
            'site_recommended_area_sqm'  => null,
        ]);

        $this->assertSame(1200.5, $attributes['site_existing_area_sqm']);
        $this->assertNull($attributes['site_recommended_area_sqm']);
    }

    public function test_children_arrays_never_reach_report_attributes(): void
    {
        $attributes = $this->reportAttributes([
            'purposes'           => ['merger'],
            'participants'       => [],
            'merger_properties'  => [],
            'extension_portions' => [],
            'subdivision_plots'  => [],
            'purpose_changes'    => [],
            'portions'           => [],
            'site_unit'          => 'sqm',
        ]);

        foreach (['purposes', 'participants', 'merger_properties', 'extension_portions', 'subdivision_plots', 'purpose_changes', 'portions'] as $key) {
            $this->assertArrayNotHasKey($key, $attributes);
        }
    }

    // --------------------------------------------------------------- misc

    public function test_representative_flag(): void
    {
        $applicant = new MasterJsiParticipant(['participant' => MasterJsiParticipant::PARTICIPANT_APPLICANT]);
        $rep = new MasterJsiParticipant(['participant' => MasterJsiParticipant::PARTICIPANT_APPLICANT_REP]);

        $this->assertFalse($applicant->isRepresentative());
        $this->assertTrue($rep->isRepresentative());
    }

    public function test_returned_is_true_only_when_decision_and_stamp_agree(): void
    {
        $returned = $this->report(['supervisor_decision' => 'returned', 'returned_at' => now()]);
        $this->assertTrue($returned->isReturned());

        $noStamp = $this->report(['supervisor_decision' => 'returned']);
        $this->assertFalse($noStamp->isReturned());

        $approved = $this->report(['supervisor_decision' => 'approved', 'returned_at' => now()]);
        $this->assertFalse($approved->isReturned());
    }

    public function test_boundary_description_is_compiled_in_fixed_order(): void
    {
        $this->assertSame(
            'On the North: A road; On the South: B mosque; On the West: C school.',
            MasterJsiReport::compileBoundaryDescription([
                'west'  => 'C school',
                'south' => 'B mosque',
                'north' => 'A road',
            ])
        );
    }

    // --------------------------------------------------------------- helpers

    private function reportAttributes(array $data): array
    {
        $controller = $this->app->make(MasterJsiController::class);

        return (new ReflectionMethod($controller, 'reportAttributes'))->invoke($controller, $data);
    }
}