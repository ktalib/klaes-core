<?php

namespace App\Models\InstrumentWorkflow;

use Illuminate\Database\Eloquent\Model;

/** A configurable ministry check of the Instrument Registration Workflow. See WorkflowPipeline. */
class WorkflowStep extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'instrument_workflow_steps';

    protected $fillable = ['step_key', 'label', 'department', 'sort_order', 'is_enabled', 'updated_by_name'];

    protected $casts = [
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];
}

