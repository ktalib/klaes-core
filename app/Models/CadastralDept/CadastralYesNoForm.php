<?php

namespace App\Models\CadastralDept;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * The Yes/No form ("Report on Application – Cadastral Department") of a case,
 * filled at AD Cad Report with Land 12. Copied from the old module's
 * questionnaire on CadastralReport (clone per module); same columns and values.
 */
class CadastralYesNoForm extends Model
{
    /** The questionnaire columns, in form order. */
    public const COLUMNS = [
        'lkn_no', 'sltr_no', 'govt_item_no', 'sit_no',
        'q1_plan_sufficient',
        'q2_ground_open', 'q2_overlapping_title',
        'q3_beaconed', 'q3_tracing_no', 'q3_deposition_plan_no',
        'q3_unapproved_town_plan_no', 'q3_layout_no', 'q3_separate_survey',
        'q4_town_plan', 'q4_town_plan_no', 'q4_shape_agrees', 'q4_purpose', 'q4_area_for_purpose',
        'q5_previous_title', 'q5_details',
        'q6_railway', 'q7_trunk_road',
        'area_applied_ha',
        // Q8's dimensions, "46.13 m x 49.52 m; 20.68 m x 10 m" (migration 2026_10_28_100000).
        'area_dimensions',
    ];

    /** The seven headline questions; all answered = form complete. */
    public const QUESTIONS = [
        'q1_plan_sufficient' => "1. Is the applicant's Plan sufficient to identify the Plot?",
        'q2_ground_open' => '2. Is the ground open correctly?',
        'q3_beaconed' => '3. Is the Plot completely beaconed?',
        'q4_town_plan' => '4. Does it lie on an area covered by a Town Plan?',
        'q5_previous_title' => '5. Has the Plot been previously held or applied for under statutory title?',
        'q6_railway' => '6. Does a Railway/Siding run alongside/through the Plot?',
        'q7_trunk_road' => '7. Is the Plot alongside a Federal/State Trunk Road?',
    ];

    /** Q4(c). Anything else is the officer's "other purpose". */
    public const PURPOSES = ['Residential', 'Commercial', 'Industrial', 'Agricultural'];

    /** The form prints "1 hectares = 2.47 acres"; the print uses the same factor. */
    public const ACRES_PER_HECTARE_AS_PRINTED = 2.47;

    protected $connection = 'sqlsrv';
    protected $table = 'cadastral_yes_no_forms';

    protected $guarded = ['id'];

    protected $casts = [
        'area_applied_ha' => 'float',
        'completed_at' => 'datetime',
    ];

    public function case()
    {
        return $this->belongsTo(CadastralCase::class, 'cadastral_case_id');
    }

    public function saver()
    {
        return $this->belongsTo(User::class, 'saved_by');
    }

    /** The headline questions not yet answered. */
    public function unanswered(): array
    {
        return array_values(array_filter(
            array_keys(self::QUESTIONS),
            fn ($c) => !in_array($this->{$c}, ['Yes', 'No'], true)
        ));
    }

    public function isComplete(): bool
    {
        return $this->completed_at !== null;
    }
}
