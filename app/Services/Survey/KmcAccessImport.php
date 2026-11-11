<?php

namespace App\Services\Survey;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * KMC Phase1 Compensation.accdb, as exported to CSV with its passport photos (user, 2026-11-11), into the Computation
 * Sheet's staging table, checked against what is already staged so nothing is there twice ("check the one we imported
 * before ... so that it won't duplicate"). Property ID is the Computation Sheet's Farm ID.
 *
 *  - The three farm-record tables (farm records update, update1, Sheet1) are versions of one register: merged to one
 *    record per farmer (Property ID + name), the newest version's values first.
 *  - A farmer already staged (same Farm ID, same name) gets the photo, District, Staff ID, Supervisor and Camera on its
 *    own row(s), and the Tag No where that was blank. Nothing already written there is overwritten. No new row.
 *  - Same Farm ID under another name (user: add, flagged) and farmers not staged at all become new rows, group
 *    "farm records".
 *  - Sulementry is the sheet's Supplementary lines (same Farm ID and area): left out where it is.
 *  - Areas1 (Areas is wholly inside it): every distinct row, its own group (user's choice).
 *
 * Photos are copied once per distinct image under the batch reference; --undo removes the rows, clears what the batch
 * filled on staged rows, and deletes the photos. Each chunk commits on its own: klas has no snapshot isolation.
 */
class KmcAccessImport
{
    private const CONN = 'sqlsrv';
    private const DISK = 'local';
    private const CHUNK = 60;
    public const SOURCE = 'KMC Phase1 Compensation.accdb';
    /** Newest first: a farmer's values come from the first of these that has the farmer. */
    private const VERSIONS = ['farm records update', 'farm records update1', 'Sheet1'];
    public const FARM_GROUP = 'farm records';

    private string $dir = '';
    private array $enrich = [];    // [ids => int[], fields => array, tag_no => ?string, photo => ?string, photo_name => ?string]
    private array $inserts = [];   // group => rows
    private array $summary = [];

    public static function photoDir(string $ref): string
    {
        return 'app/survey/computation-sheet/' . preg_replace('/[^A-Za-z0-9_-]/', '_', $ref);
    }

    /** Read and match everything. Nothing is written. */
    public function plan(string $dir): array
    {
        $this->dir = rtrim(str_replace('\\', '/', $dir), '/') . '/';
        $originals = $this->originals();
        $staged = $this->staged();

        // Farmers: the versions merged, one per Property ID + name.
        $farmers = [];
        $versionRows = 0;
        foreach (self::VERSIONS as $table) {
            if (! is_file($this->dir . $table . '.csv')) continue;
            foreach ($this->csv($this->dir . $table . '.csv') as $r) {
                $versionRows++;
                $key = self::norm($r['Property ID'] ?? '') . '#' . self::nameKey($r['First Name'] ?? '', $r['Last Name'] ?? '');
                $farmers[$key]['rows'][] = ['table' => $table, 'r' => $r];
            }
        }

        $matched = $clash = $new = $lines = 0;
        foreach ($farmers as $key => $f) {
            [$pid, $name] = explode('#', $key, 2);
            $lead = $f['rows'][0];   // newest version's first row
            $r = $lead['r'];
            $photoRow = collect($f['rows'])->first(fn ($x) => trim((string) ($x['r']['Passport_files'] ?? '')) !== '') ?? $lead;
            $photo = $this->text($photoRow['r']['Passport_files'] ?? null);
            $photoName = $photo ? ($originals[$photoRow['table'] . '|' . (int) $photoRow['r']['_RowNo']] ?? basename($photo)) : null;
            $fields = ['district' => $this->text($r['District'] ?? null), 'staff_id' => $this->text($r['Staff ID'] ?? null),
                'supervisor' => $this->text($r['Supervisor'] ?? null), 'camera' => $this->text($r['Camera'] ?? null)];
            $tables = collect($f['rows'])->pluck('table')->unique()->values()->all();

            $same = $pid !== '' ? collect($staged[$pid] ?? [])->filter(fn ($c) => $c->name_key === $name) : collect();
            if ($same->isNotEmpty()) {
                $matched++;
                $lines += $same->count();
                $this->enrich[] = ['ids' => $same->pluck('id')->all(), 'fields' => $fields, 'tag_no' => $this->text($r['Tag No'] ?? null),
                    'photo' => $photo, 'photo_name' => $photoName ? mb_substr($photoName, 0, 255) : null];
                continue;
            }

            $others = $pid !== '' ? collect($staged[$pid] ?? []) : collect();
            $others->isNotEmpty() ? $clash++ : $new++;
            $this->inserts[self::FARM_GROUP][] = $fields + [
                'source_row' => (int) $r['_RowNo'], 'kind' => 'line',
                'farm_id' => $this->text($r['Property ID'] ?? null), 'tag_no' => $this->text($r['Tag No'] ?? null),
                'first_name' => $this->text($r['First Name'] ?? null), 'middle_name' => $this->text($r['Middle Name'] ?? null),
                'last_name' => $this->text($r['Last Name'] ?? null), 'sheet_no' => $this->text($r['Sheet No'] ?? null),
                'remarks' => $others->isNotEmpty()
                    ? mb_substr('Farm ID also on the Computation Sheet as ' . $others->map(fn ($c) => trim($c->first_name . ' ' . $c->last_name) . " ({$c->sheet_name})")->unique()->implode(', '), 0, 1000)
                    : null,
                'photo_name' => $photoName ? mb_substr($photoName, 0, 255) : null,
                'raw' => json_encode(['row' => array_diff_key($r, ['_RowNo' => 1]), 'from' => $lead['table'], 'in_tables' => $tables,
                    'versions' => count($f['rows'])], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                '_photo' => $photo,
            ];
        }

        // Sulementry: only what the sheet does not already hold (same Farm ID and area).
        $supp = is_file($this->dir . 'Sulementry.csv') ? $this->csv($this->dir . 'Sulementry.csv') : [];
        $suppNew = array_values(array_filter($supp, fn ($r) => ! collect($staged[self::norm($r['Property_ID'] ?? '')] ?? [])
            ->contains(fn ($c) => abs((float) $c->area_m2 - (float) ($r['Farm_Area(Meters Sq)'] ?? 0)) < 0.01)));
        foreach ($suppNew as $r) {
            $this->inserts['Sulementry'][] = array_merge($this->blank(), [
                'source_row' => (int) $r['_RowNo'], 'kind' => 'line', 'farm_id' => $this->text($r['Property_ID']), 'tag_no' => $this->text($r['Tag_No']),
                'first_name' => $this->text($r['First_Name']), 'middle_name' => $this->text($r['Middle_Nam']), 'last_name' => $this->text($r['Last_Name']),
                'sheet_no' => $this->text($r['Sheet_No']), 'area_m2' => $this->text($r['Farm_Area(Meters Sq)']), 'area_ha' => $this->text($r['Farm_Area(Hectares)']),
                'plots' => $this->plots($r, 'No of High Density Plots(15m X 15m)', 'No of Medium Density Plots(15m X 30m)', 'No of Low Density Plots(30m X 30m)'),
                'raw' => json_encode(array_diff_key($r, ['_RowNo' => 1]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }

        // Areas1: every distinct row (the same row repeated in the table loads once; its row numbers are kept).
        $areas = [];
        if (is_file($this->dir . 'Areas1.csv')) {
            foreach ($this->csv($this->dir . 'Areas1.csv') as $r) {
                $k = json_encode(array_diff_key($r, ['_RowNo' => 1]));
                $areas[$k]['r'] ??= $r;
                $areas[$k]['rows'][] = (int) $r['_RowNo'];
            }
        }
        foreach ($areas as $a) {
            $r = $a['r'];
            $id = $this->text($r['OBJECTID'] ?? null);
            $this->inserts['Areas1'][] = array_merge($this->blank(), [
                'source_row' => $a['rows'][0], 'kind' => $id === null ? 'other' : 'line',   // the first row: plot-size factors
                'farm_id' => $this->text($r['New_ShapeI'] ?? null),
                'area_m2' => $this->text($r['Farm_Area(Meters Square)'] ?? null), 'area_ha' => $this->text($r['Farm_Area(Hectares)'] ?? null),
                'plots' => $this->plots($r, 'No of High Density Plots(15mx15m)', 'No of Medium Density Plots(15mx225m)', 'No of Low Density Plots(30mx30m)'),
                'remarks' => implode(' · ', array_filter([$id ? "OBJECTID $id" : null, $this->text($r['THEME'] ?? null),
                    count($a['rows']) > 1 ? count($a['rows']) . ' times in Areas1' : null])) ?: null,
                'raw' => json_encode(['row' => array_diff_key($r, ['_RowNo' => 1]), 'source_rows' => $a['rows']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }

        return $this->summary = [
            'version_rows' => $versionRows, 'farmers' => count($farmers),
            'matched' => $matched, 'matched_lines' => $lines, 'clash' => $clash, 'new' => $new,
            'photos_on_staged' => count(array_filter($this->enrich, fn ($e) => $e['photo'] !== null)),
            'photos_on_new' => count(array_filter($this->inserts[self::FARM_GROUP] ?? [], fn ($x) => $x['_photo'] !== null)),
            'supp' => count($supp), 'supp_new' => count($suppNew),
            'areas_rows' => array_sum(array_map(fn ($a) => count($a['rows']), $areas)), 'areas_distinct' => count($areas),
        ];
    }

    /** The staged rows the batch fills, before it fills them, for the record. */
    public function enrichedBefore(): array
    {
        $ids = array_merge(...array_map(fn ($e) => $e['ids'], $this->enrich ?: [['ids' => []]]));
        $out = [];
        foreach (array_chunk($ids, 1000) as $chunk) {
            foreach (DB::connection(self::CONN)->table(ComputationSheetImport::TABLE)->whereIn('id', $chunk)
                ->get(['id', 'import_ref', 'sheet_name', 'source_row', 'farm_id', 'tag_no', 'first_name', 'last_name', 'district', 'staff_id', 'supervisor', 'camera', 'photo_path', 'photo_name', 'enriched_ref']) as $r) {
                $out[] = (array) $r;
            }
        }

        return $out;
    }

    public function commit(string $ref, ?int $userId, ?callable $progress = null): array
    {
        $db = DB::connection(self::CONN);
        $now = now();
        $copied = [];
        $copy = function (?string $rel) use (&$copied, $ref) {
            if ($rel === null || ! is_file($src = $this->dir . $rel)) return null;
            $hash = sha1_file($src);
            if (! isset($copied[$hash])) {
                $ext = strtolower(pathinfo($src, PATHINFO_EXTENSION)) ?: 'jpg';
                Storage::disk(self::DISK)->put($copied[$hash] = self::photoDir($ref) . "/$hash.$ext", file_get_contents($src));
            }

            return $copied[$hash];
        };

        // 1. Farmers already staged: fill their rows where blank, never over what is there.
        $filled = 0;
        foreach (array_chunk($this->enrich, 100) as $i => $chunk) {
            $work = array_map(fn ($e) => $e + ['path' => $copy($e['photo'])], $chunk);
            $db->transaction(function () use ($db, $work, $ref, $now, &$filled) {
                foreach ($work as $e) {
                    $q = fn () => $db->table(ComputationSheetImport::TABLE)->whereIn('id', $e['ids'])->whereNull('enriched_ref');
                    $filled += $q()->whereNull('photo_path')->whereNull('district')->update($e['fields'] + [
                        'photo_path' => $e['path'], 'photo_name' => $e['path'] ? $e['photo_name'] : null, 'enriched_ref' => $ref, 'updated_at' => $now]);
                    if ($e['tag_no'] !== null) {
                        $db->table(ComputationSheetImport::TABLE)->whereIn('id', $e['ids'])->where('enriched_ref', $ref)->whereNull('tag_no')
                            ->update(['tag_no' => mb_substr($e['tag_no'], 0, 100), 'enriched_tag_no' => true]);
                    }
                }
            });
            if ($progress && $i % 10 === 9) $progress('staged rows filled', $filled, count($copied));
        }
        if ($progress) $progress('staged rows filled', $filled, count($copied));

        // 2. New rows: farmers not staged (or staged under another name), the Supplementary lines the sheet lacks, Areas1.
        $inserted = 0;
        foreach ($this->inserts as $group => $rows) {
            foreach (array_chunk($rows, self::CHUNK) as $chunk) {
                $insert = array_map(fn ($r) => array_diff_key($r, ['_photo' => 1]) + [
                    'import_ref' => $ref, 'source_file' => self::SOURCE, 'sheet_name' => $group, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now,
                    'photo_path' => $copy($r['_photo'] ?? null),
                ], $chunk);
                foreach ($insert as &$x) if (! $x['photo_path']) $x['photo_name'] = null;
                unset($x);
                $db->transaction(fn () => $db->table(ComputationSheetImport::TABLE)->insert($insert));
                $inserted += count($insert);
            }
            if ($progress) $progress($group, $inserted, count($copied));
        }

        return ['filled' => $filled, 'inserted' => $inserted, 'photos' => count($copied)];
    }

    /** Remove a batch: its rows, what it filled on staged rows, and its photos. */
    public static function undo(string $ref): array
    {
        $db = DB::connection(self::CONN);
        $removed = ComputationSheetImport::undo($ref);
        $cleared = 0;
        do {
            $ids = $db->table(ComputationSheetImport::TABLE)->where('enriched_ref', $ref)->limit(1000)->pluck('id')->all();
            if (! $ids) break;
            $db->transaction(function () use ($db, $ids, &$cleared) {
                $db->table(ComputationSheetImport::TABLE)->whereIn('id', $ids)->where('enriched_tag_no', true)->update(['tag_no' => null, 'enriched_tag_no' => false]);
                $cleared += $db->table(ComputationSheetImport::TABLE)->whereIn('id', $ids)->update([
                    'district' => null, 'staff_id' => null, 'supervisor' => null, 'camera' => null, 'photo_path' => null, 'photo_name' => null, 'enriched_ref' => null]);
            });
        } while (true);
        Storage::disk(self::DISK)->deleteDirectory(self::photoDir($ref));

        return ['removed' => $removed, 'cleared' => $cleared];
    }

    /* ------------------------------------------------------------------ */

    /** Staged Computation Sheet lines (not this importer's own rows), by normalised Farm ID. */
    private function staged(): array
    {
        $out = [];
        DB::connection(self::CONN)->table(ComputationSheetImport::TABLE)->where('kind', 'line')->where('source_file', '<>', self::SOURCE)
            ->whereNotNull('farm_id')->orderBy('id')
            ->select(['id', 'sheet_name', 'farm_id', 'first_name', 'last_name', 'area_m2'])
            ->chunk(2000, function ($rows) use (&$out) {
                foreach ($rows as $c) {
                    $c->name_key = self::nameKey($c->first_name, $c->last_name);
                    $out[self::norm($c->farm_id)][] = $c;
                }
            });

        return $out;
    }

    /** Farm IDs and names compared on letters and digits only, any case ("2873d" = "2873D", "Alh. Ibrahim" = "ALH IBRAHIM"). */
    private static function norm($v): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $v));
    }

    private static function nameKey($first, $last): string
    {
        return self::norm($first) . '|' . self::norm($last);
    }

    private function blank(): array
    {
        return ['tag_no' => null, 'sn_written' => null, 'first_name' => null, 'middle_name' => null, 'last_name' => null, 'sheet_no' => null,
            'area_m2' => null, 'area_ha' => null, 'creatable' => null, 'govt_50' => null, 'farm_50' => null, 'plots' => null, 'remarks' => null,
            'district' => null, 'staff_id' => null, 'supervisor' => null, 'camera' => null, 'photo_name' => null, '_photo' => null];
    }

    private function plots(array $r, string $h, string $m, string $l): ?string
    {
        $parts = [];
        foreach (['High' => $h, 'Medium' => $m, 'Low' => $l] as $label => $col) {
            if (($v = $this->text($r[$col] ?? null)) !== null) $parts[] = "$label $v";
        }

        return $parts ? implode(' · ', $parts) : null;
    }

    /** Trimmed cell, null when empty. */
    private function text($s): ?string
    {
        $s = trim((string) $s);

        return $s === '' ? null : mb_substr($s, 0, 255);
    }

    /** attachments_index.csv: the photo's name in the database (table|row => original name). */
    private function originals(): array
    {
        $out = [];
        if (is_file($file = $this->dir . 'attachments_index.csv')) {
            foreach ($this->csv($file) as $a) $out[$a['table'] . '|' . (int) $a['row_no']] = $a['original_name'];
        }

        return $out;
    }

    /** @return array<int, array<string, ?string>> */
    private function csv(string $file): array
    {
        $h = fopen($file, 'r');
        $head = fgetcsv($h);
        $head[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $head[0]);
        $rows = [];
        while (($line = fgetcsv($h)) !== false) {
            if ($line === [null]) continue;
            $rows[] = array_combine($head, array_slice(array_pad($line, count($head), null), 0, count($head)));
        }
        fclose($h);

        return $rows;
    }
}
