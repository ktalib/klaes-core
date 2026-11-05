<?php

namespace App\Models\Survey;

use Illuminate\Database\Eloquent\Model;

/** A notice in a surveyor's portal. The staff bell's table points at staff users, so the portal has its own. */
class SurveyorPortalNotification extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'surveyor_portal_notifications';
    protected $guarded = ['id'];
    protected $casts = ['read_at' => 'datetime'];
}
