<?php

namespace App\Models\InstrumentWorkflow;

use Illuminate\Database\Eloquent\Model;

/**
 * The roles allowed to perform one workflow action, set in Configurable Entries.
 * A row overrides config('instrument_workflow.roles') for that action; no row
 * means the config default applies.
 */
class WorkflowActionRole extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'instrument_workflow_action_roles';

    protected $fillable = ['action', 'roles', 'updated_by_name'];

    protected $casts = ['roles' => 'array'];
}

