<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\PhysicalRegistry;

class ShelfRackRange extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'shelf_rack_ranges';

    protected $fillable = [
        'registry_id',
        'rack',
        'shelf',
        'rack_shelf',
        'file_no',
        'serial_from',
        'serial_to',
        'serial_range',
        'source_file',
        'set_version',
        'source_sn',
        'shelf_label_id',
    ];

    protected $casts = [
        'registry_id' => 'integer',
        'shelf' => 'integer',
        'serial_from' => 'integer',
        'serial_to' => 'integer',
        'set_version' => 'integer',
        'source_sn' => 'integer',
        'shelf_label_id' => 'integer',
    ];

    public function registry()
    {
        return $this->belongsTo(PhysicalRegistry::class, 'registry_id');
    }

    /**
     * Rows from the newer "FileNo Combination_2_" workbooks. Prefer these where
     * a shelf is claimed by both sets.
     */
    public function scopeCurrentSet($query)
    {
        return $query->where('set_version', 2);
    }

    /**
     * Shelves that actually hold files (the workbooks also list empty shelves).
     */
    public function scopeAllocated($query)
    {
        return $query->whereNotNull('file_no');
    }

    /**
     * Shelf holding a given serial of a file-number series, e.g. ('RES-1981', 250).
     */
    public function scopeHoldingSerial($query, string $fileNo, int $serial)
    {
        return $query->where('file_no', $fileNo)
            ->where('serial_from', '<=', $serial)
            ->where('serial_to', '>=', $serial);
    }
}
