<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MissingFile extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'missing_files';

    protected $fillable = [
        'file_number',
        'file_title',
        'property_location',
        'tracking_id',
        'archive_registry',
        'rack_primary',
        'rack_secondary',
        'shelf_number',
        'full_label',
        'shelf_location',
        'reported_by',
        'reported_by_name',
        'remarks',
        'status',
        'source',
        'request_no',
        'found_at',
        'found_by',
        'found_by_name',
    ];

    protected $casts = [
        'found_at'   => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Status constants
    const STATUS_MISSING   = 'Missing';
    const STATUS_SEARCHING = 'Searching';
    const STATUS_FOUND     = 'Found';
}
