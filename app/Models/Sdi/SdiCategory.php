<?php

namespace App\Models\Sdi;

use App\Models\Survey\SurveyModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A thematic SDI category grouping MDAs (ported from ALAES; seven, seeded). */
class SdiCategory extends SurveyModel
{
    protected $table = 'sdi_categories';

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    public function mdas(): HasMany
    {
        return $this->hasMany(SdiMda::class, 'category_id');
    }
}
