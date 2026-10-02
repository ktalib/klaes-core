<?php

namespace App\Models\Laas;

use Illuminate\Database\Eloquent\Model;

/**
 * An internal desk alert for a staff unit — spec step (h). Not visible to the
 * applicant; their side of the story lives in LaasApplicationEvent.
 */
class LaasStageNotification extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'laas_stage_notifications';

    protected $fillable = [
        'laas_application_id',
        'department',
        'stage',
        'title',
        'message',
        'is_read',
        'read_at',
        'read_by',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    public function application()
    {
        return $this->belongsTo(LaasApplication::class, 'laas_application_id');
    }
}
