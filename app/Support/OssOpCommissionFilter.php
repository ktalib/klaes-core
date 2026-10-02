<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;

/**
 * Hides file numbers commissioned through the Lands One Stop Shop from the MLS
 * File Number Generator list, its stat cards and the batch commissioning sheet —
 * one definition, so those three can no longer disagree about what is hidden.
 *
 * The test is `mls_file_no.system_sub_type` and nothing else. The OSS posts to
 * the same commissioning endpoint as the generator, so the two write otherwise
 * identical rows; origin is stamped at write time by
 * MlsFileNoController::resolveSystemSubType() and backfilled by the
 * 2026_08_07_120000 migration.
 *
 * `source` IN ('OP Resettlement','OP Direct Allocation') was the previous test
 * and is deliberately not used: the generator writes those same values for its
 * own OP allocations, so it hid files commissioned in MLS File Commissioning.
 */
class OssOpCommissionFilter
{
    /** Commissioned from a One Stop Shop entry point. */
    public const OSS = 'OSS';

    /** Commissioned in MLS File Commissioning. */
    public const MLS = 'MLS';

    /**
     * Raw `NOT EXISTS (...)` predicate for queries driven off fileNumber.
     *
     * @param string $fileNumberColumn qualified column holding the MLS number,
     *                                 e.g. "fn.mlsfNo" — must not be user input.
     */
    public static function notExistsSql(string $fileNumberColumn): string
    {
        return "NOT EXISTS (
                    SELECT 1 FROM mls_file_no ms
                    WHERE ms.full_file_number = {$fileNumberColumn}
                      AND ms.system_sub_type = '" . self::OSS . "'
                )";
    }

    /**
     * Constrain a query that already selects from mls_file_no to non-OSS rows.
     * Rows with no stamp are treated as MLS-commissioned and stay visible.
     */
    public static function applyExclusion(Builder $query, string $table = 'mls_file_no'): Builder
    {
        return $query->where(function ($q) use ($table) {
            $q->where("{$table}.system_sub_type", '<>', self::OSS)
                ->orWhereNull("{$table}.system_sub_type");
        });
    }
}
