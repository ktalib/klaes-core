<?php

namespace App\Services\Survey;

use App\Models\Survey\SurveyCoFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * co_grouping: the CO numbers' grouping table (user, 2026-11-09), shaped like lpkn_grouping and the other Survey
 * series. CO numbers are made when a CO file is commissioned or captured, so there is no pre-generated pool: each CO
 * file gets one row, batched by its YEAR (user, 2026-11-09: "for CO files we are using year instead of registry batch no"),
 * with a tracking ID like the other series'
 * (TRK-XXXXXXXX-00009). Print File Labels reads it; the File Indexing row gets the same tracking ID when it has none.
 */
class CoGrouping
{
    public const TABLE = 'co_grouping';
    /** The batch of a CO number with no year in it. */
    public const NO_YEAR = 'No year';

    public static function installed(): bool
    {
        static $ok = null;

        return $ok ??= Schema::connection('sqlsrv')->hasTable(self::TABLE);
    }

    private function db()
    {
        return DB::connection('sqlsrv');
    }

    /** CO-9-2018 (T) -> [9, 2018]. */
    public static function parts(string $number): array
    {
        return preg_match('/^CO-(\d+)-(\d{4})/i', trim($number), $m) ? [(int) $m[1], (int) $m[2]] : [null, null];
    }

    /** A CO file's batch: its year (CO-9-2018 -> "2018"). */
    public static function batchOf(?int $year): string
    {
        return $year ? (string) $year : self::NO_YEAR;
    }

    /** The CO file's row, made when missing and kept to its number. Returns the row's tracking ID. */
    public function ensure(SurveyCoFile $f, ?string $by = null): ?string
    {
        if (! self::installed() || trim((string) $f->co_file_no) === '') return null;
        $now = now();
        $row = $this->db()->table(self::TABLE)->where('survey_co_file_id', $f->id)->whereNull('deleted_at')->first();
        [$serial, $year] = self::parts($f->co_file_no);

        if ($row) {
            if ($row->co_awaiting_fileno !== $f->co_file_no
                || (int) ($row->number ?? 0) !== $serial
                || (int) ($row->year ?? 0) !== $year
                || (string) $row->registry_batch_no !== self::batchOf($year)) {
                $batch = self::batchOf($year);
                $this->db()->table(self::TABLE)->where('id', $row->id)->update([
                    'co_awaiting_fileno' => $f->co_file_no, 'co_fileno' => $f->co_file_no, 'number' => $serial, 'year' => $year,
                    'group' => $batch, 'sys_batch_no' => $batch, 'registry_batch_no' => $batch,
                    'updated_at' => $now, 'updated_by' => $by,
                ]);
            }
            $tracking = $row->tracking_id;
        } else {
            $batch = self::batchOf($year);
            $tracking = 'TRK-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)) . '-' . str_pad((string) ($serial ?? $f->id), 5, '0', STR_PAD_LEFT);
            $this->db()->table(self::TABLE)->insert([
                'co_awaiting_fileno' => $f->co_file_no, 'co_fileno' => $f->co_file_no, 'survey_co_file_id' => $f->id,
                'number' => $serial, 'year' => $year, 'registry' => 'Survey', 'group' => $batch,
                'sys_batch_no' => $batch, 'registry_batch_no' => $batch, 'tracking_id' => $tracking,
                'created_by' => $by ?? 'Generated', 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // The File Indexing row takes the tracking ID when it has none, as the other series' rows do.
        $this->db()->table('file_indexings')->where('file_number', $f->co_file_no)
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->where(fn ($q) => $q->whereNull('tracking_id')->orWhere('tracking_id', ''))
            ->update(['tracking_id' => $tracking]);

        return $tracking;
    }

    /** A deleted CO file: its row is marked deleted, never removed. */
    public function remove(SurveyCoFile $f, ?string $by = null): void
    {
        if (! self::installed()) return;
        $this->db()->table(self::TABLE)->where('survey_co_file_id', $f->id)->whereNull('deleted_at')
            ->update(['deleted_at' => now(), 'deleted_by' => $by, 'updated_at' => now()]);
    }
}
