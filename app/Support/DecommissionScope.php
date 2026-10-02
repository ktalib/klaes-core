<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * The single definition of "is this decommissioned_files row a real decommissioning?".
 *
 * decommissioned_files.false_decommissioning carries three values:
 *
 *   0 / NULL  a real decommissioning — merger, subdivision, change of purpose,
 *             parcel update, a title-status update raised as a decommission.
 *   1         a Title Status FLAG raised from File Indexing. The file is NOT
 *             decommissioned: it is live, in use, and has no successor. This is
 *             the ONLY value that means "not really decommissioned".
 *   2         an ST handover. The file was taken over by its Sectional Titling
 *             primary and IS decommissioned — it simply keeps its records and
 *             points at the ST primary through successor_file_no.
 *
 * So the rule is `false_decommissioning <> 1`, not `= 0`. Spelling that predicate
 * out by hand at each call site is what let the two buckets drift apart before:
 * readers written as `= 0 OR NULL` silently excluded ST handovers, while readers
 * with no predicate at all counted title-status flags as lineage. Every consumer
 * now shares this class so the definition can only be changed in one place.
 */
class DecommissionScope
{
    /** The value that means "flagged only, not actually decommissioned". */
    public const FALSE_DECOMMISSIONING = 1;

    /**
     * Restrict a decommissioned_files query to REAL decommissions (everything but
     * a title-status flag). Returns the query unchanged when the column does not
     * exist, so callers work against databases predating the 2026_06_19 migration.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $query
     * @param  string|null $alias  table alias to qualify the column with, when the
     *                             query joins decommissioned_files under one
     */
    public static function real($query, ?string $alias = null, string $connection = 'sqlsrv')
    {
        if (!Schema::connection($connection)->hasColumn('decommissioned_files', 'false_decommissioning')) {
            return $query;
        }

        $column = ($alias ? $alias . '.' : '') . 'false_decommissioning';

        return $query->where(function ($q) use ($column) {
            $q->where($column, '<>', self::FALSE_DECOMMISSIONING)
              ->orWhereNull($column);
        });
    }

    /**
     * The complement of real(): title-status flags only — the "False Decommissioning"
     * listing. Kept here so the two buckets are defined together and stay exhaustive.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $query
     */
    public static function flaggedOnly($query, ?string $alias = null)
    {
        $column = ($alias ? $alias . '.' : '') . 'false_decommissioning';

        return $query->where($column, self::FALSE_DECOMMISSIONING);
    }
}
