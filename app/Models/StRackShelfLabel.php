<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StRackShelfLabel extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'st_rack_shelf_labels';
    protected $primaryKey = 'id';

    protected $fillable = [
        'rack',
        'shelf',
        'full_label',
        'is_used',
        'counter',
        'assigned',
        'status',
        'reserved_by',
        'reserved_at',
    ];

    protected $casts = [
        'is_used'     => 'boolean',
        'reserved_at' => 'datetime',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    public function getCounterAttribute($value)
    {
        if ($value instanceof \Illuminate\Database\Query\Expression) {
            return 0;
        }
        return is_numeric($value) ? (int) $value : 0;
    }

    public function getFullLabelAttribute()
    {
        if (!empty($this->attributes['full_label'])) {
            return trim($this->attributes['full_label']);
        }

        $rack  = $this->attributes['rack'] ?? null;
        $shelf = $this->attributes['shelf'] ?? null;

        if ($rack !== null || $shelf !== null) {
            return trim(($rack ?? '') . ($shelf ?? ''));
        }

        return null;
    }

    public static function getNextAvailableLabel()
    {
        return static::where('status', 'Available')
            ->where(function ($q) {
                $q->where('is_used', false)->orWhereNull('is_used');
            })
            ->orderBy('rack')
            ->orderByRaw("CAST(shelf AS INT)")
            ->first();
    }

    public function release()
    {
        return $this->update([
            'assigned' => null,
            'is_used'  => false,
            'status'   => 'Available',
            'counter'  => '0',
        ]);
    }
}
