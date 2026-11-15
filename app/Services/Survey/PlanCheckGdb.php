<?php

namespace App\Services\Survey;

use App\Models\Survey\SurveyPlanUpload as Plan;
use Symfony\Component\Process\Process;

/**
 * Survey Plan Check > Export > Geodatabase (user, 2026-11-15). A browser cannot write an Esri File Geodatabase,
 * so the card sends its features (already X / Y in the chosen system) and GDAL writes the .gdb here; it comes
 * back zipped. GDAL is pyogrio in its own Python environment (C:\xampp\klas-tools\gis-py, set up 2026-11-15);
 * the converter is resources/gis/geojson_to_gdb.py. Nothing is stored: the work files are deleted after.
 */
class PlanCheckGdb
{
    public const PYTHON = 'C:\xampp\klas-tools\gis-py\Scripts\python.exe';

    public const LAYERS = ['beacons' => 'Point', 'lines' => 'LineString', 'parcels' => 'Polygon'];

    public const MAX_FEATURES = 5000;

    public static function available(): bool
    {
        return is_file(self::PYTHON);
    }

    /** @return string the zip's path (the caller deletes it after sending) */
    public function make(array $layers, string $epsg, string $name): string
    {
        if (! self::available()) {
            throw new \RuntimeException('The Geodatabase converter (GDAL) is not installed on this server.');
        }
        if (! array_key_exists($epsg, Plan::COORD_SYSTEMS)) {
            throw new \InvalidArgumentException('Unknown coordinate system.');
        }

        $clean = [];
        $count = 0;
        foreach (self::LAYERS as $layer => $type) {
            $features = [];
            foreach ((array) ($layers[$layer] ?? []) as $f) {
                $g = $f['geometry'] ?? null;
                if (! is_array($g) || ($g['type'] ?? null) !== $type || ! is_array($g['coordinates'] ?? null)) {
                    continue;
                }
                $props = [];
                foreach ((array) ($f['properties'] ?? []) as $k => $v) {
                    $k = substr(preg_replace('/[^A-Za-z0-9_]/', '_', (string) $k), 0, 30);
                    if ($k !== '' && (is_scalar($v) || $v === null)) {
                        $props[$k] = is_string($v) ? mb_substr($v, 0, 250) : $v;
                    }
                }
                $features[] = ['type' => 'Feature', 'geometry' => ['type' => $type, 'coordinates' => $g['coordinates']], 'properties' => $props];
                if (++$count > self::MAX_FEATURES) {
                    throw new \InvalidArgumentException('Too many features for one Geodatabase (limit ' . self::MAX_FEATURES . ').');
                }
            }
            $clean[$layer] = ['type' => 'FeatureCollection', 'features' => $features];
        }
        if (! $count) {
            throw new \InvalidArgumentException('Nothing to export.');
        }

        $dir = storage_path('app/tmp/plan-check-gdb');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $base = $dir . DIRECTORY_SEPARATOR . date('YmdHis') . '-' . bin2hex(random_bytes(4));
        $in = $base . '.json';
        $out = $base . '.zip';
        file_put_contents($in, json_encode(['epsg' => (int) $epsg, 'name' => $name, 'layers' => $clean]));

        try {
            $p = new Process([self::PYTHON, resource_path('gis/geojson_to_gdb.py'), $in, $out]);
            $p->setTimeout(90);
            $p->run();
            if (! $p->isSuccessful() || ! is_file($out)) {
                throw new \RuntimeException('GDAL could not write the Geodatabase: ' . trim(mb_substr($p->getErrorOutput() ?: $p->getOutput(), -400)));
            }
        } finally {
            @unlink($in);
        }

        return $out;
    }
}
