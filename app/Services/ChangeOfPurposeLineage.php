<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * The file a Change of Purpose renamed: the "grandmother" of a subdivision raised on
 * the CoP output (CON-COM-2003-118 → CoP → CON-RES-2026-2924 → 82 plots).
 *
 * Shared by the Land Recommendation picker and the Plot Subdivision listing, so both
 * name the same grandmother. Only Change of Purpose renames are followed (successor's
 * mls_file_no.source = 'Change of Purpose'), and only where exactly one retired file
 * names it as a successor.
 *
 * A split writes many successors and is not a rename. But one file can also go through
 * Change of Purpose more than once — CON-AG-2010-110 became CON-RES-2026-4611 (217
 * plots) and later CON-COM-2026-785 — and the retired row then lists both. A successor
 * list made up ONLY of Change of Purpose files is read as several renames, so every
 * one of them still rolls up to the same grandmother (user, 2026-11-02).
 */
class ChangeOfPurposeLineage
{
    /** Split a successor_file_no value (one number, or a CSV list) into numbers. */
    public static function successorList(?string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $value))));
    }

    /**
     * The files a retired file was renamed to, or [] when its successors are a split.
     * One successor is a rename as long as it is a Change of Purpose file; several are
     * renames only when every one of them is.
     *
     * @param  string[]  $successors
     * @return string[]
     */
    public function renamesAmong(array $successors): array
    {
        if (!$successors) {
            return [];
        }

        $cop = $this->changeOfPurposeBorn($successors);

        foreach ($successors as $s) {
            if (!isset($cop[mb_strtoupper($s)])) {
                return [];
            }
        }

        return $successors;
    }

    /**
     * Every Change of Purpose number $fileNumber became, walked forward hop by hop
     * (a CoP of a CoP is followed too). $fileNumber itself comes first.
     *
     * @return string[]
     */
    public function successors(string $fileNumber): array
    {
        $fileNumber = trim($fileNumber);
        $out  = [$fileNumber];
        $seen = [mb_strtoupper($fileNumber) => true];
        $frontier = [$fileNumber];

        for ($hop = 0; $hop < 5 && $frontier; $hop++) {
            $rows = DB::connection('sqlsrv')->table('file_indexings')
                ->whereIn('file_number', $frontier)
                ->where('is_decommissioned', 1)
                ->whereNotNull('successor_file_no')
                ->pluck('successor_file_no');

            $next = [];
            foreach ($rows as $value) {
                foreach ($this->renamesAmong(self::successorList($value)) as $s) {
                    if (!isset($seen[mb_strtoupper($s)])) {
                        $seen[mb_strtoupper($s)] = true;
                        $out[]  = $s;
                        $next[] = $s;
                    }
                }
            }
            $frontier = $next;
        }

        return $out;
    }

    /**
     * @param  string[]  $fileNumbers
     * @return array<string,true>  upper-cased numbers a live Change of Purpose produced
     */
    private function changeOfPurposeBorn(array $fileNumbers): array
    {
        $born = [];
        foreach (array_chunk(array_values(array_unique($fileNumbers)), 1000) as $slice) {
            foreach (DB::connection('sqlsrv')->table('mls_file_no')
                ->whereIn('full_file_number', $slice)
                ->where('source', 'Change of Purpose')
                ->where(function ($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->pluck('full_file_number') as $f) {
                $born[mb_strtoupper(trim((string) $f))] = true;
            }
        }

        return $born;
    }

    /**
     * @param  string[]  $fileNumbers
     * @return array<string,string>  upper-cased file => its original file (moved ones only)
     */
    public function origins(array $fileNumbers): array
    {
        $current = [];
        foreach ($fileNumbers as $fileNumber) {
            $fileNumber = trim((string) $fileNumber);
            if ($fileNumber !== '') {
                $current[mb_strtoupper($fileNumber)] = $fileNumber;
            }
        }

        $visited = [];
        foreach ($current as $origin => $fileNumber) {
            $visited[$origin] = [$origin => true];
        }

        for ($hop = 0; $hop < 5 && $current; $hop++) {
            $values = array_values(array_unique($current));

            // Of the files still being walked, the ones a Change of Purpose produced.
            $copBorn = $this->changeOfPurposeBorn($values);

            if (!$copBorn) {
                break;
            }

            // The retired file each one replaced.
            $predecessors = [];
            foreach (array_chunk(array_keys($copBorn), 1000) as $slice) {
                foreach (DB::connection('sqlsrv')->table('file_indexings')
                    ->whereIn('successor_file_no', $slice)
                    ->where('is_decommissioned', 1)
                    ->get(['file_number', 'successor_file_no']) as $row) {
                    $predecessors[mb_strtoupper(trim((string) $row->successor_file_no))][] = trim((string) $row->file_number);
                }
            }

            // A file renamed by Change of Purpose more than once lists every rename in
            // one CSV, which the exact match above cannot see. The CoP file names its
            // predecessor in related_fileno; that file is taken when its successor list
            // holds this file and is renames only (renamesAmong), never a split.
            $backLinks = [];
            foreach (array_chunk(array_keys($copBorn), 1000) as $slice) {
                foreach (DB::connection('sqlsrv')->table('file_indexings')
                    ->whereIn('file_number', $slice)
                    ->whereNotNull('related_fileno')
                    ->get(['file_number', 'related_fileno']) as $row) {
                    $related = json_decode((string) $row->related_fileno, true);
                    if (is_array($related) && count($related) === 1 && trim((string) $related[0]) !== '') {
                        $backLinks[mb_strtoupper(trim((string) $row->file_number))] = trim((string) $related[0]);
                    }
                }
            }
            if ($backLinks) {
                $retired = collect();
                foreach (array_chunk(array_values(array_unique($backLinks)), 1000) as $slice) {
                    $retired = $retired->merge(DB::connection('sqlsrv')->table('file_indexings')
                        ->whereIn('file_number', $slice)
                        ->where('is_decommissioned', 1)
                        ->where('successor_file_no', 'LIKE', '%,%')
                        ->get(['file_number', 'successor_file_no']));
                }
                foreach ($retired as $row) {
                    $renames = $this->renamesAmong(self::successorList($row->successor_file_no));
                    foreach ($renames as $s) {
                        if (isset($copBorn[mb_strtoupper($s)])
                            && mb_strtoupper($backLinks[mb_strtoupper($s)] ?? '') === mb_strtoupper(trim((string) $row->file_number))) {
                            $predecessors[mb_strtoupper($s)][] = trim((string) $row->file_number);
                        }
                    }
                }
            }

            $moved = false;
            foreach ($current as $origin => $fileNumber) {
                $key  = mb_strtoupper($fileNumber);
                $prev = array_values(array_unique($predecessors[$key] ?? []));

                if (!isset($copBorn[$key]) || count($prev) !== 1
                    || isset($visited[$origin][mb_strtoupper($prev[0])])) {
                    continue;
                }

                $current[$origin] = $prev[0];
                $visited[$origin][mb_strtoupper($prev[0])] = true;
                $moved = true;
            }

            if (!$moved) {
                break;
            }
        }

        return array_filter(
            $current,
            fn ($fileNumber, $origin) => mb_strtoupper($fileNumber) !== $origin,
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * Files a Change of Purpose made out of any file whose number contains $search:
     * lets a search for the grandmother find the subdivisions raised on its CoP output.
     *
     * @return string[]
     */
    public function successorsMatching(string $search, int $limit = 200): array
    {
        $search = trim($search);
        if ($search === '') {
            return [];
        }

        $successors = DB::connection('sqlsrv')->table('file_indexings')
            ->where('file_number', 'LIKE', "%{$search}%")
            ->where('is_decommissioned', 1)
            ->whereNotNull('successor_file_no')
            ->limit($limit)
            ->pluck('successor_file_no');

        $out = [];
        foreach ($successors as $value) {
            // A rename writes one number; a split writes a CSV list and is not followed.
            // A CSV of Change of Purpose files only is several renames (renamesAmong).
            $list = self::successorList($value);
            foreach (count($list) === 1 ? $list : $this->renamesAmong($list) as $s) {
                $out[mb_strtoupper($s)] = $s;
            }
        }
        if (!$out) {
            return [];
        }

        return DB::connection('sqlsrv')->table('mls_file_no')
            ->whereIn('full_file_number', array_values($out))
            ->where('source', 'Change of Purpose')
            ->pluck('full_file_number')->map(fn ($f) => trim((string) $f))->all();
    }
}
