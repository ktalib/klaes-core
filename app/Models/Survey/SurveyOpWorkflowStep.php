<?php

namespace App\Models\Survey;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyOpWorkflowStep extends SurveyModel
{
    protected $table = 'survey_op_workflow_steps';

    protected $casts = ['completed_at' => 'datetime'];

    public function opRecord(): BelongsTo
    {
        return $this->belongsTo(SurveyOpRecord::class, 'survey_op_record_id');
    }
}
