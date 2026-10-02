<?php

namespace App\Models\Survey;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyCaseTree extends SurveyModel
{
    protected $table = 'survey_case_trees';

    protected $casts = [
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        parent::booted();

        // line_total is always quantity x unit_price; never trust the input.
        $recalc = function (self $m) {
            $m->line_total = (int) $m->quantity * (float) $m->unit_price;
        };
        static::creating($recalc);
        static::updating($recalc);
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(SurveyCompCase::class, 'survey_comp_case_id');
    }

    public function treeType(): BelongsTo
    {
        return $this->belongsTo(SurveyTreeType::class, 'survey_tree_type_id');
    }
}
