<?php

namespace App\Models\CadastralDept;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * The Cadastral Verification Report Sheet of a Verification case (client DOCX,
 * 2026-10-15). The choice lists are the sheet's own tick boxes, in its order.
 */
class CadastralVerificationReport extends Model
{
    /** Section 1, filled from the FMC and the registry. */
    public const GENERAL = [
        'application_date', 'allottee_name', 'application_type', 'plot_no',
        'layout_no', 'district_location', 'fmc_commission_date',
    ];

    /** Everything the form saves, in sheet order. */
    public const COLUMNS = [
        'application_date', 'allottee_name', 'application_type', 'plot_no', 'layout_no', 'district_location', 'fmc_commission_date',
        'original_holder_name', 'original_holder_ref', 'original_holder_date',
        'current_holder_name', 'current_holder_ref', 'current_holder_date', 'current_transaction_nature',
        'caveat_status', 'caveat_ref', 'mortgage_status', 'mortgage_ref', 'release_status', 'release_ref',
        'dciv_status', 'dciv_officer', 'dciv_genuineness', 'dciv_remarks',
        'title_status', 'title_ref', 'parcel_update_status', 'survey_plan_status', 'charting_status',
        'verdict', 'remarks',
    ];

    /** Section 2, nature of the current holder's transaction; anything else is "Other: …". */
    public const NATURES = ['Assignment', 'Gift', 'Mortgage'];

    public const ENCUMBRANCE = ['Clear', 'Encumbered'];
    public const RELEASE = ['Not Applicable', 'Executed'];

    public const DCIV_STATUS = ['Completed', 'Pending', 'Not Required'];
    public const GENUINENESS = ['Verified', 'Suspected', 'Forged'];

    public const TITLE_STATUS = ['C of O', 'RofO', 'OP', 'Pending', 'None'];
    public const PARCEL_UPDATE = ['Completed', 'In Progress', 'Not Started'];
    public const SURVEY_PLAN = ['Approved', 'Pending', 'Rejected', 'Not Available'];
    public const CHARTING = ['Matches UTM Sheet', 'Discrepancy Found'];

    public const VERDICTS = [
        'cleared' => ['VERIFIED & CLEARED', 'File is genuine, unencumbered, and ready for next stage'],
        'conditions' => ['VERIFIED WITH CONDITIONS', 'File is genuine but requires minor corrections/additional documents'],
        'rejected' => ['NOT VERIFIED / REJECTED', 'File has discrepancies, encumbrances, or failed DCIV check'],
    ];

    /** Section 7: who signs, by desk. */
    public const SIGN_OFF = [
        'ad_information' => 'Asst. Director Cadastral Information',
        'ad_report' => 'Asst. Director Cadastral Report',
        'deputy_director_cadastral' => 'Deputy Director Cadastral',
        'director_cadastral' => 'Director Cadastral',
    ];

    protected $connection = 'sqlsrv';
    protected $table = 'cadastral_verification_reports';

    protected $guarded = ['id'];

    protected $casts = [
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

    public function isComplete(): bool
    {
        return $this->completed_at !== null;
    }
}
