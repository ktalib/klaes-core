<?php

namespace App\Services\Edms;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Which part of KLAES a `scannings` row came from.
 *
 * Scan Upload and Page Typing list every scannings row, but only some were put there
 * by a scanning clerk. The rest were filed by other modules: the OSS / File
 * Commissioning passport capture, the parcel-update ingest, primary applications,
 * ST memo site plans and Cadastral. There is no source column, so this reads the
 * markers those writers already stamp on the row — a fixed `notes` sentence or a
 * fixed `document_type` — and is the ONE place that mapping lives.
 *
 * A writer that changes its notes wording must be updated here too, or its rows
 * quietly fall back to "Scanned".
 */
final class ScanSource
{
    public const SCANNED = 'scanned';
    public const OSS = 'oss';
    public const PARCEL = 'parcel';
    public const MODULE = 'module';

    /** Every module source together: anything not put there by a scanning clerk. */
    public const UPLOADS = 'uploads';

    /** The finer sources the CASE below yields. */
    public const LABELS = [
        self::SCANNED => 'Scanned Files',
        self::OSS => 'OSS / File Commissioning',
        self::PARCEL => 'Parcel Updates',
        self::MODULE => 'Other Modules',
    ];

    /** The two tabs Scan Upload and Page Typing show, in order; the first is the default. */
    public const TABS = [
        self::SCANNED => 'Scanned Files',
        self::UPLOADS => 'Module Uploads',
    ];

    public static function isValid(?string $source): bool
    {
        return $source !== null && (isset(self::LABELS[$source]) || $source === self::UPLOADS);
    }

    /**
     * The tab a source belongs on. Old links naming one module (oss, parcel, module)
     * land on Module Uploads; anything else, blank included, on Scanned Files.
     */
    public static function tab(?string $source): string
    {
        if ($source === self::UPLOADS || in_array($source, [self::OSS, self::PARCEL, self::MODULE], true)) {
            return self::UPLOADS;
        }

        return self::SCANNED;
    }

    /**
     * SQL CASE yielding the source key for a scannings row.
     *
     * Applicant passports (commissioning, the OSS FC edit page, the file-number edit
     * form and the duplex commissioning backfill) all belong to OSS / FC. Parcel
     * updates come from ParcelDocumentIngestService and MigrateParcelUploadsToEdms.
     */
    public static function caseSql(string $alias = 'scannings'): string
    {
        $a = $alias;

        return "CASE"
            . " WHEN {$a}.notes LIKE 'Applicant passport %' THEN '" . self::OSS . "'"
            . " WHEN {$a}.notes LIKE 'Parcel update upload (%'"
            . "   OR {$a}.notes LIKE 'Migrated from %(parcel update upload)%' THEN '" . self::PARCEL . "'"
            . " WHEN {$a}.notes LIKE 'Uploaded from primary application form%'"
            . "   OR {$a}.document_type = 'Recommended Site Plan Sketch'"
            . "   OR {$a}.document_type LIKE N'Cadastral %' THEN '" . self::MODULE . "'"
            . " ELSE '" . self::SCANNED . "' END";
    }

    /** Restrict a scannings query (or relation) to one source. */
    public static function apply($query, ?string $source, string $alias = 'scannings')
    {
        if ($source === self::UPLOADS) {
            $query->whereRaw('(' . self::caseSql($alias) . ') <> ?', [self::SCANNED]);
        } elseif (self::isValid($source)) {
            $query->whereRaw('(' . self::caseSql($alias) . ') = ?', [$source]);
        }

        return $query;
    }

    /** Restrict a file_indexings query to files holding at least one row of that source. */
    public static function applyToFiles($query, ?string $source)
    {
        if (self::isValid($source)) {
            $op = $source === self::UPLOADS ? '<>' : '=';
            $query->whereRaw(
                'EXISTS (SELECT 1 FROM scannings src WHERE src.file_indexing_id = file_indexings.id AND ('
                . self::caseSql('src') . ') ' . $op . ' ?)',
                [$source === self::UPLOADS ? self::SCANNED : $source]
            );
        }

        return $query;
    }

    /**
     * Number of files per source, for the tab badges. A file holding pages from two
     * sources is counted under both. Cached briefly: it scans the whole table.
     *
     * @return array<string,int> keyed by source, plus 'all'
     */
    public static function fileCounts(): array
    {
        return Cache::remember('edms.scan_source.file_counts.v2', 60, function () {
            $counts = array_fill_keys(array_keys(self::LABELS), 0) + [self::UPLOADS => 0];

            try {
                $conn = DB::connection('sqlsrv');
                $rows = $conn->select(
                    'SELECT src, COUNT(DISTINCT file_indexing_id) AS files FROM ('
                    . 'SELECT s.file_indexing_id, ' . self::caseSql('s') . ' AS src FROM scannings s'
                    // Same population as the lists: rows whose indexing record exists.
                    . ' INNER JOIN file_indexings fi ON fi.id = s.file_indexing_id'
                    . ') x GROUP BY src'
                );

                foreach ($rows as $row) {
                    $counts[$row->src] = (int) $row->files;
                }

                // Counted, not summed: a file with pages from two modules is one file.
                $counts[self::UPLOADS] = (int) ($conn->selectOne(
                    'SELECT COUNT(DISTINCT s.file_indexing_id) AS files FROM scannings s'
                    . ' INNER JOIN file_indexings fi ON fi.id = s.file_indexing_id'
                    . ' WHERE (' . self::caseSql('s') . ') <> ?',
                    [self::SCANNED]
                )->files ?? 0);

                $counts['all'] = (int) $conn->table('scannings as s')
                    ->join('file_indexings as fi', 'fi.id', '=', 's.file_indexing_id')
                    ->distinct()
                    ->count('s.file_indexing_id');
            } catch (\Throwable $e) {
                $counts['all'] = 0;
            }

            return $counts;
        });
    }
}
