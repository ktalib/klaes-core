<?php

namespace App\Services\Survey;

use Illuminate\Support\Facades\Storage;

/**
 * Large format (LF) scans of the layout plans (user, 2026-11-06). They sit in EDMS with Survey's other blind scans,
 * one folder per drawing: EDMS/BLIND_SCAN/Survey_Registry_Raw/TP-KNUPDA-358E/A3/Scan0002.jpg. The folder is the
 * key, so a folder scanned later shows on Public Layout Plans with nothing else to do.
 */
class TpLargeFormat
{
    public const ROOT = 'EDMS/BLIND_SCAN/Survey_Registry_Raw';

    private const IMAGES = ['jpg', 'jpeg', 'png', 'tif', 'tiff', 'webp'];

    /** A scan folder holding a layout map: named from its TP or BLM number, e.g. .../TP-KNUPDA-358E. */
    public static function isLayoutFolder(string $path): bool
    {
        return (bool) preg_match('/^(TP|BLM)-/i', basename(str_replace('\\', '/', $path)));
    }

    /**
     * The folders a TP No. may be scanned under. The register writes TP/K/358E where the drawing itself says
     * TP/KNUPDA/358E: the same plan (title and project agree), and no serial is on the register under both.
     *
     * @return string[]
     */
    public static function folders(?string $tpNo): array
    {
        $tp = mb_strtoupper(preg_replace('/\s+/', '', (string) $tpNo));
        if ($tp === '') {
            return [];
        }

        $names = [$tp];
        if (preg_match('#^TP/(K|KNUPDA)/(.+)$#', $tp, $m)) {
            $names = ["TP/KNUPDA/{$m[2]}", "TP/K/{$m[2]}"];
        }

        return array_map(fn ($n) => trim(preg_replace('/[^A-Z0-9_\-.]/', '-', $n), '-.'), $names);
    }

    /**
     * Pages for each TP No. given, keyed by TP No. Lists the TP folders once, then reads only the ones that match.
     *
     * @param  iterable<string>  $tpNos
     * @return array<string, array<int, array{name:string, url:string}>>
     */
    public function pagesFor(iterable $tpNos): array
    {
        $disk = Storage::disk('public');
        $held = [];
        foreach ($disk->directories(self::ROOT) as $dir) {
            $held[mb_strtoupper(basename($dir))] = $dir;
        }

        $out = [];
        foreach ($tpNos as $tp) {
            $dirs = array_filter(array_map(fn ($f) => $held[$f] ?? null, self::folders($tp)));
            $pages = [];
            foreach ($dirs as $dir) {
                foreach ($disk->allFiles($dir) as $file) {
                    if (in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), self::IMAGES, true)) {
                        $pages[] = $file;
                    }
                }
            }
            if ($pages) {
                natcasesort($pages);
                $out[$tp] = array_values(array_map(fn ($p) => [
                    'name' => basename($p),
                    'url'  => '/storage/' . implode('/', array_map('rawurlencode', explode('/', $p))),
                ], $pages));
            }
        }

        return $out;
    }
}
