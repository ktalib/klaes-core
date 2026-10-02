<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\User;

class TmpValuationRenderTest extends TestCase
{
    public function test_render()
    {
        $user = User::query()->first();
        $res = $this->actingAs($user)->get('/valuation-reports');
        echo "STATUS: ".$res->getStatusCode()."\n";
        $html = $res->getContent();
        echo "search input: ".substr_count($html,'id="valuation-search"')."\n";
        echo "data-row: ".substr_count($html,'data-row')."\n";
        echo "no-match: ".substr_count($html,'valuation-no-match')."\n";
        $this->assertTrue(true);
    }
}
