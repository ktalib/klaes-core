<?php

namespace App\Models\Survey;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A CO (Compensation) file, numbered PRJ-YYYY-0001. One CO file holds many
 * projects (user transcript, 2026-10-25). Existing paper CO files are keyed in
 * with their own number (source = captured).
 */
class SurveyCoFile extends SurveyModel
{
    protected $table = 'survey_co_files';

    protected $casts = ['commission_date' => 'date'];

    public const STATUSES = ['Draft', 'Commissioned', 'Active', 'Closed'];

    public const SOURCE_COMMISSIONED = 'commissioned';
    public const SOURCE_CAPTURED     = 'captured';

    public function projects(): HasMany
    {
        return $this->hasMany(SurveyProject::class, 'survey_co_file_id');
    }

    /** Next PRJ-YYYY-0001. Captured numbers share the column, so only this year's series counts. */
    public static function nextNumber(): string
    {
        return static::nextRef('co_file_no', 'PRJ', 4);
    }
}
