<?php

namespace App\Models\Survey;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One beacon on a layout plan — together these rows are the LPKN
 * "List of Coordinates", and the legs field observations are computed from.
 */
class SurveyLpknCoordinate extends SurveyModel
{
    protected $table = 'survey_lpkn_coordinates';

    protected $casts = [
        'northing'  => 'decimal:3',
        'easting'   => 'decimal:3',
        'elevation' => 'decimal:3',
        'distance'  => 'decimal:3',
    ];

    public function layout(): BelongsTo
    {
        return $this->belongsTo(SurveyLpkn::class, 'survey_lpkn_id');
    }
}
