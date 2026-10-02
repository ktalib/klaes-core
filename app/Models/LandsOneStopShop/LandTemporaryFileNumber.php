<?php

namespace App\Models\LandsOneStopShop;

use Illuminate\Database\Eloquent\Model;

class LandTemporaryFileNumber extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'land_temporary_file_numbers';

    protected $primaryKey = 'id';

    public $timestamps = true;

    protected $fillable = [
        'applicant_name',
        'file_no',
        'plot_no',
        'district',
        'lga',
        'phone',
        'address',
        'remarks',
        'status',
        'recommendation_generated_at',
        'captured_by',
        'updated_by',
    ];

    protected $casts = [
        'recommendation_generated_at' => 'datetime',
        'created_at'                  => 'datetime',
        'updated_at'                  => 'datetime',
    ];
}
