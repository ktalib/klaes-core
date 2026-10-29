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
