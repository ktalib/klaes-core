<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DcivGrouping extends Model
{
    use HasFactory;

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
    protected $table = 'dciv_grouping';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'dciv_awaiting_fileno',
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
        'dciv_fileno',
        'mapping',
        'group',
        'mdc_batch_no',
        'sys_batch_no',
        'registry_batch_no',
        'tracking_id',
        'test_control',
        'indexing_mapping',
        'shelf_rack',
        'indexing_dciv_fileno',
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
        'deleted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
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
}
