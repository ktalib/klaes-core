<?php

namespace App\Models\Survey;

use Illuminate\Database\Eloquent\Model;

/**
 * A reference beacon entered with a survey plan (CR 11112026-2, item 4): its number, picked from the
 * beacon lists or typed, and its X / Y as written on the plan. Used later to check the plan on the map.
 */
class SurveyPlanReferenceBeacon extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'survey_plan_reference_beacons';

    protected $guarded = ['id'];

    protected $casts = ['sort' => 'integer'];
}
