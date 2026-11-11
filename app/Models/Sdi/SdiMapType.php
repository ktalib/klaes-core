<?php

namespace App\Models\Sdi;

use App\Models\Survey\SurveyModel;

/** A product the GIS unit makes (Thematic, Cadastral, Topographic, ...). Configurable; retired, never deleted. */
class SdiMapType extends SurveyModel
{
    protected $table = 'sdi_map_types';

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    public function requests(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SdiMapRequest::class, 'map_type_id');
    }

    /** The "Others (Specify)" choice on both map request forms (Survey Update 5c, user 2026-11-10). */
    public const OTHER = '__other__';

    /**
     * A form's map type choice as [map_type_id, map_type_other]: a listed type, or the typed name
     * unless it is a listed type typed again (then that type). Refuses anything else.
     */
    public static function resolveChoice(?string $choice, ?string $typed, bool $activeOnly = true): array
    {
        $choice = trim((string) $choice);
        if ($choice === self::OTHER) {
            $typed = trim(preg_replace('/\s+/u', ' ', (string) $typed));
            if ($typed === '') {
                throw \Illuminate\Validation\ValidationException::withMessages(['map_type_other' => 'Specify the type of map.']);
            }
            $match = static::query()->when($activeOnly, fn ($q) => $q->where('is_active', true))
                ->whereRaw('LOWER(LTRIM(RTRIM(name))) = ?', [mb_strtolower($typed)])->first();

            return $match ? [$match->id, null] : [null, $typed];
        }
        // ctype_digit first: the portal form is public, and SQL Server errors on a non-number here.
        if (! ctype_digit($choice) || ! static::query()->when($activeOnly, fn ($q) => $q->where('is_active', true))->whereKey($choice)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['map_type_id' => 'Choose the type of map needed, or Others.']);
        }

        return [(int) $choice, null];
    }

    public const BASEMAP_STREETS   = 'streets';
    public const BASEMAP_SATELLITE = 'satellite';
    public const BASEMAP_TERRAIN   = 'terrain';

    /** A land use map: the request form overlays File Indexing land use. */
    public function isLandUse(): bool
    {
        $name = strtolower((string) $this->name);

        return str_contains($name, 'land use') || str_contains($name, 'land-use') || str_contains($name, 'landuse');
    }

    /** Which basemap previews this type while the requester draws (as ALAES). */
    public function previewBasemap(): string
    {
        $name = strtolower(trim((string) $this->name));

        if (str_contains($name, 'land use') || str_contains($name, 'cadastral') || str_contains($name, 'layout')) {
            return self::BASEMAP_SATELLITE;
        }
        if (str_contains($name, 'topograph') || str_contains($name, 'contour') || str_contains($name, 'terrain')) {
            return self::BASEMAP_TERRAIN;
        }

        return self::BASEMAP_STREETS;
    }
}
