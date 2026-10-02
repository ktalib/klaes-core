<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Grouping extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'sqlsrv';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'grouping';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'awaiting_fileno',
        'mls_fileno',
        'mapping',
        'group',
        'batch_no',
        'mdc_batch_no',
        'sys_batch_no',
        'shelf_rack',
        'date',
        'created_by',
        'indexed_by',
        'indexing_mapping',
        'indexing_mls_fileno',
        'registry',
        'date_index',
        'year',
        'landuse',
        'tracking_id',
        'test_control',
        'updated_by',
        'deleted_by'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date' => 'date',
        'date_index' => 'date',
        'mapping' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [];

    /**
     * Boot method to automatically set created_by and updated_by
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (auth()->check()) {
                $model->created_by = auth()->user()->name ?? auth()->user()->id;
            }
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                $model->updated_by = auth()->user()->name ?? auth()->user()->id;
            }
        });

        static::deleting(function ($model) {
            if (auth()->check()) {
                $model->deleted_by = auth()->user()->name ?? auth()->user()->id;
                $model->save();
            }
        });
    }

    /**
     * Scope for mapping status
     */
    public function scopeMapped($query)
    {
        return $query->where('mapping', 1);
    }

    /**
     * Scope for unmapped records
     */
    public function scopeUnmapped($query)
    {
        return $query->where('mapping', 0);
    }

    /**
     * Scope for specific batch
     */
    public function scopeByBatch($query, $batchNo)
    {
        return $query->where('batch_no', $batchNo);
    }

    /**
     * Scope for specific shelf/rack
     */
    public function scopeByShelfRack($query, $shelfRack)
    {
        return $query->where('shelf_rack', $shelfRack);
    }

    /**
     * Scope for specific year
     */
    public function scopeByYear($query, $year)
    {
        return $query->where('year', $year);
    }

    /**
     * Scope for specific land use
     */
    public function scopeByLanduse($query, $landuse)
    {
        return $query->where('landuse', $landuse);
    }

    /**
     * Scope for current year
     */
    public function scopeCurrentYear($query)
    {
        return $query->where('year', date('Y'));
    }
}