<?php

namespace App\Services\FileIndexing;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * "Related File (Physical File is Sighted)" on File Indexing (user, 2026-10-28).
 *
 * Each related file on an indexing record is answered Yes or No. No means NPF (No
 * Physical File): the related file is not physically with this one, so Quick Search
 * (web and mobile) does not treat the pair as "kept inside" each other, and the KANGIS
 * land-file lock does not hold it. NPF is derived, never set on its own. Cover
 * missing with the contents sighted is still Yes, with an optional note.
 *
 * No has two categories, worked out live from whether the related file has its own
 * indexing record (user, 2026-10-29, recording 289): "Physical File Not in the File"
 * (never indexed — the true NPF) and "Physical File Indexed" (not in this folder, but
 * indexed on its own). Either way Quick Search shows the related file where it really
 * is and never refers the requester to the KANGIS file; the category is only a label.
 *
 * A related file with no row is unanswered (indexed before this existed, or added by
 * code rather than the form) and keeps the old behaviour. The form is required to
 * answer every related file it submits.
 */
class RelatedFileSightings
{
    /** Hidden form field carrying the answers as JSON. */
    public const REQUEST_FIELD = 'related_file_sightings';

    /** Categories of a related file's answer (category()). */
    public const SIGHTED = 'sighted';
    public const NOT_IN_FILE = 'not_in_file';      // No, never indexed: NPF
    public const INDEXED_ELSEWHERE = 'indexed';    // No, but indexed on its own

    public const CATEGORY_LABELS = [
        self::SIGHTED => 'Physical File Sighted',
        self::NOT_IN_FILE => 'Physical File Not in the File',
        self::INDEXED_ELSEWHERE => 'Physical File Indexed',
    ];

    public static function category(?bool $sighted, bool $relatedIndexed): ?string
    {
        if ($sighted === null) {
            return null;
        }
        if ($sighted) {
            return self::SIGHTED;
        }

        return $relatedIndexed ? self::INDEXED_ELSEWHERE : self::NOT_IN_FILE;
    }

    public const TABLE = 'file_indexing_related_sightings';
    public const LOG_TABLE = 'file_indexing_related_sighting_log';

    public const COVER_MISSING_TEXT = 'Cover missing – contents sighted';

    public const REQUIRED_MESSAGE = 'You must indicate whether the Related File physical file has been sighted.';

    private static ?bool $tablesExist = null;

    public static function tablesExist(): bool
    {
        if (self::$tablesExist === null) {
            try {
                self::$tablesExist = Schema::connection('sqlsrv')->hasTable(self::TABLE);
            } catch (\Throwable $e) {
                self::$tablesExist = false;
            }
        }

        return self::$tablesExist;
    }

    /**
     * Answers submitted by the form, keyed by KangisBlockIndexing::key().
     *
     * @return array<string, array{file_number:string, sighted:?bool, cover_missing:?string}>
     */
    public function answersFrom(Request $request): array
    {
        $raw = $request->input(self::REQUEST_FIELD);
        $items = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($items)) {
            return [];
        }

        $out = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $fileNo = trim((string) ($item['file_number'] ?? ''));
            $key = KangisBlockIndexing::key($fileNo);
            if ($key === '') {
                continue;
            }
            $sighted = $item['sighted'] ?? null;
            $sighted = ($sighted === null || $sighted === '') ? null : filter_var($sighted, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $cover = trim((string) ($item['cover_missing'] ?? ''));

            $out[$key] = [
                'file_number' => $fileNo,
                'sighted' => $sighted,
                // Only a sighted file can be "cover missing"; NPF has nothing to note.
                'cover_missing' => ($sighted === true && $cover !== '') ? mb_substr($cover, 0, 500) : null,
            ];
        }

        return $out;
    }

    /**
     * Which of these file numbers have a live indexing record of their own, keyed by
     * KangisBlockIndexing::key(). Splits a No answer into "Physical File Indexed" (the
     * related file has its own record) and "Physical File Not in the File" (NPF), read
     * live so a file indexed later moves category by itself.
     *
     * @param  string[]  $fileNumbers
     * @return array<string, true>
     */
    public function indexedKeys(array $fileNumbers): array
    {
        $numbers = [];
        foreach ($fileNumbers as $no) {
            $no = trim((string) $no);
            if ($no !== '') {
                $numbers[KangisBlockIndexing::key($no)] = $no;
            }
        }
        if (!$numbers) {
            return [];
        }

        $found = [];
        foreach (array_chunk(array_values($numbers), 500) as $chunk) {
            $rows = DB::connection('sqlsrv')->table('file_indexings')
                ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
                ->where(fn ($q) => $q->whereIn('file_number', $chunk)
                    ->orWhereIn('kangis_file_no', $chunk)
                    ->orWhereIn('new_kangis_file_no', $chunk))
                ->get(['file_number', 'kangis_file_no', 'new_kangis_file_no']);
            foreach ($rows as $row) {
                foreach ([$row->file_number, $row->kangis_file_no, $row->new_kangis_file_no] as $n) {
                    $k = KangisBlockIndexing::key($n);
                    if ($k !== '' && isset($numbers[$k])) {
                        $found[$k] = true;
                    }
                }
            }
        }

        return $found;
    }

    /**
     * The related file's title for the form: its own indexing record first, then the
     * file-number registers (fileNumber across its number columns, mls_file_no), so a
     * file that was never indexed can still show a name. Null when none holds one.
     */
    public function titleFor(string $fileNumber): ?string
    {
        $fileNumber = trim($fileNumber);
        if ($fileNumber === '') {
            return null;
        }
        $conn = DB::connection('sqlsrv');
        // Placeholders ("-", "N/A", "Group Title") are not a title.
        $clean = fn ($t) => (($t = trim((string) $t)) !== '' && !in_array(strtoupper($t), ['-', '--', 'N/A', 'NA', 'NONE', 'NIL', 'GROUP TITLE'], true)) ? $t : null;

        $title = $conn->table('file_indexings')
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->where(fn ($q) => $q->where('file_number', $fileNumber)
                ->orWhere('kangis_file_no', $fileNumber)
                ->orWhere('new_kangis_file_no', $fileNumber))
            ->orderByDesc('id')
            ->value('file_title');
        if ($clean($title)) {
            return $clean($title);
        }

        $title = $conn->table('fileNumber')
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->where(fn ($q) => $q->where('mlsfNo', $fileNumber)
                ->orWhere('kangisFileNo', $fileNumber)
                ->orWhere('NewKANGISFileNo', $fileNumber)
                ->orWhere('st_file_no', $fileNumber))
            ->whereNotNull('FileName')
            ->orderByDesc('id')
            ->value('FileName');
        if ($clean($title)) {
            return $clean($title);
        }

        $title = $conn->table('mls_file_no')
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->where(fn ($q) => $q->where('full_file_number', $fileNumber)->orWhere('kangis_file_no', $fileNumber))
            ->whereNotNull('file_name')
            ->orderByDesc('id')
            ->value('file_name');
        if ($clean($title)) {
            return $clean($title);
        }

        // Titles officers typed for it under "Enter Related Details", then DCIV links.
        $title = $conn->table('file_indexing_links')
            ->whereNull('deleted_at')
            ->where('file_number', $fileNumber)
            ->whereNotNull('file_title')
            ->where('file_title', '<>', '')
            ->orderByDesc('id')
            ->value('file_title');
        if ($clean($title)) {
            return $clean($title);
        }

        $title = $conn->table('master_dciv_links')
            ->where('related_file_number', $fileNumber)
            ->whereNotNull('related_file_title')
            ->orderByDesc('id')
            ->value('related_file_title');

        return $clean($title);
    }

    /** True when the submitting form carries the Physical File Sighted answers. */
    public function submitted(Request $request): bool
    {
        return $request->has(self::REQUEST_FIELD);
    }

    /**
     * The refusal when a related file picked on the form was left unanswered, else null.
     * Only forms that carry the field are checked; other callers of store/update are not.
     */
    public function refusal(Request $request): ?string
    {
        if (!$this->submitted($request)) {
            return null;
        }

        $answers = $this->answersFrom($request);
        $missing = [];
        foreach (KangisBlockIndexing::landFilesFrom($request->input('related_fileno')) as $fileNo) {
            $key = KangisBlockIndexing::key($fileNo);
            if (!isset($answers[$key]) || $answers[$key]['sighted'] === null) {
                $missing[] = $fileNo;
            }
        }

        if (!$missing) {
            return null;
        }

        return self::REQUIRED_MESSAGE . ' (' . implode(', ', $missing) . ')';
    }

    /**
     * Save the answers for one indexing record and log each change.
     *
     * $relatedFileNos is the record's related list as saved. Answers are kept for those
     * numbers only; a stored answer whose related file is no longer on the record is
     * removed (and logged). Related numbers with no submitted answer are left as they are.
     *
     * @param  string[]  $relatedFileNos
     */
    public function sync(int $fileIndexingId, ?string $fileNumber, array $relatedFileNos, array $answers, $user = null): void
    {
        if (!self::tablesExist() || $fileIndexingId <= 0) {
            return;
        }

        $conn = DB::connection('sqlsrv');
        $now = now();
        $userId = $user->id ?? null;
        $userName = $user ? (trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: ($user->name ?? null)) : null;

        $current = $conn->table(self::TABLE)
            ->where('file_indexing_id', $fileIndexingId)
            ->get()
            ->keyBy('related_key');

        $onRecord = [];
        foreach (KangisBlockIndexing::landFilesFrom($relatedFileNos) as $fileNo) {
            $onRecord[KangisBlockIndexing::key($fileNo)] = $fileNo;
        }

        $log = [];
        $logRow = fn (string $relatedNo, string $action, $old, ?array $new) => [
            'file_indexing_id' => $fileIndexingId,
            'file_number' => $fileNumber,
            'related_file_number' => $relatedNo,
            'action' => $action,
            'old_physical_sighted' => $old ? (int) $old->physical_sighted : null,
            'new_physical_sighted' => $new ? (int) $new['sighted'] : null,
            'old_npf' => $old ? (int) $old->npf : null,
            'new_npf' => $new ? (int) !$new['sighted'] : null,
            'old_cover_missing' => $old->cover_missing ?? null,
            'new_cover_missing' => $new['cover_missing'] ?? null,
            'changed_by' => $userId,
            'changed_by_name' => $userName,
            'created_at' => $now,
        ];

        foreach ($onRecord as $key => $fileNo) {
            $answer = $answers[$key] ?? null;
            if (!$answer || $answer['sighted'] === null) {
                continue;
            }
            $old = $current->get($key);
            $values = [
                'file_number' => $fileNumber,
                'related_file_number' => $fileNo,
                'physical_sighted' => (int) $answer['sighted'],
                'npf' => (int) !$answer['sighted'],
                'cover_missing' => $answer['cover_missing'],
                'updated_by' => $userId,
                'updated_at' => $now,
            ];

            if (!$old) {
                $conn->table(self::TABLE)->insert($values + [
                    'file_indexing_id' => $fileIndexingId,
                    'related_key' => $key,
                    'created_at' => $now,
                ]);
                $log[] = $logRow($fileNo, 'set', null, $answer);
                continue;
            }

            $changed = (bool) $old->physical_sighted !== $answer['sighted']
                || (string) ($old->cover_missing ?? '') !== (string) ($answer['cover_missing'] ?? '');
            if ($changed) {
                $conn->table(self::TABLE)->where('id', $old->id)->update($values);
                $log[] = $logRow($fileNo, 'changed', $old, $answer);
            } elseif ((string) $old->file_number !== (string) $fileNumber) {
                $conn->table(self::TABLE)->where('id', $old->id)->update(['file_number' => $fileNumber]);
            }
        }

        foreach ($current as $key => $old) {
            if (!isset($onRecord[$key])) {
                $conn->table(self::TABLE)->where('id', $old->id)->delete();
                $log[] = $logRow((string) $old->related_file_number, 'removed', $old, null);
            }
        }

        if ($log) {
            try {
                $conn->table(self::LOG_TABLE)->insert($log);
            } catch (\Throwable $e) {
                Log::warning('Related file sighting log not written', ['file_indexing_id' => $fileIndexingId, 'error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Stored answers for a record, for the form to open with.
     *
     * @return array<int, array{file_number:string, sighted:bool, cover_missing:?string}>
     */
    public function forRecord(?int $fileIndexingId): array
    {
        if (!$fileIndexingId || !self::tablesExist()) {
            return [];
        }

        return DB::connection('sqlsrv')->table(self::TABLE)
            ->where('file_indexing_id', $fileIndexingId)
            ->orderBy('id')
            ->get(['related_file_number', 'physical_sighted', 'cover_missing'])
            ->map(fn ($r) => [
                'file_number' => (string) $r->related_file_number,
                'sighted' => (bool) $r->physical_sighted,
                'cover_missing' => $r->cover_missing,
            ])
            ->all();
    }

    /**
     * Pairs answered No ("not in the file") among these file numbers, keyed by pairKey(),
     * each with its category: NOT_IN_FILE (the related file was never indexed: NPF) or
     * INDEXED_ELSEWHERE (indexed on its own). A pair is the two files, whichever record
     * stores the link (a KANGIS row lists its land file and the land row lists the
     * KANGIS file back), so the answer on either side counts. When both sides were
     * answered, the latest answer wins.
     *
     * @param  string[]  $fileNumbers
     * @return array<string, string>
     */
    public function npfPairs(array $fileNumbers): array
    {
        $keys = [];
        foreach ($fileNumbers as $no) {
            $k = KangisBlockIndexing::key($no);
            if ($k !== '') {
                $keys[$k] = true;
            }
        }
        if (count($keys) < 2 || !self::tablesExist()) {
            return [];
        }

        $latest = [];
        foreach (array_chunk(array_keys($keys), 1000) as $chunk) {
            $rows = DB::connection('sqlsrv')->table(self::TABLE)
                ->whereIn('related_key', $chunk)
                ->get(['id', 'file_number', 'related_file_number', 'related_key', 'npf', 'updated_at']);
            foreach ($rows as $row) {
                $own = KangisBlockIndexing::key($row->file_number);
                if (!isset($keys[$own])) {
                    continue;
                }
                $pair = self::pairKey($row->file_number, $row->related_key);
                $stamp = [(string) $row->updated_at, (int) $row->id];
                if (!isset($latest[$pair]) || $stamp > $latest[$pair]['stamp']) {
                    $latest[$pair] = ['stamp' => $stamp, 'npf' => (bool) $row->npf, 'related_key' => $row->related_key, 'related_file_number' => $row->related_file_number];
                }
            }
        }

        $notInFile = array_filter($latest, fn ($answer) => $answer['npf']);
        if (!$notInFile) {
            return [];
        }

        // Both No categories keep the pair apart; the category only labels it.
        $indexed = $this->indexedKeys(array_column($notInFile, 'related_file_number'));

        $pairs = [];
        foreach ($notInFile as $pair => $answer) {
            $pairs[$pair] = isset($indexed[$answer['related_key']]) ? self::INDEXED_ELSEWHERE : self::NOT_IN_FILE;
        }

        return $pairs;
    }

    /** Order-free key for the pair of two file numbers. */
    public static function pairKey(?string $fileNo, ?string $otherFileNo): string
    {
        $pair = [KangisBlockIndexing::key($fileNo), KangisBlockIndexing::key($otherFileNo)];
        sort($pair, SORT_STRING);

        return implode('|', $pair);
    }
}
