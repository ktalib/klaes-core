<?php

namespace App\Models\Survey;

use Illuminate\Database\Eloquent\Model;

/**
 * Survey Department staff list (surveyors_list), loaded from Surveyor.xlsx
 * on 2026-10-22. Ministry staff, not the SURCON register (cadastral_surveyors).
 *
 * No soft deletes: staff who leave are deactivated (is_active = 0) so beacon
 * register lines that name them keep their link.
 */
class SurveyorListEntry extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'surveyors_list';
    protected $guarded = ['id'];

    protected $casts = [
        'date_of_birth'             => 'date',
        'date_of_first_appointment' => 'date',
        'is_active'                 => 'boolean',
    ];

    public const GENDERS = ['MALE', 'FEMALE'];

    /** First name, surname, other name: the order the source sheet used. */
    public function getFullNameAttribute(): string
    {
        return trim(preg_replace('/\s+/', ' ', "{$this->first_name} {$this->surname} {$this->other_name}"));
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }
}
