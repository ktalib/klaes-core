<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Shared lookup list for "Allocation Source" forms: which institution a
 * request came from, and who its correspondence is addressed to. Grows over
 * time — picking "Other (Specify)" and typing a new value remembers it here
 * so it appears in the dropdown next time.
 */
class AllocationSourceLookup extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'allocation_source_lookups';

    const TYPE_INSTITUTION_GOVERNMENT  = 'institution_government';
    const TYPE_INSTITUTION_OTHER       = 'institution_other';
    const TYPE_ADDRESSED_TO_GOVERNMENT = 'addressed_to_government';
    const TYPE_ADDRESSED_TO_OTHER      = 'addressed_to_other';

    protected $fillable = ['type', 'name', 'sort_order', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /** Active option names for a lookup type, in their configured order. */
    public static function options(string $type): array
    {
        return static::query()
            ->where('type', $type)
            ->where('is_active', true)
            ->orderBy('sort_order')
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

        $nextSortOrder = ((int) static::query()->where('type', $type)->max('sort_order')) + 10;

        static::create([
            'type'       => $type,
            'name'       => $name,
            'sort_order' => $nextSortOrder,
            'is_active'  => true,
        ]);

        return $name;
    }
}
