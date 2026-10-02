<?php

namespace App\Models\Phs;

use Illuminate\Database\Eloquent\Model;

/**
 * Simple shared lookup list for PHS forms (job titles, departments, ...).
 * Values grow over time: when a user picks "Other" and types a new value on
 * the onboarding form, it is remembered here so it appears in the dropdown
 * the next time around.
 */
class PhsLookup extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'phs_lookups';

    const TYPE_JOB_TITLE  = 'job_title';
    const TYPE_DEPARTMENT = 'department';

    protected $fillable = ['type', 'name', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /** Active option names for a lookup type, alphabetically ordered. */
    public static function options(string $type): array
    {
        return static::query()
            ->where('type', $type)
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    /**
     * Find-or-create a lookup value (case-insensitive) and return the stored
     * name. Used to grow the list when a user supplies an "Other" value.
     * Returns null for empty input.
     */
    public static function remember(string $type, ?string $name): ?string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        $existing = static::query()
            ->where('type', $type)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing) {
            return $existing->name;
        }

        static::create(['type' => $type, 'name' => $name, 'is_active' => true]);

        return $name;
    }
}
