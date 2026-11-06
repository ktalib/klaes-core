<?php

namespace Tests\Feature;

use App\Services\Survey\TpLargeFormat;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** View LF on Public Layout Plans (user, 2026-11-06): the folder under Survey_Registry_Raw is the key. */
class SurveyTpLargeFormatTest extends TestCase
{
    public function test_the_register_short_form_finds_the_folder_named_on_the_drawing(): void
    {
        $this->assertSame(['TP-KNUPDA-358E', 'TP-K-358E'], TpLargeFormat::folders('TP/K/358E'));
        $this->assertSame(['TP-KNUPDA-358E', 'TP-K-358E'], TpLargeFormat::folders('tp/knupda/358e'));
        $this->assertSame(['TP-UDB-105A'], TpLargeFormat::folders('TP/UDB/105A'));
        $this->assertSame([], TpLargeFormat::folders(''));
    }

    public function test_pages_are_listed_in_order_and_a_drawing_with_no_folder_has_none(): void
    {
        Storage::fake('public');
        $root = TpLargeFormat::ROOT;
        Storage::disk('public')->put("{$root}/TP-KNUPDA-358E/A3/Scan0010.jpg", 'x');
        Storage::disk('public')->put("{$root}/TP-KNUPDA-358E/A3/Scan0002 (2).jpg", 'x');
        Storage::disk('public')->put("{$root}/TP-KNUPDA-358E/A3/notes.txt", 'x');
        Storage::disk('public')->put("{$root}/CO-1-2006/A4/IMG_0001.jpg", 'x');

        $pages = app(TpLargeFormat::class)->pagesFor(['TP/K/358E', 'TP/UDB/1']);

        $this->assertSame(['TP/K/358E'], array_keys($pages));
        $this->assertSame(['Scan0002 (2).jpg', 'Scan0010.jpg'], array_column($pages['TP/K/358E'], 'name'));
        $this->assertSame('/storage/EDMS/BLIND_SCAN/Survey_Registry_Raw/TP-KNUPDA-358E/A3/Scan0002%20%282%29.jpg', $pages['TP/K/358E'][0]['url']);
    }
}
