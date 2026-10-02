<?php

namespace App\Models;

use App\Services\GenderNormalizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * The `genders` lookup that backs every gender dropdown (see the
 * <x-gender-select> component).
 *
 * Forms submit the NAME, not the id: the consuming columns
 * (file_indexings.gender, mls_file_no.gender, st_file_numbers.gender, ...) are
 * varchars validated against GenderNormalizer::CANON, so an id would break every
 * existing row and every gender report.
 */
class Gender extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'genders';

    protected $fillable = [
        'name',
        'code',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    /** Cache key for the option list; cleared on every write. */
    private const CACHE_KEY = 'genders.options';

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * The values to offer in a dropdown, in display order.
     *
     * Falls back to GenderNormalizer::CANON when the table is missing or empty, so
     * a form never renders an empty gender list on an un-migrated / un-seeded
     * environment. The fallback is the same four values the seeder writes.
     *
     * @return array<int, string>
     */
    public static function options(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            try {
                $names = static::query()
                    ->active()
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->pluck('name')
                    ->all();
            } catch (Throwable $e) {
                return GenderNormalizer::CANON;
            }

            return $names ?: GenderNormalizer::CANON;
        });
    }

    public static function flushOptionsCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::flushOptionsCache());
        static::deleted(fn () => static::flushOptionsCache());
    }
}
