<?php

namespace App\Services\FileNumber;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Undo what commissioning a file wrote OUTSIDE the eight registers Master Delete
 * already purges, so no record keeps naming a file number that no longer exists.
 *
 * Commissioning a parcel update retires the parent (decommissioned_files, the
 * is_decommissioned flags), writes a PRA transaction, fills the new number into
 * the parent's PropID_Master row and stamps the source application. Master Delete
 * removed only the new number's own rows, so COM-2026-378 left RES-2009-1265
 * retired into a file that did not exist, still holding serial 378 out of the
 * reuse pool through pra / PropID_Master.
 *
 * Runs inside the caller's transaction. The new number's customer/entity staging
 * rows are deleted by the cascade itself, never handed back to the parent (as
 * instructed 2026-10-09); the grouping table is deliberately left alone.
 */
class MasterDeleteLineageCleanup
{
    private const FLAG_COLUMNS = [
        'decommissioned_at', 'decommissioned_by', 'decommissioning_reason',
        'decommissioning_date', 'successor_file_no',
    ];

    /**
     * @param  string[] $fileNumbers  every number the deleted file answered to
     * @return array<string,int>      rows touched, per table
     */
    public function purge(array $fileNumbers): array
    {
        $numbers = array_values(array_unique(array_filter(array_map(
            fn ($f) => strtoupper(trim((string) $f)),
            $fileNumbers
        ))));
        $counts = [];
        if (!$numbers) {
            return $counts;
        }

        foreach ($numbers as $number) {
            $this->unwindDecommissioning($number, $counts);
        }

        $db = DB::connection('sqlsrv');

        // PRA transactions written for the file (a Change of Purpose writes one).
        $counts['pra'] = $this->safely('pra', fn () => $db->table('pra')
            ->where(fn ($q) => $q->whereIn('mlsFNo', $numbers)->orWhereIn('fileno', $numbers))
            ->delete());

        $counts['PropID_Master'] = $this->safely('PropID_Master', fn () => $this->releasePropIds($numbers));

        $counts['related_file_number'] = $this->safely('related_file_number', fn () => $db->table('related_file_number')
            ->where(fn ($q) => $q->whereIn('file_number', $numbers)->orWhereIn('related_fileno', $numbers))
            ->delete());

        $counts['deprecated_records'] = $this->safely('deprecated_records', fn () => $db->table('deprecated_records')
            ->whereIn('file_number', $numbers)
            ->delete());

        $counts['file_commissioning_sheets'] = $this->safely('file_commissioning_sheets', fn () => $db->table('file_commissioning_sheets')
            ->whereIn('file_number', $numbers)
            ->delete());

        // The LAAS portal application commissioning opened for the file, with its
        // events. The applicant's portal account belongs to the person and stays.
        $counts['laas_applications'] = $this->safely('laas_applications', function () use ($db, $numbers) {
            $ids = $db->table('laas_applications')->whereIn('file_number', $numbers)->pluck('id')->all();
            if (!$ids) {
                return 0;
            }
            foreach (['laas_application_events', 'laas_documents', 'laas_stage_notifications'] as $child) {
                $db->table($child)->whereIn('laas_application_id', $ids)->delete();
            }
            return $db->table('laas_applications')->whereIn('id', $ids)->delete();
        });

        return array_filter($counts);
    }

    /**
     * The deleted file as a SUCCESSOR: the archive rows that retired a parent into
     * it, and the parent's flags. A parent retired only into this file comes back
     * to its previous state -- the retirement before this one if it had one
     * (RES-2009-1265 was already merged into RES-2026-3955), active otherwise.
     * A parent split into several files keeps its row with this number removed.
     * The deleted file as a PARENT: its own archive rows go with it.
     */
    private function unwindDecommissioning(string $number, array &$counts): void
    {
        $db = DB::connection('sqlsrv');

        $rows = $db->table('decommissioned_files')
            ->where('successor_file_no', $number)
            ->orWhere('successor_file_no', 'like', '%' . $number . '%')
            ->get();

        foreach ($rows as $row) {
            $successors = array_values(array_filter(array_map('trim', explode(',', (string) $row->successor_file_no))));
            $remaining = array_values(array_filter($successors, fn ($s) => strtoupper($s) !== $number));
            if (count($remaining) === count($successors)) {
                continue; // a LIKE hit on a longer number, e.g. CON-COM-2026-378
            }

            if ($remaining) {
                $db->table('decommissioned_files')->where('id', $row->id)
                    ->update(['successor_file_no' => implode(', ', $remaining), 'updated_at' => now()]);
                $counts['decommissioned_files'] = ($counts['decommissioned_files'] ?? 0) + 1;
                continue;
            }

            $db->table('decommissioned_files')->where('id', $row->id)->delete();
            $counts['decommissioned_files'] = ($counts['decommissioned_files'] ?? 0) + 1;

            $parent = trim((string) $row->file_no);
            if ($parent !== '') {
                $counts['parent_flags'] = ($counts['parent_flags'] ?? 0) + $this->restoreParent($parent, $number);
                $counts['change_of_purpose_applications'] = ($counts['change_of_purpose_applications'] ?? 0)
                    + $this->deleteApplication($parent, $number);
            }
        }

        $counts['decommissioned_files'] = ($counts['decommissioned_files'] ?? 0)
            + $db->table('decommissioned_files')->where('file_no', $number)->delete();
    }

    /** Point the parent's flags back at its previous retirement, or clear them. */
    private function restoreParent(string $parent, string $deleted): int
    {
        $db = DB::connection('sqlsrv');
        $previous = $db->table('decommissioned_files')
            ->where('file_no', $parent)
            ->where(fn ($q) => $q->whereNull('false_decommissioning')->orWhere('false_decommissioning', 0))
            ->orderByDesc('id')
            ->first();

        $touched = 0;
        foreach (['fileNumber' => 'mlsfNo', 'file_indexings' => 'file_number', 'mls_file_no' => 'full_file_number'] as $table => $column) {
            $schema = Schema::connection('sqlsrv');
            if (!$schema->hasColumn($table, 'is_decommissioned')) {
                continue;
            }
            $values = $previous
                ? [
                    'is_decommissioned' => 1,
                    'decommissioned_at' => $previous->decommissioning_date ?? $previous->created_at,
                    'decommissioned_by' => $previous->decommissioned_by,
                    'decommissioning_reason' => $previous->decommissioning_reason,
                    'decommissioning_date' => $previous->decommissioning_date ?? $previous->created_at,
                    'successor_file_no' => $previous->successor_file_no,
                ]
                : array_merge(['is_decommissioned' => 0], array_fill_keys(self::FLAG_COLUMNS, null));

            $values = array_filter(
                $values,
                fn ($c) => $schema->hasColumn($table, $c),
                ARRAY_FILTER_USE_KEY
            );

            // Only rows this file retired: a parent row already re-pointed elsewhere is
            // not this delete's to change.
            $touched += $db->table($table)
                ->where($column, $parent)
                ->where('successor_file_no', $deleted)
                ->update($values);
        }

        return $touched;
    }

    /** The application whose commissioning produced the deleted file, once nothing it made is left. */
    private function deleteApplication(string $parent, string $deleted): int
    {
        $db = DB::connection('sqlsrv');
        $apps = $db->table('change_of_purpose_applications')
            ->where('file_no', $parent)
            ->where('remarks', 'like', '%-> ' . $deleted . '%')
            ->get(['id', 'remarks']);

        $removed = 0;
        foreach ($apps as $app) {
            preg_match_all('/->\s*([A-Z0-9()\/ -]+?)\s+on\s/i', (string) $app->remarks, $m);
            $made = array_diff(array_map(fn ($f) => strtoupper(trim($f)), $m[1] ?? []), [$deleted]);
            $survivors = $made
                ? $db->table('fileNumber')->whereIn('mlsfNo', array_values($made))->exists()
                : false;
            if (!$survivors) {
                $removed += $db->table('change_of_purpose_applications')->where('id', $app->id)->delete();
            }
        }

        return $removed;
    }

    /**
     * The number's own PropID_Master row goes; a row it was only filled into (the
     * parent's, on a Change of Purpose) keeps its property id with the number cleared.
     */
    private function releasePropIds(array $numbers): int
    {
        $db = DB::connection('sqlsrv');
        $columns = ['primary_file_number', 'mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'temp_fileno'];
        $rows = $db->table('PropID_Master')
            ->where(function ($q) use ($columns, $numbers) {
                foreach ($columns as $column) {
                    $q->orWhereIn($column, $numbers);
                }
            })
            ->get(array_merge(['id'], $columns));

        $touched = 0;
        foreach ($rows as $row) {
            $owned = collect($columns)
                ->map(fn ($c) => strtoupper(trim((string) ($row->{$c} ?? ''))))
                ->filter()
                ->every(fn ($v) => in_array($v, $numbers, true) || in_array(preg_replace('/-REF-\d+$/', '', $v), $numbers, true));

            if ($owned) {
                $touched += $db->table('PropID_Master')->where('id', $row->id)->delete();
                continue;
            }

            $clear = [];
            foreach ($columns as $column) {
                if (in_array(strtoupper(trim((string) ($row->{$column} ?? ''))), $numbers, true)) {
                    $clear[$column] = null;
                }
            }
            if (array_key_exists('primary_file_number', $clear)) {
                // The primary is the row's name; hand it to the next identifier it holds.
                $clear['primary_file_number'] = collect($columns)
                    ->reject(fn ($c) => array_key_exists($c, $clear))
                    ->map(fn ($c) => trim((string) ($row->{$c} ?? '')))
                    ->first(fn ($v) => $v !== '');
            }
            if ($clear) {
                $clear['updated_at'] = now();
                $touched += $db->table('PropID_Master')->where('id', $row->id)->update($clear);
            }
        }

        return $touched;
    }

    private function safely(string $table, callable $work): int
    {
        try {
            return (int) $work();
        } catch (\Exception $e) {
            Log::warning("Could not purge {$table} during master delete", ['error' => $e->getMessage()]);
            return 0;
        }
    }
}
