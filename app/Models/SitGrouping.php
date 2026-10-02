<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SitGrouping extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'sqlsrv';
    protected $table = 'sit_grouping';

    protected $fillable = [
        'sit_awaiting_fileno',
        'date',
        'created_by',
        'indexed_by',
        'date_index',
        'year',
        'landuse',
        'updated_by',
        'deleted_by',
        'number',
        'registry',
        'sit_fileno',
        'mapping',
        'group',
        'mdc_batch_no',
        'sys_batch_no',
        'registry_batch_no',
        'tracking_id',
        'test_control',
        'indexing_mapping',
        'shelf_rack',
        'indexing_sit_fileno',
        'year_batch_no'
    ];

    protected $casts = [
        'date' => 'date',
        'date_index' => 'date',
        'mapping' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

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
}
