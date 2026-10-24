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
 * names it as its sole successor; a split writes many successors and is not a rename.
 */
class ChangeOfPurposeLineage
{
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
            $copBorn = [];
            foreach (array_chunk($values, 1000) as $slice) {
                foreach (DB::connection('sqlsrv')->table('mls_file_no')
                    ->whereIn('full_file_number', $slice)
                    ->where('source', 'Change of Purpose')
                    ->where(function ($q) {
                        $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                    })
                    ->pluck('full_file_number') as $f) {
                    $copBorn[mb_strtoupper(trim((string) $f))] = true;
                }
            }

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
            $value = trim((string) $value);
            if ($value !== '' && !str_contains($value, ',')) {
                $out[mb_strtoupper($value)] = $value;
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
