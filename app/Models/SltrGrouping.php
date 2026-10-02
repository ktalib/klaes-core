<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Support\SltrDigitRank;

class SltrGrouping extends Model
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
    protected $table = 'sltr_grouping';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'sltr_awaiting_fileno',
        'digit_rank',
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
        'sltr_fileno',
        'mapping',
        'group',
        'mdc_batch_no',
        'sys_batch_no',
        'registry_batch_no',
        'tracking_id',
        'test_control',
        'indexing_mapping',
        'shelf_rack',
        'indexing_sltr_fileno',
        'year_batch_no',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date' => 'date',
        'date_index' => 'date',
        'year' => 'integer',
        'number' => 'integer',
        'mapping' => 'integer',
        'test_control' => 'integer',
        'indexing_mapping' => 'integer',
        'digit_rank' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * Bootstrap the model and its traits.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->digit_rank = SltrDigitRank::fromFileNumber($model->sltr_awaiting_fileno);

            if (auth()->check()) {
                $model->created_by = auth()->user()->name ?? auth()->user()->id;
            }
        });

        static::updating(function ($model) {
            $model->digit_rank = SltrDigitRank::fromFileNumber($model->sltr_awaiting_fileno);

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
