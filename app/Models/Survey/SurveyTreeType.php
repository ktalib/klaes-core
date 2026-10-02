<?php

namespace App\Models\Survey;

use Illuminate\Database\Eloquent\Relations\HasMany;

/** Valuation catalogue for monetary schemes. */
class SurveyTreeType extends SurveyModel
{
    protected $table = 'survey_tree_types';

    protected $casts = ['default_unit_price' => 'decimal:2'];

    public function caseTrees(): HasMany
    {
        return $this->hasMany(SurveyCaseTree::class, 'survey_tree_type_id');
    }
}
