<?php

namespace App\Services\Survey;

use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Loads Computation sheet.xlsx as it is written (user, 2026-11-06), into survey_computation_sheet_rows for the
 * Computation Sheet tab of Farm Assessment. Every row that has anything on it, with the workbook's own text. It writes
 * no project, land, farmer or allocation line. Each sheet commits on its own: klas has no snapshot isolation.
 */
class ComputationSheetImport
{
    private const CONN = 'sqlsrv';
    public const TABLE = 'survey_computation_sheet_rows';

    /** Rows per insert (sqlsrv allows 2100 parameters). */
    private const CHUNK = 80;

    private array $sheets = [];
    private string $file = '';

    /** Read every sheet. Nothing is written. */
    public function plan(string $path): array
    {
        $this->file = basename($path);
        $reader = IOFactory::createReader('Xlsx');
        $book = $reader->load($path);

        foreach ($book->getWorksheetIterator() as $ws) {
            $rows = ComputationSheetReader::written($ws->toArray(null, true, false, false), $ws->toArray(null, true, true, false));
            if ($rows) $this->sheets[$ws->getTitle()] = $rows;
        }

        return collect($this->sheets)->map(fn ($rows) => [
            'rows'   => count($rows),
            'lines'  => count(array_filter($rows, fn ($r) => $r['kind'] === 'line')),
            'totals' => count(array_filter($rows, fn ($r) => $r['kind'] === 'total')),
            'other'  => count(array_filter($rows, fn ($r) => $r['kind'] === 'other')),
            'long'   => count(array_filter($rows, fn ($r) => mb_strlen((string) $r['plots']) > 500 || mb_strlen((string) $r['remarks']) > 1000)),
        ])->all();
    }

    public function commit(string $ref, ?int $userId, ?callable $progress = null): int
    {
        $db = DB::connection(self::CONN);
        $now = now();
        $total = 0;

        foreach ($this->sheets as $sheet => $rows) {
            $db->transaction(function () use ($db, $sheet, $rows, $ref, $userId, $now, &$total) {
                foreach (array_chunk($rows, self::CHUNK) as $chunk) {
                    $db->table(self::TABLE)->insert(array_map(fn ($r) => [
                        'import_ref' => $ref, 'source_file' => mb_substr($this->file, 0, 255), 'sheet_name' => mb_substr($sheet, 0, 100), 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now,
                        'plots' => $r['plots'] === null ? null : mb_substr($r['plots'], 0, 500), 'remarks' => $r['remarks'] === null ? null : mb_substr($r['remarks'], 0, 1000),
                    ] + array_diff_key($r, ['plots' => 1, 'remarks' => 1]), $chunk));
                    $total += count($chunk);
                }
            });
            if ($progress) $progress($sheet, $total);
        }

        return $total;
    }

    public static function undo(string $ref): int
    {
        $n = 0;
        do {
            $ids = DB::connection(self::CONN)->table(self::TABLE)->where('import_ref', $ref)->limit(1000)->pluck('id')->all();
            if ($ids) $n += DB::connection(self::CONN)->table(self::TABLE)->whereIn('id', $ids)->delete();
        } while ($ids);

        return $n;
    }
}
