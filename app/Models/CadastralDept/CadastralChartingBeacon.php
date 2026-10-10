<?php

namespace App\Models\CadastralDept;

use Illuminate\Database\Eloquent\Model;

/** One coordinate row of a charting card (UTM Zone 32N, Minna datum). */
class CadastralChartingBeacon extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'cadastral_charting_beacons';

    protected $guarded = ['id'];

    protected $casts = ['easting' => 'float', 'northing' => 'float'];
}
