<?php

namespace App\Models\Sdi;

use App\Models\Survey\SurveyModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Kano State ministry, department or agency that can ask the Survey
 * Department's GIS unit for a map. The custodian is the Ministry of Land and
 * Physical Planning. Retired with is_active, never deleted.
 */
class SdiMda extends SurveyModel
{
    protected $table = 'sdi_mdas';

    protected $casts = [
        'category_id'  => 'integer',
        'is_custodian' => 'boolean',
        'is_active'    => 'boolean',
        'sort_order'   => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(SdiCategory::class, 'category_id');
    }

    public function mapRequests(): HasMany
    {
        return $this->hasMany(SdiMapRequest::class, 'mda_id');
    }

    public function displayName(): string
    {
        return $this->name . ($this->abbreviation ? " ({$this->abbreviation})" : '');
    }
}
