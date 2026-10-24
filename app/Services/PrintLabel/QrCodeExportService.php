<?php

namespace App\Services\PrintLabel;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Exports the file numbers labelled on the Print File Label pages
 * (Land, KANGIS, SLTR, ST) within a date range, with their Shelf/Rack,
 * as CSV or PDF.
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

    public static function moduleConfig(string $module): array
    {
        if (!isset(self::MODULES[$module])) {
            throw new RuntimeException("Unknown print label module [{$module}].");
        }

        return self::MODULES[$module];
    }

    /** One row per file number (its latest label) generated between date_from and date_to. */
    public function query(string $module, ?string $dateFrom, ?string $dateTo)
    {
        $cfg = self::moduleConfig($module);

        $inner = DB::connection('sqlsrv')
            ->table("{$cfg['items']} as i")
            ->join("{$cfg['batches']} as b", 'b.id', '=', 'i.batch_id')
            ->whereNotNull('i.file_number')
            ->where('i.file_number', '<>', '')
            ->select([
                'i.id', 'i.file_number', 'i.shelf_location',
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

        return DB::connection('sqlsrv')->query()->fromSub($inner, 'q')->where('q.file_rank', 1);
    }

    public function count(string $module, ?string $dateFrom, ?string $dateTo): int
    {
        return (int) $this->query($module, $dateFrom, $dateTo)->count();
    }

    public function rows(string $module, ?string $dateFrom, ?string $dateTo, ?int $limit = null)
    {
        $query = $this->query($module, $dateFrom, $dateTo)->orderBy('q.file_number')->orderBy('q.id');

        return $limit ? $query->limit($limit)->cursor() : $query->cursor();
    }

    public function writeCsv(string $module, ?string $dateFrom, ?string $dateTo, $handle): void
    {
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['S/N', 'File Number', 'Shelf/Rack']);

        $serial = 0;
        foreach ($this->rows($module, $dateFrom, $dateTo) as $row) {
            fputcsv($handle, [++$serial, $row->file_number, $row->shelf_location]);
        }
    }

    public function pdfRows(string $module, ?string $dateFrom, ?string $dateTo): array
    {
        $out = [];
        foreach ($this->rows($module, $dateFrom, $dateTo, self::PDF_LIMIT) as $row) {
            $out[] = ['file_number' => $row->file_number, 'shelf' => $row->shelf_location];
        }

        return $out;
    }
}
