<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * The new file numbers an SPU / APU application produced once commissioned, for the
 * "New FileNo(s)" column on the parcel-update listings.
 *
 * The applications do not store them. Commissioning writes each new file's
 * mls_file_no row with source = the workflow (Subdivision, Separation, Merger,
 * Extension File, Change of Purpose) and points its file_indexings.related_fileno at
 * the source file(s), so a new file belongs to an application when both match.
 * A Duplex records its own: duplex_parcel_update_files.final_file_no.
 *
 * One query per listing page, not per row.
 */
class ParcelUpdateNewFiles
{
    /** FILE NO (upper) => file title, for every new file this request has resolved. */
    private static array $titles = [];

    /** FILE NO (upper) => register details, see details(). */
    private static array $details = [];

    /**
     * What the opened rows under a listing row show for each new file: its title, plot,
     * location, commissioning date and who commissioned it, from mls_file_no (falling
     * back to the indexed record). One query per call; cached for the request.
     *
     * @param  string[]  $files
     * @return array<string, array{title:string, plot:string, district:string, lga:string, location:string, date:?string, by:string}>  keyed by upper-cased file no
     */
    public static function details(array $files): array
    {
        $missing = [];
        foreach ($files as $file) {
            $key = mb_strtoupper(trim((string) $file));
            if ($key !== '' && !array_key_exists($key, self::$details)) {
                $missing[$key] = trim((string) $file);
            }
        }

        foreach (array_chunk(array_values($missing), 500) as $chunk) {
            $rows = DB::connection('sqlsrv')->table('mls_file_no as m')
                ->leftJoin('file_indexings as fi', 'fi.file_number', '=', 'm.full_file_number')
                ->whereIn('m.full_file_number', $chunk)
                ->whereRaw('ISNULL(m.is_deleted, 0) = 0')
                ->get([
                    'm.full_file_number', 'm.file_name', 'm.plot_no', 'm.district', 'm.lga', 'm.location',
                    'm.commissioning_date', 'm.created_at', 'm.created_by',
                    'fi.file_title', 'fi.plot_number', 'fi.district as fi_district', 'fi.lga as fi_lga',
                ]);
            foreach ($rows as $r) {
                $pick = fn (...$v) => trim((string) (collect($v)->first(fn ($x) => trim((string) $x) !== '') ?? ''));
                self::$details[mb_strtoupper(trim((string) $r->full_file_number))] = [
                    'title' => $pick($r->file_title, $r->file_name),
                    'plot' => $pick($r->plot_no, $r->plot_number),
                    'district' => $pick($r->district, $r->fi_district),
                    'lga' => $pick($r->lga, $r->fi_lga),
                    'location' => $pick($r->location),
                    'date' => $r->commissioning_date ?: $r->created_at,
                    'by' => $pick($r->created_by),
                ];
            }
        }

        $out = [];
        foreach ($files as $file) {
            $key = mb_strtoupper(trim((string) $file));
            $out[$key] = self::$details[$key] ?? ['title' => self::$titles[$key] ?? '', 'plot' => '', 'district' => '', 'lga' => '', 'location' => '', 'date' => null, 'by' => ''];
        }

        return $out;
    }
    /** The title of a new file resolved by forApplications() / forDuplexes() earlier in this request. */
    public static function titleOf(string $fileNo): string
    {
        return self::$titles[mb_strtoupper(trim($fileNo))] ?? '';
    }

    /**
     * @param  iterable  $records      application rows (models or objects)
     * @param  string    $mlsSource    mls_file_no.source written by this workflow
     * @param  callable  $sourcesOf    fn ($record): string[]  the record's source file number(s)
     * @return array<int, string[]>    record id => new file numbers, in file-number order
     */
    public function forApplications(iterable $records, string $mlsSource, callable $sourcesOf): array
    {
        $bySource = [];   // SOURCE FILE (upper) => [record ids]
        $ids = [];
        foreach ($records as $record) {
            $ids[] = $record->id;
            foreach ((array) $sourcesOf($record) as $fileNo) {
                $fileNo = trim((string) $fileNo);
                if ($fileNo !== '') {
                    $bySource[mb_strtoupper($fileNo)][] = $record->id;
                }
            }
        }

        $out = array_fill_keys($ids, []);
        if (!$bySource) {
            return $out;
        }

        foreach (array_chunk(array_keys($bySource), 300) as $chunk) {
            $rows = DB::connection('sqlsrv')->table('file_indexings as fi')
                ->join('mls_file_no as m', 'm.full_file_number', '=', 'fi.file_number')
                ->where('m.source', $mlsSource)
                ->whereRaw('ISNULL(m.is_deleted, 0) = 0')
                ->where(function ($q) use ($chunk) {
                    foreach ($chunk as $fileNo) {
                        $q->orWhere('fi.related_fileno', 'LIKE', '%"' . $fileNo . '"%');
                    }
                })
                ->get(['fi.file_number', 'fi.related_fileno', 'fi.file_title']);

            foreach ($rows as $row) {
                self::$titles[mb_strtoupper(trim((string) $row->file_number))] = trim((string) $row->file_title);
                $related = json_decode((string) $row->related_fileno, true);
                foreach (is_array($related) ? $related : [] as $parent) {
                    foreach ($bySource[mb_strtoupper(trim((string) $parent))] ?? [] as $id) {
                        $out[$id][] = trim((string) $row->file_number);
                    }
                }
            }
        }

        foreach ($out as $id => $files) {
            $files = array_values(array_unique($files));
            natcasesort($files);
            $out[$id] = array_values($files);
        }

        return $out;
    }

    /** Duplex: duplex_parcel_update_files.final_file_no per duplex. @return array<int, string[]> */
    public function forDuplexes(iterable $records): array
    {
        $ids = [];
        foreach ($records as $record) {
            $ids[] = $record->id;
        }
        $out = array_fill_keys($ids, []);
        if (!$ids) {
            return $out;
        }

        DB::connection('sqlsrv')->table('duplex_parcel_update_files')
            ->whereIn('duplex_parcel_update_id', $ids)
            ->whereNotNull('final_file_no')
            ->orderBy('id')
            ->get(['duplex_parcel_update_id', 'final_file_no'])
            ->each(function ($r) use (&$out) {
                $fileNo = trim((string) $r->final_file_no);
                if ($fileNo !== '' && !in_array($fileNo, $out[$r->duplex_parcel_update_id] ?? [], true)) {
                    $out[$r->duplex_parcel_update_id][] = $fileNo;
                }
            });

        // A duplex runs its stages in order, so an earlier stage's result is consumed by
        // the next (Change of Purpose -> CON-RES-2026-4611, then Subdivision of 4611).
        // Those in-between numbers are decommissioned; only the final files are listed.
        $all = array_merge(...array_values($out ?: [[]]));
        $retired = [];
        foreach (array_chunk(array_values(array_unique($all)), 1000) as $chunk) {
            $query = DB::connection('sqlsrv')->table('decommissioned_files')->whereIn('file_no', $chunk);
            foreach (\App\Support\DecommissionScope::real($query)->pluck('file_no') as $fileNo) {
                $retired[mb_strtoupper(trim((string) $fileNo))] = true;
            }
        }
        if ($retired) {
            foreach ($out as $id => $files) {
                $out[$id] = array_values(array_filter($files, fn ($f) => !isset($retired[mb_strtoupper($f)])));
            }
        }

        // Titles from the indexed record, as for the SPU workflows.
        $final = array_values(array_unique(array_merge(...array_values($out ?: [[]]))));
        foreach (array_chunk($final, 1000) as $chunk) {
            DB::connection('sqlsrv')->table('file_indexings')->whereIn('file_number', $chunk)
                ->get(['file_number', 'file_title'])
                ->each(function ($r) {
                    self::$titles[mb_strtoupper(trim((string) $r->file_number))] = trim((string) $r->file_title);
                });
        }

        return $out;
    }
}
