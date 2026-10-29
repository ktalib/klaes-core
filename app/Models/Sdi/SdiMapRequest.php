<?php

namespace App\Models\Sdi;

use App\Models\Survey\SurveyModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A request for a map, logged by Survey for an MDA (or another body), and
 * worked by the Production Unit: Pending -> In Progress -> Fulfilled (the map
 * file uploaded) or Rejected. Ported from ALAES's SdiMapRequest; the drawn area
 * is GeoJSON with its bounding box recomputed server-side.
 */
class SdiMapRequest extends SurveyModel
{
    protected $table = 'sdi_map_requests';

    protected $casts = [
        'mda_id'         => 'integer',
        'map_type_id'    => 'integer',
        'requested_by'   => 'integer',
        'actioned_by'    => 'integer',
        'received_date'  => 'date',
        'due_date'       => 'date',
        'started_at'     => 'datetime',
        'actioned_at'    => 'datetime',
        'output_formats' => 'array',
        'bbox_north'     => 'float',
        'bbox_south'     => 'float',
        'bbox_east'      => 'float',
        'bbox_west'      => 'float',
    ];

    public const PRIORITY_LABELS = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'];

    public const PENDING     = 'pending';
    public const IN_PROGRESS = 'in_progress';
    public const FULFILLED   = 'fulfilled';
    public const REJECTED    = 'rejected';

    public const STATUS_LABELS = [
        self::PENDING     => 'Pending',
        self::IN_PROGRESS => 'In Progress',
        self::FULFILLED   => 'Fulfilled',
        self::REJECTED    => 'Rejected',
    ];

    /** Formats the requester can ask for (Survey Plan Extraction's PDF / DWG / Shapefile, plus print). */
    public const FORMATS = ['pdf' => 'PDF', 'print' => 'Printed copy', 'dwg' => 'DWG', 'shapefile' => 'Shapefile'];

    /** Local disk, like Survey's field photos: an MDA's map has no land file to file it under in EDMS. */
    public const DISK = 'local';

    public function mda(): BelongsTo
    {
        return $this->belongsTo(SdiMda::class, 'mda_id');
    }

    public function mapType(): BelongsTo
    {
        return $this->belongsTo(SdiMapType::class, 'map_type_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function actionedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actioned_by');
    }

    public function scopeOpen($q)
    {
        return $q->whereIn('status', [self::PENDING, self::IN_PROGRESS]);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::PENDING, self::IN_PROGRESS], true);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status);
    }

    public function priorityLabel(): string
    {
        return self::PRIORITY_LABELS[$this->priority] ?? ucfirst((string) $this->priority);
    }

    public function requesterName(): string
    {
        return $this->mda?->displayName() ?? ($this->requester_other ?: '—');
    }

    public function formatsLabel(): string
    {
        return collect((array) $this->output_formats)->map(fn ($f) => self::FORMATS[$f] ?? $f)->implode(', ') ?: '—';
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->due_date && $this->due_date->isBefore(today());
    }

    public function hasGeometry(): bool
    {
        return ! empty($this->geometry_geojson);
    }

    public function boundaryPoints(): array
    {
        return $this->hasGeometry() ? self::boundaryPointsFromGeoJson(json_decode($this->geometry_geojson, true)) : [];
    }

    /** Exterior-ring vertices of a Polygon / MultiPolygon, closing vertex dropped (as ALAES). */
    public static function boundaryPointsFromGeoJson(mixed $geojson): array
    {
        if (! is_array($geojson)) return [];
        $geometry = $geojson['geometry'] ?? $geojson;
        if (! is_array($geometry)) return [];

        $polygons = match ($geometry['type'] ?? null) {
            'Polygon'      => [$geometry['coordinates'] ?? []],
            'MultiPolygon' => $geometry['coordinates'] ?? [],
            default        => [],
        };
        if (! is_array($polygons)) return [];

        $points = [];
        foreach ($polygons as $polygon) {
            $ring = is_array($polygon) ? ($polygon[0] ?? []) : [];
            if (! is_array($ring)) continue;

            $ringPoints = [];
            foreach ($ring as $c) {
                if (! is_array($c) || ! is_numeric($c[0] ?? null) || ! is_numeric($c[1] ?? null)) continue;
                $x = (float) $c[0];
                $y = (float) $c[1];
                if (is_finite($x) && is_finite($y) && $x >= -180 && $x <= 180 && $y >= -90 && $y <= 90) {
                    $ringPoints[] = ['x' => $x, 'y' => $y];
                }
            }
            if (count($ringPoints) > 1 && $ringPoints[0] === end($ringPoints)) array_pop($ringPoints);
            array_push($points, ...$ringPoints);
        }

        return $points;
    }

    /** N/S/E/W of the drawn area, or null when fewer than three vertices. */
    public static function boundsFromGeoJson(mixed $geojson): ?array
    {
        $points = self::boundaryPointsFromGeoJson($geojson);
        if (count($points) < 3) return null;

        $xs = array_column($points, 'x');
        $ys = array_column($points, 'y');

        return ['bbox_north' => max($ys), 'bbox_south' => min($ys), 'bbox_east' => max($xs), 'bbox_west' => min($xs)];
    }

    public function boundsSummary(): ?string
    {
        return $this->bbox_north === null ? null
            : sprintf('N %.5f, S %.5f, E %.5f, W %.5f', $this->bbox_north, $this->bbox_south, $this->bbox_east, $this->bbox_west);
    }
}
