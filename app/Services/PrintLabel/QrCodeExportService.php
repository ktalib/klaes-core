<?php

namespace App\Services\PrintLabel;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Exports the file numbers labelled on the Print File Label pages
 * (Land, KANGIS, SLTR, ST) within a date range, with their File Prefix and
 * Shelf/Rack, as CSV or PDF. Optionally limited to one prefix, and always in
 * prefix order then numerically 1 to N within the prefix (RES-2009-2 before
 * RES-2009-10), not the plain text order the database would give.
 */
class QrCodeExportService
{
    /** Most rows one PDF may hold; larger ranges must be narrowed or exported as CSV. */
    public const PDF_LIMIT = 5000;

    public const MODULES = [
        'land'   => ['label' => 'Land',   'batches' => 'print_label_batches',        'items' => 'print_label_batch_items',        'soft_deletes_batches' => false],
        'kangis' => ['label' => 'KANGIS', 'batches' => 'kangis_print_label_batches', 'items' => 'kangis_print_label_batch_items', 'soft_deletes_batches' => false],
        'sltr'   => ['label' => 'SLTR',   'batches' => 'sltr_print_label_batches',   'items' => 'sltr_print_label_batch_items',   'soft_deletes_batches' => true],
        'st'     => ['label' => 'ST',     'batches' => 'st_print_label_batches',     'items' => 'st_print_label_batch_items',     'soft_deletes_batches' => true],
    ];

    /** Everything before the first digit of the (left-trimmed) file number. */
    private const LEAD_SQL = "RTRIM(LEFT(LTRIM(i.file_number), PATINDEX('%[0-9]%', LTRIM(i.file_number) + '0') - 1))";

    public static function moduleConfig(string $module): array
    {
        if (!isset(self::MODULES[$module])) {
            throw new RuntimeException("Unknown print label module [{$module}].");
        }

        return self::MODULES[$module];
    }

    /**
     * The file prefix: everything before the first digit, less a trailing "-" —
     * CON-RES-1981-1 -> CON-RES, MLKN 115 -> MLKN, KN1044 -> KN,
     * ST-MIXED-2025-1-001 -> ST-MIXED. Worked out in SQL so the filter, the
     * per-prefix counts and the exported column can never disagree.
     */
    private static function prefixSql(): string
    {
        $lead = self::LEAD_SQL;

        return "UPPER(CASE WHEN RIGHT({$lead}, 1) = '-' THEN LEFT({$lead}, LEN({$lead}) - 1) ELSE {$lead} END)";
    }

    /** One row per file number (its latest label) generated between date_from and date_to. */
    public function query(string $module, ?string $dateFrom, ?string $dateTo, ?string $prefix = null)
    {
        $cfg = self::moduleConfig($module);

        $inner = DB::connection('sqlsrv')
            ->table("{$cfg['items']} as i")
            ->join("{$cfg['batches']} as b", 'b.id', '=', 'i.batch_id')
            ->whereNotNull('i.file_number')
            ->where('i.file_number', '<>', '')
            ->select([
                'i.id', 'i.file_number', 'i.shelf_location',
                DB::raw(self::prefixSql() . ' as file_prefix'),
                DB::raw('ROW_NUMBER() OVER (PARTITION BY UPPER(LTRIM(RTRIM(i.file_number))) ORDER BY i.id DESC) as file_rank'),
            ]);

        if ($cfg['soft_deletes_batches']) {
            $inner->whereNull('b.deleted_at');
        }
        if ($dateFrom) {
            $inner->where('i.created_at', '>=', $dateFrom . ' 00:00:00');
        }
        if ($dateTo) {
            $inner->where('i.created_at', '<=', $dateTo . ' 23:59:59.997');
        }

        $outer = DB::connection('sqlsrv')->query()->fromSub($inner, 'q')->where('q.file_rank', 1);

        $prefix = strtoupper(trim((string) $prefix));
        if ($prefix !== '') {
            $outer->where('q.file_prefix', $prefix);
        }

        return $outer;
    }

    public function count(string $module, ?string $dateFrom, ?string $dateTo, ?string $prefix = null): int
    {
        return (int) $this->query($module, $dateFrom, $dateTo, $prefix)->count();
    }

    /**
     * File prefixes in the range with how many file numbers each holds, A to Z.
     *
     * @return array<int,array{prefix:string,count:int}>
     */
    public function prefixes(string $module, ?string $dateFrom, ?string $dateTo): array
    {
        return $this->query($module, $dateFrom, $dateTo)
            ->select('q.file_prefix', DB::raw('COUNT(*) as total'))
            ->groupBy('q.file_prefix')
            ->orderBy('q.file_prefix')
            ->get()
            ->map(fn ($r) => ['prefix' => (string) $r->file_prefix, 'count' => (int) $r->total])
            ->all();
    }

    /**
     * Rows in prefix order, then 1 to N within the prefix.
     *
     * Sorted in PHP: SQL Server has no natural sort, and the numeric parts vary
     * in count (MLKN 115, RES-2009-6381, ST-RES-2025-2-001, MLKN 1338_1_2). A
     * range is at most a few hundred thousand short strings.
     *
     * @return array<int,array{prefix:string,file_number:string,shelf:?string,id:int}>
     */
    public function rows(string $module, ?string $dateFrom, ?string $dateTo, ?string $prefix = null, ?int $limit = null): array
    {
        $rows = [];
        foreach ($this->query($module, $dateFrom, $dateTo, $prefix)->cursor() as $row) {
            $rows[] = [
                'prefix' => (string) $row->file_prefix,
                'file_number' => trim((string) $row->file_number),
                'shelf' => $row->shelf_location,
                'id' => (int) $row->id,
            ];
        }

        usort($rows, function (array $a, array $b) {
            return strcmp($a['prefix'], $b['prefix'])
                ?: strnatcasecmp(self::serialPart($a['file_number']), self::serialPart($b['file_number']))
                ?: strcasecmp($a['file_number'], $b['file_number'])
                ?: $a['id'] <=> $b['id'];
        });

        return $limit ? array_slice($rows, 0, $limit) : $rows;
    }

    /** The file number from its first digit on: "2009-6381" for RES-2009-6381. */
    private static function serialPart(string $fileNumber): string
    {
        $at = strpbrk($fileNumber, '0123456789');

        return $at === false ? $fileNumber : $at;
    }

    public function writeCsv(string $module, ?string $dateFrom, ?string $dateTo, $handle, ?string $prefix = null): void
    {
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['S/N', 'File Prefix', 'File Number', 'Shelf/Rack']);

        $serial = 0;
        foreach ($this->rows($module, $dateFrom, $dateTo, $prefix) as $row) {
            fputcsv($handle, [++$serial, $row['prefix'], $row['file_number'], $row['shelf']]);
        }
    }

    public function pdfRows(string $module, ?string $dateFrom, ?string $dateTo, ?string $prefix = null): array
    {
        return $this->rows($module, $dateFrom, $dateTo, $prefix, self::PDF_LIMIT);
    }
}
