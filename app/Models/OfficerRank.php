<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Officer Rank (Seniority) lookup — backs the rank dropdown on the user
 * create/edit and profile forms, and supplies seniority weights for File
 * Search Request prioritisation. See config/file_request_priority.php for the
 * legacy config-based hierarchy that this table was seeded from.
 */
class OfficerRank extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'officer_ranks';

    protected $fillable = [
        'name',
        'weight',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'weight'     => 'integer',
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
    ];

    /**
     * Rank names for the dropdown, most senior first. Falls back to the config
     * options if the table is empty (e.g. before the seed migration runs).
     */
    public static function options(): array
    {
        try {
            $names = static::query()
                ->where('is_active', true)
                ->orderByDesc('weight')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->pluck('name')
                ->all();
        } catch (\Throwable $e) {
            $names = [];
        }

        return $names ?: (array) config('file_request_priority.options', []);
    }

    /**
     * Exact (case-insensitive) seniority weight for a saved rank, or null if the
     * rank isn't in the table. Used as a fallback by FileSearchRequest::priorityFor().
     */
    public static function weightFor(?string $name): ?int
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        $row = static::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        return $row ? (int) $row->weight : null;
    }

    /**
     * Find (case-insensitive) or create a rank by name. New ranks default to
     * weight 0 (lowest seniority) and sort after the existing ones.
     */
    public static function findOrCreateByName(string $name): self
    {
        $name = trim($name);

        $existing = static::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing) {
            return $existing;
        }

        return static::create([
            'name'       => $name,
            'weight'     => 0,
            'sort_order' => (int) static::max('sort_order') + 1,
            'is_active'  => true,
        ]);
    }

    /**
     * Resolve the rank submitted from a create/edit/profile form. Handles the
     * "Other (specify)" option by adding the typed value to the lookup table.
     * Returns the canonical rank name to store on users.rank, or null.
     */
    public static function resolveSubmittedRank(?string $rank, ?string $other): ?string
    {
        $rank = trim((string) $rank);

        if ($rank === self::OTHER_VALUE) {
            $other = trim((string) $other);
            if ($other === '') {
                return null;
            }

            return static::findOrCreateByName($other)->name;
        }

        return $rank !== '' ? $rank : null;
    }

    /** Sentinel value emitted by the dropdown's "Other (specify)" option. */
    public const OTHER_VALUE = '__other__';
}
