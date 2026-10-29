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
 * A related file with no row is unanswered (indexed before this existed, or added by
 * code rather than the form) and keeps the old behaviour. The form is required to
 * answer every related file it submits.
 */
class RelatedFileSightings
{
    /** Hidden form field carrying the answers as JSON. */
    public const REQUEST_FIELD = 'related_file_sightings';

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
     * KangisBlockIndexing::key(). A file that has been indexed was physically handled,
     * so it is never NPF (user, 2026-10-29), whether it was indexed before or after
     * the answer.
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
     * Indexing a file clears every NPF answer that names it as the related file: it is
     * sighted now. Each cleared answer is logged ("cleared_indexed"). Returns the count.
     *
     * @param  string[]  $fileNumbers  the newly indexed record's numbers
     */
    public function clearNpfFor(array $fileNumbers, $user = null): int
    {
        if (!self::tablesExist()) {
            return 0;
        }
        $keys = array_values(array_unique(array_filter(array_map([KangisBlockIndexing::class, 'key'], $fileNumbers))));
        if (!$keys) {
            return 0;
        }

        $conn = DB::connection('sqlsrv');
        $rows = $conn->table(self::TABLE)->whereIn('related_key', $keys)->where('npf', 1)->get();
        if ($rows->isEmpty()) {
            return 0;
        }

        $now = now();
        $userId = $user->id ?? null;
        $userName = $user ? (trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: ($user->name ?? null)) : null;

        $conn->table(self::TABLE)->whereIn('id', $rows->pluck('id')->all())->update([
            'physical_sighted' => 1,
            'npf' => 0,
            'updated_by' => $userId,
            'updated_at' => $now,
        ]);

        try {
            $conn->table(self::LOG_TABLE)->insert($rows->map(fn ($old) => [
                'file_indexing_id' => $old->file_indexing_id,
                'file_number' => $old->file_number,
                'related_file_number' => $old->related_file_number,
                'action' => 'cleared_indexed',
                'old_physical_sighted' => (int) $old->physical_sighted,
                'new_physical_sighted' => 1,
                'old_npf' => (int) $old->npf,
                'new_npf' => 0,
                'old_cover_missing' => $old->cover_missing,
                'new_cover_missing' => $old->cover_missing,
                'changed_by' => $userId,
                'changed_by_name' => $userName,
                'created_at' => $now,
            ])->all());
        } catch (\Throwable $e) {
            Log::warning('Related file sighting clear not logged', ['keys' => $keys, 'error' => $e->getMessage()]);
        }

        return $rows->count();
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

        // An indexed related file is sighted by definition; nothing to answer.
        if ($missing) {
            $indexed = $this->indexedKeys($missing);
            $missing = array_values(array_filter($missing, fn ($no) => !isset($indexed[KangisBlockIndexing::key($no)])));
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

        // A related file with its own indexing record is sighted, whatever was picked.
        $indexed = $this->indexedKeys(array_values($onRecord));

        foreach ($onRecord as $key => $fileNo) {
            $answer = $answers[$key] ?? null;
            if (isset($indexed[$key])) {
                $answer = ['file_number' => $fileNo, 'sighted' => true, 'cover_missing' => $answer['cover_missing'] ?? null];
            }
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
     * NPF pairs among these file numbers, keyed by pairKey(). A pair is the two files,
     * whichever record stores the link (a KANGIS row lists its land file and the land
     * row lists the KANGIS file back), so the answer on either side counts. When both
     * sides were answered, the latest answer wins.
     *
     * @param  string[]  $fileNumbers
     * @return array<string, true>
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

        $npf = array_filter($latest, fn ($answer) => $answer['npf']);
        if (!$npf) {
            return [];
        }

        // An NPF answer no longer holds once that related file has been indexed (user,
        // 2026-10-29): someone handled its physical file. Read here as well as cleared on
        // indexing, so a file indexed before the answer is covered too.
        $indexed = $this->indexedKeys(array_column($npf, 'related_file_number'));

        $pairs = [];
        foreach ($npf as $pair => $answer) {
            if (!isset($indexed[$answer['related_key']])) {
                $pairs[$pair] = true;
            }
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
