<?php

namespace App\Http\Controllers;

use App\Models\LandRecommendation;
use Illuminate\Http\Request;

/**
 * Public demo of the fixed Land RofO letter layout: a frame that cannot be pushed
 * down onto the security paper's barcode.
 *
 * The letter is a copy of land_rofos.templates.rofo_print under land_rofos/demo;
 * the live template is not touched. Every value is sample data
 * typed into the demo page: nothing is read from or written to the database, no
 * security serial is minted and no QR token is issued.
 */
class LandRofoLayoutDemoController extends Controller
{
    private const PRESETS = [
        'short' => [
            'name' => 'UMMA ABDULKADIR MALAM',
            'address' => 'HOTORO G.R.A., KANO.',
            'location' => 'MAIDUGURI ROAD TARAUNI KANO STATE',
        ],
        'long' => [
            'name' => 'ALHAJI MUHAMMAD SANI ABUBAKAR DANLADI GWARZO',
            'address' => 'NO. 14B OPPOSITE ZALIHA MEMORIAL SCHOOL, OFF ZARIA ROAD, BEHIND GIDAN MAN FETUR, GYADI-GYADI QUARTERS, TARAUNI LOCAL GOVERNMENT AREA, KANO STATE',
            'location' => 'GYADI-GYADI LAYOUT EXTENSION, ALONG ZARIA ROAD, BY NEW BRIDGE, TARAUNI LGA, KANO STATE',
        ],
        'extreme' => [
            'name' => 'KANO NORTHERN REGIONAL AGRICULTURAL AND INDUSTRIAL DEVELOPMENT COOPERATIVE SOCIETY LIMITED',
            'address' => 'C/O ALHAJI IBRAHIM MUSA DAN-BATTA, PLOT 221 BLOCK C, SHOP NO. 45 TO 48, SABON GARI MARKET, BY FAGGE ROUNDABOUT, OPPOSITE FIRST BANK, FAGGE LOCAL GOVERNMENT AREA, KANO STATE, NIGERIA. P.O. BOX 12345 KANO. TEL: 08031234567',
            'location' => 'KUMBOTSO NEW INDUSTRIAL LAYOUT PHASE II, ALONG KANO-ZARIA EXPRESSWAY, BEHIND CHALLAWA INDUSTRIAL ESTATE, KUMBOTSO LOCAL GOVERNMENT AREA, KANO STATE',
        ],
    ];

    public function index(Request $request)
    {
        return view('land_rofos.demo.index', [
            'values' => $this->values($request),
            'presets' => self::PRESETS,
        ]);
    }

    public function letter(Request $request)
    {
        $values = $this->values($request);

        $recommendation = new LandRecommendation();
        $recommendation->forceFill([
            'id' => 0,
            'file_number' => $values['file_no'],
            'applicant_name' => $values['name'],
            'applicant_address' => $values['address'],
            'location' => $values['location'],
            'plot_number' => $values['plot'],
            'layout_plan_no' => $values['plan'],
            'old_file_number' => null,
            'application_date' => '2024-05-22',
            'date_issued' => '2026-11-12',
            'ground_rent' => 15,
            'ground_rent_unit' => 'Per Square Meters',
            'development_charge' => 'To follow',
            'survey_fees' => 20000,
            'term' => '97',
            'land_use' => $values['purpose'],
            'purpose_of_clause' => $values['purpose'],
            'development_value' => 10000000,
            'development_period' => '2',
            'rofo_land_use_category' => $values['purpose'],
            'rofo_survey_fees' => 20000,
            'rofo_dev_charge' => 0,
        ]);

        // Only the letter itself: one Original, on the security paper scan unless
        // the demo page switches the scan off.
        $request->query->set('status', 'Original');
        $request->query->set('rofo-security-paper', $values['paper'] ? '1' : '0');

        return view('land_rofos.demo.rofo_fixed', [
            'recommendation' => $recommendation,
            'securityCode' => (object) ['code' => 'A05033000'],
            'supersededDate' => '',
            'isWhiteCopy' => false,
            'printVersionsOnly' => ['Original'],
            'demoLabel' => 'Fixed layout',
        ]);
    }

    /** The sample values: a preset, then anything typed on the demo page. */
    private function values(Request $request): array
    {
        $preset = self::PRESETS[$request->query('preset')] ?? self::PRESETS['long'];
        $text = fn (string $key, string $default) => mb_substr(trim((string) $request->query($key, $default)), 0, 400);

        return [
            'preset' => array_key_exists($request->query('preset'), self::PRESETS) ? $request->query('preset') : 'long',
            'name' => $text('name', $preset['name']),
            'address' => $text('address', $preset['address']),
            'location' => $text('location', $preset['location']),
            'plot' => $text('plot', 'GP 898'),
            'plan' => $text('plan', 'TP/KN/1234'),
            'file_no' => $text('file_no', 'RES-2024-2303'),
            'purpose' => $text('purpose', 'RESIDENTIAL'),
            'paper' => $request->query('paper', '1') !== '0',
        ];
    }
}
