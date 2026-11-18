<?php

namespace App\Http\Controllers\Api\Desktop;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * The Kano map data for KLAES-CORE Survey Desktop (user, 2026-11-18: "just as we did for web"): a PC without its own
 * copy reads the same files the web map reads. The GIS layers (PMTiles in storage/app/gis-tiles, signed-in users only,
 * as Survey\GisTilesController) come through here with the desktop token; the imagery tiles are the web's public
 * /tiles/<set>/... folders. KLAES staff get the full parcels, as on the web.
 */
class DesktopMapController extends Controller
{
    private const GIS = ['kano_parcels', 'kano_beacons', 'kano_roads', 'kano_lga', 'kano_control_beacons'];
    private const TILE_SETS = ['kano' => 'kano-imagery', 'ortho' => 'kano-metro-ortho'];

    /** What the server has: GIS layer names, and each imagery set's tiles.json (bounds, zooms, format). */
    public function layers(): JsonResponse
    {
        $gis = array_values(array_filter(self::GIS, fn ($f) => is_file(storage_path("app/gis-tiles/{$f}.pmtiles"))));
        $tiles = [];
        foreach (self::TILE_SETS as $key => $dir) {
            $json = public_path("tiles/{$dir}/tiles.json");
            if (is_file($json) && is_array($j = json_decode((string) file_get_contents($json), true))) {
                $tiles[$key] = ['dir' => $dir, 'url' => url("tiles/{$dir}"), 'bounds' => $j['bounds'] ?? null, 'minzoom' => $j['minzoom'] ?? 10,
                    'maxzoom' => $j['maxzoom'] ?? 18, 'format' => $j['format'] ?? 'webp'];
            }
        }

        return response()->json(['data' => ['gis' => $gis, 'tiles' => $tiles], 'as_of' => now()->toIso8601String()]);
    }

    /** One PMTiles file; the map reads it in byte ranges (BinaryFileResponse answers Range itself). */
    public function gis(string $file)
    {
        abort_unless(in_array($file, self::GIS, true), 404);
        $path = storage_path("app/gis-tiles/{$file}.pmtiles");
        abort_unless(is_file($path), 404);

        return response()->file($path, ['Content-Type' => 'application/vnd.pmtiles', 'Cache-Control' => 'private, max-age=86400']);
    }
}
