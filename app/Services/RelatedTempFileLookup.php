<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Related and Temporary file numbers for a page of files, for the expandable rows on
 * the Indexed Files, MLPP FileNo Generator, Related Files and Re-grant tables
 * (Change Request 22102026, items 4-6).
 *
 * The two are kept apart: a Temporary File is NOT a Related File. Anything that looks
 * like a temporary number (TEMP-..., "...(T)") or is recorded as the file's temp number
 * is listed under "temporary" only, never under "related".
 */
class RelatedTempFileLookup
{
    /** Hard cap per call: one table page. */
    public const MAX_FILES = 200;

    /**
     * @param  string[]  $fileNumbers
     * @return array<string, array{related: array<int, array{file_number: string, file_title: ?string}>, temporary: array<int, array{file_number: string, file_title: ?string}>}>
     *         keyed by the file number as given; files with neither are left out.
     */
    public function forFiles(array $fileNumbers): array
    {
        $keys = [];
        foreach ($fileNumbers as $fn) {
            $fn = trim((string) $fn);
            if ($fn !== '' && $fn !== '-') {
                $keys[self::norm($fn)] = $fn;
            }
            if (count($keys) >= self::MAX_FILES) {
                break;
            }
        }
        if (!$keys) {
            return [];
        }

        $db = DB::connection('sqlsrv');
        // Plain IN on the columns (CI collation, trailing blanks ignored) so the indexes
        // are used; matching back to the key is done in PHP with norm().
        $upper = array_keys($keys);
        $related = [];   // norm(file) => [norm(related) => original]
        $temp = [];      // norm(file) => [norm(temp) => original]

        $add = function (array &$bucket, string $file, $value) {
            $value = trim((string) $value);
            if ($value === '' || $value === '-' || $value === '[]' || strtoupper($value) === 'NONE') {
                return;
            }
            $bucket[$file][self::norm($value)] = $value;
        };

        // 1. file_indexings: related_fileno (JSON list) and temp_file_no. A record is
        //    reached by its file number or, for a temp-only record, by its temp number.
        foreach (array_chunk($upper, 200) as $chunk) {
            $rows = $db->table('file_indexings')
                ->whereNull('deleted_at')
                ->where(function ($q) use ($chunk) {
                    $q->whereIn('file_number', $chunk)
                        ->orWhereIn('temp_file_no', $chunk);
                })
                ->get(['id', 'file_number', 'temp_file_no', 'related_fileno']);

            $idToKeys = [];
            foreach ($rows as $row) {
                foreach ([$row->file_number, $row->temp_file_no] as $own) {
                    $k = self::norm((string) $own);
                    if (!isset($keys[$k])) {
                        continue;
                    }
                    $idToKeys[$row->id][] = $k;
                    $add($temp, $k, $row->temp_file_no);
                    if (self::norm((string) $row->file_number) !== $k) {
                        // Reached through its temp number: the main number is related.
                        $add($related, $k, $row->file_number);
                    }
                    foreach (self::parseList($row->related_fileno) as $r) {
                        $add($related, $k, $r);
                    }
                }
            }

            // file_indexing_links: the "related file" rows captured on the indexing form.
            if ($idToKeys) {
                $links = $db->table('file_indexing_links')
                    ->whereIn('file_indexing_id', array_keys($idToKeys))
                    ->get(['file_indexing_id', 'file_number']);
                foreach ($links as $link) {
                    foreach (array_unique($idToKeys[$link->file_indexing_id] ?? []) as $k) {
                        $add($related, $k, $link->file_number);
                    }
                }
            }
        }

        // 2. fileNumber (commissioning): related_fileno and temp_fileno / temp_file_no,
        //    on whichever column holds the number.
        foreach (array_chunk($upper, 200) as $chunk) {
            $rows = $db->table('fileNumber')
                ->where(function ($q) use ($chunk) {
                    foreach (['mlsfNo', 'kangisFileNo', 'NewKANGISFileNo'] as $col) {
                        $q->orWhereIn($col, $chunk);
                    }
                })
                ->get(['mlsfNo', 'kangisFileNo', 'NewKANGISFileNo', 'related_fileno', 'temp_fileno', 'temp_file_no']);
            foreach ($rows as $row) {
                foreach (array_unique([self::norm((string) $row->mlsfNo), self::norm((string) $row->kangisFileNo), self::norm((string) $row->NewKANGISFileNo)]) as $k) {
                    if (!isset($keys[$k])) {
                        continue;
                    }
                    $add($temp, $k, $row->temp_fileno);
                    $add($temp, $k, $row->temp_file_no);
                    foreach (self::parseList($row->related_fileno) as $r) {
                        $add($related, $k, $r);
                    }
                }
            }
        }

        // 3. related_file_number register (the Related Files table's own source).
        foreach (array_chunk($upper, 200) as $chunk) {
            $rows = $db->table('related_file_number')
                ->whereIn('file_number', $chunk)
                ->whereNotIn('source_table', ['pic', 'dciv_file_no'])
                ->get(['file_number', 'related_fileno']);
            foreach ($rows as $row) {
                $add($related, self::norm((string) $row->file_number), $row->related_fileno);
            }
        }

        // Split: temp-looking numbers move to "temporary"; drop the file itself.
        $result = [];
        $allNumbers = [];
        foreach ($keys as $k => $given) {
            $t = $temp[$k] ?? [];
            $r = [];
            foreach ($related[$k] ?? [] as $nk => $value) {
                if ($nk === $k || isset($t[$nk])) {
                    continue;
                }
                if (self::looksTemporary($value)) {
                    $t[$nk] = $value;
                    continue;
                }
                $r[$nk] = $value;
            }
            unset($t[$k]);
            if (!$r && !$t) {
                continue;
            }
            $result[$given] = ['related' => array_values($r), 'temporary' => array_values($t)];
            $allNumbers = array_merge($allNumbers, array_keys($r), array_keys($t));
        }

        // Titles, one batch: indexed title first, then the commissioning FileName.
        $titles = $this->titles(array_values(array_unique($allNumbers)));
        foreach ($result as &$entry) {
            foreach (['related', 'temporary'] as $kind) {
                $entry[$kind] = array_map(fn ($no) => [
                    'file_number' => $no,
                    'file_title' => $titles[self::norm($no)] ?? null,
                ], $entry[$kind]);
            }
        }
        unset($entry);

        return $result;
    }

    /**
     * Row details for the expanded table: title, location, plot / TP / LPKN and the
     * indexed record's id, from the latest file_indexings row (by file number or temp
     * number), else the commissioning fileNumber row.
     *
     * @param  string[]  $numbers
     * @return array<string, array<string, mixed>>  keyed by the number as given
     */
    public function details(array $numbers): array
    {
        $keys = [];
        foreach ($numbers as $no) {
            $no = trim((string) $no);
            if ($no !== '') {
                $keys[self::norm($no)] = $no;
            }
            if (count($keys) >= self::MAX_FILES) {
                break;
            }
        }
        if (!$keys) {
            return [];
        }
        $db = DB::connection('sqlsrv');
        $found = [];
        $rows = $db->table('file_indexings')
            ->whereNull('deleted_at')
            ->where(function ($q) use ($keys) {
                $q->whereIn('file_number', array_keys($keys))->orWhereIn('temp_file_no', array_keys($keys));
            })
            ->orderBy('id')
            ->get(['id', 'file_number', 'temp_file_no', 'file_title', 'location', 'plot_number', 'tp_no', 'lpkn_no']);
        foreach ($rows as $row) {
            foreach ([$row->file_number, $row->temp_file_no] as $no) {
                $k = self::norm((string) $no);
                if (isset($keys[$k])) {
                    $found[$k] = [ // later id wins: latest indexed record
                        'indexing_id' => (int) $row->id,
                        'file_title' => $row->file_title,
                        'location' => $row->location,
                        'plot_number' => $row->plot_number,
                        'tp_no' => $row->tp_no,
                        'lpkn_no' => $row->lpkn_no,
                    ];
                }
            }
        }

        $missing = array_values(array_diff(array_keys($keys), array_keys($found)));
        if ($missing) {
            $rows = $db->table('fileNumber')
                ->where(function ($q) use ($missing) {
                    foreach (['mlsfNo', 'kangisFileNo', 'NewKANGISFileNo'] as $col) {
                        $q->orWhereIn($col, $missing);
                    }
                })
                ->get(['mlsfNo', 'kangisFileNo', 'NewKANGISFileNo', 'FileName', 'location', 'plot_no', 'tp_no']);
            foreach ($rows as $row) {
                foreach ([$row->mlsfNo, $row->kangisFileNo, $row->NewKANGISFileNo] as $no) {
                    $k = self::norm((string) $no);
                    if (in_array($k, $missing, true) && !isset($found[$k])) {
                        $found[$k] = [
                            'indexing_id' => null,
                            'file_title' => $row->FileName,
                            'location' => $row->location,
                            'plot_number' => $row->plot_no,
                            'tp_no' => $row->tp_no,
                            'lpkn_no' => null,
                        ];
                    }
                }
            }
        }

        $out = [];
        foreach ($found as $k => $d) {
            $out[$keys[$k]] = $d;
        }
        return $out;
    }

    /** @param string[] $normNumbers @return array<string, string> */
    private function titles(array $normNumbers): array
    {
        $titles = [];
        $db = DB::connection('sqlsrv');
        foreach (array_chunk($normNumbers, 200) as $chunk) {
            $rows = $db->table('file_indexings')
                ->whereNull('deleted_at')
                ->where(function ($q) use ($chunk) {
                    $q->whereIn('file_number', $chunk)
                        ->orWhereIn('temp_file_no', $chunk);
                })
                ->whereNotNull('file_title')
                ->orderBy('id')
                ->get(['file_number', 'temp_file_no', 'file_title']);
            foreach ($rows as $row) {
                foreach ([$row->file_number, $row->temp_file_no] as $no) {
                    $k = self::norm((string) $no);
                    if ($k !== '' && in_array($k, $chunk, true) && trim((string) $row->file_title) !== '') {
                        $titles[$k] = $row->file_title;
                    }
                }
            }

            $missing = array_values(array_diff($chunk, array_keys($titles)));
            if ($missing) {
                $rows = $db->table('fileNumber')
                    ->where(function ($q) use ($missing) {
                        foreach (['mlsfNo', 'kangisFileNo', 'NewKANGISFileNo'] as $col) {
                            $q->orWhereIn($col, $missing);
                        }
                    })
                    ->whereNotNull('FileName')
                    ->get(['mlsfNo', 'kangisFileNo', 'NewKANGISFileNo', 'FileName']);
                foreach ($rows as $row) {
                    foreach ([$row->mlsfNo, $row->kangisFileNo, $row->NewKANGISFileNo] as $no) {
                        $k = self::norm((string) $no);
                        if (in_array($k, $missing, true) && !isset($titles[$k]) && trim((string) $row->FileName) !== '') {
                            $titles[$k] = $row->FileName;
                        }
                    }
                }
            }
        }
        return $titles;
    }

    /** JSON list, or a single plain value. @return string[] */
    private static function parseList($raw): array
    {
        $raw = trim((string) $raw);
        if ($raw === '' || $raw === '[]') {
            return [];
        }
        if ($raw[0] === '[') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return array_values(array_filter(array_map(fn ($v) => is_scalar($v) ? trim((string) $v) : '', $decoded)));
            }
        }
        return [$raw];
    }

    public static function looksTemporary(string $value): bool
    {
        $v = strtoupper(trim($value));
        return str_starts_with($v, 'TEMP') || (bool) preg_match('/\(T\)\s*$/', $v);
    }

    private static function norm(string $value): string
    {
        return strtoupper(trim($value));
    }
}
