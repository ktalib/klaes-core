<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlotExtension extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'plot_extensions';

    protected $fillable = [
        'original_file_no',
        'tracking_id',
        'file_name',
        'land_use',
        'purpose_id',
        'location',
        'lga',
        'district',
        'plot_no',
        'tp_no',
        'customer_type',
        'phone_no',
        'address',
        'is_indexed',
        'created_by',
        'is_deleted',
    ];

    protected $casts = [
        'is_indexed' => 'boolean',
        'is_deleted' => 'boolean',
    ];

    public function purpose()
    {
        return $this->belongsTo(Purpose::class, 'purpose_id');
    }

    /**
     * Scope to get only active (non-deleted) records
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
        });
    }
}
