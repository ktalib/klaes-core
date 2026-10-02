<?php

namespace App\Models\Cadastral;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A charted parcel.
 *
 * Charts are versioned rather than edited in place: newVersion() clones the row,
 * marks the old one superseded and keeps both, so what was charted when survives.
 *
 * Conversion files (CON-*) are not charted at all per the concept note; they are
 * recorded with charting_required = false and go straight to index-card
 * commissioning.
 */
class CadastralChart extends CadastralModel
{
    protected $table = 'cadastral_charts';

    protected $casts = [
        'supersedes_chart_id' => 'integer',
        'charting_required' => 'boolean',
        'is_current'        => 'boolean',
        'charted_on'        => 'date',
        'checked_on'        => 'date',
        'area_sqm'          => 'float',
        'version'           => 'integer',
    ];

    public const CATEGORY_DIRECT     = 'direct';
    public const CATEGORY_CONVERSION = 'conversion';

    public const STATUSES = ['Draft', 'Charted', 'Checked', 'Approved', 'Superseded'];

    public function coordinates(): HasMany
    {
        return $this->hasMany(CadastralChartCoordinate::class, 'cadastral_chart_id')
            ->orderBy('sort_order');
    }

    public function indexCards(): HasMany
    {
        return $this->hasMany(CadastralIndexCard::class, 'cadastral_chart_id');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_chart_id');
    }

    public function scopeCurrent(Builder $q): Builder
    {
        return $q->where('is_current', true);
    }

    public function isConversion(): bool
    {
        return $this->chart_category === self::CATEGORY_CONVERSION;
    }

    /**
     * The identity a conflict scan compares on. Two live charts sharing this are
     * the same piece of ground described twice.
     */
    public function identityKey(): ?string
    {
        $parts = array_filter([
            trim((string) $this->plot_no),
            trim((string) $this->block_no),
            trim((string) $this->layout_name),
        ], fn ($p) => $p !== '');

        return $parts === [] ? null : strtoupper(implode('|', $parts));
    }

    /**
     * The GIS screen this chart mirrors, as [label, url], or null.
     *
     * origin/origin_id point at a legacy gisCapture or surveyCadastral row by
     * reference (the module never writes those tables); this opens that row in
     * its own capture view rather than drawing a map here (plan Q6).
     */
    public function gisLink(): ?array
    {
        if (! $this->origin_id) {
            return null;
        }

        $route = match ($this->origin) {
            'gisCapture'      => ['GIS capture record', 'gis_record.view'],
            'surveyCadastral' => ['Survey cadastral record', 'survey_cadastral.edit'],
            default           => null,
        };

        if (! $route || ! \Illuminate\Support\Facades\Route::has($route[1])) {
            return null;
        }

        return [$route[0] . ' #' . $this->origin_id, route($route[1], $this->origin_id)];
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'Approved'   => 'active',
            'Checked'    => 'completed',
            'Superseded' => 'rejected',
            'Charted'    => 'review',
            default      => 'pending',
        };
    }
}
