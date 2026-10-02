<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CadastralRackShelfLabel extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'cadastral_rack_shelf_labels';
    protected $primaryKey = 'id';

    protected $fillable = [
        'rack',
        'shelf',
        'full_label',
        'is_used',
        'reserved_by',
        'reserved_at',
        'counter',
        'assigned'
    ];

    protected $casts = [
        'is_used' => 'boolean',
        'reserved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Normalize counter reads to an integer, handling any residual DB::raw expressions.
     */
    public function getCounterAttribute($value)
    {
        if ($value instanceof \Illuminate\Database\Query\Expression) {
            // Expression may linger in-memory after DB::raw updates; treat as zero
            return 0;
        }

        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * Get the next available rack/shelf label for assignment
     */
    public static function getNextAvailableLabel()
    {
        return static::where(function ($query) {
                $query->where('is_used', false)
                    ->orWhereNull('is_used');
            })
            ->whereNull('assigned')
            ->orderBy('id')
            ->first();
    }

    /**
     * Get rack/shelf labels assigned to a specific batch
     */
    public static function getLabelsForBatch($batchId)
    {
        return static::where('assigned', $batchId)->get();
    }

    /**
     * Assign a label to a batch
     */
    public function assignToBatch($batchId)
    {
        return $this->update([
            'assigned' => $batchId,
            'is_used' => true,
        ]);
    }

    /**
     * Get the formatted full label (Rack/Shelf format)
     */
    public function getFullLabelAttribute()
    {
        if (!empty($this->attributes['full_label'])) {
            return trim($this->attributes['full_label']);
        }

        $rack = $this->attributes['rack'] ?? null;
        $shelf = $this->attributes['shelf'] ?? null;

        if ($rack !== null || $shelf !== null) {
            $rackPart = $rack !== null ? trim((string) $rack) : '';
            $shelfPart = $shelf !== null ? trim((string) $shelf) : '';
            return trim($rackPart . $shelfPart);
        }

        return null;
    }

    /**
     * Get available labels with pagination
     */
    public static function getAvailableLabels($limit = 10)
    {
        return static::where(function ($query) {
                $query->where('is_used', false)
                    ->orWhereNull('is_used');
            })
            ->whereNull('assigned')
            ->orderBy('rack')
            ->orderBy('shelf')
            ->limit($limit)
            ->get();
    }

    /**
     * Release a label from batch assignment
     */
    public function release()
    {
        return $this->update([
            'assigned' => null,
            'is_used' => false,
        ]);
    }
}
