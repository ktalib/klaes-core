<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SidebarTabCardsTest extends TestCase
{
    /** A key with no card would leave the link going straight to the page. */
    public function test_every_tagged_sidebar_link_has_a_card(): void
    {
        $html = view('admin.menu.partials.tab_cards')->render();

        $keys = [];
        foreach (File::files(resource_path('views/admin/menu/partials/modules')) as $file) {
            preg_match_all('/data-tab-card="([a-z-]+)"/', $file->getContents(), $m);
            $keys = array_merge($keys, $m[1]);
        }

        $this->assertNotEmpty($keys);
        foreach ($keys as $key) {
            $this->assertStringContainsString('data-tab-card-panel="' . $key . '"', $html, "No card for {$key}");
        }
    }

    public function test_cards_link_straight_to_the_tabs(): void
    {
        $html = html_entity_decode(view('admin.menu.partials.tab_cards')->render());

        foreach (['/land-rofos?tab=batches', '/land-rofos?tab=reissuance', '/land-recommendations?type=ROFO&tab=extant', 'record_type=fefr', '/regrant?tab=due&url=survey'] as $url) {
            $this->assertStringContainsString($url, $html);
        }
        // Cadastral opens RofO view only, which has no Batches tab.
        $this->assertStringNotContainsString('/land-rofos?url=cadastral&tab=batches', $html);
        // OSS has no Batches or Re-issuance on its cards.
        foreach (['/land-rofos?view=only&tab=batches', '/land-rofos?view=only&tab=reissuance', '/land-recommendations?type=OSS&tab=batches'] as $url) {
            $this->assertStringNotContainsString($url, $html);
        }
    }
}
