<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Extant_Beacons.xlsx (CR 11112026-2, item 4) into survey_extant_beacons: the list behind the reference
 * beacon dropdown on Survey Plan Upload. Two columns, OBJECTID and beaconName; every row is loaded, repeats
 * included (the dropdown shows each name once). Dry run unless --commit; --undo=<ref> removes a load.
 *
 *   php artisan survey:import-extant-beacons "<file.xlsx>" --commit --ref=EB-20261111-1
 */
class ImportExtantBeacons extends Command
{
    protected $signature = 'survey:import-extant-beacons {file?} {--commit} {--ref=} {--undo=}';

    protected $description = 'Load the Extant Beacons spreadsheet into survey_extant_beacons (reference beacon dropdown).';

    public function handle(): int
    {
        $db = DB::connection('sqlsrv');

        if ($undo = $this->option('undo')) {
            $n = $db->table('survey_extant_beacons')->where('import_ref', $undo)->delete();
            $this->info("Removed {$n} rows of {$undo}.");

            return self::SUCCESS;
        }

        $file = (string) $this->argument('file');
        if ($file === '' || ! is_file($file)) {
            $this->error('Give the path of the .xlsx file.');

            return self::FAILURE;
        }
        $ref = (string) ($this->option('ref') ?: 'EB-' . now()->format('Ymd') . '-1');
        if ($db->table('survey_extant_beacons')->where('import_ref', $ref)->exists()) {
            $this->error("{$ref} is already loaded. Undo it first, or use another --ref.");

            return self::FAILURE;
        }

        $rows = $this->read($file);
        $names = array_filter($rows, fn ($r) => $r['beacon_name'] !== '');
        $distinct = count(array_unique(array_map(fn ($r) => $r['name_key'], $names)));
        $this->info(sprintf('%d rows, %d with a name, %d distinct names.', count($rows), count($names), $distinct));

        if (! $this->option('commit')) {
            $this->line('Dry run. Add --commit to load.');

            return self::SUCCESS;
        }

        $now = now();
        $loaded = 0;
        foreach (array_chunk($names, 300) as $chunk) {
            $db->table('survey_extant_beacons')->insert(array_map(fn ($r) => $r + ['import_ref' => $ref, 'created_at' => $now], $chunk));
            $loaded += count($chunk);
        }
        $this->info("Loaded {$loaded} rows as {$ref}.");

        return self::SUCCESS;
    }

    /** @return array<int, array{object_id:?int, beacon_name:string, name_key:string}> */
    private function read(string $file): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($file) !== true) {
            throw new \RuntimeException('Cannot open the spreadsheet.');
        }

        $shared = [];
        $xml = new \XMLReader();
        if (($s = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $xml->XML($s);
            while ($xml->read()) {
                if ($xml->nodeType === \XMLReader::ELEMENT && $xml->name === 'si') {
                    // No next() here: it would skip the following <si> as read() moves on.
                    $shared[] = trim(html_entity_decode(strip_tags($xml->readOuterXml()), ENT_QUOTES | ENT_XML1, 'UTF-8'));
                }
            }
            $xml->close();
        }

        $out = [];
        $xml->XML((string) $zip->getFromName('xl/worksheets/sheet1.xml'));
        $row = [];
        $col = null;
        $type = null;
        while ($xml->read()) {
            if ($xml->nodeType === \XMLReader::ELEMENT && $xml->name === 'row') {
                $row = [];
            } elseif ($xml->nodeType === \XMLReader::ELEMENT && $xml->name === 'c') {
                $col = preg_replace('/\d+/', '', (string) $xml->getAttribute('r'));
                $type = $xml->getAttribute('t');
            } elseif ($xml->nodeType === \XMLReader::ELEMENT && in_array($xml->name, ['v', 't'], true)) {
                $v = $xml->readString();
                $row[$col] = $type === 's' ? ($shared[(int) $v] ?? '') : $v;
            } elseif ($xml->nodeType === \XMLReader::END_ELEMENT && $xml->name === 'row') {
                $name = trim(preg_replace('/\s+/', ' ', (string) ($row['B'] ?? '')));
                if (strcasecmp($name, 'beaconName') === 0) {
                    continue;   // header
                }
                $out[] = [
                    'object_id' => is_numeric($row['A'] ?? null) ? (int) $row['A'] : null,
                    'beacon_name' => mb_substr($name, 0, 60),
                    'name_key' => mb_substr(strtoupper(str_replace(' ', '', $name)), 0, 60),
                ];
            }
        }
        $xml->close();
        $zip->close();

        return $out;
    }
}
