<?php

namespace App\Models\InstrumentWorkflow;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only history of an application: every stage transition plus the
 * material non-transition actions (reprints, uploads, decisions).
 */
class InstrumentApplicationEvent extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'instrument_application_events';

    public const UPDATED_AT = null;

    protected $fillable = [
        'application_id',
        'event',
        'from_stage',
        'to_stage',
        'actor_id',
        'actor_name',
        'note',
        'meta',
        'created_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public function application()
    {
        return $this->belongsTo(InstrumentApplication::class, 'application_id');
    }
}

