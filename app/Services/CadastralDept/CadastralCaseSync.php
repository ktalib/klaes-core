<?php

namespace App\Services\CadastralDept;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A cadastral case keeps its own copy of the file's details from intake. An edit of the
 * file title, Plot No., District or LGA in File Indexing, Land FC or OSS FC is carried
 * to the file's cases, and the case Location is rebuilt as "District, LGA" (user, 2026-11-19).
 *
 * Only values that were given are written: a blank never clears the case.
 */
class CadastralCaseSync
{
    private const WIDTH = ['file_title' => 255, 'plot_no' => 50, 'district' => 150, 'lga' => 100, 'location' => 255];

    /**
     * @param  array<int, string|null>|string|null  $fileNumbers  the file's number(s), any column
     * @param  array{file_title?:mixed, plot_no?:mixed, district?:mixed, lga?:mixed}  $values
     * @return int cases updated
     */
    public function sync($fileNumbers, array $values, ?int $fileIndexingId = null): int
    {
        try {
            $numbers = array_values(array_unique(array_filter(array_map(
                fn ($n) => trim((string) $n), (array) $fileNumbers
            ), fn ($n) => $n !== '')));
            if (! $numbers && ! $fileIndexingId) {
                return 0;
            }

            $set = [];
            foreach (['file_title', 'plot_no', 'district', 'lga'] as $key) {
                $v = $this->clean($values[$key] ?? null);
                if ($v !== null) {
                    $set[$key] = mb_substr($v, 0, self::WIDTH[$key]);
                }
            }
            if (! $set) {
                return 0;
            }

            $db = DB::connection('sqlsrv');
            if (! Schema::connection('sqlsrv')->hasTable('cadastral_cases')) {
                return 0;
            }

            $cases = $db->table('cadastral_cases')
                ->where(function ($q) use ($numbers, $fileIndexingId) {
                    if ($numbers) {
                        $q->whereIn('file_number', $numbers);
                    }
                    if ($fileIndexingId) {
                        $q->orWhere('file_indexing_id', $fileIndexingId);
                    }
                })
                ->get(['id', 'file_title', 'plot_no', 'district', 'lga', 'location']);

            $updated = 0;
            foreach ($cases as $case) {
                $row = $set;
                if (isset($set['district']) || isset($set['lga'])) {
                    $location = self::location($set['district'] ?? $case->district, $set['lga'] ?? $case->lga);
                    if ($location !== '') {
                        $row['location'] = mb_substr($location, 0, self::WIDTH['location']);
                    }
                }
                $changed = array_filter($row, fn ($v, $k) => trim((string) $case->{$k}) !== $v, ARRAY_FILTER_USE_BOTH);
                if ($changed) {
                    $db->table('cadastral_cases')->where('id', $case->id)->update($changed + ['updated_at' => now()]);
                    $updated++;
                }
                // The case's File Movement Card holds the same details; it follows too (user, 2026-11-19).
                $this->syncCard($db, (int) $case->id, $row);
            }

            return $updated;
        } catch (\Throwable $e) {
            // The edit itself has saved; a failed case copy must not undo it.
            \Log::warning('Cadastral case sync failed', ['files' => $fileNumbers, 'error' => $e->getMessage()]);

            return 0;
        }
    }

    /** Copy the given details onto the case's File Movement Card, where one exists. */
    private function syncCard($db, int $caseId, array $row): void
    {
        if (! Schema::connection('sqlsrv')->hasTable('cadastral_fmcs')) {
            return;
        }
        $card = $db->table('cadastral_fmcs')->where('cadastral_case_id', $caseId)->first(['id', 'file_title', 'plot_no', 'district', 'lga', 'location']);
        if (! $card) {
            return;
        }
        $changed = array_filter($row, fn ($v, $k) => trim((string) $card->{$k}) !== $v, ARRAY_FILTER_USE_BOTH);
        if ($changed) {
            $db->table('cadastral_fmcs')->where('id', $card->id)->update($changed + ['updated_at' => now()]);
        }
    }

    /** "District, LGA"; one of them when the other is blank or the same name. */
    public static function location($district, $lga): string
    {
        $district = trim((string) $district);
        $lga = trim((string) $lga);
        if ($district !== '' && strcasecmp($district, $lga) === 0) {
            return $district;
        }

        return implode(', ', array_filter([$district, $lga], fn ($p) => $p !== ''));
    }

    /** Trimmed text, the first title of a block's JSON list, or null when blank. */
    private function clean($value): ?string
    {
        if (is_string($value) && str_starts_with(ltrim($value), '[')) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            }
        }
        if (is_array($value)) {
            $value = collect($value)->map(fn ($v) => trim((string) $v))->first(fn ($v) => $v !== '');
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
