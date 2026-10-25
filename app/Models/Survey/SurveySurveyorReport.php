<?php

namespace App\Models\Survey;

use App\Models\Cadastral\CadastralSurveyJob;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Surveyor's Report, the Survey Department form (docs/SURVEYOR_s_report-1.docx),
 * attached to an Instruction to Survey. Draft until submitted; a submitted report
 * is locked and moves Survey's own I to S to "Report Submitted".
 */
class SurveySurveyorReport extends SurveyModel
{
    protected $table = 'survey_surveyor_reports';

    protected $casts = [
        'survey_its_id'           => 'integer',
        'cadastral_survey_job_id' => 'integer',
        'boundaries_pointed_on'   => 'date',
        'commenced_on'            => 'date',
        'completed_on'            => 'date',
        'report_date'             => 'date',
        'submitted_at'            => 'datetime',
        'distance_km'             => 'decimal:2',
        'on_layout_plan'          => 'boolean',
        'corners_demarcated'      => 'boolean',
        'encroachment'            => 'boolean',
        'developed'               => 'boolean',
        'previous_kn_coincides'   => 'boolean',
        'field_jacket_checklist'  => 'array',
    ];

    public const DRAFT     = 'Draft';
    public const SUBMITTED = 'Submitted';

    public const TRANSPORT = ['applicant' => 'Applicant', 'ministry' => 'Ministry'];

    /** "In the field jacket, the following field records are numbered and filed in that order." */
    public const FIELD_RECORDS = [
        'surveyors_report'     => "Surveyor's report",
        'instruction'          => 'Instruction to survey',
        'application_form'     => 'Application form (Form 1)',
        'site_plan'            => 'Site plan',
        'traverse_diagram'     => 'Traverse diagram',
        'field_book'           => 'Field book',
        'traverse_computation' => 'Traverse computation sheets',
        'surveyor_computation' => "Surveyor's computation sheets",
        'surveyors_plan'       => "Surveyor's plan",
    ];

    /** The road reservation clause, fixed text on the form. */
    public const ROAD_RESERVATIONS = 'The following reservations from the centre lines of the road are maintained: all state roads and federal roads within the Kano outer ring road 100 feet; federal roads outside the ring road and the Kano outer ring road 150 feet; all other minor roads and donkey tracks 50 feet; railways 50 feet.';

    public function its(): BelongsTo
    {
        return $this->belongsTo(SurveyIts::class, 'survey_its_id');
    }

    public function cadastralJob(): BelongsTo
    {
        return $this->belongsTo(CadastralSurveyJob::class, 'cadastral_survey_job_id');
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::SUBMITTED;
    }
}
