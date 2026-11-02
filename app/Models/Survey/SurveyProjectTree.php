<?php

namespace App\Models\Survey;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One economic-tree line of a Monetary Compensation project (user, 2026-11-02): a tree of the
 * Kano schedule in one Category, its stands and rate per stand; amount = stands x rate.
 */
class SurveyProjectTree extends SurveyModel
{
    protected $table = 'survey_project_trees';

    protected $casts = [
        'survey_project_id'   => 'integer',
        'survey_tree_type_id' => 'integer',
        'stands'              => 'integer',
        'rate'                => 'decimal:2',
        'amount'              => 'decimal:2',
        'sort_order'          => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SurveyProject::class, 'survey_project_id');
    }

    /** "Mango (Mature)". */
    public function getLabelAttribute(): string
    {
        return $this->tree_name . ($this->maturity ? ' (' . $this->maturity . ')' : '');
    }
}
