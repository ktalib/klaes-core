<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Kano GIS layers for the Survey Plan card's Layers panel (user, 2026-11-15: "add the rest" of F:\GIS Data).
 *
 * PMTiles files made by resources/gis/make_vector_tiles.py into storage/app/gis-tiles, served to signed-in
 * users only (they are not in public/). The map reads them in byte ranges; BinaryFileResponse answers Range
 * requests itself.
 *
 * Parcels (user): KLAES staff get every attribute (file numbers, deeds details); MLPP-SG surveyors get the
 * public set (shape, plot, block, TP plan, layout, district, LGA).
 */
class GisTilesController extends Controller
{
    private const FILES = [
        'parcels' => 'kano_parcels',
        'beacons' => 'kano_beacons',
        'roads' => 'kano_roads',
        'lga' => 'kano_lga',
        'control-beacons' => 'kano_control_beacons',
    ];

    public function show(Request $request, string $layer)
    {
        abort_unless(isset(self::FILES[$layer]), 404);

        $file = self::FILES[$layer];
        if ($layer === 'parcels' && $request->routeIs('surveyor-portal.*')) {
            $file = 'kano_parcels_public';
        }

        $path = storage_path('app/gis-tiles/' . $file . '.pmtiles');
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => 'application/vnd.pmtiles',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
