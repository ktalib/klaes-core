<?php

namespace App\Models\Survey;

/**
 * A privately owned layout (GKN → Public Survey → Private Layout, user 2026-10-30):
 * an owner or developer's layout plan and the surveyor who prepared it, numbered
 * PVL-YYYY-0001. The plan document is kept on the private local disk (a private
 * layout has no land file to file it under in EDMS).
 */
class SurveyPrivateLayout extends SurveyModel
{
    protected $table = 'survey_private_layouts';

    protected $casts = [
        'plot_count'   => 'integer',
        'area_ha'      => 'decimal:4',
        'submitted_on' => 'date',
        'approved_on'  => 'date',
    ];

    public const STATUSES  = ['Submitted', 'Under Review', 'Approved', 'Rejected'];
    public const LAND_USES = ['Residential', 'Commercial', 'Industrial', 'Agricultural', 'Institutional', 'Mixed'];
    public const OTHER = 'Other';

    public const DISK = 'local';

    public function getLandUseLabelAttribute(): ?string
    {
        return $this->land_use === self::OTHER ? ($this->land_use_other ?: self::OTHER) : $this->land_use;
    }

    /** District, LGA, State: never the plot number. */
    public function getLocationAttribute(): string
    {
        return \App\Support\AddressBuilder::propertyLocation(\App\Support\AddressBuilder::fromPrefixed($this, 'prop_'));
    }
}
