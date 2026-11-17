<?php

namespace App\Services\Survey;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Geodatabase Catalog for the Survey Plan card (user, Recording 5, 2026-11-16: "a catalog ... it will show you all the
 * layers inside the database ... select the ones that you want to load"). A browser cannot read an Esri File
 * Geodatabase, so the zipped .gdb comes here: GDAL (resources/gis/gdb_catalog.py, gis-py venv) lists its feature
 * datasets and layers, and then reads only the layers picked, as GeoJSON in their own coordinates.
 *
 * Nothing is kept: each upload is unpacked under storage/app/tmp/gdb-catalog/<token>, the token is held in the
 * uploader's session, and folders older than two hours are deleted.
 */
class GdbCatalog
{
    public const MAX_MB = 500;

    /** Unpacked size a geodatabase may have (the F:\GIS Data ones are 0.4-0.8 GB). */
    public const MAX_UNZIPPED_MB = 2048;

    /** Features read per layer: a browser map stays usable. "Only the map view" reads a part of a bigger layer. */
    public const MAX_FEATURES = 20000;

    private const SESSION = 'gdb_catalog_tokens';

    private function root(): string
    {
        return storage_path('app/tmp/gdb-catalog');
    }

    private function clean(): void
    {
        foreach (glob($this->root() . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $old) {
            if (filemtime($old) < time() - 7200) {
                File::deleteDirectory($old);
            }
        }
    }

    /** @return array{token:string, name:string, layers:array} */
    public function open(UploadedFile $zip): array
    {
        if (! PlanCheckGdb::available()) {
            throw new \RuntimeException('The Geodatabase reader (GDAL) is not installed on this server.');
        }
        $this->clean();
        $token = bin2hex(random_bytes(12));
        $dir = $this->root() . DIRECTORY_SEPARATOR . $token;
        File::ensureDirectoryExists($dir, 0775);

        $z = new \ZipArchive();
        if ($z->open($zip->getRealPath()) !== true) {
            File::deleteDirectory($dir);
            throw new \InvalidArgumentException('That file is not a zip that can be opened.');
        }
        // Only what sits inside a *.gdb folder, never a path that climbs out.
        $gdb = null;
        $bytes = 0;
        for ($i = 0; $i < $z->numFiles; $i++) {
            $st = $z->statIndex($i);
            $name = str_replace('\\', '/', (string) $st['name']);
            if ($name === '' || str_ends_with($name, '/') || str_contains($name, '../') || str_starts_with($name, '/') || preg_match('/^[A-Za-z]:/', $name)) {
                continue;
            }
            if (! preg_match('#^(.*?[^/]+\.gdb)/[^/]+$#i', $name, $m)) {
                continue;
            }
            $gdb ??= $m[1];
            if ($m[1] !== $gdb) {
                continue;   // one geodatabase per zip
            }
            $bytes += (int) $st['size'];
            if ($bytes > self::MAX_UNZIPPED_MB * 1048576) {
                $z->close();
                File::deleteDirectory($dir);
                throw new \InvalidArgumentException('The geodatabase is too large to open here.');
            }
            $target = $dir . DIRECTORY_SEPARATOR . 'g.gdb' . DIRECTORY_SEPARATOR . basename($name);
            File::ensureDirectoryExists(dirname($target));
            $in = $z->getStream($st['name']);
            $out = fopen($target, 'wb');
            stream_copy_to_stream($in, $out);
            fclose($in);
            fclose($out);
        }
        $z->close();
        if (! $gdb) {
            File::deleteDirectory($dir);
            throw new \InvalidArgumentException('No .gdb folder was found in that zip. Zip the whole .gdb folder.');
        }

        $out = $this->run(['list', $dir . DIRECTORY_SEPARATOR . 'g.gdb'], 120);
        $list = json_decode($out, true);
        if (! is_array($list['layers'] ?? null)) {
            File::deleteDirectory($dir);
            throw new \RuntimeException('The geodatabase could not be read.');
        }
        $name = basename($gdb);
        file_put_contents($dir . DIRECTORY_SEPARATOR . 'catalog.json', json_encode(['name' => $name, 'layers' => $list['layers']]));
        session()->push(self::SESSION, $token);

        return ['token' => $token, 'name' => $name, 'layers' => array_map(fn ($l) => array_diff_key($l, ['crs_wkt' => 1]), $list['layers']),
            'max_features' => self::MAX_FEATURES];
    }

    /**
     * The picked layers as GeoJSON (coordinates as stored). $bbox: [xmin, ymin, xmax, ymax] in the layers' own system,
     * to read only the part in the map view.
     */
    public function read(string $token, array $names, ?array $bbox = null): array
    {
        if (! preg_match('/^[a-f0-9]{24}$/', $token) || ! in_array($token, (array) session(self::SESSION, []), true)) {
            throw new \InvalidArgumentException('That geodatabase is no longer open. Add it again.');
        }
        $dir = $this->root() . DIRECTORY_SEPARATOR . $token;
        $cat = json_decode((string) @file_get_contents($dir . DIRECTORY_SEPARATOR . 'catalog.json'), true);
        if (! is_array($cat)) {
            throw new \InvalidArgumentException('That geodatabase is no longer open. Add it again.');
        }
        $known = array_column($cat['layers'], 'name');
        $names = array_values(array_intersect($known, $names));
        if (! $names) {
            throw new \InvalidArgumentException('Tick at least one layer.');
        }
        $args = ['read', $dir . DIRECTORY_SEPARATOR . 'g.gdb', $dir . DIRECTORY_SEPARATOR . 'out.json', (string) self::MAX_FEATURES];
        if ($bbox && count($bbox) === 4 && count(array_filter($bbox, 'is_numeric')) === 4) {
            $args[] = '--bbox=' . implode(',', array_map(fn ($v) => (string) (float) $v, $bbox));
        }
        $this->run(array_merge($args, $names), 300);
        $res = json_decode((string) @file_get_contents($dir . DIRECTORY_SEPARATOR . 'out.json'), true);
        @unlink($dir . DIRECTORY_SEPARATOR . 'out.json');
        if (! is_array($res['layers'] ?? null)) {
            throw new \RuntimeException('Those layers could not be read.');
        }

        return ['name' => $cat['name'], 'layers' => $res['layers']];
    }

    private function run(array $args, int $timeout): string
    {
        $p = new Process(array_merge([PlanCheckGdb::PYTHON, '-I', resource_path('gis/gdb_catalog.py')], $args));
        $p->setTimeout($timeout);
        $p->run();
        if (! $p->isSuccessful()) {
            \Illuminate\Support\Facades\Log::warning('GDB catalog failed', ['args' => $args[0], 'err' => mb_substr($p->getErrorOutput(), -1500)]);
            throw new \RuntimeException('The geodatabase could not be read.');
        }

        return $p->getOutput();
    }
}
