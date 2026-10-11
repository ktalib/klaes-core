<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * The allottee's response to a First Serve (Notice of Contravention of Land
 * Use), keyed in from the Ministry's response form. One per notice.
 */
class SpaNoticeResponse extends Model
{
    protected $connection = 'sqlsrv';
    protected $table      = 'spa_notice_responses';

    /** Section C options, in the form's order, with the form's wording. */
    public const OPTIONS = [
        'formalize'     => ['Option 1: Intent to Formalize / Comply',
            'I acknowledge the contravention and wish to formalize my title / regularize the current land use as per Ministry guidelines. I am ready to pay the necessary fees and submit required documents.'],
        'change_of_use' => ['Option 2: Request for Change of Land Use',
            'I acknowledge the contravention but wish to apply for a formal Change of Land Use from the originally approved use to the current use. I understand this is subject to approval and payment of applicable conversion fees.'],
        'appeal'        => ['Option 3: Explanation / Appeal',
            'I wish to provide an explanation or appeal the notice based on the following grounds (please attach supporting documents):'],
        'surrender'     => ['Option 4: Voluntary Surrender',
            'I am unable to comply with the approved land use or formalize the title. I hereby indicate my willingness to voluntarily surrender the property to the Ministry for appropriate action.'],
        'other'         => ['Option 5: Other (Please Specify):', ''],
    ];

    public const DECISIONS = ['noted' => 'Noted', 'referred' => 'Referred to', 'closed' => 'File Closed'];

    public const ATTACHMENTS = [
        'original_notice' => 'Copy of the Original Notice of Contravention',
        'title_document'  => 'Copy of Certificate of Occupancy (C of O) / Offer of Allocation / Receipt of Payment',
        'survey_plan'     => 'Approved Survey Plan',
        'payment'         => 'Evidence of Payment for any recent transactions',
        'other_docs'      => 'Any other supporting documents (e.g., building plans, photos, etc.)',
    ];

    protected $fillable = [
        'spa_notice_id', 'spa_application_id', 'file_number',
        'reference_no', 'original_notice_date', 'response_date',
        'allottee_name', 'allottee_address', 'phone', 'email', 'representative_name',
        'plot_number', 'location', 'approved_land_use', 'current_land_use',
        'response_option', 'appeal_grounds', 'other_details',
        'declaration_signed', 'allottee_signed_date',
        'received_by', 'receiver_designation', 'date_received', 'time_received',
        'action_remarks', 'director_decision', 'referred_to', 'director_date',
        'attachments', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'original_notice_date' => 'date',
        'response_date'        => 'date',
        'allottee_signed_date' => 'date',
        'date_received'        => 'date',
        'director_date'        => 'date',
        'declaration_signed'   => 'boolean',
        'attachments'          => 'array',
    ];

    private static ?bool $supported = null;

    /** False until the migration has run, so the Notice page keeps working before it. */
    public static function supported(): bool
    {
        return self::$supported ??= Schema::connection('sqlsrv')->hasTable('spa_notice_responses');
    }

    public function notice()
    {
        return $this->belongsTo(SpaNotice::class, 'spa_notice_id');
    }

    public function optionLabel(): string
    {
        return self::OPTIONS[$this->response_option][0] ?? (string) $this->response_option;
    }
}
