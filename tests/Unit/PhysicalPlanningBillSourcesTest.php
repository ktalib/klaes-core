<?php

namespace Tests\Unit;

use App\Services\PhysicalPlanningBillSources as Sources;
use Illuminate\Http\Request;
use Tests\TestCase;

class PhysicalPlanningBillSourcesTest extends TestCase
{
    public function test_the_tabs_follow_the_directors_order_on_every_page(): void
    {
        foreach (['bills', 'payments', 'reports'] as $page) {
            $this->assertSame(
                ['Sectional Titling', 'SLTR', 'Conversion', 'Master JSI (Post CofO)'],
                array_column(Sources::tabs($page), 'label')
            );
        }
    }

    public function test_report_tabs_stay_on_the_report(): void
    {
        foreach (Sources::tabs('reports') as $tab) {
            $this->assertStringContainsString('url=report', $tab['url']);
        }
    }

    public function test_the_sectional_titling_module_is_not_treated_as_the_central_area(): void
    {
        $this->assertFalse(Sources::isCentral(Request::create('/programmes/payments')));
        $this->assertFalse(Sources::isCentral(Request::create('/programmes/payments', 'GET', ['url' => 'report'])));
    }

    public function test_central_requests_are_recognised(): void
    {
        foreach ([['url' => 'physical_planning'], ['url' => 'report', 'source' => Sources::ST], ['source' => 'pp_sltr'], ['source' => 'pp_master_jsi']] as $query) {
            $this->assertTrue(Sources::isCentral(Request::create('/programmes/payments', 'GET', $query)));
        }
    }

    public function test_each_source_has_its_own_billing_source(): void
    {
        $billingSources = array_column(Sources::SOURCES, 'billing_source');

        $this->assertSame($billingSources, array_values(array_unique($billingSources)));
        $this->assertSame('pp_conversion', Sources::keyForBillingSource('PP_BETTERMENT_BILL'));
        $this->assertNull(Sources::keyForBillingSource(null));
    }

    public function test_evidence_url_encodes_file_numbers_with_spaces(): void
    {
        $this->assertStringEndsWith('/storage/EDMS/KN%20123/a.pdf', Sources::evidenceUrl('EDMS/KN 123/a.pdf'));
        $this->assertNull(Sources::evidenceUrl(null));
    }
}
