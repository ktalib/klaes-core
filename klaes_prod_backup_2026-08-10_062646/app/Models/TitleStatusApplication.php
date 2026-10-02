<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TitleStatusApplication extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'title_status_applications';

    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const TYPE_WITHDRAWAL   = 'Withdrawal (Application)';
    public const TYPE_CANCELLATION = 'Cancellation (RofO)';
    public const TYPE_REVOKE       = 'Revoke (CofO)';
    public const TYPE_LITIGATION   = 'Litigation';
    public const TYPE_AMENDMENT    = 'Amendment/Reconsideration (Application/RofO/CofO)';
    public const TYPE_SURRENDER    = 'Surrender';
    public const TYPE_REGRANT      = 'Re-grant';
    public const TYPE_RESETTLEMENT = 'Resettlement';
    public const TYPE_SUBDIVISION  = 'Subdivision';
    public const TYPE_MERGER       = 'Merger';
    public const TYPE_PURPOSE      = 'Change of Purpose';
    public const TYPE_EXTENSION    = 'Extension';
    public const TYPE_CHANGE_NAME  = 'Change of Name';
    public const TYPE_SEPARATION   = 'Separation';

    public const AUTHORITY_OPTIONS = [
        "Governor's Directive",
        "Ministerial Directive",
        "Court Order",
        "Administrative Decision",
        "Regulatory Notice",
        "Other",
    ];

    public const INITIATED_BY_OPTIONS = [
        'Ministry',
        'Allottee',
        'Applicant',
        'Court Order',
    ];

    /** Per-type options for the Initiated By dropdown. Single-option types lock the value. */
    public const INITIATED_BY_BY_TYPE = [
        self::TYPE_WITHDRAWAL   => ['Applicant'],
        self::TYPE_CANCELLATION => ['Ministry', 'Allottee'],
        self::TYPE_REVOKE       => ['Ministry', 'Allottee'],
        self::TYPE_LITIGATION   => ['Court Order'],
        self::TYPE_AMENDMENT    => ['Ministry', 'Allottee'],
        self::TYPE_SURRENDER    => ['Applicant'],
        self::TYPE_REGRANT      => ['Ministry'],
        self::TYPE_RESETTLEMENT => ['Ministry', 'Allottee'],
        self::TYPE_SUBDIVISION  => ['Applicant', 'Ministry'],
        self::TYPE_MERGER       => ['Applicant', 'Ministry'],
        self::TYPE_PURPOSE      => ['Applicant', 'Ministry'],
        self::TYPE_EXTENSION    => ['Applicant', 'Ministry'],
        self::TYPE_CHANGE_NAME  => ['Applicant', 'Ministry'],
        self::TYPE_SEPARATION   => ['Applicant', 'Ministry'],
    ];

    protected $fillable = [
        'url',
        'title_type',
        'source_table',
        'source_id',
        'file_no',
        'see_fileno',
        'file_title',
        'applicant_name',
        'plot_no',
        'house_no',
        'street_name',
        'district',
        'lga',
        'state',
        'location',
        'land_use',
        'cofo_number',
        'title_no',
        'reg_page',
        'reg_volume',
        'date_of_issue',
        'date_of_expiry',
        'authority',
        'authority_reference',
        'initiated_by',
        'reason',
        'remark',
        'status',
        'captured_by',
        'updated_by',
        'approved_by',
        'approved_at',
        'is_deleted',
        'deleted_by',
        'deleted_at',
    ];

    protected $casts = [
        'date_of_issue'  => 'date',
        'date_of_expiry' => 'date',
        'approved_at'    => 'datetime',
    ];
}
