<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlotApplicationSize extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'plot_application_sizes';

    protected $fillable = [
        'application_id',
        'application_type',
        'plot_number',
        'source_file_no',
        'source_file_title',
        'plot_size',
        // The parcel's sides as the officer read them off the plan, "60 x 21 x 46".
        // plot_size (m²) stays the figure everything downstream calculates with.
        'dimensions',
        'type',
        // Where this SOURCE PLOT is, for a merger — the plots being merged are
        // separate parcels with separate file numbers, so each carries its own
        // location. The plot no itself lives in `plot_number` above, and a
        // subdivision/separation/extension fragment leaves all five null: those
        // hang off one mother plot whose location is on the application row.
        'house_no',
        'street_name',
        'district',
        'lga',
        'state',
    ];
}
