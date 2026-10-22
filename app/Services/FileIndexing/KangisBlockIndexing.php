<?php

namespace App\Services\FileIndexing;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * KANGIS Block Indexing (user, 2026-10-17).
 *
 * On a KANGIS Registry record the KANGIS number is file_indexings.file_number and
 * the land (MLS) file is the Related File No (file_indexings.related_fileno, a JSON
 * list). A land file may be related to ONE KANGIS file. The "KANGIS Block Indexing"
 * box on the KANGIS index (offered to config('fileindexing.kangis_block_usernames'))
 * marks the land file "multiple KANGIS files", after which it may be related to any
 * number of them.
 *
 * Both facts live on the land file's own file_indexings row(s):
 *   kangis_link_count     live KANGIS records carrying it (recounted on every save)
 *   allow_multiple_kangis the box's Yes
 * A land file that is not indexed yet has no row: the rule still holds (it is read
 * from the KANGIS records themselves) but the count and the Yes are not stored, so
 * the box must be ticked again on its next KANGIS file.
 *
 * Existing multi-related land files were exported to CSV for treatment and are
 * neither marked nor changed; edits only check land files newly added to a record.
 */
class KangisBlockIndexing
{
    public const REQUEST_FIELD = 'kangis_block_index';

    public const REGISTRY = 'KANGIS Registry';

    public static function canUse(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        $allowed = array_map('strtolower', (array) config('fileindexing.kangis_block_usernames', []));
        if (in_array('*', $allowed, true)) {
            return true;
        }

        return in_array(strtolower(trim((string) $user->username)), $allowed, true);
    }

    public static function isKangisRegistry(?string $generalRegistry): bool
    {
        return strcasecmp(trim((string) $generalRegistry), self::REGISTRY) === 0;
    }

    /** Comparison key: upper-case, whitespace removed. */
    public static function key(?string $fileNo): string
    {
        return strtoupper((string) preg_replace('/\s+/', '', (string) $fileNo));
    }

    /**
     * Land file numbers carried by a related_fileno value (JSON list, array or plain string).
     *
     * @return string[]
     */
    public static function landFilesFrom($relatedFileno): array
    {
        if (is_string($relatedFileno)) {
            $trimmed = trim($relatedFileno);
            if ($trimmed === '') {
                return [];
            }
            $decoded = str_starts_with($trimmed, '[') ? json_decode($trimmed, true) : null;
            $relatedFileno = is_array($decoded) ? $decoded : [$trimmed];
        }

        if (!is_array($relatedFileno)) {
            return [];
        }

        $out = [];
        foreach ($relatedFileno as $value) {
            $value = trim((string) (is_array($value) ? ($value['file_number'] ?? '') : $value));
            if ($value !== '' && !isset($out[self::key($value)])) {
                $out[self::key($value)] = $value;
            }
        }

        return array_values($out);
    }

    /**
     * KANGIS file numbers of the live KANGIS Registry records carrying this land file.
     *
     * @return string[]
     */
    public function kangisFilesFor(string $landFileNo, ?int $excludeFileIndexingId = null): array
    {
        if (self::key($landFileNo) === '') {
            return [];
        }

        return $this->kangisRecordsFor($landFileNo, $excludeFileIndexingId)
            ->pluck('file_number')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** Live KANGIS Registry records carrying this land file as their Related File No. */
    private function kangisRecordsFor(string $landFileNo, ?int $excludeFileIndexingId = null)
    {
        $key = self::key($landFileNo);

        // related_fileno is a JSON list (["RES-2015-1254"]) or, on old rows, a bare
        // number. LIKE wildcards in the number are escaped for SQL Server.
        $likeKey = str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $key);
        $column = "REPLACE(REPLACE(REPLACE(UPPER(related_fileno), ' ', ''), CHAR(9), ''), CHAR(10), '')";

        return DB::connection('sqlsrv')
            ->table('file_indexings')
            ->where('general_registry', self::REGISTRY)
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->where(fn ($q) => $q->whereNull('is_decommissioned')->orWhere('is_decommissioned', 0))
            ->when($excludeFileIndexingId, fn ($q) => $q->where('id', '!=', $excludeFileIndexingId))
            ->where(function ($q) use ($column, $likeKey, $key) {
                $q->whereRaw("{$column} LIKE ?", ['%"' . $likeKey . '"%'])
                    ->orWhereRaw("{$column} = ?", [$key]);
            })
            ->orderBy('id');
    }

    /** The land file's own (non-KANGIS) index rows. */
    private function landRows(string $landFileNo)
    {
        return DB::connection('sqlsrv')
            ->table('file_indexings')
            ->whereRaw("REPLACE(REPLACE(UPPER(file_number), ' ', ''), CHAR(9), '') = ?", [self::key($landFileNo)])
            ->where(fn ($q) => $q->whereNull('general_registry')->orWhere('general_registry', '!=', self::REGISTRY))
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0));
    }

    public function allowsMultiple(string $landFileNo): bool
    {
        return self::key($landFileNo) !== ''
            && $this->landRows($landFileNo)->where('allow_multiple_kangis', 1)->exists();
    }

    /**
     * Where the land file stands, for the form's confirmation.
     *
     * @return array{land_file:string, kangis_files:string[], allow_multiple:bool, indexed:bool}
     */
    public function status(string $landFileNo, ?int $excludeFileIndexingId = null): array
    {
        return [
            'land_file' => trim($landFileNo),
            'kangis_files' => $this->kangisFilesFor($landFileNo, $excludeFileIndexingId),
            'allow_multiple' => $this->allowsMultiple($landFileNo),
            'indexed' => $this->landRows($landFileNo)->exists(),
        ];
    }

    /**
     * Land files already related to another live KANGIS record and not marked multiple.
     *
     * @param  string[]  $landFileNos
     * @return array<string, string[]>  land file => KANGIS file numbers holding it
     */
    public function conflicts(array $landFileNos, ?int $excludeFileIndexingId = null): array
    {
        $conflicts = [];
        foreach ($landFileNos as $landFileNo) {
            if (self::key($landFileNo) === '' || $this->allowsMultiple($landFileNo)) {
                continue;
            }
            $holders = $this->kangisFilesFor($landFileNo, $excludeFileIndexingId);
            if (!empty($holders)) {
                $conflicts[$landFileNo] = $holders;
            }
        }

        return $conflicts;
    }

    public function refusalMessage(array $conflicts): string
    {
        $parts = [];
        foreach ($conflicts as $landFileNo => $kangisFiles) {
            $parts[] = "{$landFileNo} is already related to KANGIS file " . implode(', ', $kangisFiles);
        }

        return implode('; ', $parts)
            . '. A land file may be related to one KANGIS file only unless KANGIS Block Indexing is ticked.';
    }

    /**
     * The box's Yes: mark the land files "multiple KANGIS files" on their index rows.
     *
     * @param  string[]  $landFileNos
     */
    public function markMultiple(array $landFileNos): void
    {
        foreach ($landFileNos as $landFileNo) {
            if (self::key($landFileNo) !== '') {
                $this->landRows($landFileNo)->update(['allow_multiple_kangis' => 1]);
            }
        }
    }

    /** file_indexings.plot_number is nvarchar(100) (and indexed, so not widened casually). */
    public const PLOT_NUMBER_MAX = 100;

    /**
     * A Plot Number value as a list: "1, 2 and 4" / "1, 2 & 4" / "1,2,and 4" -> [1, 2, 4].
     *
     * @return string[]
     */
    public static function plotList(?string $value): array
    {
        $parts = preg_split('/\s*,\s*(?:and\s+|&\s*)?|\s+(?:and|&)\s+|\s*&\s*/i', trim((string) $value)) ?: [];

        return array_values(array_filter(array_map('trim', $parts), fn ($p) => $p !== ''));
    }

    /** [1, 2, 4] -> "1, 2 and 4". */
    public static function formatPlots(array $plots): string
    {
        $plots = array_values($plots);
        if (count($plots) <= 1) {
            return (string) ($plots[0] ?? '');
        }
        $last = array_pop($plots);

        return implode(', ', $plots) . ' and ' . $last;
    }

    /**
     * Add a KANGIS file's plot to the land file's Plot Number list (KANGIS Block
     * Indexing), replacing $replace when the KANGIS file's plot was changed. A plot
     * already listed is not repeated. Returns false when the list would not fit.
     *
     * The swap never removes the first plot (the land file's own, appends go after
     * it) nor one another KANGIS file on this land file still carries.
     */
    public function addPlotToLandFile(string $landFileNo, string $plot, ?string $replace = null, ?int $kangisFileIndexingId = null): bool
    {
        $plot = trim($plot);
        $replace = $replace !== null ? trim($replace) : null;
        if (self::key($landFileNo) === '' || $plot === '') {
            return true;
        }
        $same = fn ($a, $b) => self::key($a) === self::key($b);

        if ($replace !== null && !$same($replace, $plot)) {
            $stillUsed = $this->kangisRecordsFor($landFileNo, $kangisFileIndexingId)
                ->pluck('plot_number')
                ->contains(fn ($p) => $same((string) $p, $replace));
            if ($stillUsed) {
                $replace = null;
            }
        }

        foreach ($this->landRows($landFileNo)->get(['id', 'plot_number']) as $row) {
            $plots = self::plotList($row->plot_number);
            if ($replace !== null && !$same($replace, $plot)) {
                $plots = array_values(array_filter($plots, fn ($p, $i) => $i === 0 || !$same($p, $replace), ARRAY_FILTER_USE_BOTH));
            }
            if (!array_filter($plots, fn ($p) => $same($p, $plot))) {
                $plots[] = $plot;
            }

            $value = self::formatPlots($plots);
            if ($value === (string) $row->plot_number) {
                continue;
            }
            if (mb_strlen($value) > self::PLOT_NUMBER_MAX) {
                return false;
            }
            DB::connection('sqlsrv')->table('file_indexings')->where('id', $row->id)->update(['plot_number' => $value]);
        }

        return true;
    }

    /**
     * Add a KANGIS file's holder name to the land file's File Title (KANGIS Block
     * Indexing, user 2026-10-20): "KUMBO DAUDA and KATO IDRIS", "A, B and C". Names are
     * only ever added on save (an edit never swaps one out), and a name already in the
     * title is not repeated.
     *
     * The title is rebuilt from kangis_base_title (the land file's own title, kept the
     * first time) plus kangis_holder_names, never re-read from the title itself: a
     * holder name can contain "AND". Each listed name remembers the KANGIS records that
     * brought it ({"name","ids"}), so deleting a KANGIS record takes back only its own
     * name (removeKangisFromLandFiles). Plain-string entries are from before that.
     */
    public function addNameToLandFile(string $landFileNo, string $name, ?int $kangisFileIndexingId = null): void
    {
        $name = trim(preg_replace('/\s+/', ' ', $name));
        if (self::key($landFileNo) === '' || $name === '') {
            return;
        }
        $same = fn ($a, $b) => self::key($a) === self::key($b);

        foreach ($this->landRows($landFileNo)->get(['id', 'file_title', 'kangis_base_title', 'kangis_holder_names']) as $row) {
            [$base, $entries] = $this->titleParts($row);

            if ($base !== '' && $same($base, $name)) {
                continue;
            }
            $found = false;
            foreach ($entries as &$entry) {
                if ($same($entry['name'], $name)) {
                    $found = true;
                    if ($kangisFileIndexingId && !in_array($kangisFileIndexingId, $entry['ids'], true)) {
                        $entry['ids'][] = $kangisFileIndexingId;
                    }
                }
            }
            unset($entry);
            if (!$found) {
                $entries[] = ['name' => $name, 'ids' => $kangisFileIndexingId ? [$kangisFileIndexingId] : []];
            }
            $this->saveTitle((int) $row->id, $base, $entries);
        }
    }

    /**
     * A KANGIS record is gone (Master Delete / moved to Indexing Duplicates): take its
     * name and plot back off each of its land files, then recount. The land file's own
     * title and first plot are never removed, nor a name or plot another live KANGIS
     * record on the land file still carries.
     *
     * @param  object  $kangisRow  the KANGIS file_indexings row as it was (id, file_title,
     *                             plot_number, related_fileno, general_registry)
     */
    public function removeKangisFromLandFiles(object $kangisRow): void
    {
        if (!self::isKangisRegistry($kangisRow->general_registry ?? null)) {
            return;
        }
        $kangisId = (int) ($kangisRow->id ?? 0);
        $landFiles = self::landFilesFrom($kangisRow->related_fileno ?? null);
        $title = trim(preg_replace('/\s+/', ' ', (string) ($kangisRow->file_title ?? '')));
        $plots = self::plotList($kangisRow->plot_number ?? null);
        $same = fn ($a, $b) => self::key($a) === self::key($b);

        foreach (array_values($landFiles) as $i => $landFile) {
            $others = $this->kangisRecordsFor($landFile, $kangisId ?: null)->get(['id', 'file_title', 'plot_number']);
            // This KANGIS file's plot for this land file: the whole value for one land file,
            // its list item by position for several (as it was added).
            $plot = count($landFiles) === 1
                ? trim((string) ($kangisRow->plot_number ?? ''))
                : (count($plots) === count($landFiles) ? $plots[$i] : '');
            $plotStillUsed = $plot === '' || $others->contains(
                fn ($o) => $same((string) $o->plot_number, $plot)
                    || (bool) array_filter(self::plotList($o->plot_number), fn ($p) => $same($p, $plot))
            );

            foreach ($this->landRows($landFile)->get(['id', 'file_title', 'plot_number', 'kangis_base_title', 'kangis_holder_names']) as $row) {
                $update = [];

                if (!$plotStillUsed) {
                    $list = self::plotList($row->plot_number);
                    $kept = array_values(array_filter($list, fn ($p, $j) => $j === 0 || !$same($p, $plot), ARRAY_FILTER_USE_BOTH));
                    if (count($kept) !== count($list)) {
                        $update['plot_number'] = self::formatPlots($kept);
                    }
                }

                if ($row->kangis_holder_names !== null) {
                    [$base, $entries] = $this->titleParts($row);
                    $before = $entries;
                    $entries = array_values(array_filter(array_map(function ($e) use ($kangisId, $title, $same, $others) {
                        if ($kangisId && in_array($kangisId, $e['ids'], true)) {
                            $e['ids'] = array_values(array_diff($e['ids'], [$kangisId]));
                            return $e['ids'] ? $e : null;
                        }
                        // Entry from before ids were kept: drop it when it is this record's title
                        // and no other live KANGIS record on the land file has that title.
                        if (!$e['ids'] && $title !== '' && $same($e['name'], $title)
                            && !$others->contains(fn ($o) => $same((string) $o->file_title, $title))) {
                            return null;
                        }
                        return $e;
                    }, $entries)));
                    if ($entries != $before) {
                        $update += $this->titleColumns($base, $entries);
                    }
                }

                if ($update) {
                    DB::connection('sqlsrv')->table('file_indexings')->where('id', $row->id)->update($update);
                }
            }
        }
        $this->recount($landFiles);
        // ...off the land files' own Related File No. and links...
        foreach ($landFiles as $landFile) {
            $this->removeKangisFromLandRelated($landFile, (string) ($kangisRow->file_number ?? ''));
        }
        // ...and off the land files' row in the related_file_number register.
        app(\App\Services\RelatedFileNumberRegistrar::class)->detachKangis((string) ($kangisRow->file_number ?? ''), $landFiles);
    }

    /** [$base, $entries] for a land row; a title changed by hand since it was built becomes the new base. */
    private function titleParts(object $row): array
    {
        $base = $row->kangis_base_title !== null ? trim((string) $row->kangis_base_title) : trim((string) $row->file_title);
        $raw = json_decode((string) $row->kangis_holder_names, true);
        $entries = [];
        foreach (is_array($raw) ? $raw : [] as $item) {
            $name = trim((string) (is_array($item) ? ($item['name'] ?? '') : $item));
            if ($name !== '') {
                $entries[] = ['name' => $name, 'ids' => is_array($item) ? array_values(array_map('intval', (array) ($item['ids'] ?? []))) : []];
            }
        }
        if ($row->kangis_base_title !== null && trim((string) $row->file_title) !== $this->titleFrom($base, $entries)) {
            return [trim((string) $row->file_title), []];
        }

        return [$base, $entries];
    }

    private function titleFrom(string $base, array $entries): string
    {
        return self::formatPlots(array_merge($base !== '' ? [$base] : [], array_column($entries, 'name')));
    }

    private function titleColumns(string $base, array $entries): array
    {
        return [
            'file_title' => $this->titleFrom($base, $entries),
            'kangis_base_title' => $base,
            'kangis_holder_names' => json_encode($entries, JSON_UNESCAPED_UNICODE),
        ];
    }

    private function saveTitle(int $rowId, string $base, array $entries): void
    {
        DB::connection('sqlsrv')->table('file_indexings')->where('id', $rowId)->update($this->titleColumns($base, $entries));
    }

    /**
     * Editing a shared land file (user, 2026-10-20): the edit form saves the title and
     * plot it shows, which used to wipe the KANGIS holders' names and plots. Here the
     * submitted title replaces only the land file's own name (the added names stay after
     * it) and a single submitted plot replaces only the first plot. A submitted title
     * that already carries added names is taken as a full hand edit.
     */
    public function keepKangisAdditions(int $landRowId, array $payload): array
    {
        $row = DB::connection('sqlsrv')->table('file_indexings')->where('id', $landRowId)
            ->first(['id', 'file_title', 'plot_number', 'kangis_base_title', 'kangis_holder_names', 'allow_multiple_kangis']);
        if (!$row || $row->kangis_base_title === null && !(int) $row->allow_multiple_kangis) {
            return $payload;
        }
        $same = fn ($a, $b) => self::key($a) === self::key($b);

        if (array_key_exists('file_title', $payload) && $row->kangis_base_title !== null) {
            $submitted = trim(preg_replace('/\s+/', ' ', (string) $payload['file_title']));
            [$base, $entries] = $this->titleParts($row);
            if ($entries && $submitted !== '' && !$same($submitted, (string) $row->file_title)) {
                $carriesNames = (bool) array_filter($entries, fn ($e) => str_contains(self::key($submitted), self::key($e['name'])));
                if ($carriesNames) {
                    $payload['kangis_base_title'] = $submitted;
                    $payload['kangis_holder_names'] = json_encode([], JSON_UNESCAPED_UNICODE);
                } else {
                    $payload = array_merge($payload, $this->titleColumns($submitted, $entries));
                }
            } elseif ($entries && $same($submitted, (string) $row->file_title)) {
                $payload['file_title'] = $row->file_title;
            }
        }

        if (array_key_exists('plot_number', $payload)) {
            $current = self::plotList($row->plot_number);
            $submitted = trim((string) $payload['plot_number']);
            if (count($current) > 1 && $submitted !== '' && !$same($submitted, (string) $row->plot_number)
                && count(self::plotList($submitted)) === 1) {
                $current[0] = $submitted;
                $value = self::formatPlots(array_values(array_unique($current)));
                if (mb_strlen($value) <= self::PLOT_NUMBER_MAX) {
                    $payload['plot_number'] = $value;
                }
            }
        }

        return $payload;
    }

    /**
     * Rebuild a land file's KANGIS names and plots from the live KANGIS records on it
     * (repair). Keeps the land file's own title and first plot.
     *
     * @return array<int, array{before:object, after:array}>
     */
    public function rebuildLandFile(string $landFileNo): array
    {
        $records = $this->kangisRecordsFor($landFileNo)->get(['id', 'file_title', 'plot_number', 'related_fileno']);
        $same = fn ($a, $b) => self::key($a) === self::key($b);
        $done = [];
        foreach ($this->landRows($landFileNo)->get(['id', 'file_title', 'plot_number', 'kangis_base_title', 'kangis_holder_names']) as $row) {
            $base = $row->kangis_base_title !== null ? trim((string) $row->kangis_base_title) : trim((string) $row->file_title);
            $plots = array_slice(self::plotList($row->plot_number), 0, 1);
            $entries = [];
            foreach ($records as $rec) {
                $name = trim(preg_replace('/\s+/', ' ', (string) $rec->file_title));
                if ($name !== '' && !$same($name, $base)) {
                    $k = array_search(self::key($name), array_map(fn ($e) => self::key($e['name']), $entries), true);
                    if ($k === false) {
                        $entries[] = ['name' => $name, 'ids' => [(int) $rec->id]];
                    } else {
                        $entries[$k]['ids'][] = (int) $rec->id;
                    }
                }
                $lands = self::landFilesFrom($rec->related_fileno);
                $list = self::plotList($rec->plot_number);
                $pos = array_search(self::key($landFileNo), array_map([self::class, 'key'], $lands), true);
                $plot = count($lands) === 1 ? trim((string) $rec->plot_number) : (count($list) === count($lands) && $pos !== false ? $list[$pos] : '');
                if ($plot !== '' && !array_filter($plots, fn ($p) => $same($p, $plot))) {
                    $plots[] = $plot;
                }
            }
            $after = $this->titleColumns($base, $entries) + ['plot_number' => self::formatPlots($plots)];
            if (mb_strlen($after['plot_number']) > self::PLOT_NUMBER_MAX) {
                unset($after['plot_number']);
            }
            DB::connection('sqlsrv')->table('file_indexings')->where('id', $row->id)->update($after);
            $done[] = ['before' => $row, 'after' => $after];
        }
        $this->recount([$landFileNo]);

        return $done;
    }

    /**
     * The KANGIS files on a shared land file add up in the land file's own Related File
     * No. (user, 2026-10-20): "KNGP 28" -> "KNGP 28, KNGP 6". The number goes into its
     * related_fileno JSON list and gets a file_indexing_links row, which is what
     * "View Related FileNo(s)" reads. Already listed: nothing changes.
     */
    public function addKangisToLandRelated(string $landFileNo, int $kangisFileIndexingId): void
    {
        $kangis = DB::connection('sqlsrv')->table('file_indexings')->where('id', $kangisFileIndexingId)
            ->first(['id', 'file_number', 'file_title', 'plot_number', 'location', 'land_use_type', 'district', 'lga']);
        $kangisNo = trim((string) ($kangis->file_number ?? ''));
        if (!$kangis || $kangisNo === '' || self::key($landFileNo) === '') {
            return;
        }

        foreach ($this->landRows($landFileNo)->get(['id', 'related_fileno', 'tracking_id', 'indexing_type']) as $row) {
            $list = self::landFilesFrom($row->related_fileno);
            if (!array_filter($list, fn ($n) => self::key($n) === self::key($kangisNo))) {
                $list[] = $kangisNo;
                DB::connection('sqlsrv')->table('file_indexings')->where('id', $row->id)
                    ->update(['related_fileno' => json_encode(array_values($list), JSON_UNESCAPED_UNICODE)]);
            }
            $linked = DB::connection('sqlsrv')->table('file_indexing_links')
                ->where('file_indexing_id', $row->id)
                ->whereRaw("REPLACE(UPPER(LTRIM(RTRIM(file_number))), ' ', '') = ?", [self::key($kangisNo)])
                ->exists();
            if (!$linked) {
                $by = auth()->user()->userName ?? auth()->user()->name ?? 'System';
                DB::connection('sqlsrv')->table('file_indexing_links')->insert([
                    'file_indexing_id' => $row->id,
                    'file_number' => $kangisNo,
                    'indexing_type' => $row->indexing_type ?? 'Regular',
                    'file_title' => $kangis->file_title,
                    'plot_number' => $kangis->plot_number,
                    'location' => $kangis->location,
                    'land_use_type' => $kangis->land_use_type,
                    'district' => $kangis->district,
                    'lga' => $kangis->lga,
                    'tracking_id' => $row->tracking_id,
                    'created_by' => $by,
                    'updated_by' => $by,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /** A KANGIS file is gone or no longer on the land file: off its Related File No. and links. */
    public function removeKangisFromLandRelated(string $landFileNo, string $kangisNo): void
    {
        $kangisNo = trim($kangisNo);
        if ($kangisNo === '' || self::key($landFileNo) === '') {
            return;
        }
        foreach ($this->landRows($landFileNo)->get(['id', 'related_fileno']) as $row) {
            $list = self::landFilesFrom($row->related_fileno);
            $kept = array_values(array_filter($list, fn ($n) => self::key($n) !== self::key($kangisNo)));
            if (count($kept) !== count($list)) {
                DB::connection('sqlsrv')->table('file_indexings')->where('id', $row->id)
                    ->update(['related_fileno' => $kept ? json_encode($kept, JSON_UNESCAPED_UNICODE) : null]);
            }
            DB::connection('sqlsrv')->table('file_indexing_links')
                ->where('file_indexing_id', $row->id)
                ->whereRaw("REPLACE(UPPER(LTRIM(RTRIM(file_number))), ' ', '') = ?", [self::key($kangisNo)])
                ->delete();
        }
    }

    /**
     * Store the live KANGIS count on each land file's index rows.
     *
     * @param  string[]  $landFileNos
     */
    public function recount(array $landFileNos): void
    {
        $seen = [];
        foreach ($landFileNos as $landFileNo) {
            $key = self::key($landFileNo);
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $this->landRows($landFileNo)->update(['kangis_link_count' => count($this->kangisFilesFor($landFileNo))]);
        }
    }
}
