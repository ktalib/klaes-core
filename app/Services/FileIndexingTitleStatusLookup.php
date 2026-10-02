<?php

namespace App\Services;

use App\Models\TitleStatusApplication;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Reads back the Title Status / Title Status Update selection already recorded for a
 * file, so the File Indexing UPDATE screen can re-tick it.
 *
 * The create form writes this selection through the shared title-status backend
 * (TitleStatusController::store), which splits it across two homes:
 *
 *   - Title Status Update types (Withdrawal, Cancellation, Litigation, Re-grant ...)
 *     and Change of Name become `title_status_applications` rows.
 *   - Parcel Update types (Subdivision, Merger, Change of Purpose, Extension,
 *     Separation) are routed by TitleStatusParcelRouter into their own
 *     *_applications tables as `status = 'hidden'` rows, and deliberately get NO
 *     title_status_applications row.
 *
 * Nothing on file_indexings records which boxes were ticked, so the edit screen had
 * no way to show them - it opened with every box clear even for a file that already
 * carried a status. Since the form makes the selection mandatory, that also meant a
 * plain "update the address" edit forced the operator to re-pick a status, and each
 * re-pick wrote ANOTHER application row. selectedTypesFor() is what lets the form
 * both restore the ticks and skip re-posting what is already on record.
 *
 * Stored type strings are not one vocabulary: the standalone module writes the
 * TitleStatusApplication constants ("Cancellation (RofO)", "Revoke (CofO)") while the
 * indexing form posts its own shorter option values ("Cancellation", "Revoke"), and
 * Re-grant / Closed / Resettlement are stored as their directional sub-kind
 * ("Re-granted From", "Closed To" ...). Both spellings are live in the data, so the
 * map below folds every variant back onto the value the form's input actually carries.
 */
class FileIndexingTitleStatusLookup
{
    /** Parcel-update option value => table holding its hidden rows. */
    private const PARCEL_TABLES = [
        'Subdivision'       => 'plot_subdivision_applications',
        'Merger'            => 'plot_merger_applications',
        'Change of Purpose' => 'change_of_purpose_applications',
        'Extension'         => 'plot_extension_applications',
        'Separation'        => 'plot_separation_applications',
    ];

    /**
     * Stored title_type => the value on the form input, for every type whose stored
     * spelling differs. Anything already equal to a form value passes through.
     */
    private const TYPE_ALIASES = [
        'Cancellation (RofO)' => 'Cancellation',
        'Revoke (CofO)'       => 'Revoke',
        'Revocation'          => 'Revoke',
        'Re-granted From'     => 'Re-grant',
        'Re-granted To'       => 'Re-grant',
        'Closed To'           => 'Closed',
        'Continued From'      => 'Closed',
        'Resettled From'      => 'Resettlement',
        'Resettled To'        => 'Resettlement',
        'Plot Extension'      => 'Extension',
        'File Extension'      => 'Extension',
    ];

    /** Values the form's Title Status Update radios carry (single-select). */
    private const UPDATE_VALUES = [
        'Withdrawal (Application)',
        'Withdrawal (Allocation)',
        'Cancellation',
        'Revoke',
        'Litigation',
        'Amendment/Reconsideration (Application/RofO/CofO)',
        'Surrender',
        'Re-grant',
        'Resettlement',
        'Closed',
    ];

    /** Values the form's Parcel Update checkboxes carry (multi-select). */
    private const PARCEL_VALUES = [
        'Subdivision',
        'Merger',
        'Extension',
        'Separation',
        'Change of Purpose',
        'Change of Name',
    ];

    /**
     * The selection to restore on the update screen.
     *
     * @return array{
     *     types:string[], update_type:?string, regrant_kind:string, closed_kind:string,
     *     resettlement_kind:string, see_fileno:string, reason:string, remark:string,
     *     recorded_types:string[]
     * }
     */
    public function forFileNumber(?string $fileNumber, ?string $tempFileNo = null): array
    {
        $empty = [
            'types'             => [],
            'update_type'       => null,
            'regrant_kind'      => '',
            'closed_kind'       => '',
            'resettlement_kind' => '',
            'see_fileno'        => '',
            'reason'            => '',
            'remark'            => '',
            'recorded_types'    => [],
        ];

        $candidates = $this->lookupKeys($fileNumber, $tempFileNo);
        if (empty($candidates)) {
            return $empty;
        }

        try {
            $selection = $empty;

            // --- Title Status Update (+ Change of Name) -----------------------
            // Newest last: a later row is the more recent statement of the file's
            // status, and the radios can only hold one of them.
            $rows = TitleStatusApplication::on('sqlsrv')
                ->select(['id', 'title_type', 'see_fileno', 'reason', 'remark', 'created_at'])
                ->where(function ($query) use ($candidates) {
                    $this->whereFileNumberIn($query, 'file_no', $candidates);
                })
                ->where(function ($query) {
                    $query->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();

            foreach ($rows as $row) {
                $stored = trim((string) $row->title_type);
                $value  = $this->toFormValue($stored);

                if ($value === null) {
                    // A type this form has no input for (e.g. the legacy
                    // "Withdrawn/Revorked/Canceled" bucket). Nothing to tick.
                    continue;
                }

                $selection['recorded_types'][] = $stored;

                if (in_array($value, self::UPDATE_VALUES, true)) {
                    $selection['update_type'] = $value;
                } elseif (!in_array($value, self::PARCEL_VALUES, true)) {
                    continue;
                }

                if (!in_array($value, $selection['types'], true)) {
                    $selection['types'][] = $value;
                }

                // Directional sub-kind, kept verbatim - it is what the hidden input
                // holds and what gets re-reported if the status is saved again.
                if (in_array($stored, ['Re-granted From', 'Re-granted To'], true)) {
                    $selection['regrant_kind'] = $stored;
                } elseif (in_array($stored, ['Closed To', 'Continued From'], true)) {
                    $selection['closed_kind'] = $stored;
                } elseif (in_array($stored, ['Resettled From', 'Resettled To'], true)) {
                    $selection['resettlement_kind'] = $stored;
                }

                $see = trim((string) ($row->see_fileno ?? ''));
                if ($see !== '') {
                    $selection['see_fileno'] = $see;
                }

                $reason = trim((string) ($row->reason ?? ''));
                if ($reason !== '') {
                    $selection['reason'] = $reason;
                }

                $remark = trim((string) ($row->remark ?? ''));
                if ($remark !== '') {
                    $selection['remark'] = $remark;
                }
            }

            // The radios are single-select, so only the newest update type survives;
            // drop any earlier one the loop above may have added.
            if ($selection['update_type'] !== null) {
                $updateType = $selection['update_type'];
                $selection['types'] = array_values(array_filter(
                    $selection['types'],
                    static function ($value) use ($updateType) {
                        return $value === $updateType
                            || !in_array($value, self::UPDATE_VALUES, true);
                    }
                ));
            }

            // --- Parcel Update ------------------------------------------------
            foreach (self::PARCEL_TABLES as $value => $table) {
                if (in_array($value, $selection['types'], true)) {
                    continue;
                }

                if ($this->parcelRowExists($table, $candidates)) {
                    $selection['types'][] = $value;
                    $selection['recorded_types'][] = $value;
                }
            }

            $selection['types'] = array_values(array_unique($selection['types']));
            $selection['recorded_types'] = array_values(array_unique($selection['recorded_types']));

            return $selection;
        } catch (\Throwable $e) {
            // The update screen must still render; an un-restored selection is a
            // visible annoyance, a 500 is not.
            Log::warning('FileIndexingTitleStatusLookup::forFileNumber - failed', [
                'file_number' => $fileNumber,
                'error' => $e->getMessage(),
            ]);

            return $empty;
        }
    }

    /**
     * File numbers to match on: the permanent number and, when the file is held under
     * a temporary number, its base form too. The title-status writer strips the "(T)"
     * suffix before saving, so the temp number itself is never the stored key.
     */
    private function lookupKeys(?string $fileNumber, ?string $tempFileNo): array
    {
        $keys = [];

        foreach ([$fileNumber, $tempFileNo] as $candidate) {
            $value = trim((string) $candidate);
            if ($value === '') {
                continue;
            }

            $value = trim((string) preg_replace('/\(\s*T\s*\)\s*$/i', '', $value));
            if ($value !== '') {
                $keys[] = $value;
            }
        }

        return array_values(array_unique($keys));
    }

    /** Case/whitespace-insensitive match, the way the rest of the module compares numbers. */
    private function whereFileNumberIn($query, string $column, array $candidates): void
    {
        foreach ($candidates as $i => $candidate) {
            $clause = "UPPER(LTRIM(RTRIM({$column}))) = UPPER(?)";
            $i === 0
                ? $query->whereRaw($clause, [$candidate])
                : $query->orWhereRaw($clause, [$candidate]);
        }
    }

    private function parcelRowExists(string $table, array $candidates): bool
    {
        try {
            return DB::connection('sqlsrv')->table($table)
                ->where(function ($query) use ($candidates) {
                    $this->whereFileNumberIn($query, 'file_no', $candidates);
                })
                ->exists();
        } catch (\Throwable $e) {
            Log::warning('FileIndexingTitleStatusLookup::parcelRowExists - failed', [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /** Stored title_type => the form input's value, or null when the form has no such option. */
    private function toFormValue(string $storedType): ?string
    {
        if ($storedType === '') {
            return null;
        }

        $value = self::TYPE_ALIASES[$storedType] ?? $storedType;

        if (in_array($value, self::UPDATE_VALUES, true) || in_array($value, self::PARCEL_VALUES, true)) {
            return $value;
        }

        return null;
    }
}
